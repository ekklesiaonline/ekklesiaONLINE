<?php

use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * Regression coverage for the diocese-scoped person picker:
 * GET parish/clergy/person-search (Parish\Controllers\Clergy::personSearch).
 *
 * @internal
 */
final class ParishClergyPersonSearchFeatureTest extends CIUnitTestCase
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
            ['GET', 'parish/clergy/person-search', '\\Parish\\Controllers\\Clergy::personSearch'],
        ]);
    }

    protected function tearDown(): void
    {
        Services::reset(true);

        parent::tearDown();
    }

    public function testPersonSearchRejectsQueryShorterThanTwoCharacters(): void
    {
        [, $dioceseId, $parishId] = $this->insertHierarchy();

        $staffId = $this->insertUser('person.search.staff.short');
        $this->insertProfile($staffId, $parishId, $dioceseId, 'Staff', 'User');
        $this->insertAffiliation($staffId, $parishId, 'member');
        $this->injectAuth($staffId, ['member.view']);

        $response = $this->get('parish/clergy/person-search?q=a');

        $response->assertStatus(422);
        $payload = json_decode($response->getJSON(), true);
        self::assertIsArray($payload);
        self::assertFalse($payload['success']);
        self::assertSame('error', $payload['status']);
        self::assertSame('QUERY_TOO_SHORT', $payload['error']['code']);
        self::assertSame(422, $payload['meta']['http_status']);
    }

    public function testPersonSearchRejectsBlankQuery(): void
    {
        [, $dioceseId, $parishId] = $this->insertHierarchy();

        $staffId = $this->insertUser('person.search.staff.blank');
        $this->insertProfile($staffId, $parishId, $dioceseId, 'Staff', 'Blank');
        $this->insertAffiliation($staffId, $parishId, 'member');
        $this->injectAuth($staffId, ['member.view']);

        $response = $this->get('parish/clergy/person-search');

        $response->assertStatus(422);
        $payload = json_decode($response->getJSON(), true);
        self::assertSame('QUERY_TOO_SHORT', $payload['error']['code'] ?? null);
    }

    public function testPersonSearchForbiddenWithoutMemberView(): void
    {
        [, $dioceseId, $parishId] = $this->insertHierarchy();

        $memberId = $this->insertUser('person.search.member.noperm');
        $this->insertProfile($memberId, $parishId, $dioceseId, 'Plain', 'Member');
        $this->insertAffiliation($memberId, $parishId, 'member');
        $this->injectAuth($memberId, []);

        $response = $this->get('parish/clergy/person-search?q=smith');

        $response->assertStatus(403);
        $payload = json_decode($response->getJSON(), true);
        self::assertIsArray($payload);
        self::assertFalse($payload['success']);
        self::assertSame('error', $payload['status']);
        self::assertSame('SCOPE_FORBIDDEN', $payload['error']['code']);
        self::assertSame(403, $payload['meta']['http_status']);
    }

    public function testPersonSearchForbiddenWithoutParishContext(): void
    {
        [, $dioceseId, $parishId] = $this->insertHierarchy();

        $drifterId = $this->insertUser('person.search.drifter.noparish');
        // No parish_affiliation and no profile parish: operationalParish()
        // cannot resolve a parish, so the endpoint must refuse.
        $this->injectAuth($drifterId, ['member.view']);

        $response = $this->get('parish/clergy/person-search?q=smith');

        $response->assertStatus(403);
        $payload = json_decode($response->getJSON(), true);
        self::assertSame('SCOPE_FORBIDDEN', $payload['error']['code'] ?? null);
    }

    public function testPersonSearchReturnsOnlyDioceseRegistryPersons(): void
    {
        [, $dioceseId, $parishId] = $this->insertHierarchy();
        $otherDioceseId = $this->insertDiocese($this->insertProvince('Other Province'), 'Other Diocese');

        $staffId = $this->insertUser('person.search.staff.scope');
        $this->insertProfile($staffId, $parishId, $dioceseId, 'Staff', 'Scope');
        $this->insertAffiliation($staffId, $parishId, 'member');
        $this->injectAuth($staffId, ['member.view']);

        // In-registry: linked account + canonical status in this diocese.
        $inPersonId = $this->insertPerson('Ada', 'Searchwell');
        $this->linkPersonToUser($inPersonId, $this->insertUser('person.search.in.linked'));
        $this->insertCanonicalStatus($inPersonId, $dioceseId);

        // Same name match but registered in another diocese: must not leak.
        $outPersonId = $this->insertPerson('Abel', 'Searchwell');
        $this->linkPersonToUser($outPersonId, $this->insertUser('person.search.out.linked'));
        $this->insertCanonicalStatus($outPersonId, $otherDioceseId);

        // Ordination evidence but standing elsewhere: also excluded.
        $ordPersonId = $this->insertPerson('Otis', 'Searchwell');
        $this->linkPersonToUser($ordPersonId, $this->insertUser('person.search.ord.linked'));
        $this->insertOrdination($ordPersonId);
        $this->insertCanonicalStatus($ordPersonId, $otherDioceseId);

        // No linked account: the inner join on user_person drops it.
        $unlinkedId = $this->insertPerson('Uma', 'Searchwell');
        $this->insertCanonicalStatus($unlinkedId, $dioceseId);

        $response = $this->get('parish/clergy/person-search?q=searchwell');

        $response->assertStatus(200);
        $payload = json_decode($response->getJSON(), true);
        self::assertIsArray($payload);
        self::assertTrue($payload['success']);
        self::assertSame('success', $payload['status']);
        self::assertSame(200, $payload['meta']['http_status']);

        $ids = array_map(static fn (array $row): int => (int) $row['id'], $payload['data']);
        self::assertSame([$inPersonId], $ids);
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function insertHierarchy(): array
    {
        $provinceId = $this->insertProvince('Search Province');
        $dioceseId = $this->insertDiocese($provinceId, 'Search Diocese');
        $parishId = $this->insertParish($dioceseId, 'Search Parish');

        return [$provinceId, $dioceseId, $parishId];
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

    private function insertDiocese(int $provinceId, string $name): int
    {
        $this->db->table('diocese')->insert([
            'province_id' => $provinceId,
            'name' => $name,
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
            'email' => strtolower(str_replace(' ', '.', $name)) . '@example.org',
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

        return (int) $this->db->insertID();
    }

    private function insertPerson(string $firstName, string $lastName): int
    {
        $this->db->table('person')->insert([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function linkPersonToUser(int $personId, int $userId): void
    {
        $this->db->table('user_person')->insert([
            'person_id' => $personId,
            'user_id' => $userId,
            'is_primary' => 1,
            'linked_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function insertCanonicalStatus(int $personId, int $dioceseId): void
    {
        $this->db->table('clergy_canonical_status')->insert([
            'person_id' => $personId,
            'diocese_id' => $dioceseId,
            'status_code' => 'active',
            'effective_from' => '2020-01-01',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function insertOrdination(int $personId): void
    {
        $this->db->table('clergy_ordination')->insert([
            'person_id' => $personId,
            'order_code' => 'priest',
            'ordained_on' => '2020-01-01',
            'verification_status' => 'verified',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function insertProfile(int $userId, int $parishId, int $dioceseId, string $firstName, string $lastName): void
    {
        $this->db->table('profile')->insert([
            'user_id' => $userId,
            'parish_id' => $parishId,
            'diocese_id' => $dioceseId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'locale' => 'en',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function insertAffiliation(int $userId, int $parishId, string $status): void
    {
        $this->db->table('parish_affiliation')->insert([
            'linked_user_id' => $userId,
            'parish_id' => $parishId,
            'membership_status' => $status,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array<int,string> $permissions
     */
    private function injectAuth(int $userId, array $permissions): void
    {
        $fakeUser = new class($userId, $permissions) extends User {
            /**
             * @param array<int,string> $permissions
             */
            public function __construct(private int $testUserId, private array $permissions)
            {
                parent::__construct([
                    'id' => $testUserId,
                    'username' => 'person.search.feature.' . $testUserId,
                    'email' => 'person.search.feature.' . $testUserId . '@example.org',
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
                return in_array('parishadmin', $groups, true);
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
