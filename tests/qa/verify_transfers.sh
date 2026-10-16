#!/usr/bin/env bash
# Phase 9.5.4 stock transfer runtime verification against the QA database (tests/qa/env.sh). Run reset_db.sh first.
set -u  # no pipefail: "curl | grep -q" would fail on SIGPIPE after an early match
source "$(dirname "$0")/env.sh"
T="$QA_TMP/p9t"; mkdir -p "$T"
PASS=0; FAIL=0
ok(){ echo "PASS  $1"; PASS=$((PASS+1)); }
bad(){ echo "FAIL  $1 :: $2"; FAIL=$((FAIL+1)); }
# mysql.exe under Git Bash occasionally returns nothing (no error); retry empty results.
q(){ local r="" i; for i in 1 2 3 4; do r=$($M -e "$1" | tr -d ''); [ -n "$r" ] && break; done; printf '%s' "$r"; }
login(){ rm -f "$1"; local tok; tok=$(curl -s -c "$1" -b "$1" $B/login | grep -oE 'name="csrf_test_name" value="[^"]+"' | sed -E 's/.*value="([^"]+)"/\1/'); curl -s -o /dev/null -w '%{url_effective}' -L -c "$1" -b "$1" --data-urlencode "login=$2" --data-urlencode "password=$3" --data-urlencode "csrf_test_name=$tok" $B/login; }
csrf(){ grep -oE 'csrf_cookie_name\s+\S+' "$1" | awk '{print $2}'; }
post(){ local c=$1 path=$2; shift 2; curl -s -o "$T/post_out.html" -w '%{http_code}' -b "$c" -c "$c" --data-urlencode "csrf_test_name=$(csrf "$c")" "$@" "$B/$path"; }
flash(){ curl -s -b "$1" -c "$1" "$B/${2:-dashboard}" | grep -oE 'alert-(success|danger|warning)[^>]*>[^<]*(<[^>]*>[^<]*){0,3}' | sed -E 's/<[^>]*>//g' | head -1; }
setcfg(){ post "$A" configuration/save -d scope=business -d "owner_id=$1" -d "setting_key=$2" -d "value=$3" >/dev/null; }
use(){ post "$A" shop-context/select -d "location_id=$1" >/dev/null; }
unit(){ q "SELECT CONCAT(l.code,':',cu.status,':',cu.gas_weight_kg) FROM cylinder_units cu JOIN locations l ON l.id=cu.location_id WHERE cu.id=$1"; }
reconcile(){ # gas history (movements in-out) must equal gas in the branch's filled cylinders
  local loc=$1
  local hist; hist=$(q "SELECT ROUND(COALESCE(SUM(CASE WHEN direction='in' THEN quantity WHEN direction='out' THEN -quantity ELSE 0 END),0),3) FROM inventory_movements WHERE location_id=$loc AND inventory_type='gas_kg'")
  local stock; stock=$(q "SELECT ROUND(COALESCE(SUM(gas_weight_kg),0),3) FROM cylinder_units WHERE location_id=$loc AND status='filled'")
  [ "$hist" = "$stock" ] && ok "gas reconciles at $2 (history $hist = stock $stock)" || bad "gas reconcile $2" "history $hist vs stock $stock"; }

A="$T/admin.txt"; login "$A" admin admin123 | grep -q dashboard && ok "admin login" || bad "admin login" ""
BIZ=$(q "SELECT id FROM businesses WHERE code='DEFAULT'"); MAIN=$(q "SELECT id FROM locations WHERE code='MAIN'")
U1=$(q "SELECT id FROM cylinder_units WHERE unit_code='QA-E2E-C6-001'"); UE=$(q "SELECT id FROM cylinder_units WHERE unit_code='QA-ORDER-EMPTY-C6-001'"); U2=$(q "SELECT id FROM cylinder_units WHERE unit_code='QA-ORDER-C6-001'")
post "$A" business-management/shop/save -d "business_id=$BIZ" -d code=QA-B2 -d "name=QA Branch Two" >/dev/null
B2=$(q "SELECT id FROM locations WHERE code='QA-B2'")
post "$A" business-management/business/save -d code=QA2 -d "name=QA Second Business" >/dev/null
BIZ2=$(q "SELECT id FROM businesses WHERE code='QA2'")
post "$A" business-management/shop/save -d "business_id=$BIZ2" -d code=QA2-S1 -d "name=QA2 Shop" >/dev/null
OTHER=$(q "SELECT id FROM locations WHERE code='QA2-S1'")
[ -n "$B2" ] && [ -n "$OTHER" ] && ok "fixtures: branch QA-B2 and other-business shop QA2-S1" || bad "fixtures" "b2=$B2 other=$OTHER"

