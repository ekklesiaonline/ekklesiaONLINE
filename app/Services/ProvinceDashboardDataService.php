<?php

namespace App\Services;

use App\Services\MembershipStatusService;

/**
 * ProvinceDashboardDataService
 *
 * Read-side query assembly for the provincial dashboard: date ranges,
 * attendance aggregates, membership/status overviews, diocese summary and
 * health rows, province totals, and recent-services feeds. Extracted from
 * Province\Controllers\Dashboard (phase 5 split); behavior unchanged.
 */
class ProvinceDashboardDataService
{

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


    public function getPreviousDateRange(string $startDate, string $endDate): array
    {
        $start = new \DateTimeImmutable($startDate);
        $end = new \DateTimeImmutable($endDate);
        $days = $start->diff($end)->days + 1;
        return [$start->modify('-' . $days . ' days')->format('Y-m-d'), $start->modify('-1 day')->format('Y-m-d')];
    }


    public function getAttendanceAggregate(string $period, ?string $startDate, ?string $endDate, int $provinceId, ?int $dioceseId = null): array
    {
        $builder = db_connect()->table('attendance a')
            ->select('COUNT(CASE WHEN a.attended = 1 THEN 1 END) as total_attendance')
            ->select('COUNT(DISTINCT s.id) as total_services')
            ->select('COUNT(CASE WHEN a.communicant = 1 AND a.attended = 1 THEN 1 END) as total_communicants')
            ->join('service s', 's.id = a.service_id')
            ->join('parish c', 'c.id = s.parish_id')
            ->join('diocese d', 'd.id = c.diocese_id')
            ->where('d.province_id', $provinceId);

        if ($dioceseId !== null) {
            $builder->where('c.diocese_id', $dioceseId);
        }

        switch ($period) {
            case 'today':
                $builder->where('s.service_date', date('Y-m-d'));
                break;
            case 'week':
                $builder->where('s.service_date >=', date('Y-m-d', strtotime('monday this week')));
                $builder->where('s.service_date <=', date('Y-m-d', strtotime('sunday this week')));
                break;
            case 'month':
                $builder->where('s.service_date >=', date('Y-m-01'));
                $builder->where('s.service_date <=', date('Y-m-t'));
                break;
            case 'year':
                $builder->where('s.service_date >=', date('Y-01-01'));
                $builder->where('s.service_date <=', date('Y-12-31'));
                break;
            case 'sundays':
                $builder->where('DAYOFWEEK(s.service_date)', 1);
                $builder->where('s.service_date >=', $startDate ?: date('Y-01-01'));
                $builder->where('s.service_date <=', $endDate ?: date('Y-12-31'));
                break;
            case 'weekdays':
                $builder->whereIn('DAYOFWEEK(s.service_date)', [2, 3, 4, 5, 6, 7]);
                $builder->where('s.service_date >=', $startDate ?: date('Y-01-01'));
                $builder->where('s.service_date <=', $endDate ?: date('Y-12-31'));
                break;
            case 'custom':
                if (!empty($startDate) && !empty($endDate)) {
                    $builder->where('s.service_date >=', $startDate);
                    $builder->where('s.service_date <=', $endDate);
                }
                break;
        }

        $row = $builder->get()->getRowArray();

        $totalAttendance = (int) ($row['total_attendance'] ?? 0);
        $totalServices = (int) ($row['total_services'] ?? 0);

        return [
            'total_attendance' => $totalAttendance,
            'total_services' => $totalServices,
            'total_communicants' => (int) ($row['total_communicants'] ?? 0),
            'average_per_service' => $totalServices > 0 ? round($totalAttendance / $totalServices, 2) : 0,
            'period' => $period,
        ];
    }


