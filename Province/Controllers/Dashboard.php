<?php

namespace Province\Controllers;

use App\Controllers\BaseController;
use App\Services\MembershipStatusService;

class Dashboard extends BaseController
{
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

        $membershipOverview = $this->getMembershipOverview($provinceId, $selectedDioceseId);
        $statusCounts = $this->getStatusCounts($provinceId, $selectedDioceseId);
        $attendanceStats = $this->getAttendanceAggregate($period, $startDate, $endDate, $provinceId, $selectedDioceseId);
        [$previousStartDate, $previousEndDate] = $this->getPreviousDateRange($startDate, $endDate);
        $previousAttendanceStats = $this->getAttendanceAggregate('custom', $previousStartDate, $previousEndDate, $provinceId, $selectedDioceseId);
        $dioceseSummaryRows = $this->getDioceseSummaryRows($period, $startDate, $endDate, $provinceId, $selectedDioceseId);
        $allDioceseSummaryRows = $this->getDioceseSummaryRows($period, $startDate, $endDate, $provinceId, null);
        $provinceTotals = $this->buildProvinceTotals($allDioceseSummaryRows);
        $dioceseHealthRows = $this->getDioceseHealthRows($dioceseSummaryRows, $provinceId);

        $attendanceToday = $this->getAttendanceAggregate('today', null, null, $provinceId, $selectedDioceseId);
        $attendanceWeek = $this->getAttendanceAggregate('week', null, null, $provinceId, $selectedDioceseId);
        $attendanceMonth = $this->getAttendanceAggregate('month', null, null, $provinceId, $selectedDioceseId);
        $attendanceYear = $this->getAttendanceAggregate('year', null, null, $provinceId, $selectedDioceseId);
        $attendanceSundays = $this->getAttendanceAggregate('sundays', date('Y-01-01'), date('Y-12-31'), $provinceId, $selectedDioceseId);
        $attendanceWeekdays = $this->getAttendanceAggregate('weekdays', date('Y-01-01'), date('Y-12-31'), $provinceId, $selectedDioceseId);
        $showDioceseScopeDashboard = $selectedDioceseId !== null;

        $recentServices = $showDioceseScopeDashboard
            ? $this->getRecentServicesForDiocese($provinceId, $selectedDioceseId)
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
            'total_guests' => (int) ($membershipOverview['guest_count'] ?? 0),
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
            'scope_total_guests' => (int) ($membershipOverview['guest_count'] ?? 0),
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
            'today' => $this->getAttendanceAggregate('today', null, null, $provinceId, $dioceseId),
            'week' => $this->getAttendanceAggregate('week', null, null, $provinceId, $dioceseId),
            'month' => $this->getAttendanceAggregate('month', null, null, $provinceId, $dioceseId),
            'year' => $this->getAttendanceAggregate('year', null, null, $provinceId, $dioceseId),
            'sundays' => $this->getAttendanceAggregate('sundays', date('Y-01-01'), date('Y-12-31'), $provinceId, $dioceseId),
            'weekdays' => $this->getAttendanceAggregate('weekdays', date('Y-01-01'), date('Y-12-31'), $provinceId, $dioceseId),
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

        $rows = $this->getDioceseSummaryRows($filters['period'], $filters['start_date'], $filters['end_date'], $provinceId, $dioceseId);
        $healthRows = $this->getDioceseHealthRows($rows, $provinceId);
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

        [$startDate, $endDate] = $this->resolveDateRange($period, is_string($startDate) ? $startDate : null, is_string($endDate) ? $endDate : null);
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

    protected function getPreviousDateRange(string $startDate, string $endDate): array
    {
        $start = new \DateTimeImmutable($startDate);
        $end = new \DateTimeImmutable($endDate);
        $days = $start->diff($end)->days + 1;
        return [$start->modify('-' . $days . ' days')->format('Y-m-d'), $start->modify('-1 day')->format('Y-m-d')];
    }

    protected function getAttendanceAggregate(string $period, ?string $startDate, ?string $endDate, int $provinceId, ?int $dioceseId = null): array
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

