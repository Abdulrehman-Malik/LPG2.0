<?php

namespace App\Controllers;

use App\Services\AuditService;
use App\Services\ConfigurationService;
use App\Services\PermissionService;
use App\Services\ShopScopeService;
use CodeIgniter\Controller;
use Config\Database;

class Configuration extends Controller
{
    protected ConfigurationService $configuration;
    protected ShopScopeService $scope;

    public function __construct()
    {
        $this->configuration = new ConfigurationService();
        $this->scope = new ShopScopeService();
    }

    public function index()
    {
        $userId = (int) (session()->get('user_id') ?? 0);
        $canSeeApplication = PermissionService::allows('APPLICATION_SETTINGS_VIEW');
        $canSeeBusiness = PermissionService::allows('BUSINESS_SETTINGS_VIEW');
        $canSeeShop = PermissionService::allows('SHOP_SETTINGS_VIEW');

        $businesses = [];
        if ($canSeeBusiness) {
            $businessIds = $this->scope->accessibleBusinessIds($userId);
            if ($businessIds) {
                $businesses = Database::connect()->table('businesses')
                    ->whereIn('id', $businessIds)
                    ->where('is_active', 1)
                    ->orderBy('name')
                    ->get()->getResultArray();
            }
        }

        $shops = [];
        if ($canSeeShop) {
            $shopIds = $this->scope->accessibleShopIds($userId);
            if ($shopIds) {
                $shops = Database::connect()->table('locations l')
                    ->select('l.id,l.name,l.business_id,b.name AS business_name')
                    ->join('businesses b', 'b.id=l.business_id', 'left')
                    ->whereIn('l.id', $shopIds)
                    ->where('l.is_active', 1)
                    ->orderBy('b.name')
                    ->orderBy('l.name')
                    ->get()->getResultArray();
            }
        }

        if (!$canSeeApplication && !$canSeeBusiness && !$canSeeShop) {
            return $this->response->setStatusCode(403)->setBody('You do not have access to Configuration.');
        }

        return view('configuration/index', [
            'title' => 'Configuration',
            'canSeeApplication' => $canSeeApplication,
            'businesses' => $businesses,
            'shops' => $shops,
        ]);
    }

    public function application()
    {
        if (!PermissionService::allows('APPLICATION_SETTINGS_VIEW')) {
            return $this->response->setStatusCode(403)->setBody('Forbidden');
        }

        return $this->renderScope('application', 0, null, null, 'Application Settings');
    }

    public function business(int $businessId)
    {
        $userId = (int) (session()->get('user_id') ?? 0);
        if (!PermissionService::allows('BUSINESS_SETTINGS_VIEW') || !$this->scope->canAccessBusiness($businessId, $userId)) {
            return $this->response->setStatusCode(403)->setBody('Forbidden');
        }

        $business = Database::connect()->table('businesses')
            ->where(['id' => $businessId, 'is_active' => 1])
            ->get()->getRowArray();
        if (!$business) {
            return $this->response->setStatusCode(404)->setBody('Business not found');
        }

        return $this->renderScope('business', $businessId, $businessId, null, 'Business Settings — ' . $business['name']);
    }

    public function shop(int $shopId)
    {
        $userId = (int) (session()->get('user_id') ?? 0);
        if (!PermissionService::allows('SHOP_SETTINGS_VIEW') || !$this->scope->canAccessShop($shopId, $userId)) {
            return $this->response->setStatusCode(403)->setBody('Forbidden');
        }

        $shop = Database::connect()->table('locations l')
            ->select('l.id,l.name,l.business_id,b.name AS business_name')
            ->join('businesses b', 'b.id=l.business_id', 'left')
            ->where(['l.id' => $shopId, 'l.is_active' => 1])
            ->get()->getRowArray();
        if (!$shop) {
            return $this->response->setStatusCode(404)->setBody('Shop not found');
        }

        return $this->renderScope('shop', $shopId, (int) ($shop['business_id'] ?? 0), $shopId, 'Shop Settings — ' . $shop['name']);
    }

    protected function renderScope(string $scope, int $ownerId, ?int $businessId, ?int $shopId, string $title)
    {
        $definitions = ConfigurationService::definitions($scope);
        $settings = [];
        foreach ($definitions as $key => $definition) {
            $resolved = $this->configuration->resolve($key, $businessId, $shopId);
            $settings[] = array_merge($definition, $resolved, [
                'key' => $key,
                'scope' => $scope,
                'owner_id' => $ownerId,
                'has_override' => $resolved['source'] === $scope,
            ]);
        }

        $canEdit = match ($scope) {
            'application' => PermissionService::allows('APPLICATION_SETTINGS_MANAGE'),
            'business' => PermissionService::allows('BUSINESS_SETTINGS_MANAGE'),
            'shop' => PermissionService::allows('SHOP_SETTINGS_MANAGE'),
            default => false,
        };

        return view('configuration/scope', [
            'title' => $title,
            'scope' => $scope,
            'scopeOwnerId' => $ownerId,
            'scopeOwnerName' => $title,
            'businessId' => $businessId,
            'shopId' => $shopId,
            'settings' => $settings,
            'canEdit' => $canEdit,
            'backUrl' => site_url('configuration'),
        ]);
    }

