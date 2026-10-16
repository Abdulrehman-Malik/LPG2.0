#!/usr/bin/env bash
# Phase 9.3 / 9.1 verification: user branch access, branch setup checklist, expense categories,
# cash-close and ledger scope fixes, sidebar highlighting.
set -u  # no pipefail: "curl | grep -q" would fail on SIGPIPE after an early match
source "$(dirname "$0")/env.sh"
T="$QA_TMP/p9ba"; mkdir -p "$T"
PASS=0; FAIL=0
ok(){ echo "PASS  $1"; PASS=$((PASS+1)); }
bad(){ echo "FAIL  $1 :: $2"; FAIL=$((FAIL+1)); }
q(){ local r="" i; for i in 1 2 3 4; do r=$($M -e "$1" | tr -d '\r'); [ -n "$r" ] && break; done; printf '%s' "$r"; }
login(){ rm -f "$1"; local tok; tok=$(curl -s -c "$1" -b "$1" $B/login | grep -oE 'name="csrf_test_name" value="[^"]+"' | sed -E 's/.*value="([^"]+)"/\1/'); curl -s -o /dev/null -w '%{url_effective}' -L -c "$1" -b "$1" --data-urlencode "login=$2" --data-urlencode "password=$3" --data-urlencode "csrf_test_name=$tok" $B/login; }
csrf(){ grep -oE 'csrf_cookie_name\s+\S+' "$1" | awk '{print $2}'; }
post(){ local c=$1 path=$2; shift 2; curl -s -o "$T/post_out.html" -w '%{http_code}' -b "$c" -c "$c" --data-urlencode "csrf_test_name=$(csrf "$c")" "$@" "$B/$path"; }
flash(){ curl -s -b "$1" -c "$1" "$B/${2:-dashboard}" | grep -oE 'alert-(success|danger|warning)[^>]*>[^<]*(<[^>]*>[^<]*){0,3}' | sed -E 's/<[^>]*>//g' | head -1; }
use(){ post "$A" shop-context/select -d "location_id=$1" >/dev/null; }

A="$T/admin.txt"; login "$A" admin admin123 | grep -q dashboard && ok "admin login" || bad "admin login" ""
BIZ=$(q "SELECT id FROM businesses WHERE code='DEFAULT'"); MAIN=$(q "SELECT id FROM locations WHERE code='MAIN'")
post "$A" business-management/shop/save -d "business_id=$BIZ" -d code=QA-B2 -d "name=QA Branch Two" >/dev/null
B2=$(q "SELECT id FROM locations WHERE code='QA-B2'")
CASHIER=$(q "SELECT id FROM roles WHERE code='CASHIER'")

