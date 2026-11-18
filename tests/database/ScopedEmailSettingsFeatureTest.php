<?php

use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * Regression coverage for the cross-tenant email-settings IDOR: a diocesan or
 * provincial admin must not read, save, or test another diocese/province's
 * scoped email settings — the scope must resolve server-side.
 *
 * @internal
 */
final class ScopedEmailSettingsFeatureTest extends CIUnitTestCase
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
        $this->withRoutes([
            ['GET', 'diocese/email-settings', '\\App\\Controllers\\Diocese\\EmailSettingsController::index'],
            ['POST', 'diocese/email-settings', '\\App\\Controllers\\Diocese\\EmailSettingsController::save'],
            ['POST', 'diocese/email-settings/test', '\\App\\Controllers\\Diocese\\EmailSettingsController::test'],
            ['GET', 'province/email-settings', '\\App\\Controllers\\Province\\EmailSettingsController::index'],
            ['POST', 'province/email-settings', '\\App\\Controllers\\Province\\EmailSettingsController::save'],
            ['POST', 'province/email-settings/test', '\\App\\Controllers\\Province\\EmailSettingsController::test'],
        ]);
    }

    protected function tearDown(): void
    {
        Services::reset(true);

        parent::tearDown();
    }

    public function testDioceseIndexRejectsForeignDiocese(): void
    {
        $dioceseId = $this->insertDiocese($this->insertProvince('P1'), 'Own Diocese');
        $otherDioceseId = $this->insertDiocese($this->insertProvince('P2'), 'Other Diocese');

        $adminId = $this->insertUser('scope.email.d.index');
        $this->insertProfile($adminId, $dioceseId);
        $this->injectAuth($adminId, ['diocese.admin'], ['dioceseadmin']);

        $response = $this->get('diocese/email-settings?diocese_id=' . $otherDioceseId);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function testDioceseSaveRejectsForeignScope(): void
    {
        $dioceseId = $this->insertDiocese($this->insertProvince('P3'), 'Save Diocese');
        $otherDioceseId = $this->insertDiocese($this->insertProvince('P4'), 'Victim Diocese');

        $adminId = $this->insertUser('scope.email.d.save');
        $this->insertProfile($adminId, $dioceseId);
        $this->injectAuth($adminId, ['diocese.admin'], ['dioceseadmin']);

        $response = $this->withHeaders([csrf_header() => csrf_hash()])
            ->post('diocese/email-settings', [
                'scope_type' => 'diocese',
                'scope_id'   => $otherDioceseId,
                'from_name'  => 'Hijacked Sender',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $row = $this->db->table('diocese_setting')
            ->where('diocese_id', $otherDioceseId)
            ->where('key', 'email.from_name')
            ->get()
            ->getRowArray();
        self::assertNull($row, 'Foreign scope settings must not be written');
    }

    public function testDioceseTestRejectsForeignScope(): void
    {
        $dioceseId = $this->insertDiocese($this->insertProvince('P5'), 'Test Diocese');
        $otherDioceseId = $this->insertDiocese($this->insertProvince('P6'), 'Victim Test Diocese');

        $adminId = $this->insertUser('scope.email.d.test');
        $this->insertProfile($adminId, $dioceseId);
        $this->injectAuth($adminId, ['diocese.admin'], ['dioceseadmin']);

        $response = $this->withHeaders([csrf_header() => csrf_hash()])
            ->post('diocese/email-settings/test', [
                'scope_type'     => 'diocese',
                'scope_id'       => $otherDioceseId,
                'test_recipient' => 'attacker@example.org',
            ]);

        $response->assertStatus(403);
        $payload = json_decode($response->getJSON(), true);
        self::assertSame('SCOPE_FORBIDDEN', $payload['error']['code'] ?? null);
    }

    public function testDioceseTestPassesScopeGuardForOwnDiocese(): void
    {
        $dioceseId = $this->insertDiocese($this->insertProvince('P7'), 'Own Test Diocese');

        $adminId = $this->insertUser('scope.email.d.own');
        $this->insertProfile($adminId, $dioceseId);
        $this->injectAuth($adminId, ['diocese.admin'], ['dioceseadmin']);

        // Empty recipient: the scope guard must pass, then sendTest fails on
        // the missing recipient — NOT a 403.
        $response = $this->withHeaders([csrf_header() => csrf_hash()])
            ->post('diocese/email-settings/test', [
                'scope_type' => 'diocese',
                'scope_id'   => $dioceseId,
            ]);

        $payload = json_decode($response->getJSON(), true);
        self::assertNotSame(403, $response->getStatusCode());
        self::assertNotSame('SCOPE_FORBIDDEN', $payload['error']['code'] ?? null);
    }

    public function testProvinceTestRejectsForeignScope(): void
    {
        $provinceId = $this->insertProvince('Own Province');
        $otherProvinceId = $this->insertProvince('Victim Province');

        $adminId = $this->insertUser('scope.email.p.test');
        $this->db->table('profile')->insert([
            'user_id'    => $adminId,
            'province_id' => $provinceId,
            'first_name' => 'Prov',
            'last_name'  => 'Admin',
            'locale'     => 'en',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->injectAuth($adminId, ['province.admin'], ['provinceadmin']);

        $response = $this->withHeaders([csrf_header() => csrf_hash()])
            ->post('province/email-settings/test', [
                'scope_type'     => 'province',
                'scope_id'       => $otherProvinceId,
                'test_recipient' => 'attacker@example.org',
            ]);

        $response->assertStatus(403);
        $payload = json_decode($response->getJSON(), true);
        self::assertSame('SCOPE_FORBIDDEN', $payload['error']['code'] ?? null);
    }

    public function testProvinceIndexRejectsForeignScope(): void
    {
        $provinceId = $this->insertProvince('Own Index Province');
        $otherProvinceId = $this->insertProvince('Victim Index Province');

        $adminId = $this->insertUser('scope.email.p.index');
        $this->db->table('profile')->insert([
            'user_id'    => $adminId,
            'province_id' => $provinceId,
            'first_name' => 'Prov',
            'last_name'  => 'Viewer',
            'locale'     => 'en',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->injectAuth($adminId, ['province.admin'], ['provinceadmin']);

        $response = $this->get('province/email-settings?province_id=' . $otherProvinceId);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function testSystemAdminMayAddressAnyScope(): void
    {
        $otherDioceseId = $this->insertDiocese($this->insertProvince('P8'), 'Arbitrary Diocese');

        $adminId = $this->insertUser('scope.email.sys');
        $this->injectAuth($adminId, ['diocese.admin', 'system.admin'], ['systemadmin']);

        $response = $this->withHeaders([csrf_header() => csrf_hash()])
            ->post('diocese/email-settings/test', [
                'scope_type' => 'diocese',
                'scope_id'   => $otherDioceseId,
            ]);

        $payload = json_decode($response->getJSON(), true);
        self::assertNotSame(403, $response->getStatusCode());
        self::assertNotSame('SCOPE_FORBIDDEN', $payload['error']['code'] ?? null);
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

    private function insertProfile(int $userId, int $dioceseId): void
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
                    'username' => 'scope.email.' . $testUserId,
                    'email'    => 'scope.email.' . $testUserId . '@example.org',
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
