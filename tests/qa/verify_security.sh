#!/usr/bin/env bash
# Security hardening checks (backlog BL-002 onwards) against the disposable QA database and the
# dev server on $QA_BASE_URL. Run reset_db.sh first.
set -u
source "$(dirname "$0")/env.sh"
T="$QA_TMP/security"; mkdir -p "$T"
PASS=0; FAIL=0
ok(){ echo "PASS  $1"; PASS=$((PASS+1)); }
bad(){ echo "FAIL  $1 :: $2"; FAIL=$((FAIL+1)); }
q(){ local r="" i; for i in 1 2 3 4; do r=$($M -e "$1" | tr -d '\r'); [ -n "$r" ] && break; done; printf '%s' "$r"; }
login(){ # $1 cookie file, $2 user, $3 password -> prints final URL
  rm -f "$1"
  local tok; tok=$(curl -s -c "$1" -b "$1" $B/login | grep -oE 'name="csrf_test_name" value="[^"]+"' | sed -E 's/.*value="([^"]+)"/\1/')
  curl -s -o "$T/login_out.html" -w '%{url_effective}' -L -c "$1" -b "$1" --data-urlencode "login=$2" --data-urlencode "password=$3" --data-urlencode "csrf_test_name=$tok" $B/login
}
csrf(){ grep -oE 'csrf_cookie_name\s+\S+' "$1" | awk '{print $2}'; }
post(){ local c=$1 path=$2; shift 2; curl -s -o "$T/post_out.html" -w '%{http_code}' -b "$c" -c "$c" --data-urlencode "csrf_test_name=$(csrf "$c")" "$@" "$B/$path"; }
qa_clear_throttle

MAIN=$(q "SELECT id FROM locations WHERE code='MAIN'")
ADMIN_ROLE=$(q "SELECT id FROM roles WHERE code='ADMIN'")
MANAGER_ROLE=$(q "SELECT id FROM roles WHERE code='MANAGER'")
CASHIER_ROLE=$(q "SELECT id FROM roles WHERE code='CASHIER'")
ADMIN_ID=$(q "SELECT id FROM users WHERE username='admin'")
MGR_ID=$(q "SELECT id FROM users WHERE username='qa_scoped_manager'")

echo "--- BL-002 role grants"
# Give the standard Manager role user management for this check only.
q "INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT $MANAGER_ROLE,id FROM permissions WHERE code='USER_MANAGE'"
MG="$T/mgr.txt"; login "$MG" qa_scoped_manager admin123 | grep -q dashboard && ok "manager with USER_MANAGE signs in" || bad "manager login" "no dashboard"
curl -s -b "$MG" "$B/users" > "$T/users.html"
grep -q "value=\"$ADMIN_ROLE\"[^>]*>ADMIN" "$T/users.html" && bad "role list" "ADMIN offered to a manager" || ok "ADMIN role is not offered in the manager's role list"
grep -q "value=\"$CASHIER_ROLE\"" "$T/users.html" && ok "CASHIER role is offered" || bad "role list" "CASHIER missing"

post "$MG" users/save --data-urlencode "role_id=$ADMIN_ROLE" --data-urlencode "full_name=QA Escalated" --data-urlencode "username=qa_escalated" --data-urlencode "password=Secret-12345" --data-urlencode "is_active=1" >/dev/null
[ "$(q "SELECT COUNT(*) FROM users WHERE username='qa_escalated'")" = 0 ] && ok "manager cannot create an ADMIN user" || bad "grant ADMIN" "user created"

post "$MG" users/save --data-urlencode "id=$MGR_ID" --data-urlencode "role_id=$ADMIN_ROLE" --data-urlencode "full_name=QA Scoped Manager" --data-urlencode "username=qa_scoped_manager" --data-urlencode "is_active=1" >/dev/null
[ "$(q "SELECT role_id FROM users WHERE id=$MGR_ID")" = "$MANAGER_ROLE" ] && ok "manager cannot make themselves ADMIN" || bad "self escalation" "role changed"

code=$(post "$MG" users/save --data-urlencode "id=$ADMIN_ID" --data-urlencode "role_id=$CASHIER_ROLE" --data-urlencode "full_name=System Administrator" --data-urlencode "username=admin" --data-urlencode "password=Hijacked-123" --data-urlencode "is_active=1")
[ "$code" = 403 ] && [ "$(q "SELECT role_id FROM users WHERE id=$ADMIN_ID")" = "$ADMIN_ROLE" ] && ok "manager cannot change the System Administrator account (403)" || bad "edit sysadmin" "http $code"

