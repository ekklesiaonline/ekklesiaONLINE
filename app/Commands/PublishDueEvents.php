<?php

namespace App\Commands;

use App\Services\EventPublishService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Publishes draft events whose publish_at is due.
 *
 * Replaces the lazy-publish calls that lived inside GET calendar/index
 * handlers (a state mutation triggered by reads). Schedule this command
 * via cron, e.g. every 5 minutes:
 *   php spark events:publish-due
 */
class PublishDueEvents extends BaseCommand
{
    protected $group = 'Maintenance';
    protected $name = 'events:publish-due';
    protected $description = 'Publishes draft events whose scheduled publish_at time has passed.';
    protected $usage = 'events:publish-due';

    public function run(array $params)
    {
        try {
            EventPublishService::publishDueDraftEvents();
        } catch (\Throwable $e) {
            CLI::error('Failed to publish due draft events: ' . $e->getMessage());
            log_message('error', 'events:publish-due failed. error={error}', [
                'error' => $e->getMessage(),
            ]);
            return;
        }

        CLI::write('Completed. Due draft events published.', 'green');
        log_message('info', 'events:publish-due completed.');
    }
}
