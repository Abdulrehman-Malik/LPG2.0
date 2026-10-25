#!/usr/bin/env bash
# BL-007 tenant isolation harness. Builds a second tenant (business ISOB with two shops and real
# transactions), then signs in as a NON-system-administrator owner of the first business and:
#   1. calls every GET route from `php spark routes` with tenant B's IDs and query parameters,
#   2. calls every staff/customer API route with B's IDs (and X-Shop-Id: B),
#   3. POSTs to every mutating web and API route with B's IDs in the path and the body,
# and fails when a response shows B's marker text, when any of B's rows changed, or when a row
# of tenant A now references B. Disposable QA database only; rebuilds it first.
set -u
DIR="$(cd "$(dirname "$0")" && pwd)"
source "$DIR/env.sh"
T="$QA_TMP/isolation"; mkdir -p "$T"
PASS=0; FAIL=0
ok(){ echo "PASS  $1"; PASS=$((PASS+1)); }
bad(){ echo "FAIL  $1 :: $2"; FAIL=$((FAIL+1)); }
q(){ local r="" i; for i in 1 2 3 4; do r=$($M -e "$1" | tr -d '\r'); [ -n "$r" ] && break; done; printf '%s' "$r"; }
w(){ $M -e "$1"; }  # writes run exactly once (q retries statements that return nothing)
login(){ rm -f "$1"; local tok; tok=$(curl -s -c "$1" -b "$1" $B/login | grep -oE 'name="csrf_test_name" value="[^"]+"' | sed -E 's/.*value="([^"]+)"/\1/'); curl -s -o /dev/null -w '%{url_effective}' -L -c "$1" -b "$1" --data-urlencode "login=$2" --data-urlencode "password=$3" --data-urlencode "csrf_test_name=$tok" $B/login; }
csrf(){ grep -oE 'csrf_cookie_name\s+\S+' "$1" | awk '{print $2}'; }
post(){ local c=$1 path=$2; shift 2; curl -s -o "$T/post_out.html" -w '%{http_code}' -b "$c" -c "$c" --data-urlencode "csrf_test_name=$(csrf "$c")" "$@" "$B/$path"; }
d(){ date '+%Y-%m-%d %H:%M:%S'; }
MARK="ISOB Marker"

bash "$DIR/reset_db.sh" >/dev/null 2>&1 || { echo "FAIL  reset_db.sh"; exit 1; }
qa_clear_throttle

