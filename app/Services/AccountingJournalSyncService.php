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
    public function generateEntryNumber(int $parishId, string $prefix = 'JE'): string
    {
        $year = date('Y');
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

        $entryNumber = $this->generateEntryNumber($parishId);

        $entryId = $this->journalEntryModel->insert([
            'parish_id'    => $parishId,
            'entry_number' => $entryNumber,
            'period_id'    => null,
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
     * Creates a double-entry journal entry for a recorded donation.
     * Debits the Cash/Bank account and credits the Income Chart Account.
     */
    public function createJournalForDonation(int $donationId, int $cashChartAccountId, ?int $userId = null): ?int
    {
        $donationModel = model(DonationModel::class);
        $donation = $donationModel->find($donationId);

        if (!$donation || empty($donation['chart_account_id'])) {
            return null;
        }

        $amount = (float) $donation['amount'];
        $parishId = (int) $donation['parish_id'];
        $entryDate = $donation['date'] ?? date('Y-m-d');
        $incomeAccountId = (int) $donation['chart_account_id'];

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
    public function createJournalForExpense(int $expenseId, int $cashChartAccountId, ?int $userId = null): ?int
    {
        $expenseModel = model(ExpenseModel::class);
        $expense = $expenseModel->find($expenseId);

        if (!$expense || empty($expense['chart_account_id'])) {
            return null;
        }

        $amount = (float) $expense['amount'];
        $parishId = (int) $expense['parish_id'];
        $entryDate = $expense['date'] ?? date('Y-m-d');
        $expenseAccountId = (int) $expense['chart_account_id'];

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
}
