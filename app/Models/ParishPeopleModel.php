<?php

namespace App\Models;

use App\Services\MembershipStatusService;

class ParishPeopleModel extends BaseTenantModel
{
    protected $table            = 'parish_people';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'parish_id',
        'church_id',
        'user_id',
        'profile_id',
        'first_name',
        'last_name',
        'nickname',
        'profile_picture',
        'email',
        'phone',
        'portal_access',
        'membership_status',
        'is_member',
        'is_good_standing',
        'is_baptized',
        'is_confirmed',
        'is_communicant',
        'is_dependent',
        'is_under_16',
        'has_pledge',
        'is_leader',
        'leader_titles',
        'source',
        'synced_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $beforeInsert  = ['mapScopeColumnBeforeWrite'];
    protected $beforeUpdate  = ['mapScopeColumnBeforeWrite'];
    protected $afterFind     = ['mapScopeColumnAfterFind'];
    protected string $scopeColumn = 'parish_id';

    protected function initialize()
    {
        $this->detectSnapshotStorage();
    }

    /**
     * @return array{rows: array<int, array<string,mixed>>, total: int}
     */
    public function getParishPeoplePaginated(int $parishId, ?string $scopeFilter = null, ?string $status = null, int $perPage = 25, int $page = 1, array $extraFilters = []): array
    {
        // Build shared WHERE criteria as a plain array so they can be applied
        // to two independent builders: one for COUNT, one for the paginated rows.
        // Using clone on a CI4 Model shares the underlying BaseBuilder, which
        // causes countAllResults() to reset the WHERE conditions before findAll().
        $conditions = [$this->scopeColumn => $parishId];

        if ($scopeFilter === 'members') {
            $conditions['is_member'] = 1;
        } elseif ($scopeFilter === 'non_members') {
            $conditions['is_member'] = 0;
        }

        if ($status !== null && $status !== '') {
            $conditions['membership_status'] = $status;
        }

        $allowedSnapshotFilters = [
            'is_baptized',
            'is_confirmed',
            'is_communicant',
            'is_dependent',
            'is_under_16',
            'has_pledge',
            'is_leader',
        ];
        foreach ($extraFilters as $col => $val) {
            if (in_array($col, $allowedSnapshotFilters, true)) {
                $conditions[$col] = (int) $val;
            }
        }

        // Independent count query – no shared builder state.
        $countBuilder = $this->db->table($this->table);
        foreach ($conditions as $col => $val) {
            $countBuilder->where($col, $val);
        }
        $countBuilder->where('deleted_at', null);
        $total = (int) $countBuilder->countAllResults();

        $page   = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        // Row query – apply the same criteria directly on the model builder.
        // Because the count above uses $this->db->table() (not the model's builder),
        // no shared state was dirtied; $this is clean here.
        foreach ($conditions as $col => $val) {
            $this->where($col, $val);
        }

        $rows = $this
            ->orderBy('last_name', 'ASC')
            ->orderBy('first_name', 'ASC')
            ->findAll($perPage, $offset);

        return [
            'rows' => $rows,
            'total' => $total,
        ];
    }

