#!/usr/bin/env bash
# End-to-end business flow (PAGE_TEST_WORKFLOW §5) for the flows not covered by the other suites:
# opening inventory, purchases (credit and part-paid), supplier payment bounds, purchase void,
# Cylinder Sale header posting, gas wastage (partial, full, over-consumption), cylinder-type delete
# restriction, then the §6 financial/inventory invariants over the whole database.
# Run after reset_db.sh.
set -u
source "$(dirname "$0")/env.sh"
T="$QA_TMP/flow"; mkdir -p "$T"
PASS=0; FAIL=0
ok(){ echo "PASS  $1"; PASS=$((PASS+1)); }
bad(){ echo "FAIL  $1 :: $2"; FAIL=$((FAIL+1)); }
q(){ local r="" i; for i in 1 2 3 4; do r=$($M -e "$1" | tr -d '\r'); [ -n "$r" ] && break; done; printf '%s' "$r"; }
eqn(){ [ "$(printf '%.2f' "${2:-0}")" = "$(printf '%.2f' "${3:-0}")" ] && ok "$1 = $2" || bad "$1" "got $2, expected $3"; }
login(){ rm -f "$1"; local tok; tok=$(curl -s -c "$1" -b "$1" $B/login | grep -oE 'name="csrf_test_name" value="[^"]+"' | sed -E 's/.*value="([^"]+)"/\1/'); curl -s -o /dev/null -L -c "$1" -b "$1" --data-urlencode "login=$2" --data-urlencode "password=$3" --data-urlencode "csrf_test_name=$tok" $B/login; }
csrf(){ grep -oE 'csrf_cookie_name\s+\S+' "$1" | awk '{print $2}'; }
post(){ local path=$1; shift; curl -s -o "$T/out.html" -w '%{http_code}' -b "$A" -c "$A" --data-urlencode "csrf_test_name=$(csrf "$A")" "$@" "$B/$path"; }
flash(){ curl -s -b "$A" -c "$A" "$B/${1:-dashboard}" | grep -oE 'alert-(success|danger)[^>]*>(<[^>]*>)*[^<]+' | sed -E 's/<[^>]*>//g;s/.*>//' | head -1; }
salejson(){ curl -s -b "$A" -c "$A" -H 'Accept: application/json' -H 'X-Requested-With: XMLHttpRequest' --data-urlencode "csrf_test_name=$(csrf "$A")" "$@" $B/sales/save; }

A="$T/admin.txt"; login "$A" admin admin123
LOC=$(q "SELECT id FROM locations WHERE code='MAIN'")
C6=$(q "SELECT id FROM cylinder_types WHERE code='C6' AND business_id=(SELECT business_id FROM locations WHERE id=$LOC)")
TODAY=$(date +%Y-%m-%d)
filled(){ q "SELECT COUNT(*) FROM cylinder_units WHERE location_id=$LOC AND cylinder_type_id=$C6 AND status='filled'"; }
empty(){ q "SELECT COUNT(*) FROM cylinder_units WHERE location_id=$LOC AND cylinder_type_id=$C6 AND status='empty'"; }
payable(){ q "SELECT ROUND(s.opening_balance + COALESCE((SELECT SUM(credit_amount) FROM purchases WHERE supplier_id=s.id AND location_id=$LOC AND status='posted'),0) - COALESCE((SELECT SUM(amount) FROM supplier_payments WHERE supplier_id=s.id AND location_id=$LOC AND status='posted'),0),2) FROM suppliers s WHERE s.id=$1"; }
post cash/open -d opening_cash=1000 >/dev/null

echo "--- Opening inventory"
F0=$(filled)
post inventory/opening/save -d "inventory_date=$TODAY" -d "cylinder_type_id=$C6" -d quantity=2 -d actual_gas_weight_kg=6 -d "comments=QA flow opening" >/dev/null
[ "$(filled)" = "$((F0+2))" ] && ok "opening adds 2 filled C6 cylinders ($F0 -> $(filled))" || bad "opening" "$F0 -> $(filled) :: $(flash inventory/opening)"

