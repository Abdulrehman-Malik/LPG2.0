<?php
namespace App\Controllers\Filters;

use App\Services\MigrationRunnerService;
use App\Services\ShopScopeService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request,$arguments=null)
    {
        if(!session()->get('isLoggedIn')) {
            return redirect()->to(site_url('login'))->with('error','Please sign in to continue.');
        }

        try {
            $migrationGate=(new MigrationRunnerService())->status();
            if(!$migrationGate['ready']) {
                session()->destroy();
                return redirect()->to(site_url('login'))
                    ->with('migration_error',$migrationGate['blocked_message'] ?? 'Database migrations are pending. Execute them before signing in.');
            }
        } catch (\Throwable $e) {
            session()->destroy();
            return redirect()->to(site_url('login'))
                ->with('migration_error','Database migration status could not be checked: '.$e->getMessage());
        }

        $userId = (int) (session()->get('user_id') ?? 0);
        $scope = new ShopScopeService();
        if (!$scope->canAccessCurrentShop($userId)) {
            // Repair a stale current-shop context only by selecting a shop this
            // identity is still authorized to access. Never trust a request ID.
            $defaultShopId = $scope->defaultShopIdForUser($userId);
            if ($defaultShopId === null) {
                session()->destroy();
                return redirect()->to(site_url('login'))
                    ->with('error', 'Your account is not assigned to an active shop. Contact an administrator.');
            }

            session()->set('location_id', $defaultShopId);
        }
    }

    public function after(RequestInterface $request,ResponseInterface $response,$arguments=null){}
}
