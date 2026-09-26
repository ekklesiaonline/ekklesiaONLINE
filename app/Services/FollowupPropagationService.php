<?php

namespace App\Services;

use App\Models\FollowupActionModel;
use App\Models\FollowupSequenceModel;
use App\Models\FollowupTemplateModel;

/**
 * FollowupPropagationService
 *
 * Pushes edits made to upstream follow-up records (global or diocesan
 * sequences/templates/actions) into the downstream clones created from
 * them via FollowupCloneService — and recursively into their clones, so a
 * global edit flows global → diocesan clone → parish clones.
 *
 * Policy: a cloned row that has been edited locally is marked
 * is_customized = 1 and is never overwritten by propagation. Upstream
 * creates always propagate into non-customized clone sequences; upstream
 * deletes remove non-customized clones and orphan (rather than delete)
 * customized ones. assigned_to is parish-local and is never overwritten
 * by propagation.
 *
 * Deleting an upstream SEQUENCE does not propagate — the clone's
 * source_sequence_id is cleared by the FK and the clone becomes a
 * standalone sequence.
 */
class FollowupPropagationService
{
    private FollowupSequenceModel $sequenceModel;
    private FollowupActionModel   $actionModel;
    private FollowupTemplateModel $templateModel;
    private FollowupCloneService  $cloneService;

    /** Ids already processed this call — prevents recursive loops. */
    private array $visited = [];

    public function __construct(
        ?FollowupSequenceModel $sequenceModel = null,
        ?FollowupActionModel $actionModel = null,
        ?FollowupTemplateModel $templateModel = null,
        ?FollowupCloneService $cloneService = null
    ) {
        $this->sequenceModel = $sequenceModel ?? model(FollowupSequenceModel::class);
        $this->actionModel   = $actionModel   ?? model(FollowupActionModel::class);
        $this->templateModel = $templateModel ?? model(FollowupTemplateModel::class);
        $this->cloneService  = $cloneService  ?? new FollowupCloneService();
    }

    /**
     * Propagate upstream sequence metadata (name/description) into
     * non-customized clones. is_active/is_default stay scope-local.
     *
     * @return array{updated: int}
     */
    public function onSequenceUpdated(array $sequence): array
    {
        $id = (int) $sequence['id'];
        if (isset($this->visited['seq'][$id])) {
            return ['updated' => 0];
        }
        $this->visited['seq'][$id] = true;

        // Strip the clone suffix so deeper clones get the base name back.
        $baseName = (string) preg_replace('/ \(default\)$/', '', (string) $sequence['name']);

        $clones = $this->sequenceModel
            ->where('source_sequence_id', $id)
            ->where('is_customized', 0)
            ->findAll();

        $updated = 0;
        foreach ($clones as $clone) {
            $this->sequenceModel->update((int) $clone['id'], [
                'name'        => $baseName . ' (default)',
                'description' => $sequence['description'],
            ]);
            $updated++;

            $this->onSequenceUpdated([
                'id'          => (int) $clone['id'],
                'name'        => $clone['name'],
                'description' => $sequence['description'],
            ]);
        }

        return ['updated' => $updated];
    }

