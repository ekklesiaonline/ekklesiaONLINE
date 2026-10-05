<?php

declare(strict_types=1);

namespace App\Controllers\Diocese;

use App\Controllers\BaseController;
use App\Services\ClergyAuthorizationService;
use App\Services\ClergyReviewService;
use CodeIgniter\HTTP\ResponseInterface;

class ClergyReview extends BaseController
{
    private function authorized(string $permission, int $dioceseId): bool
    {
        return $dioceseId > 0 && ! $this->requirePermission($permission)
            && (new ClergyAuthorizationService())->allows((int) auth()->id(), $permission, 'diocese', $dioceseId);
    }

    public function candidates(int $dioceseId): ResponseInterface
    {
        if (! $this->authorized('clergy.appointment.manage', $dioceseId)) {
            return $this->jsonResponse($this->apiErrorPayload('Forbidden', 403, 'SCOPE_FORBIDDEN'), 403);
        }
        // Group-only candidates are visible only through an existing diocesan
        // profile/parish link; a group by itself never establishes jurisdiction.
        $rows = db_connect()->table('clergy_transition_candidate c')
            ->distinct()
            ->select('c.id, c.source_type, c.source_id, c.user_id, c.person_id, c.proposed_order, c.proposed_office, c.scope_type, c.scope_id, c.review_status')
            ->join('parish p', "c.scope_type = 'parish' AND p.id = c.scope_id", 'left')
            ->join('profile pr', 'pr.user_id = c.user_id', 'left')
            ->join('parish pp', 'pp.id = pr.parish_id', 'left')
            ->where('c.review_status', 'pending')
            ->groupStart()
                ->groupStart()->where('c.scope_type', 'diocese')->where('c.scope_id', $dioceseId)->groupEnd()
                ->orGroupStart()->where('c.scope_type', 'parish')->where('p.diocese_id', $dioceseId)->groupEnd()
                ->orGroupStart()->where('c.scope_type', null)
                    ->groupStart()->where('pr.diocese_id', $dioceseId)->orWhere('pp.diocese_id', $dioceseId)->groupEnd()
                ->groupEnd()
            ->groupEnd()->orderBy('c.id', 'ASC')->get()->getResultArray();
        return $this->jsonResponse(['success' => true, 'message' => 'Review candidates loaded.', 'data' => $rows]);
    }

