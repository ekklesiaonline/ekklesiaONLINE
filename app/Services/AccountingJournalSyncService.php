<?php

namespace App\Services;

use App\Models\BankTransactionJournalLinkModel;
use App\Models\DonationModel;
use App\Models\ExpenseModel;
use App\Models\JournalEntryLineModel;
use App\Models\JournalEntryModel;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use InvalidArgumentException;
use RuntimeException;

/**
 * AccountingJournalSyncService
 *
 * Provides atomic synchronization between operational cash/bank events
 * (donations, expenses, bank transactions) and double-entry general ledger
 * journal entries (journal_entry and journal_entry_line).
 */
class AccountingJournalSyncService
{
    protected BaseConnection $db;
    protected JournalEntryModel $journalEntryModel;
    protected JournalEntryLineModel $journalEntryLineModel;
    protected BankTransactionJournalLinkModel $bankLinkModel;

    public function __construct(
        ?BaseConnection $db = null,
        ?JournalEntryModel $journalEntryModel = null,
        ?JournalEntryLineModel $journalEntryLineModel = null,
        ?BankTransactionJournalLinkModel $bankLinkModel = null
    ) {
        $this->db = $db ?? Database::connect();
        $this->journalEntryModel = $journalEntryModel ?? new JournalEntryModel();
        $this->journalEntryLineModel = $journalEntryLineModel ?? new JournalEntryLineModel();
        $this->bankLinkModel = $bankLinkModel ?? new BankTransactionJournalLinkModel();
    }

    /**
     * Generates a sequential journal entry number for a parish.
     */
    public function generateEntryNumber(int $parishId, string $prefix = 'JE', ?string $entryDate = null): string
    {
        $year = $entryDate ? date('Y', strtotime($entryDate)) : date('Y');
        $count = $this->journalEntryModel
            ->where('parish_id', $parishId)
            ->where('entry_date >=', "{$year}-01-01")
            ->where('entry_date <=', "{$year}-12-31")
            ->countAllResults();

        $seq = str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);

