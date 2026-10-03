<?php

namespace Treasurer\Controllers;

use App\Controllers\BaseController;
use App\Services\DonationService;

class Donations extends BaseController
{
    private const CSV_EXPORT_MAX_ROWS = 50000;
    private const PDF_EXPORT_MAX_ROWS = 3000;

    protected $donationModel;
    protected $profileModel;
    protected $fundModel;
    protected DonationService $donationService;

    public function __construct()
    {
        $this->donationModel = model('DonationModel');
        $this->donationService = new DonationService($this->donationModel);
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
        $statementMemberId = (int) ($this->request->getGet('statement_member_id') ?? 0);

        $data = [
            'donations' => $donations,
            'pager' => $this->donationModel->pager,
            'summary' => $summary,
            'category' => $category,
            'fund_id' => $fundId,
            'funds' => $funds,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'statement_member_option' => $this->memberSelectOption((int) $parishId, $statementMemberId),
            'statement_member_id' => $statementMemberId,
        ];

        return view('Treasurer\Views\Donations/index', array_merge($this->getCommonViewData(lang('Finance.donationsTitle')), $data));
    }

    public function export()
    {
        if ($redirect = $this->requirePermission('donation.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $filters = $this->getDonationFilters();

        $query = $this->buildFilteredDonationsQuery($parishId, $filters)
            ->orderBy($this->donationTable() . '.date', 'DESC')
            ->get();

        // Stream rows unbuffered into php://temp (spills to disk past 2 MB)
        // so peak memory stays bounded regardless of result size.
        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, [
            lang('Finance.colDate'),
            lang('Finance.colDonor'),
            lang('Finance.colFund'),
            lang('Finance.colFundType'),
            lang('Finance.colCategory'),
            lang('Finance.colAmount'),
            lang('Finance.colPaymentMethod'),
            lang('Finance.colNotes'),
            'Receipt Number',
        ]);

        $rowCount = 0;
        while ($rowCount < self::CSV_EXPORT_MAX_ROWS && is_array($donation = $query->getUnbufferedRow('array'))) {
            fputcsv($handle, [
                (string) ($donation['date'] ?? ''),
                $this->formatDonorName($donation),
                (string) ($donation['fund_name'] ?? lang('Finance.unassigned')),
                $this->mapFundTypeLabel((string) ($donation['fund_type'] ?? 'unrestricted')),
                $this->mapDonationCategoryLabel((string) ($donation['category'] ?? 'other')),
                number_format((float) ($donation['amount'] ?? 0), 2, '.', ''),
                (string) ($donation['payment_method'] ?? ''),
                (string) ($donation['notes'] ?? ''),
                (string) ($donation['receipt_number'] ?? ''),
            ]);
            $rowCount++;
        }

        $query->freeResult();
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $this->audit()->log('donation.export_csv', [
            'parish_id' => $parishId,
            'entity_type' => 'donation_report',
            'meta' => array_merge(
                $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
                [
                    'count' => $rowCount,
                    'truncated' => $rowCount >= self::CSV_EXPORT_MAX_ROWS,
                    'category' => (string) ($filters['category'] ?? ''),
                    'fund_id' => (int) ($filters['fund_id'] ?? 0),
                    'start_date' => (string) ($filters['start_date'] ?? ''),
                    'end_date' => (string) ($filters['end_date'] ?? ''),
                ]
            ),
        ]);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $this->buildDonationExportFilename('csv') . '"')
            ->setBody($csv ?: '');
    }

