<?php

namespace App\Services;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;

class PeopleDirectoryService
{
    private const SNAPSHOT_FILTERS = [
        'is_baptized',
        'is_confirmed',
        'is_communicant',
        'is_dependent',
        'is_under_16',
        'has_pledge',
        'is_leader',
    ];

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * Fetch a paginated list of people for a parish.
     *
     * @return array<string,mixed>
     */
    public function listForParish(int $parishId, array $filters = []): array
    {
        $limit  = (int) ($filters['limit'] ?? 25);
        $offset = (int) ($filters['offset'] ?? 0);

        $builder = $this->buildBaseQuery($parishId, $filters);

        // Pass $reset=false: countAllResults() resetting the query builder also clears
        // the aliased-table tracking on the shared BaseConnection (clone and original
        // both reference the same connection instance), which corrupts the alias
        // resolution for the original builder's subsequent get() call whenever a DB
        // table prefix is configured (e.g. the test DB). $countBuilder is discarded
        // right after this, so it doesn't need to reset itself.
        $countBuilder = clone $builder;
        $total = (int) $countBuilder->countAllResults(false);

        $sort  = (string) ($filters['sort'] ?? 'p.last_name ASC, p.first_name ASC');
        $sortParts = explode(' ', $sort, 2);
        $sortField = $sortParts[0] ?? 'p.last_name';
        $sortDir   = ($sortParts[1] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

        $rows = $builder
            ->orderBy($sortField, $sortDir)
            ->limit($limit, $offset)
            ->get()
            ->getResultArray();

        $items = array_map(fn (array $row): array => $this->formatRow($row), $rows);

        return [
            'items'  => $items,
            'total'  => $total,
            'limit'  => $limit,
            'offset' => $offset,
        ];
    }

    /**
     * Count people for a parish without fetching the rows.
     */
    public function countForParish(int $parishId, array $filters = []): int
    {
        $builder = $this->buildBaseQuery($parishId, $filters);

        return (int) $builder->countAllResults();
    }

    private function buildBaseQuery(int $parishId, array $filters): BaseBuilder
    {
        $scope        = (string) ($filters['scope'] ?? 'all');
        $status       = (string) ($filters['status'] ?? '');
        $search       = trim((string) ($filters['search'] ?? ''));
        $extraFilters = array_intersect_key(
            (array) ($filters['extra_filters'] ?? []),
            array_flip(self::SNAPSHOT_FILTERS)
        );

        $builder = $this->db->table('person p')
            ->select([
                'p.id AS person_id',
                'p.first_name',
                'p.last_name',
                'p.email',
                'p.phone',
                'pr.phone AS profile_phone',
                'p.birthdate',
                'p.is_active',
                'p.gender',
                'pa.id AS affiliation_id',
                'pa.parish_id',
                'pa.affiliation_type',
                'pa.membership_status',
                'pa.canonical_status',
                'pa.is_member',
                'pa.is_good_standing',
                'pa.start_date',
                'pr.id AS profile_id',
                'pr.user_id',
                'pr.nickname',
                'pr.profile_picture',
                'pr.membership_status AS profile_membership_status',
                'u.username',
                'u.active AS user_active',
                'ai.secret AS email_identity',
                'ai.type AS identity_type',
            ])
            ->join('parish_affiliation pa', 'pa.person_id = p.id', 'INNER')
            ->join('profile pr', 'pr.user_id = pa.linked_user_id OR (pr.person_id = p.id AND pr.parish_id = pa.parish_id)', 'LEFT')
            ->join('users u', 'u.id = COALESCE(pa.linked_user_id, pr.user_id)', 'LEFT')
            ->join('auth_identities ai', 'ai.user_id = u.id AND ai.type = "email_password"', 'LEFT')
            ->where('pa.parish_id', $parishId)
            ->where('p.deleted_at', null)
            ->where('pa.deleted_at', null);

        if ($this->db->tableExists('member_standing_summary')) {
            $builder->select('mss.is_good_standing AS member_standing');
            $builder->join('member_standing_summary mss', 'mss.user_id = u.id', 'LEFT');
        }

        if ($extraFilters !== []) {
            $snapshotTable = $this->resolveSnapshotTable();

            if ($snapshotTable !== null) {
                $scopeColumn = $this->resolveSnapshotScopeColumn($snapshotTable);

                $builder->join(
                    $snapshotTable . ' pp',
                    'pp.' . $scopeColumn . ' = pa.parish_id AND pp.user_id = pr.user_id',
                    'INNER'
                );

                foreach ($extraFilters as $column => $value) {
                    $builder->where('pp.' . $column, (int) $value);
                }
            }
        }

        switch ($scope) {
            case 'members':
                $builder->where('pa.is_member', 1);
                break;
            case 'non_members':
                $builder->where('pa.is_member', 0);
                break;
            case 'visitors':
                $builder->where('pa.membership_status', 'visitor');
                break;
            case 'all':
            default:
                // No scope filter.
                break;
        }

        if ($status !== '') {
            $builder->where('pa.membership_status', $status);
        }

        if ($search !== '') {
            $this->applySearchFilter($builder, $search);
        }

        return $builder;
    }

    private function applySearchFilter(BaseBuilder $builder, string $search): void
    {
        $term = strtolower(trim($search));

        if (str_contains($term, '@')) {
            $hash = PersonContactService::hash($term);
            if ($hash !== null) {
                $builder->where('p.email_hash', $hash);
            }

            return;
        }

        if (ctype_digit($term)) {
            $hash = PersonContactService::hash($term);
            if ($hash !== null) {
                $builder->where('p.phone_hash', $hash);
            }

            return;
        }

        $builder->groupStart()
            ->like('p.first_name', $search, 'both', null, true)
            ->orLike('p.last_name', $search, 'both', null, true)
            ->groupEnd();
    }

    private function resolveSnapshotTable(): ?string
    {
        foreach (['parish_people', 'parish_people'] as $candidate) {
            if ($this->db->tableExists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function resolveSnapshotScopeColumn(string $table): string
    {
        $columns = array_map('strtolower', $this->db->getFieldNames($table));

        if (in_array('parish_id', $columns, true)) {
            return 'parish_id';
        }

        if (in_array('parish_id', $columns, true)) {
            return 'parish_id';
        }

        return 'parish_id';
    }

    private function formatRow(array $row): array
    {
        $row['email'] = ! empty($row['email_identity'])
            ? (string) $row['email_identity']
            : PersonContactService::decrypt((string) ($row['email'] ?? ''));

        $profilePhone = PersonContactService::decrypt((string) ($row['profile_phone'] ?? ''));
        $personPhone  = PersonContactService::decrypt((string) ($row['phone'] ?? ''));
        $row['phone'] = $profilePhone !== null && $profilePhone !== '' ? $profilePhone : $personPhone;
        unset($row['profile_phone']);

        return $row;
    }
}
