<?php

namespace App\Controllers;

class SystemDashboard extends BaseController
{
    use SystemAdminService;

    public function index()
    {
        if ($redirect = $this->requirePermission('system.admin')) {
            return $redirect;
        }

        if (!$this->isSystemAdmin()) {
            return $this->forbiddenResponse(lang('App.errorForbidden'));
        }

        $db = db_connect();

        $provinceRows = $db->table('province p')
            ->select('p.id, p.province_number, p.name, p.sort_order, COUNT(DISTINCT d.id) AS diocese_count, COUNT(DISTINCT c.id) AS church_count')
            ->join('diocese d', 'd.province_id = p.id', 'left')
            ->join('parish c', 'c.diocese_id = d.id', 'left')
            ->groupBy('p.id, p.province_number, p.name, p.sort_order')
            ->orderBy('p.sort_order', 'ASC')
            ->orderBy('p.province_number', 'ASC')
            ->get()
            ->getResultArray();

        $selectedProvinceId = (int) ($this->request->getGet('province_id') ?? 0);

        $dioceseQuery = $db->table('diocese d')
            ->select('d.id, d.name, d.province_id, p.name AS province_name, p.province_number, COUNT(DISTINCT c.id) AS church_count')
            ->join('province p', 'p.id = d.province_id', 'inner')
            ->join('parish c', 'c.diocese_id = d.id', 'left')
            ->groupBy('d.id, d.name, d.province_id, p.name, p.province_number')
            ->orderBy('p.sort_order', 'ASC')
            ->orderBy('p.province_number', 'ASC')
            ->orderBy('d.name', 'ASC');

        if ($selectedProvinceId > 0) {
            $dioceseQuery->where('d.province_id', $selectedProvinceId);
        }

        $dioceseRows = $dioceseQuery->get()->getResultArray();

        $systemAdminCount = (int) $db->table('auth_groups_users')->where('group', 'systemadmin')->countAllResults();
        $provinceAdminCount = (int) $db->table('auth_groups_users')->where('group', 'provinceadmin')->countAllResults();
        $dioceseAdminCount = (int) $db->table('auth_groups_users')->where('group', 'dioceseadmin')->countAllResults();

        $attendanceByProvince = $db->table('attendance a')
            ->select('p.id AS province_id, p.province_number, p.name AS province_name, COUNT(DISTINCT s.id) AS total_services, COUNT(CASE WHEN a.attended = 1 THEN 1 END) AS total_attendance')
            ->join('service s', 's.id = a.service_id', 'inner')
            ->join('parish c', 'c.id = s.parish_id', 'inner')
            ->join('diocese d', 'd.id = c.diocese_id', 'inner')
            ->join('province p', 'p.id = d.province_id', 'inner')
            ->groupBy('p.id, p.province_number, p.name')
            ->orderBy('p.sort_order', 'ASC')
            ->orderBy('p.province_number', 'ASC')
            ->get()
            ->getResultArray();

        $auditEventCount = 0;
        $hasAuditLogTable = $this->tableExistsSafe($db, 'audit_log');

        if ($hasAuditLogTable) {
            $primaryTransferAuditActions = [
                'system.primary_transfer.password_failed',
                'system.primary_transfer.otp_sent',
                'system.primary_transfer.otp_failed',
                'system.primary_transfer.completed',
            ];

            $auditEventCount = (int) $db->table('audit_log')
                ->whereIn('action', $primaryTransferAuditActions)
                ->countAllResults();
        }

        $totals = [
            'provinces' => count($provinceRows),
            'dioceses' => (int) $db->table('diocese')->countAllResults(),
            'parishes' => (int) $db->table('parish')->countAllResults(),
            'system_admins' => $systemAdminCount,
            'province_admins' => $provinceAdminCount,
            'diocese_admins' => $dioceseAdminCount,
        ];

        $data = array_merge($this->getCommonViewData(lang('App.headerSystemAdminDashboard')), [
            'totals' => $totals,
            'provinces' => $provinceRows,
            'dioceses' => $dioceseRows,
            'selectedProvinceId' => $selectedProvinceId,
            'attendance_by_province' => $attendanceByProvince,
            'audit_event_count' => $auditEventCount,
            'has_audit_logs_table' => $hasAuditLogTable,
        ]);

        return view('System/dashboard', $data);
    }
}
