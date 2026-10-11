<?php

namespace App\Services;

use App\Models\BankAccountModel;
use App\Models\BankReconciliationItemModel;
use App\Models\BankReconciliationModel;
use App\Models\BankTransactionModel;

/**
 * Bank reconciliation domain logic.
 *
 * Parish isolation is enforced at the bank_account boundary: callers resolve
 * the account through accountForParish() and downstream operations are keyed
 * off bank_account_id / bank_reconciliation_id, which have no parish column.
 *
 * Transaction lifecycle: unmatched -> cleared (ticked on an open
 * reconciliation) -> reconciled (reconciliation closed). Unticking a cleared
 * transaction on an open reconciliation returns it to unmatched.
 */
class BankReconciliationService
{
    private BankAccountModel $accounts;
    private BankTransactionModel $transactions;
    private BankReconciliationModel $reconciliations;
    private BankReconciliationItemModel $items;

    public function __construct(
        ?BankAccountModel $accounts = null,
        ?BankTransactionModel $transactions = null,
        ?BankReconciliationModel $reconciliations = null,
        ?BankReconciliationItemModel $items = null
    ) {
        $this->accounts        = $accounts ?? new BankAccountModel();
        $this->transactions    = $transactions ?? new BankTransactionModel();
        $this->reconciliations = $reconciliations ?? new BankReconciliationModel();
        $this->items           = $items ?? new BankReconciliationItemModel();
    }

    /**
     * Resolve a bank account only when it belongs to the given parish.
     *
     * @return array<string, mixed>|null
     */
    public function accountForParish(int $accountId, int $parishId): ?array
    {
        $account = $this->accounts
            ->where('id', $accountId)
            ->where('parish_id', $parishId)
            ->first();

        return is_array($account) ? $account : null;
    }

    /**
     * Record a bank transaction on an account.
     *
     * @param array{transaction_date: string, amount: float|int|string, description?: ?string, reference?: ?string} $data
     * @return array{ok: bool, code: ?string, id: ?int}
     */
    public function addTransaction(int $accountId, array $data): array
    {
        $id = $this->transactions->insert([
            'bank_account_id'  => $accountId,
            'transaction_date' => (string) $data['transaction_date'],
            'amount'           => $data['amount'],
            'description'      => $data['description'] ?? null,
            'reference'        => $data['reference'] ?? null,
            'status'           => 'unmatched',
        ]);

        if ($id === false) {
            return ['ok' => false, 'code' => 'insert_failed', 'id' => null];
        }

        return ['ok' => true, 'code' => null, 'id' => (int) $id];
    }

    /**
     * Import a batch of bank transactions for an account with duplicate detection.
     *
     * Matches on date, amount, and reference (or description) to prevent duplicates.
     *
     * @param int $accountId
     * @param array<int, array{transaction_date: string, amount: float|int|string, description?: ?string, reference?: ?string}> $transactions
     * @return array{imported: int, skipped: int, errors: array<int, string>}
     */
    public function importTransactions(int $accountId, array $transactions): array
    {
        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        foreach ($transactions as $txn) {
            $date   = trim((string) $txn['transaction_date']);
            $amount = (float) $txn['amount'];
            $desc   = isset($txn['description']) && $txn['description'] !== '' ? (string) $txn['description'] : null;
            $ref    = isset($txn['reference']) && $txn['reference'] !== '' ? (string) $txn['reference'] : null;

            if ($date === '') {
                continue;
            }

            // Fresh builder for each check to avoid builder state carry-over
            $builder = $this->transactions->builder();
            $builder->where('bank_account_id', $accountId)
                ->where('transaction_date', $date)
                ->where('amount', $amount);

            if ($ref !== null) {
                $builder->groupStart()
                    ->where('reference', $ref)
                    ->orWhere('description', $desc)
                    ->groupEnd();
            } elseif ($desc !== null) {
                $builder->where('description', $desc);
            }

            if ($builder->countAllResults() > 0) {
                $skipped++;
                continue;
            }

            $res = $this->addTransaction($accountId, [
                'transaction_date' => $date,
                'amount'           => $amount,
                'description'      => $desc,
                'reference'        => $ref,
            ]);

            if ($res['ok']) {
                $imported++;
            } else {
                $errors[] = "Failed to import transaction on {$date} for {$amount}";
            }
        }

        return [
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => $errors,
        ];
    }

