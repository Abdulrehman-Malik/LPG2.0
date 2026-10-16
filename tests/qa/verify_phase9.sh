#!/usr/bin/env bash
# Phase 9 (9.5.1-9.5.3) runtime verification against the QA database (tests/qa/env.sh). Run reset_db.sh first.
set -u  # no pipefail: "curl | grep -q" would fail on SIGPIPE after an early match
source "$(dirname "$0")/env.sh"
T="$QA_TMP/p9"; mkdir -p "$T"
PASS=0; FAIL=0
ok(){ echo "PASS  $1"; PASS=$((PASS+1)); }
bad(){ echo "FAIL  $1 :: $2"; FAIL=$((FAIL+1)); }
# mysql.exe under Git Bash occasionally returns nothing (no error); retry empty results.
q(){ local r="" i; for i in 1 2 3 4; do r=$($M -e "$1" | tr -d ''); [ -n "$r" ] && break; done; printf '%s' "$r"; }
sid(){ grep -oE '"id": *[0-9]+' | grep -oE '[0-9]+'; }
login(){ rm -f "$1"; local tok; tok=$(curl -s -c "$1" -b "$1" $B/login | grep -oE 'name="csrf_test_name" value="[^"]+"' | sed -E 's/.*value="([^"]+)"/\1/'); curl -s -o /dev/null -w '%{url_effective}' -L -c "$1" -b "$1" --data-urlencode "login=$2" --data-urlencode "password=$3" --data-urlencode "csrf_test_name=$tok" $B/login; }
csrf(){ grep -oE 'csrf_cookie_name\s+\S+' "$1" | awk '{print $2}'; }
post(){ local c=$1 path=$2; shift 2; curl -s -o "$T/post_out.html" -w '%{http_code}' -b "$c" -c "$c" --data-urlencode "csrf_test_name=$(csrf "$c")" "$@" "$B/$path"; }
flash(){ curl -s -b "$1" -c "$1" "$B/${2:-dashboard}" | grep -oE 'alert-(success|danger|warning)[^>]*>[^<]*(<[^>]*>[^<]*){0,3}' | sed -E 's/<[^>]*>//g' | head -2 | tr '\n' ' '; }
sale(){ local c=$1 type=$2 cust=$3 lines=$4 pays=$5 at=$6
  curl -s -b "$c" -c "$c" -H 'Accept: application/json' -H 'X-Requested-With: XMLHttpRequest' \
    --data-urlencode "csrf_test_name=$(csrf "$c")" --data-urlencode "transaction_type=$type" --data-urlencode "customer_id=$cust" \
    --data-urlencode "lines_json=$lines" --data-urlencode "payments_json=$pays" --data-urlencode "transaction_at=$at" $B/sales/save; }
setcfg(){ post "$A" configuration/save -d scope=business -d "owner_id=$BIZ" -d "setting_key=$1" -d "value=$2" >/dev/null; }

A="$T/admin.txt"; login "$A" admin admin123 | grep -q dashboard && ok "admin login" || bad "admin login" ""
BIZ=$(q "SELECT id FROM businesses WHERE code='DEFAULT'"); LOC=$(q "SELECT id FROM locations WHERE code='MAIN'")
C6=$(q "SELECT id FROM cylinder_types WHERE code='C6' AND business_id=$BIZ"); U1=$(q "SELECT id FROM cylinder_units WHERE unit_code='QA-E2E-C6-001'")
gasline(){ echo "[{\"cylinder_type_id\":\"${2:-$C6}\",\"source_cylinder_unit_id\":\"$U1\",\"quantity\":\"$1\",\"gas_entry_mode\":\"quantity\",\"gas_rate\":\"\"}]"; }
CASH='[{"payment_type":"sale","payment_mode":"cash","amount":"10"}]'
d(){ date -d "$1" '+%Y-%m-%dT%H:%M'; }

echo "--- 9.5.1 configuration choice settings"
setcfg backdate_mode bogus
[ -z "$(q "SELECT setting_value FROM business_settings WHERE business_id=$BIZ AND setting_key='backdate_mode'")" ] && ok "invalid choice rejected, nothing stored ($(flash "$A" configuration/business/$BIZ))" || bad "invalid choice" "stored"
curl -s -b "$A" $B/configuration/business/$BIZ | grep -q "Back-dated transactions" && ok "business configuration screen shows the new settings" || bad "config screen" "missing"
curl -s -b "$A" $B/configuration/business/$BIZ | grep -q 'Two steps — sending branch dispatches' && ok "choice options rendered as a dropdown" || bad "choice dropdown" "missing"

