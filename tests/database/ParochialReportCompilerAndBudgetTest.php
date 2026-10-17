<?php

namespace Tests\Database;

use App\Models\ParishBudgetModel;
use App\Services\FinanceReportsDataService;
use App\Services\ParochialReportDataService;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Finance\Controllers\Budgets;
use Finance\Controllers\Reports;
use Finance\Controllers\ReportExports;
use Tests\Support\Database\CanonicalTestSchema;
use Tests\Support\Database\SystemSettingsTestHelper;

/**
 * Priority 5.3 & 5.4 Integration Test:
 * - Episcopal Parochial Report Finance Lines Compiler
 * - ParochialReportDataService financial metric delegation
 * - Budget Cloning (Roll-Forward with adjustment factor)
 * - Budget Vestry PDF and CSV Exports
 * - Parochial Report CSV and PDF Exports
 *
 * @internal
 */
final class ParochialReportCompilerAndBudgetTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        helper(['auth', 'form', 'url']);
        Services::reset(true);

        CanonicalTestSchema::ensure($this->db);
        SystemSettingsTestHelper::createCoreAuthTables($this->db);
        SystemSettingsTestHelper::createSystemSettingsTableAndSeed($this->db);
        SystemSettingsTestHelper::createAuditLogTable($this->db);

        foreach ([
            'journal_entry_line',
            'journal_entry',
            'chart_of_accounts',
            'bank_transaction',
            'bank_account',
            'parish_budget',
            'parish_affiliation',
            'parish',
            'users',
            'profile',
            'audit_log',
        ] as $table) {
            CanonicalTestSchema::reset($this->db, $table);
        }

        $this->seedParish(501, 'St. Jude Parish');
        $this->seedParish(502, 'Trinity Parish');

        $this->userId = $this->insertUser('treasurer.user', 'treasurer@stjude.org');
        $this->insertProfile($this->userId, 501, 'Parish', 'Treasurer');
        $this->db->table('parish_affiliation')->insert([
            'parish_id'         => 501,
            'linked_user_id'    => $this->userId,
            'membership_status' => 'member',
            'is_primary'        => 1,
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);
        $this->injectReportAuth($this->userId, ['report.view', 'finance.view', 'finance.manage']);

        session()->set('selected_parish_id', 501);

        $this->seedChartOfAccounts();
    }

    protected function tearDown(): void
    {
        Services::reset(true);
        parent::tearDown();
    }

    public function testBuildParochialReportFinanceLinesComputesCanonicalLinesCorrectly(): void
    {
        $year = (int) date('Y');

        // Post Journal Entries for Parish 501
        $this->postJournalEntry(501, $year . '-03-15', 'Pledges', [
            ['chart_account_id' => $this->getChartAccountId('1110'), 'debit' => 10000.0, 'credit' => 0.0],     // Cash Operating
            ['chart_account_id' => $this->getChartAccountId('4110'), 'debit' => 0.0, 'credit' => 10000.0],     // Pledge Offerings (Line 3)
        ]);

        $this->postJournalEntry(501, $year . '-05-20', 'Restricted Building', [
            ['chart_account_id' => $this->getChartAccountId('1110'), 'debit' => 2500.0, 'credit' => 0.0],      // Cash
            ['chart_account_id' => $this->getChartAccountId('5110'), 'debit' => 0.0, 'credit' => 2500.0],      // Restricted Building Donations (Line 4)
        ]);

        $this->postJournalEntry(501, $year . '-06-10', 'Hall Rental', [
            ['chart_account_id' => $this->getChartAccountId('1110'), 'debit' => 1200.0, 'credit' => 0.0],      // Cash
            ['chart_account_id' => $this->getChartAccountId('4210'), 'debit' => 0.0, 'credit' => 1200.0],      // Rental Income (Line 5)
        ]);

        $this->postJournalEntry(501, $year . '-07-01', 'Stipend', [
            ['chart_account_id' => $this->getChartAccountId('6110'), 'debit' => 4000.0, 'credit' => 0.0],      // Clergy Stipend (Line 6 Non-capital)
            ['chart_account_id' => $this->getChartAccountId('1110'), 'debit' => 0.0, 'credit' => 4000.0],      // Cash
        ]);

        $this->postJournalEntry(501, $year . '-08-15', 'Capital Roof Repair', [
            ['chart_account_id' => $this->getChartAccountId('8610'), 'debit' => 3000.0, 'credit' => 0.0],      // Major Roof Repairs (Line 7 Capital)
            ['chart_account_id' => $this->getChartAccountId('1110'), 'debit' => 0.0, 'credit' => 3000.0],      // Cash
        ]);

        $reportService = new FinanceReportsDataService();
        $lines = $reportService->buildParochialReportFinanceLines(501, $year);

        // Line 3 Plate & Pledge = 10,000
        $this->assertSame(10000.0, (float) $lines['line_3_plate_pledge']);
        $this->assertSame(10000.0, (float) $lines['revenue_unrestricted']);

        // Line 4 Restricted = 2,500
        $this->assertSame(2500.0, (float) $lines['line_4_restricted']);
        $this->assertSame(2500.0, (float) $lines['revenue_restricted']);

        // Line 5 Other Revenue = 1,200
        $this->assertSame(1200.0, (float) $lines['line_5_other_revenue']);

        // Total Line A Revenue = 13,700
        $this->assertSame(13700.0, (float) $lines['total_revenue']);

        // Line 6 Non-capital = 4,000
        $this->assertSame(4000.0, (float) $lines['line_6_noncapital']);
        $this->assertSame(4000.0, (float) $lines['expenses_noncapital']);

        // Line 7 Capital = 3,000
        $this->assertSame(3000.0, (float) $lines['line_7_capital']);
        $this->assertSame(3000.0, (float) $lines['expenses_capital']);

        // Total Line B Expenses = 7,000
        $this->assertSame(7000.0, (float) $lines['total_expenses']);

        // Net Operating = 13,700 - 7,000 = 6,700
        $this->assertSame(6700.0, (float) $lines['net_operating']);

        // Line 8 Cash = 10,000 + 2,500 + 1,200 - 4,000 - 3,000 = 6,700
        $this->assertSame(6700.0, (float) $lines['cash_balance']);
        $this->assertSame(6700.0, (float) $lines['line_8_cash']);

        // Line 12 Rental Income = 1,200, rents_to_outside = 1
        $this->assertSame(1, (int) $lines['rents_to_outside']);
        $this->assertSame(1200.0, (float) $lines['rental_income']);

        // ParochialReportDataService::getFinancialMetrics delegation test
        $parochialReportService = new ParochialReportDataService();
        $metrics = $parochialReportService->getFinancialMetrics($this->db, 501, $year);

        $this->assertSame(10000.0, (float) $metrics['revenue_unrestricted']);
        $this->assertSame(2500.0, (float) $metrics['revenue_restricted']);
        $this->assertSame(4000.0, (float) $metrics['expenses_noncapital']);
        $this->assertSame(3000.0, (float) $metrics['expenses_capital']);
        $this->assertSame(6700.0, (float) $metrics['cash_balance']);
    }

    public function testBankCashFallbackAggregatesTransactionsForAllAccounts(): void
    {
        $year = (int) date('Y');
        $activeAccounts = [];
        foreach ([
            ['Operating', 1000.0, 1],
            ['Savings', 2000.0, 1],
            ['Inactive', 5000.0, 0],
        ] as [$name, $openingBalance, $isActive]) {
            $this->db->table('bank_account')->insert([
                'parish_id' => 501,
                'name' => $name,
                'opening_balance' => $openingBalance,
                'is_active' => $isActive,
            ]);
            if ($isActive === 1) {
                $activeAccounts[] = (int) $this->db->insertID();
            }
        }

        foreach ([
            [$activeAccounts[0], sprintf('%04d-06-01', $year), 250.0],
            [$activeAccounts[0], sprintf('%04d-06-15', $year), -50.0],
            [$activeAccounts[0], sprintf('%04d-12-31', $year + 1), 900.0],
            [$activeAccounts[1], sprintf('%04d-07-01', $year), 100.0],
        ] as [$accountId, $date, $amount]) {
            $this->db->table('bank_transaction')->insert([
                'bank_account_id' => $accountId,
                'transaction_date' => $date,
                'amount' => $amount,
            ]);
        }

        $lines = (new FinanceReportsDataService())->buildParochialReportFinanceLines(501, $year);

        $this->assertSame(3300.0, (float) $lines['cash_balance']);
    }

    public function testLedgerBalanceAggregationIncludesParishLiabilities(): void
    {
        $year = (int) date('Y');
        $this->postJournalEntry(501, sprintf('%04d-02-01', $year), 'Short-term borrowing', [
            ['chart_account_id' => $this->getChartAccountId('1110'), 'debit' => 300.0, 'credit' => 0.0],
            ['chart_account_id' => $this->getChartAccountId('2110'), 'debit' => 0.0, 'credit' => 300.0],
        ]);

        $lines = (new FinanceReportsDataService())->buildParochialReportFinanceLines(501, $year);

        $this->assertSame(300.0, (float) $lines['cash_balance']);
        $this->assertSame(300.0, (float) $lines['debt_amount']);
        $this->assertSame(1, (int) $lines['has_debt']);
    }

    public function testBudgetCloningRollForwardWithAdjustment(): void
    {
        $budgetModel = new ParishBudgetModel();

        // Seed 2025 budget lines for Parish 501
        $coa4110 = $this->getChartAccountId('4110');
        $coa6110 = $this->getChartAccountId('6110');

        $budgetModel->insert([
            'parish_id'        => 501,
            'chart_account_id' => $coa4110,
            'fiscal_year'      => 2025,
            'budget_amount'    => 50000.00,
            'created_by'       => $this->userId,
        ]);
        $budgetModel->insert([
            'parish_id'        => 501,
            'chart_account_id' => $coa6110,
            'fiscal_year'      => 2025,
            'budget_amount'    => 40000.00,
            'created_by'       => $this->userId,
        ]);

        // Clone from 2025 to 2026 with +5% inflation adjustment
        $result = $budgetModel->cloneBudget(501, 2025, 2026, 'budget', 5.0, false, $this->userId);

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['copied_count']);

        // Verify cloned 2026 amounts
        $cloned2026 = $budgetModel->where('parish_id', 501)->where('fiscal_year', 2026)->findAll();
        $this->assertCount(2, $cloned2026);

        $amountsByAccId = [];
        foreach ($cloned2026 as $row) {
            $amountsByAccId[(int) $row['chart_account_id']] = (float) $row['budget_amount'];
        }

        // 50,000 * 1.05 = 52,500
        $this->assertEquals(52500.00, $amountsByAccId[$coa4110]);
        // 40,000 * 1.05 = 42,000
        $this->assertEquals(42000.00, $amountsByAccId[$coa6110]);

        // Try to clone again without overwrite -> should fail
        $secondResult = $budgetModel->cloneBudget(501, 2025, 2026, 'budget', 0.0, false, $this->userId);
        $this->assertFalse($secondResult['success']);
        $this->assertSame('target_has_existing', $secondResult['error']);

        // Clone with overwrite = true -> should succeed
        $overwriteResult = $budgetModel->cloneBudget(501, 2025, 2026, 'budget', 10.0, true, $this->userId);
        $this->assertTrue($overwriteResult['success']);

        $updated2026 = $budgetModel->where('parish_id', 501)->where('fiscal_year', 2026)->findAll();
        $newAmounts = [];
        foreach ($updated2026 as $row) {
            $newAmounts[(int) $row['chart_account_id']] = (float) $row['budget_amount'];
        }
        // 50,000 * 1.10 = 55,000
        $this->assertEquals(55000.00, $newAmounts[$coa4110]);
    }

    public function testReportsParochialReportViewAndExports(): void
    {
        $year = (int) date('Y');

        // Test Controller View
        $controller = $this->makeReportsController(['year' => (string) $year]);

        $response = $controller->parochialReport();
        $this->assertIsString($response);
        $this->assertStringContainsString('Line 3', $response);
        $this->assertStringContainsString('Line A', $response);
        $this->assertStringContainsString('Line B', $response);

        // Test CSV Export
        $exportsController = $this->makeReportExportsController(['year' => (string) $year]);

        $csvResponse = $exportsController->parochialReportCsv();
        $this->assertSame(200, $csvResponse->getStatusCode());
        $this->assertStringContainsString('text/csv', $csvResponse->getHeaderLine('Content-Type'));
        $body = (string) $csvResponse->getBody();
        $this->assertStringContainsString('Line 1', $body);
        $this->assertStringContainsString('Line 3', $body);
        $this->assertStringContainsString('Line A', $body);

        // Test PDF Export
        $pdfResponse = $exportsController->parochialReportPdf();
        $this->assertSame(200, $pdfResponse->getStatusCode());
        $this->assertStringContainsString('application/pdf', $pdfResponse->getHeaderLine('Content-Type'));
        $pdfBody = (string) $pdfResponse->getBody();
        $this->assertStringStartsWith('%PDF', $pdfBody);
    }

    public function testBudgetsExportsAndCloneEndpoint(): void
    {
        $budgetModel = new ParishBudgetModel();
        $coa4110 = $this->getChartAccountId('4110');
        $budgetModel->insert([
            'parish_id'        => 501,
            'chart_account_id' => $coa4110,
            'fiscal_year'      => 2025,
            'budget_amount'    => 25000.00,
            'created_by'       => $this->userId,
        ]);

        $controller = $this->makeBudgetsController('GET', ['year' => '2025']);

        // Test Budgets CSV Export
        $csvResponse = $controller->exportCsv();
        $this->assertSame(200, $csvResponse->getStatusCode());
        $this->assertStringContainsString('text/csv', $csvResponse->getHeaderLine('Content-Type'));
        $body = (string) $csvResponse->getBody();
        $this->assertStringContainsString('4110', $body);
        $this->assertStringContainsString('25000.00', $body);

        // Test Budgets PDF Export
        $pdfResponse = $controller->exportPdf();
        $this->assertSame(200, $pdfResponse->getStatusCode());
        $this->assertStringContainsString('application/pdf', $pdfResponse->getHeaderLine('Content-Type'));
        $pdfBody = (string) $pdfResponse->getBody();
        $this->assertStringStartsWith('%PDF', $pdfBody);

        // Test Clone POST Endpoint
        $postController = $this->makeBudgetsController('POST', [], [
            'source_year'        => '2025',
            'target_year'        => '2026',
            'source_basis'       => 'budget',
            'adjustment_percent' => '5.0',
            'overwrite'          => '1',
        ]);
        $cloneResponse = $postController->clone();
        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $cloneResponse);
        $this->assertStringContainsString('/budgets?year=2026', $cloneResponse->getHeaderLine('Location'));
        $this->assertNull(session('error'));
        $this->assertNotNull(session('success'));

        // Verify Cloned Budget in Target Year
        $cloned = $budgetModel->where('parish_id', 501)->where('fiscal_year', 2026)->findAll();
        $this->assertCount(1, $cloned);
        $this->assertEquals(26250.00, (float) $cloned[0]['budget_amount']); // 25,000 * 1.05
    }

    private function makeReportsController(array $get = []): Reports
    {
        $_GET     = $get;
        $_POST    = [];
        $_REQUEST = $get;

        service('superglobals')->setGetArray($get);
        service('superglobals')->setPostArray([]);
        service('superglobals')->setRequestArray($get);

        $request = service('request');
        $request->setMethod('GET');
        $request->setGlobal('get', $get);
        $request->setGlobal('post', []);
        $request->setGlobal('request', $get);

        $controller = new Reports();
        $controller->initController($request, service('response'), service('logger'));

        return $controller;
    }

    private function makeReportExportsController(array $get = []): ReportExports
    {
        $_GET     = $get;
        $_POST    = [];
        $_REQUEST = $get;

        service('superglobals')->setGetArray($get);
        service('superglobals')->setPostArray([]);
        service('superglobals')->setRequestArray($get);

        $request = service('request');
        $request->setMethod('GET');
        $request->setGlobal('get', $get);
        $request->setGlobal('post', []);
        $request->setGlobal('request', $get);

        $controller = new ReportExports();
        $controller->initController($request, service('response'), service('logger'));

        return $controller;
    }

    private function makeBudgetsController(string $method = 'GET', array $get = [], array $post = []): Budgets
    {
        $_GET     = $get;
        $_POST    = $post;
        $_REQUEST = array_merge($get, $post);

        service('superglobals')->setGetArray($get);
        service('superglobals')->setPostArray($post);
        service('superglobals')->setRequestArray(array_merge($get, $post));

        $request = service('request');
        $request->setMethod($method);
        $request->setGlobal('get', $get);
        $request->setGlobal('post', $post);
        $request->setGlobal('request', array_merge($get, $post));

        $controller = new Budgets();
        $controller->initController($request, service('response'), service('logger'));

        return $controller;
    }

    private function postJournalEntry(int $parishId, string $date, string $memo, array $lines): int
    {
        $now = date('Y-m-d H:i:s');
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        foreach ($lines as $line) {
            $totalDebit += (float) ($line['debit'] ?? 0.0);
            $totalCredit += (float) ($line['credit'] ?? 0.0);
        }

        $this->db->table('journal_entry')->insert([
            'parish_id'     => $parishId,
            'entry_number'  => 'JE-' . date('Ymd-His') . '-' . mt_rand(100, 999),
            'entry_date'    => $date,
            'source_type'   => 'manual',
            'status'        => 'posted',
            'memo'          => $memo,
            'posted_by'     => $this->userId,
            'posted_at'     => $now,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
        $entryId = (int) $this->db->insertID();

        foreach ($lines as $line) {
            $this->db->table('journal_entry_line')->insert([
                'journal_entry_id' => $entryId,
                'chart_account_id' => $line['chart_account_id'],
                'debit_amount'     => $line['debit'] ?? 0.0,
                'credit_amount'    => $line['credit'] ?? 0.0,
                'created_at'       => $now,
            ]);
        }

        return $entryId;
    }

    private function getChartAccountId(string $code): int
    {
        $row = $this->db->table('chart_of_accounts')->where('code', $code)->get()->getRowArray();
        return (int) ($row['id'] ?? 0);
    }

    private function seedChartOfAccounts(): void
    {
        $accounts = [
            ['id' => 1, 'code' => '1110', 'name' => 'Operating Checking', 'account_type' => 'asset', 'category' => 'current_asset', 'is_active' => 1, 'allow_budget' => 0],
            ['id' => 2, 'code' => '1410', 'name' => 'Church Building', 'account_type' => 'asset', 'category' => 'fixed_asset', 'is_active' => 1, 'allow_budget' => 0],
            ['id' => 3, 'code' => '2110', 'name' => 'Accounts Payable', 'account_type' => 'liability', 'category' => 'current_liability', 'is_active' => 1, 'allow_budget' => 0],
            ['id' => 4, 'code' => '3110', 'name' => 'General Operating Fund', 'account_type' => 'equity', 'category' => 'net_assets', 'is_active' => 1, 'allow_budget' => 0],
            ['id' => 5, 'code' => '4110', 'name' => 'Pledge Offerings', 'account_type' => 'income', 'category' => 'revenue', 'is_active' => 1, 'allow_budget' => 1],
            ['id' => 6, 'code' => '4210', 'name' => 'Parish Hall Rental', 'account_type' => 'income', 'category' => 'revenue', 'is_active' => 1, 'allow_budget' => 1],
            ['id' => 7, 'code' => '5110', 'name' => 'Building Fund Donations', 'account_type' => 'income', 'category' => 'revenue', 'is_active' => 1, 'allow_budget' => 1],
            ['id' => 8, 'code' => '6110', 'name' => 'Clergy Stipend', 'account_type' => 'expense', 'category' => 'operations', 'is_active' => 1, 'allow_budget' => 1],
            ['id' => 9, 'code' => '8610', 'name' => 'Major Capital Repairs', 'account_type' => 'expense', 'category' => 'operations', 'is_active' => 1, 'allow_budget' => 1],
        ];

        foreach ($accounts as $acc) {
            $this->db->table('chart_of_accounts')->insert($acc);
        }
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

    private function insertProfile(int $userId, int $parishId, string $firstName, string $lastName): void
    {
        $this->db->table('profile')->insert([
            'user_id'    => $userId,
            'parish_id'  => $parishId,
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function injectReportAuth(int $userId, array $permissions): void
    {
        $fakeUser = new class ($userId, $permissions) extends User {
            public function __construct(private int $userIdForTest, private array $perms)
            {
                $this->attributes['id']       = $userIdForTest;
                $this->attributes['username'] = 'treasurer.' . $userIdForTest;
                $this->attributes['email']    = 'treasurer.' . $userIdForTest . '@example.com';
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
                return in_array('finance', $groups, true);
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
