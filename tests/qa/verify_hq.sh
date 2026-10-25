#!/usr/bin/env bash
# Phase 9.2 Branch Overview verification: report figures vs independent SQL, and branch scope.
set -u  # no pipefail: "curl | grep -q" would fail on SIGPIPE after an early match
source "$(dirname "$0")/env.sh"
T="$QA_TMP/p9hq"; mkdir -p "$T"
PASS=0; FAIL=0
ok(){ echo "PASS  $1"; PASS=$((PASS+1)); }
bad(){ echo "FAIL  $1 :: $2"; FAIL=$((FAIL+1)); }
q(){ local r="" i; for i in 1 2 3 4; do r=$($M -e "$1" | tr -d '\r'); [ -n "$r" ] && break; done; printf '%s' "$r"; }
login(){ rm -f "$1"; local tok; tok=$(curl -s -c "$1" -b "$1" $B/login | grep -oE 'name="csrf_test_name" value="[^"]+"' | sed -E 's/.*value="([^"]+)"/\1/'); curl -s -o /dev/null -w '%{url_effective}' -L -c "$1" -b "$1" --data-urlencode "login=$2" --data-urlencode "password=$3" --data-urlencode "csrf_test_name=$tok" $B/login; }
csrf(){ grep -oE 'csrf_cookie_name\s+\S+' "$1" | awk '{print $2}'; }
post(){ local c=$1 path=$2; shift 2; curl -s -o "$T/post_out.html" -w '%{http_code}' -b "$c" -c "$c" --data-urlencode "csrf_test_name=$(csrf "$c")" "$@" "$B/$path"; }
use(){ post "$A" shop-context/select -d "location_id=$1" >/dev/null; }
sale(){ curl -s -b "$A" -c "$A" -H 'Accept: application/json' -H 'X-Requested-With: XMLHttpRequest' --data-urlencode "csrf_test_name=$(csrf "$A")" --data-urlencode transaction_type=gas_sale --data-urlencode "customer_id=$1" --data-urlencode "lines_json=[{\"cylinder_type_id\":\"$C6\",\"source_cylinder_unit_id\":\"$2\",\"quantity\":\"$3\",\"gas_entry_mode\":\"quantity\",\"gas_rate\":\"${5:-}\"}]" --data-urlencode "payments_json=$4" $B/sales/save | grep -oE '"id": *[0-9]+' | grep -oE '[0-9]+'; }
# CSV value for branch name and column header
csvval(){ awk -F, -v row="$2" -v col="$3" 'NR==2{for(i=1;i<=NF;i++){h=$i;gsub(/"/,"",h);if(h==col)c=i}} NR>2{n=$2;gsub(/"/,"",n);if(n==row)print $c}' "$1"; }
eq(){ # label, actual, expected (numeric compare to 2dp)
  if [ "$(printf '%.2f' "${2:-0}")" = "$(printf '%.2f' "${3:-0}")" ]; then ok "$1 = $2"; else bad "$1" "report $2 vs SQL $3"; fi; }

A="$T/admin.txt"; login "$A" admin admin123 | grep -q dashboard && ok "admin login" || bad "admin login" ""
BIZ=$(q "SELECT id FROM businesses WHERE code='DEFAULT'"); MAIN=$(q "SELECT id FROM locations WHERE code='MAIN'")
C6=$(q "SELECT id FROM cylinder_types WHERE code='C6' AND business_id=$BIZ")
U1=$(q "SELECT id FROM cylinder_units WHERE unit_code='QA-E2E-C6-001'"); U2=$(q "SELECT id FROM cylinder_units WHERE unit_code='QA-ORDER-C6-001'")
CUST=$(q "SELECT id FROM customers WHERE code='QA-E2E-CUST'"); CREDIT=$(q "SELECT id FROM customers WHERE code='QA-E2E-CREDIT'")
post "$A" business-management/shop/save -d "business_id=$BIZ" -d code=QA-B2 -d "name=QA Branch Two" >/dev/null
B2=$(q "SELECT id FROM locations WHERE code='QA-B2'")
post "$A" configuration/save -d scope=business -d "owner_id=$BIZ" -d setting_key=stock_transfer_enabled -d value=true >/dev/null
post "$A" configuration/save -d scope=business -d "owner_id=$BIZ" -d setting_key=stock_transfer_mode -d value=one_step >/dev/null
post "$A" inventory/transfers/create -d "to_location_id=$B2" -d "unit_ids[]=$U2" >/dev/null
[ "$(q "SELECT location_id FROM cylinder_units WHERE id=$U2")" = "$B2" ] && ok "fixture: cylinder moved to QA-B2" || bad "fixture transfer" ""

