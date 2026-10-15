<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Services\MigrationRunnerService;
use App\Services\ShopScopeService;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RedirectResponse;

class Migrations extends Controller
{
    /**
     * Pending migrations block sign-in, so this endpoint cannot sit behind the auth filter.
     * It must still never run for anonymous visitors: the caller is either a signed-in
     * System Administrator or supplies the credentials of an active System Administrator.
     */
    private function authorizedSystemAdministrator(): bool
    {
        $sessionUserId = (int) (session()->get('user_id') ?? 0);
        if ($sessionUserId > 0 && $this->isSystemAdministrator($sessionUserId, (string) session()->get('role'))) {
            return true;
        }

        $login = trim((string) $this->request->getPost('admin_login'));
        $password = (string) $this->request->getPost('admin_password');
        if ($login === '' || $password === '') {
            return false;
        }

        $user = (new UserModel())->findByLogin($login);
        return $user
            && (int) ($user['is_active'] ?? 0) === 1
            && password_verify($password, (string) $user['password_hash'])
            && $this->isSystemAdministrator((int) $user['id'], (string) ($user['role'] ?? ''));
    }

    private function isSystemAdministrator(int $userId, string $roleCode): bool
    {
        try {
            return (new ShopScopeService())->isSystemAdministrator($userId);
        } catch (\Throwable $e) {
            // Databases older than migration 032 have no is_system_admin flag yet;
            // the ADMIN role is the administrator there.
            return strtoupper($roleCode) === 'ADMIN';
        }
    }

    public function run(): RedirectResponse
    {
        if (!$this->authorizedSystemAdministrator()) {
            log_message('warning', 'Rejected unauthorized migration run request from {ip}', ['ip' => $this->request->getIPAddress()]);
            return redirect()->to(site_url('login'))
                ->with('migration_error', 'Only a System Administrator can execute migrations. Enter a valid System Administrator username and password.');
        }

        try {
            $result = (new MigrationRunnerService())->runPending();

            if ($result['success']) {
                return redirect()->to(site_url('login'))
                    ->with('migration_success', $result['message']);
            }

            $failed = null;
            foreach ($result['executed'] as $row) {
                if ($row['status'] === 'failed') {
                    $failed = $row;
                    break;
                }
            }

            return redirect()->to(site_url('login'))
                ->with('migration_error', ($failed['file'] ?? 'Migration').' — '.($failed['message'] ?? $result['message']));
        } catch (\Throwable $e) {
            return redirect()->to(site_url('login'))
                ->with('migration_error', $e->getMessage());
        }
    }
}
