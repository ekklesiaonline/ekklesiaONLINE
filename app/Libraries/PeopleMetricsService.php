<?php

namespace App\Libraries;

use App\Models\GuestModel;
use App\Models\LeadershipPositionModel;
use App\Models\MemberParishAffiliationModel;

class PeopleMetricsService
{
    public function getParishSummary(int $parishId, ?int $year = null): array
    {
        $year ??= (int) date('Y');

        $guestModel = model(GuestModel::class);
        $affiliationModel = model(MemberParishAffiliationModel::class);
        $leadershipModel = model(LeadershipPositionModel::class);
        $hasIsMember = $this->hasAffiliationMemberTag();

        $goodStandingBuilder = db_connect()->table('member_standing_summary mss')
            ->select('COUNT(DISTINCT mss.user_id) AS count', false)
            ->join('parish_affiliation mca', 'mca.linked_user_id = mss.user_id', 'inner')
            ->join('profile p', 'p.user_id = mss.user_id', 'inner')
            ->join('users u', 'u.id = mss.user_id', 'inner')
            ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("provinceadmin","dioceseadmin")', 'left')
            ->where('mca.parish_id', $parishId)
            ->where('mca.is_member', 1)
            ->where('mca.membership_status', 'member')
            ->where('mss.year', $year)
            ->where('mss.is_good_standing', 1)
            ->where('agu.id IS NULL');

        if ($hasIsMember) {
            $goodStandingBuilder->where('mca.is_member', 1);
        }

        $goodStandingRow = $goodStandingBuilder->get()->getRowArray();

        $totalUsers = $affiliationModel->countDistinctAffiliatedPeople($parishId);
        $legacyGuestCount = $guestModel->getParishGuestCount($parishId, true);
        $personVisitorCount = $this->countPersonVisitors($parishId);

        $totalGuests = $legacyGuestCount + $personVisitorCount;
        $totalPeople = $affiliationModel->countDistinctPeople($parishId);

        return [
            'total_users' => $totalUsers,
            'total_guests' => $totalGuests,
            'total_people' => $totalPeople,
            'total_members' => $affiliationModel->countDistinctMembers($parishId),
            'active_members' => $affiliationModel->countDistinctMembers($parishId, ['member']),
            'inactive_members' => $affiliationModel->countDistinctMembers($parishId, ['inactive']),
            'good_standing_members' => (int) ($goodStandingRow['count'] ?? 0),
            'leadership_members' => $leadershipModel->countActiveForParish($parishId),
            'year' => $year,
        ];
    }

    private function hasAffiliationMemberTag(): bool
    {
        try {
            $db = db_connect();
            $columns = $db->getFieldNames($db->getPrefix() . 'parish_affiliation');
            if ($columns === []) {
                $columns = $db->getFieldNames('parish_affiliation');
            }
        } catch (\Throwable $e) {
            $columns = [];
        }

        return in_array('is_member', array_map('strtolower', $columns), true);
    }
    private function countPersonVisitors(int $parishId): int
    {
        try {
            return (int) db_connect()->table('parish_affiliation mca')
                ->select('COUNT(DISTINCT mca.person_id) AS count', false)
                ->join('person p', 'p.id = mca.person_id', 'inner')
                ->where('mca.parish_id', $parishId)
                ->where('mca.affiliation_type', 'guest')
                ->where('mca.deleted_at', null)
                ->get()
                ->getRowArray()['count'] ?? 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
