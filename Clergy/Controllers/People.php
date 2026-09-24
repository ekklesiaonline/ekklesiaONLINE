<?php

namespace Clergy\Controllers;

use App\Controllers\AdminHierarchyGuard;
use App\Controllers\BaseController;
use App\Models\MemberParishAffiliationModel;
use App\Services\AnnualPledgeService;
use App\Services\DonationService;
use App\Services\FamilyService;
use App\Services\LeadershipPositionService;
use App\Services\MemberAccessService;
use App\Services\PeopleDirectoryService;
use App\Services\PeopleViewDataService;
use App\Services\PersonMembershipDetailsService;
use App\Services\PersonProfileSyncService;
use App\Services\SacramentalRecordService;

class People extends BaseController
{
    use AdminHierarchyGuard;

    private MemberParishAffiliationModel $affiliationModel;
    private MemberAccessService $memberAccessService;
    private PeopleViewDataService $peopleViewDataService;
    private LeadershipPositionService $leadershipPositionService;
    private DonationService $donationService;
    private AnnualPledgeService $annualPledgeService;
    private SacramentalRecordService $sacramentalRecordService;
    private FamilyService $familyService;

    public function __construct()
    {
        $this->affiliationModel = model(MemberParishAffiliationModel::class);
        $this->memberAccessService = service('memberAccess');
        $this->peopleViewDataService = service('peopleViewData');
        $this->leadershipPositionService = new LeadershipPositionService();
        $this->donationService = new DonationService();
        $this->annualPledgeService = new AnnualPledgeService();
        $this->sacramentalRecordService = new SacramentalRecordService();
        $this->familyService = new FamilyService();
    }

    /**
     * Ensure the profile.person_id and user_person mapping are in sync
     * with the canonical person row. Delegates to PeopleViewDataService.
     *
     * @param int      $profileId The profile ID to repair (preferred).
     * @param int|null $userId    Optional: the user_id to look up by if
     *                            profileId is 0 (used by member-tag toggle).
     */
    private function ensureProfilePersonLink(int $profileId, ?int $userId = null): void
    {
        $this->peopleViewDataService->ensureProfilePersonLink($profileId, $userId);
    }

    /**
     * Enrich status history rows with the actor's display name.
     * Delegates to PeopleViewDataService.
     *
     * @param array<int, array<string, mixed>> $history
     * @return array<int, array<string, mixed>>
     */
    private function enrichStatusHistory(array $history): array
    {
        return $this->peopleViewDataService->enrichStatusHistory($history);
    }

    private function canManagePortalAccess(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        if ($user->can('member.edit')) {
            return true;
        }

        try {
            return $user->can('portal.manage');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function index()
    {
        if ($redirect = $this->requirePermission('member.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        if ($this->isProvinceAdmin()) {
            return redirect()->to('/members');
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return redirect()->to('/dashboard')->with('error', lang('Members.noParishSelected'));
        }

        $scope = (string) ($this->request->getGet('scope') ?? 'all');
        if (!in_array($scope, ['all', 'members', 'non_members', 'visitors'], true)) {
            $scope = 'all';
        }

        $status = (string) ($this->request->getGet('status') ?? '');
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = (int) ($this->request->getGet('per_page') ?? 25);
        if ($perPage < 10 || $perPage > 100) {
            $perPage = 25;
        }

        $allowedFilters = ['is_baptized', 'is_confirmed', 'is_communicant', 'is_dependent', 'is_under_16', 'has_pledge', 'is_leader'];
        $extraFilters = [];
        foreach ($allowedFilters as $filter) {
            $value = $this->request->getGet($filter);
            if ($value !== null && $value !== '') {
                $extraFilters[$filter] = (int) $value;
            }
        }

        $offset = ($page - 1) * $perPage;
        $peoplePage = (new PeopleDirectoryService())->listForParish($parishId, [
            'scope'         => $scope,
            'status'        => $status !== '' ? $status : null,
            'search'        => (string) ($this->request->getGet('search') ?? ''),
            'extra_filters' => $extraFilters,
            'limit'         => $perPage,
            'offset'        => $offset,
            'sort'          => 'p.last_name ASC, p.first_name ASC',
        ]);
        $peopleRows = $this->annotateRowsWithProfilePending($peoplePage['items'] ?? [], 'user_id', 'profile_id');
        $totalItems = (int) ($peoplePage['total'] ?? 0);
        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $pagerLinks = service('pager')->makeLinks($page, $perPage, $totalItems);

        $canModerateRegistrations = auth()->user()->inGroup('parishadmin') && auth()->user()->can('member.edit');
        $pendingRegistrations = [];
        if ($canModerateRegistrations) {
            $pendingRegistrations = model('UserRegistrationRequestModel')->getPendingByParish($parishId, 50);
        }
        $pendingRegistrationsCount = count($pendingRegistrations);
        $showProfilePendingIndicator = auth()->user()->can('member.edit')
            || auth()->user()->inGroup('parishadmin')
            || $this->isProvinceAdmin()
            || $this->isDioceseAdmin()
            || $this->isSystemAdmin();

        return view('Clergy\Views\People/index', array_merge(
            $this->getCommonViewData(lang('People.pageTitle')),
            [
                'people' => $peopleRows,
                'status_counts' => (new PeopleDirectoryService())->statusCountsForParish($parishId, $scope),
                'selected_scope' => $scope,
                'selected_status' => $status,
                'extra_filters' => $extraFilters,
                'per_page' => $perPage,
                'current_page' => $page,
                'total_items' => $totalItems,
                'total_pages' => $totalPages,
                'pager_links' => $pagerLinks,
                'can_moderate_registrations' => $canModerateRegistrations,
                'pending_registrations_count' => $pendingRegistrationsCount,
                'pending_registrations' => $pendingRegistrations,
                'show_profile_pending_indicator' => $showProfilePendingIndicator,
            ]
        ));
    }

    public function toggleMemberTag(int $userId)
    {
        if ($redirect = $this->requirePermission('member.edit')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        if ($this->isProvinceAdmin()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'PROVINCE_WRITE_DISABLED'), 403);
        }

        if ($code = $this->guardTargetUserMutation($userId)) {
            return $this->jsonResponse($this->apiErrorPayload($this->hierarchyGuardMessage($code), 403, 'TARGET_OUTRANKS_ACTOR'), 403);
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $isMember = (bool) ((int) $this->request->getPost('is_member') === 1);

        $this->ensureProfilePersonLink(0, $userId);

        $ok = $this->affiliationModel->setMemberTag($userId, $parishId, $isMember);
        if (!$ok) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('People.updateTagFailed'),
            ], 422);
        }

        $this->audit()->log('people.member_tag.update', [
            'parish_id' => $parishId,
            'entity_type' => 'person',
            'entity_id' => $userId,
            'new_values' => [
                'is_member' => $isMember ? 1 : 0,
            ],
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('People.updateTagSuccess'),
        ]);
    }

