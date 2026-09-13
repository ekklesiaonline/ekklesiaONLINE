<?php

namespace App\Services;

use App\Models\AnnualPledgeModel;
use App\Models\AttendanceModel;
use App\Models\DonationModel;
use App\Models\FamilyMemberModel;
use App\Models\FamilyModel;
use App\Models\LeadershipPositionModel;
use App\Models\ParishModel;
use App\Models\PersonMembershipDetailsModel;
use App\Models\ProfileModel;
use App\Models\SacramentalRecordModel;

/**
 * Assembles all personal-data sections for the /me dashboard.
 *
 * Wraps the existing per-person model/service methods so the controller
 * stays thin. Every section key is always present in the returned array,
 * even when the underlying data is empty (callers can rely on key existence).
 */
class MemberDashboardDataService
{
    private ProfileModel $profileModel;
    private ParishModel $parishModel;
    private PersonMembershipDetailsService $membershipDetails;
    private PersonStatusHistoryService $statusHistory;
    private PersonOnboardingService $onboarding;
    private LeadershipPositionModel $leadershipModel;
    private SacramentalRecordModel $sacramentModel;
    private AnnualPledgeModel $pledgeModel;
    private DonationModel $donationModel;
    private AttendanceModel $attendanceModel;
    private FamilyMemberModel $familyMemberModel;
    private FamilyModel $familyModel;

    public function __construct(
        ?ProfileModel $profileModel = null,
        ?ParishModel $parishModel = null,
        ?PersonMembershipDetailsService $membershipDetails = null,
        ?PersonStatusHistoryService $statusHistory = null,
        ?PersonOnboardingService $onboarding = null,
        ?LeadershipPositionModel $leadershipModel = null,
        ?SacramentalRecordModel $sacramentModel = null,
        ?AnnualPledgeModel $pledgeModel = null,
        ?DonationModel $donationModel = null,
        ?AttendanceModel $attendanceModel = null,
        ?FamilyMemberModel $familyMemberModel = null,
        ?FamilyModel $familyModel = null
    ) {
        $this->profileModel       = $profileModel ?? model(ProfileModel::class);
        $this->parishModel        = $parishModel ?? model(ParishModel::class);
        $this->membershipDetails  = $membershipDetails ?? new PersonMembershipDetailsService();
        $this->statusHistory      = $statusHistory ?? new PersonStatusHistoryService();
        $this->onboarding         = $onboarding ?? new PersonOnboardingService();
        $this->leadershipModel    = $leadershipModel ?? model(LeadershipPositionModel::class);
        $this->sacramentModel     = $sacramentModel ?? model(SacramentalRecordModel::class);
        $this->pledgeModel        = $pledgeModel ?? model(AnnualPledgeModel::class);
        $this->donationModel      = $donationModel ?? model(DonationModel::class);
        $this->attendanceModel    = $attendanceModel ?? model(AttendanceModel::class);
        $this->familyMemberModel  = $familyMemberModel ?? model(FamilyMemberModel::class);
        $this->familyModel        = $familyModel ?? model(FamilyModel::class);
    }

