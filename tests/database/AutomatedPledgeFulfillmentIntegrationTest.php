<?php

namespace Tests\Database;

use App\Models\AnnualPledgeModel;
use App\Models\DonationModel;
use App\Services\PledgeFulfillmentService;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * @internal
 */
final class AutomatedPledgeFulfillmentIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    protected function setUp(): void
    {
        parent::setUp();
        helper(['auth', 'form']);
        Services::reset(true);
        $this->ensureTablesExist();
    }

    protected function tearDown(): void
    {
        Services::reset(true);
        parent::tearDown();
    }

    public function testTitheDonationAutomaticallyIncreasesFulfilledAmountAndSetsPartialStatus(): void
    {
        $parishId = $this->insertParish('Grace Church');
        $userId = $this->insertUser('alice_pledge', 'alice@example.com');
        $fundId = $this->insertFund($parishId, 'General Fund');

        $pledgeModel = new AnnualPledgeModel();
        $pledgeId = $pledgeModel->insert([
            'user_id'            => $userId,
            'parish_id'          => $parishId,
            'pledge_year'        => 2026,
            'pledged_amount'     => 1000.00,
            'fulfilled_amount'   => 0.00,
            'fulfillment_status' => 'pledged',
            'pledged_at'         => '2026-01-01',
        ]);

        $this->injectAuthUser($userId);

        // Record a tithe donation of $250
        $donationModel = new DonationModel();
        $donationModel->insert([
            'parish_id' => $parishId,
            'fund_id'   => $fundId,
            'user_id'   => $userId,
            'date'      => '2026-03-15',
            'amount'    => '250.00',
            'category'  => 'tithe',
        ]);

        $pledge = $pledgeModel->find($pledgeId);
        $this->assertNotNull($pledge);
        $this->assertEqualsWithDelta(250.00, (float) $pledge['fulfilled_amount'], 0.01);
        $this->assertSame('partial', $pledge['fulfillment_status']);
    }

    public function testAdditionalTitheDonationFulfillingPledgeSetsFulfilledStatus(): void
    {
        $parishId = $this->insertParish('Trinity Parish');
        $userId = $this->insertUser('bob_pledge', 'bob@example.com');
        $fundId = $this->insertFund($parishId, 'Operating Fund');

        $pledgeModel = new AnnualPledgeModel();
        $pledgeId = $pledgeModel->insert([
            'user_id'            => $userId,
            'parish_id'          => $parishId,
            'pledge_year'        => 2026,
            'pledged_amount'     => 500.00,
            'fulfilled_amount'   => 0.00,
            'fulfillment_status' => 'pledged',
        ]);

        $this->injectAuthUser($userId);
        $donationModel = new DonationModel();

        // First donation: $300 -> partial
        $donationModel->insert([
            'parish_id' => $parishId,
            'fund_id'   => $fundId,
            'user_id'   => $userId,
            'date'      => '2026-04-10',
            'amount'    => '300.00',
            'category'  => 'tithe',
        ]);

        $pledgeAfterFirst = $pledgeModel->find($pledgeId);
        $this->assertEqualsWithDelta(300.00, (float) $pledgeAfterFirst['fulfilled_amount'], 0.01);
        $this->assertSame('partial', $pledgeAfterFirst['fulfillment_status']);

        // Second donation: $200 -> total $500 matches pledged_amount -> fulfilled
        $donationModel->insert([
            'parish_id' => $parishId,
            'fund_id'   => $fundId,
            'user_id'   => $userId,
            'date'      => '2026-05-20',
            'amount'    => '200.00',
            'category'  => 'tithe',
        ]);

        $pledgeAfterSecond = $pledgeModel->find($pledgeId);
        $this->assertEqualsWithDelta(500.00, (float) $pledgeAfterSecond['fulfilled_amount'], 0.01);
        $this->assertSame('fulfilled', $pledgeAfterSecond['fulfillment_status']);

        // Third donation: $100 -> total $600 exceeds pledged_amount -> stays fulfilled
        $donationModel->insert([
            'parish_id' => $parishId,
            'fund_id'   => $fundId,
            'user_id'   => $userId,
            'date'      => '2026-06-15',
            'amount'    => '100.00',
            'category'  => 'tithe',
        ]);

        $pledgeAfterThird = $pledgeModel->find($pledgeId);
        $this->assertEqualsWithDelta(600.00, (float) $pledgeAfterThird['fulfilled_amount'], 0.01);
        $this->assertSame('fulfilled', $pledgeAfterThird['fulfillment_status']);
    }

    public function testNonTitheDonationDoesNotAffectPledge(): void
    {
        $parishId = $this->insertParish('All Saints');
        $userId = $this->insertUser('carol_pledge', 'carol@example.com');
        $fundId = $this->insertFund($parishId, 'Building Fund');

        $pledgeModel = new AnnualPledgeModel();
        $pledgeId = $pledgeModel->insert([
            'user_id'            => $userId,
            'parish_id'          => $parishId,
            'pledge_year'        => 2026,
            'pledged_amount'     => 1200.00,
            'fulfilled_amount'   => 0.00,
            'fulfillment_status' => 'pledged',
        ]);

        $this->injectAuthUser($userId);
        $donationModel = new DonationModel();

        // Donations for offering and building fund
        $donationModel->insert([
            'parish_id' => $parishId,
            'fund_id'   => $fundId,
            'user_id'   => $userId,
            'date'      => '2026-02-14',
            'amount'    => '150.00',
            'category'  => 'offering',
        ]);

        $donationModel->insert([
            'parish_id' => $parishId,
            'fund_id'   => $fundId,
            'user_id'   => $userId,
            'date'      => '2026-03-01',
            'amount'    => '500.00',
            'category'  => 'building fund',
        ]);

        $pledge = $pledgeModel->find($pledgeId);
        $this->assertEqualsWithDelta(0.00, (float) $pledge['fulfilled_amount'], 0.01);
        $this->assertSame('pledged', $pledge['fulfillment_status']);
    }

    public function testChartAccountWithPledgeCategoryOrCodeTriggersFulfillment(): void
    {
        $parishId = $this->insertParish('St Paul Parish');
        $userId = $this->insertUser('david_pledge', 'david@example.com');
        $fundId = $this->insertFund($parishId, 'Ministry Fund');
        $coaId = $this->insertChartAccount('4120', 'Pledge & Tithe Revenue', 'income', 'tithe');

        $pledgeModel = new AnnualPledgeModel();
        $pledgeId = $pledgeModel->insert([
            'user_id'            => $userId,
            'parish_id'          => $parishId,
            'pledge_year'        => 2026,
            'pledged_amount'     => 800.00,
            'fulfilled_amount'   => 0.00,
            'fulfillment_status' => 'pledged',
        ]);

        $this->injectAuthUser($userId);
        $donationModel = new DonationModel();

        // Donation with category = 'other' but chart account = 4120 (Pledge & Tithe)
        $donationModel->insert([
            'parish_id'        => $parishId,
            'fund_id'          => $fundId,
            'user_id'          => $userId,
            'chart_account_id' => $coaId,
            'date'             => '2026-05-01',
            'amount'           => '400.00',
            'category'         => 'other',
        ]);

        $pledge = $pledgeModel->find($pledgeId);
        $this->assertEqualsWithDelta(400.00, (float) $pledge['fulfilled_amount'], 0.01);
        $this->assertSame('partial', $pledge['fulfillment_status']);
    }

    public function testDonationAmountUpdateRecalculatesFulfilledAmount(): void
    {
        $parishId = $this->insertParish('St Luke Parish');
        $userId = $this->insertUser('emma_pledge', 'emma@example.com');
        $fundId = $this->insertFund($parishId, 'General Fund');

        $pledgeModel = new AnnualPledgeModel();
        $pledgeId = $pledgeModel->insert([
            'user_id'            => $userId,
            'parish_id'          => $parishId,
            'pledge_year'        => 2026,
            'pledged_amount'     => 600.00,
            'fulfilled_amount'   => 0.00,
            'fulfillment_status' => 'pledged',
        ]);

        $this->injectAuthUser($userId);
        $donationModel = new DonationModel();

        $donationId = $donationModel->insert([
            'parish_id' => $parishId,
            'fund_id'   => $fundId,
            'user_id'   => $userId,
            'date'      => '2026-04-01',
            'amount'    => '200.00',
            'category'  => 'tithe',
        ]);

        $this->assertEqualsWithDelta(200.00, (float) $pledgeModel->find($pledgeId)['fulfilled_amount'], 0.01);

        // Update amount from 200.00 to 600.00
        $donationModel->update($donationId, [
            'amount' => '600.00',
        ]);

        $pledge = $pledgeModel->find($pledgeId);
        $this->assertEqualsWithDelta(600.00, (float) $pledge['fulfilled_amount'], 0.01);
        $this->assertSame('fulfilled', $pledge['fulfillment_status']);
    }

    public function testDonationCategoryChangeFromTitheToOfferingDecrementsPledge(): void
    {
        $parishId = $this->insertParish('St Matthew');
        $userId = $this->insertUser('frank_pledge', 'frank@example.com');
        $fundId = $this->insertFund($parishId, 'General Fund');

        $pledgeModel = new AnnualPledgeModel();
        $pledgeId = $pledgeModel->insert([
            'user_id'            => $userId,
            'parish_id'          => $parishId,
            'pledge_year'        => 2026,
            'pledged_amount'     => 1000.00,
            'fulfilled_amount'   => 0.00,
            'fulfillment_status' => 'pledged',
        ]);

        $this->injectAuthUser($userId);
        $donationModel = new DonationModel();

        $donationId = $donationModel->insert([
            'parish_id' => $parishId,
            'fund_id'   => $fundId,
            'user_id'   => $userId,
            'date'      => '2026-05-15',
            'amount'    => '350.00',
            'category'  => 'tithe',
        ]);

        $this->assertEqualsWithDelta(350.00, (float) $pledgeModel->find($pledgeId)['fulfilled_amount'], 0.01);

        // Change category to offering
        $donationModel->update($donationId, [
            'category' => 'offering',
        ]);

        $pledge = $pledgeModel->find($pledgeId);
        $this->assertEqualsWithDelta(0.00, (float) $pledge['fulfilled_amount'], 0.01);
        $this->assertSame('pledged', $pledge['fulfillment_status']);
    }

    public function testDonationDateChangeToDifferentYearRecalculatesBothPledges(): void
    {
        $parishId = $this->insertParish('St Andrew');
        $userId = $this->insertUser('grace_pledge', 'grace@example.com');
        $fundId = $this->insertFund($parishId, 'General Fund');

        $pledgeModel = new AnnualPledgeModel();
        $pledge2025Id = $pledgeModel->insert([
            'user_id'            => $userId,
            'parish_id'          => $parishId,
            'pledge_year'        => 2025,
            'pledged_amount'     => 500.00,
            'fulfilled_amount'   => 0.00,
            'fulfillment_status' => 'pledged',
        ]);

        $pledge2026Id = $pledgeModel->insert([
            'user_id'            => $userId,
            'parish_id'          => $parishId,
            'pledge_year'        => 2026,
            'pledged_amount'     => 1000.00,
            'fulfilled_amount'   => 0.00,
            'fulfillment_status' => 'pledged',
        ]);

        $this->injectAuthUser($userId);
        $donationModel = new DonationModel();

        // Donation initially recorded in 2026
        $donationId = $donationModel->insert([
            'parish_id' => $parishId,
            'fund_id'   => $fundId,
            'user_id'   => $userId,
            'date'      => '2026-01-10',
            'amount'    => '200.00',
            'category'  => 'tithe',
        ]);

        $this->assertEqualsWithDelta(200.00, (float) $pledgeModel->find($pledge2026Id)['fulfilled_amount'], 0.01);
        $this->assertEqualsWithDelta(0.00, (float) $pledgeModel->find($pledge2025Id)['fulfilled_amount'], 0.01);

        // Date corrected to 2025-12-30
        $donationModel->update($donationId, [
            'date' => '2025-12-30',
        ]);

        // 2026 pledge should decrease to 0, 2025 pledge should increase to 200
        $pledge2026 = $pledgeModel->find($pledge2026Id);
        $pledge2025 = $pledgeModel->find($pledge2025Id);

        $this->assertEqualsWithDelta(0.00, (float) $pledge2026['fulfilled_amount'], 0.01);
        $this->assertSame('pledged', $pledge2026['fulfillment_status']);

        $this->assertEqualsWithDelta(200.00, (float) $pledge2025['fulfilled_amount'], 0.01);
        $this->assertSame('partial', $pledge2025['fulfillment_status']);
    }

    public function testDonationDeletionDecrementsFulfilledAmount(): void
    {
        $parishId = $this->insertParish('Christ Church');
        $userId = $this->insertUser('henry_pledge', 'henry@example.com');
        $fundId = $this->insertFund($parishId, 'General Fund');

        $pledgeModel = new AnnualPledgeModel();
        $pledgeId = $pledgeModel->insert([
            'user_id'            => $userId,
            'parish_id'          => $parishId,
            'pledge_year'        => 2026,
            'pledged_amount'     => 500.00,
            'fulfilled_amount'   => 0.00,
            'fulfillment_status' => 'pledged',
        ]);

        $this->injectAuthUser($userId);
        $donationModel = new DonationModel();

        $donation1Id = $donationModel->insert([
            'parish_id' => $parishId,
            'fund_id'   => $fundId,
            'user_id'   => $userId,
            'date'      => '2026-02-01',
            'amount'    => '300.00',
            'category'  => 'tithe',
        ]);

        $donation2Id = $donationModel->insert([
            'parish_id' => $parishId,
            'fund_id'   => $fundId,
            'user_id'   => $userId,
            'date'      => '2026-03-01',
            'amount'    => '200.00',
            'category'  => 'tithe',
        ]);

        $this->assertEqualsWithDelta(500.00, (float) $pledgeModel->find($pledgeId)['fulfilled_amount'], 0.01);
        $this->assertSame('fulfilled', $pledgeModel->find($pledgeId)['fulfillment_status']);

        // Delete donation 2
        $donationModel->delete($donation2Id);

        $pledge = $pledgeModel->find($pledgeId);
        $this->assertEqualsWithDelta(300.00, (float) $pledge['fulfilled_amount'], 0.01);
        $this->assertSame('partial', $pledge['fulfillment_status']);
    }

    public function testCancelledPledgePreservesCancelledStatus(): void
    {
        $parishId = $this->insertParish('Epiphany Church');
        $userId = $this->insertUser('isabel_pledge', 'isabel@example.com');
        $fundId = $this->insertFund($parishId, 'General Fund');

        $pledgeModel = new AnnualPledgeModel();
        $pledgeId = $pledgeModel->insert([
            'user_id'            => $userId,
            'parish_id'          => $parishId,
            'pledge_year'        => 2026,
            'pledged_amount'     => 1000.00,
            'fulfilled_amount'   => 0.00,
            'fulfillment_status' => 'cancelled',
        ]);

        $this->injectAuthUser($userId);
        $donationModel = new DonationModel();

        $donationModel->insert([
            'parish_id' => $parishId,
            'fund_id'   => $fundId,
            'user_id'   => $userId,
            'date'      => '2026-05-01',
            'amount'    => '1000.00',
            'category'  => 'tithe',
        ]);

        $pledge = $pledgeModel->find($pledgeId);
        $this->assertEqualsWithDelta(1000.00, (float) $pledge['fulfilled_amount'], 0.01);
        $this->assertSame('cancelled', $pledge['fulfillment_status']);
    }

    public function testCreatingPledgeWithExistingTithesImmediatelyCalculatesFulfilledAmount(): void
    {
        $parishId = $this->insertParish('St Jude Parish');
        $userId = $this->insertUser('jack_pledge', 'jack@example.com');
        $fundId = $this->insertFund($parishId, 'General Fund');

        $this->injectAuthUser($userId);
        $donationModel = new DonationModel();

        // Tithes recorded BEFORE pledge was entered in system
        $donationModel->insert([
            'parish_id' => $parishId,
            'fund_id'   => $fundId,
            'user_id'   => $userId,
            'date'      => '2026-01-15',
            'amount'    => '150.00',
            'category'  => 'tithe',
        ]);
        $donationModel->insert([
            'parish_id' => $parishId,
            'fund_id'   => $fundId,
            'user_id'   => $userId,
            'date'      => '2026-02-15',
            'amount'    => '250.00',
            'category'  => 'tithe',
        ]);

        // Now member enters annual pledge
        $pledgeModel = new AnnualPledgeModel();
        $pledgeId = $pledgeModel->insert([
            'user_id'            => $userId,
            'parish_id'          => $parishId,
            'pledge_year'        => 2026,
            'pledged_amount'     => 1000.00,
            'fulfilled_amount'   => 0.00,
            'fulfillment_status' => 'pledged',
        ]);

        /** @var PledgeFulfillmentService $fulfillmentService */
        $fulfillmentService = service('pledgeFulfillment');
        $updatedPledge = $fulfillmentService->recalculatePledge($userId, $parishId, 2026);

        $this->assertNotNull($updatedPledge);
        $this->assertEqualsWithDelta(400.00, (float) $updatedPledge['fulfilled_amount'], 0.01);
        $this->assertSame('partial', $updatedPledge['fulfillment_status']);
    }

    public function testPledgeFulfillmentServiceSyncParishAndAllPledges(): void
    {
        $parishId = $this->insertParish('Resurrection Parish');
        $userId = $this->insertUser('sync_user', 'sync@example.com');
        $fundId = $this->insertFund($parishId, 'General Fund');

        $pledgeModel = new AnnualPledgeModel();
        $pledgeId = $pledgeModel->insert([
            'user_id'            => $userId,
            'parish_id'          => $parishId,
            'pledge_year'        => 2026,
            'pledged_amount'     => 500.00,
            'fulfilled_amount'   => 0.00,
            'fulfillment_status' => 'pledged',
        ]);

        $this->injectAuthUser($userId);
        $donationModel = new DonationModel();
        $donationModel->insert([
            'parish_id' => $parishId,
            'fund_id'   => $fundId,
            'user_id'   => $userId,
            'date'      => '2026-07-01',
            'amount'    => '500.00',
            'category'  => 'tithe',
        ]);

        /** @var PledgeFulfillmentService $fulfillmentService */
        $fulfillmentService = service('pledgeFulfillment');

        $stats = $fulfillmentService->syncParishPledges($parishId, 2026);
        $this->assertSame(1, $stats['total_checked']);

        $pledge = $pledgeModel->find($pledgeId);
        $this->assertEqualsWithDelta(500.00, (float) $pledge['fulfilled_amount'], 0.01);
        $this->assertSame('fulfilled', $pledge['fulfillment_status']);
    }

    private function injectAuthUser(int $userId): void
    {
        $fakeUser = new class($userId) extends User {
            public function __construct(private int $idForTest)
            {
                parent::__construct([
                    'id'       => $idForTest,
                    'username' => 'user' . $idForTest,
                    'email'    => 'user' . $idForTest . '@example.com',
                ]);
            }

            public function can(string ...$permissions): bool
            {
                return true;
            }

            public function inGroup(string ...$groups): bool
            {
                return true;
            }
        };

        $auth = new class(config('Auth'), $fakeUser, $userId) extends \CodeIgniter\Shield\Auth {
            public function __construct($config, private User $fakeUser, private int $userId)
            {
                parent::__construct($config);
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
                return $this->userId;
            }

            public function user(): ?User
            {
                return $this->fakeUser;
            }
        };

        Services::injectMock('auth', $auth);
    }

    private function insertUser(string $username, string $email): int
    {
        $this->db->table('users')->insert([
            'username'   => $username,
            'email'      => $email,
            'active'     => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertParish(string $name): int
    {
        $this->db->table('parish')->insert([
            'name'       => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertFund(int $parishId, string $name): int
    {
        $this->db->table('fund')->insert([
            'parish_id'  => $parishId,
            'name'       => $name,
            'fund_type'  => 'unrestricted',
            'is_active'  => 1,
            'is_locked'  => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertChartAccount(string $code, string $name, string $type, string $category): int
    {
        $this->db->table('chart_of_accounts')->insert([
            'code'         => $code,
            'name'         => $name,
            'account_type' => $type,
            'category'     => $category,
            'allow_budget' => 1,
            'is_active'    => 1,
            'sort_order'   => 10,
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function ensureTablesExist(): void
    {
        $prefix = $this->db->getPrefix();

        $definitions = [
            'users' => '(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NULL,
                email TEXT NULL,
                active INTEGER DEFAULT 1,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )',
            'profile' => '(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                parish_id INTEGER NULL,
                first_name TEXT NULL,
                last_name TEXT NULL,
                locale TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )',
            'parish' => '(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )',
            'chart_of_accounts' => '(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT NOT NULL,
                name TEXT NOT NULL,
                account_type TEXT NOT NULL,
                category TEXT NULL,
                description TEXT NULL,
                allow_budget INTEGER DEFAULT 0,
                is_active INTEGER DEFAULT 1,
                sort_order INTEGER DEFAULT 0,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )',
            'fund' => '(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                parish_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                description TEXT NULL,
                fund_type TEXT NOT NULL,
                restriction_purpose TEXT NULL,
                chart_account_id INTEGER NULL,
                is_active INTEGER DEFAULT 1,
                is_locked INTEGER DEFAULT 0,
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
            'annual_pledge' => '(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                person_id INTEGER NULL,
                parish_id INTEGER NOT NULL,
                pledge_year INTEGER NOT NULL,
                pledged_amount REAL NULL,
                fulfilled_amount REAL NOT NULL DEFAULT 0.00,
                pledged_at TEXT NULL,
                fulfillment_status TEXT NOT NULL DEFAULT \'pledged\',
                status_id INTEGER NULL,
                notes TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )',
            'donation_receipts' => '(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                parish_id INTEGER NOT NULL,
                donation_id INTEGER NOT NULL,
                receipt_number TEXT NOT NULL,
                issued_at TEXT NOT NULL,
                issued_by INTEGER NOT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )',
        ];

        foreach ($definitions as $table => $definition) {
            $this->db->query('DROP TABLE IF EXISTS "' . $prefix . $table . '"');
            $this->db->query('CREATE TABLE "' . $prefix . $table . '" ' . $definition);
        }
    }
}
