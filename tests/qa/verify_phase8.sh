#!/usr/bin/env bash
# Phase 8 runtime verification against the disposable QA database (tests/qa/env.sh) and the
# local dev server on :8080. Run reset_qa_db.sh first.
set -u  # no pipefail: "curl | grep -q" would fail on SIGPIPE after an early match
source "$(dirname "$0")/env.sh"
T="$QA_TMP/p8"; mkdir -p "$T"
PASS=0; FAIL=0
ok(){ echo "PASS  $1"; PASS=$((PASS+1)); }
bad(){ echo "FAIL  $1 :: $2"; FAIL=$((FAIL+1)); }
# mysql.exe under Git Bash occasionally returns nothing (no error); retry empty results.
q(){ local r="" i; for i in 1 2 3 4; do r=$($M -e "$1" | tr -d ''); [ -n "$r" ] && break; done; printf '%s' "$r"; }
sid(){ grep -oE '"id": *[0-9]+' | grep -oE '[0-9]+'; }

login(){ # $1 cookie file, $2 user, $3 password -> prints final URL
  rm -f "$1"
  local tok; tok=$(curl -s -c "$1" -b "$1" $B/login | grep -oE 'name="csrf_test_name" value="[^"]+"' | sed -E 's/.*value="([^"]+)"/\1/')
  curl -s -o "$T/login_out.html" -w '%{url_effective}' -L -c "$1" -b "$1" --data-urlencode "login=$2" --data-urlencode "password=$3" --data-urlencode "csrf_test_name=$tok" $B/login
}
csrf(){ grep -oE 'csrf_cookie_name\s+\S+' "$1" | awk '{print $2}'; }
sale(){ # $1 cookie, $2 transaction_type, $3 customer_id, $4 lines_json, $5 payments_json, [$6 extra curl args...]
  local c=$1; shift; local type=$1 cust=$2 lines=$3 pays=$4; shift 4
  curl -s -b "$c" -c "$c" -H 'Accept: application/json' -H 'X-Requested-With: XMLHttpRequest' \
    --data-urlencode "csrf_test_name=$(csrf "$c")" --data-urlencode "transaction_type=$type" \
    --data-urlencode "customer_id=$cust" --data-urlencode "lines_json=$lines" --data-urlencode "payments_json=$pays" \
    --data-urlencode "transaction_at=$(date '+%Y-%m-%d %H:%M:%S')" "$@" $B/sales/save
}
post(){ local c=$1 path=$2; shift 2; curl -s -o "$T/post_out.html" -w '%{http_code}' -b "$c" -c "$c" --data-urlencode "csrf_test_name=$(csrf "$c")" "$@" "$B/$path"; }

A="$T/admin.txt"
login "$A" admin admin123 | grep -q dashboard && ok "admin login" || bad "admin login" "did not reach dashboard"

LOC=$(q "SELECT id FROM locations WHERE code='MAIN'")
C6=$(q "SELECT id FROM cylinder_types WHERE code='C6'")
U1=$(q "SELECT id FROM cylinder_units WHERE unit_code='QA-E2E-C6-001'")
UE=$(q "SELECT id FROM cylinder_units WHERE unit_code='QA-ORDER-EMPTY-C6-001'")
CUST=$(q "SELECT id FROM customers WHERE code='QA-E2E-CUST'")
CREDIT=$(q "SELECT id FROM customers WHERE code='QA-E2E-CREDIT'")
gasline(){ echo "[{\"cylinder_type_id\":\"$C6\",\"source_cylinder_unit_id\":\"$U1\",\"quantity\":\"$1\",\"gas_entry_mode\":\"quantity\",\"gas_rate\":\"\"}]"; }
cashpay(){ echo "[{\"payment_type\":\"sale\",\"payment_mode\":\"cash\",\"amount\":\"$1\"}]"; }
gas(){ q "SELECT CONCAT(status,':',gas_weight_kg) FROM cylinder_units WHERE id=$U1"; }