    public function exportPdf()
    {
        if ($redirect = $this->requirePermission('donation.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $filters = $this->getDonationFilters();
        $donations = $this->buildFilteredDonationsQuery($parishId, $filters)
            ->orderBy($this->donationTable() . '.date', 'DESC')
            ->limit(self::PDF_EXPORT_MAX_ROWS + 1)
            ->get()
            ->getResultArray();

        $pdfTruncated = count($donations) > self::PDF_EXPORT_MAX_ROWS;
        if ($pdfTruncated) {
            $donations = array_slice($donations, 0, self::PDF_EXPORT_MAX_ROWS);
        }

        $summary = $this->donationModel->getParishSummary($parishId, $filters['start_date'], $filters['end_date']);

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($this->buildDonationsPdfHtml($parishId, $filters, $donations, $summary, $pdfTruncated));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $this->audit()->log('donation.export_pdf', [
            'parish_id' => $parishId,
            'entity_type' => 'donation_report',
            'meta' => array_merge(
                $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
                [
                    'count' => count($donations),
                    'category' => (string) ($filters['category'] ?? ''),
                    'fund_id' => (int) ($filters['fund_id'] ?? 0),
                    'start_date' => (string) ($filters['start_date'] ?? ''),
                    'end_date' => (string) ($filters['end_date'] ?? ''),
                ]
            ),
        ]);

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $this->buildDonationExportFilename('pdf') . '"')
            ->setBody($dompdf->output());
    }

    public function statement()
    {
        if ($redirect = $this->requirePermission('donation.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $memberUserId = (int) ($this->request->getGet('statement_member_id') ?? 0);
        $startDate = $this->normalizeDate($this->request->getGet('start_date')) ?? date('Y-01-01');
        $endDate = $this->normalizeDate($this->request->getGet('end_date')) ?? date('Y-m-d');

        if ($memberUserId <= 0) {
            return redirect()->to('/donations')->with('error', lang('Treasurer.selectMemberForStatement'));
        }

        $member = $this->getParishMemberProfile($parishId, $memberUserId);
        if ($member === null) {
            return redirect()->to('/donations')->with('error', lang('Treasurer.memberOutsideParishScope'));
        }

        $donations = $this->buildFilteredDonationsQuery($parishId, [
            'category' => '',
            'fund_id' => 0,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ])
            ->where($this->donationTable() . '.user_id', $memberUserId)
            ->orderBy($this->donationTable() . '.date', 'ASC')
            ->get()
            ->getResultArray();

        $totalAmount = array_reduce($donations, static function (float $carry, array $donation): float {
            return $carry + (float) ($donation['amount'] ?? 0);
        }, 0.0);

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($this->buildContributionStatementPdfHtml($parishId, $member, $startDate, $endDate, $donations, $totalAmount));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $this->audit()->log('donation.statement_pdf', [
            'parish_id' => $parishId,
            'entity_type' => 'member_statement',
            'entity_id' => $memberUserId,
            'meta' => array_merge(
                $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
                [
                    'count' => count($donations),
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'total_amount' => number_format($totalAmount, 2, '.', ''),
                ]
            ),
        ]);

        $filename = 'contribution_statement_' . $memberUserId . '_' . date('Ymd_His') . '.pdf';

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($dompdf->output());
    }

    /**
     * Display receipt for a donation.
     */
    public function receipt($donationId)
    {
        if ($redirect = $this->requirePermission('donation.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $donationId = (int) $donationId;

        $donation = $this->donationModel->find($donationId);
        if (empty($donation) || (int) ($donation['parish_id'] ?? 0) !== $parishId) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Treasurer.donationNotFound')], 404);
        }

        $receipt = $this->donationModel->getReceipt($donationId);
        if (empty($receipt)) {
            return redirect()->to('/donations')->with('error', 'No receipt has been issued for this donation.');
        }

        $donor = $this->formatDonorName($donation);

        return view('Treasurer\Views\Donations/receipt', array_merge($this->getCommonViewData('Donation Receipt'), [
            'donation' => $donation,
            'receipt'  => $receipt,
            'donor'    => $donor,
            'parish'   => model('ParishModel')->find($parishId),
        ]));
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

        return view('Treasurer\Views\Donations/create', array_merge($this->getCommonViewData('Record Donation'), [
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

        $data = [
            'parish_id' => $attributionParishId,
            'fund_id' => $fundId,
            'user_id' => $this->request->getPost('user_id') ?: null,
            'date' => $this->request->getPost('date'),
            'amount' => $this->request->getPost('amount'),
            // Keep transaction category aligned with the selected chart account.
            'category' => (string) ($chartAccount['category'] ?? ($this->request->getPost('category') ?: 'other')),
            'chart_account_id' => (int) $chartAccount['id'],
            'payment_method' => $this->request->getPost('payment_method'),
            'notes' => $this->request->getPost('notes'),
        ];

        $donationId = (int) $this->donationModel->insert($data, true);

        if ($donationId > 0) {
            $receipt = $this->donationModel->issueReceipt($donationId);

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

        return view('Treasurer\Views\Donations/edit', array_merge($this->getCommonViewData(lang('Treasurer.editDonationTitle')), [
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

        $data = [
            'fund_id'             => $fundId,
            'user_id'             => $this->request->getPost('user_id') ?: null,
            'date'                => (string) $this->request->getPost('date'),
            'amount'              => (string) $this->request->getPost('amount'),
            'category'            => (string) ($chartAccount['category'] ?? ($this->request->getPost('category') ?: 'other')),
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

    private function getDonationFilters(): array
    {
        return [
            'category' => trim((string) ($this->request->getGet('category') ?? '')),
            'fund_id' => (int) ($this->request->getGet('fund_id') ?? 0),
            'start_date' => $this->normalizeDate($this->request->getGet('start_date')),
            'end_date' => $this->normalizeDate($this->request->getGet('end_date')),
        ];
    }

    private function buildFilteredDonationsQuery(int $parishId, array $filters)
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

    private function normalizeDate($value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }

    private function formatDonorName(array $donation): string
    {
        $name = trim((string) (($donation['first_name'] ?? '') . ' ' . ($donation['last_name'] ?? '')));

        if ($name !== '') {
            return $name;
        }

        return !empty($donation['user_id']) ? lang('Finance.memberPrefix') . (int) $donation['user_id'] : lang('Finance.anonymous');
    }

    private function buildDonationExportFilename(string $extension): string
    {
        return 'donations_export_' . date('Ymd_His') . '.' . $extension;
    }

    private function getParishName(int $parishId): string
    {
        $parish = model('ParishModel')->find($parishId);

        return (string) ($parish['name'] ?? lang('Treasurer.unknownParish'));
    }

    private function getParishMemberProfile(int $parishId, int $memberUserId): ?array
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
    private function memberSelectOption(int $parishId, int $userId): array
    {
        if ($parishId <= 0 || $userId <= 0) {
            return [];
        }

        $option = model('MemberParishAffiliationModel')->memberOption($parishId, $userId);

        return $option === null ? [] : [$option];
    }

    private function donationTable(): string
    {
        return $this->donationModel->db->getPrefix() . 'donation';
    }

    private function resolveExistingProfileTable(): ?string
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

    private function buildDonationsPdfHtml(int $parishId, array $filters, array $donations, array $summary, bool $truncated = false): string
    {
        $rows = '';

        foreach ($donations as $donation) {
            $rows .= '<tr>'
                . '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars((string) ($donation['date'] ?? '')) . '</td>'
                . '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($this->formatDonorName($donation)) . '</td>'
                . '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars((string) ($donation['fund_name'] ?? lang('Finance.unassigned'))) . '</td>'
                . '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($this->mapFundTypeLabel((string) ($donation['fund_type'] ?? 'unrestricted'))) . '</td>'
                . '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($this->mapDonationCategoryLabel((string) ($donation['category'] ?? 'other'))) . '</td>'
                . '<td style="padding: 8px; border: 1px solid #ddd; text-align: right;">$' . number_format((float) ($donation['amount'] ?? 0), 2) . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="6" style="padding: 12px; border: 1px solid #ddd; text-align: center; color: #666;">' . htmlspecialchars(lang('Treasurer.noDonationsForFilters')) . '</td></tr>';
        }

        $filterParts = [];
        if (!empty($filters['category'])) {
            $filterParts[] = lang('Treasurer.categoryLabel') . ': ' . $this->mapDonationCategoryLabel((string) $filters['category']);
        }
        if (!empty($filters['fund_id'])) {
            $filterParts[] = lang('Treasurer.fundIdLabel') . ': ' . (int) $filters['fund_id'];
        }
        if (!empty($filters['start_date'])) {
            $filterParts[] = lang('Treasurer.fromLabel') . ': ' . $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $filterParts[] = lang('Treasurer.toLabel') . ': ' . $filters['end_date'];
        }

        return '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . htmlspecialchars(lang('Treasurer.donationsExportTitle')) . '</title>
            <style>
                body { font-family: Arial, sans-serif; color: #333; font-size: 12px; }
                h1 { color: #1f4b8f; margin-bottom: 6px; }
                .meta { color: #666; margin-bottom: 18px; }
                table { width: 100%; border-collapse: collapse; }
                th { background: #f4f6f8; text-align: left; padding: 8px; border: 1px solid #ddd; }
                .summary { margin: 18px 0; }
                .summary-box { display: inline-block; width: 31%; margin-right: 2%; padding: 12px; border: 1px solid #ddd; background: #fafafa; box-sizing: border-box; }
                .summary-label { color: #666; font-size: 11px; text-transform: uppercase; }
                .summary-value { font-size: 18px; color: #1f4b8f; font-weight: bold; margin-top: 4px; }
            </style>
        </head>
        <body>
            <h1>' . htmlspecialchars(lang('Treasurer.donationsExportTitle')) . '</h1>
            <div class="meta">
                <strong>' . htmlspecialchars(lang('Treasurer.parishLabel')) . ':</strong> ' . htmlspecialchars($this->getParishName($parishId)) . '<br>
                <strong>' . htmlspecialchars(lang('Treasurer.generatedLabel')) . ':</strong> ' . htmlspecialchars($this->formatLocalizedDateTime(date('Y-m-d H:i:s'))) . '<br>
                <strong>' . htmlspecialchars(lang('Treasurer.filtersLabel')) . ':</strong> ' . htmlspecialchars($filterParts !== [] ? implode(' | ', $filterParts) : lang('Treasurer.noneLabel')) . ($truncated ? '<br><strong>' . htmlspecialchars(sprintf('Showing first %d records. Narrow the filters or use CSV export for the full set.', self::PDF_EXPORT_MAX_ROWS)) . '</strong>' : '') . '
            </div>
            <div class="summary">
                <div class="summary-box">
                    <div class="summary-label">' . htmlspecialchars(lang('Treasurer.totalDonationsLabel')) . '</div>
                    <div class="summary-value">$' . number_format((float) ($summary['total'] ?? 0), 2) . '</div>
                </div>
                <div class="summary-box">
                    <div class="summary-label">' . htmlspecialchars(lang('Finance.categoryTithe')) . '</div>
                    <div class="summary-value">$' . number_format((float) ($summary['tithe'] ?? 0), 2) . '</div>
                </div>
                <div class="summary-box">
                    <div class="summary-label">' . htmlspecialchars(lang('Treasurer.offeringsOtherLabel')) . '</div>
                    <div class="summary-value">$' . number_format((float) (($summary['offering'] ?? 0) + ($summary['other'] ?? 0)), 2) . '</div>
                </div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>' . htmlspecialchars(lang('Finance.colDate')) . '</th>
                        <th>' . htmlspecialchars(lang('Finance.colDonor')) . '</th>
                        <th>' . htmlspecialchars(lang('Finance.colFund')) . '</th>
                        <th>' . htmlspecialchars(lang('Finance.colFundType')) . '</th>
                        <th>' . htmlspecialchars(lang('Finance.colCategory')) . '</th>
                        <th style="text-align: right;">' . htmlspecialchars(lang('Finance.colAmount')) . '</th>
                    </tr>
                </thead>
                <tbody>' . $rows . '</tbody>
            </table>
        </body>
        </html>';
    }

    private function buildContributionStatementPdfHtml(int $parishId, array $member, string $startDate, string $endDate, array $donations, float $totalAmount): string
    {
        $memberName = trim((string) (($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')));
        $rows = '';

        foreach ($donations as $donation) {
            $rows .= '<tr>'
                . '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars((string) ($donation['date'] ?? '')) . '</td>'
                . '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars((string) ($donation['fund_name'] ?? lang('Finance.unassigned'))) . '</td>'
                . '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($this->mapDonationCategoryLabel((string) ($donation['category'] ?? 'other'))) . '</td>'
                . '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars((string) ($donation['payment_method'] ?? '-')) . '</td>'
                . '<td style="padding: 8px; border: 1px solid #ddd; text-align: right;">$' . number_format((float) ($donation['amount'] ?? 0), 2) . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="5" style="padding: 12px; border: 1px solid #ddd; text-align: center; color: #666;">' . htmlspecialchars(lang('Treasurer.noContributionsForPeriod')) . '</td></tr>';
        }

        return '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . htmlspecialchars(lang('Treasurer.contributionStatementTitle')) . '</title>
            <style>
                body { font-family: Arial, sans-serif; color: #333; font-size: 12px; }
                h1 { color: #1f4b8f; margin-bottom: 4px; }
                h2 { color: #444; font-size: 16px; margin-top: 0; }
                .meta { margin-bottom: 18px; color: #555; }
                .totals { margin: 18px 0; padding: 14px; border: 1px solid #ddd; background: #fafafa; }
                .totals strong { color: #1f4b8f; font-size: 18px; }
                table { width: 100%; border-collapse: collapse; }
                th { background: #f4f6f8; text-align: left; padding: 8px; border: 1px solid #ddd; }
            </style>
        </head>
        <body>
            <h1>' . htmlspecialchars(lang('Treasurer.contributionStatementTitle')) . '</h1>
            <h2>' . htmlspecialchars($this->getParishName($parishId)) . '</h2>
            <div class="meta">
                <strong>' . htmlspecialchars(lang('Finance.memberLabel')) . ':</strong> ' . htmlspecialchars($memberName !== '' ? $memberName : (lang('Finance.memberPrefix') . (int) ($member['user_id'] ?? 0))) . '<br>
                <strong>' . htmlspecialchars(lang('App.colEmail')) . ':</strong> ' . htmlspecialchars((string) ($member['email'] ?? '-')) . '<br>
                <strong>' . htmlspecialchars(lang('Treasurer.statementPeriodLabel')) . ':</strong> ' . htmlspecialchars($startDate) . ' ' . htmlspecialchars(lang('Treasurer.reportTo')) . ' ' . htmlspecialchars($endDate) . '<br>
                <strong>' . htmlspecialchars(lang('Treasurer.generatedLabel')) . ':</strong> ' . htmlspecialchars($this->formatLocalizedDateTime(date('Y-m-d H:i:s'))) . '
            </div>
            <div class="totals">
                ' . htmlspecialchars(lang('Treasurer.totalContributionsLabel')) . ': <strong>$' . number_format($totalAmount, 2) . '</strong>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>' . htmlspecialchars(lang('Finance.colDate')) . '</th>
                        <th>' . htmlspecialchars(lang('Finance.colFund')) . '</th>
                        <th>' . htmlspecialchars(lang('Finance.colCategory')) . '</th>
                        <th>' . htmlspecialchars(lang('Finance.colPaymentMethod')) . '</th>
                        <th style="text-align: right;">' . htmlspecialchars(lang('Finance.colAmount')) . '</th>
                    </tr>
                </thead>
                <tbody>' . $rows . '</tbody>
            </table>
        </body>
        </html>';
    }

    private function mapFundTypeLabel(string $fundType): string
    {
        $map = [
            'restricted' => lang('Treasurer.fundTypeRestricted'),
            'permanent' => lang('Treasurer.fundTypePermanent'),
            'unrestricted' => lang('Treasurer.fundTypeUnrestricted'),
        ];

        return $map[$fundType] ?? ucfirst($fundType);
    }

    private function mapDonationCategoryLabel(string $category): string
    {
        $map = [
            'tithe' => lang('Finance.categoryTithe'),
            'offering' => lang('Finance.categoryOffering'),
            'mission' => lang('Finance.categoryMission'),
            'building fund' => lang('Finance.categoryBuilding'),
            'building_fund' => lang('Finance.categoryBuilding'),
            'other' => lang('Finance.categoryOther'),
        ];

        return $map[$category] ?? ucfirst(str_replace('_', ' ', $category));
    }

    private function formatLocalizedDateTime(string $value): string
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        $locale = $this->request->getLocale() ?: config('App')->defaultLocale;
        if (class_exists(\IntlDateFormatter::class)) {
            $formatter = new \IntlDateFormatter(
                $locale,
                \IntlDateFormatter::MEDIUM,
                \IntlDateFormatter::SHORT,
                date_default_timezone_get()
            );
            $formatter->setPattern('MMM d, y h:mm a');
            $formatted = $formatter->format($timestamp);
            if (is_string($formatted) && $formatted !== '') {
                return $formatted;
            }
        }

        return date('Y-m-d H:i', $timestamp);
    }
}
