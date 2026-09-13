<?php

namespace Clergy\Controllers;

use App\Controllers\AdminHierarchyGuard;
use App\Controllers\BaseController;
use App\Libraries\AddressHelper;
use App\Libraries\DataEncryption;
use App\Libraries\PhoneHelper;
use App\Services\ParishPeopleRegistryService;
use App\Services\NonPortalMemberProvisioningService;
use App\Services\MembershipStatusService;
use App\Services\PersonStatusHistoryService;
use App\Services\UploadPathService;

class Members extends BaseController
{
    use AdminHierarchyGuard;

    protected $profileModel;
    protected $churchModel;
    protected $memberChurchAffiliationModel;
    protected $nonPortalProvisioningService;

    public function __construct()
    {
        $this->churchModel = model('ParishModel');
    }

    protected function getProfileModel()
    {
        if ($this->profileModel === null) {
            $this->profileModel = model('ProfileModel');
        }

        return $this->profileModel;
    }

    protected function getMemberParishAffiliationModel()
    {
        if ($this->memberChurchAffiliationModel === null) {
            $this->memberChurchAffiliationModel = model('MemberParishAffiliationModel');
        }

        return $this->memberChurchAffiliationModel;
    }

    protected function getNonPortalProvisioningService(): NonPortalMemberProvisioningService
    {
        if ($this->nonPortalProvisioningService === null) {
            $this->nonPortalProvisioningService = new NonPortalMemberProvisioningService();
        }

        return $this->nonPortalProvisioningService;
    }

    /**
     * Redirect to members index with a de-duplicated flash error.
     * Prevents duplicate alerts when the same message is already in flash/session.
     */
    protected function redirectMembersError(string $message)
    {
        $existingFlash = session()->getFlashdata('error');
        $existing = $existingFlash !== null ? (string) $existingFlash : (string) session('error');

        if ($existing === $message) {
            return redirect()->to('/members');
        }

        return redirect()->to('/members')->with('error', $message);
    }

    /**
     * Display list of members (or admin staff for province admin)
     */
    public function index()
    {
        if ($redirect = $this->requirePermission('member.view')) {
            return $redirect;
        }

        $isProvinceAdmin = $this->isProvinceAdmin();

        // Province admin: split view — province-level CRUD + diocesan read-only section.
        if ($isProvinceAdmin) {
            $db = \Config\Database::connect();
            $profileTable = $this->resolveExistingProfileTable($db);
            if ($profileTable === null) {
                return redirect()->to('/province/dashboard')->with('error', lang('App.errorGeneric'));
            }

            $scope = $this->resolveProvinceScopeForStaffManagement();
            $provinceId = (int) ($scope['province_id'] ?? 0);
            if ($provinceId <= 0) {
                return redirect()->to('/province/dashboard')->with('error', lang('Members.unableResolveProvince'));
            }

            // Diocese filter for the diocesan section: GET param takes precedence over session.
            $requestedDioceseId = (int) $this->request->getGet('diocese_scope_id');
            if ($requestedDioceseId > 0) {
                $valid = $db->table('diocese')
                    ->where('id', $requestedDioceseId)
                    ->where('province_id', $provinceId)
                    ->countAllResults() > 0;
                $selectedDioceseId = $valid ? $requestedDioceseId : null;
            } else {
                $selectedDioceseId = $this->resolveSelectedDioceseForProvince($provinceId);
            }

            // --- Province staff: provinceadmin group users scoped to this province ---
            $provinceStaff = $db->table($profileTable . ' p')
                ->select('p.id as profile_id, p.user_id, p.first_name, p.last_name, p.phone, ai.secret as email')
                ->join('users u', 'u.id = p.user_id')
                ->join('auth_identities ai', 'ai.user_id = u.id AND ai.type = "email_password"', 'left')
                ->join('auth_groups_users agu', 'agu.user_id = p.user_id AND agu.group = "provinceadmin"')
                ->where('p.province_id', $provinceId)
                ->orderBy('p.last_name', 'ASC')
                ->orderBy('p.first_name', 'ASC')
                ->get()
                ->getResultArray();
            $provinceStaff = array_map(static function (array $row): array {
                $row['phone'] = DataEncryption::decrypt($row['phone'] ?? null);
                return $row;
            }, $provinceStaff);
            $provinceStaff = $this->annotateRowsWithProfilePending($provinceStaff, 'user_id', 'profile_id');

            // --- Diocesan staff: diocesanadmin, churchadmin, clergy — read-only for province admin ---
            $diocesanStaffBuilder = $db->table($profileTable . ' p')
                ->select('p.id as profile_id, p.user_id, p.first_name, p.last_name, p.phone, ai.secret as email, agu.group as role, c.id as parish_id, c.name as parish_name, d.id as diocese_id, d.name as diocese_name')
                ->join('users u', 'u.id = p.user_id')
                ->join('auth_identities ai', 'ai.user_id = u.id AND ai.type = "email_password"', 'left')
                ->join('auth_groups_users agu', 'agu.user_id = p.user_id')
                ->join('parish c', 'c.id = p.parish_id', 'left')
                ->join('diocese d', 'd.id = COALESCE(p.diocese_id, c.diocese_id)', 'left')
                ->whereIn('agu.group', ['diocesanadmin', 'parishadmin', 'clergy'])
                ->where('d.province_id', $provinceId);

            if ($selectedDioceseId !== null) {
                $diocesanStaffBuilder->where('d.id', $selectedDioceseId);
            }

            $diocesanStaff = $diocesanStaffBuilder
                ->orderBy('agu.group', 'ASC')
                ->orderBy('d.name', 'ASC')
                ->orderBy('p.last_name', 'ASC')
                ->get()
                ->getResultArray();
            $diocesanStaff = array_map(static function (array $row): array {
                $row['phone'] = DataEncryption::decrypt($row['phone'] ?? null);
                return $row;
            }, $diocesanStaff);
            $diocesanStaff = $this->annotateRowsWithProfilePending($diocesanStaff, 'user_id', 'profile_id');

            // Dioceses list for the diocesan section filter dropdown.
            $diocesesInProvince = $db->table('diocese')
                ->where('province_id', $provinceId)
                ->orderBy('name', 'ASC')
                ->get()
                ->getResultArray();

            $data = array_merge($this->getCommonViewData('Staff Management'), [
                'is_admin_management' => true,
                'province_staff'      => $provinceStaff,
                'diocesan_staff'      => $diocesanStaff,
                'canManageProvinceStaff' => false,
                'dioceses_in_province' => $diocesesInProvince,
                'staff_roles' => [
                    'provinceadmin'        => 'Province Admin',
                    'diocesanadmin'        => 'Diocesan Admin',
                    'parishadmin'          => 'Parish Admin',
                    'clergy'               => 'Clergy',
                    'treasurer'            => 'Treasurer',
                    'volunteer_coordinator' => 'Volunteer Coordinator',
                    'member'               => 'Member',
                ],
                'isProvinceAdmin'  => true,
                'scope_province_id' => $provinceId,
                'scope_diocese_id'  => $selectedDioceseId,
            ]);

            return view('Clergy\Views\Members/admin-management', $data);
        }

        // Non-ProvinceAdmin: delegated to unified People directory with members scope.
        $churchId = (int) ($this->getOperationalParishId() ?? 0);

        if ($churchId <= 0) {
            return redirect()->to('/dashboard')->with('error', lang('Members.noChurchSelected'));
        }

        $status = $this->request->getGet('status');
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = (int) ($this->request->getGet('per_page') ?? 25);
        if ($perPage < 10 || $perPage > 100) {
            $perPage = 25;
        }

        $query = [
            'scope'    => 'members',
            'page'     => $page,
            'per_page' => $perPage,
        ];
        if (is_string($status) && $status !== '') {
            $query['status'] = $status;
        }

        return redirect()->to('/people?' . http_build_query($query));
    }