    /**
     * Assemble all 8 personal-data sections for a user.
     *
     * @return array{
     *   identity: array<string, mixed>,
     *   membership: array<string, mixed>,
     *   status_history: array<int, array<string, mixed>>,
     *   sacraments: array<int, array<string, mixed>>,
     *   leadership: array<int, array<string, mixed>>,
     *   family: array<string, mixed>|null,
     *   family_members: array<int, array<string, mixed>>,
     *   pledges: array<int, array<string, mixed>>,
     *   donations: array<int, array<string, mixed>>,
     *   attendance_summary: array<string, mixed>,
     *   attendance_recent: array<int, array<string, mixed>>,
     *   onboarding: array<string, mixed>|null,
     * }
     */
    public function assemble(int $userId, int $personId, int $parishId): array
    {
        // 1. Identity
        $identity = $this->resolveIdentity($userId, $personId, $parishId);

        // 2. Membership status + sacrament flags
        $membership = $this->membershipDetails->forPerson($personId, $parishId);

        // 3. Status history timeline
        $statusHistory = $this->statusHistory->historyForPerson($personId, null, $parishId);

        // 4. Sacramental records
        $sacraments = $this->sacramentModel->getRecordsForPerson($personId, $parishId);

        // 5. Leadership positions
        $leadership = $this->leadershipModel->getAllPositionsForUser($userId);

        // 6. Family
        [$family, $familyMembers] = $this->resolveFamily($userId, $parishId);

        // 7. Pledges + giving history
        $pledges  = $this->pledgeModel->getMemberPledges($userId, $parishId);
        $donations = $this->donationModel->getMemberDonationsForParish($userId, $parishId);

        // 8. Attendance
        $attendanceSummary = $this->resolveAttendanceSummary($userId, $parishId);
        $attendanceRecent  = $this->attendanceModel->getMemberAttendance($userId, 10);

        // Onboarding milestones
        $onboarding = $this->onboarding->forPerson($personId, $parishId);

        // Sacrament/membership flag discrepancies (sacramental_record is canonical).
        $sacramentDiscrepancies = (new SacramentFlagSyncService())->discrepanciesForPerson($personId, $parishId);

        return [
            'identity'           => $identity,
            'membership'         => $membership,
            'status_history'     => $statusHistory,
            'sacraments'         => $sacraments,
            'leadership'         => $leadership,
            'family'             => $family,
            'family_members'     => $familyMembers,
            'pledges'            => $pledges,
            'donations'          => $donations,
            'attendance_summary' => $attendanceSummary,
            'attendance_recent'  => $attendanceRecent,
            'onboarding'         => $onboarding,
            'sacrament_discrepancies' => $sacramentDiscrepancies,
        ];
    }

    /**
     * Resolve identity card data: profile + parish name.
     *
     * @return array<string, mixed>
     */
    private function resolveIdentity(int $userId, int $personId, int $parishId): array
    {
        $profile = $this->profileModel->where('user_id', $userId)->first();

        if (!$profile) {
            // Fallback: try by person_id
            $profile = $this->profileModel->find($personId);
        }

        $parish = $this->parishModel->find($parishId);

        return [
            'profile'      => $profile ?? [],
            'parish_name'  => $parish['name'] ?? '',
            'parish_id'    => $parishId,
            'person_id'    => $personId,
            'user_id'      => $userId,
        ];
    }

    /**
     * Resolve the user's family and its members.
     *
     * Builds the query directly rather than using FamilyModel::getFamilyWithMembers
     * to avoid the session-based currentParishId() dependency.
     *
     * @return array{0: array<string, mixed>|null, 1: array<int, array<string, mixed>>}
     */
    private function resolveFamily(int $userId, int $parishId): array
    {
        $userFamily = $this->familyMemberModel->getUserFamily($userId);

        if (!$userFamily) {
            return [null, []];
        }

        $familyId = (int) ($userFamily['id'] ?? 0);
        if ($familyId <= 0) {
            return [null, []];
        }

        // Only return the family if it belongs to the user's parish.
        if ((int) ($userFamily['parish_id'] ?? 0) !== $parishId) {
            return [null, []];
        }

        $db = db_connect();

        $members = $db->table('family_member fm')
            ->select('fm.relationship, p.user_id, p.first_name, p.last_name, p.birth_date, p.profile_picture')
            ->join('profile p', 'p.user_id = fm.user_id', 'left')
            ->where('fm.family_id', $familyId)
            ->orderBy('FIELD(fm.relationship, "Head", "Spouse", "Child", "Parent", "Sibling", "Other")', '', false)
            ->get()
            ->getResultArray();

        return [$userFamily, $members];
    }

    /**
     * Resolve the user's year-to-date attendance summary.
     *
     * @return array{attended_count: int, total_services: int, communion_count: int}
     */
    private function resolveAttendanceSummary(int $userId, int $parishId): array
    {
        $yearStart = date('Y-01-01');
        $yearEnd   = date('Y-12-31');

        $summary = $this->attendanceModel->getMemberAttendanceSummary($userId, $parishId, $yearStart, $yearEnd);

        $communionCount = $this->attendanceModel->getCommunionCountForMemberYear($userId, $parishId, (int) date('Y'));

        return [
            'attended_count'  => (int) ($summary['attended_count'] ?? 0),
            'total_services'  => (int) ($summary['total_services'] ?? 0),
            'communion_count' => (int) ($communionCount ?? 0),
        ];
    }
}
