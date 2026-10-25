<?php

namespace App\Commands;

use App\Services\TransactionalDataRefreshService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * QA-only command (tests/qa). verify_security.sh copies it into app/Commands, runs it with
 * CI_ENVIRONMENT=production against the disposable QA database and removes it again; it is never
 * deployed. Proves destructive tools refuse to run in production (BL-016).
 */
class QaProductionGuards extends BaseCommand
{
    protected $group = 'QA';
    protected $name = 'qa:production-guards';

    public function run(array $params)
    {
        CLI::write('environment:' . ENVIRONMENT);
        try {
            (new TransactionalDataRefreshService())->refresh();
            CLI::write('refresh:ran');
        } catch (\RuntimeException $e) {
            CLI::write('refresh:' . (str_contains($e->getMessage(), 'disabled in production') ? 'refused' : 'error ' . $e->getMessage()));
        }
    }
}
