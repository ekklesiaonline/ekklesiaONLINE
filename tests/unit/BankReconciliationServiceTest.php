<?php

use App\Services\BankReconciliationService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * T3.4: bank accounts + reconciliation lifecycle.
 *
 * Covers account parish scoping, transaction creation, the open -> cleared ->
 * reconciled lifecycle, the one-open-reconciliation rule, and the requirement
 * that a reconciliation only closes when the cleared total equals the
 * statement balance.
 *
 * @internal
 */
final class BankReconciliationServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    private BankReconciliationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BankReconciliationService();
        $this->seedParish(301);
        session()->set('selected_parish_id', 301);
    }

    public function testAccountForParishEnforcesScope(): void
    {
        $accountId = $this->insertAccount(301, 'Operating');

        $this->assertNotNull($this->service->accountForParish($accountId, 301));
        $this->assertNull($this->service->accountForParish($accountId, 302));
    }

    public function testAddTransactionCreatesUnmatchedRow(): void
    {
        $accountId = $this->insertAccount(301, 'Operating');

        $result = $this->service->addTransaction($accountId, [
            'transaction_date' => '2026-09-01',
            'amount'           => 1250.50,
            'description'      => 'Deposit',
        ]);

        $this->assertTrue($result['ok']);

        $row = $this->db->table('bank_transaction')->where('id', $result['id'])->get()->getRowArray();
        $this->assertSame('unmatched', $row['status']);
        $this->assertEquals(1250.50, (float) $row['amount']);
    }

    public function testOnlyOneOpenReconciliationPerAccount(): void
    {
        $accountId = $this->insertAccount(301, 'Operating');

        $first = $this->service->openReconciliation($accountId, '2026-09-30', 5000.00);
        $this->assertTrue($first['ok']);

        $second = $this->service->openReconciliation($accountId, '2026-10-31', 5100.00);
        $this->assertFalse($second['ok']);
        $this->assertSame('already_open', $second['code']);
    }

    public function testSaveClearedMarksTransactionsAndItems(): void
    {
        $accountId = $this->insertAccount(301, 'Operating');
        $txnA = $this->insertTransaction($accountId, '2026-09-05', 100.00);
        $txnB = $this->insertTransaction($accountId, '2026-09-10', -25.00);

        $recon = $this->service->openReconciliation($accountId, '2026-09-30', 75.00);
        $this->assertTrue($recon['ok']);

        $save = $this->service->saveCleared($recon['id'], [$txnA, $txnB]);
        $this->assertTrue($save['ok']);

        $statusA = $this->db->table('bank_transaction')->where('id', $txnA)->get()->getRow('status');
        $statusB = $this->db->table('bank_transaction')->where('id', $txnB)->get()->getRow('status');
        $this->assertSame('cleared', $statusA);
        $this->assertSame('cleared', $statusB);

        $reconRow = $this->db->table('bank_reconciliation')->where('id', $recon['id'])->get()->getRowArray();
        $totals = $this->service->totals($reconRow);
        $this->assertEquals(75.00, $totals['cleared_total']);
        $this->assertEquals(0.0, $totals['difference']);
        $this->assertSame(2, $totals['cleared_count']);
    }

    public function testUntickingReturnsTransactionToUnmatched(): void
    {
        $accountId = $this->insertAccount(301, 'Operating');
        $txnA = $this->insertTransaction($accountId, '2026-09-05', 100.00);
        $txnB = $this->insertTransaction($accountId, '2026-09-10', -25.00);

        $recon = $this->service->openReconciliation($accountId, '2026-09-30', 100.00);
        $this->service->saveCleared($recon['id'], [$txnA, $txnB]);

        // Untick B — only A remains cleared.
        $this->service->saveCleared($recon['id'], [$txnA]);

        $statusB = $this->db->table('bank_transaction')->where('id', $txnB)->get()->getRow('status');
        $this->assertSame('unmatched', $statusB);

        $itemCount = $this->db->table('bank_reconciliation_item')
            ->where('bank_reconciliation_id', $recon['id'])
            ->countAllResults();
        $this->assertSame(1, $itemCount);
    }

    public function testSaveClearedIgnoresTransactionsOutsideWorksheet(): void
    {
        $accountId = $this->insertAccount(301, 'Operating');
        $inScope = $this->insertTransaction($accountId, '2026-09-05', 100.00);
        $outOfScope = $this->insertTransaction($accountId, '2026-10-15', 50.00); // after statement date
        $otherAccount = $this->insertAccount(301, 'Savings');
        $otherTxn = $this->insertTransaction($otherAccount, '2026-09-01', 999.00);

        $recon = $this->service->openReconciliation($accountId, '2026-09-30', 100.00);
        $save = $this->service->saveCleared($recon['id'], [$inScope, $outOfScope, $otherTxn]);

        $this->assertTrue($save['ok']);

        $itemCount = $this->db->table('bank_reconciliation_item')
            ->where('bank_reconciliation_id', $recon['id'])
            ->countAllResults();
        $this->assertSame(1, $itemCount);
    }

    public function testCloseFailsWhenUnbalanced(): void
    {
        $accountId = $this->insertAccount(301, 'Operating');
        $txnA = $this->insertTransaction($accountId, '2026-09-05', 100.00);

        $recon = $this->service->openReconciliation($accountId, '2026-09-30', 200.00);
        $this->service->saveCleared($recon['id'], [$txnA]);

        $result = $this->service->close($recon['id'], 77);
        $this->assertFalse($result['ok']);
        $this->assertSame('unbalanced', $result['code']);

        $row = $this->db->table('bank_reconciliation')->where('id', $recon['id'])->get()->getRowArray();
        $this->assertSame('open', $row['status']);
    }

    public function testCloseMarksTransactionsReconciled(): void
    {
        $accountId = $this->insertAccount(301, 'Operating');
        $txnA = $this->insertTransaction($accountId, '2026-09-05', 100.00);
        $txnB = $this->insertTransaction($accountId, '2026-09-10', -25.00);

        $recon = $this->service->openReconciliation($accountId, '2026-09-30', 75.00);
        $this->service->saveCleared($recon['id'], [$txnA, $txnB]);

        $result = $this->service->close($recon['id'], 77);
        $this->assertTrue($result['ok']);

        $row = $this->db->table('bank_reconciliation')->where('id', $recon['id'])->get()->getRowArray();
        $this->assertSame('closed', $row['status']);
        $this->assertSame(77, (int) $row['closed_by']);
        $this->assertNotEmpty($row['closed_at']);

        $statusA = $this->db->table('bank_transaction')->where('id', $txnA)->get()->getRow('status');
        $this->assertSame('reconciled', $statusA);
    }

    public function testClosedReconciliationRejectsFurtherSaves(): void
    {
        $accountId = $this->insertAccount(301, 'Operating');
        $txnA = $this->insertTransaction($accountId, '2026-09-05', 100.00);

        $recon = $this->service->openReconciliation($accountId, '2026-09-30', 100.00);
        $this->service->saveCleared($recon['id'], [$txnA]);
        $this->service->close($recon['id'], 77);

        $result = $this->service->saveCleared($recon['id'], []);
        $this->assertFalse($result['ok']);
        $this->assertSame('not_open', $result['code']);
    }

    public function testReconciliationForParishRejectsCrossTenantAccess(): void
    {
        $this->seedParish(302);
        $accountId = $this->insertAccount(302, 'Other Parish');
        $recon = $this->service->openReconciliation($accountId, '2026-09-30', 100.00);

        // Session parish is 301 — a parish-302 reconciliation is unreachable.
        $this->assertNull($this->service->reconciliationForParish($recon['id'], 301));

        session()->set('selected_parish_id', 302);
        $this->assertNotNull($this->service->reconciliationForParish($recon['id'], 302));
    }

    public function testWorksheetTransactionsPopulatesLinkedJournalEntryDetails(): void
    {
        $accountId = $this->insertAccount(301, 'Operating');
        $txnId = $this->insertTransaction($accountId, '2026-09-15', 500.00);

        // Insert a journal entry
        $this->db->table('journal_entry')->insert([
            'parish_id'    => 301,
            'entry_number' => 'JE-2026-00042',
            'entry_date'   => '2026-09-15',
            'source_type'  => 'bank_transaction',
            'source_id'    => $txnId,
            'status'       => 'posted',
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);
        $journalId = (int) $this->db->insertID();

        // Link bank transaction to journal entry
        $this->db->table('bank_transaction_journal_link')->insert([
            'bank_transaction_id' => $txnId,
            'journal_entry_id'    => $journalId,
            'created_at'          => date('Y-m-d H:i:s'),
        ]);

        $recon = $this->service->openReconciliation($accountId, '2026-09-30', 500.00);
        $reconRow = $this->db->table('bank_reconciliation')->where('id', $recon['id'])->get()->getRowArray();

        $worksheet = $this->service->worksheetTransactions($reconRow);

        $this->assertCount(1, $worksheet);
        $this->assertSame($txnId, (int) $worksheet[0]['id']);
        $this->assertSame($journalId, (int) $worksheet[0]['journal_entry_id']);
        $this->assertSame('JE-2026-00042', $worksheet[0]['journal_entry_number']);
        $this->assertSame('posted', $worksheet[0]['journal_entry_status']);
    }

    private function seedParish(int $parishId): void
    {
        if ($this->db->table('parish')->where('id', $parishId)->countAllResults() > 0) {
            return;
        }

        $this->db->table('parish')->insert([
            'id'         => $parishId,
            'name'       => 'Test Parish ' . $parishId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function insertAccount(int $parishId, string $name): int
    {
        $this->db->table('bank_account')->insert([
            'parish_id'       => $parishId,
            'name'            => $name,
            'currency_code'   => 'USD',
            'opening_balance' => 0,
            'is_active'       => 1,
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertTransaction(int $accountId, string $date, float $amount): int
    {
        $this->db->table('bank_transaction')->insert([
            'bank_account_id'  => $accountId,
            'transaction_date' => $date,
            'amount'           => $amount,
            'status'           => 'unmatched',
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }
}
