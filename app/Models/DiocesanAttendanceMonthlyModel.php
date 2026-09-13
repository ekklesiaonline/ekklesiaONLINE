<?php

namespace App\Models;

use CodeIgniter\Model;

class DiocesanAttendanceMonthlyModel extends Model
{
    protected $table            = 'diocesan_attendance_monthly';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'diocese_id',
        'parish_id',
        'period_month',
        'sunday_services_count',
        'sunday_attended_total',
        'sunday_attendance_average',
        'weekday_services_count',
        'weekday_attended_total',
        'weekday_attendance_average',
        'source_last_service_date',
        'generated_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Aggregate diocesan Sunday average for a period preset.
     *
     * Supported presets:
     * - this_month
     * - this_year
     * - last_month
     * - last_year
     * - custom_range (requires $customStart/$customEnd as YYYY-MM-DD)
     */
    public function getDiocesanSundayAverageByPeriod(
        int $dioceseId,
        string $period,
        ?string $customStart = null,
        ?string $customEnd = null
    ): array {
        $range = $this->resolvePeriodRange($period, $customStart, $customEnd);
        if ($range === null) {
            return [
                'sunday_services_count' => 0,
                'sunday_attended_total' => 0,
                'sunday_attendance_average' => 0.0,
                'period_start_month' => null,
                'period_end_month' => null,
            ];
        }

        $sql = '
            SELECT
                COALESCE(SUM(sunday_services_count), 0) AS sunday_services_count,
                COALESCE(SUM(sunday_attended_total), 0) AS sunday_attended_total,
                ROUND(
                    COALESCE(
                        SUM(sunday_attended_total) / NULLIF(SUM(sunday_services_count), 0),
                        0
                    ),
                    2
                ) AS sunday_attendance_average,
                COALESCE(SUM(weekday_services_count), 0) AS weekday_services_count,
                COALESCE(SUM(weekday_attended_total), 0) AS weekday_attended_total,
                ROUND(
                    COALESCE(
                        SUM(weekday_attended_total) / NULLIF(SUM(weekday_services_count), 0),
                        0
                    ),
                    2
                ) AS weekday_attendance_average
            FROM diocesan_attendance_monthly
            WHERE diocese_id = ?
              AND period_month >= ?
              AND period_month <= ?
        ';

        $row = $this->db->query($sql, [
            $dioceseId,
            $range['start_month'],
            $range['end_month'],
        ])->getRowArray() ?? [];

        return [
            'sunday_services_count' => (int) ($row['sunday_services_count'] ?? 0),
            'sunday_attended_total' => (int) ($row['sunday_attended_total'] ?? 0),
            'sunday_attendance_average' => (float) ($row['sunday_attendance_average'] ?? 0),
            'weekday_services_count' => (int) ($row['weekday_services_count'] ?? 0),
            'weekday_attended_total' => (int) ($row['weekday_attended_total'] ?? 0),
            'weekday_attendance_average' => (float) ($row['weekday_attendance_average'] ?? 0),
            'period_start_month' => $range['start_month'],
            'period_end_month' => $range['end_month'],
        ];
    }