echo "--- 9.5.2 back-dating policy (default: none)"
R=$(sale "$A" gas_sale "" "$(gasline 0.1)" "$CASH" "$(d yesterday)"); echo "$R" | grep -q "not allowed" && ok "yesterday rejected under 'none'" || bad "none/yesterday" "$R"
R=$(sale "$A" gas_sale "" "$(gasline 0.1)" "$CASH" "$(d '+2 days')"); echo "$R" | grep -qi "future" && ok "future date rejected" || bad "future" "$R"
R=$(sale "$A" gas_sale "" "$(gasline 0.1)" "$CASH" "$(d now)"); echo "$R" | grep -qE '"success": *true' && ok "today accepted" || bad "today" "$R"
setcfg backdate_mode days; setcfg backdate_max_days 3
[ "$(q "SELECT setting_value FROM business_settings WHERE business_id=$BIZ AND setting_key='backdate_mode'")" = '"days"' ] && ok "business set to 3 days back" || bad "set days" "$(flash "$A" configuration/business/$BIZ)"
R=$(sale "$A" gas_sale "" "$(gasline 0.1)" "$CASH" "$(d yesterday)"); S=$(echo "$R" | sid)
[ -n "$S" ] && [ "$(q "SELECT DATE(transaction_at) FROM sales WHERE id=$S")" = "$(date -d yesterday +%Y-%m-%d)" ] && ok "yesterday accepted and stored with yesterday's date" || bad "days/yesterday" "$R"
R=$(sale "$A" gas_sale "" "$(gasline 0.1)" "$CASH" "$(d '-5 days')"); echo "$R" | grep -q "outside the allowed" && ok "5 days back rejected" || bad "days/-5" "$R"
setcfg backdate_mode current_month
R=$(sale "$A" gas_sale "" "$(gasline 0.1)" "$CASH" "$(date '+%Y-%m-01T09:00')"); echo "$R" | grep -qE '"success": *true' && ok "1st of current month accepted" || bad "month/1st" "$R"
R=$(sale "$A" gas_sale "" "$(gasline 0.1)" "$CASH" "$(date -d "$(date +%Y-%m-01) -1 day" '+%Y-%m-%dT09:00')"); echo "$R" | grep -q "outside the allowed" && ok "last day of previous month rejected" || bad "month/prev" "$R"
curl -s -b "$A" $B/expenses | grep -q 'name="transaction_at"' && ok "expense screen shows date field when back-dating is allowed" || bad "expense date field" "missing"
CAT=$(q "SELECT id FROM expense_categories WHERE business_id=$BIZ AND code='OTHER'")
post "$A" expenses/save -d "category_id=$CAT" -d amount=5 -d payment_mode=cash -d "transaction_at=$(d yesterday)" >/dev/null
[ "$(q "SELECT DATE(expense_at) FROM expenses ORDER BY id DESC LIMIT 1")" = "$(date -d yesterday +%Y-%m-%d)" ] && ok "back-dated expense stored with chosen date" || bad "expense date" "$(flash "$A" expenses)"

echo "--- 9.5.3 business-owned master data"
post "$A" business-management/business/save -d code=QA2 -d "name=QA Second Business" -d city=Lahore >/dev/null
BIZ2=$(q "SELECT id FROM businesses WHERE code='QA2'")
[ -n "$BIZ2" ] && [ "$(q "SELECT COUNT(*) FROM cylinder_types WHERE business_id=$BIZ2")" = 6 ] && [ "$(q "SELECT COUNT(*) FROM expense_categories WHERE business_id=$BIZ2")" = 5 ] && ok "new business provisioned with 6 cylinder types and 5 expense categories" || bad "provisioning" "biz2=$BIZ2"
C6B=$(q "SELECT id FROM cylinder_types WHERE code='C6' AND business_id=$BIZ2")
R=$(sale "$A" gas_sale "" "$(gasline 0.1 "$C6B")" "$CASH" "$(d now)"); echo "$R" | grep -qi "invalid or inactive cylinder type" && ok "POS rejects another business's cylinder type" || bad "foreign type POS" "$R"
post "$A" rates/save -d rate_type=cylinder_package -d "cylinder_type_id=$C6B" -d rate_value=1 -d "effective_from=$(d now)" >/dev/null
[ "$(q "SELECT COUNT(*) FROM rate_cards WHERE cylinder_type_id=$C6B")" = 0 ] && ok "rate card for another business's type rejected" || bad "foreign rate" "created"
post "$A" cylinder-types/toggle -d "id=$C6B" >/dev/null
[ "$(q "SELECT is_active FROM cylinder_types WHERE id=$C6B")" = 1 ] && ok "cannot toggle another business's cylinder type" || bad "foreign toggle" "changed"
curl -s -b "$A" $B/cylinder-types | grep -q "value=\"$C6B\"" && bad "type list" "foreign type visible" || ok "cylinder type list shows only this business's types"
OTHERCAT=$(q "SELECT id FROM expense_categories WHERE business_id=$BIZ2 AND code='OTHER'")
N=$(q "SELECT COUNT(*) FROM expenses"); post "$A" expenses/save -d "category_id=$OTHERCAT" -d amount=5 -d payment_mode=cash >/dev/null
[ "$(q "SELECT COUNT(*) FROM expenses")" = "$N" ] && ok "expense with another business's category rejected" || bad "foreign category" "posted"

