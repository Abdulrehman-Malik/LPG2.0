<?php

namespace App\Controllers\Api\V1\Staff;

use App\Services\ApiSessionService;
use App\Services\AuditService;
use App\Services\PermissionService;
use App\Services\ShopScopeService;
use CodeIgniter\Controller;
use Config\Database;

class Catalog extends Controller
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

    protected function businessContext(int $businessId, string $permission): array
    {
        $principal = $this->principal();
        if (!$principal) {
            return ['response' => $this->fail(401, 'INVALID_OR_EXPIRED_TOKEN', 'A valid staff access token is required.')];
        }
        $userId = (int) $principal['user_id'];
        if (!PermissionService::allowsForUser($permission, $userId)) {
            return ['response' => $this->fail(403, 'PERMISSION_DENIED', 'The staff account does not have the required catalog permission.')];
        }
        if (!(new ShopScopeService())->canAccessBusiness($businessId, $userId)) {
            return ['response' => $this->fail(403, 'BUSINESS_ACCESS_DENIED', 'The selected business is outside this account\'s access scope.')];
        }

        $business = Database::connect()->table('businesses')
            ->where(['id' => $businessId, 'is_active' => 1])
            ->get()->getRowArray();
        if (!$business) {
            return ['response' => $this->fail(404, 'BUSINESS_NOT_FOUND', 'Active business not found.')];
        }

        return ['principal' => $principal, 'user_id' => $userId, 'business' => $business];
    }

    public function businessCatalog(int $businessId)
    {
        $context = $this->businessContext($businessId, 'BUSINESS_CATALOG_VIEW');
        if (isset($context['response'])) {
            return $context['response'];
        }

        $rows = Database::connect()->table('business_catalog_products p')
            ->select('p.id,p.business_id,p.cylinder_type_id,p.sale_mode,p.display_name,p.description,p.quantity_unit,p.min_quantity,p.quantity_step,p.is_active,p.sort_order,p.created_at,p.updated_at,ct.code AS cylinder_code,ct.name AS cylinder_name,ct.capacity_kg')
            ->join('cylinder_types ct', 'ct.id=p.cylinder_type_id', 'inner')
            ->where('p.business_id', $businessId)
            ->orderBy('p.sort_order')->orderBy('p.display_name')
            ->get()->getResultArray();

        return $this->response->setJSON(['data' => [
            'business' => ['id' => (int) $context['business']['id'], 'name' => $context['business']['name']],
            'products' => $rows,
        ]]);
    }

    public function saveBusinessProduct(int $businessId)
    {
        $context = $this->businessContext($businessId, 'BUSINESS_CATALOG_MANAGE');
        if (isset($context['response'])) {
            return $context['response'];
        }

        $input = $this->payload();
        $productId = (int) ($input['product_id'] ?? 0);
        $cylinderTypeId = (int) ($input['cylinder_type_id'] ?? 0);
        $mode = trim((string) ($input['sale_mode'] ?? ''));
        $name = trim((string) ($input['display_name'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $active = $this->parseBoolean($input['is_active'] ?? true);
        if ($active === null) {
            return $this->fail(422, 'VALIDATION_FAILED', 'is_active must be true or false.');
        }
        $minimum = $input['min_quantity'] ?? ($mode === 'sell_gas_only' ? 0.1 : 1);
        $step = $input['quantity_step'] ?? ($mode === 'sell_gas_only' ? 0.1 : 1);
        $minimum = is_numeric($minimum) ? round((float) $minimum, 3) : 0;
        $step = is_numeric($step) ? round((float) $step, 3) : 0;

        if ($cylinderTypeId <= 0 || !in_array($mode, ['sell_gas_only','sell_filled','sell_empty'], true)
            || $name === '' || mb_strlen($name) > 150 || mb_strlen($description) > 500
            || $minimum <= 0 || $step <= 0) {
            return $this->fail(422, 'VALIDATION_FAILED', 'Provide a cylinder type, supported sale mode, display name, positive minimum quantity and positive quantity step.');
        }
        if ($mode !== 'sell_gas_only' && (floor($minimum) !== $minimum || floor($step) !== $step)) {
            return $this->fail(422, 'VALIDATION_FAILED', 'Cylinder sale minimum and step must be whole numbers.');
        }
        if ($mode === 'sell_gas_only' && ($minimum < 0.1 || $step < 0.1)) {
            return $this->fail(422, 'VALIDATION_FAILED', 'Gas refill minimum and quantity step must be at least 0.1 KG.');
        }

        $db = Database::connect();
        $type = $db->table('cylinder_types')->where(['id' => $cylinderTypeId, 'business_id' => $businessId, 'is_active' => 1])->get()->getRowArray();
        if (!$type) {
            return $this->fail(422, 'INVALID_CYLINDER_TYPE', 'Select an active cylinder type.');
        }

        if ($productId > 0) {
            $existing = $db->table('business_catalog_products')
                ->where(['id' => $productId, 'business_id' => $businessId])
                ->get()->getRowArray();
            if (!$existing) {
                return $this->fail(404, 'CATALOG_PRODUCT_NOT_FOUND', 'Catalog product not found in this business.');
            }
        }

        $data = [
            'business_id' => $businessId,
            'cylinder_type_id' => $cylinderTypeId,
            'sale_mode' => $mode,
            'display_name' => $name,
            'description' => $description !== '' ? $description : null,
            'quantity_unit' => $mode === 'sell_gas_only' ? 'kg' : 'cylinder',
            'min_quantity' => $minimum,
            'quantity_step' => $step,
            'is_active' => $active ? 1 : 0,
            'sort_order' => max(0, (int) ($input['sort_order'] ?? 0)),
            'updated_by' => (int) $context['user_id'],
        ];

        $db->transBegin();
        try {
            if ($productId > 0) {
                $db->table('business_catalog_products')->where('id', $productId)->update($data);
                $savedId = $productId;
            } else {
                $data['created_by'] = (int) $context['user_id'];
                $db->table('business_catalog_products')->insert($data);
                $savedId = (int) $db->insertID();
            }

            if (!$db->transStatus()) {
                throw new \RuntimeException('Catalog product save failed.');
            }
            $db->transCommit();

            AuditService::log(
                $productId > 0 ? 'BUSINESS_CATALOG_PRODUCT_UPDATED' : 'BUSINESS_CATALOG_PRODUCT_CREATED',
                'business_catalog_product',
                $savedId,
                $productId > 0 ? $existing : null,
                ['business_id' => $businessId, 'cylinder_type_id' => $cylinderTypeId, 'sale_mode' => $mode, 'display_name' => $name, 'is_active' => $active],
                (int) $context['user_id'],
                null
            );

            return $this->response->setStatusCode($productId > 0 ? 200 : 201)->setJSON(['data' => [
                'product_id' => $savedId,
                'business_id' => $businessId,
                'status' => 'saved',
            ]]);
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Business catalog save failed: {message}', ['message' => $e->getMessage()]);
            return $this->fail(409, 'CATALOG_PRODUCT_SAVE_FAILED', 'The product could not be saved. Check whether a product already exists for this cylinder type and sale mode.');
        }
    }

    public function shopProducts(int $shopId)
    {
        $principal = $this->principal();
        if (!$principal) {
            return $this->fail(401, 'INVALID_OR_EXPIRED_TOKEN', 'A valid staff access token is required.');
        }
        $userId = (int) $principal['user_id'];
        $scope = new ShopScopeService();
        if (!PermissionService::allowsForUser('SHOP_SETTINGS_VIEW', $userId) || !$scope->canAccessShop($shopId, $userId)) {
            return $this->fail(403, 'SHOP_ACCESS_DENIED', 'The staff account cannot view products for this shop.');
        }
        $businessId = $scope->businessIdForShop($shopId, $userId);
        if ($businessId === null) {
            return $this->fail(404, 'SHOP_NOT_FOUND', 'Shop is not assigned to an active business.');
        }

        $rows = Database::connect()->query(
            'SELECT p.id AS product_id,p.business_id,p.cylinder_type_id,p.sale_mode,p.display_name,p.description,p.quantity_unit,p.min_quantity,p.quantity_step,p.is_active AS catalog_active,COALESCE(a.is_active,0) AS shop_active,ct.code AS cylinder_code,ct.name AS cylinder_name
             FROM business_catalog_products p
             JOIN cylinder_types ct ON ct.id=p.cylinder_type_id
             LEFT JOIN shop_product_availability a
               ON a.business_catalog_product_id=p.id AND a.location_id=? AND a.business_id=p.business_id
             WHERE p.business_id=?
             ORDER BY p.sort_order,p.display_name',
            [$shopId, $businessId]
        )->getResultArray();

        return $this->response->setJSON(['data' => ['shop_id' => $shopId, 'business_id' => $businessId, 'products' => $rows]]);
    }

    public function setShopProductAvailability(int $shopId, int $productId)
    {
        $principal = $this->principal();
        if (!$principal) {
            return $this->fail(401, 'INVALID_OR_EXPIRED_TOKEN', 'A valid staff access token is required.');
        }
        $userId = (int) $principal['user_id'];
        $scope = new ShopScopeService();
        if (!PermissionService::allowsForUser('SHOP_SETTINGS_MANAGE', $userId) || !$scope->canAccessShop($shopId, $userId)) {
            return $this->fail(403, 'SHOP_ACCESS_DENIED', 'The staff account cannot change product availability for this shop.');
        }
        $businessId = $scope->businessIdForShop($shopId, $userId);
        if ($businessId === null) {
            return $this->fail(404, 'SHOP_NOT_FOUND', 'Shop is not assigned to an active business.');
        }

        $input = $this->payload();
        $active = $this->parseBoolean($input['is_active'] ?? null);
        if ($active === null) {
            return $this->fail(422, 'VALIDATION_FAILED', 'is_active must be true or false.');
        }

        $db = Database::connect();
        $product = $db->table('business_catalog_products')
            ->where(['id' => $productId, 'business_id' => $businessId, 'is_active' => 1])
            ->get()->getRowArray();
        if (!$product) {
            return $this->fail(404, 'CATALOG_PRODUCT_NOT_FOUND', 'An active catalog product from this business is required.');
        }

        $existing = $db->table('shop_product_availability')
            ->where(['location_id' => $shopId, 'business_id' => $businessId, 'business_catalog_product_id' => $productId])
            ->get()->getRowArray();

        if ($existing) {
            $db->table('shop_product_availability')->where('id', (int) $existing['id'])->update([
                'is_active' => $active ? 1 : 0,
                'updated_by' => $userId,
            ]);
        } else {
            $db->table('shop_product_availability')->insert([
                'business_id' => $businessId,
                'location_id' => $shopId,
                'business_catalog_product_id' => $productId,
                'is_active' => $active ? 1 : 0,
                'updated_by' => $userId,
            ]);
        }

        AuditService::log(
            'SHOP_PRODUCT_AVAILABILITY_CHANGED',
            'shop_product_availability',
            $productId,
            $existing,
            ['business_id' => $businessId, 'location_id' => $shopId, 'business_catalog_product_id' => $productId, 'is_active' => $active],
            $userId,
            $shopId
        );

        return $this->response->setJSON(['data' => ['shop_id' => $shopId, 'product_id' => $productId, 'is_active' => $active]]);
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
