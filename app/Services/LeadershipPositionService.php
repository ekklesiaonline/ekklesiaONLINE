<?php

namespace App\Services;

use App\Models\LeadershipPositionModel;
use App\Models\MemberStandingSummaryModel;

/**
 * LeadershipPositionService
 *
 * Domain logic for leadership position CRUD: scope resolution, scope-based
 * manage checks, appointee eligibility, and persistence. HTTP concerns
 * (request parsing, JSON responses, auth/permission gates) stay in the
 * controller.
 */
class LeadershipPositionService
{
    public const CHECK_PORTAL_ACCESS = 'portal_access';
    public const CHECK_BAPTIZED      = 'baptized';
    public const CHECK_CONFIRMED     = 'confirmed';
    public const CHECK_PLEDGED       = 'pledged';
    public const CHECK_GOOD_STANDING = 'good_standing';

    private LeadershipPositionModel $model;

    public function __construct(?LeadershipPositionModel $model = null)
    {
        $this->model = $model ?? model(LeadershipPositionModel::class);
    }

    /**
     * Validation rules shared by create/update. The create endpoint also
     * requires scope_type.
     *
     * @return array<string, string>
     */
    public function validationRules(bool $includeScopeType): array
    {
        $rules = [
            'title'           => 'required|min_length[2]|max_length[150]',
            'role_category'   => 'permit_empty|in_list[clergy,lay_staff]',
            'clergy_order'    => 'permit_empty|in_list[bishop,priest,deacon]',
            'employment_type' => 'permit_empty|in_list[full_time,part_time,non_stipendiary,unpaid]',
            'started_at'      => 'permit_empty|valid_date',
            'ended_at'        => 'permit_empty|valid_date',
            'role_code'       => 'permit_empty|max_length[100]',
        ];

        if ($includeScopeType) {
            $rules['scope_type'] = 'required|in_list[parish,diocese,province]';
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $positionId): ?array
    {
        $row = $this->model->find($positionId);

        return is_array($row) ? $row : null;
    }

    /**
     * Resolve the concrete parish/diocese/province IDs for a scope type.
     * The caller supplies the actor's scope IDs for non-parish scopes.
     *
     * @return array{parish_id: ?int, diocese_id: ?int, province_id: ?int}|null
     */
    public function resolveScopeIds(string $scopeType, int $parishId, ?int $dioceseScopeId, ?int $provinceScopeId): ?array
    {
        $result = ['parish_id' => null, 'diocese_id' => null, 'province_id' => null];

        switch ($scopeType) {
            case 'parish':
                $result['parish_id'] = $parishId;
                return $result;
            case 'diocese':
                if ($dioceseScopeId === null || $dioceseScopeId <= 0) {
                    return null;
                }
                $result['diocese_id'] = $dioceseScopeId;
                return $result;
            case 'province':
                if ($provinceScopeId === null || $provinceScopeId <= 0) {
                    return null;
                }
                $result['province_id'] = $provinceScopeId;
                return $result;
            default:
                return null;
        }
    }

    /**
     * Whether the position falls inside the actor's administrative scope.
     */
    public function canManage(array $position, int $parishId, ?int $dioceseScopeId, ?int $provinceScopeId): bool
    {
        $scopeType = (string) ($position['scope_type'] ?? '');

        if ($scopeType === 'parish') {
            return (int) ($position['parish_id'] ?? 0) === $parishId;
        }

        if ($scopeType === 'diocese') {
            return $dioceseScopeId !== null && $dioceseScopeId > 0
                && (int) ($position['diocese_id'] ?? 0) === $dioceseScopeId;
        }

        if ($scopeType === 'province') {
            return $provinceScopeId !== null && $provinceScopeId > 0
                && (int) ($position['province_id'] ?? 0) === $provinceScopeId;
        }

        return false;
    }

    /**
     * Whether the position requires the appointee to be in good standing.
     * Canon I.17-style rule: lay leadership requires a communicant in good
     * standing; ordained ministry (clergy) is the exception. An unset
     * category is treated as lay (fail closed).
     */
    public function requiresGoodStanding(array $payload): bool
    {
        return (string) ($payload['role_category'] ?? '') !== 'clergy';
    }

    /**
     * Whether the person is in good standing for appointment purposes:
     * an admin-granted good_standing_override on their membership details
     * (scoped to the appointment parish when known), or a current-year
     * member_standing_summary row with is_good_standing = 1. Fails closed
     * when the standing data cannot be verified.
     */
    public function isInGoodStanding(int $userId, int $personId, ?int $parishId = null, ?int $year = null): bool
    {
        $db = db_connect();
        $year ??= (int) date('Y');

        if ($this->hasGoodStandingOverride($personId, $parishId)) {
            return true;
        }

        if ($userId > 0 && $this->tableExists($db, 'member_standing_summary')) {
            // member_standing_summary is a snapshot refreshed by
            // `standing:refresh`; recompute this user first so the gate
            // never reads a stale row.
            try {
                model(MemberStandingSummaryModel::class)->updateSingleUserStanding($userId, $year);
            } catch (\Throwable $e) {
                log_message('warning', 'LeadershipPositionService: standing refresh failed for user {user}: {error}', [
                    'user'  => $userId,
                    'error' => $e->getMessage(),
                ]);
            }

            $row = $db->table('member_standing_summary')
                ->select('is_good_standing')
                ->where('user_id', $userId)
                ->where('year', $year)
                ->get()
                ->getRowArray();

            return (int) ($row['is_good_standing'] ?? 0) === 1;
        }

        return false;
    }

    /**
     * Whether the person has an admin-granted good-standing override on
     * their membership details (scoped to the parish when known).
     */
    public function hasGoodStandingOverride(int $personId, ?int $parishId = null): bool
    {
        $db = db_connect();
        if ($personId <= 0 || ! $this->tableExists($db, 'person_membership_details')) {
            return false;
        }

        $builder = $db->table('person_membership_details')
            ->where('person_id', $personId)
            ->where('good_standing_override', 1);
        if ($parishId !== null && $parishId > 0) {
            $builder->where('parish_id', $parishId);
        }

        return $builder->countAllResults() > 0;
    }

    /**
     * Whether the user can actually sign in to the portal: an active users
     * row, an email_password identity, and profile.portal_access = 'enabled'
     * (invite accepted and first login completed — 'invited' is not enough
     * for leadership eligibility).
     */
    public function hasPortalAccess(int $userId): bool
    {
        $db = db_connect();
        if ($userId <= 0
            || ! $this->tableExists($db, 'users')
            || ! $this->tableExists($db, 'auth_identities')) {
            return false;
        }

        $user = $db->table('users')->select('active')->where('id', $userId)->get()->getRowArray();
        if ($user === null || (int) ($user['active'] ?? 0) !== 1) {
            return false;
        }

        $hasIdentity = $db->table('auth_identities')
            ->where('user_id', $userId)
            ->where('type', 'email_password')
            ->countAllResults() > 0;
        if (! $hasIdentity) {
            return false;
        }

        if ($this->tableExists($db, 'profile') && $this->columnExistsSafe($db, 'profile', 'portal_access')) {
            $profile = $db->table('profile')
                ->select('portal_access')
                ->where('user_id', $userId)
                ->orderBy('id', 'DESC')
                ->get()
                ->getRowArray();

            return strtolower(trim((string) ($profile['portal_access'] ?? 'none'))) === 'enabled';
        }

        return true;
    }

    /**
     * Portal-access map for a list of users — one batched pass for UI
     * annotation. Returns user_id => bool.
     *
     * @param array<int, int> $userIds
     * @return array<int, bool>
     */
    public function portalAccessMapForUsers(array $userIds): array
    {
        $db = db_connect();
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id): bool => $id > 0)));
        if ($userIds === [] || ! $this->tableExists($db, 'users') || ! $this->tableExists($db, 'auth_identities')) {
            return [];
        }

        $identityRows = $db->table('auth_identities')
            ->distinct()
            ->select('user_id')
            ->where('type', 'email_password')
            ->whereIn('user_id', $userIds)
            ->get()
            ->getResultArray();
        $hasIdentity = [];
        foreach ($identityRows as $row) {
            $hasIdentity[(int) $row['user_id']] = true;
        }

        $activeRows = $db->table('users')
            ->select('id')
            ->where('active', 1)
            ->whereIn('id', $userIds)
            ->get()
            ->getResultArray();
        $isActive = [];
        foreach ($activeRows as $row) {
            $isActive[(int) $row['id']] = true;
        }

        $enabled = [];
        if ($this->tableExists($db, 'profile') && $this->columnExistsSafe($db, 'profile', 'portal_access')) {
            $profileRows = $db->table('profile')
                ->select('user_id, portal_access')
                ->whereIn('user_id', $userIds)
                ->get()
                ->getResultArray();
            foreach ($profileRows as $row) {
                $uid = (int) $row['user_id'];
                $enabled[$uid] = ($enabled[$uid] ?? false)
                    || strtolower(trim((string) ($row['portal_access'] ?? 'none'))) === 'enabled';
            }
        } else {
            $enabled = $hasIdentity;
        }

        $map = [];
        foreach ($userIds as $uid) {
            $map[$uid] = isset($hasIdentity[$uid], $isActive[$uid]) && ($enabled[$uid] ?? false);
        }

        return $map;
    }

    /**
     * Full leadership eligibility for an appointee.
     *
     * Portal access is required for every position (clergy included).
     * Lay positions additionally require baptism plus the good-standing
     * block (confirmed + current-year pledge + communicant-in-good-standing
     * or an admin override); clergy are exempt from the standing block.
     *
     * @param array<string, mixed> $payload Position payload (role_category drives the clergy exemption)
     * @return array{eligible: bool, checks: array<string, bool|null>, failed: array<int, string>}
     */
    public function eligibilityFor(int $userId, int $personId, ?int $parishId, array $payload = []): array
    {
        $checks = [self::CHECK_PORTAL_ACCESS => $this->hasPortalAccess($userId)];

        if ($this->requiresGoodStanding($payload)) {
            $override = $this->hasGoodStandingOverride($personId, $parishId);

            // Baptism is a fact check, not a standing attribute — the
            // good-standing override never bypasses it.
            $checks[self::CHECK_BAPTIZED] = $this->hasSacramentOrFlag($personId, $parishId, 'baptism', 'is_baptized');

            if ($override) {
                $checks[self::CHECK_CONFIRMED]     = true;
                $checks[self::CHECK_PLEDGED]       = true;
                $checks[self::CHECK_GOOD_STANDING] = true;
            } else {
                $checks[self::CHECK_CONFIRMED]     = $this->hasSacramentOrFlag($personId, $parishId, 'confirmation', 'is_confirmed')
                    || $this->profileConfirmed($userId);
                $checks[self::CHECK_PLEDGED]       = $this->hasCurrentPledge($userId, $personId, $parishId);
                $checks[self::CHECK_GOOD_STANDING] = $this->isInGoodStanding($userId, $personId, $parishId);
            }
        } else {
            $checks[self::CHECK_BAPTIZED]      = null;
            $checks[self::CHECK_CONFIRMED]     = null;
            $checks[self::CHECK_PLEDGED]       = null;
            $checks[self::CHECK_GOOD_STANDING] = null;
        }

        $failed = array_keys(array_filter($checks, static fn ($value) => $value === false));

        return [
            'eligible' => $failed === [],
            'checks'   => $checks,
            'failed'   => $failed,
        ];
    }

    /**
     * Localized message listing the failed eligibility checks.
     *
     * @param array{failed?: array<int, string>} $eligibility
     */
    public function eligibilityMessage(array $eligibility): string
    {
        $labels = [
            self::CHECK_PORTAL_ACCESS => lang('Leadership.reqPortalAccess'),
            self::CHECK_BAPTIZED      => lang('Leadership.reqBaptized'),
            self::CHECK_CONFIRMED     => lang('Leadership.reqConfirmed'),
            self::CHECK_PLEDGED       => lang('Leadership.reqPledged'),
            self::CHECK_GOOD_STANDING => lang('Leadership.reqGoodStanding'),
        ];

        $failedLabels = [];
        foreach (($eligibility['failed'] ?? []) as $check) {
            $failedLabels[] = $labels[$check] ?? $check;
        }

        if ($failedLabels === []) {
            return lang('Leadership.notGoodStanding');
        }

        return lang('Leadership.eligibilityMissing', [implode('; ', $failedLabels)]);
    }

    /**
     * Canonical person for a user: user_person (primary link first), then
     * profile.person_id as fallback.
     */
    public function resolvePersonIdForUser(int $userId): ?int
    {
        $db = db_connect();
        if ($userId <= 0) {
            return null;
        }

        if ($this->tableExists($db, 'user_person')) {
            $row = $db->table('user_person')
                ->select('person_id')
                ->where('user_id', $userId)
                ->orderBy('is_primary', 'DESC')
                ->orderBy('id', 'ASC')
                ->get()
                ->getRowArray();
            if ($row !== null && (int) ($row['person_id'] ?? 0) > 0) {
                return (int) $row['person_id'];
            }
        }

        if ($this->tableExists($db, 'profile') && $this->columnExistsSafe($db, 'profile', 'person_id')) {
            $row = $db->table('profile')
                ->select('person_id')
                ->where('user_id', $userId)
                ->where('person_id IS NOT NULL', null, false)
                ->orderBy('id', 'ASC')
                ->get()
                ->getRowArray();
            if ($row !== null && (int) ($row['person_id'] ?? 0) > 0) {
                return (int) $row['person_id'];
            }
        }

        return null;
    }

    /**
     * Parish context for eligibility checks: the appointment parish when
     * known, otherwise the person's first live affiliation parish (falling
     * back to the user's linked affiliation).
     */
    public function resolveEligibilityParishId(int $personId, ?int $preferredParishId, int $userId = 0): ?int
    {
        if ($preferredParishId !== null && $preferredParishId > 0) {
            return $preferredParishId;
        }

        $db = db_connect();
        if (! $this->tableExists($db, 'parish_affiliation')) {
            return null;
        }

        $builder = $db->table('parish_affiliation')
            ->select('parish_id')
            ->where('deleted_at', null)
            ->orderBy('id', 'ASC')
            ->limit(1);

        if ($personId > 0) {
            $builder->where('person_id', $personId);
        } elseif ($userId > 0) {
            $builder->where('linked_user_id', $userId);
        } else {
            return null;
        }

        $row = $builder->get()->getRowArray();

        return $row !== null ? (int) $row['parish_id'] : null;
    }

    /**
     * Baptism/confirmation: the sacramental_record row is canonical; the
     * derived person_membership_details flag is accepted as an admin
     * attestation when no record has been entered yet.
     */
    private function hasSacramentOrFlag(int $personId, ?int $parishId, string $sacramentType, string $flagColumn): bool
    {
        $db = db_connect();
        if ($personId <= 0) {
            return false;
        }

        if ($this->tableExists($db, 'sacramental_record')) {
            $builder = $db->table('sacramental_record')
                ->where('person_id', $personId)
                ->where("LOWER(sacrament_type) = '" . $sacramentType . "'", null, false);
            if ($parishId !== null && $parishId > 0) {
                $builder->where('parish_id', $parishId);
            }
            if ($builder->countAllResults() > 0) {
                return true;
            }
        }

        if ($this->tableExists($db, 'person_membership_details')) {
            $builder = $db->table('person_membership_details')
                ->where('person_id', $personId)
                ->where($flagColumn, 1);
            if ($parishId !== null && $parishId > 0) {
                $builder->where('parish_id', $parishId);
            }

            return $builder->countAllResults() > 0;
        }

        return false;
    }

    /**
     * profile.is_confirmed — the derived flag member_standing_summary reads.
     */
    private function profileConfirmed(int $userId): bool
    {
        $db = db_connect();
        if ($userId <= 0
            || ! $this->tableExists($db, 'profile')
            || ! $this->columnExistsSafe($db, 'profile', 'is_confirmed')) {
            return false;
        }

        return $db->table('profile')
            ->where('user_id', $userId)
            ->where('is_confirmed', 1)
            ->countAllResults() > 0;
    }

    /**
     * A non-cancelled annual_pledge for the current year, or the manual
     * has_pledge membership flag (parish-scoped when the parish is known).
     */
    private function hasCurrentPledge(int $userId, int $personId, ?int $parishId, ?int $year = null): bool
    {
        $db = db_connect();
        $year ??= (int) date('Y');

        if ($userId > 0 && $this->tableExists($db, 'annual_pledge')) {
            $hasPledge = $db->table('annual_pledge')
                ->where('user_id', $userId)
                ->where('pledge_year', $year)
                ->groupStart()
                    ->where('fulfillment_status !=', 'cancelled')
                    ->orWhere('fulfillment_status IS NULL', null, false)
                ->groupEnd()
                ->countAllResults() > 0;
            if ($hasPledge) {
                return true;
            }
        }

        if ($personId > 0 && $this->tableExists($db, 'person_membership_details')) {
            $builder = $db->table('person_membership_details')
                ->where('person_id', $personId)
                ->where('has_pledge', 1);
            if ($parishId !== null && $parishId > 0) {
                $builder->where('parish_id', $parishId);
            }

            return $builder->countAllResults() > 0;
        }

        return false;
    }

    private function columnExistsSafe(\CodeIgniter\Database\BaseConnection $db, string $table, string $column): bool
    {
        try {
            return in_array($column, $db->getFieldNames($table), true);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function tableExists(\CodeIgniter\Database\BaseConnection $db, string $table): bool
    {
        try {
            return $db->tableExists($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Insert a position. Returns the new ID or null on failure
     * (call errors() for details).
     */
    public function create(array $payload): ?int
    {
        if ($this->model->insert($payload) === false) {
            return null;
        }

        return (int) $this->model->getInsertID();
    }

    public function update(int $positionId, array $payload): bool
    {
        return (bool) $this->model->update($positionId, $payload);
    }

    public function delete(int $positionId): bool
    {
        return (bool) $this->model->delete($positionId);
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->model->errors() ?: [];
    }
}
