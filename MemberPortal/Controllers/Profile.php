<?php

namespace MemberPortal\Controllers;

use App\Controllers\BaseController;
use App\Libraries\AddressHelper;
use App\Libraries\PhoneHelper;
use App\Services\MembershipStatusService;
use App\Services\PersonOnboardingService;

class Profile extends BaseController
{
    protected $profileModel;
    protected $userModel;
    protected ?array $profileColumnsCache = null;

    public function __construct()
    {
        $this->userModel = model('UserModel');
    }

    protected function getProfileModel()
    {
        if ($this->profileModel === null) {
            $this->profileModel = model('ProfileModel');
        }

        return $this->profileModel;
    }

    /**
     * Display current user's profile
     */
    public function index()
    {
        if (!auth()->loggedIn()) {
            return redirect()->to('login');
        }

        $user = auth()->user();
        $state = $this->resolveCurrentUserProfileState($user);

        if ($state['requires_completion']) {
            return redirect()->to('/profile/complete')->with('error', lang('Profile.pendingProfileRedirectNotice'));
        }

        $profile = $state['profile'];

        if (!$profile) {
            return redirect()->to('dashboard')->with('error', lang('App.errorNotFound'));
        }

        $isElevatedAdmin = $this->isElevatedAdmin($user);

        $data = array_merge($this->getCommonViewData('My Profile'), [
            'user' => $user,
        ]);
        // Override profile with the one we just fetched
        $data['profile'] = $profile;
        $data['isElevatedAdmin'] = $isElevatedAdmin;
        $data['adminScope'] = $isElevatedAdmin ? $this->resolveElevatedAdminScopeLabel($user) : null;
        $data['parishAffiliation'] = $this->resolveParishAffiliation($profile);
        $data['otherAffiliations'] = $this->resolveAllParishAffiliations((int) $user->id, (int) ($profile['parish_id'] ?? 0));
        $data['completionPct'] = $this->calculateCompletionPct($profile);
        $data['registrationStatus'] = $state['status'];
        $data['userGroups'] = $this->resolveLoggedInGroups($user);

        return view('MemberPortal\Views\Profile/view', $data);
    }

    /**
     * Edit current user's profile
     */
    public function edit()
    {
        if (!auth()->loggedIn()) {
            return redirect()->to('login');
        }

        if ($redirect = $this->requirePermission('profile.edit')) {
            return $redirect;
        }

        $user = auth()->user();
        $state = $this->resolveCurrentUserProfileState($user);

        if ($state['requires_completion']) {
            return redirect()->to('/profile/complete')->with('error', lang('Profile.pendingProfileRedirectNotice'));
        }

        $profile = $state['profile'];

        if (!$profile) {
            return redirect()->to('dashboard')->with('error', lang('App.errorNotFound'));
        }

        $isElevatedAdmin = $this->isElevatedAdmin($user);

        $data = array_merge($this->getCommonViewData('Edit My Profile'), [
            'user' => $user,
        ]);
        // Override profile with the one we just fetched
        $data['profile'] = $profile;
        $data['isElevatedAdmin'] = $isElevatedAdmin;
        $data['adminScope'] = $isElevatedAdmin ? $this->resolveElevatedAdminScopeLabel($user) : null;
        $data['parishAffiliation'] = $this->resolveParishAffiliation($profile);
        $data['otherAffiliations'] = $this->resolveAllParishAffiliations((int) $user->id, (int) ($profile['parish_id'] ?? 0));
        $data['provinces'] = db_connect()->table('province')
            ->select('id, province_number, name')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();
        $data['diocesesByProvince'] = $this->getDiocesesByProvinceOptions();
        $data['parishesByDiocese'] = $this->getParishesByDioceseOptions();
        $statusOptions = [];
        foreach (MembershipStatusService::profileStatuses() as $status) {
            $statusOptions[$status] = MembershipStatusService::label($status);
        }
        $data['membershipStatusOptions'] = $statusOptions;
        $data['registrationStatus'] = $state['status'];
        $data['primaryParishId'] = (int) ($profile['parish_id'] ?? 0);
        $data['pendingParishId'] = (int) ($state['registration_request']['parish_id'] ?? 0);

        return view('MemberPortal\Views\Profile/edit', $data);
    }

