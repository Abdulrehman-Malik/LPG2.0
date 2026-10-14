<?php

namespace App\Controllers;

use App\Services\ApiSessionService;
use App\Services\AuditService;
use App\Services\PermissionService;
use App\Services\ShopScopeService;
use CodeIgniter\Controller;
use Config\Database;

class BusinessManagement extends Controller
{
    protected function canCreateBusiness(): bool
    {
        return PermissionService::allows('APPLICATION_SETTINGS_MANAGE');
    }

    public function index()
    {
        if (!PermissionService::allows('APPLICATION_SETTINGS_VIEW')
            && !PermissionService::allows('APPLICATION_SETTINGS_MANAGE')
            && !PermissionService::allows('SHOP_ACCESS_MANAGE')) {
            return $this->response->setStatusCode(403)->setBody('You do not have permission to view business and shop administration.');
        }

        $userId = (int) (session()->get('user_id') ?? 0);
        $scope = new ShopScopeService();
        $canManageAccess = PermissionService::allows('SHOP_ACCESS_MANAGE');
        $businessIds = $scope->accessibleBusinessIds($userId);
        $accessibleShopIds = $scope->accessibleShopIds($userId);
        if ($accessibleShopIds) {
            $rows = Database::connect()->table('locations')
                ->select('business_id')
                ->whereIn('id', $accessibleShopIds)
                ->where('business_id IS NOT NULL', null, false)
                ->get()->getResultArray();
            foreach ($rows as $row) {
                $businessIds[] = (int) $row['business_id'];
            }
        }
        $businessIds = array_values(array_unique(array_filter(array_map('intval', $businessIds), static fn(int $id): bool => $id > 0)));

        $businesses = [];
        if ($businessIds) {
            $businesses = Database::connect()->table('businesses')
                ->whereIn('id', $businessIds)
                ->where('is_active', 1)
                ->orderBy('name')
                ->get()->getResultArray();
        }

        $db = Database::connect();
        foreach ($businesses as &$business) {
            $businessId = (int) $business['id'];
            $business['can_manage_business'] = $scope->canAccessBusiness($businessId, $userId);
            $business['shops'] = [];
            if ($accessibleShopIds) {
                $business['shops'] = $db->table('locations')
                    ->where(['business_id' => $businessId, 'is_active' => 1])
                    ->whereIn('id', $accessibleShopIds)
                    ->orderBy('name')
                    ->get()->getResultArray();
            }

            $business['assignments'] = [];
            $business['staff_options'] = [];
            if ($canManageAccess && $business['can_manage_business']) {
                $business['assignments'] = $db->query(
                    "SELECT u.id AS user_id,u.full_name,u.username,u.is_active,'head_office' AS access_type,NULL AS shop_id,NULL AS shop_name,uba.is_default
                     FROM user_business_access uba JOIN users u ON u.id=uba.user_id
                     WHERE uba.business_id=?
                     UNION ALL
                     SELECT u.id AS user_id,u.full_name,u.username,u.is_active,'shop' AS access_type,l.id AS shop_id,l.name AS shop_name,usa.is_default
                     FROM user_shop_access usa JOIN users u ON u.id=usa.user_id JOIN locations l ON l.id=usa.location_id
                     WHERE l.business_id=?
                     ORDER BY full_name,user_id",
                    [$businessId, $businessId]
                )->getResultArray();

                $shopIdsForBusiness = array_map(static fn(array $shop): int => (int) $shop['id'], $business['shops']);
                if ($shopIdsForBusiness) {
                    $business['staff_options'] = $db->table('users u')
                        ->select('u.id,u.full_name,u.username')
                        ->groupStart()
                            ->whereIn('u.location_id', $shopIdsForBusiness)
                            ->orWhereIn('u.id', array_column($business['assignments'], 'user_id') ?: [0])
                        ->groupEnd()
                        ->where('u.is_active', 1)
                        ->orderBy('u.full_name')
                        ->get()->getResultArray();
                }
            }
        }
        unset($business);

        return view('business_management/index', [
            'title' => 'Businesses & Shops',
            'businesses' => $businesses,
            'canCreateBusiness' => $this->canCreateBusiness(),
            'canManageAccess' => $canManageAccess,
            'canCreateShop' => $canManageAccess,
            'isSystemAdmin' => $scope->isSystemAdministrator($userId),
        ]);
    }

