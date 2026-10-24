<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * BL-014: the SaaS role set (docs/roles.md). ADMIN is labelled Owner; DRIVER and ACCOUNTANT are
 * added; delivery permissions are created and granted to Owner, Manager and Driver.
 */
class StandardSaasRoles extends Migration
{
    private const NEW_PERMISSIONS = [
        'DELIVERY_MANAGE' => 'Assign deliveries, manage vehicles and settle drivers',
        'DELIVERY_VIEW_OWN' => 'View own assigned deliveries',
        'DELIVERY_RECORD' => 'Record deliveries and collections',
    ];

    private const NEW_ROLES = [
        'DRIVER' => ['Driver', 'Delivery driver: own deliveries only', ['DELIVERY_VIEW_OWN', 'DELIVERY_RECORD']],
        'ACCOUNTANT' => ['Accountant', 'Ledgers, receipts, payments, expenses, cash book and reports',
            ['DASHBOARD_VIEW', 'POS_HISTORY', 'CUSTOMER_MANAGE', 'SUPPLIER_MANAGE', 'PURCHASE_MANAGE', 'CASH_MANAGE', 'EXPENSE_MANAGE', 'REPORT_VIEW', 'AUDIT_VIEW']],
    ];

    public function up(): void
    {
        foreach (self::NEW_PERMISSIONS as $code => $name) {
            $this->db->table('permissions')->ignore(true)->insert(['code' => $code, 'name' => $name]);
        }
        foreach (['ADMIN', 'MANAGER'] as $roleCode) {
            $this->grant($roleCode, array_keys(self::NEW_PERMISSIONS));
        }
        foreach (self::NEW_ROLES as $code => [$name, $description, $permissions]) {
            $this->db->table('roles')->ignore(true)->insert(['code' => $code, 'name' => $name, 'description' => $description]);
            $this->grant($code, $permissions);
        }
        $this->db->table('roles')->where('code', 'ADMIN')->update(['name' => 'Owner']);
    }

    public function down(): void
    {
        $this->db->table('roles')->where('code', 'ADMIN')->update(['name' => 'Administrator']);
        foreach (array_keys(self::NEW_ROLES) as $code) {
            $role = $this->db->table('roles')->select('id')->where('code', $code)->get()->getRowArray();
            if (!$role) {
                continue;
            }
            if ($this->db->table('users')->where('role_id', (int) $role['id'])->countAllResults() > 0) {
                throw new \RuntimeException("Role $code is assigned to users; reassign them before rolling back.");
            }
            $this->db->table('role_permissions')->where('role_id', (int) $role['id'])->delete();
            $this->db->table('roles')->where('id', (int) $role['id'])->delete();
        }
        $ids = array_column($this->db->table('permissions')->select('id')->whereIn('code', array_keys(self::NEW_PERMISSIONS))->get()->getResultArray(), 'id');
        if ($ids) {
            $this->db->table('role_permissions')->whereIn('permission_id', $ids)->delete();
            $this->db->table('permissions')->whereIn('id', $ids)->delete();
        }
    }

    private function grant(string $roleCode, array $permissionCodes): void
    {
        $role = $this->db->table('roles')->select('id')->where('code', $roleCode)->get()->getRowArray();
        if (!$role) {
            return;
        }
        foreach ($this->db->table('permissions')->select('id')->whereIn('code', $permissionCodes)->get()->getResultArray() as $p) {
            $this->db->table('role_permissions')->ignore(true)->insert(['role_id' => (int) $role['id'], 'permission_id' => (int) $p['id']]);
        }
    }
}
