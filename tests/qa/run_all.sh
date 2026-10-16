#!/usr/bin/env bash
# Runs every QA suite against the disposable QA database, resetting it between suites.
#   tests/qa/run_all.sh            all suites
#   tests/qa/run_all.sh --no-e2e   skip the Playwright runtime spec (tests/e2e)
#   tests/qa/run_all.sh --a11y     also run the accessibility audit (report only)
#   tests/qa/run_all.sh --suites-only   only the shell business-logic suites (no browser; used by CI)
# Exits 1 when any suite reports a failure.
# Needs: the app served at $QA_BASE_URL against $QA_DB, mysql and php on PATH (or QA_MYSQL/QA_PHP),
# and `npm install` in tests/e2e (Playwright + axe-core).
set -u
DIR="$(cd "$(dirname "$0")" && pwd)"
source "$DIR/env.sh"
mkdir -p "$QA_TMP"
RESULTS=()
FAILED=0
reset(){ local out; out=$(bash "$DIR/reset_db.sh" 2>&1) || { echo "reset_db.sh failed:"; echo "$out" | tail -5; echo "$out" | tail -5 | gh_annotate "reset_db.sh"; exit 1; }; }
# GitHub shows at most 10 annotations per step, so each suite's failures go into one annotation.
gh_annotate(){ [ -n "${GITHUB_ACTIONS:-}" ] || return 0; local line msg=""; while IFS= read -r line; do [ -n "$line" ] && msg+="${line//%/%25}%0A"; done; [ -n "$msg" ] && echo "::error title=$1::$msg"; return 0; }
record(){ RESULTS+=("$(printf '%-24s %s' "$1" "$2")"); echo "== $1: $2"; if [[ "$2" =~ [1-9][0-9]*\ failed ]]; then FAILED=1; fi; }
shell_suite(){ reset; for pre in "${@:2}"; do bash "$DIR/$pre.sh" >/dev/null 2>&1; done; local out; out=$(bash "$DIR/$1.sh" 2>&1); echo "$out" | grep '^FAIL' | tee >(gh_annotate "$1"); [ "$(echo "$out" | grep -c '^PASS')" = 0 ] && echo "$out" | tail -5 | gh_annotate "$1 (no PASS lines)"; record "$1" "$(echo "$out" | grep -c '^PASS') passed, $(echo "$out" | grep -c '^FAIL') failed"; }
node_check(){ local name=$1; shift; reset; "$@"; local out; out=$(cd "$DIR/checks" && node "$name.mjs" 2>&1); echo "$out" | grep '^FAIL'; record "$name" "$(echo "$out" | tail -1)"; }

SUITES_ONLY=0; [[ " $* " == *" --suites-only "* ]] && SUITES_ONLY=1
if [[ " $* " != *" --no-e2e "* && $SUITES_ONLY = 0 ]]; then
  reset
  out=$(cd "$QA_ROOT/tests/e2e" && LPG_QA_BASE_URL="$B" npx playwright test lpg-runtime.spec.mjs --workers=1 --reporter=list 2>&1); echo "$out" > "$QA_TMP/playwright.txt"; echo "$out" | grep -E "^\s+(✘|x) "
  record "playwright e2e" "$(echo "$out" | grep -E '[0-9]+ (passed|failed)' | tr '\n' ' ')"
fi
shell_suite verify_phase8
shell_suite verify_phase9
shell_suite verify_transfers
shell_suite verify_hq
shell_suite verify_branch_admin
shell_suite verify_business_flow
shell_suite verify_pages verify_hq
if [ $SUITES_ONLY = 0 ]; then
node_check pos_check true
node_check history_check true
node_check cash_check true
node_check hier_check bash "$DIR/verify_branch_admin.sh"
node_check parties_check eval 'bash "$DIR/verify_phase9.sh" >/dev/null 2>&1; bash "$DIR/verify_hq.sh" >/dev/null 2>&1; $M < "$DIR/fixtures/parties.sql"'
node_check shell_check bash "$DIR/verify_hq.sh"
fi
if [[ " $* " == *" --a11y "* ]]; then reset; (cd "$DIR/checks" && node a11y_check.mjs) | tee "$QA_TMP/a11y-report.txt" | head -1; fi
echo; echo "SUMMARY"; printf '%s\n' "${RESULTS[@]}"
exit $FAILED
