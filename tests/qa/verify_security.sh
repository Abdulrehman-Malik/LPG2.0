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

echo; echo "RESULT: $PASS passed, $FAIL failed"
[ "$FAIL" = 0 ]
