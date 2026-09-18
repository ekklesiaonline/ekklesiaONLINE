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

        $sort = (string) ($filters['sort'] ?? 'p.last_name ASC, p.first_name ASC');
        foreach (explode(',', $sort) as $part) {
            $sortParts = preg_split('/\s+/', trim($part));
            $sortField = $sortParts[0] ?? 'p.last_name';
            $sortDir   = strtoupper($sortParts[1] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
            if (! preg_match('/^[a-z_]+\.[a-z_0-9]+$/i', $sortField)) {
                continue;
            }
            $builder->orderBy($sortField, $sortDir);
        }

        $rows = $builder
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

    /**
     * Membership-status tallies for a parish directory scope — canonical
     * replacement for ParishPeopleModel::getStatusCounts. Counts distinct
     * person_ids from parish_affiliation.
     *
     * @return array<int, array{membership_status: string, count: int}>
     */
    public function statusCountsForParish(int $parishId, string $scope = 'all'): array
    {
        $builder = $this->db->table('parish_affiliation')
            ->select('membership_status, COUNT(DISTINCT person_id) AS count', false)
            ->where('parish_id', $parishId)
            ->where('deleted_at IS NULL', null, false);

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
                break;
        }

        $bucket = [];
        $validStatuses = MembershipStatusService::affiliationStatuses();

        foreach ($builder->groupBy('membership_status', false)->get()->getResultArray() as $row) {
            $status = MembershipStatusService::normalizeAffiliationStatus((string) ($row['membership_status'] ?? ''));
            if (! in_array($status, $validStatuses, true)) {
                $status = MembershipStatusService::profileToAffiliation($status);
            }
            if (! in_array($status, $validStatuses, true)) {
                $status = 'visitor';
            }

            $bucket[$status] = (int) ($bucket[$status] ?? 0) + (int) ($row['count'] ?? 0);
        }

        $normalized = [];
        foreach ($bucket as $status => $count) {
            $normalized[] = ['membership_status' => $status, 'count' => $count];
        }

        return $normalized;
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
            // Canonical sources for the legacy parish_people flag columns:
            // person_membership_details (baptized/confirmed/communicant/
            // dependent/has_pledge), person.birthdate (under-16), and
            // leadership_position (is_leader).
            $needsPmd = array_intersect(
                array_keys($extraFilters),
                ['is_baptized', 'is_confirmed', 'is_communicant', 'is_dependent', 'has_pledge']
            );

            if ($needsPmd !== [] && $this->db->tableExists('person_membership_details')) {
                $builder->join(
                    'person_membership_details pmd',
                    'pmd.person_id = p.id AND pmd.parish_id = pa.parish_id',
                    'LEFT'
                );

                foreach ($needsPmd as $column) {
                    $builder->where('pmd.' . $column, (int) $extraFilters[$column]);
                }
            }

            if (array_key_exists('is_under_16', $extraFilters)) {
                $cutoff = date('Y-m-d', strtotime('-16 years'));
                if ((int) $extraFilters['is_under_16'] === 1) {
                    $builder->where('p.birthdate >', $cutoff);
                } else {
                    $builder->groupStart()
                        ->where('p.birthdate <=', $cutoff)
                        ->orWhere('p.birthdate IS NULL', null, false)
                        ->groupEnd();
                }
            }

            if (array_key_exists('is_leader', $extraFilters)) {
                $existsSql = 'EXISTS (SELECT 1 FROM ' . $this->db->prefixTable('leadership_position')
                    . ' lp WHERE (lp.person_id = p.id OR lp.user_id = pa.linked_user_id)'
                    . ' AND lp.parish_id = pa.parish_id AND lp.scope_type = "parish" AND lp.active = 1)';
                $builder->where(((int) $extraFilters['is_leader'] === 1 ? '' : 'NOT ') . $existsSql, null, false);
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
            $hash = PersonContactService::emailIndex($term);
            if ($hash !== null) {
                $builder->where('p.email_hash', $hash);
            }

            return;
        }

        if (ctype_digit($term)) {
            $hash = PersonContactService::phoneIndex($term);
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

    /**
     * Canonical replacement for ParishPeopleModel::getPeopleForExport —
     * parish-scoped export rows for the given portal user IDs.
     *
     * @param array<int, int> $userIds
     * @return array<int, array<string, mixed>>
     */
    public function peopleForExport(int $parishId, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $rows = $this->db->table('parish_affiliation pa')
            ->select([
                'p.first_name',
                'p.last_name',
                'p.email AS person_email',
                'p.phone AS person_phone',
                'pa.membership_status',
                'pa.is_member',
                'pa.is_good_standing',
                'pa.linked_user_id AS user_id',
                'pr.portal_access',
                'ai.secret AS email_identity',
            ])
            ->join('person p', 'p.id = pa.person_id', 'inner')
            ->join('profile pr', 'pr.user_id = pa.linked_user_id', 'left')
            ->join('auth_identities ai', 'ai.user_id = pa.linked_user_id AND ai.type = "email_password"', 'left')
            ->where('pa.parish_id', $parishId)
            ->where('pa.deleted_at IS NULL', null, false)
            ->whereIn('pa.linked_user_id', $userIds)
            ->orderBy('p.last_name', 'ASC')
            ->orderBy('p.first_name', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($rows as &$row) {
            $row['email'] = (string) ($row['email_identity'] ?? '') !== ''
                ? (string) $row['email_identity']
                : PersonContactService::decrypt((string) ($row['person_email'] ?? ''));
            $row['phone'] = PersonContactService::decrypt((string) ($row['person_phone'] ?? ''));
            $row['portal_access'] = (string) ($row['portal_access'] ?? 'none');
            unset($row['person_email'], $row['person_phone'], $row['email_identity']);
        }
        unset($row);

        return $rows;
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
