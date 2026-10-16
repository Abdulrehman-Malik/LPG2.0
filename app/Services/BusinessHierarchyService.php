<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * Tenant structure for the Business Hierarchy screen (Phase 9.7): business -> Head Office users,
 * business -> shops -> shop staff, with each shop's setup readiness and live position.
 *
 * Only businesses and shops the user may access are returned. A user with Head Office access to a
 * business also sees that business's inactive shops. Every figure is one grouped query across all
 * visible shops.
 */
class BusinessHierarchyService
{
    private BaseConnection $db;
    private ShopScopeService $scope;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
        $this->scope = new ShopScopeService($this->db);
    }

    /** @return list<array> businesses, each with 'head_office', 'shops' (with 'staff', 'readiness', 'live') and 'totals' */
    public function tree(int $userId): array
    {
        $shopIds = array_map('intval', $this->scope->accessibleShopIds($userId));
        $businessIds = array_map('intval', $this->scope->accessibleBusinessIds($userId));
        if ($shopIds) {
            foreach ($this->db->table('locations')->select('business_id')->whereIn('id', $shopIds)->get()->getResultArray() as $row) {
                $businessIds[] = (int) $row['business_id'];
            }
        }
        $businessIds = array_values(array_unique(array_filter($businessIds)));
        if (!$businessIds) {
            return [];
        }

        $businesses = $this->db->table('businesses')->whereIn('id', $businessIds)->orderBy('name')->get()->getResultArray();
        $headOfficeBusinessIds = array_values(array_filter($businessIds, fn (int $id) => $this->scope->canAccessBusiness($id, $userId)));

        // Shops: those the user can access, plus every shop (incl. inactive) of businesses with Head Office access.
        $shopQuery = $this->db->table('locations')->groupStart()->whereIn('id', $shopIds ?: [0]);
        if ($headOfficeBusinessIds) {
            $shopQuery->orWhereIn('business_id', $headOfficeBusinessIds);
        }
        $shops = $shopQuery->groupEnd()->orderBy('is_active', 'DESC')->orderBy('name')->get()->getResultArray();
        $allShopIds = array_map(static fn ($s) => (int) $s['id'], $shops);

        $readiness = (new BranchReadinessService($this->db))->forLocations($allShopIds);
        $live = $this->liveFigures($allShopIds);
        $staffByShop = $this->staffByShop($allShopIds);
        $headOffice = $this->headOfficeUsers($businessIds);

        $tree = [];
        foreach ($businesses as $business) {
            $id = (int) $business['id'];
            $business['can_manage'] = in_array($id, $headOfficeBusinessIds, true);
            $business['head_office'] = $headOffice[$id] ?? [];
            $business['shops'] = [];
            foreach ($shops as $shop) {
                if ((int) $shop['business_id'] !== $id) {
                    continue;
                }
                $sid = (int) $shop['id'];
                $checks = $readiness[$sid] ?? [];
                $shop['readiness'] = $checks;
                $shop['ready'] = $checks && !array_filter($checks, static fn ($c) => !$c['ok']);
                $shop['live'] = $live[$sid] ?? $this->emptyLive();
                $shop['staff'] = $staffByShop[$sid] ?? [];
                $shop['can_switch'] = in_array($sid, $shopIds, true) && (int) $shop['is_active'] === 1;
                $business['shops'][] = $shop;
            }
            $userIds = array_merge(array_column($business['head_office'], 'id'), ...array_map(static fn ($s) => array_column($s['staff'], 'id'), $business['shops'] ?: [['staff' => []]]));
            $business['totals'] = [
                'shops' => count($business['shops']),
                'active_shops' => count(array_filter($business['shops'], static fn ($s) => (int) $s['is_active'] === 1)),
                'ready_shops' => count(array_filter($business['shops'], static fn ($s) => $s['ready'])),
                'users' => count(array_unique(array_map('intval', $userIds))),
                'gas_kg' => array_sum(array_map(static fn ($s) => $s['live']['gas_kg'], $business['shops'])),
                'sales_today' => array_sum(array_map(static fn ($s) => $s['live']['sales_today'], $business['shops'])),
            ];
            $tree[] = $business;
        }
        return $tree;
    }

    /** Gas in filled cylinders, filled/empty counts, customers, open cash session and today's sales per shop. */
    private function liveFigures(array $shopIds): array
    {
        if (!$shopIds) {
            return [];
        }
        $out = [];
        foreach ($shopIds as $id) {
            $out[$id] = $this->emptyLive();
        }
        $in = implode(',', array_fill(0, count($shopIds), '?'));
        foreach ($this->db->query(
            "SELECT location_id,SUM(status='filled') filled,SUM(status='empty') empty_count,COALESCE(SUM(CASE WHEN status='filled' THEN gas_weight_kg END),0) gas_kg
             FROM cylinder_units WHERE location_id IN ($in) AND status IN ('filled','empty') GROUP BY location_id",
            $shopIds
        )->getResultArray() as $r) {
            $out[(int) $r['location_id']]['filled'] = (int) $r['filled'];
            $out[(int) $r['location_id']]['empty'] = (int) $r['empty_count'];
            $out[(int) $r['location_id']]['gas_kg'] = (float) $r['gas_kg'];
        }
        foreach ($this->db->query("SELECT location_id,COUNT(*) n FROM customers WHERE location_id IN ($in) AND is_active=1 GROUP BY location_id", $shopIds)->getResultArray() as $r) {
            $out[(int) $r['location_id']]['customers'] = (int) $r['n'];
        }
        foreach ($this->db->query(
            "SELECT cr.location_id,cr.code FROM cash_sessions cs JOIN cash_registers cr ON cr.id=cs.register_id WHERE cr.location_id IN ($in) AND cs.status='open'",
            $shopIds
        )->getResultArray() as $r) {
            $out[(int) $r['location_id']]['cash_open'] = true;
            $out[(int) $r['location_id']]['register'] = $r['code'];
        }
        foreach ($this->db->query(
            "SELECT location_id,COUNT(*) n,COALESCE(SUM(total_amount),0) total FROM sales
             WHERE location_id IN ($in) AND status='posted' AND transaction_type NOT IN ('security_deposit','cylinder_return')
               AND transaction_at>=? AND transaction_at<? GROUP BY location_id",
            [...$shopIds, date('Y-m-d 00:00:00'), date('Y-m-d 00:00:00', strtotime('+1 day'))]
        )->getResultArray() as $r) {
            $out[(int) $r['location_id']]['sales_today'] = (float) $r['total'];
            $out[(int) $r['location_id']]['sale_count_today'] = (int) $r['n'];
        }
        return $out;
    }

    private function emptyLive(): array
    {
        return ['filled' => 0, 'empty' => 0, 'gas_kg' => 0.0, 'customers' => 0, 'cash_open' => false, 'register' => null, 'sales_today' => 0.0, 'sale_count_today' => 0];
    }

    private function staffByShop(array $shopIds): array
    {
        if (!$shopIds) {
            return [];
        }
        $out = [];
        foreach ($this->db->table('user_shop_access usa')
            ->select('usa.location_id,usa.is_default,u.id,u.full_name,u.username,u.email,u.is_active,u.last_login_at,r.code role_code,r.name role_name')
            ->join('users u', 'u.id=usa.user_id')->join('roles r', 'r.id=u.role_id', 'left')
            ->whereIn('usa.location_id', $shopIds)->orderBy('u.full_name')->get()->getResultArray() as $row) {
            $out[(int) $row['location_id']][] = $row;
        }
        return $out;
    }

    private function headOfficeUsers(array $businessIds): array
    {
        $out = [];
        foreach ($this->db->table('user_business_access uba')
            ->select('uba.business_id,uba.is_default,u.id,u.full_name,u.username,u.email,u.is_active,u.last_login_at,r.code role_code,r.name role_name')
            ->join('users u', 'u.id=uba.user_id')->join('roles r', 'r.id=u.role_id', 'left')
            ->whereIn('uba.business_id', $businessIds)->orderBy('u.full_name')->get()->getResultArray() as $row) {
            $out[(int) $row['business_id']][] = $row;
        }
        return $out;
    }
}
