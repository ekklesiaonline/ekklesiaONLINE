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
    public const CHECK_VERIFIED_ORDER = 'verified_order';

    private const CLERGY_OFFICES = [
        'diocesan_bishop' => ['diocese', 'bishop'],
        'coadjutor_bishop' => ['diocese', 'bishop'],
        'suffragan_bishop' => ['diocese', 'bishop'],
        'assisting_bishop' => ['diocese', 'bishop'],
        'rector' => ['parish', 'priest'],
        'priest_in_charge' => ['parish', 'priest'],
        'interim_rector' => ['parish', 'priest'],
        'vicar' => ['parish', 'priest'],
        'cathedral_dean' => ['parish', 'priest'],
        'curate' => ['parish', 'priest'],
        'associate_priest' => ['parish', 'priest'],
        'parish_deacon' => ['parish', 'deacon'],
        'regional_dean' => ['deanery', 'priest'],
        'deanery_dean' => ['deanery', 'priest'],
        'archdeacon' => ['diocese', 'deacon'],
    ];

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
            'role_code'       => 'permit_empty|alpha_dash|max_length[100]',
            'pastoral_in_charge' => 'permit_empty|in_list[0,1]',
        ];

        if ($includeScopeType) {
            $rules['scope_type'] = 'required|in_list[parish,deanery,diocese,province]';
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
     * @return array{parish_id: ?int, deanery_id: ?int, diocese_id: ?int, province_id: ?int}|null
     */
    public function resolveScopeIds(string $scopeType, int $parishId, ?int $dioceseScopeId, ?int $provinceScopeId, ?int $deaneryId = null): ?array
    {
        $result = ['parish_id' => null, 'deanery_id' => null, 'diocese_id' => null, 'province_id' => null];

        switch ($scopeType) {
            case 'parish':
                if ($parishId <= 0) {
                    return null;
                }
                $result['parish_id'] = $parishId;
                return $result;
            case 'deanery':
                if ($deaneryId === null || $deaneryId <= 0 || $dioceseScopeId === null
                    || ! (new HierarchyService())->deaneryInDiocese($deaneryId, $dioceseScopeId)) {
                    return null;
                }
                $result['deanery_id'] = $deaneryId;
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
        $actor = auth()->user();
        if ($actor === null || ! $actor->can('leadership_position.manage')) {
            return false;
        }
        $scopeType = (string) ($position['scope_type'] ?? '');
        $scopeId = (int) ($position[$scopeType . '_id'] ?? 0);
        if ($scopeId <= 0) {
            return false;
        }
        if ($actor->inGroup('dioceseadmin') && $dioceseScopeId !== null && $dioceseScopeId > 0) {
            $hierarchy = new HierarchyService();
            return match ($scopeType) {
                'parish' => $hierarchy->parishInDiocese($scopeId, $dioceseScopeId),
                'deanery' => $hierarchy->deaneryInDiocese($scopeId, $dioceseScopeId),
                'diocese' => $scopeId === $dioceseScopeId,
                default => false,
            };
        }
        return $actor->inGroup('parishadmin') && $scopeType === 'parish'
            && $parishId > 0 && $scopeId === $parishId;
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
            $checks[self::CHECK_VERIFIED_ORDER] = $this->officeEvidenceValid($userId, $personId, $payload);
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
            self::CHECK_VERIFIED_ORDER => lang('Leadership.reqVerifiedOrder'),
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

    /** Validate the full merged appointment, including scope exclusive-or. */
    public function validPositionPayload(array $payload): bool
    {
        $scope = (string) ($payload['scope_type'] ?? '');
        if (! in_array($scope, ['parish', 'deanery', 'diocese', 'province'], true)) {
            return false;
        }
        $count = 0;
        foreach (['parish', 'deanery', 'diocese', 'province'] as $candidate) {
            $id = (int) ($payload[$candidate . '_id'] ?? 0);
            if ($id > 0) {
                $count++;
                if ($candidate !== $scope) {
                    return false;
                }
            }
        }
        if ($count !== 1 || (int) ($payload[$scope . '_id'] ?? 0) <= 0) {
            return false;
        }
        $start = $payload['started_at'] ?? null;
        $end = $payload['ended_at'] ?? null;
        if ($start !== null && $end !== null && $start > $end) {
            return false;
        }
        $category = (string) ($payload['role_category'] ?? '');
        if (! empty($payload['pastoral_in_charge'])
            && ($category !== 'clergy' || (string) ($payload['role_code'] ?? '') !== 'cathedral_dean')) {
            return false;
        }
        if ($category === 'clergy') {
            return $this->officeEvidenceValid((int) ($payload['user_id'] ?? 0),
                (int) ($payload['person_id'] ?? 0), $payload);
        }
        // A lay or unspecified category cannot claim an ordained order/office.
        return ! isset(self::CLERGY_OFFICES[(string) ($payload['role_code'] ?? '')])
            && trim((string) ($payload['clergy_order'] ?? '')) === '';
    }

    /**
     * Required ordained order for a clergy office code, or null when the code
     * is not a clergy office.
     */
    public function requiredOrderForOffice(string $roleCode): ?string
    {
        return self::CLERGY_OFFICES[$roleCode][1] ?? null;
    }

    public function officeEvidenceValid(int $userId, int $personId, array $payload): bool
    {
        $code = (string) ($payload['role_code'] ?? '');
        $office = self::CLERGY_OFFICES[$code] ?? null;
        $scope = (string) ($payload['scope_type'] ?? '');
        if ($userId <= 0 || $personId <= 0 || $office === null || $scope !== $office[0]
            || (string) ($payload['role_category'] ?? '') !== 'clergy'
            || (string) ($payload['clergy_order'] ?? '') !== $office[1]
            || $this->resolvePersonIdForUser($userId) !== $personId) {
            return false;
        }
        $db = db_connect();
        if (! $this->tableExists($db, 'clergy_ordination')
            || ! $this->tableExists($db, 'clergy_canonical_status')
            || $db->table('clergy_ordination')->where('person_id', $personId)
                ->where('order_code', $office[1])->where('verification_status', 'verified')
                ->where('verified_at IS NOT NULL', null, false)->countAllResults() === 0) {
            return false;
        }
        $scopeId = (int) ($payload[$scope . '_id'] ?? 0);
        if ($scopeId <= 0) {
            return false;
        }
        $dioceseId = $scope === 'diocese' ? $scopeId : (int) (($db->table($scope)
            ->select('diocese_id')->where('id', $scopeId)->get()->getRowArray())['diocese_id'] ?? 0);
        if ($dioceseId <= 0 || $db->table('diocese')->where('id', $dioceseId)->countAllResults() === 0) {
            return false;
        }
        if ($scope === 'deanery' && ! (new HierarchyService($db))->deaneryInDiocese($scopeId, $dioceseId)) {
            return false;
        }
        if ($code === 'cathedral_dean' && ($db->table('parish')->select('congregation_type')
            ->where('id', $scopeId)->get()->getRowArray()['congregation_type'] ?? '') !== 'cathedral') {
            return false;
        }
        $today = date('Y-m-d');
        $standing = $db->table('clergy_canonical_status')
            ->select('status_code')->where('person_id', $personId)->where('diocese_id', $dioceseId)
            ->where('effective_from <=', $today)
            ->groupStart()->where('effective_to', null)->orWhere('effective_to >=', $today)->groupEnd()
            ->get()->getResultArray();
        $licensed = false;
        foreach ($standing as $row) {
            if (in_array($row['status_code'], ['suspended', 'inhibited', 'transferred', 'retired'], true)) {
                return false;
            }
            $licensed = $licensed || in_array($row['status_code'], ['resident', 'licensed'], true);
        }
        return $licensed;
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

        $linkedPersonId = null;
        if ($this->tableExists($db, 'user_person')) {
            $rows = $db->table('user_person')
                ->select('person_id, is_primary')
                ->where('user_id', $userId)
                ->orderBy('is_primary', 'DESC')
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();
            $primary = array_values(array_filter($rows, static fn (array $r): bool => (int) $r['is_primary'] === 1));
            if (count($primary) > 1 || (count($primary) === 0 && count($rows) > 1)) {
                return null;
            }
            $linkedPersonId = (int) ($primary[0]['person_id'] ?? $rows[0]['person_id'] ?? 0);
        }

        if ($this->tableExists($db, 'profile') && $this->columnExistsSafe($db, 'profile', 'person_id')) {
            $rows = $db->table('profile')
                ->select('person_id')
                ->where('user_id', $userId)
                ->where('person_id IS NOT NULL', null, false)
                ->get()
                ->getResultArray();
            $profileIds = array_unique(array_map(static fn (array $r): int => (int) $r['person_id'], $rows));
            if (count($profileIds) > 1 || ($profileIds !== [] && $linkedPersonId > 0 && $profileIds[0] !== $linkedPersonId)) {
                return null;
            }
            $linkedPersonId = $linkedPersonId ?: ($profileIds[0] ?? 0);
        }

        return $linkedPersonId > 0 ? $linkedPersonId : null;
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
        unset($payload['approved_by_user_id'], $payload['approved_at'],
            $payload['approval_evidence_reference'], $payload['revoked_at']);
        if (! $this->validPositionPayload($payload)) {
            return null;
        }
        if (! db_connect()->fieldExists('deanery_id', 'leadership_position')) {
            unset($payload['deanery_id']);
        }
        if (! db_connect()->fieldExists('pastoral_in_charge', 'leadership_position')) {
            unset($payload['pastoral_in_charge']);
        }
        if ($this->model->insert($payload) === false) {
            return null;
        }

        return (int) $this->model->getInsertID();
    }

    public function update(int $positionId, array $payload): bool
    {
        $existing = $this->find($positionId);
        if ($existing === null) {
            return false;
        }
        unset($payload['user_id'], $payload['person_id'], $payload['scope_type'],
            $payload['parish_id'], $payload['deanery_id'], $payload['diocese_id'], $payload['province_id'],
            $payload['approved_by_user_id'], $payload['approved_at'],
            $payload['approval_evidence_reference'], $payload['revoked_at']);
        if (! $this->validPositionPayload(array_merge($existing, $payload))) {
            return false;
        }
        $material = false;
        foreach (['role_code', 'role_category', 'clergy_order', 'pastoral_in_charge', 'active', 'started_at', 'ended_at'] as $field) {
            $material = $material || (string) ($payload[$field] ?? $existing[$field] ?? '') !== (string) ($existing[$field] ?? '');
        }
        $db = db_connect();
        $db->transBegin();
        try {
            if ($material && ! empty($existing['approved_at'])) {
                $payload['approved_by_user_id'] = null;
                $payload['approved_at'] = null;
                $payload['revoked_at'] = date('Y-m-d H:i:s');
                if ($db->tableExists('clergy_access_grant')) {
                    $db->table('clergy_access_grant')->where('source_position_id', $positionId)
                        ->where('revoked_at', null)->update(['revoked_at' => date('Y-m-d H:i:s')]);
                }
            }
            $ok = (bool) $this->model->update($positionId, $payload);
            if (! $ok || $db->transStatus() === false) {
                $db->transRollback();
                return false;
            }
            $db->transCommit();
            return true;
        } catch (\Throwable $e) {
            $db->transRollback();
            return false;
        }
    }

    public function delete(int $positionId): bool
    {
        $position = $this->find($positionId);
        if ($position === null) {
            return false;
        }
        $db = db_connect();
        if (! empty($position['approved_at']) || ($db->tableExists('clergy_access_grant')
            && $db->table('clergy_access_grant')->where('source_position_id', $positionId)->countAllResults() > 0)) {
            // Preserve authorization provenance and history: never hard-delete a signed office.
            $db->transBegin();
            $now = date('Y-m-d H:i:s');
            $ok = (bool) $this->model->update($positionId, [
                'active' => 0, 'ended_at' => date('Y-m-d'), 'revoked_at' => $now,
            ]);
            $db->table('clergy_access_grant')->where('source_position_id', $positionId)
                ->where('revoked_at', null)->update(['revoked_at' => $now]);
            if (! $ok || $db->transStatus() === false) {
                $db->transRollback();
                return false;
            }
            $db->transCommit();
            return true;
        }
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
