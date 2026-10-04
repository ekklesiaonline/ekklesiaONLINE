<?php

namespace Tests\Database;

use App\Libraries\DataEncryption;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Parish\Controllers\FinanceDashboard;
use Tests\Support\Database\CanonicalTestSchema;
use Tests\Support\Database\SystemSettingsTestHelper;

/**
 * Parish finance dashboard integration tests:
 * KPIs, period filters, pledge progress meter, bank balances,
 * action toolbar, and parish isolation.
 *
 * @internal
 */
final class ParishFinanceDashboardIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    private int $treasurerUserId;

    protected function setUp(): void
    {
        parent::setUp();

        helper(['auth', 'form', 'url', 'format']);
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
            'annual_pledge',
            'bank_account',
            'bank_transaction',
        ] as $table) {
            CanonicalTestSchema::reset($this->db, $table);
        }

        $this->createFinanceTables();

        $this->seedParish(301, 'Parish 301');
        $this->seedParish(302, 'Parish 302');

        $this->treasurerUserId = $this->insertUser('treasurer.301', 'treasurer301@example.com');
        $this->insertProfile($this->treasurerUserId, 301, 'Finance', 'Officer');

        $this->injectFinanceAuth($this->treasurerUserId, [
            'finance.view',
            'finance.manage',
            'donation.create',
            'donation.view',
            'expense.create',
            'expense.view',
            'budget.view',
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
    // Dashboard Rendering Tests
    // -----------------------------------------------------------------------

    public function testDashboardRendersKpisAndActionToolbar(): void
    {
        $fundId = $this->insertFund(301, 'General Fund');
        $today = date('Y-m-d');
        $this->insertDonation(301, $fundId, '500.00', $today);
        $this->insertExpense(301, $fundId, 150.00, $today, 'Altar supplies');

        $this->insertPledge(301, $this->treasurerUserId, (int) date('Y'), 2000.00, 1000.00);

        $bankId = $this->insertBankAccount(301, 'Main Operating', 'Chase Bank', '****1234', 5000.00);
        $this->insertBankTransaction($bankId, 500.00);

        $output = $this->makeController('GET')->index();

        $this->assertIsString($output);

        // Action Toolbar buttons
        $this->assertStringContainsString('donations/create', $output);
        $this->assertStringContainsString('expenses/create', $output);
        $this->assertStringContainsString('funds', $output);
        $this->assertStringContainsString('banking', $output);
        $this->assertStringContainsString('budgets', $output);
        $this->assertStringContainsString('pledges', $output);
        $this->assertStringContainsString('reports', $output);

        // KPI metrics
        $this->assertStringContainsString('500.00', $output);
        $this->assertStringContainsString('150.00', $output);
        $this->assertStringContainsString('350.00', $output); // Net

        // Pledge progress meter
        $this->assertStringContainsString('2,000.00', $output);
        $this->assertStringContainsString('1,000.00', $output);
        $this->assertStringContainsString('50.0%', $output);

        // Bank balance
        $this->assertStringContainsString('Main Operating', $output);
        $this->assertStringContainsString('5,500.00', $output); // 5000 opening + 500 txn

        // Active funds
        $this->assertStringContainsString('General Fund', $output);
    }

    public function testPeriodFilteringAggregatesCorrectly(): void
    {
        $fundId = $this->insertFund(301, 'Operating Fund');

        // Past year donation
        $this->insertDonation(301, $fundId, '1200.00', '2025-06-15');
        $this->insertExpense(301, $fundId, 400.00, '2025-06-20', 'Painting');

        // Current month donation
        $today = date('Y-m-d');
        $this->insertDonation(301, $fundId, '300.00', $today);
        $this->insertExpense(301, $fundId, 100.00, $today, 'Utilities');

        // Filter by year 2025 using custom range
        $outputCustom = $this->makeController('GET', [], [
            'period'     => 'custom',
            'start_date' => '2025-01-01',
            'end_date'   => '2025-12-31',
        ])->index();

        $this->assertIsString($outputCustom);
        $this->assertStringContainsString('1,200.00', $outputCustom);
        $this->assertStringContainsString('400.00', $outputCustom);
        $this->assertStringContainsString('800.00', $outputCustom); // Net
    }

    public function testParishTenantIsolation(): void
    {
        // Parish 301 data
        $fund1 = $this->insertFund(301, 'Parish 301 Fund');
        $this->insertDonation(301, $fund1, '100.00', date('Y-m-d'));
        $this->insertBankAccount(301, 'Parish 301 Bank', 'Bank A', '****1111', 1000.00);

        // Parish 302 foreign data
        $fund2 = $this->insertFund(302, 'Foreign Fund 302');
        $this->insertDonation(302, $fund2, '9999.00', date('Y-m-d'));
        $this->insertBankAccount(302, 'Secret Foreign Bank', 'Bank B', '****9999', 50000.00);

        $output = $this->makeController('GET')->index();

        $this->assertIsString($output);
        $this->assertStringContainsString('Parish 301 Fund', $output);
        $this->assertStringContainsString('Parish 301 Bank', $output);
        $this->assertStringNotContainsString('Foreign Fund 302', $output);
        $this->assertStringNotContainsString('Secret Foreign Bank', $output);
        $this->assertStringNotContainsString('9,999.00', $output);
    }

    public function testUnauthenticatedRedirectsToLogin(): void
    {
        $this->injectLoggedOutAuth();

        $response = $this->makeController('GET')->index();
        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testNoOperationalParishRedirects(): void
    {
        session()->remove('selected_parish_id');

        // User profile without parish
        $userWithoutParish = $this->insertUser('no.parish', 'noparish@example.com');
        $this->insertProfile($userWithoutParish, 0);
        $this->injectFinanceAuth($userWithoutParish, ['finance.view']);

        $response = $this->makeController('GET')->index();
        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $get
     */
    private function makeController(string $method = 'GET', array $post = [], array $get = []): FinanceDashboard
    {
        $request = service('request');
        $request->setMethod($method);

        if ($method === 'POST') {
            $request->setGlobal('post', $post);
            $request->setGlobal('request', $post);
        }

        if ($get !== []) {
            $request->setGlobal('get', $get);
        }

        $controller = new FinanceDashboard();
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

    private function insertFund(int $parishId, string $name): int
    {
        $this->db->table('fund')->insert([
            'parish_id'           => $parishId,
            'name'                => $name,
            'fund_type'           => 'unrestricted',
            'is_active'           => 1,
            'is_locked'           => 0,
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertDonation(int $parishId, int $fundId, string $amount, string $date): int
    {
        $encrypted = DataEncryption::encrypt($amount);
        $this->db->table('donation')->insert([
            'parish_id'        => $parishId,
            'fund_id'          => $fundId,
            'amount'           => $encrypted,
            'date'             => $date,
            'category'         => 'tithe',
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
            'deleted_at'       => null,
        ]);

        return (int) $this->db->insertID();
    }

    private function insertExpense(int $parishId, int $fundId, float $amount, string $date, string $desc): int
    {
        $this->db->table('expense')->insert([
            'parish_id'   => $parishId,
            'fund_id'     => $fundId,
            'amount'      => $amount,
            'date'        => $date,
            'category'    => 'operations',
            'description' => $desc,
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertPledge(int $parishId, int $userId, int $year, float $pledged, float $fulfilled): int
    {
        $this->db->table('annual_pledge')->insert([
            'parish_id'          => $parishId,
            'user_id'            => $userId,
            'pledge_year'        => $year,
            'pledged_amount'     => $pledged,
            'fulfilled_amount'   => $fulfilled,
            'fulfillment_status' => 'pledged',
            'created_at'         => date('Y-m-d H:i:s'),
            'updated_at'         => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertBankAccount(int $parishId, string $name, string $institution, string $masked, float $openingBalance): int
    {
        $this->db->table('bank_account')->insert([
            'parish_id'             => $parishId,
            'name'                  => $name,
            'institution_name'      => $institution,
            'masked_account_number' => $masked,
            'currency_code'         => 'USD',
            'opening_balance'       => $openingBalance,
            'is_active'             => 1,
            'created_at'            => date('Y-m-d H:i:s'),
            'updated_at'            => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertBankTransaction(int $bankAccountId, float $amount): int
    {
        $this->db->table('bank_transaction')->insert([
            'bank_account_id'  => $bankAccountId,
            'transaction_date' => date('Y-m-d'),
            'amount'           => $amount,
            'description'      => 'Deposit',
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
                $this->attributes['email']    = 'finance.' . $userIdForTest . '@example.com';
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

    private function injectLoggedOutAuth(): void
    {
        $auth = new class () extends Auth {
            public function __construct()
            {
            }

            public function setAuthenticator(?string $alias = null): self
            {
                return $this;
            }

            public function loggedIn(): bool
            {
                return false;
            }

            public function id(): ?int
            {
                return null;
            }

            public function user(): ?User
            {
                return null;
            }
        };

        Services::injectMock('auth', $auth);
    }
}
