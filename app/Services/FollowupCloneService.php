<?php

namespace App\Services;

use App\Models\FollowupActionModel;
use App\Models\FollowupSequenceModel;
use App\Models\FollowupTemplateModel;

class FollowupCloneService
{
    private FollowupSequenceService $sequenceService;
    private FollowupSequenceModel   $sequenceModel;
    private FollowupActionModel     $actionModel;
    private FollowupTemplateModel   $templateModel;

    public function __construct(
        ?FollowupSequenceService $sequenceService = null,
        ?FollowupSequenceModel $sequenceModel = null,
        ?FollowupActionModel $actionModel = null,
        ?FollowupTemplateModel $templateModel = null
    ) {
        $this->sequenceService = $sequenceService ?? new FollowupSequenceService();
        $this->sequenceModel   = $sequenceModel   ?? model(FollowupSequenceModel::class);
        $this->actionModel     = $actionModel     ?? model(FollowupActionModel::class);
        $this->templateModel   = $templateModel   ?? model(FollowupTemplateModel::class);
    }

    /**
     * Clone the best upstream default sequence into a parish, resolving the
     * cascade diocesan default → global default. The clone always becomes
     * the parish default. Returns the new parish sequence ID, or null when
     * no upstream default exists.
     */
    public function cloneUpstreamDefaultIntoParish(int $parishId): ?int
    {
        $upstream = $this->sequenceService->resolveUpstreamDefaultForParish($parishId);

        if (!$upstream) {
            log_message('warning', "No upstream default follow-up sequence found; parish {$parishId} will have no default sequence.");
            return null;
        }

        return $this->cloneSequenceIntoScope((int) $upstream['id'], ['parish_id' => $parishId], true);
    }

    /**
     * Clone the global default sequence into a parish. Kept for callers that
     * specifically want the global template; most callers should use
     * cloneUpstreamDefaultIntoParish() so diocesan defaults win.
     */
    public function cloneGlobalDefaultIntoParish(int $parishId): ?int
    {
        $globalSequence = $this->sequenceService->getGlobalDefaultTemplateSequence();
        if (!$globalSequence) {
            log_message('warning', "No global default follow-up sequence found; parish {$parishId} will have no default sequence.");
            return null;
        }

        return $this->cloneSequenceIntoScope((int) $globalSequence['id'], ['parish_id' => $parishId], true);
    }

    /**
     * Clone an upstream (global or diocesan) sequence into a parish. The
     * clone becomes the parish default only when the parish has none —
     * suggested sequences are adopted without stealing the default.
     */
    public function cloneSequenceIntoParish(int $sourceSequenceId, int $parishId): ?int
    {
        return $this->cloneSequenceIntoScope(
            $sourceSequenceId,
            ['parish_id' => $parishId],
            !$this->sequenceService->hasDefaultForParish($parishId)
        );
    }

    /**
     * Clone a global sequence into a diocese. The clone becomes the
     * diocesan default only when the diocese has none.
     */
    public function cloneSequenceIntoDiocese(int $sourceSequenceId, int $dioceseId): ?int
    {
        $hasDefault = $this->sequenceService->getDioceseDefaultSequence($dioceseId) !== null;

        return $this->cloneSequenceIntoScope(
            $sourceSequenceId,
            ['diocese_id' => $dioceseId],
            !$hasDefault
        );
    }

