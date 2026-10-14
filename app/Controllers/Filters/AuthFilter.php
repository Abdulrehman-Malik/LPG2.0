<?php
namespace App\Controllers\Filters;

use App\Services\MigrationRunnerService;
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
    }

    public function after(RequestInterface $request,ResponseInterface $response,$arguments=null){}
}
