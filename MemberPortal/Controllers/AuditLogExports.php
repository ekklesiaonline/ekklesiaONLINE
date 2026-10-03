<?php

namespace MemberPortal\Controllers;

/**
 * AuditLogExports
 *
 * Audit-log export endpoints: CSV, JSON, and PDF. Extends AuditLogs to
 * reuse the shared scope/filter/format helpers. Extracted from AuditLogs
 * (phase 4 split); routes and behavior unchanged.
 */
class AuditLogExports extends AuditLogs
{

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

        $builder = $this->auditQuery->baseBuilder();

        $this->auditQuery->applyScopeFilter($builder, $this->auditScopeRole(), $scopeParishIds, (int) $filters['parish_id']);
        $this->auditQuery->applyOptionalFilters($builder, $filters, $this->isSystemAdmin());

        $query = $builder
            ->orderBy('audit_log.created_at', 'DESC')
            ->get();

        // Stream rows unbuffered into php://temp (spills to disk past 2 MB)
        // so peak memory stays bounded regardless of audit table size.
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

        $maxRows = 50000;
        $rowCount = 0;
        while ($rowCount < $maxRows && is_array($row = $query->getUnbufferedRow('array'))) {
            $actorName = trim((string) (($row['actor_first_name'] ?? '') . ' ' . ($row['actor_last_name'] ?? '')));
            if ($actorName === '') {
                $actorName = lang('App.auditUserFallback', [(int) ($row['user_id'] ?? 0)]);
            }

            fputcsv($handle, [
                (string) ($row['created_at'] ?? ''),
                (string) ($row['parish_name'] ?? ''),
                $actorName,
                (string) ($row['action'] ?? ''),
                (string) ($row['entity_type'] ?? ''),
                (string) ($row['entity_id'] ?? ''),
                (string) ($row['ip_address'] ?? ''),
                (string) ($row['user_agent'] ?? ''),
                $this->toCsvSafeValue($row['old_values'] ?? null),
                $this->toCsvSafeValue($row['new_values'] ?? null),
            ]);
            $rowCount++;
        }

        $query->freeResult();
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

        $builder = $this->auditQuery->baseBuilder();

        $this->auditQuery->applyScopeFilter($builder, $this->auditScopeRole(), $scopeParishIds, (int) $filters['parish_id']);
        $this->auditQuery->applyOptionalFilters($builder, $filters, $this->isSystemAdmin());

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

        $builder = $this->auditQuery->baseBuilder();

        $this->auditQuery->applyScopeFilter($builder, $this->auditScopeRole(), $scopeParishIds, (int) $filters['parish_id']);
        $this->auditQuery->applyOptionalFilters($builder, $filters, $this->isSystemAdmin());

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
                <td>' . esc((string) ($row['parish_name'] ?? '-')) . '</td>
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
}