    /**
     * Show member details.
     *
     * The dedicated Members detail view has been unified into the People
     * directory (see People::view()), the same way index()/search() already
     * delegate their listing to /people. This keeps a single canonical
     * "view person" page instead of two independently-maintained templates.
     */
    public function view($id)
    {
        if ($redirect = $this->requirePermission('member.view')) {
            return $redirect;
        }

        return redirect()->to('/people/view/' . (int) $id);
    }

    /**
     * Show create admin account form
     */
    public function create()
    {
        if ($redirect = $this->requirePermission('member.create')) {
            return $redirect;
        }

        // Province admins must use Quick Create Admin from dashboard for explicit church selection
        if ($this->isProvinceAdmin()) {
            return redirect()->to('/province/dashboard')
                ->with('error', lang('Members.provinceAdminRestriction'));
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $allowedRoles = $this->getCreatableRoles();
        if (empty($allowedRoles)) {
            return redirect()->to('/members')->with('error', lang('Members.notAllowed'));
        }

        $guestData = (array) session('guest_data');
        if ($guestData !== []) {
            $guestData['role'] = $guestData['role'] ?? 'member';
            session()->setFlashdata('_ci_old_input', ['post' => $guestData, 'get' => []]);
        }

        return view('Clergy\Views\Members/create', array_merge(
            $this->getCommonViewData('Create Account'),
            ['allowedRoles' => $allowedRoles]
        ));
    }

    /**
     * Store new admin account
     */
    public function store()
    {
        if ($redirect = $this->requirePermission('member.create')) {
            return $redirect;
        }

        // Province admins must use Quick Create Admin from dashboard for explicit church selection
        if ($this->isProvinceAdmin()) {
            return redirect()->to('/province/dashboard')
                ->with('error', lang('Members.provinceAdminRestriction'));
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $allowedRoles = $this->getCreatableRoles();
        if (empty($allowedRoles)) {
            return redirect()->to('/members')->with('error', lang('Members.notAllowed'));
        }

        $roleValidation = implode(',', $allowedRoles);

        $rules = [
            'email' => 'required|valid_email',
            'password' => 'required|min_length[8]',
            'role' => 'required|in_list[' . $roleValidation . ']',
            'first_name' => 'required|min_length[2]',
            'last_name' => 'required|min_length[2]',
            'nickname'   => 'permit_empty|max_length[100]',
            'phone_country_code' => 'permit_empty|valid_calling_code',
            'phone' => 'permit_empty|valid_phone_e164',
            'country' => 'permit_empty|valid_country',
            'profile_picture' => 'permit_empty|uploaded[profile_picture]|max_size[profile_picture,2048]|is_image[profile_picture]|mime_in[profile_picture,image/jpg,image/jpeg,image/png,image/gif]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $email = $this->normalizeEmail((string) $this->request->getPost('email'));

        $emailExists = $this->emailIdentityExists($email);

        if ($emailExists) {
            return redirect()->back()->withInput()->with('errors', ['email' => 'Email is already in use.']);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            // Create user account
            $users = auth()->getProvider();
            $user = new \CodeIgniter\Shield\Entities\User([
                'username' => $email,
                'email' => $email,
                'password' => $this->request->getPost('password'),
            ]);

            $users->save($user);
            $user = $users->findById($users->getInsertID());
            $user->activate();

            // Assign selected privileged group
            $user->addGroup($this->request->getPost('role'));

            // Create profile
            $assignedChurchId = (int) ($this->getOperationalParishId() ?? 0);
            $assignedChurch = $assignedChurchId > 0 ? $this->churchModel->findWithHierarchy($assignedChurchId) : null;

            $callingCode = $this->request->getPost('phone_country_code');
            $localPhone = $this->request->getPost('phone');
            $e164Phone = PhoneHelper::toE164($callingCode, $localPhone);

            // Ensure the canonical person row exists and link the profile to it.
            $personId = $assignedChurchId > 0
                ? $this->getMemberParishAffiliationModel()->getOrCreatePersonIdForUser((int) $user->id)
                : null;

            $profileData = [
                'user_id' => $user->id,
                'person_id' => $personId > 0 ? $personId : null,
                'parish_id' => $assignedChurchId > 0 ? $assignedChurchId : null,
                'province_id' => isset($assignedChurch['province_id']) ? (int) $assignedChurch['province_id'] : null,
                'diocese_id' => isset($assignedChurch['diocese_id']) ? (int) $assignedChurch['diocese_id'] : null,
                'first_name' => $this->request->getPost('first_name'),
                'middle_name' => $this->request->getPost('middle_name'),
                'last_name' => $this->request->getPost('last_name'),
                'nickname' => $this->request->getPost('nickname') ?: null,
                'phone' => $e164Phone,
                'phone_country_code' => $callingCode ?: null,
                'address' => $this->request->getPost('address'),
                'address_line_2' => $this->request->getPost('address_line_2') ?: null,
                'city' => $this->request->getPost('city'),
                'state' => $this->request->getPost('state'),
                'zip' => $this->request->getPost('zip'),
                'country' => $this->request->getPost('country') ?: null,
                'birth_date' => $this->request->getPost('birth_date') ?: null,
                'baptism_date' => $this->request->getPost('baptism_date') ?: null,
                'is_confirmed' => $this->request->getPost('is_confirmed') ? 1 : 0,
                'confirmation_date' => $this->request->getPost('confirmation_date') ?: null,
                'membership_status' => 'member',
                'spiritual_gifts' => $this->request->getPost('spiritual_gifts'),
            ];

            // Handle profile picture upload
            $file = $this->request->getFile('profile_picture');
            if ($file && $file->isValid() && !$file->hasMoved()) {
                $storedPath = $this->storeProfilePictureForUser($file, (int) $user->id);
                if ($storedPath !== null) {
                    $profileData['profile_picture'] = $storedPath;
                }
            }

            $profileId = (int) $this->getProfileModel()->insert($profileData, true);

            // Sync the canonical person record with the full profile data (the person
            // may have been auto-created earlier with placeholder names).
            if ($profileId > 0) {
                (new \App\Services\PersonProfileSyncService())->syncFromProfile($profileId);
            }

            if ($assignedChurchId > 0 && !$this->getMemberParishAffiliationModel()->attachToParish($user->id, $assignedChurchId, true, 'member', true, $personId > 0 ? $personId : null)) {
                $db->transRollback();
                return redirect()->back()->withInput()->with('error', lang('Members.affiliationFailed'));
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                return redirect()->back()->withInput()->with('error', lang('App.errorFailedCreate'));
            }

            $this->audit()->log('member.create', [
                'parish_id' => $assignedChurchId > 0 ? $assignedChurchId : null,
                'entity_type' => 'member',
                'entity_id' => $profileId > 0 ? $profileId : $user->id,
                'new_values' => [
                    'user_id' => (int) $user->id,
                    'role' => (string) $this->request->getPost('role'),
                    'membership_status' => 'member',
                    'parish_id' => $assignedChurchId > 0 ? $assignedChurchId : null,
                ],
            ]);

            if ($assignedChurchId > 0) {
                (new ParishPeopleRegistryService())->syncParish($assignedChurchId);
            }

            return redirect()->to('/members')->with('success', lang('App.successCreated'));
        } catch (\Exception $e) {
            $db->transRollback();
            return $this->safeRedirectError('An error occurred while creating the account. Please try again.', $e);
        }
    }

    public function storeNonPortal()
    {
        $result = $this->storeNonPortalPerson(
            1,  // isMember: 1 — full members
            'members',
            'member.create_non_portal',
            [
                'user_id' => 0,  // Will be populated from result
                'family_id' => null,
                'family_linked' => false,
            ]
        );

        // Handle redirect result from shared method
        if (is_array($result)) {
            (new ParishPeopleRegistryService())->syncParish($result['parishId']);
            return redirect()->to($result['redirectPath'])->with('success', lang('Members.nonPortalCreateSuccess'));
        }

        return $result;  // Return early redirect if validation failed
    }

    /**
     * Role policy matrix (guardrail):
     * System-level, province-level, diocese-level, and parish-level admin
     * accounts are created through the dedicated primary-admin controllers.
     * This endpoint is reserved for non-admin roles.
     *
     * - ProvinceAdmin can create: clergy
     * - DiocesanAdmin can create: clergy, treasurer, volunteer_coordinator, member
     * - ParishAdmin can create: clergy, treasurer, volunteer_coordinator, member
     * - Everyone else: none
     *
     * Keep this matrix aligned with form options and store() validation.
     */
    protected function getCreatableRoles(): array
    {
        $userId = (int) (auth()->id() ?? 0);
        if ($userId > 0) {
            $roleScope = $this->isProvinceAdmin()
                ? 'provinceadmin'
                : ($this->isDioceseAdmin() ? 'diocesanadmin' : (($this->user && $this->user->inGroup('parishadmin')) ? 'parishadmin' : 'none'));
            $cacheKey = 'members_creatable_roles_v2_' . $userId . '_' . $roleScope;
            $cache = \Config\Services::cache();
            $cached = $cache->get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $roles = [];
        if ($this->isProvinceAdmin()) {
            $roles = ['clergy'];
        } elseif ($this->isDioceseAdmin()) {
            $roles = ['clergy', 'treasurer', 'volunteer_coordinator', 'member'];
        } elseif ($this->user && $this->user->inGroup('parishadmin')) {
            $roles = ['clergy', 'treasurer', 'volunteer_coordinator', 'member'];
        }

        if (isset($cacheKey)) {
            \Config\Services::cache()->save($cacheKey, $roles, 3600);
        }

        return $roles;
    }

    /**
     * POST members/revoke-portal-access/:id
     * Removes the email_password auth identity so the member can no longer log in
     * and the non-portal badge logic evaluates correctly.
     */
    public function revokePortalAccess(int $id): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('member.edit')) {
            return $this->response->setJSON(['success' => false, 'message' => lang('App.notAuthorized')])->setStatusCode(403);
        }

        $operationalParishId = (int) ($this->getOperationalParishId() ?? 0);
        $profile = $this->getProfileModel()->getProfileWithUser($id, $operationalParishId ?: null);

        if (!$profile) {
            return $this->response->setJSON(['success' => false, 'message' => lang('App.notFound')])->setStatusCode(404);
        }

        $userId = (int) ($profile['user_id'] ?? 0);
        if ($code = $this->guardTargetUserMutation($userId, true)) {
            return $this->response->setJSON(['success' => false, 'message' => $this->hierarchyGuardMessage($code)])->setStatusCode(403);
        }

        $result = $this->getNonPortalProvisioningService()->revokePortalAccess($userId, $this->churchId);

        if (!$result['ok']) {
            $httpStatus = $result['http_status'] ?? 500;
            return $this->response->setJSON([
                'success' => false,
                'message' => $result['message'],
                'error' => ['code' => $result['code'], 'message' => $result['message']],
            ])->setStatusCode($httpStatus);
        }

        (new ParishPeopleRegistryService())->syncParish($this->churchId);

        $this->audit()->log('member.revoke_portal_access', array_merge(
            ['profile_id' => $id, 'user_id' => $userId],
            $this->buildParishContextMeta($this->churchId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT)
        ));

        return $this->response->setJSON(['success' => true, 'message' => 'Portal access revoked successfully.']);
    }

    /**
     * Show edit member form
     */
    public function edit($id)
    {
        if ($redirect = $this->requirePermission('member.edit')) {
            return $redirect;
        }

        $operationalParishId = (int) ($this->getOperationalParishId() ?? 0);
        $profile = $this->getProfileModel()->getProfileWithUser($id, $operationalParishId ?: null, false);

        if (!$profile) {
            return $this->redirectMembersError('Member not found');
        }

        // Keep edit form status aligned to the active affiliation in operational church.
        $churchId = (int) ($this->getOperationalParishId() ?? 0);
        if ($churchId > 0) {
            $affiliation = $this->getMemberParishAffiliationModel()
                ->getActiveAffiliationForParish((int) ($profile['user_id'] ?? 0), $churchId);
            if (is_array($affiliation) && isset($affiliation['membership_status'])) {
                $profile['membership_status'] = (string) $affiliation['membership_status'];
            }
        }

        return view('Clergy\Views\Members/edit', array_merge($this->getCommonViewData('Edit Member'), ['profile' => $profile]));
    }

    /**
     * Update member
     */
    public function update($id)
    {
        if ($redirect = $this->requirePermission('member.edit')) {
            return $redirect;
        }

        $operationalParishId = (int) ($this->getOperationalParishId() ?? 0);
        $profile = $this->getProfileModel()->getProfileWithUser($id, $operationalParishId ?: null, false);

        if (!$profile) {
            return $this->redirectMembersError('Member not found');
        }

        if ($code = $this->guardTargetUserMutation((int) ($profile['user_id'] ?? 0))) {
            return $this->redirectMembersError($this->hierarchyGuardMessage($code));
        }

        $rules = [
            'first_name' => 'required|min_length[2]',
            'last_name' => 'required|min_length[2]',
            'phone_country_code' => 'permit_empty|valid_calling_code',
            'phone' => 'permit_empty|valid_phone_e164',
            'country' => 'permit_empty|valid_country',
            'membership_status' => 'required|in_list[' . implode(',', \App\Services\MembershipStatusService::affiliationStatuses()) . ']',
            'profile_picture' => 'permit_empty|uploaded[profile_picture]|max_size[profile_picture,2048]|is_image[profile_picture]|mime_in[profile_picture,image/jpg,image/jpeg,image/png,image/gif]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $submittedStatus = (string) $this->request->getPost('membership_status');

        $callingCode = $this->request->getPost('phone_country_code');
        $localPhone = $this->request->getPost('phone');
        $e164Phone = PhoneHelper::toE164($callingCode, $localPhone);

        $profileData = [
            'first_name' => $this->request->getPost('first_name'),
            'middle_name' => $this->request->getPost('middle_name'),
            'last_name' => $this->request->getPost('last_name'),
            'phone' => $e164Phone,
            'phone_country_code' => $callingCode ?: null,
            'address' => $this->request->getPost('address'),
            'address_line_2' => $this->request->getPost('address_line_2') ?: null,
            'city' => $this->request->getPost('city'),
            'state' => $this->request->getPost('state'),
            'zip' => $this->request->getPost('zip'),
            'country' => $this->request->getPost('country') ?: null,
            'birth_date' => $this->request->getPost('birth_date') ?: null,
            'baptism_date' => $this->request->getPost('baptism_date') ?: null,
            'is_confirmed' => $this->request->getPost('is_confirmed') ? 1 : 0,
            'confirmation_date' => $this->request->getPost('confirmation_date') ?: null,
            'spiritual_gifts' => $this->request->getPost('spiritual_gifts'),
        ];

        // Handle profile picture upload
        $file = $this->request->getFile('profile_picture');
        if ($file && $file->isValid() && !$file->hasMoved()) {
            // Delete old profile picture if exists
            if (!empty($profile['profile_picture'])) {
                $oldFile = $this->resolveStoredUploadAbsolutePath((string) $profile['profile_picture'], (int) ($profile['user_id'] ?? 0));
                if ($oldFile !== null && is_file($oldFile)) {
                    @unlink($oldFile);
                }
            }

            $storedPath = $this->storeProfilePictureForUser($file, (int) ($profile['user_id'] ?? 0));
            if ($storedPath !== null) {
                $profileData['profile_picture'] = $storedPath;
            }
        }

        $this->getProfileModel()->update($id, $profileData);

        // Sync the canonical person record with the updated profile data.
        (new \App\Services\PersonProfileSyncService())->syncFromProfile($id);

        // Update the canonical profile's membership_status only when editing the home parish.
        // The operational per-parish status lives in parish_affiliation.
        $churchId = (int) ($this->getOperationalParishId() ?? 0);
        $homeParishId = (int) ($profile['parish_id'] ?? 0);
        if ($churchId > 0 && $churchId === $homeParishId) {
            $this->getProfileModel()->update($id, ['membership_status' => $submittedStatus]);
        }

        // Sync affiliation status for current church so /members, /people, and detail
        // views stay consistent with the status chosen in this edit form.
        if ($churchId > 0) {
            $affiliationStatus = \App\Services\MembershipStatusService::profileToAffiliation($submittedStatus);

            $affiliation = $this->getMemberParishAffiliationModel()
                ->getActiveAffiliationForParish((int) ($profile['user_id'] ?? 0), $churchId);

            if (is_array($affiliation)) {
                $fromStatus = (string) ($affiliation['membership_status'] ?? '');
                $affiliationUpdate = ['membership_status' => $affiliationStatus];

                if (array_key_exists('is_member', $affiliation)) {
                    $affiliationUpdate['is_member'] = \App\Services\MembershipStatusService::isMember($affiliationStatus) ? 1 : 0;
                }

                $this->getMemberParishAffiliationModel()->update((int) ($affiliation['id'] ?? 0), $affiliationUpdate);

                if ($fromStatus !== $affiliationStatus) {
                    $history = new PersonStatusHistoryService();
                    $history->record(
                        (int) $id,
                        'affiliation',
                        $fromStatus,
                        $affiliationStatus,
                        $churchId,
                        auth()->loggedIn() ? (int) auth()->id() : null,
                        (string) ($this->request->getPost('status_reason') ?? 'Manual status update')
                    );
                }
            }

            // Refresh church_people snapshot immediately instead of waiting for cron.
            (new ParishPeopleRegistryService())->syncParish($churchId);
        }

        $this->audit()->log('member.update', [
            'parish_id' => (int) ($this->getOperationalParishId() ?? 0),
            'entity_type' => 'member',
            'entity_id' => (int) $id,
            'old_values' => [
                'membership_status' => (string) ($profile['membership_status'] ?? ''),
                'profile_picture' => !empty($profile['profile_picture']),
            ],
            'new_values' => [
                'membership_status' => $submittedStatus,
                'profile_picture' => array_key_exists('profile_picture', $profileData) ? true : !empty($profile['profile_picture']),
            ],
        ]);

        return redirect()->to('/members')->with('success', lang('App.successUpdated'));
    }

    /**
     * Bulk transfer members to another church with reason logging
     */
    public function transfer()
    {
        return redirect()->to('/members')->with('error', lang('App.errorUnauthorized'));
    }

    /**
     * Delete member
     */
    public function delete($id)
    {
        if ($redirect = $this->requirePermission('member.delete')) {
            return $redirect;
        }

        $operationalParishId = (int) ($this->getOperationalParishId() ?? 0);
        $profile = $this->getProfileModel()->getProfileWithUser($id, $operationalParishId ?: null);

        if (!$profile) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Clergy.memberNotFound')], 404);
        }

        if ($code = $this->guardTargetUserMutation((int) ($profile['user_id'] ?? 0), true)) {
            return $this->jsonResponse(['success' => false, 'message' => $this->hierarchyGuardMessage($code)], 403);
        }

        // Delete profile and user
        $this->audit()->log('member.delete', [
            'parish_id' => (int) ($this->getOperationalParishId() ?? 0),
            'entity_type' => 'member',
            'entity_id' => (int) $id,
            'old_values' => [
                'user_id' => (int) ($profile['user_id'] ?? 0),
                'membership_status' => (string) ($profile['membership_status'] ?? ''),
            ],
        ]);

        $affectedChurchIds = $this->deactivateActiveAffiliationsForUser((int) ($profile['user_id'] ?? 0));

        if (!$this->getProfileModel()->delete($id)) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Members.deleteError')], 500);
        }

        // If the person no longer has any active profiles, mark the canonical
        // person record as inactive.
        $this->deactivatePersonsForUser((int) ($profile['user_id'] ?? 0));

        if ($affectedChurchIds !== []) {
            $registryService = new ParishPeopleRegistryService();
            foreach ($affectedChurchIds as $affectedChurchId) {
                $registryService->syncParish($affectedChurchId);
            }
        }

        // Note: User will be cascade deleted or you can handle separately

        return $this->jsonResponse(['success' => true, 'message' => lang('Clergy.memberDeletedSuccess')]);
    }

    public function bulkDelete()
    {
        if ($redirect = $this->requirePermission('member.delete')) {
            return $redirect;
        }

        $requested = (array) $this->request->getPost('member_ids');
        $memberIds = array_values(array_unique(array_filter(array_map('intval', $requested), static fn(int $id): bool => $id > 0)));
        $deleteReason = trim((string) $this->request->getPost('delete_reason'));

        if ($memberIds === []) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Members.bulkDeleteSelectAtLeastOne')], 400);
        }

        if (strlen($deleteReason) < 8) {
            return $this->jsonResponse(['success' => false, 'message' => lang('App.bulkDeleteReasonRequired')], 400);
        }

        $deleted = 0;
        $skipped = 0;
        $operationalParishId = (int) ($this->getOperationalParishId() ?? 0);

        foreach ($memberIds as $memberId) {
            $profile = $this->getProfileModel()->getProfileWithUser($memberId, $operationalParishId ?: null);
            if (!$profile) {
                $skipped++;
                continue;
            }

            if ($this->guardTargetUserMutation((int) ($profile['user_id'] ?? 0), true) !== null) {
                $skipped++;
                continue;
            }

            $affectedChurchIds = $this->deactivateActiveAffiliationsForUser((int) ($profile['user_id'] ?? 0));

            $this->audit()->log('member.delete', [
                'parish_id' => (int) ($this->getOperationalParishId() ?? 0),
                'entity_type' => 'member',
                'entity_id' => (int) $memberId,
                'old_values' => [
                    'user_id' => (int) ($profile['user_id'] ?? 0),
                    'membership_status' => (string) ($profile['membership_status'] ?? ''),
                ],
                'new_values' => [
                    'delete_reason' => $deleteReason,
                ],
                'meta' => [
                    'reason' => $deleteReason,
                    'bulk' => true,
                    'requested_count' => count($memberIds),
                ],
            ]);

            if ($this->getProfileModel()->delete($memberId)) {
                $this->deactivatePersonsForUser((int) ($profile['user_id'] ?? 0));

                if ($affectedChurchIds !== []) {
                    $registryService = new ParishPeopleRegistryService();
                    foreach ($affectedChurchIds as $affectedChurchId) {
                        $registryService->syncParish($affectedChurchId);
                    }
                }
                $deleted++;
            } else {
                $skipped++;
            }
        }

        return $this->jsonResponse([
            'success' => $deleted > 0,
            'message' => lang('Members.bulkDeleteResult', [$deleted, $skipped]),
            'deleted' => $deleted,
            'skipped' => $skipped,
        ]);
    }

