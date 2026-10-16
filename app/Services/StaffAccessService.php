<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;

/**
 * Branch (shop) access of a staff user within one business, managed from the Users screen
 * (Phase 9.3.2). Only branches the acting user may access are changed; assignments in other
 * branches or businesses, and Head Office access, are left untouched.
 */
class StaffAccessService
{
    private BaseConnection $db;
    private ShopScopeService $scope;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
        $this->scope = new ShopScopeService($this->db);
    }

    /** Active branches of the business that the acting user may assign. */
    public function manageableBranches(int $actorId, int $businessId): array
    {
        $ids = $this->scope->accessibleShopIds($actorId);
        if (!$ids) {
            return [];
        }
        return $this->db->table('locations')->select('id,code,name')
            ->where(['business_id' => $businessId, 'is_active' => 1])->whereIn('id', $ids)
            ->orderBy('name')->get()->getResultArray();
    }

    /** @return array{branch_ids: list<int>, default_id: int|null} */
    public function assignment(int $userId, int $businessId): array
    {
        $rows = $this->db->table('user_shop_access usa')->select('usa.location_id,usa.is_default')
            ->join('locations l', 'l.id=usa.location_id')
            ->where(['usa.user_id' => $userId, 'l.business_id' => $businessId])->get()->getResultArray();
        $default = null;
        foreach ($rows as $row) {
            if ((int) $row['is_default'] === 1) {
                $default = (int) $row['location_id'];
            }
        }
        return ['branch_ids' => array_map(static fn ($r) => (int) $r['location_id'], $rows), 'default_id' => $default];
    }

    /**
     * Validates the requested branches before anything is saved.
     *
     * @param list<int> $branchIds
     * @return list<int> requested branches limited to those the actor manages
     */
    public function validate(int $actorId, int $businessId, array $branchIds, ?int $defaultId): array
    {
        $manageable = array_map(static fn ($b) => (int) $b['id'], $this->manageableBranches($actorId, $businessId));
        $requested = array_values(array_intersect($manageable, array_map('intval', $branchIds)));
        if (!$requested) {
            throw new RuntimeException('Select at least one branch this user may work in.');
        }
        if ($defaultId !== null && !in_array($defaultId, $requested, true)) {
            throw new RuntimeException('The default branch must be one of the selected branches.');
        }
        return $requested;
    }

    /**
     * @param list<int> $branchIds already validated with validate()
     */
    public function sync(int $actorId, int $userId, int $businessId, array $branchIds, ?int $defaultId): void
    {
        $manageable = array_map(static fn ($b) => (int) $b['id'], $this->manageableBranches($actorId, $businessId));
        $requested = array_values(array_intersect($manageable, $branchIds));
        $defaultId = $defaultId ?? ($requested[0] ?? null);

        $this->db->transBegin();
        try {
            $current = array_map('intval', array_column(
                $this->db->table('user_shop_access')->select('location_id')->where('user_id', $userId)->get()->getResultArray(),
                'location_id'
            ));
            $remove = array_values(array_diff(array_intersect($current, $manageable), $requested));
            if ($remove) {
                $this->db->table('user_shop_access')->where('user_id', $userId)->whereIn('location_id', $remove)->delete();
            }
            foreach (array_diff($requested, $current) as $locationId) {
                $this->db->table('user_shop_access')->insert(['user_id' => $userId, 'location_id' => $locationId, 'is_default' => 0]);
            }
            if ($defaultId !== null) {
                $this->db->table('user_shop_access')->where('user_id', $userId)->update(['is_default' => 0]);
                $this->db->table('user_business_access')->where('user_id', $userId)->update(['is_default' => 0]);
                $this->db->table('user_shop_access')->where(['user_id' => $userId, 'location_id' => $defaultId])->update(['is_default' => 1]);
                $this->db->table('users')->where('id', $userId)->update(['location_id' => $defaultId]);
            }
            if (!$this->db->transStatus()) {
                throw new RuntimeException('Branch access could not be saved.');
            }
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        AuditService::log('STAFF_BRANCH_ACCESS_UPDATED', 'user', $userId, ['branch_ids' => $current ?? []], ['branch_ids' => $requested, 'default_id' => $defaultId], $actorId, $defaultId);
    }
}
