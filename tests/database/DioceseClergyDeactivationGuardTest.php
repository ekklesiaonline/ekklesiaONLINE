<?php

use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * Regression coverage for clergy account deactivation guards:
 * POST diocese/clergy/{id}/deactivate must refuse self-targets, higher-tier
 * targets, and any account holding an admin group (peer lockout).
 *
 * @internal
 */
final class DioceseClergyDeactivationGuardTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    private int $dioceseId;

    protected function setUp(): void
    {
        parent::setUp();

        helper(['auth', 'form']);
        Services::reset(true);
        $this->withRoutes([
            ['POST', 'diocese/clergy/(:num)/deactivate', '\\Diocese\\Controllers\\ClergyAccounts::deactivate/$1'],
        ]);

        $this->dioceseId = $this->insertDiocese($this->insertProvince('Guard Province'), 'Guard Diocese');
    }

    protected function tearDown(): void
    {
        Services::reset(true);

        parent::tearDown();
    }

    public function testDeactivatingSelfIsDenied(): void
    {
        $adminId = $this->insertClergyUser('clergy.guard.self', $this->dioceseId, ['clergy', 'dioceseadmin']);
        $this->injectAuth($adminId, ['clergy.manage'], ['dioceseadmin']);

        $response = $this->withHeaders([csrf_header() => csrf_hash()])
            ->post('diocese/clergy/' . $adminId . '/deactivate');

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertActive($adminId);
    }

    public function testDeactivatingPeerDiocesanAdminIsDenied(): void
    {
        $actorId = $this->insertUser('clergy.guard.actor');
        $this->insertDioceseProfile($actorId, $this->dioceseId);
        $this->injectAuth($actorId, ['clergy.manage'], ['dioceseadmin']);

        $peerId = $this->insertClergyUser('clergy.guard.peer', $this->dioceseId, ['clergy', 'dioceseadmin']);

        $response = $this->withHeaders([csrf_header() => csrf_hash()])
            ->post('diocese/clergy/' . $peerId . '/deactivate');

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertActive($peerId);
    }

    public function testDeactivatingSystemAdminClergyIsDenied(): void
    {
        $actorId = $this->insertUser('clergy.guard.actor2');
        $this->insertDioceseProfile($actorId, $this->dioceseId);
        $this->injectAuth($actorId, ['clergy.manage'], ['dioceseadmin']);

        $targetId = $this->insertClergyUser('clergy.guard.sysadmin', $this->dioceseId, ['clergy', 'systemadmin']);

        $response = $this->withHeaders([csrf_header() => csrf_hash()])
            ->post('diocese/clergy/' . $targetId . '/deactivate');

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertActive($targetId);
    }

    public function testDeactivatingOrdinaryClergySucceeds(): void
    {
        $actorId = $this->insertUser('clergy.guard.actor3');
        $this->insertDioceseProfile($actorId, $this->dioceseId);
        $this->injectAuth($actorId, ['clergy.manage'], ['dioceseadmin']);

        $targetId = $this->insertClergyUser('clergy.guard.plain', $this->dioceseId, ['clergy']);

        $response = $this->withHeaders([csrf_header() => csrf_hash()])
            ->post('diocese/clergy/' . $targetId . '/deactivate');

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $row = $this->db->table('users')->where('id', $targetId)->get()->getRowArray();
        self::assertSame(0, (int) $row['active']);
    }

    private function assertActive(int $userId): void
    {
        $row = $this->db->table('users')->where('id', $userId)->get()->getRowArray();
        self::assertSame(1, (int) $row['active'], 'users.active must stay 1 when the guard denies');
    }

    /**
     * @param array<int,string> $groups
     */
    private function insertClergyUser(string $username, int $dioceseId, array $groups): int
    {
        $userId = $this->insertUser($username);
        $this->insertDioceseProfile($userId, $dioceseId);
        foreach ($groups as $group) {
            $this->db->table('auth_groups_users')->insert([
                'user_id'    => $userId,
                'group'      => $group,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return $userId;
    }

    private function insertProvince(string $name): int
    {
        $this->db->table('province')->insert([
            'name'            => $name,
            'province_number' => '1',
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertDiocese(int $provinceId, string $name): int
    {
        $this->db->table('diocese')->insert([
            'province_id' => $provinceId,
            'name'        => $name,
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertUser(string $username): int
    {
        $this->db->table('users')->insert([
            'username'   => $username,
            'active'     => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertDioceseProfile(int $userId, int $dioceseId): void
    {
        $this->db->table('profile')->insert([
            'user_id'    => $userId,
            'diocese_id' => $dioceseId,
            'first_name' => 'Dio',
            'last_name'  => 'Admin',
            'locale'     => 'en',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array<int,string> $permissions
     * @param array<int,string> $groups
     */
    private function injectAuth(int $userId, array $permissions, array $groups): void
    {
        $fakeUser = new class($userId, $permissions, $groups) extends User {
            /**
             * @param array<int,string> $permissions
             * @param array<int,string> $groups
             */
            public function __construct(private int $testUserId, private array $permissions, private array $groups)
            {
                parent::__construct([
                    'id'       => $testUserId,
                    'username' => 'clergy.guard.' . $testUserId,
                    'email'    => 'clergy.guard.' . $testUserId . '@example.org',
                ]);
            }

            public function can(string ...$permissions): bool
            {
                foreach ($permissions as $permission) {
                    if (!in_array($permission, $this->permissions, true)) {
                        return false;
                    }
                }

                return true;
            }

            public function inGroup(string ...$groups): bool
            {
                foreach ($groups as $group) {
                    if (in_array($group, $this->groups, true)) {
                        return true;
                    }
                }

                return false;
            }
        };

        $auth = new class(config('Auth'), $fakeUser, $userId) extends Auth {
            public function __construct($config, private User $fakeUser, private int $testUserId)
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
                return $this->testUserId;
            }

            public function user(): ?User
            {
                return $this->fakeUser;
            }
        };

        Services::injectMock('auth', $auth);
    }
}
