<?php

namespace Diocesan\Controllers;

use App\Controllers\BaseController;
use App\Services\MembershipStatusService;
use App\Libraries\PeopleMetricsService;

class Dashboard extends BaseController
{
    public function index()
    {
        if (!$this->isDioceseAdmin()) {
            return $this->forbiddenResponse(lang('App.accessDenied'));
        }

        $db = db_connect();
        $scopeDioceseId = (int) $this->resolveDiocesanScopeId();

        if ($scopeDioceseId <= 0) {
            return view('Diocesan\Views\Dashboard/no_parishes');
        }

        $period = (string) ($this->request->getGet('period') ?? 'year');
        $customStart = $this->request->getGet('start_date');
        $customEnd = $this->request->getGet('end_date');
        [$startDate, $endDate] = $this->resolveDateRange($period, $customStart, $customEnd);

        $parishes = $db->table('parish')
            ->select('id, name')
            ->where('diocese_id', $scopeDioceseId)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        $validParishIds = array_map(static fn($r) => (int) $r['id'], $parishes);

        $parishScope = strtolower(trim((string) ($this->request->getGet('parish_id') ?? 'all')));
        $isAllParishes = $parishScope === 'all' || $parishScope === '';
        $selectedParishId = null;
        if (!$isAllParishes && ctype_digit($parishScope)) {
            $candidate = (int) $parishScope;
            if (in_array($candidate, $validParishIds, true)) {
                $selectedParishId = $candidate;
            }
        }

        $membershipOverview = $this->getMembershipOverview($scopeDioceseId, $selectedParishId);
        $parishSummaryRows  = $this->getParishSummaryRows($period, $startDate, $endDate, $scopeDioceseId, $selectedParishId);
        $allParishSummaryRows = $this->getParishSummaryRows($period, $startDate, $endDate, $scopeDioceseId, null);
        $dioceseTotals      = $this->buildDioceseTotals($allParishSummaryRows);

        $attendanceToday    = $this->getDioceseAttendanceAggregate('today',    null, null, $scopeDioceseId, $selectedParishId);
        $attendanceWeek     = $this->getDioceseAttendanceAggregate('week',     null, null, $scopeDioceseId, $selectedParishId);
        $attendanceMonth    = $this->getDioceseAttendanceAggregate('month',    null, null, $scopeDioceseId, $selectedParishId);
        $attendanceYear     = $this->getDioceseAttendanceAggregate('year',     null, null, $scopeDioceseId, $selectedParishId);
        $attendanceSundays  = $this->getDioceseAttendanceAggregate('sundays',  date('Y-01-01'), date('Y-12-31'), $scopeDioceseId, $selectedParishId);
        $attendanceWeekdays = $this->getDioceseAttendanceAggregate('weekdays', date('Y-01-01'), date('Y-12-31'), $scopeDioceseId, $selectedParishId);

        $attendanceStats = $this->getDioceseAttendanceAggregate($period, $startDate, $endDate, $scopeDioceseId, $selectedParishId);

        $selectedParishName = 'All Parishes';
        if ($selectedParishId !== null) {
            foreach ($parishes as $row) {
                if ((int) $row['id'] === $selectedParishId) {
                    $selectedParishName = (string) $row['name'];
                    break;
                }
            }
        }

        // Sunday attendance trend chart
        $allowedPeriods = ['this_year', 'custom_range'];
        $requestedPeriod = strtolower(trim((string) ($this->request->getGet('attendance_period') ?? 'this_year')));
        if (!in_array($requestedPeriod, $allowedPeriods, true)) {
            $requestedPeriod = 'this_year';
        }
        $allowedGranularity = ['month', 'week'];
        $requestedGranularity = strtolower(trim((string) ($this->request->getGet('attendance_granularity') ?? 'month')));
        if (!in_array($requestedGranularity, $allowedGranularity, true)) {
            $requestedGranularity = 'month';
        }
        $requestedStartDate = (string) ($this->request->getGet('attendance_start_date') ?? '');
        $requestedEndDate   = (string) ($this->request->getGet('attendance_end_date') ?? '');
        $sundayAttendanceStartDate = null;
        $sundayAttendanceEndDate   = null;
        if ($requestedPeriod === 'custom_range') {
            $sundayAttendanceStartDate = $this->isValidYmd($requestedStartDate) ? $requestedStartDate : date('Y-01-01');
            $sundayAttendanceEndDate   = $this->isValidYmd($requestedEndDate)   ? $requestedEndDate   : date('Y-m-d');
        }
        $sundayAttendanceTrend = $this->getDiocesanSundayAttendanceAverageTrend(
            $scopeDioceseId,
            $requestedPeriod,
            $sundayAttendanceStartDate,
            $sundayAttendanceEndDate,
            $requestedGranularity
        );

        // Pending registration approvals (exclude elevated admin self-requests).
        // Diocese admins cannot moderate registrations themselves (that is a
        // parish-admin action), so the dashboard surfaces the queue for
        // oversight only — letting the diocese admin see which parishes have
        // backlog and follow up with their parish admins.
        $registrationRequestModel = model('UserRegistrationRequestModel');
        $pendingAdminReviewsCount = $registrationRequestModel->countPendingByDiocese($scopeDioceseId);
        $pendingAdminReviews      = $pendingAdminReviewsCount > 0
            ? $registrationRequestModel->getPendingByDiocese($scopeDioceseId, 50)
            : [];

        // Recent network activity (8 items for dashboard widget)
        $recentActivity = $this->localizeRecentNetworkActivity(
            $this->getRecentNetworkActivity($scopeDioceseId, 8, 0)
        );

        // Audit logs
        $recentAuditLogs    = [];
        $recentAuditCount7d = 0;
        if ($db->tableExists('parish_role_audit_log')) {
            $recentAuditLogs = model('ParishRoleAuditModel')->getRecentByDiocese($scopeDioceseId, 20);
            foreach ($recentAuditLogs as $log) {
                if (!empty($log['created_at']) && strtotime((string) $log['created_at']) >= strtotime('-7 days')) {
                    $recentAuditCount7d++;
                }
            }
        }

        $showParishScopeDashboard = $selectedParishId !== null;

        $data = array_merge($this->getCommonViewData(lang('Dashboard.dioceseDashboard')), [
            'parishes'                => $parishes,
            'selected_diocese_id'     => $scopeDioceseId,
            'selected_parish_id'      => $selectedParishId,
            'is_all_parishes'         => $selectedParishId === null,
            'selected_parish_name'    => $selectedParishName,
            'current_period'          => $period,
            'start_date'              => $startDate,
            'end_date'                => $endDate,

            'total_parishes'          => (int) ($membershipOverview['total_parishes'] ?? 0),
            'total_people'            => (int) ($membershipOverview['total_people'] ?? 0),
            'total_visitors'            => (int) ($membershipOverview['visitor_count'] ?? 0),
            'total_members'           => (int) ($membershipOverview['total_members'] ?? 0),
            'active_members'          => (int) ($membershipOverview['active_members'] ?? 0),
            'good_standing_members'   => (int) ($membershipOverview['good_standing_members'] ?? 0),
            'leadership_members'      => (int) ($membershipOverview['leadership_members'] ?? 0),

            'attendance_stats'        => $attendanceStats,
            'attendance_today'        => $attendanceToday,
            'attendance_week'         => $attendanceWeek,
            'attendance_month'        => $attendanceMonth,
            'attendance_year'         => $attendanceYear,
            'attendance_sundays'      => $attendanceSundays,
            'attendance_weekdays'     => $attendanceWeekdays,

            'parish_summary_rows'     => $parishSummaryRows,
            'diocese_totals'          => $dioceseTotals,

            'show_parish_scope_dashboard' => $showParishScopeDashboard,
            'scope_total_people'      => (int) ($membershipOverview['total_people'] ?? 0),
            'scope_total_members'     => (int) ($membershipOverview['total_members'] ?? 0),
            'scope_total_visitors'      => (int) ($membershipOverview['visitor_count'] ?? 0),
            'scope_good_standing_members' => (int) ($membershipOverview['good_standing_members'] ?? 0),
            'scope_leadership_members' => (int) ($membershipOverview['leadership_members'] ?? 0),
            'scope_total_attendance'  => (int) ($attendanceStats['total_attendance'] ?? 0),
            'scope_total_services'    => (int) ($attendanceStats['total_services'] ?? 0),
            'scope_asa'               => (float) ($attendanceSundays['average_per_service'] ?? 0),
            'scope_awa'               => (float) ($attendanceWeekdays['average_per_service'] ?? 0),

            'sunday_attendance_labels'            => $sundayAttendanceTrend['labels'],
            'sunday_attendance_averages'          => $sundayAttendanceTrend['sunday_averages'],
            'communion_attendance_averages'       => $sundayAttendanceTrend['communion_averages'],
            'sunday_attendance_overall_average'   => (float) ($sundayAttendanceTrend['sunday_overall_average'] ?? 0.0),
            'communion_attendance_overall_average' => (float) ($sundayAttendanceTrend['communion_overall_average'] ?? 0.0),
            'sunday_attendance_has_data'          => (bool) ($sundayAttendanceTrend['has_data'] ?? false),
            'sunday_attendance_period'            => $requestedPeriod,
            'sunday_attendance_granularity'       => $requestedGranularity,
            'sunday_attendance_start_date'        => $sundayAttendanceStartDate,
            'sunday_attendance_end_date'          => $sundayAttendanceEndDate,

            'pending_admin_reviews_count' => (int) $pendingAdminReviewsCount,
            'pending_admin_reviews'       => $pendingAdminReviews,
            'recent_activity'             => $recentActivity,
            'recent_activity_more_url'    => base_url('diocese/dashboard/recent-activity'),
            'recent_audit_logs'           => $recentAuditLogs,
            'recent_audit_count_7d'       => (int) $recentAuditCount7d,
        ]);

        return view('Diocesan\Views\Dashboard/diocese', $data);
    }

