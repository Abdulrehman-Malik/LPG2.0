#!/usr/bin/env bash
# Rates and customer pricing (requirements.md "Rates and Customer Pricing"): filled cylinder rate,
# pro-rata for partly filled cylinders, customer rates (filled, per KG, empty) with fallback,
# ending a customer rate, the two business settings, custom-rate flag, Cylinder Sale and Gas Sale.
# Run after reset_db.sh.
set -u
source "$(dirname "$0")/env.sh"
T="$QA_TMP/rates"; mkdir -p "$T"
PASS=0; FAIL=0
ok(){ echo "PASS  $1"; PASS=$((PASS+1)); }
bad(){ echo "FAIL  $1 :: $2"; FAIL=$((FAIL+1)); }
q(){ local r="" i; for i in 1 2 3 4; do r=$($M -e "$1" | tr -d '\r'); [ -n "$r" ] && break; done; printf '%s' "$r"; }
eqn(){ [ "$(printf '%.2f' "${2:-0}")" = "$(printf '%.2f' "${3:-0}")" ] && ok "$1 = $2" || bad "$1" "got $2, expected $3"; }
login(){ rm -f "$1"; local tok; tok=$(curl -s -c "$1" -b "$1" $B/login | grep -oE 'name="csrf_test_name" value="[^"]+"' | sed -E 's/.*value="([^"]+)"/\1/'); curl -s -o /dev/null -L -c "$1" -b "$1" --data-urlencode "login=$2" --data-urlencode "password=$3" --data-urlencode "csrf_test_name=$tok" $B/login; }
csrf(){ grep -oE 'csrf_cookie_name\s+\S+' "$1" | awk '{print $2}'; }
post(){ local path=$1; shift; curl -s -o "$T/out.html" -w '%{http_code}' -b "$A" -c "$A" --data-urlencode "csrf_test_name=$(csrf "$A")" "$@" "$B/$path"; }
flash(){ curl -s -b "$A" -c "$A" "$B/${1:-dashboard}" | grep -oE 'alert-(success|danger)[^>]*>(<[^>]*>)*[^<]+' | sed -E 's/<[^>]*>//g;s/.*>//' | head -1; }
sale(){ # $1 type, $2 customer, $3 lines_json, $4 cash paid
  curl -s -b "$A" -c "$A" -H 'Accept: application/json' -H 'X-Requested-With: XMLHttpRequest' --data-urlencode "csrf_test_name=$(csrf "$A")" \
    --data-urlencode "transaction_type=$1" --data-urlencode "customer_id=$2" --data-urlencode "lines_json=$3" \
    --data-urlencode "payments_json=[{\"payment_type\":\"$5\",\"payment_mode\":\"cash\",\"amount\":\"$4\"}]" --data-urlencode security_deposit_amount=0 $B/sales/save > "$T/sale.json"
  grep -oE '"id": *[0-9]+' "$T/sale.json" | grep -oE '[0-9]+'; }
setting(){ q "INSERT INTO business_settings(business_id,setting_key,setting_value) VALUES (1,'$1',JSON_QUOTE('$2')) ON DUPLICATE KEY UPDATE setting_value=JSON_QUOTE('$2'); SELECT 1" >/dev/null; }
unset_setting(){ q "DELETE FROM business_settings WHERE setting_key='$1'; SELECT 1" >/dev/null; }
full_c6(){ q "SELECT id FROM cylinder_units WHERE location_id=$LOC AND cylinder_type_id=$C6 AND status='filled' AND gas_weight_kg=6 ORDER BY id LIMIT 1"; }
issue(){ # $1 customer, $2 unit, $3 cash, [$4 gas_rate per KG]
  sale security_deposit "$1" "[{\"cylinder_type_id\":\"$C6\",\"cylinder_status\":\"filled\",\"selected_cylinder_unit_ids\":[$2],\"gas_rate\":\"${4:-}\",\"cylinder_rate\":\"\"}]" "$3" combined; }
total(){ q "SELECT total_amount FROM sales WHERE id=${1:-0}"; }

