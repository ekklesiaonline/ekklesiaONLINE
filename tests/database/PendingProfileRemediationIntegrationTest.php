<?php

use App\Filters\RegistrationGateFilter;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Diocese\Controllers\RegistrationRequests;
use MemberPortal\Controllers\ProfileOnboarding;

/**
 * Verification of Pending Profile lifecycle remediation:
 * 1. Moderation redirect safety (preserves caller context; never redirects to /diocese/dashboard).
 * 2. Onboarding start detached from registration hook (no premature profile_id in person_onboarding).
 * 3. Profile completion creates canonical person and initializes onboarding with canonical person_id.
 * 4. Profile completion locks parish to registered request, ignoring client POST tampering.
 * 5. Deactivated accounts (active = 0) are blocked from /profile/complete.
 * 6. Language switcher (/locale/set/*) is excepted from RegistrationGateFilter for pending users.
 * 7. RegistrationGateFilter evaluates newest registration request (orderBy id DESC).
 *
 * @internal
 */
final class PendingProfileRemediationIntegrationTest extends CIUnitTestCase
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

        try {
            $this->db->query('DROP TABLE IF EXISTS ' . $this->resolveTableName('user_registration_requests'));
            $this->db->query('DROP TABLE IF EXISTS ' . $this->resolveTableName('user_registration_request'));
            $this->db->resetDataCache();
        } catch (\Throwable $e) {}

        if (session_status() === PHP_SESSION_ACTIVE) {
            session()->destroy();
        }
        $_SESSION = [];
        unset($_SERVER['HTTP_REFERER']);
        $_POST = [];
        $_GET = [];
        $_REQUEST = [];

        try {
            $request = service('request');
            $request->setGlobal('request', []);
            $request->setGlobal('post', []);
            $request->setGlobal('get', []);
        } catch (\Throwable $e) {}

        \CodeIgniter\Config\Factories::reset();
        Services::reset(true);
        parent::tearDown();
    }

    public function testModerationRedirectsReturnBackOrFallbackToPeopleNotDioceseDashboard(): void
    {
        [$provinceId, $dioceseId, $parish1Id] = $this->seedHierarchy();
        $adminId = $this->insertUser('parish.mod.admin', 'modadmin@example.org');
        $this->insertGroup($adminId, 'parishadmin');
        $this->insertProfile($adminId, $parish1Id, 'Admin', 'Mod');
        $this->injectAuthUser($adminId, true, true);

        $applicantUserId = $this->insertUser('applicant.mod', 'applicant.mod@example.org');
        $this->insertProfile($applicantUserId, null, 'Applicant', 'Mod');
        $req1Id = $this->insertRegistrationRequest($applicantUserId, $parish1Id, $dioceseId, $provinceId, 'pending_review');

        // 1. With previous URL pointing to /people#pendingRegistrationsCard
        $_SERVER['HTTP_REFERER'] = 'http://example.com/people?page=2#pendingRegistrationsCard';
        session()->set('_ci_previous_url', 'http://example.com/people?page=2#pendingRegistrationsCard');
        $request = service('request');
        $request->setMethod('post');
        $request->setGlobal('post', ['role' => 'member']);

        session()->set('operational_parish_id', $parish1Id);

        $controller = new RegistrationRequests();
        $controller->initController($request, service('response'), service('logger'));
        $result = $controller->approve($req1Id);

        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $result);
        $redirectUrl = (string) $result->getHeaderLine('Location');
        $this->assertStringNotContainsString('/diocese/dashboard', $redirectUrl);
        $this->assertStringContainsString('/people', $redirectUrl);

        // 2. With referer pointing to /diocese/dashboard -> fallback to /people#pendingRegistrationsCard
        $_SERVER['HTTP_REFERER'] = 'http://example.com/diocese/dashboard';
        session()->set('_ci_previous_url', 'http://example.com/diocese/dashboard');
        $req2Id = $this->insertRegistrationRequest($applicantUserId, $parish1Id, $dioceseId, $provinceId, 'pending_review');
        $result2 = $controller->dismiss($req2Id);

        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $result2);
        $redirectUrl2 = (string) $result2->getHeaderLine('Location');
        $this->assertStringNotContainsString('/diocese/dashboard', $redirectUrl2);
        $this->assertStringContainsString('/people#pendingRegistrationsCard', $redirectUrl2);

        // 3. Without referer -> fallback to /people#pendingRegistrationsCard
        unset($_SERVER['HTTP_REFERER']);
        unset($_SESSION['_ci_previous_url']);
        session()->remove('_ci_previous_url');
        $serverVars = $_SERVER;
        unset($serverVars['HTTP_REFERER']);
        $request->setGlobal('server', $serverVars);
        $req3Id = $this->insertRegistrationRequest($applicantUserId, $parish1Id, $dioceseId, $provinceId, 'pending_review');
        $result3 = $controller->ban($req3Id);

        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $result3);
        $redirectUrl3 = (string) $result3->getHeaderLine('Location');
        $this->assertStringNotContainsString('/diocese/dashboard', $redirectUrl3);
        $this->assertStringContainsString('/people#pendingRegistrationsCard', $redirectUrl3);
    }

    public function testEventsDoesNotCallPersonOnboardingStartOnRegister(): void
    {
        $eventsContent = file_get_contents(APPPATH . 'Config/Events.php');
        $this->assertNotFalse($eventsContent);

        // Verify that PersonOnboardingService is not imported or instantiated in Events.php
        $this->assertStringNotContainsString('PersonOnboardingService', $eventsContent);
        $this->assertStringNotContainsString('$onboarding->start', $eventsContent);
    }

    public function testCompleteSubmitSyncsCanonicalPersonAndStartsOnboardingWithPersonId(): void
    {
        [$provinceId, $dioceseId, $parish1Id] = $this->seedHierarchy();
        $userId = $this->insertUser('complete.onboarding.user', 'onboarding@example.org');
        $this->insertProfile($userId, null, 'FirstName', 'LastName');
        $reqId = $this->insertRegistrationRequest($userId, $parish1Id, $dioceseId, $provinceId, 'pending_profile');

        $this->injectAuthUser($userId);

        $request = service('request');
        $request->setMethod('post');
        $postData = [
            'first_name' => 'VerifiedFirst',
            'last_name' => 'VerifiedLast',
            'phone_country_code' => '+1',
            'phone' => '8095551234',
            'province_id' => $provinceId,
            'diocese_id' => $dioceseId,
            'parish_id' => $parish1Id,
        ];
        $request->setGlobal('post', $postData);
        $request->setGlobal('request', $postData);

        $controller = new ProfileOnboarding();
        $controller->initController($request, service('response'), service('logger'));
        $result = $controller->completeSubmit();

        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $result);
        $this->assertNull(session('error'), 'Session error: ' . (string) session('error'));
        $this->assertNull(session('errors'), 'Validation errors: ' . json_encode(session('errors')));

        // Verify canonical person record was created and synced
        $person = $this->db->table($this->resolveTableName('person'))
            ->where('first_name', 'VerifiedFirst')
            ->where('last_name', 'VerifiedLast')
            ->get()
            ->getRowArray();
        $this->assertNotNull($person, 'Canonical person record was not created during profile completion!');
        $personId = (int) $person['id'];
        $this->assertGreaterThan(0, $personId);

        // Verify person_onboarding was started with the canonical person_id
        $onboarding = $this->db->table($this->resolveTableName('person_onboarding'))
            ->where('person_id', $personId)
            ->where('parish_id', $parish1Id)
            ->get()
            ->getRowArray();
        $this->assertNotNull($onboarding, 'person_onboarding was not started with canonical person_id!');
        $this->assertSame('pending', (string) ($onboarding['onboarding_status'] ?? ''));
    }

    public function testCompleteSubmitLocksParishToRegisteredRequestIgnoringTamperedPost(): void
    {
        [$provinceId, $dioceseId, $parish1Id, $parish2Id] = $this->seedHierarchy();
        $userId = $this->insertUser('tamper.tester', 'tamper@example.org');
        $this->insertProfile($userId, null, 'Tamper', 'Tester');
        $reqId = $this->insertRegistrationRequest($userId, $parish1Id, $dioceseId, $provinceId, 'pending_profile');

        $this->injectAuthUser($userId);

        $request = service('request');
        $request->setMethod('post');
        // Malicious client tampers with parish_id in POST body, trying to select parish2Id
        $postData = [
            'first_name' => 'Tamper',
            'last_name' => 'Tester',
            'phone_country_code' => '+1',
            'phone' => '8095551234',
            'province_id' => $provinceId,
            'diocese_id' => $dioceseId,
            'parish_id' => $parish2Id,
        ];
        $request->setGlobal('post', $postData);
        $request->setGlobal('request', $postData);

        $controller = new ProfileOnboarding();
        $controller->initController($request, service('response'), service('logger'));
        $result = $controller->completeSubmit();

        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $result);

        // Verify that registered request still points to parish1Id (tamper ignored/prevented)
        $req = $this->db->table($this->resolveTableName('user_registration_requests'))
            ->where('id', $reqId)
            ->get()
            ->getRowArray();
        $this->assertSame($parish1Id, (int) ($req['requested_parish_id'] ?? 0));
        $this->assertSame('pending_review', (string) ($req['status'] ?? ''));
    }

    public function testDeactivatedUserBlockedOnProfileComplete(): void
    {
        [$provinceId, $dioceseId, $parish1Id] = $this->seedHierarchy();
        $userId = $this->insertUser('deactivated.user', 'deactivated@example.org');
        $this->db->table($this->resolveTableName('users'))->where('id', $userId)->update(['active' => 0]);
        $this->insertProfile($userId, null, 'Deactivated', 'User');
        $this->insertRegistrationRequest($userId, $parish1Id, $dioceseId, $provinceId, 'pending_profile');

        $this->injectAuthUser($userId, false);

        $request = service('request');
        $controller = new ProfileOnboarding();
        $controller->initController($request, service('response'), service('logger'));

        // 1. GET /profile/complete
        $result = $controller->complete();
        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $result);
        $this->assertSame(site_url('login'), (string) $result->getHeaderLine('Location'));

        // 2. POST /profile/complete
        $this->injectAuthUser($userId, false);
        $request->setMethod('post');
        $postData = [
            'first_name' => 'Deactivated',
            'last_name' => 'User',
            'province_id' => $provinceId,
            'diocese_id' => $dioceseId,
            'parish_id' => $parish1Id,
        ];
        $request->setGlobal('post', $postData);
        $resultSubmit = $controller->completeSubmit();
        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $resultSubmit);
        $this->assertSame(site_url('login'), (string) $resultSubmit->getHeaderLine('Location'));
    }

    public function testRegistrationGateAllowsLocaleSetForPendingProfile(): void
    {
        $ref = new \ReflectionClass(\Config\Filters::class);
        $defaults = $ref->getDefaultProperties();
        $except = $defaults['globals']['before']['registrationGate']['except'] ?? [];
        $this->assertContains('locale/set/*', $except);
    }

    public function testRegistrationGateUsesMostRecentRegistrationRequestOrderDesc(): void
    {
        [$provinceId, $dioceseId, $parish1Id] = $this->seedHierarchy();
        $userId = $this->insertUser('reapplied.user', 'reapplied@example.org');
        $this->insertProfile($userId, null, 'Reapplied', 'User');

        // Older request: banned
        $this->insertRegistrationRequest($userId, $parish1Id, $dioceseId, $provinceId, 'banned');
        // Newer request: pending_profile
        $this->insertRegistrationRequest($userId, $parish1Id, $dioceseId, $provinceId, 'pending_profile');

        $this->injectAuthUser($userId, true, false);

        $request = service('request');
        $filter = new RegistrationGateFilter();
        $result = $filter->before($request);

        // Because the latest request is pending_profile, it redirects to /profile/complete (NOT /login as banned would)
        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $result);
        $this->assertStringContainsString('profile/complete', (string) $result->getHeaderLine('Location'));
    }

    private function ensureCoreTablesExist(): void
    {
        $this->createTableIfNotExists('users', '
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NULL,
            email TEXT NULL,
            active INTEGER DEFAULT 1,
            created_at TEXT NULL,
            updated_at TEXT NULL,
            deleted_at TEXT NULL
        ');
        $this->createTableIfNotExists('province', '
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            province_number TEXT NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL
        ');
        $this->createTableIfNotExists('diocese', '
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            province_id INTEGER NULL,
            name TEXT NOT NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL
        ');
        $this->createTableIfNotExists('parish', '
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            diocese_id INTEGER NULL,
            name TEXT NOT NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL
        ');
        $this->createTableIfNotExists('profile', '
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            person_id INTEGER NULL,
            first_name TEXT NULL,
            last_name TEXT NULL,
            phone TEXT NULL,
            phone_country_code TEXT NULL,
            province_id INTEGER NULL,
            diocese_id INTEGER NULL,
            parish_id INTEGER NULL,
            locale TEXT NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL,
            deleted_at TEXT NULL
        ');
        $this->createTableIfNotExists('user_registration_requests', '
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            province_id INTEGER NULL,
            diocese_id INTEGER NULL,
            requested_parish_id INTEGER NULL,
            status TEXT NULL,
            requested_role TEXT NULL,
            reviewed_by INTEGER NULL,
            review_notes TEXT NULL,
            profile_completed_at TEXT NULL,
            reviewed_at TEXT NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL
        ');
        $this->createTableIfNotExists('user_registration_request', '
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            province_id INTEGER NULL,
            diocese_id INTEGER NULL,
            requested_parish_id INTEGER NULL,
            status TEXT NULL,
            requested_role TEXT NULL,
            reviewed_by INTEGER NULL,
            review_notes TEXT NULL,
            profile_completed_at TEXT NULL,
            reviewed_at TEXT NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL
        ');
        $this->createTableIfNotExists('person', '
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            first_name TEXT NULL,
            last_name TEXT NULL,
            email TEXT NULL,
            phone TEXT NULL,
            birthdate TEXT NULL,
            address TEXT NULL,
            source TEXT NULL,
            is_active INTEGER DEFAULT 1,
            created_at TEXT NULL,
            updated_at TEXT NULL,
            deleted_at TEXT NULL
        ');
        $this->createTableIfNotExists('user_person', '
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            person_id INTEGER NOT NULL,
            is_primary INTEGER DEFAULT 1,
            linked_at TEXT NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL
        ');
        $this->createTableIfNotExists('person_onboarding', '
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            person_id INTEGER NOT NULL,
            parish_id INTEGER NULL,
            onboarding_status TEXT NOT NULL,
            started_at TEXT NULL,
            completed_at TEXT NULL,
            notes TEXT NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL
        ');
        $this->createTableIfNotExists('person_status_history', '
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            person_id INTEGER NOT NULL,
            status_type TEXT NOT NULL,
            old_status TEXT NULL,
            new_status TEXT NOT NULL,
            parish_id INTEGER NULL,
            changed_by INTEGER NULL,
            reason TEXT NULL,
            created_at TEXT NULL
        ');
        $this->createTableIfNotExists('parish_affiliation', '
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            linked_user_id INTEGER NOT NULL,
            person_id INTEGER NULL,
            parish_id INTEGER NOT NULL,
            diocese_id INTEGER NULL,
            membership_status TEXT NULL,
            is_primary INTEGER DEFAULT 0,
            is_member INTEGER DEFAULT 0,
            is_home INTEGER DEFAULT 0,
            active INTEGER DEFAULT 1,
            source TEXT DEFAULT \'system\',
            start_date TEXT NULL,
            end_date TEXT NULL,
            followed_up INTEGER DEFAULT 0,
            converted_to_member_at TEXT NULL,
            converted_by_user_id INTEGER NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL,
            deleted_at TEXT NULL
        ');
        $this->createTableIfNotExists('parish_role_audit_log', '
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            parish_id INTEGER NULL,
            actor_user_id INTEGER NOT NULL,
            target_user_id INTEGER NOT NULL,
            action TEXT NOT NULL,
            role TEXT NULL,
            details TEXT NULL,
            created_at TEXT NULL
        ');
        $this->createTableIfNotExists('auth_groups_users', '
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            "group" TEXT NOT NULL,
            created_at TEXT NULL
        ');
        $this->createTableIfNotExists('auth_identities', '
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
            updated_at TEXT NULL
        ');

        // Ensure person_id column exists on profile table
        $profileTable = $this->resolveTableName('profile');
        if (! $this->db->fieldExists('person_id', $profileTable)) {
            $this->db->query('ALTER TABLE ' . $profileTable . ' ADD COLUMN person_id INTEGER NULL');
        }
        $this->db->resetDataCache();
    }

    private function createTableIfNotExists(string $base, string $definition): void
    {
        $table = $this->resolveTableName($base);
        $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (' . $definition . ')');
    }

    private function seedHierarchy(): array
    {
        $this->db->table($this->resolveTableName('province'))->insert([
            'name' => 'Remediation Province',
            'province_number' => 'RP-1',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $provinceId = (int) $this->db->insertID();

        $this->db->table($this->resolveTableName('diocese'))->insert([
            'province_id' => $provinceId,
            'name' => 'Remediation Diocese',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $dioceseId = (int) $this->db->insertID();

        $this->db->table($this->resolveTableName('parish'))->insert([
            'diocese_id' => $dioceseId,
            'name' => 'Remediation Parish 1',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $parish1Id = (int) $this->db->insertID();

        $this->db->table($this->resolveTableName('parish'))->insert([
            'diocese_id' => $dioceseId,
            'name' => 'Remediation Parish 2',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $parish2Id = (int) $this->db->insertID();

        return [$provinceId, $dioceseId, $parish1Id, $parish2Id];
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

    private function insertProfile(int $userId, ?int $parishId, string $first, string $last): void
    {
        $this->db->table($this->resolveTableName('profile'))->insert([
            'user_id' => $userId,
            'first_name' => $first,
            'last_name' => $last,
            'parish_id' => $parishId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function insertGroup(int $userId, string $group): void
    {
        $this->db->table($this->resolveTableName('auth_groups_users'))->insert([
            'user_id' => $userId,
            'group' => $group,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function insertRegistrationRequest(int $userId, int $parishId, int $dioceseId, int $provinceId, string $status): int
    {
        $table = $this->resolveTableName('user_registration_requests');
        $this->db->table($table)->insert([
            'user_id' => $userId,
            'province_id' => $provinceId,
            'diocese_id' => $dioceseId,
            'requested_parish_id' => $parishId,
            'status' => $status,
            'requested_role' => 'member',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function injectAuthUser(int $userId, bool $active = true, bool $isStaffAdmin = false): void
    {
        $fakeUser = new class($userId, $active, $isStaffAdmin) extends User {
            public function __construct(private int $userIdForTest, private bool $isActive = true, private bool $staffAdmin = false)
            {
                parent::__construct([
                    'id' => $userIdForTest,
                    'username' => 'auth.user.' . $userIdForTest,
                    'email' => 'auth.user.' . $userIdForTest . '@example.org',
                    'active' => $isActive ? 1 : 0,
                ]);
            }

            public function can(string ...$permissions): bool
            {
                return true;
            }

            public function inGroup(string ...$groups): bool
            {
                return $this->staffAdmin && in_array('parishadmin', $groups, true);
            }
        };

        $auth = new class(config('Auth'), $fakeUser, $userId) extends Auth {
            private bool $isLoggedIn = true;

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
                return $this->isLoggedIn;
            }

            public function logout(): void
            {
                $this->isLoggedIn = false;
            }

            public function id(): ?int
            {
                return $this->isLoggedIn ? $this->userIdForTest : null;
            }

            public function user(): ?User
            {
                return $this->isLoggedIn ? $this->fakeUser : null;
            }
        };

        Services::injectMock('auth', $auth);
    }

    private function resolveTableName(string $base): string
    {
        return $this->db->getPrefix() . $base;
    }
}
