<?php

namespace Diocese\Controllers;

use App\Controllers\BaseController;
use App\Services\DioceseDashboardDataService;

class Dashboard extends BaseController
{
    private DioceseDashboardDataService $dashboardData;

    public function __construct()
    {
        $this->dashboardData = new DioceseDashboardDataService();
    }

    public function index()
    {
        if (!$this->isDioceseAdmin()) {
            return $this->forbiddenResponse(lang('App.accessDenied'));
        }

        $db = db_connect();
        $scopeDioceseId = (int) $this->resolveDiocesanScopeId();

        if ($scopeDioceseId <= 0) {
            return view('Diocese\Views\Dashboard/no_parishes');
        }

        $period = (string) ($this->request->getGet('period') ?? 'year');
        $customStart = $this->request->getGet('start_date');
        $customEnd = $this->request->getGet('end_date');
        [$startDate, $endDate] = $this->dashboardData->resolveDateRange($period, $customStart, $customEnd);

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

        $membershipOverview = $this->dashboardData->getMembershipOverview($scopeDioceseId, $selectedParishId);
        $parishSummaryRows  = $this->dashboardData->getParishSummaryRows($period, $startDate, $endDate, $scopeDioceseId, $selectedParishId);
        $allParishSummaryRows = $selectedParishId === null
            ? $parishSummaryRows
            : $this->dashboardData->getParishSummaryRows($period, $startDate, $endDate, $scopeDioceseId, null);
        $dioceseTotals      = $this->dashboardData->buildDioceseTotals($allParishSummaryRows);

        $attendancePresets  = $this->dashboardData->getDioceseAttendancePresets($scopeDioceseId, $selectedParishId);
        $attendanceToday    = $attendancePresets['today'];
        $attendanceWeek     = $attendancePresets['week'];
        $attendanceMonth    = $attendancePresets['month'];
        $attendanceYear     = $attendancePresets['year'];
        $attendanceSundays  = $attendancePresets['sundays'];
        $attendanceWeekdays = $attendancePresets['weekdays'];

        $attendanceStats = $attendancePresets[$period]
            ?? $this->dashboardData->getDioceseAttendanceAggregate($period, $startDate, $endDate, $scopeDioceseId, $selectedParishId);

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
            $sundayAttendanceStartDate = $this->dashboardData->isValidYmd($requestedStartDate) ? $requestedStartDate : date('Y-01-01');
            $sundayAttendanceEndDate   = $this->dashboardData->isValidYmd($requestedEndDate)   ? $requestedEndDate   : date('Y-m-d');
        }
        $sundayAttendanceTrend = $this->dashboardData->getDiocesanSundayAttendanceAverageTrend(
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
        $recentActivity = $this->dashboardData->localizeRecentNetworkActivity(
            $this->dashboardData->getRecentNetworkActivity($scopeDioceseId, 8, 0)
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

        return view('Diocese\Views\Dashboard/diocese', $data);
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
            'today'    => $this->dashboardData->getDioceseAttendanceAggregate('today',    null, null, $scopeDioceseId, $parishId),
            'week'     => $this->dashboardData->getDioceseAttendanceAggregate('week',     null, null, $scopeDioceseId, $parishId),
            'month'    => $this->dashboardData->getDioceseAttendanceAggregate('month',    null, null, $scopeDioceseId, $parishId),
            'year'     => $this->dashboardData->getDioceseAttendanceAggregate('year',     null, null, $scopeDioceseId, $parishId),
            'sundays'  => $this->dashboardData->getDioceseAttendanceAggregate('sundays',  date('Y-01-01'), date('Y-12-31'), $scopeDioceseId, $parishId),
            'weekdays' => $this->dashboardData->getDioceseAttendanceAggregate('weekdays', date('Y-01-01'), date('Y-12-31'), $scopeDioceseId, $parishId),
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

        $total    = $this->dashboardData->countRecentNetworkActivity($scopeDioceseId, $filters);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page     = max(1, min($requestedPage, $lastPage));
        $offset   = ($page - 1) * $perPage;

        $recentActivity = $this->dashboardData->localizeRecentNetworkActivity(
            $this->dashboardData->getRecentNetworkActivity($scopeDioceseId, $perPage, $offset, $filters)
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

        return view('Diocese\Views\Dashboard/recent_activity', $data);
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
}