post "$MG" users/save --data-urlencode "role_id=$CASHIER_ROLE" --data-urlencode "full_name=QA New Cashier" --data-urlencode "username=qa_new_cashier" --data-urlencode "password=Secret-12345" --data-urlencode "is_active=1" >/dev/null
[ "$(q "SELECT role_id FROM users WHERE username='qa_new_cashier'")" = "$CASHIER_ROLE" ] && ok "manager can create a CASHIER" || bad "create cashier" "not created"

AD="$T/admin.txt"; login "$AD" admin admin123 >/dev/null
post "$AD" users/save --data-urlencode "id=$(q "SELECT id FROM users WHERE username='qa_new_cashier'")" --data-urlencode "role_id=$ADMIN_ROLE" --data-urlencode "full_name=QA New Cashier" --data-urlencode "username=qa_new_cashier" --data-urlencode "is_active=1" >/dev/null
[ "$(q "SELECT role_id FROM users WHERE username='qa_new_cashier'")" = "$ADMIN_ROLE" ] && ok "System Administrator can grant ADMIN" || bad "sysadmin grant" "role unchanged"
q "DELETE rp FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE rp.role_id=$MANAGER_ROLE AND p.code='USER_MANAGE'"

echo "--- BL-003 default passwords"
[ "$(q "SELECT must_change_password FROM users WHERE username='pak'")" = 1 ] && ok "migration flags the seeded 'pak' account (default password)" || bad "pak flag" "not flagged"
ADMIN_HASH=$(q "SELECT password_hash FROM users WHERE username='admin'")
q "UPDATE users SET must_change_password=1 WHERE username='admin'"
qa_clear_throttle
AF="$T/forced.txt"; url=$(login "$AF" admin admin123)
echo "$url" | grep -q "account/password" && ok "flagged account lands on Change Password after sign-in" || bad "forced redirect" "$url"
curl -s -o /dev/null -w '%{redirect_url}' -b "$AF" "$B/dashboard" | grep -q "account/password" && ok "every other page redirects to Change Password" || bad "page gate" "dashboard reachable"
api=$(curl -s -H 'Content-Type: application/json' -d '{"username":"admin","password":"admin123"}' "$B/api/v1/staff/auth/login")
echo "$api" | grep -q PASSWORD_CHANGE_REQUIRED && ok "staff API login refused until the password is changed" || bad "api refusal" "$api"
post "$AF" account/password --data-urlencode current_password=admin123 --data-urlencode new_password=admin123 --data-urlencode confirm_password=admin123 >/dev/null
[ "$(q "SELECT must_change_password FROM users WHERE username='admin'")" = 1 ] && ok "a known default password is refused as the new password" || bad "default refused" "accepted"
post "$AF" account/password --data-urlencode current_password=admin123 --data-urlencode new_password=short1 --data-urlencode confirm_password=short1 >/dev/null
[ "$(q "SELECT must_change_password FROM users WHERE username='admin'")" = 1 ] && ok "a password below the minimum length is refused" || bad "length refused" "accepted"
post "$AF" account/password --data-urlencode current_password=wrong-current --data-urlencode new_password=QaChanged-Pass-2026 --data-urlencode confirm_password=QaChanged-Pass-2026 >/dev/null
[ "$(q "SELECT must_change_password FROM users WHERE username='admin'")" = 1 ] && ok "a wrong current password is refused" || bad "current check" "accepted"
post "$AF" account/password --data-urlencode current_password=admin123 --data-urlencode new_password=QaChanged-Pass-2026 --data-urlencode confirm_password=QaChanged-Pass-2026 >/dev/null
[ "$(q "SELECT must_change_password FROM users WHERE username='admin'")" = 0 ] && ok "a valid new password clears the flag" || bad "change" "flag still set"
curl -s -o /dev/null -w '%{http_code}' -b "$AF" "$B/dashboard" | grep -q 200 && ok "dashboard reachable after the change" || bad "after change" "dashboard blocked"
[ "$(q "SELECT COUNT(*) FROM audit_logs WHERE action='PASSWORD_CHANGED' AND entity_id=$ADMIN_ID")" -ge 1 ] && ok "password change is audit-logged" || bad "audit" "no row"
q "UPDATE users SET password_hash='$ADMIN_HASH', must_change_password=0 WHERE username='admin'"