echo "--- disabled by default"
curl -s -b "$A" $B/inventory/transfers | grep -q "Stock transfer is turned off" && ok "page explains transfer is off" || bad "off page" ""
post "$A" inventory/transfers/create -d "to_location_id=$B2" -d "unit_ids[]=$U1" >/dev/null
[ "$(q "SELECT COUNT(*) FROM stock_transfers")" = 0 ] && ok "create rejected while disabled ($(flash "$A" inventory/transfers))" || bad "disabled create" "created"

echo "--- two-step flow"
setcfg $BIZ stock_transfer_enabled true
GAS0=$(q "SELECT SUM(gas_weight_kg) FROM cylinder_units WHERE location_id=$MAIN AND status='filled'")
post "$A" inventory/transfers/create -d "to_location_id=$B2" -d "unit_ids[]=$U1" -d "unit_ids[]=$UE" -d "notes=QA two step" >/dev/null
TR1=$(q "SELECT id FROM stock_transfers ORDER BY id DESC LIMIT 1")
[ "$(q "SELECT CONCAT(status,'|',transfer_no,'|',flow) FROM stock_transfers WHERE id=$TR1")" = "in_transit|TR-$(printf %06d $TR1)|two_step" ] && ok "dispatched as TR-$(printf %06d $TR1), in transit" || bad "dispatch" "$(q "SELECT status,transfer_no FROM stock_transfers WHERE id=$TR1")"
[ "$(unit $U1)" = "MAIN:in_transit:6.000" ] && [ "$(unit $UE)" = "MAIN:in_transit:0.000" ] && ok "cylinders in transit keep code and gas weight" || bad "in transit units" "$(unit $U1) $(unit $UE)"
GAS1=$(q "SELECT SUM(gas_weight_kg) FROM cylinder_units WHERE location_id=$MAIN AND status='filled'")
[ "$(q "SELECT ROUND($GAS0-$GAS1,3)")" = "6.000" ] && ok "MAIN available gas dropped by 6 kg at dispatch" || bad "MAIN gas" "$GAS0 -> $GAS1"
C6=$(q "SELECT cylinder_type_id FROM cylinder_units WHERE id=$U1")
R=$(curl -s -b "$A" -c "$A" -H 'Accept: application/json' -H 'X-Requested-With: XMLHttpRequest' --data-urlencode "csrf_test_name=$(csrf "$A")" --data-urlencode transaction_type=gas_sale --data-urlencode "lines_json=[{\"cylinder_type_id\":\"$C6\",\"source_cylinder_unit_id\":\"$U1\",\"quantity\":\"1\",\"gas_entry_mode\":\"quantity\"}]" --data-urlencode 'payments_json=[{"payment_type":"sale","payment_mode":"cash","amount":"100"}]' $B/sales/save)
echo "$R" | grep -qE '"success": *false' && ok "POS cannot sell from an in-transit cylinder" || bad "sell in transit" "$R"
post "$A" "inventory/transfers/$TR1/receive" >/dev/null
[ "$(q "SELECT status FROM stock_transfers WHERE id=$TR1")" = in_transit ] && ok "sending branch cannot confirm receipt" || bad "sender receive" "received"
use $B2
curl -s -b "$A" $B/inventory/transfers | grep -q "Awaiting your receipt" && ok "receiving branch sees 'Awaiting your receipt'" || bad "incoming badge" ""
post "$A" "inventory/transfers/$TR1/receive" >/dev/null
[ "$(q "SELECT status FROM stock_transfers WHERE id=$TR1")" = received ] && [ "$(unit $U1)" = "QA-B2:filled:6.000" ] && [ "$(unit $UE)" = "QA-B2:empty:0.000" ] && ok "received: cylinders now in QA-B2 stock as filled 6 kg / empty" || bad "receive" "$(unit $U1) $(unit $UE) $(flash "$A" inventory/transfers/$TR1)"
post "$A" "inventory/transfers/$TR1/receive" >/dev/null
[ "$(q "SELECT COUNT(*) FROM inventory_movements WHERE source_type='stock_transfer' AND source_id=$TR1 AND location_id=$B2")" = 3 ] && ok "second receipt refused; exactly 3 IN movements at QA-B2" || bad "double receive" "$(q "SELECT COUNT(*) FROM inventory_movements WHERE source_type='stock_transfer' AND source_id=$TR1 AND location_id=$B2")"

