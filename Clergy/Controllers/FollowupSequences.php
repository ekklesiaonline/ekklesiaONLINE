<?php

namespace Clergy\Controllers;

use App\Controllers\BaseController;

/**
 * FollowupSequences
 *
 * Parish follow-up sequence management: listing (with upstream clone
 * suggestions), create/edit/save, toggle, default, delete, clone, and
 * per-sequence action save/delete.
 *
 * Permissions required:
 *   Read  – visitors.view
 *   Write – visitors.edit
 */
class FollowupSequences extends BaseController
{
    protected $sequenceModel;
    protected $actionModel;
    protected $templateModel;
    protected $sequenceService;

    public function __construct()
    {
        $this->sequenceModel   = model('FollowupSequenceModel');
        $this->actionModel     = model('FollowupActionModel');
        $this->templateModel   = model('FollowupTemplateModel');
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
}