    /**
     * Clone any upstream (non-parish) sequence into a parish or diocese
     * scope, including all referenced templates and actions. Lineage
     * columns (source_sequence_id / source_action_id / source_template_id)
     * are stamped so later upstream edits propagate.
     *
     * @param array{parish_id?: int, diocese_id?: int} $scope
     */
    public function cloneSequenceIntoScope(int $sourceSequenceId, array $scope, bool $forceDefault = false): ?int
    {
        $source = $this->sequenceModel->find($sourceSequenceId);
        $parishId  = (int) ($scope['parish_id'] ?? 0);
        $dioceseId = (int) ($scope['diocese_id'] ?? 0);

        if (!is_array($source) || FollowupSequenceModel::scopeOf($source) === 'parish') {
            log_message('error', "cloneSequenceIntoScope: sequence {$sourceSequenceId} is not an upstream sequence.");
            return null;
        }

        if ($parishId <= 0 && $dioceseId <= 0) {
            log_message('error', 'cloneSequenceIntoScope: a parish_id or diocese_id scope is required.');
            return null;
        }

        // A scope only ever needs one clone of a given source: reuse the
        // existing clone rather than stacking duplicates that would all
        // receive the same propagation updates.
        $existingQuery = $this->sequenceModel->builder()
            ->where('source_sequence_id', (int) $source['id']);

        if ($parishId > 0) {
            $existingQuery->where('parish_id', $parishId);
        } else {
            $existingQuery->where('diocese_id', $dioceseId)->where('parish_id IS NULL', null, false);
        }

        $existing = $existingQuery->get()->getRowArray();

        if ($existing) {
            $existingId = (int) $existing['id'];
            if ($forceDefault) {
                if ($parishId > 0) {
                    $this->sequenceService->setDefaultForSequence($existingId, $parishId);
                } else {
                    $this->sequenceService->setDioceseDefaultSequence($existingId, $dioceseId);
                }
            }
            log_message('info', "Clone of upstream follow-up sequence {$sourceSequenceId} already exists in scope " . json_encode($scope) . " as sequence {$existingId}; reusing.");
            return $existingId;
        }

        $db = $this->sequenceModel->db;

        try {
            $db->transStart();

            $now           = date('Y-m-d H:i:s');
            $newSequenceId = $this->sequenceModel->insert([
                'parish_id'           => $parishId > 0 ? $parishId : null,
                'diocese_id'          => $dioceseId > 0 ? $dioceseId : null,
                'source_sequence_id'  => (int) $source['id'],
                'name'                => preg_replace('/ \(default\)$/', '', (string) $source['name']) . ' (default)',
                'description'         => $source['description'],
                'is_active'           => 1,
                'is_default'          => 0,
                'is_global_default'   => 0,
                'is_diocese_default'  => 0,
                'is_customized'       => 0,
                'created_at'          => $now,
                'updated_at'          => $now,
            ], true);

            if (!$newSequenceId) {
                throw new \RuntimeException('Failed to insert cloned sequence row.');
            }

            $newSequenceId = (int) $newSequenceId;

            if ($forceDefault) {
                if ($parishId > 0) {
                    $this->sequenceService->setDefaultForSequence($newSequenceId, $parishId);
                } else {
                    $this->sequenceService->setDioceseDefaultSequence($newSequenceId, $dioceseId);
                }
            }

            // Load source actions and clone referenced templates into the
            // same scope.
            $actions = $this->actionModel->getBySequence($sourceSequenceId);

            $templateMap = [];
            foreach ($actions as $action) {
                $templateId = $action['template_id'] ? (int) $action['template_id'] : null;
                if ($templateId && !isset($templateMap[$templateId])) {
                    $templateMap[$templateId] = $this->cloneTemplateIntoScope($templateId, $scope, $now);
                }
            }

            // Clone actions. Human-channel assignees resolve to the parish's
            // primary admin; at diocesan scope (and parishes without a
            // primary admin) they stay NULL for staff to pick up downstream.
            $primaryAdminId = $parishId > 0 ? $this->resolvePrimaryAdminId($parishId) : null;

            foreach ($actions as $action) {
                $oldTemplateId = $action['template_id'] ? (int) $action['template_id'] : null;
                $newTemplateId = $oldTemplateId ? ($templateMap[$oldTemplateId] ?? null) : null;
                $isHuman       = in_array($action['channel'], ['call', 'manual'], true);

                $this->actionModel->insert([
                    'sequence_id'      => $newSequenceId,
                    'week_number'      => (int) $action['week_number'],
                    'day_offset'       => (int) $action['day_offset'],
                    'channel'          => $action['channel'],
                    'template_id'      => $newTemplateId,
                    'assigned_to'      => $isHuman ? $primaryAdminId : null,
                    'instructions'     => $action['instructions'] ?? null,
                    'source_action_id' => (int) $action['id'],
                    'is_customized'    => 0,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ]);
            }

            $db->transComplete();

            log_message('info', "Cloned upstream follow-up sequence {$sourceSequenceId} into scope " . json_encode($scope) . " as sequence {$newSequenceId}.");

            return $newSequenceId;
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', "Failed to clone follow-up sequence {$sourceSequenceId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * The parish's primary admin user id, or null when none is configured.
     * Used as the default assignee for cloned call/manual steps.
     */
    public function resolvePrimaryAdminId(int $parishId): ?int
    {
        $userId = (int) (model('ParishAdminPrimaryModel')
            ->getByParish($parishId)['user_id'] ?? 0);

        return $userId > 0 ? $userId : null;
    }

    /**
     * Clone an upstream template into a parish, stamping source_template_id
     * for propagation lineage. Returns the new template ID.
     */
    public function cloneTemplateIntoParish(int $templateId, int $parishId, ?string $now = null): ?int
    {
        return $this->cloneTemplateIntoScope($templateId, ['parish_id' => $parishId], $now);
    }

    /**
     * Clone an upstream template into a parish or diocese scope, stamping
     * source_template_id for propagation lineage. Returns the new ID.
     *
     * @param array{parish_id?: int, diocese_id?: int} $scope
     */
    public function cloneTemplateIntoScope(int $templateId, array $scope, ?string $now = null): ?int
    {
        $template = $this->templateModel->find($templateId);
        if (!is_array($template)) {
            return null;
        }

        $now   = $now ?? date('Y-m-d H:i:s');
        $newId = $this->templateModel->insert([
            'parish_id'          => (int) ($scope['parish_id'] ?? 0) > 0 ? (int) $scope['parish_id'] : null,
            'diocese_id'         => (int) ($scope['diocese_id'] ?? 0) > 0 ? (int) $scope['diocese_id'] : null,
            'source_template_id' => $templateId,
            'channel'            => $template['channel'],
            'name'               => preg_replace('/ \(default\)$/', '', (string) $template['name']) . ' (default)',
            'subject'            => $template['subject'] ?? null,
            'body_text'          => $template['body_text'],
            'body_html'          => $template['body_html'] ?? null,
            'variables'          => $template['variables'] ?? null,
            'is_customized'      => 0,
            'created_at'         => $now,
            'updated_at'         => $now,
        ], true);

        return $newId ? (int) $newId : null;
    }
}
