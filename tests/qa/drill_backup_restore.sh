#!/usr/bin/env bash
# Backup / restore drill (release runbook §6, SQA P12-01) on the disposable QA database, using
# only the application's own Database Backup & Restore screens:
#   snapshot -> create + download backup -> damage data -> restore uploaded file -> compare.
# Run after reset_db.sh and a data fixture (e.g. verify_hq.sh).
set -u
source "$(dirname "$0")/env.sh"
T="$QA_TMP/drill"; mkdir -p "$T"
PASS=0; FAIL=0
ok(){ echo "PASS  $1"; PASS=$((PASS+1)); }
bad(){ echo "FAIL  $1 :: $2"; FAIL=$((FAIL+1)); }
q(){ local r="" i; for i in 1 2 3 4; do r=$($M -e "$1" | tr -d '\r'); [ -n "$r" ] && break; done; printf '%s' "$r"; }
login(){ rm -f "$1"; local tok; tok=$(curl -s -c "$1" -b "$1" $B/login | grep -oE 'name="csrf_test_name" value="[^"]+"' | sed -E 's/.*value="([^"]+)"/\1/'); curl -s -o /dev/null -L -c "$1" -b "$1" --data-urlencode "login=$2" --data-urlencode "password=$3" --data-urlencode "csrf_test_name=$tok" $B/login; }
csrf(){ grep -oE 'csrf_cookie_name\s+\S+' "$1" | awk '{print $2}'; }
flash(){ curl -s -b "$A" -c "$A" "$B/database-backup" | grep -oE 'alert-(success|danger)[^>]*>(<[^>]*>)*[^<]+' | sed -E 's/<[^>]*>//g;s/.*>//' | head -1; }
# Business tables only: audit/migration/backup bookkeeping and sessions legitimately change during the drill.
snapshot(){
  local tables; tables=$(q "SELECT GROUP_CONCAT(table_name ORDER BY table_name) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE' AND table_name NOT IN ('audit_logs','lpg_migration_history','ci_sessions','api_sessions','api_refresh_tokens')")
  local out="" t
  for t in ${tables//,/ }; do out+="$t $(q "SELECT COUNT(*) FROM \`$t\`") $(q "CHECKSUM TABLE \`$t\`" | awk '{print $2}')"$'\n'; done
  printf '%s' "$out"
}

A="$T/admin.txt"; login "$A" admin admin123
snapshot > "$T/before.txt"
ROWS=$(awk '{s+=$2} END {print s}' "$T/before.txt"); TABLES=$(wc -l < "$T/before.txt")
[ "$ROWS" -gt 0 ] && ok "snapshot: $TABLES tables, $ROWS rows" || bad "snapshot" "empty"

curl -s -o /dev/null -b "$A" -c "$A" --data-urlencode "csrf_test_name=$(csrf "$A")" "$B/database-backup/create"
MSG=$(flash); NAME=$(echo "$MSG" | grep -oE '[A-Za-z0-9._-]+\.sql' | head -1)
[ -n "$NAME" ] && ok "backup created: $NAME" || bad "backup create" "$MSG"
curl -s -b "$A" -o "$T/backup.sql" "$B/database-backup/download?file=$NAME"
SIZE=$(wc -c < "$T/backup.sql"); grep -q "INSERT INTO" "$T/backup.sql" && [ "$SIZE" -gt 1000 ] && ok "backup downloaded ($SIZE bytes)" || bad "download" "$SIZE bytes"

# Damage: delete sales lines and payments, rename customers, remove a cash movement.
q "SET FOREIGN_KEY_CHECKS=0; DELETE FROM sale_payments; DELETE FROM sale_items; UPDATE customers SET name=CONCAT('DAMAGED ',name); DELETE FROM cash_transactions ORDER BY id DESC LIMIT 1; SET FOREIGN_KEY_CHECKS=1; SELECT 1" >/dev/null
snapshot > "$T/damaged.txt"
diff -q "$T/before.txt" "$T/damaged.txt" >/dev/null && bad "damage step" "nothing changed" || ok "data damaged ($(diff "$T/before.txt" "$T/damaged.txt" | grep -c '^<') tables differ)"

curl -s -o /dev/null -b "$A" -c "$A" -F "csrf_test_name=$(csrf "$A")" -F "backup_file=@$T/backup.sql;type=application/sql" "$B/database-backup/restore"
MSG=$(flash); echo "$MSG" | grep -q "restored successfully" && ok "restore reported success" || bad "restore" "$MSG"

# No new sign-in here: it would update users.last_login_at and differ from the snapshot.
snapshot > "$T/after.txt"
if diff "$T/before.txt" "$T/after.txt" > "$T/diff.txt"; then ok "all $TABLES tables match the pre-damage snapshot (rows and checksums)"; else bad "restored data differs" "$(head -6 "$T/diff.txt" | tr '\n' ' ')"; fi
[ "$(q "SELECT COUNT(*) FROM customers WHERE name LIKE 'DAMAGED %'")" = "0" ] && ok "damaged customer names reverted" || bad "customers" "still damaged"
curl -s -o /dev/null -w '%{http_code}' -b "$A" "$B/dashboard" | grep -q 200 && ok "app works after restore (dashboard 200)" || bad "dashboard" "not 200"

echo; echo "RESULT: $PASS passed, $FAIL failed"
