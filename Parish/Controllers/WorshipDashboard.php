<?php

namespace Parish\Controllers;

use App\Controllers\BaseController;

/**
 * Parish worship dashboard — dedicated clergy landing page.
 *
 * Surfaces services, attendance, worship planning, and a people/follow-up
 * widget, scoped to the clergy user's parish.
 */
class WorshipDashboard extends BaseController
{
    public function index()
    {
        if (!auth()->loggedIn()) {
            return redirect()->to('/login');
        }

        $parishId = $this->getOperationalParishId();

        if (!$parishId) {
            return redirect()->to('/profile')->with('error', lang('App.errorNotFound'));
        }

        $parishId = (int) $parishId;
        $db = db_connect();

        // Recent services
        $recentServices = model('ServiceModel')->getParishServices($parishId, 5);

        // Pending follow-ups this month
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');
        $pendingFollowUps = (int) ($db->table('followup_log')
            ->where('parish_id', $parishId)
            ->where('status', 'pending')
            ->where('scheduled_date >=', $monthStart)
            ->where('scheduled_date <=', $monthEnd)
            ->countAllResults());

        // Recent guests
        $recentGuests = (new \App\Services\GuestDirectoryService())->recentForParish($parishId, 5);

        // Call tasks today
        $callTasksToday = (int) $db->table('call_task')
            ->where('parish_id', $parishId)
            ->where('status !=', 'completed')
            ->where('scheduled_date', date('Y-m-d'))
            ->countAllResults();

        // Sunday attendance trend (current year, weekly)
        $sundayAttendanceTrend = $this->getParishSundayAttendanceTrend($parishId);

        $data = array_merge($this->getCommonViewData(lang('Dashboard.parishWorshipDashboard')), [
            'parish_id' => $parishId,
            'recent_services' => $recentServices,
            'pending_followups' => $pendingFollowUps,
            'recent_guests' => $recentGuests,
            'call_tasks_today' => $callTasksToday,
            'sunday_attendance_labels' => $sundayAttendanceTrend['labels'],
            'sunday_attendance_averages' => $sundayAttendanceTrend['sunday_averages'],
            'communion_attendance_averages' => $sundayAttendanceTrend['communion_averages'],
            'sunday_attendance_has_data' => $sundayAttendanceTrend['has_data'],
        ]);

        return view('Parish\Views\Worship\index', $data);
    }

    /**
     * Get parish Sunday attendance trend for the current year.
     */
    protected function getParishSundayAttendanceTrend(int $parishId): array
    {
        $db = db_connect();
        $yearStart = date('Y-01-01');
        $yearEnd = date('Y-12-31');

        $serviceRows = $db->query(
            "SELECT s.id AS service_id,
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
             ORDER BY s.service_date ASC",
            [$parishId, $yearStart, $yearEnd]
        )->getResultArray();

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

            $labels[] = $dateLabel;
            $presentCounts[] = (float) ($row['present_count'] ?? 0);
            $communionCounts[] = (float) ($row['communion_count'] ?? 0);
        }

        return [
            'labels' => $labels,
            'sunday_averages' => $presentCounts,
            'communion_averages' => $communionCounts,
            'has_data' => count($presentCounts) > 0,
        ];
    }
}