echo "--- build tenant B (business ISOB) as System Administrator"
A="$T/sysadmin.txt"; login "$A" admin admin123 >/dev/null
use(){ post "$A" shop-context/select -d "location_id=$1" >/dev/null; }
post "$A" business-management/business/save -d code=ISOB -d "name=$MARK Business" -d city=Karachi >/dev/null
BIZB=$(q "SELECT id FROM businesses WHERE code='ISOB'")
post "$A" business-management/shop/save -d "business_id=$BIZB" -d code=ISOB-1 -d "name=$MARK Shop One" >/dev/null
post "$A" business-management/shop/save -d "business_id=$BIZB" -d code=ISOB-2 -d "name=$MARK Shop Two" >/dev/null
B1=$(q "SELECT id FROM locations WHERE code='ISOB-1'"); B2=$(q "SELECT id FROM locations WHERE code='ISOB-2'")
TYPEB=$(q "SELECT id FROM cylinder_types WHERE business_id=$BIZB AND code='C11_8'")
CATB=$(q "SELECT id FROM expense_categories WHERE business_id=$BIZB AND code='OTHER'")
use "$B1"
post "$A" rates/save -d rate_type=gas_per_kg -d cylinder_type_id= -d rate_value=250 -d "effective_from=$(date -d '-1 minute' '+%Y-%m-%dT%H:%M')" >/dev/null
post "$A" customers/save -d code=ISOB-C1 -d "name=$MARK Customer" -d phone=03009990001 -d city=Karachi -d credit_limit=100000 -d allow_credit_sale=1 -d opening_balance=0 -d is_active=1 >/dev/null
CUSTB=$(q "SELECT id FROM customers WHERE code='ISOB-C1'")
post "$A" suppliers/save -d code=ISOB-S1 -d "name=$MARK Supplier" -d credit_limit=100000 -d opening_balance=0 -d is_active=1 >/dev/null
SUPB=$(q "SELECT id FROM suppliers WHERE code='ISOB-S1'")
post "$A" inventory/opening/save -d "inventory_date=$(date +%Y-%m-%d)" -d "cylinder_type_id=$TYPEB" -d quantity=4 -d actual_gas_weight_kg=11.8 -d "comments=$MARK opening" >/dev/null
OPENB=$(q "SELECT id FROM inventory_opening_balances WHERE location_id=$B1 ORDER BY id DESC LIMIT 1")
post "$A" cash/open -d opening_cash=0 >/dev/null
SESSB=$(q "SELECT cs.id FROM cash_sessions cs JOIN cash_registers r ON r.id=cs.register_id WHERE r.location_id=$B1 AND cs.status='open' LIMIT 1")
bsale(){ curl -s -b "$A" -c "$A" -H 'Accept: application/json' -H 'X-Requested-With: XMLHttpRequest' --data-urlencode "csrf_test_name=$(csrf "$A")" --data-urlencode transaction_type=gas_sale --data-urlencode "customer_id=$CUSTB" --data-urlencode "lines_json=[{\"cylinder_type_id\":\"$TYPEB\",\"source_cylinder_unit_id\":\"\",\"quantity\":\"$1\",\"gas_entry_mode\":\"quantity\",\"gas_rate\":\"\"}]" --data-urlencode "payments_json=$2" --data-urlencode "transaction_at=$(d)" $B/sales/save | grep -oE '"id": *[0-9]+' | grep -oE '[0-9]+'; }
SALEB=$(bsale 2 '[{"payment_type":"sale","payment_mode":"cash","amount":"500"}]')
SALEB2=$(bsale 1 '[{"payment_type":"sale","payment_mode":"credit","amount":"0"}]')
post "$A" receipts/save -d "customer_id=$CUSTB" -d amount=100 -d payment_mode=cash >/dev/null
post "$A" purchases/save -d "supplier_id=$SUPB" --data-urlencode "lines_json=[{\"line_type\":\"filled_cylinder\",\"cylinder_type_id\":\"$TYPEB\",\"quantity\":\"1\",\"unit_rate\":\"3000\",\"actual_gas_weight_kg\":\"11.8\"}]" --data-urlencode "payments_json=[]" -d discount_amount=0 >/dev/null
PURB=$(q "SELECT id FROM purchases WHERE location_id=$B1 ORDER BY id DESC LIMIT 1")
post "$A" supplier-payments/save -d "supplier_id=$SUPB" -d amount=10 -d payment_mode=cash >/dev/null
post "$A" expenses/save -d "category_id=$CATB" -d amount=50 -d payment_mode=cash -d "description=$MARK expense" >/dev/null
post "$A" configuration/save -d scope=business -d "owner_id=$BIZB" -d setting_key=stock_transfer_enabled -d value=true >/dev/null
post "$A" configuration/save -d scope=business -d "owner_id=$BIZB" -d setting_key=stock_transfer_mode -d value=two_step >/dev/null
UNITB=$(q "SELECT id FROM cylinder_units WHERE location_id=$B1 AND status='filled' ORDER BY id LIMIT 1")
post "$A" inventory/transfers/create -d "to_location_id=$B2" -d "unit_ids[]=$UNITB" -d "notes=$MARK transfer" >/dev/null
TRB=$(q "SELECT id FROM stock_transfers WHERE business_id=$BIZB ORDER BY id DESC LIMIT 1")
use "$(q "SELECT id FROM locations WHERE code='MAIN'")"
# Mobile-ordering rows of tenant B (inserted directly; the API flows are covered by E2E-026…030).
w "INSERT INTO business_catalog_products(business_id,cylinder_type_id,sale_mode,display_name,quantity_unit,is_active) VALUES($BIZB,$TYPEB,'sell_gas_only','$MARK Gas','kg',1)"
PRODB=$(q "SELECT id FROM business_catalog_products WHERE business_id=$BIZB LIMIT 1")
w "INSERT INTO shop_product_availability(business_id,location_id,business_catalog_product_id,is_active) VALUES($BIZB,$B1,$PRODB,1)"
w "INSERT INTO customer_app_accounts(customer_id,business_id,username,password_hash,status) VALUES($CUSTB,$BIZB,'isob_app_user','$(q "SELECT password_hash FROM users WHERE username='admin'")','approved')"
ACCB=$(q "SELECT id FROM customer_app_accounts WHERE username='isob_app_user'")
w "INSERT INTO customer_signup_requests(business_id,requested_location_id,username,customer_name,status) VALUES($BIZB,$B1,'isob_signup','$MARK Signup','pending_approval')"
SIGNB=$(q "SELECT id FROM customer_signup_requests WHERE username='isob_signup'")
w "INSERT INTO customer_orders(business_id,location_id,customer_id,customer_app_account_id,order_no,idempotency_key,status,fulfilment_method,customer_name_snapshot,subtotal,total_amount) VALUES($BIZB,$B1,$CUSTB,$ACCB,'ISOB-ORD-1','isob-key-1','pending','collection','$MARK Customer',500,500)"
ORDB=$(q "SELECT id FROM customer_orders WHERE order_no='ISOB-ORD-1'")
w "INSERT INTO customer_rates(customer_id,business_id,rate_type,cylinder_type_id,rate_value,effective_from,notes) VALUES($CUSTB,$BIZB,'gas_per_kg',NULL,240,NOW(),'$MARK rate')"
CRATEB=$(q "SELECT id FROM customer_rates WHERE customer_id=$CUSTB LIMIT 1")
ROLE_ADMIN=$(q "SELECT id FROM roles WHERE code='ADMIN'")
HASH=$(q "SELECT password_hash FROM users WHERE username='admin'")
w "INSERT INTO users(location_id,is_system_admin,role_id,full_name,username,email,password_hash,is_active) VALUES($B1,0,$ROLE_ADMIN,'$MARK Owner','isob_owner','isob_owner@x.local','$HASH',1)"
USERB=$(q "SELECT id FROM users WHERE username='isob_owner'")
w "INSERT INTO user_business_access(user_id,business_id,access_level,is_default) VALUES($USERB,$BIZB,'head_office',1)"
w "INSERT INTO user_shop_access(user_id,location_id,is_default) VALUES($USERB,$B1,1)"
# BL-012: a Head Office user of B without a home branch, and a business-level rate of B.
w "INSERT INTO users(location_id,is_system_admin,role_id,full_name,username,email,password_hash,is_active) VALUES(NULL,0,$ROLE_ADMIN,'$MARK HO User','isob_ho','isob_ho@x.local','$HASH',1)"
w "INSERT INTO user_business_access(user_id,business_id,access_level,is_default) VALUES((SELECT id FROM users WHERE username='isob_ho'),$BIZB,'head_office',1)"
w "INSERT INTO rate_cards(location_id,business_id,rate_type,cylinder_type_id,rate_value,effective_from) VALUES(NULL,$BIZB,'gas_per_kg',NULL,999,NOW()-INTERVAL 1 DAY)"

