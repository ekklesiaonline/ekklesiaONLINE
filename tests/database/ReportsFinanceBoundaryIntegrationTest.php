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
 * @internal
 */
final class ReportsFinanceBoundaryIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

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

    public function testProvinceAdminIsRedirectedFromReportsByFinanceBoundaryFilter(): void
    {
        $userId = $this->insertUser('province.reports.boundary.user');
        $this->injectProvinceAdminAuth($userId);

        $response = $this->get('reports');

        $response->assertRedirect();
        $response->assertRedirectTo('/dashboard');
        $error = (string) session('error');
        $this->assertContains($error, [
            lang('App.errorUnauthorized'),
            lang('Scope.noParishScopeAssigned'),
            'No parish scope is assigned to your account.',
        ]);
    }

    public function testDiocesanAdminIsRedirectedFromReportsByFinanceBoundaryFilter(): void
    {
        $userId = $this->insertUser('diocesan.reports.boundary.user');
        $this->injectDiocesanAdminAuth($userId);

        $response = $this->get('reports');

        $response->assertRedirect();
        $response->assertRedirectTo('/dashboard');
        $error = (string) session('error');
        $this->assertContains($error, [
            lang('App.errorUnauthorized'),
            lang('Scope.noParishScopeAssigned'),
            'No parish scope is assigned to your account.',
        ]);
    }

    public function testProvinceAdminIsRedirectedFromParishListBySystemBoundaryFilter(): void
    {
        $userId = $this->insertUser('province.parish.boundary.user');
        $this->insertProfileScope($userId, 1, null);
        $this->injectProvinceAdminAuth($userId);

        $response = $this->get('parish_list');

        $response->assertRedirect();
        $error = (string) session('error');
        $this->assertContains($error, [
            lang('Auth.notEnoughPrivilege'),
            lang('Scope.parishOutsideProvinceScope'),
            lang('Diocesan.parishManagementAccessDenied'),
            'Diocesan.parishManagementAccessDenied',
            'Parish is outside your province scope.',
            'Not enough privilege to access this resource.',
        ]);
    }

    public function testDiocesanAdminIsRedirectedFromParishListBySystemBoundaryFilter(): void
    {
        $userId = $this->insertUser('diocesan.parish.boundary.user');
        $this->insertProfileScope($userId, null, 1);
        $this->injectDiocesanAdminAuth($userId);

        $response = $this->get('parish_list');

        $response->assertRedirect();
        $error = (string) session('error');
        $this->assertContains($error, [
            lang('Auth.notEnoughPrivilege'),
            lang('Diocesan.parishManagementAccessDenied'),
            'Diocesan.parishManagementAccessDenied',
            'Not enough privilege to access this resource.',
        ]);
    }

    public function testDiocesanAdminCanAccessDioceseFinance(): void
    {
        $userId = $this->insertUser('diocesan.finance.boundary.user');
        $this->insertProfileScope($userId, null, 1);
        $this->injectDioceseAdminAuth($userId);

        $response = $this->get('diocese/finance');

        $response->assertOK();
        $response->assertSee(lang('App.navFinanceDashboard'));
    }

    public function testProvinceAdminIsRedirectedFromDioceseFinance(): void
    {
        $userId = $this->insertUser('province.finance.boundary.user');
        $this->insertProfileScope($userId, 1, null);
        $this->injectProvinceAdminAuth($userId);

        $response = $this->get('diocese/finance');

        $this->assertTrue($response->isRedirect() || $response->getStatusCode() === 403);
    }

    private function insertProfileScope(int $userId, ?int $provinceId, ?int $dioceseId): void
    {
        $parishId = $this->profileScopeParishId();
        $profileTable = $this->createTableName('profile');
        if (!$this->db->tableExists($profileTable)) {
            $profileTable = $this->resolveTableName('profile');
        }
        $profileColumns = array_map('strtolower', $this->db->getFieldNames($profileTable));

        $payload = [
            'user_id' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (in_array('locale', $profileColumns, true)) {
            $payload['locale'] = 'en';
        }

        if ($provinceId !== null && in_array('province_id', $profileColumns, true)) {
            $payload['province_id'] = $provinceId;
        }

        if ($dioceseId !== null && in_array('diocese_id', $profileColumns, true)) {
            $payload['diocese_id'] = $dioceseId;
        }

        if (in_array('parish_id', $profileColumns, true)) {
            $payload['parish_id'] = $parishId;
        }

        $payload = $this->filterPayloadByColumns($profileTable, $payload);

        try {
            $this->db->table($profileTable)->insert($payload);
        } catch (\Throwable $e) {
            if (array_key_exists('parish_id', $payload)) {
                unset($payload['parish_id']);
                $payload['parish_id'] = $parishId;
                $payload = $this->filterPayloadByColumns($profileTable, $payload);
                $this->db->table($profileTable)->insert($payload);
                return;
            }

            throw $e;
        }
    }

    private function profileScopeParishId(): ?int
    {
        return 0;
    }

    private function injectProvinceAdminAuth(int $userId): void
    {
        $fakeUser = new class($userId) extends User {
            public function __construct(private int $idForTest)
            {
                parent::__construct([
                    'id' => $idForTest,
                    'username' => 'province.reports.boundary.user.' . $idForTest,
                    'email' => 'province.reports.boundary.' . $idForTest . '@example.org',
                ]);
            }

            public function can(string ...$permissions): bool
            {
                $granted = [
                    'province.admin',
                    'dashboard.view',
                    'report.view',
                ];

                foreach ($permissions as $permission) {
                    if (! in_array($permission, $granted, true)) {
                        return false;
                    }
                }

                return true;
            }

            public function inGroup(string ...$groups): bool
            {
                return in_array('provinceadmin', $groups, true);
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

    private function injectDiocesanAdminAuth(int $userId): void
    {
        $fakeUser = new class($userId) extends User {
            public function __construct(private int $idForTest)
            {
                parent::__construct([
                    'id' => $idForTest,
                    'username' => 'diocesan.reports.boundary.user.' . $idForTest,
                    'email' => 'diocesan.reports.boundary.' . $idForTest . '@example.org',
                ]);
            }

            public function can(string ...$permissions): bool
            {
                $granted = [
                    'diocese.admin',
                    'dashboard.view',
                    'report.view',
                ];

                foreach ($permissions as $permission) {
                    if (! in_array($permission, $granted, true)) {
                        return false;
                    }
                }

                return true;
            }

            public function inGroup(string ...$groups): bool
            {
                return in_array('diocesanadmin', $groups, true);
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

    private function injectDioceseAdminAuth(int $userId): void
    {
        $fakeUser = new class($userId) extends User {
            public function __construct(private int $idForTest)
            {
                parent::__construct([
                    'id' => $idForTest,
                    'username' => 'diocese.reports.boundary.user.' . $idForTest,
                    'email' => 'diocese.reports.boundary.' . $idForTest . '@example.org',
                ]);
            }

            public function can(string ...$permissions): bool
            {
                $granted = [
                    'diocese.admin',
                    'dashboard.view',
                    'report.view',
                ];

                foreach ($permissions as $permission) {
                    if (! in_array($permission, $granted, true)) {
                        return false;
                    }
                }

                return true;
            }

            public function inGroup(string ...$groups): bool
            {
                return in_array('dioceseadmin', $groups, true);
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

    private function insertUser(string $username): int
    {
        $this->db->table($this->resolveTableName('users'))->insert([
            'username' => $username,
            'active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $userId = (int) $this->db->insertID();

        $identityTable = $this->resolveTableName('auth_identities');
        $identityColumns = array_map('strtolower', $this->db->getFieldNames($identityTable));
        $identityPayload = [
            'user_id' => $userId,
            'type' => 'email_password',
            'secret' => $username . '@example.test',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $this->db->table($identityTable)->insert($identityPayload);

        return $userId;
    }

    private function ensureCoreTablesExist(): void
    {
        $usersTable = $this->createTableName('users');
        if (! $this->tableExists('users')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $usersTable . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NULL,
                email TEXT NULL,
                active INTEGER DEFAULT 1,
                deleted_at TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }

        $this->ensureColumnExists($this->resolveTableName('users'), 'email', 'TEXT NULL');

        $sessionsTable = $this->createTableName('user_session');
        if (! $this->tableExists('user_session')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $sessionsTable . ' (
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

        $resolvedSessions = $this->resolveTableName('user_session');
        $this->ensureColumnExists($resolvedSessions, 'session_token', 'TEXT NOT NULL DEFAULT "fixture-session"');
        $this->ensureColumnExists($resolvedSessions, 'ip_address', 'TEXT NULL');
        $this->ensureColumnExists($resolvedSessions, 'user_agent', 'TEXT NULL');
        $this->ensureColumnExists($resolvedSessions, 'device_fingerprint', 'TEXT NULL');
        $this->ensureColumnExists($resolvedSessions, 'is_new_device', 'INTEGER DEFAULT 0');
        $this->ensureColumnExists($resolvedSessions, 'last_active', 'TEXT NULL');

        $registrationRequestsTable = $this->createTableName('user_registration_request');
        if (! $this->tableExists('user_registration_request')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $registrationRequestsTable . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                status TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }

        $profilesTable = $this->createTableName('profile');
        if (! $this->tableExists('profile')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $profilesTable . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                locale TEXT NULL,
                province_id INTEGER NULL,
                diocese_id INTEGER NULL,
                parish_id INTEGER NULL,
                parish_id INTEGER NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        $resolvedProfiles = $this->resolveTableName('profile');
        $this->ensureColumnExists($resolvedProfiles, 'locale', 'TEXT NULL');
        $this->ensureColumnExists($resolvedProfiles, 'province_id', 'INTEGER NULL');
        $this->ensureColumnExists($resolvedProfiles, 'diocese_id', 'INTEGER NULL');
        $this->ensureColumnExists($resolvedProfiles, 'parish_id', 'INTEGER NULL');
        $this->ensureColumnExists($resolvedProfiles, 'parish_id', 'INTEGER NULL');
        $prefixedProfiles = $this->createTableName('profile');
        $this->ensureColumnExists($prefixedProfiles, 'province_id', 'INTEGER NULL');
        $this->ensureColumnExists($prefixedProfiles, 'diocese_id', 'INTEGER NULL');
        $this->ensureColumnExists($prefixedProfiles, 'parish_id', 'INTEGER NULL');
        $this->ensureColumnExists($prefixedProfiles, 'parish_id', 'INTEGER NULL');
        $this->ensureColumnExists($prefixedProfiles, 'locale', 'TEXT NULL');

        if ($this->tableExists('dioceses')) {
            $this->ensureColumnExists($this->resolveTableName('dioceses'), 'province_id', 'INTEGER NULL');
        }

        $parishTable = $this->createTableName('parish');
        if (! $this->tableExists('parish')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $parishTable . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                diocese_id INTEGER NULL,
                name TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        $this->ensureColumnExists($this->resolveTableName('parish'), 'diocese_id', 'INTEGER NULL');
        $this->ensureColumnExists($this->createTableName('parish'), 'diocese_id', 'INTEGER NULL');

        $identitiesTable = $this->createTableName('auth_identities');
        if (! $this->tableExists('auth_identities')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $identitiesTable . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                type TEXT NOT NULL,
                secret TEXT NULL,
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

        $groupsUsersTable = $this->createTableName('auth_groups_users');
        if (! $this->tableExists('auth_groups_users')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $groupsUsersTable . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                "group" TEXT NOT NULL,
                created_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        $settingsTable = $this->createTableName('settings');
        if (! $this->tableExists('settings')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $settingsTable . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                class TEXT NOT NULL,
                key TEXT NOT NULL,
                value TEXT NULL,
                type TEXT NULL,
                context TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }
    }

    private function tableExists(string $table): bool
    {
        return $this->db->tableExists($table) || $this->db->tableExists($this->db->getPrefix() . $table);
    }

    private function createTableName(string $baseName): string
    {
        return $this->db->getPrefix() . $baseName;
    }

    private function resolveTableName(string $table): string
    {
        $prefixed = $this->db->getPrefix() . $table;
        if ($this->db->getPrefix() !== '') {
            return $prefixed;
        }

        return $table;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function filterPayloadByColumns(string $table, array $payload): array
    {
        try {
            $columns = array_map('strtolower', $this->db->getFieldNames($table));
        } catch (\Throwable $e) {
            return $payload;
        }

        if ($columns === []) {
            return $payload;
        }

        foreach (array_keys($payload) as $key) {
            if (!in_array(strtolower((string) $key), $columns, true)) {
                unset($payload[$key]);
            }
        }

        return $payload;
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
            // Ignore SQLite alter limitations in ephemeral test DBs.
        }
    }
}
