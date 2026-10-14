<?php

namespace App\Controllers\Api\V1\Staff;

use App\Services\ApiSessionService;
use App\Services\AuditService;
use App\Services\PermissionService;
use App\Services\ShopScopeService;
use CodeIgniter\Controller;
use Config\Database;

class Businesses extends Controller
{
    protected function principal(): ?array
    {
        $header = trim($this->request->getHeaderLine('Authorization'));
        if (!preg_match('/^Bearer\s+([a-f0-9]{64})$/i', $header, $match)) {
            return null;
        }
        return (new ApiSessionService())->authenticateAccessToken($match[1], 'staff');
    }

    protected function payload(): array
    {
        $json = $this->request->getJSON(true);
        if (is_array($json)) {
            return $json;
        }
        $post = $this->request->getPost();
        return is_array($post) ? $post : [];
    }

    protected function fail(int $status, string $code, string $message)
    {
        return $this->response->setStatusCode($status)->setJSON(['error' => ['code' => $code, 'message' => $message]]);
    }

    protected function guard(): array
    {
        $principal = $this->principal();
        if (!$principal) {
            return ['response' => $this->fail(401, 'INVALID_OR_EXPIRED_TOKEN', 'A valid staff access token is required.')];
        }
        return ['principal' => $principal, 'user_id' => (int) $principal['user_id']];
    }

    protected function canViewBusiness(int $businessId, int $userId): bool
    {
        $scope = new ShopScopeService();
        if ($scope->canAccessBusiness($businessId, $userId)) {
            return true;
        }
        if (!$scope->isSystemAdministrator($userId)) {
            return false;
        }
        return Database::connect()->table('businesses')
            ->where(['id' => $businessId, 'is_active' => 1])
            ->countAllResults() > 0;
    }

    public function index()
    {
        $guard = $this->guard();
        if (isset($guard['response'])) {
            return $guard['response'];
        }
        $userId = (int) $guard['user_id'];
        $scope = new ShopScopeService();
        $businessIds = $scope->accessibleBusinessIds($userId);

        // A shop-only staff account may see its own business label, but does not
        // gain Head Office permissions or visibility into sibling shops.
        $shopIds = $scope->accessibleShopIds($userId);
        if ($shopIds) {
            $ownedRows = Database::connect()->table('locations')
                ->select('business_id')
                ->whereIn('id', $shopIds)
                ->where('business_id IS NOT NULL', null, false)
                ->get()->getResultArray();
            foreach ($ownedRows as $row) {
                $businessIds[] = (int) $row['business_id'];
            }
        }
        $businessIds = array_values(array_unique(array_filter($businessIds, static fn (int $id): bool => $id > 0)));
        if (!$businessIds) {
            return $this->response->setJSON(['data' => ['businesses' => []]]);
        }

        $rows = Database::connect()->table('businesses')
            ->whereIn('id', $businessIds)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get()->getResultArray();

        return $this->response->setJSON(['data' => ['businesses' => $rows]]);
    }

