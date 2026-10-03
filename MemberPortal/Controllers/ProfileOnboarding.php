<?php

namespace MemberPortal\Controllers;

use App\Libraries\PhoneHelper;
use App\Services\PersonOnboardingService;

/**
 * ProfileOnboarding
 *
 * Profile-completion and parish-join flows: complete/completeSubmit and
 * joinParish/joinParishSubmit. Extends Profile to reuse the shared profile
 * helpers (getProfileColumns, resolveExistingProfileTable,
 * filterPayloadByTableColumns) and injected services. Extracted from
 * Profile (phase 4 split); routes and behavior unchanged.
 */
class ProfileOnboarding extends Profile
{

    /**
     * Onboarding profile completion page for self-registered users.
     */
    public function complete()
    {
        if (!auth()->loggedIn()) {
            return redirect()->to('login');
        }

        $user = auth()->user();
        if ($user !== null && isset($user->active) && $user->active === false) {
            $userId = (int) auth()->id();
            auth()->logout();
            session()->destroy();

            service('audit')->log('auth.blocked.inactive', [
                'user_id'     => $userId,
                'entity_type' => 'user',
                'entity_id'   => $userId,
                'meta'        => ['reason' => 'inactive', 'path' => uri_string()],
            ]);

            return redirect()->to('/login')->with('error', lang('Auth.accountInactive'));
        }

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

        $user = auth()->user();
        if ($user !== null && isset($user->active) && $user->active === false) {
            $userId = (int) auth()->id();
            auth()->logout();
            session()->destroy();

            service('audit')->log('auth.blocked.inactive', [
                'user_id'     => $userId,
                'entity_type' => 'user',
                'entity_id'   => $userId,
                'meta'        => ['reason' => 'inactive', 'path' => uri_string()],
            ]);

            return redirect()->to('/login')->with('error', lang('Auth.accountInactive'));
        }

        $registrationRequestModel = model('UserRegistrationRequestModel');
        $registrationRequest = $this->getCurrentRegistrationRequest();

        $legacyCompletionMode = $registrationRequest === null;

        // Lock hierarchy fields to the registered request to reject/ignore client-tampered POST values.
        if (!$legacyCompletionMode && $registrationRequest !== null) {
            $post = (array) $this->request->getPost();
            $post['province_id'] = (int) ($registrationRequest['province_id'] ?? ($post['province_id'] ?? 0));
            $post['diocese_id']  = (int) ($registrationRequest['diocese_id'] ?? ($post['diocese_id'] ?? 0));
            $post['parish_id']   = (int) (
                $registrationRequest['requested_parish_id']
                ?? $registrationRequest['parish_id']
                ?? ($post['parish_id'] ?? 0)
            );
            $this->request->setGlobal('post', $post);
        }

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
            $provinceId = (int) ($registrationRequest['province_id'] ?? $this->request->getPost('province_id'));
            $dioceseId  = (int) ($registrationRequest['diocese_id'] ?? $this->request->getPost('diocese_id'));
            $parishId   = (int) (
                $registrationRequest['requested_parish_id']
                ?? $registrationRequest['parish_id']
                ?? $this->request->getPost('parish_id')
            );

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
        $personId = null;
        if ($completedProfile) {
            // Sync the canonical person record with the completed profile data.
            $personId = (new \App\Services\PersonProfileSyncService())->syncFromProfile((int) $completedProfile['id'])
                ?? ((int) ($completedProfile['person_id'] ?? 0) ?: null);

            (new \App\Services\MemberAffiliationService())->ensurePrimaryAffiliationForUser(
                (int) $user->id,
                (int) ($completedProfile['parish_id'] ?? 0),
                $personId
            );

            if (!$legacyCompletionMode && $parishId > 0 && $personId !== null) {
                (new PersonOnboardingService())->start(
                    $personId,
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

            if ($completedProfile && $parishId > 0 && $personId !== null) {
                (new PersonOnboardingService())->advance(
                    $personId,
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
                    (int) ($completedProfile['person_id'] ?? 0) ?: $personId
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

        $personId = null;
        if ($profile) {
            $personId = (new \App\Services\PersonProfileSyncService())->syncFromProfile((int) $profile['id'])
                ?? ((int) ($profile['person_id'] ?? 0) ?: null);

            if ($parishId > 0 && $personId !== null) {
                (new PersonOnboardingService())->start(
                    $personId,
                    $parishId,
                    (int) (auth()->id() ?? 0) ?: null
                );
            }
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

            if ($profile && $parishId > 0 && $personId !== null) {
                (new PersonOnboardingService())->advance(
                    $personId,
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
                    (int) ($profile['person_id'] ?? 0) ?: $personId
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
}
