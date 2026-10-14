<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Services\MigrationRunnerService;
use App\Services\InstallationSetupService;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RedirectResponse;

class Auth extends Controller
{
    protected UserModel $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
        helper('form');
    }

    public function showLogin()
    {
        if(session()->get('isLoggedIn')) return redirect()->to(site_url('dashboard'));

        if ((new InstallationSetupService())->shouldRedirectToSetup()) {
            return redirect()->to(site_url('installation'));
        }

        try {
            $migrationGate=(new MigrationRunnerService())->status();
            $companyName = trim((string) env('app.companyName', 'Perfect LPG (Pvt.) LTD')) ?: 'Perfect LPG (Pvt.) LTD';
            return view('auth/login',['title'=>'Login','companyName'=>$companyName,'migrationGate'=>$migrationGate]);
        } catch (\Throwable $e) {
            return view('auth/login',[
                'title'=>'Login',
                'companyName'=>trim((string) env('app.companyName', 'Perfect LPG (Pvt.) LTD')) ?: 'Perfect LPG (Pvt.) LTD',
                'migrationGate'=>[
                    'ready'=>false,
                    'blocked_message'=>'Database migration status could not be checked.',
                    'system_error'=>$e->getMessage(),
                    'migrations'=>[],
                    'total'=>0,
                    'completed'=>0,
                ],
            ]);
        }
    }

    public function attemptLogin(): RedirectResponse
    {
        if ((new InstallationSetupService())->shouldRedirectToSetup()) {
            return redirect()->to(site_url('installation'));
        }
        try {
            $migrationGate=(new MigrationRunnerService())->status();
            if(!$migrationGate['ready']) {
                return redirect()->to(site_url('login'))->with('migration_error',$migrationGate['blocked_message'] ?? 'Database migrations are pending. Execute them before signing in.');
            }
        } catch (\Throwable $e) {
            return redirect()->to(site_url('login'))->with('migration_error','Database migration status could not be checked: '.$e->getMessage());
        }
        // Passwords may be short (including the configured initial password '1');
        // authentication still verifies the submitted value against the stored hash.
        $rules=['login'=>'required|min_length[1]','password'=>'required'];
        if(!$this->validate($rules)){
            return redirect()->back()->withInput()->with('errors',$this->validator->getErrors());
        }

        $login=$this->request->getPost('login');
        $password=$this->request->getPost('password');
        $user=$this->userModel->findByLogin($login);

        if(!$user || !password_verify($password,$user['password_hash'])){
            return redirect()->back()->withInput()->with('error','Invalid username/email or password.');
        }
        if(!(int)$user['is_active']){
            return redirect()->back()->withInput()->with('error','This account has been disabled. Contact an administrator.');
        }

        session()->regenerate();
        session()->set([
            'user_id'=>(int)$user['id'],
            'location_id'=>$user['location_id'] !== null ? (int)$user['location_id'] : null,
            'full_name'=>$user['full_name'],
            'username'=>$user['username'],
            'role'=>$user['role'],
            'role_name'=>$user['role_name'],
            'isLoggedIn'=>true,
        ]);
        $this->userModel->touchLastLogin((int)$user['id']);
        return redirect()->to(site_url('dashboard'))->with('success','Welcome back, '.$user['full_name'].'.');
    }

    public function logout(): RedirectResponse
    {
        session()->destroy();
        return redirect()->to(site_url('login'))->with('success','You have been logged out.');
    }
}