    public function createBusiness()
    {
        $guard = $this->guard();
        if (isset($guard['response'])) {
            return $guard['response'];
        }
        $userId = (int) $guard['user_id'];
        if (!PermissionService::allowsForUser('APPLICATION_SETTINGS_MANAGE', $userId)) {
            return $this->fail(403, 'SYSTEM_ADMIN_REQUIRED', 'Only a System Administrator can create a new business.');
        }

        $input = $this->payload();
        $code = strtoupper(trim((string) ($input['code'] ?? '')));
        $name = trim((string) ($input['name'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $phone = trim((string) ($input['phone'] ?? ''));
        $address = trim((string) ($input['address'] ?? ''));
        $city = trim((string) ($input['city'] ?? ''));

        if (!preg_match('/^[A-Z0-9_-]{2,40}$/', $code) || $name === '' || mb_strlen($name) > 150
            || mb_strlen($email) > 150 || mb_strlen($phone) > 30 || mb_strlen($address) > 255 || mb_strlen($city) > 80) {
            return $this->fail(422, 'VALIDATION_FAILED', 'Enter a valid business code and name. Contact fields exceed their supported lengths.');
        }

        $db = Database::connect();
        try {
            $db->transBegin();
            $db->table('businesses')->insert([
                'code' => $code,
                'name' => $name,
                'email' => $email !== '' ? $email : null,
                'phone' => $phone !== '' ? $phone : null,
                'address' => $address !== '' ? $address : null,
                'city' => $city !== '' ? $city : null,
                'is_active' => 1,
            ]);
            $businessId = (int) $db->insertID();
            if (!$db->transStatus()) {
                throw new \RuntimeException('Business could not be created.');
            }
            $db->transCommit();

            AuditService::log(
                'BUSINESS_CREATED',
                'business',
                $businessId,
                null,
                ['code' => $code, 'name' => $name, 'city' => $city],
                $userId,
                null
            );

            return $this->response->setStatusCode(201)->setJSON(['data' => [
                'business_id' => $businessId,
                'code' => $code,
                'name' => $name,
                'message' => 'Business created. Create its first shop and assign a Head Office user next.',
            ]]);
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Business create failed: {message}', ['message' => $e->getMessage()]);
            return $this->fail(409, 'BUSINESS_CREATE_FAILED', 'The business could not be created. Its code may already be in use.');
        }
    }

    public function shops(int $businessId)
    {
        $guard = $this->guard();
        if (isset($guard['response'])) {
            return $guard['response'];
        }
        $userId = (int) $guard['user_id'];
        if (!$this->canViewBusiness($businessId, $userId)) {
            return $this->fail(403, 'BUSINESS_ACCESS_DENIED', 'You do not have Head Office access to this business.');
        }

        $business = Database::connect()->table('businesses')->where(['id' => $businessId, 'is_active' => 1])->get()->getRowArray();
        if (!$business) {
            return $this->fail(404, 'BUSINESS_NOT_FOUND', 'Active business not found.');
        }

        $rows = Database::connect()->table('locations')
            ->where('business_id', $businessId)
            ->orderBy('name')
            ->get()->getResultArray();

        return $this->response->setJSON(['data' => ['business' => $business, 'shops' => $rows]]);
    }

    public function createShop(int $businessId)
    {
        $guard = $this->guard();
        if (isset($guard['response'])) {
            return $guard['response'];
        }
        $userId = (int) $guard['user_id'];
        if (!PermissionService::allowsForUser('SHOP_ACCESS_MANAGE', $userId)
            || !$this->canViewBusiness($businessId, $userId)) {
            return $this->fail(403, 'BUSINESS_ACCESS_DENIED', 'You do not have permission to create a shop in this business.');
        }

        $input = $this->payload();
        $code = strtoupper(trim((string) ($input['code'] ?? '')));
        $name = trim((string) ($input['name'] ?? ''));
        $address = trim((string) ($input['address'] ?? ''));
        $city = trim((string) ($input['city'] ?? ''));
        $phone = trim((string) ($input['phone'] ?? ''));

        if (!preg_match('/^[A-Z0-9_-]{2,30}$/', $code) || $name === '' || mb_strlen($name) > 120
            || mb_strlen($address) > 255 || mb_strlen($city) > 80 || mb_strlen($phone) > 30) {
            return $this->fail(422, 'VALIDATION_FAILED', 'Enter a valid shop code and name. Contact fields exceed their supported lengths.');
        }

        $db = Database::connect();
        $business = $db->table('businesses')->where(['id' => $businessId, 'is_active' => 1])->get()->getRowArray();
        if (!$business) {
            return $this->fail(404, 'BUSINESS_NOT_FOUND', 'Active business not found.');
        }

        try {
            $db->transBegin();
            $db->table('locations')->insert([
                'business_id' => $businessId,
                'code' => $code,
                'name' => $name,
                'address' => $address !== '' ? $address : null,
                'city' => $city !== '' ? $city : null,
                'phone' => $phone !== '' ? $phone : null,
                'is_active' => 1,
            ]);
            $shopId = (int) $db->insertID();

            $db->table('shop_settings')->insert(['location_id' => $shopId]);
            $db->table('cash_registers')->insert([
                'location_id' => $shopId,
                'code' => 'REG-01',
                'name' => 'Main Counter',
                'is_active' => 1,
            ]);
            $db->table('inventory_policies')->insert([
                'location_id' => $shopId,
                'cylinder_type_id' => null,
            ]);

            if (!$db->transStatus()) {
                throw new \RuntimeException('Shop provisioning failed.');
            }
            $db->transCommit();

            AuditService::log(
                'SHOP_CREATED',
                'location',
                $shopId,
                null,
                ['business_id' => $businessId, 'code' => $code, 'name' => $name],
                $userId,
                $shopId
            );

            return $this->response->setStatusCode(201)->setJSON(['data' => [
                'shop_id' => $shopId,
                'business_id' => $businessId,
                'code' => $code,
                'name' => $name,
                'message' => 'Shop created with default configuration and a counter. Activate products and assign shop staff before taking orders.',
            ]]);
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Shop create failed: {message}', ['message' => $e->getMessage()]);
            return $this->fail(409, 'SHOP_CREATE_FAILED', 'The shop could not be provisioned. Its code may already be in use or a default record may conflict.');
        }
    }

    public function staffAccess(int $businessId)
    {
        $guard = $this->guard();
        if (isset($guard['response'])) {
            return $guard['response'];
        }
        $userId = (int) $guard['user_id'];
        if (!PermissionService::allowsForUser('SHOP_ACCESS_MANAGE', $userId) || !$this->canViewBusiness($businessId, $userId)) {
            return $this->fail(403, 'BUSINESS_ACCESS_DENIED', 'You do not have permission to manage access for this business.');
        }

        $sql = "SELECT u.id AS user_id,u.full_name,u.username,u.is_active,
                    'head_office' AS access_type,NULL AS shop_id,NULL AS shop_name,uba.is_default
             FROM user_business_access uba JOIN users u ON u.id=uba.user_id
             WHERE uba.business_id=?
             UNION ALL
             SELECT u.id AS user_id,u.full_name,u.username,u.is_active,
                    'shop' AS access_type,l.id AS shop_id,l.name AS shop_name,usa.is_default
             FROM user_shop_access usa
             JOIN users u ON u.id=usa.user_id
             JOIN locations l ON l.id=usa.location_id
             WHERE l.business_id=?
             ORDER BY full_name,user_id";
        $rows = Database::connect()->query($sql, [$businessId, $businessId])->getResultArray();

        return $this->response->setJSON(['data' => ['business_id' => $businessId, 'assignments' => $rows]]);
    }

    public function setStaffAccess(int $businessId)
    {
        $guard = $this->guard();
        if (isset($guard['response'])) {
            return $guard['response'];
        }
        $actorId = (int) $guard['user_id'];
        $scope = new ShopScopeService();
        $isSystemAdmin = $scope->isSystemAdministrator($actorId);
        if (!PermissionService::allowsForUser('SHOP_ACCESS_MANAGE', $actorId)
            || (!$isSystemAdmin && !$scope->canAccessBusiness($businessId, $actorId))) {
            return $this->fail(403, 'BUSINESS_ACCESS_DENIED', 'You do not have permission to assign staff in this business.');
        }

        $input = $this->payload();
        $targetUserId = (int) ($input['user_id'] ?? 0);
        $accessType = trim((string) ($input['access_type'] ?? 'shop'));
        $shopId = (int) ($input['shop_id'] ?? 0);
        $enabled = $this->parseBoolean($input['enabled'] ?? true);
        $isDefault = $this->parseBoolean($input['is_default'] ?? false);

        if ($targetUserId <= 0 || !in_array($accessType, ['head_office', 'shop'], true)
            || $enabled === null || $isDefault === null) {
            return $this->fail(422, 'VALIDATION_FAILED', 'A valid user, access type, enabled flag and default flag are required.');
        }
        if ($accessType === 'head_office' && !$isSystemAdmin) {
            return $this->fail(403, 'SYSTEM_ADMIN_REQUIRED', 'Only a System Administrator can grant Head Office business access.');
        }

        $db = Database::connect();
        $target = $db->table('users')->where(['id' => $targetUserId, 'is_active' => 1])->get()->getRowArray();
        if (!$target) {
            return $this->fail(404, 'STAFF_NOT_FOUND', 'Select an active staff account.');
        }

        if ($accessType === 'shop') {
            $shop = $db->table('locations')
                ->where(['id' => $shopId, 'business_id' => $businessId, 'is_active' => 1])
                ->get()->getRowArray();
            if (!$shop) {
                return $this->fail(422, 'INVALID_SHOP', 'The selected shop must belong to this business and be active.');
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
                    $exists = $db->table('user_business_access')->where(['user_id' => $targetUserId, 'business_id' => $businessId])->countAllResults() > 0;
                    $data = ['access_level' => 'head_office', 'is_default' => $isDefault ? 1 : 0];
                    if ($exists) {
                        $db->table('user_business_access')->where(['user_id' => $targetUserId, 'business_id' => $businessId])->update($data);
                    } else {
                        $db->table('user_business_access')->insert(['user_id' => $targetUserId, 'business_id' => $businessId] + $data);
                    }
                } else {
                    $db->table('user_business_access')->where(['user_id' => $targetUserId, 'business_id' => $businessId])->delete();
                }
            } else {
                if ($enabled) {
                    if ($isDefault) {
                        $db->table('user_shop_access')->where('user_id', $targetUserId)->update(['is_default' => 0]);
                        $db->table('users')->where('id', $targetUserId)->update(['location_id' => $shopId]);
                    }
                    $exists = $db->table('user_shop_access')->where(['user_id' => $targetUserId, 'location_id' => $shopId])->countAllResults() > 0;
                    if ($exists) {
                        $db->table('user_shop_access')->where(['user_id' => $targetUserId, 'location_id' => $shopId])->update(['is_default' => $isDefault ? 1 : 0]);
                    } else {
                        $db->table('user_shop_access')->insert([
                            'user_id' => $targetUserId,
                            'location_id' => $shopId,
                            'is_default' => $isDefault ? 1 : 0,
                        ]);
                    }
                } else {
                    $db->table('user_shop_access')->where(['user_id' => $targetUserId, 'location_id' => $shopId])->delete();
                    if ((int) ($target['location_id'] ?? 0) === $shopId) {
                        $db->table('users')->where('id', $targetUserId)->update(['location_id' => null]);
                    }
                }
            }

            // Remove the implicit legacy single-shop fallback when explicit access
            // has been removed completely. Do not leave a removed shop in location_id.
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
            AuditService::log(
                'STAFF_ACCESS_CHANGED',
                'user',
                $targetUserId,
                ['business_id' => $businessId],
                ['business_id' => $businessId, 'access_type' => $accessType, 'shop_id' => $accessType === 'shop' ? $shopId : null, 'enabled' => $enabled, 'is_default' => $enabled && $isDefault],
                $actorId,
                $accessType === 'shop' ? $shopId : null
            );

            return $this->response->setJSON(['data' => [
                'user_id' => $targetUserId,
                'business_id' => $businessId,
                'access_type' => $accessType,
                'shop_id' => $accessType === 'shop' ? $shopId : null,
                'enabled' => $enabled,
                'is_default' => $enabled && $isDefault,
                'message' => 'Staff access saved. The staff member must sign in again because existing sessions were revoked.',
            ]]);
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Staff access assignment failed: {message}', ['message' => $e->getMessage()]);
            return $this->fail(409, 'STAFF_ACCESS_SAVE_FAILED', 'The staff access assignment could not be saved.');
        }
    }

    protected function parseBoolean($value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (!is_scalar($value)) {
            return null;
        }
        return filter_var((string) $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}