echo "--- BL-004 throttling and password length"
qa_clear_throttle
for i in 1 2 3 4 5; do curl -s -o /dev/null -H 'Content-Type: application/json' -d '{"username":"admin","password":"wrong-pass"}' "$B/api/v1/staff/auth/login"; done
hdr=$(curl -s -D - -o "$T/api6.json" -H 'Content-Type: application/json' -d '{"username":"admin","password":"admin123"}' "$B/api/v1/staff/auth/login")
echo "$hdr" | grep -q "^HTTP/[0-9.]* 429" && grep -q TOO_MANY_ATTEMPTS "$T/api6.json" && ok "6th staff API login after 5 failures is 429, even with the right password" || bad "staff api throttle" "$(head -1 <<<"$hdr")"
echo "$hdr" | grep -qi "^Retry-After: 900" && ok "429 carries Retry-After" || bad "retry-after" "header missing"
code=$(curl -s -o /dev/null -w '%{http_code}' -H 'Content-Type: application/json' -d '{"username":"qa_scoped_manager","password":"admin123"}' "$B/api/v1/staff/auth/login")
[ "$code" = 200 ] && ok "another login name is not affected" || bad "other login" "http $code"
for i in 1 2 3 4 5; do curl -s -o /dev/null -H 'Content-Type: application/json' -d '{"username":"qa_no_such_customer","password":"x"}' "$B/api/v1/customer/auth/login"; done
code=$(curl -s -o /dev/null -w '%{http_code}' -H 'Content-Type: application/json' -d '{"username":"qa_no_such_customer","password":"x"}' "$B/api/v1/customer/auth/login")
[ "$code" = 429 ] && ok "customer API login is throttled" || bad "customer throttle" "http $code"
for i in $(seq 1 20); do curl -s -o /dev/null -H 'Content-Type: application/json' -d "{\"refresh_token\":\"$(printf '%064d' $i)\"}" "$B/api/v1/staff/auth/refresh"; done
code=$(curl -s -o /dev/null -w '%{http_code}' -H 'Content-Type: application/json' -d '{"refresh_token":"abc"}' "$B/api/v1/staff/auth/refresh")
[ "$code" = 429 ] && ok "token refresh is throttled after 20 invalid tokens from one IP" || bad "refresh throttle" "http $code"
for i in $(seq 1 10); do curl -s -o /dev/null -H 'Content-Type: application/json' -d '{"shop_id":0}' "$B/api/v1/customer/auth/signup"; done
code=$(curl -s -o /dev/null -w '%{http_code}' -H 'Content-Type: application/json' -d '{"shop_id":0}' "$B/api/v1/customer/auth/signup")
[ "$code" = 429 ] && ok "customer signup is limited per IP" || bad "signup throttle" "http $code"
qa_clear_throttle
AD="$T/admin.txt"; login "$AD" admin admin123 >/dev/null
post "$AD" users/save --data-urlencode "role_id=$CASHIER_ROLE" --data-urlencode "full_name=QA Short Pw" --data-urlencode "username=qa_short_pw" --data-urlencode "password=short1" --data-urlencode "is_active=1" >/dev/null
[ "$(q "SELECT COUNT(*) FROM users WHERE username='qa_short_pw'")" = 0 ] && ok "web user save rejects a password below the minimum length" || bad "web min length" "user created"
post "$AD" users/save --data-urlencode "role_id=$CASHIER_ROLE" --data-urlencode "full_name=QA Long Pw" --data-urlencode "username=qa_long_pw" --data-urlencode "password=Secret12345" --data-urlencode "is_active=1" >/dev/null
[ "$(q "SELECT COUNT(*) FROM users WHERE username='qa_long_pw'")" = 1 ] && ok "web user save accepts a long enough password" || bad "web min length ok" "not created"
qa_clear_throttle

echo "--- BL-005 transport and headers"
h=$(curl -s -D - -o /dev/null "$B/login")
for hd in "X-Frame-Options: SAMEORIGIN" "X-Content-Type-Options: nosniff" "Referrer-Policy: same-origin"; do
  echo "$h" | grep -qi "^$hd" && ok "response carries $hd" || bad "header" "$hd missing"
done
sec=$(cd "$QA_ROOT" && env 'cookie.secure=true' "$QA_PHP" spark config:check Cookie 2>&1 | grep -A1 '"secure"' | tail -1)
echo "$sec" | grep -q "bool(true)" && ok "cookie.secure=true in env turns on Secure cookies" || bad "cookie.secure env" "$sec"
BK="$T/backup.txt"; login "$BK" admin admin123 >/dev/null
curl -s -o /dev/null -w '%{http_code}' -b "$BK" "$B/database-backup" | grep -q 200 && ok "active System Administrator opens Database Backup" || bad "backup open" "not 200"
q "UPDATE users SET is_active=0 WHERE username='admin'"
code=$(curl -s -o /dev/null -w '%{http_code}' -b "$BK" "$B/database-backup")
q "UPDATE users SET is_active=1 WHERE username='admin'"
[ "$code" = 302 ] && ok "a disabled System Administrator's existing session cannot reach Database Backup" || bad "disabled backup" "http $code"
code=$(curl -s -o /dev/null -w '%{http_code}' "$B/database-backup/download?file=x")
[ "$code" = 302 ] && ok "anonymous backup download is redirected to sign-in" || bad "anon backup" "http $code"

echo; echo "RESULT: $PASS passed, $FAIL failed"
[ "$FAIL" = 0 ]
