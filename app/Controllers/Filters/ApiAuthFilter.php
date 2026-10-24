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

        // Tenant scope of the API request (BL-008): the staff user; the branch is set once the
        // endpoint has checked the requested shop. Customer tokens act for their own customer.
        \App\Libraries\TenantContext::setApi($audience === 'staff' ? (int) ($principal['user_id'] ?? 0) : null);
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }
}
