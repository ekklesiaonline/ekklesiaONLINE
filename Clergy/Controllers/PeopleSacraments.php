<?php

namespace Clergy\Controllers;

use App\Controllers\BaseController;
use App\Services\PeopleViewDataService;
use App\Services\SacramentalRecordService;

/**
 * Sacramental-record endpoints extracted from People (fat-controller split).
 * Route handlers under /people/sacrament* are repointed here; URLs and
 * permission filters are unchanged. sacramental_record stays the canonical
 * source of truth for baptism/confirmation — every mutation re-runs
 * SacramentalRecordService::syncFlags() to keep person_membership_details
 * flags and profile dates in sync.
 */
class PeopleSacraments extends BaseController
{
    private PeopleViewDataService $peopleViewDataService;
    private SacramentalRecordService $sacramentalRecordService;

    public function __construct()
    {
        $this->peopleViewDataService = service('peopleViewData');
        $this->sacramentalRecordService = new SacramentalRecordService();
    }

    /**
     * Add a sacramental record for a person.
     */
    public function addSacrament(int $profileId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('member.edit')) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FORBIDDEN'), 403);
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        if ($this->isProvinceAdmin()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'PROVINCE_WRITE_DISABLED'), 403);
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        $profile = $this->peopleViewDataService->profileForPersonManagement($profileId, $parishId);
        if (!$profile) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'PROFILE_NOT_FOUND'), 404);
        }

        $personId = (int) ($profile['person_id'] ?? 0);
        if ($personId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'PERSON_NOT_FOUND'), 404);
        }

        if (!$this->validate($this->sacramentalRecordService->recordValidationRules())) {
            return $this->jsonResponse([
                'success' => false,
                'code' => 'VALIDATION_FAILED',
                'message' => 'Validation failed.',
                'errors' => $this->validator->getErrors(),
            ], 422);
        }

        $officiantPersonId = $this->request->getPost('officiant_person_id');
        $officiantPersonId = ($officiantPersonId !== '' && $officiantPersonId !== null) ? (int) $officiantPersonId : null;

        $payload = [
            'parish_id'           => $parishId,
            'person_id'           => $personId,
            'sacrament_type'      => trim((string) $this->request->getPost('sacrament_type')),
            'sacrament_date'      => (string) ($this->request->getPost('sacrament_date') ?? '') ?: null,
            'register_ref'        => trim((string) ($this->request->getPost('register_ref') ?? '')),
            'certificate_number'  => trim((string) ($this->request->getPost('certificate_number') ?? '')),
            'officiant_person_id' => $officiantPersonId,
            'location_notes'      => trim((string) ($this->request->getPost('location_notes') ?? '')),
            'notes'               => trim((string) ($this->request->getPost('notes') ?? '')),
        ];

        $recordId = $this->sacramentalRecordService->createRecord($payload);
        if ($recordId === null) {
            return $this->jsonResponse([
                'success' => false,
                'message' => implode(' ', array_values($this->sacramentalRecordService->recordErrors() ?: [lang('Sacraments.saveFailed')])),
            ], 500);
        }

        // Keep person_membership_details flags + profile dates in sync with
        // the canonical sacramental_record. sacramental_record is the source
        // of truth for baptism/confirmation status.
        $this->sacramentalRecordService->syncFlags(
            $personId,
            $parishId,
            auth()->loggedIn() ? (int) auth()->id() : null,
            'Sacrament record added'
        );

        $this->audit()->log('sacramental_record.create', [
            'parish_id' => $parishId,
            'entity_type' => 'sacramental_record',
            'entity_id' => $recordId,
            'new_values' => $payload,
            'meta' => $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('Sacraments.saveSuccess'),
            'data' => ['id' => $recordId],
        ]);
    }

    /**
     * Update a sacramental record.
     */
    public function updateSacrament(int $recordId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('member.edit')) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FORBIDDEN'), 403);
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        if ($this->isProvinceAdmin()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'PROVINCE_WRITE_DISABLED'), 403);
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        $record = $this->sacramentalRecordService->findRecordInParish($recordId, $parishId);
        if ($record === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'SACRAMENT_NOT_FOUND'), 404);
        }

        if (!$this->validate($this->sacramentalRecordService->recordValidationRules())) {
            return $this->jsonResponse([
                'success' => false,
                'code' => 'VALIDATION_FAILED',
                'message' => 'Validation failed.',
                'errors' => $this->validator->getErrors(),
            ], 422);
        }

        $officiantPersonId = $this->request->getPost('officiant_person_id');
        $officiantPersonId = ($officiantPersonId !== '' && $officiantPersonId !== null) ? (int) $officiantPersonId : null;

        $payload = [
            'sacrament_type'      => trim((string) $this->request->getPost('sacrament_type')),
            'sacrament_date'      => (string) ($this->request->getPost('sacrament_date') ?? '') ?: null,
            'register_ref'        => trim((string) ($this->request->getPost('register_ref') ?? '')),
            'certificate_number'  => trim((string) ($this->request->getPost('certificate_number') ?? '')),
            'officiant_person_id' => $officiantPersonId,
            'location_notes'      => trim((string) ($this->request->getPost('location_notes') ?? '')),
            'notes'               => trim((string) ($this->request->getPost('notes') ?? '')),
        ];

        if (!$this->sacramentalRecordService->updateRecord($recordId, $payload)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => implode(' ', array_values($this->sacramentalRecordService->recordErrors() ?: [lang('Sacraments.updateFailed')])),
            ], 500);
        }

        // The sacrament_type or date may have changed (e.g. "other" -> "baptism"),
        // so recompute the derived flags + profile dates from all records.
        $this->sacramentalRecordService->syncFlags(
            (int) ($record['person_id'] ?? 0),
            $parishId,
            auth()->loggedIn() ? (int) auth()->id() : null,
            'Sacrament record updated'
        );

        $this->audit()->log('sacramental_record.update', [
            'parish_id' => $parishId,
            'entity_type' => 'sacramental_record',
            'entity_id' => $recordId,
            'old_values' => $record,
            'new_values' => $payload,
            'meta' => $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('Sacraments.updateSuccess'),
        ]);
    }

    /**
     * Delete a sacramental record.
     */
    public function deleteSacrament(int $recordId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('member.edit')) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FORBIDDEN'), 403);
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        if ($this->isProvinceAdmin()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'PROVINCE_WRITE_DISABLED'), 403);
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        $record = $this->sacramentalRecordService->findRecordInParish($recordId, $parishId);
        if ($record === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'SACRAMENT_NOT_FOUND'), 404);
        }

        if (!$this->sacramentalRecordService->deleteRecord($recordId)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Sacraments.deleteFailed') ?? 'Failed to delete sacramental record.',
            ], 500);
        }

        // Deleting the only baptism/confirmation record must clear the derived
        // flags + profile dates. Recompute from the remaining records.
        $this->sacramentalRecordService->syncFlags(
            (int) ($record['person_id'] ?? 0),
            $parishId,
            auth()->loggedIn() ? (int) auth()->id() : null,
            'Sacrament record deleted'
        );

        $this->audit()->log('sacramental_record.delete', [
            'parish_id' => $parishId,
            'entity_type' => 'sacramental_record',
            'entity_id' => $recordId,
            'old_values' => $record,
            'meta' => $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('Sacraments.deleteSuccess') ?? 'Sacramental record deleted.',
        ]);
    }

    /**
     * Add a participant to a sacramental record.
     */
    public function addSacramentParticipant(int $recordId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('member.edit')) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FORBIDDEN'), 403);
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        if ($this->isProvinceAdmin()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'PROVINCE_WRITE_DISABLED'), 403);
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        $record = $this->sacramentalRecordService->findRecordInParish($recordId, $parishId);
        if ($record === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'SACRAMENT_NOT_FOUND'), 404);
        }

        if (!$this->validate($this->sacramentalRecordService->participantCreateRules())) {
            return $this->jsonResponse([
                'success' => false,
                'code' => 'VALIDATION_FAILED',
                'message' => 'Validation failed.',
                'errors' => $this->validator->getErrors(),
            ], 422);
        }

        $personId = (int) ($this->request->getPost('person_id') ?? 0);
        $role = trim((string) $this->request->getPost('participant_role'));

        if ($this->sacramentalRecordService->participantExists($recordId, $personId, $role)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Sacraments.participantAlreadyExists'),
            ], 409);
        }

        $payload = [
            'sacramental_record_id' => $recordId,
            'person_id' => $personId,
            'participant_role' => $role,
        ];

        $participantId = $this->sacramentalRecordService->createParticipant($payload);
        if ($participantId === null) {
            return $this->jsonResponse([
                'success' => false,
                'message' => implode(' ', array_values($this->sacramentalRecordService->participantErrors() ?: [lang('Sacraments.participantAddFailed')])),
            ], 500);
        }

        $this->audit()->log('sacramental_participant.create', [
            'parish_id' => $parishId,
            'entity_type' => 'sacramental_participant',
            'entity_id' => $participantId,
            'new_values' => $payload,
            'meta' => $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('Sacraments.participantAddSuccess'),
            'data' => ['id' => $participantId],
        ]);
    }

    /**
     * Update a sacramental participant's role.
     */
    public function updateSacramentParticipant(int $participantId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('member.edit')) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FORBIDDEN'), 403);
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        if ($this->isProvinceAdmin()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'PROVINCE_WRITE_DISABLED'), 403);
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        $participant = $this->sacramentalRecordService->findParticipantInParish($participantId, $parishId);
        if ($participant === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'PARTICIPANT_NOT_FOUND'), 404);
        }

        if (!$this->validate($this->sacramentalRecordService->participantUpdateRules())) {
            return $this->jsonResponse([
                'success' => false,
                'code' => 'VALIDATION_FAILED',
                'message' => 'Validation failed.',
                'errors' => $this->validator->getErrors(),
            ], 422);
        }

        $role = trim((string) $this->request->getPost('participant_role'));
        $recordId = (int) ($participant['sacramental_record_id'] ?? 0);
        $personId = (int) ($participant['person_id'] ?? 0);

        if ($this->sacramentalRecordService->participantExists($recordId, $personId, $role, $participantId)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Sacraments.participantAlreadyExists'),
            ], 409);
        }

        if (!$this->sacramentalRecordService->updateParticipantRole($participantId, $role)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => implode(' ', array_values($this->sacramentalRecordService->participantErrors() ?: [lang('Sacraments.participantUpdateFailed')])),
            ], 500);
        }

        $this->audit()->log('sacramental_participant.update', [
            'parish_id' => $parishId,
            'entity_type' => 'sacramental_participant',
            'entity_id' => $participantId,
            'old_values' => $participant,
            'new_values' => ['participant_role' => $role],
            'meta' => $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('Sacraments.participantUpdateSuccess'),
        ]);
    }

    /**
     * Delete a sacramental participant.
     */
    public function deleteSacramentParticipant(int $participantId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('member.edit')) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FORBIDDEN'), 403);
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        if ($this->isProvinceAdmin()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'PROVINCE_WRITE_DISABLED'), 403);
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        $participant = $this->sacramentalRecordService->findParticipantInParish($participantId, $parishId);
        if ($participant === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'PARTICIPANT_NOT_FOUND'), 404);
        }

        if (!$this->sacramentalRecordService->deleteParticipant($participantId)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Sacraments.participantDeleteFailed') ?? 'Failed to delete participant.',
            ], 500);
        }

        $this->audit()->log('sacramental_participant.delete', [
            'parish_id' => $parishId,
            'entity_type' => 'sacramental_participant',
            'entity_id' => $participantId,
            'old_values' => $participant,
            'meta' => $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('Sacraments.participantDeleteSuccess') ?? 'Participant removed.',
        ]);
    }
}