    /**
     * Parish-initiated appointment requests in this diocese's review queue.
     * Jurisdiction is read from the row's own diocese_id — the request
     * body can never move a request across dioceses.
     */
    public function appointmentRequests(int $dioceseId): ResponseInterface
    {
        if (! $this->authorized('clergy.appointment.manage', $dioceseId)) {
            return $this->jsonResponse($this->apiErrorPayload('Forbidden', 403, 'SCOPE_FORBIDDEN'), 403);
        }
        if (! db_connect()->tableExists('clergy_appointment_request')) {
            return $this->jsonResponse(['success' => true, 'message' => 'Appointment requests loaded.', 'data' => []]);
        }
        $rows = db_connect()->table('clergy_appointment_request r')
            ->select('r.id, r.person_id, r.parish_id, r.proposed_office, r.review_status, r.created_at, r.reviewed_at,
                p.first_name, p.last_name, pa.name AS parish_name')
            ->join('person p', 'p.id = r.person_id', 'left')
            ->join('parish pa', 'pa.id = r.parish_id', 'left')
            ->where('r.diocese_id', $dioceseId)
            ->orderBy('r.id', 'ASC')->get()->getResultArray();
        return $this->jsonResponse(['success' => true, 'message' => 'Appointment requests loaded.', 'data' => $rows]);
    }

    public function reviewAppointmentRequest(int $dioceseId, int $requestId): ResponseInterface
    {
        if (! $this->authorized('clergy.appointment.manage', $dioceseId)) {
            return $this->jsonResponse($this->apiErrorPayload('Forbidden', 403, 'SCOPE_FORBIDDEN'), 403);
        }
        if (! $this->validate([
            'decision' => 'required|in_list[approved,rejected]',
            'reason_code' => 'permit_empty|max_length[32]',
        ])) {
            return $this->jsonResponse($this->apiErrorPayload('Invalid appointment decision.', 422, 'VALIDATION_FAILED', $this->validator->getErrors()), 422);
        }
        $result = (new ClergyReviewService())->reviewAppointmentRequest(
            $requestId, $dioceseId, (string) $this->request->getPost('decision'),
            $this->request->getPost('reason_code') ?: null, (int) auth()->id()
        );
        if ($result === null) {
            return $this->jsonResponse($this->apiErrorPayload('Request is out of scope, already reviewed or ineligible.', 409, 'REQUEST_CONFLICT'), 409);
        }
        $this->audit()->log('clergy.appointment_request.review', [
            'entity_type' => 'clergy_appointment_request', 'entity_id' => $requestId,
            'diocese_id' => $dioceseId,
        ]);
        return $this->jsonResponse([
            'success' => true,
            'message' => $result > 0 ? 'Appointment request approved; position activated.' : 'Appointment request rejected.',
            'data' => $result > 0 ? ['position_id' => $result] : null,
        ]);
    }

    public function reviewCandidate(int $candidateId, int $dioceseId): ResponseInterface
    {
        if (! $this->authorized('clergy.ordination.verify', $dioceseId)) {
            return $this->jsonResponse($this->apiErrorPayload('Forbidden', 403, 'SCOPE_FORBIDDEN'), 403);
        }
        if (! $this->validate([
            'decision' => 'required|in_list[approved,rejected]',
            'confirmed_order' => 'permit_empty|in_list[deacon,priest,bishop]',
            'reason_code' => 'permit_empty|in_list[wrong_person,invalid_order,not_in_diocese,duplicate]',
        ])) {
            return $this->jsonResponse($this->apiErrorPayload('Invalid candidate decision.', 422, 'VALIDATION_FAILED', $this->validator->getErrors()), 422);
        }
        if (! (new ClergyReviewService())->reviewCandidate(
            $candidateId, $dioceseId, (string) $this->request->getPost('decision'),
            $this->request->getPost('confirmed_order') ?: null,
            $this->request->getPost('reason_code') ?: null, (int) auth()->id()
        )) {
            return $this->jsonResponse($this->apiErrorPayload('Candidate has conflicting, missing or out-of-scope evidence.', 409, 'CANDIDATE_CONFLICT'), 409);
        }
        $this->audit()->log('clergy.transition_candidate.review', [
            'entity_type' => 'clergy_transition_candidate', 'entity_id' => $candidateId,
            'diocese_id' => $dioceseId,
        ]);
        return $this->jsonResponse(['success' => true, 'message' => 'Candidate reviewed; no access was granted.']);
    }

    public function verifyOrdination(int $dioceseId): ResponseInterface
    {
        if (! $this->authorized('clergy.ordination.verify', $dioceseId)) {
            return $this->jsonResponse($this->apiErrorPayload('Forbidden', 403, 'SCOPE_FORBIDDEN'), 403);
        }
        if (! $this->validate([
            'person_id' => 'required|is_natural_no_zero|is_not_unique[person.id]',
            'order_code' => 'required|in_list[deacon,priest,bishop]',
            'deacon_track' => 'permit_empty|in_list[vocational,transitional]',
            'ordained_on' => 'permit_empty|valid_date',
            'evidence_reference' => 'required|max_length[255]',
        ])) {
            return $this->jsonResponse($this->apiErrorPayload('Invalid ordination evidence.', 422, 'VALIDATION_FAILED', $this->validator->getErrors()), 422);
        }
        $personId = (int) $this->request->getPost('person_id');
        $id = (new ClergyReviewService())->verifyOrdination(
            $personId, $dioceseId, (string) $this->request->getPost('order_code'),
            $this->request->getPost('deacon_track') ?: null,
            $this->request->getPost('ordained_on') ?: null,
            (string) $this->request->getPost('evidence_reference'), (int) auth()->id()
        );
        if ($id === null) {
            return $this->jsonResponse($this->apiErrorPayload('Verification conflicts with eligibility or existing evidence.', 409, 'ORDINATION_CONFLICT'), 409);
        }
        $this->audit()->log('clergy.ordination.verify', [
            'entity_type' => 'clergy_ordination', 'entity_id' => $id, 'diocese_id' => $dioceseId,
        ]);
        return $this->jsonResponse(['success' => true, 'message' => 'Ordination verified.', 'data' => ['id' => $id]], 201);
    }

    public function standing(int $dioceseId): ResponseInterface
    {
        if (! $this->authorized('clergy.ordination.verify', $dioceseId)) {
            return $this->jsonResponse($this->apiErrorPayload('Forbidden', 403, 'SCOPE_FORBIDDEN'), 403);
        }
        if (! $this->validate([
            'person_id' => 'required|is_natural_no_zero|is_not_unique[person.id]',
            'status_code' => 'required|in_list[resident,licensed,transferred,retired,suspended,inhibited]',
            'effective_from' => 'required|valid_date',
            'effective_to' => 'permit_empty|valid_date',
            'evidence_reference' => 'required|max_length[255]',
        ])) {
            return $this->jsonResponse($this->apiErrorPayload('Invalid canonical standing.', 422, 'VALIDATION_FAILED', $this->validator->getErrors()), 422);
        }
        $id = (new ClergyReviewService())->recordStanding(
            (int) $this->request->getPost('person_id'), $dioceseId,
            (string) $this->request->getPost('status_code'),
            (string) $this->request->getPost('effective_from'),
            $this->request->getPost('effective_to') ?: null,
            (string) $this->request->getPost('evidence_reference'), (int) auth()->id()
        );
        if ($id === null) {
            return $this->jsonResponse($this->apiErrorPayload('Standing could not be recorded.', 409, 'STANDING_CONFLICT'), 409);
        }
        $this->audit()->log('clergy.standing.verify', [
            'entity_type' => 'clergy_canonical_status', 'entity_id' => $id, 'diocese_id' => $dioceseId,
        ]);
        return $this->jsonResponse(['success' => true, 'message' => 'Canonical standing recorded.', 'data' => ['id' => $id]], 201);
    }

    public function approveOffice(int $positionId): ResponseInterface
    {
        if ($this->requirePermission('clergy.appointment.manage')) {
            return $this->jsonResponse($this->apiErrorPayload('Forbidden', 403, 'FORBIDDEN'), 403);
        }
        if (! $this->validate(['evidence_reference' => 'required|max_length[255]'])) {
            return $this->jsonResponse($this->apiErrorPayload('Invalid approval evidence.', 422, 'VALIDATION_FAILED', $this->validator->getErrors()), 422);
        }
        if (! (new ClergyReviewService())->approveOffice($positionId, (int) auth()->id(),
            (string) $this->request->getPost('evidence_reference'))) {
            return $this->jsonResponse($this->apiErrorPayload('Appointment is out of scope, ineligible or conflicts with a current incumbent.', 409, 'OFFICE_CONFLICT'), 409);
        }
        $this->audit()->log('clergy.appointment.approve', [
            'entity_type' => 'leadership_position', 'entity_id' => $positionId,
        ]);
        return $this->jsonResponse(['success' => true, 'message' => 'Clergy office approved.']);
    }

    public function revokeOffice(int $positionId): ResponseInterface
    {
        if ($this->requirePermission('clergy.appointment.manage')) {
            return $this->jsonResponse($this->apiErrorPayload('Forbidden', 403, 'FORBIDDEN'), 403);
        }
        if (! service('validation')->run(['position_id' => $positionId], ['position_id' => 'required|is_natural_no_zero'])) {
            return $this->jsonResponse($this->apiErrorPayload('Invalid office ID.', 422, 'VALIDATION_FAILED'), 422);
        }
        if (! (new ClergyReviewService())->revokeOffice($positionId, (int) auth()->id())) {
            return $this->jsonResponse($this->apiErrorPayload('Appointment is not available for revocation.', 404, 'OFFICE_NOT_FOUND'), 404);
        }
        $this->audit()->log('clergy.appointment.revoke', [
            'entity_type' => 'leadership_position', 'entity_id' => $positionId,
        ]);
        return $this->jsonResponse(['success' => true, 'message' => 'Clergy office revoked.']);
    }

    public function approveMissionOffice(int $positionId): ResponseInterface
    {
        if ($this->requirePermission('clergy.appointment.manage')) {
            return $this->jsonResponse($this->apiErrorPayload('Forbidden', 403, 'FORBIDDEN'), 403);
        }
        if (! $this->validate(['evidence_reference' => 'required|max_length[255]'])) {
            return $this->jsonResponse($this->apiErrorPayload('Invalid mission appointment evidence.', 422, 'VALIDATION_FAILED', $this->validator->getErrors()), 422);
        }
        $position = model('LeadershipPositionModel')->find($positionId);
        if (! is_array($position) || $position['scope_type'] !== 'parish') {
            return $this->jsonResponse($this->apiErrorPayload('Mission office not found.', 404, 'OFFICE_NOT_FOUND'), 404);
        }
        if (! (new ClergyReviewService())->approveLayOffice($positionId, (int) auth()->id(),
            (string) $this->request->getPost('evidence_reference'), (int) $position['parish_id'], true)) {
            return $this->jsonResponse($this->apiErrorPayload('Mission office is out of scope or ineligible.', 409, 'OFFICE_CONFLICT'), 409);
        }
        $this->audit()->log('governance.mission_office.approve', [
            'entity_type' => 'leadership_position', 'entity_id' => $positionId,
            'parish_id' => (int) $position['parish_id'],
        ]);
        return $this->jsonResponse(['success' => true, 'message' => 'Mission governance appointment approved.']);
    }

    public function delegate(int $dioceseId): ResponseInterface
    {
        if (! $this->authorized('governance.diocese.approve', $dioceseId)) {
            return $this->jsonResponse($this->apiErrorPayload('Forbidden', 403, 'SCOPE_FORBIDDEN'), 403);
        }
        if (! $this->validate([
            'user_id' => 'required|is_natural_no_zero|is_not_unique[users.id]',
            'permission' => 'required|in_list[governance.diocese.approve,clergy.ordination.verify,clergy.appointment.manage]',
            'ends_on' => 'required|valid_date',
            'evidence_reference' => 'required|max_length[255]',
        ])) {
            return $this->jsonResponse($this->apiErrorPayload('Invalid delegation.', 422, 'VALIDATION_FAILED', $this->validator->getErrors()), 422);
        }
        $id = (new ClergyReviewService())->issueDelegation(
            (int) auth()->id(), (int) $this->request->getPost('user_id'), $dioceseId,
            (string) $this->request->getPost('permission'),
            (string) $this->request->getPost('ends_on'),
            (string) $this->request->getPost('evidence_reference')
        );
        if ($id === null) {
            return $this->jsonResponse($this->apiErrorPayload('Delegation is out of scope or ineligible.', 409, 'DELEGATION_CONFLICT'), 409);
        }
        $this->audit()->log('clergy.delegation.create', [
            'entity_type' => 'clergy_access_grant', 'entity_id' => $id, 'diocese_id' => $dioceseId,
        ]);
        return $this->jsonResponse(['success' => true, 'message' => 'Diocesan delegation recorded.', 'data' => ['id' => $id]], 201);
    }

    public function delegateVacancy(int $parishId): ResponseInterface
    {
        if ($this->requirePermission('governance.diocese.approve')) {
            return $this->jsonResponse($this->apiErrorPayload('Forbidden', 403, 'FORBIDDEN'), 403);
        }
        if (! $this->validate([
            'user_id' => 'required|is_natural_no_zero|is_not_unique[users.id]',
            'ends_on' => 'required|valid_date',
            'evidence_reference' => 'required|max_length[255]',
        ])) {
            return $this->jsonResponse($this->apiErrorPayload('Invalid vacancy delegation.', 422, 'VALIDATION_FAILED', $this->validator->getErrors()), 422);
        }
        $id = (new ClergyReviewService())->issueParishVacancyDelegation(
            (int) auth()->id(), (int) $this->request->getPost('user_id'), $parishId,
            (string) $this->request->getPost('ends_on'),
            (string) $this->request->getPost('evidence_reference')
        );
        if ($id === null) {
            return $this->jsonResponse($this->apiErrorPayload('Vacancy delegation is out of scope or ineligible.', 409, 'VACANCY_DELEGATION_CONFLICT'), 409);
        }
        $this->audit()->log('clergy.vacancy_delegation.create', [
            'entity_type' => 'clergy_access_grant', 'entity_id' => $id, 'parish_id' => $parishId,
        ]);
        return $this->jsonResponse(['success' => true, 'message' => 'Parish vacancy delegation recorded.', 'data' => ['id' => $id]], 201);
    }

    public function reportPolicy(int $dioceseId): ResponseInterface
    {
        if ($this->requirePermission('governance.diocese.approve')) {
            return $this->jsonResponse($this->apiErrorPayload('Forbidden', 403, 'FORBIDDEN'), 403);
        }
        if (! $this->validate([
            'enabled' => 'required|in_list[0,1]',
            'evidence_reference' => 'required|max_length[255]',
        ])) {
            return $this->jsonResponse($this->apiErrorPayload('Invalid diocesan report policy.', 422, 'VALIDATION_FAILED', $this->validator->getErrors()), 422);
        }
        $enabled = $this->request->getPost('enabled') === '1';
        if (! (new ClergyReviewService())->setReportSignaturePolicy((int) auth()->id(), $dioceseId,
            $enabled, (string) $this->request->getPost('evidence_reference'))) {
            return $this->jsonResponse($this->apiErrorPayload('Report policy requires the verified diocesan ordinary.', 403, 'SCOPE_FORBIDDEN'), 403);
        }
        $this->audit()->log('governance.report.policy', [
            'entity_type' => 'diocese', 'entity_id' => $dioceseId, 'enabled' => $enabled,
        ]);
        return $this->jsonResponse(['success' => true, 'message' => 'Diocesan report-signature policy updated.']);
    }

    public function revokeDelegation(int $grantId): ResponseInterface
    {
        if ($this->requirePermission('governance.diocese.approve')) {
            return $this->jsonResponse($this->apiErrorPayload('Forbidden', 403, 'FORBIDDEN'), 403);
        }
        $validation = service('validation');
        if (! $validation->run(['grant_id' => $grantId], ['grant_id' => 'required|is_natural_no_zero'])) {
            return $this->jsonResponse($this->apiErrorPayload('Invalid delegation ID.', 422, 'VALIDATION_FAILED', $validation->getErrors()), 422);
        }
        if (! (new ClergyReviewService())->revokeDelegation($grantId, (int) auth()->id())) {
            return $this->jsonResponse($this->apiErrorPayload('Delegation not found in your ordinary jurisdiction.', 404, 'DELEGATION_NOT_FOUND'), 404);
        }
        $this->audit()->log('clergy.delegation.revoke', [
            'entity_type' => 'clergy_access_grant', 'entity_id' => $grantId,
        ]);
        return $this->jsonResponse(['success' => true, 'message' => 'Diocesan delegation revoked.']);
    }
}