    /**
     * Insert a clone of a newly-created upstream action into every
     * non-customized sequence cloned from the action's sequence, then
     * recurse so deeper clones receive it too.
     *
     * @return array{inserted: int, skipped_customized: int}
     */
    public function onActionCreated(array $action): array
    {
        $id = (int) $action['id'];
        if (isset($this->visited['act'][$id])) {
            return ['inserted' => 0, 'skipped_customized' => 0];
        }
        $this->visited['act'][$id] = true;

        $cloneSequences = $this->sequenceModel
            ->where('source_sequence_id', (int) $action['sequence_id'])
            ->findAll();

        $inserted = 0;
        $skipped  = 0;
        $now      = date('Y-m-d H:i:s');
        $isHuman  = in_array($action['channel'], ['call', 'manual'], true);

        foreach ($cloneSequences as $cloneSeq) {
            // A customized clone opted out of upstream composition changes.
            if (!empty($cloneSeq['is_customized'])) {
                $skipped++;
                continue;
            }

            $parishId   = (int) ($cloneSeq['parish_id'] ?? 0);
            $assignedTo = $isHuman && $parishId > 0
                ? $this->cloneService->resolvePrimaryAdminId($parishId)
                : null;

            $newId = $this->actionModel->insert([
                'sequence_id'      => (int) $cloneSeq['id'],
                'week_number'      => (int) $action['week_number'],
                'day_offset'       => (int) $action['day_offset'],
                'channel'          => $action['channel'],
                'template_id'      => $this->resolveCloneTemplateId($action, $cloneSeq),
                'assigned_to'      => $assignedTo,
                'instructions'     => $action['instructions'] ?? null,
                'source_action_id' => $id,
                'is_customized'    => 0,
                'created_at'       => $now,
                'updated_at'       => $now,
            ], true);
            $inserted++;

            // Cascade: this clone action is itself the upstream of any
            // deeper clones (e.g. global → diocese clone → parish clone).
            if ($newId) {
                $this->onActionCreated(array_merge($action, [
                    'id'          => (int) $newId,
                    'sequence_id' => (int) $cloneSeq['id'],
                    'template_id' => $this->cloneTemplateSourceId($action, $cloneSeq),
                ]));
            }
        }

        return ['inserted' => $inserted, 'skipped_customized' => $skipped];
    }

    /**
     * Push an upstream action's field changes into its non-customized
     * clones, recursively cascading to deeper clones.
     *
     * @return array{updated: int, skipped_customized: int}
     */
    public function onActionUpdated(array $action): array
    {
        $id = (int) $action['id'];
        if (isset($this->visited['act'][$id])) {
            return ['updated' => 0, 'skipped_customized' => 0];
        }
        $this->visited['act'][$id] = true;

        $clones = $this->actionModel
            ->where('source_action_id', $id)
            ->findAll();

        $updated = 0;
        $skipped = 0;

        foreach ($clones as $clone) {
            if (!empty($clone['is_customized'])) {
                $skipped++;
                continue;
            }

            $cloneSeq = $this->sequenceModel->find((int) $clone['sequence_id']);
            if (!is_array($cloneSeq)) {
                continue;
            }

            $newTemplateId = $this->resolveCloneTemplateId($action, $cloneSeq);

            $this->actionModel->update((int) $clone['id'], [
                'week_number'  => (int) $action['week_number'],
                'day_offset'   => (int) $action['day_offset'],
                'channel'      => $action['channel'],
                'template_id'  => $newTemplateId,
                'instructions' => $action['instructions'] ?? null,
            ]);
            $updated++;

            $this->onActionUpdated([
                'id'           => (int) $clone['id'],
                'sequence_id'  => (int) $clone['sequence_id'],
                'week_number'  => (int) $action['week_number'],
                'day_offset'   => (int) $action['day_offset'],
                'channel'      => $action['channel'],
                'template_id'  => $newTemplateId !== null
                    ? $this->cloneTemplateSourceId($action, $cloneSeq)
                    : null,
                'instructions' => $action['instructions'] ?? null,
            ]);
        }

        return ['updated' => $updated, 'skipped_customized' => $skipped];
    }

    /**
     * Remove clone actions of a deleted upstream action, recursively.
     * Customized clones survive and are orphaned (source_action_id
     * cleared); their own clones keep syncing from the surviving row.
     *
     * @return array{deleted: int, orphaned: int}
     */
    public function onActionDeleted(int $actionId): array
    {
        if (isset($this->visited['act'][$actionId])) {
            return ['deleted' => 0, 'orphaned' => 0];
        }
        $this->visited['act'][$actionId] = true;

        $clones = $this->actionModel
            ->where('source_action_id', $actionId)
            ->findAll();

        $deleted  = 0;
        $orphaned = 0;

        foreach ($clones as $clone) {
            $cloneId = (int) $clone['id'];
            if (!empty($clone['is_customized'])) {
                $this->actionModel->update($cloneId, ['source_action_id' => null]);
                $orphaned++;
            } else {
                $this->actionModel->delete($cloneId);
                $deleted++;
                $this->onActionDeleted($cloneId);
            }
        }

        return ['deleted' => $deleted, 'orphaned' => $orphaned];
    }

