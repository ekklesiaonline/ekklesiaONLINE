<?php

namespace Tests\Database;

use App\Libraries\DataEncryption;
use App\Models\DonationModel;
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
 * Member Portal giving statement and donation receipt integration tests:
 * PDF generation, year filtering, receipt view, cross-user boundary, and audit logging.
 *
 * @internal
 */
final class MemberGivingStatementIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    private int $memberUserId;
    private int $otherUserId;

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

        $this->createFinanceTables();

        $this->seedParish(301, 'St. Jude Parish');
        $this->seedParish(302, 'Grace Church');

        $this->memberUserId = $this->insertUser('john.member', 'john@example.com');
        $this->insertProfile($this->memberUserId, 301, 'John', 'Doe');

        $this->otherUserId = $this->insertUser('jane.other', 'jane@example.com');
        $this->insertProfile($this->otherUserId, 301, 'Jane', 'Smith');

        $this->injectMemberAuth($this->memberUserId);
        session()->set('selected_parish_id', 301);
    }

    protected function tearDown(): void
    {
        Services::reset(true);
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Statement Download Tests
    // -----------------------------------------------------------------------

    public function testMemberCanDownloadAnnualStatementPdf(): void
    {
        $fundId = $this->insertFund(301, 'General Fund');
        $this->insertDonation(301, $fundId, $this->memberUserId, '250.00', date('Y-02-15'));
        $this->insertDonation(301, $fundId, $this->memberUserId, '150.00', date('Y-03-20'));

        $response = $this->makeController('GET')->statement();

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame('application/pdf', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('giving_statement_' . date('Y') . '_' . $this->memberUserId, $response->getHeaderLine('Content-Disposition'));

        $body = (string) $response->getBody();
        $this->assertStringStartsWith('%PDF-', $body);

        // Verify audit log
        $auditRow = $this->db->table('audit_log')
            ->where('action', 'donation.statement_pdf')
            ->where('entity_type', 'member_statement')
            ->where('entity_id', $this->memberUserId)
            ->get()
            ->getRowArray();

        $this->assertNotNull($auditRow);
        $this->assertSame(301, (int) $auditRow['parish_id']);
        $this->assertSame($this->memberUserId, (int) $auditRow['user_id']);

        $meta = json_decode((string) ($auditRow['new_values'] ?? '{}'), true);
        // BaseController uses audit()->log which puts meta into log payload
    }

    public function testMemberCanDownloadStatementForPastYear(): void
    {
        $fundId = $this->insertFund(301, 'Building Fund');
        $this->insertDonation(301, $fundId, $this->memberUserId, '500.00', '2025-06-15');

        $response = $this->makeController('GET', [], ['year' => '2025'])->statement();

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame('application/pdf', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('giving_statement_2025_' . $this->memberUserId, $response->getHeaderLine('Content-Disposition'));

        $body = (string) $response->getBody();
        $this->assertStringStartsWith('%PDF-', $body);
    }

    // -----------------------------------------------------------------------
    // Receipt View Tests
    // -----------------------------------------------------------------------

    public function testMemberCanViewReceiptForOwnDonation(): void
    {
        $fundId = $this->insertFund(301, 'General Fund');
        $donId  = $this->insertDonation(301, $fundId, $this->memberUserId, '350.00', date('Y-04-10'));
        $this->insertReceipt($donId, 301, 'REC-2026-0001', $this->memberUserId);

        $output = $this->makeController('GET')->receipt($donId);

        $this->assertIsString($output);
        $this->assertStringContainsString('REC-2026-0001', $output);
        $this->assertStringContainsString('350.00', $output);
        $this->assertStringContainsString('General Fund', $output);
        $this->assertStringContainsString('St. Jude Parish', $output);
        $this->assertStringContainsString('John Doe', $output);

        // Verify audit log
        $auditRow = $this->db->table('audit_log')
            ->where('action', 'donation.view_receipt')
            ->where('entity_type', 'donation_receipt')
            ->where('user_id', $this->memberUserId)
            ->get()
            ->getRowArray();

        $this->assertNotNull($auditRow);
        $this->assertSame(301, (int) $auditRow['parish_id']);
    }

    public function testMemberCannotViewReceiptOfAnotherMember(): void
    {
        // Donation belongs to memberUserId
        $fundId = $this->insertFund(301, 'General Fund');
        $donId  = $this->insertDonation(301, $fundId, $this->memberUserId, '100.00', date('Y-05-01'));
        $this->insertReceipt($donId, 301, 'REC-2026-0002', $this->memberUserId);

        // Attempt access as otherUserId
        $this->injectMemberAuth($this->otherUserId);

        $response = $this->makeController('GET')->receipt($donId);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testMemberReceiptWithoutReceiptIssuedRedirectsWithError(): void
    {
        $fundId = $this->insertFund(301, 'General Fund');
        $donId  = $this->insertDonation(301, $fundId, $this->memberUserId, '75.00', date('Y-05-15'));
        // No receipt inserted

        $response = $this->makeController('GET')->receipt($donId);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testUnauthenticatedAccessRedirectsToLogin(): void
    {
        $this->injectLoggedOutAuth();

        $response = $this->makeController('GET')->statement();
        $this->assertInstanceOf(RedirectResponse::class, $response);

        $response2 = $this->makeController('GET')->receipt(999);
        $this->assertInstanceOf(RedirectResponse::class, $response2);
    }

    public function testDonationModelIncludesReceiptNumberInMemberDonations(): void
    {
        $fundId = $this->insertFund(301, 'Tithe Fund');
        $donId1 = $this->insertDonation(301, $fundId, $this->memberUserId, '200.00', '2026-07-01');
        $donId2 = $this->insertDonation(301, $fundId, $this->memberUserId, '100.00', '2026-06-01');
        $this->insertReceipt($donId1, 301, 'REC-TITHE-01', $this->memberUserId);

        $donations = (new DonationModel())->getMemberDonationsForParish($this->memberUserId, 301);

        $this->assertNotEmpty($donations);
        $first = $donations[0];
        $this->assertSame('REC-TITHE-01', $first['receipt_number']);

        $second = $donations[1];
        $this->assertNull($second['receipt_number']);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeController(string $method = 'GET', array $post = [], array $get = []): MyDashboard
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

        $controller = new MyDashboard();
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
            'donation_receipts' => '(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                donation_id INTEGER NOT NULL,
                parish_id INTEGER NOT NULL,
                receipt_number TEXT NOT NULL,
                issued_by INTEGER NOT NULL,
                issued_at TEXT NOT NULL,
                deleted_at TEXT NULL
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

    private function insertDonation(int $parishId, int $fundId, int $userId, string $amount, string $date): int
    {
        $encrypted = DataEncryption::encrypt($amount);
        $this->db->table('donation')->insert([
            'parish_id'        => $parishId,
            'fund_id'          => $fundId,
            'user_id'          => $userId,
            'amount'           => $encrypted,
            'date'             => $date,
            'category'         => 'tithe',
            'payment_method'   => 'bank_transfer',
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
            'deleted_at'       => null,
        ]);

        return (int) $this->db->insertID();
    }

    private function insertReceipt(int $donationId, int $parishId, string $receiptNumber, int $issuedBy): int
    {
        $this->db->table('donation_receipts')->insert([
            'donation_id'    => $donationId,
            'parish_id'      => $parishId,
            'receipt_number' => $receiptNumber,
            'issued_by'      => $issuedBy,
            'issued_at'      => date('Y-m-d H:i:s'),
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
