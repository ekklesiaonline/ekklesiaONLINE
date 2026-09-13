<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class RefreshGoodStanding extends BaseCommand
{
    protected $group = 'Maintenance';
    protected $name = 'standing:refresh';
    protected $description = 'Refreshes member_standing_summary snapshots for a year.';
    protected $usage = 'standing:refresh [--year 2026]';

    public function run(array $params)
    {
        $start = microtime(true);
        $year = (int) (CLI::getOption('year') ?? date('Y'));

        try {
            /** @var \App\Models\MemberStandingSummaryModel $standingSummaryModel */
            $standingSummaryModel = model(\App\Models\MemberStandingSummaryModel::class);
            $inserted = $standingSummaryModel->refreshForYear($year);
        } catch (\Throwable $e) {
            CLI::error('Failed to refresh standing summary: ' . $e->getMessage());
            log_message('error', 'standing:refresh failed for year={year}. error={error}', [
                'year' => $year,
                'error' => $e->getMessage(),
            ]);
            return;
        }

        $elapsedSeconds = microtime(true) - $start;
        CLI::write('Completed. Year: ' . $year . ', Rows inserted: ' . $inserted . '.', 'green');
        CLI::write('Execution time: ' . number_format($elapsedSeconds, 3) . 's', 'yellow');

        log_message('info', 'standing:refresh completed. year={year} rows={rows} duration={duration}s', [
            'year' => $year,
            'rows' => $inserted,
            'duration' => number_format($elapsedSeconds, 3),
        ]);
    }
}
