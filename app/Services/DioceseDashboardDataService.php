<?php

namespace App\Services;

/**
 * DioceseDashboardDataService
 *
 * Read-side query assembly for the diocesan dashboard: membership overview,
 * parish summary rows, attendance aggregates/trends, and the localized
 * recent-network-activity feed. Extracted from
 * Diocese\Controllers\Dashboard (phase 3 split); behavior unchanged.
 */
class DioceseDashboardDataService
{

    // ----------------------------------------------------------------
    // Data helpers
    // ----------------------------------------------------------------

    public function getMembershipOverview(int $dioceseId, ?int $parishId = null): array
    {
        $db = db_connect();

        // Parish count
        $parishQuery = $db->table('parish')->where('diocese_id', $dioceseId);
        if ($parishId !== null) {
            $parishQuery->where('id', $parishId);
        }
        $totalParishes = (int) $parishQuery->countAllResults();

        // Canonical affiliation metrics (parish_people snapshot retired).
        $hasStandingSummary = $db->tableExists('member_standing_summary');
        $peopleQuery = $db->table('parish_affiliation pa')
            ->select("
                COUNT(DISTINCT pa.person_id) AS total_people,
                COUNT(DISTINCT CASE WHEN pa.is_member = 1 THEN pa.person_id END) AS total_members,
                COUNT(DISTINCT CASE WHEN pa.is_member = 1 AND pa.membership_status = 'member' THEN pa.person_id END) AS active_members,
                COUNT(DISTINCT CASE WHEN pa.membership_status = 'visitor' THEN pa.person_id END) AS visitor_count
            ", false)
            ->join('parish c', 'c.id = pa.parish_id')
            ->where('pa.deleted_at IS NULL', null, false)
            ->where('c.diocese_id', $dioceseId);

        if ($hasStandingSummary) {
            // Good standing lives in member_standing_summary (keyed by user +
            // year), not on the affiliation row.
            $peopleQuery->select(
                'COUNT(DISTINCT CASE WHEN pa.is_member = 1 AND mss.is_good_standing = 1 THEN pa.person_id END) AS good_standing_members',
                false
            )->join(
                'member_standing_summary mss',
                'mss.user_id = pa.linked_user_id AND mss.year = ' . (int) date('Y'),
                'left'
            );
        } else {
            $peopleQuery->select('0 AS good_standing_members', false);
        }

        if ($parishId !== null) {
            $peopleQuery->where('pa.parish_id', $parishId);
        }

        $row = $peopleQuery->get()->getRowArray() ?? [];

        // Active parish-scoped leadership positions.
        $leaderQuery = $db->table('leadership_position lp')
            ->select('COUNT(DISTINCT COALESCE(lp.person_id, lp.user_id)) AS leadership_members', false)
            ->join('parish c', 'c.id = lp.parish_id')
            ->where('lp.active', 1)
            ->where('lp.scope_type', 'parish')
            ->where('c.diocese_id', $dioceseId);
        if ($parishId !== null) {
            $leaderQuery->where('lp.parish_id', $parishId);
        }
        $leaderRow = $leaderQuery->get()->getRowArray() ?? [];

        return [
            'total_parishes'        => $totalParishes,
            'total_people'          => (int) ($row['total_people'] ?? 0),
            'total_members'         => (int) ($row['total_members'] ?? 0),
            'active_members'        => (int) ($row['active_members'] ?? 0),
            'good_standing_members' => (int) ($row['good_standing_members'] ?? 0),
            'leadership_members'    => (int) ($leaderRow['leadership_members'] ?? 0),
            'visitor_count'           => (int) ($row['visitor_count'] ?? 0),
        ];
    }


    public function getParishSummaryRows(string $period, ?string $startDate, ?string $endDate, int $dioceseId, ?int $parishId = null): array
    {
        $db = db_connect();
        [$start, $end] = $this->normalizeDateRange($period, $startDate, $endDate);

        // Member counts per parish from the canonical affiliation table;
        // merged in PHP so the affiliation join cannot multiply the
        // service/metric aggregates.
        $memberCounts = [];
        $memberCountQuery = $db->table('parish_affiliation pa')
            ->select("
                pa.parish_id,
                COUNT(DISTINCT CASE WHEN pa.is_member = 1 THEN pa.person_id END) AS total_members,
                COUNT(DISTINCT CASE WHEN pa.is_member = 1 AND pa.membership_status = 'member' THEN pa.person_id END) AS active_members
            ", false)
            ->join('parish c', 'c.id = pa.parish_id')
            ->where('pa.deleted_at IS NULL', null, false)
            ->where('c.diocese_id', $dioceseId)
            ->groupBy('pa.parish_id');
        if ($parishId !== null) {
            $memberCountQuery->where('pa.parish_id', $parishId);
        }
        foreach ($memberCountQuery->get()->getResultArray() as $mc) {
            $memberCounts[(int) $mc['parish_id']] = $mc;
        }

        $query = $db->table('parish c')
            ->select("
                c.id AS parish_id,
                c.name AS parish_name,
                COUNT(DISTINCT s.id) AS total_services,
                COALESCE(SUM(sm.reported_present_count), 0) AS total_attendance,
                COALESCE(SUM(CASE WHEN DAYOFWEEK(s.service_date) = 1 THEN sm.reported_present_count ELSE 0 END), 0) AS sunday_attendance,
                COUNT(DISTINCT CASE WHEN DAYOFWEEK(s.service_date) = 1 THEN s.id END) AS sunday_services,
                COALESCE(SUM(CASE WHEN DAYOFWEEK(s.service_date) != 1 THEN sm.reported_present_count ELSE 0 END), 0) AS weekday_attendance,
                COUNT(DISTINCT CASE WHEN DAYOFWEEK(s.service_date) != 1 THEN s.id END) AS weekday_services
            ", false)
            ->join('service s', "s.parish_id = c.id AND s.service_date >= '$start' AND s.service_date <= '$end'", 'left')
            ->join('service_metric sm', 'sm.service_id = s.id', 'left')
            ->where('c.diocese_id', $dioceseId)
            ->groupBy('c.id, c.name')
            ->orderBy('c.name', 'ASC');

        if ($parishId !== null) {
            $query->where('c.id', $parishId);
        }

        $rows = $query->get()->getResultArray();

        foreach ($rows as &$row) {
            $mc = $memberCounts[(int) ($row['parish_id'] ?? 0)] ?? [];
            $row['total_members']  = (int) ($mc['total_members'] ?? 0);
            $row['active_members'] = (int) ($mc['active_members'] ?? 0);
            $sundayServices = (int) ($row['sunday_services'] ?? 0);
            $weekdayServices = (int) ($row['weekday_services'] ?? 0);
            $row['asa'] = $sundayServices > 0 ? round((float) $row['sunday_attendance'] / $sundayServices, 2) : 0.0;
            $row['awa'] = $weekdayServices > 0 ? round((float) $row['weekday_attendance'] / $weekdayServices, 2) : 0.0;
            $row['average_per_service'] = (int) ($row['total_services'] ?? 0) > 0
                ? round((float) ($row['total_attendance'] ?? 0) / (int) $row['total_services'], 2)
                : 0.0;
        }
        unset($row);

        return $rows;
    }


    public function buildDioceseTotals(array $rows): array
    {
        $totals = [
            'total_parishes'    => count($rows),
            'total_members'     => 0,
            'active_members'    => 0,
            'total_services'    => 0,
            'total_attendance'  => 0,
            'sunday_services'   => 0,
            'sunday_attendance' => 0,
            'weekday_services'  => 0,
            'weekday_attendance' => 0,
        ];

        foreach ($rows as $row) {
            $totals['total_members']      += (int) ($row['total_members'] ?? 0);
            $totals['active_members']     += (int) ($row['active_members'] ?? 0);
            $totals['total_services']     += (int) ($row['total_services'] ?? 0);
            $totals['total_attendance']   += (int) ($row['total_attendance'] ?? 0);
            $totals['sunday_services']    += (int) ($row['sunday_services'] ?? 0);
            $totals['sunday_attendance']  += (int) ($row['sunday_attendance'] ?? 0);
            $totals['weekday_services']   += (int) ($row['weekday_services'] ?? 0);
            $totals['weekday_attendance'] += (int) ($row['weekday_attendance'] ?? 0);
        }

        $totals['asa'] = $totals['sunday_services'] > 0
            ? round($totals['sunday_attendance'] / $totals['sunday_services'], 2) : 0.0;
        $totals['awa'] = $totals['weekday_services'] > 0
            ? round($totals['weekday_attendance'] / $totals['weekday_services'], 2) : 0.0;
        $totals['average_per_service'] = $totals['total_services'] > 0
            ? round($totals['total_attendance'] / $totals['total_services'], 2) : 0.0;

        return $totals;
    }


    public function getDioceseAttendanceAggregate(string $period, ?string $startDate, ?string $endDate, int $dioceseId, ?int $parishId = null): array
    {
        $db = db_connect();
        [$start, $end] = $this->normalizeDateRange($period, $startDate, $endDate);

        $query = $db->table('service s')
            ->select('COUNT(DISTINCT s.id) AS total_services, COALESCE(SUM(sm.reported_present_count), 0) AS total_attendance', false)
            ->join('service_metric sm', 'sm.service_id = s.id', 'left')
            ->join('parish c', 'c.id = s.parish_id')
            ->where('c.diocese_id', $dioceseId)
            ->where('s.service_date >=', $start)
            ->where('s.service_date <=', $end);

        if ($parishId !== null) {
            $query->where('s.parish_id', $parishId);
        }

        if ($period === 'sundays') {
            $query->where('DAYOFWEEK(s.service_date) = 1', null, false);
        } elseif ($period === 'weekdays') {
            $query->where('DAYOFWEEK(s.service_date) != 1', null, false);
        }

        $row = $query->get()->getRowArray() ?? [];
        $totalServices  = (int) ($row['total_services'] ?? 0);
        $totalAttendance = (int) ($row['total_attendance'] ?? 0);

        return [
            'total_services'     => $totalServices,
            'total_attendance'   => $totalAttendance,
            'average_per_service' => $totalServices > 0 ? round($totalAttendance / $totalServices, 2) : 0.0,
        ];
    }


    public function getDioceseAttendancePresets(int $dioceseId, ?int $parishId = null): array
    {
        $db = db_connect();
        [$todayStart, $todayEnd] = $this->normalizeDateRange('today', null, null);
        [$weekStart, $weekEnd] = $this->normalizeDateRange('week', null, null);
        [$monthStart, $monthEnd] = $this->normalizeDateRange('month', null, null);
        [$yearStart, $yearEnd] = $this->normalizeDateRange('year', null, null);
        $scanStart = min($weekStart, $yearStart);
        $scanEnd = max($weekEnd, date('Y-12-31'));

        $conditions = [
            'today' => "s.service_date >= " . $db->escape($todayStart) . " AND s.service_date <= " . $db->escape($todayEnd),
            'week' => "s.service_date >= " . $db->escape($weekStart) . " AND s.service_date <= " . $db->escape($weekEnd),
            'month' => "s.service_date >= " . $db->escape($monthStart) . " AND s.service_date <= " . $db->escape($monthEnd),
            'year' => "s.service_date >= " . $db->escape($yearStart) . " AND s.service_date <= " . $db->escape($yearEnd),
            'sundays' => "s.service_date >= " . $db->escape(date('Y-01-01')) . " AND s.service_date <= " . $db->escape(date('Y-12-31')) . ' AND DAYOFWEEK(s.service_date) = 1',
            'weekdays' => "s.service_date >= " . $db->escape(date('Y-01-01')) . " AND s.service_date <= " . $db->escape(date('Y-12-31')) . ' AND DAYOFWEEK(s.service_date) != 1',
        ];

        $select = [];
        foreach ($conditions as $key => $condition) {
            $select[] = "COUNT(DISTINCT CASE WHEN $condition THEN s.id END) AS {$key}_services";
            $select[] = "COALESCE(SUM(CASE WHEN $condition THEN sm.reported_present_count ELSE 0 END), 0) AS {$key}_attendance";
        }

        $query = $db->table('service s')
            ->select(implode(', ', $select), false)
            ->join('service_metric sm', 'sm.service_id = s.id', 'left')
            ->join('parish c', 'c.id = s.parish_id')
            ->where('c.diocese_id', $dioceseId)
            ->where('s.service_date >=', $scanStart)
            ->where('s.service_date <=', $scanEnd);
        if ($parishId !== null) {
            $query->where('s.parish_id', $parishId);
        }

        $row = $query->get()->getRowArray() ?? [];
        $presets = [];
        foreach (array_keys($conditions) as $key) {
            $totalServices = (int) ($row["{$key}_services"] ?? 0);
            $totalAttendance = (int) ($row["{$key}_attendance"] ?? 0);
            $presets[$key] = [
                'total_services' => $totalServices,
                'total_attendance' => $totalAttendance,
                'average_per_service' => $totalServices > 0 ? round($totalAttendance / $totalServices, 2) : 0.0,
            ];
        }

        return $presets;
    }


    /**
     * Normalize a named period + optional custom dates into [start, end] strings.
     */
    public function normalizeDateRange(string $period, ?string $startDate, ?string $endDate): array
    {
        $today = date('Y-m-d');
        $year  = date('Y');

        switch (strtolower(trim($period))) {
            case 'today':
                return [$today, $today];
            case 'week':
                return [date('Y-m-d', strtotime('monday this week')), date('Y-m-d', strtotime('sunday this week'))];
            case 'month':
                return [date('Y-m-01'), date('Y-m-t')];
            case 'year':
                return ["$year-01-01", $today];
            case 'sundays':
            case 'weekdays':
                return [$startDate ?? "$year-01-01", $endDate ?? date('Y-12-31')];
            case 'custom':
                if ($startDate && $endDate) {
                    return [$startDate, $endDate];
                }
                return ["$year-01-01", $today];
            default:
                return ["$year-01-01", $today];
        }
    }


    // ----------------------------------------------------------------
    // Sunday attendance trend (moved from MemberPortal\Controllers\Dashboard)
    // ----------------------------------------------------------------

    public function getDiocesanSundayAttendanceAverageTrend(
        int $dioceseId,
        string $period = 'this_year',
        ?string $customStartDate = null,
        ?string $customEndDate = null,
        string $granularity = 'month'
    ): array {
        $db = db_connect();
        $range = $this->resolveAttendancePeriodRange($period, $customStartDate, $customEndDate);
        $granularity = in_array($granularity, ['month', 'week'], true) ? $granularity : 'month';

        $empty = ['labels' => [], 'sunday_averages' => [], 'communion_averages' => [], 'sunday_overall_average' => 0.0, 'communion_overall_average' => 0.0, 'has_data' => false, 'source' => 'service_metric'];

        if ($range === null) {
            return $empty;
        }

        $serviceRows = $db->query("
            SELECT s.service_date,
                   COALESCE(sm.reported_present_count, 0) AS present_count,
                   COALESCE(sm.reported_communion_count, 0) AS communion_count
            FROM service s
            JOIN parish c ON c.id = s.parish_id
            JOIN service_metric sm ON sm.service_id = s.id
            WHERE c.diocese_id = ?
              AND s.service_date >= ?
              AND s.service_date <= ?
              AND DAYOFWEEK(s.service_date) = 1
            ORDER BY s.service_date ASC
        ", [$dioceseId, $range['start_date'], $range['end_date']])->getResultArray();

        return array_merge(
            $this->buildDiocesanSundayTrendFromServiceRows($serviceRows, $range, $granularity),
            ['source' => 'service_metric']
        );
    }


    public function buildDiocesanSundayTrendFromServiceRows(array $serviceRows, array $range, string $granularity): array
    {
        $labels           = [];
        $sundayAverages   = [];
        $communionAverages = [];
        $bucketKeys = [];

        $locale = (string) (service('request')->getLocale() ?: config('App')->defaultLocale);
        $formatterPattern = $granularity === 'week' ? 'MMM d' : 'MMM y';
        $formatter = class_exists(\IntlDateFormatter::class)
            ? new \IntlDateFormatter($locale, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE, date_default_timezone_get(), null, $formatterPattern)
            : null;

        $presentSumByBucket   = [];
        $communionSumByBucket = [];
        $serviceCountByBucket = [];

        if ($granularity === 'week') {
            $startOffset = (int) (new \DateTimeImmutable($range['start_date']))->format('N') - 1;
            $cursor  = (new \DateTimeImmutable($range['start_date']))->modify('-' . $startOffset . ' days');
            $endOffset = (int) (new \DateTimeImmutable($range['end_date']))->format('N') - 1;
            $lastWeek = (new \DateTimeImmutable($range['end_date']))->modify('-' . $endOffset . ' days');
            while ($cursor <= $lastWeek) {
                $bucketKeys[] = $cursor->format('Y-m-d');
                $cursor = $cursor->modify('+7 days');
            }
        } else {
            $startMonth = new \DateTimeImmutable($range['start_month']);
            $endMonth   = new \DateTimeImmutable($range['end_month']);
            $span = ((int) $endMonth->format('Y') - (int) $startMonth->format('Y')) * 12 + ((int) $endMonth->format('n') - (int) $startMonth->format('n'));
            for ($i = 0; $i <= $span; $i++) {
                $bucketKeys[] = $startMonth->modify('+' . $i . ' month')->format('Y-m');
            }
        }

        foreach ($serviceRows as $row) {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d', trim((string) ($row['service_date'] ?? '')));
            if (!$dt) {
                continue;
            }
            $key = $granularity === 'week' ? $dt->format('Y-m-d') : $dt->format('Y-m');
            // Find nearest Monday bucket for week granularity
            if ($granularity === 'week') {
                $dayOffset = (int) $dt->format('N') - 1;
                $key = $dt->modify('-' . $dayOffset . ' days')->format('Y-m-d');
            }
            $presentSumByBucket[$key]   = (float) ($presentSumByBucket[$key] ?? 0) + (float) ($row['present_count'] ?? 0);
            $communionSumByBucket[$key] = (float) ($communionSumByBucket[$key] ?? 0) + (float) ($row['communion_count'] ?? 0);
            $serviceCountByBucket[$key] = (int) ($serviceCountByBucket[$key] ?? 0) + 1;
        }

        $overallServiceCount = 0;
        $overallPresent      = 0.0;
        $overallCommunion    = 0.0;

        foreach ($bucketKeys as $key) {
            if ($granularity === 'week') {
                $bucketDate = new \DateTimeImmutable($key);
                $weekLabel = $formatter
                    ? (string) ($formatter->format($bucketDate->getTimestamp()) ?: $bucketDate->format('M d'))
                    : $bucketDate->format('M d');
                $labels[] = lang('Dashboard.weekOfLabel', [$weekLabel]);
            } else {
                $bucketDate = \DateTimeImmutable::createFromFormat('Y-m', $key);
                if (!$bucketDate) {
                    continue;
                }
                $labels[] = $formatter
                    ? (string) ($formatter->format($bucketDate->getTimestamp()) ?: $bucketDate->format('M Y'))
                    : $bucketDate->format('M Y');
            }

            $cnt    = (int) ($serviceCountByBucket[$key] ?? 0);
            $pres   = (float) ($presentSumByBucket[$key] ?? 0.0);
            $comm   = (float) ($communionSumByBucket[$key] ?? 0.0);
            $sundayAverages[]   = $cnt > 0 ? round($pres / $cnt, 2) : 0.0;
            $communionAverages[] = $cnt > 0 ? round($comm / $cnt, 2) : 0.0;
            $overallServiceCount += $cnt;
            $overallPresent      += $pres;
            $overallCommunion    += $comm;
        }

        return [
            'labels'                   => $labels,
            'sunday_averages'          => $sundayAverages,
            'communion_averages'       => $communionAverages,
            'sunday_overall_average'   => $overallServiceCount > 0 ? round($overallPresent / $overallServiceCount, 2) : 0.0,
            'communion_overall_average' => $overallServiceCount > 0 ? round($overallCommunion / $overallServiceCount, 2) : 0.0,
            'has_data'                 => $overallServiceCount > 0,
        ];
    }


    public function resolveAttendancePeriodRange(string $period, ?string $customStartDate, ?string $customEndDate): ?array
    {
        $period = strtolower(trim($period));
        switch ($period) {
            case 'this_month':
                $start = date('Y-m-01');
                $end   = date('Y-m-t');
                break;
            case 'this_year':
                $year  = date('Y');
                $start = $year . '-01-01';
                $end   = date('Y-m-d');
                break;
            case 'last_month':
                $start = date('Y-m-01', strtotime('first day of last month'));
                $end   = date('Y-m-t',  strtotime('last day of last month'));
                break;
            case 'last_year':
                $year  = (string) ((int) date('Y') - 1);
                $start = $year . '-01-01';
                $end   = $year . '-12-31';
                break;
            case 'custom_range':
                $start = $this->isValidYmd($customStartDate) ? (string) $customStartDate : date('Y-01-01');
                $end   = $this->isValidYmd($customEndDate)   ? (string) $customEndDate   : date('Y-m-d');
                if ($start > $end) {
                    return null;
                }
                break;
            default:
                return null;
        }

        return [
            'start_date'  => $start,
            'end_date'    => $end,
            'start_month' => date('Y-m-01', strtotime($start)),
            'end_month'   => date('Y-m-01', strtotime($end)),
        ];
    }


    public function isValidYmd(?string $value): bool
    {
        if ($value === null || trim($value) === '') {
            return false;
        }
        $value = trim($value);
        $dt    = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $dt !== false && $dt->format('Y-m-d') === $value;
    }


    // ----------------------------------------------------------------
    // Recent network activity helpers (moved from MemberPortal)
    // ----------------------------------------------------------------

    public function getRecentNetworkActivity(int $dioceseId, int $limit = 5, int $offset = 0, array $filters = []): array
    {
        $db      = db_connect();
        $feedSql = $this->buildRecentNetworkActivityFeedSql($dioceseId);
        $whereSql = $this->buildRecentNetworkActivityWhereSql($filters);

        $sql = "SELECT * FROM ({$feedSql}) feed {$whereSql} ORDER BY happened_at DESC LIMIT " . (int) $limit . " OFFSET " . max(0, (int) $offset);

        return $db->query($sql)->getResultArray();
    }


    public function countRecentNetworkActivity(int $dioceseId, array $filters = []): int
    {
        $db       = db_connect();
        $feedSql  = $this->buildRecentNetworkActivityFeedSql($dioceseId);
        $whereSql = $this->buildRecentNetworkActivityWhereSql($filters);
        $row      = $db->query("SELECT COUNT(*) AS total FROM ({$feedSql}) feed {$whereSql}")->getRowArray();

        return (int) ($row['total'] ?? 0);
    }


    public function buildRecentNetworkActivityFeedSql(int $dioceseId): string
    {
        $d = (int) $dioceseId;
        return "
            SELECT 'parish' AS activity_type, c.created_at AS happened_at, c.name AS parish_name,
                   c.id AS parish_id, c.name AS activity_subject, NULL AS amount, NULL AS liturgical_context
            FROM parish c WHERE c.created_at IS NOT NULL AND c.diocese_id = $d

            UNION ALL

            SELECT 'donation' AS activity_type, d.created_at AS happened_at, c.name AS parish_name,
                   c.id AS parish_id, d.category AS activity_subject, d.amount AS amount, NULL AS liturgical_context
            FROM donation d JOIN parish c ON c.id = d.parish_id
            WHERE d.created_at IS NOT NULL AND c.diocese_id = $d

            UNION ALL

            SELECT 'expense' AS activity_type, e.created_at AS happened_at, c.name AS parish_name,
                   c.id AS parish_id, e.category AS activity_subject, e.amount AS amount, NULL AS liturgical_context
            FROM expense e JOIN parish c ON c.id = e.parish_id
            WHERE e.created_at IS NOT NULL AND c.diocese_id = $d
        ";
    }


    public function buildRecentNetworkActivityWhereSql(array $filters): string
    {
        $db      = db_connect();
        $clauses = ['1=1'];

        $activityType = strtolower(trim((string) ($filters['activity_type'] ?? 'all')));
        if ($activityType === 'parish') {
            $activityType = 'parish';
        }
        if ($activityType !== '' && $activityType !== 'all') {
            $clauses[] = 'activity_type = ' . $db->escape($activityType);
        }
        $parishId = (int) ($filters['parish_id'] ?? 0);
        if ($parishId > 0) {
            $clauses[] = 'parish_id = ' . $parishId;
        }
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $clauses[] = 'DATE(happened_at) >= ' . $db->escape($dateFrom);
        }
        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $clauses[] = 'DATE(happened_at) <= ' . $db->escape($dateTo);
        }

        return 'WHERE ' . implode(' AND ', $clauses);
    }


    public function localizeRecentNetworkActivity(array $rows): array
    {
        helper('coa_i18n');

        foreach ($rows as &$row) {
            $type             = (string) ($row['activity_type'] ?? '');
            $rawAmount        = $row['amount'] ?? null;
            $amountText       = $rawAmount === null ? '-' : ('$' . number_format((float) $rawAmount, 2));
            $liturgicalContext = trim((string) ($row['liturgical_context'] ?? ''));
            $subject          = trim((string) ($row['activity_subject'] ?? ''));
            $subject          = $subject !== '' ? $subject : lang('Dashboard.systemOverviewActivityFallback');

            $row['display_subject']        = $subject;
            $row['display_flow']           = lang('Dashboard.systemOverviewFlowActivity');
            $row['display_flow_class']     = 'text-secondary';
            $row['display_amount']         = $amountText;
            $row['display_amount_class']   = 'text-secondary';
            $row['display_liturgical']     = $liturgicalContext !== '' ? liturgical_day_label($liturgicalContext) : '-';
            $row['display_liturgical_class'] = $this->resolveLiturgicalBadgeClass($liturgicalContext);

            switch ($type) {
                case 'parish':
                case 'parish':
                    $row['title'] = lang('Dashboard.systemOverviewActivityParishCreated', [$subject]);
                    break;
                case 'donation':
                    $cat = function_exists('coa_category_label') ? coa_category_label($subject, $subject) : $subject;
                    $row['title']              = lang('Dashboard.systemOverviewActivityDonationRecorded', [$cat]);
                    $row['display_subject']    = $cat;
                    $row['display_flow']       = lang('Dashboard.systemOverviewFlowIncome');
                    $row['display_flow_class'] = 'text-success';
                    $row['display_amount_class'] = 'text-success';
                    break;
                case 'expense':
                    $cat = function_exists('coa_category_label') ? coa_category_label($subject, $subject) : $subject;
                    $row['title']              = lang('Dashboard.systemOverviewActivityExpenseRecorded', [$cat]);
                    $row['display_subject']    = $cat;
                    $row['display_flow']       = lang('Dashboard.systemOverviewFlowExpense');
                    $row['display_flow_class'] = 'text-danger';
                    $row['display_amount_class'] = 'text-danger';
                    break;
                default:
                    $row['title'] = lang('Dashboard.systemOverviewActivityFallback');
                    break;
            }
        }
        unset($row);

        return $rows;
    }


    public function resolveLiturgicalBadgeClass(string $liturgicalContext): string
    {
        $n = strtolower(trim($liturgicalContext));
        if ($n === '') {
            return 'bg-light text-secondary border';
        }
        if (str_contains($n, 'easter') || str_contains($n, 'christmas') || str_contains($n, 'epiphany') || str_contains($n, 'all saints') || str_contains($n, 'ascension') || str_contains($n, 'trinity')) {
            return 'bg-warning text-dark';
        }
        if (str_contains($n, 'pentecost') || str_contains($n, 'palm') || str_contains($n, 'good friday') || str_contains($n, 'holy week')) {
            return 'bg-danger text-white';
        }
        if (str_contains($n, 'lent') || str_contains($n, 'advent') || str_contains($n, 'ash wednesday') || str_contains($n, 'maundy thursday')) {
            return 'bg-secondary text-white';
        }
        return 'bg-success text-white';
    }


    /**
     * Resolve a date range from a period keyword and optional custom dates.
     */
    public function resolveDateRange(string $period, ?string $customStart, ?string $customEnd): array
    {
        $today = date('Y-m-d');
        $thisWeekStart = date('Y-m-d', strtotime('monday this week'));
        $thisWeekEnd = date('Y-m-d', strtotime('sunday this week'));
        $thisMonthStart = date('Y-m-01');
        $thisMonthEnd = date('Y-m-t');
        $thisYearStart = date('Y-01-01');
        $thisYearEnd = date('Y-12-31');

        switch ($period) {
            case 'today':
                return [$today, $today];
            case 'week':
                return [$thisWeekStart, $thisWeekEnd];
            case 'year':
                return [$thisYearStart, $thisYearEnd];
            case 'custom':
                return [$customStart ?: $thisMonthStart, $customEnd ?: $thisMonthEnd];
            case 'month':
            default:
                return [$thisMonthStart, $thisMonthEnd];
        }
    }
}