echo "--- 8.3.3 void cash goes to the current open session, closed session untouched"
R=$(sale "$A" gas_sale "" "$(gasline 1)" "$(cashpay 100)"); SA=$(echo "$R" | sid)
[ -n "$SA" ] && ok "sale A posted ($R)" || bad "sale A" "$R"
S1=$(q "SELECT id FROM cash_sessions WHERE status='open'")
post "$A" cash/close -d "session_id=$S1" -d counted_cash=100 >/dev/null
post "$A" cash/open -d opening_cash=0 >/dev/null
S2=$(q "SELECT id FROM cash_sessions WHERE status='open'"); echo "   debug: S1=[$S1] S2=[$S2]"
S1SUM_BEFORE=$(q "SELECT SUM(CASE WHEN direction='in' THEN amount ELSE -amount END) FROM cash_transactions WHERE cash_session_id=$S1")
post "$A" "sales/void/$SA" -d "void_reason=QA phase 8 void" >/dev/null
[ "$(q "SELECT status FROM sales WHERE id=$SA")" = voided ] && ok "sale A voided" || bad "sale A void" "$(grep -o 'alert[^<]*<[^<]*' $T/post_out.html | head -2)"
S1SUM_AFTER=$(q "SELECT SUM(CASE WHEN direction='in' THEN amount ELSE -amount END) FROM cash_transactions WHERE cash_session_id=$S1")
[ "$S1SUM_BEFORE" = "$S1SUM_AFTER" ] && ok "closed session $S1 unchanged ($S1SUM_AFTER)" || bad "closed session changed" "$S1SUM_BEFORE -> $S1SUM_AFTER"
[ "$(q "SELECT COUNT(*) FROM cash_transactions WHERE cash_session_id=$S2 AND reference_type='sale_void' AND reference_id=$SA AND direction='out' AND amount=100")" = 1 ] && ok "reversal posted to open session $S2" || bad "reversal session" "$(q "SELECT cash_session_id,amount FROM cash_transactions WHERE reference_type='sale_void'")"
[ "$(gas)" = "filled:6.000" ] && ok "unit restored to 6 kg" || bad "unit restore" "$(gas)"

echo "--- 8.3.2 void refused after later use of the same cylinder (REV02)"
SB=$(sale "$A" gas_sale "" "$(gasline 1)" "$(cashpay 100)" | sid)
SC=$(sale "$A" gas_sale "" "$(gasline 1)" "$(cashpay 100)" | sid)
[ "$(gas)" = "filled:4.000" ] && ok "two later sales took unit to 4 kg" || bad "setup" "$(gas)"
MOV_BEFORE=$(q "SELECT COUNT(*) FROM inventory_movements")
post "$A" "sales/void/$SB" -d "void_reason=QA void older sale" >/dev/null
[ "$(q "SELECT status FROM sales WHERE id=$SB")" = posted ] && ok "older sale B stays posted" || bad "older sale voided" "should be refused"
[ "$(gas)" = "filled:4.000" ] && [ "$(q "SELECT COUNT(*) FROM inventory_movements")" = "$MOV_BEFORE" ] && ok "no stock or movement change after refused void" || bad "partial effects" "$(gas)"
grep -q "used in a later transaction" "$T/post_out.html" || curl -s -b "$A" $B/sales | grep -q "used in a later transaction" && ok "clear refusal message shown" || bad "message" "not found"
post "$A" "sales/void/$SC" -d "void_reason=QA void latest sale" >/dev/null
[ "$(q "SELECT status FROM sales WHERE id=$SC")" = voided ] && [ "$(gas)" = "filled:5.000" ] && ok "latest sale C voids and restores 5 kg" || bad "void latest" "$(gas)"

echo "--- 8.2 removed standard-role permission is not re-granted"
CASHIER=$(q "SELECT id FROM roles WHERE code='CASHIER'"); DV=$(q "SELECT id FROM permissions WHERE code='DASHBOARD_VIEW'")
BEFORE=$(q "SELECT COUNT(*) FROM role_permissions WHERE role_id=$CASHIER")
post "$A" users/role-permissions -d "role_id=$CASHIER" -d "permissions[]=$DV" >/dev/null
curl -s -o /dev/null -b "$A" $B/dashboard; curl -s -o /dev/null -b "$A" $B/users
AFTER=$(q "SELECT COUNT(*) FROM role_permissions WHERE role_id=$CASHIER")
[ "$AFTER" = 1 ] && ok "CASHIER permissions $BEFORE -> 1 and stayed 1 after later requests" || bad "permission re-granted" "count=$AFTER"

