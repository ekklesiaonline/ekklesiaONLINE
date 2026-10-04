<?php

namespace Treasurer\Controllers;

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

        return view('Treasurer\Views\Banking/index', array_merge($this->getCommonViewData(lang('Finance.bankingTitle')), [
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

        return view('Treasurer\Views\Banking/create', $this->getCommonViewData(lang('Finance.bankAccountCreate')));
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

        return view('Treasurer\Views\Banking/account', array_merge($this->getCommonViewData($account['name']), [
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
}
