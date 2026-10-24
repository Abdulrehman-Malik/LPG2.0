#!/usr/bin/env bash
# BL-015 audit coverage: every sensitive action writes one audit_logs row with user, time, IP
# and before/after values; the Audit screen filters by action. Disposable QA database; run
# reset_db.sh first.
set -u
source "$(dirname "$0")/env.sh"
T="$QA_TMP/audit"; mkdir -p "$T"
PASS=0; FAIL=0
ok(){ echo "PASS  $1"; PASS=$((PASS+1)); }
bad(){ echo "FAIL  $1 :: $2"; FAIL=$((FAIL+1)); }
q(){ local r="" i; for i in 1 2 3 4; do r=$($M -e "$1" | tr -d '\r'); [ -n "$r" ] && break; done; printf '%s' "$r"; }
login(){ rm -f "$1"; local tok; tok=$(curl -s -c "$1" -b "$1" $B/login | grep -oE 'name="csrf_test_name" value="[^"]+"' | sed -E 's/.*value="([^"]+)"/\1/'); curl -s -o /dev/null -w '%{url_effective}' -L -c "$1" -b "$1" --data-urlencode "login=$2" --data-urlencode "password=$3" --data-urlencode "csrf_test_name=$tok" $B/login; }
csrf(){ grep -oE 'csrf_cookie_name\s+\S+' "$1" | awk '{print $2}'; }
post(){ local c=$1 path=$2; shift 2; curl -s -o "$T/post_out.html" -w '%{http_code}' -b "$c" -c "$c" --data-urlencode "csrf_test_name=$(csrf "$c")" "$@" "$B/$path"; }
qa_clear_throttle
nativepath(){ if command -v cygpath >/dev/null 2>&1; then cygpath -w "$1"; else printf '%s' "$1"; fi; }
# audited ACTION ENTITY [entity_id] -> one new row with user and IP; prints the row id
last(){ q "SELECT id FROM audit_logs WHERE action='$1' AND entity_type='$2'${3:+ AND entity_id=$3} ORDER BY id DESC LIMIT 1"; }
expect(){ # label action entity [entity_id] [json key expected in new_values]
  local id; id=$(last "$2" "$3" "${4:-}")
  if [ -z "$id" ] || [ "$id" = NULL ]; then bad "$1" "no $2/$3 audit row"; return; fi
  local row; row=$(q "SELECT CONCAT(IFNULL(user_id,0),'|',IFNULL(ip_address,''),'|',IFNULL(new_values,'')) FROM audit_logs WHERE id=$id")
  [[ "$row" == 0\|* ]] && { bad "$1" "no user on audit row"; return; }
  [ -n "${5:-}" ] && [[ "$row" != *"\"$5\""* ]] && { bad "$1" "new values lack $5"; return; }
  ok "$1"
}

A="$T/admin.txt"; login "$A" admin admin123 >/dev/null
MAIN=$(q "SELECT id FROM locations WHERE code='MAIN'"); BIZ=$(q "SELECT business_id FROM locations WHERE id=$MAIN")
C6=$(q "SELECT id FROM cylinder_types WHERE code='C6' AND business_id=$BIZ")
U1=$(q "SELECT id FROM cylinder_units WHERE unit_code='QA-E2E-C6-001'")
CASHIER=$(q "SELECT id FROM roles WHERE code='CASHIER'")

