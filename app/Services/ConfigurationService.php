<?php

namespace App\Services;

use Config\Database;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

class ConfigurationService
{
    /**
     * Explicit allow-list: controllers/UI cannot write arbitrary configuration keys.
     * The definition owns default, type, validation bounds and editable scopes.
     */
    private const DEFINITIONS = [
        'customer_ordering_feature_enabled' => [
            'label' => 'Customer ordering feature',
            'type' => 'boolean',
            'default' => true,
            'scopes' => ['application'],
        ],
        'orders_enabled' => [
            'label' => 'Accept customer orders',
            'type' => 'boolean',
            'default' => true,
            'scopes' => ['application', 'business', 'shop'],
        ],
        'delivery_enabled' => [
            'label' => 'Delivery available',
            'type' => 'boolean',
            'default' => true,
            'scopes' => ['application', 'business', 'shop'],
        ],
        'collection_enabled' => [
            'label' => 'Customer collection available',
            'type' => 'boolean',
            'default' => true,
            'scopes' => ['application', 'business', 'shop'],
        ],
        'delivery_fee' => [
            'label' => 'Delivery fee',
            'type' => 'decimal',
            'default' => 0.0,
            'min' => 0.0,
            'max' => 1000000.0,
            'scopes' => ['application', 'business', 'shop'],
        ],
        'customer_self_registration_enabled' => [
            'label' => 'Customer self-registration',
            'type' => 'boolean',
            'default' => true,
            'scopes' => ['application', 'business'],
        ],
        'supported_languages' => [
            'label' => 'Supported UI languages',
            'type' => 'string_list',
            'default' => ['en'],
            'scopes' => ['application'],
        ],
        'access_token_ttl_seconds' => [
            'label' => 'Access token lifetime (seconds)',
            'type' => 'integer',
            'default' => 900,
            'min' => 60,
            'max' => 900,
            'scopes' => ['application'],
        ],
        'refresh_idle_ttl_seconds' => [
            'label' => 'Refresh session inactivity limit (seconds)',
            'type' => 'integer',
            'default' => 2592000,
            'min' => 3600,
            'max' => 2592000,
            'scopes' => ['application'],
        ],
        'refresh_absolute_ttl_seconds' => [
            'label' => 'Refresh session absolute limit (seconds)',
            'type' => 'integer',
            'default' => 7776000,
            'min' => 86400,
            'max' => 7776000,
            'scopes' => ['application'],
        ],
        'password_min_length' => [
            'label' => 'Minimum new password length',
            'type' => 'integer',
            'default' => 10,
            'min' => 10,
            'max' => 128,
            'scopes' => ['application'],
        ],
    ];

    protected $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    public static function definitions(?string $scope = null): array
    {
        $definitions = self::DEFINITIONS;
        if ($scope === null) {
            return $definitions;
        }

        return array_filter(
            $definitions,
            static fn (array $definition): bool => in_array($scope, $definition['scopes'], true)
        );
    }

