<?php

use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * @internal
 */
final class ProfilePicturePrivateDeliveryIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    protected function setUp(): void
    {
        parent::setUp();
        helper('auth');
        Services::reset(true);
        $this->ensureCoreTablesExist();
    }

    protected function tearDown(): void
    {
        Services::reset(true);
        parent::tearDown();
    }

    public function testUnauthorizedAccessToAnotherUserImageReturns404(): void
    {
        $viewerId = $this->insertUser('viewer.unauth');
        $targetId = $this->insertUser('target.unauth');

        $this->insertProfile($viewerId, ['diocese_id' => 10]);
        $storedPath = $this->createProfilePictureFileForUser($targetId);
        $this->insertProfile($targetId, ['diocese_id' => 20, 'profile_picture' => $storedPath]);

        $this->injectAuthUser($viewerId, ['member'], false);

        $controller = new \MemberPortal\Controllers\PublicAssets();
        $controller->initController(service('request'), service('response'), service('logger'));

        $this->expectException(\CodeIgniter\Exceptions\PageNotFoundException::class);
        $controller->profilePicture($targetId);
    }

    public function testAuthorizedSystemAdminAccessReturns200(): void
    {
        $profileColumns = array_map('strtolower', $this->db->getFieldNames($this->resolveTableName('profile')));
        if (!in_array('diocese_id', $profileColumns, true) || !in_array('profile_picture', $profileColumns, true)) {
            $this->markTestSkipped('Profile scope or picture columns unavailable in current schema variant.');
        }

        $viewerId = $this->insertUser('viewer.scope');
        $targetId = $this->insertUser('target.scope');

        $this->insertProfile($viewerId, ['diocese_id' => 77]);
        $storedPath = $this->createProfilePictureFileForUser($targetId, 'same-scope-image');
        $this->insertProfile($targetId, ['diocese_id' => 77, 'profile_picture' => $storedPath]);

        $this->injectAuthUser($viewerId, ['systemadmin'], false);

        $controller = new \MemberPortal\Controllers\PublicAssets();
        $controller->initController(service('request'), service('response'), service('logger'));

        $response = $controller->profilePicture($targetId);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('same-scope-image', (string) $response->getBody());
        $this->assertNotSame('', (string) $response->getHeaderLine('ETag'));
    }

    public function testEtagRevalidationReturns304(): void
    {
        $viewerId = $this->insertUser('viewer.etag');

        $storedPath = $this->createProfilePictureFileForUser($viewerId, 'etag-image');
        $this->insertProfile($viewerId, ['diocese_id' => 55, 'profile_picture' => $storedPath]);

        $this->injectAuthUser($viewerId, ['member'], true);

        $firstController = new \MemberPortal\Controllers\PublicAssets();
        $firstController->initController(service('request'), service('response'), service('logger'));
        $firstResponse = $firstController->profilePicture($viewerId);

        $etag = (string) $firstResponse->getHeaderLine('ETag');
        $this->assertNotSame('', $etag);

        $request = service('request');
        $request->setHeader('If-None-Match', $etag);

        $secondController = new \MemberPortal\Controllers\PublicAssets();
        $secondController->initController($request, service('response'), service('logger'));
        $secondResponse = $secondController->profilePicture($viewerId);

        $this->assertSame(304, $secondResponse->getStatusCode());
        $this->assertSame($etag, (string) $secondResponse->getHeaderLine('ETag'));
    }

    private function injectAuthUser(int $userId, array $groups, bool $canAll): void
    {
        $fakeUser = new class($userId, $groups, $canAll) extends User {
            public function __construct(private int $userIdForTest, private array $groupsForTest, private bool $canAllForTest)
            {
                parent::__construct([
                    'id' => $userIdForTest,
                    'username' => 'test.user.' . $userIdForTest,
                    'email' => 'test.user.' . $userIdForTest . '@example.org',
                ]);
            }

            public function can(string ...$permissions): bool
            {
                return $this->canAllForTest;
            }

            public function inGroup(string ...$groups): bool
            {
                return array_intersect($groups, $this->groupsForTest) !== [];
            }
        };

        $auth = new class(config('Auth'), $fakeUser, $userId) extends Auth {
            public function __construct($config, private User $fakeUser, private int $userIdForTest)
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
                return $this->userIdForTest;
            }

            public function user(): ?User
            {
                return $this->fakeUser;
            }
        };

        Services::injectMock('auth', $auth);
    }

    private function createProfilePictureFileForUser(int $userId, string $contents = 'private-image'): string
    {
        $relativeDir = 'profile/' . $userId;
        $absoluteDir = rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR . $userId;

        if (!is_dir($absoluteDir)) {
            mkdir($absoluteDir, 0755, true);
        }

        $name = sha1((string) $userId . '|' . microtime(true) . '|' . random_int(1, PHP_INT_MAX)) . '.jpg';
        file_put_contents($absoluteDir . DIRECTORY_SEPARATOR . $name, $contents);

        return $relativeDir . '/' . $name;
    }

    private function ensureCoreTablesExist(): void
    {
        $usersTable = $this->createTableName('users');
        if (!$this->tableExists('users')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $usersTable . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NULL,
                email TEXT NULL,
                active INTEGER DEFAULT 1,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        $profilesTable = $this->createTableName('profile');
        if (!$this->tableExists('profile')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $profilesTable . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                parish_id INTEGER NULL,
                province_id INTEGER NULL,
                diocese_id INTEGER NULL,
                first_name TEXT NULL,
                last_name TEXT NULL,
                profile_picture TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        $resolvedProfiles = $this->resolveTableName('profile');
        $this->ensureColumnExists($resolvedProfiles, 'province_id', 'INTEGER NULL');
        $this->ensureColumnExists($resolvedProfiles, 'diocese_id', 'INTEGER NULL');
        $this->ensureColumnExists($resolvedProfiles, 'profile_picture', 'TEXT NULL');
        $this->ensureColumnExists($resolvedProfiles, 'parish_id', 'INTEGER NULL');
    }

    private function insertUser(string $username): int
    {
        $table = $this->resolveTableName('users');
        $this->db->table($table)->insert([
            'username' => $username,
            'email' => $username . '@example.org',
            'active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertProfile(int $userId, array $overrides = []): int
    {
        $table = $this->resolveTableName('profile');
        $this->db->resetDataCache();
        $columns = array_map('strtolower', $this->db->getFieldNames($table) ?: []);

        $payload = [
            'user_id' => $userId,
            'first_name' => 'First' . $userId,
            'last_name' => 'Last' . $userId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (in_array('parish_id', $columns, true)) {
            $payload['parish_id'] = null;
        }
        if (in_array('parish_id', $columns, true)) {
            $payload['parish_id'] = 0;
        }
        if (in_array('profile_picture', $columns, true)) {
            $payload['profile_picture'] = null;
        }

        foreach ($overrides as $key => $value) {
            if (in_array(strtolower((string) $key), $columns, true)) {
                $payload[$key] = $value;
            }
        }

        // Ensure any columns we're about to insert actually exist (broad-suite schema drift).
        foreach (array_keys($payload) as $col) {
            $this->ensureColumnExists($table, (string) $col, 'TEXT NULL');
        }

        $this->db->table($table)->insert($payload);

        return (int) $this->db->insertID();
    }

    private function tableExists(string $table): bool
    {
        return $this->db->tableExists($table) || $this->db->tableExists($this->db->getPrefix() . $table);
    }

    private function resolveTableName(string $base): string
    {
        $prefixed = $this->createTableName($base);
        if ($this->db->getPrefix() !== '') {
            return $prefixed;
        }

        return $base;
    }

    private function createTableName(string $base): string
    {
        return $this->db->getPrefix() . $base;
    }

    private function ensureColumnExists(string $table, string $column, string $definition): void
    {
        $hasColumn = false;
        try {
            $columns = $this->db->getFieldNames($table);
            $hasColumn = in_array($column, $columns, true);
        } catch (\Throwable $e) {
            // Fall through and attempt ALTER defensively.
        }

        if ($hasColumn) {
            return;
        }

        try {
            $this->db->query('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
        } catch (\Throwable $e) {
            // Ignore if current DB driver cannot alter this table in test mode.
        }
    }
}
