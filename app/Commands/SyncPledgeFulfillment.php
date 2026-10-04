<?php

namespace App\Commands;

use App\Services\PledgeFulfillmentService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SyncPledgeFulfillment extends BaseCommand
{
    protected $group       = 'Finance';
    protected $name        = 'pledge:sync-fulfillment';
    protected $description = 'Synchronizes annual pledge fulfilled_amount and status from member tithe donations.';
    protected $usage       = 'pledge:sync-fulfillment [--parish <parish_id>] [--year <year>] [--dry-run]';
    protected $options     = [
        '--parish'  => 'Specific parish ID to sync (defaults to all parishes).',
        '--year'    => 'Specific pledge year to sync (defaults to current year).',
        '--dry-run' => 'Simulate sync without persisting changes.',
    ];

    public function run(array $params)
    {
        $start = microtime(true);

        $parishIdOption = CLI::getOption('parish');
        $parishId       = $parishIdOption !== null && is_numeric($parishIdOption) ? (int) $parishIdOption : null;

        $yearOption     = CLI::getOption('year');
        $year           = $yearOption !== null && is_numeric($yearOption) ? (int) $yearOption : (int) date('Y');

        $dryRun         = CLI::getOption('dry-run') !== null;

        CLI::write('Starting pledge fulfillment sync' . ($dryRun ? ' [DRY RUN]' : '') . '...', 'yellow');
        CLI::write('Year: ' . $year . ($parishId ? ' | Parish: #' . $parishId : ' | Scope: All parishes'));

        /** @var PledgeFulfillmentService $service */
        $service = service('pledgeFulfillment');

        if ($parishId !== null && $parishId > 0) {
            $stats = $service->syncParishPledges($parishId, $year, $dryRun);
        } else {
            $stats = $service->syncAllPledges($year, $dryRun);
        }

        $elapsedSeconds = microtime(true) - $start;

        CLI::write('');
        CLI::write('----------------------------------------', 'white');
        CLI::write('Pledge Sync Summary' . ($dryRun ? ' (Simulated)' : ''), 'green');
        CLI::write('----------------------------------------', 'white');
        CLI::write('Total pledges evaluated: ' . $stats['total_checked']);
        CLI::write('Pledges updated:         ' . $stats['updated'], $stats['updated'] > 0 ? 'green' : 'white');
        CLI::write('Pledges unchanged:       ' . $stats['unchanged']);
        CLI::write('Execution time:          ' . number_format($elapsedSeconds, 3) . 's');
        CLI::write('----------------------------------------', 'white');
    }
}
