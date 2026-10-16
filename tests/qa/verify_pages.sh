#!/usr/bin/env bash
# Every menu page and the main drill-down/data pages load for the System Administrator with
# HTTP 200 and no PHP/database exception. In CI this runs on MySQL 8, which rejects SQL that
# MariaDB accepts (reserved words such as EMPTY or LAST_VALUE). Run after a data fixture
# (run_all.sh runs verify_hq.sh first) so the queries see real rows.
set -u
source "$(dirname "$0")/env.sh"
T="$QA_TMP/pages"; mkdir -p "$T"
PASS=0; FAIL=0
ok(){ echo "PASS  $1"; PASS=$((PASS+1)); }
bad(){ echo "FAIL  $1 :: $2"; FAIL=$((FAIL+1)); }
q(){ local r="" i; for i in 1 2 3 4; do r=$($M -e "$1" | tr -d '\r'); [ -n "$r" ] && break; done; printf '%s' "$r"; }
login(){ rm -f "$1"; local tok; tok=$(curl -s -c "$1" -b "$1" $B/login | grep -oE 'name="csrf_test_name" value="[^"]+"' | sed -E 's/.*value="([^"]+)"/\1/'); curl -s -o /dev/null -w '%{url_effective}' -L -c "$1" -b "$1" --data-urlencode "login=$2" --data-urlencode "password=$3" --data-urlencode "csrf_test_name=$tok" $B/login; }
ERR='DatabaseException|ErrorException|Fatal error|Uncaught|Whoops|syntax to use near|Undefined (array key|variable|index)'
page(){ # path
  local code; code=$(curl -s -o "$T/page.html" -w '%{http_code}' -b "$A" -c "$A" "$B/$1")
  if [ "$code" = 200 ] && ! grep -qE "$ERR" "$T/page.html"; then ok "$1"
  else bad "$1" "HTTP $code $(grep -oE "\"message\": *\"[^\"]{0,160}|($ERR)[^<]{0,160}" "$T/page.html" | head -1)"; fi; }

qa_clear_throttle
A="$T/admin.txt"; login "$A" admin admin123 | grep -q dashboard && ok "admin login" || bad "admin login" ""
curl -s -b "$A" -c "$A" "$B/dashboard" -o "$T/dash.html"
MENU=$(grep -oE 'href="[^"]+" class="app-nav-link' "$T/dash.html" | sed -E 's/href="([^"]+)".*/\1/; s#^https?://[^/]+/##' | grep -v '^logout' | sort -u)
[ "$(echo "$MENU" | grep -c .)" -gt 20 ] && ok "menu has $(echo "$MENU" | grep -c .) pages" || bad "menu" "$(echo "$MENU" | grep -c .) links"
for p in $MENU; do page "$p"; done

echo "--- drill-down and data pages"
TODAY=$(date +%Y-%m-%d); MONTH=$(date +%Y-%m-01)
C6=$(q "SELECT id FROM cylinder_types WHERE code='C6' LIMIT 1")
UNIT=$(q "SELECT id FROM cylinder_units ORDER BY id LIMIT 1")
CUST=$(q "SELECT id FROM customers WHERE location_id=(SELECT id FROM locations WHERE code='MAIN') ORDER BY id DESC LIMIT 1")
SUP=$(q "SELECT id FROM suppliers ORDER BY id LIMIT 1")
SALE=$(q "SELECT id FROM sales WHERE location_id=(SELECT id FROM locations WHERE code='MAIN') ORDER BY id LIMIT 1")
for p in "dashboard?from=$MONTH&to=$TODAY" "reports?from=$MONTH&to=$TODAY" "reports/branches?from=$MONTH&to=$TODAY" "reports/branches?export=csv" \
         "reports/inventory-detail" "reports/custody" "cash/history?from=$MONTH&to=$TODAY" \
         "sales/history/data?from=$MONTH&to=$TODAY" "inventory/cylinders/$C6" "inventory/type-history/$C6" "inventory/cylinder/$UNIT" \
         ${CUST:+"customers/ledger/$CUST" "reports/ledger/$CUST" "sales/customer-balance/$CUST" "sales/custody/$CUST"} \
         ${SUP:+"suppliers/ledger/$SUP"} ${SALE:+"sales/details/$SALE" "sales/receipt/$SALE" "sales/receipt/$SALE?format=a4"}; do
  page "$p"
done

echo
echo "RESULT: $PASS passed, $FAIL failed"