for v in BIZB B1 B2 TYPEB CATB CUSTB SUPB OPENB SESSB SALEB SALEB2 PURB UNITB TRB PRODB ACCB SIGNB ORDB CRATEB USERB; do
  [ -n "${!v}" ] || bad "fixture $v" "not created"
done
[ "$FAIL" = 0 ] && ok "tenant B fixture: business $BIZB, shops $B1/$B2, sales $SALEB/$SALEB2, purchase $PURB, transfer $TRB, order $ORDB" || { echo "RESULT: $PASS passed, $FAIL failed"; exit 1; }

echo "--- tenant A owner (not a System Administrator)"
BIZA=$(q "SELECT id FROM businesses WHERE code='DEFAULT'"); MAIN=$(q "SELECT id FROM locations WHERE code='MAIN'")
w "INSERT INTO users(location_id,is_system_admin,role_id,full_name,username,email,password_hash,is_active) VALUES($MAIN,0,$ROLE_ADMIN,'QA Tenant A Owner','qa_iso_a_owner','qa_iso_a_owner@x.local','$HASH',1)"
USERA=$(q "SELECT id FROM users WHERE username='qa_iso_a_owner'")
w "INSERT INTO user_business_access(user_id,business_id,access_level,is_default) VALUES($USERA,$BIZA,'head_office',1)"
O="$T/owner.txt"; login "$O" qa_iso_a_owner admin123 | grep -q dashboard && ok "tenant A owner signs in" || bad "owner login" "no dashboard"
curl -s -b "$O" "$B/dashboard" | grep -q "Main LPG Shop" && ok "owner sees their own shop (sanity)" || bad "sanity" "own shop not shown"
TOKA=$(curl -s -H 'Content-Type: application/json' -d '{"username":"qa_iso_a_owner","password":"admin123"}' "$B/api/v1/staff/auth/login" | grep -oE '"access_token": *"[a-f0-9]{64}"' | grep -oE '[a-f0-9]{64}')
[ -n "$TOKA" ] && ok "owner gets a staff API token" || bad "owner token" "none"

