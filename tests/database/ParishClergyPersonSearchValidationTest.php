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
 * Regression coverage for the parish/clergy/person-search minimum-length
 * guard (P-1): queries shorter than 2 characters must return a 422
 * QUERY_TOO_SHORT envelope instead of enumerating the diocesan registry.
 *
 * @internal
 */
final class ParishClergyPersonSearchValidationTest extends CIUnitTestCase
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
    }

    protected function tearDown(): void
    {
        Services::reset(true);

        parent::tearDown();
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function shortQueryProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace only' => ['   '];
        yield 'single character' => ['a'];
        yield 'single multibyte character' => ['é'];
    }

    /**
     * @dataProvider shortQueryProvider
     */
    public function testShortQueryIsRejectedWith422Envelope(string $q): void
    {
        $parishId = $this->seedParishAdmin();

        $response = $this->withSession(['selected_parish_id' => $parishId])
            ->get('parish/clergy/person-search?q=' . rawurlencode($q));

        $response->assertStatus(422);

        $json = json_decode($response->getJSON() ?: (string) $response->response()->getBody(), true);
        self::assertIsArray($json);
        self::assertFalse((bool) ($json['success'] ?? true));
        self::assertSame('QUERY_TOO_SHORT', $json['error']['code'] ?? $json['code'] ?? null);
        self::assertStringNotContainsString('"data":[{', (string) $response->response()->getBody());
    }

    public function testTwoCharacterQueryPassesValidation(): void
    {
        $parishId = $this->seedParishAdmin();

        $response = $this->withSession(['selected_parish_id' => $parishId])
            ->get('parish/clergy/person-search?q=ab');

        $response->assertStatus(200);

        $json = json_decode((string) $response->response()->getBody(), true);
        self::assertIsArray($json);
        self::assertTrue((bool) ($json['success'] ?? false));
        self::assertIsArray($json['data'] ?? null);
    }

    private function seedParishAdmin(): int
    {
        $now = date('Y-m-d H:i:s');

        $this->db->table('province')->insert(['name' => 'Search Province', 'province_number' => '1', 'created_at' => $now, 'updated_at' => $now]);
        $provinceId = (int) $this->db->insertID();
        $this->db->table('diocese')->insert(['province_id' => $provinceId, 'name' => 'Search Diocese', 'created_at' => $now, 'updated_at' => $now]);
        $dioceseId = (int) $this->db->insertID();
        $this->db->table('parish')->insert(['diocese_id' => $dioceseId, 'name' => 'Search Parish', 'created_at' => $now, 'updated_at' => $now]);
        $parishId = (int) $this->db->insertID();

        $this->db->table('users')->insert(['username' => 'clergy.search.admin', 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $userId = (int) $this->db->insertID();
        $this->db->table('profile')->insert([
            'user_id' => $userId, 'parish_id' => $parishId, 'diocese_id' => $dioceseId,
            'first_name' => 'Test', 'last_name' => 'User', 'portal_access' => 'enabled',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $this->injectAuth($userId, 'parishadmin');

        return $parishId;
    }

    private function injectAuth(int $userId, string $group): void
    {
        $fakeUser = new class($userId, $group) extends User {
            public function __construct(private int $idForTest, private string $groupForTest)
            {
                parent::__construct([
                    'id' => $idForTest,
                    'username' => 'clergy.search.user.' . $idForTest,
                    'email' => 'clergy.search.' . $idForTest . '@example.org',
                ]);
            }

            public function can(string ...$permissions): bool
            {
                foreach ($permissions as $permission) {
                    if (! in_array($permission, ['member.view', 'leadership_position.manage'], true)) {
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
}