echo "--- Purchases and supplier payable"
post suppliers/save -d "name=QA Flow Supplier" -d code=QFLOW -d opening_balance=0 -d credit_limit=10000 -d is_active=1 >/dev/null
SUP=$(q "SELECT id FROM suppliers WHERE code='QFLOW'")
[ -n "$SUP" ] && ok "supplier created" || bad "supplier" "$(flash suppliers)"
F1=$(filled)
post purchases/save -d "supplier_id=$SUP" --data-urlencode "lines_json=[{\"line_type\":\"filled_cylinder\",\"cylinder_type_id\":\"$C6\",\"quantity\":\"2\",\"unit_rate\":\"1000\",\"actual_gas_weight_kg\":\"6\"}]" --data-urlencode "payments_json=[]" -d discount_amount=0 >/dev/null
P1=$(q "SELECT id FROM purchases WHERE supplier_id=$SUP ORDER BY id LIMIT 1")
[ -n "$P1" ] && ok "credit purchase posted ($(q "SELECT purchase_no FROM purchases WHERE id=$P1"))" || bad "purchase" "$(flash purchases)"
eqn "purchase total" "$(q "SELECT total_amount FROM purchases WHERE id=$P1")" 2000
eqn "unpaid amount added to payable" "$(q "SELECT credit_amount FROM purchases WHERE id=$P1")" 2000
[ "$(filled)" = "$((F1+2))" ] && ok "2 filled cylinders received" || bad "purchase stock" "$F1 -> $(filled)"
CASH0=$(q "SELECT COALESCE(SUM(CASE WHEN direction='in' THEN amount ELSE -amount END),0) FROM cash_transactions")
post purchases/save -d "supplier_id=$SUP" --data-urlencode "lines_json=[{\"line_type\":\"filled_cylinder\",\"cylinder_type_id\":\"$C6\",\"quantity\":\"1\",\"unit_rate\":\"1000\",\"actual_gas_weight_kg\":\"6\"}]" --data-urlencode 'payments_json=[{"payment_mode":"cash","amount":"500","reference_no":""}]' -d discount_amount=0 >/dev/null
P2=$(q "SELECT id FROM purchases WHERE supplier_id=$SUP ORDER BY id DESC LIMIT 1")
eqn "part-paid purchase credit" "$(q "SELECT credit_amount FROM purchases WHERE id=$P2")" 500
eqn "cash out for the purchase payment" "$(q "SELECT $CASH0 - COALESCE(SUM(CASE WHEN direction='in' THEN amount ELSE -amount END),0) FROM cash_transactions")" 500
eqn "supplier payable" "$(payable $SUP)" 2500

N0=$(q "SELECT COUNT(*) FROM supplier_payments")
post supplier-payments/save -d "supplier_id=$SUP" -d amount=3000 -d payment_mode=cheque >/dev/null
[ "$(q "SELECT COUNT(*) FROM supplier_payments")" = "$N0" ] && ok "payment above the payable refused, nothing posted ($(flash supplier-payments | cut -c1-60))" || bad "payment bound" "a payment was posted"
post supplier-payments/save -d "supplier_id=$SUP" -d amount=1000 -d payment_mode=cheque -d reference_no=CHQ-1 >/dev/null
eqn "payable after Rs. 1,000 payment" "$(payable $SUP)" 1500
curl -s -b "$A" "$B/suppliers" | grep -q "QA Flow Supplier" && ok "supplier listed" || bad "supplier list" "missing"

