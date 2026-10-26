# Shared settings for the tests/qa suites. Override any value with an environment variable.
# These suites WRITE to the database: point QA_DB at a disposable database only.
QA_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
B="${QA_BASE_URL:-http://localhost:8080}"
QA_DB="${QA_DB:-lpg_qa}"
QA_DB_HOST="${QA_DB_HOST:-127.0.0.1}"
QA_DB_PORT="${QA_DB_PORT:-3306}"
QA_DB_USER="${QA_DB_USER:-root}"
QA_DB_PASS="${QA_DB_PASS:-}"
QA_MYSQL="${QA_MYSQL:-mysql}"
QA_PHP="${QA_PHP:-php}"
QA_TMP="${QA_TMP:-${TMPDIR:-${TEMP:-/tmp}}/lpg-qa}"
case "$QA_DB" in perfect_lpg|pak_gas|production*) echo "Refusing to run QA suites against '$QA_DB'. Use a disposable database." >&2; exit 2;; esac
M="$QA_MYSQL -h $QA_DB_HOST -P $QA_DB_PORT -u $QA_DB_USER ${QA_DB_PASS:+-p$QA_DB_PASS} -N $QA_DB"
qa_spark(){ (cd "$QA_ROOT" && env CI_ENVIRONMENT=development LPG_DB_HOST="$QA_DB_HOST" LPG_DB_PORT="$QA_DB_PORT" LPG_DB_USER="$QA_DB_USER" LPG_DB_PASS="$QA_DB_PASS" LPG_DB_NAME="$QA_DB" LPG_DB_DRIVER=MySQLi "$QA_PHP" spark "$@"); }
# BL-033: units whose status differs from the to_state of their latest movement ("code status/to_state" per line).
QA_UNIT_MISMATCH_SQL="SELECT CONCAT(cu.unit_code,' ',cu.status,'/',COALESCE(m.to_state,'NULL')) FROM cylinder_units cu JOIN (SELECT cylinder_unit_id, MAX(id) mid FROM inventory_movements WHERE cylinder_unit_id IS NOT NULL GROUP BY cylinder_unit_id) l ON l.cylinder_unit_id = cu.id JOIN inventory_movements m ON m.id = l.mid WHERE NOT (m.to_state <=> cu.status)"
qa_unit_mismatches(){ $M -e "$QA_UNIT_MISMATCH_SQL"; }
qa_clear_throttle(){ rm -f "$QA_ROOT"/writable/cache/login_fail_* 2>/dev/null || true; }
export QA_BASE_URL="$B" QA_DB QA_DB_HOST QA_DB_PORT QA_DB_USER QA_DB_PASS QA_MYSQL
