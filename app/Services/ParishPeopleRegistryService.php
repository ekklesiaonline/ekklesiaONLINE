<?php

namespace App\Services;

use App\Libraries\DataEncryption;
use App\Models\ParishPeopleModel;
use App\Services\MembershipStatusService;
use App\Services\PersonStatusHistoryService;

class ParishPeopleRegistryService
{
    private ParishPeopleModel $parishPeopleModel;
    private PersonStatusHistoryService $history;

    public function __construct(?ParishPeopleModel $parishPeopleModel = null, ?PersonStatusHistoryService $history = null)
    {
        $this->parishPeopleModel = $parishPeopleModel ?? model(ParishPeopleModel::class);
        $this->history           = $history ?? new PersonStatusHistoryService();
    }

    public function syncParish(int $parishId): void
    {
        $year = (int) date('Y');
        $now = date('Y-m-d H:i:s');
        $profileTable = $this->resolveExistingTable(['profile']);
        $affiliationTable = $this->resolveExistingTable(['parish_affiliation']);
        $pledgeTable = $this->resolveExistingTable(['annual_pledge']);
        $leaderTable = $this->resolveExistingTable(['leadership_position']);
        $personMembershipDetailsTable = $this->resolveExistingTable(['person_membership_details']);

        if ($profileTable === null || $affiliationTable === null) {
            return;
        }

        $profileColumns = $this->getColumnsForTable($profileTable);
        $affiliationColumns = $this->getColumnsForTable($affiliationTable);
        $affiliationUserColumn = in_array('linked_user_id', array_map('strtolower', $affiliationColumns), true)
            ? 'linked_user_id'
            : 'user_id';
        $hasPortalAccess = in_array('portal_access', array_map('strtolower', $profileColumns), true);
        $portalAccessSelect = $hasPortalAccess ? 'p.portal_access' : '"none" as portal_access';

        $nicknameSelect = in_array('nickname', array_map('strtolower', $profileColumns), true) ? ', p.nickname' : '';

        $hasMss = $this->db()->tableExists('member_standing_summary');
        $mssSelect = $hasMss ? 'COALESCE(mss.is_good_standing, 0)' : '0';
        $mssJoin = $hasMss
            ? 'LEFT JOIN member_standing_summary mss ON mss.user_id = mca.' . $affiliationUserColumn . ' AND mss.year = ' . $year
            : '';

        // sacramental_record is the canonical source for baptism/confirmation
        // status. The snapshot falls back to person_membership_details flags
        // and then to profile dates only when no sacrament row exists.
        $hasSacramentTable = $this->db()->tableExists('sacramental_record');
        // Use ELSE NULL so MAX returns NULL (not 0) when no matching row exists,
        // allowing COALESCE to fall through to the pmd flag / profile fallback.
        $sacramentBaptizedExpr    = $hasSacramentTable ? 'MAX(CASE WHEN LOWER(sr.sacrament_type) = "baptism" THEN 1 ELSE NULL END)' : null;
        $sacramentConfirmedExpr   = $hasSacramentTable ? 'MAX(CASE WHEN LOWER(sr.sacrament_type) = "confirmation" THEN 1 ELSE NULL END)' : null;
        $sacramentJoin = $hasSacramentTable
            ? 'LEFT JOIN sacramental_record sr ON sr.person_id = p.id AND sr.parish_id = mca.parish_id AND LOWER(sr.sacrament_type) IN ("baptism","confirmation")'
            : '';

        $ageExpr = 'CASE WHEN p.birth_date IS NOT NULL AND TIMESTAMPDIFF(YEAR, p.birth_date, CURDATE()) < 16 THEN 1 ELSE 0 END';
        $baptizedExpr = 'CASE WHEN p.baptism_date IS NOT NULL THEN 1 ELSE 0 END';
        $confirmedExpr = 'CASE WHEN p.confirmation_date IS NOT NULL OR p.is_confirmed = 1 THEN 1 ELSE 0 END';
        $communicantExpr = $hasMss
            ? 'MAX(CASE WHEN mss.communion_count >= 3 THEN 1 ELSE 0 END)'
            : '0';
        $dependentExpr = 'COALESCE(p.is_dependent, 0)';

        // Build the COALESCE chain: sacramental_record -> pmd flag -> profile fallback.
        $pmdBaptized    = $this->buildBaptizedExpr($personMembershipDetailsTable, $hasSacramentTable, $sacramentBaptizedExpr, $baptizedExpr);
        $pmdConfirmed   = $this->buildConfirmedExpr($personMembershipDetailsTable, $hasSacramentTable, $sacramentConfirmedExpr, $confirmedExpr);
        $pmdCommunicant = $personMembershipDetailsTable !== null ? 'COALESCE(MAX(pmd.is_communicant), ' . $communicantExpr . ')' : $communicantExpr;
        $pmdDependent   = $personMembershipDetailsTable !== null ? 'COALESCE(MAX(pmd.is_dependent), ' . $dependentExpr . ')' : $dependentExpr;

        $hasPledgeJoin = $pledgeTable !== null;
        $pledgeJoin = '';
        $hasPledgeExpr = '0';
        if ($hasPledgeJoin) {
            $pledgeJoin = ' LEFT JOIN ' . $pledgeTable . ' ap ON ap.user_id = mca.' . $affiliationUserColumn . ' AND ap.pledge_year = ' . $year . ' AND (ap.fulfillment_status != "cancelled" OR ap.fulfillment_status IS NULL)';
            $hasPledgeExpr = 'MAX(CASE WHEN ap.id IS NOT NULL THEN 1 ELSE 0 END)';
        }

        $pmdHasPledge    = $personMembershipDetailsTable !== null && $hasPledgeExpr !== '0'
            ? 'COALESCE(MAX(pmd.has_pledge), ' . $hasPledgeExpr . ')'
            : ($personMembershipDetailsTable !== null ? 'COALESCE(MAX(pmd.has_pledge), 0)' : $hasPledgeExpr);

        $hasLeadershipJoin = $leaderTable !== null;
        $isLeaderExpr = '0';
        $leaderTitlesExpr = '""';
        if ($hasLeadershipJoin) {
            $isLeaderExpr = 'MAX(CASE WHEN lp.id IS NOT NULL THEN 1 ELSE 0 END)';
            $leaderTitlesExpr = 'COALESCE(GROUP_CONCAT(DISTINCT lp.title ORDER BY lp.title SEPARATOR ", "), "")';
        }

        $query = db_connect()->table($affiliationTable . ' mca')
            ->select('mca.parish_id AS parish_id, mca.' . $affiliationUserColumn . ' AS user_id, p.id as profile_id, p.first_name, p.last_name' . $nicknameSelect . ', p.profile_picture, p.phone, ' . $portalAccessSelect . ', SUBSTRING_INDEX(GROUP_CONCAT(mca.membership_status ORDER BY COALESCE(mca.is_primary, 0) DESC, mca.id DESC), \',\', 1) AS membership_status, SUBSTRING_INDEX(GROUP_CONCAT(mca.is_member ORDER BY COALESCE(mca.is_primary, 0) DESC, mca.id DESC), \',\', 1) AS is_member, ' . $pmdBaptized . ' AS is_baptized, ' . $pmdConfirmed . ' AS is_confirmed, ' . $pmdCommunicant . ' AS is_communicant, ' . $pmdDependent . ' AS is_dependent, ' . $ageExpr . ' AS is_under_16, ' . $pmdHasPledge . ' AS has_pledge, ' . $isLeaderExpr . ' AS is_leader, ' . $leaderTitlesExpr . ' AS leader_titles, ' . $mssSelect . ' as is_good_standing, MAX(ai.secret) as email, MAX(COALESCE(ai.force_reset, 0)) as force_reset', false)
            ->join('users u', 'u.id = mca.' . $affiliationUserColumn, 'inner')
            ->join($profileTable . ' p', 'p.user_id = mca.' . $affiliationUserColumn . ' AND (p.parish_id = mca.parish_id OR p.parish_id IS NULL)', 'inner')
            ->join('auth_identities ai', 'ai.user_id = mca.' . $affiliationUserColumn . ' AND ai.type = "email_password"', 'left')
            ->join('auth_groups_users agu', 'agu.user_id = mca.' . $affiliationUserColumn . ' AND agu.group IN ("provinceadmin","dioceseadmin")', 'left');

        if ($mssJoin !== '') {
            $query->join('member_standing_summary mss', 'mss.user_id = mca.' . $affiliationUserColumn . ' AND mss.year = ' . $year, 'left');
        }

        if ($pledgeJoin !== '') {
            // BaseBuilder::join() takes a string.
            $query->join($pledgeTable . ' ap', 'ap.user_id = mca.' . $affiliationUserColumn . ' AND ap.pledge_year = ' . $year . ' AND (ap.fulfillment_status != "cancelled" OR ap.fulfillment_status IS NULL)', 'left');
        }

        if ($hasLeadershipJoin) {
            $query->join($leaderTable . ' lp', 'lp.user_id = mca.' . $affiliationUserColumn . ' AND lp.active = 1 AND lp.scope_type = "parish" AND lp.parish_id = mca.parish_id', 'left');
        }

        if ($personMembershipDetailsTable !== null) {
            $query->join($personMembershipDetailsTable . ' pmd', 'pmd.person_id = p.id AND pmd.parish_id = mca.parish_id', 'left');
        }

        if ($sacramentJoin !== '') {
            $query->join('sacramental_record sr', 'sr.person_id = p.id AND sr.parish_id = mca.parish_id AND LOWER(sr.sacrament_type) IN ("baptism","confirmation")', 'left');
        }

        $rows = $query
            ->where('mca.parish_id', $parishId)
            ->where('mca.deleted_at', null)
            ->where('p.deleted_at', null)
            ->where('u.active', 1)
            ->where('agu.id IS NULL')
            ->groupBy('mca.parish_id, mca.' . $affiliationUserColumn . ', p.id', false)
            ->get()
            ->getResultArray();

        $snapshotTable = $this->resolveSnapshotTable();
        if ($snapshotTable === null) {
            return;
        }
        $snapshotScopeColumn = $this->resolveSnapshotScopeColumn($snapshotTable);

        $seenUserIds = [];
        foreach ($rows as $row) {
            $seenUserIds[] = (int) ($row['user_id'] ?? 0);

            $membershipStatus = MembershipStatusService::normalizeAffiliationStatus((string) ($row['membership_status'] ?? ''));
            if (!in_array($membershipStatus, MembershipStatusService::affiliationStatuses(), true)) {
                $membershipStatus = 'visitor';
            }

            $portalAccess = strtolower(trim((string) ($row['portal_access'] ?? '')));
            if (!in_array($portalAccess, ['none', 'invited', 'enabled'], true)) {
                $portalAccess = 'none';
            }
            $hasPortalIdentity = trim((string) ($row['email'] ?? '')) !== '';
            $requiresReset = (int) ($row['force_reset'] ?? 0) === 1;

            if ($hasPortalIdentity) {
                if ($requiresReset) {
                    $portalAccess = 'invited';
                } elseif ($portalAccess === 'none' || $portalAccess === 'invited') {
                    $portalAccess = 'enabled';
                }
            }

            $payload = [
                'parish_id' => (int) ($row['parish_id'] ?? 0),
                'user_id' => (int) ($row['user_id'] ?? 0),
                'profile_id' => isset($row['profile_id']) ? (int) $row['profile_id'] : null,
                'first_name' => (string) ($row['first_name'] ?? ''),
                'last_name' => (string) ($row['last_name'] ?? ''),
                'nickname' => isset($row['nickname']) && $row['nickname'] !== '' ? (string) $row['nickname'] : null,
                'profile_picture' => isset($row['profile_picture']) && $row['profile_picture'] !== '' ? (string) $row['profile_picture'] : null,
                'email' => (string) ($row['email'] ?? ''),
                'phone' => DataEncryption::decrypt((string) ($row['phone'] ?? '')) ?? '',
                'portal_access' => $portalAccess,
                'membership_status' => $membershipStatus,
                'is_member' => (int) ($row['is_member'] ?? 0),
                'is_baptized' => (int) ($row['is_baptized'] ?? 0),
                'is_confirmed' => (int) ($row['is_confirmed'] ?? 0),
                'is_communicant' => (int) ($row['is_communicant'] ?? 0),
                'is_dependent' => (int) ($row['is_dependent'] ?? 0),
                'is_under_16' => (int) ($row['is_under_16'] ?? 0),
                'has_pledge' => (int) ($row['has_pledge'] ?? 0),
                'is_good_standing' => (int) ($row['is_good_standing'] ?? 0),
                'is_leader' => (int) ($row['is_leader'] ?? 0),
                'leader_titles' => (string) ($row['leader_titles'] ?? ''),
                'source' => 'user_profile',
                'synced_at' => $now,
            ];

            $existing = db_connect()->table($snapshotTable)
                ->where($snapshotScopeColumn, $payload['parish_id'])
                ->where('user_id', $payload['user_id'])
                ->where('deleted_at', null)
                ->get()
                ->getRowArray();

            if ($existing) {
                db_connect()->table($snapshotTable)
                    ->where('id', (int) $existing['id'])
                    ->update($payload);

                $this->recordStatusChange(
                    (int) ($existing['profile_id'] ?? $payload['profile_id'] ?? 0),
                    (int) $payload['parish_id'],
                    (string) ($existing['membership_status'] ?? ''),
                    (string) $payload['membership_status']
                );
                continue;
            }

            db_connect()->table($snapshotTable)->insert($payload);

            $this->recordStatusChange(
                (int) ($payload['profile_id'] ?? 0),
                (int) $payload['parish_id'],
                null,
                (string) $payload['membership_status']
            );
        }

        $deleteBuilder = db_connect()->table($snapshotTable)
            ->where($snapshotScopeColumn, $parishId);

        if ($seenUserIds !== []) {
            $deleteBuilder->whereNotIn('user_id', $seenUserIds);
        }

        $deleteBuilder->delete();
    }