    protected function getMembershipOverview(int $provinceId, ?int $dioceseId = null): array
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
            ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("provinceadmin","diocesanadmin")', 'left')
            ->where('mca.is_member', 1)
            ->where('agu.id IS NULL')
            ->where('d.province_id', $provinceId);

        if ($dioceseId !== null) {
            $builder->where('c.diocese_id', $dioceseId);
        }

        $row = $builder->get()->getRowArray();

        $guestBuilder = db_connect()->table('guest g')
            ->select('COUNT(*) as guest_count')
            ->join('parish c', 'c.id = g.parish_id')
            ->join('diocese d', 'd.id = c.diocese_id')
            ->where('d.province_id', $provinceId)
            ->where('g.converted_to_member_at IS NULL', null, false);

        if ($dioceseId !== null) {
            $guestBuilder->where('c.diocese_id', $dioceseId);
        }

        $guestRow = $guestBuilder->get()->getRowArray();

        $standingBuilder = db_connect()->table('member_standing_summary mss')
            ->select('COUNT(DISTINCT mss.user_id) as good_standing_members')
            ->join('parish_affiliation mca', 'mca.linked_user_id = mss.user_id AND mca.is_member = 1 AND mca.membership_status = "member"', 'inner')
            ->join('parish c', 'c.id = mca.parish_id')
            ->join('diocese d', 'd.id = c.diocese_id')
            ->join('users u', 'u.id = mss.user_id')
            ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("provinceadmin","diocesanadmin")', 'left')
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
        $guestCount = (int) ($guestRow['guest_count'] ?? 0);