A="$T/admin.txt"; login "$A" admin admin123
LOC=$(q "SELECT id FROM locations WHERE code='MAIN'")
C6=$(q "SELECT id FROM cylinder_types WHERE code='C6' AND business_id=(SELECT business_id FROM locations WHERE id=$LOC)")
TODAY=$(date +%Y-%m-%d)
post cash/open -d opening_cash=1000 >/dev/null
post inventory/opening/save -d "inventory_date=$TODAY" -d "cylinder_type_id=$C6" -d quantity=8 -d actual_gas_weight_kg=6 -d "comments=QA rates" >/dev/null
q "INSERT INTO customers(location_id,code,name,opening_balance,credit_limit,allow_credit_sale,is_active) VALUES ($LOC,'QR-STD','Rates Standard',0,0,0,1),($LOC,'QR-FILL','Rates Special Cylinder',0,0,0,1),($LOC,'QR-KG','Rates Special KG',0,0,0,1); SELECT 1" >/dev/null
STD=$(q "SELECT id FROM customers WHERE code='QR-STD'"); SPF=$(q "SELECT id FROM customers WHERE code='QR-FILL'"); SPK=$(q "SELECT id FROM customers WHERE code='QR-KG'")

echo "--- Rates screen"
post rates/save -d rate_type=filled_cylinder -d "cylinder_type_id=$C6" -d rate_value=900 -d "effective_from=$(date -d '-1 minute' '+%Y-%m-%dT%H:%M' 2>/dev/null || date '+%Y-%m-%dT00:00')" -d "reason=QA" >/dev/null
[ "$(q "SELECT rate_value FROM rate_cards WHERE rate_type='filled_cylinder' AND cylinder_type_id=$C6 AND location_id=$LOC")" = "900.0000" ] && ok "filled cylinder rate saved (C6 Rs. 900 for 6 KG)" || bad "filled rate save" "$(flash rates)"
post rates/save -d rate_type=filled_cylinder -d cylinder_type_id= -d rate_value=1 -d "effective_from=${TODAY}T00:00" >/dev/null
[ "$(q "SELECT COUNT(*) FROM rate_cards WHERE rate_type='filled_cylinder'")" = 1 ] && ok "filled cylinder rate without a cylinder type refused" || bad "type required" "saved"
post rates/customer/save -d "customer_id=$SPF" -d rate_type=filled_cylinder -d "cylinder_type_id=$C6" -d rate_value=840 -d "effective_from=${TODAY}T00:00" -d "notes=QA contract" >/dev/null
post rates/customer/save -d "customer_id=$SPK" -d rate_type=gas_per_kg -d rate_value=120 -d "effective_from=${TODAY}T00:00" >/dev/null
post rates/customer/save -d "customer_id=$SPF" -d rate_type=cylinder_package -d "cylinder_type_id=$C6" -d rate_value=200 -d "effective_from=${TODAY}T00:00" >/dev/null
[ "$(q "SELECT COUNT(*) FROM customer_rates WHERE ended_at IS NULL")" = 3 ] && ok "three customer rates saved" || bad "customer rates" "$(flash 'rates?tab=customers')"
curl -s -b "$A" "$B/rates?tab=customers" | grep -q "Rates Special Cylinder" && ok "customer rates listed on the Rates screen" || bad "rates list" "missing"
curl -s -b "$A" "$B/customers/ledger/$SPF" | grep -q "Special rates" && ok "special rates shown on the customer ledger" || bad "ledger" "missing"

echo "--- Issue Cylinder: gas only, filled cylinder rate"
U=$(full_c6); S=$(issue "$STD" "$U" 900)
[ -n "$S" ] && ok "full C6 issued to a standard customer for Rs. 900" || bad "issue full" "$(head -c 300 "$T/sale.json")"
eqn "issue total = filled cylinder rate (no per-KG, no cylinder charge)" "$(total "$S")" 900
eqn "custody keeps the per-KG equivalent for returns (900 / 6)" "$(q "SELECT issued_gas_rate FROM cylinder_custody WHERE issue_sale_id=${S:-0}")" 150
[ "$(q "SELECT custom_rate_flag FROM sale_items WHERE sale_id=${S:-0}")" = 0 ] && ok "default rate not flagged as custom" || bad "custom flag" "flagged"
# Fixture: one full C6 made partly filled (4.7 KG), as after earlier use.
P=$(full_c6); q "UPDATE cylinder_units SET gas_weight_kg=4.7 WHERE id=$P; SELECT 1" >/dev/null
S=$(issue "$STD" "$P" 705)
eqn "partly filled 4.7 KG C6 charged pro-rata (900 x 4.7 / 6)" "$(total "$S")" 705

