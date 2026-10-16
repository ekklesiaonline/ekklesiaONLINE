<?php

namespace App\Controllers;

trait SystemAdminService
{
    /**
     * Shared guard: require the system.admin permission and systemadmin group
     * for any write operation.
     */
    private function guardSystemAdminWrite()
    {
        if ($redirect = $this->requirePermission('system.admin')) {
            return $redirect;
        }

        if (!$this->isSystemAdmin()) {
            return $this->forbiddenResponse(lang('App.errorForbidden'));
        }

        return null;
    }

    private const DELEGATION_PERMISSIONS = [
        'system.admin.create',
        'system.admin.update',
        'system.admin.delete',
        'system.admin.reset_password',
    ];

    /**
     * Shared guard for managing non-primary system admins.
     *
     * The primary system admin is always allowed. Otherwise the actor must have
     * the requested delegation permission. The primary account itself can never
     * be the target of these actions.
     */
    private function enforceSystemAdminAction(
        int $targetUserId,
        string $permission,
        string $notAllowedErrorKey = 'System.onlyPrimaryCanManageSystemAdmins',
        string $isPrimaryTargetErrorKey = 'System.cannotModifyPrimarySystemAdmin'
    ) {
        if (!in_array($permission, self::DELEGATION_PERMISSIONS, true)) {
            return redirect()->to(base_url('system/system-admins'))->with('error', lang('App.errorGeneric'));
        }

        if (!$this->isSystemAdminUser($targetUserId)) {
            return null;
        }

        $primarySystemAdminId = $this->getPrimarySystemAdminId();
        if ($primarySystemAdminId === null) {
            return redirect()->to(base_url('system/system-admins'))->with('error', lang('System.primarySystemAdminNotConfigured'));
        }

        if ($targetUserId === $primarySystemAdminId) {
            return redirect()->to(base_url('system/system-admins'))->with('error', lang($isPrimaryTargetErrorKey));
        }

        if ($this->isPrimarySystemAdmin() || (auth()->user()?->can($permission) ?? false)) {
            return null;
        }

        return redirect()->to(base_url('system/system-admins'))->with('error', lang($notAllowedErrorKey));
    }

    private function canCreateSystemAdminAccount(): bool
    {
        return $this->isPrimarySystemAdmin() || (auth()->user()?->can('system.admin.create') ?? false);
    }

    private function canManageAdminPermissions(): bool
    {
        return $this->isPrimarySystemAdmin();
    }

    private function getGrantedSystemAdminDelegationPermissions(int $userId): array
    {
        return array_keys(array_filter($this->getSystemAdminDelegationPermissions($userId)));
    }

    private function revokeSystemAdminDelegationPermissions($db, int $userId): void
    {
        $db->table('auth_permissions_users')
            ->where('user_id', $userId)
            ->whereIn('permission', self::DELEGATION_PERMISSIONS)
            ->delete();
    }

    private function grantSystemAdminDelegationPermissions($db, int $userId, string $now): void
    {
        foreach (self::DELEGATION_PERMISSIONS as $permission) {
            $exists = (int) $db->table('auth_permissions_users')
                ->where('user_id', $userId)
                ->where('permission', $permission)
                ->countAllResults() > 0;

            if (!$exists) {
                $db->table('auth_permissions_users')->insert([
                    'user_id' => $userId,
                    'permission' => $permission,
                    'created_at' => $now,
                ]);
            }
        }
    }

    /**
     * Load the four delegable permissions for a given user.
     *
     * @return array<string, bool>
     */
    private function getSystemAdminDelegationPermissions(int $userId): array
    {
        $db = db_connect();

        if (!$this->tableExistsSafe($db, 'auth_permissions_users')) {
            return array_fill_keys(self::DELEGATION_PERMISSIONS, false);
        }

        $rows = $db->table('auth_permissions_users')
            ->select('permission')
            ->where('user_id', $userId)
            ->whereIn('permission', self::DELEGATION_PERMISSIONS)
            ->get()
            ->getResultArray();

        $granted = array_fill_keys(self::DELEGATION_PERMISSIONS, false);
        foreach ($rows as $row) {
            $permission = (string) ($row['permission'] ?? '');
            if (in_array($permission, self::DELEGATION_PERMISSIONS, true)) {
                $granted[$permission] = true;
            }
        }

        return $granted;
    }

