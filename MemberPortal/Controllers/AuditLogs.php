<?php

namespace MemberPortal\Controllers;

use App\Controllers\BaseController;

class AuditLogs extends BaseController
{
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
                'audit_churches' => $this->getScopedParishes($scopeParishIds),
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
        $profileTable = $this->resolveExistingProfileTable();
        if ($profileTable === null) {
            $profileTable = 'profile';
        }
        $builder = $auditModel
            ->select('audit_log.*, c.name AS church_name, p.first_name AS actor_first_name, p.last_name AS actor_last_name')
            ->join('parish c', 'c.id = audit_log.parish_id', 'left')
            ->join($profileTable . ' p', 'p.user_id = audit_log.user_id', 'left');

        $this->applyScopeFilter($builder, $scopeParishIds);
        $this->applyOptionalFilters($builder, $filters);

        $rows = $builder
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
            'audit_actions' => $this->getAvailableActions($scopeParishIds),
            'audit_entity_types' => $this->getAvailableEntityTypes($scopeParishIds),
            'audit_churches' => $this->getScopedParishes($scopeParishIds),
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

    public function exportCsv()
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
            return redirect()->to('/admin/audit-logs')
                ->with('error', lang('App.auditLogsTableMissing'));
        }

        $profileTable = $this->resolveExistingProfileTable();
        if ($profileTable === null) {
            $profileTable = 'profile';
        }

        $builder = db_connect()->table('audit_log')
            ->select('audit_log.*, c.name AS church_name, p.first_name AS actor_first_name, p.last_name AS actor_last_name')
            ->join('parish c', 'c.id = audit_log.parish_id', 'left')
            ->join($profileTable . ' p', 'p.user_id = audit_log.user_id', 'left');

        $this->applyScopeFilter($builder, $scopeParishIds);
        $this->applyOptionalFilters($builder, $filters);

        $rows = $builder
            ->orderBy('audit_log.created_at', 'DESC')
            ->get()
            ->getResultArray();

        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, [
            lang('App.auditCsvCreatedAt'),
            lang('App.auditCsvParish'),
            lang('App.auditCsvActor'),
            lang('App.auditCsvAction'),
            lang('App.auditCsvEntityType'),
            lang('App.auditCsvEntityId'),
            lang('App.auditCsvIpAddress'),
            lang('App.auditCsvUserAgent'),
            lang('App.auditCsvOldValues'),
            lang('App.auditCsvNewValues'),
        ]);

        foreach ($rows as $row) {
            $actorName = trim((string) (($row['actor_first_name'] ?? '') . ' ' . ($row['actor_last_name'] ?? '')));
            if ($actorName === '') {
                $actorName = lang('App.auditUserFallback', [(int) ($row['user_id'] ?? 0)]);
            }

            fputcsv($handle, [
                (string) ($row['created_at'] ?? ''),
                (string) ($row['church_name'] ?? ''),
                $actorName,
                (string) ($row['action'] ?? ''),
                (string) ($row['entity_type'] ?? ''),
                (string) ($row['entity_id'] ?? ''),
                (string) ($row['ip_address'] ?? ''),
                (string) ($row['user_agent'] ?? ''),
                $this->toCsvSafeValue($row['old_values'] ?? null),
                $this->toCsvSafeValue($row['new_values'] ?? null),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $filename = 'audit_logs_' . date('Ymd_His') . '.csv';

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($csv ?: '');
    }

    public function exportJson()
    {
        if (!auth()->loggedIn()) {
            return redirect()->to('/')->with('error', lang('App.errorForbidden'));
        }

        if (!$this->isAuditAdmin()) {
            return redirect()->to('/')->with('error', lang('App.errorForbidden'));
        }

        $scopeParishIds = $this->resolveScopedParishIds();
        $filters = $this->resolveFilters($scopeParishIds);

        if (!$this->hasAuditLogsTable()) {
            return $this->response->setJSON(['error' => 'Audit logs table is missing'], 500);
        }

        $auditModel = model('AuditLogModel');
        $profileTable = $this->resolveExistingProfileTable();
        if ($profileTable === null) {
            $profileTable = 'profile';
        }

        $builder = $auditModel
            ->select('audit_log.*, c.name AS church_name, p.first_name AS actor_first_name, p.last_name AS actor_last_name')
            ->join('parish c', 'c.id = audit_log.parish_id', 'left')
            ->join($profileTable . ' p', 'p.user_id = audit_log.user_id', 'left');

        $this->applyScopeFilter($builder, $scopeParishIds);
        $this->applyOptionalFilters($builder, $filters);

        $rows = $builder
            ->orderBy('audit_log.created_at', 'DESC')
            ->limit(10000)
            ->get()
            ->getResultArray();

        $rows = array_map(function (array $row): array {
            $row['old_values_decoded'] = $this->decodeJsonField($row['old_values'] ?? null);
            $row['new_values_decoded'] = $this->decodeJsonField($row['new_values'] ?? null);
            $row['reason_text'] = $this->extractReasonText($row['old_values_decoded'] ?? null, $row['new_values_decoded'] ?? null);
            $row['action_formatted'] = $this->formatActionName($row['action'] ?? '');
            return $row;
        }, $rows);

        $filename = 'audit_logs_' . date('Ymd_His') . '.json';

        return $this->response
            ->setHeader('Content-Type', 'application/json; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody(json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]');
    }

    public function exportPdf()
    {
        if (!auth()->loggedIn()) {
            return redirect()->to('/')->with('error', lang('App.errorForbidden'));
        }

        if (!$this->isAuditAdmin()) {
            return redirect()->to('/')->with('error', lang('App.errorForbidden'));
        }

        $scopeParishIds = $this->resolveScopedParishIds();
        $filters = $this->resolveFilters($scopeParishIds);

        if (!$this->hasAuditLogsTable()) {
            return $this->response->setJSON(['error' => 'Audit logs table is missing'], 500);
        }

        $auditModel = model('AuditLogModel');
        $profileTable = $this->resolveExistingProfileTable();
        if ($profileTable === null) {
            $profileTable = 'profile';
        }

        $builder = $auditModel
            ->select('audit_log.*, c.name AS church_name, p.first_name AS actor_first_name, p.last_name AS actor_last_name')
            ->join('parish c', 'c.id = audit_log.parish_id', 'left')
            ->join($profileTable . ' p', 'p.user_id = audit_log.user_id', 'left');

        $this->applyScopeFilter($builder, $scopeParishIds);
        $this->applyOptionalFilters($builder, $filters);

        $rows = $builder
            ->orderBy('audit_log.created_at', 'DESC')
            ->limit(1000)
            ->get()
            ->getResultArray();

        $rows = array_map(function (array $row): array {
            $row['old_values_decoded'] = $this->decodeJsonField($row['old_values'] ?? null);
            $row['new_values_decoded'] = $this->decodeJsonField($row['new_values'] ?? null);
            $row['reason_text'] = $this->extractReasonText($row['old_values_decoded'] ?? null, $row['new_values_decoded'] ?? null);
            $row['action_formatted'] = $this->formatActionName($row['action'] ?? '');
            return $row;
        }, $rows);

        $html = $this->generatePdfHtml($rows);

        $filename = 'audit_logs_' . date('Ymd_His') . '.html';

        return $this->response
            ->setHeader('Content-Type', 'text/html; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($html);
    }

    private function generatePdfHtml(array $rows): string
    {
        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .badge { padding: 2px 6px; border-radius: 3px; font-size: 11px; color: white; }
        .badge-danger { background-color: #dc3545; }
        .badge-warning { background-color: #ffc107; color: #000; }
        .badge-info { background-color: #17a2b8; }
        .badge-success { background-color: #28a745; }
        .badge-secondary { background-color: #6c757d; }
        .badge-primary { background-color: #007bff; }
    </style>
</head>
<body>
    <h1>Audit Logs Report</h1>
    <p>Generated: ' . date('Y-m-d H:i:s') . '</p>
    <p>Total Records: ' . count($rows) . '</p>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Parish</th>
                <th>Actor</th>
                <th>Action</th>
                <th>Reason</th>
                <th>Entity</th>
                <th>IP Address</th>
            </tr>
        </thead>
        <tbody>';

        foreach ($rows as $row) {
            $actorName = trim((string) (($row['actor_first_name'] ?? '') . ' ' . ($row['actor_last_name'] ?? '')));
            $actorDisplay = $actorName !== '' ? $actorName : 'User #' . ($row['user_id'] ?? 0);
            
            $actionFormatted = $row['action_formatted'] ?? [];
            $actionDisplay = '';
            if (!empty($actionFormatted)) {
                $badgeClass = $actionFormatted['badge_class'] ?? 'secondary';
                $actionDisplay = '<span class="badge badge-' . $badgeClass . '">' . esc($actionFormatted['scope'] ?? '') . '</span> ' .
                    esc($actionFormatted['entity'] ?? '') . ' ' . esc($actionFormatted['action'] ?? '');
            } else {
                $actionDisplay = esc((string) ($row['action'] ?? ''));
            }

            $html .= '<tr>
                <td>' . esc((string) ($row['created_at'] ?? '')) . '</td>
                <td>' . esc((string) ($row['church_name'] ?? '-')) . '</td>
                <td>' . esc($actorDisplay) . '</td>
                <td>' . $actionDisplay . '</td>
                <td>' . esc((string) ($row['reason_text'] ?? '-')) . '</td>
                <td>' . esc((string) ($row['entity_type'] ?? '-')) . ' #' . ((int) ($row['entity_id'] ?? 0)) . '</td>
                <td>' . esc((string) ($row['ip_address'] ?? '-')) . '</td>
            </tr>';
        }

        $html .= '</tbody>
    </table>
</body>
</html>';

        return $html;
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
        $profileTable = $this->resolveExistingProfileTable();
        if ($profileTable === null) {
            $profileTable = 'profile';
        }

        $builder = db_connect()->table('audit_log')
            ->select('DISTINCT ' . $profileTable . '.user_id, ' . $profileTable . '.first_name, ' . $profileTable . '.last_name')
            ->join($profileTable, $profileTable . '.user_id = audit_log.user_id', 'inner')
            ->groupStart()
                ->like($profileTable . '.first_name', $query)
                ->orLike($profileTable . '.last_name', $query)
            ->groupEnd()
            ->groupBy($profileTable . '.user_id')
            ->limit(10);

        $this->applyScopeFilter($builder, $scopeParishIds);

        $results = $builder->get()->getResultArray();

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

        $auditModel = model('AuditLogModel');
        $profileTable = $this->resolveExistingProfileTable();
        if ($profileTable === null) {
            $profileTable = 'profile';
        }

        $builder = $auditModel
            ->select('audit_log.*, c.name AS church_name, p.first_name AS actor_first_name, p.last_name AS actor_last_name')
            ->join('parish c', 'c.id = audit_log.parish_id', 'left')
            ->join($profileTable . ' p', 'p.user_id = audit_log.user_id', 'left');

        $this->applyScopeFilter($builder, $scopeParishIds);
        $this->applyOptionalFilters($builder, $filters);

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

        $stats = $this->getAuditStatistics($scopeParishIds, $filters, $timeRange);

        return $this->response->setJSON($stats);
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

        $auditModel = model('AuditLogModel');
        $profileTable = $this->resolveExistingProfileTable();
        if ($profileTable === null) {
            $profileTable = 'profile';
        }

        $builder = $auditModel
            ->select('audit_log.*, c.name AS church_name, p.first_name AS actor_first_name, p.last_name AS actor_last_name')
            ->join('parish c', 'c.id = audit_log.parish_id', 'left')
            ->join($profileTable . ' p', 'p.user_id = audit_log.user_id', 'left')
            ->where('audit_log.id >', $lastId)
            ->orderBy('audit_log.id', 'ASC')
            ->limit(10);

        $this->applyScopeFilter($builder, $scopeParishIds);

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

    private function getAuditStatistics(array $scopeParishIds, array $filters, string $timeRange): array
    {
        $db = db_connect();
        $profileTable = $this->resolveExistingProfileTable();
        if ($profileTable === null) {
            $profileTable = 'profile';
        }

        // Calculate time range
        $timeConditions = $this->getTimeRangeConditions($timeRange);

        // Today's events count
        $todayBuilder = $db->table('audit_log');
        $this->applyScopeFilter($todayBuilder, $scopeParishIds);
        $todayBuilder->where('DATE(audit_log.created_at)', date('Y-m-d'));
        $todayEvents = $todayBuilder->countAllResults();

        // Security events count
        $securityBuilder = $db->table('audit_log');
        $this->applyScopeFilter($securityBuilder, $scopeParishIds);
        $securityBuilder->whereIn('audit_log.action', ['permission.denied', 'scope.denied']);
        if (!empty($timeConditions)) {
            $securityBuilder->where($timeConditions);
        }
        $securityEvents = $securityBuilder->countAllResults();

        // Most active users
        $usersBuilder = $db->table('audit_log')
            ->select($profileTable . '.first_name, ' . $profileTable . '.last_name, COUNT(audit_log.id) as event_count')
            ->join($profileTable, $profileTable . '.user_id = audit_log.user_id', 'inner')
            ->groupBy($profileTable . '.user_id')
            ->orderBy('event_count', 'DESC')
            ->limit(5);
        $this->applyScopeFilter($usersBuilder, $scopeParishIds);
        if (!empty($timeConditions)) {
            $usersBuilder->where($timeConditions);
        }
        $mostActiveUsers = $usersBuilder->get()->getResultArray();

        // Top operations
        $opsBuilder = $db->table('audit_log')
            ->select('action, COUNT(id) as event_count')
            ->groupBy('action')
            ->orderBy('event_count', 'DESC')
            ->limit(10);
        $this->applyScopeFilter($opsBuilder, $scopeParishIds);
        if (!empty($timeConditions)) {
            $opsBuilder->where($timeConditions);
        }
        $topOperations = $opsBuilder->get()->getResultArray();

        return [
            'today_events' => $todayEvents,
            'security_events' => $securityEvents,
            'most_active_users' => array_map(function (array $row): array {
                $name = trim((string) (($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')));
                return [
                    'name' => $name !== '' ? $name : 'Unknown',
                    'count' => (int) ($row['event_count'] ?? 0),
                ];
            }, $mostActiveUsers),
            'top_operations' => array_map(function (array $row): array {
                $actionFormatted = $this->formatActionName((string) ($row['action'] ?? ''));
                $actionDisplay = !empty($actionFormatted)
                    ? ($actionFormatted['scope'] ?? '') . ' ' . ($actionFormatted['entity'] ?? '') . ' ' . ($actionFormatted['action'] ?? '')
                    : (string) ($row['action'] ?? '');
                return [
                    'action' => $actionDisplay,
                    'count' => (int) ($row['event_count'] ?? 0),
                ];
            }, $topOperations),
        ];
    }

    private function getTimeRangeConditions(string $timeRange): array
    {
        switch ($timeRange) {
            case '24h':
                return ['audit_log.created_at >=' => date('Y-m-d H:i:s', strtotime('-24 hours'))];
            case '7d':
                return ['audit_log.created_at >=' => date('Y-m-d H:i:s', strtotime('-7 days'))];
            case '30d':
                return ['audit_log.created_at >=' => date('Y-m-d H:i:s', strtotime('-30 days'))];
            default:
                return [];
        }
    }

    private function getActivityChartData(array $scopeParishIds, string $timeRange): array
    {
        $db = db_connect();
        $timeConditions = $this->getTimeRangeConditions($timeRange);

        $builder = $db->table('audit_log')
            ->select('DATE(audit_log.created_at) as date, COUNT(id) as count')
            ->groupBy('DATE(audit_log.created_at)')
            ->orderBy('date', 'ASC');

        $this->applyScopeFilter($builder, $scopeParishIds);
        if (!empty($timeConditions)) {
            $builder->where($timeConditions);
        }

        $results = $builder->get()->getResultArray();

        return array_map(function (array $row): array {
            return [
                'date' => (string) ($row['date'] ?? ''),
                'count' => (int) ($row['count'] ?? 0),
            ];
        }, $results);
    }

    private function isAuditAdmin(): bool
    {
        if ($this->isSystemAdmin()) {
            return true;
        }

        return $this->isProvinceAdmin()
            || $this->isDioceseAdmin()
            || (bool) ($this->user && $this->user->inGroup('parishadmin'));
    }

    /**
     * @return array<int>
     */
    private function resolveScopedParishIds(): array
    {
        $db = db_connect();

        // System admins can review cross-scope audit history, including
        // global entries that are not tied to a specific parish.
        if ($this->isSystemAdmin()) {
            return [];
        }

        if ($this->isProvinceAdmin()) {
            $provinceId = $this->resolveAdminProvinceId();
            if ($provinceId <= 0) {
                return [];
            }

            $rows = $db->table('parish c')
                ->select('c.id')
                ->join('diocese d', 'd.id = c.diocese_id', 'left')
                ->where('d.province_id', $provinceId)
                ->get()
                ->getResultArray();

            return array_map(static fn(array $row): int => (int) $row['id'], $rows);
        }

        if ($this->isDioceseAdmin()) {
            $dioceseId = $this->resolveDiocesanScopeId();

            if ($dioceseId <= 0) {
                return [];
            }

            $rows = $db->table('parish')
                ->select('id')
                ->where('diocese_id', $dioceseId)
                ->get()
                ->getResultArray();

            return array_map(static fn(array $row): int => (int) $row['id'], $rows);
        }

        return [(int) ($this->getOperationalParishId() ?? 0)];
    }

    private function applyScopeFilter($builder, array $scopeParishIds): void
    {
        if ($this->isSystemAdmin()) {
            // If a specific parish is chosen, drill into all its logs.
            $drillParish = (int) ($this->request->getGet('parish_id') ?? 0);
            if ($drillParish > 0) {
                $builder->where('audit_log.parish_id', $drillParish);
                return;
            }

            // Default system-admin view: system-level events, security violations,
            // destructive operations, and global cron entries.
            // Parish-level operational noise is intentionally excluded.
            $builder->groupStart()
                ->where('audit_log.parish_id IS NULL')
                ->orLike('audit_log.action', 'system.', 'after')
                ->orWhereIn('audit_log.action', [
                    'permission.denied',
                    'scope.denied',
                    'member.hard_delete',
                    'member.merge',
                    'parish.bulk_delete',
                ])
                ->groupEnd();
            return;
        }

        $cleanScope = array_values(array_filter(array_unique(array_map('intval', $scopeParishIds)), static fn(int $id): bool => $id > 0));

        if ($cleanScope === []) {
            $builder->where('1 = 0');
            return;
        }

        $builder->whereIn('audit_log.parish_id', $cleanScope);

        if ($this->isProvinceAdmin()) {
            // Province admins see significant cross-parish events:
            // member management, parish governance, parochial reports,
            // portal access changes, and key finance create/delete events.
            $builder->groupStart()
                ->like('audit_log.action', 'member.', 'after')
                ->orLike('audit_log.action', 'parish.', 'after')
                ->orLike('audit_log.action', 'parochial_report.', 'after')
                ->orLike('audit_log.action', 'portal_access.', 'after')
                ->orLike('audit_log.action', 'non_portal_member.', 'after')
                ->orWhereIn('audit_log.action', [
                    'permission.denied',
                    'scope.denied',
                    'donation.create',
                    'donation.delete',
                    'expense.create',
                    'expense.delete',
                ])
                ->groupEnd();
            return;
        }

        if ($this->isDioceseAdmin()) {
            // Diocesan admins see member management, family, finance, parish,
            // people, reports, and portal access events from their diocese.
            $builder->groupStart()
                ->like('audit_log.action', 'member.', 'after')
                ->orLike('audit_log.action', 'parish.', 'after')
                ->orLike('audit_log.action', 'donation.', 'after')
                ->orLike('audit_log.action', 'expense.', 'after')
                ->orLike('audit_log.action', 'annual_pledge.', 'after')
                ->orLike('audit_log.action', 'family.', 'after')
                ->orLike('audit_log.action', 'family_member.', 'after')
                ->orLike('audit_log.action', 'parochial_report.', 'after')
                ->orLike('audit_log.action', 'portal_access.', 'after')
                ->orLike('audit_log.action', 'non_portal_member.', 'after')
                ->orLike('audit_log.action', 'people.', 'after')
                ->orWhereIn('audit_log.action', [
                    'permission.denied',
                    'scope.denied',
                    'service.create',
                    'service.update',
                    'service.delete',
                ])
                ->groupEnd();
            return;
        }

        // Parish admin (churchadmin): all logs for their parish — no action filter.
    }

    private function resolveFilters(array $scopeParishIds): array
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

    private function applyOptionalFilters($builder, array $filters): void
    {
        // Parish filter for system admin is already handled inside applyScopeFilter
        // (drill-down mode). For other roles it is applied via whereIn scope above,
        // so we only add an extra exact-match here for non-system scoped admins.
        if (!$this->isSystemAdmin() && (int) ($filters['parish_id'] ?? 0) > 0) {
            $builder->where('audit_log.parish_id', (int) $filters['parish_id']);
        }

        if ((string) ($filters['action'] ?? '') !== '') {
            $builder->where('audit_log.action', (string) $filters['action']);
        }

        if ((string) ($filters['entity_type'] ?? '') !== '') {
            $builder->where('audit_log.entity_type', (string) $filters['entity_type']);
        }

        if ((string) ($filters['actor_name'] ?? '') !== '') {
            $profileTable = $this->resolveExistingProfileTable();
            if ($profileTable !== null) {
                $builder->groupStart()
                    ->like($profileTable . '.first_name', (string) $filters['actor_name'])
                    ->orLike($profileTable . '.last_name', (string) $filters['actor_name'])
                    ->groupEnd();
            }
        }

        if ((string) ($filters['quick_filter'] ?? '') !== '') {
            $this->applyQuickFilter($builder, (string) $filters['quick_filter']);
        }

        if (!empty($filters['start_date'])) {
            $builder->where('DATE(audit_log.created_at) >=', (string) $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $builder->where('DATE(audit_log.created_at) <=', (string) $filters['end_date']);
        }
    }

    private function applyQuickFilter($builder, string $quickFilter): void
    {
        switch ($quickFilter) {
            case 'system_ops':
                $builder->like('audit_log.action', 'system.', 'after');
                break;
            case 'security_events':
                $builder->whereIn('audit_log.action', ['permission.denied', 'scope.denied']);
                break;
            case 'data_changes':
                $builder->groupStart()
                    ->like('audit_log.action', '.created', 'before')
                    ->orLike('audit_log.action', '.updated', 'before')
                    ->orLike('audit_log.action', '.deleted', 'before')
                    ->groupEnd();
                break;
        }
    }

    private function getAvailableActions(array $scopeParishIds): array
    {
        if (!$this->hasAuditLogsTable()) {
            return [];
        }

        $builder = db_connect()->table('audit_log')
            ->select('action')
            ->groupBy('action')
            ->orderBy('action', 'ASC');

        $this->applyScopeFilter($builder, $scopeParishIds);

        $rows = $builder->get()->getResultArray();

        return array_values(array_filter(array_map(static fn(array $row): string => (string) ($row['action'] ?? ''), $rows), static fn(string $value): bool => $value !== ''));
    }

    private function getAvailableEntityTypes(array $scopeParishIds): array
    {
        if (!$this->hasAuditLogsTable()) {
            return [];
        }

        $builder = db_connect()->table('audit_log')
            ->select('entity_type')
            ->where('entity_type IS NOT NULL')
            ->where('entity_type !=', '')
            ->groupBy('entity_type')
            ->orderBy('entity_type', 'ASC');

        $this->applyScopeFilter($builder, $scopeParishIds);

        $rows = $builder->get()->getResultArray();

        return array_values(array_filter(array_map(static fn(array $row): string => (string) ($row['entity_type'] ?? ''), $rows), static fn(string $value): bool => $value !== ''));
    }

    private function getScopedParishes(array $scopeParishIds): array
    {
        if ($this->isSystemAdmin()) {
            return db_connect()->table('parish')
                ->select('id, name')
                ->orderBy('name', 'ASC')
                ->get()
                ->getResultArray();
        }

        $cleanScope = array_values(array_filter(array_unique(array_map('intval', $scopeParishIds)), static fn(int $id): bool => $id > 0));
        if ($cleanScope === []) {
            return [];
        }

        return db_connect()->table('parish')
            ->select('id, name')
            ->whereIn('id', $cleanScope)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }

    private function decodeJsonField($value)
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    private function toCsvSafeValue($value): string
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

    private function extractReasonText($oldValues, $newValues): ?string
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

    private function hasAuditLogsTable(): bool
    {
        try {
            return db_connect()->tableExists('audit_log');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function resolveExistingProfileTable(): ?string
    {
        $db = db_connect();
        $candidate = 'profile';
        $variants = array_values(array_unique([$db->getPrefix() . $candidate, $candidate]));
        foreach ($variants as $table) {
            try {
                $columns = $db->getFieldNames($table);
                if ($columns !== []) {
                    return $table;
                }
            } catch (\Throwable $e) {
                // Try next table-name variant.
            }
        }

        return null;
    }

    private function formatActionName(string $action): array
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

    private function formatChangesDiff(?array $oldValues, ?array $newValues): array
    {
        $diff = [
            'added' => [],
            'removed' => [],
            'changed' => [],
            'unchanged' => [],
        ];

        if ($oldValues === null && $newValues === null) {
            return $diff;
        }

        if ($oldValues === null) {
            $diff['added'] = $newValues;
            return $diff;
        }

        if ($newValues === null) {
            $diff['removed'] = $oldValues;
            return $diff;
        }

        $allKeys = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));

        foreach ($allKeys as $key) {
            $oldValue = $oldValues[$key] ?? null;
            $newValue = $newValues[$key] ?? null;

            if (!array_key_exists($key, $oldValues)) {
                $diff['added'][$key] = $newValue;
            } elseif (!array_key_exists($key, $newValues)) {
                $diff['removed'][$key] = $oldValue;
            } elseif ($oldValue !== $newValue) {
                $diff['changed'][$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            } else {
                $diff['unchanged'][$key] = $oldValue;
            }
        }

        return $diff;
    }

    private function getActionBadgeClass(string $action): string
    {
        $formatted = $this->formatActionName($action);
        return $formatted['badge_class'] ?? 'secondary';
    }

    private function isSecurityEvent(string $action): bool
    {
        $securityActions = ['permission.denied', 'scope.denied', 'system.primary_transfer.password_failed', 'system.primary_transfer.otp_failed'];
        return in_array($action, $securityActions, true);
    }

    private function getActionIcon(string $action): string
    {
        $formatted = $this->formatActionName($action);
        return $formatted['icon'] ?? 'activity';
    }
}