    /**
     * Push an upstream template's content into its non-customized clones,
     * recursively cascading to deeper clones.
     *
     * @return array{updated: int, skipped_customized: int}
     */
    public function onTemplateUpdated(array $template): array
    {
        $id = (int) $template['id'];
        if (isset($this->visited['tpl'][$id])) {
            return ['updated' => 0, 'skipped_customized' => 0];
        }
        $this->visited['tpl'][$id] = true;

        $baseName = (string) preg_replace('/ \(default\)$/', '', (string) $template['name']);

        $clones = $this->templateModel
            ->where('source_template_id', $id)
            ->findAll();

        $updated = 0;
        $skipped = 0;

        foreach ($clones as $clone) {
            if (!empty($clone['is_customized'])) {
                $skipped++;
                continue;
            }

            $this->templateModel->update((int) $clone['id'], [
                'channel'   => $template['channel'],
                'name'      => $baseName . ' (default)',
                'subject'   => $template['subject'] ?? null,
                'body_text' => $template['body_text'],
                'body_html' => $template['body_html'] ?? null,
                'variables' => $template['variables'] ?? null,
            ]);
            $updated++;

            $this->onTemplateUpdated(array_merge($template, [
                'id'   => (int) $clone['id'],
                'name' => $clone['name'],
            ]));
        }

        return ['updated' => $updated, 'skipped_customized' => $skipped];
    }

    /**
     * Remove clone templates of a deleted upstream template, recursively.
     * Customized clones are orphaned rather than deleted; referencing
     * clone actions are unlinked first so the FK cascade does not delete
     * downstream steps.
     *
     * @return array{deleted: int, orphaned: int}
     */
    public function onTemplateDeleted(int $templateId): array
    {
        if (isset($this->visited['tpl'][$templateId])) {
            return ['deleted' => 0, 'orphaned' => 0];
        }
        $this->visited['tpl'][$templateId] = true;

        $clones = $this->templateModel
            ->where('source_template_id', $templateId)
            ->findAll();

        $deleted  = 0;
        $orphaned = 0;

        foreach ($clones as $clone) {
            $cloneId = (int) $clone['id'];

            if (!empty($clone['is_customized'])) {
                $this->templateModel->update($cloneId, ['source_template_id' => null]);
                $orphaned++;
                continue;
            }

            // Keep downstream steps intact: unlink actions that still
            // reference the clone template before deleting it (FK is
            // ON DELETE CASCADE).
            $this->actionModel
                ->where('template_id', $cloneId)
                ->set(['template_id' => null])
                ->update();

            $this->templateModel->delete($cloneId);
            $deleted++;
            $this->onTemplateDeleted($cloneId);
        }

        return ['deleted' => $deleted, 'orphaned' => $orphaned];
    }

    /**
     * Map an upstream action's template_id onto the clone sequence's own
     * scoped copy of that template, cloning it on demand when missing.
     */
    private function resolveCloneTemplateId(array $action, array $cloneSeq): ?int
    {
        $sourceTemplateId = (int) ($action['template_id'] ?? 0);
        if ($sourceTemplateId <= 0) {
            return null;
        }

        $clone = $this->templateModel
            ->where('source_template_id', $sourceTemplateId)
            ->groupStart()
                ->where('parish_id', (int) ($cloneSeq['parish_id'] ?? 0))
                ->orWhere('diocese_id', (int) ($cloneSeq['diocese_id'] ?? 0))
            ->groupEnd()
            ->first();

        if (is_array($clone)) {
            return (int) $clone['id'];
        }

        return $this->cloneService->cloneTemplateIntoScope($sourceTemplateId, [
            'parish_id'  => (int) ($cloneSeq['parish_id'] ?? 0) ?: null,
            'diocese_id' => (int) ($cloneSeq['diocese_id'] ?? 0) ?: null,
        ]);
    }

    /**
     * The clone template id resolved for a scope doubles as the source id
     * deeper clones should point at when recursing.
     */
    private function cloneTemplateSourceId(array $action, array $cloneSeq): ?int
    {
        return $this->resolveCloneTemplateId($action, $cloneSeq);
    }
}
