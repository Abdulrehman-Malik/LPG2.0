<?php

namespace App\Services;

use Config\Database;

class PermissionService
{
    /**
     * These capabilities are global platform controls. Possessing a role
     * permission is not sufficient unless the authenticated user is also
     * explicitly flagged as a System Administrator.
     */
    private const SYSTEM_ADMIN_ONLY = [
        'BACKUP_MANAGE',
        'ROLE_PERMISSION_MANAGE',
        'APPLICATION_SETTINGS_VIEW',
        'APPLICATION_SETTINGS_MANAGE',
    ];

    protected static ?array $permissionCache = null;
    protected static ?int $permissionCacheUserId = null;

    /**
     * Keep standard role permissions usable on older installations.
     * Custom roles are not changed.
     */
    protected static function syncStandardRolePermissions(): void
    {
        static $synced = false;
        if ($synced) {
            return;
        }
        $synced = true;

        $db = Database::connect();

        $permissions = [
            'DASHBOARD_VIEW' => 'View dashboard',
            'POS_SALE' => 'Create POS sales',
            'POS_HISTORY' => 'View sale history',
            'POS_VOID' => 'Void posted sales',
            'CUSTOMER_MANAGE' => 'Manage customers',
            'SUPPLIER_MANAGE' => 'Manage suppliers',
            'RATE_MANAGE' => 'Manage LPG rates',
            'INVENTORY_MANAGE' => 'Manage inventory',
            'INVENTORY_CONTROLS_MANAGE' => 'Manage inventory controls',
            'GAS_WASTAGE_MANAGE' => 'Manage gas wastage',
            'PURCHASE_MANAGE' => 'Manage purchases',
            'CASH_MANAGE' => 'Manage counter cash',
            'EXPENSE_MANAGE' => 'Manage expenses',
            'REPORT_VIEW' => 'View reports',
            'USER_MANAGE' => 'Manage users',
            'AUDIT_VIEW' => 'View audit log',
            'BACKUP_MANAGE' => 'Create and restore database backups',
            'ROLE_PERMISSION_MANAGE' => 'Manage platform-wide role permissions',
            'APPLICATION_SETTINGS_VIEW' => 'View application-wide settings',
            'APPLICATION_SETTINGS_MANAGE' => 'Manage application-wide settings',
            'BUSINESS_SETTINGS_VIEW' => 'View Head Office/business settings',
            'BUSINESS_SETTINGS_MANAGE' => 'Manage Head Office/business settings',
            'SHOP_SETTINGS_VIEW' => 'View shop settings',
            'SHOP_SETTINGS_MANAGE' => 'Manage shop settings',
            'SHOP_ACCESS_MANAGE' => 'Manage staff shop assignments',
            'BUSINESS_CATALOG_MANAGE' => 'Manage business product catalog',
            'ORDER_VIEW' => 'View customer orders',
            'ORDER_MANAGE' => 'Manage customer orders',
            'CUSTOMER_ACCOUNT_MANAGE' => 'Manage customer app accounts',
        ];

        foreach ($permissions as $code => $name) {
            $db->table('permissions')->ignore(true)->insert([
                'code' => $code,
                'name' => $name,
            ]);
        }

        $roleDefaults = [
            'ADMIN' => array_keys($permissions),
            'MANAGER' => array_values(array_diff(array_keys($permissions), [
                'USER_MANAGE',
                'BACKUP_MANAGE',
                'ROLE_PERMISSION_MANAGE',
                'APPLICATION_SETTINGS_VIEW',
                'APPLICATION_SETTINGS_MANAGE',
                'BUSINESS_SETTINGS_VIEW',
                'BUSINESS_SETTINGS_MANAGE',
                'SHOP_ACCESS_MANAGE',
            ])),
            'CASHIER' => ['DASHBOARD_VIEW', 'POS_SALE', 'POS_HISTORY', 'CUSTOMER_MANAGE', 'REPORT_VIEW'],
        ];

        foreach ($roleDefaults as $roleCode => $permissionCodes) {
            $role = $db->table('roles')->select('id')->where('code', $roleCode)->get()->getRowArray();
            if (!$role) {
                continue;
            }

            foreach ($permissionCodes as $permissionCode) {
                $permission = $db->table('permissions')
                    ->select('id')
                    ->where('code', $permissionCode)
                    ->get()
                    ->getRowArray();

                if (!$permission) {
                    continue;
                }

                $db->table('role_permissions')->ignore(true)->insert([
                    'role_id' => (int) $role['id'],
                    'permission_id' => (int) $permission['id'],
                ]);
            }
        }
    }

    public static function all(): array
    {
        $userId=(int)(session()->get('user_id') ?? 0);

        if (self::$permissionCache !== null && self::$permissionCacheUserId === $userId) {
            return self::$permissionCache;
        }

        self::$permissionCacheUserId = $userId;
        if($userId<=0) {
            return self::$permissionCache=[];
        }

        self::syncStandardRolePermissions();

        self::$permissionCache = array_values(array_unique(array_map(
            'strval',
            array_column(
                Database::connect()->table('users u')
                    ->select('permissions.code')
                    ->join('role_permissions rp','rp.role_id=u.role_id')
                    ->join('permissions','permissions.id=rp.permission_id')
                    ->where('u.id',$userId)
                    ->get()
                    ->getResultArray(),
                'code'
            )
        )));

        return self::$permissionCache;
    }

    public static function current(): array
    {
        return array_fill_keys(self::all(), true);
    }

    public static function allows(string $permission): bool
    {
        if (in_array($permission, self::SYSTEM_ADMIN_ONLY, true)) {
            $userId = (int) (session()->get('user_id') ?? 0);
            if ($userId <= 0) {
                return false;
            }

            $user = Database::connect()->table('users')
                ->select('is_system_admin,is_active')
                ->where('id', $userId)
                ->get()
                ->getRowArray();

            if (!$user || (int) ($user['is_active'] ?? 0) !== 1 || (int) ($user['is_system_admin'] ?? 0) !== 1) {
                return false;
            }
        }

        return isset(self::current()[$permission]);
    }
}