    protected function authorizeScope(string $scope, int $ownerId): array
    {
        $userId = (int) (session()->get('user_id') ?? 0);
        if ($scope === 'application') {
            return PermissionService::allows('APPLICATION_SETTINGS_MANAGE')
                ? ['allowed' => true, 'business_id' => null, 'shop_id' => null]
                : ['allowed' => false];
        }

        if ($scope === 'business') {
            return PermissionService::allows('BUSINESS_SETTINGS_MANAGE') && $this->scope->canAccessBusiness($ownerId, $userId)
                ? ['allowed' => true, 'business_id' => $ownerId, 'shop_id' => null]
                : ['allowed' => false];
        }

        if ($scope === 'shop') {
            $businessId = $this->scope->businessIdForShop($ownerId, $userId);
            return PermissionService::allows('SHOP_SETTINGS_MANAGE') && $businessId !== null
                ? ['allowed' => true, 'business_id' => $businessId, 'shop_id' => $ownerId]
                : ['allowed' => false];
        }

        return ['allowed' => false];
    }

    public function save()
    {
        $scope = trim((string) $this->request->getPost('scope'));
        $ownerId = (int) $this->request->getPost('owner_id');
        $key = trim((string) $this->request->getPost('setting_key'));
        $authorization = $this->authorizeScope($scope, $ownerId);
        if (!($authorization['allowed'] ?? false)) {
            return $this->response->setStatusCode(403)->setBody('Forbidden');
        }

        $definition = ConfigurationService::definitions()[$key] ?? null;
        if (!$definition || !in_array($scope, $definition['scopes'], true)) {
            return redirect()->back()->with('error', 'This setting cannot be changed at the selected scope.');
        }

        $raw = $this->request->getPost('value');
        try {
            $value = match ($definition['type']) {
                'boolean' => $this->parseBoolean($raw),
                'integer' => is_scalar($raw) && preg_match('/^-?\d+$/', (string) $raw) ? (int) $raw : $raw,
                'decimal' => is_scalar($raw) ? trim((string) $raw) : $raw,
                'string_list' => ['en'],
                default => $raw,
            };

            $old = $this->configuration->resolve(
                $key,
                $authorization['business_id'] ?? null,
                $authorization['shop_id'] ?? null
            );
            $this->configuration->set($scope, $scope === 'application' ? 0 : $ownerId, $key, $value, (int) session()->get('user_id'));
            $new = $this->configuration->resolve(
                $key,
                $authorization['business_id'] ?? null,
                $authorization['shop_id'] ?? null
            );

            AuditService::log(
                'CONFIGURATION_UPDATED',
                'configuration_setting',
                $ownerId ?: null,
                ['scope' => $scope, 'key' => $key, 'effective_value' => $old['value'], 'source' => $old['source']],
                ['scope' => $scope, 'key' => $key, 'effective_value' => $new['value'], 'source' => $new['source']],
                (int) session()->get('user_id'),
                $authorization['shop_id'] ?? null
            );

            return $this->scopeRedirect($scope, $ownerId)->with('success', 'Configuration setting saved.');
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function reset()
    {
        $scope = trim((string) $this->request->getPost('scope'));
        $ownerId = (int) $this->request->getPost('owner_id');
        $key = trim((string) $this->request->getPost('setting_key'));
        $authorization = $this->authorizeScope($scope, $ownerId);
        if (!($authorization['allowed'] ?? false) || !in_array($scope, ['business', 'shop'], true)) {
            return $this->response->setStatusCode(403)->setBody('Forbidden');
        }

        try {
            $before = $this->configuration->resolve($key, $authorization['business_id'] ?? null, $authorization['shop_id'] ?? null);
            $this->configuration->resetOverride($scope, $ownerId, $key);
            $after = $this->configuration->resolve($key, $authorization['business_id'] ?? null, $authorization['shop_id'] ?? null);

            AuditService::log(
                'CONFIGURATION_OVERRIDE_RESET',
                'configuration_setting',
                $ownerId,
                ['scope' => $scope, 'key' => $key, 'effective_value' => $before['value'], 'source' => $before['source']],
                ['scope' => $scope, 'key' => $key, 'effective_value' => $after['value'], 'source' => $after['source']],
                (int) session()->get('user_id'),
                $authorization['shop_id'] ?? null
            );

            return $this->scopeRedirect($scope, $ownerId)->with('success', 'Override cleared; the inherited value now applies.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    protected function parseBoolean($raw): bool
    {
        if (is_bool($raw)) {
            return $raw;
        }

        $value = filter_var((string) $raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($value === null) {
            throw new \RuntimeException('Select True or False for this setting.');
        }

        return $value;
    }

    protected function scopeRedirect(string $scope, int $ownerId)
    {
        return match ($scope) {
            'application' => redirect()->to(site_url('configuration/application')),
            'business' => redirect()->to(site_url('configuration/business/' . $ownerId)),
            'shop' => redirect()->to(site_url('configuration/shop/' . $ownerId)),
            default => redirect()->to(site_url('configuration')),
        };
    }
}