    public function getStatusCounts(int $parishId, string $scope = 'all'): array
    {
        $builder = $this->select('membership_status, COUNT(*) as count', false)
            ->where($this->scopeColumn, $parishId);

        switch ($scope) {
            case 'members':
                $builder->where('is_member', 1);
                break;
            case 'non_members':
                $builder->where('is_member', 0);
                break;
            case 'visitors':
                $builder->where('membership_status', 'visitor');
                break;
            case 'all':
            default:
                // No scope filter.
                break;
        }

        $rows = $builder
            ->groupBy('membership_status', false)
            ->findAll();

        return $this->normalizeStatusCountRows($rows);
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

    public function findSnapshotByParishUser(int $parishId, int $userId): ?array
    {
        $row = $this->where($this->scopeColumn, $parishId)
            ->where('user_id', $userId)
            ->first();

        return is_array($row) ? $row : null;
    }

    public function deleteMissingUsersForParish(int $parishId, array $seenUserIds = []): void
    {
        $builder = $this->where($this->scopeColumn, $parishId);
        if ($seenUserIds !== []) {
            $builder->whereNotIn('user_id', $seenUserIds);
        }

        $builder->delete();
    }

    public function getPeopleForExport(int $parishId, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return $this->where($this->scopeColumn, $parishId)
            ->whereIn('user_id', $userIds)
            ->orderBy('last_name', 'ASC')
            ->orderBy('first_name', 'ASC')
            ->findAll();
    }

    private function detectSnapshotStorage(): void
    {
        $parishColumns = $this->getColumnsForTable('parish_people');
        if ($parishColumns !== []) {
            $this->table = 'parish_people';
            $this->scopeColumn = $this->resolveScopeColumn($parishColumns);
            return;
        }

        $churchColumns = $this->getColumnsForTable('church_people');
        if ($churchColumns !== []) {
            $this->table = 'church_people';
            $this->scopeColumn = $this->resolveScopeColumn($churchColumns);
        }
    }

    /**
     * @return array<int,string>
     */
    private function getColumnsForTable(string $baseTable): array
    {
        $prefixed = $this->db->DBPrefix . $baseTable;
        $candidates = array_values(array_unique([$prefixed, $baseTable]));

        foreach ($candidates as $table) {
            try {
                $columns = $this->db->getFieldNames($table);
                if ($columns !== []) {
                    return $columns;
                }
            } catch (\Throwable $e) {
                // Try next name variant.
            }
        }

        return [];
    }

    /**
     * @param array<int,string> $columns
     */
    private function resolveScopeColumn(array $columns): string
    {
        $lowered = array_map('strtolower', $columns);
        if (in_array('parish_id', $lowered, true)) {
            return 'parish_id';
        }

        if (in_array('church_id', $lowered, true)) {
            return 'church_id';
        }

        return 'parish_id';
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    protected function mapScopeColumnBeforeWrite(array $data): array
    {
        if (!isset($data['data']) || !is_array($data['data'])) {
            return $data;
        }

        if ($this->scopeColumn === 'church_id') {
            if (array_key_exists('parish_id', $data['data']) && !array_key_exists('church_id', $data['data'])) {
                $data['data']['church_id'] = $data['data']['parish_id'];
            }
            unset($data['data']['parish_id']);

            return $data;
        }

        if (array_key_exists('church_id', $data['data']) && !array_key_exists('parish_id', $data['data'])) {
            $data['data']['parish_id'] = $data['data']['church_id'];
        }
        unset($data['data']['church_id']);

        return $data;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    protected function mapScopeColumnAfterFind(array $data): array
    {
        if ($this->scopeColumn !== 'church_id') {
            return $data;
        }

        if (!array_key_exists('data', $data)) {
            return $data;
        }

        if (is_array($data['data'])) {
            $data['data'] = $this->normalizeFoundData($data['data']);
        }

        return $data;
    }

    /**
     * @param array<int|string,mixed> $data
     * @return array<int|string,mixed>
     */
    private function normalizeFoundData(array $data): array
    {
        if ($data === []) {
            return $data;
        }

        if (array_is_list($data)) {
            foreach ($data as $index => $row) {
                if (is_array($row)) {
                    $data[$index] = $this->normalizeFoundRow($row);
                }
            }

            return $data;
        }

        return $this->normalizeFoundRow($data);
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function normalizeFoundRow(array $row): array
    {
        if (array_key_exists('church_id', $row) && !array_key_exists('parish_id', $row)) {
            $row['parish_id'] = $row['church_id'];
        }

        return $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByUser(int $userId): array
    {
        return $this->db->table($this->table)
            ->where('user_id', $userId)
            ->get()
            ->getResultArray();
    }

    /**
     * @param array<int, array{parish_id: int, membership_status: string}> $affiliations
     */
    public function replaceForUser(int $userId, int $primaryParishId, array $affiliations, ?int $profileId = null): void
    {
        try {
            $columns = array_map('strtolower', $this->db->getFieldNames($this->table));
        } catch (\Throwable $e) {
            return;
        }

        if (! in_array('parish_id', $columns, true)) {
            return;
        }

        $validStatuses = MembershipStatusService::affiliationStatuses();
        $desiredParishIds = [$primaryParishId];

        foreach ($affiliations as $affiliation) {
            $parishId = (int) ($affiliation['parish_id'] ?? 0);
            if ($parishId <= 0 || $parishId === $primaryParishId) {
                continue;
            }
            $desiredParishIds[] = $parishId;
        }

        // Remove affiliations that are no longer in the list, except the primary.
        $this->db->table($this->table)
            ->where('user_id', $userId)
            ->whereNotIn('parish_id', $desiredParishIds)
            ->delete();

        // Upsert remaining affiliations.
        foreach ($affiliations as $affiliation) {
            $parishId = (int) ($affiliation['parish_id'] ?? 0);
            if ($parishId <= 0 || $parishId === $primaryParishId) {
                continue;
            }

            $status = MembershipStatusService::normalizeAffiliationStatus((string) ($affiliation['membership_status'] ?? ''));
            if (!in_array($status, $validStatuses, true)) {
                $status = MembershipStatusService::profileToAffiliation((string) ($affiliation['membership_status'] ?? ''));
            }
            if (!in_array($status, $validStatuses, true)) {
                $status = 'visitor';
            }
            $isMember = (int) MembershipStatusService::isMember($status);

            $existing = $this->db->table($this->table)
                ->where('user_id', $userId)
                ->where('parish_id', $parishId)
                ->get()
                ->getRowArray();

            if ($existing) {
                if (($existing['membership_status'] ?? '') !== $status) {
                    $this->db->table($this->table)
                        ->where('id', $existing['id'])
                        ->update([
                            'membership_status' => $status,
                            'is_member' => $isMember,
                            'synced_at' => date('Y-m-d H:i:s'),
                        ]);
                }
            } else {
                $this->db->table($this->table)->insert([
                    'user_id' => $userId,
                    'parish_id' => $parishId,
                    'profile_id' => $profileId,
                    'membership_status' => $status,
                    'is_member' => $isMember,
                    'source' => 'member_portal',
                    'synced_at' => date('Y-m-d H:i:s'),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public function syncPrimaryParish(int $userId, array $profile): void
    {
        $primaryParishId = (int) ($profile['parish_id'] ?? 0);
        if ($primaryParishId <= 0) {
            return;
        }

        try {
            $columns = array_map('strtolower', $this->db->getFieldNames($this->table));
        } catch (\Throwable $e) {
            return;
        }

        if (! in_array('parish_id', $columns, true)) {
            return;
        }

        $validStatuses = MembershipStatusService::affiliationStatuses();
        $status = MembershipStatusService::normalizeAffiliationStatus((string) ($profile['membership_status'] ?? 'regular_attender'));
        if (!in_array($status, $validStatuses, true)) {
            $status = MembershipStatusService::profileToAffiliation((string) ($profile['membership_status'] ?? 'regular_attender'));
        }
        if (!in_array($status, $validStatuses, true)) {
            $status = 'visitor';
        }
        $isMember = (int) MembershipStatusService::isMember($status);

        $existing = $this->db->table($this->table)
            ->where('user_id', $userId)
            ->where('parish_id', $primaryParishId)
            ->get()
            ->getRowArray();

        if ($existing) {
            if (($existing['membership_status'] ?? '') !== $status) {
                $this->db->table($this->table)
                    ->where('id', $existing['id'])
                    ->update([
                        'membership_status' => $status,
                        'is_member' => $isMember,
                        'synced_at' => date('Y-m-d H:i:s'),
                    ]);
            }
        } else {
            $this->db->table($this->table)->insert([
                'user_id' => $userId,
                'parish_id' => $primaryParishId,
                'profile_id' => $profile['id'] ?? null,
                'first_name' => $profile['first_name'] ?? null,
                'last_name' => $profile['last_name'] ?? null,
                'email' => $profile['email'] ?? null,
                'membership_status' => $status,
                'is_member' => $isMember,
                'source' => 'member_portal',
                'synced_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
