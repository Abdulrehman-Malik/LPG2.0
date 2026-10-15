<?php

namespace App\Controllers\Api\V1\Staff;

use App\Services\ApiSessionService;
use App\Services\AuditService;
use App\Services\ConfigurationService;
use App\Services\PermissionService;
use App\Services\ShopScopeService;
use CodeIgniter\Controller;
use Config\Database;
use RuntimeException;

class Configuration extends Controller
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
        return is_array($json) ? $json : [];
    }

    protected function fail(int $status, string $code, string $message)
    {
        return $this->response->setStatusCode($status)->setJSON([
            'error' => ['code' => $code, 'message' => $message],
        ]);
    }

    protected function authorize(string $scope, int $ownerId, int $userId, bool $write = false): array
    {
        $permission = match ($scope) {
            'application' => $write ? 'APPLICATION_SETTINGS_MANAGE' : 'APPLICATION_SETTINGS_VIEW',
            'business' => $write ? 'BUSINESS_SETTINGS_MANAGE' : 'BUSINESS_SETTINGS_VIEW',
            'shop' => $write ? 'SHOP_SETTINGS_MANAGE' : 'SHOP_SETTINGS_VIEW',
            default => '',
        };
        if ($permission === '' || !PermissionService::allowsForUser($permission, $userId)) {
            return ['allowed' => false];
        }

        $scopeService = new ShopScopeService();
        if ($scope === 'application') {
            return ['allowed' => $ownerId === 0, 'business_id' => null, 'shop_id' => null];
        }
        if ($ownerId <= 0) {
            return ['allowed' => false];
        }
        if ($scope === 'business') {
            $business = Database::connect()->table('businesses')->where(['id' => $ownerId, 'is_active' => 1])->get()->getRowArray();
            $allowed = $business && $scopeService->canAccessBusiness($ownerId, $userId);
            return ['allowed' => (bool) $allowed, 'business_id' => $ownerId, 'shop_id' => null];
        }

        $businessId = $scopeService->businessIdForShop($ownerId, $userId);
        return [
            'allowed' => $businessId !== null && $scopeService->canAccessShop($ownerId, $userId),
            'business_id' => $businessId,
            'shop_id' => $ownerId,
        ];
    }

    protected function respondSettings(string $scope, int $ownerId, int $businessId, int $shopId)
    {
        $service = new ConfigurationService();
        $definitions = ConfigurationService::definitions($scope);
        $settings = [];
        foreach ($definitions as $key => $definition) {
            $resolved = $service->resolve($key, $businessId > 0 ? $businessId : null, $shopId > 0 ? $shopId : null);
            $settings[] = array_merge($definition, $resolved, [
                'key' => $key,
                'has_override' => $resolved['source'] === $scope,
                'editable' => in_array($scope, $resolved['editable_scopes'], true),
            ]);
        }
        return $this->response->setJSON(['data' => [
            'scope' => $scope,
            'owner_id' => $ownerId,
            'settings' => $settings,
        ]]);
    }

    public function application()
    {
        $principal = $this->principal();
        if (!$principal) {
            return $this->fail(401, 'INVALID_OR_EXPIRED_TOKEN', 'A valid staff access token is required.');
        }
        $userId = (int) $principal['user_id'];
        $auth = $this->authorize('application', 0, $userId);
        if (!$auth['allowed']) {
            return $this->fail(403, 'APPLICATION_SETTINGS_DENIED', 'You cannot view application-level configuration.');
        }
        return $this->respondSettings('application', 0, 0, 0);
    }

    public function business(int $businessId)
    {
        $principal = $this->principal();
        if (!$principal) {
            return $this->fail(401, 'INVALID_OR_EXPIRED_TOKEN', 'A valid staff access token is required.');
        }
        $auth = $this->authorize('business', $businessId, (int) $principal['user_id']);
        if (!$auth['allowed']) {
            return $this->fail(403, 'BUSINESS_SETTINGS_DENIED', 'You cannot view settings for this business.');
        }
        return $this->respondSettings('business', $businessId, $businessId, 0);
    }

    public function shop(int $shopId)
    {
        $principal = $this->principal();
        if (!$principal) {
            return $this->fail(401, 'INVALID_OR_EXPIRED_TOKEN', 'A valid staff access token is required.');
        }
        $auth = $this->authorize('shop', $shopId, (int) $principal['user_id']);
        if (!$auth['allowed']) {
            return $this->fail(403, 'SHOP_SETTINGS_DENIED', 'You cannot view settings for this shop.');
        }
        return $this->respondSettings('shop', $shopId, (int) $auth['business_id'], $shopId);
    }

    public function save()
    {
        $principal = $this->principal();
        if (!$principal) {
            return $this->fail(401, 'INVALID_OR_EXPIRED_TOKEN', 'A valid staff access token is required.');
        }

        $input = $this->payload();
        $scope = trim((string) ($input['scope'] ?? ''));
        $ownerId = (int) ($input['owner_id'] ?? 0);
        $key = trim((string) ($input['key'] ?? ''));
        if (!in_array($scope, ['application', 'business', 'shop'], true) || $key === '' || !array_key_exists('value', $input)) {
            return $this->fail(422, 'VALIDATION_FAILED', 'scope, owner_id, key and value are required.');
        }

        $userId = (int) $principal['user_id'];
        $auth = $this->authorize($scope, $ownerId, $userId, true);
        if (!$auth['allowed']) {
            return $this->fail(403, 'CONFIGURATION_EDIT_DENIED', 'You cannot edit settings at this scope.');
        }

        $definition = ConfigurationService::definitions()[$key] ?? null;
        if (!$definition || !in_array($scope, $definition['scopes'], true)) {
            return $this->fail(422, 'SETTING_SCOPE_NOT_ALLOWED', 'This setting cannot be changed at the selected scope.');
        }

        $value = $input['value'];
        if ($definition['type'] === 'boolean' && !is_bool($value)) {
            return $this->fail(422, 'VALIDATION_FAILED', 'Boolean settings must be sent as JSON true or false.');
        }
        if ($definition['type'] === 'integer' && !(is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value)))) {
            return $this->fail(422, 'VALIDATION_FAILED', 'This setting requires an integer.');
        }
        if ($definition['type'] === 'decimal' && !is_numeric($value)) {
            return $this->fail(422, 'VALIDATION_FAILED', 'This setting requires a numeric value.');
        }
        if ($definition['type'] === 'string_list' && !is_array($value)) {
            return $this->fail(422, 'VALIDATION_FAILED', 'This setting requires an array of language codes.');
        }

        $service = new ConfigurationService();
        try {
            $before = $service->resolve($key, $auth['business_id'] ?? null, $auth['shop_id'] ?? null);
            $service->set($scope, $ownerId, $key, $value, $userId);
            $after = $service->resolve($key, $auth['business_id'] ?? null, $auth['shop_id'] ?? null);

            AuditService::log(
                'CONFIGURATION_UPDATED',
                'configuration_setting',
                $ownerId ?: null,
                ['scope' => $scope, 'key' => $key, 'effective_value' => $before['value'], 'source' => $before['source']],
                ['scope' => $scope, 'key' => $key, 'effective_value' => $after['value'], 'source' => $after['source']],
                $userId,
                $auth['shop_id'] ?? null
            );

            return $this->response->setJSON(['data' => [
                'scope' => $scope,
                'owner_id' => $ownerId,
                'key' => $key,
                'setting' => $after,
                'message' => 'Configuration saved.',
            ]]);
        } catch (\Throwable $e) {
            return $this->fail(422, 'CONFIGURATION_SAVE_FAILED', $e->getMessage());
        }
    }

    public function reset()
    {
        $principal = $this->principal();
        if (!$principal) {
            return $this->fail(401, 'INVALID_OR_EXPIRED_TOKEN', 'A valid staff access token is required.');
        }
        $input = $this->payload();
        $scope = trim((string) ($input['scope'] ?? ''));
        $ownerId = (int) ($input['owner_id'] ?? 0);
        $key = trim((string) ($input['key'] ?? ''));
        if (!in_array($scope, ['business', 'shop'], true) || $key === '') {
            return $this->fail(422, 'VALIDATION_FAILED', 'A business/shop scope and setting key are required to reset an override.');
        }
        $userId = (int) $principal['user_id'];
        $auth = $this->authorize($scope, $ownerId, $userId, true);
        if (!$auth['allowed']) {
            return $this->fail(403, 'CONFIGURATION_EDIT_DENIED', 'You cannot reset settings at this scope.');
        }
        $definition = ConfigurationService::definitions()[$key] ?? null;
        if (!$definition || !in_array($scope, $definition['scopes'], true)) {
            return $this->fail(422, 'SETTING_SCOPE_NOT_ALLOWED', 'This setting cannot be reset at the selected scope.');
        }

        $service = new ConfigurationService();
        try {
            $before = $service->resolve($key, $auth['business_id'] ?? null, $auth['shop_id'] ?? null);
            $service->resetOverride($scope, $ownerId, $key);
            $after = $service->resolve($key, $auth['business_id'] ?? null, $auth['shop_id'] ?? null);
            AuditService::log(
                'CONFIGURATION_OVERRIDE_RESET',
                'configuration_setting',
                $ownerId,
                ['scope' => $scope, 'key' => $key, 'effective_value' => $before['value'], 'source' => $before['source']],
                ['scope' => $scope, 'key' => $key, 'effective_value' => $after['value'], 'source' => $after['source']],
                $userId,
                $auth['shop_id'] ?? null
            );
            return $this->response->setJSON(['data' => [
                'scope' => $scope,
                'owner_id' => $ownerId,
                'key' => $key,
                'setting' => $after,
                'message' => 'Override reset; inherited configuration now applies.',
            ]]);
        } catch (\Throwable $e) {
            return $this->fail(422, 'CONFIGURATION_RESET_FAILED', $e->getMessage());
        }
    }
}
