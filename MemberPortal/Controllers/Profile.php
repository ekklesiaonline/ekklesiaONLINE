<?php

namespace MemberPortal\Controllers;

use App\Controllers\BaseController;
use App\Libraries\AddressHelper;
use App\Libraries\PhoneHelper;
use App\Libraries\SchemaResolver;
use App\Services\HierarchyService;
use App\Services\MembershipStatusService;
use App\Services\PersonOnboardingService;
use App\Services\RegistrationNotificationService;

class Profile extends BaseController
{
    protected $profileModel;
    protected $userModel;
    protected ?array $profileColumnsCache = null;
    private HierarchyService $hierarchyService;
    private RegistrationNotificationService $registrationNotifications;
    private SchemaResolver $schemaResolver;

    public function __construct()
    {
        $this->userModel = model('UserModel');
        $this->hierarchyService = new HierarchyService();
        $this->registrationNotifications = new RegistrationNotificationService();
        $this->schemaResolver = new SchemaResolver();
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
        $data['provinces'] = $this->hierarchyService->provincesList();
        $data['diocesesByProvince'] = $this->hierarchyService->diocesesByProvince();
        $data['parishesByDiocese'] = $this->hierarchyService->parishesByDiocese();
        $statusOptions = [];
        foreach (MembershipStatusService::affiliationStatuses() as $status) {
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
        // against the canonical parish_affiliation table.
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

        $personId = (int) ($profile['person_id'] ?? 0) ?: null;
        $affiliationService = new \App\Services\MemberAffiliationService();
        $affiliationService->ensurePrimaryAffiliationForUser((int) $user->id, $primaryParishId, $personId);
        $affiliationService->replaceSecondaryAffiliationsForUser((int) $user->id, $primaryParishId, $filteredAffiliations, $personId);

        return redirect()->to('profile')->with('success', lang('App.successUpdated'));
    }

    /**
     * @return array{profile: array<string,mixed>|null, registration_request: array<string,mixed>|null, status: string, requires_completion: bool}
     */
    protected function resolveCurrentUserProfileState(object $user): array
    {
        $profileModel = $this->getProfileModel();
        $profile = $profileModel->where('user_id', $user->id)->first();

        // profile.membership_status no longer exists as a column — decorate the
        // array with the canonical status from the user's parish affiliation so
        // views keep reading $profile['membership_status'].
        if (is_array($profile)) {
            $affiliationStatus = model(\App\Models\MemberParishAffiliationModel::class)
                ->statusForUser((int) $user->id);
            if ($affiliationStatus !== null) {
                $profile['membership_status'] = $affiliationStatus;
            }
        }

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
        return $this->schemaResolver->resolveExistingProfileTable();
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

        // Normalize the extension to a safe whitelist based on the detected MIME type.
        // Never trust the client-supplied extension — a file named evil.php with image
        // MIME headers could otherwise be stored with a .php extension.
        $safeExtension = $this->resolveSafeImageExtension($file);
        if ($safeExtension === null) {
            return null;
        }

        $newName = bin2hex(random_bytes(16)) . '.' . $safeExtension;
        $file->move($targetDir, $newName);

        return $folder . '/' . $newName;
    }

    /**
     * Resolve a safe image extension from the uploaded file's detected MIME type.
     * Returns null if the MIME type is not in the whitelist.
     *
     * @return string|null
     */
    private function resolveSafeImageExtension($file): ?string
    {
        $mimeType = strtolower((string) $file->getMimeType());

        return match ($mimeType) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png'              => 'png',
            'image/gif'              => 'gif',
            'image/webp'             => 'webp',
            default                  => null,
        };
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

        $result['province'] = $this->hierarchyService->nameFor('province', (int) ($profile['province_id'] ?? 0));
        $result['diocese'] = $this->hierarchyService->nameFor('diocese', (int) ($profile['diocese_id'] ?? 0));
        $result['parish'] = $this->hierarchyService->nameFor('parish', (int) ($profile['parish_id'] ?? 0));

        return $result;
    }

