<?php

namespace Clergy\Controllers;

use App\Controllers\AdminHierarchyGuard;
use App\Controllers\BaseController;
use App\Services\LeadershipPositionService;

/**
 * Leadership-position endpoints extracted from People (fat-controller split).
 * Route handlers under /people/leadership* and /people/leadership-eligibility/*
 * are repointed here; URLs and permission filters are unchanged.
 */
class PeopleLeadership extends BaseController
{
    use AdminHierarchyGuard;

    private LeadershipPositionService $leadershipPositionService;

    public function __construct()
    {
        $this->leadershipPositionService = new LeadershipPositionService();
    }

    /**
     * Leadership eligibility probe for the appointment modal: returns the
     * per-requirement checklist for the person identified by profile_id so
     * the UI can show what is missing before the form is submitted.
     */
    public function leadershipEligibility(int $profileId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('leadership_position.manage')) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FORBIDDEN'), 403);
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        $profile = model('ProfileModel')->find($profileId);
        if (!$profile) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'PROFILE_NOT_FOUND'), 404);
        }

        $userId = (int) ($profile['user_id'] ?? 0);
        $personId = (int) ($profile['person_id'] ?? 0);
        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $scopeType = (string) ($this->request->getGet('scope_type') ?: 'parish');
        $scopeIds = $this->leadershipPositionService->resolveScopeIds(
            $scopeType, $parishId, $this->isDioceseAdmin() ? $this->resolveDiocesanScopeId() : null,
            null, (int) ($this->request->getGet('deanery_id') ?? 0)
        );
        if ($scopeIds === null || ! $this->leadershipPositionService->canManage(
            array_merge(['scope_type' => $scopeType], $scopeIds), $parishId,
            $this->isDioceseAdmin() ? $this->resolveDiocesanScopeId() : null, null
        )) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'POSITION_OUT_OF_SCOPE'), 403);
        }

        $eligibility = $this->leadershipPositionService->eligibilityFor(
            $userId,
            $personId,
            $this->leadershipPositionService->resolveEligibilityParishId($personId, $parishId, $userId),
            array_merge($scopeIds, [
                'scope_type' => $scopeType,
                'role_category' => (string) $this->request->getGet('role_category'),
                'role_code' => (string) $this->request->getGet('role_code'),
                'clergy_order' => (string) $this->request->getGet('clergy_order'),
            ])
        );

        return $this->jsonResponse([
            'success' => true,
            'data'    => $eligibility + [
                'message' => ($eligibility['eligible'] ?? false)
                    ? ''
                    : $this->leadershipPositionService->eligibilityMessage($eligibility),
            ],
        ]);
    }

    /**
     * Add a leadership position for the person identified by profile_id.
     */
    public function addLeadershipPosition(int $profileId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('leadership_position.manage')) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FORBIDDEN'), 403);
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'NO_PARISH'), 403);
        }

        if ($this->isProvinceAdmin()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'PROVINCE_WRITE_DISABLED'), 403);
        }

        $profile = model('ProfileModel')->find($profileId);
        if (!$profile) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'PROFILE_NOT_FOUND'), 404);
        }

        $userId = (int) ($profile['user_id'] ?? 0);
        $personId = (int) ($profile['person_id'] ?? 0);
        if ($userId <= 0 || $personId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'PERSON_NOT_FOUND'), 404);
        }

        if ($code = $this->guardTargetUserMutation($userId)) {
            return $this->jsonResponse($this->apiErrorPayload($this->hierarchyGuardMessage($code), 403, 'TARGET_OUTRANKS_ACTOR'), 403);
        }

        if (!$this->validate($this->leadershipPositionService->validationRules(true))) {
            return $this->jsonResponse($this->apiErrorPayload('Validation failed.', 422, 'VALIDATION_FAILED', $this->validator->getErrors()), 422);
        }

        $scopeType = (string) $this->request->getPost('scope_type');
        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($scopeType === 'province' || ($scopeType !== 'parish' && ! $this->isDioceseAdmin())) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'POSITION_OUT_OF_SCOPE'), 403);
        }

        $scopeIds = $this->leadershipPositionService->resolveScopeIds(
            $scopeType,
            $parishId,
            $this->isDioceseAdmin() ? $this->resolveDiocesanScopeId() : null,
            null,
            (int) ($this->request->getPost('deanery_id') ?? 0)
        );
        if ($scopeIds === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 400, 'SCOPE_NOT_FOUND'), 400);
        }
        if (! $this->leadershipPositionService->canManage(
            array_merge(['scope_type' => $scopeType], $scopeIds), $parishId,
            $this->isDioceseAdmin() ? $this->resolveDiocesanScopeId() : null, null
        )) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'POSITION_OUT_OF_SCOPE'), 403);
        }

        $payload = [
            'user_id' => $userId,
            'person_id' => $personId,
            'scope_type' => $scopeType,
            'parish_id' => $scopeIds['parish_id'],
            'deanery_id' => $scopeIds['deanery_id'],
            'diocese_id' => $scopeIds['diocese_id'],
            'province_id' => $scopeIds['province_id'],
            'title' => trim((string) $this->request->getPost('title')),
            'role_code' => trim((string) ($this->request->getPost('role_code') ?? '')),
            'role_category' => (string) ($this->request->getPost('role_category') ?? ''),
            'clergy_order' => (string) ($this->request->getPost('clergy_order') ?? ''),
            'pastoral_in_charge' => $this->request->getPost('pastoral_in_charge') ? 1 : 0,
            'employment_type' => (string) ($this->request->getPost('employment_type') ?? ''),
            'active' => (int) ($this->request->getPost('active') === '1'),
            'started_at' => (string) ($this->request->getPost('started_at') ?? '') ?: null,
            'ended_at' => (string) ($this->request->getPost('ended_at') ?? '') ?: null,
            'notes' => trim((string) ($this->request->getPost('notes') ?? '')),
            'appointed_by_user_id' => (int) (auth()->id() ?? 0),
        ];

        $eligibility = $this->leadershipPositionService->eligibilityFor(
            $userId,
            $personId,
            $this->leadershipPositionService->resolveEligibilityParishId($personId, $scopeIds['parish_id'], $userId),
            $payload
        );
        if (! ($eligibility['eligible'] ?? false)) {
            return $this->jsonResponse($this->apiErrorPayload(
                $this->leadershipPositionService->eligibilityMessage($eligibility),
                422,
                'LEADERSHIP_ELIGIBILITY_FAILED',
                ['failed_requirements' => $eligibility['failed'] ?? []]
            ), 422);
        }

        $positionId = $this->leadershipPositionService->create($payload);
        if ($positionId === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Leadership.saveFailed'), 422, 'APPOINTMENT_INVALID'), 422);
        }

        $this->audit()->log('leadership_position.create', [
            'parish_id' => $parishId,
            'entity_type' => 'leadership_position',
            'entity_id' => $positionId,
            'new_values' => ['scope_type' => $scopeType, 'role_code' => $payload['role_code']],
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('Leadership.saveSuccess'),
            'data' => ['id' => $positionId],
        ]);
    }

    /**
     * Update an existing leadership position for a person.
     */
    public function updateLeadershipPosition(int $positionId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('leadership_position.manage')) {
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

        $position = $this->leadershipPositionService->find($positionId);
        if (!$position) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'POSITION_NOT_FOUND'), 404);
        }

        if (!$this->canManageLeadershipPosition($position, $parishId)) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'POSITION_OUT_OF_SCOPE'), 403);
        }

        if ($code = $this->guardTargetUserMutation((int) ($position['user_id'] ?? 0))) {
            return $this->jsonResponse($this->apiErrorPayload($this->hierarchyGuardMessage($code), 403, 'TARGET_OUTRANKS_ACTOR'), 403);
        }

        if (!$this->validate($this->leadershipPositionService->validationRules(false))) {
            return $this->jsonResponse($this->apiErrorPayload('Validation failed.', 422, 'VALIDATION_FAILED', $this->validator->getErrors()), 422);
        }

        $payload = [
            'title' => trim((string) $this->request->getPost('title')),
            'role_code' => trim((string) ($this->request->getPost('role_code') ?? '')),
            'role_category' => (string) ($this->request->getPost('role_category') ?? ''),
            'clergy_order' => (string) ($this->request->getPost('clergy_order') ?? ''),
            'employment_type' => (string) ($this->request->getPost('employment_type') ?? ''),
            'active' => (int) ($this->request->getPost('active') === '1'),
            'started_at' => (string) ($this->request->getPost('started_at') ?? '') ?: null,
            'ended_at' => (string) ($this->request->getPost('ended_at') ?? '') ?: null,
            'notes' => trim((string) ($this->request->getPost('notes') ?? '')),
        ];
        if ($this->request->getPost('pastoral_in_charge') !== null) {
            $payload['pastoral_in_charge'] = $this->request->getPost('pastoral_in_charge') ? 1 : 0;
        }

        // Reactivating a position (active 0→1) must clear the same
        // eligibility gate as a new appointment.
        if ((int) ($position['active'] ?? 0) !== 1 && $payload['active'] === 1) {
            $eligUserId = (int) ($position['user_id'] ?? 0);
            $eligPersonId = (int) ($position['person_id'] ?? 0);
            if ($eligPersonId <= 0) {
                $eligPersonId = (int) ($this->leadershipPositionService->resolvePersonIdForUser($eligUserId) ?? 0);
            }
            $eligParishId = $this->leadershipPositionService->resolveEligibilityParishId(
                $eligPersonId,
                (int) ($position['parish_id'] ?? 0) > 0 ? (int) $position['parish_id'] : null,
                $eligUserId
            );

            $eligibility = $this->leadershipPositionService->eligibilityFor(
                $eligUserId,
                $eligPersonId,
                $eligParishId,
                array_merge($position, $payload)
            );
            if (! ($eligibility['eligible'] ?? false)) {
                return $this->jsonResponse($this->apiErrorPayload(
                    $this->leadershipPositionService->eligibilityMessage($eligibility),
                    422,
                    'LEADERSHIP_ELIGIBILITY_FAILED',
                    ['failed_requirements' => $eligibility['failed'] ?? []]
                ), 422);
            }
        }

        if (!$this->leadershipPositionService->update($positionId, $payload)) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Leadership.updateFailed'), 422, 'APPOINTMENT_INVALID'), 422);
        }

        $this->audit()->log('leadership_position.update', [
            'parish_id' => $parishId,
            'entity_type' => 'leadership_position',
            'entity_id' => $positionId,
            'new_values' => ['role_code' => $payload['role_code'], 'active' => $payload['active']],
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('Leadership.updateSuccess'),
        ]);
    }

    /**
     * Delete a leadership position for a person.
     */
    public function deleteLeadershipPosition(int $positionId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('leadership_position.manage')) {
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

        $position = $this->leadershipPositionService->find($positionId);
        if (!$position) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'POSITION_NOT_FOUND'), 404);
        }

        if (!$this->canManageLeadershipPosition($position, $parishId)) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'POSITION_OUT_OF_SCOPE'), 403);
        }

        if ($code = $this->guardTargetUserMutation((int) ($position['user_id'] ?? 0))) {
            return $this->jsonResponse($this->apiErrorPayload($this->hierarchyGuardMessage($code), 403, 'TARGET_OUTRANKS_ACTOR'), 403);
        }

        if (!$this->leadershipPositionService->delete($positionId)) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Leadership.deleteFailed'), 500, 'APPOINTMENT_DELETE_FAILED'), 500);
        }

        $this->audit()->log('leadership_position.delete', [
            'parish_id' => $parishId,
            'entity_type' => 'leadership_position',
            'entity_id' => $positionId,
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('Leadership.deleteSuccess'),
        ]);
    }

    /**
     * Determine whether the current admin can manage a leadership position.
     * Delegates to LeadershipPositionService with the actor's scope IDs.
     */
    private function canManageLeadershipPosition(array $position, int $parishId): bool
    {
        return $this->leadershipPositionService->canManage(
            $position,
            $parishId,
            $this->resolveDiocesanScopeId(),
            $this->resolveAdminProvinceId()
        );
    }
}
