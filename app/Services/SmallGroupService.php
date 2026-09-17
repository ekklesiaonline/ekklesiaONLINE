<?php

namespace App\Services;

use App\Models\GroupMembershipModel;
use App\Models\SmallGroupModel;

/**
 * Small-group membership lifecycle.
 *
 * Membership is keyed by the canonical person_id. A person may belong to
 * multiple groups; one (group, person) pair is unique — rejoining reactivates
 * the same row. Every roster change recomputes the person's pathway stage.
 */
class SmallGroupService
{
    private SmallGroupModel $groups;
    private GroupMembershipModel $memberships;
    private PathwayStageService $pathway;

    public function __construct(
        ?SmallGroupModel $groups = null,
        ?GroupMembershipModel $memberships = null,
        ?PathwayStageService $pathway = null
    ) {
        $this->groups       = $groups ?? new SmallGroupModel();
        $this->memberships  = $memberships ?? new GroupMembershipModel();
        $this->pathway      = $pathway ?? new PathwayStageService();
    }

    /**
     * Group row scoped to a parish.
     */
    public function groupForParish(int $groupId, int $parishId): ?array
    {
        $row = $this->groups
            ->where('id', $groupId)
            ->where('parish_id', $parishId)
            ->first();

        return $row === null ? null : $row;
    }

    /**
     * Add a person to a group. Rejoining after leaving reactivates the row.
     * The person must be affiliated with the group's parish.
     *
     * @return array{ok: bool, id?: int, code?: string}
     */
    public function addMember(int $groupId, int $personId, string $role = GroupMembershipModel::ROLE_MEMBER): array
    {
        if (! in_array($role, GroupMembershipModel::roles(), true)) {
            return ['ok' => false, 'code' => 'invalid_role'];
        }

        $group = $this->groups->find($groupId);

        if ($group === null) {
            return ['ok' => false, 'code' => 'group_not_found'];
        }

        if (! $this->personInParish($personId, (int) $group['parish_id'])) {
            return ['ok' => false, 'code' => 'person_not_in_parish'];
        }

        $existing = $this->memberships->membershipFor($groupId, $personId);

        if ($existing !== null && ($existing['status'] ?? '') === 'active') {
            return ['ok' => false, 'code' => 'already_member', 'id' => (int) $existing['id']];
        }

        if ($this->isAtCapacity($groupId)) {
            return ['ok' => false, 'code' => 'at_capacity'];
        }

        $today = date('Y-m-d');

        if ($existing !== null) {
            $ok = $this->memberships->update((int) $existing['id'], [
                'status'    => 'active',
                'role'      => $role,
                'joined_on' => $today,
                'left_on'   => null,
            ]);
            $membershipId = (int) $existing['id'];
        } else {
            $membershipId = $this->memberships->insert([
                'small_group_id' => $groupId,
                'person_id'      => $personId,
                'role'           => $role,
                'status'         => 'active',
                'joined_on'      => $today,
            ]);
            $ok = $membershipId !== false;
            $membershipId = (int) $membershipId;
        }

        if (! $ok) {
            return ['ok' => false, 'code' => 'insert_failed'];
        }

        $this->recomputeForGroupPerson($groupId, $personId);

        return ['ok' => true, 'id' => $membershipId];
    }

    /**
     * Mark a membership inactive. The row is kept for history.
     *
     * @return array{ok: bool, code?: string}
     */
    public function removeMember(int $groupId, int $personId): array
    {
        $existing = $this->memberships->membershipFor($groupId, $personId);

        if ($existing === null || ($existing['status'] ?? '') !== 'active') {
            return ['ok' => false, 'code' => 'not_member'];
        }

        $ok = $this->memberships->update((int) $existing['id'], [
            'status'  => 'inactive',
            'left_on' => date('Y-m-d'),
        ]);

        if (! $ok) {
            return ['ok' => false, 'code' => 'update_failed'];
        }

        $this->recomputeForGroupPerson($groupId, $personId);

        return ['ok' => true];
    }

