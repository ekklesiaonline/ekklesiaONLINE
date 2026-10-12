<?php

namespace Tests\Database;

use App\Models\JournalEntryModel;
use App\Models\JournalEntryLineModel;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Finance\Controllers\Reports;
use Finance\Controllers\ReportExports;
use Tests\Support\Database\CanonicalTestSchema;
use Tests\Support\Database\SystemSettingsTestHelper;

/**
 * Priority 5.1 & 5.2 Financial Statements Integration Test:
 * - Statement of Financial Position (Balance Sheet) (/reports/balance-sheet)
 * - Statement of Activities (/reports/statement-of-activities)
 * - CSV and PDF export endpoints
 * - Multi-tenant parish boundary isolation
 *
 * @internal
 */
final class FinancialStatementsIntegrationTest extends CIUnitTestCase
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
        $this->injectReportAuth($this->userId, ['report.view', 'finance.view']);

        session()->set('selected_parish_id', 501);

        $this->seedChartOfAccounts();
    }

    protected function tearDown(): void
    {
        Services::reset(true);
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

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

    private function seedChartOfAccounts(): void
    {
        $now = date('Y-m-d H:i:s');
        $accounts = [
            ['id' => 1, 'code' => '1120', 'name' => 'Cash - Checking', 'account_type' => 'asset', 'category' => 'current_asset'],
            ['id' => 2, 'code' => '1420', 'name' => 'Buildings & Property', 'account_type' => 'asset', 'category' => 'fixed_asset'],
            ['id' => 3, 'code' => '2110', 'name' => 'Accounts Payable', 'account_type' => 'liability', 'category' => 'current_liability'],
            ['id' => 4, 'code' => '3100', 'name' => 'Net Assets - Unrestricted', 'account_type' => 'equity', 'category' => 'net_assets'],
            ['id' => 5, 'code' => '4120', 'name' => 'Pledge & Tithe Revenue', 'account_type' => 'income', 'category' => 'unrestricted_income'],
            ['id' => 6, 'code' => '5110', 'name' => 'Special Offerings - Flowers', 'account_type' => 'income', 'category' => 'restricted_income'],
            ['id' => 7, 'code' => '6110', 'name' => 'Diocesan Assessment', 'account_type' => 'expense', 'category' => 'outreach'],
            ['id' => 8, 'code' => '7110', 'name' => 'Clergy Stipends', 'account_type' => 'expense', 'category' => 'ministry'],
            ['id' => 9, 'code' => '8110', 'name' => 'Utilities & Heating', 'account_type' => 'expense', 'category' => 'operations'],
        ];

        foreach ($accounts as $acc) {
            $this->db->table('chart_of_accounts')->insert(array_merge($acc, [
                'is_active'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    private function makeReportsController(string $method = 'GET', array $get = []): Reports
    {
        $request = service('request');
        $request->setMethod($method);
        $request->setGlobal('get', $get);
        $request->setGlobal('post', []);
        $request->setGlobal('request', $get);

        $controller = new Reports();
        $controller->initController($request, service('response'), service('logger'));

        return $controller;
    }

    private function makeReportExportsController(string $method = 'GET', array $get = []): ReportExports
    {
        $request = service('request');
        $request->setMethod($method);
        $request->setGlobal('get', $get);
        $request->setGlobal('post', []);
        $request->setGlobal('request', $get);

        $controller = new ReportExports();
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
                'memo'             => $line['memo'] ?? null,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
        }

        return $entryId;
    }

    // -------------------------------------------------------------------------
    // Priority 5.1: Statement of Financial Position Tests
    // -------------------------------------------------------------------------

    public function testBalanceSheetRendersAndCalculatesEquation(): void
    {
        // 1. Record donations: Checking Debit $10,000, Pledge Income Credit $10,000
        $this->postJournalEntry(501, '2026-03-01', 'Pledges received', [
            ['chart_account_id' => 1, 'debit' => 10000.00, 'credit' => 0.00], // Cash - Checking
            ['chart_account_id' => 5, 'debit' => 0.00, 'credit' => 10000.00], // Pledge Revenue
        ]);

        // 2. Record expense: Clergy Stipend Debit $2,500, Cash Credit $2,500
        $this->postJournalEntry(501, '2026-03-10', 'Clergy stipend paid', [
            ['chart_account_id' => 8, 'debit' => 2500.00, 'credit' => 0.00],  // Clergy Stipend
            ['chart_account_id' => 1, 'debit' => 0.00, 'credit' => 2500.00],  // Cash - Checking
        ]);

        // 3. Record building acquisition on credit: Fixed Asset Debit $50,000, AP Credit $50,000
        $this->postJournalEntry(501, '2026-03-15', 'Building renovation invoice', [
            ['chart_account_id' => 2, 'debit' => 50000.00, 'credit' => 0.00], // Building
            ['chart_account_id' => 3, 'debit' => 0.00, 'credit' => 50000.00], // Accounts Payable
        ]);

        $controller = $this->makeReportsController('GET', ['as_of_date' => '2026-03-31']);
        $response   = $controller->balanceSheet();

        $this->assertIsString($response);
        $this->assertStringContainsString('Statement of Financial Position', $response);
        $this->assertStringContainsString('2026-03-31', $response);

        // Assets verification: Cash = $7,500, Building = $50,000 => Total Assets = $57,500
        $this->assertStringContainsString('57,500.00', $response);
        $this->assertStringContainsString('7,500.00', $response);
        $this->assertStringContainsString('50,000.00', $response);

        // Net Change = $10,000 - $2,500 = $7,500
        // Total Liabilities ($50,000) + Net Assets ($7,500) = $57,500
        $this->assertStringContainsString('Balanced', $response);
    }

    public function testBalanceSheetCsvAndPdfExport(): void
    {
        $this->postJournalEntry(501, '2026-02-01', 'Opening balance', [
            ['chart_account_id' => 1, 'debit' => 5000.00, 'credit' => 0.00],
            ['chart_account_id' => 4, 'debit' => 0.00, 'credit' => 5000.00],
        ]);

        $controller = $this->makeReportExportsController('GET', ['as_of_date' => '2026-02-28']);

        // CSV Export
        $csvResponse = $controller->balanceSheetCsv();
        $this->assertSame(200, $csvResponse->getStatusCode());
        $this->assertStringContainsString('text/csv', $csvResponse->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('balance_sheet_501_', $csvResponse->getHeaderLine('Content-Disposition'));
        $this->assertStringContainsString('Cash - Checking', (string) $csvResponse->getBody());
        $this->assertStringContainsString('5000.00', (string) $csvResponse->getBody());

        // PDF Export
        $pdfResponse = $controller->balanceSheetPdf();
        $this->assertSame(200, $pdfResponse->getStatusCode());
        $this->assertStringContainsString('application/pdf', $pdfResponse->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('balance_sheet_501_', $pdfResponse->getHeaderLine('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', (string) $pdfResponse->getBody());
    }

    // -------------------------------------------------------------------------
    // Priority 5.2: Statement of Activities Tests
    // -------------------------------------------------------------------------

    public function testStatementOfActivitiesRendersAndCalculatesRevenuesAndExpenses(): void
    {
        // 1. Unrestricted Revenue: $8,000
        $this->postJournalEntry(501, '2026-01-15', 'Unrestricted pledge', [
            ['chart_account_id' => 1, 'debit' => 8000.00, 'credit' => 0.00],
            ['chart_account_id' => 5, 'debit' => 0.00, 'credit' => 8000.00],
        ]);

        // 2. Restricted Revenue: $1,200
        $this->postJournalEntry(501, '2026-01-20', 'Flower offering', [
            ['chart_account_id' => 1, 'debit' => 1200.00, 'credit' => 0.00],
            ['chart_account_id' => 6, 'debit' => 0.00, 'credit' => 1200.00],
        ]);

        // 3. Outreach Expense: $600
        $this->postJournalEntry(501, '2026-02-05', 'Diocesan assessment', [
            ['chart_account_id' => 7, 'debit' => 600.00, 'credit' => 0.00],
            ['chart_account_id' => 1, 'debit' => 0.00, 'credit' => 600.00],
        ]);

        // 4. Operations Expense: $400
        $this->postJournalEntry(501, '2026-02-15', 'Electric bill', [
            ['chart_account_id' => 9, 'debit' => 400.00, 'credit' => 0.00],
            ['chart_account_id' => 1, 'debit' => 0.00, 'credit' => 400.00],
        ]);

        $controller = $this->makeReportsController('GET', [
            'start_date' => '2026-01-01',
            'end_date'   => '2026-03-31',
        ]);
        $response = $controller->statementOfActivities();

        $this->assertIsString($response);
        $this->assertStringContainsString('Statement of Activities', $response);

        // Total Revenues = $8,000 + $1,200 = $9,200
        $this->assertStringContainsString('9,200.00', $response);

        // Total Expenses = $600 + $400 = $1,000
        $this->assertStringContainsString('1,000.00', $response);

        // Net Change = $9,200 - $1,000 = $8,200
        $this->assertStringContainsString('8,200.00', $response);
    }

    public function testStatementOfActivitiesCsvAndPdfExport(): void
    {
        $this->postJournalEntry(501, '2026-01-10', 'Pledges', [
            ['chart_account_id' => 1, 'debit' => 3000.00, 'credit' => 0.00],
            ['chart_account_id' => 5, 'debit' => 0.00, 'credit' => 3000.00],
        ]);

        $controller = $this->makeReportExportsController('GET', [
            'start_date' => '2026-01-01',
            'end_date'   => '2026-01-31',
        ]);

        // CSV Export
        $csvResponse = $controller->statementOfActivitiesCsv();
        $this->assertSame(200, $csvResponse->getStatusCode());
        $this->assertStringContainsString('text/csv', $csvResponse->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('statement_of_activities_501_', $csvResponse->getHeaderLine('Content-Disposition'));
        $this->assertStringContainsString('Pledge & Tithe Revenue', (string) $csvResponse->getBody());
        $this->assertStringContainsString('3000.00', (string) $csvResponse->getBody());

        // PDF Export
        $pdfResponse = $controller->statementOfActivitiesPdf();
        $this->assertSame(200, $pdfResponse->getStatusCode());
        $this->assertStringContainsString('application/pdf', $pdfResponse->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('statement_of_activities_501_', $pdfResponse->getHeaderLine('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', (string) $pdfResponse->getBody());
    }

    public function testFinancialStatementsMultiTenantIsolation(): void
    {
        // Post transaction in foreign parish (Parish 502)
        $this->postJournalEntry(502, '2026-01-01', 'Trinity Parish Donation', [
            ['chart_account_id' => 1, 'debit' => 99999.00, 'credit' => 0.00],
            ['chart_account_id' => 5, 'debit' => 0.00, 'credit' => 99999.00],
        ]);

        // Parish 501 views reports
        $controller = $this->makeReportsController('GET', ['as_of_date' => '2026-12-31']);
        $bsOutput   = $controller->balanceSheet();

        // 99,999 must NOT appear in Parish 501's report
        $this->assertStringNotContainsString('99,999.00', $bsOutput);

        $soaOutput = $controller->statementOfActivities();
        $this->assertStringNotContainsString('99,999.00', $soaOutput);
    }
}
