<?php

namespace App\Controllers;

use App\Libraries\DataEncryption;
use App\Services\SystemAdminUserService;

class SystemAdminUsers extends BaseController
{
    use SystemAdminService;

    private ?SystemAdminUserService $adminUserService = null;

    private function adminUserService(): SystemAdminUserService
    {
        return $this->adminUserService ??= new SystemAdminUserService();
    }

    public function storeSystemAdmin()
    {
        return $this->processStoreAdmin('systemadmin');
    }

    public function storeProvinceAdmin()
    {
        return $this->processStoreAdmin('provinceadmin');
    }

    public function storeDioceseAdmin()
    {
        return $this->processStoreAdmin('dioceseadmin');
    }

    /**
     * Shared creation logic for system/province/diocese admins.
     */
    private function processStoreAdmin(string $group)
    {
        if ($response = $this->guardSystemAdminWrite()) {
            return $response;
        }

        // Check if current user is locked out due to too many failed attempts
        if ($this->isAccountLocked(auth()->id())) {
            return redirect()->back()->with('error', 'Your account has been temporarily locked due to too many failed attempts. Please try again later.');
        }

        // MFA requirement: Check if user has 2FA enabled for sensitive operations
        if ($this->isMfaRequiredForAdminOperations() && !$this->isUserMfaEnabled(auth()->id())) {
            return redirect()->back()->with('error', 'Multi-factor authentication is required for admin operations. Please enable 2FA in your account settings.');
        }

        // Rate limiting: Allow max 5 admin creation attempts per hour per user
        if (!$this->checkRateLimit(auth()->id(), 'admin_creation', 5, 3600)) {
            return redirect()->back()->with('error', 'Too many admin creation attempts. Please try again later.');
        }

        $rules = [
            'first_name' => 'required|min_length[2]|max_length[100]',
            'last_name' => 'required|min_length[2]|max_length[100]',
            'email' => 'required|valid_email',
            'province_id' => 'permit_empty|integer',
            'diocese_id' => 'permit_empty|integer',
            'phone' => 'permit_empty|max_length[50]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $email = filter_var(trim((string) $this->request->getPost('email')), FILTER_SANITIZE_EMAIL);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->back()->withInput()->with('errors', ['email' => lang('System.invalidEmailAddress')]);
        }

        $provinceId = 0;
        $dioceseId = (int) ($this->request->getPost('diocese_id') ?? 0);

        $db = db_connect();

        if ($group === 'systemadmin') {
            $provinceId = 0;
            $dioceseId = 0;

            // Only the primary system admin or a delegated user may create system admin accounts.
            if (!$this->canCreateSystemAdminAccount()) {
                return redirect()->to(base_url('system/system-admins'))
                    ->with('error', lang('System.onlyPrimaryCanCreateSystemAdmin'));
            }
        }

        if ($group === 'provinceadmin') {
            $provinceId = (int) ($this->request->getPost('province_id') ?? 0);
            if ($provinceId <= 0 || !$this->adminUserService()->provinceExists($provinceId)) {
                return redirect()->back()->withInput()->with('error', lang('System.invalidProvinceForAdmin'));
            }

            // Restrict non-primary actors to their assigned province scope.
            if (!$this->isPrimarySystemAdmin()) {
                $actorScope = $this->getActorScope($db);
                if (($actorScope['province_id'] ?? null) !== $provinceId) {
                    return redirect()->back()->withInput()->with('error', lang('System.provinceOutsideActorScope'));
                }
            }

            $dioceseId = 0;
        }

        if ($group === 'dioceseadmin') {
            if ($dioceseId <= 0) {
                return redirect()->back()->withInput()->with('error', lang('System.invalidDioceseForAdmin'));
            }
            $diocese = $this->adminUserService()->dioceseRow($dioceseId);
            if (!$diocese) {
                return redirect()->back()->withInput()->with('error', lang('System.invalidDioceseForAdmin'));
            }

            // Restrict non-primary actors to their assigned diocese scope.
            if (!$this->isPrimarySystemAdmin()) {
                $actorScope = $this->getActorScope($db);
                if (($actorScope['diocese_id'] ?? null) !== $dioceseId) {
                    return redirect()->back()->withInput()->with('error', lang('System.dioceseOutsideActorScope'));
                }
            }

            $provinceId = (int) ($diocese['province_id'] ?? 0);
        }

        if ($this->adminUserService()->emailIdentityExists($email)) {
            // Generic error message to prevent email enumeration
            return redirect()->back()->withInput()->with('error', lang('System.adminUserCreateFailed'));
        }

        $db->transStart();

        try {
            $createdId = (new \App\Services\AdminCreationService())->createAdmin(
                $group,
                $email,
                trim((string) $this->request->getPost('first_name')),
                trim((string) $this->request->getPost('last_name')),
                preg_replace('/[^0-9+\s-]/', '', (string) ($this->request->getPost('phone') ?? '')),
                $provinceId > 0 ? $provinceId : null,
                $dioceseId > 0 ? $dioceseId : null
            );

            if ($group === 'provinceadmin') {
                if ($this->adminUserService()->scopePrimaryUserId('province', $provinceId) === null
                    && ! $this->adminUserService()->setScopePrimary('province', $provinceId, $createdId, (int) auth()->id())) {
                    throw new \RuntimeException('Failed to set province primary admin');
                }
            }

            if ($group === 'dioceseadmin') {
                $diocesePrimary = $this->adminUserService()->scopePrimaryUserId('diocese', $dioceseId);
                $makePrimary    = (int) ($this->request->getPost('make_primary') ?? 0) === 1;

                if (($diocesePrimary === null || $makePrimary) && ! $this->adminUserService()->setScopePrimary('diocese', $dioceseId, $createdId, (int) auth()->id())) {
                    throw new \RuntimeException('Failed to set diocese primary admin');
                }
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Transaction failed');
            }

            $this->audit()->log('system.admin_user.created', [
                'user_id' => (int) auth()->id(),
                'entity_type' => 'admin_user',
                'entity_id' => $createdId,
                'ip_address' => $this->request->getIPAddress(),
                'user_agent' => esc($this->request->getUserAgent()->getAgentString()),
                'timestamp' => date('Y-m-d H:i:s'),
                'new_values' => [
                    'email' => $email,
                    'role_group' => $group,
                    'province_id' => $provinceId > 0 ? $provinceId : null,
                    'diocese_id' => $dioceseId > 0 ? $dioceseId : null,
                    'first_name' => trim((string) $this->request->getPost('first_name')),
                    'last_name' => trim((string) $this->request->getPost('last_name')),
                    'permission_assigned' => $group === 'systemadmin' ? 'system.admin' : null,
                ],
            ]);

            // Clear failed attempts on successful operation
            $this->clearFailedAttempts(auth()->id(), 'admin_creation');

            $redirectUrl = match ($group) {
                'systemadmin' => 'system/system-admins',
                'provinceadmin' => 'system/province-admins',
                'dioceseadmin' => 'system/diocese-admins',
                default => 'system/dashboard',
            };
            return redirect()->to(base_url($redirectUrl))->with('success', lang('System.adminUserCreated'));
        } catch (\Throwable $e) {
            $db->transRollback();

            // Track failed attempt for account lockout
            $this->trackFailedAttempt(auth()->id(), 'admin_creation');

            return $this->safeRedirectError(lang('System.adminUserCreateFailed'), $e);
        }
    }

