<?php

namespace MemberPortal\Controllers;

use App\Controllers\BaseController;
use App\Services\MemberAccessService;

class Families extends BaseController
{
    protected $familyModel;
    protected $familyMemberModel;
    protected $profileModel;
    protected $memberParishAffiliationModel;
    protected $memberAccessService;

    public function __construct()
    {
        $this->familyModel = model('FamilyModel');
        $this->familyMemberModel = model('FamilyMemberModel');
    }

    protected function getProfileModel()
    {
        if ($this->profileModel === null) {
            $this->profileModel = model('ProfileModel');
        }

        return $this->profileModel;
    }

    protected function getMemberParishAffiliationModel()
    {
        if ($this->memberParishAffiliationModel === null) {
            $this->memberParishAffiliationModel = model('MemberParishAffiliationModel');
        }

        return $this->memberParishAffiliationModel;
    }

    protected function getMemberAccessService(): MemberAccessService
    {
        if ($this->memberAccessService === null) {
            $this->memberAccessService = service('memberAccess');
        }

        return $this->memberAccessService;
    }

    /**
     * Add member to family
     */
    public function addMember()
    {
        if ($redirect = $this->requirePermission('family.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'PARISH_SCOPE_REQUIRED'), 403);
        }

        $familyId = (int) ($this->request->getPost('family_id') ?? 0);
        $userId = (int) ($this->request->getPost('user_id') ?? 0);
        $relationship = $this->familyMemberModel->normalizeRelationship((string) ($this->request->getPost('relationship') ?? ''));

        if ($familyId <= 0 || $userId <= 0) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Families.invalidMemberSelection'), 422, 'FAMILY_MEMBER_SELECTION_INVALID'), 422);
        }

        $rawRelationship = trim((string) ($this->request->getPost('relationship') ?? ''));
        if ($rawRelationship !== '' && $relationship === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Families.relationshipInvalid'), 422, 'FAMILY_RELATIONSHIP_INVALID'), 422);
        }

        $family = $this->familyModel->find($familyId);
        if (!$family || $family['parish_id'] != $this->getOperationalParishId()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Families.invalidFamily'), 404, 'FAMILY_NOT_FOUND_IN_SCOPE'), 404);
        }

        $isAffiliated = $this->getMemberParishAffiliationModel()
            ->isUserAffiliatedWithParish((int) $userId, (int) ($this->getOperationalParishId() ?? 0), true);
        if (!$isAffiliated) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Families.invalidMemberSelection'), 422, 'FAMILY_MEMBER_SELECTION_INVALID'), 422);
        }