    // ----------------------------------------------------------------
    // AJAX: attendance period breakdown (mirrors Province equivalent)
    // ----------------------------------------------------------------

    public function attendancePeriodBreakdown()
    {
        if (!$this->isDioceseAdmin()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.accessDenied'), 403, 'DIOCESE_ADMIN_REQUIRED'), 403);
        }

        $db = db_connect();
        $scopeDioceseId = (int) $this->resolveDiocesanScopeId();
        if ($scopeDioceseId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload('Unable to resolve diocese scope', 400, 'DIOCESE_SCOPE_UNRESOLVED'), 400);
        }

        $parishIdParam = (string) ($this->request->getGet('parish_id') ?? 'all');
        $parishId = null;
        if ($parishIdParam !== 'all') {
            if (!ctype_digit($parishIdParam)) {
                return $this->jsonResponse($this->apiErrorPayload('Invalid parish id', 400, 'INVALID_PARISH_ID'), 400);
            }
            $parishId = (int) $parishIdParam;
            $exists = $db->table('parish')
                ->where('id', $parishId)
                ->where('diocese_id', $scopeDioceseId)
                ->countAllResults() > 0;
            if (!$exists) {
                return $this->jsonResponse($this->apiErrorPayload('Parish not found', 404, 'PARISH_NOT_FOUND'), 404);
            }
        }

