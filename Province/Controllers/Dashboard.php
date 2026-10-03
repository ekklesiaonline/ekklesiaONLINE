<?php

namespace Province\Controllers;

use App\Controllers\BaseController;
use App\Services\ProvinceDashboardDataService;

class Dashboard extends BaseController
{
    private ProvinceDashboardDataService $dashboardData;

    public function __construct()
    {
        $this->dashboardData = new ProvinceDashboardDataService();
    }

    public function index()
    {
        if (!$this->isProvinceAdmin()) {
            return $this->forbiddenResponse(lang('Province.accessDeniedProvinceRequired'));
        }

        $db = db_connect();
        $scope = $this->resolveProvinceScope();
        $provinceId = $scope['province_id'];

        if ($provinceId === null) {
            return view('Province\Views\Dashboard/no_parishes');
        }

        $filters = $this->resolveDashboardFilters();
        if (!$filters['valid']) {
            return redirect()->to(base_url('province/dashboard'))->with('error', $filters['error']);
        }
        $period = $filters['period'];
        $startDate = $filters['start_date'];
        $endDate = $filters['end_date'];

        $dioceses = $db->table('diocese')
            ->select('id, name')
            ->where('province_id', $provinceId)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        $validDioceseIds = array_map(static fn($row) => (int) $row['id'], $dioceses);

        $dioceseScope = strtolower(trim((string) ($this->request->getGet('diocese_id') ?? 'all')));
        $isAllDioceses = $dioceseScope === 'all' || $dioceseScope === '';
        $selectedDioceseId = null;
        if (!$isAllDioceses && ctype_digit($dioceseScope)) {
            $candidate = (int) $dioceseScope;
            if (in_array($candidate, $validDioceseIds, true)) {
                $selectedDioceseId = $candidate;
            }
        }

        // Persist active province/diocese selection for other province-admin modules.
        session()->set('province_scope_id', $provinceId);
        session()->set('province_selected_diocese_id', $selectedDioceseId);

        $membershipOverview = $this->dashboardData->getMembershipOverview($provinceId, $selectedDioceseId);
        $statusCounts = $this->dashboardData->getStatusCounts($provinceId, $selectedDioceseId);
        $attendanceStats = $this->dashboardData->getAttendanceAggregate($period, $startDate, $endDate, $provinceId, $selectedDioceseId);
        [$previousStartDate, $previousEndDate] = $this->dashboardData->getPreviousDateRange($startDate, $endDate);
        $previousAttendanceStats = $this->dashboardData->getAttendanceAggregate('custom', $previousStartDate, $previousEndDate, $provinceId, $selectedDioceseId);
        $dioceseSummaryRows = $this->dashboardData->getDioceseSummaryRows($period, $startDate, $endDate, $provinceId, $selectedDioceseId);
        $allDioceseSummaryRows = $this->dashboardData->getDioceseSummaryRows($period, $startDate, $endDate, $provinceId, null);
        $provinceTotals = $this->dashboardData->buildProvinceTotals($allDioceseSummaryRows);
        $dioceseHealthRows = $this->dashboardData->getDioceseHealthRows($dioceseSummaryRows, $provinceId);

        $attendanceToday = $this->dashboardData->getAttendanceAggregate('today', null, null, $provinceId, $selectedDioceseId);
        $attendanceWeek = $this->dashboardData->getAttendanceAggregate('week', null, null, $provinceId, $selectedDioceseId);
        $attendanceMonth = $this->dashboardData->getAttendanceAggregate('month', null, null, $provinceId, $selectedDioceseId);
        $attendanceYear = $this->dashboardData->getAttendanceAggregate('year', null, null, $provinceId, $selectedDioceseId);
        $attendanceSundays = $this->dashboardData->getAttendanceAggregate('sundays', date('Y-01-01'), date('Y-12-31'), $provinceId, $selectedDioceseId);
        $attendanceWeekdays = $this->dashboardData->getAttendanceAggregate('weekdays', date('Y-01-01'), date('Y-12-31'), $provinceId, $selectedDioceseId);
        $showDioceseScopeDashboard = $selectedDioceseId !== null;

        $recentServices = $showDioceseScopeDashboard
            ? $this->dashboardData->getRecentServicesForDiocese($provinceId, $selectedDioceseId)
            : [];

        $selectedDioceseName = 'All Dioceses';
        if ($selectedDioceseId !== null) {
            foreach ($dioceses as $row) {
                if ((int) $row['id'] === $selectedDioceseId) {
                    $selectedDioceseName = (string) $row['name'];
                    break;
                }
            }
        }

        $data = array_merge($this->getCommonViewData('Province Dashboard'), [
            'dioceses' => $dioceses,
            'selected_province_id' => $provinceId,
            'selected_diocese_id' => $selectedDioceseId,
            'is_all_dioceses' => $selectedDioceseId === null,
            'selected_diocese_name' => $selectedDioceseName,
            'current_period' => $period,
            'start_date' => $startDate,
            'end_date' => $endDate,

            'total_dioceses' => (int) ($membershipOverview['total_dioceses'] ?? 0),
            'total_parishes' => (int) ($membershipOverview['total_parishes'] ?? 0),
            'total_people' => (int) ($membershipOverview['total_people'] ?? 0),
            'total_visitors' => (int) ($membershipOverview['visitor_count'] ?? 0),
            'total_members' => (int) ($membershipOverview['total_members'] ?? 0),
            'active_members' => (int) ($membershipOverview['active_members'] ?? 0),
            'good_standing_members' => (int) ($membershipOverview['good_standing_members'] ?? 0),
            'leadership_members' => (int) ($membershipOverview['leadership_members'] ?? 0),
            'status_counts' => $statusCounts,

            'attendance_stats' => $attendanceStats,
            'previous_attendance_stats' => $previousAttendanceStats,
            'previous_start_date' => $previousStartDate,
            'previous_end_date' => $previousEndDate,
            'attendance_today' => $attendanceToday,
            'attendance_week' => $attendanceWeek,
            'attendance_month' => $attendanceMonth,
            'attendance_year' => $attendanceYear,
            'attendance_sundays' => $attendanceSundays,
            'attendance_weekdays' => $attendanceWeekdays,

            'diocese_summary_rows' => $dioceseSummaryRows,
            'diocese_health_rows' => $dioceseHealthRows,
            'province_totals' => $provinceTotals,

            // Diocese-selected mode (single diocese scope)
            'show_diocese_scope_dashboard' => $showDioceseScopeDashboard,
            'scope_total_people' => (int) ($membershipOverview['total_people'] ?? 0),
            'scope_total_members' => (int) ($membershipOverview['total_members'] ?? 0),
            'scope_total_visitors' => (int) ($membershipOverview['visitor_count'] ?? 0),
            'scope_good_standing_members' => (int) ($membershipOverview['good_standing_members'] ?? 0),
            'scope_leadership_members' => (int) ($membershipOverview['leadership_members'] ?? 0),
            'scope_total_attendance' => (int) ($attendanceStats['total_attendance'] ?? 0),
            'scope_total_services' => (int) ($attendanceStats['total_services'] ?? 0),
            // ASA = Average Sunday Attendance, AWA = Average Weekday Attendance (YTD)
            'scope_asa' => (float) ($attendanceSundays['average_per_service'] ?? 0),
            'scope_awa' => (float) ($attendanceWeekdays['average_per_service'] ?? 0),
            'recent_services' => $recentServices,
        ]);

        return view('Province\Views\Dashboard/province', $data);
    }

