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

schema(){ q "SET SESSION group_concat_max_len=100000000;
SELECT MD5(GROUP_CONCAT(x ORDER BY x SEPARATOR '|')) FROM (
 SELECT CONCAT_WS(':','C',TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,IFNULL(COLUMN_DEFAULT,'~'),EXTRA) x FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME NOT IN ('migrations','lpg_migration_history')
 UNION ALL SELECT CONCAT_WS(':','I',TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX,COLUMN_NAME,NON_UNIQUE) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME NOT IN ('migrations','lpg_migration_history')
 UNION ALL SELECT CONCAT_WS(':','K',TABLE_NAME,CONSTRAINT_NAME,CONSTRAINT_TYPE) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME NOT IN ('migrations','lpg_migration_history')
) t"; }
BRANCH_TABLES="customers sales purchases customer_receipts supplier_payments expenses cash_registers cylinder_units inventory_movements inventory_opening_balances cylinder_custody customer_security_deposits document_sequences shop_settings inventory_policies inventory_wastage_logs shop_purchase_void_users supplier_opening_balances shop_setting_overrides rate_cards rate_change_log audit_logs"
NULLABLE_TABLES=" rate_cards rate_change_log audit_logs "
# Rows whose tenant disagrees with their branch (or is missing), summed over every branch-owned table.
mismatches(){ local t n=0; for t in $BRANCH_TABLES; do n=$((n + $(q "SELECT COUNT(*) FROM \`$t\` x LEFT JOIN locations l ON l.id=x.location_id WHERE (x.location_id IS NOT NULL AND (x.business_id IS NULL OR x.business_id<>l.business_id))"))); done; echo $n; }

echo "--- BL-009 business_id on branch-owned tables"
missing=""; wrongnull=""
for t in $BRANCH_TABLES; do
  nn=$(q "SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='business_id'")
  [ -z "$nn" ] && missing+=" $t"
  if [[ "$NULLABLE_TABLES" == *" $t "* ]]; then [ "$nn" = YES ] || wrongnull+=" $t"; else [ "$nn" = NO ] || wrongnull+=" $t"; fi
done
[ -z "$missing" ] && ok "all 22 branch-owned tables have business_id" || bad "business_id column" "missing:$missing"
[ -z "$wrongnull" ] && ok "business_id is NOT NULL except on rate_cards, rate_change_log, audit_logs" || bad "nullability" "$wrongnull"
[ "$(mismatches)" = 0 ] && ok "every existing row carries its branch's business" || bad "backfill" "$(mismatches) mismatching rows"
[ "$(q "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND CONSTRAINT_TYPE='FOREIGN KEY' AND CONSTRAINT_NAME LIKE 'fk\_%\_branch_business'")" = 22 ] && ok "22 composite (location_id, business_id) foreign keys" || bad "composite fks" "$(q "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND CONSTRAINT_NAME LIKE 'fk\_%\_branch_business'")"
# A second business to try cross-tenant writes against.
w "INSERT INTO businesses(code,name) VALUES('QA-T2','QA Tenant Two')"; BIZ2=$(q "SELECT id FROM businesses WHERE code='QA-T2'")
w "INSERT INTO locations(business_id,code,name) VALUES($BIZ2,'QA-T2-1','QA Tenant Two Shop')"; LOC2=$(q "SELECT id FROM locations WHERE code='QA-T2-1'")
MAIN=$(q "SELECT id FROM locations WHERE code='MAIN'")
err=$($M -e "INSERT INTO customers(location_id,business_id,code,name) VALUES($MAIN,$BIZ2,'QA-X','Cross tenant')" 2>&1)
echo "$err" | grep -q "foreign key constraint fails" && ok "insert naming one business's branch with another business is rejected" || bad "cross insert" "$err"
UNIT=$(q "SELECT id FROM cylinder_units WHERE location_id=$MAIN LIMIT 1")
err=$($M -e "UPDATE cylinder_units SET location_id=$LOC2 WHERE id=$UNIT" 2>&1)
echo "$err" | grep -q "foreign key constraint fails" && [ "$(q "SELECT location_id FROM cylinder_units WHERE id=$UNIT")" = "$MAIN" ] && ok "moving a cylinder to another business's branch is rejected" || bad "cross move" "$err"
# The application's driver stamps the tenant on inserts that do not set it (temporary QA command).
cp "$QA_ROOT/tests/qa/commands/QaTenantStamp.php" "$QA_ROOT/app/Commands/QaTenantStamp.php"
STAMP=$(qa_spark qa:tenant-stamp "$LOC2" 2>&1 | grep -o 'stamped:[0-9a-z]*' | tail -1)
rm -f "$QA_ROOT/app/Commands/QaTenantStamp.php"
[ "$STAMP" = "stamped:$BIZ2" ] && ok "Query Builder insert without business_id is stamped with the branch's business" || bad "builder stamp" "$STAMP"
w "DELETE FROM customers WHERE code='QA-STAMP'"; w "DELETE FROM locations WHERE id=$LOC2"; w "DELETE FROM businesses WHERE id=$BIZ2"
FULL=$(schema)
rollback_to BusinessIdOnBranchTables && ok "BL-009 rolled back" || bad "rollback 009" "still applied"
[ "$(q "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='business_id' AND TABLE_NAME IN ('customers','sales','inventory_movements','audit_logs')")" = 0 ] && ok "rollback removes business_id from branch-owned tables" || bad "rollback columns" "still present"
BEFORE=$(schema)
qa_spark migrations:run >/dev/null 2>&1
[ "$(schema)" = "$FULL" ] && ok "re-applying BL-009 gives the identical schema" || bad "reapply" "schema differs"
rollback_to BusinessIdOnBranchTables; [ "$(schema)" = "$BEFORE" ] && ok "second rollback gives the identical pre-BL-009 schema" || bad "rollback twice" "schema differs"
qa_spark migrations:run >/dev/null 2>&1

bash "$DIR/reset_db.sh" >/dev/null 2>&1
echo; echo "RESULT: $PASS passed, $FAIL failed"
[ "$FAIL" = 0 ]
