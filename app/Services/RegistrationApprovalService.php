<?php

namespace App\Services;

use App\Controllers\AdminHierarchyGuard;
use CodeIgniter\Database\BaseConnection;
use Config\AuthGroups;

/**
 * Centralised, hierarchy-aware registration approval/dismiss/ban/delete actions.
 *
 * This service is used both by the diocesan/parish moderation UI and by the
 * member-portal onboarding flow. It enforces that a lower-tier admin cannot
 * approve, dismiss, ban, or delete a request belonging to a higher-tier admin,
 * and that existing higher-tier Shield groups are never stripped when a lower
 * role is assigned.
 */
class RegistrationApprovalService
{
    use AdminHierarchyGuard;

    private const TOP_TIER_GROUPS = [
        AuthGroups::SYSTEM_ADMIN,
        AuthGroups::PROVINCE_ADMIN,
        AuthGroups::DIOCESE_ADMIN,
    ];

    private const PARISH_SCOPED_ROLES = [
        'member',
        'clergy',
        'parishadmin',
        'finance',
        'vestry_secretary',
        'volunteer_coordinator',
        'followup_coordinator',
        'worship_coordinator',
        'worship_volunteer',
    ];

    private const ALLOWED_ROLES = [
        'member',
        'clergy',
        'parishadmin',
        'finance',
        'vestry_secretary',
        'volunteer_coordinator',
        'followup_coordinator',
        'worship_coordinator',
        'worship_volunteer',
    ];

    private BaseConnection $db;

    public function __construct()
    {
        $this->db = db_connect();
    }

    /**
     * Approve a single registration request.
     *
     * @return string|null Error string, or null on success.
     */
    public function approve(int $actorUserId, int $requestId, ?string $role = null, ?array $actorScope = null): ?string
    {
        $request = $this->getRequest($requestId);
        if ($request === null || $request['status'] !== 'pending_review') {
            return lang('Diocesan.registrationRequestMissingOrReviewed');
        }

        if ($actorScope !== null && !$this->requestInParishScope($request, $actorScope)) {
            return lang('Diocesan.requestOutsideDioceseScope');
        }

        $scope = $actorScope ?? $this->resolveParishScope($request);
        if ($scope === null) {
            return lang('Diocesan.requestOutsideDioceseScope');
        }

        if ($code = $this->guardTargetUserMutation((int) $request['user_id'], false, $actorUserId)) {
            return $this->hierarchyGuardMessage($code);
        }

        return $this->performApprove($actorUserId, $request, $role, $scope);
    }

    /**
     * Auto-approve a request for a top-tier admin that is approving their own parish join.
     *
     * @return string|null Error string, or null on success.
     */
    public function autoApproveForElevatedAdmin(int $userId, int $requestId, ?string $role = null): ?string
    {
        if (!$this->isTopTierAdmin($userId)) {
            return lang('Diocesan.accessDeniedDiocesanAdmin');
        }

        return $this->approve($userId, $requestId, $role, null);
    }

    /**
     * Dismiss a registration request.
     *
     * @return string|null Error string, or null on success.
     */
    public function dismiss(int $actorUserId, int $requestId, ?array $actorScope = null): ?string
    {
        return $this->markReviewed($actorUserId, $requestId, 'dismissed', false, $actorScope);
    }

    /**
     * Ban a registration request.
     *
     * @return string|null Error string, or null on success.
     */
    public function ban(int $actorUserId, int $requestId, ?array $actorScope = null): ?string
    {
        return $this->markReviewed($actorUserId, $requestId, 'banned', true, $actorScope);
    }

