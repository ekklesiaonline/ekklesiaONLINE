<?php

namespace App\Services;

use App\Models\ChartOfAccountModel;
use App\Models\DonationModel;
use App\Models\FundModel;

/**
 * DonationService
 *
 * Shared domain logic for donation recording: fund/income-account
 * validation, parish-scoped lookups, persistence, and receipt issuance.
 * Previously duplicated between Clergy\Controllers\People (JSON API) and
 * Treasurer\Controllers\Donations (form posts).
 */
class DonationService
{
    private DonationModel $donations;
    private FundModel $funds;
    private ChartOfAccountModel $chartAccounts;

    public function __construct(
        ?DonationModel $donations = null,
        ?FundModel $funds = null,
        ?ChartOfAccountModel $chartAccounts = null
    ) {
        $this->donations = $donations ?? model(DonationModel::class);
        $this->funds = $funds ?? model(FundModel::class);
        $this->chartAccounts = $chartAccounts ?? model(ChartOfAccountModel::class);
    }

    /**
     * @return array<string, string>
     */
    public function validationRules(): array
    {
        return [
            'date'             => 'required|valid_date',
            'amount'           => 'required|decimal|greater_than[0]',
            'fund_id'          => 'required|is_not_unique[fund.id]',
            'category'         => 'permit_empty|max_length[100]',
            'chart_account_id' => 'permit_empty|is_not_unique[chart_of_accounts.id]',
            'payment_method'   => 'permit_empty|max_length[50]',
            'notes'            => 'permit_empty|max_length[500]',
        ];
    }

    /**
     * A fund that exists, belongs to the parish, and is not locked.
     *
     * @return array<string, mixed>|null
     */
    public function findUsableFund(int $fundId, int $parishId): ?array
    {
        $fund = $this->funds->find($fundId);

        if (! is_array($fund)
            || (int) ($fund['parish_id'] ?? 0) !== $parishId
            || (int) ($fund['is_active'] ?? 0) !== 1
            || (int) ($fund['is_locked'] ?? 0) === 1) {
            return null;
        }

        return $fund;
    }

    public function restrictionDataForFund(array $fund): array
    {
        $isRestricted = (string) ($fund['fund_type'] ?? 'unrestricted') !== 'unrestricted';

        return [
            'is_restricted'       => $isRestricted ? 1 : 0,
            'restriction_purpose' => $isRestricted ? ($fund['restriction_purpose'] ?? null) : null,
        ];
    }

    /**
     * An active income chart account.
     *
     * @return array<string, mixed>|null
     */
    public function findIncomeAccount(int $chartAccountId): ?array
    {
        $account = $this->chartAccounts
            ->where('id', $chartAccountId)
            ->where('is_active', 1)
            ->where('account_type', 'income')
            ->first();

        return is_array($account) ? $account : null;
    }

    public function resolveIncomeAccount(array $fund, int $requestedChartAccountId): ?array
    {
        if ($requestedChartAccountId > 0) {
            return $this->findIncomeAccount($requestedChartAccountId);
        }

        $defaultChartAccountId = (int) ($fund['chart_account_id'] ?? 0);

        return $defaultChartAccountId > 0 ? $this->findIncomeAccount($defaultChartAccountId) : null;
    }

    /**
     * Donation scoped to a parish (tenant isolation).
     *
     * @return array<string, mixed>|null
     */
    public function findInParish(int $donationId, int $parishId): ?array
    {
        $donation = $this->donations->find($donationId);

        if (! is_array($donation) || (int) ($donation['parish_id'] ?? 0) !== $parishId) {
            return null;
        }

        return $donation;
    }

    /**
     * Insert a donation. Returns the new ID or null on failure.
     */
    public function create(array $data): ?int
    {
        $id = (int) $this->donations->insert($data, true);

        return $id > 0 ? $id : null;
    }

    public function update(int $donationId, array $data): bool
    {
        return (bool) $this->donations->update($donationId, $data);
    }

    public function delete(int $donationId): bool
    {
        return (bool) $this->donations->delete($donationId);
    }

    /**
     * @return array<string, mixed>
     */
    public function issueReceipt(int $donationId): array
    {
        return (array) $this->donations->issueReceipt($donationId);
    }
}