# MAIN: cash sale 1 kg, credit sale 0.3 kg, a sale that is then voided; expense; receipt
S1=$(sale "" $U1 1 '[{"payment_type":"sale","payment_mode":"cash","amount":"100"}]')
S2=$(sale "$CREDIT" $U1 0.3 '[{"payment_type":"sale","payment_mode":"credit","amount":"0"}]')
S3=$(sale "" $U1 0.5 '[{"payment_type":"sale","payment_mode":"cash","amount":"50"}]')
post "$A" "sales/void/$S3" -d "void_reason=QA hq void" >/dev/null
CAT=$(q "SELECT id FROM expense_categories WHERE business_id=$BIZ AND code='OTHER'")
post "$A" expenses/save -d "category_id=$CAT" -d amount=25 -d payment_mode=cash >/dev/null
post "$A" receipts/save -d "customer_id=$CREDIT" -d amount=10 -d payment_mode=cash >/dev/null
# QA-B2: needs its own open cash session for cash sales
use $B2; post "$A" cash/open -d opening_cash=0 >/dev/null
S4=$(sale "" $U2 2 '[{"payment_type":"sale","payment_mode":"cash","amount":"200"}]' 100)
use $MAIN
[ -n "$S1" ] && [ -n "$S2" ] && [ -n "$S4" ] && ok "fixture sales posted ($S1,$S2,$S3 voided,$S4)" || bad "fixture sales" "$S1/$S2/$S3/$S4"