    /**
     * Resolve all parish affiliations for a user, including the primary one.
     *
     * @return array<int, array<string, mixed>>
     */
    private function resolveAllParishAffiliations(int $userId, int $primaryParishId): array
    {
        return $this->hierarchyService->parishAffiliationsForUser($userId, $primaryParishId);
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
            ];
        }

        $provinces = $this->hierarchyService->provincesList();

        return view('MemberPortal\Views\Profile/complete', [
            'title' => 'Complete Profile',
            'user' => $user,
            'profile' => $profileData,
            'registration_request' => $registrationRequest,
            'legacy_completion_mode' => $legacyCompletionMode,
            'provinces' => $provinces,
            'diocesesByProvince' => $this->hierarchyService->diocesesByProvince(),
            'parishesByDiocese' => $this->hierarchyService->parishesByDiocese(),
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

            if (!$this->hierarchyService->dioceseInProvince($dioceseId, $provinceId)) {
                return redirect()->back()->withInput()->with('error', lang('Profile.dioceseMismatch'));
            }

            if (!$this->hierarchyService->parishInDiocese($parishId, $dioceseId)) {
                return redirect()->back()->withInput()->with('error', lang('Profile.parishDioceseMismatch'));
            }
        }

        $db = db_connect();
        $db->transStart();

        $profile = $this->getProfileModel()->where('user_id', $user->id)->first();
        if (!$profile) {
            $profileInsertData = [
                'user_id' => $user->id,
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
        }

        $profileUpdate = $this->filterPayloadByTableColumns('profile', $profileUpdateData);

        $this->getProfileModel()->update($profile['id'], $profileUpdate);

        $completedProfile = $this->getProfileModel()->where('user_id', $user->id)->first();
        if ($completedProfile) {
            // Sync the canonical person record with the completed profile data.
            (new \App\Services\PersonProfileSyncService())->syncFromProfile((int) $completedProfile['id']);

            (new \App\Services\MemberAffiliationService())->ensurePrimaryAffiliationForUser(
                (int) $user->id,
                (int) ($completedProfile['parish_id'] ?? 0),
                (int) ($completedProfile['person_id'] ?? 0) ?: null
            );

            if (!$legacyCompletionMode && $parishId > 0) {
                (new PersonOnboardingService())->start(
                    (int) $completedProfile['id'],
                    $parishId,
                    (int) (auth()->id() ?? 0) ?: null
                );
            }
        }

        if (!$legacyCompletionMode && $registrationRequest) {
            $registrationRequestUpdate = $this->filterPayloadByTableColumns('user_registration_requests', [
                'province_id'          => $provinceId,
                'diocese_id'           => $dioceseId,
                'parish_id'            => $parishId,
                'requested_parish_id'  => $parishId,
                'status'               => 'pending_review',
                'profile_completed_at' => date('Y-m-d H:i:s'),
            ]);

            $registrationRequestModel->update($registrationRequest['id'], $registrationRequestUpdate);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return redirect()->back()->withInput()->with('error', lang('Profile.completeError') ?? 'Unable to complete profile.');
        }

        if ($legacyCompletionMode) {
            return redirect()->to('/profile')->with('success', lang('Profile.completeSuccess'));
        }

        // Top-tier admins who already hold an elevated role are auto-approved so they
        // cannot be locked out by a lower-tier parish admin review queue.
        $isElevatedAdmin = $this->isElevatedAdmin($user);

        if ($isElevatedAdmin && $registrationRequest) {
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
                (new \App\Services\MemberAffiliationService())->ensurePrimaryAffiliationForUser(
                    (int) $user->id,
                    (int) ($completedProfile['parish_id'] ?? 0),
                    (int) ($completedProfile['person_id'] ?? 0) ?: null
                );
            }

            return redirect()->to($this->resolveAuthenticatedLandingPath($user, $this->getCurrentRegistrationRequest()))
                ->with('success', lang('Profile.completionSuccess'));
        }

        $this->registrationNotifications->logAudit(
            $parishId,
            (int) auth()->id(),
            (int) $user->id,
            'registration_submitted_for_review',
            'Submitted onboarding profile and selected parish for parish-admin authorization.'
        );

        $this->registrationNotifications->notifyParishAdminsOfPendingReview(
            $parishId,
            (int) $registrationRequest['id'],
            (int) $user->id,
            (string) $this->request->getPost('first_name'),
            (string) $this->request->getPost('last_name'),
            $user->email ?? $user->username ?? '',
            $this->resolveExistingProfileTable(),
            array_map('strtolower', $this->getProfileColumns()),
            (int) auth()->id()
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
            'provinces' => $this->hierarchyService->provincesList(),
            'diocesesByProvince' => $this->hierarchyService->diocesesByProvince(),
            'parishesByDiocese' => $this->hierarchyService->parishesByDiocese(),
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

        if (!$this->hierarchyService->dioceseInProvince($dioceseId, $provinceId)) {
            return redirect()->back()->withInput()->with('error', lang('Profile.dioceseMismatch'));
        }

        if (!$this->hierarchyService->parishInDiocese($parishId, $dioceseId)) {
            return redirect()->back()->withInput()->with('error', lang('Profile.parishDioceseMismatch'));
        }

        $now = date('Y-m-d H:i:s');

        $firstName = (string) $this->request->getPost('first_name');
        $lastName = (string) $this->request->getPost('last_name');
        $phone = $this->request->getPost('phone') ?: null;

        $db = db_connect();
        $db->transStart();

        if (!$profile) {
            $insert = $this->filterPayloadByTableColumns('profile', [
                'user_id' => (int) $user->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $phone,
                'province_id' => $provinceId,
                'diocese_id' => $dioceseId,
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
        $requestPayload = $this->filterPayloadByTableColumns('user_registration_requests', [
            'user_id' => (int) $user->id,
            'province_id' => $provinceId,
            'diocese_id' => $dioceseId,
            'parish_id' => $parishId,
            'requested_parish_id' => $parishId,
            'status' => 'pending_review',
            'profile_completed_at' => $now,
        ]);

        if ($registrationRequest) {
            $registrationRequestModel->update($registrationRequest['id'], $requestPayload);
        } else {
            $registrationRequestModel->insert($requestPayload);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return redirect()->back()->withInput()->with('error', lang('Profile.joinParishError') ?? 'Unable to process join request.');
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
                (new \App\Services\MemberAffiliationService())->ensurePrimaryAffiliationForUser(
                    (int) $user->id,
                    (int) ($profile['parish_id'] ?? 0),
                    (int) ($profile['person_id'] ?? 0) ?: null
                );
            }

            return redirect()->to('/profile')->with('success', lang('Profile.joinParishSuccess'));
        }

        if ($registrationRequest) {
            $this->registrationNotifications->notifyParishAdminsOfPendingReview(
                $parishId,
                (int) $registrationRequest['id'],
                (int) $user->id,
                $firstName,
                $lastName,
                $user->email ?? $user->username ?? '',
                $this->resolveExistingProfileTable(),
                array_map('strtolower', $this->getProfileColumns()),
                (int) auth()->id()
            );
        }

        return redirect()->to('/profile')->with('success', lang('Profile.joinParishSuccess'));
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function filterPayloadByTableColumns(string $baseTable, array $payload): array
    {
        return $this->schemaResolver->filterPayloadByTableColumns($baseTable, $payload);
    }
}
