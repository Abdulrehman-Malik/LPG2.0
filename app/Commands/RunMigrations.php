<?php

namespace App\Commands;

use App\Services\MigrationRunnerService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use RuntimeException;

class RunMigrations extends BaseCommand
{
    protected $group = 'LPG';

    protected $name = 'migrations:run';

    protected $description = 'Apply all pending LPG database migrations.';

    public function run(array $params)
    {
        $result = (new MigrationRunnerService())->runPending();

        foreach ($result['executed'] as $row) {
            $message = sprintf(
                '%s %s (%d statements, %d ms)',
                strtoupper($row['status']),
                $row['file'],
                (int) $row['executed_statements'],
                (int) $row['duration_ms']
            );

            if ($row['status'] === 'failed') {
                CLI::error($message);
            } else {
                CLI::write($message);
            }
        }

        if (!$result['success']) {
            throw new RuntimeException($result['message']);
        }

        CLI::write($result['message']);
    }
}
