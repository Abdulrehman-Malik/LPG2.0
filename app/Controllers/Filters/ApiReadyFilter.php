<?php

namespace App\Controllers\Filters;

use App\Services\InstallationSetupService;
use App\Services\MigrationRunnerService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class ApiReadyFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        try {
            if ((new InstallationSetupService())->shouldRedirectToSetup()) {
                return service('response')->setStatusCode(503)->setJSON([
                    'error' => [
                        'code' => 'INSTALLATION_REQUIRED',
                        'message' => 'Application installation must be completed before the API can be used.',
                    ],
                ]);
            }

            $status = (new MigrationRunnerService())->status();
            if (!($status['ready'] ?? false)) {
                return service('response')->setStatusCode(503)->setJSON([
                    'error' => [
                        'code' => 'DATABASE_UPGRADE_REQUIRED',
                        'message' => $status['blocked_message'] ?? 'Database migrations must be completed before the API can be used.',
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            log_message('error', 'Mobile API readiness check failed: {message}', ['message' => $e->getMessage()]);
            return service('response')->setStatusCode(503)->setJSON([
                'error' => [
                    'code' => 'API_NOT_READY',
                    'message' => 'The API is unavailable until installation and database checks pass.',
                ],
            ]);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }
}