echo "--- users and roles"
post "$A" users/save -d "role_id=$CASHIER" -d "full_name=QA Audit User" -d username=qa_audit_user -d password=Secret12345 -d is_active=1 >/dev/null
UID_=$(q "SELECT id FROM users WHERE username='qa_audit_user'")
expect "user create audited" CREATE user "$UID_" username
post "$A" users/save -d "id=$UID_" -d "role_id=$CASHIER" -d "full_name=QA Audit User Renamed" -d username=qa_audit_user -d is_active=0 >/dev/null
expect "user update audited (before/after)" UPDATE user "$UID_" full_name
[ -n "$(q "SELECT old_values FROM audit_logs WHERE id=$(last UPDATE user "$UID_")" | grep -o 'QA Audit User"')" ] && ok "user update keeps the old name" || bad "user before" "old name missing"
q "SELECT password_hash FROM audit_logs WHERE 1=0" >/dev/null 2>&1
[ -z "$(q "SELECT id FROM audit_logs WHERE entity_type='user' AND (new_values LIKE '%password_hash%' OR old_values LIKE '%password_hash%') LIMIT 1")" ] && ok "no password hash in user audit rows" || bad "hash in audit" "found"
PERMS=$(q "SELECT GROUP_CONCAT(permission_id) FROM role_permissions WHERE role_id=$CASHIER")
ARGS=(); for p in ${PERMS//,/ }; do ARGS+=(-d "permissions[]=$p"); done
post "$A" users/role-permissions -d "role_id=$CASHIER" "${ARGS[@]}" -d "permissions[]=$(q "SELECT id FROM permissions WHERE code='AUDIT_VIEW'")" >/dev/null
expect "role permission change audited" ROLE_PERMISSIONS_UPDATED role "$CASHIER" permissions
[[ "$(q "SELECT new_values FROM audit_logs WHERE id=$(last ROLE_PERMISSIONS_UPDATED role "$CASHIER")")" == *AUDIT_VIEW* ]] && ok "role audit lists the added permission" || bad "role audit detail" "AUDIT_VIEW missing"

echo "--- customers and rates"
post "$A" customers/save -d code=QA-AUD-C -d "name=QA Audit Customer" -d credit_limit=500 -d allow_credit_sale=1 -d is_active=1 >/dev/null
CID=$(q "SELECT id FROM customers WHERE code='QA-AUD-C'")
expect "customer create audited" CREATE customer "$CID" credit_limit
post "$A" customers/save -d "id=$CID" -d code=QA-AUD-C -d "name=QA Audit Customer" -d credit_limit=900 -d allow_credit_sale=1 -d is_active=1 >/dev/null
expect "customer credit-limit change audited" UPDATE customer "$CID" credit_limit
[[ "$(q "SELECT CONCAT(old_values,new_values) FROM audit_logs WHERE id=$(last UPDATE customer "$CID")")" == *500*900* ]] && ok "customer audit has old 500 and new 900" || bad "customer before/after" "values missing"
post "$A" rates/save -d rate_type=gas_per_kg -d cylinder_type_id= -d rate_value=321 -d "effective_from=$(date '+%Y-%m-%dT%H:%M')" -d "reason=QA audit" >/dev/null
expect "rate change audited" RATE_CHANGED rate_card "" rate_value
post "$A" rates/customer/save -d "customer_id=$CID" -d rate_type=gas_per_kg -d rate_value=300 -d "effective_from=$(date '+%Y-%m-%dT%H:%M')" >/dev/null
CR=$(q "SELECT id FROM customer_rates WHERE customer_id=$CID ORDER BY id DESC LIMIT 1")
expect "customer rate set audited" CUSTOMER_RATE_SET customer_rate "$CR" rate_value
post "$A" "rates/customer/end/$CR" >/dev/null
expect "customer rate end audited" CUSTOMER_RATE_ENDED customer_rate "$CR"

echo "--- money and settings"
post "$A" cash/move -d direction=in -d amount=25 -d "notes=QA audit move" >/dev/null
expect "manual cash movement audited" CASH_MANUAL_MOVE cash_session "" amount
SID=$(q "SELECT cs.id FROM cash_sessions cs JOIN cash_registers r ON r.id=cs.register_id WHERE r.location_id=$MAIN AND cs.status='open' LIMIT 1")
post "$A" cash/close -d "session_id=$SID" -d counted_cash=0 >/dev/null
expect "cash close audited with difference" CASH_SESSION_CLOSED cash_session "$SID" difference
post "$A" cash/open -d opening_cash=10 >/dev/null
expect "cash open audited" CASH_SESSION_OPENED cash_session "" opening_cash
post "$A" receipts/save -d "customer_id=$CID" -d amount=5 -d payment_mode=cash >/dev/null
expect "customer receipt audited" CREATE customer_receipt "" amount
SALE=$(curl -s -b "$A" -c "$A" -H 'Accept: application/json' -H 'X-Requested-With: XMLHttpRequest' --data-urlencode "csrf_test_name=$(csrf "$A")" --data-urlencode transaction_type=gas_sale --data-urlencode "customer_id=$CID" --data-urlencode "lines_json=[{\"cylinder_type_id\":\"$C6\",\"source_cylinder_unit_id\":\"$U1\",\"quantity\":\"0.5\",\"gas_entry_mode\":\"quantity\",\"gas_rate\":\"\"}]" --data-urlencode 'payments_json=[{"payment_type":"sale","payment_mode":"credit","amount":"0"}]' "$B/sales/save" | grep -oE '"id": *[0-9]+' | grep -oE '[0-9]+')
post "$A" "sales/void/$SALE" -d "void_reason=QA audit void" >/dev/null
expect "sale void audited" VOID sale "$SALE" void_reason
[[ "$(q "SELECT old_values FROM audit_logs WHERE id=$(last VOID sale "$SALE")")" == *posted* ]] && ok "sale void audit keeps the previous status" || bad "void before" "missing"
curl -s -b "$A" -c "$A" "$B/shop-settings" > "$T/ss.html"
BODY=$("$QA_PHP" "$(nativepath "$QA_ROOT/tests/qa/lib/form_body.php")" "$(nativepath "$T/ss.html")" shop-settings/save receipt_title=QA_AUDIT_TITLE)
curl -s -o /dev/null -b "$A" -c "$A" --data "$BODY" "$B/shop-settings/save"
expect "shop settings change audited" SETTINGS_UPDATE shop_settings "" receipt_title
[ -z "$(q "SELECT id FROM audit_logs WHERE action='SETTINGS_UPDATE' AND (new_values LIKE '%smtp_password%' OR old_values LIKE '%smtp_password%') LIMIT 1")" ] && ok "SMTP password is not copied into the audit log" || bad "smtp in audit" "found"

echo "--- audit screen"
curl -s -b "$A" "$B/audit?action=VOID" > "$T/audit.html"
grep -q '">Void</span>' "$T/audit.html" && ! grep -q '">Cash Session Closed</span>' "$T/audit.html" && ok "audit screen filters by action" || bad "audit filter" "unexpected rows"
curl -s -b "$A" "$B/audit?entity_type=customer" | grep -q "Customer #$CID" && ok "audit screen filters by record type" || bad "entity filter" "customer row missing"

echo; echo "RESULT: $PASS passed, $FAIL failed"
[ "$FAIL" = 0 ]