    public function grantDiocesePrimary(int $userId)
    {
        if ($response = $this->guardSystemAdminWrite()) {
            return $response;
        }

        if (! (auth()->user()?->can('system.diocese.create') ?? false)) {
            return redirect()->to(base_url('system/diocese-admins'))->with('error', lang('App.errorForbidden'));
        }

        $dioceseId = $this->adminUserService()->adminScopeForUser($userId, ['dioceseadmin'], 'diocese_id');

        if ($dioceseId === null) {
            return redirect()->to(base_url('system/diocese-admins'))->with('error', lang('System.adminUserNotFound'));
        }

        if ($this->adminUserService()->scopePrimaryUserId('diocese', $dioceseId) === $userId) {
            return redirect()->to(base_url('system/diocese-admins'))->with('error', lang('Diocesan.userAlreadyPrimary'));
        }

        if (! $this->adminUserService()->setScopePrimary('diocese', $dioceseId, $userId, (int) auth()->id())) {
            return redirect()->to(base_url('system/diocese-admins'))->with('error', lang('App.errorGeneric'));
        }

        $this->audit()->log('system.diocese_primary.granted', [
            'user_id'    => (int) auth()->id(),
            'entity_type' => 'admin_user',
            'entity_id'  => $userId,
            'new_values' => ['diocese_id' => $dioceseId],
        ]);

        return redirect()->to(base_url('system/diocese-admins'))->with('success', lang('Diocesan.primaryGranted'));
    }

