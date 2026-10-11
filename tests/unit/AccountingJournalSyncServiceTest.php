<?php

namespace Tests\Unit;

use App\Models\BankTransactionJournalLinkModel;
use App\Models\JournalEntryLineModel;
use App\Models\JournalEntryModel;
use App\Services\AccountingJournalSyncService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use InvalidArgumentException;

/**
 * @internal
 */
final class AccountingJournalSyncServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;

    protected function setUp(): void
    {
        parent::setUp();

        $db = db_connect();
        $db->table('parish')->ignore(true)->insert([
            'id'         => 1,
            'name'       => 'Test Parish',
            'diocese_id' => 1,
            'is_active'  => 1,
        ]);

        session()->set('selected_parish_id', 1);
    }

    public function testUnbalancedJournalEntryThrowsException(): void
    {
        $service = new AccountingJournalSyncService();

        $lines = [
            [
                'chart_account_id' => 101,
                'debit_amount'     => 100.00,
                'credit_amount'    => 0.00,
            ],
            [
                'chart_account_id' => 401,
                'debit_amount'     => 0.00,
                'credit_amount'    => 80.00, // Unbalanced: 100 vs 80
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unbalanced journal entry');

        $service->createJournalEntry(1, '2026-10-11', 'manual', null, 'Test Unbalanced', $lines);
    }

    public function testBalancedJournalEntryCreatesRecordAndLines(): void
    {
        $service = new AccountingJournalSyncService();

        $lines = [
            [
                'chart_account_id' => 101,
                'debit_amount'     => 150.00,
                'credit_amount'    => 0.00,
                'memo'             => 'Cash Debit',
            ],
            [
                'chart_account_id' => 401,
                'debit_amount'     => 0.00,
                'credit_amount'    => 150.00,
                'memo'             => 'Pledge Income Credit',
            ],
        ];

        $entryId = $service->createJournalEntry(1, '2026-10-11', 'donation', 99, 'Test Balanced Donation', $lines);

        $this->assertGreaterThan(0, $entryId);

        $entry = model(JournalEntryModel::class)->find($entryId);
        $this->assertNotNull($entry);
        $this->assertSame('donation', $entry['source_type']);
        $this->assertSame(99, $entry['source_id']);
        $this->assertSame('posted', $entry['status']);

        $lineItems = model(JournalEntryLineModel::class)->where('journal_entry_id', $entryId)->findAll();
        $this->assertCount(2, $lineItems);
    }

    public function testLinkBankTransactionToJournal(): void
    {
        $service = new AccountingJournalSyncService();

        $linked = $service->linkBankTransactionToJournal(55, 77);
        $this->assertTrue($linked);

        $record = model(BankTransactionJournalLinkModel::class)
            ->where('bank_transaction_id', 55)
            ->where('journal_entry_id', 77)
            ->first();

        $this->assertNotNull($record);

        // Re-linking is idempotent
        $relinked = $service->linkBankTransactionToJournal(55, 77);
        $this->assertTrue($relinked);
    }

    public function testResolveOperatingCashChartAccountIdWithAccount1120(): void
    {
        $db = db_connect();
        $db->table('chart_of_accounts')->ignore(true)->insert([
            'id'           => 112,
            'code'         => '1120',
            'name'         => 'Cash - Checking',
            'account_type' => 'asset',
            'is_active'    => 1,
        ]);

        $service = new AccountingJournalSyncService();
        $accountId = $service->resolveOperatingCashChartAccountId(1);

        $this->assertSame(112, $accountId);
    }

    public function testResolveOperatingCashChartAccountIdWithBankAccountOverride(): void
    {
        $db = db_connect();
        $db->table('chart_of_accounts')->ignore(true)->insert([
            'id'           => 113,
            'code'         => '1130',
            'name'         => 'Cash - Savings',
            'account_type' => 'asset',
            'is_active'    => 1,
        ]);

        $db->table('bank_account')->insert([
            'parish_id'        => 1,
            'chart_account_id' => 113,
            'name'             => 'Operating Savings',
            'is_active'        => 1,
        ]);
        $bankId = (int) $db->insertID();

        $service = new AccountingJournalSyncService();
        $resolved = $service->resolveOperatingCashChartAccountId(1, $bankId);

        $this->assertSame(113, $resolved);
    }

    public function testCreateJournalForDonationAutoResolvesAndIsIdempotent(): void
    {
        $db = db_connect();
        $db->table('chart_of_accounts')->ignore(true)->insert([
            'id'           => 112,
            'code'         => '1120',
            'name'         => 'Cash - Checking',
            'account_type' => 'asset',
            'is_active'    => 1,
        ]);
        $db->table('chart_of_accounts')->ignore(true)->insert([
            'id'           => 412,
            'code'         => '4120',
            'name'         => 'Pledge & Tithe Revenue',
            'account_type' => 'income',
            'is_active'    => 1,
        ]);

        $donationId = (int) $db->table('donation')->insert([
            'parish_id'        => 1,
            'amount'           => '350.00',
            'date'             => '2026-10-11',
            'category'         => 'tithe',
            'chart_account_id' => 412,
            'notes'            => 'Weekly Pledge Tithe',
        ]);

        $service = new AccountingJournalSyncService();
        $entryId = $service->createJournalForDonation($donationId);

        $this->assertNotNull($entryId);
        $this->assertGreaterThan(0, $entryId);

        $entry = model(JournalEntryModel::class)->find($entryId);
        $this->assertSame('donation', $entry['source_type']);
        $this->assertSame($donationId, $entry['source_id']);
        $this->assertSame('posted', $entry['status']);

        $lines = model(JournalEntryLineModel::class)->where('journal_entry_id', $entryId)->findAll();
        $this->assertCount(2, $lines);

        // Verify debit on Cash (1120) and credit on Income (4120)
        $cashLine = array_values(array_filter($lines, fn($l) => (int) $l['chart_account_id'] === 112))[0] ?? null;
        $incomeLine = array_values(array_filter($lines, fn($l) => (int) $l['chart_account_id'] === 412))[0] ?? null;

        $this->assertNotNull($cashLine);
        $this->assertNotNull($incomeLine);
        $this->assertEquals(350.00, (float) $cashLine['debit_amount']);
        $this->assertEquals(0.00, (float) $cashLine['credit_amount']);
        $this->assertEquals(0.00, (float) $incomeLine['debit_amount']);
        $this->assertEquals(350.00, (float) $incomeLine['credit_amount']);

        // Idempotency check: calling again returns existing entry ID
        $secondEntryId = $service->createJournalForDonation($donationId);
        $this->assertSame($entryId, $secondEntryId);

        $totalEntries = model(JournalEntryModel::class)
            ->where('source_type', 'donation')
            ->where('source_id', $donationId)
            ->countAllResults();
        $this->assertSame(1, $totalEntries);
    }

    public function testCreateJournalForExpenseAutoResolvesAndIsIdempotent(): void
    {
        $db = db_connect();
        $db->table('chart_of_accounts')->ignore(true)->insert([
            'id'           => 112,
            'code'         => '1120',
            'name'         => 'Cash - Checking',
            'account_type' => 'asset',
            'is_active'    => 1,
        ]);
        $db->table('chart_of_accounts')->ignore(true)->insert([
            'id'           => 711,
            'code'         => '7110',
            'name'         => 'Clergy Stipend',
            'account_type' => 'expense',
            'is_active'    => 1,
        ]);

        $expenseId = (int) $db->table('expense')->insert([
            'parish_id'        => 1,
            'amount'           => 225.50,
            'date'             => '2026-10-11',
            'description'      => 'Clergy monthly stipend portion',
            'category'         => 'clergy',
            'chart_account_id' => 711,
        ]);

        $service = new AccountingJournalSyncService();
        $entryId = $service->createJournalForExpense($expenseId);

        $this->assertNotNull($entryId);
        $this->assertGreaterThan(0, $entryId);

        $entry = model(JournalEntryModel::class)->find($entryId);
        $this->assertSame('expense', $entry['source_type']);
        $this->assertSame($expenseId, $entry['source_id']);
        $this->assertSame('posted', $entry['status']);

        $lines = model(JournalEntryLineModel::class)->where('journal_entry_id', $entryId)->findAll();
        $this->assertCount(2, $lines);

        // Verify debit on Expense (7110) and credit on Cash (1120)
        $expenseLine = array_values(array_filter($lines, fn($l) => (int) $l['chart_account_id'] === 711))[0] ?? null;
        $cashLine = array_values(array_filter($lines, fn($l) => (int) $l['chart_account_id'] === 112))[0] ?? null;

        $this->assertNotNull($expenseLine);
        $this->assertNotNull($cashLine);
        $this->assertEquals(225.50, (float) $expenseLine['debit_amount']);
        $this->assertEquals(0.00, (float) $expenseLine['credit_amount']);
        $this->assertEquals(0.00, (float) $cashLine['debit_amount']);
        $this->assertEquals(225.50, (float) $cashLine['credit_amount']);

        // Idempotency check: calling again returns existing entry ID
        $secondEntryId = $service->createJournalForExpense($expenseId);
        $this->assertSame($entryId, $secondEntryId);

        $totalEntries = model(JournalEntryModel::class)
            ->where('source_type', 'expense')
            ->where('source_id', $expenseId)
            ->countAllResults();
        $this->assertSame(1, $totalEntries);
    }

    public function testBackfillHistorySimulatesAndExecutes(): void
    {
        $db = db_connect();
        $db->table('chart_of_accounts')->ignore(true)->insert([
            'id'           => 112,
            'code'         => '1120',
            'name'         => 'Cash - Checking',
            'account_type' => 'asset',
            'is_active'    => 1,
        ]);
        $db->table('chart_of_accounts')->ignore(true)->insert([
            'id'           => 412,
            'code'         => '4120',
            'name'         => 'Pledge & Tithe Revenue',
            'account_type' => 'income',
            'is_active'    => 1,
        ]);
        $db->table('chart_of_accounts')->ignore(true)->insert([
            'id'           => 711,
            'code'         => '7110',
            'name'         => 'Clergy Stipend',
            'account_type' => 'expense',
            'is_active'    => 1,
        ]);

        $db->table('donation')->insert([
            'parish_id'        => 1,
            'amount'           => '100.00',
            'date'             => '2025-05-15',
            'category'         => 'tithe',
            'chart_account_id' => 412,
        ]);

        $db->table('expense')->insert([
            'parish_id'        => 1,
            'amount'           => 50.00,
            'date'             => '2025-06-20',
            'description'      => 'Office supplies 2025',
            'category'         => 'office',
            'chart_account_id' => 711,
        ]);

        $service = new AccountingJournalSyncService();

        // 1. Dry run
        $dryStats = $service->backfillHistory(1, 2025, true);
        $this->assertTrue($dryStats['is_dry_run']);
        $this->assertSame(1, $dryStats['donations_evaluated']);
        $this->assertSame(1, $dryStats['donations_created']);
        $this->assertSame(1, $dryStats['expenses_evaluated']);
        $this->assertSame(1, $dryStats['expenses_created']);
        $this->assertSame(2, $dryStats['total_created']);

        // Verify no actual journal entries were created during dry run
        $entries2025 = model(JournalEntryModel::class)
            ->where('parish_id', 1)
            ->where('entry_date >=', '2025-01-01')
            ->where('entry_date <=', '2025-12-31')
            ->countAllResults();
        $this->assertSame(0, $entries2025);

        // 2. Live execution
        $liveStats = $service->backfillHistory(1, 2025, false);
        $this->assertFalse($liveStats['is_dry_run']);
        $this->assertSame(1, $liveStats['donations_created']);
        $this->assertSame(1, $liveStats['expenses_created']);
        $this->assertSame(2, $liveStats['total_created']);

        // 3. Second run should skip already processed entries
        $secondStats = $service->backfillHistory(1, 2025, false);
        $this->assertSame(1, $secondStats['donations_skipped']);
        $this->assertSame(1, $secondStats['expenses_skipped']);
        $this->assertSame(0, $secondStats['total_created']);
    }

    public function testCommandFinanceSyncJournalHistoryExecutes(): void
    {
        $result = command('finance:sync-journal-history --parish 1 --dry-run');
        $this->assertNotNull($result);
    }
}
