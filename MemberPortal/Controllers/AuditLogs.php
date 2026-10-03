<?php

namespace MemberPortal\Controllers;

use App\Controllers\BaseController;
use App\Services\AuditLogQueryService;

class AuditLogs extends BaseController
{
    protected AuditLogQueryService $auditQuery;

    public function __construct()
    {
        $this->auditQuery = new AuditLogQueryService();
    }

    public function index()
    {
        if (!auth()->loggedIn()) {
            return redirect()->to('/login');
        }

        if (!$this->isAuditAdmin()) {
            return redirect()->to('/dashboard')->with('error', lang('App.onlyAdminAccess', [lang('App.navAuditLogs')]));
        }

        $scopeParishIds = $this->resolveScopedParishIds();
        $filters = $this->resolveFilters($scopeParishIds);

        if (!$this->hasAuditLogsTable()) {
            $data = array_merge($this->getCommonViewData(lang('App.navAuditLogs')), [
                'audit_rows' => [],
                'pager' => null,
                'audit_actions' => [],
                'audit_entity_types' => [],
                'audit_parishes' => $this->getScopedParishes($scopeParishIds),
                'filter_parish_id' => $filters['parish_id'],
                'filter_action' => $filters['action'],
                'filter_entity_type' => $filters['entity_type'],
                'filter_actor_name' => $filters['actor_name'],
                'filter_quick_filter' => $filters['quick_filter'],
                'filter_start_date' => $filters['start_date'],
                'filter_end_date' => $filters['end_date'],
                'audit_table_missing' => true,
            ]);

            return view('MemberPortal\\Views\\Admin/audit_logs', $data);
        }

        $auditModel = model('AuditLogModel');
        $profileTable = $this->auditQuery->profileTable();
        $builder = $auditModel->builder()
            ->select('audit_log.*, c.name AS parish_name, p.first_name AS actor_first_name, p.last_name AS actor_last_name')
            ->join('parish c', 'c.id = audit_log.parish_id', 'left')
            ->join($profileTable . ' p', 'p.user_id = audit_log.user_id', 'left');

        $this->auditQuery->applyScopeFilter($builder, $this->auditScopeRole(), $scopeParishIds, (int) $filters['parish_id']);
        $this->auditQuery->applyOptionalFilters($builder, $filters, $this->isSystemAdmin());

        $rows = $auditModel
            ->orderBy('audit_log.created_at', 'DESC')
            ->paginate(40);

        $rows = array_map(function (array $row): array {
            $row['old_values_decoded'] = $this->decodeJsonField($row['old_values'] ?? null);
            $row['new_values_decoded'] = $this->decodeJsonField($row['new_values'] ?? null);
            $row['reason_text'] = $this->extractReasonText($row['old_values_decoded'] ?? null, $row['new_values_decoded'] ?? null);
            $row['action_formatted'] = $this->formatActionName($row['action'] ?? '');
            $row['is_security_event'] = $this->isSecurityEvent($row['action'] ?? '');
            return $row;
        }, $rows);

        $data = array_merge($this->getCommonViewData(lang('App.navAuditLogs')), [
            'audit_rows' => $rows,
            'pager' => $auditModel->pager,
            'audit_actions' => $this->auditQuery->availableActions($this->auditScopeRole(), $scopeParishIds, (int) $filters['parish_id']),
            'audit_entity_types' => $this->auditQuery->availableEntityTypes($this->auditScopeRole(), $scopeParishIds, (int) $filters['parish_id']),
            'audit_parishes' => $this->getScopedParishes($scopeParishIds),
            'filter_parish_id' => $filters['parish_id'],
            'filter_action' => $filters['action'],
            'filter_entity_type' => $filters['entity_type'],
            'filter_actor_name' => $filters['actor_name'],
            'filter_quick_filter' => $filters['quick_filter'],
            'filter_start_date' => $filters['start_date'],
            'filter_end_date' => $filters['end_date'],
            'audit_table_missing' => false,
        ]);

        return view('MemberPortal\Views\Admin/audit_logs', $data);
    }

