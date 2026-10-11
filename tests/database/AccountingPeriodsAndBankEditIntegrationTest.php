<?php

namespace Tests\Database;

use App\Models\AccountingPeriodModel;
use App\Models\BankAccountModel;
use App\Models\BankReconciliationModel;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Finance\Controllers\AccountingPeriods;
use Finance\Controllers\Banking;
use Tests\Support\Database\CanonicalTestSchema;
use Tests\Support\Database\SystemSettingsTestHelper;

/**
 * Priorities 4.3 & 4.4 Integration Test:
 * - Accounting Period Management UI (/accounting-periods, generate, store, close, reopen)
 * - Bank Account Edit & Archive (/banking/edit, update, toggle-active)
 *
 * @internal
 */
final class AccountingPeriodsAndBankEditIntegrationTest extends CIUnitTestCase
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
            'accounting_period',
            'bank_reconciliation_item',
            'bank_reconciliation',
            'bank_transaction',
            'bank_account',
            'chart_of_accounts',
            'parish',
            'users',
            'profile',
            'audit_log',
        ] as $table) {
            CanonicalTestSchema::reset($this->db, $table);
        }

        $this->seedParish(401, 'St. Michael Parish');
        $this->seedParish(402, 'Grace Church');

        $this->userId = $this->insertUser('finance.manager', 'manager@stmichael.org');
        $this->insertProfile($this->userId, 401, 'Finance', 'Manager');
        $this->injectFinanceAuth($this->userId, ['finance.view', 'finance.manage']);

        session()->set('selected_parish_id', 401);
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

    private function injectFinanceAuth(int $userId, array $permissions): void
    {
        $fakeUser = new class ($userId, $permissions) extends User {
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

    private function makeAccountingPeriodsController(string $method = 'GET', array $post = [], array $get = []): AccountingPeriods
    {
        $request = service('request');
        $request->setMethod($method);
        $request->setGlobal('get', $get);
        $request->setGlobal('post', $post);
        $request->setGlobal('request', array_merge($get, $post));

        $controller = new AccountingPeriods();
        $controller->initController($request, service('response'), service('logger'));

        return $controller;
    }

    private function makeBankingController(string $method = 'GET', array $post = [], array $get = []): Banking
    {
        $request = service('request');
        $request->setMethod($method);
        $request->setGlobal('get', $get);
        $request->setGlobal('post', $post);
        $request->setGlobal('request', array_merge($get, $post));

        $controller = new Banking();
        $controller->initController($request, service('response'), service('logger'));

        return $controller;
    }

    private function insertBankAccount(int $parishId, string $name, float $openingBalance = 0.0, int $isActive = 1): int
    {
        $this->db->table('bank_account')->insert([
            'parish_id'              => $parishId,
            'name'                   => $name,
            'institution_name'       => 'Test Bank',
            'masked_account_number'  => '****1234',
            'routing_last4'          => '5678',
            'currency_code'          => 'USD',
            'opening_balance'        => $openingBalance,
            'is_active'              => $isActive,
            'created_at'             => date('Y-m-d H:i:s'),
            'updated_at'             => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    // -------------------------------------------------------------------------
    // Priority 4.3: Accounting Period Management Tests
    // -------------------------------------------------------------------------

    public function testAccountingPeriodsIndexRendersCorrectly(): void
    {
        $controller = $this->makeAccountingPeriodsController('GET', [], ['year' => '2026']);
        $response   = $controller->index();

        $this->assertIsString($response);
        $this->assertStringContainsString('Accounting Periods', $response);
        $this->assertStringContainsString('2026', $response);
    }

    public function testGenerateYearAccountingPeriodsSuccess(): void
    {
        $controller = $this->makeAccountingPeriodsController('POST', ['fiscal_year' => 2026]);
        $response   = $controller->generate();

        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $response);

        $model = model(AccountingPeriodModel::class);
        $periods = $model->where('parish_id', 401)->where('fiscal_year', 2026)->findAll();
        $this->assertCount(12, $periods);

        // Check January
        $jan = $model->where('parish_id', 401)->where('fiscal_year', 2026)->where('period_number', 1)->first();
        $this->assertNotNull($jan);
        $this->assertSame('January 2026', $jan['period_name']);
        $this->assertSame('2026-01-01', $jan['start_date']);
        $this->assertSame('2026-01-31', $jan['end_date']);
        $this->assertSame('open', $jan['status']);

        // Check February (leap year check: 2026 has 28 days)
        $feb = $model->where('parish_id', 401)->where('fiscal_year', 2026)->where('period_number', 2)->first();
        $this->assertNotNull($feb);
        $this->assertSame('2026-02-28', $feb['end_date']);
    }

    public function testGenerateYearAccountingPeriodsIsIdempotent(): void
    {
        $controller1 = $this->makeAccountingPeriodsController('POST', ['fiscal_year' => 2027]);
        $controller1->generate();

        $model = model(AccountingPeriodModel::class);
        $this->assertCount(12, $model->where('parish_id', 401)->where('fiscal_year', 2027)->findAll());

        // Generate again: should not duplicate
        $controller2 = $this->makeAccountingPeriodsController('POST', ['fiscal_year' => 2027]);
        $response = $controller2->generate();
        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $response);

        $this->assertCount(12, $model->where('parish_id', 401)->where('fiscal_year', 2027)->findAll());
    }

    public function testManualAccountingPeriodCreation(): void
    {
        $postData = [
            'fiscal_year'   => 2026,
            'period_number' => 13,
            'period_name'   => 'Year-End Adjustments',
            'start_date'    => '2026-12-31',
            'end_date'      => '2026-12-31',
        ];

        $controller = $this->makeAccountingPeriodsController('POST', $postData);
        $response   = $controller->store();

        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $response);

        $model  = model(AccountingPeriodModel::class);
        $period = $model->where('parish_id', 401)->where('fiscal_year', 2026)->where('period_number', 13)->first();
        $this->assertNotNull($period);
        $this->assertSame('Year-End Adjustments', $period['period_name']);
        $this->assertSame('open', $period['status']);
    }

    public function testManualAccountingPeriodValidationFailsOnInvalidDates(): void
    {
        $postData = [
            'fiscal_year'   => 2026,
            'period_number' => 14,
            'period_name'   => 'Invalid Range',
            'start_date'    => '2026-05-15',
            'end_date'      => '2026-05-01', // End before start
        ];

        $controller = $this->makeAccountingPeriodsController('POST', $postData);
        $response   = $controller->store();

        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $response);

        $model = model(AccountingPeriodModel::class);
        $period = $model->where('parish_id', 401)->where('period_number', 14)->first();
        $this->assertNull($period);
    }

    public function testCloseAndReopenAccountingPeriod(): void
    {
        $model = model(AccountingPeriodModel::class);
        $periodId = (int) $model->insert([
            'parish_id'     => 401,
            'fiscal_year'   => 2026,
            'period_number' => 3,
            'period_name'   => 'March 2026',
            'start_date'    => '2026-03-01',
            'end_date'      => '2026-03-31',
            'status'        => 'open',
        ]);

        // Close period
        $controller = $this->makeAccountingPeriodsController('POST');
        $response   = $controller->close($periodId);
        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $response);

        $closed = $model->find($periodId);
        $this->assertSame('closed', $closed['status']);
        $this->assertNotNull($closed['closed_at']);

        // Closing an already closed period returns redirect error
        $controllerAlreadyClosed = $this->makeAccountingPeriodsController('POST');
        $responseAlreadyClosed   = $controllerAlreadyClosed->close($periodId);
        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $responseAlreadyClosed);

        // Reopen period
        $controllerReopen = $this->makeAccountingPeriodsController('POST');
        $responseReopen   = $controllerReopen->reopen($periodId);
        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $responseReopen);

        $reopened = $model->find($periodId);
        $this->assertSame('open', $reopened['status']);
        $this->assertNull($reopened['closed_at']);
    }

    public function testAccountingPeriodMultiTenantIsolation(): void
    {
        $model = model(AccountingPeriodModel::class);
        $foreignPeriodId = (int) $model->insert([
            'parish_id'     => 402, // Other parish
            'fiscal_year'   => 2026,
            'period_number' => 1,
            'period_name'   => 'January 2026 (Grace Church)',
            'start_date'    => '2026-01-01',
            'end_date'      => '2026-01-31',
            'status'        => 'open',
        ]);

        // Parish 401 user tries to close Parish 402's period
        $controller = $this->makeAccountingPeriodsController('POST');
        $response   = $controller->close($foreignPeriodId);
        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $response);

        // Ensure foreign period remains untouched in database
        $foreignPeriod = $this->db->table('accounting_period')->where('id', $foreignPeriodId)->get()->getRowArray();
        $this->assertNotNull($foreignPeriod);
        $this->assertSame('open', $foreignPeriod['status']);
        $this->assertNull($foreignPeriod['closed_at']);
    }

    // -------------------------------------------------------------------------
    // Priority 4.4: Bank Account Edit & Archive Tests
    // -------------------------------------------------------------------------

    public function testBankAccountEditRendersForm(): void
    {
        $accountId = $this->insertBankAccount(401, 'Operating Checking Account', 1500.00);

        $controller = $this->makeBankingController('GET');
        $response   = $controller->edit($accountId);

        $this->assertIsString($response);
        $this->assertStringContainsString('Edit Bank Account', $response);
        $this->assertStringContainsString('Operating Checking Account', $response);
    }

    public function testBankAccountEditRejectsForeignParishAccount(): void
    {
        $foreignAccountId = $this->insertBankAccount(402, 'Grace Church Account', 5000.00);

        $controller = $this->makeBankingController('GET');
        $response   = $controller->edit($foreignAccountId);

        // Should redirect back with error
        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $response);
    }

    public function testBankAccountUpdateSavesChanges(): void
    {
        $accountId = $this->insertBankAccount(401, 'Old Account Name', 1000.00);

        $postData = [
            'name'                  => 'Primary Operations Checking',
            'institution_name'      => 'First National Bank',
            'masked_account_number' => '****9876',
            'routing_last4'         => '4321',
            'currency_code'         => 'USD',
            'opening_balance'       => 2500.50,
            'opened_on'             => '2026-01-01',
            'is_active'             => 1,
        ];

        $controller = $this->makeBankingController('POST', $postData);
        $response   = $controller->update($accountId);

        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $response);

        $model   = model(BankAccountModel::class);
        $updated = $model->find($accountId);

        $this->assertSame('Primary Operations Checking', $updated['name']);
        $this->assertSame('First National Bank', $updated['institution_name']);
        $this->assertSame('****9876', $updated['masked_account_number']);
        $this->assertSame('4321', $updated['routing_last4']);
        $this->assertEquals(2500.50, (float) $updated['opening_balance']);
        $this->assertSame(1, (int) $updated['is_active']);
    }

    public function testBankAccountToggleActiveArchiveAndActivate(): void
    {
        $accountId = $this->insertBankAccount(401, 'Savings Account', 1000.00, 1);
        $model     = model(BankAccountModel::class);

        // Archive: active 1 -> 0
        $controller1 = $this->makeBankingController('POST');
        $response1   = $controller1->toggleActive($accountId);
        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $response1);

        $archived = $model->find($accountId);
        $this->assertSame(0, (int) $archived['is_active']);

        // Reactivate: active 0 -> 1
        $controller2 = $this->makeBankingController('POST');
        $response2   = $controller2->toggleActive($accountId);
        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $response2);

        $reactivated = $model->find($accountId);
        $this->assertSame(1, (int) $reactivated['is_active']);
    }

    public function testBankAccountDeactivationBlockedWhenOpenReconciliationExists(): void
    {
        $accountId = $this->insertBankAccount(401, 'Checking With Open Recon', 2000.00, 1);

        // Create an open reconciliation session on this account
        $this->db->table('bank_reconciliation')->insert([
            'bank_account_id'   => $accountId,
            'statement_date'    => '2026-01-31',
            'statement_balance' => 2000.00,
            'status'            => 'open',
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);
        $reconId = (int) $this->db->insertID();

        $this->assertGreaterThan(0, $reconId);

        // Attempt to archive / deactivate
        $controller = $this->makeBankingController('POST');
        $response   = $controller->toggleActive($accountId);
        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $response);

        // Account must STILL be active
        $model   = model(BankAccountModel::class);
        $account = $model->find($accountId);
        $this->assertSame(1, (int) $account['is_active']);
    }

    public function testBankAccountToggleActiveMultiTenantIsolation(): void
    {
        $foreignAccountId = $this->insertBankAccount(402, 'Grace Church Checking', 3000.00, 1);

        // Parish 401 user tries to archive Parish 402's account
        $controller = $this->makeBankingController('POST');
        $response   = $controller->toggleActive($foreignAccountId);
        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $response);

        $account = $this->db->table('bank_account')->where('id', $foreignAccountId)->get()->getRowArray();
        $this->assertNotNull($account);
        $this->assertSame(1, (int) $account['is_active']);
    }
}
