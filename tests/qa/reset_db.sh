#!/usr/bin/env bash
# Rebuilds the disposable QA database: schema, application migrations, then the shared
# Playwright fixtures. Never touches any other database.
set -euo pipefail
source "$(dirname "$0")/env.sh"
mkdir -p "$QA_TMP"
cd "$QA_ROOT"
# MariaDB (XAMPP) does not support CAST(... AS JSON); MySQL 8 accepts the stripped form too.
sed -E "s/CAST\(('([^']|'')*') AS JSON\)/\1/g" database/schema.sql > "$QA_TMP/schema.sql"
MR="$QA_MYSQL -h $QA_DB_HOST -P $QA_DB_PORT -u $QA_DB_USER ${QA_DB_PASS:+-p$QA_DB_PASS}"
$MR -e "DROP DATABASE IF EXISTS \`$QA_DB\`; CREATE DATABASE \`$QA_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
$MR "$QA_DB" < "$QA_TMP/schema.sql"
qa_spark migrations:run | grep -v '^SKIPPED' | tail -2
$MR "$QA_DB" < tests/e2e/fixtures/seed_catalog.sql
$MR "$QA_DB" < tests/e2e/fixtures/seed_pos.sql
qa_clear_throttle
echo "QA database $QA_DB reset."