echo "--- Purchase void"
q "UPDATE shop_settings SET purchase_void_enabled=1 WHERE location_id=$LOC; INSERT IGNORE INTO shop_purchase_void_users(location_id,business_id,user_id) SELECT $LOC,(SELECT business_id FROM locations WHERE id=$LOC),id FROM users WHERE username='admin'; SELECT 1" >/dev/null
F2=$(filled)
post "purchases/void/$P2" -d "void_reason=QA flow void" >/dev/null
[ "$(q "SELECT status FROM purchases WHERE id=$P2")" = "voided" ] && ok "part-paid purchase voided" || bad "purchase void" "$(flash purchases)"
[ "$(filled)" = "$((F2-1))" ] && ok "voided purchase's cylinder removed from stock" || bad "void stock" "$F2 -> $(filled)"
eqn "payable after void (500 credit reversed)" "$(payable $SUP)" 1000

echo "--- staff API: suppliers, purchases, supplier payments (BL-025)"
TOK=$(curl -s -H 'Content-Type: application/json' -d '{"username":"admin","password":"admin123"}' "$B/api/v1/staff/auth/login" | grep -oE '"access_token": *"[a-f0-9]{64}"' | grep -oE '[a-f0-9]{64}')
api(){ curl -s -H "Authorization: Bearer $TOK" -H "X-Shop-Id: $LOC" -H 'Content-Type: application/json' "$@"; }
apipayable(){ api "$B/api/v1/staff/suppliers?per_page=100" | node -e 'let s="";process.stdin.on("data",d=>s+=d).on("end",()=>{const r=JSON.parse(s).data.suppliers.find(x=>+x.id===+process.argv[1]);console.log(r?r.payable:"")})' "$1"; }
eqn "API payable equals SupplierBalanceService" "$(apipayable $SUP)" "$(payable $SUP)"
R=$(api -H 'Idempotency-Key: qa-api-purchase-1' -d "{\"supplier_id\":$SUP,\"lines\":[{\"line_type\":\"filled_cylinder\",\"cylinder_type_id\":$C6,\"quantity\":1,\"unit_rate\":1000,\"actual_gas_weight_kg\":6}],\"payments\":[]}" "$B/api/v1/staff/purchases")
echo "$R" | grep -q '"purchase_no"' && ok "API purchase posted" || bad "API purchase" "$R"
api -H 'Idempotency-Key: qa-api-purchase-1' -d "{\"supplier_id\":$SUP,\"lines\":[{\"line_type\":\"filled_cylinder\",\"cylinder_type_id\":$C6,\"quantity\":1,\"unit_rate\":1000,\"actual_gas_weight_kg\":6}],\"payments\":[]}" "$B/api/v1/staff/purchases" >/dev/null
eqn "payable after API credit purchase (replay posted nothing)" "$(payable $SUP)" 2000
eqn "API payable after purchase" "$(apipayable $SUP)" 2000
R=$(api -d "{\"supplier_id\":$SUP,\"amount\":5000,\"payment_mode\":\"cheque\"}" -w ' HTTP%{http_code}' "$B/api/v1/staff/supplier-payments")
echo "$R" | grep -q 'HTTP422' && ok "API payment above the payable refused" || bad "API payment bound" "$R"
api -d "{\"supplier_id\":$SUP,\"amount\":500,\"payment_mode\":\"cheque\",\"reference_no\":\"CHQ-API\"}" "$B/api/v1/staff/supplier-payments" | grep -q '"payment_no"' && ok "API supplier payment posted" || bad "API payment" ""
eqn "payable after API payment" "$(apipayable $SUP)" 1500
R=$(api -d '{"name":"QA API Supplier","code":"QAPI-SUP","phone":"0300","opening_balance":250}' -w ' HTTP%{http_code}' "$B/api/v1/staff/suppliers")
SUPA=$(q "SELECT id FROM suppliers WHERE code='QAPI-SUP'")
echo "$R" | grep -q 'HTTP201' && [ "$(q "SELECT is_active FROM suppliers WHERE id=$SUPA")" = 1 ] && ok "API supplier created active" || bad "API supplier create" "$R"
eqn "API supplier opening payable recorded at the shop" "$(apipayable $SUPA)" 250
api -X PUT -d '{"phone":"0311"}' "$B/api/v1/staff/suppliers/$SUPA" >/dev/null
[ "$(q "SELECT CONCAT(name,'|',phone,'|',is_active) FROM suppliers WHERE id=$SUPA")" = "QA API Supplier|0311|1" ] && ok "API partial supplier update keeps other fields" || bad "API supplier update" "$(q "SELECT CONCAT(name,'|',phone,'|',is_active) FROM suppliers WHERE id=$SUPA")"
eqn "partial update keeps the opening payable" "$(apipayable $SUPA)" 250
api "$B/api/v1/staff/suppliers/$SUP/ledger" | grep -q '"purchases"' && ok "API supplier ledger" || bad "API ledger" ""
[ "$(curl -s -o /dev/null -w '%{http_code}' -H "Authorization: Bearer $TOK" -H "X-Shop-Id: $LOC" "$B/api/v1/staff/purchases/99999999")" = 404 ] && ok "API unknown purchase is 404" || bad "API purchase 404" ""

