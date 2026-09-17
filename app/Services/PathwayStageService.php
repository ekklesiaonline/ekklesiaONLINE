<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Maintains parish_affiliation.pathway_stage — the denormalized 1-3 stage of
 * the membership pathway:
 *
 *   1  The Anglican Way      — covenant member (affiliation membership_status
 *                              = 'member'; inactive members keep their stage)
 *   2  Rooted in Community   — placed in an active parish small group
 *   3  Sent to Serve         — placed in a ministry team (team_member)
 *
 * The stage is derived from facts, never set directly: this service is called
 * after membership transitions, group roster changes, and team assignments.
 * Terminal statuses (transferred, deceased) clear the stage — the affiliation
 * has ended and person_status_history preserves the story.
 */
class PathwayStageService
{
    public const STAGE_NONE      = null;
    public const STAGE_ANGLICAN  = 1;
    public const STAGE_COMMUNITY = 2;
    public const STAGE_SENT      = 3;

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * Derive the current pathway stage for a person in a parish.
     */
    public function stageFor(int $personId, int $parishId): ?int
    {
        if ($personId <= 0 || $parishId <= 0) {
            return null;
        }

        $affiliation = $this->db->table('parish_affiliation')
            ->select('membership_status')
            ->where('person_id', $personId)
            ->where('parish_id', $parishId)
            ->get()
            ->getRowArray();

        if ($affiliation === null) {
            return null;
        }

        $status = (string) ($affiliation['membership_status'] ?? '');

        // Terminal / pre-member statuses are not on the pathway.
        if (! in_array($status, ['member', 'inactive'], true)) {
            return null;
        }

        if ($this->hasTeamPlacement($personId, $parishId)) {
            return self::STAGE_SENT;
        }

        if ($this->hasGroupPlacement($personId, $parishId)) {
            return self::STAGE_COMMUNITY;
        }

        return self::STAGE_ANGLICAN;
    }

    /**
     * Recompute and persist pathway_stage on the affiliation row.
     *
     * @return array{ok: bool, stage: ?int, previous: ?int, changed: bool}
     */
    public function recompute(int $personId, int $parishId): array
    {
        $affiliation = $this->db->table('parish_affiliation')
            ->select('id, pathway_stage')
            ->where('person_id', $personId)
            ->where('parish_id', $parishId)
            ->get()
            ->getRowArray();

        if ($affiliation === null) {
            return ['ok' => false, 'stage' => null, 'previous' => null, 'changed' => false];
        }

        $previous = isset($affiliation['pathway_stage']) ? (int) $affiliation['pathway_stage'] : null;
        $stage = $this->stageFor($personId, $parishId);

        if ($stage === $previous) {
            return ['ok' => true, 'stage' => $stage, 'previous' => $previous, 'changed' => false];
        }

        $updated = $this->db->table('parish_affiliation')
            ->where('id', (int) $affiliation['id'])
            ->update(['pathway_stage' => $stage, 'updated_at' => date('Y-m-d H:i:s')]);

        return ['ok' => $updated !== false, 'stage' => $stage, 'previous' => $previous, 'changed' => $updated !== false];
    }

    /**
     * Recompute every affiliation in a parish — used after bulk roster edits
     * or a standing refresh.
     */
    public function recomputeParish(int $parishId): int
    {
        $affiliations = $this->db->table('parish_affiliation')
            ->select('person_id')
            ->where('parish_id', $parishId)
            ->get()
            ->getResultArray();

        $changed = 0;
        foreach ($affiliations as $row) {
            $result = $this->recompute((int) $row['person_id'], $parishId);
            if ($result['changed']) {
                $changed++;
            }
        }

        return $changed;
    }

    /**
     * Display metadata for a stage value.
     *
     * @return array{labelKey: string, badge: string}|null
     */
    public static function stageMeta(?int $stage): ?array
    {
        return match ($stage) {
            1 => ['labelKey' => 'Groups.stage1', 'badge' => 'info'],
            2 => ['labelKey' => 'Groups.stage2', 'badge' => 'primary'],
            3 => ['labelKey' => 'Groups.stage3', 'badge' => 'success'],
            default => null,
        };
    }

    private function hasGroupPlacement(int $personId, int $parishId): bool
    {
        if (! $this->db->tableExists('group_membership') || ! $this->db->tableExists('small_group')) {
            return false;
        }

        return $this->db->table('group_membership gm')
            ->join('small_group sg', 'sg.id = gm.small_group_id')
            ->where('gm.person_id', $personId)
            ->where('gm.status', 'active')
            ->where('sg.parish_id', $parishId)
            ->where('sg.deleted_at IS NULL', null, false)
            ->countAllResults() > 0;
    }

    private function hasTeamPlacement(int $personId, int $parishId): bool
    {
        if (! $this->db->tableExists('team_member') || ! $this->db->tableExists('team')) {
            return false;
        }

        // Rows inserted before person_id was populated resolve via user_person.
        // NB: this must run BEFORE the builder below — an interleaved get() on
        // the shared connection clobbers the alias registry and the outer
        // wheres get prefixed (db_vt.parish_id).
        $linkedUsers = $this->linkedUserIds($personId);

        $builder = $this->db->table('team_member tm')
            ->join('team vt', 'vt.id = tm.team_id')
            ->where('vt.parish_id', $parishId)
            ->groupStart()
                ->where('tm.person_id', $personId);

        if ($linkedUsers !== []) {
            $builder->orWhereIn('tm.user_id', $linkedUsers);
        }

        return $builder->groupEnd()->countAllResults() > 0;
    }

    /**
     * @return array<int, int>
     */
    private function linkedUserIds(int $personId): array
    {
        if (! $this->db->tableExists('user_person')) {
            return [];
        }

        $rows = $this->db->table('user_person')
            ->select('user_id')
            ->where('person_id', $personId)
            ->get()
            ->getResultArray();

        return array_values(array_filter(array_map(
            static fn (array $row): int => (int) ($row['user_id'] ?? 0),
            $rows
        )));
    }
}