    public function getMembershipOverview(int $provinceId, ?int $dioceseId = null): array
    {
        $builder = db_connect()->table('parish_affiliation mca')
            ->select('COUNT(DISTINCT mca.linked_user_id) as total_affiliated')
            ->select('COUNT(DISTINCT CASE WHEN mca.is_member = 1 THEN mca.linked_user_id END) as total_members')
            ->select('COUNT(DISTINCT CASE WHEN mca.is_member = 1 AND mca.membership_status = "member" THEN mca.linked_user_id END) as active_members')
            ->select('COUNT(DISTINCT c.id) as total_parishes')
            ->select('COUNT(DISTINCT c.diocese_id) as total_dioceses')
            ->join('parish c', 'c.id = mca.parish_id')
            ->join('diocese d', 'd.id = c.diocese_id')
            ->join('users u', 'u.id = mca.linked_user_id')
            ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("provinceadmin","diocesanadmin","dioceseadmin")', 'left')
            ->where('mca.is_member', 1)
            ->where('agu.id IS NULL')
            ->where('d.province_id', $provinceId);

        if ($dioceseId !== null) {
            $builder->where('c.diocese_id', $dioceseId);
        }

        $row = $builder->get()->getRowArray();

        $visitorBuilder = db_connect()->table('parish_affiliation g')
            ->select('COUNT(DISTINCT g.person_id) as visitor_count')
            ->join('parish c', 'c.id = g.parish_id')
            ->join('diocese d', 'd.id = c.diocese_id')
            ->where('d.province_id', $provinceId)
            ->where('g.membership_status', 'visitor')
            ->where('g.deleted_at IS NULL', null, false)
            ->where('g.converted_to_member_at IS NULL', null, false);

        if ($dioceseId !== null) {
            $visitorBuilder->where('c.diocese_id', $dioceseId);
        }

        $visitorRow = $visitorBuilder->get()->getRowArray();

        $standingBuilder = db_connect()->table('member_standing_summary mss')
            ->select('COUNT(DISTINCT mss.user_id) as good_standing_members')
            ->join('parish_affiliation mca', 'mca.linked_user_id = mss.user_id AND mca.is_member = 1 AND mca.membership_status = "member"', 'inner')
            ->join('parish c', 'c.id = mca.parish_id')
            ->join('diocese d', 'd.id = c.diocese_id')
            ->join('users u', 'u.id = mss.user_id')
            ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("provinceadmin","diocesanadmin","dioceseadmin")', 'left')
            ->where('d.province_id', $provinceId)
            ->where('mss.year', (int) date('Y'))
            ->where('mss.is_good_standing', 1)
            ->where('agu.id IS NULL');

        if ($dioceseId !== null) {
            $standingBuilder->where('c.diocese_id', $dioceseId);
        }

        $standingRow = $standingBuilder->get()->getRowArray();

        $leadershipBuilder = db_connect()->table('leadership_position lp')
            ->select('COUNT(DISTINCT lp.user_id) as leadership_members')
            ->join('parish c', 'c.id = lp.parish_id', 'left')
            ->where('lp.active', 1)
            ->groupStart()
            ->groupStart()
            ->where('lp.scope_type', 'province')
            ->where('lp.province_id', $provinceId)
            ->groupEnd()
            ->orGroupStart()
            ->where('lp.scope_type', 'diocese')
            ->where('lp.diocese_id IN (SELECT id FROM diocese WHERE province_id = ' . (int) $provinceId . ')', null, false)
            ->groupEnd()
            ->orGroupStart()
            ->where('lp.scope_type', 'parish')
            ->where('c.diocese_id IN (SELECT id FROM diocese WHERE province_id = ' . (int) $provinceId . ')', null, false)
            ->groupEnd()
            ->groupEnd();

        if ($dioceseId !== null) {
            $leadershipBuilder->groupStart()
                ->groupStart()
                ->where('lp.scope_type', 'diocese')
                ->where('lp.diocese_id', $dioceseId)
                ->groupEnd()
                ->orGroupStart()
                ->where('lp.scope_type', 'parish')
                ->where('c.diocese_id', $dioceseId)
                ->groupEnd()
                ->groupEnd();
        }

        $leadershipRow = $leadershipBuilder->get()->getRowArray();

        $totalAffiliated = (int) ($row['total_affiliated'] ?? 0);
        $visitorCount = (int) ($visitorRow['visitor_count'] ?? 0);

        return [
            'total_people' => $totalAffiliated + $visitorCount,
            'visitor_count' => $visitorCount,
            'total_members' => (int) ($row['total_members'] ?? 0),
            'active_members' => (int) ($row['active_members'] ?? 0),
            'total_parishes' => (int) ($row['total_parishes'] ?? 0),
            'total_dioceses' => (int) ($row['total_dioceses'] ?? 0),
            'good_standing_members' => (int) ($standingRow['good_standing_members'] ?? 0),
            'leadership_members' => (int) ($leadershipRow['leadership_members'] ?? 0),
        ];
    }


