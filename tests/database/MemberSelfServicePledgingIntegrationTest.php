<?php

namespace Tests\Database;

use App\Libraries\DataEncryption;
use App\Models\AnnualPledgeModel;
use App\Models\DonationModel;
use App\Services\PledgeFulfillmentService;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use MemberPortal\Controllers\MyDashboard;
use Tests\Support\Database\CanonicalTestSchema;
use Tests\Support\Database\SystemSettingsTestHelper;

/**
 * Integration tests for Member Self-Service Pledging (Priority 3.9).
 *
 * @internal
 */
final class MemberSelfServicePledgingIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    private int $memberUserId;
    private int $parishId = 301;

    protected function setUp(): void
    {
        parent::setUp();

        helper(['auth', 'form', 'url', 'finance']);
        Services::reset(true);

        CanonicalTestSchema::ensure($this->db);
        SystemSettingsTestHelper::createCoreAuthTables($this->db);
        SystemSettingsTestHelper::createSystemSettingsTableAndSeed($this->db);
        SystemSettingsTestHelper::createAuditLogTable($this->db);

        foreach (['parish', 'users', 'profile', 'audit_log'] as $table) {
            CanonicalTestSchema::reset($this->db, $table);
        }

        $this->createPledgeAndFinanceTables();

        $this->seedParish($this->parishId, 'St. Jude Parish');

        $this->memberUserId = $this->insertUser('member.jane', 'jane@example.com');
        $this->insertProfile($this->memberUserId, $this->parishId, 'Jane', 'Doe');

        $this->injectMemberAuth($this->memberUserId);
        session()->set('selected_parish_id', $this->parishId);
    }

    protected function tearDown(): void
    {
        Services::reset(true);
        parent::tearDown();
    }

    public function testMemberCanSubmitAnnualPledgeForCurrentYear(): void
    {
        $currentYear = (int) date('Y');
        $controller = $this->makeController('POST', [
            'pledge_year'    => $currentYear,
            'pledged_amount' => '1200.00',
            'notes'          => 'Faithful annual commitment',
        ]);

        $response = $controller->submitPledge();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(site_url('me'), $response->getHeaderLine('Location'));

        $pledgeModel = new AnnualPledgeModel();
        $pledge = $pledgeModel->getPledgeForYear($this->memberUserId, $this->parishId, $currentYear);

        $this->assertNotNull($pledge);
        $this->assertSame($this->memberUserId, (int) $pledge['user_id']);
        $this->assertSame($this->parishId, (int) $pledge['parish_id']);
        $this->assertSame($currentYear, (int) $pledge['pledge_year']);
        $this->assertEqualsWithDelta(1200.00, (float) $pledge['pledged_amount'], 0.01);
        $this->assertEqualsWithDelta(0.00, (float) $pledge['fulfilled_amount'], 0.01);
        $this->assertSame('pledged', $pledge['fulfillment_status']);
        $this->assertSame('Faithful annual commitment', $pledge['notes']);

        // Check audit log
        $auditRow = $this->db->table('audit_log')
            ->where('action', 'annual_pledge.member_submit')
            ->where('entity_type', 'annual_pledge')
            ->where('entity_id', (int) $pledge['id'])
            ->get()
            ->getRowArray();

        $this->assertNotNull($auditRow);
        $this->assertSame($this->parishId, (int) $auditRow['parish_id']);
        $this->assertSame($this->memberUserId, (int) $auditRow['user_id']);
    }

    public function testMemberSubmittingPledgeWithExistingTithesInstantlyReflectsFulfillment(): void
    {
        $currentYear = (int) date('Y');
        $fundId = $this->insertFund($this->parishId, 'Tithe Fund');

        // Existing tithe donation of $400 for current year
        $this->insertDonation($this->parishId, $fundId, $this->memberUserId, '400.00', $currentYear . '-03-10');

        $controller = $this->makeController('POST', [
            'pledge_year'    => $currentYear,
            'pledged_amount' => '1000.00',
            'notes'          => 'Pledge with prior tithes',
        ]);

        $response = $controller->submitPledge();
        $this->assertInstanceOf(RedirectResponse::class, $response);

        $pledgeModel = new AnnualPledgeModel();
        $pledge = $pledgeModel->getPledgeForYear($this->memberUserId, $this->parishId, $currentYear);

        $this->assertNotNull($pledge);
        $this->assertEqualsWithDelta(1000.00, (float) $pledge['pledged_amount'], 0.01);
        $this->assertEqualsWithDelta(400.00, (float) $pledge['fulfilled_amount'], 0.01);
        $this->assertSame('partial', $pledge['fulfillment_status']);
    }

    public function testMemberCanUpdateExistingActivePledgeAmountAndNotes(): void
    {
        $currentYear = (int) date('Y');
        $fundId = $this->insertFund($this->parishId, 'Tithe Fund');
        $this->insertDonation($this->parishId, $fundId, $this->memberUserId, '500.00', $currentYear . '-04-01');

        $pledgeModel = new AnnualPledgeModel();
        $pledgeId = $pledgeModel->insert([
            'user_id'            => $this->memberUserId,
            'parish_id'          => $this->parishId,
            'pledge_year'        => $currentYear,
            'pledged_amount'     => 1000.00,
            'fulfilled_amount'   => 500.00,
            'pledged_at'         => $currentYear . '-01-01',
            'fulfillment_status' => 'partial',
            'notes'              => 'Original note',
        ]);

        // Member updates pledge amount down to 500.00 (which is now 100% fulfilled!)
        $controller = $this->makeController('POST', [
            'pledge_year'    => $currentYear,
            'pledged_amount' => '500.00',
            'notes'          => 'Adjusted note',
        ]);

        $response = $controller->submitPledge();
        $this->assertInstanceOf(RedirectResponse::class, $response);

        // Ensure no duplicate rows created
        $allPledges = $pledgeModel->getMemberPledges($this->memberUserId, $this->parishId);
        $this->assertCount(1, $allPledges);

        $updated = $pledgeModel->find($pledgeId);
        $this->assertEqualsWithDelta(500.00, (float) $updated['pledged_amount'], 0.01);
        $this->assertEqualsWithDelta(500.00, (float) $updated['fulfilled_amount'], 0.01);
        $this->assertSame('fulfilled', $updated['fulfillment_status']);
        $this->assertSame('Adjusted note', $updated['notes']);

        // Check audit log for member_update
        $auditRow = $this->db->table('audit_log')
            ->where('action', 'annual_pledge.member_update')
            ->where('entity_type', 'annual_pledge')
            ->where('entity_id', $pledgeId)
            ->get()
            ->getRowArray();

        $this->assertNotNull($auditRow);
    }

    public function testMemberCannotUpdateCancelledPledge(): void
    {
        $currentYear = (int) date('Y');
        $pledgeModel = new AnnualPledgeModel();
        $pledgeId = $pledgeModel->insert([
            'user_id'            => $this->memberUserId,
            'parish_id'          => $this->parishId,
            'pledge_year'        => $currentYear,
            'pledged_amount'     => 1000.00,
            'fulfilled_amount'   => 0.00,
            'pledged_at'         => $currentYear . '-01-01',
            'fulfillment_status' => 'cancelled',
            'notes'              => 'Cancelled by office',
        ]);

        $controller = $this->makeController('POST', [
            'pledge_year'    => $currentYear,
            'pledged_amount' => '1500.00',
            'notes'          => 'Trying to reactivate',
        ]);

        $response = $controller->submitPledge();
        $this->assertInstanceOf(RedirectResponse::class, $response);

        // Verify pledge was NOT changed
        $pledge = $pledgeModel->find($pledgeId);
        $this->assertEqualsWithDelta(1000.00, (float) $pledge['pledged_amount'], 0.01);
        $this->assertSame('cancelled', $pledge['fulfillment_status']);
    }

    public function testValidationRejectsPastYearAndNonPositiveAmount(): void
    {
        $controller = $this->makeController('POST', [
            'pledge_year'    => 2015,
            'pledged_amount' => '-100.00',
        ]);

        $response = $controller->submitPledge();
        $this->assertInstanceOf(RedirectResponse::class, $response);

        $pledgeModel = new AnnualPledgeModel();
        $this->assertNull($pledgeModel->getPledgeForYear($this->memberUserId, $this->parishId, 2015));
    }

    public function testUnauthenticatedUserCannotSubmitPledge(): void
    {
        $this->injectLoggedOutAuth();

        $controller = $this->makeController('POST', [
            'pledge_year'    => (int) date('Y'),
            'pledged_amount' => '500.00',
        ]);

        $response = $controller->submitPledge();
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(site_url('login'), $response->getHeaderLine('Location'));
    }

    public function testAjaxSubmissionFollowsApiContract(): void
    {
        $currentYear = (int) date('Y');

        // Successful AJAX submission
        $controller = $this->makeController('POST', [
            'pledge_year'    => $currentYear,
            'pledged_amount' => '650.00',
            'notes'          => 'AJAX pledge',
        ], [], true);

        $response = $controller->submitPledge();
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getBody(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('success', $data['status']);
        $this->assertSame(200, $data['meta']['http_status']);
        $this->assertSame(650.00, (float) $data['data']['pledged_amount']);

        // Validation failure AJAX submission
        $badController = $this->makeController('POST', [
            'pledge_year'    => 1999,
            'pledged_amount' => '0',
        ], [], true);

        $badResponse = $badController->submitPledge();
        $this->assertSame(422, $badResponse->getStatusCode());

        $badData = json_decode((string) $badResponse->getBody(), true);
        $this->assertFalse($badData['success']);
        $this->assertSame('error', $badData['status']);
        $this->assertSame('VALIDATION_FAILED', $badData['error']['code']);
        $this->assertNotEmpty($badData['errors']);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeController(string $method = 'GET', array $post = [], array $get = [], bool $isAjax = false): MyDashboard
    {
        $request = service('request');
        $request->setMethod($method);

        if ($isAjax) {
            $request->setHeader('X-Requested-With', 'XMLHttpRequest');
        }

        if ($method === 'POST') {
            $request->setGlobal('post', $post);
            $request->setGlobal('request', $post);
        }

        if ($get !== []) {
            $request->setGlobal('get', $get);
        }

        $controller = new MyDashboard();
        $controller->initController($request, service('response'), service('logger'));

        return $controller;
    }

    private function createPledgeAndFinanceTables(): void
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

    private function insertProfile(int $userId, int $parishId, string $first = 'Member', string $last = 'User'): void
    {
        $this->db->table('profile')->insert([
            'user_id'    => $userId,
            'parish_id'  => $parishId,
            'person_id'  => $userId + 1000,
            'first_name' => $first,
            'last_name'  => $last,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
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

    private function insertDonation(int $parishId, int $fundId, int $userId, string $amount, string $date): int
    {
        $encrypted = DataEncryption::encrypt($amount);
        $this->db->table('donation')->insert([
            'parish_id'      => $parishId,
            'fund_id'        => $fundId,
            'user_id'        => $userId,
            'amount'         => $encrypted,
            'date'           => $date,
            'category'       => 'tithe',
            'payment_method' => 'cash',
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
            'deleted_at'     => null,
        ]);

        return (int) $this->db->insertID();
    }

    private function injectMemberAuth(int $userId): void
    {
        $fakeUser = new class ($userId) extends User {
            public function __construct(private int $userIdForTest)
            {
                $this->attributes['id']       = $userIdForTest;
                $this->attributes['username'] = 'member.' . $userIdForTest;
                $this->attributes['email']    = 'member.' . $userIdForTest . '@example.com';
            }

            public function can(string ...$permissions): bool
            {
                return false;
            }

            public function inGroup(string ...$groups): bool
            {
                return in_array('member', $groups, true);
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
