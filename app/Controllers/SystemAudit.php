<?php

namespace App\Controllers;

use App\Models\AuditLogModel;
use App\Services\SystemSettingService;

class SystemAudit extends BaseController
{
    use SystemAdminService;

    private SystemSettingService $settings;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->settings = service('systemSettings');
    }

    public function index()
    {
        if ($redirect = $this->requirePermission('system.admin')) {
            return $redirect;
        }

        if (!$this->isSystemAdmin()) {
            return $this->forbiddenResponse(lang('App.errorForbidden'));
        }

        $db = db_connect();
        $hasAuditTable = $this->tableExistsSafe($db, 'audit_log');

        $filters = [
            'action' => trim((string) $this->request->getGet('action')),
            'user_id' => $this->request->getGet('user_id'),
            'entity_type' => trim((string) $this->request->getGet('entity_type')),
            'date_from' => trim((string) $this->request->getGet('date_from')),
            'date_to' => trim((string) $this->request->getGet('date_to')),
            'search' => trim((string) $this->request->getGet('search')),
        ];

        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = min(100, max(10, (int) ($this->request->getGet('per_page') ?? 25)));

        $rows = [];
        $totalRows = 0;
        $totalPages = 0;

        if ($hasAuditTable) {
            $builder = $this->buildAuditQuery($db, $filters);
            $totalRows = (int) (clone $builder)->countAllResults();
            $totalPages = $totalRows > 0 ? (int) ceil($totalRows / $perPage) : 1;
            $page = min($page, max(1, $totalPages));

            $rows = $builder
                ->orderBy('created_at', 'DESC')
                ->limit($perPage, ($page - 1) * $perPage)
                ->get()
                ->getResultArray();
        }

        return view('System/audit', array_merge(
            $this->getCommonViewData(lang('System.sectionAuditLog')),
            [
                'filters' => $filters,
                'hasAuditTable' => $hasAuditTable,
                'rows' => $rows,
                'page' => $page,
                'perPage' => $perPage,
                'totalRows' => $totalRows,
                'totalPages' => $totalPages,
            ]
        ));
    }

    private function buildAuditQuery($db, array $filters)
    {
        $builder = $db->table('audit_log');

        $action = trim((string) ($filters['action'] ?? ''));
        $userId = $filters['user_id'] ?? null;
        $entityType = trim((string) ($filters['entity_type'] ?? ''));
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));

        if ($action !== '') {
            $builder->like('action', $action, 'after');
        }

        if (is_numeric($userId) && (int) $userId > 0) {
            $builder->where('user_id', (int) $userId);
        }

        if ($entityType !== '') {
            $builder->where('entity_type', $entityType);
        }

        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $builder->where('created_at >=', $dateFrom . ' 00:00:00');
        }

        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $builder->where('created_at <=', $dateTo . ' 23:59:59');
        }

        if ($search !== '') {
            $builder->groupStart();
            $builder->like('action', $search);
            $builder->orLike('entity_type', $search);
            $builder->orLike('old_values', $search);
            $builder->orLike('new_values', $search);
            $builder->groupEnd();
        }

        return $builder;
    }

    public function data()
    {
        if ($redirect = $this->requirePermission('system.admin')) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Forbidden']);
        }

        if (!$this->isSystemAdmin()) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Forbidden']);
        }

        $db = db_connect();
        if (!$this->tableExistsSafe($db, 'audit_log')) {
            return $this->response->setJSON([
                'draw' => (int) $this->request->getGet('draw'),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
            ]);
        }

        $start = (int) $this->request->getGet('start');
        $length = (int) $this->request->getGet('length');
        $length = $length > 0 ? min($length, 250) : 25;
        $page = max(0, (int) floor($start / $length));

        $action = trim((string) $this->request->getGet('action'));
        $userId = $this->request->getGet('user_id');
        $entityType = trim((string) $this->request->getGet('entity_type'));
        $dateFrom = trim((string) $this->request->getGet('date_from'));
        $dateTo = trim((string) $this->request->getGet('date_to'));
        $search = trim((string) $this->request->getGet('search'));

        $builder = $db->table('audit_log');

        if ($action !== '') {
            $builder->like('action', $action, 'after');
        }

        if (is_numeric($userId) && (int) $userId > 0) {
            $builder->where('user_id', (int) $userId);
        }

        if ($entityType !== '') {
            $builder->where('entity_type', $entityType);
        }

        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $builder->where('created_at >=', $dateFrom . ' 00:00:00');
        }

        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $builder->where('created_at <=', $dateTo . ' 23:59:59');
        }

        if ($search !== '') {
            $builder->groupStart();
            $builder->like('action', $search);
            $builder->orLike('entity_type', $search);
            $builder->orLike('old_values', $search);
            $builder->orLike('new_values', $search);
            $builder->groupEnd();
        }

        $totalRecords = (int) $db->table('audit_log')->countAllResults();

        $filteredBuilder = clone $builder;
        $filteredRecords = (int) $filteredBuilder->countAllResults();

        $orderColumn = (int) $this->request->getGet('order[0][column]');
        $orderDir = in_array(strtolower((string) $this->request->getGet('order[0][dir]')), ['asc', 'desc'], true)
            ? strtoupper((string) $this->request->getGet('order[0][dir]'))
            : 'DESC';

        $columns = ['created_at', 'action', 'entity_type', 'entity_id', 'user_id', 'ip_address'];
        $orderBy = $columns[$orderColumn] ?? 'created_at';
        $builder->orderBy($orderBy, $orderDir);

        $rows = $builder->limit($length, $start)->get()->getResultArray();

        $data = array_map(static function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'action' => (string) ($row['action'] ?? ''),
                'entity_type' => (string) ($row['entity_type'] ?? ''),
                'entity_id' => (int) ($row['entity_id'] ?? 0),
                'user_id' => $row['user_id'] !== null ? (int) $row['user_id'] : null,
                'ip_address' => (string) ($row['ip_address'] ?? ''),
                'user_agent' => (string) ($row['user_agent'] ?? ''),
                'old_values' => $row['old_values'] ?? null,
                'new_values' => $row['new_values'] ?? null,
            ];
        }, $rows);

        return $this->response->setJSON([
            'draw' => (int) $this->request->getGet('draw'),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }
}
