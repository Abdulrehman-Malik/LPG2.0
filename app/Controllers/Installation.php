<?php

namespace App\Controllers;

use App\Services\InstallationSetupService;
use CodeIgniter\Controller;

class Installation extends Controller
{
    public function index()
    {
        $service = new InstallationSetupService();
        if (!$service->enabled()) {
            return redirect()->to(site_url('login'))->with('migration_error', 'Fresh database setup is disabled. Enable FRESH_DATABASE_SETUP in .env only during deployment.');
        }

        try {
            $state = $service->inspect();
            return view('installation/setup', [
                'title' => 'Database Installation Setup',
                'state' => $state,
                'error' => session()->getFlashdata('installation_error'),
                'success' => session()->getFlashdata('installation_success'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Installation status check failed: {error}', ['error' => $e->getMessage()]);
            return view('installation/setup', [
                'title' => 'Database Installation Setup',
                'state' => [
                    'database' => (string) (config('Database')->default['database'] ?? ''),
                    'exists' => false,
                    'tables' => 0,
                    'has_data' => false,
                    'admin_exists' => false,
                    'complete' => false,
                ],
                'error' => 'Database server could not be inspected: ' . $e->getMessage(),
                'success' => null,
            ]);
        }
    }

    public function run()
    {
        $service = new InstallationSetupService();
        try {
            $result = $service->install([
                'admin_password' => (string) $this->request->getPost('admin_password'),
                'confirm' => (string) $this->request->getPost('confirm'),
                'replace_confirm' => (string) $this->request->getPost('replace_confirm'),
            ]);
            log_message('warning', 'Database installation completed for {database}; backup={backup}', [
                'database' => $result['database'],
                'backup' => $result['backup'] ? basename($result['backup']) : 'not-required',
            ]);
            return redirect()->to(site_url('login'))->with('migration_success', 'Database setup completed and schema/migrations were applied. You can now sign in.');
        } catch (\Throwable $e) {
            log_message('error', 'Database installation failed: {error}', ['error' => $e->getMessage()]);
            return redirect()->to(site_url('installation'))->with('installation_error', $e->getMessage());
        }
    }
}
