<?php

namespace App\Models;

use App\Libraries\DataEncryption;
use App\Services\MembershipStatusService;
use App\Services\PersonStatusHistoryService;
use Config\AuthGroups;

class MemberParishAffiliationModel extends BaseTenantModel
{
    private ?bool $hasIsMemberColumn = null;
    private PersonStatusHistoryService $history;

    protected $table            = 'parish_affiliation';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'linked_user_id',
        'parish_id',
        'person_id',
        'affiliation_type',
        'membership_status',
        'canonical_status',
        'pathway_stage',
        'is_member',
        'is_good_standing',
        'source',
        'start_date',
        'end_date',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function __construct()
    {
        parent::__construct();
        $this->history = new PersonStatusHistoryService();
    }

    protected $validationRules = [
        'linked_user_id'    => 'permit_empty|is_not_unique[users.id]',
        'parish_id'         => 'required|is_not_unique[parish.id]',
        'membership_status' => 'required|in_list[visitor,regular_attender,member,inactive,transferred,deceased]',
        'is_member'         => 'permit_empty|in_list[0,1]',
    ];

    public function isUserAffiliatedWithParish(int $userId, int $parishId, bool $activeOnly = true): bool
    {
        $builder = $this->where('linked_user_id', $userId)
            ->where('parish_id', $parishId);

        if ($activeOnly) {
            $builder->where('is_member', 1);
        }

        return $builder->first() !== null;
    }

    public function getParishMembers(int $parishId, ?string $status = null, bool $membersOnly = true, ?int $actorParishId = null): array
    {
        if ($actorParishId !== null && $actorParishId !== $parishId) {
            throw new \RuntimeException('Access denied: parish scope mismatch');
        }

        $isMemberSelect = 'mca.is_member';

        $builder = $this->db->table('parish_affiliation mca')
            ->select('p.*, u.username, u.active as user_active, ai.secret as email, ai.force_reset, mca.membership_status as affiliation_status, ' . $isMemberSelect, false)
            ->join('profile p', 'p.user_id = mca.linked_user_id')
            ->where('p.deleted_at', null)
            ->join('users u', 'u.id = mca.linked_user_id')
            ->join('auth_identities ai', 'ai.user_id = u.id AND ai.type = "email_password"', 'left')
            ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("' . AuthGroups::PROVINCE_ADMIN . '","' . AuthGroups::DIOCESE_ADMIN . '")', 'left')
            ->where('mca.parish_id', $parishId)
            ->where('mca.deleted_at', null)
            ->where('mca.is_member', 1)
            ->where('agu.id IS NULL');

        if ($membersOnly) {
            $builder->where('mca.is_member', 1);
        }

        if ($status !== null && $status !== '') {
            $builder->where('mca.membership_status', $status);
        }

        $rows = $builder
            ->orderBy('p.last_name', 'ASC')
            ->orderBy('p.first_name', 'ASC')
            ->get()
            ->getResultArray();

        return array_map(fn(array $row): array => $this->decryptProfileFields($row), $rows);
    }