    /**
     * Open a reconciliation for an account. Only one may be open at a time.
     *
     * @return array{ok: bool, code: ?string, id: ?int}
     */
    public function openReconciliation(int $accountId, string $statementDate, float $statementBalance): array
    {
        if ($this->reconciliations->openForAccount($accountId) !== null) {
            return ['ok' => false, 'code' => 'already_open', 'id' => null];
        }

        $id = $this->reconciliations->insert([
            'bank_account_id'   => $accountId,
            'statement_date'    => $statementDate,
            'statement_balance' => $statementBalance,
            'status'            => 'open',
        ]);

        if ($id === false) {
            return ['ok' => false, 'code' => 'insert_failed', 'id' => null];
        }

        return ['ok' => true, 'code' => null, 'id' => (int) $id];
    }

    /**
     * Resolve a reconciliation, but only when it belongs to an account owned
     * by the given parish.
     *
     * @return array<string, mixed>|null
     */
    public function reconciliationForParish(int $reconciliationId, int $parishId): ?array
    {
        $recon = $this->reconciliations->find($reconciliationId);

        if (! is_array($recon)) {
            return null;
        }

        $account = $this->accountForParish((int) ($recon['bank_account_id'] ?? 0), $parishId);

        return $account === null ? null : $recon;
    }

    /**
     * Worksheet rows: every transaction on the account dated on or before the
     * statement date that is not yet reconciled, with its cleared flag in this
     * reconciliation.
     *
     * @param array<string, mixed> $reconciliation
     * @return array<int, array<string, mixed>>
     */
    public function worksheetTransactions(array $reconciliation): array
    {
        $reconId = (int) ($reconciliation['id'] ?? 0);
        $isOpen = ($reconciliation['status'] ?? '') === 'open';
        $clearedIds = array_flip($this->items->clearedTransactionIds($reconId));

        $builder = $this->transactions
            ->where('bank_account_id', (int) $reconciliation['bank_account_id'])
            ->where('transaction_date <=', (string) $reconciliation['statement_date']);

        if ($isOpen) {
            $builder->where('status !=', 'reconciled');
        } else {
            $itemTxnIds = array_keys($clearedIds);
            if (empty($itemTxnIds)) {
                return [];
            }
            $builder->whereIn('id', $itemTxnIds);
        }

        $rows = $builder
            ->orderBy('transaction_date', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();

        $txnIds = array_filter(array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $rows));
        $journalMap = [];

        if (! empty($txnIds)) {
            $db = $this->transactions->db;
            if ($db->tableExists('bank_transaction_journal_link') && $db->tableExists('journal_entry')) {
                $links = $db->table('bank_transaction_journal_link btjl')
                    ->select('btjl.bank_transaction_id, je.id AS journal_entry_id, je.entry_number, je.status AS journal_entry_status')
                    ->join('journal_entry je', 'je.id = btjl.journal_entry_id', 'inner')
                    ->whereIn('btjl.bank_transaction_id', $txnIds)
                    ->get()
                    ->getResultArray();

                foreach ($links as $link) {
                    $journalMap[(int) $link['bank_transaction_id']] = $link;
                }
            }
        }

        foreach ($rows as &$row) {
            $txnId = (int) $row['id'];
            $row['is_cleared'] = isset($clearedIds[$txnId]) ? 1 : ($isOpen ? 0 : 1);
            $row['journal_entry_id'] = isset($journalMap[$txnId]['journal_entry_id']) ? (int) $journalMap[$txnId]['journal_entry_id'] : null;
            $row['journal_entry_number'] = isset($journalMap[$txnId]['entry_number']) ? (string) $journalMap[$txnId]['entry_number'] : null;
            $row['journal_entry_status'] = isset($journalMap[$txnId]['journal_entry_status']) ? (string) $journalMap[$txnId]['journal_entry_status'] : null;
        }
        unset($row);

        return $rows;
    }

