<?php

namespace App\Controllers;

use App\Services\AuditService;
use App\Services\PermissionService;
use App\Services\ShopScopeService;
use CodeIgniter\Controller;
use Config\Database;

class MobileCatalogManagement extends Controller
{
    protected function context(): array
    {
        $userId = (int) (session()->get('user_id') ?? 0);
        $scope = new ShopScopeService();
        $accessibleShopIds = $scope->accessibleShopIds($userId);
        if ($userId <= 0 || !$accessibleShopIds) {
            return ['error' => $this->response->setStatusCode(403)->setBody('No active shop scope is assigned to this account.')];
        }

        $requestedShopId = (int) $this->request->getGet('shop_id');
        $sessionShopId = (int) (session()->get('location_id') ?? 0);
        $shopId = $requestedShopId > 0 ? $requestedShopId : $sessionShopId;
        if (!in_array($shopId, $accessibleShopIds, true)) {
            $shopId = (int) $accessibleShopIds[0];
        }
        $businessId = (int) ($scope->businessIdForShop($shopId, $userId) ?? 0);
        if ($businessId <= 0) {
            return ['error' => $this->response->setStatusCode(409)->setBody('The selected shop is not assigned to a business. Complete business/shop setup before configuring customer ordering.')];
        }

        return [
            'user_id' => $userId,
            'shop_id' => $shopId,
            'business_id' => $businessId,
            'scope' => $scope,
            'shop_ids' => $accessibleShopIds,
        ];
    }

    public function index()
    {
        $context = $this->context();
        if (isset($context['error'])) return $context['error'];

        $hasReadOrManagePermission =
            PermissionService::allows('BUSINESS_CATALOG_VIEW')
            || PermissionService::allows('BUSINESS_CATALOG_MANAGE')
            || PermissionService::allows('SHOP_SETTINGS_VIEW')
            || PermissionService::allows('SHOP_SETTINGS_MANAGE');
        if (!$hasReadOrManagePermission) {
            return $this->response->setStatusCode(403)->setBody('You do not have permission to view or manage the customer ordering catalog.');
        }

        $userId = (int) $context['user_id'];
        $shopId = (int) $context['shop_id'];
        $businessId = (int) $context['business_id'];
        $scope = $context['scope'];
        $db = Database::connect();

        $shops = [];
        if (PermissionService::allows('SHOP_SETTINGS_VIEW')) {
            $shops = $db->table('locations l')
                ->select('l.id,l.name,l.business_id,b.name AS business_name')
                ->join('businesses b', 'b.id=l.business_id', 'inner')
                ->whereIn('l.id', $context['shop_ids'])
                ->where(['l.is_active' => 1, 'b.is_active' => 1])
                ->orderBy('b.name')->orderBy('l.name')
                ->get()->getResultArray();
        }

        $canViewBusinessCatalog = PermissionService::allows('BUSINESS_CATALOG_VIEW') && $scope->canAccessBusiness($businessId, $userId);
        $canManageBusinessCatalog = $canViewBusinessCatalog && PermissionService::allows('BUSINESS_CATALOG_MANAGE');
        $canManageShopAvailability = PermissionService::allows('SHOP_SETTINGS_MANAGE') && $scope->canAccessShop($shopId, $userId);

        $products = $db->query(
            'SELECT p.id AS product_id,p.business_id,p.cylinder_type_id,p.sale_mode,p.display_name,p.description,p.quantity_unit,p.min_quantity,p.quantity_step,p.is_active AS catalog_active,p.sort_order,
                    ct.code AS cylinder_code,ct.name AS cylinder_name,ct.capacity_kg,
                    COALESCE(a.is_active,0) AS shop_active
             FROM business_catalog_products p
             JOIN cylinder_types ct ON ct.id=p.cylinder_type_id
             LEFT JOIN shop_product_availability a ON a.business_catalog_product_id=p.id AND a.location_id=? AND a.business_id=p.business_id
             WHERE p.business_id=?
             ORDER BY p.sort_order,p.display_name',
            [$shopId, $businessId]
        )->getResultArray();

        $cylinderTypes = $db->table('cylinder_types')
            ->select('id,code,name,capacity_kg,empty_cylinder_price')
            ->where('is_active', 1)
            ->orderBy('sort_order')->orderBy('capacity_kg')
            ->get()->getResultArray();

        $shop = $db->table('locations')->where('id', $shopId)->get()->getRowArray();
        $business = $db->table('businesses')->where('id', $businessId)->get()->getRowArray();
        $editProduct = null;
        $editId = (int) $this->request->getGet('edit');
        if ($editId > 0 && $canManageBusinessCatalog) {
            $editProduct = $db->table('business_catalog_products')
                ->where(['id' => $editId, 'business_id' => $businessId])
                ->get()->getRowArray();
        }

        return view('mobile_catalog/index', [
            'title' => 'Customer Ordering Catalog',
            'business' => $business ?: [],
            'shop' => $shop ?: [],
            'shops' => $shops,
            'products' => $products,
            'cylinderTypes' => $cylinderTypes,
            'selectedShopId' => $shopId,
            'canViewBusinessCatalog' => $canViewBusinessCatalog,
            'canManageBusinessCatalog' => $canManageBusinessCatalog,
            'canManageShopAvailability' => $canManageShopAvailability,
            'editProduct' => $editProduct,
        ]);
    }

