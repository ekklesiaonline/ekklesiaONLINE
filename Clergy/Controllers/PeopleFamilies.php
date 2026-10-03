<?php

namespace Clergy\Controllers;

use App\Controllers\BaseController;
use App\Services\FamilyService;
use App\Services\PeopleViewDataService;

/**
 * Family/household endpoints extracted from People (fat-controller split).
 * Route handlers under /people/family* are repointed here; URLs and
 * permission filters are unchanged.
 */
class PeopleFamilies extends BaseController
{
    private PeopleViewDataService $peopleViewDataService;
    private FamilyService $familyService;

    public function __construct()
    {
        $this->peopleViewDataService = service('peopleViewData');
        $this->familyService = new FamilyService();
    }

    /**
     * Create a new family with the current person as head of household.
     */
    public function createFamilyForPerson(int $profileId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('family.manage')) {
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

        $userId = (int) ($profile['user_id'] ?? 0);
        if ($userId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'PERSON_NOT_FOUND'), 404);
        }

        if (!$this->validate($this->familyService->familyNameRules())) {
            return $this->jsonResponse([
                'success' => false,
                'code' => 'VALIDATION_FAILED',
                'message' => 'Validation failed.',
                'errors' => $this->validator->getErrors(),
            ], 422);
        }

        if ($this->familyService->userHasAnotherFamily($userId)) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Families.memberAlreadyInAnotherFamily'), 409, 'PERSON_ALREADY_IN_FAMILY'), 409);
        }

        $familyId = $this->familyService->createFamilyWithHead(
            $parishId,
            trim((string) $this->request->getPost('family_name')),
            $userId,
            trim((string) ($this->request->getPost('address') ?? ''))
        );

        if ($familyId === null) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Families.createFailed'),
            ], 500);
        }

        $this->audit()->log('family.create', [
            'parish_id' => $parishId,
            'entity_type' => 'family',
            'entity_id' => $familyId,
            'new_values' => [
                'family_name' => trim((string) $this->request->getPost('family_name')),
                'head_of_household_id' => $userId,
            ],
            'meta' => $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('Families.createSuccess'),
            'data' => ['family_id' => $familyId],
        ]);
    }

    /**
     * Add the current person to an existing family.
     */
    public function addPersonToFamily(int $profileId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('family.manage')) {
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

        $userId = (int) ($profile['user_id'] ?? 0);
        if ($userId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'PERSON_NOT_FOUND'), 404);
        }

        $familyId = (int) ($this->request->getPost('family_id') ?? 0);
        if ($familyId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Families.invalidFamily'), 422, 'FAMILY_REQUIRED'), 422);
        }

        $family = $this->familyService->findFamilyInParish($familyId, $parishId);
        if ($family === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Families.invalidFamily'), 404, 'FAMILY_NOT_FOUND'), 404);
        }

        $relationship = $this->familyService->normalizeRelationship((string) ($this->request->getPost('relationship') ?? ''));
        if ($relationship === null && trim((string) ($this->request->getPost('relationship') ?? '')) !== '') {
            return $this->jsonResponse($this->apiErrorPayload(lang('Families.relationshipInvalid'), 422, 'INVALID_RELATIONSHIP'), 422);
        }

        if ($this->familyService->relationExists($familyId, $userId)) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Families.memberAlreadyExists'), 409, 'ALREADY_IN_FAMILY'), 409);
        }

        if ($this->familyService->enforceSingleFamilyMembership() && $this->familyService->userHasAnotherFamily($userId, $familyId)) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Families.memberAlreadyInAnotherFamily'), 409, 'ALREADY_IN_ANOTHER_FAMILY'), 409);
        }

        $relationId = $this->familyService->addMember($familyId, $userId, $relationship);

        if ($relationId === null) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Families.failedAddMember'),
            ], 500);
        }

        $this->audit()->log('family_member.add', [
            'parish_id' => $parishId,
            'entity_type' => 'family_member',
            'entity_id' => $relationId,
            'new_values' => [
                'family_id' => $familyId,
                'user_id' => $userId,
                'relationship' => $relationship,
            ],
            'meta' => $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('Families.memberAddSuccess'),
            'data' => ['family_member_id' => $relationId],
        ]);
    }

    /**
     * Remove the current person from their family.
     */
    public function removePersonFromFamily(int $profileId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('family.manage')) {
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

        $userId = (int) ($profile['user_id'] ?? 0);
        if ($userId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'PERSON_NOT_FOUND'), 404);
        }

        $relation = $this->familyService->findRelationForUser($userId);
        if ($relation === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Families.relationNotFound'), 404, 'NOT_IN_FAMILY'), 404);
        }

        $family = $this->familyService->findFamilyInParish((int) ($relation['family_id'] ?? 0), $parishId);
        if ($family === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FAMILY_SCOPE_FORBIDDEN'), 403);
        }

        $relationId = (int) ($relation['id'] ?? 0);
        if (!$this->familyService->removeMember($relationId)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Families.failedRemoveMember'),
            ], 500);
        }

        $this->audit()->log('family_member.remove', [
            'parish_id' => $parishId,
            'entity_type' => 'family_member',
            'entity_id' => $relationId,
            'old_values' => $relation,
            'meta' => $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('Families.memberRemoveSuccess'),
        ]);
    }

    /**
     * Update the current person's relationship within their family.
     */
    public function updateFamilyRelationship(int $profileId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('family.manage')) {
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

        $userId = (int) ($profile['user_id'] ?? 0);
        if ($userId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNotFound'), 404, 'PERSON_NOT_FOUND'), 404);
        }

        $relation = $this->familyService->findRelationForUser($userId);
        if ($relation === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Families.relationNotFound'), 404, 'NOT_IN_FAMILY'), 404);
        }

        $family = $this->familyService->findFamilyInParish((int) ($relation['family_id'] ?? 0), $parishId);
        if ($family === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FAMILY_SCOPE_FORBIDDEN'), 403);
        }

        $relationship = $this->familyService->normalizeRelationship((string) ($this->request->getPost('relationship') ?? ''));
        if ($relationship === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Families.relationshipInvalid'), 422, 'INVALID_RELATIONSHIP'), 422);
        }

        $relationId = (int) ($relation['id'] ?? 0);
        if (!$this->familyService->updateRelationship($relationId, $relationship)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Families.updateFailed'),
            ], 500);
        }

        $this->audit()->log('family_member.update', [
            'parish_id' => $parishId,
            'entity_type' => 'family_member',
            'entity_id' => $relationId,
            'old_values' => $relation,
            'new_values' => ['relationship' => $relationship],
            'meta' => $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('Families.updateSuccess'),
        ]);
    }
}
