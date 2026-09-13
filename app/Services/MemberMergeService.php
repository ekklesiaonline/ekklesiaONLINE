<?php

namespace App\Services;

use App\Libraries\DataEncryption;
use CodeIgniter\Database\BaseConnection;

/**
 * MemberMergeService
 *
 * Handles three operations:
 *  1. findDuplicatesForParish()  — detect probable duplicate pairs within a parish
 *  2. getMergePreview()          — load both profile for side-by-side comparison
 *  3. merge()                    — atomically reassign all FK references from the
 *                                  duplicate to the canonical, then hard-delete the duplicate
 *  4. hardDelete()               — full hard-delete of a single member with complete
 *                                  FK cleanup (the same teardown used by merge's second phase)
 *
 * FK RESOLUTION STRATEGY (matches schema from April 2026 audit):
 *
 *  Reassign to canonical (merge) / NULL (hard-delete):
 *    attendance, annual_pledge, donations, event_registration,
 *    family_members, team_member, volunteer_schedule, leadership_position,
 *    parish_affiliation, member_discipline, member_standing_summary,
 *    call_tasks (via guest_id), parish_role_audit_logs (target_user_id),
 *    families.head_of_household_id, volunteer_teams.leader_id,
 *    expenses.approved_by, guests.converted_user_id / converted_by_user_id,
 *    parish_budget.created_by / updated_by, audit_logs.user_id,
 *    user_registration_request.reviewed_by,
 *    leadership_position.appointed_by_user_id,
 *    parish_role_audit_logs.actor_user_id
 *
 *  Hard-delete (own rows):
 *    auth_identities, auth_groups_users, auth_permissions_users,
 *    auth_remember_tokens, user_sessions, system_admin_primary,
 *    user_registration_request (where user_id = target),
 *    parish_people, profile, users
 *
 * NOTE: users.active is never set to 0 as part of portal revocation or deletion
 *       because syncParish() filters on u.active = 1.
 */
class MemberMergeService
{
    private BaseConnection $db;
    private ?string $profileScopeColumn = null;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    // -------------------------------------------------------------------------
    // 1. Duplicate detection
    // -------------------------------------------------------------------------

