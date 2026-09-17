<?php

namespace Treasurer\Controllers;

use App\Controllers\BaseController;
use App\Services\BankReconciliationService;

class Banking extends BaseController
{
    private BankReconciliationService $banking;

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

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return redirect()->to('/dashboard')->with('error', lang('Treasurer.noParishForBudgeting'));
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

    /**
     * Open a new reconciliation session for an account.
     */
    public function startReconciliation($accountId)
    {
        if ($redirect = $this->requirePermission('finance.manage')) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $account = $this->banking->accountForParish((int) $accountId, $parishId);

        if ($account === null) {
            return redirect()->to('/banking')->with('error', lang('Finance.bankAccountNotFound'));
        }

        $rules = [
            'statement_date'    => 'required|valid_date',
            'statement_balance' => 'required|decimal',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $result = $this->banking->openReconciliation(
            (int) $account['id'],
            (string) $this->request->getPost('statement_date'),
            (float) $this->request->getPost('statement_balance')
        );

        if (! $result['ok']) {
            $message = ($result['code'] ?? '') === 'already_open'
                ? lang('Finance.reconAlreadyOpen')
                : lang('Finance.reconSaveFailed');

            return redirect()->back()->withInput()->with('error', $message);
        }

        $this->audit()->log('banking.reconciliation.open', [
            'parish_id'   => $parishId,
            'entity_type' => 'bank_reconciliation',
            'entity_id'   => (int) $result['id'],
            'new_values'  => ['bank_account_id' => (int) $account['id']],
        ]);

        return redirect()->to('/banking/reconciliations/' . (int) $result['id']);
    }

    /**
     * Reconciliation worksheet.
     */
    public function reconciliation($id)
    {
        if ($redirect = $this->requirePermission('finance.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $recon = $this->banking->reconciliationForParish((int) $id, $parishId);

        if ($recon === null) {
            return redirect()->to('/banking')->with('error', lang('Finance.reconNotFound'));
        }

        $account = $this->banking->accountForParish((int) $recon['bank_account_id'], $parishId);

        return view('Treasurer\Views\Banking/reconciliation', array_merge($this->getCommonViewData(lang('Finance.reconTitle')), [
            'recon'        => $recon,
            'account'      => $account,
            'transactions' => $this->banking->worksheetTransactions($recon),
            'totals'       => $this->banking->totals($recon),
            'canManage'    => auth()->user()->can('finance.manage') && ($recon['status'] ?? '') === 'open',
        ]));
    }

    /**
     * Save the cleared set for an open reconciliation.
     */
    public function saveReconciliationItems($id)
    {
        if ($redirect = $this->requirePermission('finance.manage')) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $recon = $this->banking->reconciliationForParish((int) $id, $parishId);

        if ($recon === null) {
            return redirect()->to('/banking')->with('error', lang('Finance.reconNotFound'));
        }

        $clearedIds = (array) ($this->request->getPost('cleared') ?? []);
        $result = $this->banking->saveCleared((int) $recon['id'], array_map('intval', $clearedIds));

        if (! $result['ok']) {
            $message = ($result['code'] ?? '') === 'not_open'
                ? lang('Finance.reconNotOpen')
                : lang('Finance.reconSaveFailed');

            return redirect()->back()->with('error', $message);
        }

        return redirect()->to('/banking/reconciliations/' . (int) $recon['id'])->with('success', lang('App.successSaved'));
    }

    /**
     * Close a reconciliation when cleared total equals statement balance.
     */
    public function closeReconciliation($id)
    {
        if ($redirect = $this->requirePermission('finance.manage')) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $recon = $this->banking->reconciliationForParish((int) $id, $parishId);

        if ($recon === null) {
            return redirect()->to('/banking')->with('error', lang('Finance.reconNotFound'));
        }

        $result = $this->banking->close((int) $recon['id'], auth()->loggedIn() ? (int) auth()->id() : null);

        if (! $result['ok']) {
            $code = (string) ($result['code'] ?? '');
            $message = match ($code) {
                'unbalanced' => lang('Finance.reconUnbalanced'),
                'not_open'   => lang('Finance.reconNotOpen'),
                default      => lang('Finance.reconSaveFailed'),
            };

            return redirect()->to('/banking/reconciliations/' . (int) $recon['id'])->with('error', $message);
        }

        $this->audit()->log('banking.reconciliation.close', [
            'parish_id'   => $parishId,
            'entity_type' => 'bank_reconciliation',
            'entity_id'   => (int) $recon['id'],
        ]);

        return redirect()->to('/banking/' . (int) $recon['bank_account_id'])->with('success', lang('Finance.reconClosed'));
    }
}