curl -s -b "$A" "$B/reports/branches?export=csv" -o "$T/hq.csv"
for spec in "MAIN:$MAIN" "QA-B2:$B2"; do
  name=${spec%%:*}; loc=${spec##*:}
  [ "$name" = MAIN ] && bn="Main LPG Shop" || bn="QA Branch Two"
  echo "--- $bn"
  eq "$bn sales count" "$(csvval "$T/hq.csv" "$bn" Sales)" "$(q "SELECT COUNT(*) FROM sales WHERE location_id=$loc AND status='posted' AND transaction_type NOT IN ('security_deposit','cylinder_return')")"
  eq "$bn sales total" "$(csvval "$T/hq.csv" "$bn" 'Sales total')" "$(q "SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE location_id=$loc AND status='posted' AND transaction_type NOT IN ('security_deposit','cylinder_return')")"
  eq "$bn gas KG sold" "$(csvval "$T/hq.csv" "$bn" 'Gas KG sold')" "$(q "SELECT COALESCE(SUM(si.gas_weight_kg),0) FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE s.location_id=$loc AND s.status='posted'")"
  eq "$bn credit created" "$(csvval "$T/hq.csv" "$bn" 'Credit created')" "$(q "SELECT COALESCE(SUM(credit_amount),0) FROM sales WHERE location_id=$loc AND status='posted'")"
  eq "$bn voided" "$(csvval "$T/hq.csv" "$bn" 'Voided sales')" "$(q "SELECT COUNT(*) FROM sales WHERE location_id=$loc AND status='voided'")"
  eq "$bn expenses" "$(csvval "$T/hq.csv" "$bn" Expenses)" "$(q "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE location_id=$loc")"
  eq "$bn customer receipts" "$(csvval "$T/hq.csv" "$bn" 'Customer receipts')" "$(q "SELECT COALESCE(SUM(amount),0) FROM customer_receipts WHERE location_id=$loc AND status='posted'")"
  eq "$bn gas stock" "$(csvval "$T/hq.csv" "$bn" 'Gas stock KG')" "$(q "SELECT COALESCE(SUM(gas_weight_kg),0) FROM cylinder_units WHERE location_id=$loc AND status='filled'")"
  eq "$bn filled" "$(csvval "$T/hq.csv" "$bn" 'Filled cylinders')" "$(q "SELECT COUNT(*) FROM cylinder_units WHERE location_id=$loc AND status='filled'")"
  eq "$bn cash expected" "$(csvval "$T/hq.csv" "$bn" 'Open cash expected')" "$(q "SELECT COALESCE(SUM(CASE WHEN ct.direction='in' THEN ct.amount ELSE -ct.amount END),0) FROM cash_transactions ct JOIN cash_sessions cs ON cs.id=ct.cash_session_id JOIN cash_registers cr ON cr.id=cs.register_id WHERE cr.location_id=$loc AND cs.status='open'")"
  eq "$bn customer OS" "$(csvval "$T/hq.csv" "$bn" 'Customer OS')" "$(q "SELECT COALESCE(SUM(GREATEST(0,c.opening_balance+(SELECT COALESCE(SUM(credit_amount+return_gas_ledger_amount),0) FROM sales s WHERE s.customer_id=c.id AND s.status='posted')-(SELECT COALESCE(SUM(amount),0) FROM customer_receipts r WHERE r.customer_id=c.id AND r.status='posted'))),0) FROM customers c WHERE c.location_id=$loc")"
done
eq "TOTAL sales" "$(csvval "$T/hq.csv" TOTAL 'Sales total')" "$(q "SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE location_id IN ($MAIN,$B2) AND status='posted'")"
[ "${GITHUB_ACTIONS:-}" ] && [ "$FAIL" != 0 ] && head -5 "$T/hq.csv" | cut -c1-300 | sed "s/^/FAIL  csv: /"


echo "--- staff API reports (BL-027)"
apitok(){ curl -s -H 'Content-Type: application/json' -d "{\"username\":\"$1\",\"password\":\"admin123\"}" "$B/api/v1/staff/auth/login" | grep -oE '"access_token": *"[a-f0-9]{64}"' | grep -oE '[a-f0-9]{64}'; }
TOK=$(apitok admin)
# jget FILE PATH: value at a dotted path of a JSON file ("" when absent)
jget(){ node -e 'const v=process.argv[1].split(".").reduce((o,k)=>o==null?o:o[k],JSON.parse(require("fs").readFileSync(0,"utf8")));console.log(v==null?"":typeof v==="object"?JSON.stringify(v):v)' "$2" < "$1"; }
curl -s -H "Authorization: Bearer $TOK" -H "X-Shop-Id: $MAIN" "$B/api/v1/staff/reports/branches" -o "$T/hq_api.json"
for spec in "MAIN:$MAIN:Main LPG Shop" "QA-B2:$B2:QA Branch Two"; do
  loc=$(echo "$spec" | cut -d: -f2); bn=$(echo "$spec" | cut -d: -f3)
  eq "API $bn sales total equals the web report" "$(jget "$T/hq_api.json" "data.branches.$loc.sales_total")" "$(csvval "$T/hq.csv" "$bn" 'Sales total')"
  eq "API $bn gas stock equals the web report" "$(jget "$T/hq_api.json" "data.branches.$loc.gas_stock_kg")" "$(csvval "$T/hq.csv" "$bn" 'Gas stock KG')"
  eq "API $bn customer OS equals the web report" "$(jget "$T/hq_api.json" "data.branches.$loc.customer_os")" "$(csvval "$T/hq.csv" "$bn" 'Customer OS')"
  eq "API $bn voided equals the web report" "$(jget "$T/hq_api.json" "data.branches.$loc.voided_count")" "$(csvval "$T/hq.csv" "$bn" 'Voided sales')"
done
eq "API TOTAL sales equals the web report" "$(jget "$T/hq_api.json" data.totals.sales_total)" "$(csvval "$T/hq.csv" TOTAL 'Sales total')"
FROM=$(date '+%Y-%m-01'); TO=$(date '+%Y-%m-%d')
curl -s -H "Authorization: Bearer $TOK" -H "X-Shop-Id: $MAIN" "$B/api/v1/staff/reports/period?from=$FROM&to=$TO" -o "$T/period_api.json"
eq "API period report MAIN sales total" "$(jget "$T/period_api.json" data.sales.total)" "$(q "SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE location_id=$MAIN AND status='posted' AND transaction_type NOT IN ('security_deposit','cylinder_return') AND transaction_at BETWEEN '$FROM 00:00:00' AND '$TO 23:59:59'")"
curl -s -o "$T/dash_api.json" -w '%{http_code}' -H "Authorization: Bearer $TOK" -H "X-Shop-Id: $MAIN" "$B/api/v1/staff/reports/dashboard" | grep -q 200 && [ "$(jget "$T/dash_api.json" data.from)" = "$TO" ] && ok "API dashboard defaults to today" || bad "API dashboard" "$(head -c 200 "$T/dash_api.json")"
curl -s -o /dev/null -w '%{http_code}' -H "Authorization: Bearer $TOK" -H "X-Shop-Id: $MAIN" "$B/api/v1/staff/reports/custody" | grep -q 200 && ok "API custody report" || bad "API custody" ""
SMT=$(apitok qa_scoped_manager)
curl -s -H "Authorization: Bearer $SMT" "$B/api/v1/staff/reports/branches?branch_ids[]=$B2" -o "$T/hq_scoped.json"
[ -z "$(jget "$T/hq_scoped.json" "data.branches.$B2")" ] && [ -n "$(jget "$T/hq_scoped.json" "data.branches.$MAIN")" ] && ok "API: MAIN-only manager cannot see QA-B2 in the branch overview" || bad "API branch scope" "$(head -c 300 "$T/hq_scoped.json")"

echo "--- branch scope"
SM="$T/scoped.txt"; login "$SM" qa_scoped_manager admin123 >/dev/null
curl -s -b "$SM" "$B/reports/branches?export=csv&branch_ids[]=$B2" -o "$T/scoped.csv"
grep -q "QA Branch Two" "$T/scoped.csv" && bad "scope" "MAIN-only manager sees QA-B2" || ok "MAIN-only manager cannot see QA-B2, even when requesting it"
grep -q "Main LPG Shop" "$T/scoped.csv" && ok "MAIN-only manager sees MAIN" || bad "scope main" "$(head -3 "$T/scoped.csv")"
curl -s -o "$T/branches.html" -w '%{http_code}' -b "$A" "$B/reports/branches?branch_ids[]=$B2" | grep -q 200 && grep -q "QA Branch Two" "$T/branches.html" && ! grep -q '<td class="fw-semibold">Main LPG Shop' "$T/branches.html" && ok "branch filter limits the report to QA-B2" || bad "branch filter" ""

for k in stock_transfer_enabled stock_transfer_mode; do post "$A" configuration/reset -d scope=business -d "owner_id=$BIZ" -d "setting_key=$k" >/dev/null; done
echo; echo "RESULT: $PASS passed, $FAIL failed"
