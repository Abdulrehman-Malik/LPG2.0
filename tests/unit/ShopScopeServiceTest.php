<?php

use App\Services\ShopScopeService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * @internal
 */
final class ShopScopeServiceTest extends CIUnitTestCase
{
    protected BaseConnection $testConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testConnection = Database::connect('tests');
        $this->testConnection->initialize();

        foreach (['user_shop_access', 'user_business_access', 'users', 'locations', 'businesses'] as $table) {
            $this->testConnection->query('DROP TABLE IF EXISTS ' . $table);
        }

        $this->testConnection->query('CREATE TABLE businesses (
            id INTEGER PRIMARY KEY,
            is_active INTEGER NOT NULL DEFAULT 1
        )');
        $this->testConnection->query('CREATE TABLE locations (
            id INTEGER PRIMARY KEY,
            business_id INTEGER NULL,
            is_active INTEGER NOT NULL DEFAULT 1
        )');
        $this->testConnection->query('CREATE TABLE users (
            id INTEGER PRIMARY KEY,
            location_id INTEGER NULL,
            is_system_admin INTEGER NOT NULL DEFAULT 0,
            is_active INTEGER NOT NULL DEFAULT 1
        )');
        $this->testConnection->query('CREATE TABLE user_business_access (
            user_id INTEGER NOT NULL,
            business_id INTEGER NOT NULL,
            is_default INTEGER NOT NULL DEFAULT 0,
            PRIMARY KEY(user_id, business_id)
        )');
        $this->testConnection->query('CREATE TABLE user_shop_access (
            user_id INTEGER NOT NULL,
            location_id INTEGER NOT NULL,
            is_default INTEGER NOT NULL DEFAULT 0,
            PRIMARY KEY(user_id, location_id)
        )');

        $this->testConnection->table('businesses')->insertBatch([
            ['id' => 1, 'is_active' => 1],
            ['id' => 2, 'is_active' => 1],
            ['id' => 3, 'is_active' => 0],
        ]);
        $this->testConnection->table('locations')->insertBatch([
            ['id' => 11, 'business_id' => 1, 'is_active' => 1],
            ['id' => 12, 'business_id' => 1, 'is_active' => 1],
            ['id' => 13, 'business_id' => 1, 'is_active' => 0],
            ['id' => 21, 'business_id' => 2, 'is_active' => 1],
            ['id' => 31, 'business_id' => 3, 'is_active' => 1],
        ]);
        $this->testConnection->table('users')->insertBatch([
            ['id' => 1, 'location_id' => 11, 'is_system_admin' => 0, 'is_active' => 1],
            ['id' => 2, 'location_id' => null, 'is_system_admin' => 0, 'is_active' => 1],
            ['id' => 3, 'location_id' => 11, 'is_system_admin' => 1, 'is_active' => 1],
            ['id' => 4, 'location_id' => 21, 'is_system_admin' => 0, 'is_active' => 1],
            ['id' => 5, 'location_id' => 11, 'is_system_admin' => 0, 'is_active' => 1],
            ['id' => 6, 'location_id' => 11, 'is_system_admin' => 0, 'is_active' => 0],
        ]);
        $this->testConnection->table('user_shop_access')->insertBatch([
            ['user_id' => 1, 'location_id' => 11, 'is_default' => 1],
            ['user_id' => 5, 'location_id' => 12, 'is_default' => 1],
        ]);
        $this->testConnection->table('user_business_access')->insert([
            'user_id' => 2,
            'business_id' => 1,
            'is_default' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        foreach (['user_shop_access', 'user_business_access', 'users', 'locations', 'businesses'] as $table) {
            $this->testConnection->query('DROP TABLE IF EXISTS ' . $table);
        }

        parent::tearDown();
    }

    public function testDirectShopAssignmentCannotAccessAnotherShop(): void
    {
        $scope = new ShopScopeService($this->testConnection);

        $this->assertTrue($scope->canAccessShop(11, 1));
        $this->assertFalse($scope->canAccessShop(12, 1));
        $this->assertFalse($scope->canAccessShop(21, 1));
    }

    public function testHeadOfficeBusinessAssignmentIncludesOnlyItsActiveShops(): void
    {
        $scope = new ShopScopeService($this->testConnection);

        $this->assertSame([11, 12], $scope->accessibleShopIds(2));
        $this->assertTrue($scope->canAccessBusiness(1, 2));
        $this->assertFalse($scope->canAccessBusiness(2, 2));
        $this->assertFalse($scope->canAccessShop(21, 2));
        $this->assertFalse($scope->canAccessShop(13, 2));
    }

    public function testSystemAdministratorHasActivePlatformWideShopScope(): void
    {
        $scope = new ShopScopeService($this->testConnection);

        $this->assertTrue($scope->isSystemAdministrator(3));
        $this->assertSame([11, 12, 21, 31], $scope->accessibleShopIds(3));
        $this->assertTrue($scope->canAccessBusiness(2, 3));
    }

    public function testLegacyLocationFallbackAppliesOnlyWithoutExplicitAssignments(): void
    {
        $scope = new ShopScopeService($this->testConnection);

        $this->assertSame([21], $scope->accessibleShopIds(4));
        $this->assertTrue($scope->canAccessShop(21, 4));

        // Explicit assignments replace the legacy location fallback instead of
        // silently adding an unassigned shop to a user's authorized scope.
        $this->assertSame([12], $scope->accessibleShopIds(5));
        $this->assertFalse($scope->canAccessShop(11, 5));
    }

    public function testInactiveUsersAndInvalidShopIdsHaveNoScope(): void
    {
        $scope = new ShopScopeService($this->testConnection);

        $this->assertSame([], $scope->accessibleShopIds(6));
        $this->assertSame([], $scope->accessibleShopIds(999));
        $this->assertFalse($scope->canAccessShop(0, 3));
    }
}
