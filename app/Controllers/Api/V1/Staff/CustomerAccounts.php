<?php

namespace App\Controllers\Api\V1\Staff;

use App\Services\ApiSessionService;
use App\Services\AuditService;
use App\Services\ConfigurationService;
use App\Services\PermissionService;
use App\Services\ShopScopeService;
use CodeIgniter\Controller;
use Config\Database;

class CustomerAccounts extends Controller
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

    protected function principal(): ?array
    {
        $header = trim($this->request->getHeaderLine('Authorization'));
        if (!preg_match('/^Bearer\s+([a-f0-9]{64})$/i', $header, $match)) {
            return null;
        }
        return (new ApiSessionService())->authenticateAccessToken($match[1], 'staff');
    }

    protected function selectedShop(int $userId): ?int
    {
        $scope = new ShopScopeService();
        $requested = trim($this->request->getHeaderLine('X-Shop-Id'));
        $shopId = $requested !== '' && ctype_digit($requested)
            ? (int) $requested
            : (int) ($scope->defaultShopIdForUser($userId) ?? 0);

        return $scope->canAccessShop($shopId, $userId) ? $shopId : null;
    }

    protected function guard(string $permission = 'CUSTOMER_ACCOUNT_MANAGE'): array
    {
        $principal = $this->principal();
        if (!$principal) {
            return ['response' => $this->response->setStatusCode(401)->setJSON([
                'error' => ['code' => 'INVALID_OR_EXPIRED_TOKEN', 'message' => 'A valid staff access token is required.'],
            ])];
        }

        $userId = (int) $principal['user_id'];
        if (!PermissionService::allowsForUser($permission, $userId)) {
            return ['response' => $this->response->setStatusCode(403)->setJSON([
                'error' => ['code' => 'PERMISSION_DENIED', 'message' => 'You do not have permission to manage customer app accounts.'],
            ])];
        }

        $shopId = $this->selectedShop($userId);
        if ($shopId === null) {
            return ['response' => $this->response->setStatusCode(403)->setJSON([
                'error' => ['code' => 'SHOP_ACCESS_DENIED', 'message' => 'Select a shop assigned to your staff account.'],
            ])];
        }

        return ['principal' => $principal, 'user_id' => $userId, 'shop_id' => $shopId];
    }

    public function signupRequests()
    {
        $guard = $this->guard();
        if (isset($guard['response'])) {
            return $guard['response'];
        }

        $rows = Database::connect()->table('customer_signup_requests')
            ->select('id,username,customer_name,phone,city,address,vehicle_no,status,created_at')
            ->where(['requested_location_id' => $guard['shop_id'], 'status' => 'pending_approval'])
            ->orderBy('created_at', 'ASC')
            ->get()->getResultArray();

        [$page, $meta] = \App\Libraries\ApiPage::rows($rows, $this->request);
        return $this->response->setJSON(['data' => ['shop_id' => $guard['shop_id'], 'requests' => $page], 'meta' => $meta]);
    }

    public function approveSignup(int $requestId)
    {
        $guard = $this->guard();
        if (isset($guard['response'])) {
            return $guard['response'];
        }

        $input = $this->payload();
        $chosenCustomerId = (int) ($input['customer_id'] ?? 0);
        $db = Database::connect();
        $db->transBegin();

        try {
            $request = $db->query(
                'SELECT * FROM customer_signup_requests
                 WHERE id=? AND requested_location_id=? AND status=?
                 LIMIT 1 FOR UPDATE',
                [$requestId, (int) $guard['shop_id'], 'pending_approval']
            )->getRowArray();

            if (!$request) {
                $db->transRollback();
                return $this->response->setStatusCode(404)->setJSON([
                    'error' => ['code' => 'SIGNUP_REQUEST_NOT_FOUND', 'message' => 'Pending signup request was not found for the selected shop.'],
                ]);
            }

            if (empty($request['password_hash'])) {
                throw new \RuntimeException('The signup request has no valid password credential.');
            }

            $usernameTaken = $db->table('customer_app_accounts')
                ->where('username', (string) $request['username'])
                ->countAllResults() > 0;
            if ($usernameTaken) {
                throw new \RuntimeException('The requested username is already assigned to an app account.');
            }

            if ($chosenCustomerId > 0) {
                $customer = $db->table('customers')
                    ->where([
                        'id' => $chosenCustomerId,
                        'location_id' => (int) $guard['shop_id'],
                        'is_active' => 1,
                    ])
                    ->get()->getRowArray();
                if (!$customer) {
                    throw new \RuntimeException('Select an active financial customer from this shop.');
                }
            } else {
                $db->table('customers')->insert([
                    'location_id' => (int) $guard['shop_id'],
                    'code' => null,
                    'name' => (string) $request['customer_name'],
                    'phone' => $request['phone'] ?: null,
                    'city' => $request['city'] ?: null,
                    'address' => $request['address'] ?: null,
                    'vehicle_no' => $request['vehicle_no'] ?: null,
                    'credit_limit' => 0,
                    'allow_credit_sale' => 0,
                    'opening_balance' => 0,
                    'is_active' => 1,
                ]);
                $chosenCustomerId = (int) $db->insertID();
                $customer = $db->table('customers')->where('id', $chosenCustomerId)->get()->getRowArray();
            }

            $alreadyLinked = $db->table('customer_app_accounts')
                ->where('customer_id', $chosenCustomerId)
                ->countAllResults() > 0;
            if ($alreadyLinked) {
                throw new \RuntimeException('This financial customer already has an app account. Link the request to a different customer.');
            }

            $db->table('customer_app_accounts')->insert([
                'customer_id' => $chosenCustomerId,
                'username' => (string) $request['username'],
                'password_hash' => (string) $request['password_hash'],
                'status' => 'approved',
                'approved_by' => (int) $guard['user_id'],
                'approved_at' => date('Y-m-d H:i:s'),
                'password_changed_at' => date('Y-m-d H:i:s'),
            ]);
            $accountId = (int) $db->insertID();

            $db->table('customer_signup_requests')->where('id', $requestId)->update([
                'status' => 'approved',
                'linked_customer_id' => $chosenCustomerId,
                'reviewed_by' => (int) $guard['user_id'],
                'reviewed_at' => date('Y-m-d H:i:s'),
                'password_hash' => null,
                'rejection_reason' => null,
            ]);

            if (!$db->transStatus()) {
                throw new \RuntimeException('Approval could not be saved.');
            }
            $db->transCommit();

            AuditService::log(
                'CUSTOMER_APP_SIGNUP_APPROVED',
                'customer_app_account',
                $accountId,
                ['signup_request_id' => $requestId, 'status' => 'pending_approval'],
                ['signup_request_id' => $requestId, 'customer_id' => $chosenCustomerId, 'username' => $request['username']],
                (int) $guard['user_id'],
                (int) $guard['shop_id']
            );

            return $this->response->setStatusCode(201)->setJSON(['data' => [
                'signup_request_id' => $requestId,
                'customer_app_account_id' => $accountId,
                'customer_id' => $chosenCustomerId,
                'shop_id' => (int) $guard['shop_id'],
                'status' => 'approved',
                'message' => 'Customer app account approved. The customer can now sign in.',
            ]]);
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Customer signup approval failed: {message}', ['message' => $e->getMessage()]);
            return $this->response->setStatusCode(422)->setJSON([
                'error' => ['code' => 'APPROVAL_FAILED', 'message' => 'Signup approval failed. Verify the customer selection, shop scope and account uniqueness.'],
            ]);
        }
    }

    public function rejectSignup(int $requestId)
    {
        $guard = $this->guard();
        if (isset($guard['response'])) {
            return $guard['response'];
        }

        $input = $this->payload();
        $reason = trim((string) ($input['reason'] ?? ''));
        if ($reason === '') {
            return $this->response->setStatusCode(422)->setJSON([
                'error' => ['code' => 'REJECTION_REASON_REQUIRED', 'message' => 'Provide a reason for rejecting the registration.'],
            ]);
        }

        $db = Database::connect();
        $db->transBegin();
        try {
            $request = $db->query(
                'SELECT id,username,status FROM customer_signup_requests
                 WHERE id=? AND requested_location_id=? AND status=?
                 LIMIT 1 FOR UPDATE',
                [$requestId, (int) $guard['shop_id'], 'pending_approval']
            )->getRowArray();

            if (!$request) {
                $db->transRollback();
                return $this->response->setStatusCode(404)->setJSON([
                    'error' => ['code' => 'SIGNUP_REQUEST_NOT_FOUND', 'message' => 'Pending signup request was not found for the selected shop.'],
                ]);
            }

            $db->table('customer_signup_requests')->where('id', $requestId)->update([
                'status' => 'rejected',
                'reviewed_by' => (int) $guard['user_id'],
                'reviewed_at' => date('Y-m-d H:i:s'),
                'rejection_reason' => mb_substr($reason, 0, 500),
                'password_hash' => null,
            ]);

            if (!$db->transStatus()) {
                throw new \RuntimeException('Rejection could not be saved.');
            }
            $db->transCommit();

            AuditService::log(
                'CUSTOMER_APP_SIGNUP_REJECTED',
                'customer_signup_request',
                $requestId,
                ['status' => 'pending_approval'],
                ['status' => 'rejected', 'reason' => $reason],
                (int) $guard['user_id'],
                (int) $guard['shop_id']
            );

            return $this->response->setJSON(['data' => ['signup_request_id' => $requestId, 'status' => 'rejected']]);
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Customer signup rejection failed: {message}', ['message' => $e->getMessage()]);
            return $this->response->setStatusCode(422)->setJSON([
                'error' => ['code' => 'REJECTION_FAILED', 'message' => 'The signup request could not be rejected.'],
            ]);
        }
    }

    public function createAccount(int $customerId)
    {
        $guard = $this->guard();
        if (isset($guard['response'])) {
            return $guard['response'];
        }

        $input = $this->payload();
        $username = strtolower(trim((string) ($input['username'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        $passwordMinLength = (int) (new ConfigurationService())->resolve('password_min_length')['value'];
        if (!preg_match('/^[a-z0-9._-]{3,80}$/', $username) || strlen($password) < $passwordMinLength) {
            return $this->response->setStatusCode(422)->setJSON([
                'error' => ['code' => 'VALIDATION_FAILED', 'message' => 'Username must be 3–80 supported characters and password must be at least ' . $passwordMinLength . ' characters.'],
            ]);
        }

        $db = Database::connect();
        $db->transBegin();
        try {
            $customer = $db->query(
                'SELECT id,name,location_id,is_active FROM customers WHERE id=? AND location_id=? AND is_active=1 FOR UPDATE',
                [$customerId, (int) $guard['shop_id']]
            )->getRowArray();
            if (!$customer) {
                $db->transRollback();
                return $this->response->setStatusCode(404)->setJSON([
                    'error' => ['code' => 'CUSTOMER_NOT_FOUND', 'message' => 'Active financial customer was not found in the selected shop.'],
                ]);
            }

            if ($db->table('customer_app_accounts')->where('customer_id', $customerId)->countAllResults() > 0) {
                $db->transRollback();
                return $this->response->setStatusCode(409)->setJSON([
                    'error' => ['code' => 'ACCOUNT_EXISTS', 'message' => 'This customer already has an app account.'],
                ]);
            }
            if ($db->table('customer_app_accounts')->where('username', $username)->countAllResults() > 0
                || $db->table('customer_signup_requests')->where('pending_username', $username)->countAllResults() > 0) {
                $db->transRollback();
                return $this->response->setStatusCode(409)->setJSON([
                    'error' => ['code' => 'USERNAME_UNAVAILABLE', 'message' => 'This username is already in use or awaiting approval.'],
                ]);
            }

            $db->table('customer_app_accounts')->insert([
                'customer_id' => $customerId,
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'status' => 'approved',
                'approved_by' => (int) $guard['user_id'],
                'approved_at' => date('Y-m-d H:i:s'),
                'password_changed_at' => date('Y-m-d H:i:s'),
            ]);
            $accountId = (int) $db->insertID();
            if (!$db->transStatus()) {
                throw new \RuntimeException('Customer app account could not be saved.');
            }
            $db->transCommit();

            AuditService::log(
                'CUSTOMER_APP_ACCOUNT_CREATED',
                'customer_app_account',
                $accountId,
                null,
                ['customer_id' => $customerId, 'username' => $username],
                (int) $guard['user_id'],
                (int) $guard['shop_id']
            );

            return $this->response->setStatusCode(201)->setJSON(['data' => [
                'customer_app_account_id' => $accountId,
                'customer_id' => $customerId,
                'username' => $username,
                'status' => 'approved',
            ]]);
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Customer app account creation failed: {message}', ['message' => $e->getMessage()]);
            return $this->response->setStatusCode(422)->setJSON([
                'error' => ['code' => 'ACCOUNT_CREATION_FAILED', 'message' => 'Customer App account could not be created. Verify the customer is eligible and the username is available.'],
            ]);
        }
    }

    public function enableAccount(int $accountId)
    {
        $guard = $this->guard();
        if (isset($guard['response'])) {
            return $guard['response'];
        }

        $db = Database::connect();
        $db->transBegin();
        try {
            $account = $db->query(
                'SELECT ca.id,ca.customer_id,ca.username,ca.status,c.location_id,c.is_active AS customer_active
                 FROM customer_app_accounts ca
                 JOIN customers c ON c.id=ca.customer_id
                 WHERE ca.id=? AND c.location_id=?
                 LIMIT 1 FOR UPDATE',
                [$accountId, (int) $guard['shop_id']]
            )->getRowArray();

            if (!$account) {
                $db->transRollback();
                return $this->response->setStatusCode(404)->setJSON([
                    'error' => ['code' => 'ACCOUNT_NOT_FOUND', 'message' => 'Customer App account was not found in the selected shop.'],
                ]);
            }
            if ((int) $account['customer_active'] !== 1) {
                $db->transRollback();
                return $this->response->setStatusCode(409)->setJSON([
                    'error' => ['code' => 'CUSTOMER_INACTIVE', 'message' => 'An inactive financial customer cannot use the Customer App.'],
                ]);
            }

            $db->table('customer_app_accounts')->where('id', $accountId)->update(['status' => 'approved']);
            // Revoke any stale sessions even if the account was disabled outside this UI.
            (new ApiSessionService())->revokeAllForCustomer($accountId);
            if (!$db->transStatus()) {
                throw new \RuntimeException('Customer App account could not be enabled.');
            }
            $db->transCommit();

            AuditService::log('CUSTOMER_APP_ACCOUNT_ENABLED', 'customer_app_account', $accountId,
                ['status' => $account['status']], ['status' => 'approved'],
                (int) $guard['user_id'], (int) $guard['shop_id']);

            return $this->response->setJSON(['data' => [
                'customer_app_account_id' => $accountId,
                'status' => 'approved',
            ]]);
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Customer App account enable failed: {message}', ['message' => $e->getMessage()]);
            return $this->response->setStatusCode(422)->setJSON([
                'error' => ['code' => 'ACCOUNT_ENABLE_FAILED', 'message' => 'The Customer App account could not be enabled.'],
            ]);
        }
    }

    public function resetPassword(int $accountId)
    {
        $guard = $this->guard();
        if (isset($guard['response'])) {
            return $guard['response'];
        }

        $input = $this->payload();
        $password = (string) ($input['password'] ?? '');
        $passwordMinLength = (int) (new ConfigurationService())->resolve('password_min_length')['value'];
        if (strlen($password) < $passwordMinLength) {
            return $this->response->setStatusCode(422)->setJSON([
                'error' => ['code' => 'VALIDATION_FAILED', 'message' => 'New password must be at least ' . $passwordMinLength . ' characters.'],
            ]);
        }

        $db = Database::connect();
        $db->transBegin();
        try {
            $account = $db->query(
                'SELECT ca.id,ca.customer_id,ca.username,ca.status,c.location_id,c.is_active AS customer_active
                 FROM customer_app_accounts ca
                 JOIN customers c ON c.id=ca.customer_id
                 WHERE ca.id=? AND c.location_id=?
                 LIMIT 1 FOR UPDATE',
                [$accountId, (int) $guard['shop_id']]
            )->getRowArray();

            if (!$account) {
                $db->transRollback();
                return $this->response->setStatusCode(404)->setJSON([
                    'error' => ['code' => 'ACCOUNT_NOT_FOUND', 'message' => 'Customer App account was not found in the selected shop.'],
                ]);
            }
            if ((string) $account['status'] !== 'approved' || (int) $account['customer_active'] !== 1) {
                $db->transRollback();
                return $this->response->setStatusCode(409)->setJSON([
                    'error' => ['code' => 'ACCOUNT_NOT_ACTIVE', 'message' => 'Only an approved account linked to an active customer can have its password reset.'],
                ]);
            }

            $db->table('customer_app_accounts')->where('id', $accountId)->update([
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'password_changed_at' => date('Y-m-d H:i:s'),
            ]);
            (new ApiSessionService())->revokeAllForCustomer($accountId);
            if (!$db->transStatus()) {
                throw new \RuntimeException('Customer app password could not be updated.');
            }
            $db->transCommit();
            AuditService::log(
                'CUSTOMER_APP_PASSWORD_RESET',
                'customer_app_account',
                $accountId,
                ['password_changed' => false],
                ['password_changed' => true, 'sessions_revoked' => true],
                (int) $guard['user_id'],
                (int) $guard['shop_id']
            );

            return $this->response->setJSON(['data' => [
                'customer_app_account_id' => $accountId,
                'status' => 'password_reset',
                'sessions_revoked' => true,
            ]]);
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Customer app password reset failed: {message}', ['message' => $e->getMessage()]);
            return $this->response->setStatusCode(422)->setJSON([
                'error' => ['code' => 'PASSWORD_RESET_FAILED', 'message' => 'The Customer App password could not be reset.'],
            ]);
        }
    }

    public function disableAccount(int $accountId)
    {
        $guard = $this->guard();
        if (isset($guard['response'])) {
            return $guard['response'];
        }

        $db = Database::connect();
        $db->transBegin();
        try {
            $account = $db->query(
                'SELECT ca.id,ca.customer_id,c.location_id,ca.status
                 FROM customer_app_accounts ca
                 JOIN customers c ON c.id=ca.customer_id
                 WHERE ca.id=? AND c.location_id=?
                 LIMIT 1 FOR UPDATE',
                [$accountId, (int) $guard['shop_id']]
            )->getRowArray();

            if (!$account) {
                $db->transRollback();
                return $this->response->setStatusCode(404)->setJSON([
                    'error' => ['code' => 'ACCOUNT_NOT_FOUND', 'message' => 'Customer App account was not found in the selected shop.'],
                ]);
            }

            $db->table('customer_app_accounts')->where('id', $accountId)->update(['status' => 'disabled']);
            (new ApiSessionService())->revokeAllForCustomer($accountId);
            if (!$db->transStatus()) {
                throw new \RuntimeException('Customer App account could not be disabled.');
            }
            $db->transCommit();

            AuditService::log(
                'CUSTOMER_APP_ACCOUNT_DISABLED',
                'customer_app_account',
                $accountId,
                ['status' => $account['status']],
                ['status' => 'disabled', 'sessions_revoked' => true],
                (int) $guard['user_id'],
                (int) $guard['shop_id']
            );

            return $this->response->setJSON(['data' => ['customer_app_account_id' => $accountId, 'status' => 'disabled', 'sessions_revoked' => true]]);
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Customer App account disable failed: {message}', ['message' => $e->getMessage()]);
            return $this->response->setStatusCode(422)->setJSON([
                'error' => ['code' => 'ACCOUNT_DISABLE_FAILED', 'message' => 'The Customer App account could not be disabled.'],
            ]);
        }
    }
}
