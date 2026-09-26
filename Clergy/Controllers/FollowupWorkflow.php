<?php

namespace Clergy\Controllers;

use App\Controllers\BaseController;

/**
 * FollowupWorkflow
 *
 * Manages follow-up sequences, their actions, and message templates.
 * Also provides a manual-trigger endpoint for staff to dispatch a
 * follow-up action immediately (bypassing scheduled execution).
 *
 * Permissions required:
 *   Read  – visitors.view
 *   Write – visitors.edit
 */
class FollowupWorkflow extends BaseController
{
    protected $sequenceModel;
    protected $actionModel;
    protected $templateModel;
    protected $logModel;
    protected $visitorDirectory;
    protected $sequenceService;

    public function __construct()
    {
        $this->sequenceModel   = model('FollowupSequenceModel');
        $this->actionModel     = model('FollowupActionModel');
        $this->templateModel   = model('FollowupTemplateModel');
        $this->logModel        = model('FollowupLogModel');
        $this->visitorDirectory  = new \App\Services\VisitorDirectoryService();
        $this->sequenceService   = new \App\Services\FollowupSequenceService();
    }

    // ------------------------------------------------------------------
    // Sequences – list
    // ------------------------------------------------------------------

    /**
     * List all follow-up sequences available to the operational parish:
     * parish rows plus upstream suggestions (global and the parish's
     * diocese) that can be cloned. Suggestions are hidden by default;
     * ?upstream_global=1 / ?upstream_diocese=1 opt each group in.
     */
    public function sequences()
    {
        if ($redirect = $this->requirePermission('followup.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId      = (int) $this->getOperationalParishId();
        $showGlobal    = $this->request->getGet('upstream_global') === '1';
        $showDiocese   = $this->request->getGet('upstream_diocese') === '1';
        $dioceseId     = $this->sequenceModel->parishDioceseId($parishId);

        $sequences = $this->sequenceModel
            ->where('parish_id', $parishId)
            ->orderBy('name', 'ASC')
            ->findAll();

        // Upstream sequences available for cloning, per group toggle.
        $upstream = [];
        if ($showGlobal) {
            $upstream = array_merge($upstream, $this->sequenceModel
                ->where('parish_id IS NULL', null, false)
                ->where('diocese_id IS NULL', null, false)
                ->orderBy('name', 'ASC')
                ->findAll());
        }
        if ($showDiocese && $dioceseId > 0) {
            $upstream = array_merge($upstream, $this->sequenceModel
                ->where('diocese_id', $dioceseId)
                ->orderBy('name', 'ASC')
                ->findAll());
        }
        $sequences = array_merge($sequences, $upstream);

        $scopeNames = [];
        if ($dioceseId > 0) {
            $row = $this->sequenceModel->db->table('diocese')->select('name')->where('id', $dioceseId)->get()->getRowArray();
            $scopeNames['diocese'] = (string) ($row['name'] ?? '');
        }

        foreach ($sequences as &$seq) {
            $seq['action_count'] = $this->actionModel
                ->where('sequence_id', $seq['id'])
                ->countAllResults();
            $seq['scope'] = \App\Models\FollowupSequenceModel::scopeOf($seq);
        }
        unset($seq);

        return view('Clergy\Views\FollowupWorkflow/sequences', array_merge(
            $this->getCommonViewData(lang('Visitors.pageTitleSequences')),
            [
                'sequences'    => $sequences,
                'parishId'     => $parishId,
                'scopeNames'   => $scopeNames,
                'showGlobal'   => $showGlobal,
                'showDiocese'  => $showDiocese,
                'hasDiocese'   => $dioceseId > 0,
            ]
        ));
    }

    /**
     * POST followup-workflow/sequences/clone/:id (AJAX)
     *
     * Clone an upstream sequence (global or diocesan) into the
     * operational parish. The clone keeps lineage to its source so upstream
     * edits propagate until the parish customizes a row.
     */
    public function cloneSequence(int $id)
    {
        if ($redirect = $this->requirePermission('followup.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) $this->getOperationalParishId();
        $sequence = $this->sequenceModel->find($id);

        if (!$sequence || (int) $sequence['is_active'] !== 1) {
            return $this->jsonResponse(['success' => false, 'message' => 'Not found or inactive.'], 404);
        }

        // Guard: the source must be an upstream sequence within this
        // parish's hierarchy — global or its own diocese.
        $scope     = \App\Models\FollowupSequenceModel::scopeOf($sequence);
        $dioceseId = $this->sequenceModel->parishDioceseId($parishId);

        $allowed = match ($scope) {
            'global'  => true,
            'diocese' => (int) $sequence['diocese_id'] === $dioceseId,
            default   => false,
        };

        if (!$allowed) {
            return $this->jsonResponse(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $newId = (new \App\Services\FollowupCloneService())->cloneSequenceIntoParish($id, $parishId);

        if (!$newId) {
            return $this->jsonResponse(['success' => false, 'message' => 'Clone failed.'], 500);
        }

        $this->audit()->log('followup_sequence.cloned', [
            'parish_id'   => $parishId,
            'entity_type' => 'followup_sequence',
            'entity_id'   => $newId,
            'meta'        => ['source_sequence_id' => $id],
        ]);

        return $this->jsonResponse(['success' => true, 'sequence_id' => $newId]);
    }

    // ------------------------------------------------------------------
    // Sequences – create / edit form
    // ------------------------------------------------------------------

    /**
     * Show the create/edit form for a sequence.
     * Actions are listed in a table; "Add Action" opens a Bootstrap modal.
     *
     * @param int $id  0 = new sequence
     */
    public function edit_sequence(int $id = 0)
    {
        if ($redirect = $this->requirePermission('followup.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) $this->getOperationalParishId();
        $sequence = $id > 0 ? $this->sequenceModel->find($id) : null;

        if ($id > 0 && $sequence === null) {
            return redirect()->to('followup-workflow/sequences')->with('error', 'Sequence not found.');
        }

        // Parish-scope guard: a parish admin cannot edit another parish's sequence
        // or a global (parish_id=null) sequence.
        if ($sequence !== null) {
            $seqParishId = $sequence['parish_id'];
            if ($seqParishId === null || (int) $seqParishId !== $parishId) {
                return redirect()->to('followup-workflow/sequences')
                    ->with('error', 'Global sequences cannot be edited at this level.');
            }
        }

        $actions   = $id > 0 ? $this->actionModel->getBySequenceWithTemplates($id) : [];
        $templates = $this->templateModel->getAllForParish($parishId);
        $leaders   = model('LeadershipPositionModel')->getAssignableParishLeaders($parishId);

        $pageTitle = $id > 0
            ? lang('Visitors.pageTitleEditSequence', [esc($sequence['name'])])
            : lang('Visitors.pageTitleNewSequence');

        return view('Clergy\Views\FollowupWorkflow/edit_sequence', array_merge(
            $this->getCommonViewData($pageTitle),
            [
                'sequence'  => $sequence,
                'actions'   => $actions,
                'templates' => $templates,
                'leaders'   => $leaders,
                'parishId'  => $parishId,
            ]
        ));
    }

    // ------------------------------------------------------------------
    // Sequences – save (create or update)
    // ------------------------------------------------------------------

    /**
     * Handle POST for sequence create (id = 0) and update (id > 0).
     *
     * @param int $id  0 = create
     */
    public function saveSequence(int $id = 0)
    {
        if ($redirect = $this->requirePermission('followup.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) $this->getOperationalParishId();

        $rules = [
            'name'        => 'required|min_length[3]|max_length[150]',
            'description' => 'permit_empty',
            'is_active'   => 'permit_empty|in_list[0,1]',
            'is_default'  => 'permit_empty|in_list[0,1]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $isDefault = (int) ($this->request->getPost('is_default') ?? 0);

        $data = [
            'name'        => $this->request->getPost('name'),
            'description' => $this->request->getPost('description') ?: null,
            'is_active'   => (int) ($this->request->getPost('is_active') ?? 1),
            'parish_id'   => $parishId,
            'is_default'  => 0, // handled explicitly below
        ];

        if ($id > 0) {
            $sequence = $this->sequenceModel->find($id);
            if (
                !$sequence
                || $sequence['parish_id'] === null
                || (int) $sequence['parish_id'] !== $parishId
            ) {
                return redirect()->to('followup-workflow/sequences')
                    ->with('error', 'Not found or forbidden.');
            }
            // Editing a clone's name/description marks it customized so
            // upstream propagation no longer overwrites local text.
            if (!empty($sequence['source_sequence_id'])) {
                $source = $this->sequenceModel->find((int) $sequence['source_sequence_id']);
                if (is_array($source)
                    && ($data['name'] !== $source['name'] . ' (default)'
                        && $data['name'] !== $source['name']
                        || ($data['description'] ?? null) !== ($source['description'] ?? null))) {
                    $data['is_customized'] = 1;
                }
            }
            $this->sequenceModel->update($id, $data);
            $event = 'followup_sequence.updated';
        } else {
            $id    = (int) $this->sequenceModel->insert($data);
            $event = 'followup_sequence.created';

            // If this is the parish's first sequence, make it the default
            // automatically unless the user explicitly left the box unchecked.
            $hasExistingDefault = $this->sequenceService->hasDefaultForParish($parishId);
            if (!$hasExistingDefault) {
                $isDefault = 1;
            }
        }

        if ($isDefault && $id > 0) {
            $this->sequenceService->setDefaultForSequence($id, $parishId);
        }

        $this->audit()->log($event, [
            'parish_id'   => $parishId,
            'entity_type' => 'followup_sequence',
            'entity_id'   => $id,
        ]);

        return redirect()->to('followup-workflow/sequences/edit/' . $id)
            ->with('success', 'Sequence saved.');
    }

    // ------------------------------------------------------------------
    // Sequences – toggle active state (AJAX POST)
    // ------------------------------------------------------------------

    public function toggleSequence(int $id)
    {
        if ($redirect = $this->requirePermission('followup.manage')) {
            return $redirect;
        }

        $parishId = (int) $this->getOperationalParishId();
        $sequence = $this->sequenceModel->find($id);

        if (
            !$sequence
            || $sequence['parish_id'] === null
            || (int) $sequence['parish_id'] !== $parishId
        ) {
            return $this->jsonResponse(['success' => false, 'message' => 'Not found or forbidden.'], 404);
        }

        $newState = $sequence['is_active'] ? 0 : 1;
        $update = ['is_active' => $newState];

        // Deactivating the default sequence removes the default flag so the
        // parish does not silently end up with no active default.
        if ($newState === 0 && !empty($sequence['is_default'])) {
            $update['is_default'] = 0;
        }

        $this->sequenceModel->update($id, $update);

        $this->audit()->log('followup_sequence.toggled', [
            'parish_id'   => $parishId,
            'entity_type' => 'followup_sequence',
            'entity_id'   => $id,
            'meta'        => ['is_active' => $newState],
        ]);

        return $this->jsonResponse(['success' => true, 'is_active' => $newState]);
    }

    /**
     * POST followup-workflow/sequences/set-default/:id (AJAX)
     * Mark a parish sequence as the default for first-visit scheduling.
     */
    public function setDefaultSequence(int $id)
    {
        if ($redirect = $this->requirePermission('followup.manage')) {
            return $redirect;
        }

        $parishId = (int) $this->getOperationalParishId();
        $sequence = $this->sequenceModel->find($id);

        if (
            !$sequence
            || $sequence['parish_id'] === null
            || (int) $sequence['parish_id'] !== $parishId
        ) {
            return $this->jsonResponse(['success' => false, 'message' => 'Not found or forbidden.'], 404);
        }

        if (!$sequence['is_active']) {
            return $this->jsonResponse(['success' => false, 'message' => 'Cannot set an inactive sequence as default.'], 422);
        }

        $ok = $this->sequenceService->setDefaultForSequence($id, $parishId);

        if (!$ok) {
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to update default.'], 500);
        }

        $this->audit()->log('followup_sequence.set_default', [
            'parish_id'   => $parishId,
            'entity_type' => 'followup_sequence',
            'entity_id'   => $id,
        ]);

        return $this->jsonResponse(['success' => true, 'is_default' => true]);
    }

    // ------------------------------------------------------------------
    // Sequences – delete (AJAX POST)
    // ------------------------------------------------------------------

    public function deleteSequence(int $id)
    {
        if ($redirect = $this->requirePermission('followup.manage')) {
            return $redirect;
        }

        $parishId = (int) $this->getOperationalParishId();
        $sequence = $this->sequenceModel->find($id);

        if (
            !$sequence
            || $sequence['parish_id'] === null
            || (int) $sequence['parish_id'] !== $parishId
        ) {
            return $this->jsonResponse(['success' => false, 'message' => 'Not found or forbidden.'], 404);
        }

        // Delete child actions before removing the parent
        $this->actionModel->where('sequence_id', $id)->delete();
        $this->sequenceModel->delete($id);

        $this->audit()->log('followup_sequence.deleted', [
            'parish_id'   => $parishId,
            'entity_type' => 'followup_sequence',
            'entity_id'   => $id,
        ]);

        return $this->jsonResponse(['success' => true]);
    }

    // ------------------------------------------------------------------
    // Actions – save (AJAX POST, create or update)
    // ------------------------------------------------------------------

    /**
     * Create or update a followup action step.
     * Returns updated action list for the sequence so the table re-renders.
     *
     * @param int $id  0 = create
     */
    public function saveAction(int $id = 0)
    {
        if ($redirect = $this->requirePermission('followup.manage')) {
            return $redirect;
        }

        $parishId   = (int) $this->getOperationalParishId();
        $sequenceId = (int) $this->request->getPost('sequence_id');

        $sequence = $this->sequenceModel->find($sequenceId);
        if (
            !$sequence
            || $sequence['parish_id'] === null
            || (int) $sequence['parish_id'] !== $parishId
        ) {
            return $this->jsonResponse(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $isManual = $this->request->getPost('channel') === 'manual';

        $rules = [
            'week_number'  => 'required|is_natural_no_zero|less_than_equal_to[52]',
            'day_offset'   => 'required|is_natural|less_than_equal_to[365]',
            'channel'      => 'required|in_list[text,email,call,mail,gift,manual]',
            'template_id'  => 'permit_empty|is_not_unique[followup_template.id]',
        ];

        if ($isManual) {
            $rules['assigned_to']  = 'required|is_not_unique[users.id]';
            $rules['instructions'] = 'required|max_length[255]';
        } else {
            $rules['assigned_to']  = 'permit_empty|is_not_unique[users.id]';
            $rules['instructions'] = 'permit_empty|max_length[255]';
        }

        if (!$this->validate($rules)) {
            return $this->jsonResponse(['success' => false, 'errors' => $this->validator->getErrors()], 422);
        }

        $assignedTo = (int) ($this->request->getPost('assigned_to') ?? 0);

        // Parish-scope guard: a manual task may only be assigned to a user
        // holding an active leadership_position scoped to this parish.
        if ($assignedTo > 0 && !model('LeadershipPositionModel')->isActiveParishLeader($assignedTo, $parishId)) {
            return $this->jsonResponse([
                'success' => false,
                'errors'  => ['assigned_to' => lang('Visitors.seqAssigneeNotLeader')],
            ], 422);
        }

        $templateId = $this->request->getPost('template_id');
        $templateId = ($templateId !== '' && $templateId !== null) ? (int) $templateId : null;

        // Channel-template compatibility guard: an email template cannot be
        // attached to an SMS action, etc.
        if ($templateId !== null && !$this->templateModel->channelMatches($templateId, $this->request->getPost('channel'))) {
            return $this->jsonResponse([
                'success' => false,
                'errors'  => ['template_id' => lang('Visitors.seqTemplateChannelMismatch')],
            ], 422);
        }

        $data = [
            'sequence_id'  => $sequenceId,
            'week_number'  => (int) $this->request->getPost('week_number'),
            'day_offset'   => (int) $this->request->getPost('day_offset'),
            'channel'      => $this->request->getPost('channel'),
            'template_id'  => $templateId,
            'assigned_to'  => $assignedTo > 0 ? $assignedTo : null,
            'instructions' => trim((string) $this->request->getPost('instructions')) ?: null,
        ];

        if ($id > 0) {
            $existing = $this->actionModel->find($id);
            if (!$existing || (int) $existing['sequence_id'] !== $sequenceId) {
                return $this->jsonResponse(['success' => false, 'message' => 'Not found.'], 404);
            }
            // Parish edits to a cloned step detach it from propagation.
            if (!empty($existing['source_action_id'])) {
                $data['is_customized'] = 1;
            }
            $this->actionModel->update($id, $data);
            $event = 'followup_action.updated';
        } else {
            $id    = (int) $this->actionModel->insert($data);
            $event = 'followup_action.created';
            // Adding a local step to a cloned sequence diverges its
            // composition — upstream creates no longer propagate into it.
            $this->markSequenceCustomized($sequence);
        }

        $this->audit()->log($event, [
            'parish_id'   => $parishId,
            'entity_type' => 'followup_action',
            'entity_id'   => $id,
            'meta'        => ['sequence_id' => $sequenceId],
        ]);

        $updatedActions = $this->actionModel->getBySequenceWithTemplates($sequenceId);

        return $this->jsonResponse(['success' => true, 'actions' => $updatedActions]);
    }

    /**
     * Flag a cloned sequence as customized when its composition diverges
     * (parish adds or removes steps), so upstream creates no longer
     * propagate into it.
     */
    private function markSequenceCustomized(array $sequence): void
    {
        if (!empty($sequence['source_sequence_id']) && empty($sequence['is_customized'])) {
            $this->sequenceModel->update((int) $sequence['id'], ['is_customized' => 1]);
        }
    }

    // ------------------------------------------------------------------
    // Actions – delete (AJAX POST)
    // ------------------------------------------------------------------

    public function deleteAction(int $id)
    {
        if ($redirect = $this->requirePermission('followup.manage')) {
            return $redirect;
        }

        $parishId = (int) $this->getOperationalParishId();
        $action   = $this->actionModel->find($id);

        if (!$action) {
            return $this->jsonResponse(['success' => false, 'message' => 'Not found.'], 404);
        }

        $sequence = $this->sequenceModel->find($action['sequence_id']);
        if (
            !$sequence
            || $sequence['parish_id'] === null
            || (int) $sequence['parish_id'] !== $parishId
        ) {
            return $this->jsonResponse(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $sequenceId = (int) $action['sequence_id'];
        $this->actionModel->delete($id);
        // Removing a cloned step diverges the clone's composition.
        $this->markSequenceCustomized($sequence);

        $this->audit()->log('followup_action.deleted', [
            'parish_id'   => $parishId,
            'entity_type' => 'followup_action',
            'entity_id'   => $id,
            'meta'        => ['sequence_id' => $sequenceId],
        ]);

        $updatedActions = $this->actionModel->getBySequenceWithTemplates($sequenceId);

        return $this->jsonResponse(['success' => true, 'actions' => $updatedActions]);
    }

    // ------------------------------------------------------------------
    // Templates – list
    // ------------------------------------------------------------------

    public function templates()
    {
        if ($redirect = $this->requirePermission('followup.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId     = (int) $this->getOperationalParishId();
        $templatesRaw = $this->templateModel->getAllForParish($parishId);
        $templates    = [];

        // Normalize all rows to a flat associative array so the view can safely render scalar values.
        foreach ($templatesRaw as $row) {
            if (!is_array($row)) {
                continue;
            }

            $name = isset($row['name']) && !is_array($row['name']) ? (string) $row['name'] : '';
            if ($name === '') {
                continue;
            }

            $subject = '';
            if (isset($row['subject']) && !is_array($row['subject'])) {
                $subject = (string) $row['subject'];
            }

            $bodyText = '';
            if (isset($row['body_text']) && !is_array($row['body_text'])) {
                $bodyText = (string) $row['body_text'];
            }

            $bodyHtml = '';
            if (isset($row['body_html']) && !is_array($row['body_html'])) {
                $bodyHtml = (string) $row['body_html'];
            }

            $variables = '';
            if (isset($row['variables'])) {
                if (is_array($row['variables'])) {
                    $encoded = json_encode($row['variables']);
                    $variables = $encoded !== false ? $encoded : '';
                } else {
                    $variables = (string) $row['variables'];
                }
            }

            $channel = isset($row['channel']) && !is_array($row['channel']) ? (string) $row['channel'] : '';
            if (!in_array($channel, ['text', 'email', 'call', 'mail', 'gift'], true)) {
                $channel = 'email';
            }

            $templates[] = [
                'id'         => isset($row['id']) ? (int) $row['id'] : 0,
                'parish_id'  => array_key_exists('parish_id', $row) && $row['parish_id'] !== null ? (int) $row['parish_id'] : null,
                'name'       => $name,
                'channel'    => $channel,
                'subject'    => $subject,
                'body_text'  => $bodyText,
                'body_html'  => $bodyHtml,
                'variables'  => $variables,
            ];
        }

        // Build step metadata from follow-up actions so templates list in workflow order.
        $usageRows = db_connect()
            ->table('followup_action fa')
            ->select('fa.template_id, MIN(fa.week_number) AS min_week, MIN(fa.day_offset) AS min_day, COUNT(*) AS usage_count')
            ->join('followup_sequence fs', 'fs.id = fa.sequence_id', 'inner')
            ->where('fa.template_id IS NOT NULL', null, false)
            ->groupStart()
            ->where('fs.parish_id', $parishId)
            ->orWhere('fs.parish_id IS NULL', null, false)
            ->groupEnd()
            ->groupBy('fa.template_id')
            ->get()
            ->getResultArray();

        $usageByTemplateId = [];
        foreach ($usageRows as $row) {
            $usageByTemplateId[(int) $row['template_id']] = [
                'min_week'    => isset($row['min_week']) ? (int) $row['min_week'] : null,
                'min_day'     => isset($row['min_day']) ? (int) $row['min_day'] : null,
                'usage_count' => (int) ($row['usage_count'] ?? 0),
            ];
        }

        foreach ($templates as &$tpl) {
            $templateId = (int) ($tpl['id'] ?? 0);
            $usage      = $usageByTemplateId[$templateId] ?? null;

            $stepWeek = null;
            $stepDay  = null;

            if (is_array($usage)) {
                $stepWeek = $usage['min_week'];
                $stepDay  = $usage['min_day'];
            } elseif (preg_match('/\bweek\s*(\d+)\b/i', (string) ($tpl['name'] ?? ''), $m)) {
                $stepWeek = (int) $m[1];
            }

            $tpl['step_week']  = $stepWeek;
            $tpl['step_day']   = $stepDay;
            $tpl['step_label'] = ($stepWeek !== null)
                ? ($stepDay !== null ? ('W' . $stepWeek . ' / D+' . $stepDay) : ('W' . $stepWeek))
                : '—';

            // Sort priority: templates used in actions first, then by day offset/week, then channel+name.
            $sortBase = $stepDay !== null
                ? $stepDay
                : ($stepWeek !== null ? ($stepWeek * 7) : 9999);
            $tpl['step_sort_key'] = $sortBase;
            $tpl['step_has_usage'] = is_array($usage) ? 1 : 0;
        }
        unset($tpl);

        usort($templates, static function (array $a, array $b): int {
            $usageCmp = ($b['step_has_usage'] <=> $a['step_has_usage']);
            if ($usageCmp !== 0) {
                return $usageCmp;
            }

            $stepCmp = ((int) $a['step_sort_key'] <=> (int) $b['step_sort_key']);
            if ($stepCmp !== 0) {
                return $stepCmp;
            }

            $channelCmp = strcmp((string) ($a['channel'] ?? ''), (string) ($b['channel'] ?? ''));
            if ($channelCmp !== 0) {
                return $channelCmp;
            }

            return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        // Recent visitors for the live-preview visitor selector (50 most recent)
        $visitorsForPreview = $this->visitorDirectory->pickerRowsForParish($parishId, 50);

        return view('Clergy\Views\FollowupWorkflow/templates', array_merge(
            $this->getCommonViewData(lang('Visitors.pageTitleTemplates')),
            [
                'templates'        => $templates,
                'parishId'         => $parishId,
                'visitorsForPreview' => $visitorsForPreview,
            ]
        ));
    }

    // ------------------------------------------------------------------
    // Templates – save (AJAX POST, create or update)
    // ------------------------------------------------------------------

    /**
     * @param int $id  0 = create
     */
    public function saveTemplate(int $id = 0)
    {
        if ($redirect = $this->requirePermission('followup.manage')) {
            return $redirect;
        }

        $parishId = (int) $this->getOperationalParishId();

        // Upstream templates (parish_id = NULL: global/diocesan/provincial)
        // are read-only at parish level.
        if ($id > 0) {
            $existing = $this->templateModel->find($id);
            if ($existing && $existing['parish_id'] === null) {
                return $this->jsonResponse(
                    ['success' => false, 'message' => 'Upstream templates cannot be edited here.'],
                    403
                );
            }
            if ($existing && (int) $existing['parish_id'] !== $parishId) {
                return $this->jsonResponse(['success' => false, 'message' => 'Forbidden.'], 403);
            }
        }

        $rules = [
            'channel'   => 'required|in_list[text,email,call,mail,gift]',
            'name'      => 'required|min_length[3]|max_length[150]',
            'body_text' => 'required',
            'subject'   => 'permit_empty|max_length[255]',
        ];

        if (!$this->validate($rules)) {
            return $this->jsonResponse(['success' => false, 'errors' => $this->validator->getErrors()], 422);
        }

        $variablesRaw = $this->request->getPost('variables');
        if (is_string($variablesRaw) && $variablesRaw !== '') {
            $decoded = json_decode($variablesRaw, true);
            if (!is_array($decoded)) {
                $tags         = array_filter(array_map('trim', explode(',', $variablesRaw)));
                $variablesRaw = json_encode(array_values($tags));
            } else {
                $variablesRaw = json_encode($decoded);
            }
        } else {
            $variablesRaw = null;
        }

        $data = [
            'parish_id' => $parishId,
            'channel'   => $this->request->getPost('channel'),
            'name'      => $this->request->getPost('name'),
            'subject'   => $this->request->getPost('subject') ?: null,
            'body_text' => $this->request->getPost('body_text'),
            'body_html' => $this->request->getPost('body_html') ?: null,
            'variables' => $variablesRaw,
        ];

        if ($id > 0) {
            // Parish edits to a cloned template detach it from propagation.
            if (!empty($existing['source_template_id'])) {
                $data['is_customized'] = 1;
            }
            $this->templateModel->update($id, $data);
            $event = 'followup_template.updated';
        } else {
            $id    = (int) $this->templateModel->insert($data);
            $event = 'followup_template.created';
        }

        $this->audit()->log($event, [
            'parish_id'   => $parishId,
            'entity_type' => 'followup_template',
            'entity_id'   => $id,
        ]);

        // Warn about unrecognised tokens (template is saved regardless).
        $knownTokens = [
            '{first_name}',
            '{last_name}',
            '{parish_name}',
            '{visit_date}',
            '{clergy_name}',
            '{phone}',
            '{next_event_title}',
            '{next_event_date}',
            '{clergy_email}',
            '{parish_address}',
            '{unsubscribe_url}',
        ];
        $allContent = ($data['body_text'] ?? '') . ' ' . ($data['body_html'] ?? '') . ' ' . ($data['subject'] ?? '');
        preg_match_all('/\{[^}]+\}/', $allContent, $matches);
        $unknownTokens = array_values(array_diff(array_unique($matches[0] ?? []), $knownTokens));

        return $this->jsonResponse([
            'success'       => true,
            'id'            => $id,
            'unknown_tokens' => $unknownTokens,
        ]);
    }

    // ------------------------------------------------------------------
    // Templates – delete (AJAX POST)
    // ------------------------------------------------------------------

    public function deleteTemplate(int $id)
    {
        if ($redirect = $this->requirePermission('followup.manage')) {
            return $redirect;
        }

        $parishId = (int) $this->getOperationalParishId();
        $template = $this->templateModel->find($id);

        if (!$template) {
            return $this->jsonResponse(['success' => false, 'message' => 'Not found.'], 404);
        }

        if ($template['parish_id'] === null || (int) $template['parish_id'] !== $parishId) {
            return $this->jsonResponse(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $this->templateModel->delete($id);

        $this->audit()->log('followup_template.deleted', [
            'parish_id'   => $parishId,
            'entity_type' => 'followup_template',
            'entity_id'   => $id,
        ]);

        return $this->jsonResponse(['success' => true]);
    }

    // ------------------------------------------------------------------
    // Templates – preview
    // ------------------------------------------------------------------

    /**
     * Render a template substituted with sample values so staff can
     * verify formatting before assigning it to a workflow action.
     */
    public function preview_template(int $id)
    {
        if ($redirect = $this->requirePermission('followup.view')) {
            return $redirect;
        }

        $template = $this->templateModel->find($id);

        if (!$template) {
            return redirect()->to('followup-workflow/templates')->with('error', 'Template not found.');
        }

        $parishId = (int) $this->getOperationalParishId();
        $parish   = db_connect()->table('parish')->where('id', $parishId)->get()->getRowArray();

        $sampleVars = [
            '{first_name}'  => 'Maria',
            '{last_name}'   => 'García',
            '{parish_name}' => $parish['name'] ?? 'Grace Parish',
            '{visit_date}'  => date('F j, Y'),
            '{clergy_name}' => 'Clergy Juan',
            '{phone}'       => '(555) 000-0000',
        ];

        // Decode stored variables JSON to show available tags in the UI
        $definedVars = [];
        if (!empty($template['variables'])) {
            $decoded = json_decode($template['variables'], true);
            if (is_array($decoded)) {
                $definedVars = $decoded;
            }
        }

        $preview = [
            'subject'   => $this->applyTemplateVars((string) ($template['subject'] ?? ''), $sampleVars),
            'body_text' => $this->applyTemplateVars((string) ($template['body_text'] ?? ''), $sampleVars),
            'body_html' => $this->applyTemplateVars((string) ($template['body_html'] ?? ''), $sampleVars),
        ];

        return view('Clergy\Views\FollowupWorkflow/preview_template', array_merge(
            $this->getCommonViewData(lang('Visitors.pageTitlePreviewTemplate')),
            [
                'template'    => $template,
                'preview'     => $preview,
                'sampleVars'  => $sampleVars,
                'definedVars' => $definedVars,
            ]
        ));
    }

    // ------------------------------------------------------------------
    // Preview parsed template (AJAX POST)
    // ------------------------------------------------------------------

    /**
     * Return a parsed preview of a template body using a real visitor's data.
     *
     * POST body:
     *   template_id  – (int) ID of the template to preview.
     *   visitor_id     – (int|optional) Visitor to use for variable substitution.
     *                  If omitted or invalid, falls back to hardcoded sample data.
     *
     * Returns JSON:
     *   { success: true, subject: '…', body_text: '…', body_html: '…' }
     */
    public function previewParsed()
    {
        if ($redirect = $this->requirePermission('followup.view')) {
            return $redirect;
        }

        if (! $this->request->isAJAX()) {
            return $this->jsonResponse(['success' => false, 'message' => 'AJAX only.'], 400);
        }

        $templateId = (int) $this->request->getPost('template_id');
        $visitorId    = (int) $this->request->getPost('visitor_id');
        $parishId   = (int) $this->getOperationalParishId();

        // Load template and guard ownership
        $template = $this->templateModel->find($templateId);
        if (! $template) {
            return $this->jsonResponse(['success' => false, 'message' => 'Template not found.'], 404);
        }

        $tplParishId = (int) ($template['parish_id'] ?? 0);
        if ($tplParishId !== 0 && $tplParishId !== $parishId) {
            return $this->jsonResponse(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        // Load the parish row
        $parish = db_connect()->table('parish')->where('id', $parishId)->get()->getRowArray() ?: null;

        // Load parish settings as a flat key→value map
        $settingModel    = model('ParishSettingModel');
        $settingsRows    = $settingModel->getParishSettings($parishId);   // rows with key, value
        $parishSettings  = [];
        foreach ($settingsRows as $row) {
            $parishSettings[(string) $row['key']] = (string) ($row['value'] ?? '');
        }

        // Load and decrypt visitor, or fall back to sample data
        $visitor = null;
        if ($visitorId > 0) {
            $visitor = $this->visitorDirectory->findByPerson($visitorId, $parishId);
        }

        if ($visitor === null) {
            $visitor = [
                'first_name'  => 'María',
                'last_name'   => 'García',
                'visit_date'  => date('Y-m-d'),
                'phone'       => '(555) 000-0000',
                'interests'   => '{"children":true,"music":true}',
                'parish_id'   => $parishId,
            ];
        }

        // Find next upcoming event for this parish
        $nextEvent = null;
        $today     = date('Y-m-d H:i:s');
        $nextEvent = db_connect()
            ->table('event')
            ->where('parish_id', $parishId)
            ->where('status', 'published')
            ->where('start_at >=', $today)
            ->orderBy('start_at', 'ASC')
            ->limit(1)
            ->get()
            ->getRowArray() ?: null;

        // Apply token substitution via the helper
        helper('followup_template');
        $vars = parse_template_vars($visitor, $parishSettings, $parish, $nextEvent);

        return $this->jsonResponse([
            'success'   => true,
            'subject'   => str_replace(array_keys($vars), array_values($vars), (string) ($template['subject']   ?? '')),
            'body_text' => str_replace(array_keys($vars), array_values($vars), (string) ($template['body_text'] ?? '')),
            'body_html' => str_replace(array_keys($vars), array_values($vars), (string) ($template['body_html'] ?? '')),
        ]);
    }

    // ------------------------------------------------------------------
    // Manual trigger (AJAX POST)
    // ------------------------------------------------------------------

    /**
     * Immediately dispatch a follow-up action for a visitor, bypassing
     * the scheduled execution date.
     *
     * If a pending log entry exists for the (visitor, action) pair, it is
     * marked executed. Otherwise a new log row is inserted in the
     * 'sent' state so the timeline reflects the manual send.
     */
    public function manual_trigger(int $visitorId, int $actionId)
    {
        if ($redirect = $this->requirePermission('followup.manage')) {
            return $redirect;
        }

        $parishId = (int) $this->getOperationalParishId();
        $visitor    = $this->visitorDirectory->findByPerson($visitorId, $parishId);

        if (!$visitor) {
            return $this->jsonResponse(['success' => false, 'message' => 'Visitor not found or forbidden.'], 404);
        }

        $action = $this->actionModel->find($actionId);
        if (!$action) {
            return $this->jsonResponse(['success' => false, 'message' => 'Action not found.'], 404);
        }

        $currentUserId = (int) auth()->id();

        // Prefer dispatching an existing pending row over creating a duplicate
        $logEntry = $this->logModel
            ->where('person_id', $visitorId)
            ->where('action_id', $actionId)
            ->where('status', 'pending')
            ->first();

        if (!$logEntry) {
            $logEntry = [
                'id'             => null,
                'person_id'      => $visitorId,
                'parish_id'      => $parishId,
                'action_id'      => $actionId,
                'assigned_to'    => $currentUserId,
                'scheduled_date' => date('Y-m-d'),
                'status'         => 'pending',
                'channel'        => $action['channel'],
                'template_id'    => $action['template_id'] ?? null,
                'instructions'   => $action['instructions'] ?? null,
            ];
        }

        $template = null;
        if (!empty($logEntry['template_id'])) {
            $template = $this->templateModel->find((int) $logEntry['template_id']);
        }

        $result = (new \App\Services\FollowupDispatchService())->dispatch($logEntry, is_array($template) ? $template : null, $visitor);

        if (!$result['success'] && empty($result['metadata']['skipped'])) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Dispatch failed: ' . ($result['error'] ?? 'Unknown error'),
            ], 500);
        }

        if ($logEntry['id'] !== null) {
            $logId = (int) $logEntry['id'];
            if (!empty($result['metadata']['skipped'])) {
                $this->logModel->markSkipped($logId, array_merge(
                    $result['metadata'],
                    ['triggered_by' => $currentUserId, 'manual' => true]
                ));
            } else {
                $this->logModel->markExecuted($logId, array_merge(
                    $result['metadata'],
                    ['triggered_by' => $currentUserId, 'manual' => true]
                ));
            }
        } else {
            // Create a log row reflecting the manual dispatch.
            $logId = (int) $this->logModel->insert([
                'person_id'         => $visitorId,
                'parish_id'         => $parishId,
                'action_id'         => $actionId,
                'assigned_to'       => $currentUserId,
                'scheduled_date'    => date('Y-m-d'),
                'executed_at'       => date('Y-m-d H:i:s'),
                'status'            => !empty($result['metadata']['skipped']) ? 'skipped' : 'sent',
                'response_metadata' => json_encode(array_merge(
                    $result['metadata'],
                    ['triggered_by' => $currentUserId, 'manual' => true]
                )),
            ]);
        }

        $this->audit()->log('followup.manual_trigger', [
            'parish_id'   => $parishId,
            'entity_type' => 'followup_log',
            'entity_id'   => $logId,
            'meta'        => [
                'visitor_id'  => $visitorId,
                'action_id' => $actionId,
                'channel'   => $action['channel'],
            ],
        ]);

        return $this->jsonResponse([
            'success' => true,
            'log_id'  => $logId,
            'channel' => $action['channel'],
        ]);
    }

    // ------------------------------------------------------------------
    // Call tasks
    // ------------------------------------------------------------------

    /**
     * Display pending (and recent) call tasks for the operational parish.
     * Accepts ?status= filter (pending|completed|cancelled|all).
     */
    public function callTasks()
    {
        if ($redirect = $this->requirePermission('followup.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = $this->getOperationalParishId();
        $status   = $this->request->getGet('status') ?? 'pending';
        $status   = in_array($status, ['pending', 'completed', 'cancelled', 'all'], true) ? $status : 'pending';
        $taskType = $this->request->getGet('type');
        $taskType = in_array($taskType, ['call', 'manual'], true) ? $taskType : null;

        /** @var \App\Models\CallTaskModel $model */
        $model = model('CallTaskModel');

        if ($status === 'pending') {
            $tasks = $model->getPendingForParish($parishId, $taskType);
        } elseif ($status === 'all') {
            $tasks = $model->getAllForParish($parishId, 100, $taskType);
        } else {
            // Status filtered in SQL — filtering after LIMIT would hide
            // recent completions once the parish passes 100 tasks.
            $tasks = $model->getByStatusForParish($parishId, $status, 100, $taskType);
        }

        return view('Clergy\Views\FollowupWorkflow/call_tasks', array_merge(
            $this->getCommonViewData(lang('Visitors.pageTitleCallTasks')),
            [
                'tasks'          => $tasks,
                'activeStatus'   => $status,
                'activeType'     => $taskType,
                'pendingCount'   => $model->countPending($parishId),
            ]
        ));
    }

    /**
     * AJAX POST — mark a call task as completed.
     * Body: { notes?: string }
     */
    public function completeCallTask(int $id)
    {
        if ($redirect = $this->requirePermission('followup.manage')) {
            return $redirect;
        }

        if (!$this->request->isAJAX()) {
            return $this->jsonResponse(['success' => false, 'message' => 'AJAX only.'], 400);
        }

        /** @var \App\Models\CallTaskModel $model */
        $model = model('CallTaskModel');
        $task  = $model->find($id);

        if (!$task) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Visitors.taskNotFound')], 404);
        }

        // Same scope contract as Visitors::logMarkDone — the task must
        // belong to the operational parish and its person must still be in
        // the visitor domain there. person_id falls back to the linked log
        // for drifted rows; log_id is always set per the follow-up schema.
        $parishId  = $this->getOperationalParishId();
        $personId  = (int) ($task['person_id'] ?? 0);
        if ($personId <= 0 && !empty($task['log_id'])) {
            $logRow   = $this->logModel->find((int) $task['log_id']);
            $personId = (int) ($logRow['person_id'] ?? 0);
        }
        if ((int) $task['parish_id'] !== $parishId
            || ! $this->visitorDirectory->visitorAffiliationExists($personId, $parishId)) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Visitors.visitorNotFound')], 403);
        }

        $notes = (string) ($this->request->getPost('notes') ?? '');
        if ($notes !== '') {
            $model->update($id, ['notes' => $notes]);
        }

        if (!$model->markCompleted($id)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Update failed.'], 500);
        }

        // Resolve the linked log when the task is closed ahead of dispatch
        // ('pending') or completed on a retry ('failed') — the call is done,
        // no cron run is needed to reflect it in the follow-up timeline.
        $logId = (int) ($task['log_id'] ?? 0);
        if ($logId > 0) {
            $log = $this->logModel->find($logId);
            if (is_array($log) && in_array($log['status'] ?? '', ['pending', 'failed'], true)) {
                $this->logModel->markExecuted($logId, ['completed_via' => 'call_task']);
            }
        }

        $this->audit()->log('followup.call_task_completed', ['call_task_id' => $id]);

        return $this->jsonResponse(['success' => true, 'task_id' => $id]);
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    /**
     * Replace {placeholder} tokens in a template string with sample values.
     */
    private function applyTemplateVars(string $text, array $vars): string
    {
        return str_replace(array_keys($vars), array_values($vars), $text);
    }
}