    private function getPrimarySystemAdminId(): ?int
    {
        $db = db_connect();

        if (!$this->tableExistsSafe($db, 'system_admin_primary')) {
            return null;
        }

        $row = $db->table('system_admin_primary')
            ->select('user_id')
            ->where('id', 1)
            ->get()
            ->getRowArray();

        $userId = (int) ($row['user_id'] ?? 0);
        return $userId > 0 ? $userId : null;
    }

    /**
     * Determine whether the currently authenticated user is the primary
     * system admin. The singleton system_admin_primary row is the source of
     * truth; the auth_permissions_users row is kept in sync automatically.
     */
    private function isPrimarySystemAdmin(): bool
    {
        if (!$this->isSystemAdmin()) {
            return false;
        }

        $primarySystemAdminId = $this->getPrimarySystemAdminId();
        if ($primarySystemAdminId === null) {
            return false;
        }

        $currentUserId = (int) (auth()->id() ?? 0);
        if ($currentUserId !== $primarySystemAdminId) {
            return false;
        }

        // Ensure the primary admin also holds the system.primary permission
        // so that Shield's can() checks stay consistent with the table.
        $this->ensurePrimarySystemAdminPermission($currentUserId);

        return true;
    }

    /**
     * Ensure the designated primary system admin has the system.primary
     * permission in auth_permissions_users. This repairs stale/missing rows
     * without requiring a logout/login cycle.
     */
    private function ensurePrimarySystemAdminPermission(int $userId): void
    {
        $db = db_connect();

        if (!$this->tableExistsSafe($db, 'auth_permissions_users')) {
            return;
        }

        $hasPermission = $db->table('auth_permissions_users')
            ->where('user_id', $userId)
            ->where('permission', 'system.primary')
            ->countAllResults() > 0;

        if ($hasPermission) {
            return;
        }

        $db->table('auth_permissions_users')
            ->where('permission', 'system.primary')
            ->delete();

        $db->table('auth_permissions_users')->insert([
            'user_id' => $userId,
            'permission' => 'system.primary',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Move the system.primary user permission to the new primary system admin.
     */
    private function syncPrimarySystemAdminPermission(int $newUserId, ?int $oldUserId = null): void
    {
        $db = db_connect();

        if (!$this->tableExistsSafe($db, 'auth_permissions_users')) {
            return;
        }

        $db->table('auth_permissions_users')
            ->where('permission', 'system.primary')
            ->delete();

        if ($oldUserId !== null && $oldUserId > 0) {
            $db->table('auth_permissions_users')
                ->where('user_id', $oldUserId)
                ->where('permission', 'system.primary')
                ->delete();
        }

        $db->table('auth_permissions_users')->insert([
            'user_id' => $newUserId,
            'permission' => 'system.primary',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function isSystemAdminUser(int $userId): bool
    {
        $db = db_connect();

        return $db->table('auth_groups_users')
            ->where('user_id', $userId)
            ->where('group', 'systemadmin')
            ->limit(1)
            ->get()
            ->getRow() !== null;
    }

    private function tableExistsSafe($db, string $table): bool
    {
        $prefix = (string) $db->getPrefix();
        $tableName = $prefix . $table;

        try {
            $db->query(sprintf('SELECT 1 FROM %s LIMIT 1', $tableName));
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function resolveExistingProfileTable($db): ?string
    {
        $prefix = (string) $db->getPrefix();
        $candidates = [];
        if ($prefix !== '') {
            $candidates[] = $prefix . 'profile';
            $candidates[] = $prefix . 'profiles';
        }
        $candidates[] = 'profile';
        $candidates[] = 'profiles';

        foreach ($candidates as $table) {
            try {
                $fields = $db->getFieldNames($table);
                if ($fields !== []) {
                    return $table;
                }
            } catch (\Throwable $e) {
                // Try next candidate.
            }
        }

        return null;
    }

    private function isPrimaryTransferOtpEnabled(): bool
    {
        return service('systemSettings')->isPrimaryTransferOtpRequired();
    }

    private function issuePrimaryTransferOtpChallenge(int $actorUserId, int $targetUserId, string $email): string
    {
        $code = (string) random_int(100000, 999999);
        $ttlSeconds = 600;

        session()->set([
            'primary_transfer_otp_required' => true,
            'primary_transfer_otp_hash' => hash('sha256', $code),
            'primary_transfer_otp_actor_user_id' => $actorUserId,
            'primary_transfer_otp_target_user_id' => $targetUserId,
            'primary_transfer_otp_expires_at' => time() + $ttlSeconds,
            'primary_transfer_otp_attempts' => 0,
            'primary_transfer_otp_email' => $email,
        ]);

        return $code;
    }

    private function verifyPrimaryTransferOtpChallenge(int $actorUserId, int $targetUserId, string $otpCode): bool
    {
        $required = (bool) session()->get('primary_transfer_otp_required');
        if (!$required) {
            return false;
        }

        $expiresAt = (int) (session()->get('primary_transfer_otp_expires_at') ?? 0);
        if ($expiresAt <= 0 || time() > $expiresAt) {
            $this->clearPrimaryTransferOtpChallenge();
            return false;
        }

        $challengeActor = (int) (session()->get('primary_transfer_otp_actor_user_id') ?? 0);
        $challengeTarget = (int) (session()->get('primary_transfer_otp_target_user_id') ?? 0);
        if ($challengeActor !== $actorUserId || $challengeTarget !== $targetUserId) {
            return false;
        }

        $attempts = (int) (session()->get('primary_transfer_otp_attempts') ?? 0);
        if ($attempts >= 5) {
            $this->clearPrimaryTransferOtpChallenge();
            return false;
        }

        session()->set('primary_transfer_otp_attempts', $attempts + 1);
        $expectedHash = (string) (session()->get('primary_transfer_otp_hash') ?? '');
        if ($expectedHash === '' || !hash_equals($expectedHash, hash('sha256', $otpCode))) {
            return false;
        }

        return true;
    }

    private function clearPrimaryTransferOtpChallenge(): void
    {
        session()->remove([
            'primary_transfer_otp_required',
            'primary_transfer_otp_hash',
            'primary_transfer_otp_actor_user_id',
            'primary_transfer_otp_target_user_id',
            'primary_transfer_otp_expires_at',
            'primary_transfer_otp_attempts',
            'primary_transfer_otp_email',
        ]);
    }

    private function buildPrimaryTransferOtpEmailBody(string $code, int $targetUserId): string
    {
        $targetName = $this->getSystemAdminDisplayName($targetUserId);
        $safeCode = esc($code);
        $safeTarget = esc($targetName);

        return <<<HTML
        <p>A primary system admin ownership transfer has been requested in <strong>ekklesiaONLINE</strong>.</p>
        <p>Enter this verification code to complete the transfer:</p>
        <p style="font-size:1.5rem;font-weight:700;letter-spacing:0.15em;">{$safeCode}</p>
        <p>Target system admin: <strong>{$safeTarget}</strong></p>
        <p>This code expires in 10 minutes and can be used only once.</p>
        HTML;
    }

    private function getSystemAdminDisplayName(int $userId): string
    {
        $db = db_connect();
        $profileTable = $this->resolveExistingProfileTable($db);
        if ($profileTable === null) {
            return 'User #' . $userId;
        }

        $row = $db->table($profileTable)
            ->select('first_name, last_name')
            ->where('user_id', $userId)
            ->get()
            ->getRowArray();

        $firstName = trim((string) ($row['first_name'] ?? ''));
        $lastName = trim((string) ($row['last_name'] ?? ''));
        $fullName = trim($firstName . ' ' . $lastName);

        return $fullName !== '' ? $fullName : 'User #' . $userId;
    }

    /**
     * Safely assign an admin group to a user.
     * Delegated to AdminCreationService so group assignment logic lives in one place.
     *
     * Returns the previous admin group (or an empty string if none), or null
     * when the supplied group is not supported.
     */
    private function assignAdminGroupSafely(int $userId, string $group): ?string
    {
        return (new \App\Services\AdminCreationService())->assignGroup($userId, $group);
    }

    /**
     * Fetch the authenticated actor's province/diocese scope from the profile table.
     */
    private function getActorScope($db): array
    {
        $profileTable = $this->resolveExistingProfileTable($db);
        if ($profileTable === null) {
            return ['province_id' => null, 'diocese_id' => null];
        }

        $row = $db->table($profileTable)
            ->select('province_id, diocese_id')
            ->where('user_id', (int) (auth()->id() ?? 0))
            ->get()
            ->getRowArray();

        return [
            'province_id' => isset($row['province_id']) ? (int) $row['province_id'] : null,
            'diocese_id' => isset($row['diocese_id']) ? (int) $row['diocese_id'] : null,
        ];
    }

    /**
     * Check rate limit for specific action.
     */
    private function checkRateLimit(int $userId, string $action, int $maxAttempts, int $timeWindow): bool
    {
        $cache = \Config\Services::cache();
        $cacheKey = "rate_limit_{$action}_{$userId}";

        $attempts = $cache->get($cacheKey) ?: 0;

        if ($attempts >= $maxAttempts) {
            return false;
        }

        $cache->save($cacheKey, $attempts + 1, $timeWindow);
        return true;
    }

    /**
     * Check if MFA is required for admin operations.
     */
    private function isMfaRequiredForAdminOperations(): bool
    {
        return service('systemSettings')->isMfaRequiredForAdminOps();
    }

    /**
     * Check if user has MFA enabled.
     */
    private function isUserMfaEnabled(int $userId): bool
    {
        $db = db_connect();

        $hasTwoFactor = $db->table('auth_identities')
            ->where('user_id', $userId)
            ->where('type', '2fa')
            ->countAllResults() > 0;

        return $hasTwoFactor;
    }

    /**
     * Check if user account is locked due to too many failed attempts.
     */
    private function isAccountLocked(int $userId): bool
    {
        $cache = \Config\Services::cache();
        $lockoutKey = "account_lockout_{$userId}";

        return $cache->get($lockoutKey) !== null;
    }

    /**
     * Track failed attempt for account lockout.
     */
    private function trackFailedAttempt(int $userId, string $action): void
    {
        $cache = \Config\Services::cache();
        $attemptKey = "failed_attempts_{$action}_{$userId}";

        $config = service('systemSettings')->getLockoutConfig();
        $maxAttempts = (int) ($config['max_attempts'] ?? 5);
        $windowSeconds = (int) ($config['window_seconds'] ?? 900);
        $durationSeconds = (int) ($config['duration_seconds'] ?? 1800);

        $attempts = $cache->get($attemptKey) ?: 0;
        $attempts++;

        if ($attempts >= $maxAttempts) {
            $cache->save("account_lockout_{$userId}", true, $durationSeconds);
            $cache->delete($attemptKey);
        } else {
            $cache->save($attemptKey, $attempts, $windowSeconds);
        }
    }

    /**
     * Clear failed attempts (call on successful operations).
     */
    private function clearFailedAttempts(int $userId, string $action): void
    {
        $cache = \Config\Services::cache();
        $attemptKey = "failed_attempts_{$action}_{$userId}";
        $cache->delete($attemptKey);
    }
}