    public function syncAll(): array
    {
        $parishRows = db_connect()->table('parish')
            ->select('id')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $synced = 0;
        foreach ($parishRows as $row) {
            $parishId = (int) ($row['id'] ?? 0);
            if ($parishId <= 0) {
                continue;
            }

            $this->syncParish($parishId);
            $synced++;
        }

        return [
            'parishes_synced' => $synced,
        ];
    }

    /**
     * @return array<int,string>
     */
    private function getColumnsForTable(string $baseTable): array
    {
        $db = db_connect();
        $prefixed = $db->getPrefix() . $baseTable;
        $candidates = array_values(array_unique([$prefixed, $baseTable]));

        foreach ($candidates as $table) {
            try {
                $columns = $db->getFieldNames($table);
                if ($columns !== []) {
                    return $columns;
                }
            } catch (\Throwable $e) {
                // Try next name variant.
            }
        }

        return [];
    }

    private function resolveExistingTable(array $baseCandidates): ?string
    {
        foreach ($baseCandidates as $candidate) {
            if ($this->getColumnsForTable($candidate) !== []) {
                return $candidate;
            }
        }

        return null;
    }

    private function resolveSnapshotTable(): ?string
    {
        foreach (['parish_people', 'church_people'] as $candidate) {
            if ($this->getColumnsForTable($candidate) !== []) {
                return $candidate;
            }
        }

        return null;
    }