    public function bulkToggleMemberTag(): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('member.edit')) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FORBIDDEN'), 403);
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'NO_PARISH'), 403);
        }

        if ($this->isProvinceAdmin()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'PROVINCE_WRITE_DISABLED'), 403);
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $isMember = (bool) ((int) $this->request->getPost('is_member') === 1);
        $userIds = $this->request->getPost('user_ids');

        if (!is_array($userIds) || $userIds === []) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Members.bulkDeleteSelectAtLeastOne')], 422);
        }

        $userIds = array_filter(array_map('intval', $userIds), static fn(int $id): bool => $id > 0);
        if ($userIds === []) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Members.bulkDeleteSelectAtLeastOne')], 422);
        }

        $actorPriority = $this->getUserRolePriority((int) (auth()->id() ?? 0));
        $targetPriorities = $this->getUserRolePriorities($userIds);

        $updated = 0;
        foreach ($userIds as $uid) {
            if (($targetPriorities[$uid] ?? 0) > $actorPriority) {
                continue;
            }
            $this->ensureProfilePersonLink(0, $uid);
            if ($this->affiliationModel->setMemberTag($uid, $parishId, $isMember)) {
                $updated++;
            }
        }

        if ($updated > 0) {

            $this->audit()->log('people.member_tag.bulk_update', [
                'parish_id' => $parishId,
                'entity_type' => 'person',
                'new_values' => ['is_member' => $isMember ? 1 : 0, 'count' => $updated],
            ]);
        }

        if ($updated === 0) {
            return $this->jsonResponse(['success' => false, 'message' => lang('People.updateTagFailed')], 422);
        }

        return $this->jsonResponse(['success' => true, 'updated' => $updated]);
    }

    public function enablePortalAccess(int $userId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('member.view')) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FORBIDDEN'), 403);
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'PARISH_SCOPE_REQUIRED'), 403);
        }

        if ($this->isProvinceAdmin()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'PROVINCE_WRITE_DISABLED'), 403);
        }

        if (!$this->canManagePortalAccess()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FORBIDDEN'), 403);
        }

        if ($userId <= 0) {
            return $this->jsonResponse([
                'success' => false,
                'code' => 'VALIDATION_FAILED',
                'message' => 'Request validation failed.',
                'errors' => [[
                    'field' => 'user_id',
                    'code' => 'INVALID',
                    'message' => 'user_id must be greater than 0.',
                ]],
            ], 422);
        }

        if ($code = $this->guardTargetUserMutation($userId)) {
            return $this->jsonResponse($this->apiErrorPayload($this->hierarchyGuardMessage($code), 403, 'TARGET_OUTRANKS_ACTOR'), 403);
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'PARISH_SCOPE_REQUIRED'), 403);
        }

        $payload = $this->request->getJSON(true);
        if (!is_array($payload)) {
            $payload = $this->request->getPost();
        }

        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $validation = service('validation');
        $validation->setRules([
            'email' => 'required|valid_email',
        ]);

        if (!$validation->run(['email' => $email])) {
            return $this->jsonResponse([
                'success' => false,
                'code' => 'VALIDATION_FAILED',
                'message' => 'Request validation failed.',
                'errors' => array_map(static function (string $message, string $field): array {
                    return [
                        'field' => $field,
                        'code' => 'VALIDATION_FAILED',
                        'message' => $message,
                    ];
                }, $validation->getErrors(), array_keys($validation->getErrors())),
            ], 422);
        }

        $result = $this->memberAccessService->enablePortalAccess($userId, [
            'email' => $email,
        ], $parishId);

        if (!($result['ok'] ?? false)) {
            return $this->jsonResponse([
                'success' => false,
                'code' => (string) ($result['code'] ?? 'INTERNAL_ERROR'),
                'message' => (string) ($result['message'] ?? 'Request failed.'),
                'errors' => is_array($result['errors'] ?? null) ? $result['errors'] : [],
            ], (int) ($result['http_status'] ?? 500));
        }

        $this->audit()->log('portal_access.enable', [
            'parish_id' => $parishId,
            'entity_type' => 'person',
            'entity_id' => $userId,
            'new_values' => [
                'portal_access' => 'invited',
                'identity_type' => 'email_password',
                'requires_password_reset' => 1,
            ],
        ]);

        return $this->jsonResponse([
            'success' => true,
            'code' => (string) ($result['code'] ?? 'PORTAL_ACCESS_ENABLED'),
            'message' => (string) ($result['message'] ?? 'Portal invite email sent successfully. Access activates after password setup.'),
            'data' => is_array($result['data'] ?? null) ? $result['data'] : [],
        ]);
    }

    public function export()
    {
        if ($redirect = $this->requirePermission('member.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return redirect()->to('/people');
        }

        $userIds = $this->request->getPost('user_ids');
        if (!is_array($userIds) || $userIds === []) {
            return redirect()->to('/people')->with('error', lang('People.noSelectionForExport'));
        }

        $userIds = array_map('intval', $userIds);

        $rows = (new PeopleDirectoryService())->peopleForExport($parishId, $userIds);

        $csvLines = [];
        $csvLines[] = implode(',', ['First Name', 'Last Name', 'Email', 'Phone', 'Membership Status', 'Is Member', 'Good Standing', 'Portal Access']);

        foreach ($rows as $row) {
            $csvLines[] = implode(',', array_map(
                static fn(mixed $v): string => '"' . str_replace('"', '""', (string) ($v ?? '')) . '"',
                [
                    $row['first_name'] ?? '',
                    $row['last_name'] ?? '',
                    $row['email'] ?? '',
                    $row['phone'] ?? '',
                    $row['membership_status'] ?? '',
                    (int) ($row['is_member'] ?? 0) === 1 ? 'Yes' : 'No',
                    (int) ($row['is_good_standing'] ?? 0) === 1 ? 'Yes' : 'No',
                    $row['portal_access'] ?? 'none',
                ]
            ));
        }

        $filename = 'people-export-' . date('Y-m-d') . '.csv';

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setHeader('Cache-Control', 'no-store, no-cache')
            ->setBody(implode("\r\n", $csvLines));
    }

    public function storeNonPortal()
    {
        $result = $this->storeNonPortalPerson(
            0,  // isMember: 0 — non-members/dependents
            'people',
            'person.create_non_portal'
        );

        // Handle redirect result from shared method
        if (is_array($result)) {
            return redirect()->to($result['redirectPath'])->with('success', lang('Members.nonPortalCreateSuccess'));
        }

        return $result;  // Return early redirect if validation failed
    }

    /**
     * Show non-member / person details.
     */
    public function view($id)
    {
        if ($redirect = $this->requirePermission('member.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return redirect()->to('/people')->with('error', lang('Members.noParishSelected'));
        }

        $profile = model('ProfileModel')->find((int) $id);
        if (!$profile) {
            return redirect()->to('/people')->with('error', lang('People.personNotFound'));
        }

        // Canonical parish-membership check for this profile's person/user.
        $person = db_connect()->table('parish_affiliation pa')
            ->select('pa.*, pe.email AS person_email, ai.secret AS email_identity')
            ->join('person pe', 'pe.id = pa.person_id', 'left')
            ->join('auth_identities ai', 'ai.user_id = pa.linked_user_id AND ai.type = "email_password"', 'left')
            ->where('pa.parish_id', $parishId)
            ->where('pa.deleted_at', null)
            ->groupStart()
                ->where('pa.person_id', (int) ($profile['person_id'] ?? 0))
                ->orWhere('pa.linked_user_id', (int) ($profile['user_id'] ?? 0))
            ->groupEnd()
            ->get()
            ->getRowArray();

        if (!$person) {
            return redirect()->to('/people')->with('error', lang('People.personNotFound'));
        }

        $person['email'] = (string) ($person['email_identity'] ?? '') !== ''
            ? (string) $person['email_identity']
            : \App\Services\PersonContactService::decrypt((string) ($person['person_email'] ?? ''));

        $userId = (int) ($profile['user_id'] ?? 0);
        $personId = (int) ($profile['person_id'] ?? 0);

        // Auto-repair: if the profile is not linked to a canonical person,
        // attempt to create/find the link on the fly so the canonical identity
        // card is never permanently stuck on the "not linked" message.
        // Works for both portal (user_id > 0) and non-portal (user_id = 0) profiles.
        if ($personId <= 0) {
            $this->ensureProfilePersonLink((int) $id, $userId > 0 ? $userId : null);
            $profile  = model('ProfileModel')->find((int) $id) ?? $profile;
            $personId = (int) ($profile['person_id'] ?? 0);
        }

        $affiliations = $userId > 0 ? $this->affiliationModel->getUserAffiliations($userId) : [];

        $data = array_merge($this->getCommonViewData(lang('People.viewPerson')), [
            'profile' => $profile,
            'person' => $person,
            'is_person' => true,
            'affiliations' => $affiliations,
            'membership_details' => (new \App\Services\PersonMembershipDetailsService())->forPerson($personId, $parishId),
            'status_history' => $this->enrichStatusHistory((new \App\Services\PersonStatusHistoryService())->historyForPerson($personId, null, $parishId)),
            'leadership' => $userId > 0 ? (new \App\Models\LeadershipPositionModel())->getAllPositionsForUser($userId) : [],
            'onboarding' => (new \App\Services\PersonOnboardingService())->forPerson($personId, $parishId),
            'family' => [],
            'family_members' => [],
            'parish_families' => auth()->user()->can('family.manage')
                ? (new \App\Models\FamilyModel())->getParishFamilies($parishId)
                : [],
        ]);

        $family = [];
        $familyMembers = [];
        $sacraments = [];
        $sacramentParticipants = [];
        if ($personId > 0 && auth()->user()->can('member.edit')) {
            $sacraments = (new \App\Models\SacramentalRecordModel())->getRecordsForPerson($personId, $parishId);
            $participantModel = new \App\Models\SacramentalParticipantModel();
            foreach ($sacraments as $sr) {
                $srId = (int) ($sr['id'] ?? 0);
                if ($srId > 0) {
                    $sacramentParticipants[$srId] = $participantModel->getParticipantsForRecord($srId);
                }
            }
        }

        if ($userId > 0) {
            $familyMemberModel = new \App\Models\FamilyMemberModel();
            $userFamily = $familyMemberModel->getUserFamily($userId);
            if (!empty($userFamily)) {
                $fullFamily = (new \App\Models\FamilyModel())->getFamilyWithMembers((int) $userFamily['id']);
                if ($fullFamily !== null && (int) ($fullFamily['parish_id'] ?? 0) === $parishId) {
                    $family = $fullFamily;
                    $familyMembers = $fullFamily['members'] ?? [];
                }
            }
        }

        $data['family'] = $family;
        $data['family_members'] = $familyMembers;

        $personAffiliation = null;
        if ($personId > 0 && $parishId > 0) {
            $personAffiliation = $this->affiliationModel->db->table('parish_affiliation')
                ->where('person_id', $personId)
                ->where('parish_id', $parishId)
                ->where('deleted_at IS NULL', null, false)
                ->orderBy('id', 'DESC')
                ->get()
                ->getRowArray();
        }

        $data['group_memberships'] = ($personId > 0 && auth()->user()->can('groups.view'))
            ? model('GroupMembershipModel')->activeForPersonInParish($personId, $parishId)
            : [];
        $data['pathway_stage'] = isset($personAffiliation['pathway_stage']) && $personAffiliation['pathway_stage'] !== null
            ? (int) $personAffiliation['pathway_stage']
            : null;
        $data['can_assign_groups'] = auth()->user()->can('groups.assign');

        return view('Clergy\Views\People/view', array_merge($data, [
            'profile' => $profile,
            'donations' => ($userId > 0 && auth()->user()->can('donation.view'))
                ? (new \App\Models\DonationModel())->getMemberDonationsForParish($userId, $parishId)
                : [],
            'pledges' => ($userId > 0 && (auth()->user()->can('finance.manage') || auth()->user()->can('annual_pledge.manage')))
                ? (new \App\Models\AnnualPledgeModel())->getMemberPledges($userId, $parishId)
                : [],
            'sacraments' => $sacraments,
            'sacrament_participants' => $sacramentParticipants,
            'sacrament_discrepancies' => $personId > 0
                ? (new \App\Services\SacramentFlagSyncService())->discrepanciesForPerson($personId, $parishId)
                : [],
            'parish_affiliated_people' => auth()->user()->can('member.edit')
                ? db_connect()->table('parish_affiliation pa')
                    ->select('pa.linked_user_id AS user_id, pe.id AS person_id, pe.first_name, pe.last_name')
                    ->join('person pe', 'pe.id = pa.person_id', 'inner')
                    ->where('pa.parish_id', $parishId)
                    ->where('pa.deleted_at IS NULL', null, false)
                    ->distinct()
                    ->orderBy('pe.last_name', 'ASC')
                    ->orderBy('pe.first_name', 'ASC')
                    ->get()
                    ->getResultArray()
                : [],
            'funds' => auth()->user()->can('donation.view')
                ? (new \App\Models\FundModel())->getParishFunds($parishId)
                : [],
            'chart_accounts' => auth()->user()->can('donation.view')
                ? (new \App\Models\ChartOfAccountModel())->getGroupedActiveAccountsForType('income')
                : [],
            'can_edit' => auth()->user()->can('member.edit'),
            'can_delete' => auth()->user()->can('member.delete'),
            'can_manage_donations' => auth()->user()->can('donation.create') && auth()->user()->can('donation.edit'),
            'can_manage_pledges' => auth()->user()->can('annual_pledge.manage'),
        ]));
    }

    /**
     * Save membership detail flags for a person in the current parish.
     */
    public function saveMembershipDetails(int $personId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('member.edit')) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FORBIDDEN'), 403);
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        // Ensure the target person belongs to the current parish.
        $affiliated = db_connect()->table('parish_affiliation')
            ->where('person_id', $personId)
            ->where('parish_id', $parishId)
            ->where('deleted_at', null)
            ->countAllResults() > 0;
        if (!$affiliated) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'PERSON_NOT_FOUND'), 404);
        }

        if ($this->isProvinceAdmin()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'PROVINCE_WRITE_DISABLED'), 403);
        }

        $payload = [
            'is_baptized'            => (int) ($this->request->getPost('is_baptized') === '1'),
            'is_confirmed'           => (int) ($this->request->getPost('is_confirmed') === '1'),
            'is_communicant'         => (int) ($this->request->getPost('is_communicant') === '1'),
            'is_dependent'           => (int) ($this->request->getPost('is_dependent') === '1'),
            'has_pledge'             => (int) ($this->request->getPost('has_pledge') === '1'),
            'good_standing_override' => (int) ($this->request->getPost('good_standing_override') === '1'),
        ];

        $service = new PersonMembershipDetailsService();
        $saved = $service->saveForPerson($personId, $payload, $parishId, (string) ($this->request->getPost('reason') ?? ''));

        if (!$saved) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('People.membershipDetailsSaveFailed'),
            ], 500);
        }

        $this->audit()->log('people.membership_details.update', [
            'parish_id' => $parishId,
            'entity_type' => 'person',
            'entity_id' => $personId,
            'new_values' => $payload,
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('People.membershipDetailsSaved'),
        ]);
    }

    /**
     * Leadership eligibility probe for the appointment modal: returns the
     * per-requirement checklist for the person identified by profile_id so
     * the UI can show what is missing before the form is submitted.
     */
    public function leadershipEligibility(int $profileId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('member.edit')) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FORBIDDEN'), 403);
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        $profile = model('ProfileModel')->find($profileId);
        if (!$profile) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'PROFILE_NOT_FOUND'), 404);
        }

        $userId = (int) ($profile['user_id'] ?? 0);
        $personId = (int) ($profile['person_id'] ?? 0);
        $parishId = (int) ($this->getOperationalParishId() ?? 0);

        $eligibility = $this->leadershipPositionService->eligibilityFor(
            $userId,
            $personId,
            $this->leadershipPositionService->resolveEligibilityParishId($personId, $parishId, $userId),
            ['role_category' => (string) $this->request->getGet('role_category')]
        );

        return $this->jsonResponse([
            'success' => true,
            'data'    => $eligibility + [
                'message' => ($eligibility['eligible'] ?? false)
                    ? ''
                    : $this->leadershipPositionService->eligibilityMessage($eligibility),
            ],
        ]);
    }

    /**
     * Add a leadership position for the person identified by profile_id.
     */
    public function addLeadershipPosition(int $profileId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('member.edit')) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FORBIDDEN'), 403);
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        if ($this->isProvinceAdmin()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'PROVINCE_WRITE_DISABLED'), 403);
        }

        $profile = model('ProfileModel')->find($profileId);
        if (!$profile) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'PROFILE_NOT_FOUND'), 404);
        }

        $userId = (int) ($profile['user_id'] ?? 0);
        $personId = (int) ($profile['person_id'] ?? 0);
        if ($userId <= 0 || $personId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'PERSON_NOT_FOUND'), 404);
        }

        if ($code = $this->guardTargetUserMutation($userId)) {
            return $this->jsonResponse($this->apiErrorPayload($this->hierarchyGuardMessage($code), 403, 'TARGET_OUTRANKS_ACTOR'), 403);
        }

        if (!$this->validate($this->leadershipPositionService->validationRules(true))) {
            return $this->jsonResponse([
                'success' => false,
                'code' => 'VALIDATION_FAILED',
                'message' => 'Validation failed.',
                'errors' => $this->validator->getErrors(),
            ], 422);
        }

        $scopeType = (string) $this->request->getPost('scope_type');
        $parishId = (int) ($this->getOperationalParishId() ?? 0);

        $scopeIds = $this->leadershipPositionService->resolveScopeIds(
            $scopeType,
            $parishId,
            $this->resolveDiocesanScopeId(),
            $this->resolveAdminProvinceId()
        );
        if ($scopeIds === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 400, 'SCOPE_NOT_FOUND'), 400);
        }

        $payload = [
            'user_id' => $userId,
            'person_id' => $personId,
            'scope_type' => $scopeType,
            'parish_id' => $scopeIds['parish_id'],
            'diocese_id' => $scopeIds['diocese_id'],
            'province_id' => $scopeIds['province_id'],
            'title' => trim((string) $this->request->getPost('title')),
            'role_code' => trim((string) ($this->request->getPost('role_code') ?? '')),
            'role_category' => (string) ($this->request->getPost('role_category') ?? ''),
            'clergy_order' => (string) ($this->request->getPost('clergy_order') ?? ''),
            'employment_type' => (string) ($this->request->getPost('employment_type') ?? ''),
            'active' => (int) ($this->request->getPost('active') === '1'),
            'started_at' => (string) ($this->request->getPost('started_at') ?? '') ?: null,
            'ended_at' => (string) ($this->request->getPost('ended_at') ?? '') ?: null,
            'notes' => trim((string) ($this->request->getPost('notes') ?? '')),
            'appointed_by_user_id' => (int) (auth()->id() ?? 0),
        ];

        $eligibility = $this->leadershipPositionService->eligibilityFor(
            $userId,
            $personId,
            $this->leadershipPositionService->resolveEligibilityParishId($personId, $scopeIds['parish_id'], $userId),
            $payload
        );
        if (! ($eligibility['eligible'] ?? false)) {
            return $this->jsonResponse($this->apiErrorPayload(
                $this->leadershipPositionService->eligibilityMessage($eligibility),
                422,
                'LEADERSHIP_ELIGIBILITY_FAILED',
                ['failed_requirements' => $eligibility['failed'] ?? []]
            ), 422);
        }

        $positionId = $this->leadershipPositionService->create($payload);
        if ($positionId === null) {
            return $this->jsonResponse([
                'success' => false,
                'message' => implode(' ', array_values($this->leadershipPositionService->errors() ?: [lang('Leadership.saveFailed')])),
            ], 500);
        }

        $this->audit()->log('leadership_position.create', [
            'parish_id' => $parishId,
            'entity_type' => 'leadership_position',
            'entity_id' => $positionId,
            'new_values' => $payload,
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('Leadership.saveSuccess'),
            'data' => ['id' => $positionId],
        ]);
    }

    /**
     * Update an existing leadership position for a person.
     */
    public function updateLeadershipPosition(int $positionId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('member.edit')) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FORBIDDEN'), 403);
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        if ($this->isProvinceAdmin()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'PROVINCE_WRITE_DISABLED'), 403);
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        $position = $this->leadershipPositionService->find($positionId);
        if (!$position) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'POSITION_NOT_FOUND'), 404);
        }

        if (!$this->canManageLeadershipPosition($position, $parishId)) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'POSITION_OUT_OF_SCOPE'), 403);
        }

        if ($code = $this->guardTargetUserMutation((int) ($position['user_id'] ?? 0))) {
            return $this->jsonResponse($this->apiErrorPayload($this->hierarchyGuardMessage($code), 403, 'TARGET_OUTRANKS_ACTOR'), 403);
        }

        if (!$this->validate($this->leadershipPositionService->validationRules(false))) {
            return $this->jsonResponse([
                'success' => false,
                'code' => 'VALIDATION_FAILED',
                'message' => 'Validation failed.',
                'errors' => $this->validator->getErrors(),
            ], 422);
        }

        $payload = [
            'title' => trim((string) $this->request->getPost('title')),
            'role_code' => trim((string) ($this->request->getPost('role_code') ?? '')),
            'role_category' => (string) ($this->request->getPost('role_category') ?? ''),
            'clergy_order' => (string) ($this->request->getPost('clergy_order') ?? ''),
            'employment_type' => (string) ($this->request->getPost('employment_type') ?? ''),
            'active' => (int) ($this->request->getPost('active') === '1'),
            'started_at' => (string) ($this->request->getPost('started_at') ?? '') ?: null,
            'ended_at' => (string) ($this->request->getPost('ended_at') ?? '') ?: null,
            'notes' => trim((string) ($this->request->getPost('notes') ?? '')),
        ];

        // Reactivating a position (active 0→1) must clear the same
        // eligibility gate as a new appointment.
        if ((int) ($position['active'] ?? 0) !== 1 && $payload['active'] === 1) {
            $eligUserId = (int) ($position['user_id'] ?? 0);
            $eligPersonId = (int) ($position['person_id'] ?? 0);
            if ($eligPersonId <= 0) {
                $eligPersonId = (int) ($this->leadershipPositionService->resolvePersonIdForUser($eligUserId) ?? 0);
            }
            $eligParishId = $this->leadershipPositionService->resolveEligibilityParishId(
                $eligPersonId,
                (int) ($position['parish_id'] ?? 0) > 0 ? (int) $position['parish_id'] : null,
                $eligUserId
            );

            $eligibility = $this->leadershipPositionService->eligibilityFor(
                $eligUserId,
                $eligPersonId,
                $eligParishId,
                array_merge($position, $payload)
            );
            if (! ($eligibility['eligible'] ?? false)) {
                return $this->jsonResponse($this->apiErrorPayload(
                    $this->leadershipPositionService->eligibilityMessage($eligibility),
                    422,
                    'LEADERSHIP_ELIGIBILITY_FAILED',
                    ['failed_requirements' => $eligibility['failed'] ?? []]
                ), 422);
            }
        }

        if (!$this->leadershipPositionService->update($positionId, $payload)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => implode(' ', array_values($this->leadershipPositionService->errors() ?: [lang('Leadership.updateFailed')])),
            ], 500);
        }

        $this->audit()->log('leadership_position.update', [
            'parish_id' => $parishId,
            'entity_type' => 'leadership_position',
            'entity_id' => $positionId,
            'old_values' => $position,
            'new_values' => $payload,
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('Leadership.updateSuccess'),
        ]);
    }

    /**
     * Delete a leadership position for a person.
     */
    public function deleteLeadershipPosition(int $positionId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('member.edit')) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FORBIDDEN'), 403);
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        if ($this->isProvinceAdmin()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'PROVINCE_WRITE_DISABLED'), 403);
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        $position = $this->leadershipPositionService->find($positionId);
        if (!$position) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'POSITION_NOT_FOUND'), 404);
        }

        if (!$this->canManageLeadershipPosition($position, $parishId)) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'POSITION_OUT_OF_SCOPE'), 403);
        }

        if ($code = $this->guardTargetUserMutation((int) ($position['user_id'] ?? 0))) {
            return $this->jsonResponse($this->apiErrorPayload($this->hierarchyGuardMessage($code), 403, 'TARGET_OUTRANKS_ACTOR'), 403);
        }

        if (!$this->leadershipPositionService->delete($positionId)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Leadership.deleteFailed'),
            ], 500);
        }

        $this->audit()->log('leadership_position.delete', [
            'parish_id' => $parishId,
            'entity_type' => 'leadership_position',
            'entity_id' => $positionId,
            'old_values' => $position,
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('Leadership.deleteSuccess'),
        ]);
    }

    /**
     * Determine whether the current admin can manage a leadership position.
     * Delegates to LeadershipPositionService with the actor's scope IDs.
     */
    private function canManageLeadershipPosition(array $position, int $parishId): bool
    {
        return $this->leadershipPositionService->canManage(
            $position,
            $parishId,
            $this->resolveDiocesanScopeId(),
            $this->resolveAdminProvinceId()
        );
    }

    /**
     * Add a donation on behalf of a person.
     */
    public function addDonation(int $profileId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('donation.create')) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FORBIDDEN'), 403);
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        if ($this->isProvinceAdmin()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'PROVINCE_WRITE_DISABLED'), 403);
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        $profile = $this->getProfileForPersonManagement($profileId, $parishId);
        if (!$profile) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'PROFILE_NOT_FOUND'), 404);
        }

        $userId = (int) ($profile['user_id'] ?? 0);
        if ($userId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'PERSON_NOT_FOUND'), 404);
        }

        if ($code = $this->guardTargetUserMutation($userId)) {
            return $this->jsonResponse($this->apiErrorPayload($this->hierarchyGuardMessage($code), 403, 'TARGET_OUTRANKS_ACTOR'), 403);
        }

        if (!$this->validate($this->donationService->validationRules())) {
            return $this->jsonResponse([
                'success' => false,
                'code' => 'VALIDATION_FAILED',
                'message' => 'Validation failed.',
                'errors' => $this->validator->getErrors(),
            ], 422);
        }

        $fundId = (int) $this->request->getPost('fund_id');
        $fund = $this->donationService->findUsableFund($fundId, $parishId);
        if ($fund === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Treasurer.validFundRequired'), 400, 'INVALID_FUND'), 400);
        }

        $chartAccount = $this->donationService->findIncomeAccount((int) $this->request->getPost('chart_account_id'));

        if ($chartAccount === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Treasurer.validIncomeAccountRequired'), 400, 'INVALID_CHART_ACCOUNT'), 400);
        }

        $data = [
            'parish_id' => $parishId,
            'fund_id' => $fundId,
            'user_id' => $userId,
            'date' => (string) $this->request->getPost('date'),
            'amount' => (string) $this->request->getPost('amount'),
            'category' => (string) ($chartAccount['category'] ?? ($this->request->getPost('category') ?: 'other')),
            'chart_account_id' => (int) $chartAccount['id'],
            'payment_method' => (string) ($this->request->getPost('payment_method') ?? ''),
            'notes' => trim((string) ($this->request->getPost('notes') ?? '')),
            'is_restricted' => (int) ($this->request->getPost('is_restricted') === '1'),
            'restriction_purpose' => (string) ($this->request->getPost('restriction_purpose') ?? ''),
        ];

        $donationId = $this->donationService->create($data);
        if ($donationId === null) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Treasurer.donationRecordFailed'),
            ], 500);
        }

        $receipt = $this->donationService->issueReceipt($donationId);

        $this->audit()->log('donation.create', [
            'parish_id' => $parishId,
            'entity_type' => 'donation',
            'entity_id' => $donationId,
            'new_values' => [
                'fund_id' => $fundId,
                'user_id' => $userId,
                'date' => $data['date'],
                'amount' => $data['amount'],
                'category' => $data['category'],
                'chart_account_id' => $data['chart_account_id'],
                'receipt_number' => (string) ($receipt['receipt_number'] ?? ''),
            ],
            'meta' => $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('Treasurer.donationRecorded'),
            'data' => ['id' => $donationId],
        ]);
    }

    /**
     * Update a donation for a person.
     */
    public function updateDonation(int $donationId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('donation.edit')) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FORBIDDEN'), 403);
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        if ($this->isProvinceAdmin()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'PROVINCE_WRITE_DISABLED'), 403);
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        $donation = $this->donationService->findInParish($donationId, $parishId);
        if ($donation === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Treasurer.donationNotFound'), 404, 'DONATION_NOT_FOUND'), 404);
        }

        if ($code = $this->guardTargetUserMutation((int) ($donation['user_id'] ?? 0))) {
            return $this->jsonResponse($this->apiErrorPayload($this->hierarchyGuardMessage($code), 403, 'TARGET_OUTRANKS_ACTOR'), 403);
        }

        if (!$this->validate($this->donationService->validationRules())) {
            return $this->jsonResponse([
                'success' => false,
                'code' => 'VALIDATION_FAILED',
                'message' => 'Validation failed.',
                'errors' => $this->validator->getErrors(),
            ], 422);
        }

        $fundId = (int) $this->request->getPost('fund_id');
        $fund = $this->donationService->findUsableFund($fundId, $parishId);
        if ($fund === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Treasurer.validFundRequired'), 400, 'INVALID_FUND'), 400);
        }

        $chartAccount = $this->donationService->findIncomeAccount((int) $this->request->getPost('chart_account_id'));

        if ($chartAccount === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Treasurer.validIncomeAccountRequired'), 400, 'INVALID_CHART_ACCOUNT'), 400);
        }

        $data = [
            'fund_id' => $fundId,
            'date' => (string) $this->request->getPost('date'),
            'amount' => (string) $this->request->getPost('amount'),
            'category' => (string) ($chartAccount['category'] ?? ($this->request->getPost('category') ?: 'other')),
            'chart_account_id' => (int) $chartAccount['id'],
            'payment_method' => (string) ($this->request->getPost('payment_method') ?? ''),
            'notes' => trim((string) ($this->request->getPost('notes') ?? '')),
            'is_restricted' => (int) ($this->request->getPost('is_restricted') === '1'),
            'restriction_purpose' => (string) ($this->request->getPost('restriction_purpose') ?? ''),
        ];

        if (!$this->donationService->update($donationId, $data)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Treasurer.donationUpdateFailed') ?? 'Failed to update donation.',
            ], 500);
        }

        $this->audit()->log('donation.update', [
            'parish_id' => $parishId,
            'entity_type' => 'donation',
            'entity_id' => $donationId,
            'old_values' => $donation,
            'new_values' => $data,
            'meta' => $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('Treasurer.donationUpdated') ?? 'Donation updated.',
        ]);
    }

    /**
     * Delete a donation for a person.
     */
    public function deleteDonation(int $donationId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('donation.delete')) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FORBIDDEN'), 403);
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        if ($this->isProvinceAdmin()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'PROVINCE_WRITE_DISABLED'), 403);
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        $donation = $this->donationService->findInParish($donationId, $parishId);
        if ($donation === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Treasurer.donationNotFound'), 404, 'DONATION_NOT_FOUND'), 404);
        }

        if ($code = $this->guardTargetUserMutation((int) ($donation['user_id'] ?? 0))) {
            return $this->jsonResponse($this->apiErrorPayload($this->hierarchyGuardMessage($code), 403, 'TARGET_OUTRANKS_ACTOR'), 403);
        }

        if (!$this->donationService->delete($donationId)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Treasurer.donationDeleteFailed'),
            ], 500);
        }

        $this->audit()->log('donation.delete', [
            'parish_id' => $parishId,
            'entity_type' => 'donation',
            'entity_id' => $donationId,
            'old_values' => $donation,
            'meta' => $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('Treasurer.donationDeleted'),
        ]);
    }

    /**
     * Add an annual pledge for a person.
     */
    public function addPledge(int $profileId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('annual_pledge.manage')) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FORBIDDEN'), 403);
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        if ($this->isProvinceAdmin()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'PROVINCE_WRITE_DISABLED'), 403);
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        $profile = $this->getProfileForPersonManagement($profileId, $parishId);
        if (!$profile) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'PROFILE_NOT_FOUND'), 404);
        }

        $userId = (int) ($profile['user_id'] ?? 0);
        if ($userId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'PERSON_NOT_FOUND'), 404);
        }

        if ($code = $this->guardTargetUserMutation($userId)) {
            return $this->jsonResponse($this->apiErrorPayload($this->hierarchyGuardMessage($code), 403, 'TARGET_OUTRANKS_ACTOR'), 403);
        }

        if (!$this->validate($this->annualPledgeService->validationRules(true))) {
            return $this->jsonResponse([
                'success' => false,
                'code' => 'VALIDATION_FAILED',
                'message' => 'Validation failed.',
                'errors' => $this->validator->getErrors(),
            ], 422);
        }

        $payload = [
            'user_id' => $userId,
            'parish_id' => $parishId,
            'pledge_year' => (int) $this->request->getPost('pledge_year'),
            'pledged_amount' => $this->request->getPost('pledged_amount') !== '' ? (float) $this->request->getPost('pledged_amount') : null,
            'fulfilled_amount' => $this->request->getPost('fulfilled_amount') !== '' ? (float) $this->request->getPost('fulfilled_amount') : 0,
            'pledged_at' => (string) ($this->request->getPost('pledged_at') ?? '') ?: null,
            'fulfillment_status' => (string) $this->request->getPost('fulfillment_status'),
            'notes' => trim((string) ($this->request->getPost('notes') ?? '')),
        ];

        $pledgeId = $this->annualPledgeService->create($payload);
        if ($pledgeId === null) {
            return $this->jsonResponse([
                'success' => false,
                'message' => implode(' ', array_values($this->annualPledgeService->errors() ?: [lang('Pledges.saveFailed')])),
            ], 500);
        }

        $this->audit()->log('annual_pledge.create', [
            'parish_id' => $parishId,
            'entity_type' => 'annual_pledge',
            'entity_id' => $pledgeId,
            'new_values' => $payload,
            'meta' => $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('App.pledgeSavedSuccess'),
            'data' => ['id' => $pledgeId],
        ]);
    }

    /**
     * Update an annual pledge for a person.
     */
    public function updatePledge(int $pledgeId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('annual_pledge.manage')) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FORBIDDEN'), 403);
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        if ($this->isProvinceAdmin()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'PROVINCE_WRITE_DISABLED'), 403);
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        $pledge = $this->annualPledgeService->findInParish($pledgeId, $parishId);
        if ($pledge === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.pledgeNotFound'), 404, 'PLEDGE_NOT_FOUND'), 404);
        }

        if ($code = $this->guardTargetUserMutation((int) ($pledge['user_id'] ?? 0))) {
            return $this->jsonResponse($this->apiErrorPayload($this->hierarchyGuardMessage($code), 403, 'TARGET_OUTRANKS_ACTOR'), 403);
        }

        if (!$this->validate($this->annualPledgeService->validationRules(false))) {
            return $this->jsonResponse([
                'success' => false,
                'code' => 'VALIDATION_FAILED',
                'message' => 'Validation failed.',
                'errors' => $this->validator->getErrors(),
            ], 422);
        }

        $payload = [
            'pledged_amount' => $this->request->getPost('pledged_amount') !== '' ? (float) $this->request->getPost('pledged_amount') : null,
            'fulfilled_amount' => $this->request->getPost('fulfilled_amount') !== '' ? (float) $this->request->getPost('fulfilled_amount') : 0,
            'pledged_at' => (string) ($this->request->getPost('pledged_at') ?? '') ?: null,
            'fulfillment_status' => (string) $this->request->getPost('fulfillment_status'),
            'notes' => trim((string) ($this->request->getPost('notes') ?? '')),
        ];

        if (!$this->annualPledgeService->update($pledgeId, $payload)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => implode(' ', array_values($this->annualPledgeService->errors() ?: [lang('Pledges.updateFailed')])),
            ], 500);
        }

        $this->audit()->log('annual_pledge.update', [
            'parish_id' => $parishId,
            'entity_type' => 'annual_pledge',
            'entity_id' => $pledgeId,
            'old_values' => $pledge,
            'new_values' => $payload,
            'meta' => $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('App.pledgeUpdatedSuccess'),
        ]);
    }

    /**
     * Delete an annual pledge for a person.
     */
    public function deletePledge(int $pledgeId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('annual_pledge.manage')) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FORBIDDEN'), 403);
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        if ($this->isProvinceAdmin()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'PROVINCE_WRITE_DISABLED'), 403);
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        $pledge = $this->annualPledgeService->findInParish($pledgeId, $parishId);
        if ($pledge === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.pledgeNotFound'), 404, 'PLEDGE_NOT_FOUND'), 404);
        }

        if ($code = $this->guardTargetUserMutation((int) ($pledge['user_id'] ?? 0))) {
            return $this->jsonResponse($this->apiErrorPayload($this->hierarchyGuardMessage($code), 403, 'TARGET_OUTRANKS_ACTOR'), 403);
        }

        if (!$this->annualPledgeService->delete($pledgeId)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Pledges.deleteFailed') ?? 'Failed to delete pledge.',
            ], 500);
        }

        $this->audit()->log('annual_pledge.delete', [
            'parish_id' => $parishId,
            'entity_type' => 'annual_pledge',
            'entity_id' => $pledgeId,
            'old_values' => $pledge,
            'meta' => $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('Pledges.deleteSuccess') ?? 'Pledge deleted.',
        ]);
    }

    /**
     * Add a sacramental record for a person.
     */
    public function addSacrament(int $profileId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('member.edit')) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FORBIDDEN'), 403);
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        if ($this->isProvinceAdmin()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'PROVINCE_WRITE_DISABLED'), 403);
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        $profile = $this->getProfileForPersonManagement($profileId, $parishId);
        if (!$profile) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'PROFILE_NOT_FOUND'), 404);
        }

        $personId = (int) ($profile['person_id'] ?? 0);
        if ($personId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'PERSON_NOT_FOUND'), 404);
        }

        if (!$this->validate($this->sacramentalRecordService->recordValidationRules())) {
            return $this->jsonResponse([
                'success' => false,
                'code' => 'VALIDATION_FAILED',
                'message' => 'Validation failed.',
                'errors' => $this->validator->getErrors(),
            ], 422);
        }

        $officiantPersonId = $this->request->getPost('officiant_person_id');
        $officiantPersonId = ($officiantPersonId !== '' && $officiantPersonId !== null) ? (int) $officiantPersonId : null;

        $payload = [
            'parish_id'           => $parishId,
            'person_id'           => $personId,
            'sacrament_type'      => trim((string) $this->request->getPost('sacrament_type')),
            'sacrament_date'      => (string) ($this->request->getPost('sacrament_date') ?? '') ?: null,
            'register_ref'        => trim((string) ($this->request->getPost('register_ref') ?? '')),
            'certificate_number'  => trim((string) ($this->request->getPost('certificate_number') ?? '')),
            'officiant_person_id' => $officiantPersonId,
            'location_notes'      => trim((string) ($this->request->getPost('location_notes') ?? '')),
            'notes'               => trim((string) ($this->request->getPost('notes') ?? '')),
        ];

        $recordId = $this->sacramentalRecordService->createRecord($payload);
        if ($recordId === null) {
            return $this->jsonResponse([
                'success' => false,
                'message' => implode(' ', array_values($this->sacramentalRecordService->recordErrors() ?: [lang('Sacraments.saveFailed')])),
            ], 500);
        }

        // Keep person_membership_details flags + profile dates in sync with
        // the canonical sacramental_record. sacramental_record is the source
        // of truth for baptism/confirmation status.
        $this->sacramentalRecordService->syncFlags(
            $personId,
            $parishId,
            auth()->loggedIn() ? (int) auth()->id() : null,
            'Sacrament record added'
        );

        $this->audit()->log('sacramental_record.create', [
            'parish_id' => $parishId,
            'entity_type' => 'sacramental_record',
            'entity_id' => $recordId,
            'new_values' => $payload,
            'meta' => $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('Sacraments.saveSuccess'),
            'data' => ['id' => $recordId],
        ]);
    }

    /**
     * Update a sacramental record.
     */
    public function updateSacrament(int $recordId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('member.edit')) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FORBIDDEN'), 403);
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        if ($this->isProvinceAdmin()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'PROVINCE_WRITE_DISABLED'), 403);
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        $record = $this->sacramentalRecordService->findRecordInParish($recordId, $parishId);
        if ($record === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'SACRAMENT_NOT_FOUND'), 404);
        }

        if (!$this->validate($this->sacramentalRecordService->recordValidationRules())) {
            return $this->jsonResponse([
                'success' => false,
                'code' => 'VALIDATION_FAILED',
                'message' => 'Validation failed.',
                'errors' => $this->validator->getErrors(),
            ], 422);
        }

        $officiantPersonId = $this->request->getPost('officiant_person_id');
        $officiantPersonId = ($officiantPersonId !== '' && $officiantPersonId !== null) ? (int) $officiantPersonId : null;

        $payload = [
            'sacrament_type'      => trim((string) $this->request->getPost('sacrament_type')),
            'sacrament_date'      => (string) ($this->request->getPost('sacrament_date') ?? '') ?: null,
            'register_ref'        => trim((string) ($this->request->getPost('register_ref') ?? '')),
            'certificate_number'  => trim((string) ($this->request->getPost('certificate_number') ?? '')),
            'officiant_person_id' => $officiantPersonId,
            'location_notes'      => trim((string) ($this->request->getPost('location_notes') ?? '')),
            'notes'               => trim((string) ($this->request->getPost('notes') ?? '')),
        ];

        if (!$this->sacramentalRecordService->updateRecord($recordId, $payload)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => implode(' ', array_values($this->sacramentalRecordService->recordErrors() ?: [lang('Sacraments.updateFailed')])),
            ], 500);
        }

        // The sacrament_type or date may have changed (e.g. "other" -> "baptism"),
        // so recompute the derived flags + profile dates from all records.
        $this->sacramentalRecordService->syncFlags(
            (int) ($record['person_id'] ?? 0),
            $parishId,
            auth()->loggedIn() ? (int) auth()->id() : null,
            'Sacrament record updated'
        );

        $this->audit()->log('sacramental_record.update', [
            'parish_id' => $parishId,
            'entity_type' => 'sacramental_record',
            'entity_id' => $recordId,
            'old_values' => $record,
            'new_values' => $payload,
            'meta' => $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('Sacraments.updateSuccess'),
        ]);
    }

    /**
     * Delete a sacramental record.
     */
    public function deleteSacrament(int $recordId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('member.edit')) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FORBIDDEN'), 403);
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        if ($this->isProvinceAdmin()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'PROVINCE_WRITE_DISABLED'), 403);
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        $record = $this->sacramentalRecordService->findRecordInParish($recordId, $parishId);
        if ($record === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'SACRAMENT_NOT_FOUND'), 404);
        }

        if (!$this->sacramentalRecordService->deleteRecord($recordId)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Sacraments.deleteFailed') ?? 'Failed to delete sacramental record.',
            ], 500);
        }

        // Deleting the only baptism/confirmation record must clear the derived
        // flags + profile dates. Recompute from the remaining records.
        $this->sacramentalRecordService->syncFlags(
            (int) ($record['person_id'] ?? 0),
            $parishId,
            auth()->loggedIn() ? (int) auth()->id() : null,
            'Sacrament record deleted'
        );

        $this->audit()->log('sacramental_record.delete', [
            'parish_id' => $parishId,
            'entity_type' => 'sacramental_record',
            'entity_id' => $recordId,
            'old_values' => $record,
            'meta' => $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('Sacraments.deleteSuccess') ?? 'Sacramental record deleted.',
        ]);
    }

    /**
     * Add a participant to a sacramental record.
     */
    public function addSacramentParticipant(int $recordId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('member.edit')) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FORBIDDEN'), 403);
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        if ($this->isProvinceAdmin()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'PROVINCE_WRITE_DISABLED'), 403);
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        $record = $this->sacramentalRecordService->findRecordInParish($recordId, $parishId);
        if ($record === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'SACRAMENT_NOT_FOUND'), 404);
        }

        if (!$this->validate($this->sacramentalRecordService->participantCreateRules())) {
            return $this->jsonResponse([
                'success' => false,
                'code' => 'VALIDATION_FAILED',
                'message' => 'Validation failed.',
                'errors' => $this->validator->getErrors(),
            ], 422);
        }

        $personId = (int) ($this->request->getPost('person_id') ?? 0);
        $role = trim((string) $this->request->getPost('participant_role'));

        if ($this->sacramentalRecordService->participantExists($recordId, $personId, $role)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Sacraments.participantAlreadyExists'),
            ], 409);
        }

        $payload = [
            'sacramental_record_id' => $recordId,
            'person_id' => $personId,
            'participant_role' => $role,
        ];

        $participantId = $this->sacramentalRecordService->createParticipant($payload);
        if ($participantId === null) {
            return $this->jsonResponse([
                'success' => false,
                'message' => implode(' ', array_values($this->sacramentalRecordService->participantErrors() ?: [lang('Sacraments.participantAddFailed')])),
            ], 500);
        }

        $this->audit()->log('sacramental_participant.create', [
            'parish_id' => $parishId,
            'entity_type' => 'sacramental_participant',
            'entity_id' => $participantId,
            'new_values' => $payload,
            'meta' => $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('Sacraments.participantAddSuccess'),
            'data' => ['id' => $participantId],
        ]);
    }

    /**
     * Update a sacramental participant's role.
     */
    public function updateSacramentParticipant(int $participantId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('member.edit')) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FORBIDDEN'), 403);
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        if ($this->isProvinceAdmin()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'PROVINCE_WRITE_DISABLED'), 403);
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        $participant = $this->sacramentalRecordService->findParticipantInParish($participantId, $parishId);
        if ($participant === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'PARTICIPANT_NOT_FOUND'), 404);
        }

        if (!$this->validate($this->sacramentalRecordService->participantUpdateRules())) {
            return $this->jsonResponse([
                'success' => false,
                'code' => 'VALIDATION_FAILED',
                'message' => 'Validation failed.',
                'errors' => $this->validator->getErrors(),
            ], 422);
        }

        $role = trim((string) $this->request->getPost('participant_role'));
        $recordId = (int) ($participant['sacramental_record_id'] ?? 0);
        $personId = (int) ($participant['person_id'] ?? 0);

        if ($this->sacramentalRecordService->participantExists($recordId, $personId, $role, $participantId)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Sacraments.participantAlreadyExists'),
            ], 409);
        }

        if (!$this->sacramentalRecordService->updateParticipantRole($participantId, $role)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => implode(' ', array_values($this->sacramentalRecordService->participantErrors() ?: [lang('Sacraments.participantUpdateFailed')])),
            ], 500);
        }

        $this->audit()->log('sacramental_participant.update', [
            'parish_id' => $parishId,
            'entity_type' => 'sacramental_participant',
            'entity_id' => $participantId,
            'old_values' => $participant,
            'new_values' => ['participant_role' => $role],
            'meta' => $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('Sacraments.participantUpdateSuccess'),
        ]);
    }

    /**
     * Delete a sacramental participant.
     */
    public function deleteSacramentParticipant(int $participantId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('member.edit')) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FORBIDDEN'), 403);
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        if ($this->isProvinceAdmin()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'PROVINCE_WRITE_DISABLED'), 403);
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        $participant = $this->sacramentalRecordService->findParticipantInParish($participantId, $parishId);
        if ($participant === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'PARTICIPANT_NOT_FOUND'), 404);
        }

        if (!$this->sacramentalRecordService->deleteParticipant($participantId)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Sacraments.participantDeleteFailed') ?? 'Failed to delete participant.',
            ], 500);
        }

        $this->audit()->log('sacramental_participant.delete', [
            'parish_id' => $parishId,
            'entity_type' => 'sacramental_participant',
            'entity_id' => $participantId,
            'old_values' => $participant,
            'meta' => $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('Sacraments.participantDeleteSuccess') ?? 'Participant removed.',
        ]);
    }

    /**
     * Create a new family with the current person as head of household.
     */
    public function createFamilyForPerson(int $profileId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('family.manage')) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FORBIDDEN'), 403);
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        if ($this->isProvinceAdmin()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'PROVINCE_WRITE_DISABLED'), 403);
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        $profile = $this->getProfileForPersonManagement($profileId, $parishId);
        if (!$profile) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'PROFILE_NOT_FOUND'), 404);
        }

        $userId = (int) ($profile['user_id'] ?? 0);
        if ($userId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'PERSON_NOT_FOUND'), 404);
        }

        if (!$this->validate($this->familyService->familyNameRules())) {
            return $this->jsonResponse([
                'success' => false,
                'code' => 'VALIDATION_FAILED',
                'message' => 'Validation failed.',
                'errors' => $this->validator->getErrors(),
            ], 422);
        }

        if ($this->familyService->userHasAnotherFamily($userId)) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Families.memberAlreadyInAnotherFamily'), 409, 'PERSON_ALREADY_IN_FAMILY'), 409);
        }

        $familyId = $this->familyService->createFamilyWithHead(
            $parishId,
            trim((string) $this->request->getPost('family_name')),
            $userId,
            trim((string) ($this->request->getPost('address') ?? ''))
        );

        if ($familyId === null) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Families.createFailed'),
            ], 500);
        }

        $this->audit()->log('family.create', [
            'parish_id' => $parishId,
            'entity_type' => 'family',
            'entity_id' => $familyId,
            'new_values' => [
                'family_name' => trim((string) $this->request->getPost('family_name')),
                'head_of_household_id' => $userId,
            ],
            'meta' => $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('Families.createSuccess'),
            'data' => ['family_id' => $familyId],
        ]);
    }

    /**
     * Add the current person to an existing family.
     */
    public function addPersonToFamily(int $profileId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('family.manage')) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FORBIDDEN'), 403);
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        if ($this->isProvinceAdmin()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'PROVINCE_WRITE_DISABLED'), 403);
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        $profile = $this->getProfileForPersonManagement($profileId, $parishId);
        if (!$profile) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'PROFILE_NOT_FOUND'), 404);
        }

        $userId = (int) ($profile['user_id'] ?? 0);
        if ($userId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'PERSON_NOT_FOUND'), 404);
        }

        $familyId = (int) ($this->request->getPost('family_id') ?? 0);
        if ($familyId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Families.invalidFamily'), 422, 'FAMILY_REQUIRED'), 422);
        }

        $family = $this->familyService->findFamilyInParish($familyId, $parishId);
        if ($family === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Families.invalidFamily'), 404, 'FAMILY_NOT_FOUND'), 404);
        }

        $relationship = $this->familyService->normalizeRelationship((string) ($this->request->getPost('relationship') ?? ''));
        if ($relationship === null && trim((string) ($this->request->getPost('relationship') ?? '')) !== '') {
            return $this->jsonResponse($this->apiErrorPayload(lang('Families.relationshipInvalid'), 422, 'INVALID_RELATIONSHIP'), 422);
        }

        if ($this->familyService->relationExists($familyId, $userId)) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Families.memberAlreadyExists'), 409, 'ALREADY_IN_FAMILY'), 409);
        }

        if ($this->familyService->enforceSingleFamilyMembership() && $this->familyService->userHasAnotherFamily($userId, $familyId)) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Families.memberAlreadyInAnotherFamily'), 409, 'ALREADY_IN_ANOTHER_FAMILY'), 409);
        }

        $relationId = $this->familyService->addMember($familyId, $userId, $relationship);

        if ($relationId === null) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Families.failedAddMember'),
            ], 500);
        }

        $this->audit()->log('family_member.add', [
            'parish_id' => $parishId,
            'entity_type' => 'family_member',
            'entity_id' => $relationId,
            'new_values' => [
                'family_id' => $familyId,
                'user_id' => $userId,
                'relationship' => $relationship,
            ],
            'meta' => $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('Families.memberAddSuccess'),
            'data' => ['family_member_id' => $relationId],
        ]);
    }

    /**
     * Remove the current person from their family.
     */
    public function removePersonFromFamily(int $profileId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('family.manage')) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FORBIDDEN'), 403);
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        if ($this->isProvinceAdmin()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'PROVINCE_WRITE_DISABLED'), 403);
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        $profile = $this->getProfileForPersonManagement($profileId, $parishId);
        if (!$profile) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'PROFILE_NOT_FOUND'), 404);
        }

        $userId = (int) ($profile['user_id'] ?? 0);
        if ($userId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'PERSON_NOT_FOUND'), 404);
        }

        $relation = $this->familyService->findRelationForUser($userId);
        if ($relation === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Families.relationNotFound'), 404, 'NOT_IN_FAMILY'), 404);
        }

        $family = $this->familyService->findFamilyInParish((int) ($relation['family_id'] ?? 0), $parishId);
        if ($family === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FAMILY_SCOPE_FORBIDDEN'), 403);
        }

        $relationId = (int) ($relation['id'] ?? 0);
        if (!$this->familyService->removeMember($relationId)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Families.failedRemoveMember'),
            ], 500);
        }

        $this->audit()->log('family_member.remove', [
            'parish_id' => $parishId,
            'entity_type' => 'family_member',
            'entity_id' => $relationId,
            'old_values' => $relation,
            'meta' => $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('Families.memberRemoveSuccess'),
        ]);
    }

    /**
     * Update the current person's relationship within their family.
     */
    public function updateFamilyRelationship(int $profileId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('family.manage')) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FORBIDDEN'), 403);
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        if ($this->isProvinceAdmin()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'PROVINCE_WRITE_DISABLED'), 403);
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        $profile = $this->getProfileForPersonManagement($profileId, $parishId);
        if (!$profile) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'PROFILE_NOT_FOUND'), 404);
        }

        $userId = (int) ($profile['user_id'] ?? 0);
        if ($userId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'PERSON_NOT_FOUND'), 404);
        }

        $relation = $this->familyService->findRelationForUser($userId);
        if ($relation === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Families.relationNotFound'), 404, 'NOT_IN_FAMILY'), 404);
        }

        $family = $this->familyService->findFamilyInParish((int) ($relation['family_id'] ?? 0), $parishId);
        if ($family === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FAMILY_SCOPE_FORBIDDEN'), 403);
        }

        $relationship = $this->familyService->normalizeRelationship((string) ($this->request->getPost('relationship') ?? ''));
        if ($relationship === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Families.relationshipInvalid'), 422, 'INVALID_RELATIONSHIP'), 422);
        }

        $relationId = (int) ($relation['id'] ?? 0);
        if (!$this->familyService->updateRelationship($relationId, $relationship)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Families.updateFailed'),
            ], 500);
        }

        $this->audit()->log('family_member.update', [
            'parish_id' => $parishId,
            'entity_type' => 'family_member',
            'entity_id' => $relationId,
            'old_values' => $relation,
            'new_values' => ['relationship' => $relationship],
            'meta' => $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('Families.updateSuccess'),
        ]);
    }

    /**
     * Load a profile scoped to the current parish for person-management endpoints.
     */
    private function getProfileForPersonManagement(int $profileId, int $parishId): ?array
    {
        return db_connect()->table('profile p')
            ->select('p.*')
            ->join('parish_affiliation pa', '(pa.person_id = p.person_id OR pa.linked_user_id = p.user_id) AND pa.deleted_at IS NULL', 'inner')
            ->where('p.id', $profileId)
            ->where('pa.parish_id', $parishId)
            ->get()
            ->getRowArray();
    }

    /**
     * Bulk delete non-member people from the current parish.
     */
    public function bulkDelete(): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('member.delete')) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FORBIDDEN'), 403);
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        if ($this->isProvinceAdmin()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'PROVINCE_WRITE_DISABLED'), 403);
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        $requested = (array) $this->request->getPost('profile_ids');
        if ($requested === []) {
            $requested = (array) $this->request->getPost('member_ids');
        }

        $profileIds = array_values(array_unique(array_filter(array_map('intval', $requested), static fn(int $id): bool => $id > 0)));
        $deleteReason = trim((string) $this->request->getPost('delete_reason'));

        if ($profileIds === []) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Members.bulkDeleteSelectAtLeastOne')], 400);
        }

        if (strlen($deleteReason) < 8) {
            return $this->jsonResponse(['success' => false, 'message' => lang('App.bulkDeleteReasonRequired')], 400);
        }

        $deleted = 0;
        $skipped = 0;
        $now = date('Y-m-d H:i:s');
        $db = db_connect();

        foreach ($profileIds as $profileId) {
            $profile = model('ProfileModel')->find($profileId);
            if (!$profile) {
                $skipped++;
                continue;
            }

            // Canonical parish membership check: affiliation row keyed by
            // person_id, with linked_user_id as a bridge for profiles whose
            // person_id is unset/dangling.
            $affiliation = $db->table('parish_affiliation')
                ->where('parish_id', $parishId)
                ->where('deleted_at', null)
                ->groupStart()
                    ->where('person_id', (int) ($profile['person_id'] ?? 0))
                    ->orWhere('linked_user_id', (int) ($profile['user_id'] ?? 0))
                ->groupEnd()
                ->get()
                ->getRowArray();

            if (!$affiliation) {
                $skipped++;
                continue;
            }

            // Only allow deleting non-members from the /people list.
            if ((int) ($affiliation['is_member'] ?? 1) === 1) {
                $skipped++;
                continue;
            }

            $userId = (int) ($profile['user_id'] ?? 0);
            if ($userId > 0 && $this->guardTargetUserMutation($userId) !== null) {
                $skipped++;
                continue;
            }

            // Soft-delete the profile.
            if (!model('ProfileModel')->delete($profileId)) {
                $skipped++;
                continue;
            }

            // Soft-delete the matching parish affiliation.
            $db->table('parish_affiliation')
                ->where('id', (int) $affiliation['id'])
                ->update(['deleted_at' => $now, 'updated_at' => $now]);

            $this->audit()->log('people.bulk_delete', [
                'parish_id' => $parishId,
                'entity_type' => 'person',
                'entity_id' => $profileId,
                'new_values' => [
                    'user_id' => $userId,
                    'reason' => $deleteReason,
                ],
            ]);

            $deleted++;
        }

        return $this->jsonResponse([
            'success' => $deleted > 0,
            'message' => lang('Members.bulkDeleteResult', [$deleted, $skipped]),
            'deleted' => $deleted,
            'skipped' => $skipped,
        ]);
    }
}