echo "--- Cylinder Sale (header path)"
E0=$(empty)
R=$(salejson --data-urlencode transaction_type=cylinder_sale --data-urlencode "lines_json=[{\"cylinder_type_id\":\"$C6\",\"cylinder_status\":\"empty\",\"quantity\":\"1\",\"cylinder_rate\":\"1500\",\"selected_cylinder_unit_ids\":[]}]" --data-urlencode 'payments_json=[{"payment_type":"sale","payment_mode":"cash","amount":"1500"}]')
SID=$(echo "$R" | grep -oE '"id": *[0-9]+' | grep -oE '[0-9]+')
[ -n "$SID" ] && ok "empty cylinder sold ($(q "SELECT sale_no FROM sales WHERE id=$SID"))" || bad "cylinder sale" "$R"
[ "$(empty)" = "$((E0-1))" ] && ok "one empty cylinder left stock" || bad "cylinder sale stock" "$E0 -> $(empty)"
eqn "cylinder sale total" "$(q "SELECT total_amount FROM sales WHERE id=$SID")" 1500

echo "--- Gas wastage"
post inventory/controls/save -d cylinder_type_id= -d stock_validation_enabled=1 -d wastage_mode=fixed_kg -d wastage_percent=0 -d wastage_fixed_kg=10 >/dev/null
U=$(q "SELECT id FROM cylinder_units WHERE location_id=$LOC AND cylinder_type_id=$C6 AND status='filled' AND gas_weight_kg>=6 ORDER BY id DESC LIMIT 1")
post inventory/wastage/record -d "cylinder_unit_id=$U" -d gas_weight_kg=1 -d "reason=QA leak" >/dev/null
eqn "partial wastage leaves 5 KG" "$(q "SELECT gas_weight_kg FROM cylinder_units WHERE id=$U")" 5
post inventory/wastage/record -d "cylinder_unit_id=$U" -d gas_weight_kg=5 -d "reason=QA residual" >/dev/null
[ "$(q "SELECT CONCAT(status,'|',gas_weight_kg) FROM cylinder_units WHERE id=$U")" = "empty|0.000" ] && ok "full wastage makes the cylinder empty" || bad "full wastage" "$(q "SELECT CONCAT(status,'|',gas_weight_kg) FROM cylinder_units WHERE id=$U") :: $(flash inventory/wastage)"
W=$(q "SELECT COUNT(*) FROM inventory_movements WHERE cylinder_unit_id=$U")
post inventory/wastage/record -d "cylinder_unit_id=$U" -d gas_weight_kg=1 -d "reason=QA over" >/dev/null
[ "$(q "SELECT COUNT(*) FROM inventory_movements WHERE cylinder_unit_id=$U")" = "$W" ] && ok "wastage on an empty cylinder refused (no movement)" || bad "over-consumption" "movement recorded"

