<?php

namespace App\Services;

use Config\Database;
use CodeIgniter\Database\BaseConnection;

/**
 * Resolves trusted business/shop scope for the authenticated staff identity.
 *
 * Capability permissions remain the responsibility of PermissionService. This class
 * answers the separate question of which business/shop data the user may access.
 */
class ShopScopeService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    protected function resolveUserId(?int $userId = null): int
    {
        return $userId ?? (int) (session()->get('user_id') ?? 0);
    }

    protected function activeUser(?int $userId = null): ?array
    {
        $id = $this->resolveUserId($userId);
        if ($id <= 0) {
            return null;
        }

        $user = $this->db->table('users')
            ->select('id,location_id,is_system_admin,is_active')
            ->where('id', $id)
            ->get()
            ->getRowArray();

        if (!$user || (int) ($user['is_active'] ?? 0) !== 1) {
            return null;
        }

        return $user;
    }

    public function isSystemAdministrator(?int $userId = null): bool
    {
        $user = $this->activeUser($userId);
        return $user !== null && (int) ($user['is_system_admin'] ?? 0) === 1;
    }

    /**
     * Returns only active business IDs explicitly assigned to this identity.
     * System administrators have platform-wide business scope.
     *
     * @return list<int>
     */
    public function accessibleBusinessIds(?int $userId = null): array
    {
        $user = $this->activeUser($userId);
        if (!$user) {
            return [];
        }

        if ((int) ($user['is_system_admin'] ?? 0) === 1) {
            return array_values(array_unique(array_map(
                'intval',
                array_column(
                    $this->db->table('businesses')
                        ->select('id')
                        ->where('is_active', 1)
                        ->get()
                        ->getResultArray(),
                    'id'
                )
            )));
        }

        $id = (int) $user['id'];
        $rows = $this->db->table('user_business_access uba')
            ->select('b.id')
            ->join('businesses b', 'b.id=uba.business_id', 'inner')
            ->where('uba.user_id', $id)
            ->where('b.is_active', 1)
            ->get()
            ->getResultArray();

        return array_values(array_unique(array_map('intval', array_column($rows, 'id'))));
    }

    /**
     * Returns active shops that are directly assigned or belong to an assigned
     * Head Office business. The legacy users.location_id fallback applies only
     * when the identity has no explicit business/shop assignments at all.
     *
     * @return list<int>
     */
    public function accessibleShopIds(?int $userId = null): array
    {
        $user = $this->activeUser($userId);
        if (!$user) {
            return [];
        }

        if ((int) ($user['is_system_admin'] ?? 0) === 1) {
            $rows = $this->db->table('locations l')
                ->select('l.id')
                ->join('businesses b', 'b.id=l.business_id', 'left')
                ->where('l.is_active', 1)
                ->groupStart()
                    ->where('l.business_id IS NULL', null, false)
                    ->orWhere('b.is_active', 1)
                ->groupEnd()
                ->get()
                ->getResultArray();

            return array_values(array_unique(array_map('intval', array_column($rows, 'id'))));
        }

        $id = (int) $user['id'];

        // Count assignments separately so a stale/inactive explicit assignment
        // cannot accidentally enable the legacy single-location fallback.
        $hasShopAssignments = $this->db->table('user_shop_access')
            ->where('user_id', $id)
            ->countAllResults() > 0;
        $hasBusinessAssignments = $this->db->table('user_business_access')
            ->where('user_id', $id)
            ->countAllResults() > 0;

        $shopIds = [];
        $directRows = $this->db->table('user_shop_access usa')
            ->select('l.id')
            ->join('locations l', 'l.id=usa.location_id', 'inner')
            ->join('businesses b', 'b.id=l.business_id', 'left')
            ->where('usa.user_id', $id)
            ->where('l.is_active', 1)
            ->groupStart()
                ->where('l.business_id IS NULL', null, false)
                ->orWhere('b.is_active', 1)
            ->groupEnd()
            ->get()
            ->getResultArray();

        foreach ($directRows as $row) {
            $shopIds[] = (int) $row['id'];
        }

        $businessIds = $this->accessibleBusinessIds($id);
        if ($businessIds) {
            $businessRows = $this->db->table('locations')
                ->select('id')
                ->where('is_active', 1)
                ->whereIn('business_id', $businessIds)
                ->get()
                ->getResultArray();

            foreach ($businessRows as $row) {
                $shopIds[] = (int) $row['id'];
            }
        }

        if (!$hasShopAssignments && !$hasBusinessAssignments && !empty($user['location_id'])) {
            $legacyShop = $this->db->table('locations l')
                ->select('l.id')
                ->join('businesses b', 'b.id=l.business_id', 'left')
                ->where(['l.id' => (int) $user['location_id'], 'l.is_active' => 1])
                ->groupStart()
                    ->where('l.business_id IS NULL', null, false)
                    ->orWhere('b.is_active', 1)
                ->groupEnd()
                ->get()
                ->getRowArray();

            if ($legacyShop) {
                $shopIds[] = (int) $legacyShop['id'];
            }
        }

        $shopIds = array_values(array_unique(array_filter($shopIds, static fn (int $id): bool => $id > 0)));
        sort($shopIds, SORT_NUMERIC);

        return $shopIds;
    }

    public function defaultShopIdForUser(?int $userId = null): ?int
    {
        $user = $this->activeUser($userId);
        if (!$user) {
            return null;
        }

        $id = (int) $user['id'];
        $currentShopId = (int) ($user['location_id'] ?? 0);
        if ($currentShopId > 0 && $this->canAccessShop($currentShopId, $id)) {
            return $currentShopId;
        }

        $directDefault = $this->db->table('user_shop_access usa')
            ->select('l.id')
            ->join('locations l', 'l.id=usa.location_id', 'inner')
            ->join('businesses b', 'b.id=l.business_id', 'left')
            ->where('usa.user_id', $id)
            ->where('usa.is_default', 1)
            ->where('l.is_active', 1)
            ->groupStart()
                ->where('l.business_id IS NULL', null, false)
                ->orWhere('b.is_active', 1)
            ->groupEnd()
            ->orderBy('l.id', 'ASC')
            ->get()
            ->getRowArray();
        if ($directDefault) {
            return (int) $directDefault['id'];
        }

        $businessDefault = $this->db->table('user_business_access uba')
            ->select('l.id')
            ->join('businesses b', 'b.id=uba.business_id', 'inner')
            ->join('locations l', 'l.business_id=b.id', 'inner')
            ->where('uba.user_id', $id)
            ->where('uba.is_default', 1)
            ->where('b.is_active', 1)
            ->where('l.is_active', 1)
            ->orderBy('l.id', 'ASC')
            ->get()
            ->getRowArray();
        if ($businessDefault) {
            return (int) $businessDefault['id'];
        }

        $accessible = $this->accessibleShopIds($id);
        return $accessible ? (int) $accessible[0] : null;
    }

    public function canAccessShop(int $locationId, ?int $userId = null): bool
    {
        if ($locationId <= 0) {
            return false;
        }

        return in_array($locationId, $this->accessibleShopIds($userId), true);
    }

    public function canAccessBusiness(int $businessId, ?int $userId = null): bool
    {
        if ($businessId <= 0) {
            return false;
        }

        return in_array($businessId, $this->accessibleBusinessIds($userId), true);
    }

    /**
     * Validates the server-side current shop context. A request parameter alone
     * is never a source of authorization.
     */
    public function canAccessCurrentShop(?int $userId = null): bool
    {
        return $this->canAccessShop((int) (\App\Libraries\TenantContext::branchId() ?? 0), $userId);
    }

    public function businessIdForShop(int $locationId, ?int $userId = null): ?int
    {
        if (!$this->canAccessShop($locationId, $userId)) {
            return null;
        }

        $row = $this->db->table('locations')
            ->select('business_id')
            ->where(['id' => $locationId, 'is_active' => 1])
            ->get()
            ->getRowArray();

        $businessId = (int) ($row['business_id'] ?? 0);
        return $businessId > 0 ? $businessId : null;
    }
}
