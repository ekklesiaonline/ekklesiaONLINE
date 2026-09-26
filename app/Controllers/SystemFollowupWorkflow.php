<?php

namespace App\Controllers;

/**
 * SystemFollowupWorkflow
 *
 * System-admin UI for the global follow-up sequence and message-template
 * records (all scope columns NULL). Diocesan records are managed by
 * diocesan admins under Diocesan\Controllers\FollowupWorkflow.
 *
 * Upstream rows are cloned into dioceses and parishes; edits propagate
 * into existing non-customized clones via FollowupPropagationService.
 */
class SystemFollowupWorkflow extends UpstreamFollowupWorkflow
{
    protected function permissionName(): string
    {
        return 'system.admin';
    }

    protected function routeBase(): string
    {
        return 'system/followup-workflow';
    }

    protected function viewLayout(): string
    {
        return 'Clergy\Views\layouts/main';
    }

    /**
     * System admins manage global rows only (all scope columns NULL).
     * Diocesan rows belong to the diocesan UI.
     */
    protected function applyManagedScope($query)
    {
        return $query
            ->where('parish_id IS NULL', null, false)
            ->where('diocese_id IS NULL', null, false);
    }

    protected function canManageRow(array $row): bool
    {
        return ($row['parish_id'] ?? null) === null
            && ($row['diocese_id'] ?? null) === null;
    }

    protected function resolveScopeAssignment(): ?array
    {
        return ['diocese_id' => null];
    }

    protected function scopeViewData(): array
    {
        return [
            'scopeMode' => 'system',
            'backUrl'   => 'system/dashboard',
        ];
    }

    /**
     * Global sequences may reference global templates only.
     */
    protected function templatesForSequence(array $sequence): array
    {
        return $this->templateModel
            ->where('parish_id IS NULL', null, false)
            ->where('diocese_id IS NULL', null, false)
            ->orderBy('channel', 'ASC')
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    protected function markScopedDefault(array $sequence): bool
    {
        return $this->sequenceService->setGlobalDefaultSequence((int) $sequence['id']);
    }

    protected function scopeAuditMeta(array $row): array
    {
        return ['scope' => 'global'];
    }
}
