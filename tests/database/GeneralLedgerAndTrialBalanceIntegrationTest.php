<?php

namespace Tests\Database;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Finance\Controllers\JournalEntries;
use Finance\Controllers\ReportExports;
use Finance\Controllers\Reports;
use Tests\Support\Database\CanonicalTestSchema;
use Tests\Support\Database\SystemSettingsTestHelper;

/**
 * Integration tests for Priority 4.1 & 4.2:
 * General Ledger Explorer UI (/journal-entries), detail view, CSV export,
 * and Trial Balance Report (/reports/trial-balance, CSV, PDF).
 *
 * @internal
 */
final class GeneralLedgerAndTrialBalanceIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    private int $treasurerUserId;

    protected function setUp(): void
    {
        parent::setUp();

        helper(['auth', 'form', 'url', 'finance']);
        Services::reset(true);

        CanonicalTestSchema::ensure($this->db);
        SystemSettingsTestHelper::createCoreAuthTables($this->db);
        SystemSettingsTestHelper::createSystemSettingsTableAndSeed($this->db);
        SystemSettingsTestHelper::createAuditLogTable($this->db);

        foreach ([
            'parish',
            'users',
            'auth_groups_users',
            'auth_permissions_users',
            'profile',
            'audit_log',
            'chart_of_accounts',
            'journal_entry',
            'journal_entry_line',
        ] as $table) {
            CanonicalTestSchema::reset($this->db, $table);
        }

        $this->seedParish(301, 'Parish 301 St. Jude');
        $this->seedParish(302, 'Parish 302 Foreign St. Luke');

        $this->treasurerUserId = $this->insertUser('treasurer.parish301', 'treasurer301@example.test');
        $this->insertProfile($this->treasurerUserId, 301, 'Treasurer', 'User');

        $this->injectFinanceAuth($this->treasurerUserId, [
            'finance.view',
            'finance.manage',
            'report.view',
        ]);

        session()->set('selected_parish_id', 301);
    }

    protected function tearDown(): void
    {
        Services::reset(true);
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Priority 4.1: General Ledger Journal Entries Tests
    // -----------------------------------------------------------------------

    public function testJournalEntriesIndexRendersEntriesAndSummaryKPIs(): void
    {
        $coaCash = $this->insertChartAccount('1010', 'Operating Cash', 'asset');
        $coaTithe = $this->insertChartAccount('4010', 'Tithes & Offerings', 'income');
        $coaSupplies = $this->insertChartAccount('5010', 'Office Supplies', 'expense');

        // Entry 1: Donation ($500 debit cash, $500 credit tithe)
        $entry1 = $this->insertJournalEntry(301, 'JE-2026-0001', '2026-03-01', 'donation', 'posted', 'Sunday Offering');
        $this->insertJournalLine($entry1, $coaCash, 500.0, 0.0);
        $this->insertJournalLine($entry1, $coaTithe, 0.0, 500.0);

        // Entry 2: Expense ($150 debit supplies, $150 credit cash)
        $entry2 = $this->insertJournalEntry(301, 'JE-2026-0002', '2026-03-05', 'expense', 'posted', 'Paper and Pens');
        $this->insertJournalLine($entry2, $coaSupplies, 150.0, 0.0);
        $this->insertJournalLine($entry2, $coaCash, 0.0, 150.0);

        // Foreign Entry: Parish 302 ($9999)
        $foreignEntry = $this->insertJournalEntry(302, 'JE-2026-9999', '2026-03-10', 'manual', 'posted', 'Foreign Parish Gift');
        $this->insertJournalLine($foreignEntry, $coaCash, 9999.0, 0.0);
        $this->insertJournalLine($foreignEntry, $coaTithe, 0.0, 9999.0);

        $controller = $this->makeJournalEntriesController('GET');
        $output = $controller->index();

        $this->assertIsString($output);

        // Entries in Parish 301 must appear
        $this->assertStringContainsString('JE-2026-0001', $output);
        $this->assertStringContainsString('Sunday Offering', $output);
        $this->assertStringContainsString('JE-2026-0002', $output);
        $this->assertStringContainsString('Paper and Pens', $output);

        // KPI Summary checks: 2 entries, $650.00 debits, $650.00 credits
        $this->assertStringContainsString('650.00', $output);
        $this->assertStringContainsString(lang('Finance.trialBalanceBalanced'), $output);

        // Foreign parish data must be isolated
        $this->assertStringNotContainsString('JE-2026-9999', $output);
        $this->assertStringNotContainsString('Foreign Parish Gift', $output);
        $this->assertStringNotContainsString('9,999.00', $output);
    }

    public function testJournalEntriesFiltersBySourceDateRangeAndSearch(): void
    {
        $coaCash = $this->insertChartAccount('1010', 'Operating Cash', 'asset');
        $coaTithe = $this->insertChartAccount('4010', 'Tithes', 'income');
        $coaUtility = $this->insertChartAccount('5020', 'Electric Utility', 'expense');

        $e1 = $this->insertJournalEntry(301, 'JE-2026-0001', '2026-03-01', 'donation', 'posted', 'Tithe Offering');
        $this->insertJournalLine($e1, $coaCash, 300.0, 0.0);
        $this->insertJournalLine($e1, $coaTithe, 0.0, 300.0);

        $e2 = $this->insertJournalEntry(301, 'JE-2026-0002', '2026-03-15', 'expense', 'posted', 'Electric Bill');
        $this->insertJournalLine($e2, $coaUtility, 120.0, 0.0);
        $this->insertJournalLine($e2, $coaCash, 0.0, 120.0);

        // Filter 1: source_type = donation
        $controller = $this->makeJournalEntriesController('GET', ['source_type' => 'donation']);
        $outDonation = $controller->index();
        $this->assertStringContainsString('JE-2026-0001', $outDonation);
        $this->assertStringNotContainsString('JE-2026-0002', $outDonation);

        // Filter 2: start_date = 2026-03-10
        $controller = $this->makeJournalEntriesController('GET', ['start_date' => '2026-03-10']);
        $outDate = $controller->index();
        $this->assertStringNotContainsString('JE-2026-0001', $outDate);
        $this->assertStringContainsString('JE-2026-0002', $outDate);

        // Filter 3: search keyword 'Electric'
        $controller = $this->makeJournalEntriesController('GET', ['search' => 'Electric']);
        $outSearch = $controller->index();
        $this->assertStringNotContainsString('JE-2026-0001', $outSearch);
        $this->assertStringContainsString('JE-2026-0002', $outSearch);

        // Filter 4: chart_account_id matching Electric Utility
        $controller = $this->makeJournalEntriesController('GET', ['chart_account_id' => $coaUtility]);
        $outAccount = $controller->index();
        $this->assertStringNotContainsString('JE-2026-0001', $outAccount);
        $this->assertStringContainsString('JE-2026-0002', $outAccount);
    }

    public function testJournalEntryViewFullPageAndLines(): void
    {
        $coaCash = $this->insertChartAccount('1010', 'Operating Cash', 'asset');
        $coaTithe = $this->insertChartAccount('4010', 'Tithes & Offerings', 'income');

        $entryId = $this->insertJournalEntry(301, 'JE-2026-0088', '2026-03-01', 'donation', 'posted', 'Sunday Tithes');
        $this->insertJournalLine($entryId, $coaCash, 850.0, 0.0, 'Cash received');
        $this->insertJournalLine($entryId, $coaTithe, 0.0, 850.0, 'General tithe income');

        $controller = $this->makeJournalEntriesController('GET');
        $output = $controller->view($entryId);

        $this->assertIsString($output);
        $this->assertStringContainsString('JE-2026-0088', $output);
        $this->assertStringContainsString('1010', $output);
        $this->assertStringContainsString('Operating Cash', $output);
        $this->assertStringContainsString('4010', $output);
        $this->assertStringContainsString('Tithes &amp; Offerings', $output);
        $this->assertStringContainsString('850.00', $output);
        $this->assertStringContainsString(lang('Finance.trialBalanceBalanced'), $output);
    }

    public function testJournalEntryViewJsonReturnsStandardApiContract(): void
    {
        $coaCash = $this->insertChartAccount('1010', 'Operating Cash', 'asset');
        $coaTithe = $this->insertChartAccount('4010', 'Tithes', 'income');

        $entryId = $this->insertJournalEntry(301, 'JE-2026-0099', '2026-03-02', 'manual', 'posted', 'Initial Fund Entry');
        $this->insertJournalLine($entryId, $coaCash, 1000.0, 0.0);
        $this->insertJournalLine($entryId, $coaTithe, 0.0, 1000.0);

        $controller = $this->makeJournalEntriesController('GET', ['format' => 'json']);
        $response = $controller->view($entryId);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertTrue($payload['success']);
        $this->assertSame('success', $payload['status']);
        $this->assertArrayHasKey('data', $payload);
        $this->assertSame('JE-2026-0099', $payload['data']['entry']['entry_number']);
        $this->assertCount(2, $payload['data']['entry']['lines']);
        $this->assertSame(1000.0, (float) $payload['data']['entry']['total_debit']);
        $this->assertSame(1000.0, (float) $payload['data']['entry']['total_credit']);
    }

    public function testJournalEntryCrossParishIsolation(): void
    {
        $foreignEntry = $this->insertJournalEntry(302, 'JE-2026-9999', '2026-03-10', 'manual', 'posted', 'Foreign');

        $controller = $this->makeJournalEntriesController('GET');
        $response = $controller->view($foreignEntry);

        // BaseController redirect or 404 response
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertTrue($response->getStatusCode() === 302 || $response->getStatusCode() === 404);
    }

    public function testJournalEntriesExportCsvGeneratesBalancedFile(): void
    {
        $coaCash = $this->insertChartAccount('1010', 'Cash', 'asset');
        $coaTithe = $this->insertChartAccount('4010', 'Tithe', 'income');

        $e1 = $this->insertJournalEntry(301, 'JE-2026-0101', '2026-03-01', 'donation', 'posted', 'March Week 1');
        $this->insertJournalLine($e1, $coaCash, 450.0, 0.0);
        $this->insertJournalLine($e1, $coaTithe, 0.0, 450.0);

        $controller = $this->makeJournalEntriesController('GET');
        $response = $controller->export();

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertStringContainsString('text/csv', (string) $response->getHeaderLine('Content-Type'));

        $csv = (string) $response->getBody();
        $this->assertStringContainsString('Entry Number', $csv);
        $this->assertStringContainsString('JE-2026-0101', $csv);
        $this->assertStringContainsString('March Week 1', $csv);
        $this->assertStringContainsString('450.00', $csv);
    }

    // -----------------------------------------------------------------------
    // Priority 4.2: Trial Balance Report Tests
    // -----------------------------------------------------------------------

    public function testTrialBalanceReportCalculatesAccurateBalances(): void
    {
        $coaCash = $this->insertChartAccount('1010', 'Operating Cash', 'asset');
        $coaTithe = $this->insertChartAccount('4010', 'Tithes & Offerings', 'income');
        $coaSupplies = $this->insertChartAccount('5010', 'Office Supplies', 'expense');

        // Donation: Debit Cash 500, Credit Tithe 500
        $e1 = $this->insertJournalEntry(301, 'JE-2026-0001', '2026-03-01', 'donation', 'posted', 'Donation');
        $this->insertJournalLine($e1, $coaCash, 500.0, 0.0);
        $this->insertJournalLine($e1, $coaTithe, 0.0, 500.0);

        // Expense: Debit Supplies 150, Credit Cash 150
        $e2 = $this->insertJournalEntry(301, 'JE-2026-0002', '2026-03-05', 'expense', 'posted', 'Supplies');
        $this->insertJournalLine($e2, $coaSupplies, 150.0, 0.0);
        $this->insertJournalLine($e2, $coaCash, 0.0, 150.0);

        // Cash net: Debit 350. Supplies net: Debit 150. Tithe net: Credit 500.
        // Total Debits = 500. Total Credits = 500. Balanced!
        $reportsController = $this->makeReportsController('GET', [
            'as_of_date' => '2026-03-31',
        ]);
        $output = $reportsController->trialBalance();

        $this->assertIsString($output);
        $this->assertStringContainsString('1010', $output);
        $this->assertStringContainsString('Operating Cash', $output);
        $this->assertStringContainsString('350.00', $output);

        $this->assertStringContainsString('4010', $output);
        $this->assertStringContainsString('500.00', $output);

        $this->assertStringContainsString('5010', $output);
        $this->assertStringContainsString('150.00', $output);

        $this->assertStringContainsString(lang('Finance.trialBalanceBalanced'), $output);
    }

    public function testTrialBalanceDetectsUnbalancedLedger(): void
    {
        $coaCash = $this->insertChartAccount('1010', 'Operating Cash', 'asset');
        $coaTithe = $this->insertChartAccount('4010', 'Tithes & Offerings', 'income');

        // Unbalanced entry: Debit 500, Credit 400 (Variance = 100)
        $e1 = $this->insertJournalEntry(301, 'JE-2026-ERR', '2026-03-01', 'manual', 'posted', 'Unbalanced');
        $this->insertJournalLine($e1, $coaCash, 500.0, 0.0);
        $this->insertJournalLine($e1, $coaTithe, 0.0, 400.0);

        $reportsController = $this->makeReportsController('GET', [
            'as_of_date' => '2026-03-31',
        ]);
        $output = $reportsController->trialBalance();

        $this->assertIsString($output);
        $this->assertStringContainsString(lang('Finance.trialBalanceUnbalanced'), $output);
        $this->assertStringContainsString('100.00', $output);
    }

    public function testTrialBalanceDateCutoffLogic(): void
    {
        $coaCash = $this->insertChartAccount('1010', 'Cash', 'asset');
        $coaTithe = $this->insertChartAccount('4010', 'Tithe', 'income');

        // March entry
        $e1 = $this->insertJournalEntry(301, 'JE-2026-0001', '2026-03-15', 'donation', 'posted', 'March');
        $this->insertJournalLine($e1, $coaCash, 300.0, 0.0);
        $this->insertJournalLine($e1, $coaTithe, 0.0, 300.0);

        // April entry (future relative to March cutoff)
        $e2 = $this->insertJournalEntry(301, 'JE-2026-0002', '2026-04-10', 'donation', 'posted', 'April');
        $this->insertJournalLine($e2, $coaCash, 700.0, 0.0);
        $this->insertJournalLine($e2, $coaTithe, 0.0, 700.0);

        // Cutoff as of 2026-03-31 -> April entry must be excluded
        $reportsController = $this->makeReportsController('GET', [
            'as_of_date' => '2026-03-31',
        ]);
        $output = $reportsController->trialBalance();

        $this->assertIsString($output);
        $this->assertStringContainsString('300.00', $output);
        $this->assertStringNotContainsString('1,000.00', $output);
    }

    public function testTrialBalanceZeroBalanceToggle(): void
    {
        $coaActive = $this->insertChartAccount('1010', 'Active Cash', 'asset');
        $coaZero = $this->insertChartAccount('2010', 'Unused Liability', 'liability');

        $e = $this->insertJournalEntry(301, 'JE-2026-0001', '2026-03-01', 'manual', 'posted', 'Active');
        $this->insertJournalLine($e, $coaActive, 200.0, 0.0);
        $this->insertJournalLine($e, $coaActive, 0.0, 200.0);

        // include_zero = 0 (default)
        $cOmit = $this->makeReportsController('GET', ['include_zero' => '0']);
        $outOmit = $cOmit->trialBalance();
        $this->assertStringNotContainsString('2010', $outOmit);
        $this->assertStringNotContainsString('Unused Liability', $outOmit);

        // include_zero = 1
        $cInclude = $this->makeReportsController('GET', ['include_zero' => '1']);
        $outInclude = $cInclude->trialBalance();
        $this->assertStringContainsString('2010', $outInclude);
        $this->assertStringContainsString('Unused Liability', $outInclude);
    }

    public function testTrialBalanceExportCsvAndPdf(): void
    {
        $coaCash = $this->insertChartAccount('1010', 'Operating Cash', 'asset');
        $coaTithe = $this->insertChartAccount('4010', 'Tithes', 'income');

        $e = $this->insertJournalEntry(301, 'JE-2026-0001', '2026-03-01', 'donation', 'posted', 'Test');
        $this->insertJournalLine($e, $coaCash, 600.0, 0.0);
        $this->insertJournalLine($e, $coaTithe, 0.0, 600.0);

        $exportsController = $this->makeReportExportsController('GET', [
            'as_of_date' => '2026-03-31',
        ]);

        // CSV Export
        $csvResponse = $exportsController->trialBalanceCsv();
        $this->assertInstanceOf(ResponseInterface::class, $csvResponse);
        $this->assertStringContainsString('text/csv', (string) $csvResponse->getHeaderLine('Content-Type'));
        $csvBody = (string) $csvResponse->getBody();
        $this->assertStringContainsString('Account Code', $csvBody);
        $this->assertStringContainsString('Account Name', $csvBody);
        $this->assertStringContainsString('1010', $csvBody);
        $this->assertStringContainsString('Operating Cash', $csvBody);
        $this->assertStringContainsString('600.00', $csvBody);
        $this->assertStringContainsString('Balanced', $csvBody);

        // PDF Export
        $pdfResponse = $exportsController->trialBalancePdf();
        $this->assertInstanceOf(ResponseInterface::class, $pdfResponse);
        $this->assertStringContainsString('application/pdf', (string) $pdfResponse->getHeaderLine('Content-Type'));
        $pdfBody = (string) $pdfResponse->getBody();
        $this->assertStringStartsWith('%PDF', $pdfBody);
    }

    public function testTrialBalanceExcludesForeignParishData(): void
    {
        $coaCash = $this->insertChartAccount('1010', 'Cash', 'asset');
        $coaTithe = $this->insertChartAccount('4010', 'Tithe', 'income');

        // Parish 301 entry: $100
        $e1 = $this->insertJournalEntry(301, 'JE-2026-0001', '2026-03-01', 'donation', 'posted', 'Local');
        $this->insertJournalLine($e1, $coaCash, 100.0, 0.0);
        $this->insertJournalLine($e1, $coaTithe, 0.0, 100.0);

        // Foreign Parish 302 entry: $8888
        $e2 = $this->insertJournalEntry(302, 'JE-2026-9999', '2026-03-01', 'donation', 'posted', 'Foreign');
        $this->insertJournalLine($e2, $coaCash, 8888.0, 0.0);
        $this->insertJournalLine($e2, $coaTithe, 0.0, 8888.0);

        $reportsController = $this->makeReportsController('GET', [
            'as_of_date' => '2026-03-31',
        ]);
        $output = $reportsController->trialBalance();

        $this->assertIsString($output);
        $this->assertStringContainsString('100.00', $output);
        $this->assertStringNotContainsString('8,888.00', $output);
        $this->assertStringNotContainsString('8888.00', $output);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * @param array<string, mixed> $get
     * @param array<string, mixed> $post
     */
    private function makeJournalEntriesController(string $method = 'GET', array $get = [], array $post = []): JournalEntries
    {
        $request = Services::request();
        $request->setMethod($method);
        $request->setGlobal('get', $get);
        $request->setGlobal('post', $post);
        $request->setGlobal('request', $method === 'POST' ? $post : $get);

        $controller = new JournalEntries();
        $controller->initController($request, service('response'), service('logger'));

        return $controller;
    }

    /**
     * @param array<string, mixed> $get
     */
    private function makeReportsController(string $method = 'GET', array $get = []): Reports
    {
        $request = Services::request();
        $request->setMethod($method);
        $request->setGlobal('get', $get);
        $request->setGlobal('post', []);
        $request->setGlobal('request', $get);

        $controller = new Reports();
        $controller->initController($request, service('response'), service('logger'));

        return $controller;
    }

    /**
     * @param array<string, mixed> $get
     */
    private function makeReportExportsController(string $method = 'GET', array $get = []): ReportExports
    {
        $request = Services::request();
        $request->setMethod($method);
        $request->setGlobal('get', $get);
        $request->setGlobal('post', []);
        $request->setGlobal('request', $get);

        $controller = new ReportExports();
        $controller->initController($request, service('response'), service('logger'));

        return $controller;
    }

    private function seedParish(int $id, string $name): void
    {
        $this->db->table('parish')->insert([
            'id'         => $id,
            'name'       => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function insertUser(string $username, string $email): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('users')->insert([
            'username'   => $username,
            'email'      => $email,
            'active'     => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->db->insertID();
    }

    private function insertProfile(int $userId, int $parishId, string $first = 'User', string $last = 'Name'): void
    {
        $this->db->table('profile')->insert([
            'user_id'    => $userId,
            'parish_id'  => $parishId,
            'first_name' => $first,
            'last_name'  => $last,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function insertChartAccount(string $code, string $name, string $type, ?string $category = null): int
    {
        $this->db->table('chart_of_accounts')->insert([
            'code'         => $code,
            'name'         => $name,
            'account_type' => $type,
            'category'     => $category,
            'is_active'    => 1,
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertJournalEntry(
        int $parishId,
        string $entryNumber,
        string $entryDate,
        string $sourceType = 'manual',
        string $status = 'posted',
        ?string $memo = null
    ): int {
        $now = date('Y-m-d H:i:s');
        $this->db->table('journal_entry')->insert([
            'parish_id'    => $parishId,
            'entry_number' => $entryNumber,
            'entry_date'   => $entryDate,
            'source_type'  => $sourceType,
            'status'       => $status,
            'memo'         => $memo,
            'posted_at'    => $status === 'posted' ? $now : null,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        return (int) $this->db->insertID();
    }

    private function insertJournalLine(
        int $journalEntryId,
        int $chartAccountId,
        float $debit,
        float $credit,
        ?string $memo = null
    ): int {
        $now = date('Y-m-d H:i:s');
        $this->db->table('journal_entry_line')->insert([
            'journal_entry_id' => $journalEntryId,
            'chart_account_id' => $chartAccountId,
            'debit_amount'     => $debit,
            'credit_amount'    => $credit,
            'memo'             => $memo,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        return (int) $this->db->insertID();
    }

    /**
     * @param list<string> $permissions
     */
    private function injectFinanceAuth(int $userId, array $permissions): void
    {
        $fakeUser = new class ($userId, $permissions) extends User {
            /**
             * @param list<string> $perms
             */
            public function __construct(private int $userIdForTest, private array $perms)
            {
                $this->attributes['id']       = $userIdForTest;
                $this->attributes['username'] = 'finance.' . $userIdForTest;
                $this->attributes['email']    = 'finance.' . $userIdForTest . '@example.test';
            }

            public function can(string ...$permissions): bool
            {
                foreach ($permissions as $permission) {
                    if (in_array($permission, $this->perms, true)) {
                        return true;
                    }
                }

                return false;
            }

            public function inGroup(string ...$groups): bool
            {
                return in_array('finance', $groups, true) || in_array('parishadmin', $groups, true);
            }
        };

        $auth = new class ($fakeUser, $userId) extends Auth {
            public function __construct(private User $fakeUser, private int $userIdForTest)
            {
            }

            public function setAuthenticator(?string $alias = null): self
            {
                return $this;
            }

            public function loggedIn(): bool
            {
                return true;
            }

            public function id(): int
            {
                return $this->userIdForTest;
            }

            public function user(): User
            {
                return $this->fakeUser;
            }
        };

        Services::injectMock('auth', $auth);
    }
}