echo "--- Cylinder type delete restriction"
post cylinder-types/delete -d "id=$C6" >/dev/null
[ -n "$(q "SELECT id FROM cylinder_types WHERE id=$C6")" ] && ok "type with stock cannot be deleted" || bad "delete restriction" "C6 deleted"
post cylinder-types/save -d code=QTMP -d "name=QA temp" -d capacity_kg=9 -d empty_cylinder_price=100 -d sort_order=99 -d is_active=1 >/dev/null
TMP=$(q "SELECT id FROM cylinder_types WHERE code='QTMP'")
post cylinder-types/delete -d "id=$TMP" >/dev/null
# BL-030: delete is a soft delete — the row stays (deleted_at set, inactive) and lists skip it.
[ "$(q "SELECT CONCAT(deleted_at IS NOT NULL,'|',is_active) FROM cylinder_types WHERE code='QTMP'")" = "1|0" ] && ! curl -s -b "$A" "$B/cylinder-types" | grep -q "QTMP" && ok "unused type can be deleted (soft delete, hidden from the list)" || bad "delete unused" "$(q "SELECT CONCAT(COALESCE(deleted_at,'NULL'),'|',is_active) FROM cylinder_types WHERE code='QTMP'")"

echo "--- Money rounding (D10-2)"
# Two lines whose amounts differ by rule: 0.5 KG x 10.006 = 5.003 per line.
#   document: total round(10.006) = 10.01 ; line: 5.00 + 5.00 = 10.00
setting(){ q "INSERT INTO business_settings(business_id,setting_key,setting_value) VALUES ((SELECT business_id FROM locations WHERE id=$LOC),'$1',JSON_QUOTE('$2')) ON DUPLICATE KEY UPDATE setting_value=JSON_QUOTE('$2'); SELECT 1" >/dev/null; }
unset_setting(){ q "DELETE FROM business_settings WHERE setting_key='$1'; SELECT 1" >/dev/null; }
# One gas line per cylinder type is allowed, so the second line uses an 11.8 KG cylinder.
C11=$(q "SELECT id FROM cylinder_types WHERE code='C11_8' AND business_id=(SELECT business_id FROM locations WHERE id=$LOC)")
post inventory/opening/save -d "inventory_date=$TODAY" -d "cylinder_type_id=$C11" -d quantity=2 -d actual_gas_weight_kg=11.8 -d "comments=QA rounding" >/dev/null
gas2(){ # $1 = cash paid
  local u1 u2; u1=$(q "SELECT id FROM cylinder_units WHERE location_id=$LOC AND cylinder_type_id=$C6 AND status='filled' AND gas_weight_kg>=0.5 ORDER BY id LIMIT 1")
  u2=$(q "SELECT id FROM cylinder_units WHERE location_id=$LOC AND cylinder_type_id=$C11 AND status='filled' AND gas_weight_kg>=0.5 ORDER BY id LIMIT 1")
  salejson --data-urlencode transaction_type=gas_sale --data-urlencode "lines_json=[{\"cylinder_type_id\":\"$C6\",\"source_cylinder_unit_id\":\"$u1\",\"quantity\":\"0.5\",\"gas_entry_mode\":\"quantity\",\"gas_rate\":\"10.006\"},{\"cylinder_type_id\":\"$C11\",\"source_cylinder_unit_id\":\"$u2\",\"quantity\":\"0.5\",\"gas_entry_mode\":\"quantity\",\"gas_rate\":\"10.006\"}]" --data-urlencode "payments_json=[{\"payment_type\":\"sale\",\"payment_mode\":\"cash\",\"amount\":\"$1\"}]" | tee "$T/sale.json" | grep -oE '"id": *[0-9]+' | grep -oE '[0-9]+'; }