    /**
     * Deactivate all active affiliations for a user and return impacted church IDs.
     *
     * @return array<int,int>
     */
    protected function deactivateActiveAffiliationsForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $rows = db_connect()->table('parish_affiliation')
            ->select('parish_id AS parish_id')
            ->where('linked_user_id', $userId)
            ->where('is_member', 1)
            ->get()
            ->getResultArray();

        $churchIds = array_values(array_unique(array_map(
            static fn(array $row): int => (int) ($row['parish_id'] ?? 0),
            $rows
        )));
        $churchIds = array_values(array_filter($churchIds, static fn(int $id): bool => $id > 0));

        if ($churchIds === []) {
            return [];
        }

        db_connect()->table('parish_affiliation')
            ->where('linked_user_id', $userId)
            ->where('is_member', 1)
            ->update([
                'is_member'  => 0,
                'end_date'   => date('Y-m-d'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        return $churchIds;
    }

    /**
     * Deactivate the canonical person records for a user if no active profiles
     * remain linked to them.
     */
    private function deactivatePersonsForUser(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $db = db_connect();
        $service = new \App\Services\PersonCleanupService($db);

        $personIds = [];

        if ($db->tableExists('user_person')) {
            $rows = $db->table('user_person')
                ->distinct()
                ->select('person_id')
                ->where('user_id', $userId)
                ->get()
                ->getResultArray();
            foreach ($rows as $row) {
                $id = (int) ($row['person_id'] ?? 0);
                if ($id > 0) {
                    $personIds[$id] = true;
                }
            }
        }

        if ($db->tableExists('profile')) {
            $rows = $db->table('profile')
                ->distinct()
                ->select('person_id')
                ->where('user_id', $userId)
                ->get()
                ->getResultArray();
            foreach ($rows as $row) {
                $id = (int) ($row['person_id'] ?? 0);
                if ($id > 0) {
                    $personIds[$id] = true;
                }
            }
        }

        foreach (array_keys($personIds) as $personId) {
            $service->deactivateIfNoActiveProfiles($personId);
        }
    }

    /**
     * AJAX search members
     */
    public function search()
    {
        if (!$this->checkPermission('member.view')) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'MEMBERS_VIEW_FORBIDDEN'), 403);
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'CHURCH_SCOPE_REQUIRED'), 403);
        }

        $keyword = $this->request->getGet('q');
        $churchId = $this->getOperationalParishId();

        $members = $this->getMemberParishAffiliationModel()->searchParishMembers((int) $churchId, (string) $keyword);

        return $this->jsonResponse($members);
    }

    public function attachParish()
    {
        if ($redirect = $this->requirePermission('member.edit')) {
            return $redirect;
        }

        $profileId = (int) $this->request->getPost('profile_id');
        $churchId = (int) $this->request->getPost('parish_id');
        $allowedStatuses = ['visitor', 'regular_attender', 'member', 'inactive', 'transferred', 'deceased'];
        $status = MembershipStatusService::normalizeAffiliationStatus((string) ($this->request->getPost('membership_status') ?? 'member'));
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'member';
        }
        $isHome = (int) ($this->request->getPost('is_primary') ?? 0) === 1;

        $operationalParishId = (int) ($this->getOperationalParishId() ?? 0);
        $profile = service('profileLookup')->findProfileInParishScope($profileId, $operationalParishId);
        if (!$profile) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Clergy.memberNotFoundInScope'), 404, 'MEMBER_NOT_FOUND_IN_SCOPE'), 404);
        }

        if ($code = $this->guardTargetUserMutation((int) ($profile['user_id'] ?? 0))) {
            return $this->jsonResponse($this->apiErrorPayload($this->hierarchyGuardMessage($code), 403, 'TARGET_OUTRANKS_ACTOR'), 403);
        }

        $targetChurch = $this->churchModel->findWithHierarchy($churchId);
        if (!$targetChurch || !$this->isParishWithinCurrentDioceseScope((int) ($targetChurch['diocese_id'] ?? 0))) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Clergy.targetParishOutsideDiocesan'), 403, 'PARISH_OUTSIDE_DIOCESAN_SCOPE'), 403);
        }

        if (!service('parishScopeValidator')->canActorAccessParish(auth()->user(), $churchId)) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Clergy.targetParishOutsideScope'), 403, 'PARISH_OUTSIDE_SCOPE'), 403);
        }

        $db = db_connect();
        $db->transStart();

        $ok = $this->getMemberParishAffiliationModel()->attachToParish((int) $profile['user_id'], $churchId, false, $status);
        if (!$ok) {
            $db->transRollback();
            return $this->jsonResponse($this->apiErrorPayload(lang('Clergy.unableAttachParish'), 500, 'PARISH_ATTACH_FAILED'), 500);
        }

        if ($isHome && !$this->getMemberParishAffiliationModel()->setHomeParish((int) $profile['user_id'], $churchId)) {
            $db->transRollback();
            return $this->jsonResponse($this->apiErrorPayload(lang('Clergy.unableSetHomeParish'), 500, 'HOME_PARISH_UPDATE_FAILED'), 500);
        }

        if (!$this->syncProfileParishMirror((int) $profile['id'], (int) $profile['user_id'], isset($profile['province_id']) ? (int) $profile['province_id'] : null, isset($profile['diocese_id']) ? (int) $profile['diocese_id'] : null)) {
            $db->transRollback();
            return $this->jsonResponse($this->apiErrorPayload(lang('Clergy.unableSyncProfileCompat'), 500, 'PROFILE_COMPAT_SYNC_FAILED'), 500);
        }

        $db->transComplete();
        if ($db->transStatus() === false) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Clergy.attachTransactionFailed'), 500, 'PARISH_ATTACH_TRANSACTION_FAILED'), 500);
        }

        $this->audit()->log('member.parish_attached', [
            'parish_id' => $churchId,
            'entity_type' => 'parish_affiliation',
            'entity_id' => (int) $profile['user_id'],
            'new_values' => [
                'profile_id' => $profileId,
                'parish_id' => $churchId,
                'membership_status' => $status,
                'is_primary' => $isHome,
            ],
        ]);

        (new ParishPeopleRegistryService())->syncParish($churchId);

        return $this->jsonResponse(['success' => true, 'message' => lang('Clergy.parishAttachedSuccess')]);
    }

    public function detachParish()
    {
        if ($redirect = $this->requirePermission('member.edit')) {
            return $redirect;
        }

        $profileId = (int) $this->request->getPost('profile_id');
        $churchId = (int) $this->request->getPost('parish_id');

        $operationalParishId = (int) ($this->getOperationalParishId() ?? 0);
        $profile = service('profileLookup')->findProfileInParishScope($profileId, $operationalParishId);
        if (!$profile) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Clergy.memberNotFoundInScope'), 404, 'MEMBER_NOT_FOUND_IN_SCOPE'), 404);
        }

        if ($code = $this->guardTargetUserMutation((int) ($profile['user_id'] ?? 0))) {
            return $this->jsonResponse($this->apiErrorPayload($this->hierarchyGuardMessage($code), 403, 'TARGET_OUTRANKS_ACTOR'), 403);
        }

        $affiliations = $this->getMemberParishAffiliationModel()->getUserAffiliations((int) $profile['user_id']);
        if (count($affiliations) <= 1) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Clergy.memberMustRemainAttached'), 400, 'MEMBER_LAST_PARISH_CONSTRAINT'), 400);
        }

        $targetChurch = $this->churchModel->findWithHierarchy($churchId);
        if (!$targetChurch || !$this->isParishWithinCurrentDioceseScope((int) ($targetChurch['diocese_id'] ?? 0))) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Clergy.targetParishOutsideDiocesan'), 403, 'PARISH_OUTSIDE_DIOCESAN_SCOPE'), 403);
        }

        if (!service('parishScopeValidator')->canActorAccessParish(auth()->user(), $churchId)) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Clergy.targetParishOutsideScope'), 403, 'PARISH_OUTSIDE_SCOPE'), 403);
        }

        $db = db_connect();
        $db->transStart();

        $ok = $this->getMemberParishAffiliationModel()->detachFromParish((int) $profile['user_id'], $churchId);
        if (!$ok) {
            $db->transRollback();
            return $this->jsonResponse($this->apiErrorPayload(lang('Clergy.unableDetachParish'), 500, 'PARISH_DETACH_FAILED'), 500);
        }

        if (!$this->syncProfileParishMirror((int) $profile['id'], (int) $profile['user_id'], isset($profile['province_id']) ? (int) $profile['province_id'] : null, isset($profile['diocese_id']) ? (int) $profile['diocese_id'] : null)) {
            $db->transRollback();
            return $this->jsonResponse($this->apiErrorPayload(lang('Clergy.unableSyncProfileCompat'), 500, 'PROFILE_COMPAT_SYNC_FAILED'), 500);
        }

        $db->transComplete();
        if ($db->transStatus() === false) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Clergy.detachTransactionFailed'), 500, 'PARISH_DETACH_TRANSACTION_FAILED'), 500);
        }

        $this->audit()->log('member.parish_detached', [
            'parish_id' => $churchId,
            'entity_type' => 'parish_affiliation',
            'entity_id' => (int) $profile['user_id'],
            'old_values' => [
                'profile_id' => $profileId,
                'parish_id' => $churchId,
            ],
        ]);

        (new ParishPeopleRegistryService())->syncParish($churchId);

        return $this->jsonResponse(['success' => true, 'message' => lang('Clergy.parishDetachedSuccess')]);
    }

    public function setHomeParish()
    {
        if ($redirect = $this->requirePermission('member.edit')) {
            return $redirect;
        }

        $profileId = (int) $this->request->getPost('profile_id');
        $churchId = (int) $this->request->getPost('parish_id');

        $operationalParishId = (int) ($this->getOperationalParishId() ?? 0);
        $profile = service('profileLookup')->findProfileInParishScope($profileId, $operationalParishId);
        if (!$profile) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Clergy.memberNotFoundInScope'), 404, 'MEMBER_NOT_FOUND_IN_SCOPE'), 404);
        }

        if ($code = $this->guardTargetUserMutation((int) ($profile['user_id'] ?? 0))) {
            return $this->jsonResponse($this->apiErrorPayload($this->hierarchyGuardMessage($code), 403, 'TARGET_OUTRANKS_ACTOR'), 403);
        }

        $affiliated = $this->getMemberParishAffiliationModel()->isUserAffiliatedWithParish((int) $profile['user_id'], $churchId, true);
        if (!$affiliated) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Clergy.memberNotAffiliatedParish'), 400, 'PARISH_AFFILIATION_REQUIRED'), 400);
        }

        $targetChurch = $this->churchModel->findWithHierarchy($churchId);
        if (!$targetChurch || !$this->isParishWithinCurrentDioceseScope((int) ($targetChurch['diocese_id'] ?? 0))) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Clergy.targetParishOutsideDiocesan'), 403, 'PARISH_OUTSIDE_DIOCESAN_SCOPE'), 403);
        }

        if (!service('parishScopeValidator')->canActorAccessParish(auth()->user(), $churchId)) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Clergy.targetParishOutsideScope'), 403, 'PARISH_OUTSIDE_SCOPE'), 403);
        }

        $db = db_connect();
        $db->transStart();

        $ok = $this->getMemberParishAffiliationModel()->setHomeParish((int) $profile['user_id'], $churchId);
        if (!$ok) {
            $db->transRollback();
            return $this->jsonResponse($this->apiErrorPayload(lang('Clergy.unableSetHomeParish'), 500, 'HOME_PARISH_UPDATE_FAILED'), 500);
        }

        if (!$this->syncProfileParishMirror((int) $profile['id'], (int) $profile['user_id'], isset($profile['province_id']) ? (int) $profile['province_id'] : null, isset($profile['diocese_id']) ? (int) $profile['diocese_id'] : null)) {
            $db->transRollback();
            return $this->jsonResponse($this->apiErrorPayload(lang('Clergy.unableSyncProfileCompat'), 500, 'PROFILE_COMPAT_SYNC_FAILED'), 500);
        }

        $db->transComplete();
        if ($db->transStatus() === false) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Clergy.setHomeTransactionFailed'), 500, 'HOME_PARISH_TRANSACTION_FAILED'), 500);
        }

        $this->audit()->log('member.home_parish_changed', [
            'parish_id' => $churchId,
            'entity_type' => 'parish_affiliation',
            'entity_id' => (int) $profile['user_id'],
            'new_values' => [
                'profile_id' => $profileId,
                'parish_id' => $churchId,
            ],
        ]);

        (new ParishPeopleRegistryService())->syncParish($churchId);

        return $this->jsonResponse(['success' => true, 'message' => lang('Clergy.homeParishUpdatedSuccess')]);
    }

    /**
     * Reassign diocesan staff to another church (ProvinceAdmin only, AJAX).
     * Province admins may only reassign provinceadmin-role users within their province.
     * Diocesan/church staff (diocesanadmin, churchadmin, clergy) are managed by their diocese.
     */
    public function reassign()
    {
        return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'PROVINCE_WRITE_DISABLED'), 403);
    }

    protected function resolveProvinceScopeForStaffManagement(): array
    {
        $profile = $this->getProfile();

        $provinceId = !empty($profile['province_id']) ? (int) $profile['province_id'] : null;
        if ($provinceId === null && !empty($profile['diocese_id'])) {
            $diocese = db_connect()->table('diocese')
                ->select('province_id')
                ->where('id', (int) $profile['diocese_id'])
                ->get()
                ->getRowArray();
            if (!empty($diocese['province_id'])) {
                $provinceId = (int) $diocese['province_id'];
            }
        }

        if ($provinceId === null) {
            $churchId = (int) ($this->getOperationalParishId() ?? 0);
            if ($churchId > 0) {
                $church = $this->churchModel->findWithHierarchy($churchId);
                if (!empty($church['province_id'])) {
                    $provinceId = (int) $church['province_id'];
                }
            }
        }

        return ['province_id' => $provinceId];
    }

    /**
     * @deprecated Scoped lookups are now performed at the query level via
     * ProfileModel::getProfileWithUser($id, $parishId). Keep helper removed to
     * prevent accidental post-fetch scope checks that could leak data.
     */

    protected function getAttachableParishesForCurrentScope(int $userId): array
    {
        $currentParishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($currentParishId <= 0) {
            return [];
        }

        $currentParish = $this->churchModel->findWithHierarchy($currentParishId);
        $dioceseId = (int) ($currentParish['diocese_id'] ?? 0);
        if ($dioceseId <= 0) {
            return [];
        }

        $existingAffiliations = $this->getMemberParishAffiliationModel()->getUserAffiliations($userId);
        $existingParishIds = array_map(static fn(array $row): int => (int) ($row['parish_id'] ?? 0), $existingAffiliations);

        $builder = db_connect()->table('parish')
            ->select('id, name')
            ->where('diocese_id', $dioceseId)
            ->orderBy('name', 'ASC');

        if (!empty($existingParishIds)) {
            $builder->whereNotIn('id', $existingParishIds);
        }

        return $builder->get()->getResultArray();
    }

    protected function isParishWithinCurrentDioceseScope(int $targetDioceseId): bool
    {
        if ($targetDioceseId <= 0) {
            return false;
        }

        $currentParishId = (int) ($this->getOperationalParishId() ?? 0);
        $currentParish = $currentParishId > 0 ? $this->churchModel->findWithHierarchy($currentParishId) : null;
        $currentDioceseId = (int) ($currentParish['diocese_id'] ?? 0);

        if ($currentDioceseId > 0) {
            return $currentDioceseId === $targetDioceseId;
        }

        if ($this->isDioceseAdmin()) {
            return $this->resolveDiocesanScopeId() === $targetDioceseId;
        }

        return false;
    }

    protected function syncProfileParishMirror(int $profileId, int $userId, ?int $fallbackProvinceId = null, ?int $fallbackDioceseId = null): bool
    {
        $affiliation = db_connect()->table('parish_affiliation')
            ->select('parish_id as parish_id, diocese_id')
            ->where('linked_user_id', $userId)
            ->where('is_member', 1)
            ->orderBy('is_primary', 'DESC')
            ->orderBy('updated_at', 'DESC')
            ->get()
            ->getFirstRow('array');

        $churchId = isset($affiliation['parish_id']) ? (int) $affiliation['parish_id'] : null;
        $dioceseId = isset($affiliation['diocese_id']) ? (int) $affiliation['diocese_id'] : null;
        $provinceId = $fallbackProvinceId;

        if ($churchId) {
            $church = $this->churchModel->findWithHierarchy($churchId);
            if ($church) {
                $provinceId = isset($church['province_id']) ? (int) $church['province_id'] : $provinceId;
                $dioceseId = isset($church['diocese_id']) ? (int) $church['diocese_id'] : $dioceseId;
            }
        }

        if ($dioceseId === null) {
            $dioceseId = $fallbackDioceseId;
        }

        return $this->getProfileModel()->update($profileId, [
            'parish_id' => $churchId,
            'diocese_id' => $dioceseId,
            'province_id' => $provinceId,
        ]);
    }

    protected function resolveSelectedDioceseForProvince(int $provinceId): ?int
    {
        $sessionDioceseId = session()->get('province_selected_diocese_id');
        if ($sessionDioceseId === null || $sessionDioceseId === '') {
            return null;
        }

        $candidate = (int) $sessionDioceseId;
        if ($candidate <= 0) {
            return null;
        }

        $exists = db_connect()->table('diocese')
            ->where('id', $candidate)
            ->where('province_id', $provinceId)
            ->countAllResults() > 0;

        if (!$exists) {
            return null;
        }

        return $candidate;
    }

    private function storeProfilePictureForUser($file, int $userId): ?string
    {
        if ($userId <= 0 || !$file || !$file->isValid() || $file->hasMoved()) {
            return null;
        }

        $folder = UploadPathService::profileAvatarRelativeDir($userId);
        $targetDir = UploadPathService::absoluteUploadDir($folder);
        if ($targetDir === null) {
            return null;
        }

        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            return null;
        }

        $newName = $file->getRandomName();
        $file->move($targetDir, $newName);

        return $folder . '/' . $newName;
    }

    /**
     * Resolves a stored upload path under WRITEPATH/uploads with traversal protection.
     * Supports legacy flat filenames by scoping to the member's user folder.
     */
    private function resolveStoredUploadAbsolutePath(string $storedPath, int $userId): ?string
    {
        $storedPath = trim($storedPath);
        if ($storedPath === '') {
            return null;
        }

        $uploadsRoot = rtrim((string) realpath(rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads'), DIRECTORY_SEPARATOR);
        if ($uploadsRoot === '') {
            return null;
        }

        $normalized = ltrim(str_replace('\\', '/', $storedPath), '/');
        $segments = array_values(array_filter(explode('/', $normalized), static fn(string $segment): bool => $segment !== '' && $segment !== '.'));
        foreach ($segments as $segment) {
            if ($segment === '..') {
                return null;
            }
        }

        $scopedPath = implode(DIRECTORY_SEPARATOR, $segments);
        if ($scopedPath === '') {
            return null;
        }

        $candidate = $uploadsRoot . DIRECTORY_SEPARATOR . $scopedPath;
        $candidateReal = realpath($candidate);
        if (is_string($candidateReal) && str_starts_with($candidateReal, $uploadsRoot . DIRECTORY_SEPARATOR)) {
            return $candidateReal;
        }

        $basename = basename($scopedPath);
        if ($basename === '' || $userId <= 0) {
            return null;
        }

        $legacyCandidates = [
            $uploadsRoot . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR . $userId . DIRECTORY_SEPARATOR . $basename,
            $uploadsRoot . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR . $userId . DIRECTORY_SEPARATOR . 'avatar' . DIRECTORY_SEPARATOR . $basename,
        ];

        foreach ($legacyCandidates as $legacy) {
            $legacyReal = realpath($legacy);
            if (is_string($legacyReal) && str_starts_with($legacyReal, $uploadsRoot . DIRECTORY_SEPARATOR)) {
                return $legacyReal;
            }
        }

        return null;
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

    // -------------------------------------------------------------------------
    // Province Staff CRUD  (province admin only — manages provinceadmin accounts
    // that are scoped to the same province; cannot touch diocesan/church staff)
    // -------------------------------------------------------------------------

    /**
     * AJAX: Return province staff profile data for edit-modal pre-population.
     */
    public function getProvinceStaff(int $profileId)
    {
        return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'PROVINCE_WRITE_DISABLED'), 403);
    }

    /**
     * AJAX: Create a new province-level staff member (assigns provinceadmin role).
     */
    public function storeProvinceStaff()
    {
        return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'PROVINCE_WRITE_DISABLED'), 403);
    }

    /**
     * AJAX: Update a province staff profile (name / phone only).
     */
    public function updateProvinceStaff(int $profileId)
    {
        return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'PROVINCE_WRITE_DISABLED'), 403);
    }

    /**
     * AJAX: Delete a province staff member.
     */
    public function deleteProvinceStaff(int $profileId)
    {
        return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'PROVINCE_WRITE_DISABLED'), 403);
    }
}
