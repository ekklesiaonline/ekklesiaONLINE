<?php

declare(strict_types=1);

namespace App\Controllers\Diocese;

use App\Controllers\BaseController;
use App\Services\DeaneryService;
use App\Services\HierarchyService;
use CodeIgniter\HTTP\ResponseInterface;

class Deaneries extends BaseController
{
    private function authorizedDiocese(string $permission): ?int
    {
        if ($this->requirePermission($permission)) {
            return null;
        }
        $dioceseId = $this->resolveDiocesanScopeId();
        return $dioceseId > 0 ? $dioceseId : null;
    }

    public function index(): ResponseInterface
    {
        $dioceseId = $this->authorizedDiocese('deanery.view');
        if ($dioceseId === null) {
            return $this->jsonResponse($this->apiErrorPayload('Forbidden', 403, 'SCOPE_FORBIDDEN'), 403);
        }
        $hierarchy = new HierarchyService();
        $rows = $hierarchy->deaneriesInDiocese($dioceseId);
        foreach ($rows as &$row) {
            $row['parishes'] = $hierarchy->parishesInDeanery((int) $row['id']);
        }
        unset($row);
        return $this->jsonResponse(['success' => true, 'message' => 'Deaneries loaded.', 'data' => $rows]);
    }

    public function manage(): string|ResponseInterface
    {
        $dioceseId = $this->authorizedDiocese('deanery.view');
        if ($dioceseId === null || ! $this->isDioceseAdmin()) {
            return $this->jsonResponse($this->apiErrorPayload('Forbidden', 403, 'SCOPE_FORBIDDEN'), 403);
        }
        $db = db_connect();
        if (! $db->tableExists('deanery')) {
            return $this->jsonResponse($this->apiErrorPayload('Deaneries are not configured.', 503, 'MIGRATION_REQUIRED'), 503);
        }
        $hierarchy = new HierarchyService($db);
        $rows = $hierarchy->deaneriesInDiocese($dioceseId);
        $allowedPerPage = [10, 25, 50, 100];
        $perPage = (int) ($this->request->getGet('per_page') ?? 10);
        if (! in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }
        $total = count($rows);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min((int) ($this->request->getGet('page') ?? 1), $lastPage));
        $rows = array_slice($rows, ($page - 1) * $perPage, $perPage);
        foreach ($rows as &$row) {
            $row['parishes'] = $hierarchy->parishesInDeanery((int) $row['id']);
        }
        unset($row);
        return view('Diocese\Views\Deaneries/manage', array_merge(
            $this->getCommonViewData('Deaneries'), [
                'deaneries' => $rows,
                'parishes' => $db->table('parish')->select('id, name')
                    ->where('diocese_id', $dioceseId)->where('is_active', 1)
                    ->orderBy('name', 'ASC')->get()->getResultArray(),
                'can_manage_deaneries' => auth()->user()?->can('deanery.manage') === true,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => $lastPage,
                ],
            ]
        ));
    }

    public function store(): ResponseInterface
    {
        $dioceseId = $this->authorizedDiocese('deanery.manage');
        if ($dioceseId === null) {
            return $this->jsonResponse($this->apiErrorPayload('Forbidden', 403, 'SCOPE_FORBIDDEN'), 403);
        }
        if (! $this->validate([
            'code' => 'required|alpha_dash|max_length[64]',
            'name' => 'required|min_length[2]|max_length[150]',
        ])) {
            return $this->jsonResponse($this->apiErrorPayload('Invalid deanery.', 422, 'VALIDATION_FAILED', $this->validator->getErrors()), 422);
        }
        $id = (new DeaneryService())->create($dioceseId, (string) $this->request->getPost('code'), (string) $this->request->getPost('name'));
        if ($id === null) {
            return $this->jsonResponse($this->apiErrorPayload('Deanery code already exists or is invalid.', 409, 'DEANERY_CONFLICT'), 409);
        }
        $this->audit()->log('deanery.create', [
            'entity_type' => 'deanery', 'entity_id' => $id, 'diocese_id' => $dioceseId,
        ]);
        return $this->jsonResponse(['success' => true, 'message' => 'Deanery created.', 'data' => ['id' => $id]], 201);
    }

    public function assign(int $deaneryId): ResponseInterface
    {
        $dioceseId = $this->authorizedDiocese('deanery.manage');
        if ($dioceseId === null || ! (new HierarchyService())->deaneryInDiocese($deaneryId, $dioceseId)) {
            return $this->jsonResponse($this->apiErrorPayload('Not found in your diocese.', 404, 'DEANERY_NOT_FOUND'), 404);
        }
        if (! $this->validate([
            'parish_id' => 'required|is_natural_no_zero|is_not_unique[parish.id]',
            'started_at' => 'required|valid_date',
            'ended_at' => 'permit_empty|valid_date',
        ])) {
            return $this->jsonResponse($this->apiErrorPayload('Invalid parish membership.', 422, 'VALIDATION_FAILED', $this->validator->getErrors()), 422);
        }
        $parishId = (int) $this->request->getPost('parish_id');
        $start = (string) $this->request->getPost('started_at');
        $end = $this->request->getPost('ended_at') ?: null;
        if (! (new DeaneryService())->assignParish($deaneryId, $parishId, $start, $end, (int) auth()->id())) {
            return $this->jsonResponse($this->apiErrorPayload('Parish is outside this diocese or the dates overlap.', 409, 'MEMBERSHIP_CONFLICT'), 409);
        }
        $this->audit()->log('deanery.membership.create', [
            'entity_type' => 'deanery', 'entity_id' => $deaneryId,
            'parish_id' => $parishId, 'diocese_id' => $dioceseId,
        ]);
        return $this->jsonResponse(['success' => true, 'message' => 'Parish assigned to deanery.'], 201);
    }

    public function end(int $membershipId): ResponseInterface
    {
        $dioceseId = $this->authorizedDiocese('deanery.manage');
        if ($dioceseId === null) {
            return $this->jsonResponse($this->apiErrorPayload('Forbidden', 403, 'SCOPE_FORBIDDEN'), 403);
        }
        if (! $this->validate(['ended_at' => 'required|valid_date'])) {
            return $this->jsonResponse($this->apiErrorPayload('Invalid end date.', 422, 'VALIDATION_FAILED', $this->validator->getErrors()), 422);
        }
        if (! (new DeaneryService())->endMembership($membershipId, $dioceseId, (string) $this->request->getPost('ended_at'))) {
            return $this->jsonResponse($this->apiErrorPayload('Membership not found or end date invalid.', 404, 'MEMBERSHIP_NOT_FOUND'), 404);
        }
        $this->audit()->log('deanery.membership.end', [
            'entity_type' => 'deanery_parish_membership', 'entity_id' => $membershipId,
            'diocese_id' => $dioceseId,
        ]);
        return $this->jsonResponse(['success' => true, 'message' => 'Deanery membership ended.']);
    }

    public function setCongregationType(int $parishId): ResponseInterface
    {
        $dioceseId = $this->authorizedDiocese('parishes.edit');
        if ($dioceseId === null || ! (new HierarchyService())->parishInDiocese($parishId, $dioceseId)) {
            return $this->jsonResponse($this->apiErrorPayload('Congregation not found in your diocese.', 404, 'PARISH_NOT_FOUND'), 404);
        }
        if (! $this->validate(['congregation_type' => 'required|in_list[parish,cathedral,mission]'])) {
            return $this->jsonResponse($this->apiErrorPayload('Invalid congregation type.', 422, 'VALIDATION_FAILED', $this->validator->getErrors()), 422);
        }
        $db = db_connect();
        if (! $db->fieldExists('congregation_type', 'parish')) {
            return $this->jsonResponse($this->apiErrorPayload('Congregation types are not configured.', 503, 'MIGRATION_REQUIRED'), 503);
        }
        if ($db->table('parish_report')->where('parish_id', $parishId)
            ->whereNotIn('report_status', ['draft', 'diocesan_approved'])->countAllResults() > 0) {
            return $this->jsonResponse($this->apiErrorPayload('A report is awaiting signature.', 409, 'REPORT_IN_PROGRESS'), 409);
        }
        $db->table('parish')->where('id', $parishId)->where('diocese_id', $dioceseId)
            ->update(['congregation_type' => (string) $this->request->getPost('congregation_type')]);
        $this->audit()->log('congregation.type.update', [
            'entity_type' => 'parish', 'entity_id' => $parishId, 'diocese_id' => $dioceseId,
        ]);
        return $this->jsonResponse(['success' => true, 'message' => 'Congregation type saved.']);
    }
}