    public function saveBusiness()
    {
        $userId = (int) (session()->get('user_id') ?? 0);
        if (!$this->canCreateBusiness()) {
            return $this->response->setStatusCode(403)->setBody('Only a System Administrator can create a business.');
        }

        $input = [
            'code' => strtoupper(trim((string) $this->request->getPost('code'))),
            'name' => trim((string) $this->request->getPost('name')),
            'email' => trim((string) $this->request->getPost('email')),
            'phone' => trim((string) $this->request->getPost('phone')),
            'address' => trim((string) $this->request->getPost('address')),
            'city' => trim((string) $this->request->getPost('city')),
        ];
        if (!preg_match('/^[A-Z0-9_-]{2,40}$/', $input['code']) || $input['name'] === '' || mb_strlen($input['name']) > 150
            || mb_strlen($input['email']) > 150 || mb_strlen($input['phone']) > 30
            || mb_strlen($input['address']) > 255 || mb_strlen($input['city']) > 80
            || ($input['email'] !== '' && !filter_var($input['email'], FILTER_VALIDATE_EMAIL))) {
            return redirect()->back()->withInput()->with('error', 'Enter a valid business code and name. Check the contact fields.');
        }

        $db = Database::connect();
        try {
            $db->transBegin();
            $db->table('businesses')->insert([
                'code' => $input['code'],
                'name' => $input['name'],
                'email' => $input['email'] !== '' ? $input['email'] : null,
                'phone' => $input['phone'] !== '' ? $input['phone'] : null,
                'address' => $input['address'] !== '' ? $input['address'] : null,
                'city' => $input['city'] !== '' ? $input['city'] : null,
                'is_active' => 1,
            ]);
            $businessId = (int) $db->insertID();
            if (!$db->transStatus()) {
                throw new \RuntimeException('Business record could not be saved.');
            }
            $db->transCommit();

            AuditService::log('BUSINESS_CREATED', 'business', $businessId, null, [
                'code' => $input['code'], 'name' => $input['name'], 'city' => $input['city'],
            ], $userId, null);
            return redirect()->to(site_url('business-management'))->with('success', 'Business created. Create a shop and assign staff access before processing orders.');
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Web business create failed: {message}', ['message' => $e->getMessage()]);
            return redirect()->back()->withInput()->with('error', 'Business could not be created. The code may already exist.');
        }
    }

    public function saveShop()
    {
        $userId = (int) (session()->get('user_id') ?? 0);
        $businessId = (int) $this->request->getPost('business_id');
        $scope = new ShopScopeService();
        if (!PermissionService::allows('SHOP_ACCESS_MANAGE') || !$scope->canAccessBusiness($businessId, $userId)) {
            return $this->response->setStatusCode(403)->setBody('You do not have permission to create a shop in this business.');
        }

        $input = [
            'code' => strtoupper(trim((string) $this->request->getPost('code'))),
            'name' => trim((string) $this->request->getPost('name')),
            'address' => trim((string) $this->request->getPost('address')),
            'city' => trim((string) $this->request->getPost('city')),
            'phone' => trim((string) $this->request->getPost('phone')),
        ];
        if (!preg_match('/^[A-Z0-9_-]{2,30}$/', $input['code']) || $input['name'] === '' || mb_strlen($input['name']) > 120
            || mb_strlen($input['address']) > 255 || mb_strlen($input['city']) > 80 || mb_strlen($input['phone']) > 30) {
            return redirect()->back()->withInput()->with('error', 'Enter a valid shop code and name. Contact fields exceed their supported lengths.');
        }

        $db = Database::connect();
        if (!$db->table('businesses')->where(['id' => $businessId, 'is_active' => 1])->countAllResults()) {
            return redirect()->back()->with('error', 'The selected business is not active.');
        }

        try {
            $db->transBegin();
            $db->table('locations')->insert([
                'business_id' => $businessId,
                'code' => $input['code'],
                'name' => $input['name'],
                'address' => $input['address'] !== '' ? $input['address'] : null,
                'city' => $input['city'] !== '' ? $input['city'] : null,
                'phone' => $input['phone'] !== '' ? $input['phone'] : null,
                'is_active' => 1,
            ]);
            $shopId = (int) $db->insertID();
            $db->table('shop_settings')->insert(['location_id' => $shopId]);
            $db->table('cash_registers')->insert([
                'location_id' => $shopId, 'code' => 'REG-01', 'name' => 'Main Counter', 'is_active' => 1,
            ]);
            $db->table('inventory_policies')->insert([
                'location_id' => $shopId, 'cylinder_type_id' => null,
            ]);
            if (!$db->transStatus()) {
                throw new \RuntimeException('Shop provisioning failed.');
            }
            $db->transCommit();

            AuditService::log('SHOP_CREATED', 'location', $shopId, null, [
                'business_id' => $businessId, 'code' => $input['code'], 'name' => $input['name'],
            ], $userId, $shopId);
            return redirect()->to(site_url('business-management'))->with('success', 'Shop created with default settings and counter. Activate catalog products and assign shop staff before taking orders.');
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Web shop create failed: {message}', ['message' => $e->getMessage()]);
            return redirect()->back()->withInput()->with('error', 'Shop could not be created. The shop code may already exist or a default record conflicted.');
        }
    }