    /**
     * Delete a registration request and the underlying user account.
     *
     * @return string|null Error string, or null on success.
     */
    public function delete(int $actorUserId, int $requestId, ?array $actorScope = null): ?string
    {
        $request = $this->getRequest($requestId);
        if ($request === null) {
            return lang('Diocesan.registrationRequestNotFound');
        }

        if ($actorScope !== null && !$this->requestInParishScope($request, $actorScope)) {
            return lang('Diocesan.requestOutsideDioceseScope');
        }

        $userId = (int) $request['user_id'];

        if ($code = $this->guardTargetUserMutation($userId, true, $actorUserId)) {
            return $this->hierarchyGuardMessage($code);
        }

        $parishId = $this->resolveAuditParishId($userId, (int) ($request['requested_parish_id'] ?? 0));

        $this->db->transStart();

        $this->logModerationAudit(
            $parishId,
            $actorUserId,
            $userId,
            'registration_deleted',
            $request['requested_role'] ?? null,
            'Deleted registration request and user account during parish moderation.'
        );

        model('ProfileModel')->where('user_id', $userId)->delete();
        model('UserRegistrationRequestModel')->delete($requestId);
        $this->db->table('users')->where('id', $userId)->delete();

        // Remove user_person links and clean up any orphaned canonical persons.
        (new \App\Services\PersonCleanupService($this->db))->cleanupForUser($userId);

        $this->db->transComplete();

        return $this->db->transStatus() ? null : lang('Diocesan.registrationDeleteFailed');
    }

    /**
     * Determine whether a user belongs to a top-tier admin group.
     */
    public function isTopTierAdmin(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        return $this->db->table('auth_groups_users')
            ->where('user_id', $userId)
            ->whereIn('group', self::TOP_TIER_GROUPS)
            ->countAllResults() > 0;
    }

    private function performApprove(int $actorUserId, array $request, ?string $role, array $scope): ?string
    {
        $users = auth()->getProvider();

        $allowedRoles = self::ALLOWED_ROLES;
        if ($role === null || $role === '') {
            $role = (string) ($request['requested_role'] ?? 'member');
        }
        if (!in_array($role, $allowedRoles, true)) {
            return lang('Diocesan.invalidRoleSelected');
        }

        $parishId = (int) (($request['requested_parish_id'] ?? 0) ?: $scope['parish_id']);

        if (!in_array($role, self::PARISH_SCOPED_ROLES, true)) {
            $parishId = 0;
        }

        $user = $users->findById((int) $request['user_id']);
        if (!$user) {
            return lang('Diocesan.associatedUserNotFound');
        }

        $profileModel = model('ProfileModel');
        $profile = $profileModel->where('user_id', $user->id)->first();
        if (!$profile) {
            return lang('Diocesan.userProfileNotFound');
        }

        $this->db->transStart();

        $this->setUserGroups($user->id, [$role]);

        // Approval affiliates the person but does not confer canonical
        // membership: the parish promotes visitor -> member once
        // sacramental/standing requirements are verified. The affiliation
        // row (created below) owns membership_status.
        $profileModel->update($profile['id'], [
            'province_id' => (int) $scope['province_id'],
            'diocese_id' => (int) $scope['diocese_id'],
            'parish_id' => $parishId > 0 ? $parishId : null,
        ]);

        // Sync the canonical person record with the approved profile.
        $personId = (new \App\Services\PersonProfileSyncService())->syncFromProfile((int) $profile['id'])
            ?? ((int) ($profile['person_id'] ?? 0) ?: null);

        // The people directory is affiliation-driven: approval must create
        // the person's canonical parish_affiliation row or they remain
        // invisible to parish people lists. visitor keeps them out
        // of member counts until a parish admin promotes them.
        if ($parishId > 0) {
            (new MemberAffiliationService($this->db))->ensurePrimaryAffiliationForUser(
                (int) $user->id,
                $parishId,
                $personId
            );

            $affiliationCreated = $this->db->table('parish_affiliation')
                ->where('linked_user_id', (int) $user->id)
                ->where('parish_id', $parishId)
                ->where('deleted_at', null)
                ->countAllResults() > 0;

            if (! $affiliationCreated) {
                $this->db->transRollback();
                return lang('Diocesan.registrationApproveFailed');
            }
        }

        model('UserRegistrationRequestModel')->update((int) $request['id'], [
            'status' => 'approved',
            'requested_role' => $role,
            'reviewed_by' => $actorUserId,
            'reviewed_at' => date('Y-m-d H:i:s'),
        ]);

        $this->logModerationAudit(
            $parishId > 0 ? $parishId : null,
            $actorUserId,
            (int) $request['user_id'],
            'registration_approved',
            $role,
            'Approved registration request for parish onboarding.'
        );

        $this->db->transComplete();

        return $this->db->transStatus() ? null : lang('Diocesan.registrationApproveFailed');
    }

