<?php

namespace App\Commands;

use App\Services\AccountingJournalSyncService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SyncJournalHistory extends BaseCommand
{
    protected $group       = 'Finance';
    protected $name        = 'finance:sync-journal-history';
    protected $description = 'Synchronizes historical donations and expenses into general ledger double-entry journal entries.';
    protected $usage       = 'finance:sync-journal-history [--parish <parish_id>] [--year <year>] [--dry-run]';
    protected $options     = [
        '--parish'  => 'Specific parish ID to backfill (defaults to all parishes).',
        '--year'    => 'Specific transaction year to backfill (defaults to all years).',
        '--dry-run' => 'Simulate backfill without persisting changes.',
    ];

    public function run(array $params)
    {
        $start = microtime(true);

        $parishIdOption = CLI::getOption('parish');
        $parishId       = $parishIdOption !== null && is_numeric($parishIdOption) ? (int) $parishIdOption : null;

        $yearOption     = CLI::getOption('year');
        $year           = $yearOption !== null && is_numeric($yearOption) ? (int) $yearOption : null;

        $dryRun         = CLI::getOption('dry-run') !== null;

        CLI::write('Starting accounting journal history sync' . ($dryRun ? ' [DRY RUN]' : '') . '...', 'yellow');
        CLI::write('Year: ' . ($year ? (string) $year : 'All years') . ($parishId ? ' | Parish: #' . $parishId : ' | Scope: All parishes'));

        $service = new AccountingJournalSyncService();
        $stats   = $service->backfillHistory($parishId, $year, $dryRun);

        $elapsedSeconds = microtime(true) - $start;

        CLI::write('');
        CLI::write('----------------------------------------', 'white');
        CLI::write('Journal Sync Summary' . ($dryRun ? ' (Simulated)' : ''), 'green');
        CLI::write('----------------------------------------', 'white');
        CLI::write('Donations evaluated: ' . $stats['donations_evaluated']);
        CLI::write('Donations created:   ' . $stats['donations_created'], $stats['donations_created'] > 0 ? 'green' : 'white');
        CLI::write('Donations skipped:   ' . $stats['donations_skipped']);
        CLI::write('Expenses evaluated:  ' . $stats['expenses_evaluated']);
        CLI::write('Expenses created:    ' . $stats['expenses_created'], $stats['expenses_created'] > 0 ? 'green' : 'white');
        CLI::write('Expenses skipped:    ' . $stats['expenses_skipped']);
        CLI::write('Total JE created:    ' . $stats['total_created'], $stats['total_created'] > 0 ? 'green' : 'white');
        CLI::write('Execution time:      ' . number_format($elapsedSeconds, 3) . 's');
        CLI::write('----------------------------------------', 'white');
    }
}
