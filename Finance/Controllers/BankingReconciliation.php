<?php

namespace Finance\Controllers;

/**
 * BankingReconciliation
 *
 * Bank reconciliation lifecycle endpoints: start, view items, save items,
 * close. Extracted from Banking (treasurer second pass); delegates to the
 * shared BankReconciliationService via the parent.
 */
class BankingReconciliation extends Banking
{

    /**
     * Open a new reconciliation session for an account.
     */
    public function startReconciliation($accountId)
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

        return view('Finance\Views\Banking/reconciliation', array_merge($this->getCommonViewData(lang('Finance.reconTitle')), [
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

        if ($redirect = $this->requireParish()) {
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

        if ($redirect = $this->requireParish()) {
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
