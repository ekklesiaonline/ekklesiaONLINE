<?php

namespace App\Commands;

use App\Models\ChartOfAccountModel;
use App\Models\DonationModel;
use App\Models\MemberParishAffiliationModel;
use App\Models\ServiceModel;
use App\Models\VolunteerScheduleModel;
use App\Models\VolunteerTeamModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class PerformanceBenchmark extends BaseCommand
{
    protected $group = 'Maintenance';
    protected $name = 'optimize:benchmark';
    protected $description = 'Runs Phase 5 performance baselines for representative DB and cache reads.';
    protected $usage = 'optimize:benchmark [--parish 1] [--start-date 2026-01-01] [--end-date 2026-12-31] [--enable-slow-log] [--slow-log-threshold-ms 200]';

    public function run(array $params)
    {
        $db = db_connect();
        $parishId = (int) (CLI::getOption('parish') ?? 0);
        $startDate = (string) (CLI::getOption('start-date') ?? date('Y-01-01'));
        $endDate = (string) (CLI::getOption('end-date') ?? date('Y-m-d'));
        $enableSlowLog = CLI::getOption('enable-slow-log') !== null;
        $slowLogThresholdMs = (float) (CLI::getOption('slow-log-threshold-ms') ?? 200);

        if ($parishId <= 0) {
            $parishId = $this->resolveBenchmarkParishId($db);
        }

        if ($parishId <= 0) {
            CLI::error('Unable to resolve a parish_id for benchmarking.');
            exit(1);
        }

        CLI::write('Phase 5 Performance Benchmark', 'cyan');
        CLI::write('-----------------------------', 'cyan');
        CLI::write('Parish ID: ' . $parishId . ' | Range: ' . $startDate . ' -> ' . $endDate, 'light_gray');
        CLI::newLine();

        if ($enableSlowLog) {
            $this->enableSlowQueryLog($db, $slowLogThresholdMs);
        }

        $this->printSlowLogStatus($db);
        CLI::newLine();
        CLI::write('Benchmark Results', 'light_gray');

        $memberModel = model(MemberParishAffiliationModel::class);
        $serviceModel = model(ServiceModel::class);
        $teamModel = model(VolunteerTeamModel::class);
        $scheduleModel = model(VolunteerScheduleModel::class);
        $chartModel = model(ChartOfAccountModel::class);
        $donationModel = model(DonationModel::class);
        $cache = cache();

        $results = [];
        $results[] = $this->measure('Members list (active, 50/page)', 500, static function () use ($memberModel, $parishId): array {
            $result = $memberModel->getParishMembersPaginated($parishId, 'active', 50, 1);
            return [
                'rows' => count($result['rows'] ?? []),
                'meta' => 'total=' . (int) ($result['total'] ?? 0),
            ];
        });
        $results[] = $this->measure('Services list (50/page)', 500, static function () use ($serviceModel, $parishId, $startDate, $endDate): array {
            $result = $serviceModel->getParishServicesPaginated($parishId, 50, 1, $startDate, $endDate);
            return [
                'rows' => count($result['rows'] ?? []),
                'meta' => 'total=' . (int) ($result['total'] ?? 0),
            ];
        });
        $results[] = $this->measure('Volunteer teams list (50/page)', 300, static function () use ($teamModel, $parishId): array {
            $result = $teamModel->getParishTeamsPaginated($parishId, 50, 1);
            return [
                'rows' => count($result['rows'] ?? []),
                'meta' => 'total=' . (int) ($result['total'] ?? 0),
            ];
        });
        $results[] = $this->measure('Volunteer schedules list (50/page)', 300, static function () use ($scheduleModel, $parishId): array {
            $result = $scheduleModel->getParishSchedulesPaginated($parishId, 50, 1);
            return [
                'rows' => count($result['rows'] ?? []),
                'meta' => 'total=' . (int) ($result['total'] ?? 0),
            ];
        });
        $results[] = $this->measure('Finance summary aggregate', 500, static function () use ($db, $parishId, $startDate, $endDate, $donationModel): array {
            $donations = $db->table('donation')
                ->select('COUNT(*) AS row_count')
                ->where('parish_id', $parishId)
                ->where('date >=', $startDate)
                ->where('date <=', $endDate)
                ->get()
                ->getRowArray() ?? [];

            $donationTotal = $donationModel->getTotalDonations($parishId, $startDate, $endDate);

            $expenses = $db->table('expense')
                ->select('COUNT(*) AS row_count, COALESCE(SUM(amount), 0) AS total_amount')
                ->where('parish_id', $parishId)
                ->where('date >=', $startDate)
                ->where('date <=', $endDate)
                ->get()
                ->getRowArray() ?? [];

            $services = $db->table('service')
                ->select('COUNT(*) AS row_count')
                ->where('parish_id', $parishId)
                ->where('date >=', $startDate)
                ->where('date <=', $endDate)
                ->get()
                ->getRowArray() ?? [];

            $attendance = $db->table('attendance a')
                ->select('COUNT(*) AS row_count')
                ->join('service s', 's.id = a.service_id', 'inner')
                ->where('s.parish_id', $parishId)
                ->where('s.service_date >=', $startDate)
                ->where('s.service_date <=', $endDate)
                ->where('a.attended', 1)
                ->get()
                ->getRowArray() ?? [];

            return [
                'rows' => (int) ($services['row_count'] ?? 0),
                'meta' => 'donations=' . (int) ($donations['row_count'] ?? 0)
                    . ', donation_total=' . number_format((float) $donationTotal, 2, '.', '')
                    . ', expenses=' . (int) ($expenses['row_count'] ?? 0)
                    . ', services=' . (int) ($services['row_count'] ?? 0)
                    . ', attendance=' . (int) ($attendance['row_count'] ?? 0),
            ];
        });

        $cache->delete('coa_active_type_expense');
        $results[] = $this->measure('Chart of accounts cache cold', 200, static function () use ($chartModel): array {
            $rows = $chartModel->getActiveByType('expense');
            return [
                'rows' => count($rows),
                'meta' => 'type=expense',
            ];
        });
        $results[] = $this->measure('Chart of accounts cache warm', 50, static function () use ($chartModel): array {
            $rows = $chartModel->getActiveByType('expense');
            return [
                'rows' => count($rows),
                'meta' => 'type=expense',
            ];
        });

        $warnings = 0;
        foreach ($results as $result) {
            $color = $result['status'] === 'PASS' ? 'green' : 'yellow';
            CLI::write(
                '- ' . $result['label']
                    . ': ' . number_format($result['duration_ms'], 2) . 'ms'
                    . ' (target <= ' . number_format($result['target_ms'], 2) . 'ms, ' . $result['status'] . ')'
                    . ' | rows=' . $result['rows']
                    . ($result['meta'] !== '' ? ' | ' . $result['meta'] : ''),
                $color
            );

            if ($result['status'] !== 'PASS') {
                $warnings++;
            }
        }

        CLI::newLine();
        if ($warnings === 0) {
            CLI::write('Benchmark result: GREEN (all measured paths within target).', 'green');
            return;
        }

        CLI::write('Benchmark result: ATTENTION (' . $warnings . ' measured path(s) above target).', 'yellow');
    }

    private function resolveBenchmarkParishId($db): int
    {
        $row = $db->table('parish')->select('id')->orderBy('id', 'ASC')->get(1)->getRowArray();
        return (int) ($row['id'] ?? 0);
    }

    private function enableSlowQueryLog($db, float $thresholdMs): void
    {
        $seconds = max(0.001, $thresholdMs / 1000);

        try {
            $db->query("SET GLOBAL slow_query_log = 'ON'");
            $db->query("SET GLOBAL log_output = 'FILE'");
            $db->query('SET GLOBAL long_query_time = ' . number_format($seconds, 3, '.', ''));
            CLI::write('Slow query log enabled for current DB server session.', 'green');
        } catch (\Throwable $e) {
            CLI::write('Slow query log could not be enabled automatically: ' . $e->getMessage(), 'yellow');
        }
    }

    private function printSlowLogStatus($db): void
    {
        CLI::write('Slow Query Logging', 'light_gray');

        $variables = [
            'slow_query_log',
            'long_query_time',
            'log_output',
            'slow_query_log_file',
        ];

        $status = [];
        foreach ($variables as $variable) {
            try {
                $row = $db->query("SHOW GLOBAL VARIABLES LIKE '" . $variable . "'")->getRowArray();
                $status[$variable] = (string) ($row['Value'] ?? $row['value'] ?? 'unavailable');
            } catch (\Throwable $e) {
                $status[$variable] = 'unavailable';
            }
        }

        CLI::write('- slow_query_log: ' . ($status['slow_query_log'] ?? 'unavailable'));
        CLI::write('- long_query_time: ' . ($status['long_query_time'] ?? 'unavailable'));
        CLI::write('- log_output: ' . ($status['log_output'] ?? 'unavailable'));
        CLI::write('- slow_query_log_file: ' . ($status['slow_query_log_file'] ?? 'unavailable'));
        CLI::write('- app monitoring.slowQueryMs: ' . (string) (env('monitoring.slowQueryMs') ?? '100'));
        CLI::write('- app monitoring.slowRequestMs: ' . (string) (env('monitoring.slowRequestMs') ?? '800'));
    }

    /**
     * @return array{label: string, duration_ms: float, target_ms: float, status: string, rows: int, meta: string}
     */
    private function measure(string $label, float $targetMs, callable $callback): array
    {
        $startedAt = microtime(true);
        $result = $callback();
        $durationMs = (microtime(true) - $startedAt) * 1000;

        return [
            'label' => $label,
            'duration_ms' => $durationMs,
            'target_ms' => $targetMs,
            'status' => $durationMs <= $targetMs ? 'PASS' : 'WARN',
            'rows' => (int) ($result['rows'] ?? 0),
            'meta' => (string) ($result['meta'] ?? ''),
        ];
    }
}
