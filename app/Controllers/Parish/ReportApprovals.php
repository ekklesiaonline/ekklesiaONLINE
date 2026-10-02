<?php

declare(strict_types=1);

namespace App\Controllers\Parish;

use App\Controllers\BaseController;
use App\Services\ClergyReviewService;
use App\Services\HierarchyService;
use App\Services\ParishReportApprovalService;
use CodeIgniter\HTTP\ResponseInterface;

class ReportApprovals extends BaseController
{
    private function ready(): bool
    {
        $db = db_connect();
        return $db->tableExists('parish_report_signature')
            && $db->fieldExists('report_revision', 'parish_report');
    }

    public function submit(int $parishId): ResponseInterface
    {
        if ($this->requirePermission('report.parochial')) {
            return $this->jsonResponse($this->apiErrorPayload('Forbidden', 403, 'FORBIDDEN'), 403);
        }
        if (! $this->validate(['report_year' => 'required|is_natural_no_zero|greater_than_equal_to[1900]|less_than_equal_to[2200]'])) {
            return $this->jsonResponse($this->apiErrorPayload('Invalid reporting year.', 422, 'VALIDATION_FAILED', $this->validator->getErrors()), 422);
        }
        if (! $this->ready()) {
            return $this->jsonResponse($this->apiErrorPayload('Report signing is not configured.', 503, 'MIGRATION_REQUIRED'), 503);
        }
        $id = (new ParishReportApprovalService())->submit($parishId,
            (int) $this->request->getPost('report_year'), (int) auth()->id());
        if ($id === null) {
            return $this->jsonResponse($this->apiErrorPayload('Report is out of scope or its policy, snapshot or status is not ready.', 409, 'REPORT_NOT_READY'), 409);
        }
        $this->audit()->log('parish_report.submit', [
            'entity_type' => 'parish_report', 'entity_id' => $id, 'parish_id' => $parishId,
        ]);
        return $this->jsonResponse(['success' => true, 'message' => 'Report submitted for certification.', 'data' => ['id' => $id]], 201);
    }

    public function certifyClerk(int $reportId): ResponseInterface
    {
        return $this->sign($reportId, 'certify_clerk', 'governance.report.certify_clerk');
    }

    public function approveWarden(int $reportId): ResponseInterface
    {
        return $this->sign($reportId, 'approve_warden', 'governance.report.approve_warden');
    }

    public function finalizeClergy(int $reportId): ResponseInterface
    {
        return $this->sign($reportId, 'finalize_clergy', 'governance.report.finalize_clergy');
    }

    public function approveDiocese(int $reportId): ResponseInterface
    {
        return $this->sign($reportId, 'approve_diocese', 'governance.diocese.approve');
    }

    private function sign(int $reportId, string $step, string $permission): ResponseInterface
    {
        if ($this->requirePermission($permission)) {
            return $this->jsonResponse($this->apiErrorPayload('Forbidden', 403, 'FORBIDDEN'), 403);
        }
        $validation = service('validation');
        if (! $validation->run(['report_id' => $reportId], ['report_id' => 'required|is_natural_no_zero'])) {
            return $this->jsonResponse($this->apiErrorPayload('Invalid report ID.', 422, 'VALIDATION_FAILED', $validation->getErrors()), 422);
        }
        if (! $this->ready()) {
            return $this->jsonResponse($this->apiErrorPayload('Report signing is not configured.', 503, 'MIGRATION_REQUIRED'), 503);
        }
        $service = new ParishReportApprovalService();
        if (! $service->sign($reportId, (int) auth()->id(), $step)) {
            $code = $service->lastSignError();
            $status = match ($code) {
                'SCOPE_FORBIDDEN', 'SELF_SIGNATURE_FORBIDDEN' => 403,
                'REPORT_NOT_FOUND' => 404,
                'REPORT_SIGNING_FAILED' => 500,
                default => 409,
            };
            $message = $status === 500 ? 'Unable to sign the report.' : 'Report signature is unavailable.';
            return $this->jsonResponse($this->apiErrorPayload($message, $status, $code), $status);
        }
        $this->audit()->log('parish_report.' . $step, [
            'entity_type' => 'parish_report', 'entity_id' => $reportId,
        ]);
        return $this->jsonResponse(['success' => true, 'message' => 'Report signature recorded.']);
    }

    public function newRevision(int $reportId): ResponseInterface
    {
        if ($this->requirePermission('report.parochial')) {
            return $this->jsonResponse($this->apiErrorPayload('Forbidden', 403, 'FORBIDDEN'), 403);
        }
        $validation = service('validation');
        if (! $validation->run(['report_id' => $reportId], ['report_id' => 'required|is_natural_no_zero'])) {
            return $this->jsonResponse($this->apiErrorPayload('Invalid report ID.', 422, 'VALIDATION_FAILED', $validation->getErrors()), 422);
        }
        if (! $this->ready()) {
            return $this->jsonResponse($this->apiErrorPayload('Report signing is not configured.', 503, 'MIGRATION_REQUIRED'), 503);
        }
        if (! (new ParishReportApprovalService())->startRevision($reportId, (int) auth()->id())) {
            return $this->jsonResponse($this->apiErrorPayload('Report cannot be revised by this actor.', 409, 'REVISION_CONFLICT'), 409);
        }
        $this->audit()->log('parish_report.new_revision', [
            'entity_type' => 'parish_report', 'entity_id' => $reportId,
        ]);
        return $this->jsonResponse(['success' => true, 'message' => 'New report revision opened; prior signatures remain archived.']);
    }

    public function approveLayOffice(int $positionId): ResponseInterface
    {
        if ($this->requirePermission('governance.vestry.manage')) {
            return $this->jsonResponse($this->apiErrorPayload('Forbidden', 403, 'FORBIDDEN'), 403);
        }
        if (! $this->validate(['evidence_reference' => 'required|max_length[255]'])) {
            return $this->jsonResponse($this->apiErrorPayload('Invalid appointment evidence.', 422, 'VALIDATION_FAILED', $this->validator->getErrors()), 422);
        }
        $position = model('LeadershipPositionModel')->find($positionId);
        if (! is_array($position) || $position['scope_type'] !== 'parish') {
            return $this->jsonResponse($this->apiErrorPayload('Lay office not found.', 404, 'OFFICE_NOT_FOUND'), 404);
        }
        $parishId = (int) $position['parish_id'];
        $parishAdmin = auth()->user()?->inGroup('parishadmin') === true
            && $parishId > 0 && $parishId === (int) $this->getOperationalParishId();
        $diocesanAdmin = $this->isDioceseAdmin()
            && (new HierarchyService())->parishInDiocese($parishId, $this->resolveDiocesanScopeId());
        if (! $parishAdmin && ! $diocesanAdmin) {
            return $this->jsonResponse($this->apiErrorPayload('Forbidden', 403, 'SCOPE_FORBIDDEN'), 403);
        }
        if (! (new ClergyReviewService())->approveLayOffice($positionId, (int) auth()->id(),
            (string) $this->request->getPost('evidence_reference'), $parishId)) {
            return $this->jsonResponse($this->apiErrorPayload('Lay office requires separate, eligible signatory review.', 409, 'OFFICE_CONFLICT'), 409);
        }
        $this->audit()->log('governance.lay_office.approve', [
            'entity_type' => 'leadership_position', 'entity_id' => $positionId, 'parish_id' => $parishId,
        ]);
        return $this->jsonResponse(['success' => true, 'message' => 'Lay governance appointment approved.']);
    }
}