    private function markReviewed(int $actorUserId, int $requestId, string $status, bool $deactivateUser, ?array $actorScope): ?string
    {
        $request = $this->getRequest($requestId);
        if ($request === null || !in_array($request['status'], ['pending_profile', 'pending_review'], true)) {
            return lang('Diocesan.registrationRequestMissingOrReviewed');
        }

        if ($actorScope !== null && !$this->requestInParishScope($request, $actorScope)) {
            return lang('Diocesan.requestOutsideDioceseScope');
        }

        if ($code = $this->guardTargetUserMutation((int) $request['user_id'], $deactivateUser, $actorUserId)) {
            return $this->hierarchyGuardMessage($code);
        }

        $parishId = $this->resolveAuditParishId((int) $request['user_id'], (int) ($request['requested_parish_id'] ?? 0));

        $this->db->transStart();

        $this->logModerationAudit(
            $parishId,
            $actorUserId,
            (int) $request['user_id'],
            $status === 'banned' ? 'registration_banned' : 'registration_dismissed',
            $request['requested_role'] ?? null,
            $status === 'banned'
                ? 'Banned registration request during parish moderation.'
                : 'Dismissed registration request during parish moderation.'
        );

        model('UserRegistrationRequestModel')->update((int) $request['id'], [
            'status' => $status,
            'reviewed_by' => $actorUserId,
            'reviewed_at' => date('Y-m-d H:i:s'),
        ]);

        if ($deactivateUser) {
            $this->db->table('users')
                ->where('id', (int) $request['user_id'])
                ->update(['active' => 0]);

            // Security: banned/dismissed accounts lose all trusted devices
            // and tracked sessions immediately. Best-effort: never let a
            // missing table break moderation actions.
            try {
                (new \App\Services\TrustedDeviceService())->revokeAll((int) $request['user_id'], null, $actorUserId);
                model('UserSessionModel')->removeAllForUser((int) $request['user_id']);
            } catch (\Throwable $e) {
                log_message('warning', 'Post-ban session/trust cleanup failed: {error}', ['error' => $e->getMessage()]);
            }
        }

        $this->db->transComplete();

        return $this->db->transStatus() ? null : lang('Diocesan.registrationUpdateFailed');
    }

    private function getRequest(int $requestId): ?array
    {
        $requestModel = model('UserRegistrationRequestModel');

        return $requestModel->find($requestId) ?: null;
    }

    private function resolveParishScope(array $request): ?array
    {
        $parishId = (int) ($request['requested_parish_id'] ?? $request['parish_id'] ?? 0);

        return $this->resolveParishScopeById($parishId);
    }

    private function resolveParishScopeById(int $parishId): ?array
    {
        if ($parishId <= 0) {
            return null;
        }

        $parish = model('ParishModel')->findWithHierarchy($parishId);
        if (!$parish || empty($parish['diocese_id']) || empty($parish['province_id'])) {
            return null;
        }

        return [
            'parish_id' => $parishId,
            'diocese_id' => (int) $parish['diocese_id'],
            'province_id' => (int) $parish['province_id'],
        ];
    }

