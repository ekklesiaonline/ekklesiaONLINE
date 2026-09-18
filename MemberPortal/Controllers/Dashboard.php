<?php

namespace MemberPortal\Controllers;

use App\Controllers\BaseController;
use App\Libraries\PeopleMetricsService;

class Dashboard extends BaseController
{
    /**
     * DEPRECATED — redirects to the new structure-first dashboard routes.
     *
     * The old /dashboard URL now resolves the user's canonical dashboard
     * scope via BaseController::resolveDashboardScope() and redirects.
     * This shim will be removed in a future release.
     */
    public function index()
    {
        if (!auth()->loggedIn()) {
            return redirect()->to('/login');
        }

        $user = auth()->user();
        $registrationRequest = $this->getCurrentRegistrationRequest();

        // Pending profile completion always takes precedence.
        if ($this->getRegistrationStatus($registrationRequest) === 'pending_profile') {
            return redirect()->to('/profile/complete');
        }

        // Pending review gate (kept here because it renders a specific view).
        if ($registrationRequest) {
            $status = $this->getRegistrationStatus($registrationRequest);

            if ($status === 'pending_review' && !$this->canBypassPendingReviewGate($user)) {
                return view('MemberPortal\Views\Auth/pending_approval', [
                    'title' => 'Awaiting Approval',
                ]);
            }
        }

        $scope = $this->resolveDashboardScope($user);
        $landingPath = $scope['route'];

        // Log the deprecation redirect for tracking stale bookmarks.
        log_message('info', '[DEPRECATED] /dashboard redirecting user {user} to {path}', [
            'user' => (int) $user->id,
            'path' => $landingPath,
        ]);

        return redirect()->to($landingPath);
    }

    /**
     * Dashboard access is role-driven. Keep this gate in-controller so
     * group-based admins do not depend on direct user-permission rows.
     */
    private function canAccessMemberDashboard($user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->can('dashboard.view')) {
            return true;
        }

