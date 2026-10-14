<?php

namespace App\Controllers;

use App\Services\MigrationRunnerService;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RedirectResponse;

class Migrations extends Controller
{
    public function run(): RedirectResponse
    {
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
