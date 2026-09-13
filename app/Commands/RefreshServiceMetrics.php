<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class RefreshServiceMetrics extends BaseCommand
{
    protected $group = 'Maintenance';
    protected $name = 'service-metrics:refresh';
    protected $description = 'Refreshes service-metrics-based Sunday aggregate tables without using member attendance write paths.';
    protected $usage = 'service-metrics:refresh [--from 2026-01-01] [--to 2026-12-31] [--rolling-days 35] [--diocese 2] [--parish 9] [--rebuild-period-buckets]';

    public function run(array $params)
    {
        $start = microtime(true);

        CLI::write('Starting service-metrics refresh flow (separate from member attendance registration)...', 'cyan');

        try {
            service('commands')->run('attendance:sync-sunday-service-metrics', $params);
        } catch (\Throwable $e) {
            CLI::error('service-metrics:refresh failed: ' . $e->getMessage());
            log_message('error', 'service-metrics:refresh failed. error={error}', [
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $elapsedSeconds = microtime(true) - $start;
        CLI::write('service-metrics:refresh completed in ' . number_format($elapsedSeconds, 3) . 's', 'green');

        log_message('info', 'service-metrics:refresh completed. duration={duration}s', [
            'duration' => number_format($elapsedSeconds, 3),
        ]);
    }
}
