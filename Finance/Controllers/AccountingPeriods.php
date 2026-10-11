<?php

namespace Finance\Controllers;

use App\Controllers\BaseController;
use App\Models\AccountingPeriodModel;

class AccountingPeriods extends BaseController
{
    protected AccountingPeriodModel $periodModel;

    public function __construct()
    {
        $this->periodModel = new AccountingPeriodModel();
    }

    /**
     * List accounting periods by fiscal year for the operational parish.
     */
    public function index()
    {
        if ($redirect = $this->requirePermission('finance.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $fiscalYears = $this->periodModel->getDistinctFiscalYears($parishId);
        $currentYear = (int) date('Y');

        $selectedYear = (int) ($this->request->getGet('year') ?? ($fiscalYears[0] ?? $currentYear));
        if ($selectedYear <= 0) {
            $selectedYear = $currentYear;
        }

        $periods = $this->periodModel->getParishPeriods($parishId, $selectedYear);

        $summary = [
            'total'       => count($periods),
            'open'        => 0,
            'closed'      => 0,
            'soft_closed' => 0,
        ];
        foreach ($periods as $p) {
            $st = (string) ($p['status'] ?? 'open');
            if (isset($summary[$st])) {
                $summary[$st]++;
            }
        }

        return view('Finance\Views\AccountingPeriods/index', array_merge($this->getCommonViewData(lang('Finance.accountingPeriodsTitle')), [
            'periods'      => $periods,
            'fiscalYears'  => $fiscalYears,
            'selectedYear' => $selectedYear,
            'summary'      => $summary,
            'canManage'    => auth()->user() !== null && auth()->user()->can('finance.manage'),
        ]));
    }

    /**
     * Generate 12 standard monthly periods for a given fiscal year.
     */
    public function generate()
    {
        if ($redirect = $this->requirePermission('finance.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $fiscalYear = (int) ($this->request->getPost('fiscal_year') ?? date('Y'));

        if ($fiscalYear < 2000 || $fiscalYear > 2100) {
            return redirect()->back()->with('error', lang('Finance.invalidFiscalYear'));
        }

        $created = $this->periodModel->generateYearPeriods($parishId, $fiscalYear);

        $this->audit()->log('accounting_period.generate', [
            'parish_id'   => $parishId,
            'entity_type' => 'accounting_period',
            'entity_id'   => $fiscalYear,
            'new_values'  => ['fiscal_year' => $fiscalYear, 'created_count' => $created],
        ]);

        $message = $created > 0
            ? lang('Finance.accountingPeriodsGenerated', [$created, $fiscalYear])
            : lang('Finance.accountingPeriodsAlreadyExist', [$fiscalYear]);

        return redirect()->to('/accounting-periods?year=' . $fiscalYear)->with('success', $message);
    }

    /**
     * Create a single accounting period manually.
     */
    public function store()
    {
        if ($redirect = $this->requirePermission('finance.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);

        $rules = [
            'fiscal_year'   => 'required|integer',
            'period_number' => 'required|integer',
            'period_name'   => 'required|max_length[50]',
            'start_date'    => 'required|valid_date',
            'end_date'      => 'required|valid_date',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $startDate = (string) $this->request->getPost('start_date');
        $endDate = (string) $this->request->getPost('end_date');
        if ($startDate > $endDate) {
            return redirect()->back()->withInput()->with('error', lang('Finance.periodStartMustPrecedeEnd'));
        }

        $fiscalYear = (int) $this->request->getPost('fiscal_year');
        $periodNumber = (int) $this->request->getPost('period_number');

        // Check for duplicate period number in same fiscal year
        $existing = $this->periodModel->where('parish_id', $parishId)
            ->where('fiscal_year', $fiscalYear)
            ->where('period_number', $periodNumber)
            ->first();
        if ($existing) {
            return redirect()->back()->withInput()->with('error', lang('Finance.periodNumberAlreadyExists'));
        }

        $id = $this->periodModel->insert([
            'parish_id'     => $parishId,
            'fiscal_year'   => $fiscalYear,
            'period_number' => $periodNumber,
            'period_name'   => trim((string) $this->request->getPost('period_name')),
            'start_date'    => $startDate,
            'end_date'      => $endDate,
            'status'        => 'open',
        ]);

        if (!$id) {
            return redirect()->back()->withInput()->with('error', lang('Finance.periodSaveFailed'));
        }

        $this->audit()->log('accounting_period.create', [
            'parish_id'   => $parishId,
            'entity_type' => 'accounting_period',
            'entity_id'   => (int) $id,
            'new_values'  => [
                'fiscal_year'   => $fiscalYear,
                'period_number' => $periodNumber,
                'period_name'   => (string) $this->request->getPost('period_name'),
            ],
        ]);

        return redirect()->to('/accounting-periods?year=' . $fiscalYear)->with('success', lang('App.successSaved'));
    }

    /**
     * Close an accounting period (locks transaction mutations).
     */
    public function close($id)
    {
        if ($redirect = $this->requirePermission('finance.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $period = $this->periodModel->where('id', (int) $id)->where('parish_id', $parishId)->first();

        if (!$period) {
            return redirect()->to('/accounting-periods')->with('error', lang('Finance.periodNotFound'));
        }

        if (($period['status'] ?? '') === 'closed') {
            return redirect()->back()->with('error', lang('Finance.periodAlreadyClosed'));
        }

        $this->periodModel->closePeriod((int) $id);

        $this->audit()->log('accounting_period.close', [
            'parish_id'   => $parishId,
            'entity_type' => 'accounting_period',
            'entity_id'   => (int) $id,
            'new_values'  => ['status' => 'closed', 'period_name' => $period['period_name']],
        ]);

        return redirect()->to('/accounting-periods?year=' . $period['fiscal_year'])
            ->with('success', lang('Finance.periodClosedSuccess', [$period['period_name']]));
    }

    /**
     * Reopen an accounting period.
     */
    public function reopen($id)
    {
        if ($redirect = $this->requirePermission('finance.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $period = $this->periodModel->where('id', (int) $id)->where('parish_id', $parishId)->first();

        if (!$period) {
            return redirect()->to('/accounting-periods')->with('error', lang('Finance.periodNotFound'));
        }

        if (($period['status'] ?? '') === 'open') {
            return redirect()->back()->with('error', lang('Finance.periodAlreadyOpen'));
        }

        $this->periodModel->reopenPeriod((int) $id);

        $this->audit()->log('accounting_period.reopen', [
            'parish_id'   => $parishId,
            'entity_type' => 'accounting_period',
            'entity_id'   => (int) $id,
            'new_values'  => ['status' => 'open', 'period_name' => $period['period_name']],
        ]);

        return redirect()->to('/accounting-periods?year=' . $period['fiscal_year'])
            ->with('success', lang('Finance.periodReopenedSuccess', [$period['period_name']]));
    }
}