    public function actorAutocomplete()
    {
        if (!auth()->loggedIn()) {
            return $this->response->setJSON(['error' => 'Unauthorized'], 401);
        }

        if (!$this->isAuditAdmin()) {
            return $this->response->setJSON(['error' => 'Forbidden'], 403);
        }

        $query = trim((string) ($this->request->getGet('q') ?? ''));
        if (strlen($query) < 2) {
            return $this->response->setJSON([]);
        }

        $scopeParishIds = $this->resolveScopedParishIds();
        $results = $this->auditQuery->actorSuggestions(
            $query,
            $this->auditScopeRole(),
            $scopeParishIds,
            (int) ($this->request->getGet('parish_id') ?? 0)
        );

        $actors = array_map(function (array $row): array {
            $name = trim((string) (($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')));
            return [
                'id' => (int) ($row['user_id'] ?? 0),
                'name' => $name !== '' ? $name : 'User #' . ($row['user_id'] ?? 0),
            ];
        }, $results);

        return $this->response->setJSON($actors);
    }

    public function loadMore()
    {
        if (!auth()->loggedIn()) {
            return $this->response->setJSON(['error' => 'Unauthorized'], 401);
        }

        if (!$this->isAuditAdmin()) {
            return $this->response->setJSON(['error' => 'Forbidden'], 403);
        }

        $scopeParishIds = $this->resolveScopedParishIds();
        $filters = $this->resolveFilters($scopeParishIds);
        $lastId = (int) ($this->request->getGet('last_id') ?? 0);

        if (!$this->hasAuditLogsTable()) {
            return $this->response->setJSON(['rows' => [], 'has_more' => false]);
        }

        $builder = $this->auditQuery->baseBuilder();

        $this->auditQuery->applyScopeFilter($builder, $this->auditScopeRole(), $scopeParishIds, (int) $filters['parish_id']);
        $this->auditQuery->applyOptionalFilters($builder, $filters, $this->isSystemAdmin());

        if ($lastId > 0) {
            $builder->where('audit_log.id <', $lastId);
        }

        $rows = $builder
            ->orderBy('audit_log.id', 'DESC')
            ->limit(20)
            ->get()
            ->getResultArray();

        $rows = array_map(function (array $row): array {
            $row['old_values_decoded'] = $this->decodeJsonField($row['old_values'] ?? null);
            $row['new_values_decoded'] = $this->decodeJsonField($row['new_values'] ?? null);
            $row['reason_text'] = $this->extractReasonText($row['old_values_decoded'] ?? null, $row['new_values_decoded'] ?? null);
            $row['action_formatted'] = $this->formatActionName($row['action'] ?? '');
            $row['is_security_event'] = $this->isSecurityEvent($row['action'] ?? '');
            return $row;
        }, $rows);

        $hasMore = count($rows) === 20;

        return $this->response->setJSON([
            'rows' => $rows,
            'has_more' => $hasMore,
        ]);
    }

    /**
     * Return the decoded old/new value payloads for one audit row.
     * The row must be inside the caller's audit scope — reuses the same
     * scope filter as the index listing so visibility matches exactly.
     */
    public function diff($id)
    {
        if (!auth()->loggedIn()) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'status'  => 'error',
                'message' => 'Unauthorized',
                'error'   => ['code' => 'unauthorized', 'message' => 'Unauthorized', 'details' => []],
            ]);
        }

        if (!$this->isAuditAdmin()) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'status'  => 'error',
                'message' => 'Forbidden',
                'error'   => ['code' => 'forbidden', 'message' => 'Forbidden', 'details' => []],
            ]);
        }

        $auditId = (int) $id;

        if ($auditId <= 0 || !$this->hasAuditLogsTable()) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'status'  => 'error',
                'message' => 'Not found',
                'error'   => ['code' => 'not_found', 'message' => 'Not found', 'details' => []],
            ]);
        }

        $builder = $this->auditQuery->baseBuilder()
            ->where('audit_log.id', $auditId);

        // System admins can drill into any parish from the index, so a
        // single-row diff is permitted for any audit row. Other roles reuse
        // the listing scope filter (drill=0 applies the base role scope).
        if (!$this->isSystemAdmin()) {
            $this->auditQuery->applyScopeFilter(
                $builder,
                $this->auditScopeRole(),
                $this->resolveScopedParishIds(),
                0
            );
        }

        $row = $builder->get()->getRowArray();

        if (empty($row)) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'status'  => 'error',
                'message' => 'Not found',
                'error'   => ['code' => 'not_found', 'message' => 'Not found', 'details' => []],
            ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'status'  => 'success',
            'message' => 'OK',
            'data'    => [
                'old_values' => $this->decodeJsonField($row['old_values'] ?? null),
                'new_values' => $this->decodeJsonField($row['new_values'] ?? null),
            ],
            'meta'    => ['http_status' => 200, 'ts' => date(DATE_ATOM)],
        ]);
    }

    public function statistics()
    {
        if (!auth()->loggedIn()) {
            return $this->response->setJSON(['error' => 'Unauthorized'], 401);
        }

        if (!$this->isAuditAdmin()) {
            return $this->response->setJSON(['error' => 'Forbidden'], 403);
        }

        $scopeParishIds = $this->resolveScopedParishIds();
        $filters = $this->resolveFilters($scopeParishIds);
        $timeRange = (string) ($this->request->getGet('time_range') ?? '24h');

        if (!$this->hasAuditLogsTable()) {
            return $this->response->setJSON([
                'today_events' => 0,
                'security_events' => 0,
                'most_active_users' => [],
                'top_operations' => [],
            ]);
        }

        $stats = $this->auditQuery->statistics(
            $this->auditScopeRole(),
            $scopeParishIds,
            (int) $filters['parish_id'],
            $timeRange
        );

        return $this->response->setJSON([
            'today_events' => $stats['today_events'],
            'security_events' => $stats['security_events'],
            'most_active_users' => array_map(function (array $row): array {
                $name = trim((string) (($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')));
                return [
                    'name' => $name !== '' ? $name : 'Unknown',
                    'count' => (int) ($row['event_count'] ?? 0),
                ];
            }, $stats['most_active_users']),
            'top_operations' => array_map(function (array $row): array {
                $actionFormatted = $this->formatActionName((string) ($row['action'] ?? ''));
                $actionDisplay = !empty($actionFormatted)
                    ? ($actionFormatted['scope'] ?? '') . ' ' . ($actionFormatted['entity'] ?? '') . ' ' . ($actionFormatted['action'] ?? '')
                    : (string) ($row['action'] ?? '');
                return [
                    'action' => $actionDisplay,
                    'count' => (int) ($row['event_count'] ?? 0),
                ];
            }, $stats['top_operations']),
        ]);
    }

    public function realtimeUpdates()
    {
        if (!auth()->loggedIn()) {
            return $this->response->setJSON(['error' => 'Unauthorized'], 401);
        }

        if (!$this->isAuditAdmin()) {
            return $this->response->setJSON(['error' => 'Forbidden'], 403);
        }

        $lastId = (int) ($this->request->getGet('last_id') ?? 0);
        $scopeParishIds = $this->resolveScopedParishIds();

        if (!$this->hasAuditLogsTable()) {
            return $this->response->setJSON(['new_events' => [], 'has_more' => false]);
        }

        $builder = $this->auditQuery->baseBuilder()
            ->where('audit_log.id >', $lastId)
            ->orderBy('audit_log.id', 'ASC')
            ->limit(10);

        $this->auditQuery->applyScopeFilter(
            $builder,
            $this->auditScopeRole(),
            $scopeParishIds,
            (int) ($this->request->getGet('parish_id') ?? 0)
        );

        $rows = $builder->get()->getResultArray();

        $rows = array_map(function (array $row): array {
            $row['old_values_decoded'] = $this->decodeJsonField($row['old_values'] ?? null);
            $row['new_values_decoded'] = $this->decodeJsonField($row['new_values'] ?? null);
            $row['reason_text'] = $this->extractReasonText($row['old_values_decoded'] ?? null, $row['new_values_decoded'] ?? null);
            $row['action_formatted'] = $this->formatActionName($row['action'] ?? '');
            $row['is_security_event'] = $this->isSecurityEvent($row['action'] ?? '');
            return $row;
        }, $rows);

        $hasMore = count($rows) === 10;

        return $this->response->setJSON([
            'new_events' => $rows,
            'has_more' => $hasMore,
        ]);
    }

    protected function isAuditAdmin(): bool
    {
        if ($this->isSystemAdmin()) {
            return true;
        }

        return $this->isProvinceAdmin()
            || $this->isDioceseAdmin()
            || (bool) ($this->user && $this->user->inGroup('parishadmin'));
    }

    /**
     * Scope role for audit queries: system admins see global events,
     * province/diocese admins see their subtree, everyone else is a
     * parish admin limited to the operational parish.
     */
    protected function auditScopeRole(): string
    {
        if ($this->isSystemAdmin()) {
            return 'system';
        }
        if ($this->isProvinceAdmin()) {
            return 'province';
        }
        if ($this->isDioceseAdmin()) {
            return 'diocese';
        }
        return 'parish';
    }

    /**
     * @return array<int>
     */
    protected function resolveScopedParishIds(): array
    {
        return $this->auditQuery->scopedParishIds(
            $this->auditScopeRole(),
            $this->isProvinceAdmin() ? $this->resolveAdminProvinceId() : 0,
            $this->isDioceseAdmin() ? $this->resolveDiocesanScopeId() : 0,
            $this->getOperationalParishId()
        );
    }

    protected function resolveFilters(array $scopeParishIds): array
    {
        $selectedParishId = (int) ($this->request->getGet('parish_id') ?? 0);
        if (!$this->isSystemAdmin() && $selectedParishId > 0 && !in_array($selectedParishId, $scopeParishIds, true)) {
            $selectedParishId = 0;
        }

        return [
            'parish_id' => $selectedParishId,
            'action' => trim((string) ($this->request->getGet('action') ?? '')),
            'entity_type' => trim((string) ($this->request->getGet('entity_type') ?? '')),
            'actor_name' => trim((string) ($this->request->getGet('actor_name') ?? '')),
            'quick_filter' => trim((string) ($this->request->getGet('quick_filter') ?? '')),
            'start_date' => $this->normalizeDate((string) ($this->request->getGet('start_date') ?? '')),
            'end_date' => $this->normalizeDate((string) ($this->request->getGet('end_date') ?? '')),
        ];
    }

    protected function getScopedParishes(array $scopeParishIds): array
    {
        return $this->auditQuery->scopedParishes($this->isSystemAdmin(), $scopeParishIds);
    }

    protected function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }

    protected function decodeJsonField($value)
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    protected function toCsvSafeValue($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_string($value)) {
            return $value;
        }

        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json === false ? '' : $json;
    }

    protected function extractReasonText($oldValues, $newValues): ?string
    {
        $candidates = [];

        if (is_array($newValues)) {
            $candidates[] = $newValues['delete_reason'] ?? null;
            $candidates[] = $newValues['reason'] ?? null;
            if (isset($newValues['meta']) && is_array($newValues['meta'])) {
                $candidates[] = $newValues['meta']['reason'] ?? null;
            }
            if (isset($newValues['__meta']) && is_array($newValues['__meta'])) {
                $candidates[] = $newValues['__meta']['reason'] ?? null;
            }
        }

        if (is_array($oldValues)) {
            $candidates[] = $oldValues['delete_reason'] ?? null;
            $candidates[] = $oldValues['reason'] ?? null;
            if (isset($oldValues['meta']) && is_array($oldValues['meta'])) {
                $candidates[] = $oldValues['meta']['reason'] ?? null;
            }
            if (isset($oldValues['__meta']) && is_array($oldValues['__meta'])) {
                $candidates[] = $oldValues['__meta']['reason'] ?? null;
            }
        }

        foreach ($candidates as $candidate) {
            if (is_string($candidate)) {
                $reason = trim($candidate);
                if ($reason !== '') {
                    return $reason;
                }
            }
        }

        return null;
    }

    protected function hasAuditLogsTable(): bool
    {
        return $this->auditQuery->tableExists();
    }

    protected function formatActionName(string $action): array
    {
        $parts = explode('.', $action);
        $scope = $parts[0] ?? 'unknown';
        $entity = $parts[1] ?? 'unknown';
        $actionType = $parts[2] ?? 'unknown';

        $scopeLabels = [
            'system' => lang('App.auditScopeSystem'),
            'province' => lang('App.auditScopeProvince'),
            'diocese' => lang('App.auditScopeDiocese'),
            'parish' => lang('App.auditScopeParish'),
            'member' => lang('App.auditScopeMember'),
            'family' => lang('App.auditScopeFamily'),
            'donation' => lang('App.auditScopeDonation'),
            'expense' => lang('App.auditScopeExpense'),
            'service' => lang('App.auditScopeService'),
            'permission' => lang('App.auditScopePermission'),
            'scope' => lang('App.auditScopeScope'),
            'portal_access' => lang('App.auditScopePortalAccess'),
            'non_portal_member' => lang('App.auditScopeNonPortalMember'),
            'parochial_report' => lang('App.auditScopeParochialReport'),
            'annual_pledge' => lang('App.auditScopeAnnualPledge'),
            'people' => lang('App.auditScopePeople'),
            'family_member' => lang('App.auditScopeFamilyMember'),
            'liturgical_entry' => lang('App.auditScopeLiturgicalEntry'),
            'chart_account' => lang('App.auditScopeChartAccount'),
        ];

        $actionLabels = [
            'created' => lang('App.auditActionCreated'),
            'updated' => lang('App.auditActionUpdated'),
            'deleted' => lang('App.auditActionDeleted'),
            'toggled' => lang('App.auditActionToggled'),
            'denied' => lang('App.auditActionDenied'),
            'failed' => lang('App.auditActionFailed'),
            'completed' => lang('App.auditActionCompleted'),
            'sent' => lang('App.auditActionSent'),
            'password_failed' => lang('App.auditActionPasswordFailed'),
            'otp_failed' => lang('App.auditActionOtpFailed'),
            'otp_sent' => lang('App.auditActionOtpSent'),
            'hard_delete' => lang('App.auditActionHardDelete'),
            'merge' => lang('App.auditActionMerge'),
            'bulk_delete' => lang('App.auditActionBulkDelete'),
            'create' => lang('App.auditActionCreated'),
            'update' => lang('App.auditActionUpdated'),
            'delete' => lang('App.auditActionDeleted'),
        ];

        $badgeClasses = [
            'system' => 'danger',
            'province' => 'warning',
            'diocese' => 'info',
            'parish' => 'success',
            'member' => 'primary',
            'family' => 'secondary',
            'donation' => 'success',
            'expense' => 'danger',
            'service' => 'info',
            'permission' => 'warning',
            'scope' => 'warning',
            'portal_access' => 'primary',
            'non_portal_member' => 'secondary',
            'parochial_report' => 'info',
            'annual_pledge' => 'success',
            'people' => 'primary',
            'family_member' => 'secondary',
            'liturgical_entry' => 'info',
            'chart_account' => 'primary',
        ];

        $actionIcons = [
            'created' => 'plus-circle',
            'updated' => 'pencil',
            'deleted' => 'trash',
            'toggled' => 'arrow-repeat',
            'denied' => 'x-circle',
            'failed' => 'exclamation-triangle',
            'completed' => 'check-circle',
            'sent' => 'send',
            'password_failed' => 'lock',
            'otp_failed' => 'shield-x',
            'otp_sent' => 'shield-check',
            'hard_delete' => 'trash3',
            'merge' => 'union',
            'bulk_delete' => 'trash2',
            'create' => 'plus-circle',
            'update' => 'pencil',
            'delete' => 'trash',
        ];

        return [
            'scope' => $scopeLabels[$scope] ?? ucfirst($scope),
            'entity' => ucfirst(str_replace('_', ' ', $entity)),
            'action' => $actionLabels[$actionType] ?? ucfirst(str_replace('_', ' ', $actionType)),
            'badge_class' => $badgeClasses[$scope] ?? 'secondary',
            'icon' => $actionIcons[$actionType] ?? 'activity',
        ];
    }

    protected function isSecurityEvent(string $action): bool
    {
        $securityActions = ['permission.denied', 'scope.denied', 'system.primary_transfer.password_failed', 'system.primary_transfer.otp_failed'];
        return in_array($action, $securityActions, true);
    }
}
