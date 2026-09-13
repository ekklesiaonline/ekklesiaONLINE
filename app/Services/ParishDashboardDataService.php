<?php

namespace App\Services;

use App\Libraries\PeopleMetricsService;

/**
 * Assembles parish-level KPI data for the parish admin dashboard.
 *
 * Extracts the inline query-builder logic that was previously in
 * MemberPortal\Controllers\Dashboard::index() into a testable service.
 */
class ParishDashboardDataService
{
    /**
     * Assemble all parish-level dashboard data for a given parish.
     *
     * @return array{
     *   people_metrics: array<string, mixed>,
     *   status_counts: array<int, array<string, mixed>>,
     *   total_families: int,
     *   birthdays_this_week: int,
     *   pending_followups_this_month: int,
     *   done_followups_this_month: int,
     *   events_this_month: int,
     *   recent_services: array<int, array<string, mixed>>,
     *   recent_guests: array<int, array<string, mixed>>,
     *   sunday_attendance_trend: array<string, mixed>,
     *   pending_registrations_count: int,
     * }
     */
    public function assemble(int $parishId, ?int $userId = null, bool $canModerateRegistrations = false): array
    {
        $db = db_connect();

        // People metrics
        $peopleMetricsService = new PeopleMetricsService();
        $peopleMetrics = $peopleMetricsService->getParishSummary($parishId, (int) date('Y'));

        // Status counts
        $statusCounts = model('ProfileModel')->getStatusCounts($parishId);

        // Total families
        $totalFamilies = (int) $db->table('family f')
            ->select('COUNT(DISTINCT f.id) as count', false)
            ->join('profile p', 'p.user_id = f.head_of_household_id', 'inner')
            ->where('p.parish_id', $parishId)
            ->where('f.deleted_at', null)
            ->get()
            ->getRow()
            ->count;

        // Date ranges
        $now = new \DateTimeImmutable('now');
        $weekStart = $now->modify('monday this week')->setTime(0, 0, 0);
        $weekEnd = $weekStart->modify('+6 days')->setTime(23, 59, 59);
        $monthStart = $now->modify('first day of this month')->setTime(0, 0, 0);
        $monthEnd = $now->modify('last day of this month')->setTime(23, 59, 59);

        // Birthdays this week
        $birthdaysThisWeek = $this->countBirthdaysThisWeek($db, $parishId, $weekStart, $weekEnd);

        // Pending follow-ups this month
        $pendingFollowUpsThisMonth = (int) ($db->table('followup_log gfl')
            ->select('COUNT(DISTINCT gfl.id) AS cnt')
            ->where('gfl.parish_id', $parishId)
            ->where('gfl.status', 'pending')
            ->where('gfl.scheduled_date >=', $monthStart->format('Y-m-d'))
            ->where('gfl.scheduled_date <=', $monthEnd->format('Y-m-d'))
            ->get()->getRowArray()['cnt'] ?? 0);

        // Completed follow-ups this month
        $completedFollowUpsRow = $db->table('call_task ct')
            ->select('COUNT(DISTINCT ct.id) AS task_count')
            ->where('ct.parish_id', $parishId)
            ->where('ct.status', 'completed')
            ->where('ct.updated_at >=', $monthStart->format('Y-m-d H:i:s'))
            ->where('ct.updated_at <=', $monthEnd->format('Y-m-d H:i:s'))
            ->get()
            ->getRowArray();
        $doneFollowUpsThisMonth = (int) ($completedFollowUpsRow['task_count'] ?? 0);

        // Events this month
        $eventsThisMonth = (int) $db->table('event')
            ->where('parish_id', $parishId)
            ->where('start_at >=', $monthStart->format('Y-m-d H:i:s'))
            ->where('start_at <=', $monthEnd->format('Y-m-d H:i:s'))
            ->countAllResults();

        // Recent services
        $recentServices = model('ServiceModel')->getParishServices($parishId, 5);

        // Recent guests
        $recentGuests = model('GuestModel')->getRecentGuests($parishId, 5);

        // Pending registrations count
        $pendingRegistrationsCount = 0;
        if ($canModerateRegistrations) {
            $pendingRegistrationsCount = count(model('UserRegistrationRequestModel')->getPendingByParish($parishId, 50));
        }

        return [
            'people_metrics'                => $peopleMetrics,
            'status_counts'                 => $statusCounts,
            'total_families'                => $totalFamilies,
            'birthdays_this_week'           => $birthdaysThisWeek,
            'pending_followups_this_month'  => $pendingFollowUpsThisMonth,
            'done_followups_this_month'     => $doneFollowUpsThisMonth,
            'events_this_month'             => $eventsThisMonth,
            'recent_services'               => $recentServices,
            'recent_guests'                 => $recentGuests,
            'pending_registrations_count'   => $pendingRegistrationsCount,
        ];
    }

    /**
     * Count members with birthdays in the current week, excluding province/diocese admins.
     */
    private function countBirthdaysThisWeek($db, int $parishId, \DateTimeImmutable $weekStart, \DateTimeImmutable $weekEnd): int
    {
        $profileTable = $this->resolveExistingProfileTable($db);
        if ($profileTable === null) {
            return 0;
        }

        $birthdayRows = $db->table('parish_affiliation mca')
            ->select('p.birth_date')
            ->join($profileTable . ' p', 'p.user_id = mca.linked_user_id')
            ->join('users u', 'u.id = mca.linked_user_id')
            ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("provinceadmin","diocesanadmin")', 'left')
            ->where('mca.parish_id', $parishId)
            ->where('mca.is_member', 1)
            ->where('p.birth_date IS NOT NULL')
            ->where('agu.id IS NULL')
            ->get()
            ->getResultArray();

        $count = 0;
        foreach ($birthdayRows as $row) {
            $birthDateValue = (string) ($row['birth_date'] ?? '');
            if ($birthDateValue === '') {
                continue;
            }

            $birthTimestamp = strtotime($birthDateValue);
            if ($birthTimestamp === false) {
                continue;
            }

            $monthDay = date('m-d', $birthTimestamp);
            $startMonthDay = $weekStart->format('m-d');
            $endMonthDay = $weekEnd->format('m-d');

            if ($startMonthDay <= $endMonthDay) {
                if ($monthDay >= $startMonthDay && $monthDay <= $endMonthDay) {
                    $count++;
                }
                continue;
            }

            if ($monthDay >= $startMonthDay || $monthDay <= $endMonthDay) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Resolve which profile table exists (profile or a snapshot).
     */
    private function resolveExistingProfileTable($db): ?string
    {
        $candidates = ['profile'];
        foreach ($candidates as $table) {
            if ($db->tableExists($table)) {
                return $table;
            }
        }
        return null;
    }
}
