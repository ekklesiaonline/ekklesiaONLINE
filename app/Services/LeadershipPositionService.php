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