    public function getStatusCounts(int $provinceId, ?int $dioceseId = null): array
    {
        $builder = db_connect()->table('parish_affiliation mca')
            ->select('mca.membership_status, COUNT(DISTINCT mca.linked_user_id) as count')
            ->join('parish c', 'c.id = mca.parish_id')
            ->join('diocese d', 'd.id = c.diocese_id')
            ->join('users u', 'u.id = mca.linked_user_id')
            ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("provinceadmin","diocesanadmin","dioceseadmin")', 'left')
            ->where('mca.is_member', 1)
            ->where('agu.id IS NULL')
            ->where('d.province_id', $provinceId)
            ->groupBy('mca.membership_status');

        if ($dioceseId !== null) {
            $builder->where('c.diocese_id', $dioceseId);
        }

        $rows = $builder->get()->getResultArray();
        $bucket = [];
        $validStatuses = MembershipStatusService::affiliationStatuses();

        foreach ($rows as $row) {
            $status = MembershipStatusService::normalizeAffiliationStatus((string) ($row['membership_status'] ?? ''));
            if (!in_array($status, $validStatuses, true)) {
                $status = 'visitor';
            }

            $bucket[$status] = (int) ($bucket[$status] ?? 0) + (int) ($row['count'] ?? 0);
        }

        $normalized = [];
        foreach ($bucket as $status => $count) {
            $normalized[] = [
                'membership_status' => $status,
                'count' => $count,
            ];
        }

        return $normalized;
    }


    public function countDioceseSummaryRows(int $provinceId, ?int $dioceseId = null): int
    {
        $query = db_connect()->table('diocese')
            ->where('province_id', $provinceId);

        if ($dioceseId !== null) {
            $query->where('id', $dioceseId);
        }

        return (int) $query->countAllResults();
    }