        return "{$prefix}-{$year}-{$seq}";
    }

    /**
     * Creates a balanced double-entry journal entry with line items.
     *
     * @param array<int, array{chart_account_id: int, debit_amount: float, credit_amount: float, memo?: string}> $lines
     */
    public function createJournalEntry(
        int $parishId,
        string $entryDate,
        string $sourceType,
        ?int $sourceId,
        ?string $memo,
        array $lines,
        string $status = 'posted',
        ?int $userId = null
    ): int {
        if (empty($lines)) {
            throw new InvalidArgumentException('Journal entry must contain at least two line items.');
        }

        $totalDebits = 0.0;
        $totalCredits = 0.0;

        foreach ($lines as $line) {
            $totalDebits += (float) ($line['debit_amount'] ?? 0.0);
            $totalCredits += (float) ($line['credit_amount'] ?? 0.0);
        }

        // Validate double-entry equality with epsilon for float comparison
        if (abs($totalDebits - $totalCredits) > 0.005) {
            throw new InvalidArgumentException(sprintf(
                'Unbalanced journal entry: Total debits (%.2f) do not equal total credits (%.2f).',
                $totalDebits,
                $totalCredits
            ));
        }

        $this->db->transStart();

        $entryNumber = $this->generateEntryNumber($parishId, 'JE', $entryDate);

        // Resolve accounting period for this transaction date if configured
        $periodModel = model(\App\Models\AccountingPeriodModel::class);
        $period = $periodModel->findPeriodForDate($parishId, $entryDate);

        if ($period && ($period['status'] ?? 'open') === 'closed') {
            throw new RuntimeException("Cannot create journal entry: Accounting period '{$period['period_name']}' is closed.");
        }

        $periodId = $period ? (int) $period['id'] : null;

        $entryId = $this->journalEntryModel->insert([
            'parish_id'    => $parishId,
            'entry_number' => $entryNumber,
            'period_id'    => $periodId,
            'entry_date'   => $entryDate,
            'source_type'  => $sourceType,
            'source_id'    => $sourceId,
            'memo'         => $memo,
            'status'       => $status,
            'posted_by'    => $userId,
            'posted_at'    => $status === 'posted' ? date('Y-m-d H:i:s') : null,
            'created_by'   => $userId,
        ], true);

        if (!$entryId) {
            $this->db->transRollback();
            throw new RuntimeException('Failed to create journal entry.');
        }

        foreach ($lines as $line) {
            $lineInserted = $this->journalEntryLineModel->insert([
                'journal_entry_id' => (int) $entryId,
                'chart_account_id' => (int) $line['chart_account_id'],
                'debit_amount'     => (float) ($line['debit_amount'] ?? 0.0),
                'credit_amount'    => (float) ($line['credit_amount'] ?? 0.0),
                'memo'             => $line['memo'] ?? null,
            ]);

            if (!$lineInserted) {
                $this->db->transRollback();
                throw new RuntimeException('Failed to insert journal entry line.');
            }
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            throw new RuntimeException('Transaction failed while saving journal entry.');
        }

        return (int) $entryId;
    }

    /**
     * Links a bank transaction record to a general ledger journal entry.
     */
    public function linkBankTransactionToJournal(int $bankTransactionId, int $journalEntryId): bool
    {
        $existing = $this->bankLinkModel
            ->where('bank_transaction_id', $bankTransactionId)
            ->where('journal_entry_id', $journalEntryId)
            ->first();

        if ($existing) {
            return true;
        }

        return (bool) $this->bankLinkModel->insert([
            'bank_transaction_id' => $bankTransactionId,
            'journal_entry_id'    => $journalEntryId,
        ]);
    }

    /**
     * Resolves the operating cash chart account ID.
     *
     * Decision 5.1:
     * - If a specific parish bank account is provided and has a linked chart account, use it.
     * - Primary operating cash defaults to Account 1120 ('Cash - Checking').
     * - If Account 1120 is not found, fallback to the parish's active bank account linked chart account.
     * - If still not found, fallback to an active asset account (preferring 1100s).
     */
    public function resolveOperatingCashChartAccountId(?int $parishId = null, ?int $bankAccountId = null): ?int
    {
        $chartAccountModel = model(\App\Models\ChartOfAccountModel::class);

        // 1. Explicit bank account override
        if ($bankAccountId !== null && $bankAccountId > 0) {
            $bankAccount = $this->db->table('bank_account')
                ->where('id', $bankAccountId)
                ->where('is_active', 1)
                ->get()
                ->getRowArray();

            if (!empty($bankAccount['chart_account_id'])) {
                $account = $chartAccountModel->where('id', (int) $bankAccount['chart_account_id'])
                    ->where('is_active', 1)
                    ->first();
                if ($account) {
                    return (int) $account['id'];
                }
            }
        }

        // 2. Primary default: Account 1120 ('Cash - Checking')
        $defaultAccount = $chartAccountModel
            ->where('code', '1120')
            ->where('is_active', 1)
            ->first();

        if ($defaultAccount) {
            return (int) $defaultAccount['id'];
        }

        // 3. Fallback: Parish active bank account with a linked chart account
        if ($parishId !== null && $parishId > 0) {
            $parishBank = $this->db->table('bank_account')
                ->where('parish_id', $parishId)
                ->where('is_active', 1)
                ->where('chart_account_id IS NOT NULL', null, false)
                ->orderBy('id', 'ASC')
                ->get()
                ->getRowArray();

            if (!empty($parishBank['chart_account_id'])) {
                $account = $chartAccountModel->where('id', (int) $parishBank['chart_account_id'])
                    ->where('is_active', 1)
                    ->first();
                if ($account) {
                    return (int) $account['id'];
                }
            }
        }

        // 4. Fallback: Search for any active 1100s asset account (e.g. 1100, 1110)
        $assetAccount = $chartAccountModel
            ->where('account_type', 'asset')
            ->where('is_active', 1)
            ->like('code', '11', 'after')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('code', 'ASC')
            ->first();

        if ($assetAccount) {
            return (int) $assetAccount['id'];
        }

        // 5. Final fallback: Any active asset account
        $anyAsset = $chartAccountModel
            ->where('account_type', 'asset')
            ->where('is_active', 1)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('code', 'ASC')
            ->first();

        return $anyAsset ? (int) $anyAsset['id'] : null;
    }

    /**
     * Creates a double-entry journal entry for a recorded donation.
     * Debits the Cash/Bank account and credits the Income Chart Account.
     */
    public function createJournalForDonation(
        int $donationId,
        ?int $cashChartAccountId = null,
        ?int $userId = null,
        ?int $bankAccountId = null
    ): ?int {
        $donationModel = model(DonationModel::class);
        $donation = $donationModel->find($donationId);

        if (!$donation) {
            return null;
        }

        $amount = (float) $donation['amount'];
        if ($amount <= 0.0) {
            return null;
        }

        $parishId = (int) $donation['parish_id'];

        // Idempotency check: don't duplicate journal entry
        $existing = $this->journalEntryModel
            ->where('parish_id', $parishId)
            ->where('source_type', 'donation')
            ->where('source_id', $donationId)
            ->first();

        if ($existing) {
            return (int) $existing['id'];
        }

        // Resolve cash account if not provided
        if ($cashChartAccountId === null || $cashChartAccountId <= 0) {
            $cashChartAccountId = $this->resolveOperatingCashChartAccountId($parishId, $bankAccountId);
        }

        if ($cashChartAccountId === null || $cashChartAccountId <= 0) {
            log_message('warning', "Cannot create journal entry for donation #{$donationId}: no operating cash chart account resolved.");
            return null;
        }

        // Resolve income account
        $incomeAccountId = !empty($donation['chart_account_id']) ? (int) $donation['chart_account_id'] : null;
        if ($incomeAccountId === null) {
            if (!empty($donation['fund_id'])) {
                $fund = $this->db->table('fund')->where('id', (int) $donation['fund_id'])->get()->getRowArray();
                if (!empty($fund['chart_account_id'])) {
                    $incomeAccountId = (int) $fund['chart_account_id'];
                }
            }
            if ($incomeAccountId === null) {
                $chartAccountModel = model(\App\Models\ChartOfAccountModel::class);
                $defaultIncome = $chartAccountModel
                    ->where('code', '4320')
                    ->where('is_active', 1)
                    ->first();
                if (!$defaultIncome) {
                    $defaultIncome = $chartAccountModel
                        ->where('account_type', 'income')
                        ->where('is_active', 1)
                        ->first();
                }
                if ($defaultIncome) {
                    $incomeAccountId = (int) $defaultIncome['id'];
                }
            }
        }

        if ($incomeAccountId === null) {
            log_message('warning', "Cannot create journal entry for donation #{$donationId}: no income chart account resolved.");
            return null;
        }

        $entryDate = $donation['date'] ?? date('Y-m-d');

        $lines = [
            [
                'chart_account_id' => $cashChartAccountId,
                'debit_amount'     => $amount,
                'credit_amount'    => 0.0,
                'memo'             => 'Donation Cash Receipt',
            ],
            [
                'chart_account_id' => $incomeAccountId,
                'debit_amount'     => 0.0,
                'credit_amount'    => $amount,
                'memo'             => $donation['notes'] ?? 'Donation Income',
            ],
        ];

        return $this->createJournalEntry(
            $parishId,
            $entryDate,
            'donation',
            $donationId,
            "Donation #{$donationId}",
            $lines,
            'posted',
            $userId
        );
    }

    /**
     * Creates a double-entry journal entry for an operational expense.
     * Debits the Expense Chart Account and credits the Cash/Bank account.
     */
    public function createJournalForExpense(
        int $expenseId,
        ?int $cashChartAccountId = null,
        ?int $userId = null,
        ?int $bankAccountId = null
    ): ?int {
        $expenseModel = model(ExpenseModel::class);
        $expense = $expenseModel->find($expenseId);

        if (!$expense) {
            return null;
        }

        $amount = (float) $expense['amount'];
        if ($amount <= 0.0) {
            return null;
        }

        $parishId = (int) $expense['parish_id'];

        // Idempotency check: don't duplicate journal entry
        $existing = $this->journalEntryModel
            ->where('parish_id', $parishId)
            ->where('source_type', 'expense')
            ->where('source_id', $expenseId)
            ->first();

        if ($existing) {
            return (int) $existing['id'];
        }

        // Resolve cash account if not provided
        if ($cashChartAccountId === null || $cashChartAccountId <= 0) {
            $cashChartAccountId = $this->resolveOperatingCashChartAccountId($parishId, $bankAccountId);
        }

        if ($cashChartAccountId === null || $cashChartAccountId <= 0) {
            log_message('warning', "Cannot create journal entry for expense #{$expenseId}: no operating cash chart account resolved.");
            return null;
        }

        // Resolve expense account
        $expenseAccountId = !empty($expense['chart_account_id']) ? (int) $expense['chart_account_id'] : null;
        if ($expenseAccountId === null) {
            if (!empty($expense['fund_id'])) {
                $fund = $this->db->table('fund')->where('id', (int) $expense['fund_id'])->get()->getRowArray();
                if (!empty($fund['chart_account_id'])) {
                    $expenseAccountId = (int) $fund['chart_account_id'];
                }
            }
            if ($expenseAccountId === null) {
                $chartAccountModel = model(\App\Models\ChartOfAccountModel::class);
                $defaultExpense = $chartAccountModel
                    ->where('account_type', 'expense')
                    ->where('is_active', 1)
                    ->orderBy('sort_order', 'ASC')
                    ->orderBy('code', 'ASC')
                    ->first();
                if ($defaultExpense) {
                    $expenseAccountId = (int) $defaultExpense['id'];
                }
            }
        }

        if ($expenseAccountId === null) {
            log_message('warning', "Cannot create journal entry for expense #{$expenseId}: no expense chart account resolved.");
            return null;
        }

        $entryDate = $expense['date'] ?? date('Y-m-d');

        $lines = [
            [
                'chart_account_id' => $expenseAccountId,
                'debit_amount'     => $amount,
                'credit_amount'    => 0.0,
                'memo'             => $expense['description'] ?? 'Parish Expense',
            ],
            [
                'chart_account_id' => $cashChartAccountId,
                'debit_amount'     => 0.0,
                'credit_amount'    => $amount,
                'memo'             => 'Disbursement / Cash Payment',
            ],
        ];

        return $this->createJournalEntry(
            $parishId,
            $entryDate,
            'expense',
            $expenseId,
            "Expense #{$expenseId}: " . ($expense['description'] ?? ''),
            $lines,
            'posted',
            $userId
        );
    }

    /**
     * Backfills historical donations and expenses into general ledger journal entries.
     *
     * @return array{
     *   donations_evaluated: int,
     *   donations_created: int,
     *   donations_skipped: int,
     *   expenses_evaluated: int,
     *   expenses_created: int,
     *   expenses_skipped: int,
     *   total_created: int,
     *   is_dry_run: bool
     * }
     */
    public function backfillHistory(?int $parishId = null, ?int $year = null, bool $dryRun = false): array
    {
        $donationsEvaluated = 0;
        $donationsCreated = 0;
        $donationsSkipped = 0;

        $expensesEvaluated = 0;
        $expensesCreated = 0;
        $expensesSkipped = 0;

        // 1. Process Donations
        $donationBuilder = $this->db->table('donation')
            ->select('id, parish_id, date');

        if ($parishId !== null && $parishId > 0) {
            $donationBuilder->where('parish_id', $parishId);
        }
        if ($year !== null && $year > 0) {
            $donationBuilder->where('date >=', "{$year}-01-01")
                ->where('date <=', "{$year}-12-31");
        }

        if ($this->db->fieldExists('deleted_at', 'donation')) {
            $donationBuilder->where('deleted_at', null);
        }

        $donations = $donationBuilder->orderBy('date', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray();

        foreach ($donations as $donation) {
            $donationsEvaluated++;
            $dId = (int) $donation['id'];
            $pId = (int) $donation['parish_id'];

            $exists = $this->journalEntryModel
                ->where('parish_id', $pId)
                ->where('source_type', 'donation')
                ->where('source_id', $dId)
                ->first();

            if ($exists) {
                $donationsSkipped++;
                continue;
            }

            if ($dryRun) {
                $donationsCreated++;
            } else {
                $createdId = $this->createJournalForDonation($dId);
                if ($createdId !== null && $createdId > 0) {
                    $donationsCreated++;
                } else {
                    $donationsSkipped++;
                }
            }
        }

        // 2. Process Expenses
        $expenseBuilder = $this->db->table('expense')
            ->select('id, parish_id, date');

        if ($parishId !== null && $parishId > 0) {
            $expenseBuilder->where('parish_id', $parishId);
        }
        if ($year !== null && $year > 0) {
            $expenseBuilder->where('date >=', "{$year}-01-01")
                ->where('date <=', "{$year}-12-31");
        }

        if ($this->db->fieldExists('deleted_at', 'expense')) {
            $expenseBuilder->where('deleted_at', null);
        }

        $expenses = $expenseBuilder->orderBy('date', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray();

        foreach ($expenses as $expense) {
            $expensesEvaluated++;
            $eId = (int) $expense['id'];
            $pId = (int) $expense['parish_id'];

            $exists = $this->journalEntryModel
                ->where('parish_id', $pId)
                ->where('source_type', 'expense')
                ->where('source_id', $eId)
                ->first();

            if ($exists) {
                $expensesSkipped++;
                continue;
            }

            if ($dryRun) {
                $expensesCreated++;
            } else {
                $createdId = $this->createJournalForExpense($eId);
                if ($createdId !== null && $createdId > 0) {
                    $expensesCreated++;
                } else {
                    $expensesSkipped++;
                }
            }
        }

        return [
            'donations_evaluated' => $donationsEvaluated,
            'donations_created'   => $donationsCreated,
            'donations_skipped'   => $donationsSkipped,
            'expenses_evaluated'  => $expensesEvaluated,
            'expenses_created'    => $expensesCreated,
            'expenses_skipped'    => $expensesSkipped,
            'total_created'       => $donationsCreated + $expensesCreated,
            'is_dry_run'          => $dryRun,
        ];
    }
}