pur2(){ post purchases/save -d "supplier_id=$SUP" --data-urlencode "lines_json=[{\"line_type\":\"filled_cylinder\",\"cylinder_type_id\":\"$C6\",\"quantity\":\"1\",\"unit_rate\":\"10.006\",\"actual_gas_weight_kg\":\"6\"},{\"line_type\":\"filled_cylinder\",\"cylinder_type_id\":\"$C6\",\"quantity\":\"1\",\"unit_rate\":\"10.006\",\"actual_gas_weight_kg\":\"6\"}]" --data-urlencode "payments_json=[]" -d discount_amount=0 >/dev/null; q "SELECT id FROM purchases WHERE supplier_id=$SUP ORDER BY id DESC LIMIT 1"; }
unset_setting money_rounding_mode
S1=$(gas2 10.01); [ -n "$S1" ] && ok "document rule: 2-line gas sale posted for Rs. 10.01" || bad "document rule sale" "$(cat "$T/sale.json" | head -c 200)"
eqn "document rule: sale total" "$(q "SELECT total_amount FROM sales WHERE id=${S1:-0}")" 10.01
P1R=$(pur2); eqn "document rule: purchase total (2 x 10.006)" "$(q "SELECT total_amount FROM purchases WHERE id=${P1R:-0}")" 20.01
setting money_rounding_mode line
S2=$(gas2 10.00); [ -n "$S2" ] && ok "line rule: same sale posted for Rs. 10.00" || bad "line rule sale" "$(cat "$T/sale.json" | head -c 200)"
eqn "line rule: sale total" "$(q "SELECT total_amount FROM sales WHERE id=${S2:-0}")" 10.00
eqn "line rule: total = sum of printed lines" "$(q "SELECT SUM(line_total) FROM sale_items WHERE sale_id=${S2:-0}")" 10.00
P2R=$(pur2); eqn "line rule: purchase total (10.01 + 10.01)" "$(q "SELECT total_amount FROM purchases WHERE id=${P2R:-0}")" 20.02
eqn "line rule: purchase total = sum of lines" "$(q "SELECT SUM(line_total) FROM purchase_items WHERE purchase_id=${P2R:-0}")" 20.02
unset_setting money_rounding_mode

echo "--- Supplier balance mode (D9-4)"
BIZ=$(q "SELECT business_id FROM locations WHERE id=$LOC")
post business-management/shop/save -d "business_id=$BIZ" -d code=QA-B2 -d "name=QA Branch Two" >/dev/null
B2=$(q "SELECT id FROM locations WHERE code='QA-B2'")
[ -n "$B2" ] && ok "second branch QA-B2 available" || bad "QA-B2" "not created"
MAINPAY=$(payable $SUP)
post shop-context/select -d "location_id=$B2" >/dev/null
N1=$(q "SELECT COUNT(*) FROM supplier_payments")
post supplier-payments/save -d "supplier_id=$SUP" -d amount=100 -d payment_mode=cheque -d reference_no=CHQ-B2 >/dev/null
[ "$(q "SELECT COUNT(*) FROM supplier_payments")" = "$N1" ] && ok "separate balances: QA-B2 cannot pay MAIN's debt (owes nothing here)" || bad "separate balances" "payment posted at QA-B2"
setting supplier_balance_mode business
post supplier-payments/save -d "supplier_id=$SUP" -d "amount=$(awk "BEGIN{printf \"%.2f\", $MAINPAY+1}")" -d payment_mode=cheque >/dev/null
[ "$(q "SELECT COUNT(*) FROM supplier_payments")" = "$N1" ] && ok "single balance: payment above the business payable ($MAINPAY) refused" || bad "single balance bound" "over-payment posted"
post supplier-payments/save -d "supplier_id=$SUP" -d amount=100 -d payment_mode=cheque -d reference_no=CHQ-B2 >/dev/null
[ "$(q "SELECT COUNT(*) FROM supplier_payments WHERE location_id=$B2 AND supplier_id=$SUP AND status='posted'")" = 1 ] && ok "single balance: QA-B2 pays the business debt (Rs. 100)" || bad "single balance payment" "$(flash supplier-payments)"
unset_setting supplier_balance_mode
post shop-context/select -d "location_id=$LOC" >/dev/null
eqn "separate balances again: MAIN payable unchanged by QA-B2's payment" "$(payable $SUP)" "$MAINPAY"