    /**
     * Per-parish Sunday averages inside a diocese for a period preset.
     *
     * Returns rows sorted by parish name, useful for diocesan comparison lists.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getParishSundayAveragesByPeriod(
        int $dioceseId,
        string $period,
        ?string $customStart = null,
        ?string $customEnd = null
    ): array {
        $range = $this->resolvePeriodRange($period, $customStart, $customEnd);
        if ($range === null) {
            return [];
        }

        $sql = '
            SELECT
                dam.parish_id,
                c.name AS parish_name,
                COALESCE(SUM(dam.sunday_services_count), 0) AS sunday_services_count,
                COALESCE(SUM(dam.sunday_attended_total), 0) AS sunday_attended_total,
                ROUND(
                    COALESCE(
                        SUM(dam.sunday_attended_total) / NULLIF(SUM(dam.sunday_services_count), 0),
                        0
                    ),
                    2
                ) AS sunday_attendance_average
            FROM diocesan_attendance_monthly dam
                        JOIN parish c ON c.id = dam.parish_id
            WHERE dam.diocese_id = ?
              AND dam.period_month >= ?
              AND dam.period_month <= ?
            GROUP BY dam.parish_id, c.name
            ORDER BY c.name ASC
        ';

        return $this->db->query($sql, [
            $dioceseId,
            $range['start_month'],
            $range['end_month'],
        ])->getResultArray();
    }

    /**
     * Monthly trend series for diocesan Sunday averages in a period preset.
     *
     * @return array<int, array{period_month: string, sunday_services_count: int, sunday_attended_total: int, sunday_attendance_average: float}>
     */
    public function getMonthlySeriesByPeriod(
        int $dioceseId,
        string $period,
        ?string $customStart = null,
        ?string $customEnd = null
    ): array {
        $range = $this->resolvePeriodRange($period, $customStart, $customEnd);
        if ($range === null) {
            return [];
        }

        $sql = '
            SELECT
                period_month,
                COALESCE(SUM(sunday_services_count), 0) AS sunday_services_count,
                COALESCE(SUM(sunday_attended_total), 0) AS sunday_attended_total,
                ROUND(
                    COALESCE(
                        SUM(sunday_attended_total) / NULLIF(SUM(sunday_services_count), 0),
                        0
                    ),
                    2
                ) AS sunday_attendance_average,
                COALESCE(SUM(weekday_services_count), 0) AS weekday_services_count,
                COALESCE(SUM(weekday_attended_total), 0) AS weekday_attended_total,
                ROUND(
                    COALESCE(
                        SUM(weekday_attended_total) / NULLIF(SUM(weekday_services_count), 0),
                        0
                    ),
                    2
                ) AS weekday_attendance_average
            FROM diocesan_attendance_monthly
            WHERE diocese_id = ?
              AND period_month >= ?
              AND period_month <= ?
            GROUP BY period_month
            ORDER BY period_month ASC
        ';

        $rows = $this->db->query($sql, [
            $dioceseId,
            $range['start_month'],
            $range['end_month'],
        ])->getResultArray();

        if ($rows === []) {
            return [];
        }

        return array_map(static function (array $row): array {
            return [
                'period_month' => (string) ($row['period_month'] ?? ''),
                'sunday_services_count' => (int) ($row['sunday_services_count'] ?? 0),
                'sunday_attended_total' => (int) ($row['sunday_attended_total'] ?? 0),
                'sunday_attendance_average' => (float) ($row['sunday_attendance_average'] ?? 0),
                'weekday_services_count' => (int) ($row['weekday_services_count'] ?? 0),
                'weekday_attended_total' => (int) ($row['weekday_attended_total'] ?? 0),
                'weekday_attendance_average' => (float) ($row['weekday_attendance_average'] ?? 0),
            ];
        }, $rows);
    }

    /**
     * Converts period preset to an inclusive month window.
     *
     * @return array{start_month: string, end_month: string}|null
     */
    private function resolvePeriodRange(string $period, ?string $customStart, ?string $customEnd): ?array
    {
        $period = strtolower(trim($period));

        switch ($period) {
            case 'this_month':
                return [
                    'start_month' => date('Y-m-01'),
                    'end_month' => date('Y-m-01'),
                ];

            case 'this_year':
                $year = date('Y');
                return [
                    'start_month' => $year . '-01-01',
                    'end_month' => $year . '-12-01',
                ];

            case 'last_month':
                $month = date('Y-m-01', strtotime('-1 month'));
                return [
                    'start_month' => $month,
                    'end_month' => $month,
                ];

            case 'last_year':
                $year = (string) ((int) date('Y') - 1);
                return [
                    'start_month' => $year . '-01-01',
                    'end_month' => $year . '-12-01',
                ];

            case 'custom_range':
                if (!$this->isValidDate($customStart) || !$this->isValidDate($customEnd)) {
                    return null;
                }

                $startMonth = date('Y-m-01', strtotime((string) $customStart));
                $endMonth = date('Y-m-01', strtotime((string) $customEnd));

                if ($startMonth > $endMonth) {
                    return null;
                }

                return [
                    'start_month' => $startMonth,
                    'end_month' => $endMonth,
                ];

            default:
                return null;
        }
    }

    private function isValidDate(?string $value): bool
    {
        if ($value === null || trim($value) === '') {
            return false;
        }

        $value = trim($value);
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $dt !== false && $dt->format('Y-m-d') === $value;
    }
}
