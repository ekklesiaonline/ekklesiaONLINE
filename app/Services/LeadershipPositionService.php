<?php

namespace App\Services;

use App\Models\LeadershipPositionModel;

/**
 * LeadershipPositionService
 *
 * Domain logic for leadership position CRUD: scope resolution, scope-based
 * manage checks, and persistence. HTTP concerns (request parsing, JSON
 * responses, auth/permission gates) stay in the controller.
 */
class LeadershipPositionService
{
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

        if ($this->tableExists($db, 'person_membership_details')) {
            $override = $db->table('person_membership_details')
                ->where('person_id', $personId)
                ->where('good_standing_override', 1);
            if ($parishId !== null && $parishId > 0) {
                $override->where('parish_id', $parishId);
            }
            if ($override->countAllResults() > 0) {
                return true;
            }
        }

        if ($userId > 0 && $this->tableExists($db, 'member_standing_summary')) {
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
