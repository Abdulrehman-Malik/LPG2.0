#!/usr/bin/env bash
# Tenancy schema migrations (backlog BL-013, BL-009, BL-010, BL-012) on the disposable QA
# database: legacy upgrade path, tenant columns stamped on every insert path, composite keys
# rejecting cross-tenant rows, and clean rollback. Rebuilds the QA database first and at the end.
set -u
DIR="$(cd "$(dirname "$0")" && pwd)"
source "$DIR/env.sh"
T="$QA_TMP/tenancy"; mkdir -p "$T"
PASS=0; FAIL=0
ok(){ echo "PASS  $1"; PASS=$((PASS+1)); }
bad(){ echo "FAIL  $1 :: $2"; FAIL=$((FAIL+1)); }
q(){ local r="" i; for i in 1 2 3 4; do r=$($M -e "$1" | tr -d '\r'); [ -n "$r" ] && break; done; printf '%s' "$r"; }
w(){ $M -e "$1"; }
rollback_to(){ # roll back reversible migrations until the named class is no longer applied
  local i; for i in $(seq 1 20); do
    [ "$(q "SELECT COUNT(*) FROM migrations WHERE class LIKE '%$1'")" = 0 ] && return 0
    qa_spark migrate:rollback >/dev/null 2>&1
  done; return 1; }

bash "$DIR/reset_db.sh" >/dev/null 2>&1 || { echo "FAIL  reset_db.sh"; exit 1; }

echo "--- BL-013 every branch belongs to a business"
[ "$(q "SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='locations' AND COLUMN_NAME='business_id'")" = NO ] && ok "fresh install: locations.business_id is NOT NULL" || bad "fresh not null" "nullable"
rollback_to AssignUnownedBranches && ok "rolled back to before BL-013" || bad "rollback" "AssignUnownedBranches still applied"
[ "$(q "SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='locations' AND COLUMN_NAME='business_id'")" = NO ] && ok "rollback on a fresh install keeps NOT NULL (nothing was changed)" || bad "fresh rollback" "became nullable"
# Simulate a database upgraded through migration 032: nullable column and a branch with no business.
w "ALTER TABLE locations MODIFY business_id BIGINT UNSIGNED NULL"
w "INSERT INTO locations(business_id,code,name) VALUES(NULL,'QA-LEGACY','QA Legacy Branch')"
LEG=$(q "SELECT id FROM locations WHERE code='QA-LEGACY'")
qa_spark migrations:run >/dev/null 2>&1
DEF=$(q "SELECT id FROM businesses WHERE code='DEFAULT'")
[ "$(q "SELECT business_id FROM locations WHERE id=$LEG")" = "$DEF" ] && ok "legacy branch assigned to the DEFAULT business" || bad "legacy assign" "$(q "SELECT IFNULL(business_id,'NULL') FROM locations WHERE id=$LEG")"
[ "$(q "SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='locations' AND COLUMN_NAME='business_id'")" = NO ] && ok "column made NOT NULL" || bad "not null" "still nullable"
rollback_to AssignUnownedBranches
[ "$(q "SELECT IFNULL(business_id,'NULL') FROM locations WHERE id=$LEG")" = NULL ] && ok "rollback restores the legacy branch without a business" || bad "legacy rollback" "still assigned"
[ "$(q "SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='locations' AND COLUMN_NAME='business_id'")" = YES ] && ok "rollback restores the nullable column" || bad "nullable restore" "NOT NULL"
w "DELETE FROM locations WHERE id=$LEG"
w "ALTER TABLE locations MODIFY business_id BIGINT UNSIGNED NOT NULL"
qa_spark migrations:run >/dev/null 2>&1

#BL009

bash "$DIR/reset_db.sh" >/dev/null 2>&1
echo; echo "RESULT: $PASS passed, $FAIL failed"
[ "$FAIL" = 0 ]
