<?php
namespace App\Controllers\Filters;

use App\Services\MigrationRunnerService;
use App\Services\ShopScopeService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class AuthFilter implements FilterInterface
{
    /**
     * Ends the signed-in identity and shows the reason on the login page. The session is
     * cleared and its ID rotated rather than destroyed, because a destroyed session cannot
     * carry the flash message to the next request.
     */
    private function signOut(string $flashKey, string $message)
    {
        $session = session();
        $session->remove(['user_id','location_id','full_name','username','role','role_name','isLoggedIn']);
        $session->regenerate(true);
        return redirect()->to(site_url('login'))->with($flashKey, $message);
    }

    public function before(RequestInterface $request,$arguments=null)
    {
        if(!session()->get('isLoggedIn')) {
            return redirect()->to(site_url('login'))->with('error','Please sign in to continue.');
        }

        try {
            $migrationGate=(new MigrationRunnerService())->status();
            if(!$migrationGate['ready']) {
                return $this->signOut('migration_error',$migrationGate['blocked_message'] ?? 'Database migrations are pending. Execute them before signing in.');
            }
        } catch (\Throwable $e) {
            return $this->signOut('migration_error','Database migration status could not be checked: '.$e->getMessage());
        }

        $userId = (int) (session()->get('user_id') ?? 0);
        $activeUser = \Config\Database::connect()->table('users')
            ->select('id,must_change_password')
            ->where(['id' => $userId, 'is_active' => 1])
            ->get()->getRowArray();
        if (!$activeUser) {
            // An administrator disabled this account after sign-in: end the session now.
            return $this->signOut('error', 'This account has been disabled. Contact an administrator.');
        }

        // An account still on a shipped default password may only change it (BL-003).
        if ((int) ($activeUser['must_change_password'] ?? 0) === 1 && !url_is('account/password')) {
            return redirect()->to(site_url('account/password'));
        }

        $scope = new ShopScopeService();
        if (!$scope->canAccessCurrentShop($userId)) {
            // Repair a stale current-shop context only by selecting a shop this
            // identity is still authorized to access. Never trust a request ID.
            $defaultShopId = $scope->defaultShopIdForUser($userId);
            if ($defaultShopId === null) {
                return $this->signOut('error', 'Your account is not assigned to an active shop. Contact an administrator.');
            }

            session()->set('location_id', $defaultShopId);
        }
    }

    public function after(RequestInterface $request,ResponseInterface $response,$arguments=null){}
}
