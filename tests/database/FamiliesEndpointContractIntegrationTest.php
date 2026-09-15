<?php

use App\Services\MemberAccessService;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Authentication\AuthenticatorInterface;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Result;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * @internal
 */
final class FamiliesEndpointContractIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    protected $migrate = true;
    protected $refresh = true;

    protected function setUp(): void
    {
        parent::setUp();

        helper('auth');
        helper('form');
        Services::reset(true);
        $this->ensureTablesExist();
    }

    protected function tearDown(): void
    {
        Services::reset(true);
        parent::tearDown();
    }

    public function testAddMemberNonPortalMalformedJsonReturnsBadRequestJsonContract(): void
    {
        $parishId = $this->insertParish('Malformed JSON Parish');
        $userId = $this->insertUser('family.contract.malformed');
        $this->upsertProfile($userId, $parishId);
        $this->injectFamiliesManageAuth($userId);

        $response = $this->withHeaders($this->withCsrfHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Request-Id' => 'req_bad_json_1',
        ]))->withBody('{"family_id":')->post('families/addMemberNonPortal');

        $response->assertStatus(400);
        $payload = $this->decodePayload($response->getJSON());
        $this->assertEnvelope($payload, false);
        $this->assertSame('BAD_REQUEST_JSON', (string) ($payload['code'] ?? ''));
        $this->assertSame('req_bad_json_1', (string) ($payload['meta']['request_id'] ?? ''));
    }

    public function testAddMemberNonPortalValidationPriorityWinsOverNotFoundAndScopeChecks(): void
    {
        $parishId = $this->insertParish('Validation Priority Parish');
        $userId = $this->insertUser('family.contract.validation');
        $this->upsertProfile($userId, $parishId);
        $this->injectFamiliesManageAuth($userId);

        $response = $this->withHeaders($this->withCsrfHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ]))->withBody(json_encode([
            'family_id' => 999999,
            'first_name' => 'A',
            'last_name' => 'ValidLast',
            'portal_access' => 'none',
        ]))->post('families/addMemberNonPortal');

        $response->assertStatus(422);
        $payload = $this->decodePayload($response->getJSON());
        $this->assertEnvelope($payload, false);
        $this->assertSame('VALIDATION_FAILED', (string) ($payload['code'] ?? ''));
    }

    public function testAddMemberNonPortalReturnsOutOfScopeForCrossParishFamily(): void
    {
        $parishA = $this->insertParish('Scope Parish A');
        $parishB = $this->insertParish('Scope Parish B');
        $familyInB = $this->insertFamily($parishB, 'Family B');

        $userId = $this->insertUser('family.contract.scope');
        $this->upsertProfile($userId, $parishA);
        $this->injectFamiliesManageAuth($userId);

        $response = $this->withHeaders($this->withCsrfHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ]))->withBody(json_encode([
            'family_id' => $familyInB,
            'first_name' => 'Scoped',
            'last_name' => 'User',
            'portal_access' => 'none',
        ]))->post('families/addMemberNonPortal');

        $response->assertStatus(403);
        $payload = $this->decodePayload($response->getJSON());
        $this->assertEnvelope($payload, false);
        $this->assertSame('OUT_OF_SCOPE', (string) ($payload['code'] ?? ''));
    }

    public function testAddMemberNonPortalReturnsFamilyNotFoundWhenFamilyMissingInScope(): void
    {
        $parishId = $this->insertParish('Family Missing Parish');
        $userId = $this->insertUser('family.contract.notfound');
        $this->upsertProfile($userId, $parishId);
        $this->injectFamiliesManageAuth($userId);

        $response = $this->withHeaders($this->withCsrfHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ]))->withBody(json_encode([
            'family_id' => 123456,
            'first_name' => 'Known',
            'last_name' => 'Missing',
            'portal_access' => 'none',
        ]))->post('families/addMemberNonPortal');

        $response->assertStatus(404);
        $payload = $this->decodePayload($response->getJSON());
        $this->assertEnvelope($payload, false);
        $this->assertSame('FAMILY_NOT_FOUND', (string) ($payload['code'] ?? ''));
    }

    public function testAddMemberNonPortalRequiresFamilyIdWhenOmitted(): void
    {
        $parishId = $this->insertParish('Standalone Non Portal Parish');
        $adminId = $this->insertUser('family.contract.standalone.admin');
        $this->upsertProfile($adminId, $parishId);
        $this->injectFamiliesManageAuth($adminId);

        $response = $this->withHeaders($this->withCsrfHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ]))->withBody(json_encode([
            'first_name' => 'Solo',
            'last_name' => 'Member',
            'portal_access' => 'none',
        ]))->post('families/addMemberNonPortal');

        $response->assertStatus(422);
        $payload = $this->decodePayload($response->getJSON());
        $this->assertEnvelope($payload, false);
        $this->assertSame('VALIDATION_FAILED', (string) ($payload['code'] ?? ''));
    }

    public function testEnablePortalReturnsConflictForAlreadyEnabledIdentity(): void
    {
        $parishId = $this->insertParish('Conflict Parish');
        $familyId = $this->insertFamily($parishId, 'Conflict Family');

        $adminId = $this->insertUser('family.contract.conflict.admin');
        $this->upsertProfile($adminId, $parishId);

        $memberId = $this->insertUser('family.contract.conflict.member');
        $this->upsertProfile($memberId, $parishId, 'none');
        $this->insertAffiliation($memberId, $parishId);
        $this->insertFamilyMember($familyId, $memberId, 'Child');
        $this->insertEmailIdentity($memberId, 'already@enabled.test');

        $this->injectFamiliesManageAuth($adminId);

        $response = $this->withHeaders($this->withCsrfHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ]))->withBody(json_encode([
            'family_id' => $familyId,
            'email' => 'already@enabled.test',
        ]))->post('families/enablePortal/' . $memberId);

        $response->assertStatus(409);
        $payload = $this->decodePayload($response->getJSON());
        $this->assertEnvelope($payload, false);
        $this->assertSame('PORTAL_ACCESS_ALREADY_ENABLED', (string) ($payload['code'] ?? ''));
    }

    public function testEnablePortalReturnsUserNotFoundWhenUserDoesNotExist(): void
    {
        $parishId = $this->insertParish('User Missing Parish');
        $familyId = $this->insertFamily($parishId, 'User Missing Family');

        $adminId = $this->insertUser('family.contract.missing.admin');
        $this->upsertProfile($adminId, $parishId);
        $this->injectFamiliesManageAuth($adminId);

        $requestId = 'req-enable-user-not-found';
        $response = $this->withHeaders($this->withCsrfHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Request-Id' => $requestId,
        ]))->withBody(json_encode([
            'family_id' => $familyId,
            'email' => 'missing.user@example.org',
        ]))->post('families/enablePortal/999999');

        $response->assertStatus(404);
        $payload = $this->decodePayload($response->getJSON());
        $this->assertEnvelope($payload, false);
        $this->assertSame('USER_NOT_FOUND', (string) ($payload['code'] ?? ''));
        $this->assertSame($requestId, (string) ($payload['meta']['request_id'] ?? ''));
        $this->assertSame($parishId, (int) ($payload['meta']['parish_id'] ?? 0));
    }

    public function testAddMemberNonPortalReturnsInternalErrorWhenServiceFailsUnexpectedly(): void
    {
        $parishId = $this->insertParish('Internal Error Parish');
        $familyId = $this->insertFamily($parishId, 'Internal Error Family');

        $adminId = $this->insertUser('family.contract.internal.admin');
        $this->upsertProfile($adminId, $parishId);
        $this->injectFamiliesManageAuth($adminId);

        $failingService = new class($this->db) extends MemberAccessService {
            public function createNonPortalUser(array $payload, int $parishId): array
            {
                return [
                    'ok' => false,
                    'http_status' => 500,
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Unexpected failure.',
                    'errors' => [],
                    'data' => null,
                ];
            }
        };
        Services::injectMock('memberAccess', $failingService);

        $response = $this->withHeaders($this->withCsrfHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ]))->withBody(json_encode([
            'family_id' => $familyId,
            'first_name' => 'Internal',
            'last_name' => 'Failure',
            'portal_access' => 'none',
        ]))->post('families/addMemberNonPortal');

        $response->assertStatus(500);
        $payload = $this->decodePayload($response->getJSON());
        $this->assertEnvelope($payload, false);
        $this->assertSame('INTERNAL_ERROR', (string) ($payload['code'] ?? ''));
    }

    /**
     * @param string|false $json
     * @return array<string,mixed>
     */
    private function decodePayload($json): array
    {
        $decoded = json_decode((string) $json, true);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function assertEnvelope(array $payload, bool $success): void
    {
        $this->assertSame(
            ['success', 'code', 'message', 'data', 'errors', 'meta'],
            array_keys($payload),
            'Envelope key ordering mismatch.'
        );

        $this->assertIsBool($payload['success']);
        $this->assertSame($success, (bool) $payload['success']);
        $this->assertIsString($payload['code']);
        $this->assertIsString($payload['message']);
        $this->assertArrayHasKey('meta', $payload);
        $this->assertIsArray($payload['meta']);
        $this->assertArrayHasKey('request_id', $payload['meta']);
        $this->assertArrayHasKey('timestamp', $payload['meta']);
        $this->assertArrayHasKey('parish_id', $payload['meta']);

        if ($success) {
            $this->assertIsArray($payload['data']);
            $this->assertSame([], $payload['errors']);
            return;
        }

        $this->assertNull($payload['data']);
        $this->assertIsArray($payload['errors']);
    }

    /**
     * @param array<string,string> $headers
     * @return array<string,string>
     */
    private function withCsrfHeaders(array $headers): array
    {
        $headers[csrf_header()] = csrf_hash();
        return $headers;
    }

    private function injectFamiliesManageAuth(int $userId): void
    {
        $fakeUser = new class($userId) extends User {
            public function __construct(private int $idForTest)
            {
                parent::__construct([
                    'id' => $idForTest,
                    'username' => 'family.contract.user.' . $idForTest,
                    'email' => 'family.contract.' . $idForTest . '@example.org',
                ]);
            }

            public function can(string ...$permissions): bool
            {
                foreach ($permissions as $permission) {
                    if ($permission !== 'family.manage' && $permission !== 'family.view') {
                        return false;
                    }
                }

                return true;
            }

            public function inGroup(string ...$groups): bool
            {
                return false;
            }
        };

        $auth = new class(config('Auth'), $fakeUser, $userId) extends Auth {
            public function __construct($config, private User $fakeUser, private int $userId)
            {
                parent::__construct($config);
            }

            public function getAuthenticator(): AuthenticatorInterface
            {
                return new class($this->fakeUser) implements AuthenticatorInterface {
                    public function __construct(private User $fakeUser) {}

                    public function attempt(array $credentials): Result
                    {
                        return new Result(['success' => false, 'reason' => 'not-used-in-test']);
                    }

                    public function check(array $credentials): Result
                    {
                        return new Result(['success' => false, 'reason' => 'not-used-in-test']);
                    }

                    public function loggedIn(): bool
                    {
                        return true;
                    }

                    public function login(User $user): void {}

                    public function loginById($userId): void {}

                    public function logout(): void {}

                    public function getUser(): ?User
                    {
                        return $this->fakeUser;
                    }

                    public function recordActiveDate(): void {}

                    public function isPending(): bool
                    {
                        return false;
                    }

                    public function getPendingMessage(): string
                    {
                        return '';
                    }

                    public function startUpAction(string $type, User $user): bool
                    {
                        return false;
                    }
                };
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

    private function insertParish(string $name): int
    {
        $this->db->table('parish')->insert([
            'name' => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertFamily(int $parishId, string $familyName): int
    {
        $this->db->table('family')->insert([
            'parish_id' => $parishId,
            'family_name' => $familyName,
            'head_of_household_id' => null,
            'address' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertUser(string $username): int
    {
        $this->db->table('users')->insert([
            'username' => $username,
            'active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $userId = (int) $this->db->insertID();
        $this->insertEmailIdentity($userId, $username . '@example.test');

        return $userId;
    }

    private function upsertProfile(int $userId, int $parishId, string $portalAccess = 'none'): void
    {
        $existing = $this->db->table('profile')->where('user_id', $userId)->get()->getRowArray();
        $data = [
            'parish_id' => $parishId,
            'first_name' => 'Test',
            'last_name' => 'User',
            'membership_status' => 'active',
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($existing === null) {
            $data['user_id'] = $userId;
            $data['created_at'] = date('Y-m-d H:i:s');
            $this->db->table('profile')->insert($data);
            return;
        }

        $this->db->table('profile')->where('user_id', $userId)->update($data);
    }

    private function insertAffiliation(int $userId, int $parishId): void
    {
        $existing = $this->db->table('parish_affiliation')
            ->where('linked_user_id', $userId)
            ->where('parish_id', $parishId)
            ->get()
            ->getRowArray();

        if ($existing !== null) {
            $this->db->table('parish_affiliation')->where('id', $existing['id'])->update([
                'membership_status' => 'member',
                'canonical_status' => 'member',
                'is_member' => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            return;
        }

        $this->db->table('parish_affiliation')->insert([
            'linked_user_id' => $userId,
            'parish_id' => $parishId,
            'person_id' => null,
            'affiliation_type' => 'member',
            'membership_status' => 'member',
            'canonical_status' => 'member',
            'is_member' => 1,
            'is_good_standing' => 0,
            'source' => 'system',
            'start_date' => date('Y-m-d'),
            'end_date' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function insertFamilyMember(int $familyId, int $userId, ?string $relationship = null): void
    {
        $this->db->table('family_member')->insert([
            'family_id' => $familyId,
            'user_id' => $userId,
            'relationship' => $relationship,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function insertEmailIdentity(int $userId, string $email): void
    {
        $this->db->table('auth_identities')->insert([
            'user_id' => $userId,
            'type' => 'email_password',
            'secret' => strtolower(trim($email)),
            'secret2' => password_hash('StrongPass123', PASSWORD_DEFAULT),
            'expires' => null,
            'extra' => null,
            'force_reset' => 0,
            'last_used_at' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function ensureTablesExist(): void
    {
        $prefix = $this->db->getPrefix();
        $tables = $this->db->listTables();

        if (!in_array($prefix . 'parish', $tables, true) && !in_array('parish', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'parish (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                diocese_id INTEGER NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        if (!in_array($prefix . 'users', $tables, true) && !in_array('users', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NULL,
                active INTEGER DEFAULT 1,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        if (!in_array($prefix . 'family', $tables, true) && !in_array('family', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'family (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                parish_id INTEGER NOT NULL,
                family_name TEXT NOT NULL,
                head_of_household_id INTEGER NULL,
                address TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        $familyTable = $this->resolveTable('family');
        $this->ensureColumnExists($familyTable, 'parish_id', 'INTEGER NULL');

        if (!in_array($prefix . 'profile', $tables, true) && !in_array('profile', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'profile (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                parish_id INTEGER NOT NULL,
                first_name TEXT NULL,
                last_name TEXT NULL,
                membership_status TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        $profileTable = $this->resolveTable('profile');
        $this->ensureColumnExists($profileTable, 'parish_id', 'INTEGER NULL');
        $this->ensureColumnExists($profileTable, 'first_name', 'TEXT NULL');
        $this->ensureColumnExists($profileTable, 'last_name', 'TEXT NULL');
        $this->ensureColumnExists($profileTable, 'membership_status', 'TEXT NULL');
        $this->ensureColumnExists($profileTable, 'portal_access', 'TEXT NULL');
        $this->ensureColumnExists($profileTable, 'portal_enabled_at', 'TEXT NULL');

        $prefixedProfile = $prefix . 'profile';
        $this->ensureColumnExists($prefixedProfile, 'parish_id', 'INTEGER NULL');
        $this->ensureColumnExists($prefixedProfile, 'first_name', 'TEXT NULL');
        $this->ensureColumnExists($prefixedProfile, 'last_name', 'TEXT NULL');
        $this->ensureColumnExists($prefixedProfile, 'membership_status', 'TEXT NULL');
        $this->ensureColumnExists($prefixedProfile, 'portal_access', 'TEXT NULL');
        $this->ensureColumnExists($prefixedProfile, 'portal_enabled_at', 'TEXT NULL');

        if (!in_array($prefix . 'parish_affiliation', $tables, true) && !in_array('parish_affiliation', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'parish_affiliation (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                linked_user_id INTEGER NULL,
                parish_id INTEGER NOT NULL,
                person_id INTEGER NULL,
                affiliation_type TEXT DEFAULT "member",
                membership_status TEXT DEFAULT "guest",
                canonical_status TEXT NULL,
                is_member INTEGER DEFAULT 0,
                is_good_standing INTEGER DEFAULT 0,
                source TEXT DEFAULT "system",
                start_date TEXT NULL,
                end_date TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        $affiliationTable = $this->resolveTable('parish_affiliation');
        $this->ensureColumnExists($affiliationTable, 'linked_user_id', 'INTEGER NULL');
        $this->ensureColumnExists($affiliationTable, 'parish_id', 'INTEGER NULL');
        $this->ensureColumnExists($affiliationTable, 'person_id', 'INTEGER NULL');
        $this->ensureColumnExists($affiliationTable, 'affiliation_type', 'TEXT NULL');
        $this->ensureColumnExists($affiliationTable, 'membership_status', 'TEXT NULL');
        $this->ensureColumnExists($affiliationTable, 'canonical_status', 'TEXT NULL');
        $this->ensureColumnExists($affiliationTable, 'is_member', 'INTEGER DEFAULT 0');
        $this->ensureColumnExists($affiliationTable, 'is_good_standing', 'INTEGER DEFAULT 0');
        $this->ensureColumnExists($affiliationTable, 'source', 'TEXT NULL');
        $this->ensureColumnExists($affiliationTable, 'start_date', 'TEXT NULL');
        $this->ensureColumnExists($affiliationTable, 'end_date', 'TEXT NULL');

        $prefixedAffiliation = $prefix . 'parish_affiliation';
        $this->ensureColumnExists($prefixedAffiliation, 'linked_user_id', 'INTEGER NULL');
        $this->ensureColumnExists($prefixedAffiliation, 'parish_id', 'INTEGER NULL');
        $this->ensureColumnExists($prefixedAffiliation, 'person_id', 'INTEGER NULL');

        if (!in_array($prefix . 'family_member', $tables, true) && !in_array('family_member', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'family_member (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                family_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                relationship TEXT NULL,
                created_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        $familyMemberTable = $this->resolveTable('family_member');
        $this->ensureColumnExists($familyMemberTable, 'family_id', 'INTEGER NOT NULL');
        $this->ensureColumnExists($familyMemberTable, 'user_id', 'INTEGER NOT NULL');
        $this->ensureColumnExists($familyMemberTable, 'relationship', 'TEXT NULL');
        $this->ensureColumnExists($familyMemberTable, 'created_at', 'TEXT NULL');

        $prefixedFamilyMember = $prefix . 'family_member';
        $this->ensureColumnExists($prefixedFamilyMember, 'family_id', 'INTEGER NOT NULL');
        $this->ensureColumnExists($prefixedFamilyMember, 'user_id', 'INTEGER NOT NULL');
        $this->ensureColumnExists($prefixedFamilyMember, 'relationship', 'TEXT NULL');
        $this->ensureColumnExists($prefixedFamilyMember, 'created_at', 'TEXT NULL');

        if (!in_array($prefix . 'auth_identities', $tables, true) && !in_array('auth_identities', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'auth_identities (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                type TEXT NOT NULL,
                name TEXT NULL,
                secret TEXT NOT NULL,
                secret2 TEXT NULL,
                expires TEXT NULL,
                extra TEXT NULL,
                force_reset INTEGER DEFAULT 0,
                last_used_at TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        $authTable = $this->resolveTable('auth_identities');
        $this->ensureColumnExists($authTable, 'secret', 'TEXT NOT NULL DEFAULT ""');
        $this->ensureColumnExists($authTable, 'secret2', 'TEXT NULL');
        $this->ensureColumnExists($authTable, 'expires', 'TEXT NULL');
        $this->ensureColumnExists($authTable, 'extra', 'TEXT NULL');
        $this->ensureColumnExists($authTable, 'force_reset', 'INTEGER DEFAULT 0');
        $this->ensureColumnExists($authTable, 'last_used_at', 'TEXT NULL');

        if (!in_array($prefix . 'auth_groups_users', $tables, true) && !in_array('auth_groups_users', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'auth_groups_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                "group" TEXT NOT NULL,
                created_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        if (!in_array($prefix . 'user_registration_request', $tables, true) && !in_array('user_registration_request', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'user_registration_request (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                status TEXT NULL,
                requested_parish_id INTEGER NULL,
                requested_diocese_id INTEGER NULL,
                requested_province_id INTEGER NULL,
                reviewed_by INTEGER NULL,
                reviewed_at TEXT NULL,
                notes TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }

        if (!in_array($prefix . 'user_session', $tables, true) && !in_array('user_session', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'user_session (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                session_token TEXT NOT NULL,
                ip_address TEXT NULL,
                user_agent TEXT NULL,
                device_fingerprint TEXT NULL,
                is_new_device INTEGER DEFAULT 0,
                last_active TEXT NULL,
                created_at TEXT NULL
            )');
        }

        if (!in_array($prefix . 'settings', $tables, true) && !in_array('settings', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                class TEXT NOT NULL,
                `key` TEXT NOT NULL,
                value TEXT NULL,
                type TEXT NULL,
                context TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }
    }

    private function resolveTable(string $base): string
    {
        if ($this->db->tableExists($base)) {
            return $base;
        }

        return $this->db->getPrefix() . $base;
    }

    private function ensureColumnExists(string $table, string $column, string $definition): void
    {
        try {
            $columns = $this->db->getFieldNames($table);
            if (in_array($column, $columns, true)) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }

        try {
            $this->db->query('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
        } catch (\Throwable $e) {
            // Ignore test DB drift limitations.
        }
    }
}
