<?php

use App\Services\ConfigurationService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * @internal
 */
final class ConfigurationServiceTest extends CIUnitTestCase
{
    protected BaseConnection $testConnection;
    private string $prefix = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->testConnection = Database::connect('tests');
        $this->testConnection->initialize();
        $this->prefix = $this->testConnection->getPrefix();

        foreach (['application_settings', 'business_settings', 'shop_setting_overrides', 'locations', 'businesses'] as $table) {
            $this->testConnection->query('DROP TABLE IF EXISTS `' . $this->prefix . $table . '`');
        }

        $this->testConnection->query('CREATE TABLE `' . $this->prefix . 'businesses` (id INTEGER PRIMARY KEY, is_active INTEGER NOT NULL DEFAULT 1)');
        $this->testConnection->query('CREATE TABLE `' . $this->prefix . 'locations` (id INTEGER PRIMARY KEY, business_id INTEGER NOT NULL, is_active INTEGER NOT NULL DEFAULT 1)');
        $this->testConnection->query('CREATE TABLE `' . $this->prefix . 'application_settings` (setting_key TEXT PRIMARY KEY, setting_value TEXT NOT NULL, updated_by INTEGER, updated_at TEXT)');
        $this->testConnection->query('CREATE TABLE `' . $this->prefix . 'business_settings` (business_id INTEGER NOT NULL, setting_key TEXT NOT NULL, setting_value TEXT NOT NULL, updated_by INTEGER, updated_at TEXT, PRIMARY KEY(business_id,setting_key))');
        $this->testConnection->query('CREATE TABLE `' . $this->prefix . 'shop_setting_overrides` (location_id INTEGER NOT NULL, setting_key TEXT NOT NULL, setting_value TEXT NOT NULL, updated_by INTEGER, updated_at TEXT, PRIMARY KEY(location_id,setting_key))');

        $this->testConnection->table('businesses')->insertBatch([
            ['id' => 1, 'is_active' => 1],
            ['id' => 2, 'is_active' => 1],
        ]);
        $this->testConnection->table('locations')->insertBatch([
            ['id' => 11, 'business_id' => 1, 'is_active' => 1],
            ['id' => 21, 'business_id' => 2, 'is_active' => 1],
        ]);
    }

    protected function tearDown(): void
    {
        foreach (['application_settings', 'business_settings', 'shop_setting_overrides', 'locations', 'businesses'] as $table) {
            $this->testConnection->query('DROP TABLE IF EXISTS `' . $this->prefix . $table . '`');
        }
        parent::tearDown();
    }

    private function service(): ConfigurationService
    {
        return new ConfigurationService($this->testConnection);
    }

    public function testEffectiveValueUsesApplicationThenBusinessThenShopPrecedence(): void
    {
        $this->testConnection->table('application_settings')->insert([
            'setting_key' => 'delivery_fee', 'setting_value' => '7.00',
        ]);
        $this->testConnection->table('business_settings')->insert([
            'business_id' => 1, 'setting_key' => 'delivery_fee', 'setting_value' => '8.50',
        ]);
        $this->testConnection->table('shop_setting_overrides')->insert([
            'location_id' => 11, 'setting_key' => 'delivery_fee', 'setting_value' => '9.25',
        ]);

        $service = $this->service();
        $this->assertSame(7.0, $service->resolve('delivery_fee')['value']);
        $this->assertSame('application', $service->resolve('delivery_fee')['source']);

        $this->assertSame(8.5, $service->resolve('delivery_fee', 1)['value']);
        $this->assertSame('business', $service->resolve('delivery_fee', 1)['source']);

        $this->assertSame(9.25, $service->resolve('delivery_fee', 1, 11)['value']);
        $this->assertSame('shop', $service->resolve('delivery_fee', 1, 11)['source']);
    }

    public function testApplicationFeatureKillSwitchOverridesLowerScopeSettings(): void
    {
        $this->testConnection->table('application_settings')->insert([
            'setting_key' => 'customer_ordering_feature_enabled', 'setting_value' => 'false',
        ]);
        $this->testConnection->table('business_settings')->insert([
            'business_id' => 1, 'setting_key' => 'orders_enabled', 'setting_value' => 'true',
        ]);
        $this->testConnection->table('shop_setting_overrides')->insert([
            'location_id' => 11, 'setting_key' => 'orders_enabled', 'setting_value' => 'true',
        ]);

        $resolved = $this->service()->resolve('orders_enabled', 1, 11);
        $this->assertFalse($resolved['value']);
        $this->assertSame('application_guardrail', $resolved['source']);
    }

    public function testInvalidSecurityTtlOrScopeChangesAreRejected(): void
    {
        $service = $this->service();

        $this->expectException(RuntimeException::class);
        $service->set('application', 0, 'access_token_ttl_seconds', 1200, 1);
    }

    public function testBusinessCannotOverrideApplicationOnlySecuritySetting(): void
    {
        $service = $this->service();

        $this->expectException(RuntimeException::class);
        $service->set('business', 1, 'access_token_ttl_seconds', 600, 1);
    }

    public function testResetOverrideRestoresInheritedApplicationValue(): void
    {
        $this->testConnection->table('application_settings')->insert([
            'setting_key' => 'delivery_fee', 'setting_value' => '4.25',
        ]);
        $this->testConnection->table('shop_setting_overrides')->insert([
            'location_id' => 11, 'setting_key' => 'delivery_fee', 'setting_value' => '8.00',
        ]);

        $service = $this->service();
        $service->resetOverride('shop', 11, 'delivery_fee');

        $resolved = $service->resolve('delivery_fee', 1, 11);
        $this->assertSame(4.25, $resolved['value']);
        $this->assertSame('application', $resolved['source']);
    }
}
