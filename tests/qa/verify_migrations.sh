#!/usr/bin/env bash
# BL-001: reversible migrations (app/Database/Migrations) run by the login-page migration gate
# after the SQL history, and roll back cleanly. Disposable QA database only (tests/qa/env.sh);
# the dev server must be serving it on $QA_BASE_URL. Rebuilds the QA database first.
# Installs temporary probe migrations and always removes them again.
set -u
DIR="$(cd "$(dirname "$0")" && pwd)"
source "$DIR/env.sh"
T="$QA_TMP/migrations"; mkdir -p "$T"
PASS=0; FAIL=0
ok(){ echo "PASS  $1"; PASS=$((PASS+1)); }
bad(){ echo "FAIL  $1 :: $2"; FAIL=$((FAIL+1)); }
q(){ local r="" i; for i in 1 2 3 4; do r=$($M -e "$1" | tr -d '\r'); [ -n "$r" ] && break; done; printf '%s' "$r"; }
# Rolls back the given CI4 migration class and every later one (each migration is its own batch).
rollback_from(){ local b; b=$(q "SELECT batch FROM migrations WHERE class LIKE '%$1'"); [ -n "$b" ] && qa_spark migrate:rollback -b $((b-1)) >/dev/null 2>&1; }

MIG="$QA_ROOT/app/Database/Migrations"; mkdir -p "$MIG"
PROBE="$MIG/2026-10-24-000001_QaBl001Probe.php"
BROKEN="$MIG/2026-10-24-000002_QaBl001Broken.php"
cleanup(){ rm -f "$PROBE" "$BROKEN"; }
trap cleanup EXIT