    /**
     * Find probable duplicate pairs within a parish.
     *
     * A pair qualifies when both records share the same parish_id AND either:
     *   - case-insensitive first_name + last_name match, OR
     *   - non-empty phone values match (encrypted phone is compared after decryption)
     *
     * @return array<int, array{
     *   profile_id_a: int, user_id_a: int, first_name_a: string, last_name_a: string,
     *   email_a: string, phone_a: string, birth_date_a: string,
     *   membership_status_a: string, created_at_a: string,
     *   profile_id_b: int, user_id_b: int, first_name_b: string, last_name_b: string,
     *   email_b: string, phone_b: string, birth_date_b: string,
     *   membership_status_b: string, created_at_b: string,
     *   match_reason: string
     * }>
     */
    public function findDuplicatesForParish(int $parishId): array
    {
        $scopeColumn = $this->getProfileScopeColumn();

        // Raw query for self-join with p1.id < p2.id to avoid symmetric pairs.
        $sql = "
            SELECT
                p1.id            AS profile_id_a,
                p1.user_id       AS user_id_a,
                p1.first_name    AS first_name_a,
                COALESCE(p1.nickname, p1.first_name) AS display_first_a,
                p1.last_name     AS last_name_a,
                p1.birth_date    AS birth_date_a,
                p1.membership_status AS membership_status_a,
                p1.phone         AS phone_a,
                p1.created_at    AS created_at_a,
                COALESCE(ai1.secret, '') AS email_a,

                p2.id            AS profile_id_b,
                p2.user_id       AS user_id_b,
                p2.first_name    AS first_name_b,
                COALESCE(p2.nickname, p2.first_name) AS display_first_b,
                p2.last_name     AS last_name_b,
                p2.birth_date    AS birth_date_b,
                p2.membership_status AS membership_status_b,
                p2.phone         AS phone_b,
                p2.created_at    AS created_at_b,
                COALESCE(ai2.secret, '') AS email_b,

                CASE
                    WHEN LOWER(TRIM(p1.first_name)) = LOWER(TRIM(p2.first_name))
                     AND LOWER(TRIM(p1.last_name))  = LOWER(TRIM(p2.last_name))
                    THEN 'name'
                    ELSE 'phone'
                END AS match_reason

            FROM profile p1
            INNER JOIN profile p2
                ON  p1.{$scopeColumn} = p2.{$scopeColumn}
                AND p1.id < p2.id
                AND (
                    (
                        LOWER(TRIM(p1.first_name)) = LOWER(TRIM(p2.first_name))
                        AND LOWER(TRIM(p1.last_name)) = LOWER(TRIM(p2.last_name))
                    )
                    OR
                    (
                        p1.phone IS NOT NULL AND p1.phone != ''
                        AND p1.phone = p2.phone
                    )
                )
            LEFT JOIN auth_identities ai1
                ON ai1.user_id = p1.user_id AND ai1.type = 'email_password'
            LEFT JOIN auth_identities ai2
                ON ai2.user_id = p2.user_id AND ai2.type = 'email_password'
            WHERE p1.{$scopeColumn} = ?
            ORDER BY p1.created_at ASC, p1.last_name, p1.first_name
        ";

        $rows = $this->db->query($sql, [$parishId])->getResultArray();

        // Decrypt phone fields and resolve display values.
        foreach ($rows as &$row) {
            $row['phone_a'] = DataEncryption::decrypt((string) ($row['phone_a'] ?? '')) ?? '';
            $row['phone_b'] = DataEncryption::decrypt((string) ($row['phone_b'] ?? '')) ?? '';
        }
        unset($row);

        return $rows;
    }

    // -------------------------------------------------------------------------
    // 2. Preview
    // -------------------------------------------------------------------------

    /**
     * Load both profile for the side-by-side merge UI.
     *
     * @return array{ok: bool, canonical: array<string,mixed>|null, duplicate: array<string,mixed>|null, message: string}
     */
    public function getMergePreview(int $canonicalProfileId, int $duplicateProfileId, int $parishId): array
    {
        $canonical  = $this->loadProfile($canonicalProfileId, $parishId);
        $duplicate  = $this->loadProfile($duplicateProfileId, $parishId);

        if ($canonical === null) {
            return ['ok' => false, 'canonical' => null, 'duplicate' => null, 'message' => 'Canonical profile not found in this parish.'];
        }
        if ($duplicate === null) {
            return ['ok' => false, 'canonical' => null, 'duplicate' => null, 'message' => 'Duplicate profile not found in this parish.'];
        }
        if ($canonicalProfileId === $duplicateProfileId) {
            return ['ok' => false, 'canonical' => null, 'duplicate' => null, 'message' => 'Canonical and duplicate must be different profile.'];
        }

        return ['ok' => true, 'canonical' => $canonical, 'duplicate' => $duplicate, 'message' => ''];
    }

    // -------------------------------------------------------------------------
    // 3. Merge
    // -------------------------------------------------------------------------

