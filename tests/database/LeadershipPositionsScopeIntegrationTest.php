<?php

use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Authentication\AuthenticatorInterface;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Result;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * Regression test for the "scope_type must be one of: parish,diocese,province"
 * error when saving a new leadership position from a parish admin's portal.
 *
 * @internal
 */
final class LeadershipPositionsScopeIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

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

    public function testDioceseAdminCanOpenDiocesanLeadershipWithoutSelectedParish(): void
    {
        $dioceseId = $this->insertDiocese('Leadership Admin Diocese');
        $adminId = $this->insertUser('leadership.diocese.admin');
        $this->upsertProfile($adminId, 0, $dioceseId);
        $this->injectLeadershipAuth($adminId, 'dioceseadmin');

        $response = $this->get('leadership?scope=diocese');

        $response->assertOK();
        $response->assertSee(lang('Leadership.positionsTitle'));
    }

    public function testDioceseAdminListingExcludesAnotherDioceseAndRejectsForeignSelectedParish(): void
    {
        $ownDiocese = $this->insertDiocese('Leadership Own Diocese');
        $otherDiocese = $this->insertDiocese('Leadership Other Diocese');
        $ownParish = $this->insertParish($ownDiocese, 'Leadership Own Parish');
        $otherParish = $this->insertParish($otherDiocese, 'Leadership Other Parish');
        $adminId = $this->insertUser('leadership.scoped.diocese.admin');
        $this->upsertProfile($adminId, $ownParish, $ownDiocese);
        $this->injectLeadershipAuth($adminId, 'dioceseadmin');

        foreach ([[$ownDiocese, 'Own Diocesan Office'], [$otherDiocese, 'Foreign Diocesan Office']] as [$diocese, $title]) {
            $subject = $this->insertUser('subject.' . strtolower(str_replace(' ', '.', $title)));
            $this->upsertProfile($subject, 0, $diocese);
            $this->db->table('leadership_position')->insert([
                'user_id' => $subject, 'scope_type' => 'diocese',
                'diocese_id' => $diocese, 'title' => $title, 'active' => 1,
            ]);
        }

        $response = $this->get('leadership?scope=diocese');
        $response->assertOK();
        $response->assertSee('Own Diocesan Office');
        $response->assertDontSee('Foreign Diocesan Office');

        $foreignParishResponse = $this->withSession(['selected_parish_id' => $otherParish])
            ->get('leadership?scope=parish');
        $foreignParishResponse->assertStatus(403);
    }

    public function testOtherParishOperatorRoutesRemainClosedToDiocesanAdmin(): void
    {
        $dioceseId = $this->insertDiocese('Boundary Diocese');
        $adminId = $this->insertUser('leadership.boundary.diocese.admin');
        $this->upsertProfile($adminId, 0, $dioceseId);
        $this->injectLeadershipAuth($adminId, 'dioceseadmin');

        $response = $this->get('members');
        self::assertSame('/dashboard', parse_url((string) $response->getRedirectUrl(), PHP_URL_PATH));
    }

    public function testDioceseAdminWithoutAnAssignedDioceseCannotUseSelectedParishAsJurisdiction(): void
    {
        $dioceseId = $this->insertDiocese('Unassigned Diocese');
        $parishId = $this->insertParish($dioceseId, 'Unassigned Parish');
        $adminId = $this->insertUser('leadership.unassigned.diocese.admin');
        $this->upsertProfile($adminId, 0, 0);
        $this->injectLeadershipAuth($adminId, 'dioceseadmin');

        $response = $this->withSession(['selected_parish_id' => $parishId])
            ->get('leadership?scope=diocese');

        $response->assertStatus(403);
    }

    public function testDioceseAdminCanCreateOwnParishPositionButNotForeignParishPosition(): void
    {
        $dioceseId = $this->insertDiocese('Appointment Diocese');
        $otherDioceseId = $this->insertDiocese('Foreign Appointment Diocese');
        $parishId = $this->insertParish($dioceseId, 'Appointment Parish');
        $otherParishId = $this->insertParish($otherDioceseId, 'Foreign Appointment Parish');
        $adminId = $this->insertUser('appointment.diocese.admin');
        $this->upsertProfile($adminId, $parishId, $dioceseId);
        $this->injectLeadershipAuth($adminId, 'dioceseadmin');

        $memberId = $this->insertUser('appointment.warden.member');
        $this->upsertProfile($memberId, $parishId, $dioceseId);
        $this->db->table('person')->insert(['first_name' => 'Lay', 'last_name' => 'Warden']);
        $personId = (int) $this->db->insertID();
        $this->db->table('user_person')->insert([
            'user_id' => $memberId, 'person_id' => $personId, 'is_primary' => 1,
        ]);
        $this->db->table('profile')->where('user_id', $memberId)->update(['person_id' => $personId]);
        $this->db->table('person_membership_details')->insert([
            'person_id' => $personId, 'parish_id' => $parishId,
            'is_baptized' => 1, 'good_standing_override' => 1,
        ]);

        $payload = [
            'user_id' => $memberId, 'scope_type' => 'parish', 'title' => 'Parish Warden',
            'role_category' => 'lay_staff', 'role_code' => 'warden', 'active' => 1,
        ];
        $allowed = $this->withSession(['selected_parish_id' => $parishId])
            ->withHeaders($this->withCsrfHeaders([]))->withBodyFormat('form')
            ->post('leadership/store', $payload);
        self::assertSame('/leadership', parse_url((string) $allowed->getRedirectUrl(), PHP_URL_PATH));
        self::assertNull(session('error'));

        $denied = $this->withSession(['selected_parish_id' => $otherParishId])
            ->withHeaders($this->withCsrfHeaders([]))->withBodyFormat('form')
            ->post('leadership/store', $payload);
        self::assertNotNull(session('error'), (string) $denied->getRedirectUrl());

        $positions = $this->db->table('leadership_position')->where('user_id', $memberId)->get()->getResultArray();
        self::assertCount(1, $positions);
        self::assertSame($parishId, (int) $positions[0]['parish_id']);
    }

    public function testStoringParishScopeLeadershipPositionSucceeds(): void
    {
        $dioceseId = $this->insertDiocese('Leadership Test Diocese');
        $parishId = $this->insertParish($dioceseId, 'Leadership Test Parish');

        $adminId = $this->insertUser('leadership.scope.admin');
        $this->upsertProfile($adminId, $parishId, $dioceseId);
        $this->injectLeadershipAuth($adminId);

        $memberId = $this->insertUser('leadership.scope.member');
        $this->upsertProfile($memberId, $parishId, $dioceseId);
        $this->db->table('person')->insert(['first_name' => 'Warden', 'last_name' => 'Test']);
        $personId = (int) $this->db->insertID();
        $this->db->table('user_person')->insert([
            'user_id' => $memberId, 'person_id' => $personId, 'is_primary' => 1,
        ]);
        $this->db->table('profile')->where('user_id', $memberId)->update(['person_id' => $personId]);
        $this->db->table('person_membership_details')->insert([
            'person_id' => $personId, 'parish_id' => $parishId,
            'is_baptized' => 1, 'good_standing_override' => 1,
        ]);

        $response = $this->withHeaders($this->withCsrfHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
        ]))->withBodyFormat('form')->post('leadership/store', [
            'user_id' => $memberId,
            'scope_type' => 'parish',
            'title' => 'Parish Warden',
            'role_category' => 'lay_staff',
            'role_code' => 'warden',
            'active' => 1,
        ]);

        $this->assertSame('/leadership', parse_url((string) $response->getRedirectUrl(), PHP_URL_PATH), (string) $response->getRedirectUrl());
        $this->assertNull(session('error'));

        $stored = $this->db->table('leadership_position')
            ->where('user_id', $memberId)
            ->get()
            ->getRowArray();

        $this->assertNotNull($stored);
        $this->assertSame('parish', $stored['scope_type']);
        $this->assertSame($parishId, (int) $stored['parish_id']);
    }

    public function testStoringPositionForMemberWithoutPortalAccessIsRejected(): void
    {
        $dioceseId = $this->insertDiocese('Leadership NoPortal Diocese');
        $parishId = $this->insertParish($dioceseId, 'Leadership NoPortal Parish');

        $adminId = $this->insertUser('leadership.noportal.admin');
        $this->upsertProfile($adminId, $parishId, $dioceseId);
        $this->injectLeadershipAuth($adminId);

        // Non-portal person shape: a users row exists (so is_not_unique
        // passes) but there is no login identity and no enabled access.
        $memberId = $this->insertUserRowOnly('leadership.noportal.member');
        $this->upsertProfile($memberId, $parishId, $dioceseId, 'none');

        $this->withHeaders($this->withCsrfHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
        ]))->withBodyFormat('form')->post('leadership/store', [
            'user_id' => $memberId,
            'scope_type' => 'parish',
            'title' => 'Parish Warden',
            'role_category' => 'clergy',
            'active' => 1,
        ]);

        $this->assertNotNull(session('error'));
        $this->assertNull(
            $this->db->table('leadership_position')->where('user_id', $memberId)->get()->getRowArray()
        );
    }

    public function testStoringLayPositionWithoutGoodStandingIsRejected(): void
    {
        $dioceseId = $this->insertDiocese('Leadership Lay Diocese');
        $parishId = $this->insertParish($dioceseId, 'Leadership Lay Parish');

        $adminId = $this->insertUser('leadership.lay.admin');
        $this->upsertProfile($adminId, $parishId, $dioceseId);
        $this->injectLeadershipAuth($adminId);

        // Portal-enabled member with no baptism/standing data — a lay
        // appointment must fail the standing block even though portal
        // access is fine.
        $memberId = $this->insertUser('leadership.lay.member');
        $this->upsertProfile($memberId, $parishId, $dioceseId);

        $this->withHeaders($this->withCsrfHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
        ]))->withBodyFormat('form')->post('leadership/store', [
            'user_id' => $memberId,
            'scope_type' => 'parish',
            'title' => 'Vestry Member',
            'active' => 1,
        ]);

        $this->assertNotNull(session('error'));
        $this->assertNull(
            $this->db->table('leadership_position')->where('user_id', $memberId)->get()->getRowArray()
        );
    }

    public function testReactivatingPositionForMemberWithoutPortalAccessIsRejected(): void
    {
        $dioceseId = $this->insertDiocese('Leadership Reactivate Diocese');
        $parishId = $this->insertParish($dioceseId, 'Leadership Reactivate Parish');

        $adminId = $this->insertUser('leadership.reactivate.admin');
        $this->upsertProfile($adminId, $parishId, $dioceseId);
        $this->injectLeadershipAuth($adminId);

        $memberId = $this->insertUserRowOnly('leadership.reactivate.member');
        $this->upsertProfile($memberId, $parishId, $dioceseId, 'none');

        $this->db->table('leadership_position')->insert([
            'user_id' => $memberId,
            'scope_type' => 'parish',
            'parish_id' => $parishId,
            'title' => 'Former Warden',
            'role_category' => 'clergy',
            'active' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $positionId = (int) $this->db->insertID();

        $this->withHeaders($this->withCsrfHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
        ]))->withBodyFormat('form')->post('leadership/update/' . $positionId, [
            'title' => 'Former Warden',
            'active' => 1,
        ]);

        $this->assertNotNull(session('error'));

        $row = $this->db->table('leadership_position')->where('id', $positionId)->get()->getRowArray();
        $this->assertNotNull($row);
        $this->assertSame(0, (int) $row['active']);
    }

    private function withCsrfHeaders(array $headers): array
    {
        $headers[csrf_header()] = csrf_hash();

        return $headers;
    }

    private function injectLeadershipAuth(int $userId, string $group = 'parishadmin'): void
    {
        $fakeUser = new class($userId, $group) extends User {
            public function __construct(private int $idForTest, private string $groupForTest)
            {
                parent::__construct([
                    'id' => $idForTest,
                    'username' => 'leadership.scope.user.' . $idForTest,
                    'email' => 'leadership.scope.' . $idForTest . '@example.org',
                ]);
            }

            public function can(string ...$permissions): bool
            {
                foreach ($permissions as $permission) {
                    if (! in_array($permission, ['member.edit', 'leadership_position.manage'], true)) {
                        return false;
                    }
                }

                return true;
            }

            public function inGroup(string ...$groups): bool
            {
                return in_array($this->groupForTest, $groups, true);
            }
        };

        $auth = new class(config('Auth'), $fakeUser, $userId) extends Auth {
            public function __construct($config, private User $fakeUser, private int $testUserId)
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
                return $this->testUserId;
            }

            public function user(): ?User
            {
                return $this->fakeUser;
            }
        };

        Services::injectMock('auth', $auth);
    }

    private function insertDiocese(string $name): int
    {
        $provinceId = $this->insertProvince($name . ' Province');

        $this->db->table('diocese')->insert([
            'province_id' => $provinceId,
            'name' => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertProvince(string $name): int
    {
        $this->db->table('province')->insert([
            'name' => $name,
            'province_number' => '1',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertParish(int $dioceseId, string $name): int
    {
        $this->db->table('parish')->insert([
            'diocese_id' => $dioceseId,
            'name' => $name,
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
        $this->db->table('auth_identities')->insert([
            'user_id' => $userId,
            'type' => 'email_password',
            'secret' => strtolower($username) . '@example.test',
            'secret2' => password_hash('StrongPass123', PASSWORD_DEFAULT),
            'force_reset' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $userId;
    }

    private function insertUserRowOnly(string $username): int
    {
        $this->db->table('users')->insert([
            'username' => $username,
            'active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function upsertProfile(int $userId, int $parishId, int $dioceseId, string $portalAccess = 'enabled'): void
    {
        $this->db->table('profile')->insert([
            'user_id' => $userId,
            'parish_id' => $parishId,
            'diocese_id' => $dioceseId,
            'first_name' => 'Test',
            'last_name' => 'User',
            'portal_access' => $portalAccess,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function ensureTablesExist(): void
    {
        $tables = $this->db->listTables();
        $prefix = $this->db->getPrefix();

        if (!in_array($prefix . 'province', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'province (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                province_number TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }

        if (!in_array($prefix . 'diocese', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'diocese (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                province_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        if (!in_array($prefix . 'parish', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'parish (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                diocese_id INTEGER NULL,
                name TEXT NOT NULL,
                email TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        if (!in_array($prefix . 'users', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NULL,
                active INTEGER DEFAULT 1,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        if (!in_array($prefix . 'auth_identities', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'auth_identities (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                type TEXT NOT NULL,
                secret TEXT NULL,
                secret2 TEXT NULL,
                force_reset INTEGER DEFAULT 0,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        if (!in_array($prefix . 'profile', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'profile (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                parish_id INTEGER NULL,
                diocese_id INTEGER NULL,
                province_id INTEGER NULL,
                first_name TEXT NULL,
                last_name TEXT NULL,
                portal_access TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        if (!in_array($prefix . 'leadership_position', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'leadership_position (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                person_id INTEGER NULL,
                scope_type TEXT NOT NULL,
                parish_id INTEGER NULL,
                diocese_id INTEGER NULL,
                province_id INTEGER NULL,
                title TEXT NOT NULL,
                role_code TEXT NULL,
                employment_type TEXT NULL,
                role_category TEXT NULL,
                clergy_order TEXT NULL,
                active INTEGER DEFAULT 1,
                started_at TEXT NULL,
                ended_at TEXT NULL,
                notes TEXT NULL,
                appointed_by_user_id INTEGER NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }
    }
}