    public function getDioceseSummaryRows(
        string $period,
        ?string $startDate,
        ?string $endDate,
        int $provinceId,
        ?int $dioceseId = null,
        ?int $limit = null,
        int $offset = 0
    ): array {
        $db = db_connect();

        // When paginating, restrict every subquery to the target page of
        // dioceses so we do not aggregate attendance/visitors province-wide.
        $restrictedDioceseIds = null;
        if ($limit !== null) {
            $idQuery = $db->table('diocese')
                ->select('id')
                ->where('province_id', $provinceId)
                ->orderBy('name', 'ASC')
                ->limit($limit, $offset);
            if ($dioceseId !== null) {
                $idQuery->where('id', $dioceseId);
            }
            $restrictedDioceseIds = array_map(
                static fn(array $row) => (int) $row['id'],
                $idQuery->get()->getResultArray()
            );
        }

        $baseBuilder = $db->table('diocese d')
            ->select('d.id as diocese_id, d.name as diocese_name, COUNT(DISTINCT c.id) as parishes_count')
            ->select('COUNT(DISTINCT CASE WHEN mca.linked_user_id IS NOT NULL AND agu.id IS NULL THEN mca.linked_user_id END) as total_affiliated')
            ->select('COUNT(DISTINCT CASE WHEN mca.is_member = 1 AND agu.id IS NULL THEN mca.linked_user_id END) as total_members')
            ->select('COUNT(DISTINCT CASE WHEN mca.is_member = 1 AND mca.membership_status = "member" AND agu.id IS NULL THEN mca.linked_user_id END) as active_members')
            ->join('parish c', 'c.diocese_id = d.id', 'left')
            ->join('parish_affiliation mca', 'mca.parish_id = c.id AND mca.is_member = 1', 'left')
            ->join('users u', 'u.id = mca.linked_user_id', 'left')
            ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("provinceadmin","diocesanadmin","dioceseadmin")', 'left')
            ->where('d.province_id', $provinceId)
            ->groupBy('d.id, d.name')
            ->orderBy('d.name', 'ASC');

        if ($restrictedDioceseIds !== null) {
            $baseBuilder->whereIn('d.id', $restrictedDioceseIds);
        } elseif ($dioceseId !== null) {
            $baseBuilder->where('d.id', $dioceseId);
        }

        $baseRows = $baseBuilder->get()->getResultArray();

        $visitorBuilder = $db->table('parish_affiliation g')
            ->select('c.diocese_id as diocese_id, COUNT(DISTINCT g.person_id) as visitor_count')
            ->join('parish c', 'c.id = g.parish_id', 'inner')
            ->join('diocese d', 'd.id = c.diocese_id', 'inner')
            ->where('d.province_id', $provinceId)
            ->where('g.membership_status', 'visitor')
            ->where('g.deleted_at IS NULL', null, false)
            ->where('g.converted_to_member_at IS NULL', null, false);

        if ($restrictedDioceseIds !== null) {
            $visitorBuilder->whereIn('c.diocese_id', $restrictedDioceseIds);
        } elseif ($dioceseId !== null) {
            $visitorBuilder->where('c.diocese_id', $dioceseId);
        }

        $visitorRows = $visitorBuilder->groupBy('c.diocese_id')->get()->getResultArray();
        $visitorMap = [];
        foreach ($visitorRows as $gRow) {
            $visitorMap[(int) ($gRow['diocese_id'] ?? 0)] = (int) ($gRow['visitor_count'] ?? 0);
        }

        $attendanceBuilder = $db->table('service s')
            ->select('c.diocese_id as diocese_id')
            ->select('COUNT(CASE WHEN a.attended = 1 THEN 1 END) as total_attendance')
            ->select('COUNT(DISTINCT s.id) as total_services')
            ->join('parish c', 'c.id = s.parish_id')
            ->join('diocese d', 'd.id = c.diocese_id')
            ->join('attendance a', 'a.service_id = s.id', 'left')
            ->where('d.province_id', $provinceId);

        if ($restrictedDioceseIds !== null) {
            $attendanceBuilder->whereIn('c.diocese_id', $restrictedDioceseIds);
        } elseif ($dioceseId !== null) {
            $attendanceBuilder->where('c.diocese_id', $dioceseId);
        }

        $isSqlite = $db->DBDriver === 'SQLite3';

        switch ($period) {
            case 'today':
                $attendanceBuilder->where('s.service_date', date('Y-m-d'));
                break;
            case 'week':
                $attendanceBuilder->where('s.service_date >=', date('Y-m-d', strtotime('monday this week')));
                $attendanceBuilder->where('s.service_date <=', date('Y-m-d', strtotime('sunday this week')));
                break;
            case 'month':
                $attendanceBuilder->where('s.service_date >=', date('Y-m-01'));
                $attendanceBuilder->where('s.service_date <=', date('Y-m-t'));
                break;
            case 'year':
                $attendanceBuilder->where('s.service_date >=', date('Y-01-01'));
                $attendanceBuilder->where('s.service_date <=', date('Y-12-31'));
                break;
            case 'sundays':
                if ($isSqlite) {
                    $attendanceBuilder->where("strftime('%w', s.service_date) =", '0', false);
                } else {
                    $attendanceBuilder->where('DAYOFWEEK(s.service_date)', 1);
                }
                $attendanceBuilder->where('s.service_date >=', $startDate ?: date('Y-01-01'));
                $attendanceBuilder->where('s.service_date <=', $endDate ?: date('Y-12-31'));
                break;
            case 'weekdays':
                if ($isSqlite) {
                    $attendanceBuilder->where("strftime('%w', s.service_date) !=", '0', false);
                } else {
                    $attendanceBuilder->whereIn('DAYOFWEEK(s.service_date)', [2, 3, 4, 5, 6, 7]);
                }
                $attendanceBuilder->where('s.service_date >=', $startDate ?: date('Y-01-01'));
                $attendanceBuilder->where('s.service_date <=', $endDate ?: date('Y-12-31'));
                break;
            case 'custom':
                if (!empty($startDate) && !empty($endDate)) {
                    $attendanceBuilder->where('s.service_date >=', $startDate);
                    $attendanceBuilder->where('s.service_date <=', $endDate);
                }
                break;
        }

        $attendanceRows = $attendanceBuilder
            ->groupBy('c.diocese_id')
            ->get()
            ->getResultArray();

        $attendanceMap = [];
        foreach ($attendanceRows as $row) {
            $attendanceMap[(int) $row['diocese_id']] = [
                'total_attendance' => (int) ($row['total_attendance'] ?? 0),
                'total_services' => (int) ($row['total_services'] ?? 0),
            ];
        }

        $rows = [];
        foreach ($baseRows as $row) {
            $dioceseId = (int) ($row['diocese_id'] ?? 0);
            $attendance = $attendanceMap[$dioceseId] ?? ['total_attendance' => 0, 'total_services' => 0];
            $totalServices = (int) $attendance['total_services'];
            $totalAttendance = (int) $attendance['total_attendance'];

            $rows[] = [
                'diocese_id' => $dioceseId,
                'diocese_name' => (string) ($row['diocese_name'] ?? 'Unknown Diocese'),
                'parishes_count' => (int) ($row['parishes_count'] ?? 0),
                'total_people' => (int) ($row['total_affiliated'] ?? 0) + (int) ($visitorMap[$dioceseId] ?? 0),
                'visitor_count' => (int) ($visitorMap[$dioceseId] ?? 0),
                'total_members' => (int) ($row['total_members'] ?? 0),
                'active_members' => (int) ($row['active_members'] ?? 0),
                'total_attendance' => $totalAttendance,
                'total_services' => $totalServices,
                'average_per_service' => $totalServices > 0 ? round($totalAttendance / $totalServices, 2) : 0,
            ];
        }

        return $rows;
    }