echo "--- Customer rates and fallback"
S=$(issue "$SPF" "$(full_c6)" 840); eqn "customer filled cylinder rate (840) beats the branch rate" "$(total "$S")" 840
S=$(issue "$SPK" "$(full_c6)" 720); eqn "customer per-KG rate x capacity (120 x 6) beats the generic filled rate" "$(total "$S")" 720
SR=$(q "SELECT id FROM customer_rates WHERE customer_id=$SPF AND rate_type='filled_cylinder'")
post "rates/customer/end/$SR" >/dev/null
[ -n "$(q "SELECT ended_at FROM customer_rates WHERE id=$SR")" ] && ok "customer rate ended" || bad "end rate" "$(flash 'rates?tab=customers')"
S=$(issue "$SPF" "$(full_c6)" 900); eqn "after ending, the customer gets the standard rate again" "$(total "$S")" 900

echo "--- Cashier override"
S=$(issue "$STD" "$(full_c6)" 870 145)
eqn "edited rate (Rs. 870 per cylinder = 145 per KG) used" "$(total "$S")" 870
[ "$(q "SELECT custom_rate_flag FROM sale_items WHERE sale_id=${S:-0}")" = 1 ] && ok "edited rate flagged as custom" || bad "custom flag" "not flagged"

echo "--- Business settings"
setting issue_cylinder_charge gas_and_cylinder
S=$(issue "$STD" "$(full_c6)" 1150); eqn "issue with cylinder charge: 900 gas + 250 empty cylinder price" "$(total "$S")" 1150
unset_setting issue_cylinder_charge
setting filled_cylinder_pricing kg_rate
S=$(issue "$STD" "$(full_c6)" 600); eqn "per-KG pricing: full C6 = 100 x 6" "$(total "$S")" 600
unset_setting filled_cylinder_pricing

echo "--- Cylinder Sale and Gas Sale"
U=$(full_c6)
S=$(sale cylinder_sale "$SPF" "[{\"cylinder_type_id\":\"$C6\",\"cylinder_status\":\"filled\",\"quantity\":\"1\",\"gas_weight_kg\":\"6\",\"gas_rate\":\"\",\"cylinder_rate\":\"\",\"selected_cylinder_unit_ids\":[$U]}]" 1100 sale)
eqn "filled Cylinder Sale: 900 gas (filled rate) + 200 customer empty cylinder price" "$(total "$S")" 1100
G=$(q "SELECT id FROM cylinder_units WHERE location_id=$LOC AND cylinder_type_id=$C6 AND status='filled' AND gas_weight_kg>=1 ORDER BY id LIMIT 1")
S=$(sale gas_sale "$SPK" "[{\"cylinder_type_id\":\"$C6\",\"source_cylinder_unit_id\":\"$G\",\"quantity\":\"1\",\"gas_entry_mode\":\"quantity\",\"gas_rate\":\"\"}]" 120 sale)
eqn "Gas Sale uses the customer's rate per KG (1 KG x 120)" "$(total "$S")" 120
S=$(sale gas_sale "$STD" "[{\"cylinder_type_id\":\"$C6\",\"source_cylinder_unit_id\":\"$G\",\"quantity\":\"1\",\"gas_entry_mode\":\"quantity\",\"gas_rate\":\"\"}]" 100 sale)
eqn "Gas Sale for a standard customer stays per KG (1 KG x 100)" "$(total "$S")" 100

echo
echo "RESULT: $PASS passed, $FAIL failed"
