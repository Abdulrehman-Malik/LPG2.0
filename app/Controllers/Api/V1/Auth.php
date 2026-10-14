<?php

namespace App\Controllers\Api\V1;

use App\Services\ApiSessionService;
use App\Services\ShopScopeService;
use CodeIgniter\Controller;
use Config\Database;

class Auth extends Controller
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

    protected function errorResponse(int $status, string $code, string $message)
    {
        return $this->response->setStatusCode($status)->setJSON([
            'error' => ['code' => $code, 'message' => $message],
        ]);
    }

    protected function requestMeta(): array
    {
        return [
            'ip' => $this->request->getIPAddress(),
            'user_agent' => substr((string) $this->request->getUserAgent(), 0, 500),
        ];
    }

    protected function bearerToken(): string
    {
        $header = trim($this->request->getHeaderLine('Authorization'));
        return preg_match('/^Bearer\s+([a-f0-9]{64})$/i', $header, $match) ? $match[1] : '';
    }

    public function customerSignup()
    {
        $input = $this->payload();
        $shopId = (int) ($input['shop_id'] ?? $input['location_id'] ?? 0);
        $username = strtolower(trim((string) ($input['username'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        $name = trim((string) ($input['customer_name'] ?? $input['name'] ?? ''));
        $phone = trim((string) ($input['phone'] ?? ''));
        $city = trim((string) ($input['city'] ?? ''));
        $address = trim((string) ($input['address'] ?? ''));
        $vehicleNo = trim((string) ($input['vehicle_no'] ?? ''));

        if ($shopId <= 0 || $name === '' || !preg_match('/^[a-z0-9._-]{3,80}$/', $username)) {
            return $this->errorResponse(422, 'VALIDATION_FAILED', 'Select a shop, enter a customer name, and provide a username of 3–80 letters, numbers, dots, underscores or dashes.');
        }
        if (strlen($password) < 10) {
            return $this->errorResponse(422, 'VALIDATION_FAILED', 'Password must be at least 10 characters.');
        }

        $db = Database::connect();
        $shop = $db->table('locations l')
            ->select('l.id,l.business_id,b.name AS business_name')
            ->join('businesses b', 'b.id=l.business_id', 'inner')
            ->where(['l.id' => $shopId, 'l.is_active' => 1, 'b.is_active' => 1])
            ->get()->getRowArray();

        if (!$shop) {
            return $this->errorResponse(422, 'INVALID_SHOP', 'The selected shop is not available for customer registration.');
        }

        $db->transBegin();
        try {
            $accountExists = $db->table('customer_app_accounts')
                ->where('username', $username)
                ->countAllResults() > 0;
            $pendingExists = $db->table('customer_signup_requests')
                ->where('pending_username', $username)
                ->countAllResults() > 0;
            if ($accountExists || $pendingExists) {
                $db->transRollback();
                return $this->errorResponse(409, 'USERNAME_UNAVAILABLE', 'This username is already in use or awaiting approval.');
            }

            $db->table('customer_signup_requests')->insert([
                'business_id' => (int) $shop['business_id'],
                'requested_location_id' => $shopId,
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'customer_name' => $name,
                'phone' => $phone !== '' ? $phone : null,
                'city' => $city !== '' ? $city : null,
                'address' => $address !== '' ? $address : null,
                'vehicle_no' => $vehicleNo !== '' ? $vehicleNo : null,
                'status' => 'pending_approval',
                'created_ip' => substr($this->request->getIPAddress(), 0, 45),
                'user_agent' => substr((string) $this->request->getUserAgent(), 0, 500),
            ]);
            $requestId = (int) $db->insertID();

            if (!$db->transStatus()) {
                throw new \RuntimeException('Signup request could not be saved.');
            }
            $db->transCommit();

            return $this->response->setStatusCode(202)->setJSON([
                'status' => 'pending_approval',
                'request_id' => $requestId,
                'message' => 'Your registration was submitted. You can sign in and place orders after the shop approves your account.',
            ]);
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Customer app signup failed: {message}', ['message' => $e->getMessage()]);
            return $this->errorResponse(500, 'SIGNUP_FAILED', 'Registration could not be submitted. Please try again.');
        }
    }

    public function customerLogin()
    {
        $input = $this->payload();
        $username = strtolower(trim((string) ($input['username'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        if ($username === '' || $password === '') {
            return $this->errorResponse(422, 'VALIDATION_FAILED', 'Username and password are required.');
        }

        $row = Database::connect()->table('customer_app_accounts ca')
            ->select('ca.id,ca.username,ca.password_hash,ca.status,c.id AS customer_id,c.location_id,c.name AS customer_name,c.is_active')
            ->join('customers c', 'c.id=ca.customer_id', 'inner')
            ->where('ca.username', $username)
            ->get()->getRowArray();

        if (!$row || !password_verify($password, (string) $row['password_hash'])) {
            return $this->errorResponse(401, 'INVALID_CREDENTIALS', 'Username or password is incorrect.');
        }
        if (($row['status'] ?? '') !== 'approved' || (int) ($row['is_active'] ?? 0) !== 1) {
            return $this->errorResponse(403, 'ACCOUNT_NOT_APPROVED', 'This account is not active or has not been approved by its shop.');
        }

        try {
            $meta = $this->requestMeta();
            $tokens = (new ApiSessionService())->createSession('customer', (int) $row['id'], $meta['ip'], $meta['user_agent']);
            Database::connect()->table('customer_app_accounts')->where('id', (int) $row['id'])->update([
                'last_login_at' => date('Y-m-d H:i:s'),
            ]);

            return $this->response->setJSON([
                'data' => [
                    'tokens' => $tokens,
                    'customer' => [
                        'id' => (int) $row['customer_id'],
                        'name' => $row['customer_name'],
                        'shop_id' => (int) $row['location_id'],
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Customer app login failed: {message}', ['message' => $e->getMessage()]);
            return $this->errorResponse(503, 'AUTHENTICATION_UNAVAILABLE', 'Sign-in is temporarily unavailable.');
        }
    }

    public function staffLogin()
    {
        $input = $this->payload();
        $username = trim((string) ($input['username'] ?? $input['login'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        if ($username === '' || $password === '') {
            return $this->errorResponse(422, 'VALIDATION_FAILED', 'Username and password are required.');
        }

        $row = Database::connect()->table('users u')
            ->select('u.id,u.username,u.email,u.full_name,u.password_hash,u.is_active,u.location_id,r.code AS role_code,r.name AS role_name')
            ->join('roles r', 'r.id=u.role_id', 'inner')
            ->groupStart()
                ->where('u.username', $username)
                ->orWhere('u.email', $username)
            ->groupEnd()
            ->get()->getRowArray();

        if (!$row || !password_verify($password, (string) $row['password_hash'])) {
            return $this->errorResponse(401, 'INVALID_CREDENTIALS', 'Username or password is incorrect.');
        }
        if ((int) ($row['is_active'] ?? 0) !== 1) {
            return $this->errorResponse(403, 'ACCOUNT_DISABLED', 'This staff account has been disabled.');
        }

        try {
            $scope = new ShopScopeService();
            $shops = $scope->accessibleShopIds((int) $row['id']);
            $defaultShop = $scope->defaultShopIdForUser((int) $row['id']);
            if (!$shops || $defaultShop === null) {
                return $this->errorResponse(403, 'NO_SHOP_ACCESS', 'This staff account is not assigned to an active shop.');
            }

            $shopRows = Database::connect()->table('locations l')
                ->select('l.id,l.name,l.business_id,b.name AS business_name')
                ->join('businesses b', 'b.id=l.business_id', 'left')
                ->whereIn('l.id', $shops)
                ->orderBy('b.name')->orderBy('l.name')
                ->get()->getResultArray();

            $meta = $this->requestMeta();
            $tokens = (new ApiSessionService())->createSession('staff', (int) $row['id'], $meta['ip'], $meta['user_agent']);
            return $this->response->setJSON([
                'data' => [
                    'tokens' => $tokens,
                    'staff' => [
                        'id' => (int) $row['id'],
                        'name' => $row['full_name'],
                        'username' => $row['username'],
                        'role' => $row['role_code'],
                        'role_name' => $row['role_name'],
                    ],
                    'current_shop_id' => $defaultShop,
                    'shops' => $shopRows,
                ],
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Staff mobile login failed: {message}', ['message' => $e->getMessage()]);
            return $this->errorResponse(503, 'AUTHENTICATION_UNAVAILABLE', 'Sign-in is temporarily unavailable.');
        }
    }

    public function customerRefresh()
    {
        return $this->refreshAudience('customer');
    }

    public function staffRefresh()
    {
        return $this->refreshAudience('staff');
    }

    protected function refreshAudience(string $audience)
    {
        $input = $this->payload();
        $refresh = trim((string) ($input['refresh_token'] ?? ''));
        if ($refresh === '') {
            return $this->errorResponse(422, 'VALIDATION_FAILED', 'Refresh token is required.');
        }

        try {
            $meta = $this->requestMeta();
            $tokens = (new ApiSessionService())->refresh($refresh, $audience, $meta['ip'], $meta['user_agent']);
            if (!$tokens) {
                return $this->errorResponse(401, 'INVALID_REFRESH_TOKEN', 'Refresh token is expired, revoked, or has already been used. Sign in again.');
            }

            return $this->response->setJSON(['data' => ['tokens' => $tokens]]);
        } catch (\Throwable $e) {
            log_message('error', 'Mobile API refresh failed: {message}', ['message' => $e->getMessage()]);
            return $this->errorResponse(503, 'TOKEN_REFRESH_UNAVAILABLE', 'Token refresh is temporarily unavailable.');
        }
    }

    public function customerLogout()
    {
        return $this->logoutAudience('customer');
    }

    public function staffLogout()
    {
        return $this->logoutAudience('staff');
    }

    protected function logoutAudience(string $audience)
    {
        $token = $this->bearerToken();
        if ($token === '' || !(new ApiSessionService())->revokeFromAccessToken($token, $audience)) {
            return $this->errorResponse(401, 'INVALID_OR_EXPIRED_TOKEN', 'The session is already invalid or expired.');
        }

        return $this->response->setStatusCode(204);
    }
}