echo "--- cancel"
post "$A" inventory/transfers/create -d "to_location_id=$MAIN" -d "unit_ids[]=$U1" >/dev/null
TR2=$(q "SELECT id FROM stock_transfers ORDER BY id DESC LIMIT 1")
use $MAIN; post "$A" "inventory/transfers/$TR2/cancel" -d "cancel_reason=wrong branch" >/dev/null
[ "$(q "SELECT status FROM stock_transfers WHERE id=$TR2")" = in_transit ] && ok "receiving branch cannot cancel" || bad "receiver cancel" "cancelled"
use $B2; post "$A" "inventory/transfers/$TR2/cancel" -d "cancel_reason=" >/dev/null
[ "$(q "SELECT status FROM stock_transfers WHERE id=$TR2")" = in_transit ] && ok "cancel without reason refused" || bad "cancel no reason" ""
post "$A" "inventory/transfers/$TR2/cancel" -d "cancel_reason=Vehicle not available" >/dev/null
[ "$(q "SELECT status FROM stock_transfers WHERE id=$TR2")" = cancelled ] && [ "$(unit $U1)" = "QA-B2:filled:6.000" ] && ok "sender cancelled: cylinder back in QA-B2 stock" || bad "cancel" "$(unit $U1)"

echo "--- one-step flow and isolation"
use $MAIN; setcfg $BIZ stock_transfer_mode one_step
post "$A" inventory/transfers/create -d "to_location_id=$B2" -d "unit_ids[]=$U2" >/dev/null
TR3=$(q "SELECT id FROM stock_transfers ORDER BY id DESC LIMIT 1")
[ "$(q "SELECT CONCAT(status,'|',flow) FROM stock_transfers WHERE id=$TR3")" = "received|one_step" ] && [ "$(unit $U2)" = "QA-B2:filled:6.000" ] && ok "one-step: cylinder moved to QA-B2 immediately" || bad "one step" "$(unit $U2)"
N=$(q "SELECT COUNT(*) FROM stock_transfers"); UF=$(q "SELECT id FROM cylinder_units WHERE unit_code='QA-ORDER-FILLED-C6-001'")
post "$A" inventory/transfers/create -d "to_location_id=$OTHER" -d "unit_ids[]=$UF" >/dev/null
[ "$(q "SELECT COUNT(*) FROM stock_transfers")" = "$N" ] && ok "transfer to another business's shop rejected" || bad "cross business" "created"
post "$A" inventory/transfers/create -d "to_location_id=$B2" -d "unit_ids[]=$U1" >/dev/null
[ "$(q "SELECT COUNT(*) FROM stock_transfers")" = "$N" ] && ok "cannot transfer a cylinder that is in another branch" || bad "foreign unit" "created"
use $OTHER; curl -s -o /dev/null -w '%{http_code}' -b "$A" "$B/inventory/transfers/$TR1" | grep -q 404 && ok "a branch that is neither sender nor receiver gets 404 for TR1" || bad "detail isolation" ""; use $MAIN

echo "--- permission"
q "INSERT INTO users(location_id,is_system_admin,role_id,full_name,username,email,password_hash,is_active) SELECT $MAIN,0,r.id,'QA Cashier','qa_cashier','qa_cashier@x.local',(SELECT password_hash FROM users WHERE username='admin'),1 FROM roles r WHERE r.code='CASHIER'"
q "INSERT INTO user_shop_access(user_id,location_id,is_default) SELECT id,$MAIN,1 FROM users WHERE username='qa_cashier'"
CS="$T/cashier.txt"; login "$CS" qa_cashier admin123 >/dev/null
[ "$(curl -s -o /dev/null -w '%{http_code}' -b "$CS" $B/inventory/transfers)" = 403 ] && ok "cashier without STOCK_TRANSFER_MANAGE gets 403" || bad "permission" ""
curl -s -b "$CS" $B/dashboard | grep -q "Branch Stock Transfers" && bad "menu" "cashier sees menu" || ok "menu entry hidden from cashier"

echo "--- reconciliation"
reconcile $MAIN MAIN; reconcile $B2 QA-B2

for k in stock_transfer_enabled stock_transfer_mode; do post "$A" configuration/reset -d scope=business -d "owner_id=$BIZ" -d "setting_key=$k" >/dev/null; done
echo; echo "RESULT: $PASS passed, $FAIL failed"