    public function saveStaffAccess()
    {
        $actorId = (int) (session()->get('user_id') ?? 0);
        $businessId = (int) $this->request->getPost('business_id');
        $targetUserId = (int) $this->request->getPost('user_id');
        $accessType = trim((string) $this->request->getPost('access_type'));
        $shopId = (int) $this->request->getPost('shop_id');
        $enabled = $this->request->getPost('enabled') === '1';
        $isDefault = $this->request->getPost('is_default') === '1';
        $scope = new ShopScopeService();
        $isSystemAdmin = $scope->isSystemAdministrator($actorId);

        if (!PermissionService::allows('SHOP_ACCESS_MANAGE')
            || (!$isSystemAdmin && !$scope->canAccessBusiness($businessId, $actorId))) {
            return $this->response->setStatusCode(403)->setBody('You do not have permission to manage staff access for this business.');
        }
        if ($targetUserId <= 0 || !in_array($accessType, ['head_office', 'shop'], true)) {
            return redirect()->back()->with('error', 'Select a valid staff user and access type.');
        }
        if ($accessType === 'head_office' && !$isSystemAdmin) {
            return $this->response->setStatusCode(403)->setBody('Only a System Administrator can grant Head Office business access.');
        }

        $db = Database::connect();
        $target = $db->table('users')->where(['id' => $targetUserId, 'is_active' => 1])->get()->getRowArray();
        if (!$target) {
            return redirect()->back()->with('error', 'Select an active staff account.');
        }
        if ($accessType === 'shop') {
            $shop = $db->table('locations')->where(['id' => $shopId, 'business_id' => $businessId, 'is_active' => 1])->get()->getRowArray();
            if (!$shop || !$scope->canAccessShop($shopId, $actorId)) {
                return $this->response->setStatusCode(403)->setBody('The selected shop is outside this user scope.');
            }
        }

        $db->transBegin();
        try {
            if ($accessType === 'head_office') {
                if ($enabled) {
                    if ($isDefault) {
                        $defaultShop = $db->table('locations')->select('id')
                            ->where(['business_id' => $businessId, 'is_active' => 1])
                            ->orderBy('id', 'ASC')->get()->getRowArray();
                        if (!$defaultShop) {
                            throw new \RuntimeException('Create an active shop in this business before making Head Office access the default.');
                        }
                        $db->table('user_business_access')->where('user_id', $targetUserId)->update(['is_default' => 0]);
                        $db->table('user_shop_access')->where('user_id', $targetUserId)->update(['is_default' => 0]);
                        $db->table('users')->where('id', $targetUserId)->update(['location_id' => (int) $defaultShop['id']]);
                    }
                    $record = ['access_level' => 'head_office', 'is_default' => $isDefault ? 1 : 0];
                    $exists = $db->table('user_business_access')->where(['user_id' => $targetUserId, 'business_id' => $businessId])->countAllResults() > 0;
                    if ($exists) {
                        $db->table('user_business_access')->where(['user_id' => $targetUserId, 'business_id' => $businessId])->update($record);
                    } else {
                        $db->table('user_business_access')->insert(['user_id' => $targetUserId, 'business_id' => $businessId] + $record);
                    }
                } else {
                    $db->table('user_business_access')->where(['user_id' => $targetUserId, 'business_id' => $businessId])->delete();
                }
            } else {
                if ($enabled) {
                    if ($isDefault) {
                        $db->table('user_shop_access')->where('user_id', $targetUserId)->update(['is_default' => 0]);
                        $db->table('user_business_access')->where('user_id', $targetUserId)->update(['is_default' => 0]);
                        $db->table('users')->where('id', $targetUserId)->update(['location_id' => $shopId]);
                    }
                    $exists = $db->table('user_shop_access')->where(['user_id' => $targetUserId, 'location_id' => $shopId])->countAllResults() > 0;
                    if ($exists) {
                        $db->table('user_shop_access')->where(['user_id' => $targetUserId, 'location_id' => $shopId])->update(['is_default' => $isDefault ? 1 : 0]);
                    } else {
                        $db->table('user_shop_access')->insert([
                            'user_id' => $targetUserId, 'location_id' => $shopId, 'is_default' => $isDefault ? 1 : 0,
                        ]);
                    }
                } else {
                    $db->table('user_shop_access')->where(['user_id' => $targetUserId, 'location_id' => $shopId])->delete();
                    if ((int) ($target['location_id'] ?? 0) === $shopId) {
                        $db->table('users')->where('id', $targetUserId)->update(['location_id' => null]);
                    }
                }
            }

            $shopAssignments = (int) $db->table('user_shop_access')->where('user_id', $targetUserId)->countAllResults();
            $businessAssignments = (int) $db->table('user_business_access')->where('user_id', $targetUserId)->countAllResults();
            if (!$isSystemAdmin && $shopAssignments === 0 && $businessAssignments === 0) {
                $db->table('users')->where('id', $targetUserId)->update(['location_id' => null]);
            }
            if (!$db->transStatus()) {
                throw new \RuntimeException('Staff access assignment failed.');
            }
            $db->transCommit();

            (new ApiSessionService())->revokeAllForStaff($targetUserId);
            AuditService::log('STAFF_ACCESS_CHANGED', 'user', $targetUserId, null, [
                'business_id' => $businessId, 'access_type' => $accessType, 'shop_id' => $accessType === 'shop' ? $shopId : null,
                'enabled' => $enabled, 'is_default' => $enabled && $isDefault,
            ], $actorId, $accessType === 'shop' ? $shopId : null);

            return redirect()->to(site_url('business-management'))->with('success', 'Staff access saved. The staff member must sign in again because existing mobile sessions were revoked.');
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Web staff access save failed: {message}', ['message' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Staff access could not be saved.');
        }
    }
}
