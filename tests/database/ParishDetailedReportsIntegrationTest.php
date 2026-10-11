<?php

namespace Tests\Database;

use App\Libraries\DataEncryption;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Tests\Support\Database\CanonicalTestSchema;
use Tests\Support\Database\SystemSettingsTestHelper;
use Finance\Controllers\ReportExports as Reports;

/**
 * Parish detailed reports integration tests:
 * Fund breakdown, account/category breakdown, CSV export, PDF export,
 * summary API, unassigned transactions, and cross-parish isolation.
 *
 * @internal
 */
final class ParishDetailedReportsIntegrationTest extends CIUnitTestCase
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
        ] as $table) {
            CanonicalTestSchema::reset($this->db, $table);
        }

        $this->createFinanceTables();

        $this->seedParish(301, 'Parish 301');
        $this->seedParish(302, 'Parish 302 Foreign');

        $this->treasurerUserId = $this->insertUser('treasurer.parish301', 'treasurer301@example.test');
        $this->insertProfile($this->treasurerUserId, 301, 'Treasurer', 'User');

        $this->injectFinanceAuth($this->treasurerUserId, [
            'report.view',
            'finance.view',
            'donation.view',
            'expense.view',
        ]);

        session()->set('selected_parish_id', 301);
    }

    protected function tearDown(): void
    {
        Services::reset(true);
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // View Rendering Tests
    // -----------------------------------------------------------------------

    public function testReportsIndexRendersFundBreakdownAndAccountBreakdown(): void
    {
        $today = date('Y-m-d');
        $startDate = date('Y-01-01');
        $endDate = date('Y-12-31');

        $fundGeneral = $this->insertFund(301, 'General Operating Fund', 'unrestricted', 1);
        $fundBuilding = $this->insertFund(301, 'Building Restoration Fund', 'restricted', 1);
        $fundYouth = $this->insertFund(301, 'Youth Summer Fund', 'restricted', 0); // Inactive

        $coaIncome1 = $this->insertChartAccount('4010', 'Pledge Offerings', 'income', 'tithe');
        $coaIncome2 = $this->insertChartAccount('4020', 'Building Donations', 'income', 'building_fund');
        $coaExpense1 = $this->insertChartAccount('5010', 'Electric Utility', 'expense', 'utilities');
        $coaExpense2 = $this->insertChartAccount('5020', 'Roof Maintenance', 'expense', 'maintenance');

        $this->insertDonation(301, $fundGeneral, '1000.00', $today, 'tithe', $coaIncome1);
        $this->insertDonation(301, $fundBuilding, '500.00', $today, 'building_fund', $coaIncome2);

        $this->insertExpense(301, $fundGeneral, 350.00, $today, 'utilities', 'Power bill', $coaExpense1);
        $this->insertExpense(301, $fundBuilding, 150.00, $today, 'maintenance', 'Roof sealant', $coaExpense2);

        // Foreign parish transactions (isolation check)
        $foreignFund = $this->insertFund(302, 'Foreign Parish Fund', 'unrestricted', 1);
        $this->insertDonation(302, $foreignFund, '9999.00', $today, 'tithe', null);
        $this->insertExpense(302, $foreignFund, 8888.00, $today, 'operations', 'Foreign expense', null);

        $controller = $this->makeController('GET', [
            'start_date' => $startDate,
            'end_date'   => $endDate,
        ]);
        $output = $controller->index();

        $this->assertIsString($output);

        // Fund breakdown elements
        $this->assertStringContainsString('General Operating Fund', $output);
        $this->assertStringContainsString('Building Restoration Fund', $output);
        $this->assertStringContainsString('Youth Summer Fund', $output);
        $this->assertStringContainsString('1,000.00', $output);
        $this->assertStringContainsString('500.00', $output);
        $this->assertStringContainsString('350.00', $output);
        $this->assertStringContainsString('150.00', $output);

        // Account breakdown elements
        $this->assertStringContainsString('4010', $output);
        $this->assertStringContainsString('Pledge Offerings', $output);
        $this->assertStringContainsString('4020', $output);
        $this->assertStringContainsString('Building Donations', $output);
        $this->assertStringContainsString('5010', $output);
        $this->assertStringContainsString('Electric Utility', $output);
        $this->assertStringContainsString('5020', $output);
        $this->assertStringContainsString('Roof Maintenance', $output);

        // Parish isolation: foreign amounts and fund names must NOT appear
        $this->assertStringNotContainsString('Foreign Parish Fund', $output);
        $this->assertStringNotContainsString('9,999.00', $output);
        $this->assertStringNotContainsString('8,888.00', $output);
    }

    // -----------------------------------------------------------------------
    // CSV Export Tests
    // -----------------------------------------------------------------------

    public function testReportsExportCsvIncludesFundAndAccountBreakdownForParish(): void
    {
        $today = date('Y-m-d');
        $startDate = date('Y-01-01');
        $endDate = date('Y-12-31');

        $fundGeneral = $this->insertFund(301, 'Operating Fund', 'unrestricted', 1);
        $fundMissions = $this->insertFund(301, 'Missions Fund', 'restricted', 1);

        $coa1 = $this->insertChartAccount('4100', 'Sunday Collections', 'income', 'offering');
        $coa2 = $this->insertChartAccount('5100', 'Mission Trip Subsidy', 'expense', 'outreach');

        $this->insertDonation(301, $fundGeneral, '1200.00', $today, 'offering', $coa1);
        $this->insertExpense(301, $fundMissions, 400.00, $today, 'outreach', 'Airfare', $coa2);

        $controller = $this->makeController('GET', [
            'start_date' => $startDate,
            'end_date'   => $endDate,
        ]);
        $response = $controller->export();

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertStringContainsString('text/csv', (string) $response->getHeaderLine('Content-Type'));

        $body = (string) $response->getBody();

        // Check CSV headers and contents
        $this->assertStringContainsString('Fund Breakdown', $body);
        $this->assertStringContainsString('Operating Fund', $body);
        $this->assertStringContainsString('Missions Fund', $body);
        $this->assertStringContainsString('1200.00', $body);
        $this->assertStringContainsString('400.00', $body);

        $this->assertStringContainsString('Income by Account & Category', $body);
        $this->assertStringContainsString('4100', $body);
        $this->assertStringContainsString('Sunday Collections', $body);

        $this->assertStringContainsString('Expenses by Account & Category', $body);
        $this->assertStringContainsString('5100', $body);
        $this->assertStringContainsString('Mission Trip Subsidy', $body);
    }

    // -----------------------------------------------------------------------
    // PDF Export Tests
    // -----------------------------------------------------------------------

    public function testReportsExportPdfGeneratesValidPdfWithBreakdowns(): void
    {
        $today = date('Y-m-d');
        $startDate = date('Y-01-01');
        $endDate = date('Y-12-31');

        $fund = $this->insertFund(301, 'Capital Campaign', 'restricted', 1);
        $coa = $this->insertChartAccount('4200', 'Capital Gifts', 'income', 'building_fund');
        $this->insertDonation(301, $fund, '750.00', $today, 'building_fund', $coa);

        $controller = $this->makeController('GET', [
            'start_date' => $startDate,
            'end_date'   => $endDate,
        ]);
        $response = $controller->exportPdf();

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame('application/pdf', $response->getHeaderLine('Content-Type'));

        $body = (string) $response->getBody();
        $this->assertStringStartsWith('%PDF-', $body);
        $this->assertGreaterThan(500, strlen($body));
    }

    // -----------------------------------------------------------------------
    // Summary API Tests
    // -----------------------------------------------------------------------

    public function testReportsSummaryApiReturnsParishBreakdownsJson(): void
    {
        $today = date('Y-m-d');
        $startDate = date('Y-01-01');
        $endDate = date('Y-12-31');

        $fundA = $this->insertFund(301, 'Fund Alpha', 'unrestricted', 1);
        $fundB = $this->insertFund(301, 'Fund Beta', 'restricted', 1);

        $coaInc = $this->insertChartAccount('4001', 'General Giving', 'income', 'tithe');
        $coaExp = $this->insertChartAccount('5001', 'Ministry Supplies', 'expense', 'ministry');

        $this->insertDonation(301, $fundA, '600.00', $today, 'tithe', $coaInc);
        $this->insertDonation(301, $fundB, '400.00', $today, 'tithe', $coaInc);
        $this->insertExpense(301, $fundA, 250.00, $today, 'ministry', 'Bibles', $coaExp);

        $controller = $this->makeController('GET', [
            'start_date' => $startDate,
            'end_date'   => $endDate,
        ]);
        $response = $controller->summaryApi();

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertTrue($payload['success']);
        $this->assertSame('parish', $payload['data']['scope']);

        // Check fund breakdown in JSON
        $funds = $payload['data']['fund_breakdown']['funds'];
        $this->assertCount(2, $funds);
        $fundNames = array_column($funds, 'fund_name');
        $this->assertContains('Fund Alpha', $fundNames);
        $this->assertContains('Fund Beta', $fundNames);

        $totals = $payload['data']['fund_breakdown']['totals'];
        $this->assertEquals(1000.0, $totals['donations_total']);
        $this->assertEquals(250.0, $totals['expenses_total']);
        $this->assertEquals(750.0, $totals['net_balance']);

        // Check account breakdown in JSON
        $incomeAccounts = $payload['data']['account_breakdown']['income'];
        $this->assertNotEmpty($incomeAccounts);
        $this->assertSame('4001', $incomeAccounts[0]['account_code']);
        $this->assertEquals(1000.0, $incomeAccounts[0]['total_amount']);
        $this->assertEquals(100.0, $incomeAccounts[0]['percentage']);

        $expenseAccounts = $payload['data']['account_breakdown']['expenses'];
        $this->assertNotEmpty($expenseAccounts);
        $this->assertSame('5001', $expenseAccounts[0]['account_code']);
        $this->assertEquals(250.0, $expenseAccounts[0]['total_amount']);
        $this->assertEquals(100.0, $expenseAccounts[0]['percentage']);
    }

    // -----------------------------------------------------------------------
    // Unassigned Transactions Test
    // -----------------------------------------------------------------------

    public function testReportsHandlesUnassignedFundTransactions(): void
    {
        $today = date('Y-m-d');
        $startDate = date('Y-01-01');
        $endDate = date('Y-12-31');

        // Donation and expense with null fund_id
        $this->insertDonation(301, null, '200.00', $today, 'other', null);
        $this->insertExpense(301, null, 75.00, $today, 'other', 'Direct purchase', null);

        $controller = $this->makeController('GET', [
            'start_date' => $startDate,
            'end_date'   => $endDate,
        ]);
        $response = $controller->summaryApi();

        $payload = json_decode((string) $response->getBody(), true);
        $funds = $payload['data']['fund_breakdown']['funds'];

        $unassignedRow = null;
        foreach ($funds as $f) {
            if ($f['fund_id'] === null) {
                $unassignedRow = $f;
                break;
            }
        }

        $this->assertNotNull($unassignedRow);
        $this->assertEquals(200.0, $unassignedRow['donations_total']);
        $this->assertEquals(75.0, $unassignedRow['expenses_total']);
        $this->assertEquals(125.0, $unassignedRow['net_balance']);
    }

    // -----------------------------------------------------------------------
    // Province Admin Scope Test
    // -----------------------------------------------------------------------

    public function testProvinceAdminRendersDioceseBreakdownInsteadOfParishBreakdown(): void
    {
        $this->seedProvince(10, 'Province I');
        $this->seedDiocese(20, 10, 'Diocese of test');

        $this->db->table('parish')->where('id', 301)->update(['diocese_id' => 20]);

        $provinceAdminId = $this->insertUser('province.admin', 'provadmin@example.test');
        $this->insertProfileWithProvince($provinceAdminId, 10);
        $this->injectProvinceAdminAuth($provinceAdminId);

        $controller = $this->makeController('GET', [
            'start_date' => date('Y-01-01'),
            'end_date'   => date('Y-12-31'),
        ]);
        $output = $controller->index();

        $this->assertIsString($output);
        $this->assertStringContainsString(lang('Treasurer.reportPerDioceseSummary'), $output);
        $this->assertStringNotContainsString('id="accountBreakdownTabs"', $output);
    }

    // -----------------------------------------------------------------------
    // Helper Methods
    // -----------------------------------------------------------------------

    /**
     * @param array<string, mixed> $get
     * @param array<string, mixed> $post
     */
    private function makeController(string $method = 'GET', array $get = [], array $post = []): Reports
    {
        $request = Services::request();
        $request->setMethod($method);

        if ($method === 'POST') {
            $request->setGlobal('post', $post);
            $request->setGlobal('request', $post);
        }

        if ($get !== []) {
            $request->setGlobal('get', $get);
        }

        $controller = new Reports();
        $controller->initController($request, service('response'), service('logger'));

        return $controller;
    }

    private function createFinanceTables(): void
    {
        $prefix = $this->db->getPrefix();

        $definitions = [
            'fund' => '(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                parish_id INTEGER NULL,
                name TEXT NOT NULL,
                description TEXT NULL,
                fund_type TEXT NOT NULL DEFAULT \'unrestricted\',
                restriction_purpose TEXT NULL,
                chart_account_id INTEGER NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                is_locked INTEGER NOT NULL DEFAULT 0,
                created_by INTEGER NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )',
            'donation' => '(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                parish_id INTEGER NOT NULL,
                fund_id INTEGER NULL,
                user_id INTEGER NULL,
                amount TEXT NOT NULL,
                date TEXT NOT NULL,
                category TEXT NOT NULL,
                chart_account_id INTEGER NULL,
                payment_method TEXT NULL,
                notes TEXT NULL,
                is_restricted INTEGER NULL,
                restriction_purpose TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )',
            'expense' => '(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                parish_id INTEGER NOT NULL,
                fund_id INTEGER NULL,
                description TEXT NOT NULL,
                amount REAL NOT NULL,
                category TEXT NOT NULL,
                chart_account_id INTEGER NULL,
                date TEXT NOT NULL,
                approved_by INTEGER NULL,
                notes TEXT NULL,
                is_capital INTEGER NULL,
                receipt_path TEXT NULL,
                receipt_original_name TEXT NULL,
                receipt_mime_type TEXT NULL,
                receipt_file_size INTEGER NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )',
            'chart_of_accounts' => '(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT NOT NULL,
                name TEXT NOT NULL,
                account_type TEXT NOT NULL,
                category TEXT NULL,
                description TEXT NULL,
                allow_budget INTEGER NOT NULL DEFAULT 1,
                is_active INTEGER NOT NULL DEFAULT 1,
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )',
        ];

        foreach ($definitions as $table => $definition) {
            $this->db->query('DROP TABLE IF EXISTS "' . $prefix . $table . '"');
            $this->db->query('CREATE TABLE "' . $prefix . $table . '" ' . $definition);
        }
    }

    private function seedProvince(int $id, string $name): void
    {
        $existing = $this->db->table('province')->where('id', $id)->get()->getRowArray();
        if ($existing) {
            $this->db->table('province')->where('id', $id)->update([
                'province_number' => (string) $id,
                'name'            => $name,
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);
            return;
        }

        $this->db->table('province')->insert([
            'id'              => $id,
            'province_number' => (string) $id,
            'name'            => $name,
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    private function seedDiocese(int $id, int $provinceId, string $name): void
    {
        $existing = $this->db->table('diocese')->where('id', $id)->get()->getRowArray();
        if ($existing) {
            $this->db->table('diocese')->where('id', $id)->update([
                'province_id' => $provinceId,
                'name'        => $name,
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);
            return;
        }

        $this->db->table('diocese')->insert([
            'id'          => $id,
            'province_id' => $provinceId,
            'name'        => $name,
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    private function seedParish(int $id, string $name): void
    {
        $existing = $this->db->table('parish')->where('id', $id)->get()->getRowArray();
        if ($existing) {
            $this->db->table('parish')->where('id', $id)->update([
                'name'       => $name,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            return;
        }

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

    private function insertProfileWithProvince(int $userId, int $provinceId): void
    {
        $this->db->table('profile')->insert([
            'user_id'     => $userId,
            'province_id' => $provinceId,
            'first_name'  => 'Provincial',
            'last_name'   => 'Admin',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    private function insertFund(int $parishId, string $name, string $type = 'unrestricted', int $isActive = 1): int
    {
        $this->db->table('fund')->insert([
            'parish_id'           => $parishId,
            'name'                => $name,
            'fund_type'           => $type,
            'is_active'           => $isActive,
            'is_locked'           => 0,
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertChartAccount(string $code, string $name, string $type, ?string $category): int
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

    private function insertDonation(
        int $parishId,
        ?int $fundId,
        string $amount,
        string $date,
        string $category = 'tithe',
        ?int $chartAccountId = null
    ): int {
        $encrypted = DataEncryption::encrypt($amount);
        $this->db->table('donation')->insert([
            'parish_id'        => $parishId,
            'fund_id'          => $fundId,
            'amount'           => $encrypted,
            'date'             => $date,
            'category'         => $category,
            'chart_account_id' => $chartAccountId,
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
            'deleted_at'       => null,
        ]);

        return (int) $this->db->insertID();
    }

    private function insertExpense(
        int $parishId,
        ?int $fundId,
        float $amount,
        string $date,
        string $category,
        string $desc,
        ?int $chartAccountId = null
    ): int {
        $this->db->table('expense')->insert([
            'parish_id'        => $parishId,
            'fund_id'          => $fundId,
            'amount'           => $amount,
            'date'             => $date,
            'category'         => $category,
            'chart_account_id' => $chartAccountId,
            'description'      => $desc,
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
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

    private function injectProvinceAdminAuth(int $userId): void
    {
        $fakeUser = new class ($userId) extends User {
            public function __construct(private int $userIdForTest)
            {
                $this->attributes['id']       = $userIdForTest;
                $this->attributes['username'] = 'prov.' . $userIdForTest;
                $this->attributes['email']    = 'prov.' . $userIdForTest . '@example.test';
            }

            public function can(string ...$permissions): bool
            {
                return in_array('report.view', $permissions, true);
            }

            public function inGroup(string ...$groups): bool
            {
                return in_array('provinceadmin', $groups, true);
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
