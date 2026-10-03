<?php

namespace Clergy\Controllers;

use App\Controllers\AdminHierarchyGuard;
use App\Controllers\BaseController;
use App\Models\MemberParishAffiliationModel;
use App\Services\MemberAccessService;
use App\Services\PeopleDirectoryService;
use App\Services\PeopleViewDataService;
use App\Services\PersonMembershipDetailsService;

class People extends BaseController
{
    use AdminHierarchyGuard;

    private MemberParishAffiliationModel $affiliationModel;
    private MemberAccessService $memberAccessService;
    private PeopleViewDataService $peopleViewDataService;

    public function __construct()
    {
        $this->affiliationModel = model(MemberParishAffiliationModel::class);
        $this->memberAccessService = service('memberAccess');
        $this->peopleViewDataService = service('peopleViewData');
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
        $incompleteRegistrations = [];
        if ($canModerateRegistrations) {
            $pendingRegistrations = model('UserRegistrationRequestModel')->getPendingByParish($parishId, 50);
            $incompleteRegistrations = model('UserRegistrationRequestModel')->getIncompleteByParish($parishId, 50);
        }
        $pendingRegistrationsCount = count($pendingRegistrations);
        $incompleteRegistrationsCount = count($incompleteRegistrations);
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
                'incomplete_registrations_count' => $incompleteRegistrationsCount,
                'incomplete_registrations' => $incompleteRegistrations,
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
