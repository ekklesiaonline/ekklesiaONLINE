<?php

namespace App\Controllers;

class SystemOverview extends BaseController
{
    public function index()
    {
        if ($redirect = $this->requirePermission('system.admin')) {
            return $redirect;
        }

        if (! $this->isSystemAdmin()) {
            return $this->forbiddenResponse(lang('App.errorForbidden'));
        }

        $db = db_connect();

        $totalParishes = (int) $db->table('parish')->countAllResults();
        $recentActivity = $this->localizeRecentNetworkActivity($this->getRecentNetworkActivity(30));
        $systemHealth = $this->getSystemHealthMetrics();

        $recentActivityCount7d = 0;
        foreach ($recentActivity as $item) {
            if (! empty($item['happened_at']) && strtotime((string) $item['happened_at']) >= strtotime('-7 days')) {
                $recentActivityCount7d++;
            }
        }

        $recentAuditCount7d = 0;
        if ($db->tableExists('audit_log')) {
            $recentAuditCount7d = (int) $db->table('audit_log')
                ->where('created_at >=', date('Y-m-d H:i:s', strtotime('-7 days')))
                ->countAllResults();
        }

        $data = array_merge($this->getCommonViewData(lang('App.navSystemOverview')), [
            'total_parishes' => $totalParishes,
            'recent_activity' => $recentActivity,
            'recent_audit_logs' => [],
            'system_health' => $systemHealth,
            'recent_activity_count_7d' => $recentActivityCount7d,
            'recent_audit_count_7d' => $recentAuditCount7d,
        ]);

        return view('System/system-overview', $data);
    }

    protected function getRecentNetworkActivity(int $limit = 30): array
    {
        $db = db_connect();

        $sql = "
            SELECT *
            FROM (
                SELECT 'system_admin' AS activity_type, u.created_at AS happened_at, 
                    'System' AS parish_name, 
                    CONCAT(u.username, ' (systemadmin)') AS activity_subject, 
                    NULL AS amount
                FROM users u
                JOIN auth_groups_users agu ON agu.user_id = u.id
                WHERE u.created_at IS NOT NULL
                AND agu.group = 'systemadmin'

                UNION ALL

                SELECT 'province_admin' AS activity_type, u.created_at AS happened_at, 
                    'System' AS parish_name, 
                    CONCAT(u.username, ' (provinceadmin)') AS activity_subject, 
                    NULL AS amount
                FROM users u
                JOIN auth_groups_users agu ON agu.user_id = u.id
                WHERE u.created_at IS NOT NULL
                AND agu.group = 'provinceadmin'
            ) feed
            ORDER BY happened_at DESC
            LIMIT " . (int) $limit;

        return $db->query($sql)->getResultArray();
    }

    protected function localizeRecentNetworkActivity(array $rows): array
    {
        foreach ($rows as &$row) {
            $type = (string) ($row['activity_type'] ?? '');
            $subject = trim((string) ($row['activity_subject'] ?? ''));
            $subject = $subject !== '' ? $subject : lang('Dashboard.systemOverviewActivityFallback');

            switch ($type) {
                case 'system_admin':
                    $row['title'] = lang('Dashboard.systemOverviewActivitySystemAdminCreated', [$subject]);
                    break;
                case 'province_admin':
                    $row['title'] = lang('Dashboard.systemOverviewActivityProvinceAdminCreated', [$subject]);
                    break;
                default:
                    $row['title'] = lang('Dashboard.systemOverviewActivityFallback');
                    break;
            }
        }
        unset($row);

        return $rows;
    }

    protected function getSystemHealthMetrics(): array
    {
        $db = db_connect();

        $hasAuditTable = $db->tableExists('audit_log');

        $tableCounts = [
            'parishes' => (int) $db->table('parish')->countAllResults(),
            'donations' => (int) $db->table('donation')->countAllResults(),
            'expenses' => (int) $db->table('expense')->countAllResults(),
            'audit_logs' => $hasAuditTable ? (int) $db->table('audit_log')->countAllResults() : 0,
        ];

        $cutoff24h = date('Y-m-d H:i:s', strtotime('-24 hours'));
        $recent24h = [
            'parishes' => (int) $db->table('parish')->where('created_at >=', $cutoff24h)->countAllResults(),
            'donations' => (int) $db->table('donation')->where('created_at >=', $cutoff24h)->countAllResults(),
            'expenses' => (int) $db->table('expense')->where('created_at >=', $cutoff24h)->countAllResults(),
            'audit_logs' => $hasAuditTable
                ? (int) $db->table('audit_log')->where('created_at >=', $cutoff24h)->countAllResults()
                : 0,
        ];

        $databaseSizeBytes = null;
        try {
            $sizeRow = $db->query(
                "SELECT SUM(data_length + index_length) AS size_bytes
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE()"
            )->getRowArray();
            $databaseSizeBytes = (int) ($sizeRow['size_bytes'] ?? 0);
        } catch (\Throwable $e) {
            $databaseSizeBytes = null;
        }

        $writableLogsPath = WRITEPATH . 'logs';
        $logsSizeBytes = is_dir($writableLogsPath) ? $this->getDirectorySize($writableLogsPath) : 0;

        return [
            'table_counts' => $tableCounts,
            'total_records' => array_sum($tableCounts),
            'recent_24h' => $recent24h,
            'database_size_bytes' => $databaseSizeBytes,
            'database_size_human' => $databaseSizeBytes !== null ? $this->formatBytes($databaseSizeBytes) : lang('Dashboard.systemOverviewUnavailable'),
            'logs_size_bytes' => $logsSizeBytes,
            'logs_size_human' => $this->formatBytes($logsSizeBytes),
            'php_version' => PHP_VERSION,
            'memory_limit' => (string) ini_get('memory_limit'),
        ];
    }

    protected function getDirectorySize(string $path): int
    {
        $totalSize = 0;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->isFile()) {
                $totalSize += $file->getSize();
            }
        }

        return $totalSize;
    }

    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        $i = 0;

        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return round($value, $precision) . ' ' . $units[$i];
    }
}
