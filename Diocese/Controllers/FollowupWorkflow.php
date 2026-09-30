<?php

namespace Diocese\Controllers;

use App\Controllers\UpstreamFollowupWorkflow;

/**
 * FollowupWorkflow (Diocesan)
 *
 * Diocese-scoped follow-up sequences and templates, managed by diocesan
 * admins (permission: diocese.admin) for the diocese resolved through
 * BaseController::resolveDiocesanScopeId(). Diocesan sequences act as
 * publishable defaults: the apply-default cascade clones them into parishes
 * of the diocese that lack their own default, and edits propagate into
 * non-customized parish clones via FollowupPropagationService.
 */
class FollowupWorkflow extends UpstreamFollowupWorkflow
{
    protected function permissionName(): string
    {
        return 'diocese.admin';
    }

    protected function routeBase(): string
    {
        return 'diocese/followup-workflow';
    }

    protected function viewLayout(): string
    {
        return 'Diocese\Views\layouts/main';
    }

    protected function applyManagedScope($query)
    {
        return $query->where('diocese_id', $this->dioceseScopeId());
    }

    protected function canManageRow(array $row): bool
    {
        $scopeId = $this->dioceseScopeId();

        return $scopeId > 0
            && ($row['parish_id'] ?? null) === null
            && (int) ($row['diocese_id'] ?? 0) === $scopeId;
    }

    protected function resolveScopeAssignment(): ?array
    {
        $scopeId = $this->dioceseScopeId();

        return $scopeId > 0 ? ['diocese_id' => $scopeId] : null;
    }

    /**
     * Diocesan admins see their own rows plus global sequences (read-only)
     * so they can clone the system default into their diocese.
     */
    protected function alsoListScopes($query)
    {
        return $query->orGroupStart()
            ->where('parish_id IS NULL', null, false)
            ->where('diocese_id IS NULL', null, false)
            ->groupEnd();
    }

    protected function cloneTargetScope(): ?array
    {
        $scopeId = $this->dioceseScopeId();

        return $scopeId > 0 ? ['diocese_id' => $scopeId] : null;
    }

    protected function scopeViewData(): array
    {
        $scopeId = $this->dioceseScopeId();
        $name    = '';

        if ($scopeId > 0) {
            $row  = $this->sequenceModel->db->table('diocese')
                ->select('name')
                ->where('id', $scopeId)
                ->get()
                ->getRowArray();
            $name = (string) ($row['name'] ?? '');
        }

        return [
            'dioceseName' => $name,
            'scopeMode'   => 'diocese',
            'backUrl'     => 'diocese/dashboard',
        ];
    }

    /**
     * Actions on a diocesan sequence may reference global templates or
     * templates owned by the same diocese.
     */
    protected function templatesForSequence(array $sequence): array
    {
        $dioceseId = (int) ($sequence['diocese_id'] ?? $this->dioceseScopeId());

        return $this->templateModel
            ->where('parish_id IS NULL', null, false)
            ->groupStart()
                ->where('diocese_id IS NULL', null, false)
                ->orWhere('diocese_id', $dioceseId)
            ->groupEnd()
            ->orderBy('channel', 'ASC')
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    protected function templatesForNewSequence(): array
    {
        return $this->templatesForSequence(['diocese_id' => $this->dioceseScopeId()]);
    }

    protected function markScopedDefault(array $sequence): bool
    {
        return $this->sequenceService->setDioceseDefaultSequence(
            (int) $sequence['id'],
            (int) $sequence['diocese_id']
        );
    }

    protected function scopeAuditMeta(array $row): array
    {
        return ['scope' => 'diocese', 'diocese_id' => (int) ($row['diocese_id'] ?? $this->dioceseScopeId())];
    }

    private function dioceseScopeId(): int
    {
        return $this->resolveDiocesanScopeId();
    }
}