        return [
            'total_people' => $totalAffiliated + $guestCount,
            'guest_count' => $guestCount,
            'total_members' => (int) ($row['total_members'] ?? 0),
            'active_members' => (int) ($row['active_members'] ?? 0),
            'total_parishes' => (int) ($row['total_parishes'] ?? 0),
            'total_dioceses' => (int) ($row['total_dioceses'] ?? 0),
            'good_standing_members' => (int) ($standingRow['good_standing_members'] ?? 0),
            'leadership_members' => (int) ($leadershipRow['leadership_members'] ?? 0),
        ];
    }

    protected function getStatusCounts(int $provinceId, ?int $dioceseId = null): array
    {
        $builder = db_connect()->table('parish_affiliation mca')
            ->select('mca.membership_status, COUNT(DISTINCT mca.linked_user_id) as count')
            ->join('parish c', 'c.id = mca.parish_id')
            ->join('diocese d', 'd.id = c.diocese_id')
            ->join('users u', 'u.id = mca.linked_user_id')
            ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("provinceadmin","diocesanadmin")', 'left')
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
                $status = MembershipStatusService::profileToAffiliation($status);
            }
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

    protected function getDioceseSummaryRows(string $period, ?string $startDate, ?string $endDate, int $provinceId, ?int $dioceseId = null): array
    {
        $db = db_connect();

        $baseBuilder = $db->table('diocese d')
            ->select('d.id as diocese_id, d.name as diocese_name, COUNT(DISTINCT c.id) as parishes_count')
            ->select('COUNT(DISTINCT CASE WHEN mca.linked_user_id IS NOT NULL AND agu.id IS NULL THEN mca.linked_user_id END) as total_affiliated')
            ->select('COUNT(DISTINCT CASE WHEN mca.is_member = 1 AND agu.id IS NULL THEN mca.linked_user_id END) as total_members')
            ->select('COUNT(DISTINCT CASE WHEN mca.is_member = 1 AND mca.membership_status = "member" AND agu.id IS NULL THEN mca.linked_user_id END) as active_members')
            ->join('parish c', 'c.diocese_id = d.id', 'left')
            ->join('parish_affiliation mca', 'mca.parish_id = c.id AND mca.is_member = 1', 'left')
            ->join('users u', 'u.id = mca.linked_user_id', 'left')
            ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("provinceadmin","diocesanadmin")', 'left')
            ->where('d.province_id', $provinceId)
            ->groupBy('d.id, d.name')
            ->orderBy('d.name', 'ASC');

        if ($dioceseId !== null) {
            $baseBuilder->where('d.id', $dioceseId);
        }

        $baseRows = $baseBuilder->get()->getResultArray();

        $guestBuilder = $db->table('guest g')
            ->select('c.diocese_id as diocese_id, COUNT(*) as guest_count')
            ->join('parish c', 'c.id = g.parish_id', 'inner')
            ->join('diocese d', 'd.id = c.diocese_id', 'inner')
            ->where('d.province_id', $provinceId)
            ->where('g.converted_to_member_at IS NULL', null, false);

        if ($dioceseId !== null) {
            $guestBuilder->where('c.diocese_id', $dioceseId);
        }

        $guestRows = $guestBuilder->groupBy('c.diocese_id')->get()->getResultArray();
        $guestMap = [];
        foreach ($guestRows as $gRow) {
            $guestMap[(int) ($gRow['diocese_id'] ?? 0)] = (int) ($gRow['guest_count'] ?? 0);
        }

        $attendanceBuilder = $db->table('service s')
            ->select('c.diocese_id as diocese_id')
            ->select('COUNT(CASE WHEN a.attended = 1 THEN 1 END) as total_attendance')
            ->select('COUNT(DISTINCT s.id) as total_services')
            ->join('parish c', 'c.id = s.parish_id')
            ->join('diocese d', 'd.id = c.diocese_id')
            ->join('attendance a', 'a.service_id = s.id', 'left')
            ->where('d.province_id', $provinceId);

        if ($dioceseId !== null) {
            $attendanceBuilder->where('c.diocese_id', $dioceseId);
        }

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
                $attendanceBuilder->where('DAYOFWEEK(s.service_date)', 1);
                $attendanceBuilder->where('s.service_date >=', $startDate ?: date('Y-01-01'));
                $attendanceBuilder->where('s.service_date <=', $endDate ?: date('Y-12-31'));
                break;
            case 'weekdays':
                $attendanceBuilder->whereIn('DAYOFWEEK(s.service_date)', [2, 3, 4, 5, 6, 7]);
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
                'total_people' => (int) ($row['total_affiliated'] ?? 0) + (int) ($guestMap[$dioceseId] ?? 0),
                'guest_count' => (int) ($guestMap[$dioceseId] ?? 0),
                'total_members' => (int) ($row['total_members'] ?? 0),
                'active_members' => (int) ($row['active_members'] ?? 0),
                'total_attendance' => $totalAttendance,
                'total_services' => $totalServices,
                'average_per_service' => $totalServices > 0 ? round($totalAttendance / $totalServices, 2) : 0,
            ];
        }

        return $rows;
    }

    protected function getDioceseHealthRows(array $summaryRows, int $provinceId): array
    {
        $adminRows = db_connect()->table('profile p')
            ->select('p.diocese_id, COUNT(*) AS active_admins')
            ->join('auth_groups_users agu', 'agu.user_id = p.user_id AND agu.group = "dioceseadmin"', 'inner')
            ->join('users u', 'u.id = p.user_id AND u.active = 1', 'inner')
            ->join('diocese d', 'd.id = p.diocese_id AND d.province_id = ' . $provinceId, 'inner', false)
            ->groupBy('p.diocese_id')->get()->getResultArray();
        $serviceRows = db_connect()->table('service s')
            ->select('c.diocese_id, MAX(s.service_date) AS last_service_date')
            ->join('parish c', 'c.id = s.parish_id', 'inner')
            ->join('diocese d', 'd.id = c.diocese_id', 'inner')
            ->where('d.province_id', $provinceId)->groupBy('c.diocese_id')->get()->getResultArray();
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

    protected function buildProvinceTotals(array $rows): array
    {
        $totals = [
            'total_dioceses' => 0,
            'total_parishes' => 0,
            'total_people' => 0,
            'total_guests' => 0,
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
            $totals['total_guests'] += (int) ($row['guest_count'] ?? 0);
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

    protected function getRecentServicesForDiocese(int $provinceId, int $dioceseId, int $limit = 20): array
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