    private function resolveAuditParishId(int $userId, int $requestedParishId = 0): ?int
    {
        if ($requestedParishId > 0) {
            return $requestedParishId;
        }

        $profile = model('ProfileModel')->where('user_id', $userId)->first();
        if (!empty($profile['parish_id'])) {
            return (int) $profile['parish_id'];
        }

        return null;
    }

    private function requestInParishScope(array $request, array $scope): bool
    {
        $requestParishId = (int) ($request['requested_parish_id'] ?? 0);
        if ($requestParishId > 0) {
            return $requestParishId === (int) $scope['parish_id'];
        }

        $requestDioceseId = (int) ($request['diocese_id'] ?? 0);
        if ($requestDioceseId <= 0) {
            return false;
        }

        return $requestDioceseId === (int) $scope['diocese_id'];
    }

    private function setUserGroups(int $userId, array $newGroups): void
    {
        $existing = $this->db->table('auth_groups_users')
            ->where('user_id', $userId)
            ->get()
            ->getResultArray();

        $groups = [];
        foreach ($existing as $row) {
            $groups[] = (string) $row['group'];
        }

        foreach ($newGroups as $group) {
            $groups[] = (string) $group;
        }

        $groups = array_values(array_unique($groups));

        $this->db->table('auth_groups_users')->where('user_id', $userId)->delete();

        foreach ($groups as $group) {
            $this->db->table('auth_groups_users')->insert([
                'user_id' => $userId,
                'group' => $group,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Escalate a request back to a diocesan/province-level review queue.
     *
     * @return string|null Error string, or null on success.
     */
    public function escalate(int $actorUserId, int $requestId, ?array $actorScope = null, ?string $notes = null): ?string
    {
        $request = $this->getRequest($requestId);
        if ($request === null || $request['status'] !== 'pending_review') {
            return lang('Diocesan.registrationRequestMissingOrReviewed');
        }

        if ($actorScope !== null && !$this->requestInParishScope($request, $actorScope)) {
            return lang('Diocesan.requestOutsideDioceseScope');
        }

        // A requester who already holds a top-tier admin role (e.g. a diocese
        // admin who also submitted a personal parish-join request) must never
        // be left stuck in "pending_review" limbo by an escalation — auto-approve
        // instead, the same way completeSubmit()/joinParishSubmit() do.
        if ($this->isTopTierAdmin((int) $request['user_id'])) {
            $scope = $actorScope ?? $this->resolveParishScope($request);
            if ($scope !== null) {
                return $this->performApprove($actorUserId, $request, null, $scope);
            }
        }

        $parishId = $this->resolveAuditParishId((int) $request['user_id'], (int) ($request['requested_parish_id'] ?? 0));

        $this->db->transStart();

        $this->logModerationAudit(
            $parishId,
            $actorUserId,
            (int) $request['user_id'],
            'registration_escalated',
            $request['requested_role'] ?? null,
            $notes ?? 'Escalated to a higher-level admin for review.'
        );

        model('UserRegistrationRequestModel')->update((int) $request['id'], [
            'status' => 'pending_review',
            'reviewed_by' => $actorUserId,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'review_notes' => $notes ?? lang('Diocesan.escalatedToHigherAdmin'),
        ]);

        $this->db->transComplete();

        return $this->db->transStatus() ? null : lang('Diocesan.registrationUpdateFailed');
    }

    private function logModerationAudit(?int $parishId, int $actorUserId, int $targetUserId, string $action, ?string $role, string $details): void
    {
        if ($actorUserId <= 0 || $targetUserId <= 0) {
            return;
        }

        try {
            model('ParishRoleAuditModel')->insert([
                'parish_id' => $parishId,
                'actor_user_id' => $actorUserId,
                'target_user_id' => $targetUserId,
                'action' => $action,
                'role' => $role,
                'details' => $details,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('warning', 'Failed to write moderation audit log: {message}', ['message' => $e->getMessage()]);
        }
    }
}
