<?php

namespace App\Controllers\Api\V1\Staff;

use App\Services\ApiSessionService;
use App\Services\PermissionService;
use App\Services\ShopScopeService;
use CodeIgniter\Controller;
use Config\Database;

class Me extends Controller
{
    protected function principal(): ?array
    {
        $header = trim($this->request->getHeaderLine('Authorization'));
        if (!preg_match('/^Bearer\s+([a-f0-9]{64})$/i', $header, $match)) {
            return null;
        }

        return (new ApiSessionService())->authenticateAccessToken($match[1], 'staff');
    }

    public function show()
    {
        $principal = $this->principal();
        if (!$principal) {
            return $this->response->setStatusCode(401)->setJSON(['error' => ['code' => 'INVALID_OR_EXPIRED_TOKEN', 'message' => 'The staff session is invalid or expired.']]);
        }

        $userId = (int) $principal['user_id'];
        $user = Database::connect()->table('users u')
            ->select('u.id,u.username,u.email,u.full_name,u.location_id,r.code AS role_code,r.name AS role_name')
            ->join('roles r', 'r.id=u.role_id', 'inner')
            ->where(['u.id' => $userId, 'u.is_active' => 1])
            ->get()->getRowArray();

        if (!$user) {
            return $this->response->setStatusCode(403)->setJSON(['error' => ['code' => 'STAFF_DISABLED', 'message' => 'The staff account is no longer active.']]);
        }

        $scope = new ShopScopeService();
        $shopIds = $scope->accessibleShopIds($userId);
        $currentShopId = (int) $this->request->getHeaderLine('X-Shop-Id');
        if ($currentShopId <= 0 || !$scope->canAccessShop($currentShopId, $userId)) {
            $currentShopId = $scope->defaultShopIdForUser($userId) ?? 0;
        }

        return $this->response->setJSON(['data' => [
            'staff' => [
                'id' => (int) $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'name' => $user['full_name'],
                'role' => $user['role_code'],
                'role_name' => $user['role_name'],
            ],
            'current_shop_id' => $currentShopId,
            'permissions' => PermissionService::allForUser($userId),
            'shops' => $this->shopRows($shopIds),
        ]]);
    }

    public function shops()
    {
        $principal = $this->principal();
        if (!$principal) {
            return $this->response->setStatusCode(401)->setJSON(['error' => ['code' => 'INVALID_OR_EXPIRED_TOKEN', 'message' => 'The staff session is invalid or expired.']]);
        }

        return $this->response->setJSON(['data' => ['shops' => $this->shopRows((new ShopScopeService())->accessibleShopIds((int) $principal['user_id']))]]);
    }

    protected function shopRows(array $shopIds): array
    {
        if (!$shopIds) {
            return [];
        }

        return Database::connect()->table('locations l')
            ->select('l.id,l.name,l.business_id,b.name AS business_name')
            ->join('businesses b', 'b.id=l.business_id', 'left')
            ->whereIn('l.id', $shopIds)
            ->orderBy('b.name')
            ->orderBy('l.name')
            ->get()->getResultArray();
    }
}