    public function saveProduct()
    {
        $context = $this->context();
        if (isset($context['error'])) return $context['error'];

        $userId = (int) $context['user_id'];
        $businessId = (int) $context['business_id'];
        if (!PermissionService::allows('BUSINESS_CATALOG_MANAGE') || !$context['scope']->canAccessBusiness($businessId, $userId)) {
            return $this->response->setStatusCode(403)->setBody('You do not have permission to manage this business catalog.');
        }

        $productId = (int) $this->request->getPost('product_id');
        $cylinderTypeId = (int) $this->request->getPost('cylinder_type_id');
        $mode = trim((string) $this->request->getPost('sale_mode'));
        $name = trim((string) $this->request->getPost('display_name'));
        $description = trim((string) $this->request->getPost('description'));
        $activeRaw = (string) $this->request->getPost('is_active');
        if (!in_array($activeRaw, ['0', '1'], true)) {
            return redirect()->to(site_url('mobile-catalog?shop_id=' . (int)$context['shop_id']))->withInput()->with('error', 'Choose a valid catalog product status.');
        }
        $active = $activeRaw === '1';
        $minimumRaw = trim((string) $this->request->getPost('min_quantity'));
        $stepRaw = trim((string) $this->request->getPost('quantity_step'));
        $minimum = is_numeric($minimumRaw) ? round((float) $minimumRaw, 3) : 0.0;
        $step = is_numeric($stepRaw) ? round((float) $stepRaw, 3) : 0.0;
        $sortOrder = max(0, (int) $this->request->getPost('sort_order'));

        if ($cylinderTypeId <= 0 || !in_array($mode, ['sell_gas_only', 'sell_filled', 'sell_empty'], true)
            || $name === '' || mb_strlen($name) > 150 || mb_strlen($description) > 500
            || $minimum <= 0 || $step <= 0 || ($mode !== 'sell_gas_only' && (floor($minimum) !== $minimum || floor($step) !== $step))
            || ($mode === 'sell_gas_only' && ($minimum < 0.1 || $step < 0.1))) {
            return redirect()->to(site_url('mobile-catalog?shop_id=' . (int)$context['shop_id']))->withInput()->with('error', 'Enter a valid product, sale mode and quantity rules. Cylinder quantities must be whole numbers; gas KG minimum and step must be at least 0.1.');
        }

        $db = Database::connect();
        $type = $db->table('cylinder_types')->where(['id' => $cylinderTypeId, 'is_active' => 1])->get()->getRowArray();
        if (!$type) return redirect()->back()->withInput()->with('error', 'Select an active cylinder type.');

        $existing = null;
        if ($productId > 0) {
            $existing = $db->table('business_catalog_products')
                ->where(['id' => $productId, 'business_id' => $businessId])
                ->get()->getRowArray();
            if (!$existing) return $this->response->setStatusCode(404)->setBody('Catalog product not found in the current business.');
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
            'sort_order' => $sortOrder,
            'updated_by' => $userId,
        ];

        $db->transBegin();
        try {
            if ($productId > 0) {
                $db->table('business_catalog_products')->where('id', $productId)->update($data);
                $savedId = $productId;
            } else {
                $data['created_by'] = $userId;
                $db->table('business_catalog_products')->insert($data);
                $savedId = (int) $db->insertID();
            }
            if (!$db->transStatus()) throw new \RuntimeException('Catalog product could not be saved.');
            $db->transCommit();

            AuditService::log($productId > 0 ? 'BUSINESS_CATALOG_PRODUCT_UPDATED' : 'BUSINESS_CATALOG_PRODUCT_CREATED',
                'business_catalog_product', $savedId, $existing,
                ['business_id' => $businessId, 'cylinder_type_id' => $cylinderTypeId, 'sale_mode' => $mode, 'display_name' => $name, 'is_active' => $active],
                $userId, null);
            return redirect()->to(site_url('mobile-catalog?shop_id=' . (int)$context['shop_id']))->with('success', 'Business catalog product saved. Activate it separately for each shop that should sell it.');
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Web catalog product save failed: {message}', ['message' => $e->getMessage()]);
            return redirect()->to(site_url('mobile-catalog?shop_id=' . (int)$context['shop_id']))->withInput()->with('error', 'The product could not be saved. A product may already exist for this cylinder type and sale mode.');
        }
    }

