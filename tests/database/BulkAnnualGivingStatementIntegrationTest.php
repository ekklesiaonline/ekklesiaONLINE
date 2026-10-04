<?php

declare(strict_types=1);

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
use Tests\Support\Database\CanonicalTestSchema;
use Tests\Support\Database\SystemSettingsTestHelper;
use Treasurer\Controllers\Donations;
use ZipArchive;

/**
 * Bulk Annual Giving Statement & Tax Letter Integration Tests:
 * Multi-member batch PDF, ZIP archive generation, min-amount filtering,
 * cross-parish scoping, and audit trails.
 *
 * @internal
 */
final class BulkAnnualGivingStatementIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    private int $treasurerUserId;
    private int $donorAliceId;
    private int $donorBobId;

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
        ] as $table) {
            CanonicalTestSchema::reset($this->db, $table);
        }

        $this->createFinanceTables();

        $this->seedParish(301, 'St. Philip Parish', 'TAX-301-999');
        $this->seedParish(302, 'Grace Church Foreign', 'TAX-302-888');

        $this->treasurerUserId = $this->insertUser('treasurer.301', 'treasurer301@example.com');
        $this->insertProfile($this->treasurerUserId, 301, 'Treasurer', 'User');

        $this->donorAliceId = $this->insertUser('alice.donor', 'alice@example.com');
        $this->insertProfile($this->donorAliceId, 301, 'Alice', 'Smith', '123 Main St', 'Santo Domingo', 'DN', '10101');

        $this->donorBobId = $this->insertUser('bob.donor', 'bob@example.com');
        $this->insertProfile($this->donorBobId, 301, 'Bob', 'Jones', '456 Oak Ave', 'Santiago', 'ST', '51000');

        $this->injectTreasurerAuth($this->treasurerUserId, [
            'finance.view',
            'finance.manage',
            'donation.view',
            'donation.create',
        ]);

        session()->set('selected_parish_id', 301);
    }

    protected function tearDown(): void
    {
        Services::reset(true);
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Combined PDF Export
    // -----------------------------------------------------------------------

    public function testBulkAnnualStatementsCombinedPdfExportsAllParishDonorsForYear(): void
    {
        $fundId = $this->insertFund(301, 'General Operating');
        $otherParishFundId = $this->insertFund(302, 'Foreign Fund');

        // Alice 2025 donations in parish 301
        $this->insertDonation(301, $fundId, $this->donorAliceId, '500.00', '2025-03-15');
        $this->insertDonation(301, $fundId, $this->donorAliceId, '250.00', '2025-06-20');

        // Bob 2025 donation in parish 301
        $this->insertDonation(301, $fundId, $this->donorBobId, '1200.00', '2025-11-10');

        // Alice 2024 donation in parish 301 (must not be included in 2025 statements)
        $this->insertDonation(301, $fundId, $this->donorAliceId, '100.00', '2024-12-25');

        // Foreign parish 302 donation (must not leak into parish 301 statements)
        $foreignDonorId = $this->insertUser('charlie.foreign', 'charlie@example.com');
        $this->insertProfile($foreignDonorId, 302, 'Charlie', 'Foreign');
        $this->insertDonation(302, $otherParishFundId, $foreignDonorId, '9000.00', '2025-05-01');

        $response = $this->makeController('GET', [], ['year' => 2025, 'format' => 'pdf'])->batchStatements();

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame('application/pdf', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('Annual_Statements_2025_', $response->getHeaderLine('Content-Disposition'));

        $body = (string) $response->getBody();
        $this->assertStringStartsWith('%PDF-', $body);

        // Audit log verification
        $auditRow = $this->db->table('audit_log')
            ->where('action', 'donation.batch_statements')
            ->where('entity_type', 'batch_giving_statements')
            ->where('entity_id', 2025)
            ->get()
            ->getRowArray();

        $this->assertNotNull($auditRow);
        $this->assertSame(301, (int) $auditRow['parish_id']);
        $this->assertSame($this->treasurerUserId, (int) $auditRow['user_id']);

        $meta = json_decode((string) ($auditRow['new_values'] ?? '{}'), true);
        $this->assertSame('pdf', $meta['format'] ?? null);
        $this->assertSame(2, $meta['donor_count'] ?? null); // Alice and Bob only
        $this->assertSame('[REDACTED]', $meta['total_amount'] ?? null); // Redacted by AuditService
    }

    // -----------------------------------------------------------------------
    // ZIP Archive Export
    // -----------------------------------------------------------------------

    public function testBulkAnnualStatementsZipArchiveContainsIndividualPdfs(): void
    {
        $fundId = $this->insertFund(301, 'Building Fund');
        $this->insertDonation(301, $fundId, $this->donorAliceId, '400.00', '2025-04-12');
        $this->insertDonation(301, $fundId, $this->donorBobId, '800.00', '2025-08-19');

        $response = $this->makeController('GET', [], ['year' => 2025, 'format' => 'zip'])->batchStatements();

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame('application/zip', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('.zip', $response->getHeaderLine('Content-Disposition'));

        $zipData = (string) $response->getBody();
        $this->assertNotEmpty($zipData);

        // Verify ZIP contents using ZipArchive
        $tmpZip = WRITEPATH . 'cache/test_verify_' . uniqid() . '.zip';
        file_put_contents($tmpZip, $zipData);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($tmpZip));
        $this->assertSame(2, $zip->numFiles);

        $alicePdfFound = false;
        $bobPdfFound   = false;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $this->assertIsArray($stat);
            $filename = (string) $stat['name'];

            if (str_contains($filename, 'Alice_Smith_' . $this->donorAliceId)) {
                $alicePdfFound = true;
                $pdfBytes = (string) $zip->getFromIndex($i);
                $this->assertStringStartsWith('%PDF-', $pdfBytes);
            }
            if (str_contains($filename, 'Bob_Jones_' . $this->donorBobId)) {
                $bobPdfFound = true;
                $pdfBytes = (string) $zip->getFromIndex($i);
                $this->assertStringStartsWith('%PDF-', $pdfBytes);
            }
        }

        $zip->close();
        @unlink($tmpZip);

        $this->assertTrue($alicePdfFound, 'Alice PDF statement must exist in ZIP archive');
        $this->assertTrue($bobPdfFound, 'Bob PDF statement must exist in ZIP archive');
    }

    // -----------------------------------------------------------------------
    // Minimum Contribution Filtering
    // -----------------------------------------------------------------------

    public function testBulkAnnualStatementsRespectsMinimumContributionThreshold(): void
    {
        $fundId = $this->insertFund(301, 'Missions');

        // Alice donated $500
        $this->insertDonation(301, $fundId, $this->donorAliceId, '500.00', '2025-01-10');

        // Bob donated $20
        $this->insertDonation(301, $fundId, $this->donorBobId, '20.00', '2025-02-15');

        // Threshold = 100.00 -> only Alice qualifies
        $response = $this->makeController('GET', [], [
            'year'       => 2025,
            'format'     => 'pdf',
            'min_amount' => 100.00,
        ])->batchStatements();

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame('application/pdf', $response->getHeaderLine('Content-Type'));

        $auditRow = $this->db->table('audit_log')
            ->where('action', 'donation.batch_statements')
            ->where('entity_id', 2025)
            ->get()
            ->getRowArray();

        $this->assertNotNull($auditRow);
        $meta = json_decode((string) ($auditRow['new_values'] ?? '{}'), true);
        $this->assertSame(1, $meta['donor_count'] ?? null);
        $this->assertSame('[REDACTED]', $meta['total_amount'] ?? null); // Redacted by AuditService
    }

    // -----------------------------------------------------------------------
    // Empty Period / No Donors Found
    // -----------------------------------------------------------------------

    public function testBulkAnnualStatementsRedirectsWhenNoDonorsFound(): void
    {
        // No donations in year 2020
        $response = $this->makeController('GET', [], ['year' => 2020, 'format' => 'pdf'])->batchStatements();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/donations', $response->getHeaderLine('Location'));
    }

    // -----------------------------------------------------------------------
    // Single Member Statement via Service
    // -----------------------------------------------------------------------

    public function testSingleMemberStatementGeneratesPdf(): void
    {
        $fundId = $this->insertFund(301, 'Tithe');
        $this->insertDonation(301, $fundId, $this->donorAliceId, '350.00', '2025-04-10');

        // Create affiliation for getParishMemberProfile
        $this->db->table('parish_affiliation')->insert([
            'parish_id'         => 301,
            'linked_user_id'    => $this->donorAliceId,
            'membership_status' => 'member',
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        $response = $this->makeController('GET', [], [
            'statement_member_id' => $this->donorAliceId,
            'start_date'          => '2025-01-01',
            'end_date'            => '2025-12-31',
        ])->statement();

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame('application/pdf', $response->getHeaderLine('Content-Type'));
        $this->assertStringStartsWith('%PDF-', (string) $response->getBody());
    }

    // -----------------------------------------------------------------------
    // Cross-Parish Isolation
    // -----------------------------------------------------------------------

    public function testCrossParishAccessIsBlocked(): void
    {
        // Foreign user in parish 302
        $foreignUserId = $this->insertUser('foreign.user', 'foreign@example.com');
        $this->insertProfile($foreignUserId, 302, 'Foreign', 'Member');

        $this->db->table('parish_affiliation')->insert([
            'parish_id'         => 302,
            'linked_user_id'    => $foreignUserId,
            'membership_status' => 'member',
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        // Attempting to request statement for foreign user from parish 301 session
        $response = $this->makeController('GET', [], [
            'statement_member_id' => $foreignUserId,
            'start_date'          => '2025-01-01',
            'end_date'            => '2025-12-31',
        ])->statement();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/donations', $response->getHeaderLine('Location'));
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $get
     */
    private function makeController(string $method = 'GET', array $post = [], array $get = []): Donations
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

        $controller = new Donations();
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

    private function seedParish(int $id, string $name, string $taxId = 'TAX-12345'): void
    {
        $this->db->table('parish')->insert([
            'id'         => $id,
            'name'       => $name,
            'tax_id'     => $taxId,
            'street'     => '100 Cathedral Way',
            'city'       => 'Capital City',
            'state'      => 'CC',
            'zip'        => '00100',
            'phone'      => '809-555-0100',
            'email'      => 'office@parish.org',
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

        $userId = (int) $this->db->insertID();

        // Also insert auth_identities
        $this->db->table('auth_identities')->insert([
            'user_id'    => $userId,
            'type'       => 'email_password',
            'name'       => null,
            'secret'     => $email,
            'secret2'    => 'hashed-password',
            'expires'    => null,
            'extra'      => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $userId;
    }

    private function insertProfile(
        int $userId,
        int $parishId,
        string $first = 'User',
        string $last = 'Name',
        string $address = '',
        string $city = '',
        string $state = '',
        string $zip = ''
    ): void {
        $this->db->table('profile')->insert([
            'user_id'    => $userId,
            'parish_id'  => $parishId,
            'first_name' => $first,
            'last_name'  => $last,
            'address'    => $address,
            'city'       => $city,
            'state'      => $state,
            'zip'        => $zip,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function insertFund(int $parishId, string $name, string $fundType = 'unrestricted'): int
    {
        $this->db->table('fund')->insert([
            'parish_id'  => $parishId,
            'name'       => $name,
            'fund_type'  => $fundType,
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
            'parish_id'        => $parishId,
            'fund_id'          => $fundId,
            'user_id'          => $userId,
            'amount'           => $encrypted,
            'date'             => $date,
            'category'         => 'tithe',
            'payment_method'   => 'check',
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
            'deleted_at'       => null,
        ]);

        return (int) $this->db->insertID();
    }

    /**
     * @param list<string> $permissions
     */
    private function injectTreasurerAuth(int $userId, array $permissions): void
    {
        $fakeUser = new class ($userId, $permissions) extends User {
            /**
             * @param list<string> $perms
             */
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