    public function getParishMembersAvailableForFamily(int $parishId, int $familyId, string $keyword = '', int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));
        $preferredNameExpr = $this->getPreferredProfileNameExpr('p');

        $builder = $this->db->table('parish_affiliation mca')
            ->select('p.user_id, ' . $preferredNameExpr . ' AS first_name, p.last_name, ai.secret as email', false)
            ->join('profile p', 'p.user_id = mca.linked_user_id')
            ->where('p.deleted_at', null)
            ->join('users u', 'u.id = mca.linked_user_id')
            ->join('auth_identities ai', 'ai.user_id = u.id AND ai.type = "email_password"', 'left')
            ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("' . AuthGroups::PROVINCE_ADMIN . '","' . AuthGroups::DIOCESE_ADMIN . '")', 'left')
            ->join('family_member fm', 'fm.user_id = mca.linked_user_id AND fm.family_id = ' . (int) $familyId, 'left')
            ->where('mca.parish_id', $parishId)
            ->where('mca.deleted_at', null)
            ->where('mca.is_member', 1)
            ->where('agu.id IS NULL')
            ->where('fm.id IS NULL');

        $keyword = trim($keyword);
        if ($keyword !== '') {
            $builder->groupStart()
                ->like('p.first_name', $keyword)
                ->orLike('p.last_name', $keyword)
                ->orLike('ai.secret', $keyword)
                ->groupEnd();
        }

        return $builder
            ->orderBy('p.last_name', 'ASC')
            ->orderBy('p.first_name', 'ASC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    /**
     * Paginated parish members list for large datasets.
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function getParishMembersPaginated(int $parishId, ?string $status = null, int $perPage = 25, int $page = 1, bool $membersOnly = true): array
    {
        $standingYear = (int) date('Y');

        $hasIsMember = false;
        $hasMembershipStatus = false;
        try {
            $hasIsMember = $this->db->fieldExists('is_member', 'parish_affiliation');
            $hasMembershipStatus = $this->db->fieldExists('membership_status', 'parish_affiliation');
        } catch (\Throwable $e) {
            // Use defaults below.
        }

        $hasStandingSummary = false;
        try {
            $hasStandingSummary = $this->db->tableExists('member_standing_summary');
        } catch (\Throwable $e) {
            // Leave false.
        }

        $isMemberSelect = $hasIsMember ? 'mca.is_member' : '1 as is_member';
        $affiliationStatusSelect = $hasMembershipStatus ? 'mca.membership_status as affiliation_status' : 'NULL as affiliation_status';
        $standingSelect = $hasStandingSummary ? 'COALESCE(mss.is_good_standing, 0) as is_good_standing' : '0 as is_good_standing';

        $builder = $this->db->table('parish_affiliation mca')
            ->select('p.*, u.username, u.active as user_active, ai.secret as email, ai.force_reset, ' . $affiliationStatusSelect . ', ' . $isMemberSelect . ', ' . $standingSelect, false)
            ->join('profile p', 'p.user_id = mca.linked_user_id')
            ->where('p.deleted_at', null)
            ->join('users u', 'u.id = mca.linked_user_id')
            ->join('auth_identities ai', 'ai.user_id = u.id AND ai.type = "email_password"', 'left')
            ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("' . AuthGroups::PROVINCE_ADMIN . '","' . AuthGroups::DIOCESE_ADMIN . '")', 'left')
            ->where('mca.parish_id', $parishId)
            ->where('mca.deleted_at', null)
            ->where('agu.id IS NULL');

        if ($hasStandingSummary) {
            $builder->join('member_standing_summary mss', 'mss.user_id = mca.linked_user_id AND mss.year = ' . $standingYear, 'left');
        }

        if ($hasIsMember) {
            $builder->where('mca.is_member', 1);
        }

        if ($membersOnly && $hasIsMember) {
            $builder->where('mca.is_member', 1);
        }

        if ($status !== null && $status !== '' && $hasMembershipStatus) {
            $builder->where('mca.membership_status', $status);
        }

        $countBuilder = clone $builder;
        $total = (int) $countBuilder->countAllResults();

        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        $rows = $builder
            ->orderBy('p.last_name', 'ASC')
            ->orderBy('p.first_name', 'ASC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        $rows = array_map(fn(array $row): array => $this->decryptProfileFields($row), $rows);

        return [
            'rows' => $rows,
            'total' => $total,
        ];
    }

    public function getParishStatusCounts(int $parishId): array
    {
        $hasIsMember = false;
        $hasMembershipStatus = false;
        try {
            $hasIsMember = $this->db->fieldExists('is_member', 'parish_affiliation');
            $hasMembershipStatus = $this->db->fieldExists('membership_status', 'parish_affiliation');
        } catch (\Throwable $e) {
            return [];
        }

        if (! $hasMembershipStatus) {
            return [];
        }

        $builder = $this->db->table('parish_affiliation mca')
            ->select('mca.membership_status, COUNT(*) as count')
            ->join('users u', 'u.id = mca.linked_user_id')
            ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("' . AuthGroups::PROVINCE_ADMIN . '","' . AuthGroups::DIOCESE_ADMIN . '")', 'left')
            ->where('mca.parish_id', $parishId)
            ->where('mca.deleted_at', null)
            ->where('agu.id IS NULL')
            ->groupBy('mca.membership_status');

        if ($hasIsMember) {
            $builder->where('mca.is_member', 1);
        }

        return $this->normalizeStatusCountRows($builder->get()->getResultArray());
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizeStatusCountRows(array $rows): array
    {
        $bucket = [];
        $validStatuses = MembershipStatusService::affiliationStatuses();

        foreach ($rows as $row) {
            $status = MembershipStatusService::normalizeAffiliationStatus((string) ($row['membership_status'] ?? ''));
            if (!in_array($status, $validStatuses, true)) {
                $status = MembershipStatusService::profileToAffiliation($status);
            }
            if (!in_array($status, $validStatuses, true)) {
                $status = 'visitor';
            }

            $bucket[$status] = (int) ($bucket[$status] ?? 0) + (int) ($row['count'] ?? 0);
        }

        $normalized = [];
        foreach ($bucket as $status => $count) {
            $normalized[] = [
                'membership_status' => $status,
                'count' => $count,
            ];
        }

        return $normalized;
    }

    public function searchParishMembers(int $parishId, string $keyword, bool $membersOnly = true): array
    {
        $builder = $this->db->table('parish_affiliation mca')
            ->select('p.*, ai.secret as email, ai.force_reset')
            ->join('profile p', 'p.user_id = mca.linked_user_id')
            ->where('p.deleted_at', null)
            ->join('users u', 'u.id = mca.linked_user_id')
            ->join('auth_identities ai', 'ai.user_id = u.id AND ai.type = "email_password"', 'left')
            ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("' . AuthGroups::PROVINCE_ADMIN . '","' . AuthGroups::DIOCESE_ADMIN . '")', 'left')
            ->where('mca.parish_id', $parishId)
            ->where('mca.deleted_at', null)
            ->where('mca.is_member', 1)
            ->where('agu.id IS NULL')
            ->limit(25);

        if ($membersOnly) {
            $builder->where('mca.is_member', 1);
        }

        $phoneHash = DataEncryption::phoneIndex($keyword);
        $builder->groupStart()
            ->like('p.first_name', $keyword)
            ->orLike('p.last_name', $keyword)
            ->orLike('ai.secret', $keyword);

        if ($this->hasProfileNicknameColumn()) {
            $builder->orLike('p.nickname', $keyword);
        }

        if ($phoneHash !== null) {
            $builder->orWhere('p.phone_hash', $phoneHash);
        }

        $builder->groupEnd();

        $rows = $builder
            ->get()
            ->getResultArray();

        $rows = array_map(fn(array $row): array => $this->decryptProfileFields($row), $rows);

        return array_slice($rows, 0, 10);
    }

    private function getPreferredProfileNameExpr(string $alias): string
    {
        if ($this->hasProfileNicknameColumn()) {
            return 'COALESCE(' . $alias . '.nickname, ' . $alias . '.first_name)';
        }

        return $alias . '.first_name';
    }

    private function hasProfileNicknameColumn(): bool
    {
        $tables = [
            $this->db->getPrefix() . 'profile',
            'profile',
        ];

        foreach (array_values(array_unique($tables)) as $table) {
            try {
                $columns = array_map('strtolower', $this->db->getFieldNames($table));
                if ($columns !== []) {
                    return in_array('nickname', $columns, true);
                }
            } catch (\Throwable $e) {
                // Try next table-name variant.
            }
        }

        return false;
    }

    public function getUserAffiliations(int $userId): array
    {
        return $this->db->table('parish_affiliation mca')
            ->select('mca.*, c.name as parish_name, d.name as diocese_name')
            ->join('parish c', 'c.id = mca.parish_id')
            ->join('diocese d', 'd.id = c.diocese_id', 'left')
            ->where('mca.linked_user_id', $userId)
            ->where('mca.is_member', 1)
            ->where('mca.deleted_at', null)
            ->where('c.deleted_at', null)
            ->orderBy('c.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function getActiveAffiliationForParish(int $userId, int $parishId): ?array
    {
        $row = $this->where('linked_user_id', $userId)
            ->where('parish_id', $parishId)
            ->where('is_member', 1)
            ->first();

        return $row ?: null;
    }

    public function getAudienceAffiliationForParish(int $userId, int $parishId): ?array
    {
        $today = date('Y-m-d');

        $row = $this->where('linked_user_id', $userId)
            ->where('parish_id', $parishId)
            ->groupStart()
            ->where('end_date IS NULL', null, false)
            ->orWhere('end_date >=', $today)
            ->groupEnd()
            ->orderBy('id', 'DESC')
            ->first();

        return $row ?: null;
    }

    public function countDistinctPeople(int $parishId): int
    {
        $row = $this->db->table('parish_affiliation mca')
            ->select('COUNT(DISTINCT mca.person_id) AS count', false)
            ->join('person p', 'p.id = mca.person_id', 'inner')
            ->where('mca.parish_id', $parishId)
            ->where('mca.deleted_at', null)
            ->where('p.deleted_at', null)
            ->where('mca.person_id IS NOT NULL', null, false)
            ->get()
            ->getRowArray();

        return (int) ($row['count'] ?? 0);
    }

    public function countDistinctAffiliatedPeople(int $parishId): int
    {
        $row = $this->db->table('parish_affiliation mca')
            ->select('COUNT(DISTINCT mca.linked_user_id) AS count', false)
            ->join('users u', 'u.id = mca.linked_user_id')
            ->join('profile p', 'p.user_id = mca.linked_user_id')
            ->where('p.deleted_at', null)
            ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("' . AuthGroups::PROVINCE_ADMIN . '","' . AuthGroups::DIOCESE_ADMIN . '")', 'left')
            ->where('mca.parish_id', $parishId)
            ->where('mca.deleted_at', null)
            ->where('agu.id IS NULL')
            ->get()
            ->getRowArray();

        return (int) ($row['count'] ?? 0);
    }

    public function countDistinctMembers(int $parishId, ?array $statuses = null): int
    {
        $builder = $this->db->table('parish_affiliation mca')
            ->select('COUNT(DISTINCT mca.person_id) AS count', false)
            ->join('person p', 'p.id = mca.person_id', 'inner')
            ->where('mca.parish_id', $parishId)
            ->where('mca.deleted_at', null)
            ->where('p.deleted_at', null)
            ->where('mca.person_id IS NOT NULL', null, false)
            ->where('mca.is_member', 1);

        if ($statuses === null) {
            $builder->whereIn('mca.membership_status', ['member', 'inactive']);
        } elseif ($statuses !== []) {
            $builder->whereIn('mca.membership_status', $statuses);
        }

        $row = $builder->get()->getRowArray();

        return (int) ($row['count'] ?? 0);
    }

    public function attachToParish(int $userId, int $parishId, bool $isHome = false, string $status = 'member', bool $isMember = true, ?int $personId = null): bool
    {
        $resolvedPersonId = $personId ?? $this->getOrCreatePersonIdForUser($userId) ?? 0;
        $status           = MembershipStatusService::normalizeAffiliationStatus($status);
        $affiliationType  = MembershipStatusService::affiliationTypeForStatus($status);

        $existing = $this->db->table($this->table)
            ->where('linked_user_id', $userId)
            ->where('parish_id', $parishId)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        $fromStatus       = $existing['membership_status'] ?? null;
        $existingPersonId = (int) ($existing['person_id'] ?? 0);
        $targetPersonId   = $resolvedPersonId > 0
            ? $resolvedPersonId
            : $existingPersonId;

        $updateData = [
            'is_member'         => $isMember ? 1 : 0,
            'end_date'          => null,
            'membership_status' => $status,
            'affiliation_type'  => $affiliationType,
            'person_id'         => $targetPersonId > 0 ? $targetPersonId : ($resolvedPersonId > 0 ? $resolvedPersonId : 0),
        ];

        $result = false;

        if ($existing) {
            $result = $this->update((int) $existing['id'], $updateData) !== false;
        } else {
            $insertData = array_merge($updateData, [
                'linked_user_id' => $userId,
                'parish_id'      => $parishId,
                'start_date'     => date('Y-m-d'),
            ]);

            // Use the resolved person_id for the insert; 0 is a fallback when
            // the person table is not yet present.
            $insertData['person_id'] = $resolvedPersonId > 0 ? $resolvedPersonId : 0;

            try {
                $result = $this->insert($insertData) !== false;
            } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
                // The unique constraint on (linked_user_id, parish_id) or
                // (person_id, parish_id) means another concurrent request created
                // the row first. Fall back to updating it.
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $existing = $this->db->table($this->table)
                        ->where('linked_user_id', $userId)
                        ->where('parish_id', $parishId)
                        ->where('deleted_at', null)
                        ->get()
                        ->getRowArray();

                    if (! $existing && $resolvedPersonId > 0) {
                        $existing = $this->db->table($this->table)
                            ->where('person_id', $resolvedPersonId)
                            ->where('parish_id', $parishId)
                            ->where('deleted_at', null)
                            ->get()
                            ->getRowArray();
                    }

                    if (! $existing) {
                        throw $e;
                    }

                    $fromStatus       = $existing['membership_status'] ?? null;
                    $existingPersonId = (int) ($existing['person_id'] ?? 0);
                    $targetPersonId   = $resolvedPersonId > 0
                        ? $resolvedPersonId
                        : $existingPersonId;

                    $updateData['person_id'] = $targetPersonId > 0 ? $targetPersonId : ($existingPersonId > 0 ? $existingPersonId : 0);

                    $result = $this->update((int) $existing['id'], $updateData) !== false;
                } else {
                    throw $e;
                }
            }
        }

        if ($result && $targetPersonId > 0 && $fromStatus !== $status) {
            $this->history->record(
                $targetPersonId,
                'affiliation',
                $fromStatus,
                $status,
                $parishId,
                auth()->loggedIn() ? (int) auth()->id() : null,
                'Affiliation status changed'
            );
        }

        return $result;
    }

    public function setMemberTag(int $userId, int $parishId, bool $isMember): bool
    {
        $existing = $this->db->table($this->table)
            ->where('linked_user_id', $userId)
            ->where('parish_id', $parishId)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        if (!$existing) {
            return false;
        }

        $fromStatus = (string) ($existing['membership_status'] ?? 'visitor');
        $status     = MembershipStatusService::normalizeAffiliationStatus($fromStatus);

        // Cannot promote a non-member to member unless the record is already
        // tagged as a member or was previously an inactive member.
        if ($isMember
            && (int) ($existing['is_member'] ?? 0) === 0
            && ! in_array($status, ['member', 'inactive'], true)
        ) {
            return false;
        }
        if ($isMember && ! in_array($status, ['member', 'inactive'], true)) {
            $status = 'member';
        }
        if (! $isMember && in_array($status, ['member', 'inactive'], true)) {
            $status = 'visitor';
        }

        $result = $this->update((int) $existing['id'], [
            'is_member'         => $isMember ? 1 : 0,
            'membership_status' => $status,
            'affiliation_type'  => MembershipStatusService::affiliationTypeForStatus($status),
        ]);

        if ($result && $fromStatus !== $status) {
            $personId = (int) ($existing['person_id'] ?? 0);
            if ($personId > 0) {
                $this->history->record(
                    $personId,
                    'affiliation',
                    $fromStatus,
                    $status,
                    $parishId,
                    auth()->loggedIn() ? (int) auth()->id() : null,
                    'Member tag toggled'
                );
            }
        }

        return $result;
    }

    public function isRecordableMember(int $userId, int $parishId): bool
    {
        $row = $this->where('linked_user_id', $userId)
            ->where('parish_id', $parishId)
            ->where('is_member', 1)
            ->where('membership_status', 'member')
            ->first();

        return $row !== null;
    }

    private function isMemberColumnAvailable(): bool
    {
        // parish_affiliation always has is_member — kept for backward-compat call sites.
        return true;
    }

    public function detachFromParish(int $userId, int $parishId): bool
    {
        $existing = $this->where('linked_user_id', $userId)
            ->where('parish_id', $parishId)
            ->where('is_member', 1)
            ->first();

        if (!$existing) {
            return false;
        }

        return $this->update((int) $existing['id'], [
            'is_member' => 0,
            'end_date'  => date('Y-m-d'),
        ]);
    }

    public function setHomeParish(int $userId, int $parishId): bool
    {
        $db = $this->db;
        $db->transStart();

        try {
            $existing = $db->table('parish_affiliation')
                ->where('linked_user_id', $userId)
                ->where('parish_id', $parishId)
                ->where('is_member', 1)
                ->get()
                ->getRowArray();

            if (!$existing) {
                $db->transRollback();
                return false;
            }

            // Clear any existing primary flag for this user.
            $db->table('parish_affiliation')
                ->where('linked_user_id', $userId)
                ->where('is_primary', 1)
                ->update(['is_primary' => 0]);

            // Set the chosen active affiliation as primary.
            $db->table('parish_affiliation')
                ->where('id', (int) $existing['id'])
                ->update(['is_primary' => 1]);

            $db->transComplete();

            return $db->transStatus() !== false;
        } catch (\Throwable $e) {
            $db->transRollback();
            return false;
        }
    }

    private function decryptProfileFields(array $row): array
    {
        if (array_key_exists('phone', $row)) {
            $row['phone'] = DataEncryption::decrypt($row['phone']);
        }
        if (array_key_exists('address', $row)) {
            $row['address'] = DataEncryption::decrypt($row['address']);
        }

        return $row;
    }

    /**
     * Resolve (or create) a person_id for a user so that parish_affiliation
     * rows can satisfy the fk_pa_person foreign key.
     */
    public function getOrCreatePersonIdForUser(int $userId): ?int
    {
        if (! $this->db->tableExists('user_person') || ! $this->db->tableExists('person')) {
            return null;
        }

        $existing = $this->db->table('user_person')
            ->where('user_id', $userId)
            ->get()
            ->getRowArray();

        if (! empty($existing['person_id'])) {
            return (int) $existing['person_id'];
        }

        $profile = $this->db->table('profile')
            ->select('first_name, last_name')
            ->where('user_id', $userId)
            ->get()
            ->getRowArray();

        $firstName = trim((string) ($profile['first_name'] ?? ''));
        $lastName  = trim((string) ($profile['last_name'] ?? ''));

        if ($firstName === '' && $lastName === '') {
            $identity = $this->db->table('auth_identities')
                ->select('secret')
                ->where('user_id', $userId)
                ->where('type', 'email_password')
                ->get()
                ->getRowArray();
            $email = (string) ($identity['secret'] ?? '');
            $firstName = $email !== '' ? 'User' : 'Unknown';
            $lastName  = $email !== '' ? $email : 'Unknown';
        }

        $now = date('Y-m-d H:i:s');

        $this->db->table('person')->insert([
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $personId = (int) $this->db->insertID();

        $this->db->table('user_person')->insert([
            'user_id'    => $userId,
            'person_id'  => $personId,
            'is_primary' => 1,
            'linked_at'  => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $personId;
    }
}
