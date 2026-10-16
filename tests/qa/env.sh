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
qa_clear_throttle(){ rm -f "$QA_ROOT"/writable/cache/login_fail_* 2>/dev/null || true; }
export QA_BASE_URL="$B" QA_DB QA_DB_HOST QA_DB_PORT QA_DB_USER QA_DB_PASS QA_MYSQL