    /**
     * Update current user's profile
     */
    public function update()
    {
        if (!auth()->loggedIn()) {
            return redirect()->to('login');
        }

        if ($redirect = $this->requirePermission('profile.edit')) {
            return $redirect;
        }

        $user = auth()->user();
        $state = $this->resolveCurrentUserProfileState($user);

        if ($state['requires_completion']) {
            return redirect()->to('/profile/complete')->with('error', lang('Profile.pendingProfileRedirectNotice'));
        }

        $profile = $state['profile'];

        if (!$profile) {
            return redirect()->to('dashboard')->with('error', lang('App.errorNotFound'));
        }

        $rules = [
            'first_name' => 'permit_empty|string|max_length[255]',
            'middle_name' => 'permit_empty|string|max_length[255]',
            'last_name' => 'permit_empty|string|max_length[255]',
            'phone_country_code' => 'permit_empty|valid_calling_code',
            'phone' => 'permit_empty|valid_phone_e164',
            'address' => 'permit_empty|string',
            'address_line_2' => 'permit_empty|string',
            'city' => 'permit_empty|string|max_length[255]',
            'state' => 'permit_empty|string|max_length[50]',
            'zip' => 'permit_empty|string|max_length[20]',
            'country' => 'permit_empty|valid_country',
            'birth_date' => 'permit_empty|valid_date',
            'baptism_date' => 'permit_empty|valid_date',
            'spiritual_gifts' => 'permit_empty|string',
            'locale' => 'permit_empty|in_list[en,es,fr]',
            'profile_picture' => 'permit_empty|uploaded[profile_picture]|max_size[profile_picture,2048]|is_image[profile_picture]|mime_in[profile_picture,image/jpg,image/jpeg,image/png,image/gif]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $callingCode = $this->request->getPost('phone_country_code');
        $localPhone = $this->request->getPost('phone');
        $e164Phone = PhoneHelper::toE164($callingCode, $localPhone);

        $updateData = [
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
            'spiritual_gifts' => $this->request->getPost('spiritual_gifts'),
            'locale' => $this->request->getPost('locale') ?: 'en',
        ];

        foreach (array_keys($updateData) as $field) {
            if (!$this->profileColumnExists($field)) {
                unset($updateData[$field]);
            }
        }

        // Handle profile picture upload
        $file = $this->request->getFile('profile_picture');
        if ($file && $file->isValid() && !$file->hasMoved()) {
            // Delete old profile picture if exists
            if (!empty($profile['profile_picture'])) {
                $oldFile = $this->resolveStoredUploadAbsolutePath((string) $profile['profile_picture'], (int) $user->id);
                if ($oldFile !== null && is_file($oldFile)) {
                    @unlink($oldFile);
                }
            }

            $storedPath = $this->storeProfilePictureForUser($file, (int) $user->id);
            if ($storedPath !== null && $this->profileColumnExists('profile_picture')) {
                $updateData['profile_picture'] = $storedPath;
            }
        }

        $this->getProfileModel()->update($profile['id'], $updateData);

        // Sync the canonical person record with the updated profile data.
        (new \App\Services\PersonProfileSyncService())->syncFromProfile((int) $profile['id']);

        // Refresh cached locale in session so the change takes effect immediately
        $effectiveLocale = (string) ($updateData['locale'] ?? 'en');
        session()->set('user_locale', $effectiveLocale);
        service('request')->setLocale($effectiveLocale);

        // Sync primary parish and save any additional parish affiliations
        $this->syncPrimaryParishPeople($profile);
        $parishPeopleModel = model('ParishPeopleModel');
        $primaryParishId = (int) ($profile['parish_id'] ?? 0);
        $pendingParishId = (int) ($state['registration_request']['parish_id'] ?? 0);
        $affiliations = $this->request->getPost('affiliations') ?? [];
        $filteredAffiliations = [];
        foreach ($affiliations as $affiliation) {
            $affParishId = (int) ($affiliation['parish_id'] ?? 0);
            if ($affParishId === $primaryParishId || $affParishId === $pendingParishId) {
                return redirect()->back()->withInput()->with('error', lang('Profile.cannotAddDuplicateParish'));
            }
            if ($affParishId > 0) {
                $filteredAffiliations[] = $affiliation;
            }
        }
        $parishPeopleModel->replaceForUser((int) $user->id, $primaryParishId, $filteredAffiliations, (int) ($profile['id'] ?? 0));

        return redirect()->to('profile')->with('success', lang('App.successUpdated'));
    }

    /**
     * @return array{profile: array<string,mixed>|null, registration_request: array<string,mixed>|null, status: string, requires_completion: bool}
     */
    protected function resolveCurrentUserProfileState(object $user): array
    {
        $profileModel = $this->getProfileModel();
        $profile = $profileModel->where('user_id', $user->id)->first();
        $registrationRequest = $this->getCurrentRegistrationRequest();
        $status = $this->getRegistrationStatus($registrationRequest);

        $isStaffAdmin = $user->inGroup('systemadmin')
            || $user->inGroup('provinceadmin')
            || $user->inGroup('dioceseadmin')
            || $user->inGroup('parishadmin');

        return [
            'profile' => $profile,
            'registration_request' => $registrationRequest,
            'status' => $status,
            'requires_completion' => !$isStaffAdmin
                && (($status === 'pending_profile') || !$profile),
        ];
    }

    /**
     * Delete profile picture
     */
    public function deletePicture()
    {
        if (!auth()->loggedIn()) {
            return $this->jsonResponse(['success' => false, 'message' => lang('MemberPortal.notAuthenticated')], 401);
        }

        $user = auth()->user();
        $profile = $this->getProfileModel()->where('user_id', $user->id)->first();

        if (!$profile || empty($profile['profile_picture'])) {
            return $this->jsonResponse(['success' => false, 'message' => lang('MemberPortal.noPictureTodelete')], 404);
        }

        // Delete file from disk
        $filePath = $this->resolveStoredUploadAbsolutePath((string) $profile['profile_picture'], (int) $user->id);
        if ($filePath !== null && is_file($filePath)) {
            @unlink($filePath);
        }

        // Update database
        if ($this->profileColumnExists('profile_picture')) {
            $this->getProfileModel()->update($profile['id'], ['profile_picture' => null]);
        }

        return $this->jsonResponse(['success' => true, 'message' => lang('MemberPortal.picturDeletedSuccess')]);
    }

    /**
     * @return array<int,string>
     */
    private function getProfileColumns(): array
    {
        if ($this->profileColumnsCache !== null) {
            return $this->profileColumnsCache;
        }

        $db = db_connect();
        $table = $this->resolveExistingProfileTable();
        if ($table === null) {
            $this->profileColumnsCache = [];
            return [];
        }

        try {
            $columns = $db->getFieldNames($table);
            if ($columns !== []) {
                $this->profileColumnsCache = $columns;
                return $columns;
            }
        } catch (\Throwable $e) {
            // Fall through to empty result.
        }

        $this->profileColumnsCache = [];
        return [];
    }

    private function resolveExistingProfileTable(): ?string
    {
        $db = db_connect();
        $candidates = array_values(array_unique([
            $db->getPrefix() . 'profile',
            'profile',
            $db->getPrefix() . 'profiles',
            'profiles',
        ]));

        foreach ($candidates as $table) {
            try {
                $columns = $db->getFieldNames($table);
                if ($columns !== []) {
                    return $table;
                }
            } catch (\Throwable $e) {
                // Try next table-name variant.
            }
        }

        return null;
    }

    private function profileColumnExists(string $column): bool
    {
        $columns = array_map('strtolower', $this->getProfileColumns());
        return $columns === [] || in_array(strtolower($column), $columns, true);
    }

    private function storeProfilePictureForUser($file, int $userId): ?string
    {
        if ($userId <= 0 || !$file || !$file->isValid() || $file->hasMoved()) {
            return null;
        }

        $folder = \App\Services\UploadPathService::profileAvatarRelativeDir($userId);
        $targetDir = \App\Services\UploadPathService::absoluteUploadDir($folder);
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
     * Supports legacy flat filenames by scoping to the profile user's folder.
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

    /**
     * Resolve province/diocese/parish names for the user's profile.
     *
     * @return array{province: string, diocese: string, parish: string}
     */
    private function resolveParishAffiliation(?array $profile): array
    {
        $result = ['province' => '', 'diocese' => '', 'parish' => ''];

        if (!$profile) {
            return $result;
        }

        $db = db_connect();

        $provinceId = (int) ($profile['province_id'] ?? 0);
        $dioceseId = (int) ($profile['diocese_id'] ?? 0);
        $parishId = (int) ($profile['parish_id'] ?? 0);

        if ($provinceId > 0) {
            $row = $db->table('province')->select('name')->where('id', $provinceId)->get()->getRowArray();
            $result['province'] = (string) ($row['name'] ?? '');
        }

        if ($dioceseId > 0) {
            $row = $db->table('diocese')->select('name')->where('id', $dioceseId)->get()->getRowArray();
            $result['diocese'] = (string) ($row['name'] ?? '');
        }

        if ($parishId > 0) {
            $row = $db->table('parish')->select('name')->where('id', $parishId)->get()->getRowArray();
            $result['parish'] = (string) ($row['name'] ?? '');
        }

        return $result;
    }

    /**
     * Resolve all parish affiliations for a user, including the primary one.
     *
     * @return array<int, array<string, mixed>>
     */
    private function resolveAllParishAffiliations(int $userId, int $primaryParishId): array
    {
        $db = db_connect();

        try {
            $columns = array_map('strtolower', $db->getFieldNames('parish_people'));
            if (! in_array('parish_id', $columns, true)) {
                return [];
            }

            $rows = $db->table('parish_people pp')
                ->select('pp.id, pp.parish_id, p.diocese_id, d.province_id, pp.membership_status, p.name as parish_name')
                ->join('parish p', 'p.id = pp.parish_id', 'left')
                ->join('diocese d', 'd.id = p.diocese_id', 'left')
                ->where('pp.user_id', $userId)
                ->orderBy('p.name', 'ASC')
                ->get()
                ->getResultArray();
        } catch (\Throwable $e) {
            return [];
        }

        foreach ($rows as &$row) {
            $row['is_primary'] = ((int) $row['parish_id']) === $primaryParishId;
        }

        return $rows;
    }

    /**
     * Sync the primary parish from profile into parish_people.
     */
    private function syncPrimaryParishPeople(array $profile): void
    {
        $userId = (int) ($profile['user_id'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        model('ParishPeopleModel')->syncPrimaryParish($userId, $profile);
    }

    /**
     * Calculate profile completion percentage based on filled fields.
     */
    private function calculateCompletionPct(?array $profile): int
    {
        if (!$profile) {
            return 0;
        }

        $fields = [
            'first_name', 'last_name', 'phone', 'phone_country_code', 'address',
            'address_line_2', 'city', 'state', 'zip', 'country',
            'birth_date', 'baptism_date', 'spiritual_gifts', 'profile_picture',
        ];

        $filled = 0;
        $total = count($fields);

        foreach ($fields as $field) {
            $value = trim((string) ($profile[$field] ?? ''));
            if ($value !== '' && $value !== '0000-00-00') {
                $filled++;
            }
        }

        return (int) round(($filled / $total) * 100);
    }

    /**
     * Onboarding profile completion page for self-registered users.
     */
    public function complete()
    {
        if (!auth()->loggedIn()) {
            return redirect()->to('login');
        }

        $user = auth()->user();
        $registrationRequest = $this->getCurrentRegistrationRequest();
        $profile = $this->getProfileModel()->where('user_id', $user->id)->first();

        $legacyCompletionMode = $registrationRequest === null;

        if ($legacyCompletionMode && $profile) {
            return redirect()->to('/profile');
        }

        $status = $this->getRegistrationStatus($registrationRequest);
        if ($this->shouldLogoutForRegistrationStatus($registrationRequest)) {
            auth()->logout();

            return redirect()->to('/login');
        }

        if (!$legacyCompletionMode && $status !== 'pending_profile') {
            return redirect()->to('/dashboard');
        }

        $profileData = $profile;
        if (!$profileData) {
            $profileData = [
                'user_id' => (int) $user->id,
                'province_id' => $registrationRequest['province_id'] ?? null,
                'diocese_id' => $registrationRequest['diocese_id'] ?? null,
                'membership_status' => 'visitor',
            ];
        }

        $provinces = db_connect()->table('province')
            ->select('id, province_number, name')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        return view('MemberPortal\Views\Profile/complete', [
            'title' => 'Complete Profile',
            'user' => $user,
            'profile' => $profileData,
            'registration_request' => $registrationRequest,
            'legacy_completion_mode' => $legacyCompletionMode,
            'provinces' => $provinces,
            'diocesesByProvince' => $this->getDiocesesByProvinceOptions(),
            'parishesByDiocese' => $this->getParishesByDioceseOptions(),
        ]);
    }

    /**
     * Save onboarding profile details and move user to diocesan review.
     */
    public function completeSubmit()
    {
        if (!auth()->loggedIn()) {
            return redirect()->to('login');
        }

        // Backward compatibility for older clients posting parish_id.
        if ($this->request->getPost('parish_id') === null) {
            $legacyParishId = (int) ($this->request->getPost('parish_id') ?? 0);
            if ($legacyParishId > 0) {
                $post = (array) $this->request->getPost();
                $post['parish_id'] = $legacyParishId;
                $this->request->setGlobal('post', $post);
            }
        }

        $user = auth()->user();
        $registrationRequestModel = model('UserRegistrationRequestModel');
        $registrationRequest = $this->getCurrentRegistrationRequest();

        $legacyCompletionMode = $registrationRequest === null;

        $status = $this->getRegistrationStatus($registrationRequest);
        if ($this->shouldLogoutForRegistrationStatus($registrationRequest)) {
            auth()->logout();

            return redirect()->to('/login');
        }

        if (!$legacyCompletionMode && $status !== 'pending_profile') {
            return redirect()->to('/dashboard');
        }

        $rules = [
            'first_name' => 'required|min_length[2]|max_length[100]',
            'last_name' => 'required|min_length[2]|max_length[100]',
            'phone_country_code' => 'permit_empty|valid_calling_code',
            'phone' => 'permit_empty|valid_phone_e164',
        ];

        if (!$legacyCompletionMode) {
            $rules['province_id'] = 'required|is_not_unique[province.id]';
            $rules['diocese_id'] = 'required|is_not_unique[diocese.id]';
            $rules['parish_id'] = 'required|is_not_unique[parish.id]';
        }

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $provinceId = 0;
        $dioceseId = 0;
        $parishId = 0;

        if (!$legacyCompletionMode) {
            $provinceId = (int) $this->request->getPost('province_id');
            $dioceseId = (int) $this->request->getPost('diocese_id');
            $parishId = (int) $this->request->getPost('parish_id');

            $dioceseValid = db_connect()->table('diocese')
                ->where('id', $dioceseId)
                ->where('province_id', $provinceId)
                ->countAllResults() > 0;

            if (!$dioceseValid) {
                return redirect()->back()->withInput()->with('error', lang('Profile.dioceseMismatch'));
            }

            $parishValid = db_connect()->table('parish')
                ->where('id', $parishId)
                ->where('diocese_id', $dioceseId)
                ->countAllResults() > 0;

            if (!$parishValid) {
                return redirect()->back()->withInput()->with('error', lang('Profile.parishDioceseMismatch'));
            }
        }

        $profile = $this->getProfileModel()->where('user_id', $user->id)->first();
        if (!$profile) {
            $profileInsertData = [
                'user_id' => $user->id,
                'membership_status' => 'visitor',
            ];

            if (!$legacyCompletionMode) {
                $profileInsertData['province_id'] = $provinceId;
            }

            $profileInsert = $this->filterPayloadByTableColumns('profile', $profileInsertData);
            $this->getProfileModel()->insert($profileInsert);
            $profile = $this->getProfileModel()->where('user_id', $user->id)->first();
        }

        $callingCode = $this->request->getPost('phone_country_code');
        $localPhone = $this->request->getPost('phone');
        $e164Phone = PhoneHelper::toE164($callingCode, $localPhone);

        $profileUpdateData = [
            'first_name' => $this->request->getPost('first_name'),
            'last_name' => $this->request->getPost('last_name'),
            'phone' => $e164Phone,
            'phone_country_code' => $callingCode ?: null,
        ];

        if (!$legacyCompletionMode) {
            $profileUpdateData['province_id'] = $provinceId;
            $profileUpdateData['diocese_id'] = $dioceseId;
            $profileUpdateData['membership_status'] = 'pending';
        }

        $profileUpdate = $this->filterPayloadByTableColumns('profile', $profileUpdateData);

        $this->getProfileModel()->update($profile['id'], $profileUpdate);

        $completedProfile = $this->getProfileModel()->where('user_id', $user->id)->first();
        if ($completedProfile) {
            // Sync the canonical person record with the completed profile data.
            (new \App\Services\PersonProfileSyncService())->syncFromProfile((int) $completedProfile['id']);

            $this->syncPrimaryParishPeople($completedProfile);

            if (!$legacyCompletionMode && $parishId > 0) {
                (new PersonOnboardingService())->start(
                    (int) $completedProfile['id'],
                    $parishId,
                    (int) (auth()->id() ?? 0) ?: null
                );
            }
        }

        if ($legacyCompletionMode) {
            return redirect()->to('/profile')->with('success', lang('Profile.completeSuccess'));
        }

        $registrationRequestUpdate = $this->filterPayloadByTableColumns('user_registration_request', [
            'province_id' => $provinceId,
            'diocese_id' => $dioceseId,
            'parish_id' => $parishId,
            'requested_parish_id' => $parishId,
            'requested_parish_id' => $parishId,
            'parish_id' => $parishId,
            'status' => 'pending_review',
            'profile_completed_at' => date('Y-m-d H:i:s'),
        ]);

        $registrationRequestModel->update($registrationRequest['id'], $registrationRequestUpdate);

        // Top-tier admins who already hold an elevated role are auto-approved so they
        // cannot be locked out by a lower-tier parish admin review queue.
        $isElevatedAdmin = $this->isElevatedAdmin($user);

        if ($isElevatedAdmin) {
            $approvalService = new \App\Services\RegistrationApprovalService();
            $error = $approvalService->autoApproveForElevatedAdmin(
                (int) $user->id,
                (int) $registrationRequest['id'],
                null
            );

            if ($error !== null) {
                return redirect()->to('/dashboard')->with('error', $error);
            }

            if ($completedProfile && $parishId > 0) {
                (new PersonOnboardingService())->advance(
                    (int) $completedProfile['id'],
                    'completed',
                    $parishId,
                    (int) $user->id,
                    'Auto-approved because the requester already holds an elevated admin role.'
                );
            }

            $completedProfile = $this->getProfileModel()->where('user_id', $user->id)->first();
            if ($completedProfile) {
                $this->syncPrimaryParishPeople($completedProfile);
            }

            return redirect()->to($this->resolveAuthenticatedLandingPath($user, $this->getCurrentRegistrationRequest()))
                ->with('success', lang('Profile.completionSuccess'));
        }

        $this->logRegistrationAudit(
            $parishId,
            (int) $user->id,
            'registration_submitted_for_review',
            'Submitted onboarding profile and selected parish for parish-admin authorization.'
        );

        $this->notifyParishAdminsOfPendingReview(
            $parishId,
            (int) $registrationRequest['id'],
            (int) $user->id,
            (string) $this->request->getPost('first_name'),
            (string) $this->request->getPost('last_name'),
            $user->email ?? $user->username ?? ''
        );

        return redirect()->to('/dashboard')->with('success', lang('Profile.completionSuccess'));
    }

    /**
     * Show the parish-join request form for existing users with no primary parish.
     */
    public function joinParish()
    {
        if (!auth()->loggedIn()) {
            return redirect()->to('login');
        }

        $user = auth()->user();
        $profile = $this->getProfileModel()->where('user_id', $user->id)->first();
        $registrationRequest = $this->getCurrentRegistrationRequest();

        if ($profile && !empty($profile['parish_id'])) {
            return redirect()->to('/profile')->with('error', lang('Profile.joinParishAlreadyAssigned'));
        }

        $status = $this->getRegistrationStatus($registrationRequest);
        if ($status === 'pending_review') {
            return redirect()->to('/profile')->with('success', lang('Profile.joinParishPendingReview'));
        }
        if (in_array($status, ['dismissed', 'banned'], true)) {
            return redirect()->to('/profile')->with('error', lang('Profile.joinParishRejected'));
        }

        $profileData = $profile ?: [
            'user_id' => (int) $user->id,
            'first_name' => '',
            'last_name' => '',
            'phone' => '',
        ];

        return view('MemberPortal\Views\Profile/join_parish', [
            'title' => 'Join a Parish',
            'user' => $user,
            'profile' => $profileData,
            'provinces' => db_connect()->table('province')
                ->select('id, province_number, name')
                ->orderBy('sort_order', 'ASC')
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray(),
            'diocesesByProvince' => $this->getDiocesesByProvinceOptions(),
            'parishesByDiocese' => $this->getParishesByDioceseOptions(),
        ]);
    }

    /**
     * Save a parish-join request from an existing user and queue it for admin review.
     */
    public function joinParishSubmit()
    {
        if (!auth()->loggedIn()) {
            return redirect()->to('login');
        }

        $user = auth()->user();
        $profile = $this->getProfileModel()->where('user_id', $user->id)->first();
        $registrationRequest = $this->getCurrentRegistrationRequest();

        if ($profile && !empty($profile['parish_id'])) {
            return redirect()->to('/profile')->with('error', lang('Profile.joinParishAlreadyAssigned'));
        }

        $status = $this->getRegistrationStatus($registrationRequest);
        if ($status === 'pending_review') {
            return redirect()->to('/profile')->with('success', lang('Profile.joinParishPendingReview'));
        }
        if (in_array($status, ['dismissed', 'banned'], true)) {
            return redirect()->to('/profile')->with('error', lang('Profile.joinParishRejected'));
        }

        $rules = [
            'first_name' => 'required|min_length[2]|max_length[100]',
            'last_name' => 'required|min_length[2]|max_length[100]',
            'phone' => 'permit_empty|max_length[50]',
            'province_id' => 'required|is_not_unique[province.id]',
            'diocese_id' => 'required|is_not_unique[diocese.id]',
            'parish_id' => 'required|is_not_unique[parish.id]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $provinceId = (int) $this->request->getPost('province_id');
        $dioceseId = (int) $this->request->getPost('diocese_id');
        $parishId = (int) $this->request->getPost('parish_id');

        $dioceseValid = db_connect()->table('diocese')
            ->where('id', $dioceseId)
            ->where('province_id', $provinceId)
            ->countAllResults() > 0;
        if (!$dioceseValid) {
            return redirect()->back()->withInput()->with('error', lang('Profile.dioceseMismatch'));
        }

        $parishValid = db_connect()->table('parish')
            ->where('id', $parishId)
            ->where('diocese_id', $dioceseId)
            ->countAllResults() > 0;
        if (!$parishValid) {
            return redirect()->back()->withInput()->with('error', lang('Profile.parishDioceseMismatch'));
        }

        $now = date('Y-m-d H:i:s');

        $firstName = (string) $this->request->getPost('first_name');
        $lastName = (string) $this->request->getPost('last_name');
        $phone = $this->request->getPost('phone') ?: null;

        if (!$profile) {
            $insert = $this->filterPayloadByTableColumns('profile', [
                'user_id' => (int) $user->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $phone,
                'province_id' => $provinceId,
                'diocese_id' => $dioceseId,
                'membership_status' => 'visitor',
            ]);
            $this->getProfileModel()->insert($insert);
            $profile = $this->getProfileModel()->where('user_id', $user->id)->first();
        } else {
            $update = $this->filterPayloadByTableColumns('profile', [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $phone,
                'province_id' => $provinceId,
                'diocese_id' => $dioceseId,
                'membership_status' => 'visitor',
            ]);
            $this->getProfileModel()->update($profile['id'], $update);
        }

        if ($profile && $parishId > 0) {
            (new PersonOnboardingService())->start(
                (int) $profile['id'],
                $parishId,
                (int) (auth()->id() ?? 0) ?: null
            );
        }

        $registrationRequestModel = model('UserRegistrationRequestModel');
        $requestPayload = $this->filterPayloadByTableColumns('user_registration_request', [
            'user_id' => (int) $user->id,
            'province_id' => $provinceId,
            'diocese_id' => $dioceseId,
            'parish_id' => $parishId,
            'requested_parish_id' => $parishId,
            'requested_parish_id' => $parishId,
            'parish_id' => $parishId,
            'status' => 'pending_review',
            'profile_completed_at' => $now,
        ]);

        if ($registrationRequest) {
            $registrationRequestModel->update($registrationRequest['id'], $requestPayload);
        } else {
            $registrationRequestModel->insert($requestPayload);
        }

        $registrationRequest = $this->getCurrentRegistrationRequest();

        // Top-tier admins that join a parish are auto-approved so they are not locked
        // by a parish admin's pending review queue.
        $isElevatedAdmin = $this->isElevatedAdmin($user);

        if ($isElevatedAdmin && $registrationRequest) {
            $approvalService = new \App\Services\RegistrationApprovalService();
            $error = $approvalService->autoApproveForElevatedAdmin(
                (int) $user->id,
                (int) $registrationRequest['id'],
                null
            );

            if ($error !== null) {
                return redirect()->to('/profile')->with('error', $error);
            }

            if ($profile && $parishId > 0) {
                (new PersonOnboardingService())->advance(
                    (int) $profile['id'],
                    'completed',
                    $parishId,
                    (int) $user->id,
                    'Auto-approved because the requester already holds an elevated admin role.'
                );
            }

            $profile = $this->getProfileModel()->where('user_id', $user->id)->first();
            if ($profile) {
                $this->syncPrimaryParishPeople($profile);
            }

            return redirect()->to('/profile')->with('success', lang('Profile.joinParishSuccess'));
        }

        if ($registrationRequest) {
            $this->notifyParishAdminsOfPendingReview(
                $parishId,
                (int) $registrationRequest['id'],
                (int) $user->id,
                $firstName,
                $lastName,
                $user->email ?? $user->username ?? ''
            );
        }

        return redirect()->to('/profile')->with('success', lang('Profile.joinParishSuccess'));
    }

    private function notifyParishAdminsOfPendingReview(int $parishId, int $requestId, int $requestUserId, string $firstName, string $lastName, string $emailAddress): void
    {
        if ($parishId <= 0) {
            return;
        }

        $recipients = $this->getParishAdminRecipients($parishId);
        if ($recipients === []) {
            $this->logRegistrationAudit(
                $parishId,
                $requestUserId,
                'registration_review_notification_missing_recipients',
                'No admin recipients found for selected parish.'
            );
            return;
        }

        $parish = db_connect()->table('parish')
            ->select('name')
            ->where('id', $parishId)
            ->get()
            ->getRowArray();

        $requesterName = trim($firstName . ' ' . $lastName);
        $dashboardUrl = base_url('members');
        $subject = 'New registration awaiting parish review';
        $message = "A new user registration is awaiting review.\n\n"
            . 'Parish: ' . ($parish['name'] ?? 'Unknown') . "\n"
            . 'Applicant: ' . ($requesterName !== '' ? $requesterName : 'Name not provided') . "\n"
            . 'Email: ' . $emailAddress . "\n\n"
            . 'Action needed: authorize or ban this registration in the Members module.\n'
            . 'Request ID: #' . $requestId . "\n"
            . 'Review: ' . $dashboardUrl;

        $email = service('email');
        $delivered = 0;

        foreach ($recipients as $recipient) {
            try {
                $email->clear(true);
                $email->setTo($recipient);
                $email->setSubject($subject);
                $email->setMessage($message);
                $email->send();
                $delivered++;
            } catch (\Throwable $e) {
                log_message('warning', 'Failed sending parish pending review email to {email}: {message}', [
                    'email' => $recipient,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $this->logRegistrationAudit(
            $parishId,
            $requestUserId,
            'registration_review_notification_sent',
            'Pending registration notification dispatched to ' . $delivered . ' admin recipient(s).'
        );
    }

    private function getParishAdminRecipients(int $parishId): array
    {
        $profileTable = $this->resolveExistingProfileTable();
        if ($profileTable === null) {
            return [];
        }

        $columns = array_map('strtolower', $this->getProfileColumns());
        $parishColumn = in_array('parish_id', $columns, true) ? 'parish_id' : 'parish_id';
        $hasDioceseId = in_array('diocese_id', $columns, true);
        $hasProvinceId = in_array('province_id', $columns, true);

        $parishColumns = array_map('strtolower', db_connect()->getFieldNames('parish'));
        $parishSelect    = ['id'];
        if (in_array('diocese_id', $parishColumns, true)) {
            $parishSelect[] = 'diocese_id';
        }
        if (in_array('province_id', $parishColumns, true)) {
            $parishSelect[] = 'province_id';
        }

        $parish = db_connect()->table('parish')
            ->select(implode(', ', $parishSelect))
            ->where('id', $parishId)
            ->get()
            ->getRowArray();

        $dioceseId = (int) ($parish['diocese_id'] ?? 0);
        $provinceId = (int) ($parish['province_id'] ?? 0);

        $emails = $this->getAdminEmailsByScope($profileTable, $parishColumn, 'parishadmin', $parishId);

        if ($dioceseId > 0 && $hasDioceseId) {
            $emails = array_merge($emails, $this->getAdminEmailsByScope($profileTable, 'diocese_id', 'diocesanadmin', $dioceseId));
        }

        if ($provinceId > 0 && $hasProvinceId) {
            $emails = array_merge($emails, $this->getAdminEmailsByScope($profileTable, 'province_id', 'provinceadmin', $provinceId));
        }

        return array_values(array_unique($emails));
    }

    private function getAdminEmailsByScope(string $profileTable, string $profileColumn, string $group, int $scopeId): array
    {
        $rows = db_connect()->table('auth_groups_users agu')
            ->select('ai.secret as email')
            ->distinct()
            ->join('users u', 'u.id = agu.user_id', 'inner')
            ->join($profileTable . ' p', 'p.user_id = u.id', 'left')
            ->join('auth_identities ai', 'ai.user_id = u.id AND ai.type = "email_password"', 'inner')
            ->where('agu.group', $group)
            ->where('p.' . $profileColumn, $scopeId)
            ->get()
            ->getResultArray();

        $emails = [];
        foreach ($rows as $row) {
            $email = trim((string) ($row['email'] ?? ''));
            if ($email !== '') {
                $emails[] = $email;
            }
        }

        return $emails;
    }

    private function logRegistrationAudit(int $parishId, int $targetUserId, string $action, string $details): void
    {
        $actorUserId = (int) auth()->id();
        if ($actorUserId <= 0 || $targetUserId <= 0) {
            return;
        }

        try {
            model('ParishRoleAuditModel')->insert([
                'parish_id' => $parishId > 0 ? $parishId : null,
                'actor_user_id' => $actorUserId,
                'target_user_id' => $targetUserId,
                'action' => $action,
                'role' => null,
                'details' => $details,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('warning', 'Failed writing registration audit log: {message}', ['message' => $e->getMessage()]);
        }
    }

    private function getDiocesesByProvinceOptions(): array
    {
        $rows = db_connect()->table('diocese')
            ->select('id, province_id, name')
            ->orderBy('province_id', 'ASC')
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        $grouped = [];

        foreach ($rows as $row) {
            $provinceId = (string) ((int) ($row['province_id'] ?? 0));
            if ($provinceId === '0') {
                continue;
            }

            $grouped[$provinceId][] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
            ];
        }

        return $grouped;
    }

    private function getParishesByDioceseOptions(): array
    {
        $rows = db_connect()->table('parish')
            ->select('id, diocese_id, name, parish_ueid')
            ->orderBy('diocese_id', 'ASC')
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        $grouped = [];

        foreach ($rows as $row) {
            $dioceseId = (string) ((int) ($row['diocese_id'] ?? 0));
            if ($dioceseId === '0') {
                continue;
            }

            $grouped[$dioceseId][] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'parish_ueid' => (string) ($row['parish_ueid'] ?? ''),
            ];
        }

        return $grouped;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function filterPayloadByTableColumns(string $baseTable, array $payload): array
    {
        $db = db_connect();
        $tableVariants = [$baseTable];
        if (str_ends_with($baseTable, 's')) {
            $tableVariants[] = substr($baseTable, 0, -1);
        } else {
            $tableVariants[] = $baseTable . 's';
        }

        $candidates = [];
        foreach (array_values(array_unique($tableVariants)) as $variant) {
            $candidates[] = $db->getPrefix() . $variant;
            $candidates[] = $variant;
        }
        $candidates = array_values(array_unique($candidates));

        $columns = [];
        foreach ($candidates as $table) {
            try {
                $found = array_map('strtolower', $db->getFieldNames($table));
                if ($found !== []) {
                    $columns = $found;
                    break;
                }
            } catch (\Throwable $e) {
                // Try next table-name variant.
            }
        }

        if ($columns === []) {
            return $payload;
        }

        foreach (array_keys($payload) as $key) {
            if (!in_array(strtolower((string) $key), $columns, true)) {
                unset($payload[$key]);
            }
        }

        return $payload;
    }
}
