<?php

namespace Tests\Unit;

use App\Models\AccountingPeriodModel;
use App\Models\BankTransactionModel;
use App\Models\JournalEntryModel;
use App\Services\AccountingJournalSyncService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use RuntimeException;

/**
 * @internal
 */
final class AccountingPeriodModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;

    protected function setUp(): void
    {
        parent::setUp();

        $db = db_connect();
        $db->table('parish')->ignore(true)->insert([
            'id'         => 1,
            'name'       => 'Test Period Parish',
            'diocese_id' => 1,
            'is_active'  => 1,
        ]);

        session()->set('selected_parish_id', 1);
    }

    public function testCreateAndFindPeriodForDate(): void
    {
        $model = new AccountingPeriodModel();

        $periodId = (int) $model->insert([
            'parish_id'     => 1,
            'fiscal_year'   => 2026,
            'period_number' => 1,
            'period_name'   => 'January 2026',
            'start_date'    => '2026-01-01',
            'end_date'      => '2026-01-31',
            'status'        => 'open',
        ], true);

        $this->assertGreaterThan(0, $periodId);

        $period = $model->findPeriodForDate(1, '2026-01-15');
        $this->assertNotNull($period);
        $this->assertSame('January 2026', $period['period_name']);
        $this->assertFalse($model->isDateInClosedPeriod(1, '2026-01-15'));
    }

    public function testCloseAndReopenPeriod(): void
    {
        $model = new AccountingPeriodModel();

        $periodId = (int) $model->insert([
            'parish_id'     => 1,
            'fiscal_year'   => 2026,
            'period_number' => 2,
            'period_name'   => 'February 2026',
            'start_date'    => '2026-02-01',
            'end_date'      => '2026-02-28',
            'status'        => 'open',
        ], true);

        $this->assertTrue($model->closePeriod($periodId));
        $this->assertTrue($model->isPeriodClosed($periodId));
        $this->assertTrue($model->isDateInClosedPeriod(1, '2026-02-14'));

        $this->assertTrue($model->reopenPeriod($periodId));
        $this->assertFalse($model->isPeriodClosed($periodId));
        $this->assertFalse($model->isDateInClosedPeriod(1, '2026-02-14'));
    }

    public function testClosedPeriodBlocksJournalEntryCreation(): void
    {
        $model = new AccountingPeriodModel();

        $model->insert([
            'parish_id'     => 1,
            'fiscal_year'   => 2026,
            'period_number' => 3,
            'period_name'   => 'March 2026',
            'start_date'    => '2026-03-01',
            'end_date'      => '2026-03-31',
            'status'        => 'closed',
            'closed_at'     => date('Y-m-d H:i:s'),
        ]);

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
                'credit_amount'    => 100.00,
            ],
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Accounting period 'March 2026' is closed");

        $service->createJournalEntry(1, '2026-03-15', 'manual', null, 'Locked Entry', $lines);
    }

    public function testOpenPeriodAssignsPeriodIdToJournalEntry(): void
    {
        $model = new AccountingPeriodModel();

        $periodId = (int) $model->insert([
            'parish_id'     => 1,
            'fiscal_year'   => 2026,
            'period_number' => 4,
            'period_name'   => 'April 2026',
            'start_date'    => '2026-04-01',
            'end_date'      => '2026-04-30',
            'status'        => 'open',
        ], true);

        $service = new AccountingJournalSyncService();

        $lines = [
            [
                'chart_account_id' => 101,
                'debit_amount'     => 75.00,
                'credit_amount'    => 0.00,
            ],
            [
                'chart_account_id' => 401,
                'debit_amount'     => 0.00,
                'credit_amount'    => 75.00,
            ],
        ];

        $entryId = $service->createJournalEntry(1, '2026-04-10', 'manual', null, 'April Entry', $lines);
        $this->assertGreaterThan(0, $entryId);

        $entry = model(JournalEntryModel::class)->find($entryId);
        $this->assertSame($periodId, (int) $entry['period_id']);
    }

    public function testReconciledBankTransactionMutationBlocked(): void
    {
        $db = db_connect();

        $bankId = (int) $db->table('bank_account')->insert([
            'parish_id'        => 1,
            'chart_account_id' => 112,
            'name'             => 'Operating Account',
            'is_active'        => 1,
        ]);

        $txnModel = new BankTransactionModel();
        $txnId = (int) $txnModel->insert([
            'bank_account_id'  => $bankId,
            'transaction_date' => '2026-04-12',
            'amount'           => 120.00,
            'description'      => 'Deposit',
            'status'           => 'reconciled',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Reconciled bank transactions cannot be modified or deleted');

        $txnModel->update($txnId, [
            'amount' => 150.00,
        ]);
    }

    public function testBankTransactionInClosedPeriodMutationBlocked(): void
    {
        $db = db_connect();

        $bankId = (int) $db->table('bank_account')->insert([
            'parish_id'        => 1,
            'chart_account_id' => 112,
            'name'             => 'Operating Account 2',
            'is_active'        => 1,
        ]);

        $periodModel = new AccountingPeriodModel();
        $periodModel->insert([
            'parish_id'     => 1,
            'fiscal_year'   => 2026,
            'period_number' => 5,
            'period_name'   => 'May 2026',
            'start_date'    => '2026-05-01',
            'end_date'      => '2026-05-31',
            'status'        => 'closed',
            'closed_at'     => date('Y-m-d H:i:s'),
        ]);

        $txnModel = new BankTransactionModel();
        $txnId = (int) $txnModel->insert([
            'bank_account_id'  => $bankId,
            'transaction_date' => '2026-05-15',
            'amount'           => 80.00,
            'description'      => 'May Payment',
            'status'           => 'unmatched',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Bank transactions in a closed accounting period cannot be modified or deleted');

        $txnModel->update($txnId, [
            'description' => 'Modified description',
        ]);
    }
}
