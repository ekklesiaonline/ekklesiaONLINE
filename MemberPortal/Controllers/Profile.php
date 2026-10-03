<?php

namespace MemberPortal\Controllers;

use App\Controllers\BaseController;
use App\Libraries\PhoneHelper;
use App\Libraries\SchemaResolver;
use App\Services\HierarchyService;
use App\Services\MembershipStatusService;
use App\Services\RegistrationNotificationService;

class Profile extends BaseController
{
    protected $profileModel;
    protected $userModel;
    protected ?array $profileColumnsCache = null;
    protected HierarchyService $hierarchyService;
    protected RegistrationNotificationService $registrationNotifications;
    protected SchemaResolver $schemaResolver;

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
    protected function getProfileColumns(): array
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

    protected function resolveExistingProfileTable(): ?string
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
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    protected function filterPayloadByTableColumns(string $baseTable, array $payload): array
    {
        return $this->schemaResolver->filterPayloadByTableColumns($baseTable, $payload);
    }
}