# Fingerprint of every row tenant B owns.
B_UNITS=$(q "SELECT GROUP_CONCAT(id) FROM cylinder_units WHERE location_id IN ($B1,$B2)")
B_SALES="SELECT id FROM sales WHERE location_id IN ($B1,$B2)"
B_PURCH="SELECT id FROM purchases WHERE location_id IN ($B1,$B2)"
B_REGS="SELECT id FROM cash_registers WHERE location_id IN ($B1,$B2)"
B_CUSTS="SELECT id FROM customers WHERE location_id IN ($B1,$B2)"
SCOPES=(
 "businesses|id=$BIZB" "locations|business_id=$BIZB" "shop_settings|location_id IN ($B1,$B2)"
 "cylinder_types|business_id=$BIZB" "expense_categories|business_id=$BIZB" "suppliers|business_id=$BIZB"
 "customers|location_id IN ($B1,$B2)" "rate_cards|location_id IN ($B1,$B2)" "customer_rates|customer_id IN ($B_CUSTS)"
 "sales|location_id IN ($B1,$B2)" "sale_items|sale_id IN ($B_SALES)" "sale_payments|sale_id IN ($B_SALES)"
 "purchases|location_id IN ($B1,$B2)" "purchase_items|purchase_id IN ($B_PURCH)" "purchase_payments|purchase_id IN ($B_PURCH)"
 "customer_receipts|location_id IN ($B1,$B2)" "supplier_payments|location_id IN ($B1,$B2)" "expenses|location_id IN ($B1,$B2)"
 "cylinder_units|id IN ($B_UNITS)" "inventory_movements|location_id IN ($B1,$B2)" "inventory_opening_balances|location_id IN ($B1,$B2)"
 "cash_registers|location_id IN ($B1,$B2)" "cash_sessions|register_id IN ($B_REGS)" "cash_transactions|cash_session_id IN (SELECT id FROM cash_sessions WHERE register_id IN ($B_REGS))"
 "stock_transfers|business_id=$BIZB" "stock_transfer_items|transfer_id IN (SELECT id FROM stock_transfers WHERE business_id=$BIZB)"
 "business_catalog_products|business_id=$BIZB" "shop_product_availability|business_id=$BIZB"
 "customer_app_accounts|customer_id IN ($B_CUSTS)" "customer_signup_requests|business_id=$BIZB" "customer_orders|business_id=$BIZB"
 "users|id=$USERB" "user_business_access|business_id=$BIZB" "user_shop_access|location_id IN ($B1,$B2)"
 "business_settings|business_id=$BIZB" "shop_setting_overrides|location_id IN ($B1,$B2)"
)
fingerprint(){
  local spec t w cols out=""
  for spec in "${SCOPES[@]}"; do
    t=${spec%%|*}; w=${spec#*|}
    cols=$(q "SET SESSION group_concat_max_len=1000000; SELECT GROUP_CONCAT(CONCAT('IFNULL(\`',COLUMN_NAME,'\`,''~'')') ORDER BY ORDINAL_POSITION) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t'")
    out+="$t=$(q "SET SESSION group_concat_max_len=100000000; SELECT CONCAT(COUNT(*),':',IFNULL(MD5(GROUP_CONCAT(h ORDER BY h)),'-')) FROM (SELECT MD5(CONCAT_WS('|',$cols)) h FROM \`$t\` WHERE $w) z")"$'\n'
  done
  printf '%s' "$out"
}
# Rows of tenant A that point at tenant B (should always be zero).
crossrefs(){
  local AL="SELECT id FROM locations WHERE business_id<>$BIZB" n=0 x
  for x in \
    "SELECT COUNT(*) FROM sales WHERE location_id IN ($AL) AND customer_id IN ($B_CUSTS)" \
    "SELECT COUNT(*) FROM customer_receipts WHERE location_id IN ($AL) AND customer_id IN ($B_CUSTS)" \
    "SELECT COUNT(*) FROM cylinder_custody WHERE location_id IN ($AL) AND customer_id IN ($B_CUSTS)" \
    "SELECT COUNT(*) FROM customer_security_deposits WHERE location_id IN ($AL) AND customer_id IN ($B_CUSTS)" \
    "SELECT COUNT(*) FROM purchases WHERE location_id IN ($AL) AND supplier_id IN (SELECT id FROM suppliers WHERE business_id=$BIZB)" \
    "SELECT COUNT(*) FROM supplier_payments WHERE location_id IN ($AL) AND supplier_id IN (SELECT id FROM suppliers WHERE business_id=$BIZB)" \
    "SELECT COUNT(*) FROM supplier_opening_balances WHERE location_id IN ($AL) AND supplier_id IN (SELECT id FROM suppliers WHERE business_id=$BIZB)" \
    "SELECT COUNT(*) FROM expenses WHERE location_id IN ($AL) AND category_id IN (SELECT id FROM expense_categories WHERE business_id=$BIZB)" \
    "SELECT COUNT(*) FROM rate_cards WHERE (location_id IN ($AL) OR location_id IS NULL) AND cylinder_type_id IN (SELECT id FROM cylinder_types WHERE business_id=$BIZB)" \
    "SELECT COUNT(*) FROM cylinder_units WHERE location_id IN ($AL) AND cylinder_type_id IN (SELECT id FROM cylinder_types WHERE business_id=$BIZB)" \
    "SELECT COUNT(*) FROM inventory_movements WHERE location_id IN ($AL) AND cylinder_type_id IN (SELECT id FROM cylinder_types WHERE business_id=$BIZB)" \
    "SELECT COUNT(*) FROM stock_transfers WHERE business_id<>$BIZB AND (from_location_id IN ($B1,$B2) OR to_location_id IN ($B1,$B2))" \
    "SELECT COUNT(*) FROM user_shop_access WHERE user_id NOT IN (SELECT id FROM users WHERE username='admin' OR username LIKE 'isob%') AND location_id IN ($B1,$B2)" \
    "SELECT COUNT(*) FROM user_business_access WHERE user_id NOT IN (SELECT id FROM users WHERE username='admin' OR username LIKE 'isob%') AND business_id=$BIZB" \
    "SELECT COUNT(*) FROM customer_rates WHERE customer_id IN ($B_CUSTS) AND created_by=$USERA" \
    "SELECT COUNT(*) FROM business_catalog_products WHERE business_id<>$BIZB AND cylinder_type_id IN (SELECT id FROM cylinder_types WHERE business_id=$BIZB)" \
    "SELECT COUNT(*) FROM shop_product_availability WHERE (business_id<>$BIZB AND location_id IN ($B1,$B2)) OR (business_id=$BIZB AND location_id NOT IN ($B1,$B2))" \
    "SELECT COUNT(*) FROM customer_order_status_history WHERE order_id IN (SELECT id FROM customer_orders WHERE business_id=$BIZB) AND user_id=$USERA" \
  ; do n=$((n + $(q "$x"))); done
  echo "$n"
}
BEFORE=$(fingerprint); printf '%s' "$BEFORE" > "$T/before.txt"
[ "$(grep -cE '=[0-9]+:([0-9a-f]{32}|-)$' "$T/before.txt")" = "${#SCOPES[@]}" ] && ok "fingerprint covers ${#SCOPES[@]} tenant-B tables ($(grep -c '=0:-$' "$T/before.txt") of them empty)" || bad "fingerprint" "$(grep -vE '=[0-9]+:([0-9a-f]{32}|-)$' "$T/before.txt" | head -3 | tr '\n' ' ')"
# Detector self-test: the platform operator legitimately sees tenant B, so its marker must be found.
curl -s -b "$A" "$B/business-management" | grep -q "$MARK" && ok "leak detector self-test: marker visible to the System Administrator" || bad "detector self-test" "marker not found"
[ "$(crossrefs)" = 0 ] && ok "no tenant A row references tenant B before the crawl" || bad "crossrefs baseline" "non-zero"

# Path placeholder -> tenant B entity, chosen from the route text.
idfor(){
  local r=$1
  case "$r" in
    *customers/ledger*|*sales/custody*|*sales/customer-balance*|*reports/ledger*|*customer-app-accounts/customer/*|*customers/*/app-account*) echo "$CUSTB";;
    *suppliers/ledger*) echo "$SUPB";;
    *sales/details*|*sales/receipt*|*sales/void*) echo "$SALEB";;
    *purchases/void*) echo "$PURB";;
    *inventory/cylinders/*|*inventory/type-history*) echo "$TYPEB";;
    *inventory/cylinder/*|*inventory/adjustments/*) echo "$UNITB";;
    *inventory/transfers/*) echo "$TRB";;
    *inventory/opening/delete*) echo "$OPENB";;
    *customer-orders/*|*orders/*) echo "$ORDB";;
    *configuration/business*|*configuration/businesses*|*businesses/*) echo "$BIZB";;
    *expenses/categories/*) echo "$CATB";;
    *mobile-catalog/product/*) echo "$PRODB";;
    *rates/customer/end*) echo "$CRATEB";;
    *signup*|*customer-signups*) echo "$SIGNB";;
    *customer-app-accounts/*) echo "$ACCB";;
    *) echo "$B1";;
  esac
}
fillpath(){ # replace each ([0-9]+) with the entity id; second placeholder of shops/x/products/y is the product
  local r=$1 first; first=$(idfor "$r")
  r=${r/\(\[0-9\]+\)/$first}
  r=${r/\(\[0-9\]+\)/$PRODB}
  echo "$r"
}
QS="business_id=$BIZB&location_id=$B1&shop_id=$B1&branch_ids[]=$B1&branch_ids[]=$B2&customer_id=$CUSTB&supplier_id=$SUPB&cylinder_type_id=$TYPEB&type_id=$TYPEB&id=$SALEB&sale_id=$SALEB&order_id=$ORDB&edit=$USERB&from=2000-01-01&to=2099-12-31&tab=customers&status=&q=ISOB"
qa_spark routes 2>/dev/null | awk -F'|' '/^\| (GET|POST|PUT) /{gsub(/ /,"",$2); gsub(/ /,"",$3); print $2" "$3}' > "$T/routes.txt"
TOTAL=$(wc -l < "$T/routes.txt"); ok "route table read: $TOTAL routes"
skip(){ case "$1" in /|login|logout|installation*|migrations/run|database-backup*|account/password|shop-context/select|api/v1/staff/auth/*|api/v1/customer/auth/*) return 0;; esac; return 1; }

echo "--- 1+2. every GET route (web and API) with tenant B IDs"
COVERED=0; SKIPPED=0
while read -r method route; do
  [ "$method" = GET ] || continue
  if skip "$route"; then SKIPPED=$((SKIPPED+1)); continue; fi
  COVERED=$((COVERED+1))
  path=$(fillpath "$route")
  case "$route" in
    api/v1/*) code=$(curl -s -o "$T/get.out" -w '%{http_code}' -H "Authorization: Bearer $TOKA" -H "X-Shop-Id: $B1" "$B/$path?$QS");;
    *) code=$(curl -s -o "$T/get.out" -w '%{http_code}' -b "$O" -c "$O" "$B/$path?$QS");;
  esac
  if grep -q "$MARK" "$T/get.out"; then bad "GET $route" "HTTP $code shows tenant B data"; fi
done < "$T/routes.txt"
ok "GET crawl finished: $COVERED routes called, $SKIPPED auth/system routes skipped"

echo "--- 3. every mutating route with tenant B IDs in path and body"
BODY=(-d "id=$CUSTB" -d "customer_id=$CUSTB" -d "supplier_id=$SUPB" -d "cylinder_type_id=$TYPEB" -d "location_id=$B1" -d "shop_id=$B1" -d "business_id=$BIZB" -d "owner_id=$BIZB" -d scope=business -d "session_id=$SESSB" -d "sale_id=$SALEB" -d "unit_ids[]=$UNITB" -d "to_location_id=$B2" -d "category_id=$CATB" -d "user_id=$USERB" -d "role_id=$ROLE_ADMIN" -d "product_id=$PRODB" -d "order_id=$ORDB" -d amount=1 -d payment_mode=cash -d "name=QA Hijack" -d code=QA-HIJACK -d "full_name=QA Hijack" -d username=isob_owner -d is_active=0 -d status=cancelled -d "reason=QA hijack" -d "void_reason=QA hijack" -d "cancel_reason=QA hijack" -d setting_key=stock_transfer_enabled -d value=false -d rate_type=gas_per_kg -d rate_value=1 -d "effective_from=$(date '+%Y-%m-%dT%H:%M')" -d counted_cash=0 -d "branch_ids[]=$B1" -d branch_access_submitted=1 -d "default_branch_id=$B1")
JSON="{\"id\":$CUSTB,\"customer_id\":$CUSTB,\"business_id\":$BIZB,\"shop_id\":$B1,\"location_id\":$B1,\"user_id\":$USERB,\"status\":\"cancelled\",\"reason\":\"QA hijack\",\"is_active\":false,\"name\":\"QA Hijack\",\"code\":\"QA-HIJACK\",\"scope\":\"business\",\"owner_id\":$BIZB,\"setting_key\":\"stock_transfer_enabled\",\"value\":false,\"shop_ids\":[$B1],\"cylinder_type_id\":$TYPEB}"
MCOVERED=0
while read -r method route; do
  [ "$method" = POST ] || [ "$method" = PUT ] || continue
  if skip "$route"; then continue; fi
  MCOVERED=$((MCOVERED+1))
  path=$(fillpath "$route")
  case "$route" in
    api/v1/*) curl -s -X "$method" -o "$T/post.out" -H "Authorization: Bearer $TOKA" -H "X-Shop-Id: $B1" -H 'Content-Type: application/json' -H "Idempotency-Key: isolation-$MCOVERED" -d "$JSON" "$B/$path" >/dev/null;;
    *) post "$O" "$path" "${BODY[@]}" >/dev/null; cp "$T/post_out.html" "$T/post.out";;
  esac
  grep -q "$MARK" "$T/post.out" && bad "POST $route" "response shows tenant B data"
  # The owner's session must stay usable for the following calls.
  curl -s -o /dev/null -w '%{redirect_url}' -b "$O" "$B/dashboard" | grep -q login && login "$O" qa_iso_a_owner admin123 >/dev/null
done < "$T/routes.txt"
ok "POST crawl finished: $MCOVERED mutating routes called"
# Positive control: the crawl's requests really reach the controllers (CSRF and session valid);
# an expense category saved with the generic body lands in the owner's own business.
[ "$(q "SELECT COUNT(*) FROM expense_categories WHERE name='QA Hijack' AND business_id=$BIZA")" -ge 1 ] && ok "POST crawl reached the controllers (own-tenant write landed in tenant A)" || bad "positive control" "no POST was processed"

AFTER=$(fingerprint); printf '%s' "$AFTER" > "$T/after.txt"
if [ "$AFTER" = "$BEFORE" ]; then ok "none of tenant B's rows changed"; else
  diff <(echo "$BEFORE") <(echo "$AFTER") | grep '^>' | sed 's/^> //' | while read -r l; do echo "FAIL  tenant B rows changed :: $l"; done
  FAIL=$((FAIL+1)); fi
X=$(crossrefs); [ "$X" = 0 ] && ok "no tenant A row references tenant B after the crawl" || bad "cross-tenant references" "$X rows"
# BL-009: every row written through the app (both tenants, all screens above) carries the
# business of its branch.
MIS=0
for t in customers sales purchases customer_receipts supplier_payments expenses cash_registers cylinder_units inventory_movements inventory_opening_balances cylinder_custody customer_security_deposits document_sequences shop_settings inventory_policies inventory_wastage_logs shop_purchase_void_users supplier_opening_balances shop_setting_overrides rate_cards rate_change_log audit_logs; do
  MIS=$((MIS + $(q "SELECT COUNT(*) FROM \`$t\` x JOIN locations l ON l.id=x.location_id WHERE x.business_id IS NULL OR x.business_id<>l.business_id")))
done
[ "$MIS" = 0 ] && ok "every row written by the app carries its branch's business" || bad "tenant stamping" "$MIS rows without the right business_id"
# BL-010: and every child row written through the app carries its parent's business.
CMIS=0
for spec in cash_sessions:register_id:cash_registers sale_items:sale_id:sales sale_payments:sale_id:sales purchase_items:purchase_id:purchases purchase_payments:purchase_id:purchases stock_transfer_items:transfer_id:stock_transfers customer_rates:customer_id:customers customer_app_accounts:customer_id:customers cash_transactions:cash_session_id:cash_sessions; do
  IFS=: read -r t c p <<<"$spec"
  CMIS=$((CMIS + $(q "SELECT COUNT(*) FROM \`$t\` x JOIN \`$p\` y ON y.id=x.\`$c\` WHERE x.business_id<>y.business_id")))
done
[ "$CMIS" = 0 ] && ok "every child row written by the app carries its parent's business" || bad "child stamping" "$CMIS rows"
echo "--- BL-011 tenant-scoped models"
cp "$QA_ROOT/tests/qa/commands/QaTenantModel.php" "$QA_ROOT/app/Commands/QaTenantModel.php"
# BL-012: a branch of tenant A with no rates of its own must not fall back to tenant B's rate.
w "INSERT INTO locations(business_id,code,name) VALUES($BIZA,'QA-ISO-A2','QA Iso A Two')"; A2=$(q "SELECT id FROM locations WHERE code='QA-ISO-A2'")
TM=$(qa_spark qa:tenant-model "$MAIN" "$CUSTB" "$TYPEB" "$A2" 2>&1)
rm -f "$QA_ROOT/app/Commands/QaTenantModel.php"
for expect in no-context:refused find-other:null where-other:0 type-other:null foreign-insert:refused platform-scope:FOUND rate-a2:none; do
  echo "$TM" | grep -q "^$expect" && ok "model check $expect" || bad "model check $expect" "$(echo "$TM" | grep "^${expect%%:*}" | head -1)"
done
[ "$(q "SELECT name FROM customers WHERE id=$CUSTB")" = "$MARK Customer" ] && ok "model update/delete with another tenant's id changed nothing" || bad "model write" "tenant B customer changed or deleted"

echo "COVERAGE: $COVERED GET + $MCOVERED POST of $TOTAL routes (rest: sign-in, installation, migration and backup routes, tested in verify_security / verify_phase8)"

echo; echo "RESULT: $PASS passed, $FAIL failed"
[ "$FAIL" = 0 ]
