<?php

namespace App\Services;

use App\Models\MemberParishAffiliationModel;
use App\Models\ParishRoleAuditModel;
use App\Models\ProfileModel;
use CodeIgniter\Database\BaseConnection;

/**
 * ParishStaffService
 *
 * Domain logic for parish staff management: email identity lookup,
 * profile provisioning for staff, group membership operations,
 * username generation, and role audit logging. Authorization guards,
 * hierarchy checks, and HTTP concerns stay in the controller.
 */
class ParishStaffService
{
    private BaseConnection $db;
    private ProfileModel $profiles;
    private MemberParishAffiliationModel $affiliations;
    private ParishRoleAuditModel $roleAudit;

    public function __construct(
        ?BaseConnection $db = null,
        ?ProfileModel $profiles = null,
        ?MemberParishAffiliationModel $affiliations = null,
        ?ParishRoleAuditModel $roleAudit = null
    ) {
        $this->db = $db ?? db_connect();
        $this->profiles = $profiles ?? model(ProfileModel::class);
        $this->affiliations = $affiliations ?? model(MemberParishAffiliationModel::class);
        $this->roleAudit = $roleAudit ?? model(ParishRoleAuditModel::class);
    }

    /**
     * @return array<string, string>
     */
    public function staffValidationRules(bool $userExists): array
    {
        return [
            'first_name' => 'required|min_length[2]|max_length[100]',
            'last_name'  => 'required|min_length[2]|max_length[100]',
            'email'      => 'required|valid_email|max_length[255]',
            'password'   => $userExists ? 'permit_empty' : 'required|min_length[8]',
        ];
    }

    /**
     * The user_id owning an email_password identity, or null.
     */
    public function userIdForEmail(string $email): ?int
    {
        $row = $this->db->table('auth_identities')
            ->select('user_id')
            ->where('type', 'email_password')
            ->where('secret', $email)
            ->get()
            ->getRowArray();

        $userId = (int) ($row['user_id'] ?? 0);

        return $userId > 0 ? $userId : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function profileForUser(int $userId): ?array
    {
        $profile = $this->profiles->where('user_id', $userId)->first();

        return is_array($profile) ? $profile : null;
    }

    /**
     * Insert a staff profile when missing, or fill empty hierarchy/name
     * fields on an existing one. Never reassigns a non-empty parish_id.
     */
    public function ensureStaffProfile(
        ?array $profile,
        int $userId,
        int $parishId,
        array $parishScope,
        string $firstName,
        string $lastName
    ): void {
        if ($profile === null) {
            $this->profiles->insert([
                'user_id' => $userId,
                'parish_id' => $parishId,
                'province_id' => isset($parishScope['province_id']) ? (int) $parishScope['province_id'] : null,
                'diocese_id' => isset($parishScope['diocese_id']) ? (int) $parishScope['diocese_id'] : null,
                'first_name' => $firstName,
                'last_name' => $lastName,
            ]);

            return;
        }

        $update = [];
        if (empty($profile['parish_id'])) {
            $update['parish_id'] = $parishId;
        }
        if (empty($profile['province_id']) && isset($parishScope['province_id'])) {
            $update['province_id'] = (int) $parishScope['province_id'];
        }
        if (empty($profile['diocese_id']) && isset($parishScope['diocese_id'])) {
            $update['diocese_id'] = (int) $parishScope['diocese_id'];
        }
        if (empty($profile['first_name'])) {
            $update['first_name'] = $firstName;
        }
        if (empty($profile['last_name'])) {
            $update['last_name'] = $lastName;
        }

        if ($update !== []) {
            $this->profiles->update((int) $profile['id'], $update);
        }
    }

    /**
     * Full profile insert for a newly created staff account.
     */
    public function createStaffProfile(
        int $userId,
        int $parishId,
        array $parishScope,
        string $firstName,
        string $lastName,
        string $phone
    ): void {
        $this->profiles->insert([
            'user_id' => $userId,
            'parish_id' => $parishId,
            'province_id' => isset($parishScope['province_id']) ? (int) $parishScope['province_id'] : null,
            'diocese_id' => isset($parishScope['diocese_id']) ? (int) $parishScope['diocese_id'] : null,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $phone,
        ]);
    }

    public function attachToParish(int $userId, int $parishId): bool
    {
        return $this->affiliations->attachToParish($userId, $parishId, false, 'member');
    }

    public function setHomeParish(int $userId, int $parishId): bool
    {
        return $this->affiliations->setHomeParish($userId, $parishId);
    }

    public function isUserInParish(int $userId, int $parishId): bool
    {
        return $this->affiliations->isUserAffiliatedWithParish($userId, $parishId, true);
    }

    public function userHasGroup(int $userId, string $group): bool
    {
        return $this->db->table('auth_groups_users')
            ->where('user_id', $userId)
            ->where('group', $group)
            ->countAllResults() > 0;
    }

    public function userHasAnyGroup(int $userId): bool
    {
        return $this->db->table('auth_groups_users')
            ->where('user_id', $userId)
            ->countAllResults() > 0;
    }

    public function addGroupIfMissing(int $userId, string $group): void
    {
        if ($this->userHasGroup($userId, $group)) {
            return;
        }

        $this->db->table('auth_groups_users')->insert([
            'user_id' => $userId,
            'group' => $group,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function removeGroup(int $userId, string $group): void
    {
        $this->db->table('auth_groups_users')
            ->where('user_id', $userId)
            ->where('group', $group)
            ->delete();
    }

    public function makeUniqueUsername(string $email): string
    {
        $base = preg_replace('/[^a-z0-9_]/', '', strtolower((string) strstr($email, '@', true)));
        $base = $base ?: 'user';
        $candidate = $base;
        $counter = 1;

        while ($this->db->table('users')->where('username', $candidate)->countAllResults() > 0) {
            $candidate = $base . $counter;
            $counter++;
        }

        return $candidate;
    }

    /**
     * Record a staff role change in the parish role audit log.
     */
    public function logRoleAudit(
        int $parishId,
        int $actorUserId,
        int $targetUserId,
        string $action,
        ?string $role = null,
        ?string $details = null
    ): void {
        if ($actorUserId <= 0 || $targetUserId <= 0) {
            return;
        }

        $this->roleAudit->insert([
            'parish_id' => $parishId,
            'actor_user_id' => $actorUserId,
            'target_user_id' => $targetUserId,
            'action' => $action,
            'role' => $role,
            'details' => $details,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
