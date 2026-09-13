<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SyncDiocesanAttendanceAggregates extends BaseCommand
{
    protected $group = 'Maintenance';
    protected $name = 'attendance:sync-diocesan-aggregates';
    protected $description = 'Builds diocesan monthly Sunday and weekday attendance aggregates for dashboard reads.';
    protected $usage = 'attendance:sync-diocesan-aggregates [--from 2026-01-01] [--to 2026-12-31] [--rolling-days 35] [--diocese 2] [--parish 9]';

    public function run(array $params)
    {
        $db = db_connect();

        if (!$db->tableExists('diocesan_attendance_monthly')) {
            CLI::error('Table diocesan_attendance_monthly does not exist. Run migrations first.');
            return;
        }

        $fromOpt = (string) (CLI::getOption('from') ?? '');
        $toOpt = (string) (CLI::getOption('to') ?? '');
        $rollingDays = (int) (CLI::getOption('rolling-days') ?? 35);
        $dioceseId = (int) (CLI::getOption('diocese') ?? 0);
        $parishId = (int) (CLI::getOption('parish') ?? 0);

        [$fromDate, $toDate] = $this->resolveDateRange($fromOpt, $toOpt, $rollingDays);
        if ($fromDate === null || $toDate === null) {
            CLI::error('Invalid date range. Use --from YYYY-MM-DD and --to YYYY-MM-DD.');
            return;
        }

        if ($fromDate > $toDate) {
            CLI::error('--from cannot be later than --to.');
            return;
        }

        $scope = $this->resolveScopeParishIds($db, $parishId, $dioceseId);
        $scopeParishIds = $scope['parish_ids'];
        $scopeDioceseIds = $scope['diocese_ids'];

        if ($scopeParishIds === []) {
            CLI::write('No parishes found for requested scope. Nothing to sync.', 'yellow');
            return;
        }

        $syncStartedAt = date('Y-m-d H:i:s');
        $fromMonth = date('Y-m-01', strtotime($fromDate));
        $toMonth = date('Y-m-01', strtotime($toDate));

        CLI::write('Sync diocesan attendance aggregates', 'cyan');
        CLI::write('Range: ' . $fromDate . ' -> ' . $toDate . ' (month window ' . $fromMonth . ' -> ' . $toMonth . ')');
        CLI::write('Scope parishes: ' . count($scopeParishIds));

        $rows = $this->buildAggregateRows($db, $fromDate, $toDate, $scopeParishIds, $scopeDioceseIds);

        $db->transStart();

        $deleteBuilder = $db->table('diocesan_attendance_monthly')
            ->where('period_month >=', $fromMonth)
            ->where('period_month <=', $toMonth);

        if ($scopeParishIds !== []) {
            $deleteBuilder->whereIn('parish_id', $scopeParishIds);
        }
        if ($scopeDioceseIds !== []) {
            $deleteBuilder->whereIn('diocese_id', $scopeDioceseIds);
        }

        $deleteBuilder->delete();

        $upsertSql = 'INSERT INTO diocesan_attendance_monthly '
            . '(diocese_id, parish_id, period_month, sunday_services_count, sunday_attended_total, sunday_attendance_average, weekday_services_count, weekday_attended_total, weekday_attendance_average, source_last_service_date, generated_at, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) '
            . 'ON DUPLICATE KEY UPDATE '
            . 'sunday_services_count = VALUES(sunday_services_count), '
            . 'sunday_attended_total = VALUES(sunday_attended_total), '
            . 'sunday_attendance_average = VALUES(sunday_attendance_average), '
            . 'weekday_services_count = VALUES(weekday_services_count), '
            . 'weekday_attended_total = VALUES(weekday_attended_total), '
            . 'weekday_attendance_average = VALUES(weekday_attendance_average), '
            . 'source_last_service_date = VALUES(source_last_service_date), '
            . 'generated_at = VALUES(generated_at), '
            . 'updated_at = VALUES(updated_at)';

        $inserted = 0;
        foreach ($rows as $row) {
            $db->query($upsertSql, [
                (int) $row['diocese_id'],
                (int) $row['parish_id'],
                (string) $row['period_month'],
                (int) $row['sunday_services_count'],
                (int) $row['sunday_attended_total'],
                (float) $row['sunday_attendance_average'],
                (int) $row['weekday_services_count'],
                (int) $row['weekday_attended_total'],
                (float) $row['weekday_attendance_average'],
                ($row['source_last_service_date'] ?? '') !== '' ? (string) $row['source_last_service_date'] : null,
                $syncStartedAt,
                $syncStartedAt,
                $syncStartedAt,
            ]);
            $inserted++;
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            CLI::error('Sync failed. Transaction rolled back.');
            return;
        }

        CLI::write('Done. Upserted rows: ' . $inserted, 'green');
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveDateRange(string $fromOpt, string $toOpt, int $rollingDays): array
    {
        $fromOpt = trim($fromOpt);
        $toOpt = trim($toOpt);

        if ($fromOpt !== '' || $toOpt !== '') {
            if (!$this->isValidDate($fromOpt) || !$this->isValidDate($toOpt)) {
                return [null, null];
            }

            return [$fromOpt, $toOpt];
        }

        if ($rollingDays < 1) {
            $rollingDays = 1;
        }

        $toDate = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime('-' . ($rollingDays - 1) . ' days'));

        return [$fromDate, $toDate];
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
     * @return array{parish_ids: int[], diocese_ids: int[]}
     */
    private function resolveScopeParishIds($db, int $parishId, int $dioceseId): array
    {
        if ($parishId > 0) {
            $row = $db->table('parish')
                ->select('id, diocese_id')
                ->where('id', $parishId)
                ->get()
                ->getRowArray();

            if (empty($row)) {
                return ['parish_ids' => [], 'diocese_ids' => []];
            }

            return [
                'parish_ids' => [(int) $row['id']],
                'diocese_ids' => !empty($row['diocese_id']) ? [(int) $row['diocese_id']] : [],
            ];
        }

        $builder = $db->table('parish')->select('id, diocese_id');
        if ($dioceseId > 0) {
            $builder->where('diocese_id', $dioceseId);
        }

        $rows = $builder->get()->getResultArray();

        $parishIds = [];
        $dioceseIds = [];

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $parishIds[] = $id;
            }

            $dId = (int) ($row['diocese_id'] ?? 0);
            if ($dId > 0) {
                $dioceseIds[] = $dId;
            }
        }

        $parishIds = array_values(array_unique($parishIds));
        $dioceseIds = array_values(array_unique($dioceseIds));

        return [
            'parish_ids' => $parishIds,
            'diocese_ids' => $dioceseIds,
        ];
    }

    /**
     * @param int[] $parishIds
     * @param int[] $dioceseIds
     * @return array<int, array<string, int|float|string>>
     */
    private function buildAggregateRows($db, string $fromDate, string $toDate, array $parishIds, array $dioceseIds): array
    {
        $attendanceSubquery = $db->table('attendance')
            ->select('service_id, SUM(CASE WHEN attended = 1 THEN 1 ELSE 0 END) AS attended_count', false)
            ->groupBy('service_id')
            ->getCompiledSelect();

        $builder = $db->table('service s')
            ->select("c.diocese_id AS diocese_id, s.parish_id AS parish_id, DATE_FORMAT(s.service_date, '%Y-%m-01') AS period_month, 
                COALESCE(SUM(CASE WHEN DAYOFWEEK(s.service_date)=1 THEN 1 ELSE 0 END), 0) AS sunday_services_count,
                COALESCE(SUM(CASE WHEN DAYOFWEEK(s.service_date)=1 THEN COALESCE(a.attended_count, 0) ELSE 0 END), 0) AS sunday_attended_total,
                ROUND(COALESCE(
                    SUM(CASE WHEN DAYOFWEEK(s.service_date)=1 THEN COALESCE(a.attended_count, 0) ELSE 0 END) /
                    NULLIF(SUM(CASE WHEN DAYOFWEEK(s.service_date)=1 THEN 1 ELSE 0 END), 0),
                    0
                ), 2) AS sunday_attendance_average,
                COALESCE(SUM(CASE WHEN DAYOFWEEK(s.service_date) BETWEEN 2 AND 6 THEN 1 ELSE 0 END), 0) AS weekday_services_count,
                COALESCE(SUM(CASE WHEN DAYOFWEEK(s.service_date) BETWEEN 2 AND 6 THEN COALESCE(a.attended_count, 0) ELSE 0 END), 0) AS weekday_attended_total,
                ROUND(COALESCE(
                    SUM(CASE WHEN DAYOFWEEK(s.service_date) BETWEEN 2 AND 6 THEN COALESCE(a.attended_count, 0) ELSE 0 END) /
                    NULLIF(SUM(CASE WHEN DAYOFWEEK(s.service_date) BETWEEN 2 AND 6 THEN 1 ELSE 0 END), 0),
                    0
                ), 2) AS weekday_attendance_average,
                MAX(s.service_date) AS source_last_service_date", false)
            ->join('parish c', 'c.id = s.parish_id', 'inner')
            ->join('(' . $attendanceSubquery . ') a', 'a.service_id = s.id', 'left')
            ->where('c.diocese_id IS NOT NULL', null, false)
            ->where('s.service_date >=', $fromDate)
            ->where('s.service_date <=', $toDate)
            ->groupBy('c.diocese_id')
            ->groupBy('s.parish_id')
            ->groupBy("DATE_FORMAT(s.service_date, '%Y-%m-01')", false)
            ->orderBy('period_month', 'ASC')
            ->orderBy('parish_id', 'ASC');

        if ($parishIds !== []) {
            $builder->whereIn('s.parish_id', $parishIds);
        }

        if ($dioceseIds !== []) {
            $builder->whereIn('c.diocese_id', $dioceseIds);
        }

        $rows = $builder->get()->getResultArray();

        if ($rows === []) {
            return [];
        }

        return array_map(static function (array $row): array {
            return [
                'diocese_id' => (int) ($row['diocese_id'] ?? 0),
                'parish_id' => (int) ($row['parish_id'] ?? 0),
                'period_month' => (string) ($row['period_month'] ?? ''),
                'sunday_services_count' => (int) ($row['sunday_services_count'] ?? 0),
                'sunday_attended_total' => (int) ($row['sunday_attended_total'] ?? 0),
                'sunday_attendance_average' => (float) ($row['sunday_attendance_average'] ?? 0),
                'weekday_services_count' => (int) ($row['weekday_services_count'] ?? 0),
                'weekday_attended_total' => (int) ($row['weekday_attended_total'] ?? 0),
                'weekday_attendance_average' => (float) ($row['weekday_attendance_average'] ?? 0),
                'source_last_service_date' => (string) ($row['source_last_service_date'] ?? null),
            ];
        }, $rows);
    }
}