    /**
     * Merge duplicate into canonical, then hard-delete the duplicate.
     *
     * $fieldOverrides allows the caller to specify which profile field values to
     * carry forward onto the canonical (e.g. prefer the duplicate's phone or
     * birth_date). Keys are profile column names; values are the chosen value.
     *
     * @param array<string,mixed> $fieldOverrides
     * @return array{ok: bool, http_status: int, code: string, message: string}
     */
    public function merge(
        int $canonicalProfileId,
        int $duplicateProfileId,
        int $parishId,
        array $fieldOverrides = []
    ): array {
        if ($canonicalProfileId === $duplicateProfileId) {
            return $this->result(false, 400, 'SAME_PROFILE', 'Canonical and duplicate must be different profile.');
        }

        $canonical = $this->loadProfile($canonicalProfileId, $parishId);
        $duplicate = $this->loadProfile($duplicateProfileId, $parishId);

        if ($canonical === null) {
            return $this->result(false, 404, 'CANONICAL_NOT_FOUND', 'Canonical profile not found in this parish.');
        }
        if ($duplicate === null) {
            return $this->result(false, 404, 'DUPLICATE_NOT_FOUND', 'Duplicate profile not found in this parish.');
        }

        $canonicalUserId  = (int) $canonical['user_id'];
        $duplicateUserId  = (int) $duplicate['user_id'];

        $this->db->transBegin();

        try {
            // --- Phase 1: reassign all transactional FK references ---------------

            $this->reassignUserReferences($duplicateUserId, $canonicalUserId);

            // --- Phase 2: apply field overrides onto canonical profile -----------

            $allowedOverrides = [
                'first_name',
                'middle_name',
                'last_name',
                'nickname',
                'phone',
                'address',
                'city',
                'state',
                'zip',
                'country',
                'birth_date',
                'baptism_date',
                'spiritual_gifts',
                'social_media',
            ];
            $profileUpdate = [];
            foreach ($allowedOverrides as $field) {
                if (array_key_exists($field, $fieldOverrides)) {
                    $profileUpdate[$field] = $fieldOverrides[$field] !== '' ? $fieldOverrides[$field] : null;
                }
            }
            if ($profileUpdate !== []) {
                $profileUpdate['updated_at'] = date('Y-m-d H:i:s');
                $profileTable = $this->resolveExistingProfileTable();
                if ($profileTable !== null) {
                    $this->db->table($profileTable)
                        ->where('id', $canonicalProfileId)
                        ->update($profileUpdate);
                }
            }

            // --- Phase 3: hard-delete the duplicate ------------------------------

            $this->performHardDelete($duplicateUserId);

            if ($this->db->transStatus() === false) {
                throw new \RuntimeException('Transaction status false after merge.');
            }

            $this->db->transCommit();

            return $this->result(true, 200, 'MERGE_COMPLETE', 'Profiles merged and duplicate removed.', [
                'canonical_profile_id' => $canonicalProfileId,
                'canonical_user_id'    => $canonicalUserId,
                'duplicate_user_id'    => $duplicateUserId,
            ]);
        } catch (\Throwable $e) {
            $this->db->transRollback();

            return $this->result(false, 500, 'MERGE_FAILED', 'Merge failed: ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // 4. Hard delete
    // -------------------------------------------------------------------------

    /**
     * Fully remove a member and all their owned data from the system.
     *
     * Nullable FK references are set to NULL to preserve surrounding records
     * (e.g. donation history becomes anonymous). Non-nullable ownership rows
     * (attendance, auth data, etc.) are deleted outright.
     *
     * @return array{ok: bool, http_status: int, code: string, message: string}
     */
    public function hardDelete(int $profileId, int $parishId): array
    {
        $profile = $this->loadProfile($profileId, $parishId);

        if ($profile === null) {
            return $this->result(false, 404, 'MEMBER_NOT_FOUND', 'Member not found in this parish.');
        }

        $userId = (int) $profile['user_id'];

        $this->db->transBegin();

        try {
            $this->performHardDelete($userId);

            if ($this->db->transStatus() === false) {
                throw new \RuntimeException('Transaction status false after hard-delete.');
            }

            $this->db->transCommit();

            return $this->result(true, 200, 'HARD_DELETE_COMPLETE', 'Member permanently removed.', [
                'profile_id' => $profileId,
                'user_id'    => $userId,
            ]);
        } catch (\Throwable $e) {
            $this->db->transRollback();

            return $this->result(false, 500, 'HARD_DELETE_FAILED', 'Hard delete failed: ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Reassign all FK references from $fromUserId to $toUserId.
     * Called during merge (Phase 1) before the duplicate is deleted.
     */
    private function reassignUserReferences(int $fromUserId, int $toUserId): void
    {
        // Transactional ownership rows — simple UPDATE user_id
        $ownershipTables = [
            'attendance',
            'annual_pledge',
            'member_discipline',
        ];
        foreach ($ownershipTables as $table) {
            if ($this->tableExists($table) && $this->columnExists($table, 'user_id')) {
                $this->db->table($table)
                    ->where('user_id', $fromUserId)
                    ->update(['user_id' => $toUserId]);
            }
        }

        // member_standing_summary: unique (user_id, year). Resolve collisions first.
        if ($this->tableExists('member_standing_summary')
            && $this->columnExists('member_standing_summary', 'user_id')
            && $this->columnExists('member_standing_summary', 'year')
        ) {
            $fromYears = $this->db->table('member_standing_summary')
                ->select('year')
                ->where('user_id', $fromUserId)
                ->get()
                ->getResultArray();
            $toYears = $this->db->table('member_standing_summary')
                ->select('year')
                ->where('user_id', $toUserId)
                ->get()
                ->getResultArray();
            $toYearList    = array_column($toYears, 'year');
            $conflictYears = array_intersect(array_column($fromYears, 'year'), $toYearList);

            if ($conflictYears !== []) {
                $this->db->table('member_standing_summary')
                    ->where('user_id', $fromUserId)
                    ->whereIn('year', $conflictYears)
                    ->delete();
            }

            $this->db->table('member_standing_summary')
                ->where('user_id', $fromUserId)
                ->update(['user_id' => $toUserId]);
        }

        // donations: user_id nullable — reassign to canonical
        if ($this->tableExists('donation')) {
            $this->db->table('donation')
                ->where('user_id', $fromUserId)
                ->update(['user_id' => $toUserId]);
        }

        // family_members: deduplicate then reassign
        if ($this->tableExists('family_member')) {
            // Remove duplicate-user entries for families where canonical already exists
            $canonicalFamilies = $this->db->table('family_member')
                ->select('family_id')
                ->where('user_id', $toUserId)
                ->get()->getResultArray();
            $canonicalFamilyIds = array_column($canonicalFamilies, 'family_id');

            if ($canonicalFamilyIds !== []) {
                $this->db->table('family_member')
                    ->where('user_id', $fromUserId)
                    ->whereIn('family_id', $canonicalFamilyIds)
                    ->delete();
            }

            $this->db->table('family_member')
                ->where('user_id', $fromUserId)
                ->update(['user_id' => $toUserId]);
        }

        // families: head_of_household_id nullable
        if ($this->tableExists('family')) {
            $this->db->table('family')
                ->where('head_of_household_id', $fromUserId)
                ->update(['head_of_household_id' => $toUserId]);
        }

        // team_member: deduplicate then reassign
        if ($this->tableExists('team_member')) {
            $canonicalTeams = $this->db->table('team_member')
                ->select('team_id')
                ->where('user_id', $toUserId)
                ->get()->getResultArray();
            $canonicalTeamIds = array_column($canonicalTeams, 'team_id');

            if ($canonicalTeamIds !== []) {
                $this->db->table('team_member')
                    ->where('user_id', $fromUserId)
                    ->whereIn('team_id', $canonicalTeamIds)
                    ->delete();
            }

            $this->db->table('team_member')
                ->where('user_id', $fromUserId)
                ->update(['user_id' => $toUserId]);
        }

        // volunteer_teams.leader_id nullable
        if ($this->tableExists('team') && $this->columnExists('team', 'leader_id')) {
            $this->db->table('team')
                ->where('leader_id', $fromUserId)
                ->update(['leader_id' => $toUserId]);
        }

        // volunteer_schedule: reassign
        if ($this->tableExists('volunteer_schedule')) {
            $this->db->table('volunteer_schedule')
                ->where('user_id', $fromUserId)
                ->update(['user_id' => $toUserId]);
        }

        // leadership_position (user_id NOT NULL, appointed_by_user_id nullable)
        if ($this->tableExists('leadership_position')) {
            $this->db->table('leadership_position')
                ->where('user_id', $fromUserId)
                ->update(['user_id' => $toUserId]);
            $this->db->table('leadership_position')
                ->where('appointed_by_user_id', $fromUserId)
                ->update(['appointed_by_user_id' => $toUserId]);
        }

        // parish_affiliation: deduplicate then reassign
        if ($this->tableExists('parish_affiliation')) {
            $canonicalAffiliations = $this->db->table('parish_affiliation')
                ->select('parish_id')
                ->where('linked_user_id', $toUserId)
                ->get()->getResultArray();
            $canonicalParishIds = array_column($canonicalAffiliations, 'parish_id');

            if ($canonicalParishIds !== []) {
                $this->db->table('parish_affiliation')
                    ->where('linked_user_id', $fromUserId)
                    ->whereIn('parish_id', $canonicalParishIds)
                    ->delete();
            }

            $this->db->table('parish_affiliation')
                ->where('linked_user_id', $fromUserId)
                ->update(['linked_user_id' => $toUserId]);
        }

        // parish_role_audit_logs: both columns NOT NULL — reassign
        if ($this->tableExists('parish_role_audit_log')) {
            $this->db->table('parish_role_audit_log')
                ->where('target_user_id', $fromUserId)
                ->update(['target_user_id' => $toUserId]);
            $this->db->table('parish_role_audit_log')
                ->where('actor_user_id', $fromUserId)
                ->update(['actor_user_id' => $toUserId]);
        }

        // Nullable soft references — set to canonical.
        // Use table/column tuples (not associative array) to prevent duplicate-key overwrites.
        $nullableRefUpdates = [
            ['expense', 'approved_by'],
            ['guest', 'converted_user_id'],
            ['guest', 'converted_by_user_id'],
            ['parish_budget', 'created_by'],
            ['parish_budget', 'updated_by'],
            ['audit_log', 'user_id'],
            ['user_registration_requests', 'reviewed_by'],
        ];
        foreach ($nullableRefUpdates as [$table, $col]) {
            if ($this->tableExists($table) && $this->columnExists($table, $col)) {
                $this->db->table($table)
                    ->where($col, $fromUserId)
                    ->update([$col => $toUserId]);
            }
        }
    }

    /**
     * Hard-delete all data owned by $userId.
     * Must be called AFTER reassignUserReferences() during a merge,
     * OR standalone for a plain hard-delete.
     */
    private function performHardDelete(int $userId): void
    {
        // Nullable FK references → NULL (preserve surrounding records).
        // Use table/column tuples (not associative array) to prevent duplicate-key overwrites.
        $nullMappings = [
            ['donation', 'user_id'],
            ['family', 'head_of_household_id'],
            ['team', 'leader_id'],
            ['expense', 'approved_by'],
            ['event_registration', 'user_id'],
            ['guest', 'converted_user_id'],
            ['guest', 'converted_by_user_id'],
            ['parish_budget', 'created_by'],
            ['parish_budget', 'updated_by'],
            ['audit_log', 'user_id'],
            ['user_registration_requests', 'reviewed_by'],
            ['leadership_position', 'appointed_by_user_id'],
        ];
        foreach ($nullMappings as [$table, $col]) {
            if ($this->tableExists($table) && $this->columnExists($table, $col)) {
                $this->db->table($table)
                    ->where($col, $userId)
                    ->update([$col => null]);
            }
        }

        // NOT-NULL audit refs that cannot be NULLed — delete those rows
        // (audit rows where this member was the actor or the target)
        if ($this->tableExists('parish_role_audit_log')) {
            $this->db->table('parish_role_audit_log')
                ->groupStart()
                ->where('target_user_id', $userId)
                ->orWhere('actor_user_id', $userId)
                ->groupEnd()
                ->delete();
        }

        // Ownership rows that must be deleted outright
        $deleteTables = [
            'attendance',
            'annual_pledge',
            'family_member',
            'team_member',
            'volunteer_schedule',
            'leadership_position',
            'member_discipline',
            'member_standing_summary',
            'system_admin_primary',
            'user_registration_requests',
            'parish_people',
        ];
        foreach ($deleteTables as $table) {
            if ($this->tableExists($table) && $this->columnExists($table, 'user_id')) {
                $this->db->table($table)
                    ->where('user_id', $userId)
                    ->delete();
            }
        }

        // parish_affiliation uses linked_user_id — handle separately
        if ($this->tableExists('parish_affiliation')) {
            $this->db->table('parish_affiliation')
                ->where('linked_user_id', $userId)
                ->delete();
        }

        // Auth / session rows
        $authTables = [
            'auth_identities',
            'auth_groups_users',
            'auth_permissions_users',
            'auth_remember_tokens',
            'user_session',
        ];
        foreach ($authTables as $table) {
            if ($this->tableExists($table) && $this->columnExists($table, 'user_id')) {
                $this->db->table($table)
                    ->where('user_id', $userId)
                    ->delete();
            }
        }

        // Profile then user (order matters for FK constraints)
        $profileTable = $this->resolveExistingProfileTable();
        if ($profileTable !== null && $this->tableExists($profileTable)) {
            $this->db->table($profileTable)->where('user_id', $userId)->delete();
        }
        if ($this->tableExists('users')) {
            $this->db->table('users')->where('id', $userId)->delete();
        }

        // Clean up user_person links and remove the canonical person row only
        // when it no longer has any references.
        (new PersonCleanupService($this->db))->cleanupForUser($userId);
    }

    /**
     * Load a profile with its email (via LEFT JOIN auth_identities) and
     * decrypted phone, scoped to parishId.
     *
     * @return array<string,mixed>|null
     */
    private function loadProfile(int $profileId, int $parishId): ?array
    {
        $scopeColumn = $this->getProfileScopeColumn();

        $row = $this->db->table('profile p')
            ->select('p.*, COALESCE(p.nickname, p.first_name) AS display_first, COALESCE(ai.secret, \'\') AS email')
            ->join('auth_identities ai', 'ai.user_id = p.user_id AND ai.type = \'email_password\'', 'left')
            ->where('p.id', $profileId)
            ->where('p.' . $scopeColumn, $parishId)
            ->get()
            ->getRowArray();

        if ($row === null) {
            return null;
        }

        $row['phone_display'] = DataEncryption::decrypt((string) ($row['phone'] ?? '')) ?? '';

        return $row;
    }

    private function getProfileScopeColumn(): string
    {
        if ($this->profileScopeColumn !== null) {
            return $this->profileScopeColumn;
        }

        if ($this->db->fieldExists('parish_id', 'profile')) {
            $this->profileScopeColumn = 'parish_id';
            return $this->profileScopeColumn;
        }

        $this->profileScopeColumn = 'parish_id';

        return $this->profileScopeColumn;
    }

    private function tableExists(string $table): bool
    {
        try {
            return $this->db->tableExists($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            return $this->db->fieldExists($column, $table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function resolveExistingProfileTable(): ?string
    {
        $candidates = [
            $this->db->getPrefix() . 'profile',
            'profile',
        ];
        foreach (array_unique($candidates) as $table) {
            try {
                $columns = $this->db->getFieldNames($table);
                if ($columns !== []) {
                    return $table;
                }
            } catch (\Throwable $e) {
                // Table doesn't exist.
            }
        }
        return null;
    }

    /**
     * @param array<string,mixed>|null $data
     * @return array{ok: bool, http_status: int, code: string, message: string, data?: array<string,mixed>}
     */
    private function result(bool $ok, int $status, string $code, string $message, ?array $data = null): array
    {
        $out = ['ok' => $ok, 'http_status' => $status, 'code' => $code, 'message' => $message];
        if ($data !== null) {
            $out['data'] = $data;
        }

        return $out;
    }
}
