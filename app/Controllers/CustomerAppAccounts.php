<?php

namespace App\Controllers;

use App\Services\ApiSessionService;
use App\Services\AuditService;
use App\Services\ConfigurationService;
use App\Services\PermissionService;
use App\Services\ShopScopeService;
use CodeIgniter\Controller;
use Config\Database;

class CustomerAppAccounts extends Controller
{
    protected function context(): array
    {
        if (!PermissionService::allows('CUSTOMER_ACCOUNT_MANAGE')) {
            return ['error' => $this->response->setStatusCode(403)->setBody('You do not have permission to manage Customer App accounts.')];
        }

        $userId = (int) (session()->get('user_id') ?? 0);
        $shopId = (int) (session()->get('location_id') ?? 0);
        if ($userId <= 0 || $shopId <= 0 || !(new ShopScopeService())->canAccessShop($shopId, $userId)) {
            return ['error' => $this->response->setStatusCode(403)->setBody('An authorized shop context is required.')];
        }

        return ['user_id' => $userId, 'shop_id' => $shopId];
    }

    protected function passwordMinimum(): int
    {
        return (int) (new ConfigurationService())->resolve('password_min_length')['value'];
    }

    public function index()
    {
        $context = $this->context();
        if (isset($context['error'])) return $context['error'];

        $db = Database::connect();
        $shopId = (int) $context['shop_id'];
        $pending = $db->table('customer_signup_requests')
            ->select('id,username,customer_name,phone,city,address,vehicle_no,created_at')
            ->where(['requested_location_id' => $shopId, 'status' => 'pending_approval'])
            ->orderBy('created_at', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()->getResultArray();

        $accounts = $db->table('customer_app_accounts ca')
            ->select('ca.id AS account_id,ca.customer_id,ca.username,ca.status,ca.approved_at,ca.last_login_at,c.code AS customer_code,c.name AS customer_name,c.phone,c.is_active AS customer_active')
            ->join('customers c', 'c.id=ca.customer_id', 'inner')
            ->where('c.location_id', $shopId)
            ->orderBy('c.name')
            ->get()->getResultArray();

        $customers = $db->table('customers c')
            ->select('c.id,c.code,c.name,c.phone')
            ->join('customer_app_accounts ca', 'ca.customer_id=c.id', 'left')
            ->where(['c.location_id' => $shopId, 'c.is_active' => 1])
            ->where('ca.id IS NULL', null, false)
            ->orderBy('c.name')
            ->get()->getResultArray();

        return view('customer_app_accounts/index', [
            'title' => 'Customer App Accounts',
            'pending' => $pending,
            'accounts' => $accounts,
            'customers' => $customers,
            'shopId' => $shopId,
            'passwordMinimum' => $this->passwordMinimum(),
        ]);
    }

    public function approve(int $requestId)
    {
        $context = $this->context();
        if (isset($context['error'])) return $context['error'];

        $userId = (int) $context['user_id'];
        $shopId = (int) $context['shop_id'];
        $customerId = (int) $this->request->getPost('customer_id');
        $db = Database::connect();
        $db->transBegin();

        try {
            $request = $db->query(
                'SELECT * FROM customer_signup_requests
                 WHERE id=? AND requested_location_id=? AND status=?
                 LIMIT 1 FOR UPDATE',
                [$requestId, $shopId, 'pending_approval']
            )->getRowArray();
            if (!$request) {
                $db->transRollback();
                return redirect()->to(site_url('customer-app-accounts'))->with('error', 'This pending signup was not found for the current shop. It may already have been reviewed.');
            }
            if (empty($request['password_hash'])) {
                throw new \RuntimeException('The signup request has no valid password credential.');
            }

            $usernameTaken = $db->table('customer_app_accounts')->where('username', (string) $request['username'])->countAllResults() > 0;
            if ($usernameTaken) {
                throw new \RuntimeException('This username is already linked to a Customer App account.');
            }

            if ($customerId > 0) {
                $customer = $db->table('customers')
                    ->where(['id' => $customerId, 'location_id' => $shopId, 'is_active' => 1])
                    ->get()->getRowArray();
                if (!$customer) {
                    throw new \RuntimeException('Select an active financial customer belonging to the current shop.');
                }
            } else {
                $db->table('customers')->insert([
                    'location_id' => $shopId,
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
                $customerId = (int) $db->insertID();
            }

            if ($db->table('customer_app_accounts')->where('customer_id', $customerId)->countAllResults() > 0) {
                throw new \RuntimeException('This financial customer already has a Customer App account. Select a different customer.');
            }

            $db->table('customer_app_accounts')->insert([
                'customer_id' => $customerId,
                'username' => (string) $request['username'],
                'password_hash' => (string) $request['password_hash'],
                'status' => 'approved',
                'approved_by' => $userId,
                'approved_at' => date('Y-m-d H:i:s'),
                'password_changed_at' => date('Y-m-d H:i:s'),
            ]);
            $accountId = (int) $db->insertID();
            $db->table('customer_signup_requests')->where('id', $requestId)->update([
                'status' => 'approved',
                'linked_customer_id' => $customerId,
                'reviewed_by' => $userId,
                'reviewed_at' => date('Y-m-d H:i:s'),
                'password_hash' => null,
                'rejection_reason' => null,
            ]);

            if (!$db->transStatus()) throw new \RuntimeException('Signup approval could not be saved.');
            $db->transCommit();

            AuditService::log('CUSTOMER_APP_SIGNUP_APPROVED', 'customer_app_account', $accountId,
                ['signup_request_id' => $requestId, 'status' => 'pending_approval'],
                ['signup_request_id' => $requestId, 'customer_id' => $customerId, 'username' => $request['username']],
                $userId, $shopId);

            return redirect()->to(site_url('customer-app-accounts'))->with('success', 'Customer App account approved and linked to the selected financial customer.');
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Web customer app signup approval failed: {message}', ['message' => $e->getMessage()]);
            return redirect()->to(site_url('customer-app-accounts'))->with('error', 'Signup approval failed. Ensure the selected customer is active, belongs to this shop and has no existing Customer App account.');
        }
    }

    public function reject(int $requestId)
    {
        $context = $this->context();
        if (isset($context['error'])) return $context['error'];

        $userId = (int) $context['user_id'];
        $shopId = (int) $context['shop_id'];
        $reason = trim((string) $this->request->getPost('reason'));
        if ($reason === '' || mb_strlen($reason) > 500) {
            return redirect()->to(site_url('customer-app-accounts'))->with('error', 'A rejection reason of 1–500 characters is required.');
        }

        $db = Database::connect();
        $db->transBegin();
        try {
            $request = $db->query(
                'SELECT id,username,status FROM customer_signup_requests
                 WHERE id=? AND requested_location_id=? AND status=?
                 LIMIT 1 FOR UPDATE',
                [$requestId, $shopId, 'pending_approval']
            )->getRowArray();
            if (!$request) {
                $db->transRollback();
                return redirect()->to(site_url('customer-app-accounts'))->with('error', 'This pending signup was not found for the current shop or was already reviewed.');
            }

            $db->table('customer_signup_requests')->where('id', $requestId)->update([
                'status' => 'rejected',
                'reviewed_by' => $userId,
                'reviewed_at' => date('Y-m-d H:i:s'),
                'rejection_reason' => $reason,
                'password_hash' => null,
            ]);
            if (!$db->transStatus()) throw new \RuntimeException('Signup rejection could not be saved.');
            $db->transCommit();

            AuditService::log('CUSTOMER_APP_SIGNUP_REJECTED', 'customer_signup_request', $requestId,
                ['status' => 'pending_approval'],
                ['status' => 'rejected', 'reason' => $reason],
                $userId, $shopId);
            return redirect()->to(site_url('customer-app-accounts'))->with('success', 'Signup request rejected. The stored password hash was cleared.');
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Web customer app signup rejection failed: {message}', ['message' => $e->getMessage()]);
            return redirect()->to(site_url('customer-app-accounts'))->with('error', 'Signup request could not be rejected.');
        }
    }

    public function createAccount(int $customerId)
    {
        $context = $this->context();
        if (isset($context['error'])) return $context['error'];

        $username = strtolower(trim((string) $this->request->getPost('username')));
        $password = (string) $this->request->getPost('password');
        if (!preg_match('/^[a-z0-9._-]{3,80}$/', $username) || strlen($password) < $this->passwordMinimum()) {
            return redirect()->to(site_url('customer-app-accounts'))->with('error', 'Username must be 3–80 supported characters and password must meet the configured minimum length.');
        }

        $userId = (int) $context['user_id'];
        $shopId = (int) $context['shop_id'];
        $db = Database::connect();
        $db->transBegin();
        try {
            $customer = $db->query(
                'SELECT id,name,location_id,is_active FROM customers WHERE id=? AND location_id=? AND is_active=1 LIMIT 1 FOR UPDATE',
                [$customerId, $shopId]
            )->getRowArray();
            if (!$customer) throw new \RuntimeException('Active financial customer not found in the current shop.');

            if ($db->table('customer_app_accounts')->where('customer_id', $customerId)->countAllResults() > 0) {
                throw new \RuntimeException('This financial customer already has an app account.');
            }
            if ($db->table('customer_app_accounts')->where('username', $username)->countAllResults() > 0
                || $db->table('customer_signup_requests')->where('pending_username', $username)->countAllResults() > 0) {
                throw new \RuntimeException('This username is already used or reserved by a pending signup.');
            }

            $db->table('customer_app_accounts')->insert([
                'customer_id' => $customerId,
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'status' => 'approved',
                'approved_by' => $userId,
                'approved_at' => date('Y-m-d H:i:s'),
                'password_changed_at' => date('Y-m-d H:i:s'),
            ]);
            $accountId = (int) $db->insertID();
            if (!$db->transStatus()) throw new \RuntimeException('Customer App account could not be created.');
            $db->transCommit();

            AuditService::log('CUSTOMER_APP_ACCOUNT_CREATED', 'customer_app_account', $accountId, null,
                ['customer_id' => $customerId, 'username' => $username], $userId, $shopId);
            return redirect()->to(site_url('customer-app-accounts'))->with('success', 'Customer App login created for ' . (string) $customer['name'] . '.');
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Web customer app account creation failed: {message}', ['message' => $e->getMessage()]);
            return redirect()->to(site_url('customer-app-accounts'))->with('error', 'Customer App account could not be created. Ensure the customer has no existing app account and the username is available.');
        }
    }

    public function enable(int $accountId)
    {
        $context = $this->context();
        if (isset($context['error'])) return $context['error'];

        $userId = (int) $context['user_id'];
        $shopId = (int) $context['shop_id'];
        $db = Database::connect();
        $db->transBegin();

        try {
            $account = $db->query(
                'SELECT ca.id,ca.customer_id,ca.username,ca.status,c.location_id,c.is_active AS customer_active
                 FROM customer_app_accounts ca
                 JOIN customers c ON c.id=ca.customer_id
                 WHERE ca.id=? AND c.location_id=?
                 LIMIT 1 FOR UPDATE',
                [$accountId, $shopId]
            )->getRowArray();

            if (!$account) {
                $db->transRollback();
                return redirect()->to(site_url('customer-app-accounts'))->with('error', 'Customer App account was not found in the current shop.');
            }
            if ((int) $account['customer_active'] !== 1) {
                $db->transRollback();
                return redirect()->to(site_url('customer-app-accounts'))->with('error', 'An inactive financial customer cannot use the Customer App.');
            }

            $db->table('customer_app_accounts')->where('id', $accountId)->update(['status' => 'approved']);
            if (!$db->transStatus()) {
                throw new \RuntimeException('Customer App account could not be enabled.');
            }
            $db->transCommit();

            AuditService::log('CUSTOMER_APP_ACCOUNT_ENABLED', 'customer_app_account', $accountId,
                ['status' => $account['status']], ['status' => 'approved'], $userId, $shopId);
            return redirect()->to(site_url('customer-app-accounts'))->with('success', 'Customer App account enabled. The customer must sign in again; old sessions remain revoked.');
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Web Customer App account enable failed: {message}', ['message' => $e->getMessage()]);
            return redirect()->to(site_url('customer-app-accounts'))->with('error', 'Customer App account could not be enabled.');
        }
    }

    public function resetPassword(int $accountId)
    {
        $context = $this->context();
        if (isset($context['error'])) return $context['error'];

        $password = (string) $this->request->getPost('password');
        if (strlen($password) < $this->passwordMinimum()) {
            return redirect()->to(site_url('customer-app-accounts'))->with('error', 'New password must meet the configured minimum length.');
        }

        $userId = (int) $context['user_id'];
        $shopId = (int) $context['shop_id'];
        $db = Database::connect();
        $db->transBegin();

        try {
            $account = $db->query(
                'SELECT ca.id,ca.customer_id,ca.username,ca.status,c.location_id,c.is_active AS customer_active
                 FROM customer_app_accounts ca
                 JOIN customers c ON c.id=ca.customer_id
                 WHERE ca.id=? AND c.location_id=?
                 LIMIT 1 FOR UPDATE',
                [$accountId, $shopId]
            )->getRowArray();

            if (!$account) {
                $db->transRollback();
                return redirect()->to(site_url('customer-app-accounts'))->with('error', 'Customer App account was not found in the current shop.');
            }
            if ((string) $account['status'] !== 'approved' || (int) $account['customer_active'] !== 1) {
                $db->transRollback();
                return redirect()->to(site_url('customer-app-accounts'))->with('error', 'Only an enabled account linked to an active customer can have its password reset.');
            }

            $db->table('customer_app_accounts')->where('id', $accountId)->update([
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'password_changed_at' => date('Y-m-d H:i:s'),
            ]);
            if (!$db->transStatus()) {
                throw new \RuntimeException('Customer App password could not be updated.');
            }
            $db->transCommit();

            (new ApiSessionService())->revokeAllForCustomer($accountId);
            AuditService::log('CUSTOMER_APP_PASSWORD_RESET', 'customer_app_account', $accountId,
                ['password_changed' => false],
                ['password_changed' => true, 'sessions_revoked' => true],
                $userId, $shopId);

            return redirect()->to(site_url('customer-app-accounts'))->with('success', 'Customer App password reset. All existing app sessions were revoked.');
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Web Customer App password reset failed: {message}', ['message' => $e->getMessage()]);
            return redirect()->to(site_url('customer-app-accounts'))->with('error', 'Customer App password could not be reset.');
        }
    }

    public function disable(int $accountId)
    {
        $context = $this->context();
        if (isset($context['error'])) return $context['error'];

        $userId = (int) $context['user_id'];
        $shopId = (int) $context['shop_id'];
        $db = Database::connect();
        $account = $db->table('customer_app_accounts ca')
            ->select('ca.id,ca.customer_id,ca.status,c.location_id')
            ->join('customers c', 'c.id=ca.customer_id', 'inner')
            ->where(['ca.id' => $accountId, 'c.location_id' => $shopId])
            ->get()->getRowArray();
        if (!$account) return redirect()->to(site_url('customer-app-accounts'))->with('error', 'Customer App account was not found in this shop.');

        $db->table('customer_app_accounts')->where('id', $accountId)->update(['status' => 'disabled']);
        if (!$db->affectedRows() && (string) $account['status'] !== 'disabled') {
            return redirect()->to(site_url('customer-app-accounts'))->with('error', 'Customer App account could not be disabled.');
        }
        (new ApiSessionService())->revokeAllForCustomer($accountId);

        AuditService::log('CUSTOMER_APP_ACCOUNT_DISABLED', 'customer_app_account', $accountId,
            ['status' => $account['status']], ['status' => 'disabled'], $userId, $shopId);
        return redirect()->to(site_url('customer-app-accounts'))->with('success', 'Customer App account disabled and all its sessions revoked.');
    }
}