    private function resolveSnapshotScopeColumn(string $table): string
    {
        $columns = $this->getColumnsForTable($table);
        $lowered = array_map('strtolower', $columns);

        if (in_array('parish_id', $lowered, true)) {
            return 'parish_id';
        }

        if (in_array('church_id', $lowered, true)) {
            return 'church_id';
        }

        return 'parish_id';
    }

    private function db(): \CodeIgniter\Database\BaseConnection
    {
        return db_connect();
    }

    /**
     * Build the COALESCE chain for is_baptized:
     * sacramental_record -> person_membership_details -> profile.baptism_date.
     */
    private function buildBaptizedExpr(
        ?string $pmdTable,
        bool $hasSacramentTable,
        ?string $sacramentBaptizedExpr,
        string $baptizedExpr
    ): string {
        $chain = [];
        if ($hasSacramentTable && $sacramentBaptizedExpr !== null) {
            $chain[] = $sacramentBaptizedExpr;
        }
        if ($pmdTable !== null) {
            $chain[] = 'MAX(pmd.is_baptized)';
        }
        $chain[] = $baptizedExpr;

        return 'COALESCE(' . implode(', ', $chain) . ')';
    }

    /**
     * Build the COALESCE chain for is_confirmed:
     * sacramental_record -> person_membership_details -> profile fallback.
     */
    private function buildConfirmedExpr(
        ?string $pmdTable,
        bool $hasSacramentTable,
        ?string $sacramentConfirmedExpr,
        string $confirmedExpr
    ): string {
        $chain = [];
        if ($hasSacramentTable && $sacramentConfirmedExpr !== null) {
            $chain[] = $sacramentConfirmedExpr;
        }
        if ($pmdTable !== null) {
            $chain[] = 'MAX(pmd.is_confirmed)';
        }
        $chain[] = $confirmedExpr;

        return 'COALESCE(' . implode(', ', $chain) . ')';
    }

    private function recordStatusChange(int $personId, int $parishId, ?string $fromStatus, string $toStatus): void
    {
        if ($personId <= 0) {
            return;
        }

        $this->history->record(
            $personId,
            'affiliation',
            $fromStatus === '' ? null : $fromStatus,
            $toStatus,
            $parishId,
            null,
            'Snapshot sync'
        );
    }
}