    public function toggleAvailability(int $productId)
    {
        $context = $this->context();
        if (isset($context['error'])) return $context['error'];

        $userId = (int) $context['user_id'];
        $shopId = (int) $context['shop_id'];
        $businessId = (int) $context['business_id'];
        if (!PermissionService::allows('SHOP_SETTINGS_MANAGE') || !$context['scope']->canAccessShop($shopId, $userId)) {
            return $this->response->setStatusCode(403)->setBody('You do not have permission to change availability for this shop.');
        }

        $activeRaw = (string) $this->request->getPost('is_active');
        if (!in_array($activeRaw, ['0', '1'], true)) {
            return redirect()->to(site_url('mobile-catalog?shop_id=' . $shopId))->with('error', 'Choose a valid availability state.');
        }
        $active = $activeRaw === '1';
        $db = Database::connect();
        $product = $db->table('business_catalog_products')
            ->where(['id' => $productId, 'business_id' => $businessId, 'is_active' => 1])
            ->get()->getRowArray();
        if (!$product) return redirect()->to(site_url('mobile-catalog?shop_id=' . $shopId))->with('error', 'Only active products from this business can be activated for a shop.');

        $existing = $db->table('shop_product_availability')
            ->where(['location_id' => $shopId, 'business_id' => $businessId, 'business_catalog_product_id' => $productId])
            ->get()->getRowArray();
        $db->transBegin();
        try {
            if ($existing) {
                $db->table('shop_product_availability')->where('id', (int)$existing['id'])->update(['is_active' => $active ? 1 : 0, 'updated_by' => $userId]);
            } else {
                $db->table('shop_product_availability')->insert([
                    'business_id' => $businessId, 'location_id' => $shopId,
                    'business_catalog_product_id' => $productId, 'is_active' => $active ? 1 : 0, 'updated_by' => $userId,
                ]);
            }
            if (!$db->transStatus()) throw new \RuntimeException('Shop catalog availability could not be saved.');
            $db->transCommit();

            AuditService::log('SHOP_PRODUCT_AVAILABILITY_CHANGED', 'shop_product_availability', $productId,
                $existing, ['business_id' => $businessId, 'location_id' => $shopId, 'business_catalog_product_id' => $productId, 'is_active' => $active],
                $userId, $shopId);
            return redirect()->to(site_url('mobile-catalog?shop_id=' . $shopId))->with('success', 'Shop product availability updated.');
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Web shop catalog activation failed: {message}', ['message' => $e->getMessage()]);
            return redirect()->to(site_url('mobile-catalog?shop_id=' . $shopId))->with('error', 'Shop product availability could not be saved.');
        }
    }
}
