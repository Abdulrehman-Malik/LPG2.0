<?php

use App\Libraries\TenantContext;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class TenantContextTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function testFailsClosedWhenNoContextIsSet(): void
    {
        TenantContext::clear();
        $this->assertFalse(TenantContext::isSet());
        $this->assertNull(TenantContext::branchId());
        $this->expectException(RuntimeException::class);
        TenantContext::requireBranchId();
    }

    public function testRequireUserIdFailsWithoutUser(): void
    {
        TenantContext::setApi(null);
        $this->expectException(RuntimeException::class);
        TenantContext::requireUserId();
    }

    public function testWebContextCarriesUserAndBranch(): void
    {
        TenantContext::setWeb(7, 3);
        $this->assertTrue(TenantContext::isSet());
        $this->assertSame('web', TenantContext::source());
        $this->assertSame(7, TenantContext::requireUserId());
        $this->assertSame(3, TenantContext::requireBranchId());
    }

    public function testApiContextGetsBranchOnlyAfterShopCheck(): void
    {
        TenantContext::setApi(9);
        $this->assertSame('api', TenantContext::source());
        $this->assertNull(TenantContext::branchId());
        TenantContext::setBranch(4);
        $this->assertSame(4, TenantContext::requireBranchId());
    }

    public function testInvalidIdsAreTreatedAsMissing(): void
    {
        TenantContext::setWeb(0, -1);
        $this->assertNull(TenantContext::userId());
        $this->assertNull(TenantContext::branchId());
    }
}
