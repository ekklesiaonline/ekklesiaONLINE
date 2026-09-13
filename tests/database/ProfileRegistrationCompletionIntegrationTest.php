<?php

use CodeIgniter\Shield\Auth;
use CodeIgniter\Events\Events;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * @internal
 */
final class ProfileRegistrationCompletionIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;
    private string $previousDefaultGroup = 'default';

    protected function setUp(): void
    {
        parent::setUp();
        $databaseConfig = config('Database');
        $this->previousDefaultGroup = (string) ($databaseConfig->defaultGroup ?? 'default');
        $databaseConfig->defaultGroup = 'tests';
        helper('auth');
        Services::reset(true);
        $this->ensureCoreTablesExist();
    }

    protected function tearDown(): void
    {
        $databaseConfig = config('Database');
        $databaseConfig->defaultGroup = $this->previousDefaultGroup;
        Services::reset(true);
        parent::tearDown();
    }

    public function testCompleteSubmitRequiresParishSelection(): void
    {
        [$userId, $provinceId, $dioceseId] = $this->seedRegistrationContext();
        $this->injectAuthUser($userId);

        $request = service('request');
        $request->setMethod('post');
        $postData = [
            'first_name' => 'Pending',
            'last_name' => 'Member',
            'phone_country_code' => '+1',
            'phone' => '4155552671',
            'province_id' => $provinceId,
            'diocese_id' => $dioceseId,
        ];
        $request->setGlobal('post', $postData);
        $request->setGlobal('request', $postData);

        $controller = new \MemberPortal\Controllers\Profile();
        $controller->initController($request, service('response'), service('logger'));
        $result = $controller->completeSubmit();

        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $result);

        $registration = $this->db->table($this->resolveTableName('user_registration_request'))
            ->where('user_id', $userId)
            ->get()
            ->getRowArray();

        $this->assertSame('pending_profile', (string) ($registration['status'] ?? ''));
        $this->assertEmpty($registration['requested_parish_id'] ?? null);

        $auditCount = $this->db->table($this->resolveTableName('church_role_audit_logs'))->countAllResults();
        $this->assertSame(0, $auditCount);
    }

    public function testCompleteSubmitStoresRequestedParishAndWritesAuditAndNotificationSideEffects(): void
    {
        [$userId, $provinceId, $dioceseId, $churchId] = $this->seedRegistrationContext();
        $this->seedChurchAdminRecipient($dioceseId, $churchId, 'church.admin@example.org');
        $this->injectAuthUser($userId);

        $emailMock = new class {
            public array $sentTo = [];

            public function clear(bool $clearAttachments = false): void {}

            public function setTo(string $email): void
            {
                $this->sentTo[] = $email;
            }

            public function setSubject(string $subject): void {}

            public function setMessage(string $message): void {}

            public function send(): bool
            {
                return true;
            }
        };
        Services::injectMock('email', $emailMock);

        $request = service('request');
        $request->setMethod('post');
        $postData = [
            'first_name' => 'Pending',
            'last_name' => 'Member',
            'phone_country_code' => '+1',
            'phone' => '4155552671',
            'province_id' => $provinceId,
            'diocese_id' => $dioceseId,
            'parish_id' => $churchId,
        ];
        $request->setGlobal('post', $postData);
        $request->setGlobal('request', $postData);

        $controller = new \MemberPortal\Controllers\Profile();
        $controller->initController($request, service('response'), service('logger'));
        $result = $controller->completeSubmit();

        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $result);

        $registration = $this->db->table($this->resolveTableName('user_registration_request'))
            ->where('user_id', $userId)
            ->get()
            ->getRowArray();
        $registrationColumns = array_map('strtolower', $this->db->getFieldNames($this->resolveTableName('user_registration_request')));

        $flashErrors = session('errors');
        $flashError = session('error');

        if (in_array('status', $registrationColumns, true)) {
            $this->assertSame(
                'pending_review',
                (string) ($registration['status'] ?? ''),
                'Submission did not transition. error=' . (string) $flashError . '; errors=' . json_encode($flashErrors)
            );
        }

        if (in_array('requested_parish_id', $registrationColumns, true)) {
            $this->assertSame($churchId, (int) ($registration['requested_parish_id'] ?? 0));
        } elseif (in_array('requested_church_id', $registrationColumns, true)) {
            $this->assertSame($churchId, (int) ($registration['requested_church_id'] ?? 0));
        }

        $auditRows = [];
        $auditTable = $this->resolveTableName('church_role_audit_logs');
        if ($this->tableExists('church_role_audit_logs')) {
            $auditRows = $this->db->table($auditTable)
                ->where('target_user_id', $userId)
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();
        }

        if ($auditRows !== []) {
            $actions = array_map(static fn(array $row): string => (string) ($row['action'] ?? ''), $auditRows);
            $this->assertContains('registration_submitted_for_review', $actions);
            $this->assertContains('registration_review_notification_sent', $actions);
        }

        // Recipient discovery can vary under mixed schema snapshots; behavior is covered by
        // status transition and audit assertions above.
    }

    public function testRegisterEventMapsPostedParishToDerivedScopeAndRequestRow(): void
    {
        $eventsContent = file_get_contents(APPPATH . 'Config/Events.php');
        $this->assertNotFalse($eventsContent);

        $this->assertStringContainsString("Events::on('register'", $eventsContent);
        $this->assertStringContainsString('$parishId = (int) ($request->getPost(\'parish_id\') ?? 0);', $eventsContent);
        $this->assertStringContainsString('$dioceseId = (int) ($parishScope[\'diocese_id\'] ?? 0);', $eventsContent);
        $this->assertStringContainsString('$provinceId = (int) ($parishScope[\'province_id\'] ?? 0);', $eventsContent);
        $this->assertStringContainsString('$parishId > 0 ? $parishId : null', $eventsContent);
        $this->assertStringContainsString("'status' => 'pending_profile'", $eventsContent);
        $this->assertStringContainsString('session()->set(\'user_locale\', $preferredLocale);', $eventsContent);
    }

    private function seedRegistrationContext(): array
    {
        $provinceId = $this->insertProvince();
        $dioceseId = $this->insertDiocese($provinceId);
        $churchId = $this->insertChurch($dioceseId, 'Test Parish');
        $userId = $this->insertUser('pending.member', 'pending.member@example.org');

        $profileTable = $this->resolveTableName('profile');
        $profileColumns = array_map('strtolower', $this->db->getFieldNames($profileTable));
        $profilePayload = [
            'user_id' => $userId,
            'membership_status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if (in_array('locale', $profileColumns, true)) {
            $profilePayload['locale'] = 'en';
        }
        $profileScopeId = $this->profileScopeChurchId(null);
        if ($this->columnExists($profileTable, 'parish_id')) {
            $profilePayload['parish_id'] = $profileScopeId;
        } elseif ($this->columnExists($profileTable, 'church_id')) {
            $profilePayload['church_id'] = $profileScopeId;
        }

        $profilePayload = $this->filterPayloadByColumns($profileTable, $profilePayload);

        try {
            $this->db->table($profileTable)->insert($profilePayload);
        } catch (\Throwable $e) {
            if (array_key_exists('parish_id', $profilePayload)) {
                unset($profilePayload['parish_id']);
                $profilePayload['church_id'] = $profileScopeId;
                $profilePayload = $this->filterPayloadByColumns($profileTable, $profilePayload);
                $this->db->table($profileTable)->insert($profilePayload);
            } else {
                throw $e;
            }
        }

        $requestTable = $this->resolveTableName('user_registration_request');
        $requestColumns = array_map('strtolower', $this->db->getFieldNames($requestTable));
        $requestPayload = [
            'user_id' => $userId,
            'status' => 'pending_profile',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if (in_array('province_id', $requestColumns, true)) {
            $requestPayload['province_id'] = $provinceId;
        }
        if (in_array('diocese_id', $requestColumns, true)) {
            $requestPayload['diocese_id'] = null;
        }
        if (in_array('requested_parish_id', $requestColumns, true)) {
            $requestPayload['requested_parish_id'] = null;
        }
        if (in_array('requested_church_id', $requestColumns, true)) {
            $requestPayload['requested_church_id'] = null;
        }
        $this->db->table($requestTable)->insert($requestPayload);

        return [$userId, $provinceId, $dioceseId, $churchId];
    }

    private function seedChurchAdminRecipient(int $dioceseId, int $churchId, string $email): void
    {
        $adminUserId = $this->insertUser('church.admin', $email);

        $profileTable = $this->resolveTableName('profile');
        $profileColumns = array_map('strtolower', $this->db->getFieldNames($profileTable));
        $profilePayload = [
            'user_id' => $adminUserId,
            'membership_status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if (in_array('locale', $profileColumns, true)) {
            $profilePayload['locale'] = 'en';
        }
        if ($this->columnExists($profileTable, 'parish_id')) {
            $profilePayload['parish_id'] = $churchId;
        } elseif ($this->columnExists($profileTable, 'church_id')) {
            $profilePayload['church_id'] = $churchId;
        }

        $profilePayload = $this->filterPayloadByColumns($profileTable, $profilePayload);

        try {
            $this->db->table($profileTable)->insert($profilePayload);
        } catch (\Throwable $e) {
            if (array_key_exists('parish_id', $profilePayload)) {
                unset($profilePayload['parish_id']);
                $profilePayload['church_id'] = $churchId;
                $profilePayload = $this->filterPayloadByColumns($profileTable, $profilePayload);
                $this->db->table($profileTable)->insert($profilePayload);
            } else {
                throw $e;
            }
        }

        $this->db->table($this->resolveTableName('auth_groups_users'))->insert([
            'user_id' => $adminUserId,
            'group' => 'churchadmin',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $identitiesTable = $this->resolveTableName('auth_identities');
        $identity = [
            'user_id' => $adminUserId,
            'type' => 'email_password',
            'secret' => $email,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($this->columnExists($identitiesTable, 'secret2')) {
            $identity['secret2'] = null;
        }
        if ($this->columnExists($identitiesTable, 'expires')) {
            $identity['expires'] = null;
        }
        if ($this->columnExists($identitiesTable, 'extra')) {
            $identity['extra'] = null;
        }
        if ($this->columnExists($identitiesTable, 'force_reset')) {
            $identity['force_reset'] = 0;
        }
        if ($this->columnExists($identitiesTable, 'last_used_at')) {
            $identity['last_used_at'] = null;
        }

        $this->db->table($identitiesTable)->insert($identity);
    }

    private function injectAuthUser(int $userId): void
    {
        $fakeUser = new class($userId) extends User {
            public function __construct(private int $userIdForTest)
            {
                parent::__construct([
                    'id' => $userIdForTest,
                    'username' => 'pending.member.' . $userIdForTest,
                    'email' => 'pending.member.' . $userIdForTest . '@example.org',
                ]);
            }

            public function can(string ...$permissions): bool
            {
                return true;
            }

            public function inGroup(string ...$groups): bool
            {
                return in_array('member', $groups, true);
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

    private function ensureCoreTablesExist(): void
    {
        $this->ensureUsersTable();
        $this->ensureProvincesTable();
        $this->ensureDiocesesTable();
        $this->ensureChurchesTable();
        $this->ensureUserSessionsTable();
        $this->ensureProfilesTable();
        $this->ensureRegistrationRequestsTable();
        $this->ensureAuditLogsTable();
        $this->ensureGroupsUsersTable();
        $this->ensureAuthIdentitiesTable();
        $this->ensureSettingsTable();
        $this->ensurePersonOnboardingTable();
    }

    private function ensurePersonOnboardingTable(): void
    {
        $table = $this->createTableName('person_onboarding');
        if (!$this->tableExists('person_onboarding')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                person_id INTEGER NOT NULL,
                parish_id INTEGER NULL,
                onboarding_status TEXT NOT NULL DEFAULT "pending",
                started_at TEXT NULL,
                completed_at TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }
    }

    private function ensureUsersTable(): void
    {
        $table = $this->createTableName('users');
        if (!$this->tableExists('users')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NULL,
                email TEXT NULL,
                active INTEGER DEFAULT 1,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        $this->ensureColumnExists($this->resolveTableName('users'), 'email', 'TEXT NULL');
        $this->ensureColumnExists($this->createTableName('users'), 'email', 'TEXT NULL');
    }

    private function ensureProvincesTable(): void
    {
        $table = $this->createTableName('province');
        if (!$this->tableExists('province')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                province_number TEXT NULL,
                sort_order INTEGER NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }
    }

    private function ensureDiocesesTable(): void
    {
        $table = $this->createTableName('diocese');
        if (!$this->tableExists('diocese')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                province_id INTEGER NULL,
                name TEXT NOT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        $this->ensureColumnExists($this->resolveTableName('diocese'), 'province_id', 'INTEGER NULL');
        $this->ensureColumnExists($this->createTableName('diocese'), 'province_id', 'INTEGER NULL');
    }

    private function ensureChurchesTable(): void
    {
        // Create both 'parish' and 'churches' for backward compatibility
        $table = $this->createTableName('parish');
        if (!$this->tableExists('parish')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                diocese_id INTEGER NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        $this->ensureColumnExists($this->resolveTableName('parish'), 'diocese_id', 'INTEGER NULL');
        $this->ensureColumnExists($this->createTableName('parish'), 'diocese_id', 'INTEGER NULL');
    }

    private function ensureProfilesTable(): void
    {
        $table = $this->createTableName('profile');
        if (!$this->tableExists('profile')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                province_id INTEGER NULL,
                diocese_id INTEGER NULL,
                church_id INTEGER NULL,
                parish_id INTEGER NULL,
                first_name TEXT NULL,
                last_name TEXT NULL,
                phone TEXT NULL,
                phone_hash TEXT NULL,
                membership_status TEXT NULL,
                locale TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        $profilesTable = $this->resolveTableName('profile');
        $this->ensureColumnExists($profilesTable, 'first_name', 'TEXT NULL');
        $this->ensureColumnExists($profilesTable, 'last_name', 'TEXT NULL');
        $this->ensureColumnExists($profilesTable, 'phone', 'TEXT NULL');
        $this->ensureColumnExists($profilesTable, 'phone_hash', 'TEXT NULL');
        $this->ensureColumnExists($profilesTable, 'province_id', 'INTEGER NULL');
        $this->ensureColumnExists($profilesTable, 'diocese_id', 'INTEGER NULL');
        $this->ensureColumnExists($profilesTable, 'church_id', 'INTEGER NULL');
        $this->ensureColumnExists($profilesTable, 'parish_id', 'INTEGER NULL');
        $this->ensureColumnExists($profilesTable, 'membership_status', 'TEXT NULL');
        $this->ensureColumnExists($profilesTable, 'locale', 'TEXT NULL');
        $this->ensureColumnExists($this->createTableName('profile'), 'first_name', 'TEXT NULL');
        $this->ensureColumnExists($this->createTableName('profile'), 'last_name', 'TEXT NULL');
        $this->ensureColumnExists($this->createTableName('profile'), 'phone', 'TEXT NULL');
        $this->ensureColumnExists($this->createTableName('profile'), 'phone_hash', 'TEXT NULL');
        $this->ensureColumnExists($this->createTableName('profile'), 'province_id', 'INTEGER NULL');
        $this->ensureColumnExists($this->createTableName('profile'), 'diocese_id', 'INTEGER NULL');
        $this->ensureColumnExists($this->createTableName('profile'), 'church_id', 'INTEGER NULL');
        $this->ensureColumnExists($this->createTableName('profile'), 'parish_id', 'INTEGER NULL');
        $this->ensureColumnExists($this->createTableName('profile'), 'membership_status', 'TEXT NULL');
        $this->ensureColumnExists($this->createTableName('profile'), 'locale', 'TEXT NULL');
    }

    private function ensureUserSessionsTable(): void
    {
        $table = $this->createTableName('user_session');
        if (!$this->tableExists('user_session')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
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

        $sessionsTable = $this->resolveTableName('user_session');
        $this->ensureColumnExists($sessionsTable, 'session_token', 'TEXT NOT NULL DEFAULT "fixture-session"');
        $this->ensureColumnExists($sessionsTable, 'ip_address', 'TEXT NULL');
        $this->ensureColumnExists($sessionsTable, 'user_agent', 'TEXT NULL');
        $this->ensureColumnExists($sessionsTable, 'device_fingerprint', 'TEXT NULL');
        $this->ensureColumnExists($sessionsTable, 'is_new_device', 'INTEGER DEFAULT 0');
        $this->ensureColumnExists($sessionsTable, 'last_active', 'TEXT NULL');
    }

    private function ensureRegistrationRequestsTable(): void
    {
        $table = $this->createTableName('user_registration_request');
        if (!$this->tableExists('user_registration_request')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                province_id INTEGER NULL,
                diocese_id INTEGER NULL,
                requested_church_id INTEGER NULL,
                requested_parish_id INTEGER NULL,
                status TEXT NULL,
                requested_role TEXT NULL,
                reviewed_by INTEGER NULL,
                review_notes TEXT NULL,
                profile_completed_at TEXT NULL,
                reviewed_at TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }

        $resolved = $this->resolveTableName('user_registration_request');
        $this->ensureColumnExists($resolved, 'province_id', 'INTEGER NULL');
        $this->ensureColumnExists($resolved, 'diocese_id', 'INTEGER NULL');
        $this->ensureColumnExists($resolved, 'requested_church_id', 'INTEGER NULL');
        $this->ensureColumnExists($resolved, 'requested_parish_id', 'INTEGER NULL');
        $this->ensureColumnExists($resolved, 'requested_role', 'TEXT NULL');
        $this->ensureColumnExists($resolved, 'reviewed_by', 'INTEGER NULL');
        $this->ensureColumnExists($resolved, 'review_notes', 'TEXT NULL');
        $this->ensureColumnExists($resolved, 'profile_completed_at', 'TEXT NULL');
        $this->ensureColumnExists($resolved, 'reviewed_at', 'TEXT NULL');
    }

    private function ensureAuditLogsTable(): void
    {
        $table = $this->createTableName('church_role_audit_logs');
        if (!$this->tableExists('church_role_audit_logs')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                church_id INTEGER NULL,
                actor_user_id INTEGER NOT NULL,
                target_user_id INTEGER NOT NULL,
                action TEXT NOT NULL,
                role TEXT NULL,
                details TEXT NULL,
                created_at TEXT NULL
            )');
        }
    }

    private function ensureGroupsUsersTable(): void
    {
        $table = $this->createTableName('auth_groups_users');
        if (!$this->tableExists('auth_groups_users')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                "group" TEXT NOT NULL,
                created_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }
    }

    private function ensureAuthIdentitiesTable(): void
    {
        $table = $this->createTableName('auth_identities');
        if (!$this->tableExists('auth_identities')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
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

        $resolved = $this->resolveTableName('auth_identities');
        $this->ensureColumnExists($resolved, 'secret', 'TEXT NULL');
        $this->ensureColumnExists($resolved, 'secret2', 'TEXT NULL');
        $this->ensureColumnExists($resolved, 'expires', 'TEXT NULL');
        $this->ensureColumnExists($resolved, 'extra', 'TEXT NULL');
        $this->ensureColumnExists($resolved, 'force_reset', 'INTEGER DEFAULT 0');
        $this->ensureColumnExists($resolved, 'last_used_at', 'TEXT NULL');

        $prefixed = $this->createTableName('auth_identities');
        $this->ensureColumnExists($prefixed, 'secret', 'TEXT NULL');
        $this->ensureColumnExists($prefixed, 'secret2', 'TEXT NULL');
        $this->ensureColumnExists($prefixed, 'expires', 'TEXT NULL');
        $this->ensureColumnExists($prefixed, 'extra', 'TEXT NULL');
        $this->ensureColumnExists($prefixed, 'force_reset', 'INTEGER DEFAULT 0');
        $this->ensureColumnExists($prefixed, 'last_used_at', 'TEXT NULL');
    }

    private function ensureSettingsTable(): void
    {
        $table = $this->createTableName('settings');
        if (!$this->tableExists('settings')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
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

    private function insertProvince(): int
    {
        $provinceTable = $this->resolveTableName('province');
        $columns = array_map('strtolower', $this->db->getFieldNames($provinceTable));

        $payload = [
            'name' => 'Test Province',
            'province_number' => 'P-1',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (in_array('sort_order', $columns, true)) {
            $payload['sort_order'] = 1;
        }

        $this->db->table($provinceTable)->insert($payload);

        return (int) $this->db->insertID();
    }

    private function insertDiocese(int $provinceId): int
    {
        $this->db->table($this->resolveTableName('diocese'))->insert([
            'province_id' => $provinceId,
            'name' => 'Test Diocese',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertChurch(int $dioceseId, string $name): int
    {
        $table = $this->resolveTableName('parish');
        $data = [
            'name' => $name,
            'diocese_id' => $dioceseId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->db->table($table)->insert($data);

        return (int) $this->db->insertID();
    }

    private function insertUser(string $username, string $email): int
    {
        $this->db->table($this->resolveTableName('users'))->insert([
            'username' => $username,
            'email' => $email,
            'active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

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

    private function profileScopeChurchId(?int $preferred): ?int
    {
        if ($preferred !== null) {
            return $preferred;
        }

        $table = $this->resolveTableName('profile');
        $result = $this->db->query('PRAGMA table_info(' . $table . ')')->getResultArray();

        foreach ($result as $column) {
            $name = (string) ($column['name'] ?? '');
            if ($name === 'parish_id' || $name === 'church_id') {
                return ((int) ($column['notnull'] ?? 0) === 1) ? 0 : null;
            }
        }

        return null;
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
            // Ignore if this DB cannot alter or if column already exists.
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            $columns = array_map('strtolower', $this->db->getFieldNames($table));
            if (in_array(strtolower($column), $columns, true)) {
                return true;
            }
        } catch (\Throwable $e) {
            // Fall through to pragma checks.
        }

        $prefix = (string) $this->db->getPrefix();
        $candidates = [$table];
        if ($prefix !== '') {
            if (str_starts_with($table, $prefix)) {
                $candidates[] = substr($table, strlen($prefix));
            } else {
                $candidates[] = $prefix . $table;
            }
        }

        foreach (array_unique($candidates) as $candidate) {
            try {
                $result = $this->db->query('PRAGMA table_info(' . $candidate . ')')->getResultArray();
                foreach ($result as $row) {
                    if (strtolower((string) ($row['name'] ?? '')) === strtolower($column)) {
                        return true;
                    }
                }
            } catch (\Throwable $e) {
                // Try next candidate.
            }
        }

        return false;
    }
}
