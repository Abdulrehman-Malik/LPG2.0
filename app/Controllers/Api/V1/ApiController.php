<?php

namespace App\Controllers\Api\V1;

use App\Libraries\ApiPage;
use App\Libraries\RequestContext;
use App\Libraries\TenantContext;
use App\Libraries\Uuid;
use App\Services\PermissionService;
use App\Services\ShopScopeService;
use CodeIgniter\Controller;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Shared conventions for /api/v1 (BL-019):
 * - success: {"data": ..., "meta": {...}?}; failure: {"error": {"code","message","request_id"}}
 * - list endpoints: `page` (1…), `per_page` (1…100, default 50) → meta.page/per_page/total/has_more (ApiPage)
 * - staff endpoints: bearer token, permission code, and the shop from `X-Shop-Id` (or `shop_id`),
 *   checked against the user's shop scope before TenantContext is set
 * - creates: optional `Idempotency-Key`, handled for every POST by ApiIdempotencyFilter.
 */
abstract class ApiController extends Controller
{
    protected function payload(): array
    {
        $json = $this->request->getJSON(true);
        if (is_array($json)) {
            return $json;
        }
        $post = $this->request->getPost();
        return is_array($post) ? $post : [];
    }

    protected function ok($data, ?array $meta = null, int $status = 200): ResponseInterface
    {
        $body = ['data' => $data];
        if ($meta !== null) {
            $body['meta'] = $meta;
        }
        return $this->response->setStatusCode($status)->setJSON($body);
    }

    protected function fail(int $status, string $code, string $message, ?array $details = null): ResponseInterface
    {
        $error = ['code' => $code, 'message' => $message, 'request_id' => RequestContext::id()];
        if ($details) {
            $error['details'] = $details;
        }
        return $this->response->setStatusCode($status)->setJSON(['error' => $error]);
    }

    /** The authenticated staff principal, recorded by ApiAuthFilter. */
    protected function staff(): ?array
    {
        $principal = RequestContext::apiPrincipal();
        return $principal && $principal['audience'] === 'staff' ? ['user_id' => $principal['id']] : null;
    }

    /**
     * Resolves the shop this request acts in and checks permission. Returns
     * [userId, shopId, businessId] or an error response.
     *
     * @return array{0:int,1:int,2:int}|ResponseInterface
     */
    protected function staffScope(string $permission)
    {
        $principal = $this->staff();
        $userId = (int) ($principal['user_id'] ?? 0);
        if ($userId <= 0) {
            return $this->fail(401, 'UNAUTHENTICATED', 'A valid staff access token is required.');
        }
        if (!PermissionService::allowsForUser($permission, $userId)) {
            return $this->fail(403, 'PERMISSION_DENIED', 'You do not have permission for this action.');
        }
        $scope = new ShopScopeService();
        $requested = (int) ($this->request->getHeaderLine('X-Shop-Id') ?: ($this->request->getGet('shop_id') ?? 0));
        $shopId = $requested > 0 ? $requested : (int) ($scope->defaultShopIdForUser($userId) ?? 0);
        if ($shopId <= 0 || !$scope->canAccessShop($shopId, $userId)) {
            return $this->fail(403, 'SHOP_ACCESS_DENIED', 'You do not have access to this shop.');
        }
        TenantContext::setApi($userId, $shopId);
        return [$userId, $shopId, TenantContext::requireBusinessId()];
    }

    /**
     * Runs a create with the client's `uuid` from the body (BL-028): an invalid one is 422, a
     * valid one is put on the record the service inserts into $table.
     *
     * @param callable():ResponseInterface $create
     */
    protected function withClientUuid(string $table, callable $create): ResponseInterface
    {
        try {
            $uuid = Uuid::fromInput($this->payload()['uuid'] ?? null);
        } catch (\RuntimeException $e) {
            return $this->fail(422, 'INVALID_UUID', $e->getMessage());
        }
        Uuid::expect($table, $uuid);
        try {
            return $create();
        } finally {
            Uuid::expect($table, null);
        }
    }

    /** @return array{0:list<array<string,mixed>>,1:array<string,int|bool>} rows and meta of one page */
    protected function paginate(BaseBuilder $builder): array
    {
        return ApiPage::builder($builder, $this->request);
    }

    /** Pagination of an already-built list (for service results that are not builders). */
    protected function paginateArray(array $rows): array
    {
        return ApiPage::rows($rows, $this->request);
    }
}
