<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SyncDiocesanSundayServiceMetrics extends BaseCommand
{
    protected $group = 'Maintenance';
    protected $name = 'attendance:sync-sunday-service-metrics';
    protected $description = 'Builds Sunday service-level attendance metrics (present/communion) from service_metric for dASA calculations.';
    protected $usage = 'attendance:sync-sunday-service-metrics [--from 2026-01-01] [--to 2026-12-31] [--rolling-days 35] [--diocese 2] [--parish 9] [--rebuild-period-buckets]';

    public function run(array $params)
    {
        $db = db_connect();

        if (!$db->tableExists('diocesan_sunday_service_metrics')) {
            CLI::error('Table diocesan_sunday_service_metrics does not exist. Run migrations first.');
            return;
        }

        $fromOpt = trim((string) ($this->optionValue('from', $params) ?? ''));
        $toOpt = trim((string) ($this->optionValue('to', $params) ?? ''));
        $rollingDays = (int) ($this->optionValue('rolling-days', $params) ?? 0);
        $dioceseId = (int) ($this->optionValue('diocese', $params) ?? 0);
        $parishId = (int) ($this->optionValue('parish', $params) ?? 0);
        $rebuildPeriodBuckets = filter_var($this->optionValue('rebuild-period-buckets', $params) ?? false, FILTER_VALIDATE_BOOLEAN);

        [$fromDate, $toDate] = $this->resolveDateRange($fromOpt, $toOpt, $rollingDays);
        if ($fromDate === null || $toDate === null) {
            CLI::error('Invalid date range. Use --from YYYY-MM-DD and --to YYYY-MM-DD.');
            return;
        }

        if ($fromDate > $toDate) {
            CLI::error('--from cannot be later than --to.');
            return;
        }

        if ($rebuildPeriodBuckets) {
            if ($parishId > 0) {
                CLI::error('--rebuild-period-buckets cannot be combined with --parish because period buckets are diocesan totals.');
                return;
            }

            if (!$db->tableExists('diocesan_sunday_weekly_metrics') || !$db->tableExists('diocesan_sunday_monthly_metrics')) {
                CLI::error('Period bucket tables do not exist. Run migrations first.');
                return;
            }

            CLI::write('Rebuild diocesan weekly/monthly period buckets', 'cyan');
            CLI::write('Range: ' . $fromDate . ' -> ' . $toDate);
            if ($dioceseId > 0) {
                CLI::write('Scope diocese: ' . $dioceseId);
            }

            $result = $this->rebuildPeriodBucketsFromServiceAggregate($db, $fromDate, $toDate, $dioceseId, date('Y-m-d H:i:s'));
            if (!$result['ok']) {
                CLI::error('Period bucket rebuild failed.');
                return;
            }

            CLI::write('Upserted weekly buckets: ' . $result['weekly_count'], 'green');
            CLI::write('Upserted monthly buckets: ' . $result['monthly_count'], 'green');
            CLI::write('Done. Rebuilt period buckets only.', 'green');
            return;
        }

        $scope = $this->resolveScopeParishIds($db, $parishId, $dioceseId);
        $scopeParishIds = $scope['parish_ids'];

        if ($scopeParishIds === []) {
            CLI::write('No parishes found for requested scope. Nothing to sync.', 'yellow');
            return;
        }

        CLI::write('Sync diocesan Sunday service metrics', 'cyan');
        CLI::write('Range: ' . $fromDate . ' -> ' . $toDate);
        CLI::write('Scope parishes: ' . count($scopeParishIds));

        $builder = $db->table('service s')
            ->select('c.diocese_id, s.parish_id, s.id AS service_id, s.service_date AS service_date, COALESCE(sm.reported_present_count, 0) AS present_count, COALESCE(sm.reported_communion_count, 0) AS communion_count', false)
            ->join('parish c', 'c.id = s.parish_id', 'inner')
            ->join('service_metric sm', 'sm.service_id = s.id', 'left')
            ->where('c.diocese_id IS NOT NULL', null, false)
            ->where('s.service_date >=', $fromDate)
            ->where('s.service_date <=', $toDate)
            ->where('DAYOFWEEK(s.service_date)', 1)
            ->whereIn('s.parish_id', $scopeParishIds)
            ->orderBy('s.service_date', 'ASC')
            ->orderBy('s.id', 'ASC');

        $rows = $builder->get()->getResultArray();
        if ($rows === []) {
            CLI::write('No Sunday services found for requested scope/range.', 'yellow');
            return;
        }

        $syncStartedAt = date('Y-m-d H:i:s');
        $upsertSql = 'INSERT INTO diocesan_sunday_service_metrics '
            . '(diocese_id, parish_id, service_id, service_date, present_count, communion_count, generated_at, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) '
            . 'ON DUPLICATE KEY UPDATE '
            . 'diocese_id = VALUES(diocese_id), '
            . 'parish_id = VALUES(parish_id), '
            . 'service_date = VALUES(service_date), '
            . 'present_count = VALUES(present_count), '
            . 'communion_count = VALUES(communion_count), '
            . 'generated_at = VALUES(generated_at), '
            . 'updated_at = VALUES(updated_at)';

        $db->transStart();

        foreach ($rows as $row) {
            $db->query($upsertSql, [
                (int) ($row['diocese_id'] ?? 0),
                (int) ($row['parish_id'] ?? 0),
                (int) ($row['service_id'] ?? 0),
                (string) ($row['service_date'] ?? ''),
                (int) ($row['present_count'] ?? 0),
                (int) ($row['communion_count'] ?? 0),
                $syncStartedAt,
                $syncStartedAt,
                $syncStartedAt,
            ]);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            CLI::error('Sync failed. Transaction rolled back.');
            return;
        }

        // Build and persist diocesan weekly/monthly buckets for fast chart reads.
        // Skip when syncing a single parish to avoid partial overwrite of diocesan totals.
        if ($parishId > 0) {
            CLI::write('Skipped weekly/monthly diocesan aggregate refresh due to --parish scope.', 'yellow');
        } elseif ($db->tableExists('diocesan_sunday_weekly_metrics') && $db->tableExists('diocesan_sunday_monthly_metrics')) {
            $result = $this->rebuildPeriodBucketsFromServiceAggregate($db, $fromDate, $toDate, $dioceseId, $syncStartedAt);
            if (!$result['ok']) {
                CLI::error('Weekly/monthly diocesan aggregate upsert failed.');
                return;
            }

            CLI::write('Upserted weekly buckets: ' . $result['weekly_count'], 'green');
            CLI::write('Upserted monthly buckets: ' . $result['monthly_count'], 'green');
        }

        CLI::write('Done. Upserted rows: ' . count($rows), 'green');
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveDateRange(string $fromOpt, string $toOpt, int $rollingDays): array
    {
        if ($fromOpt !== '' || $toOpt !== '') {
            if (!$this->isValidDate($fromOpt) || !$this->isValidDate($toOpt)) {
                return [null, null];
            }

            return [$fromOpt, $toOpt];
        }

        if ($rollingDays > 0) {
            $toDate = date('Y-m-d');
            $fromDate = date('Y-m-d', strtotime('-' . ($rollingDays - 1) . ' days'));

            return [$fromDate, $toDate];
        }

        // Default cron window requested by product: Jan 1 -> today.
        return [date('Y') . '-01-01', date('Y-m-d')];
    }

    private function isValidDate(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $dt !== false && $dt->format('Y-m-d') === $value;
    }

    /**
     * Resolve an option from command params first, then fallback to CLI options.
     *
     * @param array<int|string, mixed> $params
     * @return mixed
     */
    private function optionValue(string $name, array $params)
    {
        $needle = '--' . $name;

        foreach ($params as $index => $param) {
            if (!is_string($param)) {
                continue;
            }

            if (str_starts_with($param, $needle . '=')) {
                return substr($param, strlen($needle . '='));
            }

            if ($param === $needle) {
                $next = $params[$index + 1] ?? true;
                if (is_string($next) && !str_starts_with($next, '--')) {
                    return $next;
                }

                return true;
            }
        }

        return CLI::getOption($name);
    }

    /**
     * @return array{parish_ids: int[]}
     */
    private function resolveScopeParishIds($db, int $parishId, int $dioceseId): array
    {
        if ($parishId > 0) {
            $row = $db->table('parish')
                ->select('id')
                ->where('id', $parishId)
                ->get()
                ->getRowArray();

            return empty($row) ? ['parish_ids' => []] : ['parish_ids' => [(int) $row['id']]];
        }

        $builder = $db->table('parish')->select('id');
        if ($dioceseId > 0) {
            $builder->where('diocese_id', $dioceseId);
        }

        $rows = $builder->get()->getResultArray();
        $parishIds = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $parishIds[] = $id;
            }
        }

        return ['parish_ids' => array_values(array_unique($parishIds))];
    }

    /**
     * Rebuild weekly/monthly diocesan period buckets directly from diocesan_sunday_service_metrics.
     *
     * @return array{ok: bool, weekly_count: int, monthly_count: int}
     */
    private function rebuildPeriodBucketsFromServiceAggregate($db, string $fromDate, string $toDate, int $dioceseId, string $generatedAt): array
    {
        $weeklyFrom = $this->alignToWeekStart($fromDate);
        $weeklyTo = $this->alignToWeekStart($toDate);
        $monthlyFrom = date('Y-m-01', strtotime($fromDate));
        $monthlyTo = date('Y-m-01', strtotime($toDate));

        $weeklyDelete = $db->table('diocesan_sunday_weekly_metrics')
            ->where('period_start >=', $weeklyFrom)
            ->where('period_start <=', $weeklyTo);
        $monthlyDelete = $db->table('diocesan_sunday_monthly_metrics')
            ->where('period_month >=', $monthlyFrom)
            ->where('period_month <=', $monthlyTo);

        if ($dioceseId > 0) {
            $weeklyDelete->where('diocese_id', $dioceseId);
            $monthlyDelete->where('diocese_id', $dioceseId);
        }

        $weeklyDelete->delete();
        $monthlyDelete->delete();

        $weeklySql = "
            SELECT diocese_id,
                   DATE_SUB(service_date, INTERVAL WEEKDAY(service_date) DAY) AS period_start,
                   COUNT(*) AS sunday_services_count,
                   COALESCE(SUM(present_count), 0) AS sunday_attended_total,
                   COALESCE(SUM(communion_count), 0) AS sunday_communion_total
            FROM diocesan_sunday_service_metrics
            WHERE service_date >= ?
              AND service_date <= ?
              " . ($dioceseId > 0 ? " AND diocese_id = ?" : "") . "
            GROUP BY diocese_id, DATE_SUB(service_date, INTERVAL WEEKDAY(service_date) DAY)
            ORDER BY period_start ASC
        ";
        $weeklyParams = $dioceseId > 0 ? [$fromDate, $toDate, $dioceseId] : [$fromDate, $toDate];
        $weeklyRows = $db->query($weeklySql, $weeklyParams)->getResultArray();

        $monthlySql = "
            SELECT diocese_id,
                   DATE_FORMAT(service_date, '%Y-%m-01') AS period_month,
                   COUNT(*) AS sunday_services_count,
                   COALESCE(SUM(present_count), 0) AS sunday_attended_total,
                   COALESCE(SUM(communion_count), 0) AS sunday_communion_total
            FROM diocesan_sunday_service_metrics
            WHERE service_date >= ?
              AND service_date <= ?
              " . ($dioceseId > 0 ? " AND diocese_id = ?" : "") . "
            GROUP BY diocese_id, DATE_FORMAT(service_date, '%Y-%m-01')
            ORDER BY period_month ASC
        ";
        $monthlyParams = $dioceseId > 0 ? [$fromDate, $toDate, $dioceseId] : [$fromDate, $toDate];
        $monthlyRows = $db->query($monthlySql, $monthlyParams)->getResultArray();

        $weeklyUpsertSql = 'INSERT INTO diocesan_sunday_weekly_metrics '
            . '(diocese_id, period_start, sunday_services_count, sunday_attended_total, sunday_communion_total, generated_at, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?) '
            . 'ON DUPLICATE KEY UPDATE '
            . 'sunday_services_count = VALUES(sunday_services_count), '
            . 'sunday_attended_total = VALUES(sunday_attended_total), '
            . 'sunday_communion_total = VALUES(sunday_communion_total), '
            . 'generated_at = VALUES(generated_at), '
            . 'updated_at = VALUES(updated_at)';

        $monthlyUpsertSql = 'INSERT INTO diocesan_sunday_monthly_metrics '
            . '(diocese_id, period_month, sunday_services_count, sunday_attended_total, sunday_communion_total, generated_at, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?) '
            . 'ON DUPLICATE KEY UPDATE '
            . 'sunday_services_count = VALUES(sunday_services_count), '
            . 'sunday_attended_total = VALUES(sunday_attended_total), '
            . 'sunday_communion_total = VALUES(sunday_communion_total), '
            . 'generated_at = VALUES(generated_at), '
            . 'updated_at = VALUES(updated_at)';

        $db->transStart();

        foreach ($weeklyRows as $row) {
            $db->query($weeklyUpsertSql, [
                (int) ($row['diocese_id'] ?? 0),
                (string) ($row['period_start'] ?? ''),
                (int) ($row['sunday_services_count'] ?? 0),
                (int) ($row['sunday_attended_total'] ?? 0),
                (int) ($row['sunday_communion_total'] ?? 0),
                $generatedAt,
                $generatedAt,
                $generatedAt,
            ]);
        }

        foreach ($monthlyRows as $row) {
            $db->query($monthlyUpsertSql, [
                (int) ($row['diocese_id'] ?? 0),
                (string) ($row['period_month'] ?? ''),
                (int) ($row['sunday_services_count'] ?? 0),
                (int) ($row['sunday_attended_total'] ?? 0),
                (int) ($row['sunday_communion_total'] ?? 0),
                $generatedAt,
                $generatedAt,
                $generatedAt,
            ]);
        }

        $db->transComplete();

        return [
            'ok' => $db->transStatus() !== false,
            'weekly_count' => count($weeklyRows),
            'monthly_count' => count($monthlyRows),
        ];
    }

    private function alignToWeekStart(string $ymd): string
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $ymd);
        if (!$date) {
            return $ymd;
        }

        $offset = (int) $date->format('N') - 1;
        return $date->modify('-' . $offset . ' days')->format('Y-m-d');
    }
}