        return $user->inGroup(
            'systemadmin',
            'provinceadmin',
            'parishadmin',
            'clergy',
            'treasurer',
            'volunteer_coordinator',
            'member'
        );
    }

    protected function getParishSundayAttendanceAverageTrend(
        int $parishId,
        string $period = 'this_year',
        ?string $customStartDate = null,
        ?string $customEndDate = null,
        string $granularity = 'week'
    ): array {
        $db = db_connect();
        $range = $this->resolveAttendancePeriodRange($period, $customStartDate, $customEndDate);
        $granularity = in_array($granularity, ['week', 'month'], true) ? $granularity : 'week';

        if ($range === null) {
            return [
                'labels' => [],
                'sunday_averages' => [],
                'communion_averages' => [],
                'sunday_overall_average' => 0.0,
                'communion_overall_average' => 0.0,
                'has_data' => false,
                'source' => 'service_metric',
            ];
        }

        $serviceRows = $db->query(
            "
             SELECT s.id AS service_id,
                 s.service_date AS service_date,
                 s.service_time AS service_time,
                   COALESCE(sm.reported_present_count, 0) AS present_count,
                   COALESCE(sm.reported_communion_count, 0) AS communion_count
            FROM service s
                        JOIN service_metric sm ON sm.service_id = s.id
            WHERE s.parish_id = ?
              AND s.service_date >= ?
              AND s.service_date <= ?
              AND DAYOFWEEK(s.service_date) = 1
            ORDER BY s.service_date ASC
            ",
            [$parishId, $range['start_date'], $range['end_date']]
        )->getResultArray();

        return array_merge(
            $this->buildParishSundayRawSeriesFromServiceRows($serviceRows),
            ['source' => 'service_metric']
        );
    }

    /**
     * @param array<int, array<string, mixed>> $serviceRows
     * @param array{start_date: string, end_date: string, start_month: string, end_month: string} $range
     */
    protected function buildParishSundayTotalsFromServiceRows(array $serviceRows, array $range, string $granularity): array
    {
        $labels = [];
        $presentCounts = [];
        $communionCounts = [];

        $bucketKeys = [];

        $locale = (string) ($this->request->getLocale() ?: config('App')->defaultLocale);
        $formatterPattern = $granularity === 'week' ? 'MMM d' : 'MMM y';
        $formatter = class_exists(\IntlDateFormatter::class)
            ? new \IntlDateFormatter($locale, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE, date_default_timezone_get(), null, $formatterPattern)
            : null;

        if ($granularity !== 'week') {
            $startMonth = new \DateTimeImmutable($range['start_month']);
            $endMonth = new \DateTimeImmutable($range['end_month']);
            $monthSpan = ((int) $endMonth->format('Y') - (int) $startMonth->format('Y')) * 12 + ((int) $endMonth->format('n') - (int) $startMonth->format('n'));
            for ($i = 0; $i <= $monthSpan; $i++) {
                $bucketKeys[] = $startMonth->modify('+' . $i . ' month')->format('Y-m');
            }
        }

        $presentSumByBucket = [];
        $communionSumByBucket = [];
        $serviceCountByBucket = [];

        foreach ($serviceRows as $row) {
            $serviceDate = trim((string) ($row['service_date'] ?? ''));
            if ($serviceDate === '') {
                continue;
            }

            $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $serviceDate);
            if (!$dt) {
                continue;
            }

            if ($granularity === 'week') {
                $bucketKey = $dt->format('Y-m-d');
            } else {
                $bucketKey = $dt->format('Y-m');
            }

            $presentSumByBucket[$bucketKey] = (float) ($presentSumByBucket[$bucketKey] ?? 0) + (float) ($row['present_count'] ?? 0);
            $communionSumByBucket[$bucketKey] = (float) ($communionSumByBucket[$bucketKey] ?? 0) + (float) ($row['communion_count'] ?? 0);
            $serviceCountByBucket[$bucketKey] = (int) ($serviceCountByBucket[$bucketKey] ?? 0) + 1;
        }

        if ($granularity === 'week') {
            $bucketKeys = array_keys($presentSumByBucket + $communionSumByBucket);
            sort($bucketKeys);
        }

        $overallPresent = 0.0;
        $overallCommunion = 0.0;
        $overallServiceCount = 0;

        foreach ($bucketKeys as $bucketKey) {
            if ($granularity === 'week') {
                $bucketDate = new \DateTimeImmutable($bucketKey);
                $labels[] = $formatter
                    ? (string) ($formatter->format($bucketDate->getTimestamp()) ?: $bucketDate->format('M d'))
                    : $bucketDate->format('M d');
            } else {
                $bucketDate = \DateTimeImmutable::createFromFormat('Y-m', $bucketKey);
                if (!$bucketDate) {
                    continue;
                }
                $labels[] = $formatter
                    ? (string) ($formatter->format($bucketDate->getTimestamp()) ?: $bucketDate->format('M Y'))
                    : $bucketDate->format('M Y');
            }

            $bucketServiceCount = (int) ($serviceCountByBucket[$bucketKey] ?? 0);
            $bucketPresent = (float) ($presentSumByBucket[$bucketKey] ?? 0.0);
            $bucketCommunion = (float) ($communionSumByBucket[$bucketKey] ?? 0.0);

            $presentCounts[] = $bucketServiceCount > 0 ? round($bucketPresent / $bucketServiceCount, 2) : 0.0;
            $communionCounts[] = $bucketServiceCount > 0 ? round($bucketCommunion / $bucketServiceCount, 2) : 0.0;

            $overallPresent += $bucketPresent;
            $overallCommunion += $bucketCommunion;
            $overallServiceCount += $bucketServiceCount;
        }

        return [
            'labels' => $labels,
            // Keep existing keys for compatibility with current view bindings.
            'sunday_averages' => $presentCounts,
            'communion_averages' => $communionCounts,
            'sunday_overall_average' => $overallServiceCount > 0 ? round($overallPresent / $overallServiceCount, 2) : 0.0,
            'communion_overall_average' => $overallServiceCount > 0 ? round($overallCommunion / $overallServiceCount, 2) : 0.0,
            'has_data' => $overallServiceCount > 0,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $serviceRows
     */
    protected function buildParishSundayRawSeriesFromServiceRows(array $serviceRows): array
    {
        $labels = [];
        $presentCounts = [];
        $communionCounts = [];

        $locale = (string) ($this->request->getLocale() ?: config('App')->defaultLocale);
        $dateFormatter = class_exists(\IntlDateFormatter::class)
            ? new \IntlDateFormatter($locale, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE, date_default_timezone_get(), null, 'MMM d')
            : null;

        foreach ($serviceRows as $index => $row) {
            $serviceDate = trim((string) ($row['service_date'] ?? ''));
            if ($serviceDate === '') {
                continue;
            }

            $dateObject = \DateTimeImmutable::createFromFormat('Y-m-d', $serviceDate);
            if (!$dateObject) {
                continue;
            }

            $dateLabel = $dateFormatter
                ? (string) ($dateFormatter->format($dateObject->getTimestamp()) ?: $dateObject->format('M d'))
                : $dateObject->format('M d');

            $serviceTime = trim((string) ($row['service_time'] ?? ''));
            if ($serviceTime !== '') {
                $timeObject = \DateTimeImmutable::createFromFormat('H:i:s', $serviceTime);
                $timeLabel = $timeObject ? $timeObject->format('g:i A') : substr($serviceTime, 0, 5);
                $labels[] = $dateLabel . ' ' . $timeLabel;
            } else {
                // Keep labels unique if multiple services share the same date and time is unavailable.
                $labels[] = $dateLabel . ' #' . ((int) $index + 1);
            }

            $presentCounts[] = (float) ($row['present_count'] ?? 0);
            $communionCounts[] = (float) ($row['communion_count'] ?? 0);
        }

        $rowCount = count($presentCounts);

        return [
            'labels' => $labels,
            // Keep existing keys for compatibility with current view bindings.
            'sunday_averages' => $presentCounts,
            'communion_averages' => $communionCounts,
            'sunday_overall_average' => 0.0,
            'communion_overall_average' => 0.0,
            'has_data' => $rowCount > 0,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    protected function buildDiocesanSundayTrendFromPeriodRows(array $rows, string $periodColumn, string $granularity): array
    {
        $labels = [];
        $sundayAverages = [];
        $communionAverages = [];

        $locale = (string) ($this->request->getLocale() ?: config('App')->defaultLocale);
        $formatterPattern = $granularity === 'week' ? 'MMM d' : 'MMM y';
        $formatter = class_exists(\IntlDateFormatter::class)
            ? new \IntlDateFormatter($locale, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE, date_default_timezone_get(), null, $formatterPattern)
            : null;

        $overallServiceCount = 0;
        $overallPresent = 0.0;
        $overallCommunion = 0.0;

        foreach ($rows as $row) {
            $periodValue = trim((string) ($row[$periodColumn] ?? ''));
            if ($periodValue === '') {
                continue;
            }

            if ($granularity === 'week') {
                $bucketDate = \DateTimeImmutable::createFromFormat('Y-m-d', $periodValue);
            } else {
                $bucketDate = \DateTimeImmutable::createFromFormat('Y-m-d', $periodValue)
                    ?: \DateTimeImmutable::createFromFormat('Y-m', $periodValue);
            }

            if (!$bucketDate) {
                continue;
            }

            if ($granularity === 'week') {
                $weekDateLabel = $formatter
                    ? (string) ($formatter->format($bucketDate->getTimestamp()) ?: $bucketDate->format('M d'))
                    : $bucketDate->format('M d');
                $labels[] = lang('Dashboard.weekOfLabel', [$weekDateLabel]);
            } else {
                $labels[] = $formatter
                    ? (string) ($formatter->format($bucketDate->getTimestamp()) ?: $bucketDate->format('M Y'))
                    : $bucketDate->format('M Y');
            }

            $bucketServiceCount = (int) ($row['sunday_services_count'] ?? 0);
            $bucketPresent = (float) ($row['sunday_attended_total'] ?? 0.0);
            $bucketCommunion = (float) ($row['sunday_communion_total'] ?? 0.0);

            $sundayAverages[] = $bucketServiceCount > 0 ? round($bucketPresent / $bucketServiceCount, 2) : 0.0;
            $communionAverages[] = $bucketServiceCount > 0 ? round($bucketCommunion / $bucketServiceCount, 2) : 0.0;

            $overallServiceCount += $bucketServiceCount;
            $overallPresent += $bucketPresent;
            $overallCommunion += $bucketCommunion;
        }

        return [
            'labels' => $labels,
            'sunday_averages' => $sundayAverages,
            'communion_averages' => $communionAverages,
            'sunday_overall_average' => $overallServiceCount > 0 ? round($overallPresent / $overallServiceCount, 2) : 0.0,
            'communion_overall_average' => $overallServiceCount > 0 ? round($overallCommunion / $overallServiceCount, 2) : 0.0,
            'has_data' => $overallServiceCount > 0,
        ];
    }

    /**
     * @return array{start_date: string, end_date: string, start_month: string, end_month: string}|null
     */
    protected function resolveAttendancePeriodRange(string $period, ?string $customStartDate, ?string $customEndDate): ?array
    {
        $period = strtolower(trim($period));

        switch ($period) {
            case 'this_month':
                $start = date('Y-m-01');
                $end = date('Y-m-t');
                break;

            case 'this_year':
                $year = date('Y');
                $start = $year . '-01-01';
                $end = date('Y-m-d');
                break;

            case 'last_month':
                $start = date('Y-m-01', strtotime('first day of last month'));
                $end = date('Y-m-t', strtotime('last day of last month'));
                break;

            case 'last_year':
                $year = (string) ((int) date('Y') - 1);
                $start = $year . '-01-01';
                $end = $year . '-12-31';
                break;

            case 'custom_range':
                $start = $this->isValidYmd($customStartDate) ? (string) $customStartDate : date('Y-01-01');
                $end = $this->isValidYmd($customEndDate) ? (string) $customEndDate : date('Y-m-d');
                if ($start > $end) {
                    return null;
                }
                break;

            default:
                return null;
        }

        return [
            'start_date' => $start,
            'end_date' => $end,
            'start_month' => date('Y-m-01', strtotime($start)),
            'end_month' => date('Y-m-01', strtotime($end)),
        ];
    }

    protected function isValidYmd(?string $value): bool
    {
        if ($value === null || trim($value) === '') {
            return false;
        }

        $value = trim($value);
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $dt !== false && $dt->format('Y-m-d') === $value;
    }

    /**
     * AJAX endpoint — guest follow-up KPI stats for the parish admin dashboard.
     * GET /dashboard/followup-stats
     *
     * Returns JSON:
     *   guests_in_followup  int  Distinct guests with at least one pending followup-log entry.
     *   call_tasks_today    int  Pending call tasks scheduled on or before today.
     *   failed_messages_7d  int  SMS queue rows with status=failed in the last 7 days.
     *   new_guests_weekly   array  [ {week: "Apr 7", count: N}, … ] last 12 ISO weeks.
     */
    public function followupStats()
    {
        $parishId = $this->getOperationalParishId();
        if (!$parishId) {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'No parish scope']);
        }
        $parishId = (int) $parishId;

        $db = db_connect();

        // 1. Distinct people with at least one pending followup log entry.
        $guestsInFollowup = (int) ($db->table('followup_log gfl')
            ->select('COUNT(DISTINCT gfl.person_id) AS cnt')
            ->where('gfl.status', 'pending')
            ->where('gfl.parish_id', $parishId)
            ->get()->getRowArray()['cnt'] ?? 0);

        // 2. Pending call tasks scheduled on or before today.
        $callTasksToday = (int) $db->table('call_task')
            ->where('parish_id', $parishId)
            ->where('status', 'pending')
            ->where('scheduled_date <=', date('Y-m-d'))
            ->countAllResults();

        // 3. Failed SMS entries in the last 7 days.
        $failedMessages7d = (int) $db->table('sms_queue')
            ->where('parish_id', $parishId)
            ->where('status', 'failed')
            ->where('created_at >=', date('Y-m-d H:i:s', strtotime('-7 days')))
            ->countAllResults();

        // 4. New guests per ISO week — last 12 weeks (canonical first visits).
        $cutoff = date('Y-m-d', strtotime('-12 weeks'));
        $rows = $db->table('person_visit')
            ->select('YEARWEEK(visit_date, 3) AS yw, MIN(visit_date) AS week_start, COUNT(*) AS count')
            ->where('parish_id', $parishId)
            ->where('visit_type', 'first')
            ->where('visit_date >=', $cutoff)
            ->groupBy('yw')
            ->orderBy('yw', 'ASC')
            ->get()
            ->getResultArray();

        $weeklyGuests = array_map(static function (array $row): array {
            $label = date('M j', strtotime((string) $row['week_start']));
            return ['week' => $label, 'count' => (int) $row['count']];
        }, $rows);

        return $this->response->setJSON([
            'success' => true,
            'guests_in_followup' => $guestsInFollowup,
            'call_tasks_today' => $callTasksToday,
            'failed_messages_7d' => $failedMessages7d,
            'new_guests_weekly' => $weeklyGuests,
        ]);
    }

    /**
     * Switch parish — province admin only.
     * The dioceseadmin branch has moved to Diocesan\Controllers\Dashboard::switchParish().
     */
    public function switchParish($parishId = null)
    {
        if (!$this->isProvinceAdmin()) {
            return redirect()->to('/dashboard')->with('error', lang('App.errorUnauthorized'));
        }

        if (!$parishId) {
            return redirect()->to('/province/dashboard');
        }

        $parishModel = model('ParishModel');
        $parish = $parishModel->findWithHierarchy((int) $parishId);

        if (!$parish) {
            return redirect()->to('/province/dashboard')->with('error', lang('App.parishNotFound'));
        }

        $adminProvinceId = $this->resolveAdminProvinceId();
        if ($adminProvinceId <= 0 || (int) ($parish['province_id'] ?? 0) !== $adminProvinceId) {
            return redirect()->to('/province/dashboard')->with('error', lang('App.parishOutsideProvince'));
        }

        $this->setOperationalParishId($parishId);

        return redirect()->to('/province/dashboard')->with('success', lang('App.parishSwitchedSuccess'));
    }

    private function resolveExistingProfileTable($db): ?string
    {
        $prefix = (string) $db->getPrefix();
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
