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
            'STOCK_TRANSFER_MANAGE' => 'Transfer stock between branches',
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
            'BUSINESS_CATALOG_VIEW' => 'View business product catalog',
            'BUSINESS_CATALOG_MANAGE' => 'Manage business product catalog',
            'ORDER_VIEW' => 'View customer orders',
            'ORDER_MANAGE' => 'Manage customer orders',
            'CUSTOMER_ACCOUNT_MANAGE' => 'Manage customer app accounts',
            'DELIVERY_MANAGE' => 'Assign deliveries, manage vehicles and settle drivers',
            'DELIVERY_VIEW_OWN' => 'View own assigned deliveries',
            'DELIVERY_RECORD' => 'Record deliveries and collections',
        ];

        // Only permission codes that do not exist yet are created and granted to the
        // standard roles. Existing role mappings are never re-granted, so a permission
        // an administrator removed from a standard role stays removed.
        $existingCodes = array_map('strval', array_column(
            $db->table('permissions')->select('code')->get()->getResultArray(),
            'code'
        ));
        $missingCodes = array_values(array_diff(array_keys($permissions), $existingCodes));
        if (!$missingCodes) {
            return;
        }

        foreach ($missingCodes as $code) {
            $db->table('permissions')->ignore(true)->insert([
                'code' => $code,
                'name' => $permissions[$code],
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

            ])),
            'CASHIER' => ['DASHBOARD_VIEW', 'POS_SALE', 'POS_HISTORY', 'CUSTOMER_MANAGE', 'REPORT_VIEW'],
        ];

        foreach ($roleDefaults as $roleCode => $permissionCodes) {
            $role = $db->table('roles')->select('id')->where('code', $roleCode)->get()->getRowArray();
            if (!$role) {
                continue;
            }

            foreach (array_intersect($permissionCodes, $missingCodes) as $permissionCode) {
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

    /**
     * Capabilities for an explicit staff identity. API requests do not use the
     * browser session, so they must never rely on all()'s session-derived user ID.
     *
     * @return list<string>
     */
    public static function allForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        self::syncStandardRolePermissions();

        $codes = Database::connect()->table('users u')
            ->select('permissions.code')
            ->join('role_permissions rp', 'rp.role_id=u.role_id')
            ->join('permissions', 'permissions.id=rp.permission_id')
            ->where(['u.id' => $userId, 'u.is_active' => 1])
            ->get()
            ->getResultArray();

        return array_values(array_unique(array_map('strval', array_column($codes, 'code'))));
    }

    public static function allowsForUser(string $permission, int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        if (in_array($permission, self::SYSTEM_ADMIN_ONLY, true)) {
            $user = Database::connect()->table('users')
                ->select('id')
                ->where(['id' => $userId, 'is_active' => 1, 'is_system_admin' => 1])
                ->get()->getRowArray();
            if (!$user) {
                return false;
            }
        }

        return in_array($permission, self::allForUser($userId), true);
    }

    /**
     * A user manager may only hand out (or act on holders of) roles whose permissions are all
     * permissions the manager holds; a System Administrator may grant any role.
     */
    public static function roleWithinActor(int $roleId, int $actorId): bool
    {
        if ($roleId <= 0 || $actorId <= 0) {
            return false;
        }

        $db = Database::connect();
        $actor = $db->table('users')->select('is_system_admin')->where(['id' => $actorId, 'is_active' => 1])->get()->getRowArray();
        if (!$actor) {
            return false;
        }
        if (!$db->table('roles')->where('id', $roleId)->countAllResults()) {
            return false;
        }
        if ((int) ($actor['is_system_admin'] ?? 0) === 1) {
            return true;
        }

        $roleCodes = array_map('strval', array_column(
            $db->table('role_permissions rp')->select('p.code')->join('permissions p', 'p.id=rp.permission_id')
                ->where('rp.role_id', $roleId)->get()->getResultArray(),
            'code'
        ));

        return array_diff($roleCodes, self::allForUser($actorId)) === [];
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