echo "--- 9.3.2 branch access from the Users screen"
curl -s -b "$A" $B/users | grep -q 'name="branch_ids\[\]"' && ok "user form shows branch access checkboxes" || bad "branch checkboxes" ""
post "$A" users/save -d "full_name=QA Two Branch Cashier" -d username=qa_two -d "role_id=$CASHIER" -d password=Secret12345 -d is_active=1 -d branch_access_submitted=1 -d "branch_ids[]=$MAIN" -d "branch_ids[]=$B2" -d "default_branch_id=$B2" >/dev/null
U=$(q "SELECT id FROM users WHERE username='qa_two'")
[ -n "$U" ] && [ "$(q "SELECT GROUP_CONCAT(location_id ORDER BY location_id) FROM user_shop_access WHERE user_id=$U")" = "$MAIN,$B2" ] && [ "$(q "SELECT location_id FROM user_shop_access WHERE user_id=$U AND is_default=1")" = "$B2" ] && [ "$(q "SELECT location_id FROM users WHERE id=$U")" = "$B2" ] && ok "new user has access to MAIN and QA-B2, default QA-B2" || bad "create with branches" "$(flash "$A" users)"
X="$T/two.txt"; login "$X" qa_two Secret12345 >/dev/null
curl -s -o "$T/two_dash.html" -w "%{http_code}" -b "$X" $B/dashboard > "$T/two_code.txt"; grep -q "QA Branch Two" "$T/two_dash.html" && ok "user signs in to the default branch QA-B2" || bad "default branch sign-in" "HTTP $(cat "$T/two_code.txt") $(grep -oE '"message": *"[^"]{0,200}|<title>[^<]*' "$T/two_dash.html" | head -1)"
post "$A" users/save -d "id=$U" -d "full_name=QA Two Branch Cashier" -d username=qa_two -d "role_id=$CASHIER" -d is_active=1 -d branch_access_submitted=1 -d "branch_ids[]=$MAIN" -d "default_branch_id=$MAIN" >/dev/null
[ "$(q "SELECT GROUP_CONCAT(location_id) FROM user_shop_access WHERE user_id=$U")" = "$MAIN" ] && ok "edit removes QA-B2 access and moves default to MAIN" || bad "edit branches" "$(q "SELECT GROUP_CONCAT(location_id) FROM user_shop_access WHERE user_id=$U")"
curl -s -b "$X" $B/dashboard | grep -q "Main LPG Shop" && ok "signed-in user is moved to an allowed branch on the next request" || bad "stale branch repair" ""
post "$A" users/save -d "id=$U" -d "full_name=QA Two Branch Cashier" -d username=qa_two -d "role_id=$CASHIER" -d is_active=1 -d branch_access_submitted=1 >/dev/null
[ "$(q "SELECT GROUP_CONCAT(location_id) FROM user_shop_access WHERE user_id=$U")" = "$MAIN" ] && ok "saving with no branch selected is refused ($(flash "$A" users))" || bad "no branch" "access removed"
post "$A" users/save -d "id=$U" -d "full_name=QA Two Branch Cashier" -d username=qa_two -d "role_id=$CASHIER" -d is_active=1 -d branch_access_submitted=1 -d "branch_ids[]=$MAIN" -d "default_branch_id=$B2" >/dev/null
[ "$(q "SELECT location_id FROM users WHERE id=$U")" = "$MAIN" ] && ok "default outside the selected branches is refused" || bad "bad default" ""

echo "--- 9.3.3 branch setup checklist"
P=$(curl -s -b "$A" $B/business-management)
echo "$P" | grep -q "Ready" && ok "MAIN shows Ready" || bad "MAIN ready" ""
echo "$P" | grep -q "Gas rate" && echo "$P" | grep -q "Cylinder stock" && ok "QA-B2 shows missing Gas rate and Cylinder stock" || bad "QA-B2 missing items" ""
echo "$P" | grep -q "Open branch to finish setup" && ok "switch link offered for QA-B2" || bad "switch link" ""

echo "--- 9.3 expense categories"
post "$A" expenses/categories/save -d code=fuel -d "name=Vehicle fuel" >/dev/null
[ "$(q "SELECT CONCAT(code,'|',name,'|',business_id) FROM expense_categories WHERE code='FUEL'")" = "FUEL|Vehicle fuel|$BIZ" ] && ok "category created for this business (code upper-cased)" || bad "create category" "$(flash "$A" expenses/categories)"
post "$A" expenses/categories/save -d code=FUEL -d "name=Duplicate" >/dev/null
[ "$(q "SELECT COUNT(*) FROM expense_categories WHERE code='FUEL'")" = 1 ] && ok "duplicate code refused" || bad "duplicate" ""
FUEL=$(q "SELECT id FROM expense_categories WHERE code='FUEL'")
post "$A" "expenses/categories/$FUEL/toggle" >/dev/null
N=$(q "SELECT COUNT(*) FROM expenses"); post "$A" expenses/save -d "category_id=$FUEL" -d amount=5 -d payment_mode=cash >/dev/null
[ "$(q "SELECT is_active FROM expense_categories WHERE id=$FUEL")" = 0 ] && [ "$(q "SELECT COUNT(*) FROM expenses")" = "$N" ] && ok "deactivated category cannot be used" || bad "deactivate" ""
H=$(curl -s -b "$A" $B/expenses/categories | grep -oE 'href="[^"]*" class="app-nav-link active"'); [ "$(echo "$H" | grep -c .)" = 1 ] && echo "$H" | grep -q 'expenses/categories"' && ok "sidebar highlights only Expense Categories" || bad "sidebar" "$H"
[ "$(curl -s -b "$A" $B/expenses/categories | grep -c 'nav-link active')" = 1 ] && ok "exactly one active menu entry" || bad "active count" "$(curl -s -b "$A" $B/expenses/categories | grep -c 'nav-link active')"

echo "--- 9.1 scope fixes"
S1=$(q "SELECT id FROM cash_sessions WHERE status='open' LIMIT 1")
use $B2; post "$A" cash/close -d "session_id=$S1" -d counted_cash=0 >/dev/null
[ "$(q "SELECT status FROM cash_sessions WHERE id=$S1")" = open ] && ok "QA-B2 cannot close MAIN's cash session ($(flash "$A" cash))" || bad "cash close scope" "closed"
CUST=$(q "SELECT id FROM customers WHERE code='QA-E2E-CUST'")
[ "$(curl -s -o /dev/null -w '%{http_code}' -b "$A" $B/reports/ledger/$CUST)" = 404 ] && ok "QA-B2 cannot open MAIN customer's report ledger (404)" || bad "ledger scope" ""
use $MAIN
[ "$(curl -s -o /dev/null -w '%{http_code}' -b "$A" $B/reports/ledger/$CUST)" = 200 ] && ok "MAIN can open its own customer's ledger" || bad "ledger own" ""

echo; echo "RESULT: $PASS passed, $FAIL failed"