echo "--- 9.5.3 supplier sharing modes"
post "$A" business-management/shop/save -d "business_id=$BIZ" -d code=QA-B2 -d "name=QA Branch Two" >/dev/null
LOC2=$(q "SELECT id FROM locations WHERE code='QA-B2'"); [ -n "$LOC2" ] && ok "second branch QA-B2 created" || bad "branch2" "$(flash "$A" business-management)"
post "$A" suppliers/save -d "name=QA Shared Sup" -d code=QS1 -d credit_limit=0 -d opening_balance=0 -d is_active=1 >/dev/null
[ "$(q "SELECT IFNULL(location_id,'NULL') FROM suppliers WHERE code='QS1'")" = NULL ] && ok "shared mode: supplier is business-wide" || bad "shared" "$(q "SELECT location_id FROM suppliers WHERE code='QS1'")"
setcfg supplier_scope_mode branch
post "$A" suppliers/save -d "name=QA Branch Sup" -d code=QB1 -d credit_limit=0 -d opening_balance=0 -d is_active=1 >/dev/null
[ "$(q "SELECT location_id FROM suppliers WHERE code='QB1'")" = "$LOC" ] && ok "branch mode: supplier belongs to MAIN only" || bad "branch" "$(q "SELECT location_id FROM suppliers WHERE code='QB1'")"
setcfg supplier_scope_mode both
post "$A" suppliers/save -d "name=QA Both Branch" -d code=QX1 -d supplier_scope=branch -d credit_limit=0 -d opening_balance=0 -d is_active=1 >/dev/null
post "$A" suppliers/save -d "name=QA Both Shared" -d code=QX2 -d supplier_scope=business -d credit_limit=0 -d opening_balance=0 -d is_active=1 >/dev/null
[ "$(q "SELECT location_id FROM suppliers WHERE code='QX1'")" = "$LOC" ] && [ "$(q "SELECT IFNULL(location_id,'NULL') FROM suppliers WHERE code='QX2'")" = NULL ] && ok "both mode: chosen per supplier" || bad "both" ""
post "$A" shop-context/select -d "location_id=$LOC2" >/dev/null
P=$(curl -s -b "$A" $B/suppliers)
echo "$P" | grep -q "QA Shared Sup" && ! echo "$P" | grep -q "QA Branch Sup" && ok "branch QA-B2 sees shared suppliers but not MAIN's branch-only supplier" || bad "visibility" "list wrong"
QB1=$(q "SELECT id FROM suppliers WHERE code='QB1'")
curl -s -o /dev/null -w '%{http_code}' -b "$A" $B/suppliers/ledger/$QB1 | grep -q 404 && ok "MAIN's branch-only supplier ledger is 404 from QA-B2" || bad "ledger isolation" ""
post "$A" supplier-payments/save -d "supplier_id=$QB1" -d amount=1 -d payment_mode=cash >/dev/null
flash "$A" supplier-payments | grep -q "invalid or inactive" && ok "QA-B2 cannot pay MAIN's branch-only supplier" || bad "payment isolation" "$(flash "$A" supplier-payments)"
post "$A" shop-context/select -d "location_id=$LOC" >/dev/null
for k in backdate_mode backdate_max_days supplier_scope_mode; do post "$A" configuration/reset -d scope=business -d "owner_id=$BIZ" -d "setting_key=$k" >/dev/null; done
[ "$(q "SELECT COUNT(*) FROM business_settings WHERE business_id=$BIZ")" = 0 ] && ok "business settings reset to defaults" || bad "reset" ""

echo; echo "RESULT: $PASS passed, $FAIL failed"