    protected function decodeValue($raw, $fallback)
    {
        if ($raw === null) {
            return $fallback;
        }

        if (is_array($raw) || is_bool($raw) || is_int($raw) || is_float($raw)) {
            return $raw;
        }

        $decoded = json_decode((string) $raw, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $fallback;
    }

    protected function normalizeValue(string $key, $value)
    {
        $definition = self::DEFINITIONS[$key] ?? null;
        if (!$definition) {
            throw new RuntimeException('Unknown configuration key.');
        }

        switch ($definition['type']) {
            case 'boolean':
                if (!is_bool($value)) {
                    throw new RuntimeException('Configuration ' . $key . ' must be a boolean.');
                }
                return $value;

            case 'integer':
                if (!is_int($value) && !(is_string($value) && preg_match('/^-?\d+$/', $value))) {
                    throw new RuntimeException('Configuration ' . $key . ' must be an integer.');
                }
                $normalized = (int) $value;
                if ($normalized < (int) $definition['min'] || $normalized > (int) $definition['max']) {
                    throw new RuntimeException(
                        'Configuration ' . $key . ' must be between '
                        . $definition['min'] . ' and ' . $definition['max'] . '.'
                    );
                }
                return $normalized;

            case 'decimal':
                if (!is_numeric($value)) {
                    throw new RuntimeException('Configuration ' . $key . ' must be numeric.');
                }
                $normalized = round((float) $value, 2);
                if ($normalized < (float) $definition['min'] || $normalized > (float) $definition['max']) {
                    throw new RuntimeException(
                        'Configuration ' . $key . ' must be between '
                        . $definition['min'] . ' and ' . $definition['max'] . '.'
                    );
                }
                return $normalized;

            case 'string_list':
                if (!is_array($value) || !$value) {
                    throw new RuntimeException('Configuration ' . $key . ' must be a non-empty list.');
                }
                $normalized = array_values(array_unique(array_map(
                    static fn ($item): string => strtolower(trim((string) $item)),
                    $value
                )));
                if (array_filter($normalized, static fn (string $item): bool => $item !== 'en')) {
                    throw new RuntimeException('Only languages that are implemented and tested may be enabled.');
                }
                if (!in_array('en', $normalized, true)) {
                    throw new RuntimeException('English must remain enabled for the initial release.');
                }
                return ['en'];
        }

        throw new RuntimeException('Unsupported configuration type.');
    }

    protected function storedValue(string $scope, int $ownerId, string $key)
    {
        if ($scope === 'application') {
            $row = $this->db->table('application_settings')
                ->select('setting_value')
                ->where('setting_key', $key)
                ->get()->getRowArray();
        } elseif ($scope === 'business') {
            $row = $this->db->table('business_settings')
                ->select('setting_value')
                ->where(['business_id' => $ownerId, 'setting_key' => $key])
                ->get()->getRowArray();
        } elseif ($scope === 'shop') {
            $row = $this->db->table('shop_setting_overrides')
                ->select('setting_value')
                ->where(['location_id' => $ownerId, 'setting_key' => $key])
                ->get()->getRowArray();
        } else {
            throw new RuntimeException('Invalid configuration scope.');
        }

        return $row ? $this->decodeValue($row['setting_value'], null) : null;
    }

    protected function applicationValue(string $key)
    {
        $definition = self::DEFINITIONS[$key];
        $stored = $this->storedValue('application', 0, $key);
        return $stored === null
            ? $definition['default']
            : $this->normalizeValue($key, $stored);
    }

    public function resolve(string $key, ?int $businessId = null, ?int $shopId = null): array
    {
        $definition = self::DEFINITIONS[$key] ?? null;
        if (!$definition) {
            throw new RuntimeException('Unknown configuration key.');
        }

        $value = $definition['default'];
        $source = 'application_default';
        $sourceId = null;

        if (in_array('application', $definition['scopes'], true)) {
            $appValue = $this->storedValue('application', 0, $key);
            if ($appValue !== null) {
                $value = $this->normalizeValue($key, $appValue);
                $source = 'application';
            }
        }

        if ($businessId !== null && $businessId > 0 && in_array('business', $definition['scopes'], true)) {
            $businessValue = $this->storedValue('business', $businessId, $key);
            if ($businessValue !== null) {
                $value = $this->normalizeValue($key, $businessValue);
                $source = 'business';
                $sourceId = $businessId;
            }
        }

        if ($shopId !== null && $shopId > 0 && in_array('shop', $definition['scopes'], true)) {
            $shopValue = $this->storedValue('shop', $shopId, $key);
            if ($shopValue !== null) {
                $value = $this->normalizeValue($key, $shopValue);
                $source = 'shop';
                $sourceId = $shopId;
            }
        }

        // Platform-level kill switch cannot be reopened by a business or shop override.
        if (in_array($key, ['orders_enabled', 'delivery_enabled', 'collection_enabled', 'customer_self_registration_enabled'], true)
            && !$this->applicationValue('customer_ordering_feature_enabled')) {
            $value = false;
            $source = 'application_guardrail';
            $sourceId = null;
        }

        return [
            'key' => $key,
            'label' => $definition['label'],
            'value' => $value,
            'type' => $definition['type'],
            'source' => $source,
            'source_id' => $sourceId,
            'editable_scopes' => $definition['scopes'],
        ];
    }

    public function forShop(int $shopId): array
    {
        if ($shopId <= 0) {
            throw new RuntimeException('A valid shop is required.');
        }

        $shop = $this->db->table('locations')
            ->select('id,business_id')
            ->where('id', $shopId)
            ->get()->getRowArray();

        if (!$shop || (int) ($shop['business_id'] ?? 0) <= 0) {
            throw new RuntimeException('Shop is not assigned to a business.');
        }

        $result = [];
        foreach (self::DEFINITIONS as $key => $_) {
            $result[$key] = $this->resolve($key, (int) $shop['business_id'], $shopId);
        }

        return $result;
    }

    public function forBusiness(int $businessId): array
    {
        if ($businessId <= 0 || !$this->db->table('businesses')->where('id', $businessId)->countAllResults()) {
            throw new RuntimeException('A valid business is required.');
        }

        $result = [];
        foreach (self::DEFINITIONS as $key => $_) {
            $result[$key] = $this->resolve($key, $businessId, null);
        }

        return $result;
    }

    public function forApplication(): array
    {
        $result = [];
        foreach (self::definitions('application') as $key => $_) {
            $result[$key] = $this->resolve($key, null, null);
        }

        return $result;
    }

    public function set(string $scope, int $ownerId, string $key, $value, ?int $updatedBy): void
    {
        $definition = self::DEFINITIONS[$key] ?? null;
        if (!$definition || !in_array($scope, ['application', 'business', 'shop'], true)
            || !in_array($scope, $definition['scopes'], true)) {
            throw new RuntimeException('This configuration key cannot be changed at the requested scope.');
        }

        if ($scope === 'application' && $ownerId !== 0) {
            throw new RuntimeException('Application configuration does not accept an owner ID.');
        }
        if ($scope !== 'application' && $ownerId <= 0) {
            throw new RuntimeException('A valid business or shop owner ID is required.');
        }

        $normalized = $this->normalizeValue($key, $value);
        if ($key === 'refresh_idle_ttl_seconds') {
            $absolute = $scope === 'application'
                ? ($key === 'refresh_absolute_ttl_seconds' ? $normalized : $this->applicationValue('refresh_absolute_ttl_seconds'))
                : (int) $this->applicationValue('refresh_absolute_ttl_seconds');
            if ($normalized > $absolute) {
                throw new RuntimeException('Refresh inactivity limit cannot exceed the absolute session limit.');
            }
        }
        if ($key === 'refresh_absolute_ttl_seconds') {
            $idle = (int) $this->applicationValue('refresh_idle_ttl_seconds');
            if ($normalized < $idle) {
                throw new RuntimeException('Absolute session limit cannot be shorter than the inactivity limit.');
            }
        }

        $serialized = json_encode($normalized, JSON_THROW_ON_ERROR);
        $this->db->transBegin();

        try {
            if ($scope === 'application') {
                $exists = $this->db->table('application_settings')->where('setting_key', $key)->countAllResults() > 0;
                $data = ['setting_value' => $serialized, 'updated_by' => $updatedBy];
                if ($exists) {
                    $this->db->table('application_settings')->where('setting_key', $key)->update($data);
                } else {
                    $this->db->table('application_settings')->insert(['setting_key' => $key] + $data);
                }
            } elseif ($scope === 'business') {
                if (!$this->db->table('businesses')->where('id', $ownerId)->countAllResults()) {
                    throw new RuntimeException('Business not found.');
                }
                $exists = $this->db->table('business_settings')->where(['business_id' => $ownerId, 'setting_key' => $key])->countAllResults() > 0;
                $data = ['setting_value' => $serialized, 'updated_by' => $updatedBy];
                if ($exists) {
                    $this->db->table('business_settings')->where(['business_id' => $ownerId, 'setting_key' => $key])->update($data);
                } else {
                    $this->db->table('business_settings')->insert(['business_id' => $ownerId, 'setting_key' => $key] + $data);
                }
            } else {
                if (!$this->db->table('locations')->where('id', $ownerId)->countAllResults()) {
                    throw new RuntimeException('Shop not found.');
                }
                $exists = $this->db->table('shop_setting_overrides')->where(['location_id' => $ownerId, 'setting_key' => $key])->countAllResults() > 0;
                $data = ['setting_value' => $serialized, 'updated_by' => $updatedBy];
                if ($exists) {
                    $this->db->table('shop_setting_overrides')->where(['location_id' => $ownerId, 'setting_key' => $key])->update($data);
                } else {
                    $this->db->table('shop_setting_overrides')->insert(['location_id' => $ownerId, 'setting_key' => $key] + $data);
                }
            }

            if (!$this->db->transStatus()) {
                throw new RuntimeException('Configuration update failed.');
            }
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public function resetOverride(string $scope, int $ownerId, string $key): void
    {
        $definition = self::DEFINITIONS[$key] ?? null;
        if (!$definition || !in_array($scope, ['business', 'shop'], true)
            || !in_array($scope, $definition['scopes'], true) || $ownerId <= 0) {
            throw new RuntimeException('This configuration override cannot be reset.');
        }

        if ($scope === 'business') {
            $this->db->table('business_settings')->where(['business_id' => $ownerId, 'setting_key' => $key])->delete();
        } else {
            $this->db->table('shop_setting_overrides')->where(['location_id' => $ownerId, 'setting_key' => $key])->delete();
        }
    }
}