echo "--- Invariants (PAGE_TEST_WORKFLOW §6) over the whole database"
X=$(q "SELECT COUNT(*) FROM sales s WHERE ABS(s.subtotal - (SELECT COALESCE(SUM(line_total),0) FROM sale_items i WHERE i.sale_id=s.id)) > 0.0001 + 0.005*GREATEST(1,(SELECT COUNT(*) FROM sale_items i WHERE i.sale_id=s.id))")
[ "$X" = "0" ] && ok "every sale subtotal equals the sum of its lines (within the D10-2 document-rule rounding of 0.005 per line)" || bad "sale subtotal" "$X sale(s) differ"
X=$(q "SELECT COUNT(*) FROM sales WHERE ABS(total_amount - (subtotal - discount_amount + delivery_fee_amount)) > 0.011")
[ "$X" = "0" ] && ok "every sale total = subtotal − discount + delivery" || bad "sale total" "$X sale(s) differ"
X=$(q "SELECT COUNT(*) FROM purchases p WHERE ABS(p.subtotal - (SELECT COALESCE(SUM(line_total),0) FROM purchase_items i WHERE i.purchase_id=p.id)) > 0.0001 + 0.005*GREATEST(1,(SELECT COUNT(*) FROM purchase_items i WHERE i.purchase_id=p.id)) OR ABS(p.total_amount - (p.subtotal - p.discount_amount)) > 0.011")
[ "$X" = "0" ] && ok "every purchase total equals its lines" || bad "purchase totals" "$X purchase(s) differ"
X=$(q "SELECT COUNT(*) FROM cylinder_units cu JOIN cylinder_types ct ON ct.id=cu.cylinder_type_id WHERE cu.gas_weight_kg < 0 OR cu.gas_weight_kg > ct.capacity_kg + 0.0005 OR (cu.status='filled' AND cu.gas_weight_kg <= 0)")
[ "$X" = "0" ] && ok "every cylinder's gas is within 0..capacity; filled cylinders hold gas" || bad "cylinder gas range" "$X unit(s)"
X=$(q "SELECT COUNT(*) FROM (SELECT sale_id FROM sale_payments sp JOIN sales s ON s.id=sp.sale_id WHERE s.status='posted' AND sp.payment_type='sale' AND s.transaction_type NOT IN ('security_deposit','cylinder_return') GROUP BY sale_id HAVING ABS(SUM(CASE WHEN payment_mode<>'credit' THEN amount ELSE 0 END) + MAX(s.credit_amount) - MAX(s.total_amount) - MAX(s.previous_os_balance)) > 0.011 AND ABS(SUM(CASE WHEN payment_mode<>'credit' THEN amount ELSE 0 END) + MAX(s.credit_amount) - MAX(s.total_amount)) > 0.011) x")
[ "$X" = "0" ] && ok "sale payments + unpaid credit account for each sale" || bad "sale payment breakdown" "$X sale(s)"
X=$(q "SELECT COUNT(*) FROM cash_sessions cs WHERE cs.status='closed' AND cs.counted_cash IS NULL")
[ "$X" = "0" ] && ok "every closed cash session has a counted amount" || bad "cash sessions" "$X"
X=$(q "SELECT COUNT(*) FROM (SELECT customer_order_id FROM sales WHERE customer_order_id IS NOT NULL GROUP BY customer_order_id HAVING COUNT(*)>1) x")
[ "$X" = "0" ] && ok "at most one sale per customer order" || bad "order sales" "$X"
X=$(q "SELECT COUNT(*) FROM (SELECT location_id,sale_no FROM sales GROUP BY location_id,sale_no HAVING COUNT(*)>1) x")
[ "$X" = "0" ] && ok "sale numbers unique per branch" || bad "sale numbers" "$X duplicates"

echo; echo "RESULT: $PASS passed, $FAIL failed"
