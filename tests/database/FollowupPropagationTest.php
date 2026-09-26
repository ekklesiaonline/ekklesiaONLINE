<?php

namespace Tests\Database;

use App\Services\FollowupCloneService;
use App\Services\FollowupPropagationService;
use App\Services\FollowupSequenceService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Database\CanonicalTestSchema;

/**
 * Clone lineage + upstream propagation: edits to global/diocesan
 * sequences, actions, and templates flow into parish clones unless the
 * parish customized its copy. Diocese clones act as intermediaries —
 * a global edit cascades global → diocesan clone → parish clones.
 *
 * @internal
 */
final class FollowupPropagationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        CanonicalTestSchema::ensure($this->db);
        foreach ([
            'followup_log',
            'followup_action',
            'followup_sequence',
            'followup_template',
            'parish_admin_primary',
            'users',
            'parish',
            'diocese',
            'province',
        ] as $table) {
            CanonicalTestSchema::reset($this->db, $table);
        }
    }

    // ------------------------------------------------------------------
    // Seed helpers
    // ------------------------------------------------------------------

    private function seedHierarchy(int $dioceseId = 10, int $provinceId = 5): void
    {
        $this->db->table('province')->insert([
            'id'         => $provinceId,
            'name'       => 'Test Province',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('diocese')->insert([
            'id'          => $dioceseId,
            'province_id' => $provinceId,
            'name'        => 'Test Diocese',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    private function seedParish(int $parishId, ?int $dioceseId = null): void
    {
        $this->db->table('parish')->insert([
            'id'         => $parishId,
            'name'       => 'Test Parish ' . $parishId,
            'diocese_id' => $dioceseId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Seed an upstream sequence with one email action referencing a template.
     *
     * @return array{sequence_id:int, action_id:int, template_id:int}
     */
    private function seedUpstream(array $scope, array $flags = []): array
    {
        $this->db->table('followup_sequence')->insert(array_merge([
            'name'       => 'Upstream Seq',
            'is_active'  => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $scope, $flags));
        $seqId = (int) $this->db->insertID();

        $this->db->table('followup_template')->insert(array_merge([
            'channel'    => 'email',
            'name'       => 'Upstream Tpl',
            'body_text'  => 'Hello {first_name}',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $scope));
        $tplId = (int) $this->db->insertID();

        $this->db->table('followup_action')->insert([
            'sequence_id' => $seqId,
            'week_number' => 1,
            'day_offset'  => 1,
            'channel'     => 'email',
            'template_id' => $tplId,
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
        $actionId = (int) $this->db->insertID();

        return ['sequence_id' => $seqId, 'action_id' => $actionId, 'template_id' => $tplId];
    }

    private function globalScope(): array
    {
        return ['parish_id' => null, 'diocese_id' => null];
    }

    // ------------------------------------------------------------------
    // Lineage on clone
    // ------------------------------------------------------------------

    public function testCloneStampsLineageColumns(): void
    {
        $this->seedParish(401);
        $src = $this->seedUpstream($this->globalScope(), ['is_global_default' => 1]);

        $newSeqId = (new FollowupCloneService())->cloneGlobalDefaultIntoParish(401);
        $this->assertNotNull($newSeqId);

        $seq = $this->db->table('followup_sequence')->where('id', $newSeqId)->get()->getRowArray();
        $this->assertSame($src['sequence_id'], (int) $seq['source_sequence_id']);

        $action = $this->db->table('followup_action')->where('sequence_id', $newSeqId)->get()->getRowArray();
        $this->assertSame($src['action_id'], (int) $action['source_action_id']);
        $this->assertSame(0, (int) $action['is_customized']);

        $tpl = $this->db->table('followup_template')->where('parish_id', 401)->get()->getRowArray();
        $this->assertSame($src['template_id'], (int) $tpl['source_template_id']);
    }

    // ------------------------------------------------------------------
    // Action propagation
    // ------------------------------------------------------------------

    public function testActionUpdatePropagatesToNonCustomizedClone(): void
    {
        $this->seedParish(401);
        $src = $this->seedUpstream($this->globalScope(), ['is_global_default' => 1]);
        (new FollowupCloneService())->cloneGlobalDefaultIntoParish(401);

        $cloneAction = $this->db->table('followup_action')
            ->where('source_action_id', $src['action_id'])->get()->getRowArray();

        (new FollowupPropagationService())->onActionUpdated([
            'id'           => $src['action_id'],
            'sequence_id'  => $src['sequence_id'],
            'week_number'  => 3,
            'day_offset'   => 14,
            'channel'      => 'email',
            'template_id'  => $src['template_id'],
            'instructions' => null,
        ]);

        $after = $this->db->table('followup_action')->where('id', $cloneAction['id'])->get()->getRowArray();
        $this->assertSame(3, (int) $after['week_number']);
        $this->assertSame(14, (int) $after['day_offset']);
        $this->assertSame($src['template_id'], (int) $this->db->table('followup_template')
            ->where('id', $after['template_id'])->get()->getRowArray()['source_template_id']);
    }

    public function testActionUpdateSkipsCustomizedClone(): void
    {
        $this->seedParish(401);
        $src = $this->seedUpstream($this->globalScope(), ['is_global_default' => 1]);
        (new FollowupCloneService())->cloneGlobalDefaultIntoParish(401);

        $this->db->table('followup_action')
            ->where('source_action_id', $src['action_id'])
            ->update(['is_customized' => 1, 'day_offset' => 99]);

        (new FollowupPropagationService())->onActionUpdated([
            'id'          => $src['action_id'],
            'sequence_id' => $src['sequence_id'],
            'week_number' => 3,
            'day_offset'  => 14,
            'channel'     => 'email',
            'template_id' => $src['template_id'],
        ]);

        $clone = $this->db->table('followup_action')
            ->where('source_action_id', $src['action_id'])->get()->getRowArray();
        $this->assertSame(99, (int) $clone['day_offset']);
    }

    public function testActionCreatedPropagatesToClones(): void
    {
        $this->seedParish(401);
        $src = $this->seedUpstream($this->globalScope(), ['is_global_default' => 1]);
        $cloneSeqId = (new FollowupCloneService())->cloneGlobalDefaultIntoParish(401);

        // New upstream action referencing a NEW upstream template → clone on demand.
        $this->db->table('followup_template')->insert([
            'parish_id' => null, 'channel' => 'text', 'name' => 'New SMS',
            'body_text' => 'Hi', 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $newTplId = (int) $this->db->insertID();

        $this->db->table('followup_action')->insert([
            'sequence_id' => $src['sequence_id'], 'week_number' => 2, 'day_offset' => 7,
            'channel' => 'text', 'template_id' => $newTplId, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $newActionId = (int) $this->db->insertID();

        $stats = (new FollowupPropagationService())->onActionCreated([
            'id'           => $newActionId,
            'sequence_id'  => $src['sequence_id'],
            'week_number'  => 2,
            'day_offset'   => 7,
            'channel'      => 'text',
            'template_id'  => $newTplId,
            'instructions' => null,
        ]);

        $this->assertSame(1, $stats['inserted']);

        $clone = $this->db->table('followup_action')
            ->where('sequence_id', $cloneSeqId)
            ->where('source_action_id', $newActionId)
            ->get()->getRowArray();

        $this->assertNotNull($clone);
        $this->assertSame(7, (int) $clone['day_offset']);

        // The referenced template was cloned on demand with lineage.
        $cloneTpl = $this->db->table('followup_template')->where('id', $clone['template_id'])->get()->getRowArray();
        $this->assertSame($newTplId, (int) $cloneTpl['source_template_id']);
    }

    public function testActionCreatedSkipsCustomizedCloneSequence(): void
    {
        $this->seedParish(401);
        $src = $this->seedUpstream($this->globalScope(), ['is_global_default' => 1]);
        $cloneSeqId = (new FollowupCloneService())->cloneGlobalDefaultIntoParish(401);

        // Parish customized its clone (e.g. added/removed a step locally).
        $this->db->table('followup_sequence')
            ->where('id', $cloneSeqId)
            ->update(['is_customized' => 1]);

        $this->db->table('followup_action')->insert([
            'sequence_id' => $src['sequence_id'], 'week_number' => 2, 'day_offset' => 7,
            'channel' => 'email', 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $newActionId = (int) $this->db->insertID();

        $stats = (new FollowupPropagationService())->onActionCreated([
            'id'          => $newActionId,
            'sequence_id' => $src['sequence_id'],
            'week_number' => 2,
            'day_offset'  => 7,
            'channel'     => 'email',
        ]);

        $this->assertSame(0, $stats['inserted']);
        $this->assertSame(1, $stats['skipped_customized']);

        $count = (int) $this->db->table('followup_action')
            ->where('sequence_id', $cloneSeqId)
            ->where('source_action_id', $newActionId)
            ->countAllResults();
        $this->assertSame(0, $count);
    }

    public function testActionDeleteRemovesCloneAndOrphansCustomized(): void
    {
        $this->seedParish(401);
        $this->seedParish(402);
        $src = $this->seedUpstream($this->globalScope(), ['is_global_default' => 1]);
        (new FollowupCloneService())->cloneGlobalDefaultIntoParish(401);
        (new FollowupCloneService())->cloneSequenceIntoParish($src['sequence_id'], 402);

        // Parish 402 customized its copy.
        $this->db->table('followup_action')
            ->where('source_action_id', $src['action_id'])
            ->whereIn('sequence_id', function ($q) {
                return $q->select('id')->from('followup_sequence')->where('parish_id', 402);
            })
            ->update(['is_customized' => 1]);

        $stats = (new FollowupPropagationService())->onActionDeleted($src['action_id']);

        $this->assertSame(1, $stats['deleted']);
        $this->assertSame(1, $stats['orphaned']);

        $remaining = $this->db->table('followup_action')
            ->join('followup_sequence', 'followup_sequence.id = followup_action.sequence_id')
            ->where('followup_sequence.parish_id', 402)
            ->get()->getRowArray();

        $this->assertNotNull($remaining);
        $this->assertNull($remaining['source_action_id']);
    }

    // ------------------------------------------------------------------
    // Template propagation
    // ------------------------------------------------------------------

    public function testTemplateUpdatePropagatesToNonCustomizedClone(): void
    {
        $this->seedParish(401);
        $src = $this->seedUpstream($this->globalScope(), ['is_global_default' => 1]);
        (new FollowupCloneService())->cloneGlobalDefaultIntoParish(401);

        (new FollowupPropagationService())->onTemplateUpdated([
            'id'        => $src['template_id'],
            'channel'   => 'email',
            'name'      => 'Renamed',
            'subject'   => 'New subject',
            'body_text' => 'Updated body',
            'body_html' => null,
            'variables' => '["first_name"]',
        ]);

        $clone = $this->db->table('followup_template')
            ->where('source_template_id', $src['template_id'])->get()->getRowArray();
        $this->assertSame('Updated body', $clone['body_text']);
        $this->assertSame('New subject', $clone['subject']);
    }

    public function testTemplateDeleteOrphansCustomizedAndDeletesPlain(): void
    {
        $this->seedParish(401);
        $this->seedParish(402);
        $src = $this->seedUpstream($this->globalScope(), ['is_global_default' => 1]);
        (new FollowupCloneService())->cloneGlobalDefaultIntoParish(401);
        (new FollowupCloneService())->cloneSequenceIntoParish($src['sequence_id'], 402);

        $this->db->table('followup_template')
            ->where('parish_id', 402)
            ->where('source_template_id', $src['template_id'])
            ->update(['is_customized' => 1]);

        $stats = (new FollowupPropagationService())->onTemplateDeleted($src['template_id']);

        $this->assertSame(1, $stats['deleted']);
        $this->assertSame(1, $stats['orphaned']);

        // The non-customized clone is gone; its referencing action was unlinked.
        $this->assertSame(0, (int) $this->db->table('followup_template')
            ->where('parish_id', 401)->countAllResults());
        $action401 = $this->db->table('followup_action')
            ->join('followup_sequence', 'followup_sequence.id = followup_action.sequence_id')
            ->where('followup_sequence.parish_id', 401)
            ->get()->getRowArray();
        $this->assertNull($action401['template_id']);

        // The customized clone survives, orphaned.
        $tpl402 = $this->db->table('followup_template')->where('parish_id', 402)->get()->getRowArray();
        $this->assertNull($tpl402['source_template_id']);
    }

    // ------------------------------------------------------------------
    // Sequence propagation
    // ------------------------------------------------------------------

    public function testSequenceUpdatePropagatesNameDescription(): void
    {
        $this->seedParish(401);
        $this->seedParish(402);
        $src = $this->seedUpstream($this->globalScope(), ['is_global_default' => 1]);
        (new FollowupCloneService())->cloneGlobalDefaultIntoParish(401);
        (new FollowupCloneService())->cloneSequenceIntoParish($src['sequence_id'], 402);

        $this->db->table('followup_sequence')
            ->where('parish_id', 402)
            ->update(['is_customized' => 1]);

        (new FollowupPropagationService())->onSequenceUpdated([
            'id'          => $src['sequence_id'],
            'name'        => 'Renamed Upstream',
            'description' => 'New description',
        ]);

        $c401 = $this->db->table('followup_sequence')->where('parish_id', 401)->get()->getRowArray();
        $c402 = $this->db->table('followup_sequence')->where('parish_id', 402)->get()->getRowArray();

        $this->assertSame('Renamed Upstream (default)', $c401['name']);
        $this->assertSame('New description', $c401['description']);
        $this->assertSame('Upstream Seq (default)', $c402['name']); // untouched
    }

    // ------------------------------------------------------------------
    // Cascade resolution
    // ------------------------------------------------------------------

    public function testCascadePrefersDioceseOverGlobal(): void
    {
        $this->seedHierarchy(10, 5);
        $this->seedParish(401, 10);

        $this->seedUpstream($this->globalScope(), ['is_global_default' => 1]);
        $diocese = $this->seedUpstream(['diocese_id' => 10], ['is_diocese_default' => 1]);

        $service = new FollowupSequenceService();
        $resolved = $service->resolveUpstreamDefaultForParish(401);
        $this->assertSame($diocese['sequence_id'], (int) $resolved['id']);
    }

    public function testCascadeFallsBackToGlobal(): void
    {
        $this->seedHierarchy(10, 5);
        $this->seedParish(401, 10);

        $global = $this->seedUpstream($this->globalScope(), ['is_global_default' => 1]);

        // No diocesan default → the global default applies.
        $service  = new FollowupSequenceService();
        $resolved = $service->resolveUpstreamDefaultForParish(401);
        $this->assertSame($global['sequence_id'], (int) $resolved['id']);

        // Parish outside any diocese also falls back to global.
        $this->seedParish(499, null);
        $resolved = $service->resolveUpstreamDefaultForParish(499);
        $this->assertSame($global['sequence_id'], (int) $resolved['id']);
    }

    public function testCloneUpstreamDefaultPicksDioceseDefault(): void
    {
        $this->seedHierarchy(10, 5);
        $this->seedParish(401, 10);

        $this->seedUpstream($this->globalScope(), ['is_global_default' => 1]);
        $diocese = $this->seedUpstream(['diocese_id' => 10], ['is_diocese_default' => 1]);

        $newSeqId = (new FollowupCloneService())->cloneUpstreamDefaultIntoParish(401);
        $this->assertNotNull($newSeqId);

        $seq = $this->db->table('followup_sequence')->where('id', $newSeqId)->get()->getRowArray();
        $this->assertSame($diocese['sequence_id'], (int) $seq['source_sequence_id']);
        $this->assertSame(401, (int) $seq['parish_id']);
    }

    public function testDioceseCloneOfGlobalKeepsLineage(): void
    {
        $this->seedHierarchy(10, 5);
        $src = $this->seedUpstream($this->globalScope(), ['is_global_default' => 1]);

        $newSeqId = (new FollowupCloneService())->cloneSequenceIntoDiocese($src['sequence_id'], 10);
        $this->assertNotNull($newSeqId);

        $seq = $this->db->table('followup_sequence')->where('id', $newSeqId)->get()->getRowArray();
        $this->assertSame(10, (int) $seq['diocese_id']);
        $this->assertNull($seq['parish_id']);
        $this->assertSame($src['sequence_id'], (int) $seq['source_sequence_id']);

        $tpl = $this->db->table('followup_template')->where('diocese_id', 10)->get()->getRowArray();
        $this->assertSame($src['template_id'], (int) $tpl['source_template_id']);
    }

    public function testGlobalActionUpdateCascadesThroughDioceseCloneToParish(): void
    {
        $this->seedHierarchy(10, 5);
        $this->seedParish(401, 10);
        $src = $this->seedUpstream($this->globalScope(), ['is_global_default' => 1]);

        $dioceseSeqId = (new FollowupCloneService())->cloneSequenceIntoDiocese($src['sequence_id'], 10);
        $parishSeqId  = (new FollowupCloneService())->cloneSequenceIntoParish($dioceseSeqId, 401);
        $this->assertNotNull($parishSeqId);

        (new FollowupPropagationService())->onActionUpdated([
            'id'           => $src['action_id'],
            'sequence_id'  => $src['sequence_id'],
            'week_number'  => 4,
            'day_offset'   => 21,
            'channel'      => 'email',
            'template_id'  => $src['template_id'],
            'instructions' => null,
        ]);

        $parishAction = $this->db->table('followup_action')
            ->where('sequence_id', $parishSeqId)->get()->getRowArray();
        $this->assertSame(4, (int) $parishAction['week_number']);
        $this->assertSame(21, (int) $parishAction['day_offset']);

        // The parish action points at the parish-scoped clone of the
        // diocesan clone template, not the global one.
        $parishTpl = $this->db->table('followup_template')
            ->where('id', $parishAction['template_id'])->get()->getRowArray();
        $this->assertSame(401, (int) $parishTpl['parish_id']);
    }
}
