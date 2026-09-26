<?php

namespace App\Controllers;

use App\Models\FollowupSequenceModel;
use App\Services\FollowupPropagationService;
use App\Services\FollowupSequenceService;

/**
 * UpstreamFollowupWorkflow
 *
 * Shared CRUD for non-parish follow-up sequences and templates: global
 * (managed by system admins) and diocese-scoped (managed by diocesan
 * admins). Concrete subclasses declare their scope via the abstract
 * methods; everything else — validation, propagation into downstream
 * clones, audit logging — is shared here.
 *
 * Upstream records are clone templates, not operational workflows: human
 * steps keep assigned_to = NULL and FollowupCloneService resolves the
 * assignee to the parish's primary admin at clone/propagate time.
 */
abstract class UpstreamFollowupWorkflow extends BaseController
{
    protected $sequenceModel;
    protected $actionModel;
    protected $templateModel;
    protected $sequenceService;
    protected $propagation;

    public function __construct()
    {
        $this->sequenceModel   = model('FollowupSequenceModel');
        $this->actionModel     = model('FollowupActionModel');
        $this->templateModel   = model('FollowupTemplateModel');
        $this->sequenceService = new FollowupSequenceService();
        $this->propagation     = new FollowupPropagationService();
    }

    // ------------------------------------------------------------------
    // Scope contract implemented by subclasses
    // ------------------------------------------------------------------

    /** Permission string required for every endpoint (e.g. 'system.admin'). */
    abstract protected function permissionName(): string;

    /** URL prefix for generated links (e.g. 'system/followup-workflow'). */
    abstract protected function routeBase(): string;

    /** Layout the views should extend. */
    abstract protected function viewLayout(): string;

    /**
     * Scope constraint for list queries, expressed as a closure applied to
     * a sequence/template model query (e.g. diocese_id = N).
     */
    abstract protected function applyManagedScope($query);

    /** Whether the given sequence/template row belongs to this UI's scope. */
    abstract protected function canManageRow(array $row): bool;

    /**
     * Parse the scope assignment for a save request.
     *
     * @return array{diocese_id: int|null}|null
     *         null when the requested scope is invalid.
     */
    abstract protected function resolveScopeAssignment(): ?array;

    /** Extra data passed to the views (e.g. scope name for badges). */
    abstract protected function scopeViewData(): array;

    /**
     * Additional upstream sequences listed read-only in the sequences
     * index (e.g. global rows diocesan admins may clone). Applied to the
     * listing query via OR; return the query unchanged for none.
     */
    protected function alsoListScopes($query)
    {
        return $query;
    }

    /**
     * The scope a clone should land in when a user clones an ancestor
     * sequence from this UI, or null when cloning is not offered here.
     *
     * @return array{diocese_id?: int}|null
     */
    protected function cloneTargetScope(): ?array
    {
        return null;
    }

    /**
     * Whether the given sequence is a valid clone source from this UI
     * (an ancestor scope of the clone target).
     */
    protected function isCloneableSource(array $sequence): bool
    {
        return $this->cloneTargetScope() !== null
            && FollowupSequenceModel::scopeOf($sequence) === 'global'
            && !empty($sequence['is_active']);
    }

    /** Templates usable by actions on the given sequence. */
    abstract protected function templatesForSequence(array $sequence): array;

