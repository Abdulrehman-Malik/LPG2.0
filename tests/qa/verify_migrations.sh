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

echo; echo "RESULT: $PASS passed, $FAIL failed"
[ "$FAIL" = 0 ]