        $periods = [
            'today'    => $this->getDioceseAttendanceAggregate('today',    null, null, $scopeDioceseId, $parishId),
            'week'     => $this->getDioceseAttendanceAggregate('week',     null, null, $scopeDioceseId, $parishId),
            'month'    => $this->getDioceseAttendanceAggregate('month',    null, null, $scopeDioceseId, $parishId),
            'year'     => $this->getDioceseAttendanceAggregate('year',     null, null, $scopeDioceseId, $parishId),
            'sundays'  => $this->getDioceseAttendanceAggregate('sundays',  date('Y-01-01'), date('Y-12-31'), $scopeDioceseId, $parishId),
            'weekdays' => $this->getDioceseAttendanceAggregate('weekdays', date('Y-01-01'), date('Y-12-31'), $scopeDioceseId, $parishId),
        ];

        return $this->jsonResponse([
            'success'   => true,
            'parish_id' => $parishId,
            'scope'     => $parishId === null ? 'all' : 'parish',
            'periods'   => $periods,
        ]);
    }

    // ----------------------------------------------------------------
    // Recent activity paginated view (moved from MemberPortal)
    // ----------------------------------------------------------------

    public function recentActivity()
    {
        if (!auth()->loggedIn()) {
            return redirect()->to('/login');
        }
        if (!$this->isDioceseAdmin()) {
            return redirect()->to('/diocese/dashboard')->with('error', lang('App.errorUnauthorized'));
        }

        $scopeDioceseId = (int) $this->resolveDiocesanScopeId();
        if ($scopeDioceseId <= 0) {
            return redirect()->to('/diocese/dashboard')->with('error', lang('App.unscopeResolution'));
        }

        $requestedPage = (int) ($this->request->getGet('page') ?? 1);
        $perPage = (int) ($this->request->getGet('per_page') ?? 25);
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        $rawActivityType = strtolower(trim((string) ($this->request->getGet('activity_type') ?? 'all')));
        $activityType = in_array($rawActivityType, ['all', 'parish', 'donation', 'expense'], true) ? $rawActivityType : 'all';

        $parishId = (int) ($this->request->getGet('parish_id') ?? 0);
        if ($parishId > 0) {
            $parishInScope = (int) db_connect()->table('parish')
                ->where('id', $parishId)
                ->where('diocese_id', $scopeDioceseId)
                ->countAllResults();
            if ($parishInScope <= 0) {
                $parishId = 0;
            }
        }

        $dateFrom = trim((string) ($this->request->getGet('date_from') ?? ''));
        $dateTo   = trim((string) ($this->request->getGet('date_to') ?? ''));
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) !== 1) {
            $dateFrom = '';
        }
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) !== 1) {
            $dateTo = '';
        }
        if ($dateFrom !== '' && $dateTo !== '' && strtotime($dateFrom) > strtotime($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $filters = [
            'activity_type' => $activityType,
            'parish_id'     => $parishId,
            'date_from'     => $dateFrom,
            'date_to'       => $dateTo,
        ];

        $total    = $this->countRecentNetworkActivity($scopeDioceseId, $filters);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page     = max(1, min($requestedPage, $lastPage));
        $offset   = ($page - 1) * $perPage;

        $recentActivity = $this->localizeRecentNetworkActivity(
            $this->getRecentNetworkActivity($scopeDioceseId, $perPage, $offset, $filters)
        );

        $parishOptions = db_connect()->table('parish')
            ->select('id, name')
            ->where('diocese_id', $scopeDioceseId)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        $data = array_merge($this->getCommonViewData(lang('Dashboard.systemOverviewRecentActivity')), [
            'recent_activity'        => $recentActivity,
            'filters'                => $filters,
            'parish_options'         => $parishOptions,
            'parish_options'         => $parishOptions,
            'activity_type_options'  => [
                'all'      => lang('Dashboard.recentActivityTypeAll'),
                'donation' => lang('Dashboard.recentActivityTypeIncomeDonations'),
                'expense'  => lang('Dashboard.recentActivityTypeExpenses'),
                'parish'   => lang('Dashboard.recentActivityTypeParishes'),
            ],
            'pagination' => [
                'page'      => $page,
                'per_page'  => $perPage,
                'total'     => $total,
                'last_page' => $lastPage,
            ],
        ]);

        return view('Diocesan\Views\Dashboard/recent_activity', $data);
    }

    // ----------------------------------------------------------------
    // Switch parish (dioceseadmin branch only)
    // ----------------------------------------------------------------

    public function switchParish($parishId = null)
    {
        if (!$this->isDioceseAdmin()) {
            return redirect()->to('/diocese/dashboard')->with('error', lang('App.errorUnauthorized'));
        }
        if (!$parishId) {
            return redirect()->to('/diocese/dashboard');
        }

        $parishModel = model('ParishModel');
        $parish = $parishModel->findWithHierarchy((int) $parishId);
        if (!$parish) {
            return redirect()->to('/diocese/dashboard')->with('error', lang('App.parishNotFound'));
        }

        $adminDioceseId = $this->resolveDiocesanScopeId();
        if ($adminDioceseId <= 0 || (int) ($parish['diocese_id'] ?? 0) !== $adminDioceseId) {
            return redirect()->to('/diocese/dashboard')->with('error', lang('App.parishOutsideDiocese'));
        }

        $this->setOperationalParishId($parishId);

        return redirect()->to('/diocese/dashboard')->with('success', lang('App.parishSwitchedSuccess'));
    }

    // ----------------------------------------------------------------
    // Data helpers
    // ----------------------------------------------------------------

    protected function getMembershipOverview(int $dioceseId, ?int $parishId = null): array
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

    protected function getParishSummaryRows(string $period, ?string $startDate, ?string $endDate, int $dioceseId, ?int $parishId = null): array
    {
        $db = db_connect();
        [$start, $end] = $this->normalizeDateRange($period, $startDate, $endDate);

        // Member counts per parish from the canonical affiliation table;
        // merged in PHP so the affiliation join cannot multiply the
        // service/metric aggregates.
        $memberCounts = [];
        foreach ($db->table('parish_affiliation')
            ->select("
                parish_id,
                COUNT(DISTINCT CASE WHEN is_member = 1 THEN person_id END) AS total_members,
                COUNT(DISTINCT CASE WHEN is_member = 1 AND membership_status = 'member' THEN person_id END) AS active_members
            ", false)
            ->where('deleted_at IS NULL', null, false)
            ->groupBy('parish_id')
            ->get()
            ->getResultArray() as $mc) {
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

    protected function buildDioceseTotals(array $rows): array
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

    protected function getDioceseAttendanceAggregate(string $period, ?string $startDate, ?string $endDate, int $dioceseId, ?int $parishId = null): array
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

    /**
     * Normalize a named period + optional custom dates into [start, end] strings.
     */
    protected function normalizeDateRange(string $period, ?string $startDate, ?string $endDate): array
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

    protected function getDiocesanSundayAttendanceAverageTrend(
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

    protected function buildDiocesanSundayTrendFromServiceRows(array $serviceRows, array $range, string $granularity): array
    {
        $labels           = [];
        $sundayAverages   = [];
        $communionAverages = [];
        $bucketKeys = [];

        $locale = (string) ($this->request->getLocale() ?: config('App')->defaultLocale);
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

    protected function resolveAttendancePeriodRange(string $period, ?string $customStartDate, ?string $customEndDate): ?array
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

    protected function isValidYmd(?string $value): bool
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

    protected function getRecentNetworkActivity(int $dioceseId, int $limit = 5, int $offset = 0, array $filters = []): array
    {
        $db      = db_connect();
        $feedSql = $this->buildRecentNetworkActivityFeedSql($dioceseId);
        $whereSql = $this->buildRecentNetworkActivityWhereSql($filters);

        $sql = "SELECT * FROM ({$feedSql}) feed {$whereSql} ORDER BY happened_at DESC LIMIT " . (int) $limit . " OFFSET " . max(0, (int) $offset);

        return $db->query($sql)->getResultArray();
    }

    protected function countRecentNetworkActivity(int $dioceseId, array $filters = []): int
    {
        $db       = db_connect();
        $feedSql  = $this->buildRecentNetworkActivityFeedSql($dioceseId);
        $whereSql = $this->buildRecentNetworkActivityWhereSql($filters);
        $row      = $db->query("SELECT COUNT(*) AS total FROM ({$feedSql}) feed {$whereSql}")->getRowArray();

        return (int) ($row['total'] ?? 0);
    }

    protected function buildRecentNetworkActivityFeedSql(int $dioceseId): string
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

    protected function buildRecentNetworkActivityWhereSql(array $filters): string
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

    protected function localizeRecentNetworkActivity(array $rows): array
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

    protected function resolveLiturgicalBadgeClass(string $liturgicalContext): string
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
    protected function resolveDateRange(string $period, ?string $customStart, ?string $customEnd): array
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

    // ----------------------------------------------------------------
    // Utility: resolve profile table name safely (same as MemberPortal)
    // ----------------------------------------------------------------

    private function resolveExistingProfileTable($db): ?string
    {
        $prefix     = (string) $db->getPrefix();
        $candidates = [];
        if ($prefix !== '') {
            $candidates[] = $prefix . 'profile';
        }
        $candidates[] = 'profile';

        foreach ($candidates as $table) {
            try {
                $db->getFieldNames($table);
                return $table;
            } catch (\Throwable $e) {
                // Try next candidate.
            }
        }

        return null;
    }
}