    /**
     * Templates offered in the action modal when creating a new sequence
     * (no row exists yet). Defaults to this UI's managed scope; subclasses
     * may widen it to include ancestor templates.
     */
    protected function templatesForNewSequence(): array
    {
        return $this->applyManagedScope($this->templateModel)
            ->orderBy('channel', 'ASC')
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    /** Mark the sequence as the default for its scope. */
    abstract protected function markScopedDefault(array $sequence): bool;

    /** Audit meta fragment identifying the scope (e.g. ['scope' => 'global']). */
    abstract protected function scopeAuditMeta(array $row): array;

    // ------------------------------------------------------------------
    // Sequences
    // ------------------------------------------------------------------

    public function sequences()
    {
        if ($redirect = $this->requirePermission($this->permissionName())) {
            return $redirect;
        }

        $sequences = $this->alsoListScopes($this->applyManagedScope($this->sequenceModel))
            ->orderBy('name', 'ASC')
            ->findAll();

        foreach ($sequences as &$seq) {
            $seq['action_count'] = $this->actionModel
                ->where('sequence_id', $seq['id'])
                ->countAllResults();
            $seq['scope']      = FollowupSequenceModel::scopeOf($seq);
            $seq['is_managed'] = $this->canManageRow($seq);
        }
        unset($seq);

        return view('Clergy\Views\System\FollowupWorkflow/sequences', array_merge(
            $this->getCommonViewData(lang('Visitors.pageTitleSequences')),
            $this->scopeViewData(),
            [
                'sequences' => $sequences,
                'routeBase' => $this->routeBase(),
                'layout'    => $this->viewLayout(),
                'cloneUrl'  => $this->cloneTargetScope() !== null
                    ? $this->routeBase() . '/sequences/clone/'
                    : null,
            ]
        ));
    }

    /**
     * POST {base}/sequences/clone/:id (AJAX)
     *
     * Clone an ancestor-scope sequence (e.g. the global default) into this
     * UI's managed scope. Diocesan admins use this to seed their diocesan
     * default from the system default; the clone keeps lineage so later
     * upstream edits propagate.
     */
    public function cloneSequence(int $id)
    {
        if ($redirect = $this->requirePermission($this->permissionName())) {
            return $redirect;
        }

        $target = $this->cloneTargetScope();
        if ($target === null) {
            return $this->jsonResponse(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $sequence = $this->sequenceModel->find($id);
        if (!is_array($sequence) || !$this->isCloneableSource($sequence)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Not found or outside your scope.'], 404);
        }

        $newId = isset($target['diocese_id'])
            ? (new \App\Services\FollowupCloneService())->cloneSequenceIntoDiocese($id, (int) $target['diocese_id'])
            : null;

        if (!$newId) {
            return $this->jsonResponse(['success' => false, 'message' => 'Clone failed.'], 500);
        }

        $this->audit()->log('followup_sequence.cloned', array_merge([
            'entity_type' => 'followup_sequence',
            'entity_id'   => $newId,
            'meta'        => ['source_sequence_id' => $id],
        ], $this->scopeAuditMeta($sequence)));

        return $this->jsonResponse(['success' => true, 'sequence_id' => $newId]);
    }

    public function edit_sequence(int $id = 0)
    {
        if ($redirect = $this->requirePermission($this->permissionName())) {
            return $redirect;
        }

        $sequence = $id > 0 ? $this->sequenceModel->find($id) : null;

        if ($id > 0 && ($sequence === null || !$this->canManageRow($sequence))) {
            return redirect()->to($this->routeBase() . '/sequences')
                ->with('error', 'Sequence not found or outside your scope.');
        }

        $actions   = $id > 0 ? $this->actionModel->getBySequenceWithTemplates($id) : [];
        $templates = $sequence !== null
            ? $this->templatesForSequence($sequence)
            : $this->templatesForNewSequence();

        return view('Clergy\Views\System\FollowupWorkflow/edit_sequence', array_merge(
            $this->getCommonViewData($id > 0 ? lang('Visitors.pageTitleEditSequence', [esc($sequence['name'])]) : lang('Visitors.pageTitleNewSequence')),
            $this->scopeViewData(),
            [
                'sequence'  => $sequence,
                'actions'   => $actions,
                'templates' => $templates,
                'routeBase' => $this->routeBase(),
                'layout'    => $this->viewLayout(),
            ]
        ));
    }

    public function saveSequence(int $id = 0)
    {
        if ($redirect = $this->requirePermission($this->permissionName())) {
            return $redirect;
        }

        $rules = [
            'name'        => 'required|min_length[3]|max_length[150]',
            'description' => 'permit_empty',
            'is_active'   => 'permit_empty|in_list[0,1]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $scope = $this->resolveScopeAssignment();
        if ($scope === null) {
            return redirect()->back()->withInput()->with('errors', ['scope' => lang('Visitors.seqScopeInvalid')]);
        }

        $data = [
            'name'        => $this->request->getPost('name'),
            'description' => $this->request->getPost('description') ?: null,
            'is_active'   => (int) ($this->request->getPost('is_active') ?? 1),
            'parish_id'   => null,
            'diocese_id'  => $scope['diocese_id'],
        ];

        if ($id > 0) {
            $sequence = $this->sequenceModel->find($id);
            if (!$sequence || !$this->canManageRow($sequence)) {
                return redirect()->to($this->routeBase() . '/sequences')
                    ->with('error', 'Not found or outside your scope.');
            }
            $this->sequenceModel->update($id, $data);
            $event = 'followup_sequence.updated';

            $stats = $this->propagation->onSequenceUpdated(array_merge($sequence, $data, ['id' => $id]));
        } else {
            $id    = (int) $this->sequenceModel->insert($data);
            $event = 'followup_sequence.created';
            $stats = [];
        }

        $this->audit()->log($event, array_merge([
            'entity_type' => 'followup_sequence',
            'entity_id'   => $id,
            'meta'        => $stats ? ['propagated' => $stats] : [],
        ], $this->scopeAuditMeta($data)));

        return redirect()->to($this->routeBase() . '/sequences/edit/' . $id)
            ->with('success', 'Sequence saved.');
    }

    public function toggleSequence(int $id)
    {
        if ($redirect = $this->requirePermission($this->permissionName())) {
            return $redirect;
        }

        $sequence = $this->sequenceModel->find($id);
        if (!$sequence || !$this->canManageRow($sequence)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Not found or outside your scope.'], 404);
        }

        $newState = $sequence['is_active'] ? 0 : 1;
        $update   = ['is_active' => $newState];

        // Deactivating a scoped default clears its default flag so the scope
        // does not silently lose its cascade entry.
        if ($newState === 0) {
            foreach (['is_global_default', 'is_diocese_default'] as $flag) {
                if (!empty($sequence[$flag])) {
                    $update[$flag] = 0;
                }
            }
        }

        $this->sequenceModel->update($id, $update);

        $this->audit()->log('followup_sequence.toggled', array_merge([
            'entity_type' => 'followup_sequence',
            'entity_id'   => $id,
            'meta'        => ['is_active' => $newState],
        ], $this->scopeAuditMeta($sequence)));

        return $this->jsonResponse(['success' => true, 'is_active' => $newState]);
    }

    public function deleteSequence(int $id)
    {
        if ($redirect = $this->requirePermission($this->permissionName())) {
            return $redirect;
        }

        $sequence = $this->sequenceModel->find($id);
        if (!$sequence || !$this->canManageRow($sequence)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Not found or outside your scope.'], 404);
        }

        // Clones survive: their source_sequence_id is cleared by the FK and
        // they become standalone parish sequences.
        $this->actionModel->where('sequence_id', $id)->delete();
        $this->sequenceModel->delete($id);

        $this->audit()->log('followup_sequence.deleted', array_merge([
            'entity_type' => 'followup_sequence',
            'entity_id'   => $id,
        ], $this->scopeAuditMeta($sequence)));

        return $this->jsonResponse(['success' => true]);
    }

    /**
     * Mark a sequence as the default for its scope (global / diocese),
     * dispatched by the row's own scope.
     */
    public function setUpstreamDefault(int $id)
    {
        if ($redirect = $this->requirePermission($this->permissionName())) {
            return $redirect;
        }

        $sequence = $this->sequenceModel->find($id);
        if (!$sequence || !$this->canManageRow($sequence)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Not found or outside your scope.'], 404);
        }

        if (!$sequence['is_active']) {
            return $this->jsonResponse(['success' => false, 'message' => 'Cannot set an inactive sequence as default.'], 422);
        }

        if (!$this->markScopedDefault($sequence)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to update default.'], 500);
        }

        $this->audit()->log('followup_sequence.set_default', array_merge([
            'entity_type' => 'followup_sequence',
            'entity_id'   => $id,
        ], $this->scopeAuditMeta($sequence)));

        return $this->jsonResponse(['success' => true]);
    }

    // ------------------------------------------------------------------
    // Actions
    // ------------------------------------------------------------------

    /**
     * Create or update a step on an upstream sequence (AJAX POST).
     *
     * assigned_to is always NULL at upstream scope — FollowupCloneService /
     * FollowupPropagationService resolve call/manual assignees to each
     * parish's primary admin. template_id must be usable at the sequence's
     * scope and match the action channel.
     */
    public function saveAction(int $id = 0)
    {
        if ($redirect = $this->requirePermission($this->permissionName())) {
            return $redirect;
        }

        $sequenceId = (int) $this->request->getPost('sequence_id');
        $sequence   = $this->sequenceModel->find($sequenceId);

        if (!$sequence || !$this->canManageRow($sequence)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Not found or outside your scope.'], 404);
        }

        $isManual = $this->request->getPost('channel') === 'manual';

        $rules = [
            'week_number'  => 'required|is_natural_no_zero|less_than_equal_to[52]',
            'day_offset'   => 'required|is_natural|less_than_equal_to[365]',
            'channel'      => 'required|in_list[text,email,call,mail,gift,manual]',
            'template_id'  => 'permit_empty|is_not_unique[followup_template.id]',
            'instructions' => $isManual ? 'required|max_length[255]' : 'permit_empty|max_length[255]',
        ];

        if (!$this->validate($rules)) {
            return $this->jsonResponse(['success' => false, 'errors' => $this->validator->getErrors()], 422);
        }

        $templateId = $this->request->getPost('template_id');
        $templateId = ($templateId !== '' && $templateId !== null) ? (int) $templateId : null;

        if ($templateId !== null) {
            $template = $this->templateModel->find($templateId);
            if (!$template
                || !$this->templateModel->usableAtScope($template, $sequence)
                || !$this->templateModel->channelMatches($templateId, (string) $this->request->getPost('channel'))) {
                return $this->jsonResponse([
                    'success' => false,
                    'errors'  => ['template_id' => lang('Visitors.seqTemplateChannelMismatch')],
                ], 422);
            }
        }

        $data = [
            'sequence_id'  => $sequenceId,
            'week_number'  => (int) $this->request->getPost('week_number'),
            'day_offset'   => (int) $this->request->getPost('day_offset'),
            'channel'      => (string) $this->request->getPost('channel'),
            'template_id'  => $isManual ? null : $templateId,
            'assigned_to'  => null,
            'instructions' => trim((string) $this->request->getPost('instructions')) ?: null,
        ];

        if ($id > 0) {
            $existing = $this->actionModel->find($id);
            if (!$existing || (int) $existing['sequence_id'] !== $sequenceId) {
                return $this->jsonResponse(['success' => false, 'message' => 'Not found.'], 404);
            }
            $this->actionModel->update($id, $data);
            $event = 'followup_action.updated';
            $stats = $this->propagation->onActionUpdated(array_merge($data, ['id' => $id]));
        } else {
            $id    = (int) $this->actionModel->insert($data);
            $event = 'followup_action.created';
            $stats = $this->propagation->onActionCreated(array_merge($data, ['id' => $id]));
        }

        $this->audit()->log($event, array_merge([
            'entity_type' => 'followup_action',
            'entity_id'   => $id,
            'meta'        => ['sequence_id' => $sequenceId, 'propagated' => $stats],
        ], $this->scopeAuditMeta($sequence)));

        return $this->jsonResponse([
            'success' => true,
            'actions' => $this->actionModel->getBySequenceWithTemplates($sequenceId),
        ]);
    }

    /**
     * Delete a step from an upstream sequence (AJAX POST).
     */
    public function deleteAction(int $id)
    {
        if ($redirect = $this->requirePermission($this->permissionName())) {
            return $redirect;
        }

        $action = $this->actionModel->find($id);
        if (!$action) {
            return $this->jsonResponse(['success' => false, 'message' => 'Not found.'], 404);
        }

        $sequence = $this->sequenceModel->find($action['sequence_id']);
        if (!$sequence || !$this->canManageRow($sequence)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $sequenceId = (int) $action['sequence_id'];
        $this->actionModel->delete($id);
        $stats = $this->propagation->onActionDeleted($id);

        $this->audit()->log('followup_action.deleted', array_merge([
            'entity_type' => 'followup_action',
            'entity_id'   => $id,
            'meta'        => ['sequence_id' => $sequenceId, 'propagated' => $stats],
        ], $this->scopeAuditMeta($sequence)));

        return $this->jsonResponse([
            'success' => true,
            'actions' => $this->actionModel->getBySequenceWithTemplates($sequenceId),
        ]);
    }

    // ------------------------------------------------------------------
    // Templates
    // ------------------------------------------------------------------

    public function templates()
    {
        if ($redirect = $this->requirePermission($this->permissionName())) {
            return $redirect;
        }

        $templates = $this->applyManagedScope($this->templateModel)
            ->orderBy('channel', 'ASC')
            ->orderBy('name', 'ASC')
            ->findAll();

        foreach ($templates as &$tpl) {
            $tpl['scope'] = \App\Models\FollowupTemplateModel::scopeOf($tpl);
        }
        unset($tpl);

        return view('Clergy\Views\System\FollowupWorkflow/templates', array_merge(
            $this->getCommonViewData(lang('Visitors.pageTitleTemplates')),
            $this->scopeViewData(),
            [
                'templates' => $templates,
                'routeBase' => $this->routeBase(),
                'layout'    => $this->viewLayout(),
            ]
        ));
    }

    public function saveTemplate(int $id = 0)
    {
        if ($redirect = $this->requirePermission($this->permissionName())) {
            return $redirect;
        }

        if ($id > 0) {
            $existing = $this->templateModel->find($id);
            if (!$existing || !$this->canManageRow($existing)) {
                return $this->jsonResponse(['success' => false, 'message' => 'Not found or outside your scope.'], 404);
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

        $scope = $this->resolveScopeAssignment();
        if ($scope === null) {
            return $this->jsonResponse(['success' => false, 'errors' => ['scope' => lang('Visitors.seqScopeInvalid')]], 422);
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
            'parish_id'   => null,
            'diocese_id'  => $scope['diocese_id'],
            'channel'     => $this->request->getPost('channel'),
            'name'        => $this->request->getPost('name'),
            'subject'     => $this->request->getPost('subject') ?: null,
            'body_text'   => $this->request->getPost('body_text'),
            'body_html'   => $this->request->getPost('body_html') ?: null,
            'variables'   => $variablesRaw,
        ];

        if ($id > 0) {
            $this->templateModel->update($id, $data);
            $event = 'followup_template.updated';
            $stats = $this->propagation->onTemplateUpdated(array_merge($data, ['id' => $id]));
        } else {
            $id    = (int) $this->templateModel->insert($data);
            $event = 'followup_template.created';
            $stats = [];
        }

        $this->audit()->log($event, array_merge([
            'entity_type' => 'followup_template',
            'entity_id'   => $id,
            'meta'        => $stats ? ['propagated' => $stats] : [],
        ], $this->scopeAuditMeta($data)));

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
            'success'        => true,
            'id'             => $id,
            'unknown_tokens' => $unknownTokens,
        ]);
    }

    public function deleteTemplate(int $id)
    {
        if ($redirect = $this->requirePermission($this->permissionName())) {
            return $redirect;
        }

        $template = $this->templateModel->find($id);
        if (!$template || !$this->canManageRow($template)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Not found or outside your scope.'], 404);
        }

        $this->templateModel->delete($id);
        $stats = $this->propagation->onTemplateDeleted($id);

        $this->audit()->log('followup_template.deleted', array_merge([
            'entity_type' => 'followup_template',
            'entity_id'   => $id,
            'meta'        => ['propagated' => $stats],
        ], $this->scopeAuditMeta($template)));

        return $this->jsonResponse(['success' => true]);
    }
}