    /**
     * Change a member's role within the group.
     *
     * @return array{ok: bool, code?: string}
     */
    public function updateRole(int $groupId, int $personId, string $role): array
    {
        if (! in_array($role, GroupMembershipModel::roles(), true)) {
            return ['ok' => false, 'code' => 'invalid_role'];
        }

        $existing = $this->memberships->membershipFor($groupId, $personId);

        if ($existing === null || ($existing['status'] ?? '') !== 'active') {
            return ['ok' => false, 'code' => 'not_member'];
        }

        $ok = $this->memberships->update((int) $existing['id'], ['role' => $role]);

        return $ok ? ['ok' => true] : ['ok' => false, 'code' => 'update_failed'];
    }

    /**
     * Active member count for a group (distinct persons).
     */
    public function activeMemberCount(int $groupId): int
    {
        return (int) $this->memberships->db->table('group_membership')
            ->where('small_group_id', $groupId)
            ->where('status', 'active')
            ->countAllResults();
    }

    /**
     * Whether the group has reached its capacity (null capacity = unlimited).
     */
    public function isAtCapacity(int $groupId): bool
    {
        $group = $this->groups->find($groupId);
        $capacity = (int) ($group['capacity'] ?? 0);

        if ($capacity <= 0) {
            return false;
        }

        return $this->activeMemberCount($groupId) >= $capacity;
    }

    /**
     * Parish-affiliated people eligible for group placement, searched by name.
     * Covers non-portal people (unlike the profile-keyed member search) since
     * membership is keyed by person_id.
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchAssignablePeople(int $parishId, string $keyword, int $excludeGroupId = 0): array
    {
        $builder = $this->memberships->db->table('parish_affiliation pa')
            ->select('pa.person_id, pe.first_name, pe.last_name, pe.email, pa.membership_status', false)
            ->join('person pe', 'pe.id = pa.person_id')
            ->where('pa.parish_id', $parishId)
            ->where('pa.deleted_at IS NULL', null, false)
            ->whereIn('pa.membership_status', ['member', 'regular_attender', 'inactive'])
            ->groupBy('pa.person_id')
            ->orderBy('pe.last_name', 'ASC')
            ->orderBy('pe.first_name', 'ASC')
            ->limit(25);

        $keyword = trim($keyword);
        if ($keyword !== '') {
            $builder->groupStart()
                ->like('pe.first_name', $keyword)
                ->orLike('pe.last_name', $keyword)
                ->orLike('pe.email', $keyword)
                ->groupEnd();
        }

        $rows = $builder->get()->getResultArray();

        if ($excludeGroupId > 0 && $rows !== []) {
            $personIds = array_map(static fn (array $row): int => (int) $row['person_id'], $rows);
            $active = $this->memberships->db->table('group_membership')
                ->select('person_id')
                ->where('small_group_id', $excludeGroupId)
                ->where('status', 'active')
                ->whereIn('person_id', $personIds)
                ->get()
                ->getResultArray();
            $activeIds = array_map(static fn (array $row): int => (int) $row['person_id'], $active);

            foreach ($rows as &$row) {
                $row['already_member'] = in_array((int) $row['person_id'], $activeIds, true);
            }
            unset($row);
        }

        return $rows;
    }

    /**
     * Whether the person has a live affiliation row in the parish.
     */
    private function personInParish(int $personId, int $parishId): bool
    {
        if ($personId <= 0 || $parishId <= 0) {
            return false;
        }

        return $this->memberships->db->table('parish_affiliation')
            ->where('person_id', $personId)
            ->where('parish_id', $parishId)
            ->where('deleted_at IS NULL', null, false)
            ->countAllResults() > 0;
    }

    private function recomputeForGroupPerson(int $groupId, int $personId): void
    {
        $group = $this->groups->find($groupId);
        $parishId = (int) ($group['parish_id'] ?? 0);

        if ($parishId <= 0) {
            return;
        }

        try {
            $this->pathway->recompute($personId, $parishId);
        } catch (\Throwable $e) {
            log_message('error', 'PathwayStageService::recompute failed after group change. person={person} parish={parish} error={error}', [
                'person' => $personId,
                'parish' => $parishId,
                'error'  => $e->getMessage(),
            ]);
        }
    }
}