    /**
     * Compute province-level totals from aggregate queries instead of
     * materializing all diocese rows twice.
     */
    public function getProvinceSummaryTotals(
        string $period,
        ?string $startDate,
        ?string $endDate,
        int $provinceId,
        ?int $dioceseId = null
    ): array {
        $rows = $this->getDioceseSummaryRows($period, $startDate, $endDate, $provinceId, $dioceseId, null, 0);

        return $this->buildProvinceTotals($rows);
    }


    public function getDioceseHealthRows(array $summaryRows, int $provinceId, ?array $dioceseIds = null): array
    {
        // Note: we intentionally query the whole province and let PHP filter by
        // summaryRows. The number of dioceses per province is small, and using
        // table aliases in whereIn/groupBy triggers CI4/SQLite prefixing issues
        // in test environments.
        $adminBuilder = db_connect()->table('profile p')
            ->select('p.diocese_id, COUNT(*) AS active_admins')
            ->join('auth_groups_users agu', 'agu.user_id = p.user_id AND agu.group = "dioceseadmin"', 'inner')
            ->join('users u', 'u.id = p.user_id AND u.active = 1', 'inner')
            ->join('diocese d', 'd.id = p.diocese_id', 'inner')
            ->where('d.province_id', $provinceId)
            ->groupBy('p.diocese_id');
        $db = db_connect();
        $prefix = $db->getPrefix();
        $adminRows = $adminBuilder->get()->getResultArray();
        $serviceRows = $db->query("
            SELECT c.diocese_id, MAX(s.service_date) AS last_service_date
            FROM {$prefix}service s
            INNER JOIN {$prefix}parish c ON c.id = s.parish_id
            INNER JOIN {$prefix}diocese d ON d.id = c.diocese_id
            WHERE d.province_id = ?
            GROUP BY c.diocese_id
        ", [$provinceId])->getResultArray();
        $adminMap = array_column($adminRows, 'active_admins', 'diocese_id');
        $serviceMap = array_column($serviceRows, 'last_service_date', 'diocese_id');
        $overdueDays = min(365, max(1, (int) service('systemSettings')->get('province_dashboard.reporting_overdue_days', 30)));
        $threshold = date('Y-m-d', strtotime('-' . $overdueDays . ' days'));

        return array_map(static function (array $row) use ($adminMap, $serviceMap, $threshold): array {
            $dioceseId = (int) $row['diocese_id'];
            $hasAdmin = (int) ($adminMap[$dioceseId] ?? 0) > 0;
            $lastServiceDate = $serviceMap[$dioceseId] ?? null;
            $status = !$hasAdmin ? 'attention' : ($lastServiceDate === null || $lastServiceDate < $threshold ? 'overdue' : 'healthy');
            return ['diocese_id' => $dioceseId, 'has_active_admin' => $hasAdmin, 'last_service_date' => $lastServiceDate, 'health_status' => $status];
        }, $summaryRows);
    }


    public function buildProvinceTotals(array $rows): array
    {
        $totals = [
            'total_dioceses' => 0,
            'total_parishes' => 0,
            'total_people' => 0,
            'total_visitors' => 0,
            'total_members' => 0,
            'active_members' => 0,
            'total_attendance' => 0,
            'total_services' => 0,
            'average_per_service' => 0.0,
        ];

        foreach ($rows as $row) {
            $totals['total_dioceses']++;
            $totals['total_parishes'] += (int) ($row['parishes_count'] ?? 0);
            $totals['total_people'] += (int) ($row['total_people'] ?? 0);
            $totals['total_visitors'] += (int) ($row['visitor_count'] ?? 0);
            $totals['total_members'] += (int) ($row['total_members'] ?? 0);
            $totals['active_members'] += (int) ($row['active_members'] ?? 0);
            $totals['total_attendance'] += (int) ($row['total_attendance'] ?? 0);
            $totals['total_services'] += (int) ($row['total_services'] ?? 0);
        }

        if ($totals['total_services'] > 0) {
            $totals['average_per_service'] = round($totals['total_attendance'] / $totals['total_services'], 2);
        }

        return $totals;
    }


    public function getRecentServicesForDiocese(int $provinceId, int $dioceseId, int $limit = 20): array
    {
        $rows = db_connect()->table('service s')
            ->select('s.id, s.service_date, s.service_type, c.name as parish_name')
            ->join('parish c', 'c.id = s.parish_id', 'inner')
            ->join('diocese d', 'd.id = c.diocese_id', 'inner')
            ->where('d.province_id', $provinceId)
            ->where('d.id', $dioceseId)
            ->orderBy('s.service_date', 'DESC')
            ->orderBy('s.id', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();

        return $rows;
    }
}
