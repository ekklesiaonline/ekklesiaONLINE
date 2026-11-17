<?php

namespace Finance\Controllers;

use App\Controllers\BaseController;
use App\Services\BankReconciliationService;

class Banking extends BaseController
{
    protected BankReconciliationService $banking;

    public function __construct()
    {
        $this->banking = new BankReconciliationService();
    }

    /**
     * List bank accounts for the operational parish.
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

        return view('Finance\Views\Banking/index', array_merge($this->getCommonViewData(lang('Finance.bankingTitle')), [
            'accounts' => model('BankAccountModel')->accountsWithBalances($parishId),
            'canManage' => auth()->user()->can('finance.manage'),
        ]));
    }

    /**
     * New bank account form.
     */
    public function create()
    {
        if ($redirect = $this->requirePermission('finance.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        return view('Finance\Views\Banking/create', $this->getCommonViewData(lang('Finance.bankAccountCreate')));
    }

    /**
     * Persist a new bank account.
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
        if ($parishId <= 0) {
            return redirect()->back()->withInput()->with('error', lang('App.errorNoParishScope'));
        }


        $rules = [
            'name'                  => 'required|max_length[150]',
            'institution_name'      => 'permit_empty|max_length[150]',
            'masked_account_number' => 'permit_empty|max_length[34]',
            'routing_last4'         => 'permit_empty|max_length[4]|numeric',
            'currency_code'         => 'required|exact_length[3]|alpha',
            'opening_balance'       => 'permit_empty|decimal',
            'opened_on'             => 'permit_empty|valid_date',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $accountId = model('BankAccountModel')->insert([
            'parish_id'             => $parishId,
            'name'                  => trim((string) $this->request->getPost('name')),
            'institution_name'      => trim((string) ($this->request->getPost('institution_name') ?? '')) ?: null,
            'masked_account_number' => trim((string) ($this->request->getPost('masked_account_number') ?? '')) ?: null,
            'routing_last4'         => trim((string) ($this->request->getPost('routing_last4') ?? '')) ?: null,
            'currency_code'         => strtoupper(trim((string) $this->request->getPost('currency_code'))),
            'opening_balance'       => (float) ($this->request->getPost('opening_balance') ?: 0),
            'opened_on'             => $this->request->getPost('opened_on') ?: null,
            'is_active'             => 1,
        ]);

        if ($accountId === false) {
            return redirect()->back()->withInput()->with('error', lang('Finance.bankAccountSaveFailed'));
        }

        $this->audit()->log('banking.account.create', [
            'parish_id'   => $parishId,
            'entity_type' => 'bank_account',
            'entity_id'   => (int) $accountId,
            'new_values'  => ['name' => (string) $this->request->getPost('name')],
        ]);

        return redirect()->to('/banking/' . (int) $accountId)->with('success', lang('App.successSaved'));
    }

    /**
     * Account detail: transactions + reconciliations.
     */
    public function view($id)
    {
        if ($redirect = $this->requirePermission('finance.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $account = $this->banking->accountForParish((int) $id, $parishId);

        if ($account === null) {
            return redirect()->to('/banking')->with('error', lang('Finance.bankAccountNotFound'));
        }

        $transactionModel = model('BankTransactionModel');
        $reconModel = model('BankReconciliationModel');

        return view('Finance\Views\Banking/account', array_merge($this->getCommonViewData($account['name']), [
            'account'         => $account,
            'transactions'    => $transactionModel->forAccount((int) $account['id']),
            'reconciliations' => $reconModel->forAccount((int) $account['id']),
            'openRecon'       => $reconModel->openForAccount((int) $account['id']),
            'canManage'       => auth()->user()->can('finance.manage'),
        ]));
    }

    /**
     * Add a bank transaction to an account.
     */
    public function storeTransaction($accountId)
    {
        if ($redirect = $this->requirePermission('finance.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return redirect()->back()->withInput()->with('error', lang('App.errorNoParishScope'));
        }

        $account = $this->banking->accountForParish((int) $accountId, $parishId);

        if ($account === null) {
            return redirect()->to('/banking')->with('error', lang('Finance.bankAccountNotFound'));
        }

        $rules = [
            'transaction_date' => 'required|valid_date',
            'amount'           => 'required|decimal',
            'description'      => 'permit_empty|max_length[255]',
            'reference'        => 'permit_empty|max_length[100]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $result = $this->banking->addTransaction((int) $account['id'], [
            'transaction_date' => (string) $this->request->getPost('transaction_date'),
            'amount'           => (float) $this->request->getPost('amount'),
            'description'      => $this->request->getPost('description') ?: null,
            'reference'        => $this->request->getPost('reference') ?: null,
        ]);

        if (! $result['ok']) {
            return redirect()->back()->withInput()->with('error', lang('Finance.bankTransactionSaveFailed'));
        }

        $this->audit()->log('banking.transaction.create', [
            'parish_id'   => $parishId,
            'entity_type' => 'bank_transaction',
            'entity_id'   => (int) $result['id'],
            'new_values'  => ['bank_account_id' => (int) $account['id']],
        ]);

        return redirect()->to('/banking/' . (int) $account['id'])->with('success', lang('App.successSaved'));
    }

    /**
     * Edit existing bank account form.
     */
    public function edit($id)
    {
        if ($redirect = $this->requirePermission('finance.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $account = $this->banking->accountForParish((int) $id, $parishId);

        if ($account === null) {
            return redirect()->to('/banking')->with('error', lang('Finance.bankAccountNotFound'));
        }

        $dioceseId = 0;
        $parishRow = model('ParishModel')->find($parishId);
        if (is_array($parishRow)) {
            $dioceseId = (int) ($parishRow['diocese_id'] ?? 0);
        }

        $chartAccounts = model('ChartOfAccountModel')
            ->where('account_type', 'asset')
            ->where('is_active', 1)
            ->groupStart()
                ->where('scope_type', 'system')
                ->orGroupStart()
                    ->where('scope_type', 'diocese')
                    ->where('scope_id', $dioceseId)
                ->groupEnd()
                ->orGroupStart()
                    ->where('scope_type', 'parish')
                    ->where('scope_id', $parishId)
                ->groupEnd()
            ->groupEnd()
            ->orderBy('code', 'ASC')
            ->findAll();

        return view('Finance\Views\Banking/edit', array_merge($this->getCommonViewData(lang('Finance.bankAccountEdit')), [
            'account'       => $account,
            'chartAccounts' => $chartAccounts,
        ]));
    }

    /**
     * Persist updates to a bank account.
     */
    public function update($id)
    {
        if ($redirect = $this->requirePermission('finance.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return redirect()->back()->withInput()->with('error', lang('App.errorNoParishScope'));
        }

        $account = $this->banking->accountForParish((int) $id, $parishId);

        if ($account === null) {
            return redirect()->to('/banking')->with('error', lang('Finance.bankAccountNotFound'));
        }

        $rules = [
            'name'                  => 'required|max_length[150]',
            'institution_name'      => 'permit_empty|max_length[150]',
            'masked_account_number' => 'permit_empty|max_length[34]',
            'routing_last4'         => 'permit_empty|max_length[4]|numeric',
            'currency_code'         => 'required|exact_length[3]|alpha',
            'opening_balance'       => 'permit_empty|decimal',
            'opened_on'             => 'permit_empty|valid_date',
            'chart_account_id'      => 'permit_empty|integer',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $chartAccountId = (int) ($this->request->getPost('chart_account_id') ?? 0);
        if ($chartAccountId > 0) {
            $dioceseId = 0;
            $parishRow = model('ParishModel')->find($parishId);
            if (is_array($parishRow)) {
                $dioceseId = (int) ($parishRow['diocese_id'] ?? 0);
            }

            $chartAccount = model('ChartOfAccountModel')
                ->where('id', $chartAccountId)
                ->where('account_type', 'asset')
                ->where('is_active', 1)
                ->groupStart()
                    ->where('scope_type', 'system')
                    ->orGroupStart()
                        ->where('scope_type', 'diocese')
                        ->where('scope_id', $dioceseId)
                    ->groupEnd()
                    ->orGroupStart()
                        ->where('scope_type', 'parish')
                        ->where('scope_id', $parishId)
                    ->groupEnd()
                ->groupEnd()
                ->first();

            if ($chartAccount === null) {
                return redirect()->back()->withInput()->with('error', lang('Finance.bankInvalidChartAccount'));
            }
        }

        $updateData = [
            'parish_id'             => $parishId,
            'name'                  => trim((string) $this->request->getPost('name')),
            'institution_name'      => trim((string) ($this->request->getPost('institution_name') ?? '')) ?: null,
            'masked_account_number' => trim((string) ($this->request->getPost('masked_account_number') ?? '')) ?: null,
            'routing_last4'         => trim((string) ($this->request->getPost('routing_last4') ?? '')) ?: null,
            'currency_code'         => strtoupper(trim((string) $this->request->getPost('currency_code'))),
            'opening_balance'       => (float) ($this->request->getPost('opening_balance') ?: 0),
            'opened_on'             => $this->request->getPost('opened_on') ?: null,
            'chart_account_id'      => $chartAccountId > 0 ? $chartAccountId : null,
        ];

        $ok = model('BankAccountModel')->update((int) $id, $updateData);

        if (!$ok) {
            return redirect()->back()->withInput()->with('error', lang('Finance.bankAccountSaveFailed'));
        }

        $this->audit()->log('banking.account.update', [
            'parish_id'   => $parishId,
            'entity_type' => 'bank_account',
            'entity_id'   => (int) $id,
            'old_values'  => ['name' => $account['name'], 'chart_account_id' => $account['chart_account_id']],
            'new_values'  => ['name' => $updateData['name'], 'chart_account_id' => $updateData['chart_account_id']],
        ]);

        return redirect()->to('/banking/' . (int) $id)->with('success', lang('App.successSaved'));
    }

    /**
     * Toggle active / archived status of a bank account.
     */
    public function toggleActive($id)
    {
        if ($redirect = $this->requirePermission('finance.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return redirect()->back()->withInput()->with('error', lang('App.errorNoParishScope'));
        }

        $account = $this->banking->accountForParish((int) $id, $parishId);

        if ($account === null) {
            return redirect()->to('/banking')->with('error', lang('Finance.bankAccountNotFound'));
        }

        $currentActive = (int) ($account['is_active'] ?? 1);
        $newActive = $currentActive === 1 ? 0 : 1;

        // If archiving / deactivating, verify there is no open reconciliation
        if ($newActive === 0) {
            $openRecon = model('BankReconciliationModel')->openForAccount((int) $id);
            if ($openRecon !== null) {
                return redirect()->back()->with('error', lang('Finance.bankCannotDeactivateWithOpenRecon'));
            }
        }

        model('BankAccountModel')->skipValidation(true)->update((int) $id, ['is_active' => $newActive]);

        $this->audit()->log('banking.account.toggle_active', [
            'parish_id'   => $parishId,
            'entity_type' => 'bank_account',
            'entity_id'   => (int) $id,
            'new_values'  => ['is_active' => $newActive],
        ]);

        $msg = $newActive === 1
            ? lang('Finance.bankAccountActivatedSuccess', [$account['name']])
            : lang('Finance.bankAccountArchivedSuccess', [$account['name']]);

        return redirect()->back()->with('success', $msg);
    }
}