    /**
     * Save the cleared set for an open reconciliation: creates/updates item
     * rows and keeps bank_transaction.status in sync
     * (cleared -> 'cleared', unticked -> 'unmatched').
     *
     * @param array<int, int> $clearedTransactionIds
     * @return array{ok: bool, code: ?string}
     */
    public function saveCleared(int $reconciliationId, array $clearedTransactionIds): array
    {
        $recon = $this->reconciliations->find($reconciliationId);

        if (! is_array($recon)) {
            return ['ok' => false, 'code' => 'not_found'];
        }

        if (($recon['status'] ?? '') !== 'open') {
            return ['ok' => false, 'code' => 'not_open'];
        }

        $clearedIds = array_map('intval', $clearedTransactionIds);
        $clearedIds = array_values(array_unique(array_filter($clearedIds, static fn (int $id): bool => $id > 0)));

        // Only transactions that are actually on this worksheet are eligible.
        $worksheet = $this->worksheetTransactions($recon);
        $worksheetIds = array_map(static fn (array $row): int => (int) $row['id'], $worksheet);
        $clearedIds = array_values(array_intersect($clearedIds, $worksheetIds));

        $db = $this->reconciliations->db;
        // A stale transStatus flag from an earlier failed query on this shared
        // connection would make transComplete() report a phantom failure.
        $db->resetTransStatus();
        $db->transStart();

        $existing = $this->items
            ->where('bank_reconciliation_id', $reconciliationId)
            ->findAll();

        $existingByTxn = [];
        foreach ($existing as $item) {
            $existingByTxn[(int) ($item['bank_transaction_id'] ?? 0)] = [
                'id'         => (int) $item['id'],
                'is_cleared' => (int) ($item['is_cleared'] ?? 0),
            ];
        }

        $now = date('Y-m-d H:i:s');

        foreach ($clearedIds as $txnId) {
            if (isset($existingByTxn[$txnId])) {
                if ($existingByTxn[$txnId]['is_cleared'] !== 1) {
                    $this->items->update($existingByTxn[$txnId]['id'], ['is_cleared' => 1]);
                }
            } else {
                $this->items->insert([
                    'bank_reconciliation_id' => $reconciliationId,
                    'bank_transaction_id'    => $txnId,
                    'is_cleared'             => 1,
                    'created_at'             => $now,
                ]);
            }
        }

        // Remove item rows for transactions that were unticked.
        foreach ($existingByTxn as $txnId => $item) {
            if (! in_array($txnId, $clearedIds, true)) {
                $this->items->delete($item['id']);
            }
        }

        // Sync transaction statuses for the worksheet set.
        foreach ($worksheetIds as $txnId) {
            $cleared = in_array($txnId, $clearedIds, true);
            $this->transactions->update($txnId, ['status' => $cleared ? 'cleared' : 'unmatched']);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['ok' => false, 'code' => 'update_failed'];
        }

        return ['ok' => true, 'code' => null];
    }

    /**
     * Totals for the reconciliation worksheet.
     *
     * @param array<string, mixed> $reconciliation
     * @return array{cleared_total: float, cleared_count: int, statement_balance: float, difference: float}
     */
    public function totals(array $reconciliation): array
    {
        $clearedIds = $this->items->clearedTransactionIds((int) $reconciliation['id']);

        $clearedTotal = 0.0;
        if ($clearedIds !== []) {
            $row = $this->transactions->db->table('bank_transaction')
                ->select('COALESCE(SUM(amount), 0) AS total', false)
                ->whereIn('id', $clearedIds)
                ->get()
                ->getRowArray();
            $clearedTotal = (float) ($row['total'] ?? 0);
        }

        $statementBalance = (float) ($reconciliation['statement_balance'] ?? 0);

        return [
            'cleared_total'     => $clearedTotal,
            'cleared_count'     => count($clearedIds),
            'statement_balance' => $statementBalance,
            'difference'        => round($statementBalance - $clearedTotal, 2),
        ];
    }

    /**
     * Close an open reconciliation. Requires the cleared total to equal the
     * statement balance; cleared transactions become 'reconciled'.
     *
     * @return array{ok: bool, code: ?string}
     */
    public function close(int $reconciliationId, ?int $closedBy = null): array
    {
        $recon = $this->reconciliations->find($reconciliationId);

        if (! is_array($recon)) {
            return ['ok' => false, 'code' => 'not_found'];
        }

        if (($recon['status'] ?? '') !== 'open') {
            return ['ok' => false, 'code' => 'not_open'];
        }

        $totals = $this->totals($recon);

        if (abs($totals['difference']) >= 0.005) {
            return ['ok' => false, 'code' => 'unbalanced'];
        }

        $db = $this->reconciliations->db;
        $db->resetTransStatus();
        $db->transStart();

        $this->reconciliations->update($reconciliationId, [
            'status'    => 'closed',
            'closed_by' => $closedBy,
            'closed_at' => date('Y-m-d H:i:s'),
        ]);

        foreach ($this->items->clearedTransactionIds($reconciliationId) as $txnId) {
            $this->transactions->update($txnId, ['status' => 'reconciled']);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['ok' => false, 'code' => 'update_failed'];
        }

        return ['ok' => true, 'code' => null];
    }
}
