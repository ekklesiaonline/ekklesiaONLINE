<?php

namespace Clergy\Controllers;

use App\Controllers\AdminHierarchyGuard;
use App\Controllers\BaseController;
use App\Services\AnnualPledgeService;
use App\Services\DonationService;
use App\Services\PeopleViewDataService;

/**
 * Donation/pledge endpoints extracted from People (fat-controller split).
 * Route handlers under /people/donation* and /people/pledge* are repointed
 * here; URLs and permission filters are unchanged.
 */
class PeopleFinance extends BaseController
{
    use AdminHierarchyGuard;

    private PeopleViewDataService $peopleViewDataService;
    private DonationService $donationService;
    private AnnualPledgeService $annualPledgeService;

    public function __construct()
    {
        $this->peopleViewDataService = service('peopleViewData');
        $this->donationService = new DonationService();
        $this->annualPledgeService = new AnnualPledgeService();
    }

    /**
     * Add a donation on behalf of a person.
     */
    public function addDonation(int $profileId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('donation.create')) {
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

        if ($code = $this->guardTargetUserMutation($userId)) {
            return $this->jsonResponse($this->apiErrorPayload($this->hierarchyGuardMessage($code), 403, 'TARGET_OUTRANKS_ACTOR'), 403);
        }

        if (!$this->validate($this->donationService->validationRules())) {
            return $this->jsonResponse([
                'success' => false,
                'code' => 'VALIDATION_FAILED',
                'message' => 'Validation failed.',
                'errors' => $this->validator->getErrors(),
            ], 422);
        }

        $fundId = (int) $this->request->getPost('fund_id');
        $fund = $this->donationService->findUsableFund($fundId, $parishId);
        if ($fund === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Treasurer.validFundRequired'), 400, 'INVALID_FUND'), 400);
        }

        $chartAccount = $this->donationService->findIncomeAccount((int) $this->request->getPost('chart_account_id'));

        if ($chartAccount === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Treasurer.validIncomeAccountRequired'), 400, 'INVALID_CHART_ACCOUNT'), 400);
        }

        $data = [
            'parish_id' => $parishId,
            'fund_id' => $fundId,
            'user_id' => $userId,
            'date' => (string) $this->request->getPost('date'),
            'amount' => (string) $this->request->getPost('amount'),
            'category' => (string) ($chartAccount['category'] ?? ($this->request->getPost('category') ?: 'other')),
            'chart_account_id' => (int) $chartAccount['id'],
            'payment_method' => (string) ($this->request->getPost('payment_method') ?? ''),
            'notes' => trim((string) ($this->request->getPost('notes') ?? '')),
            ...$this->donationService->restrictionDataForFund($fund),
        ];

        $donationId = $this->donationService->create($data);
        if ($donationId === null) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Treasurer.donationRecordFailed'),
            ], 500);
        }

        $receipt = $this->donationService->issueReceipt($donationId);

        $this->audit()->log('donation.create', [
            'parish_id' => $parishId,
            'entity_type' => 'donation',
            'entity_id' => $donationId,
            'new_values' => [
                'fund_id' => $fundId,
                'user_id' => $userId,
                'date' => $data['date'],
                'amount' => $data['amount'],
                'category' => $data['category'],
                'chart_account_id' => $data['chart_account_id'],
                'receipt_number' => (string) ($receipt['receipt_number'] ?? ''),
            ],
            'meta' => $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('Treasurer.donationRecorded'),
            'data' => ['id' => $donationId],
        ]);
    }

    /**
     * Update a donation for a person.
     */
    public function updateDonation(int $donationId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('donation.edit')) {
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

        $donation = $this->donationService->findInParish($donationId, $parishId);
        if ($donation === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Treasurer.donationNotFound'), 404, 'DONATION_NOT_FOUND'), 404);
        }

        if ($code = $this->guardTargetUserMutation((int) ($donation['user_id'] ?? 0))) {
            return $this->jsonResponse($this->apiErrorPayload($this->hierarchyGuardMessage($code), 403, 'TARGET_OUTRANKS_ACTOR'), 403);
        }

        if (!$this->validate($this->donationService->validationRules())) {
            return $this->jsonResponse([
                'success' => false,
                'code' => 'VALIDATION_FAILED',
                'message' => 'Validation failed.',
                'errors' => $this->validator->getErrors(),
            ], 422);
        }

        $fundId = (int) $this->request->getPost('fund_id');
        $fund = $this->donationService->findUsableFund($fundId, $parishId);
        if ($fund === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Treasurer.validFundRequired'), 400, 'INVALID_FUND'), 400);
        }

        $chartAccount = $this->donationService->findIncomeAccount((int) $this->request->getPost('chart_account_id'));

        if ($chartAccount === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Treasurer.validIncomeAccountRequired'), 400, 'INVALID_CHART_ACCOUNT'), 400);
        }

        $data = [
            'fund_id' => $fundId,
            'date' => (string) $this->request->getPost('date'),
            'amount' => (string) $this->request->getPost('amount'),
            'category' => (string) ($chartAccount['category'] ?? ($this->request->getPost('category') ?: 'other')),
            'chart_account_id' => (int) $chartAccount['id'],
            'payment_method' => (string) ($this->request->getPost('payment_method') ?? ''),
            'notes' => trim((string) ($this->request->getPost('notes') ?? '')),
            ...$this->donationService->restrictionDataForFund($fund),
        ];

        if (!$this->donationService->update($donationId, $data)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Treasurer.donationUpdateFailed') ?? 'Failed to update donation.',
            ], 500);
        }

        $this->audit()->log('donation.update', [
            'parish_id' => $parishId,
            'entity_type' => 'donation',
            'entity_id' => $donationId,
            'old_values' => $donation,
            'new_values' => $data,
            'meta' => $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('Treasurer.donationUpdated') ?? 'Donation updated.',
        ]);
    }

    /**
     * Delete a donation for a person.
     */
    public function deleteDonation(int $donationId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('donation.delete')) {
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

        $donation = $this->donationService->findInParish($donationId, $parishId);
        if ($donation === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('Treasurer.donationNotFound'), 404, 'DONATION_NOT_FOUND'), 404);
        }

        if ($code = $this->guardTargetUserMutation((int) ($donation['user_id'] ?? 0))) {
            return $this->jsonResponse($this->apiErrorPayload($this->hierarchyGuardMessage($code), 403, 'TARGET_OUTRANKS_ACTOR'), 403);
        }

        if (!$this->donationService->delete($donationId)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Treasurer.donationDeleteFailed'),
            ], 500);
        }

        $this->audit()->log('donation.delete', [
            'parish_id' => $parishId,
            'entity_type' => 'donation',
            'entity_id' => $donationId,
            'old_values' => $donation,
            'meta' => $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('Treasurer.donationDeleted'),
        ]);
    }

    /**
     * Add an annual pledge for a person.
     */
    public function addPledge(int $profileId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('annual_pledge.manage')) {
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

        if ($code = $this->guardTargetUserMutation($userId)) {
            return $this->jsonResponse($this->apiErrorPayload($this->hierarchyGuardMessage($code), 403, 'TARGET_OUTRANKS_ACTOR'), 403);
        }

        if (!$this->validate($this->annualPledgeService->validationRules(true))) {
            return $this->jsonResponse([
                'success' => false,
                'code' => 'VALIDATION_FAILED',
                'message' => 'Validation failed.',
                'errors' => $this->validator->getErrors(),
            ], 422);
        }

        $payload = [
            'user_id' => $userId,
            'parish_id' => $parishId,
            'pledge_year' => (int) $this->request->getPost('pledge_year'),
            'pledged_amount' => $this->request->getPost('pledged_amount') !== '' ? (float) $this->request->getPost('pledged_amount') : null,
            'fulfilled_amount' => $this->request->getPost('fulfilled_amount') !== '' ? (float) $this->request->getPost('fulfilled_amount') : 0,
            'pledged_at' => (string) ($this->request->getPost('pledged_at') ?? '') ?: null,
            'fulfillment_status' => (string) $this->request->getPost('fulfillment_status'),
            'notes' => trim((string) ($this->request->getPost('notes') ?? '')),
        ];

        $pledgeId = $this->annualPledgeService->create($payload);
        if ($pledgeId === null) {
            return $this->jsonResponse([
                'success' => false,
                'message' => implode(' ', array_values($this->annualPledgeService->errors() ?: [lang('Pledges.saveFailed')])),
            ], 500);
        }

        $this->audit()->log('annual_pledge.create', [
            'parish_id' => $parishId,
            'entity_type' => 'annual_pledge',
            'entity_id' => $pledgeId,
            'new_values' => $payload,
            'meta' => $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('App.pledgeSavedSuccess'),
            'data' => ['id' => $pledgeId],
        ]);
    }

    /**
     * Update an annual pledge for a person.
     */
    public function updatePledge(int $pledgeId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('annual_pledge.manage')) {
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

        $pledge = $this->annualPledgeService->findInParish($pledgeId, $parishId);
        if ($pledge === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.pledgeNotFound'), 404, 'PLEDGE_NOT_FOUND'), 404);
        }

        if ($code = $this->guardTargetUserMutation((int) ($pledge['user_id'] ?? 0))) {
            return $this->jsonResponse($this->apiErrorPayload($this->hierarchyGuardMessage($code), 403, 'TARGET_OUTRANKS_ACTOR'), 403);
        }

        if (!$this->validate($this->annualPledgeService->validationRules(false))) {
            return $this->jsonResponse([
                'success' => false,
                'code' => 'VALIDATION_FAILED',
                'message' => 'Validation failed.',
                'errors' => $this->validator->getErrors(),
            ], 422);
        }

        $payload = [
            'pledged_amount' => $this->request->getPost('pledged_amount') !== '' ? (float) $this->request->getPost('pledged_amount') : null,
            'fulfilled_amount' => $this->request->getPost('fulfilled_amount') !== '' ? (float) $this->request->getPost('fulfilled_amount') : 0,
            'pledged_at' => (string) ($this->request->getPost('pledged_at') ?? '') ?: null,
            'fulfillment_status' => (string) $this->request->getPost('fulfillment_status'),
            'notes' => trim((string) ($this->request->getPost('notes') ?? '')),
        ];

        if (!$this->annualPledgeService->update($pledgeId, $payload)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => implode(' ', array_values($this->annualPledgeService->errors() ?: [lang('Pledges.updateFailed')])),
            ], 500);
        }

        $this->audit()->log('annual_pledge.update', [
            'parish_id' => $parishId,
            'entity_type' => 'annual_pledge',
            'entity_id' => $pledgeId,
            'old_values' => $pledge,
            'new_values' => $payload,
            'meta' => $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('App.pledgeUpdatedSuccess'),
        ]);
    }

    /**
     * Delete an annual pledge for a person.
     */
    public function deletePledge(int $pledgeId): \CodeIgniter\HTTP\ResponseInterface
    {
        if ($redirect = $this->requirePermission('annual_pledge.manage')) {
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

        $pledge = $this->annualPledgeService->findInParish($pledgeId, $parishId);
        if ($pledge === null) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.pledgeNotFound'), 404, 'PLEDGE_NOT_FOUND'), 404);
        }

        if ($code = $this->guardTargetUserMutation((int) ($pledge['user_id'] ?? 0))) {
            return $this->jsonResponse($this->apiErrorPayload($this->hierarchyGuardMessage($code), 403, 'TARGET_OUTRANKS_ACTOR'), 403);
        }

        if (!$this->annualPledgeService->delete($pledgeId)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => lang('Pledges.deleteFailed') ?? 'Failed to delete pledge.',
            ], 500);
        }

        $this->audit()->log('annual_pledge.delete', [
            'parish_id' => $parishId,
            'entity_type' => 'annual_pledge',
            'entity_id' => $pledgeId,
            'old_values' => $pledge,
            'meta' => $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
        ]);

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('Pledges.deleteSuccess') ?? 'Pledge deleted.',
        ]);
    }
}