# Columns, indexes and constraints of every application table (migration history tables excluded).
schema(){ q "SET SESSION group_concat_max_len=100000000;
SELECT MD5(GROUP_CONCAT(x ORDER BY x SEPARATOR '|')) FROM (
 SELECT CONCAT_WS(':','C',TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,IFNULL(COLUMN_DEFAULT,'~'),EXTRA) x FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME NOT IN ('migrations','lpg_migration_history')
 UNION ALL SELECT CONCAT_WS(':','I',TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX,COLUMN_NAME,NON_UNIQUE) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME NOT IN ('migrations','lpg_migration_history')
 UNION ALL SELECT CONCAT_WS(':','K',TABLE_NAME,CONSTRAINT_NAME,CONSTRAINT_TYPE) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME NOT IN ('migrations','lpg_migration_history')
) t"; }
login_page(){ curl -s "$B/login"; }
login_reaches_dashboard(){
  local c="$T/cookie.txt" tok; rm -f "$c"
  tok=$(curl -s -c "$c" -b "$c" "$B/login" | grep -oE 'name="csrf_test_name" value="[^"]+"' | sed -E 's/.*value="([^"]+)"/\1/')
  curl -s -o /dev/null -w '%{url_effective}' -L -c "$c" -b "$c" --data-urlencode login=admin --data-urlencode password=admin123 --data-urlencode "csrf_test_name=$tok" "$B/login" | grep -q dashboard
}

bash "$DIR/reset_db.sh" >/dev/null 2>&1 || { echo "FAIL  reset_db.sh"; exit 1; }
qa_clear_throttle
BASE=$(schema)
[ -n "$BASE" ] && ok "baseline schema fingerprint taken" || bad "baseline schema" "empty fingerprint"
login_reaches_dashboard && ok "login works with no pending migrations" || bad "baseline login" "did not reach dashboard"

# --- 1. A new reversible migration blocks login until it is executed
cat > "$PROBE" <<'PHP'
<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
/** QA probe for BL-001 (removed by tests/qa/verify_migrations.sh). */
class QaBl001Probe extends Migration
{
    public function up(): void
    {
        $this->forge->addField(['id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true], 'note' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true]]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('qa_bl001_probe');
        $this->forge->addColumn('customers', ['qa_bl001_note' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true]]);
        $this->db->query('ALTER TABLE customers ADD INDEX idx_qa_bl001_note (qa_bl001_note)');
    }
    public function down(): void
    {
        $this->db->query('ALTER TABLE customers DROP INDEX idx_qa_bl001_note');
        $this->forge->dropColumn('customers', 'qa_bl001_note');
        $this->forge->dropTable('qa_bl001_probe');
    }
}
PHP
login_page | grep -q "QaBl001Probe" && ok "login page lists the pending reversible migration" || bad "pending shown" "probe not listed on /login"
login_reaches_dashboard && bad "login blocked while pending" "reached dashboard" || ok "login is blocked while a reversible migration is pending"

out=$(qa_spark migrations:run 2>&1)
echo "$out" | grep -q "SUCCESS 2026-10-24-000001_QaBl001Probe.php" && ok "migrations:run applies it after the SQL history" || bad "apply" "$(echo "$out" | tail -3)"
[ "$(q "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='qa_bl001_probe'")" = 1 ] && ok "probe table created" || bad "probe table" "missing"
[ "$(q "SELECT COUNT(*) FROM migrations WHERE class LIKE '%QaBl001Probe'")" = 1 ] && ok "recorded in CodeIgniter migration history" || bad "ci history" "no row"
[ "$(q "SELECT status FROM lpg_migration_history WHERE migration_file='2026-10-24-000001_QaBl001Probe.php' ORDER BY id DESC LIMIT 1")" = success ] && ok "recorded in lpg_migration_history" || bad "lpg history" "no success row"
UP=$(schema)
[ "$UP" != "$BASE" ] && ok "schema changed after up()" || bad "up changed schema" "fingerprint unchanged"
qa_clear_throttle
login_reaches_dashboard && ok "login works again once applied" || bad "login after apply" "did not reach dashboard"

# --- 2. Rollback restores the exact previous schema; re-applying reproduces it
out=$(qa_spark migrate:rollback 2>&1)
[ "$(schema)" = "$BASE" ] && ok "migrate:rollback restores the baseline schema exactly" || bad "rollback" "$(echo "$out" | tail -3)"
[ "$(q "SELECT COUNT(*) FROM migrations WHERE class LIKE '%QaBl001Probe'")" = 0 ] && ok "rollback removes the history row" || bad "rollback history" "row still present"
login_page | grep -q "QaBl001Probe" && ok "rolled-back migration shows as pending again" || bad "pending after rollback" "not listed"
qa_spark migrations:run >/dev/null 2>&1
[ "$(schema)" = "$UP" ] && ok "re-applying gives the same schema as the first up()" || bad "re-apply" "fingerprint differs"

# --- 3. A failing migration stops the run, is reported, and keeps login blocked
cat > "$BROKEN" <<'PHP'
<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
/** QA probe for BL-001 (removed by tests/qa/verify_migrations.sh). */
class QaBl001Broken extends Migration
{
    public function up(): void { $this->db->query('ALTER TABLE qa_bl001_no_such_table ADD COLUMN x INT'); }
    public function down(): void {}
}
PHP
out=$(qa_spark migrations:run 2>&1)
echo "$out" | grep -q "FAILED 2026-10-24-000002_QaBl001Broken.php" && ok "a failing migration is reported as FAILED" || bad "failure reported" "$(echo "$out" | tail -3)"
[ "$(q "SELECT status FROM lpg_migration_history WHERE migration_file='2026-10-24-000002_QaBl001Broken.php' ORDER BY id DESC LIMIT 1")" = failed ] && ok "failure recorded with its error" || bad "failure history" "no failed row"
[ "$(q "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='qa_bl001_probe'")" = 1 ] && ok "earlier migrations are not undone by the failure" || bad "earlier kept" "probe table gone"
qa_clear_throttle
login_reaches_dashboard && bad "login blocked after failure" "reached dashboard" || ok "login stays blocked after a failed migration"

# --- 4. Remove the probes and restore the database
rm -f "$BROKEN"
qa_spark migrate:rollback >/dev/null 2>&1
rm -f "$PROBE"
[ "$(schema)" = "$BASE" ] && ok "probe removed; schema back to baseline" || bad "final cleanup" "fingerprint differs"
bash "$DIR/reset_db.sh" >/dev/null 2>&1

# --- 5. BL-028 client UUIDs: unique uuid column on every listed table; rollback; backfill on re-run
UUID_TABLES="sales sale_payments customer_receipts customers expenses inventory_movements customer_orders cash_transactions stock_transfers cylinder_custody"
for t in $UUID_TABLES; do
  uq=$(q "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='uuid' AND INDEX_NAME='uq_${t}_uuid' AND NON_UNIQUE=0")
  [ "$uq" = 1 ] && ok "$t.uuid has a unique index" || bad "$t.uuid" "unique index missing"
done
rollback_from ClientUuids
[ "$(q "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='uuid' AND TABLE_NAME IN ('sales','customers','cylinder_custody')")" = 0 ] && ok "ClientUuids rollback removes the uuid columns" || bad "uuid rollback" "columns remain"
qa_spark migrations:run >/dev/null 2>&1
[ "$(q "SELECT CONCAT(COUNT(*)>0,'|',COUNT(uuid)=COUNT(*),'|',COUNT(DISTINCT uuid)=COUNT(*)) FROM customers")" = "1|1|1" ] && ok "re-run backfills every existing customer with a distinct uuid" || bad "uuid backfill" "$(q "SELECT COUNT(*),COUNT(uuid),COUNT(DISTINCT uuid) FROM customers")"
bash "$DIR/reset_db.sh" >/dev/null 2>&1

# --- 6. BL-030 sync columns on masters, version on update, soft delete keeps the row
MASTERS="customers suppliers cylinder_types expense_categories rate_cards customer_rates business_catalog_products"
for t in $MASTERS; do
  n=$(q "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME IN ('updated_at','deleted_at','version')")
  [ "$n" = 3 ] && ok "$t has updated_at, deleted_at, version" || bad "$t sync columns" "$n of 3"
done
TOK=$(curl -s -H 'Content-Type: application/json' -d '{"username":"admin","password":"admin123"}' "$B/api/v1/staff/auth/login" | grep -oE '"access_token": *"[a-f0-9]{64}"' | grep -oE '[a-f0-9]{64}')
CID=$(curl -s -H "Authorization: Bearer $TOK" -H 'Content-Type: application/json' -d '{"name":"QA Sync Customer","code":"QA-SYNC"}' "$B/api/v1/staff/customers" | grep -oE '"id": *"?[0-9]+' | head -1 | grep -oE '[0-9]+$')
V1=$(q "SELECT version FROM customers WHERE id=$CID")
curl -s -o /dev/null -X PUT -H "Authorization: Bearer $TOK" -H 'Content-Type: application/json' -d '{"phone":"0300"}' "$B/api/v1/staff/customers/$CID"
curl -s -o /dev/null -X PUT -H "Authorization: Bearer $TOK" -H 'Content-Type: application/json' -d '{"city":"Lahore"}' "$B/api/v1/staff/customers/$CID"
[ "$(q "SELECT version FROM customers WHERE id=$CID")" = "$((V1+2))" ] && ok "two updates raise the customer version by 2" || bad "version" "$V1 -> $(q "SELECT version FROM customers WHERE id=$CID")"
curl -s -o /dev/null -X DELETE -H "Authorization: Bearer $TOK" "$B/api/v1/staff/customers/$CID"
[ "$(q "SELECT CONCAT(deleted_at IS NOT NULL,'|',is_active) FROM customers WHERE id=$CID")" = "1|0" ] && ok "deleted customer keeps its row (deleted_at set, inactive)" || bad "soft delete" "$(q "SELECT CONCAT(COALESCE(deleted_at,'NULL'),'|',is_active) FROM customers WHERE id=$CID")"
rollback_from SyncColumnsOnMasters
[ "$(q "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('customers','expense_categories') AND COLUMN_NAME IN ('deleted_at','version')")" = 0 ] \
  && [ "$(q "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='expense_categories' AND COLUMN_NAME='updated_at'")" = 0 ] \
  && [ "$(q "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customers' AND COLUMN_NAME='updated_at'")" = 1 ] \
  && ok "SyncColumnsOnMasters rollback removes only what it added" || bad "sync rollback" "columns left or removed wrongly"
qa_spark migrations:run >/dev/null 2>&1
bash "$DIR/reset_db.sh" >/dev/null 2>&1

# --- 7. BL-031 customer profile columns; rollback
n=$(q "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customers' AND COLUMN_NAME IN ('customer_type','area','notes','is_blocked','whatsapp_enabled')")
[ "$n" = 5 ] && ok "customers has customer_type, area, notes, is_blocked, whatsapp_enabled" || bad "customer profile columns" "$n of 5"
[ "$(q "SELECT COUNT(*) FROM customers WHERE customer_type<>'domestic' OR is_blocked<>0")" = 0 ] && ok "existing customers default to domestic, not blocked" || bad "profile defaults" ""
rollback_from CustomerProfileFields
[ "$(q "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customers' AND COLUMN_NAME IN ('customer_type','is_blocked')")" = 0 ] && ok "CustomerProfileFields rollback removes the columns" || bad "profile rollback" "columns remain"
qa_spark migrations:run >/dev/null 2>&1
bash "$DIR/reset_db.sh" >/dev/null 2>&1

# --- 8. BL-032 cylinder type deposit; rollback
[ "$(q "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cylinder_types' AND COLUMN_NAME='deposit_amount'")" = 1 ] && ok "cylinder_types has deposit_amount" || bad "deposit column" "missing"
[ "$(q "SELECT COUNT(*) FROM cylinder_types WHERE deposit_amount<>0")" = 0 ] && ok "existing cylinder types default to a zero deposit" || bad "deposit default" ""
rollback_from CylinderTypeDeposit
[ "$(q "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cylinder_types' AND COLUMN_NAME='deposit_amount'")" = 0 ] && ok "CylinderTypeDeposit rollback removes the column" || bad "deposit rollback" "column remains"
qa_spark migrations:run >/dev/null 2>&1
bash "$DIR/reset_db.sh" >/dev/null 2>&1

# --- 9. BL-033 movement states; rollback; back-fill of existing rows
n=$(q "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='inventory_movements' AND COLUMN_NAME IN ('from_state','to_state')")
[ "$n" = 2 ] && ok "inventory_movements has from_state and to_state" || bad "state columns" "$n of 2"
q "SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cylinder_units' AND COLUMN_NAME='status'" | grep -q retired && ok "cylinder_units.status allows retired" || bad "retired status" "missing"
rollback_from InventoryMovementStates
[ "$(q "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='inventory_movements' AND COLUMN_NAME IN ('from_state','to_state')")" = 0 ] \
  && ! q "SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cylinder_units' AND COLUMN_NAME='status'" | grep -q retired \
  && ok "InventoryMovementStates rollback removes the columns and the retired status" || bad "states rollback" "columns or status remain"
qa_spark migrations:run >/dev/null 2>&1
[ "$(q "SELECT COUNT(*) FROM inventory_movements WHERE to_state IS NULL OR from_state IS NULL")" = 0 ] && [ "$(q "SELECT COUNT(*) FROM inventory_movements")" -gt 0 ] \
  && ok "existing movements are back-filled with states" || bad "back-fill" "$(q "SELECT COUNT(*) FROM inventory_movements WHERE to_state IS NULL") rows without states"
[ "$(q "SELECT COUNT(*) FROM ($QA_UNIT_MISMATCH_SQL) x")" = 0 ] && ok "back-filled ledger reconciles with unit statuses" || bad "back-fill reconciliation" "$(qa_unit_mismatches | head -3 | tr '\n' ';')"
bash "$DIR/reset_db.sh" >/dev/null 2>&1

echo; echo "RESULT: $PASS passed, $FAIL failed"
[ "$FAIL" = 0 ]