    public function grantProvincePrimary(int $userId)
    {
        if ($response = $this->guardSystemAdminWrite()) {
            return $response;
        }

        if (! (auth()->user()?->can('system.province.create') ?? false)) {
            return redirect()->to(base_url('system/province-admins'))->with('error', lang('App.errorForbidden'));
        }

        $provinceId = $this->adminUserService()->adminScopeForUser($userId, ['provinceadmin'], 'province_id');

        if ($provinceId === null) {
            return redirect()->to(base_url('system/province-admins'))->with('error', lang('System.adminUserNotFound'));
        }

        if ($this->adminUserService()->scopePrimaryUserId('province', $provinceId) === $userId) {
            return redirect()->to(base_url('system/province-admins'))->with('error', lang('Diocesan.userAlreadyPrimary'));
        }

        if (! $this->adminUserService()->setScopePrimary('province', $provinceId, $userId, (int) auth()->id())) {
            return redirect()->to(base_url('system/province-admins'))->with('error', lang('App.errorGeneric'));
        }

        $this->audit()->log('system.province_primary.granted', [
            'user_id'     => (int) auth()->id(),
            'entity_type' => 'admin_user',
            'entity_id'   => $userId,
            'new_values'  => ['province_id' => $provinceId],
        ]);

        return redirect()->to(base_url('system/province-admins'))->with('success', lang('Diocesan.primaryGranted'));
    }

    public function grantParishPrimary(int $userId)
    {
        if ($response = $this->guardSystemAdminWrite()) {
            return $response;
        }

        $parishId = $this->adminUserService()->adminScopeForUser($userId, ['parishadmin', 'clergy'], 'parish_id');

        if ($parishId === null) {
            return redirect()->to(base_url('system/dashboard'))->with('error', lang('System.adminUserNotFound'));
        }

        if ($this->adminUserService()->scopePrimaryUserId('parish', $parishId) === $userId) {
            return redirect()->to(base_url('system/dashboard'))->with('error', lang('Diocesan.userAlreadyPrimary'));
        }

        if (! $this->adminUserService()->setScopePrimary('parish', $parishId, $userId, (int) auth()->id())) {
            return redirect()->to(base_url('system/dashboard'))->with('error', lang('App.errorGeneric'));
        }

        $this->audit()->log('system.parish_primary.granted', [
            'user_id'     => (int) auth()->id(),
            'entity_type' => 'admin_user',
            'entity_id'   => $userId,
            'new_values'  => ['parish_id' => $parishId],
        ]);

        return redirect()->to(base_url('system/dashboard'))->with('success', lang('Diocesan.primaryGranted'));
    }

