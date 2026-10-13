<?php

namespace Finance\Controllers;

use App\Controllers\BaseController;
use App\Services\AccountingJournalSyncService;
use App\Services\DonationService;
use App\Services\GivingStatementService;

class Donations extends BaseController
{
    protected $donationModel;
    protected $profileModel;
    protected $fundModel;
    protected DonationService $donationService;
    protected ?GivingStatementService $statementService = null;

    public function __construct()
    {
        $this->donationModel = model('DonationModel');
        $this->donationService = new DonationService($this->donationModel);
    }

    protected function getStatementService(): GivingStatementService
    {
        if ($this->statementService === null) {
            $this->statementService = new GivingStatementService(
                $this->donationModel,
                model('ParishModel'),
                $this->getProfileModel()
            );
        }

        return $this->statementService;
    }

    protected function getProfileModel()
    {
        if ($this->profileModel === null) {
            $this->profileModel = model('ProfileModel');
        }

        return $this->profileModel;
    }

    protected function getFundModel()
    {
        if ($this->fundModel === null) {
            $this->fundModel = model('FundModel');
        }

        return $this->fundModel;
    }

    /**
     * Display list of donations
     */
    public function index()
    {
        if ($redirect = $this->requirePermission('donation.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = $this->getOperationalParishId();
        $perPage = 20;

        $filters = $this->getDonationFilters();
        $category = $filters['category'];
        $fundId = $filters['fund_id'];
        $startDate = $filters['start_date'];
        $endDate = $filters['end_date'];

        $donations = $this->buildFilteredDonationsQuery((int) $parishId, $filters)
            ->orderBy($this->donationTable() . '.date', 'DESC')
            ->paginate($perPage);

        // Calculate summary
        $summary = $this->donationModel->getParishSummary($parishId, $startDate, $endDate);
        $funds = $this->getFundModel()->getParishFunds((int) $parishId);
        $statementService = $this->getStatementService();
        $availableYears = $statementService->getAvailableYears((int) $parishId);
        $selectedYear = (int) ($this->request->getGet('statement_year') ?? ($availableYears[0] ?? date('Y')));
        $statementMemberId = (int) ($this->request->getGet('statement_member_id') ?? 0);

        $data = [
            'donations'               => $donations,
            'pager'                   => $this->donationModel->pager,
            'summary'                 => $summary,
            'category'                => $category,
            'fund_id'                 => $fundId,
            'funds'                   => $funds,
            'start_date'              => $startDate,
            'end_date'                => $endDate,
            'statement_member_option' => $this->memberSelectOption((int) $parishId, $statementMemberId),
            'statement_member_id'     => $statementMemberId,
            'available_years'         => $availableYears,
            'selected_year'           => $selectedYear,
        ];

        return view('Finance\Views\Donations/index', array_merge($this->getCommonViewData(lang('Finance.donationsTitle')), $data));
    }

    /**
     * Show create donation form
     */
    public function create()
    {
        if ($redirect = $this->requirePermission('donation.create')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $chartOfAccountModel = model('ChartOfAccountModel');
        $chartAccounts = $chartOfAccountModel->getActiveByType('income');
        $groupedChartAccounts = $chartOfAccountModel->getGroupedActiveAccountsForType('income');
        $funds = $this->getFundModel()->getParishFunds((int) ($this->getOperationalParishId() ?? 0));

        return view('Finance\Views\Donations/create', array_merge($this->getCommonViewData('Record Donation'), [
            'donor_option' => $this->memberSelectOption((int) $this->getOperationalParishId(), (int) old('user_id')),
            'funds' => $funds,
            'chartAccounts' => $chartAccounts,
            'groupedChartAccounts' => $groupedChartAccounts,
        ]));
    }

    /**
     * Store new donation
     */
    public function store()
    {
        if ($redirect = $this->requirePermission('donation.create')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $rules = [
            'date' => 'required|valid_date',
            'amount' => 'required|decimal',
            'fund_id' => 'required|is_not_unique[fund.id]',
            'category' => 'permit_empty|max_length[100]',
            'chart_account_id' => 'required|is_not_unique[chart_of_accounts.id]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $attributionPolicy = self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT;
        $attributionParishId = (int) ($this->resolveAttributionParishId($attributionPolicy) ?? 0);
        if ($attributionParishId <= 0) {
            return redirect()->back()->withInput()->with('error', lang('App.errorNoParishScope'));
        }

        $chartAccount = $this->donationService->findIncomeAccount((int) $this->request->getPost('chart_account_id'));

        if ($chartAccount === null) {
            return redirect()->back()->withInput()->with('error', lang('Treasurer.validIncomeAccountRequired'));
        }

        $fundId = (int) $this->request->getPost('fund_id');
        $fund = $this->getFundModel()->find($fundId);
        if (!$fund || (int) ($fund['parish_id'] ?? 0) !== $attributionParishId) {
            return redirect()->back()->withInput()->with('error', lang('Treasurer.validFundRequired'));
        }

        if ((int) ($fund['is_locked'] ?? 0) === 1) {
            return redirect()->back()->withInput()->with('error', lang('Treasurer.fundLocked'));
        }

        $donationDate = (string) $this->request->getPost('date');
        $periodModel = model(\App\Models\AccountingPeriodModel::class);
        if ($periodModel->isDateInClosedPeriod($attributionParishId, $donationDate)) {
            return redirect()->back()->withInput()->with('error', lang('Finance.periodClosedMutationLocked'));
        }

        $data = [
            'parish_id' => $attributionParishId,
            'fund_id' => $fundId,
            'user_id' => $this->request->getPost('user_id') ?: null,
            'date' => $this->request->getPost('date'),
            'amount' => $this->request->getPost('amount'),
            // Keep transaction category aligned with the selected chart account.
            'category' => (string) ($chartAccount['category'] ?? ($this->request->getPost('category') ?: 'other')),
            'category_id' => !empty($chartAccount['category_id']) ? (int) $chartAccount['category_id'] : null,
            'chart_account_id' => (int) $chartAccount['id'],
            'payment_method' => $this->request->getPost('payment_method'),
            'notes' => $this->request->getPost('notes'),
        ];

        $donationId = (int) $this->donationModel->insert($data, true);

        if ($donationId > 0) {
            $receipt = $this->donationModel->issueReceipt($donationId);

            // Synchronous double-entry posting to General Ledger (Decision 5.2)
            try {
                $journalSync = new AccountingJournalSyncService();
                $journalSync->createJournalForDonation($donationId, null, auth()->id());
            } catch (\Throwable $e) {
                log_message('error', "Accounting journal sync failed for donation #{$donationId}: " . $e->getMessage());
            }

            $this->audit()->log('donation.create', [
                'parish_id' => $attributionParishId,
                'entity_type' => 'donation',
                'entity_id' => $donationId,
                'new_values' => [
                    'fund_id' => (int) ($data['fund_id'] ?? 0),
                    'user_id' => $data['user_id'] !== null ? (int) $data['user_id'] : null,
                    'date' => (string) ($data['date'] ?? ''),
                    'amount' => (string) ($data['amount'] ?? ''),
                    'category' => (string) ($data['category'] ?? ''),
                    'chart_account_id' => (int) ($data['chart_account_id'] ?? 0),
                    'receipt_number' => (string) ($receipt['receipt_number'] ?? ''),
                ],
                'meta' => $this->buildParishContextMeta($attributionParishId, $attributionPolicy),
            ]);

            return redirect()->to('/donations')->with('success', lang('Treasurer.donationRecorded'));
        }

        return redirect()->back()->withInput()->with('error', lang('Treasurer.donationRecordFailed'));
    }

    /**
     * Show edit donation form
     */
    public function edit($id)
    {
        if ($redirect = $this->requirePermission('donation.edit')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $donation = $this->donationModel->find($id);

        if (!$donation || (int) ($donation['parish_id'] ?? 0) !== $parishId) {
            return redirect()->to('/donations')->with('error', lang('Treasurer.donationNotFound'));
        }

        $chartOfAccountModel  = model('ChartOfAccountModel');
        $chartAccounts        = $chartOfAccountModel->getActiveByType('income');
        $groupedChartAccounts = $chartOfAccountModel->getGroupedActiveAccountsForType('income');
        $funds                = $this->getFundModel()->getParishFunds($parishId);

        $donorUserId = (int) old('user_id', $donation['user_id'] ?? 0);

        return view('Finance\Views\Donations/edit', array_merge($this->getCommonViewData(lang('Treasurer.editDonationTitle')), [
            'donation'             => $donation,
            'donor_option'         => $this->memberSelectOption($parishId, $donorUserId),
            'funds'                => $funds,
            'chartAccounts'        => $chartAccounts,
            'groupedChartAccounts' => $groupedChartAccounts,
        ]));
    }

    /**
     * Update donation
     */
    public function update($id)
    {
        if ($redirect = $this->requirePermission('donation.edit')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $donation = $this->donationModel->find($id);

        if (!$donation || (int) ($donation['parish_id'] ?? 0) !== $parishId) {
            return redirect()->to('/donations')->with('error', lang('Treasurer.donationNotFound'));
        }

        $rules = [
            'date'             => 'required|valid_date',
            'amount'           => 'required|decimal|greater_than[0]',
            'fund_id'          => 'required|is_not_unique[fund.id]',
            'category'         => 'permit_empty|max_length[100]',
            'chart_account_id' => 'required|is_not_unique[chart_of_accounts.id]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $chartAccount = $this->donationService->findIncomeAccount((int) $this->request->getPost('chart_account_id'));
        if ($chartAccount === null) {
            return redirect()->back()->withInput()->with('error', lang('Treasurer.validIncomeAccountRequired'));
        }

        $fundId = (int) $this->request->getPost('fund_id');
        $fund   = $this->getFundModel()->find($fundId);
        if (!$fund || (int) ($fund['parish_id'] ?? 0) !== $parishId) {
            return redirect()->back()->withInput()->with('error', lang('Treasurer.validFundRequired'));
        }

        if ((int) ($fund['is_locked'] ?? 0) === 1 && (int) ($donation['fund_id'] ?? 0) !== $fundId) {
            return redirect()->back()->withInput()->with('error', lang('Treasurer.fundLocked'));
        }

        $newDate = (string) $this->request->getPost('date');
        $oldDate = (string) ($donation['date'] ?? '');
        $periodModel = model(\App\Models\AccountingPeriodModel::class);
        if ($periodModel->isDateInClosedPeriod($parishId, $oldDate) || $periodModel->isDateInClosedPeriod($parishId, $newDate)) {
            return redirect()->back()->withInput()->with('error', lang('Finance.periodClosedMutationLocked'));
        }

        $data = [
            'fund_id'             => $fundId,
            'user_id'             => $this->request->getPost('user_id') ?: null,
            'date'                => (string) $this->request->getPost('date'),
            'amount'              => (string) $this->request->getPost('amount'),
            'category'            => (string) ($chartAccount['category'] ?? ($this->request->getPost('category') ?: 'other')),
            'category_id'         => !empty($chartAccount['category_id']) ? (int) $chartAccount['category_id'] : null,
            'chart_account_id'    => (int) $chartAccount['id'],
            'payment_method'      => $this->request->getPost('payment_method') ?: null,
            'notes'               => $this->request->getPost('notes') ?: null,
            'is_restricted'       => $this->request->getPost('is_restricted') ? 1 : 0,
            'restriction_purpose' => $this->request->getPost('is_restricted') ? ($this->request->getPost('restriction_purpose') ?: null) : null,
        ];

        if ($this->donationModel->update($id, $data)) {
            $this->audit()->log('donation.update', [
                'parish_id'   => $parishId,
                'entity_type' => 'donation',
                'entity_id'   => (int) $id,
                'old_values'  => [
                    'fund_id'          => (int) ($donation['fund_id'] ?? 0),
                    'user_id'          => isset($donation['user_id']) ? (int) $donation['user_id'] : null,
                    'date'             => (string) ($donation['date'] ?? ''),
                    'amount'           => (string) ($donation['amount'] ?? ''),
                    'category'         => (string) ($donation['category'] ?? ''),
                    'chart_account_id' => isset($donation['chart_account_id']) ? (int) $donation['chart_account_id'] : null,
                ],
                'new_values'  => [
                    'fund_id'          => (int) ($data['fund_id'] ?? 0),
                    'user_id'          => $data['user_id'] !== null ? (int) $data['user_id'] : null,
                    'date'             => (string) ($data['date'] ?? ''),
                    'amount'           => (string) ($data['amount'] ?? ''),
                    'category'         => (string) ($data['category'] ?? ''),
                    'chart_account_id' => (int) ($data['chart_account_id'] ?? 0),
                ],
                'meta'        => $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
            ]);

            return redirect()->to('/donations')->with('success', lang('Treasurer.donationUpdated'));
        }

        return redirect()->back()->withInput()->with('error', lang('Treasurer.donationUpdateFailed'));
    }

    /**
     * Delete donation
     */
    public function delete($id)
    {
        if ($redirect = $this->requirePermission('donation.delete')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $donation = $this->donationModel->find($id);

        if (!$donation || $donation['parish_id'] != $this->getOperationalParishId()) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Treasurer.donationNotFound')], 404);
        }

        $attributionParishId = (int) ($donation['parish_id'] ?? 0);
        $periodModel = model(\App\Models\AccountingPeriodModel::class);
        if ($periodModel->isDateInClosedPeriod($attributionParishId, (string) ($donation['date'] ?? ''))) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Finance.periodClosedMutationLocked')], 422);
        }

        if ($this->donationModel->delete($id)) {
            $attributionParishId = (int) ($donation['parish_id'] ?? 0);
            $this->audit()->log('donation.delete', [
                'parish_id' => $attributionParishId,
                'entity_type' => 'donation',
                'entity_id' => (int) $id,
                'old_values' => [
                    'fund_id' => (int) ($donation['fund_id'] ?? 0),
                    'user_id' => isset($donation['user_id']) ? (int) $donation['user_id'] : null,
                    'date' => (string) ($donation['date'] ?? ''),
                    'amount' => (string) ($donation['amount'] ?? ''),
                    'category' => (string) ($donation['category'] ?? ''),
                    'chart_account_id' => isset($donation['chart_account_id']) ? (int) $donation['chart_account_id'] : null,
                ],
                'meta' => $this->buildParishContextMeta($attributionParishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
            ]);

            return $this->jsonResponse(['success' => true, 'message' => lang('Treasurer.donationDeleted')]);
        }

        return $this->jsonResponse(['success' => false, 'message' => lang('Treasurer.donationDeleteFailed')], 500);
    }

    protected function getDonationFilters(): array
    {
        return [
            'category' => trim((string) ($this->request->getGet('category') ?? '')),
            'fund_id' => (int) ($this->request->getGet('fund_id') ?? 0),
            'start_date' => $this->normalizeDate($this->request->getGet('start_date')),
            'end_date' => $this->normalizeDate($this->request->getGet('end_date')),
        ];
    }

    protected function buildFilteredDonationsQuery(int $parishId, array $filters)
    {
        $profileTable = $this->resolveExistingProfileTable();
        $donationTable = $this->donationModel->db->getPrefix() . 'donation';
        $builder = $this->donationModel
            ->select("{$donationTable}.*, f.name as fund_name, f.fund_type, r.receipt_number")
            ->join('fund f', "f.id = {$donationTable}.fund_id", 'left')
            ->join('donation_receipts r', "r.donation_id = {$donationTable}.id AND r.deleted_at IS NULL", 'left', false)
            ->where("{$donationTable}.deleted_at", null);

        if ($profileTable !== null) {
            $builder->select($profileTable . '.first_name, ' . $profileTable . '.last_name')
                ->join($profileTable, $profileTable . '.user_id = ' . $donationTable . '.user_id', 'left');
        } else {
            $builder->select('NULL as first_name, NULL as last_name');
        }

        $builder->where("{$donationTable}.parish_id", $parishId);

        if (!empty($filters['category'])) {
            $builder->where("{$donationTable}.category", (string) $filters['category']);
        }

        if (!empty($filters['fund_id'])) {
            $builder->where("{$donationTable}.fund_id", (int) $filters['fund_id']);
        }

        if (!empty($filters['start_date'])) {
            $builder->where("{$donationTable}.date >=", (string) $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $builder->where("{$donationTable}.date <=", (string) $filters['end_date']);
        }

        return $builder;
    }

    protected function normalizeDate($value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }

    protected function formatDonorName(array $donation): string
    {
        $name = trim((string) (($donation['first_name'] ?? '') . ' ' . ($donation['last_name'] ?? '')));

        if ($name !== '') {
            return $name;
        }

        return !empty($donation['user_id']) ? lang('Finance.memberPrefix') . (int) $donation['user_id'] : lang('Finance.anonymous');
    }

    protected function getParishMemberProfile(int $parishId, int $memberUserId): ?array
    {
        $profileTable = $this->resolveExistingProfileTable();
        if ($profileTable === null) {
            return null;
        }

        $member = $this->getProfileModel()
            ->select($profileTable . '.*, auth_identities.secret as email')
            ->join('parish_affiliation mca', 'mca.linked_user_id = ' . $profileTable . '.user_id AND mca.parish_id = ' . $parishId . ' AND mca.is_member = 1', 'inner')
            ->join('auth_identities', 'auth_identities.user_id = ' . $profileTable . '.user_id AND auth_identities.type = "email_password"', 'left')
            ->where($profileTable . '.user_id', $memberUserId)
            ->first();

        return is_array($member) ? $member : null;
    }



    /**
     * Preselect payload for AJAX member pickers.
     *
     * @return array<int,array{id:int,text:string}>
     */
    protected function memberSelectOption(int $parishId, int $userId): array
    {
        if ($parishId <= 0 || $userId <= 0) {
            return [];
        }

        $option = model('MemberParishAffiliationModel')->memberOption($parishId, $userId);

        return $option === null ? [] : [$option];
    }

    protected function donationTable(): string
    {
        return $this->donationModel->db->getPrefix() . 'donation';
    }

    protected function resolveExistingProfileTable(): ?string
    {
        $db = db_connect();
        $candidates = [$db->getPrefix() . 'profile', 'profile'];
        foreach (array_unique($candidates) as $table) {
            try {
                $columns = $db->getFieldNames($table);
                if ($columns !== []) {
                    return $table;
                }
            } catch (\Throwable $e) {
                // Table doesn't exist.
            }
        }
        return null;
    }
}