    public function attendancePeriodBreakdown()
    {
        if (!$this->isProvinceAdmin()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Province.accessDenied'), 403, 'PROVINCE_ADMIN_REQUIRED'), 403);
        }

        $db = db_connect();
        $scope = $this->resolveProvinceScope();
        $provinceId = $scope['province_id'];

        if ($provinceId === null) {
            return $this->jsonResponse($this->apiErrorPayload('Unable to resolve selected province scope', 400, 'PROVINCE_SCOPE_UNRESOLVED'), 400);
        }
        $dioceseIdParam = (string) ($this->request->getGet('diocese_id') ?? 'all');
        $dioceseId = null;

        if ($dioceseIdParam !== 'all') {
            if (!ctype_digit($dioceseIdParam)) {
                return $this->jsonResponse($this->apiErrorPayload('Invalid diocese id', 400, 'INVALID_DIOCESE_ID'), 400);
            }

            $dioceseId = (int) $dioceseIdParam;
            $exists = $db->table('diocese')
                ->where('id', $dioceseId)
                ->where('province_id', $provinceId)
                ->countAllResults() > 0;
            if (!$exists) {
                return $this->jsonResponse($this->apiErrorPayload('Diocese not found', 404, 'DIOCESE_NOT_FOUND'), 404);
            }
        }

        $periods = [
            'today' => $this->dashboardData->getAttendanceAggregate('today', null, null, $provinceId, $dioceseId),
            'week' => $this->dashboardData->getAttendanceAggregate('week', null, null, $provinceId, $dioceseId),
            'month' => $this->dashboardData->getAttendanceAggregate('month', null, null, $provinceId, $dioceseId),
            'year' => $this->dashboardData->getAttendanceAggregate('year', null, null, $provinceId, $dioceseId),
            'sundays' => $this->dashboardData->getAttendanceAggregate('sundays', date('Y-01-01'), date('Y-12-31'), $provinceId, $dioceseId),
            'weekdays' => $this->dashboardData->getAttendanceAggregate('weekdays', date('Y-01-01'), date('Y-12-31'), $provinceId, $dioceseId),
        ];

        return $this->jsonResponse([
            'success' => true,
            'diocese_id' => $dioceseId,
            'scope' => $dioceseId === null ? 'all' : 'diocese',
            'periods' => $periods,
        ]);
    }

