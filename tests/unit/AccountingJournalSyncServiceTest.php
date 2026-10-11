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
}