        if ($this->familyMemberModel->relationExists($familyId, $userId)) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Families.memberAlreadyExists'), 409, 'FAMILY_MEMBER_ALREADY_EXISTS'), 409);
        }

        if ($this->shouldEnforceSingleFamilyMembership() && $this->familyMemberModel->userHasAnotherFamily($userId, $familyId)) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Families.memberAlreadyInAnotherFamily'), 409, 'FAMILY_MEMBER_ALREADY_IN_ANOTHER_FAMILY'), 409);
        }

        $data = [
            'family_id' => $familyId,
            'user_id' => $userId,
            'relationship' => $relationship,
        ];

        $relationId = 0;
        $db = db_connect();
        $db->transBegin();

        try {
            $relationId = (int) $this->familyMemberModel->insert($data, true);
            if ($relationId <= 0) {
                throw new \RuntimeException('Failed to add family member relation');
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Failed to add family member transaction');
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();

            $message = strtolower($e->getMessage());
            if (str_contains($message, 'uniq_family_members_family_user') || str_contains($message, 'duplicate')) {
                return $this->jsonResponse($this->apiErrorPayload(lang('Families.memberAlreadyExists'), 409, 'FAMILY_MEMBER_ALREADY_EXISTS'), 409);
            }

            return $this->jsonResponse($this->apiErrorPayload(lang('Families.failedAddMember'), 500, 'FAMILY_MEMBER_ADD_FAILED'), 500);
        }

        if ($relationId) {
            $this->audit()->log('family_member.add', [
                'parish_id' => (int) ($this->getOperationalParishId() ?? 0),
                'entity_type' => 'family_member',
                'entity_id' => (int) $relationId,
                'new_values' => $data,
            ]);

            return $this->jsonResponse(['success' => true, 'message' => lang('Families.memberAddSuccess')]);
        }

        return $this->jsonResponse($this->apiErrorPayload(lang('Families.failedAddMember'), 500, 'FAMILY_MEMBER_ADD_FAILED'), 500);
    }

    /**
     * Add a non-portal member and link to family using a strict JSON contract.
     */
    public function addMemberNonPortal()
    {
        $requestId = $this->resolveRequestId();
        $parishId = (int) ($this->getOperationalParishId() ?? 0);

        if (!auth()->loggedIn()) {
            return $this->contractResponse(false, 'UNAUTHENTICATED', 'Authentication is required.', null, [[
                'field' => 'auth',
                'code' => 'UNAUTHENTICATED',
                'message' => 'Authentication is required.',
            ]], 401, $requestId, $parishId > 0 ? $parishId : null);
        }

        $canManagePortal = auth()->user()->can('family.manage');
        if (!$canManagePortal) {
            try {
                $canManagePortal = auth()->user()->can('portal.manage');
            } catch (\Throwable $e) {
                $canManagePortal = false;
            }
        }
        if (!$canManagePortal) {
            return $this->contractResponse(false, 'FORBIDDEN', 'You do not have permission for this action.', null, [[
                'field' => 'permission',
                'code' => 'FORBIDDEN',
                'message' => 'family.manage or portal.manage permission is required.',
            ]], 403, $requestId, $parishId > 0 ? $parishId : null);
        }

        if ($parishId <= 0) {
            return $this->contractResponse(false, 'OUT_OF_SCOPE', 'No operational parish scope is available.', null, [[
                'field' => 'parish_id',
                'code' => 'OUT_OF_SCOPE',
                'message' => 'No operational parish context is available.',
            ]], 403, $requestId, null);
        }

        $parsed = $this->readCompatiblePayload();
        if ($parsed['malformed_json']) {
            return $this->contractResponse(false, 'BAD_REQUEST_JSON', 'Malformed JSON payload.', null, [[
                'field' => 'body',
                'code' => 'BAD_REQUEST_JSON',
                'message' => 'Malformed JSON payload.',
            ]], 400, $requestId, $parishId);
        }

        $payload = $parsed['payload'];
        $payload['portal_access'] = (string) ($payload['portal_access'] ?? '');

        $validation = service('validation');
        $validation->setRules([
            'family_id' => 'required|integer|greater_than[0]',
            'first_name' => 'required|min_length[2]|max_length[100]',
            'last_name' => 'required|min_length[2]|max_length[100]',
            'relationship' => 'permit_empty|max_length[50]',
            'birth_date' => 'permit_empty|regex_match[/^\d{4}-\d{2}-\d{2}$/]',
            'username' => 'permit_empty|max_length[255]',
            'portal_access' => 'required|in_list[none]',
            'is_child' => 'permit_empty|in_list[0,1,true,false]',
        ]);

        if (!$validation->run($payload)) {
            return $this->contractResponse(false, 'VALIDATION_FAILED', 'Request validation failed.', null, $this->validationErrorsToContract($validation->getErrors()), 422, $requestId, $parishId);
        }

        $result = $this->getMemberAccessService()->createNonPortalUser($payload, $parishId);
        if (!($result['ok'] ?? false)) {
            return $this->contractResponse(false, (string) ($result['code'] ?? 'INTERNAL_ERROR'), (string) ($result['message'] ?? 'Request failed.'), null, is_array($result['errors'] ?? null) ? $result['errors'] : [], (int) ($result['http_status'] ?? 500), $requestId, $parishId);
        }

        $data = is_array($result['data'] ?? null) ? $result['data'] : [];
        $targetUserId = (int) ($data['user_id'] ?? 0);
        $familyId = (int) ($data['family_id'] ?? 0);
        $isStandalone = ($data['family_linked'] ?? true) === false || $familyId <= 0;

        $this->audit()->log('non_portal_member.create', [
            'actor_user_id' => (int) auth()->id(),
            'target_user_id' => $targetUserId,
            'parish_id' => $parishId,
            'family_id' => $familyId > 0 ? $familyId : null,
        ]);
        if (!$isStandalone) {
            $this->audit()->log('family_member.add_non_portal', [
                'actor_user_id' => (int) auth()->id(),
                'target_user_id' => $targetUserId,
                'parish_id' => $parishId,
                'family_id' => $familyId,
            ]);
        }

        return $this->contractResponse(true, (string) $result['code'], (string) $result['message'], $data, [], (int) $result['http_status'], $requestId, $parishId);
    }

    /**
     * Enable portal access for an existing non-portal member using a strict JSON contract.
     */
    public function enablePortal($userId)
    {
        $requestId = $this->resolveRequestId();
        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $userId = (int) $userId;

        if (!auth()->loggedIn()) {
            return $this->contractResponse(false, 'UNAUTHENTICATED', 'Authentication is required.', null, [[
                'field' => 'auth',
                'code' => 'UNAUTHENTICATED',
                'message' => 'Authentication is required.',
            ]], 401, $requestId, $parishId > 0 ? $parishId : null);
        }

        if (!auth()->user()->can('family.manage')) {
            return $this->contractResponse(false, 'FORBIDDEN', 'You do not have permission for this action.', null, [[
                'field' => 'permission',
                'code' => 'FORBIDDEN',
                'message' => 'family.manage permission is required.',
            ]], 403, $requestId, $parishId > 0 ? $parishId : null);
        }

        if ($parishId <= 0) {
            return $this->contractResponse(false, 'OUT_OF_SCOPE', 'No operational parish scope is available.', null, [[
                'field' => 'parish_id',
                'code' => 'OUT_OF_SCOPE',
                'message' => 'No operational parish context is available.',
            ]], 403, $requestId, null);
        }

        if ($userId <= 0) {
            return $this->contractResponse(false, 'VALIDATION_FAILED', 'Request validation failed.', null, [[
                'field' => 'userId',
                'code' => 'INVALID',
                'message' => 'userId must be greater than 0.',
            ]], 422, $requestId, $parishId);
        }

        $parsed = $this->readCompatiblePayload();
        if ($parsed['malformed_json']) {
            return $this->contractResponse(false, 'BAD_REQUEST_JSON', 'Malformed JSON payload.', null, [[
                'field' => 'body',
                'code' => 'BAD_REQUEST_JSON',
                'message' => 'Malformed JSON payload.',
            ]], 400, $requestId, $parishId);
        }

        $payload = $parsed['payload'];
        if (isset($payload['email'])) {
            $payload['email'] = strtolower(trim((string) $payload['email']));
        }

        $validation = service('validation');
        $validation->setRules([
            'email' => 'required|valid_email',
            'family_id' => 'required|integer|greater_than[0]',
        ]);

        if (!$validation->run($payload)) {
            return $this->contractResponse(false, 'VALIDATION_FAILED', 'Request validation failed.', null, $this->validationErrorsToContract($validation->getErrors()), 422, $requestId, $parishId);
        }

        $result = $this->getMemberAccessService()->enablePortalAccess($userId, $payload, $parishId);
        if (!($result['ok'] ?? false)) {
            return $this->contractResponse(false, (string) ($result['code'] ?? 'INTERNAL_ERROR'), (string) ($result['message'] ?? 'Request failed.'), null, is_array($result['errors'] ?? null) ? $result['errors'] : [], (int) ($result['http_status'] ?? 500), $requestId, $parishId);
        }

        $data = is_array($result['data'] ?? null) ? $result['data'] : [];

        $this->audit()->log('portal_access.enable', [
            'actor_user_id' => (int) auth()->id(),
            'target_user_id' => $userId,
            'parish_id' => $parishId,
            'family_id' => (int) ($payload['family_id'] ?? 0),
        ]);

        return $this->contractResponse(true, (string) $result['code'], (string) $result['message'], $data, [], (int) $result['http_status'], $requestId, $parishId);
    }

    /**
     * Remove member from family
     */
    public function removeMember($id)
    {
        if ($redirect = $this->requirePermission('family.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'PARISH_SCOPE_REQUIRED'), 403);
        }

        $relation = $this->familyMemberModel->find($id);
        if (!$relation) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Families.relationNotFound'), 404, 'FAMILY_RELATION_NOT_FOUND'), 404);
        }

        $family = $this->familyModel->find((int) ($relation['family_id'] ?? 0));
        if (!$family || (int) ($family['parish_id'] ?? 0) !== (int) ($this->getOperationalParishId() ?? 0)) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorUnauthorized'), 403, 'FAMILY_RELATION_SCOPE_FORBIDDEN'), 403);
        }

        $deleted = false;
        $db = db_connect();
        $db->transBegin();

        try {
            $deleted = $this->familyMemberModel->delete($id);
            if (! $deleted) {
                throw new \RuntimeException('Failed to delete family member relation');
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Failed to delete family member transaction');
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            $deleted = false;
        }

        if ($deleted) {
            $this->audit()->log('family_member.remove', [
                'parish_id' => (int) ($this->getOperationalParishId() ?? 0),
                'entity_type' => 'family_member',
                'entity_id' => (int) $id,
                'old_values' => [
                    'family_id' => (int) ($relation['family_id'] ?? 0),
                    'user_id' => (int) ($relation['user_id'] ?? 0),
                    'relationship' => (string) ($relation['relationship'] ?? ''),
                ],
            ]);

            return $this->jsonResponse(['success' => true, 'message' => lang('Families.memberRemoveSuccess')]);
        }

        return $this->jsonResponse($this->apiErrorPayload(lang('Families.failedRemoveMember'), 500, 'FAMILY_MEMBER_REMOVE_FAILED'), 500);
    }

    public function availableMembers($familyId = null)
    {
        if ($redirect = $this->requirePermission('family.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'PARISH_SCOPE_REQUIRED'), 403);
        }

        $familyId = (int) ($familyId ?? $this->request->getGet('family_id') ?? 0);
        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $family = $this->familyModel->find($familyId);

        if (! $family || (int) ($family['parish_id'] ?? 0) !== $parishId) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Families.invalidFamily'), 404, 'FAMILY_NOT_FOUND_IN_SCOPE'), 404);
        }

        $query = trim((string) ($this->request->getGet('q') ?? ''));
        $limit = max(1, min(100, (int) ($this->request->getGet('limit') ?? 50)));

        $members = $this->getMemberParishAffiliationModel()
            ->getParishMembersAvailableForFamily($parishId, $familyId, $query, $limit);

        return $this->jsonResponse([
            'success' => true,
            'message' => 'OK',
            'data' => $members,
        ]);
    }

    protected function isParishAffiliatedMember(int $userId, int $parishId): bool
    {
        return $this->getMemberParishAffiliationModel()->isUserAffiliatedWithParish($userId, $parishId, true);
    }

    protected function ensureHeadOfHouseholdMembership(int $familyId, ?int $headOfHouseholdId): void
    {
        if ($headOfHouseholdId === null || $headOfHouseholdId <= 0) {
            return;
        }

        $existing = $this->familyMemberModel
            ->where('family_id', $familyId)
            ->where('user_id', $headOfHouseholdId)
            ->first();

        if ($existing === null) {
            $this->familyMemberModel->insert([
                'family_id' => $familyId,
                'user_id' => $headOfHouseholdId,
                'relationship' => 'Head',
            ]);
            return;
        }

        $currentRelationship = trim((string) ($existing['relationship'] ?? ''));
        if ($currentRelationship !== 'Head') {
            $this->familyMemberModel->update((int) $existing['id'], ['relationship' => 'Head']);
        }
    }

    private function shouldEnforceSingleFamilyMembership(): bool
    {
        $value = strtolower(trim((string) (env('families.enforceSingleFamilyMembership') ?? '0')));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @return array{payload: array<string,mixed>, malformed_json: bool}
     */
    private function readCompatiblePayload(): array
    {
        $contentType = strtolower((string) $this->request->getHeaderLine('Content-Type'));
        $rawBody = trim((string) $this->request->getBody());
        $isJson = str_contains($contentType, 'application/json') || ($rawBody !== '' && str_starts_with($rawBody, '{'));

        if ($isJson) {
            $decoded = json_decode($rawBody, true);
            if ($rawBody !== '' && (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded))) {
                return ['payload' => [], 'malformed_json' => true];
            }

            return ['payload' => is_array($decoded) ? $decoded : [], 'malformed_json' => false];
        }

        $post = $this->request->getPost();
        return ['payload' => is_array($post) ? $post : [], 'malformed_json' => false];
    }

    private function resolveRequestId(): string
    {
        $header = trim((string) $this->request->getHeaderLine('X-Request-Id'));
        if ($header !== '') {
            return $header;
        }

        return 'req_' . bin2hex(random_bytes(8));
    }

    /**
     * @param array<string,string> $validationErrors
     * @return array<int,array<string,string>>
     */
    private function validationErrorsToContract(array $validationErrors): array
    {
        $errors = [];
        foreach ($validationErrors as $field => $message) {
            $errors[] = [
                'field' => (string) $field,
                'code' => 'VALIDATION_ERROR',
                'message' => (string) $message,
            ];
        }

        return $errors;
    }

    /**
     * @param array<string,mixed>|null $data
     * @param array<int,array<string,string>> $errors
     */
    private function contractResponse(bool $success, string $code, string $message, ?array $data, array $errors, int $status, ?string $requestId, ?int $parishId)
    {
        $payload = [
            'success' => $success,
            'code' => $code,
            'message' => $message,
            'data' => $success ? ($data ?? []) : null,
            'errors' => $success ? [] : $errors,
            'meta' => [
                'request_id' => (string) ($requestId ?? $this->resolveRequestId()),
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                'parish_id' => $parishId,
            ],
        ];

        return $this->response
            ->setStatusCode($status)
            ->setContentType('application/json')
            ->setJSON($payload);
    }

    /**
     * Preselect payload for the head-of-household AJAX picker.
     *
     * @return array<int,array{id:int,text:string}>
     */
    protected function resolveHeadOption(int $parishId, int $userId): array
    {
        if ($parishId <= 0 || $userId <= 0) {
            return [];
        }

        $option = $this->getMemberParishAffiliationModel()->memberOption($parishId, $userId);

        return $option === null ? [] : [$option];
    }
}
