<?php
namespace App\Services;

use Config\Database;

/**
 * Audit trail of sensitive actions (who, when, from where, before/after).
 *
 * Inside a database transaction (postings, voids, payments) a failed audit write is rethrown so
 * the whole posting rolls back: a change is never committed without its audit row (BL-016).
 * Outside a transaction a failure is logged and the action continues.
 */
class AuditService
{
    public static function log(string $action, string $entityType, ?int $entityId, ?array $old, ?array $new, int $userId, ?int $locationId): void
    {
        $db = Database::connect();
        try {
            $ok = $db->table('audit_logs')->insert([
                'user_id' => $userId > 0 ? $userId : null, 'location_id' => $locationId !== null && $locationId > 0 ? $locationId : null, 'action' => $action, 'entity_type' => $entityType, 'entity_id' => $entityId,
                'old_values' => $old === null ? null : json_encode($old),
                'new_values' => $new === null ? null : json_encode($new),
                'ip_address' => service('request')->getIPAddress(),
                'user_agent' => substr((string) service('request')->getUserAgent(), 0, 500),
            ]);
            if ($ok === false) {
                throw new \RuntimeException('audit_logs insert returned false');
            }
        } catch (\Throwable $e) {
            log_message('error', 'Audit log write failed: ' . $e->getMessage());
            if ($db->transDepth > 0) {
                throw new \RuntimeException('The change was not saved because its audit record could not be written.', 0, $e);
            }
        }
    }
}