echo "--- 8.3.4 / 8.3.5 security deposit issue and refund re-check"
ISSUE="[{\"cylinder_type_id\":\"$C6\",\"cylinder_status\":\"empty\",\"selected_cylinder_unit_ids\":[$UE],\"gas_rate\":\"0\",\"cylinder_rate\":\"0\"}]"
R=$(sale "$A" security_deposit "$CUST" "$ISSUE" "[{\"payment_type\":\"combined\",\"payment_mode\":\"cash\",\"amount\":\"$(q "SELECT 500+rate_value FROM rate_cards WHERE rate_type='cylinder_package' AND cylinder_type_id=$C6 ORDER BY id DESC LIMIT 1")\"}]" --data-urlencode security_deposit_amount=500)
echo "$R" | grep -qE '"success": *true' && ok "security deposit issued ($R)" || bad "deposit issue" "$R"
DEP=$(q "SELECT COALESCE(SUM(CASE WHEN entry_type='hold' THEN amount ELSE -amount END),0) FROM customer_security_deposits WHERE customer_id=$CUST")
RET="[{\"unit_id\":\"$UE\",\"return_gas_kg\":\"0\",\"return_gas_rate\":\"0\"}]"
R=$(sale "$A" cylinder_return "$CUST" "$RET" "[{\"payment_type\":\"security_deposit_refund\",\"payment_mode\":\"cash\",\"amount\":\"9999\"}]" --data-urlencode security_deposit_refund_amount=9999)
echo "$R" | grep -qi "cannot exceed" && ok "over-refund rejected (balance $DEP)" || bad "over-refund" "$R"
R=$(sale "$A" cylinder_return "$CUST" "$RET" "[{\"payment_type\":\"security_deposit_refund\",\"payment_mode\":\"cash\",\"amount\":\"$DEP\"}]" --data-urlencode "security_deposit_refund_amount=$DEP")
echo "$R" | grep -qE '"success": *true' && ok "refund of full balance posted" || bad "refund" "$R"
[ "$(q "SELECT status FROM cylinder_units WHERE id=$UE")" = empty ] && [ "$(q "SELECT COALESCE(SUM(CASE WHEN entry_type='hold' THEN amount ELSE -amount END),0) FROM customer_security_deposits WHERE customer_id=$CUST")" = "0.00" ] && ok "cylinder back in empty stock, deposit balance 0" || bad "return state" "$(q "SELECT status FROM cylinder_units WHERE id=$UE")"
q "SELECT GET_LOCK(CONCAT('x'),0)" >/dev/null # sanity: DB reachable

echo "--- 8.1.3 anonymous migration run rejected"
N="$T/anon.txt"; rm -f "$N"; curl -s -c "$N" -b "$N" $B/login >/dev/null
HB=$(q "SELECT COUNT(*) FROM lpg_migration_history")
code=$(post "$N" migrations/run)
[ "$(q "SELECT COUNT(*) FROM lpg_migration_history")" = "$HB" ] && curl -s -b "$N" $B/login | grep -q "Only a System Administrator" && ok "anonymous request refused (HTTP $code)" || bad "anonymous migrations" "history $HB -> $(q "SELECT COUNT(*) FROM lpg_migration_history")"
code=$(post "$N" migrations/run -d admin_login=admin -d admin_password=wrong)
curl -s -b "$N" $B/login | grep -q "Only a System Administrator" && ok "wrong admin password refused" || bad "wrong password" "accepted"
code=$(post "$N" migrations/run -d admin_login=admin -d admin_password=admin123)
curl -s -b "$N" $B/login | grep -q "up to date" && ok "valid System Administrator accepted" || bad "valid admin" "$(curl -s -b "$N" $B/login | grep -o 'alert[^<]*' | head -3)"

echo "--- 8.1.4 disabled user signed out on next request"
D="$T/mgr.txt"; login "$D" qa_scoped_manager admin123 | grep -q dashboard && ok "manager login" || bad "manager login" "failed"
q "UPDATE users SET is_active=0 WHERE username='qa_scoped_manager'"
url=$(curl -s -o "$T/dis.html" -w '%{redirect_url}' -b "$D" -c "$D" $B/dashboard)
curl -s -b "$D" $B/login | grep -q "has been disabled" && [[ "$url" == *login* ]] && ok "disabled manager redirected to login with message" || bad "disabled user" "$url"
q "UPDATE users SET is_active=1 WHERE username='qa_scoped_manager'"

echo "--- 8.3.1 shop comes from the caller, not the session (CLI, no session)"
cp "$QA_ROOT/tests/qa/commands/QaPhase8Credit.php" "$QA_ROOT/app/Commands/QaPhase8Credit.php"
OUT=$(qa_spark qa:phase8-credit "$LOC" "$CREDIT" "$C6" "$U1" 2>&1)
rm -f "$QA_ROOT/app/Commands/QaPhase8Credit.php"
echo "$OUT" | grep -q "FIRST_OK" && ok "credit sale without session posted under customer mode" || bad "credit no-session" "$OUT"
echo "$OUT" | grep -q "SECOND_REJECTED:.*credit limit" && ok "second credit sale over limit rejected using real balance" || bad "credit limit no-session" "$OUT"

echo "--- 8.1.5 login throttling"
L="$T/thr.txt"
for i in 1 2 3 4 5; do login "$L" admin wrongpass >/dev/null; done
login "$L" admin admin123 >/dev/null; grep -q "Too many failed sign-in attempts" "$T/login_out.html" && ok "6th attempt blocked even with correct password" || bad "throttle" "not blocked"
login "$L" someoneelse wrongpass >/dev/null; grep -q "Invalid username" "$T/login_out.html" && ok "other login names unaffected" || bad "throttle scope" "other user blocked"

qa_clear_throttle # so later runs can sign in
echo; echo "RESULT: $PASS passed, $FAIL failed"
