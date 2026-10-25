<?php

namespace App\Controllers\Filters;

use App\Services\ApiSessionService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class ApiAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $audience = is_array($arguments) && isset($arguments[0]) ? (string) $arguments[0] : '';
        if (!in_array($audience, ['customer', 'staff'], true)) {
            return service('response')->setStatusCode(500)->setJSON([
                'error' => [
                    'code' => 'API_AUDIENCE_MISCONFIGURED',
                    'message' => 'The API route has no valid authentication audience.',
                ],
            ]);
        }

        $header = trim($request->getHeaderLine('Authorization'));
        if (!preg_match('/^Bearer\s+([a-f0-9]{64})$/i', $header, $matches)) {
            return service('response')->setStatusCode(401)->setJSON([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'A valid bearer access token is required.',
                ],
            ]);
        }

        try {
            $principal = (new ApiSessionService())->authenticateAccessToken($matches[1], $audience);
        } catch (\Throwable $e) {
            log_message('error', 'Mobile API authentication failed: {message}', ['message' => $e->getMessage()]);
            return service('response')->setStatusCode(503)->setJSON([
                'error' => [
                    'code' => 'AUTHENTICATION_UNAVAILABLE',
                    'message' => 'Authentication is temporarily unavailable.',
                ],
            ]);
        }

        if (!$principal) {
            return service('response')->setStatusCode(401)->setJSON([
                'error' => [
                    'code' => 'INVALID_OR_EXPIRED_TOKEN',
                    'message' => 'The access token is invalid, expired, revoked, or no longer authorized.',
                ],
            ]);
        }

        \App\Libraries\RequestContext::setApiPrincipal($audience, (int) ($audience === 'staff' ? ($principal['user_id'] ?? 0) : ($principal['customer_app_account_id'] ?? 0)));

        // Tenant scope of the API request (BL-008/BL-011). Staff: the requested shop (X-Shop-Id or
        // shop_id) when the user may access it, else the user's default shop; endpoints still run
        // their own shop and permission checks. Customer: the branch of the token's customer.
        if ($audience === 'staff') {
            $userId = (int) ($principal['user_id'] ?? 0);
            $scope = new \App\Services\ShopScopeService();
            $requested = (int) ($request->getHeaderLine('X-Shop-Id') ?: ($request->getGet('shop_id') ?? 0));
            $shopId = $requested > 0 && $scope->canAccessShop($requested, $userId) ? $requested : (int) ($scope->defaultShopIdForUser($userId) ?? 0);
            \App\Libraries\TenantContext::setApi($userId, $shopId);
        } else {
            $row = \Config\Database::connect()->table('customer_app_accounts ca')->select('c.location_id')
                ->join('customers c', 'c.id=ca.customer_id')->where('ca.id', (int) ($principal['customer_app_account_id'] ?? 0))
                ->get()->getRowArray();
            \App\Libraries\TenantContext::setApi(null, (int) ($row['location_id'] ?? 0));
        }
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }
}
