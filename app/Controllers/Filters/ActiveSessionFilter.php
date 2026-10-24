<?php

namespace App\Controllers\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Database;

/**
 * For routes that must keep working while database migrations are pending (Database Backup),
 * so they cannot use AuthFilter's migration gate: the session must still belong to an active
 * account. The controller's own permission check (System Administrator) still applies.
 */
class ActiveSessionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $userId = (int) (session()->get('user_id') ?? 0);
        $active = $userId > 0 && session()->get('isLoggedIn')
            && Database::connect()->table('users')->where(['id' => $userId, 'is_active' => 1])->countAllResults() > 0;
        if (!$active) {
            session()->remove(['user_id', 'location_id', 'full_name', 'username', 'role', 'role_name', 'isLoggedIn']);
            return redirect()->to(site_url('login'))->with('error', 'Please sign in to continue.');
        }

        \App\Libraries\TenantContext::setWeb($userId, (int) (session()->get('location_id') ?? 0));
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