    public function updateAdminUser(int $userId)
    {
        if ($response = $this->guardSystemAdminWrite()) {
            return $response;
        }

        if ($response = $this->enforceSystemAdminAction(
            $userId,
            'system.admin.update',
            'System.onlyPrimaryCanUpdateSystemAdmin'
        )) {
            return $response;
        }

        $rules = [
            'role_group' => 'required|in_list[systemadmin,provinceadmin,dioceseadmin]',
            'first_name' => 'required|min_length[2]|max_length[100]',
            'last_name' => 'required|min_length[2]|max_length[100]',
            'province_id' => 'permit_empty|integer',
            'diocese_id' => 'permit_empty|integer',
            'phone' => 'permit_empty|max_length[50]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $group = (string) $this->request->getPost('role_group');
        $provinceId = (int) ($this->request->getPost('province_id') ?? 0);
        $dioceseId = (int) ($this->request->getPost('diocese_id') ?? 0);

        $db = db_connect();
        $profileTable = $this->resolveExistingProfileTable($db);
        if ($profileTable === null) {
            return redirect()->back()->with('error', lang('App.errorGeneric'));
        }
        if (!$this->adminUserService()->userExists($userId)) {
            return redirect()->back()->with('error', lang('System.adminUserNotFound'));
        }

        $currentIsSystemAdmin = $this->isSystemAdminUser($userId);

        if ($group === 'systemadmin') {
            $provinceId = 0;
            $dioceseId = 0;

            if (!$currentIsSystemAdmin && !$this->isPrimarySystemAdmin()) {
                return redirect()->to(base_url('system/system-admins'))
                    ->with('error', lang('System.onlyPrimaryCanPromoteSystemAdmin'));
            }
        }

        if ($group === 'provinceadmin') {
            if ($provinceId <= 0 || !$this->adminUserService()->provinceExists($provinceId)) {
                return redirect()->back()->withInput()->with('error', lang('System.invalidProvinceForAdmin'));
            }

            // Restrict non-primary actors to their assigned province scope.
            if (!$this->isPrimarySystemAdmin()) {
                $actorScope = $this->getActorScope($db);
                if (($actorScope['province_id'] ?? null) !== $provinceId) {
                    return redirect()->back()->withInput()->with('error', lang('System.provinceOutsideActorScope'));
                }
            }

            $dioceseId = 0;
        }

        if ($group === 'dioceseadmin') {
            if ($dioceseId <= 0) {
                return redirect()->back()->withInput()->with('error', lang('System.invalidDioceseForAdmin'));
            }

            $diocese = $this->adminUserService()->dioceseRow($dioceseId);
            if (!$diocese) {
                return redirect()->back()->withInput()->with('error', lang('System.invalidDioceseForAdmin'));
            }

            $dioceseProvinceId = (int) ($diocese['province_id'] ?? 0);
            if ($provinceId <= 0) {
                $provinceId = $dioceseProvinceId;
            }

            if ($dioceseProvinceId !== $provinceId) {
                return redirect()->back()->withInput()->with('error', lang('System.invalidProvinceDiocesePair'));
            }

            // Restrict non-primary actors to their assigned diocese scope.
            if (!$this->isPrimarySystemAdmin()) {
                $actorScope = $this->getActorScope($db);
                if (($actorScope['diocese_id'] ?? null) !== $dioceseId) {
                    return redirect()->back()->withInput()->with('error', lang('System.dioceseOutsideActorScope'));
                }
            }
        }

        if ($currentIsSystemAdmin && $group !== 'systemadmin') {
            if ($this->adminUserService()->countGroupMembers('systemadmin') <= 1) {
                return redirect()->to(base_url('system/system-admins'))->with('error', lang('System.cannotRemoveLastSystemAdmin'));
            }
        }

        $revokedDelegationPermissions = $currentIsSystemAdmin && $group !== 'systemadmin'
            ? $this->getGrantedSystemAdminDelegationPermissions($userId)
            : [];

        $db->transStart();

        try {
            $oldGroup = $this->assignAdminGroupSafely($userId, $group);
            if ($revokedDelegationPermissions !== []) {
                $this->revokeSystemAdminDelegationPermissions($db, $userId);
            }
            if ($oldGroup === null) {
                throw new \RuntimeException('Unsupported admin group');
            }

            $this->adminUserService()->updateAdminProfile($profileTable, $userId, [
                'province_id' => $provinceId > 0 ? $provinceId : null,
                'diocese_id' => $dioceseId > 0 ? $dioceseId : null,
                'first_name' => trim((string) $this->request->getPost('first_name')),
                'last_name' => trim((string) $this->request->getPost('last_name')),
                'phone' => preg_replace('/[^0-9+\s-]/', '', (string) ($this->request->getPost('phone') ?? '')),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $db->transComplete();
            if ($db->transStatus() === false) {
                throw new \RuntimeException('Transaction failed');
            }

            $this->audit()->log('system.admin_user.updated', [
                'user_id' => (int) auth()->id(),
                'entity_type' => 'admin_user',
                'entity_id' => $userId,
                'ip_address' => $this->request->getIPAddress(),
                'user_agent' => $this->request->getUserAgent()->getAgentString(),
                'timestamp' => date('Y-m-d H:i:s'),
                'old_values' => [
                    'role_group' => $oldGroup !== '' ? $oldGroup : null,
                    'delegation_permissions' => $revokedDelegationPermissions,
                ],
                'new_values' => [
                    'role_group' => $group,
                    'province_id' => $provinceId > 0 ? $provinceId : null,
                    'diocese_id' => $dioceseId > 0 ? $dioceseId : null,
                    'first_name' => trim((string) $this->request->getPost('first_name')),
                    'last_name' => trim((string) $this->request->getPost('last_name')),
                    'permission_assigned' => $group === 'systemadmin' ? 'system.admin' : null,
                ],
            ]);

            $redirectUrl = match ($group) {
                'systemadmin' => 'system/system-admins',
                'provinceadmin' => 'system/province-admins',
                'dioceseadmin' => 'system/diocese-admins',
                default => 'system/dashboard',
            };
            return redirect()->to(base_url($redirectUrl))->with('success', lang('System.adminUserUpdated'));
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->safeRedirectError(lang('System.adminUserUpdateFailed'), $e);
        }
    }

    public function deleteAdminUser(int $userId)
    {
        if ($response = $this->guardSystemAdminWrite()) {
            return $response;
        }

        if ((int) (auth()->id() ?? 0) === $userId) {
            return redirect()->to(base_url('system/system-admins'))->with('error', lang('System.cannotDeleteSelf'));
        }

        $db = db_connect();
        $group = $this->adminUserService()->adminGroupOf($userId);

        if ($group === null) {
            return redirect()->to(base_url('system/dashboard'))->with('error', lang('System.adminUserNotFound'));
        }

        $targetIsSystemAdmin = ($group === 'systemadmin');

        if ($targetIsSystemAdmin) {
            if ($response = $this->enforceSystemAdminAction($userId, 'system.admin.delete')) {
                return $response;
            }

            if ($this->adminUserService()->countGroupMembers('systemadmin') <= 1) {
                return redirect()->to(base_url('system/system-admins'))->with('error', lang('System.cannotDeleteLastSystemAdmin'));
            }
        }

        if ($group === 'provinceadmin' && $this->adminUserService()->isScopePrimaryUser('province', $userId)) {
            return redirect()->to(base_url('system/province-admins'))->with('error', lang('System.cannotDeletePrimaryAdmin'));
        }

        if ($group === 'dioceseadmin' && $this->adminUserService()->isScopePrimaryUser('diocese', $userId)) {
            return redirect()->to(base_url('system/diocese-admins'))->with('error', lang('System.cannotDeletePrimaryAdmin'));
        }

        $profileTable = $this->resolveExistingProfileTable($db);

        if (!$this->adminUserService()->deleteUserCascade($profileTable, $userId)) {
            return $this->safeRedirectError(lang('System.adminUserDeleteFailed'), new \RuntimeException('Transaction failed'));
        }

        $this->audit()->log('system.admin_user.deleted', [
            'user_id' => (int) auth()->id(),
            'entity_type' => 'admin_user',
            'entity_id' => $userId,
            'ip_address' => $this->request->getIPAddress(),
            'user_agent' => esc($this->request->getUserAgent()->getAgentString()),
            'timestamp' => date('Y-m-d H:i:s'),
            'old_values' => [
                'role_group' => $group,
            ],
        ]);

        $redirectUrl = match ($group) {
            'systemadmin' => 'system/system-admins',
            'provinceadmin' => 'system/province-admins',
            'dioceseadmin' => 'system/diocese-admins',
            default => 'system/dashboard',
        };
        return redirect()->to(base_url($redirectUrl))->with('success', lang('System.adminUserDeleted'));
    }

    public function resetPassword(int $userId)
    {
        if ($response = $this->guardSystemAdminWrite()) {
            return $response;
        }

        if ((int) (auth()->id() ?? 0) === $userId) {
            return redirect()->to(base_url('system/system-admins'))->with('error', lang('System.cannotResetOwnPassword'));
        }

        if ($response = $this->enforceSystemAdminAction(
            $userId,
            'system.admin.reset_password',
            'System.onlyPrimaryCanResetPassword',
            'System.cannotModifyPrimarySystemAdmin'
        )) {
            return $response;
        }

        $minPasswordLength = service('systemSettings')->get('password_policy.min_length', 12);

        $rules = [
            'new_password' => [
                'required',
                'min_length[' . $minPasswordLength . ']',
            ],
            'new_password_confirm' => [
                'required',
                'matches[new_password]',
                'errors' => [
                    'matches' => lang('System.passwordsDoNotMatch'),
                ]
            ],
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $newPassword = (string) $this->request->getPost('new_password');
        $passwordValidation = $this->validateAdminPassword($newPassword);

        if (!$passwordValidation['ok']) {
            return redirect()->back()->withInput()->with('errors', ['new_password' => implode(' ', $passwordValidation['errors'])]);
        }

        $adminGroup = $this->adminUserService()->adminGroupOf($userId);

        if ($adminGroup === null) {
            return redirect()->back()->with('error', lang('System.adminUserNotFound'));
        }

        // Update password in auth_identities table
        $this->adminUserService()->updatePasswordIdentity($userId, service('passwords')->hash($newPassword));

        $targetIdentity = db_connect()->table('auth_identities')
            ->select('secret')
            ->where('user_id', $userId)
            ->where('type', 'email_password')
            ->get()
            ->getRowArray();
        if ($targetIdentity !== null) {
            (new \App\Services\SecurityNotificationService())
                ->sendPasswordChangedNotice($userId, (string) $targetIdentity['secret']);
        }

        $this->audit()->log('system.admin.password_reset', [
            'entity_type' => 'user',
            'entity_id' => $userId,
            'meta' => [
                'actor_user_id' => (int) (auth()->id() ?? 0),
            ],
        ]);

        $redirectUrl = match ($adminGroup) {
            'systemadmin' => 'system/system-admins',
            'provinceadmin' => 'system/province-admins',
            'dioceseadmin' => 'system/diocese-admins',
            default => 'system/dashboard',
        };

        return redirect()->to(base_url($redirectUrl))->with('success', lang('System.passwordResetSuccess'));
    }

    public function listSystemAdmins()
    {
        if ($redirect = $this->requirePermission('system.admin')) {
            return $redirect;
        }

        if (!$this->isSystemAdmin()) {
            return $this->forbiddenResponse(lang('App.errorForbidden'));
        }

        $db = db_connect();
        $profileTable = $this->resolveExistingProfileTable($db);

        // Get permissions hierarchy and system admin permissions
        $permissionsHierarchy = $this->getPermissionsHierarchy();
        $systemAdminPermissions = $this->getPermissionsForGroups(['systemadmin']);

        if ($profileTable === null) {
            return view('System/system_admins', array_merge(
                $this->getCommonViewData(lang('System.sectionSystemAdmins')),
                [
                    'admins' => [],
                    'systemAdminPermissions' => $systemAdminPermissions,
                    'permissionsHierarchy' => $permissionsHierarchy,
                    'primary_system_admin_id' => $this->getPrimarySystemAdminId(),
                    'current_user_id' => (int) (auth()->id() ?? 0),
                    'can_create_system_admin' => false,
                    'can_update_system_admin' => false,
                    'can_delete_system_admin' => false,
                    'can_reset_password' => false,
                    'can_manage_permissions' => false,
                ]
            ));
        }

        $search = trim((string) $this->request->getGet('q'));
        $admins = $this->adminUserService()->systemAdminRows(
            $profileTable,
            $search,
            $this->tableExistsSafe($db, 'system_admin_primary')
        );

        // Decrypt phone values and load delegable permissions per admin.
        foreach ($admins as $key => $admin) {
            if (!empty($admin['phone'])) {
                $admins[$key]['phone'] = DataEncryption::decrypt((string) $admin['phone']) ?? '';
            }
            $admins[$key]['delegation_permissions'] = $this->getSystemAdminDelegationPermissions((int) ($admin['user_id'] ?? 0));
            $admins[$key]['account_status'] = (int) ($admin['active'] ?? 0) !== 1
                ? 'disabled'
                : ((int) ($admin['force_reset'] ?? 0) === 1
                    ? (empty($admin['last_used_at']) ? 'invitation_pending' : 'password_setup_required')
                    : 'active');
        }

        $primarySystemAdminId = $this->getPrimarySystemAdminId();
        $currentUserId = (int) (auth()->id() ?? 0);
        $viewerIsPrimary = $this->isPrimarySystemAdmin();
        $viewerCanCreate = $this->canCreateSystemAdminAccount();
        $viewerCanUpdate = $viewerIsPrimary || (auth()->user()?->can('system.admin.update') ?? false);
        $viewerCanDelete = $viewerIsPrimary || (auth()->user()?->can('system.admin.delete') ?? false);
        $viewerCanResetPassword = $viewerIsPrimary || (auth()->user()?->can('system.admin.reset_password') ?? false);

        // Non-primary system admins must not see the primary admin row.
        if (!$viewerIsPrimary) {
            $admins = array_values(array_filter($admins, static function (array $row): bool {
                return (int) ($row['is_primary'] ?? 0) !== 1;
            }));
        }

        $perPage = 20;
        $page = max(1, (int) $this->request->getGet('page'));
        $totalAdmins = count($admins);
        $totalPages = max(1, (int) ceil($totalAdmins / $perPage));
        $page = min($page, $totalPages);
        $admins = array_slice($admins, ($page - 1) * $perPage, $perPage);

        return view('System/system_admins', array_merge(
            $this->getCommonViewData(lang('System.sectionSystemAdmins')),
            [
                'admins' => $admins,
                'systemAdminPermissions' => $systemAdminPermissions,
                'permissionsHierarchy' => $permissionsHierarchy,
                'primary_system_admin_id' => $primarySystemAdminId,
                'current_user_id' => $currentUserId,
                'can_create_system_admin' => $viewerCanCreate,
                'can_update_system_admin' => $viewerCanUpdate,
                'can_delete_system_admin' => $viewerCanDelete,
                'can_reset_password' => $viewerCanResetPassword,
                'can_manage_permissions' => $viewerIsPrimary,
                'search' => $search,
                'page' => $page,
                'per_page' => $perPage,
                'total_admins' => $totalAdmins,
                'total_pages' => $totalPages,
            ]
        ));
    }

    public function listProvinceAdmins()
    {
        if ($redirect = $this->requirePermission('system.admin')) {
            return $redirect;
        }

        if (!$this->isSystemAdmin()) {
            return $this->forbiddenResponse(lang('App.errorForbidden'));
        }

        $db = db_connect();
        $profileTable = $this->resolveExistingProfileTable($db);

        $provinces = $this->adminUserService()->provincesList();

        $admins = [];

        if ($profileTable !== null) {
            try {
                $admins = $this->adminUserService()->provinceAdminRows($profileTable);

                $admins = array_map(function (array $row): array {
                    if (!empty($row['phone'])) {
                        $row['phone'] = DataEncryption::decrypt((string) $row['phone']) ?? '';
                    }
                    return $row;
                }, $admins);
            } catch (\Throwable $e) {
                log_message('warning', 'SystemAdmin::listProvinceAdmins profile table query failed: {error}', ['error' => $e->getMessage()]);
            }
        }

        $primaryByProvince = $this->adminUserService()->primaryUserMap('province');

        return view('System/province_admins', array_merge(
            $this->getCommonViewData(lang('System.sectionProvinceAdmins')),
            [
                'admins' => $admins,
                'provinces' => $provinces,
                'primary_by_province' => $primaryByProvince,
            ]
        ));
    }

    public function listDioceseAdmins()
    {
        if ($redirect = $this->requirePermission('system.admin')) {
            return $redirect;
        }

        if (!$this->isSystemAdmin()) {
            return $this->forbiddenResponse(lang('App.errorForbidden'));
        }

        $db = db_connect();
        $profileTable = $this->resolveExistingProfileTable($db);

        $provinces = $this->adminUserService()->provincesList();

        $dioceses = $this->adminUserService()->diocesesList();

        $admins = [];

        if ($profileTable !== null) {
            try {
                $admins = $this->adminUserService()->dioceseAdminRows($profileTable);

                $admins = array_map(function (array $row): array {
                    if (!empty($row['phone'])) {
                        $row['phone'] = DataEncryption::decrypt((string) $row['phone']) ?? '';
                    }
                    return $row;
                }, $admins);
            } catch (\Throwable $e) {
                log_message('warning', 'SystemAdmin::listDioceseAdmins profile table query failed: {error}', ['error' => $e->getMessage()]);
            }
        }

        $primaryByDiocese = $this->adminUserService()->primaryUserMap('diocese');

        return view('System/diocese_admins', array_merge(
            $this->getCommonViewData(lang('System.sectionDioceseAdmins')),
            [
                'admins' => $admins,
                'provinces' => $provinces,
                'dioceses' => $dioceses,
                'primary_by_diocese' => $primaryByDiocese,
            ]
        ));
    }

    /**
     * Get permissions for specific groups
     */
    public function getPermissionsForGroups(array $groupNames): array
    {
        return $this->adminUserService()->permissionsForGroups($groupNames);
    }

    /**
     * Get all permissions organized hierarchically for display
     */
    public function getPermissionsHierarchy(): array
    {
        $authGroups = config('AuthGroups');
        $hierarchy = [];

        foreach ($authGroups->permissions as $key => $description) {
            $parts = explode('.', $key);
            $category = $parts[0] ?? 'general';
            $subcategory = $parts[1] ?? null;

            if (!isset($hierarchy[$category])) {
                $hierarchy[$category] = [
                    'name' => $category,
                    'permissions' => [],
                ];
            }

            $hierarchy[$category]['permissions'][] = [
                'key' => $key,
                'description' => $description,
                'subcategory' => $subcategory,
            ];
        }

        return $hierarchy;
    }

    public function resendAdminInvitation(int $userId)
    {
        if ($response = $this->guardSystemAdminWrite()) {
            return $response;
        }

        if (!$this->isPrimarySystemAdmin() || !$this->isSystemAdminUser($userId) || $userId === $this->getPrimarySystemAdminId()) {
            return redirect()->to(base_url('system/system-admins'))->with('error', lang('App.errorForbidden'));
        }

        $identity = $this->adminUserService()->identityForUser($userId, 'email_password');

        if (!$identity || (int) ($identity['force_reset'] ?? 0) !== 1) {
            return redirect()->to(base_url('system/system-admins'))->with('error', lang('System.adminInvitationNotPending'));
        }

        try {
            (new \App\Services\AdminInvitationService())->send((string) $identity['secret'], $userId);
            $this->audit()->log('system.admin_invitation.resent', [
                'entity_type' => 'admin_user',
                'entity_id' => $userId,
                'meta' => ['actor_user_id' => (int) (auth()->id() ?? 0)],
            ]);

            return redirect()->to(base_url('system/system-admins'))->with('success', lang('System.adminInvitationResent'));
        } catch (\Throwable $e) {
            return $this->safeRedirectError(lang('System.adminInvitationSendFailed'), $e);
        }
    }

    public function updateAdminPermissions(int $userId)
    {
        if ($response = $this->guardSystemAdminWrite()) {
            return $response;
        }

        if (!$this->canManageAdminPermissions()) {
            return redirect()->to(base_url('system/system-admins'))
                ->with('error', lang('System.onlyPrimaryCanManagePermissions'));
        }

        if ((int) (auth()->id() ?? 0) === $userId) {
            return redirect()->to(base_url('system/system-admins'))
                ->with('error', lang('System.cannotManageOwnPermissions'));
        }

        $primarySystemAdminId = $this->getPrimarySystemAdminId();
        if ($primarySystemAdminId !== null && $userId === $primarySystemAdminId) {
            return redirect()->to(base_url('system/system-admins'))
                ->with('error', lang('System.cannotModifyPrimarySystemAdmin'));
        }

        if (!$this->isSystemAdminUser($userId)) {
            return redirect()->to(base_url('system/system-admins'))
                ->with('error', lang('System.adminUserNotFound'));
        }

        $submitted = $this->request->getPost('permissions') ?? [];
        if (!is_array($submitted)) {
            $submitted = [];
        }

        $granted = [];
        foreach ($submitted as $permission) {
            $permission = (string) $permission;
            if (in_array($permission, self::DELEGATION_PERMISSIONS, true)) {
                $granted[$permission] = true;
            }
        }

        $now = date('Y-m-d H:i:s');
        $previousPermissions = $this->getGrantedSystemAdminDelegationPermissions($userId);

        try {
            if (!$this->adminUserService()->replaceUserPermissions($userId, self::DELEGATION_PERMISSIONS, array_keys($granted))) {
                throw new \RuntimeException('Transaction failed');
            }

            $this->audit()->log('system.admin_permissions.updated', [
                'user_id' => (int) (auth()->id() ?? 0),
                'entity_type' => 'admin_user',
                'entity_id' => $userId,
                'ip_address' => $this->request->getIPAddress(),
                'user_agent' => esc($this->request->getUserAgent()->getAgentString()),
                'timestamp' => $now,
                'old_values' => [
                    'permissions' => $previousPermissions,
                ],
                'new_values' => [
                    'permissions' => array_keys($granted),
                ],
            ]);

            return redirect()->to(base_url('system/system-admins'))
                ->with('success', lang('System.adminPermissionsUpdated'));
        } catch (\Throwable $e) {
            return $this->safeRedirectError(lang('System.adminPermissionsUpdateFailed'), $e);
        }
    }
}