    public function exportReport()
    {
        if (!$this->isProvinceAdmin() || !auth()->user()?->can('report.export')) {
            return $this->forbiddenResponse(lang('Province.accessDenied'));
        }

        $scope = $this->resolveProvinceScope();
        $provinceId = $scope['province_id'];
        if ($provinceId === null) {
            return $this->forbiddenResponse(lang('Province.scopeNotConfigured'));
        }

        $filters = $this->resolveDashboardFilters();
        if (!$filters['valid']) {
            return redirect()->to(base_url('province/dashboard'))->with('error', $filters['error']);
        }

        $dioceseId = $this->resolveSelectedDioceseId($provinceId);
        if ($dioceseId === false) {
            return $this->forbiddenResponse(lang('Province.accessDenied'));
        }

        $rows = $this->dashboardData->getDioceseSummaryRows($filters['period'], $filters['start_date'], $filters['end_date'], $provinceId, $dioceseId);
        $healthRows = $this->dashboardData->getDioceseHealthRows($rows, $provinceId);
        $healthByDiocese = array_column($healthRows, null, 'diocese_id');
        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, [lang('Dashboard.colDiocese'), lang('Dashboard.colParishes'), lang('Dashboard.colPeople'), lang('Dashboard.colMembers'), lang('Dashboard.colAttendance'), lang('Dashboard.colServices'), lang('Dashboard.lastServiceDate'), lang('Dashboard.adminCoverage'), lang('Dashboard.healthStatus')]);
        foreach ($rows as $row) {
            $health = $healthByDiocese[(int) $row['diocese_id']] ?? [];
            fputcsv($handle, [$row['diocese_name'], $row['parishes_count'], $row['total_people'], $row['total_members'], $row['total_attendance'], $row['total_services'], $health['last_service_date'] ?? '', !empty($health['has_active_admin']) ? lang('Dashboard.adminCovered') : lang('Dashboard.adminMissing'), lang('Dashboard.health_' . ($health['health_status'] ?? 'attention'))]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $this->audit()->log('province.report.exported', ['entity_type' => 'province_report', 'meta' => ['province_id' => $provinceId, 'diocese_id' => $dioceseId, 'period' => $filters['period'], 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date'], 'row_count' => count($rows)]]);
        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="province-report-' . date('Ymd') . '.csv"')
            ->setBody("\xEF\xBB\xBF" . $csv);
    }

    public function quickCreateParish()
    {
        if ($this->isDioceseAdmin()) {
            $rules = [
                'name' => 'required|min_length[3]|max_length[255]',
                'email' => 'permit_empty|valid_email',
                'website' => 'permit_empty|valid_url',
            ];

            if (!$this->validate($rules)) {
                return $this->jsonResponse($this->apiErrorPayload('Invalid input', 400, 'VALIDATION_FAILED', $this->validator->getErrors()), 400);
            }

            $profile = $this->getProfile();
            $scopeDioceseId = !empty($profile['diocese_id']) ? (int) $profile['diocese_id'] : 0;
            if ($scopeDioceseId <= 0) {
                return $this->jsonResponse($this->apiErrorPayload('Unable to resolve diocesan scope', 400, 'DIOCESAN_SCOPE_UNRESOLVED'), 400);
            }

            $dioceseId = (int) ($this->request->getPost('diocese_id') ?? $scopeDioceseId);
            if ($dioceseId !== $scopeDioceseId) {
                return $this->jsonResponse($this->apiErrorPayload('Selected diocese is outside your diocesan scope', 403, 'DIOCESE_OUTSIDE_DIOCESAN_SCOPE'), 403);
            }

            $parishId = model('ParishModel')->insert([
                'name' => $this->request->getPost('name'),
                'diocese_id' => $dioceseId,
                'email' => $this->request->getPost('email'),
                'phone' => $this->request->getPost('phone'),
                'address' => $this->request->getPost('address'),
                'website' => $this->request->getPost('website'),
            ]);

            if ($parishId) {
                try {
                    $this->audit()->log('province.parish.created', [
                        'user_id' => (int) auth()->id(),
                        'entity_type' => 'parish',
                        'entity_id' => $parishId,
                        'ip_address' => $this->request->getIPAddress(),
                        'user_agent' => esc($this->request->getUserAgent()->getAgentString()),
                        'timestamp' => date('Y-m-d H:i:s'),
                        'new_values' => [
                            'name' => $this->request->getPost('name'),
                            'diocese_id' => $dioceseId,
                            'email' => $this->request->getPost('email'),
                            'phone' => $this->request->getPost('phone'),
                        ],
                    ]);
                } catch (\Throwable $e) {
                    log_message('warning', 'Failed to log audit event for province parish creation: {message}', ['message' => $e->getMessage()]);
                }
                return $this->jsonResponse([
                    'success' => true,
                    'message' => 'Parish created successfully',
                    'parish_id' => $parishId,
                ]);
            }

            return $this->jsonResponse($this->apiErrorPayload('Failed to create parish', 500, 'PARISH_CREATE_FAILED'), 500);
        }

        return $this->jsonResponse($this->apiErrorPayload('Province workflow does not allow parish creation. Create dioceses and assign diocesan admins instead.', 403, 'PROVINCE_PARISH_CREATE_FORBIDDEN'), 403);
    }

    protected function resolveDashboardFilters(): array
    {
        $period = (string) ($this->request->getGet('period') ?? 'year');
        if (!in_array($period, ['today', 'week', 'month', 'year', 'custom'], true)) {
            return ['valid' => false, 'error' => lang('Dashboard.invalidPeriod')];
        }

        $startDate = $this->request->getGet('start_date');
        $endDate = $this->request->getGet('end_date');
        if ($period === 'custom') {
            foreach ([$startDate, $endDate] as $date) {
                $parsedDate = is_string($date) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
                $dateErrors = \DateTimeImmutable::getLastErrors();
                if (!$parsedDate || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
                    return ['valid' => false, 'error' => lang('Dashboard.invalidDateRange')];
                }
            }
            if ($startDate > $endDate) {
                return ['valid' => false, 'error' => lang('Dashboard.invalidDateRange')];
            }
        }

        [$startDate, $endDate] = $this->dashboardData->resolveDateRange($period, is_string($startDate) ? $startDate : null, is_string($endDate) ? $endDate : null);
        return ['valid' => true, 'period' => $period, 'start_date' => $startDate, 'end_date' => $endDate];
    }

    protected function resolveSelectedDioceseId(int $provinceId): int|false|null
    {
        $raw = (string) ($this->request->getGet('diocese_id') ?? 'all');
        if ($raw === '' || $raw === 'all') {
            return null;
        }
        if (!ctype_digit($raw) || db_connect()->table('diocese')->where('id', (int) $raw)->where('province_id', $provinceId)->countAllResults() === 0) {
            return false;
        }
        return (int) $raw;
    }

    protected function resolveProvinceScope(): array
    {
        $profile = $this->getProfile();
        $userId = (int) (auth()->id() ?? 0);
        $provinceId = (int) ($profile['province_id'] ?? 0);

        // If the profile does not carry a province, look up the primary province admin record.
        if ($provinceId <= 0 && $userId > 0 && db_connect()->tableExists('province_admin_primary')) {
            $primary = db_connect()->table('province_admin_primary')
                ->where('user_id', $userId)
                ->get()
                ->getRowArray();

            if (!empty($primary['province_id'])) {
                $provinceId = (int) $primary['province_id'];
            }
        }

        if ($provinceId <= 0 || db_connect()->table('province')->where('id', $provinceId)->countAllResults() === 0) {
            return ['parish_id' => null, 'province_id' => null, 'parish' => null];
        }

        return ['parish_id' => null, 'province_id' => $provinceId, 'parish' => null];
    }
}
