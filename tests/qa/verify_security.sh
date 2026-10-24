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

echo; echo "RESULT: $PASS passed, $FAIL failed"
[ "$FAIL" = 0 ]
