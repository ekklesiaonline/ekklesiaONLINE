<?php

namespace Parish\Controllers;

use App\Controllers\BaseController;
use App\Services\ParishDashboardDataService;

/**
 * Parish admin dashboard — full parish CRUD overview.
 *
 * Replaces the parish-admin portion of MemberPortal\Controllers\Dashboard.
 */
class Dashboard extends BaseController
{
    public function index()
    {
        if (!auth()->loggedIn()) {
            return redirect()->to('/login');
        }

        $user = auth()->user();

        if (!$this->canLandOnDashboard($user)) {
            return redirect()->to('/profile')->with('error', lang('Auth.notEnoughPrivilege'));
        }

        $churchId = $this->getOperationalParishId();

        if (!$churchId) {
            return view('Parish\Views\Dashboard\no_parish');
        }

        $canModerateRegistrations = $user->inGroup('parishadmin') && $user->can('member.edit');

        $service = new ParishDashboardDataService();
        $data    = $service->assemble((int) $churchId, (int) $user->id, $canModerateRegistrations);
        $metrics = $data['people_metrics'];

        // Sunday attendance trend
        $sundayAttendanceTrend = $this->getParishSundayAttendanceAverageTrend(
            (int) $churchId,
            'this_year',
            null,
            null,
            'week'
        );

        $viewData = array_merge($this->getCommonViewData(lang('Dashboard.parishDashboard')), [
            'total_people'                        => (int) ($metrics['total_people'] ?? 0),
            'total_members'                       => (int) ($metrics['total_members'] ?? 0),
            'active_members'                      => (int) ($metrics['active_members'] ?? 0),
            'good_standing_members'               => (int) ($metrics['good_standing_members'] ?? 0),
            'leadership_members'                  => (int) ($metrics['leadership_members'] ?? 0),
            'total_guests'                        => (int) ($metrics['total_guests'] ?? 0),
            'total_families'                      => $data['total_families'],
            'birthdays_this_week'                 => $data['birthdays_this_week'],
            'pending_followups_this_month'        => $data['pending_followups_this_month'],
            'done_followups_this_month_admins'    => $data['done_followups_this_month'],
            'events_this_month'                   => $data['events_this_month'],
            'status_counts'                       => $data['status_counts'],
            'can_moderate_registrations'          => $canModerateRegistrations,
            'pending_registrations_count'         => $data['pending_registrations_count'],
            'recent_services'                     => $data['recent_services'],
            'recent_guests'                       => $data['recent_guests'],
            'sunday_attendance_labels'            => $sundayAttendanceTrend['labels'],
            'sunday_attendance_averages'          => $sundayAttendanceTrend['sunday_averages'],
            'communion_attendance_averages'       => $sundayAttendanceTrend['communion_averages'],
            'sunday_attendance_overall_average'   => (float) ($sundayAttendanceTrend['sunday_overall_average'] ?? 0.0),
            'communion_attendance_overall_average' => (float) ($sundayAttendanceTrend['communion_overall_average'] ?? 0.0),
            'sunday_attendance_has_data'          => (bool) ($sundayAttendanceTrend['has_data'] ?? false),
        ]);

        return view('Parish\Views\Dashboard\index', $viewData);
    }

    /**
     * Parish Sunday attendance trend from service_metric data.
     */
    protected function getParishSundayAttendanceAverageTrend(
        int $churchId,
        string $period = 'this_year',
        ?string $customStartDate = null,
        ?string $customEndDate = null,
        string $granularity = 'week'
    ): array {
        $db = db_connect();
        $range = $this->resolveAttendancePeriodRange($period, $customStartDate, $customEndDate);

        if ($range === null) {
            return [
                'labels' => [],
                'sunday_averages' => [],
                'communion_averages' => [],
                'sunday_overall_average' => 0.0,
                'communion_overall_average' => 0.0,
                'has_data' => false,
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
            [$churchId, $range['start_date'], $range['end_date']]
        )->getResultArray();

        return $this->buildParishSundayRawSeriesFromServiceRows($serviceRows);
    }

    /**
     * Resolve the date range for an attendance period.
     *
     * @return array{start_date: string, end_date: string, start_month: string, end_month: string}|null
     */
    protected function resolveAttendancePeriodRange(string $period, ?string $customStart, ?string $customEnd): ?array
    {
        $thisYearStart = date('Y-01-01');
        $thisYearEnd = date('Y-12-31');

        switch ($period) {
            case 'this_year':
                return [
                    'start_date' => $thisYearStart,
                    'end_date' => $thisYearEnd,
                    'start_month' => date('Y-01'),
                    'end_month' => date('Y-12'),
                ];
            case 'custom_range':
                $start = $customStart ?: $thisYearStart;
                $end = $customEnd ?: date('Y-m-d');
                return [
                    'start_date' => $start,
                    'end_date' => $end,
                    'start_month' => substr($start, 0, 7),
                    'end_month' => substr($end, 0, 7),
                ];
            default:
                return null;
        }
    }

    /**
     * Build raw Sunday series from service rows.
     *
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
                $labels[] = $dateLabel . ' #' . ((int) $index + 1);
            }

            $presentCounts[] = (float) ($row['present_count'] ?? 0);
            $communionCounts[] = (float) ($row['communion_count'] ?? 0);
        }

        $rowCount = count($presentCounts);

        return [
            'labels' => $labels,
            'sunday_averages' => $presentCounts,
            'communion_averages' => $communionCounts,
            'sunday_overall_average' => 0.0,
            'communion_overall_average' => 0.0,
            'has_data' => $rowCount > 0,
        ];
    }
}
