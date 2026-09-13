<?php

namespace App\Services;

class AdminCreationService
{
    private const ADMIN_GROUPS = ['systemadmin', 'provinceadmin', 'dioceseadmin'];

    /**
     * Create a new system/province/diocese admin account.
     *
     * @return int The created user's ID.
     * @throws \RuntimeException on any creation failure.
     */
    public function createAdmin(
        string $group,
        string $email,
        string $firstName,
        string $lastName,
        ?string $phone,
        ?int $provinceId,
        ?int $dioceseId
    ): int {
        if (!$this->isSupportedGroup($group)) {
            throw new \RuntimeException('Unsupported admin group');
        }

        $db = db_connect();

        $existing = $db->table('auth_identities')
            ->where('type', 'email_password')
            ->where('secret', $email)
            ->countAllResults();
        if ($existing > 0) {
            throw new \RuntimeException('ADMIN_EMAIL_ALREADY_EXISTS');
        }

        $users = auth()->getProvider();
        $user = new \CodeIgniter\Shield\Entities\User([
            'username' => $email,
            'email'    => $email,
            'password' => bin2hex(random_bytes(32)),
        ]);

        $users->save($user);
        $created = $users->findById($users->getInsertID());
        if (!$created) {
            throw new \RuntimeException('User creation failed');
        }
        $created->activate();

        $this->assignGroup((int) $created->id, $group);

        $db->table('auth_identities')
            ->where('user_id', (int) $created->id)
            ->where('type', 'email_password')
            ->update([
                'force_reset' => 1,
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);

        // A diocese belongs to exactly one province; derive it when the caller
        // only supplied a diocese (e.g. a diocese admin creating a co-admin)
        // so both the profile and the registration request carry a consistent scope.
        $resolvedProvinceId = $provinceId > 0 ? $provinceId : null;
        if ($resolvedProvinceId === null && $dioceseId > 0) {
            $diocese = $db->table('diocese')->select('province_id')->where('id', $dioceseId)->get()->getRowArray();
            $resolvedProvinceId = (int) ($diocese['province_id'] ?? 0) ?: null;
        }

        model('ProfileModel')->insert([
            'user_id'         => (int) $created->id,
            'parish_id'       => null,
            'province_id'     => $resolvedProvinceId,
            'diocese_id'      => $dioceseId > 0 ? $dioceseId : null,
            'first_name'      => $firstName,
            'last_name'       => $lastName,
            'phone'           => preg_replace('/[^0-9+\s-]/', '', (string) ($phone ?? '')),
            // profile.membership_status is an ENUM of visitor/regular_attender/
            // member/non_active/inactive/transferred/deceased — 'active' is not
            // a valid value and MySQL silently stores '' for it. 'member' is the
            // value MembershipStatusService normalizes/displays as "Active".
            'membership_status' => 'member',
        ]);

        // user_registration_requests.province_id is NOT NULL, so we can only
        // record an approved request when a province can be resolved. A
        // system admin has no province/diocese scope; those accounts keep
        // relying on "no registration_request row" being treated as approved
        // everywhere else in the app (RegistrationGateFilter, Profile state).
        if ($resolvedProvinceId !== null) {
            $this->recordApprovedRegistrationRequest(
                (int) $created->id,
                $group,
                $resolvedProvinceId,
                $dioceseId > 0 ? $dioceseId : null
            );
        }

        try {
            (new AdminInvitationService())->send($email, (int) $created->id);
        } catch (\Throwable $e) {
            log_message('error', 'Admin invitation email failed for user {user_id}: {message}', [
                'user_id' => (int) $created->id,
                'message' => $e->getMessage(),
            ]);
        }

        return (int) $created->id;
    }

    /**
     * Safely assign an admin group to a user.
     *
     * Returns the previous admin group (or an empty string if none), or null
     * when the supplied group is not supported.
     */
    public function assignGroup(int $userId, string $group): ?string
    {
        if (!$this->isSupportedGroup($group)) {
            return null;
        }

        $db = db_connect();

        $oldGroupRow = $db->table('auth_groups_users')
            ->select('group')
            ->where('user_id', $userId)
            ->whereIn('group', self::ADMIN_GROUPS)
            ->get()
            ->getRowArray();
        $oldGroup = $oldGroupRow['group'] ?? '';

        $db->table('auth_groups_users')
            ->where('user_id', $userId)
            ->whereIn('group', self::ADMIN_GROUPS)
            ->delete();

        $db->table('auth_groups_users')->insert([
            'user_id'    => $userId,
            'group'      => $group,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $oldGroup;
    }

    public function isSupportedGroup(string $group): bool
    {
        return in_array($group, self::ADMIN_GROUPS, true);
    }

    /**
     * Insert an already-approved user_registration_request row for an
     * admin-created account, so status lookups elsewhere in the app see a
     * coherent "approved" state instead of "no record".
     */
    private function recordApprovedRegistrationRequest(int $userId, string $group, int $provinceId, ?int $dioceseId): void
    {
        $db = db_connect();
        $table = $this->resolveRegistrationRequestTable($db);
        if ($table === null) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $payload = [
            'user_id'              => $userId,
            'province_id'          => $provinceId,
            'diocese_id'           => $dioceseId,
            'status'               => 'approved',
            'requested_role'       => $group,
            'profile_completed_at' => $now,
            'reviewed_at'          => $now,
        ];

        $columns = array_map('strtolower', $db->getFieldNames($table));
        $payload = array_intersect_key($payload, array_flip($columns));

        $db->table($table)->insert($payload);
    }

    /**
     * @return string|null Resolved table name, or null if it does not exist.
     */
    private function resolveRegistrationRequestTable($db): ?string
    {
        $candidates = array_values(array_unique([
            $db->getPrefix() . 'user_registration_request',
            'user_registration_request',
            $db->getPrefix() . 'user_registration_requests',
            'user_registration_requests',
        ]));

        foreach ($candidates as $candidate) {
            try {
                if ($db->getFieldNames($candidate) !== []) {
                    return $candidate;
                }
            } catch (\Throwable $e) {
                // Try next table-name variant.
            }
        }

        return null;
    }
}
