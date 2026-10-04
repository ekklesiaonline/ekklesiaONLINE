<?php

namespace Tests\Database;

use App\Models\BankAccountModel;
use App\Models\BankReconciliationModel;
use App\Models\BankTransactionModel;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Tests\Support\Database\CanonicalTestSchema;
use Tests\Support\Database\SystemSettingsTestHelper;
use Treasurer\Controllers\BankingReconciliation as Banking;

/**
 * Phase 6: Banking & Bank Reconciliation Controller Integration Test.
 *
 * Verifies:
 * - Bank accounts listing scoped by parish.
 * - Bank account creation, validation, and audit logging.
 * - Transaction creation and audit logging.
 * - Reconciliation session start with single-open enforcement.
 * - Worksheet clearing/un-clearing transactions.
 * - Closing reconciliation: rejection when unbalanced, success when balanced.
 * - Multi-tenant isolation preventing cross-parish account/reconciliation access.
 *
 * @internal
 */
final class BankingControllerIntegrationTest extends CIUnitTestCase
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
            'bank_reconciliation_item',
            'bank_reconciliation',
            'bank_transaction',
            'bank_account',
            'parish',
            'users',
            'profile',
            'audit_log',
        ] as $table) {
            CanonicalTestSchema::reset($this->db, $table);
        }

        $this->seedParish(301, 'Parish 301');
        $this->seedParish(302, 'Parish 302');

        $this->userId = $this->insertUser('finance.officer', 'finance@example.com');
        $this->insertProfile($this->userId, 301, 'Finance', 'Officer');
        $this->injectFinanceAuth($this->userId, ['finance.view', 'finance.manage']);

        session()->set('selected_parish_id', 301);
    }

    protected function tearDown(): void
    {
        Services::reset(true);
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

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

            public function id(): ?int
            {
                return $this->userIdForTest;
            }

            public function user(): ?User
            {
                return $this->fakeUser;
            }
        };

        Services::injectMock('auth', $auth);
    }

    private function makeController(string $method = 'GET', array $post = []): Banking
    {
        $request = service('request');
        $request->setMethod($method);

        if ($method === 'POST') {
            $request->setGlobal('post', $post);
            $request->setGlobal('request', $post);
        }

        $controller = new Banking();
        $controller->initController($request, service('response'), service('logger'));

        return $controller;
    }

    private function insertAccount(int $parishId, string $name, float $openingBalance = 0.0): int
    {
        $this->db->table('bank_account')->insert([
            'parish_id'       => $parishId,
            'name'            => $name,
            'currency_code'   => 'USD',
            'opening_balance' => $openingBalance,
            'is_active'       => 1,
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertTransaction(int $accountId, string $date, float $amount, string $status = 'unmatched'): int
    {
        $this->db->table('bank_transaction')->insert([
            'bank_account_id'  => $accountId,
            'transaction_date' => $date,
            'amount'           => $amount,
            'status'           => $status,
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    // -----------------------------------------------------------------------
    // Tests: Account Management
    // -----------------------------------------------------------------------

    public function testIndexListsAccountsForOperationalParish(): void
    {
        $acc301 = $this->insertAccount(301, 'Parish 301 Main Checking', 1000.0);
        $acc302 = $this->insertAccount(302, 'Parish 302 Secret Account', 5000.0);

        $controller = $this->makeController('GET');
        $output     = $controller->index();

        $this->assertIsString($output);
        $this->assertStringContainsString('Parish 301 Main Checking', $output);
        $this->assertStringNotContainsString('Parish 302 Secret Account', $output);
    }

    public function testCreateRendersForm(): void
    {
        $controller = $this->makeController('GET');
        $output     = $controller->create();

        $this->assertIsString($output);
        $this->assertStringContainsString(lang('Finance.bankAccountCreate'), $output);
    }

    public function testStorePersistsAccountAndLogsAudit(): void
    {
        $postData = [
            'name'                  => 'General Fund Checking',
            'institution_name'      => 'First National Bank',
            'masked_account_number' => '****1234',
            'routing_last4'         => '5678',
            'currency_code'         => 'usd',
            'opening_balance'       => '2500.50',
            'opened_on'             => '2026-01-01',
        ];

        $controller = $this->makeController('POST', $postData);
        $response   = $controller->store();

        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $response);

        // Check account in DB
        $account = $this->db->table('bank_account')->where('name', 'General Fund Checking')->get()->getRowArray();
        $this->assertNotNull($account);
        $this->assertSame(301, (int) $account['parish_id']);
        $this->assertSame('USD', $account['currency_code']);
        $this->assertEquals(2500.50, (float) $account['opening_balance']);

        // Check audit log
        $audit = $this->db->table('audit_log')->where('action', 'banking.account.create')->get()->getRowArray();
        $this->assertNotNull($audit);
        $this->assertSame(301, (int) $audit['parish_id']);
        $this->assertSame((int) $account['id'], (int) $audit['entity_id']);
    }

    public function testStoreValidatesRequiredFields(): void
    {
        $postData = [
            'name'          => '', // empty name
            'currency_code' => 'INVALID',
        ];

        $controller = $this->makeController('POST', $postData);
        $response   = $controller->store();

        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $response);
        $this->assertSame(0, $this->db->table('bank_account')->countAllResults());
    }

    public function testViewShowsAccountDetail(): void
    {
        $accId = $this->insertAccount(301, 'Operating');
        $this->insertTransaction($accId, '2026-09-01', 500.0);

        $controller = $this->makeController('GET');
        $output     = $controller->view($accId);

        $this->assertIsString($output);
        $this->assertStringContainsString('Operating', $output);
    }

    public function testViewBlocksCrossParishAccess(): void
    {
        $accId302 = $this->insertAccount(302, 'Other Parish Account');

        $controller = $this->makeController('GET');
        $response   = $controller->view($accId302);

        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $response);
    }

    // -----------------------------------------------------------------------
    // Tests: Transactions
    // -----------------------------------------------------------------------

    public function testStoreTransactionAddsRowAndLogsAudit(): void
    {
        $accId = $this->insertAccount(301, 'Operating');

        $postData = [
            'transaction_date' => '2026-09-15',
            'amount'           => '350.25',
            'description'      => 'Pledge Deposit',
            'reference'        => 'DEP-101',
        ];

        $controller = $this->makeController('POST', $postData);
        $response   = $controller->storeTransaction($accId);

        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $response);

        $txn = $this->db->table('bank_transaction')->where('bank_account_id', $accId)->get()->getRowArray();
        $this->assertNotNull($txn);
        $this->assertSame('unmatched', $txn['status']);
        $this->assertEquals(350.25, (float) $txn['amount']);
        $this->assertSame('Pledge Deposit', $txn['description']);

        $audit = $this->db->table('audit_log')->where('action', 'banking.transaction.create')->get()->getRowArray();
        $this->assertNotNull($audit);
        $this->assertSame(301, (int) $audit['parish_id']);
    }

    // -----------------------------------------------------------------------
    // Tests: Reconciliation Lifecycle
    // -----------------------------------------------------------------------

    public function testStartReconciliationEnforcesSingleOpenRule(): void
    {
        $accId = $this->insertAccount(301, 'Operating');

        $postData = [
            'statement_date'    => '2026-09-30',
            'statement_balance' => '5000.00',
        ];

        // Start first reconciliation
        $controller1 = $this->makeController('POST', $postData);
        $response1   = $controller1->startReconciliation($accId);
        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $response1);

        $recon = $this->db->table('bank_reconciliation')->where('bank_account_id', $accId)->get()->getRowArray();
        $this->assertNotNull($recon);
        $this->assertSame('open', $recon['status']);

        // Attempting to open a second reconciliation on same account fails
        $controller2 = $this->makeController('POST', $postData);
        $response2   = $controller2->startReconciliation($accId);
        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $response2);

        $this->assertSame(1, $this->db->table('bank_reconciliation')->where('bank_account_id', $accId)->countAllResults());
    }

    public function testSaveReconciliationItemsSyncsClearedStatus(): void
    {
        $accId = $this->insertAccount(301, 'Operating');
        $txnA  = $this->insertTransaction($accId, '2026-09-05', 100.00);
        $txnB  = $this->insertTransaction($accId, '2026-09-10', 50.00);

        $reconId = model(BankReconciliationModel::class)->insert([
            'bank_account_id'   => $accId,
            'statement_date'    => '2026-09-30',
            'statement_balance' => 150.00,
            'status'            => 'open',
        ]);

        // Clear only txnA
        $controller = $this->makeController('POST', ['cleared' => [(string) $txnA]]);
        $response   = $controller->saveReconciliationItems($reconId);

        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $response);

        $statusA = $this->db->table('bank_transaction')->where('id', $txnA)->get()->getRow('status');
        $statusB = $this->db->table('bank_transaction')->where('id', $txnB)->get()->getRow('status');
        $this->assertSame('cleared', $statusA);
        $this->assertSame('unmatched', $statusB);
    }

    public function testCloseReconciliationRejectsUnbalanced(): void
    {
        $accId = $this->insertAccount(301, 'Operating');
        $txnA  = $this->insertTransaction($accId, '2026-09-05', 100.00, 'cleared');

        $reconId = model(BankReconciliationModel::class)->insert([
            'bank_account_id'   => $accId,
            'statement_date'    => '2026-09-30',
            'statement_balance' => 500.00, // Unbalanced: cleared total is 100, statement is 500
            'status'            => 'open',
        ]);

        $this->db->table('bank_reconciliation_item')->insert([
            'bank_reconciliation_id' => $reconId,
            'bank_transaction_id'    => $txnA,
            'is_cleared'             => 1,
            'created_at'             => date('Y-m-d H:i:s'),
        ]);

        $controller = $this->makeController('POST');
        $response   = $controller->closeReconciliation($reconId);

        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $response);

        $recon = $this->db->table('bank_reconciliation')->where('id', $reconId)->get()->getRowArray();
        $this->assertSame('open', $recon['status'], 'Unbalanced reconciliation must remain open.');
    }

    public function testCloseReconciliationClosesBalancedSessionAndMarksTransactionsReconciled(): void
    {
        $accId = $this->insertAccount(301, 'Operating');
        $txnA  = $this->insertTransaction($accId, '2026-09-05', 100.00, 'cleared');
        $txnB  = $this->insertTransaction($accId, '2026-09-10', 50.00, 'cleared');

        $reconId = model(BankReconciliationModel::class)->insert([
            'bank_account_id'   => $accId,
            'statement_date'    => '2026-09-30',
            'statement_balance' => 150.00, // Balanced: 100 + 50 = 150
            'status'            => 'open',
        ]);

        foreach ([$txnA, $txnB] as $txnId) {
            $this->db->table('bank_reconciliation_item')->insert([
                'bank_reconciliation_id' => $reconId,
                'bank_transaction_id'    => $txnId,
                'is_cleared'             => 1,
                'created_at'             => date('Y-m-d H:i:s'),
            ]);
        }

        $controller = $this->makeController('POST');
        $response   = $controller->closeReconciliation($reconId);

        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $response);

        // Verify reconciliation is closed
        $recon = $this->db->table('bank_reconciliation')->where('id', $reconId)->get()->getRowArray();
        $this->assertSame('closed', $recon['status']);
        $this->assertSame($this->userId, (int) $recon['closed_by']);
        $this->assertNotEmpty($recon['closed_at']);

        // Verify transactions transitioned to reconciled
        $statusA = $this->db->table('bank_transaction')->where('id', $txnA)->get()->getRow('status');
        $statusB = $this->db->table('bank_transaction')->where('id', $txnB)->get()->getRow('status');
        $this->assertSame('reconciled', $statusA);
        $this->assertSame('reconciled', $statusB);

        // Verify audit log
        $audit = $this->db->table('audit_log')->where('action', 'banking.reconciliation.close')->get()->getRowArray();
        $this->assertNotNull($audit);
        $this->assertSame(301, (int) $audit['parish_id']);
    }

    public function testCrossParishReconciliationAccessIsBlocked(): void
    {
        $acc302 = $this->insertAccount(302, 'Other Parish Account');
        $recon302Id = model(BankReconciliationModel::class)->insert([
            'bank_account_id'   => $acc302,
            'statement_date'    => '2026-09-30',
            'statement_balance' => 100.00,
            'status'            => 'open',
        ]);

        // Current session parish is 301
        $controller = $this->makeController('GET');
        $response   = $controller->reconciliation($recon302Id);

        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $response);
    }
}
