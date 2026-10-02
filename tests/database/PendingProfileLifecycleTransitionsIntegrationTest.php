<?php

use App\Services\RegistrationApprovalService;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * @internal
 */
final class PendingProfileLifecycleTransitionsIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    private string $previousDefaultGroup = 'default';

    protected function setUp(): void
    {
        parent::setUp();
        $databaseConfig = config('Database');
        $this->previousDefaultGroup = (string) ($databaseConfig->defaultGroup ?? 'default');
        $databaseConfig->defaultGroup = 'tests';
        helper('auth');
        \CodeIgniter\Config\Factories::reset();
        Services::reset(true);
        $this->ensureCoreTablesExist();
    }

    protected function tearDown(): void
    {
        $databaseConfig = config('Database');
        $databaseConfig->defaultGroup = $this->previousDefaultGroup;
        try {
            $this->db->query('DROP TABLE IF EXISTS ' . $this->resolveTableName('user_registration_requests'));
            $this->db->resetDataCache();
        } catch (\Throwable $e) {}
        \CodeIgniter\Config\Factories::reset();
        Services::reset(true);
        parent::tearDown();
    }

    public function testModelSeparatesIncompleteProfilesFromReviewReady(): void
    {
        [$parishId, $dioceseId, $provinceId] = $this->seedHierarchy();

        $incompleteUserId = $this->insertUser('incomplete.user', 'incomplete@example.org');
        $this->insertProfile($incompleteUserId, null, 'Incomplete', 'User');
        $incompleteReqId = $this->insertRegistrationRequest($incompleteUserId, $parishId, $dioceseId, $provinceId, 'pending_profile');

        $reviewUserId = $this->insertUser('review.user', 'review@example.org');
        $this->insertProfile($reviewUserId, null, 'Review', 'User');
        $reviewReqId = $this->insertRegistrationRequest($reviewUserId, $parishId, $dioceseId, $provinceId, 'pending_review');

        $requestModel = model('UserRegistrationRequestModel');
        $incompleteList = $requestModel->getIncompleteByParish($parishId);
        $this->assertCount(1, $incompleteList);
        $this->assertSame($incompleteUserId, (int) $incompleteList[0]['user_id']);
        $this->assertSame('pending_profile', (string) $incompleteList[0]['status']);
        $this->assertSame(1, $requestModel->countIncompleteByParish($parishId));

        $pendingList = $requestModel->getPendingByParish($parishId);
        $this->assertCount(1, $pendingList);
        $this->assertSame($reviewUserId, (int) $pendingList[0]['user_id']);
        $this->assertSame('pending_review', (string) $pendingList[0]['status']);
        $this->assertSame(1, $requestModel->countPendingByParish($parishId));
    }

    public function testApproveRejectsPendingProfileAndAcceptsPendingReview(): void
    {
        [$parishId, $dioceseId, $provinceId] = $this->seedHierarchy();
        $adminId = $this->insertUser('parish.admin', 'admin@example.org');
        $this->insertGroup($adminId, 'parishadmin');
        $this->insertProfile($adminId, $parishId, 'Admin', 'User');
        $actorScope = ['parish_id' => $parishId, 'diocese_id' => $dioceseId, 'province_id' => $provinceId];

        $service = new RegistrationApprovalService();

        // 1. Pending profile request -> approve must fail
        $incompleteUserId = $this->insertUser('incomplete.applicant', 'incomplete.app@example.org');
        $this->insertProfile($incompleteUserId, null, 'Inc', 'App');
        $incompleteReqId = $this->insertRegistrationRequest($incompleteUserId, $parishId, $dioceseId, $provinceId, 'pending_profile');

        $error = $service->approve($adminId, $incompleteReqId, 'member', $actorScope);
        $this->assertNotNull($error);
        $this->assertSame(lang('Diocesan.registrationRequestMissingOrReviewed'), $error);

        // 2. Pending review request -> approve succeeds
        $reviewUserId = $this->insertUser('ready.applicant', 'ready.app@example.org');
        $this->insertProfile($reviewUserId, null, 'Ready', 'App');
        $reviewReqId = $this->insertRegistrationRequest($reviewUserId, $parishId, $dioceseId, $provinceId, 'pending_review');

        $this->injectAuthUser($adminId);
        $error = $service->approve($adminId, $reviewReqId, 'member', $actorScope);
        $this->assertNull($error);

        $updatedReq = $this->db->table($this->resolveTableName('user_registration_requests'))
            ->where('id', $reviewReqId)
            ->get()
            ->getRowArray();
        $this->assertSame('approved', (string) ($updatedReq['status'] ?? ''));
    }

    public function testEscalateRejectsPendingProfileAndAcceptsPendingReview(): void
    {
        [$parishId, $dioceseId, $provinceId] = $this->seedHierarchy();
        $adminId = $this->insertUser('parish.admin2', 'admin2@example.org');
        $this->insertGroup($adminId, 'parishadmin');
        $this->insertProfile($adminId, $parishId, 'Admin', 'Two');
        $actorScope = ['parish_id' => $parishId, 'diocese_id' => $dioceseId, 'province_id' => $provinceId];

        $service = new RegistrationApprovalService();

        // 1. Pending profile request -> escalate must fail
        $incompleteUserId = $this->insertUser('inc.esc', 'inc.esc@example.org');
        $this->insertProfile($incompleteUserId, null, 'Inc', 'Esc');
        $incompleteReqId = $this->insertRegistrationRequest($incompleteUserId, $parishId, $dioceseId, $provinceId, 'pending_profile');

        $error = $service->escalate($adminId, $incompleteReqId, $actorScope, 'Trying to escalate incomplete');
        $this->assertNotNull($error);
        $this->assertSame(lang('Diocesan.registrationRequestMissingOrReviewed'), $error);

        // 2. Pending review request -> escalate succeeds
        $reviewUserId = $this->insertUser('ready.esc', 'ready.esc@example.org');
        $this->insertProfile($reviewUserId, null, 'Ready', 'Esc');
        $reviewReqId = $this->insertRegistrationRequest($reviewUserId, $parishId, $dioceseId, $provinceId, 'pending_review');

        $error = $service->escalate($adminId, $reviewReqId, $actorScope, 'Needs diocesan attention');
        $this->assertNull($error);

        $updatedReq = $this->db->table($this->resolveTableName('user_registration_requests'))
            ->where('id', $reviewReqId)
            ->get()
            ->getRowArray();
        $this->assertSame('pending_review', (string) ($updatedReq['status'] ?? ''));
        $this->assertSame('Needs diocesan attention', (string) ($updatedReq['review_notes'] ?? ''));
        $this->assertSame($adminId, (int) ($updatedReq['reviewed_by'] ?? 0));
    }

    public function testDismissAndBanAcceptPendingProfile(): void
    {
        [$parishId, $dioceseId, $provinceId] = $this->seedHierarchy();
        $adminId = $this->insertUser('parish.admin3', 'admin3@example.org');
        $this->insertGroup($adminId, 'parishadmin');
        $this->insertProfile($adminId, $parishId, 'Admin', 'Three');
        $actorScope = ['parish_id' => $parishId, 'diocese_id' => $dioceseId, 'province_id' => $provinceId];

        $service = new RegistrationApprovalService();

        // 1. Dismiss pending_profile
        $user1Id = $this->insertUser('user1.dismiss', 'user1@example.org');
        $this->insertProfile($user1Id, null, 'User', 'One');
        $req1Id = $this->insertRegistrationRequest($user1Id, $parishId, $dioceseId, $provinceId, 'pending_profile');

        $error = $service->dismiss($adminId, $req1Id, $actorScope);
        $this->assertNull($error);

        $req1 = $this->db->table($this->resolveTableName('user_registration_requests'))->where('id', $req1Id)->get()->getRowArray();
        $this->assertSame('dismissed', (string) ($req1['status'] ?? ''));

        // 2. Ban pending_profile
        $user2Id = $this->insertUser('user2.ban', 'user2@example.org');
        $this->insertProfile($user2Id, null, 'User', 'Two');
        $req2Id = $this->insertRegistrationRequest($user2Id, $parishId, $dioceseId, $provinceId, 'pending_profile');

        $error = $service->ban($adminId, $req2Id, $actorScope);
        $this->assertNull($error);

        $req2 = $this->db->table($this->resolveTableName('user_registration_requests'))->where('id', $req2Id)->get()->getRowArray();
        $this->assertSame('banned', (string) ($req2['status'] ?? ''));

        $user2Record = $this->db->table($this->resolveTableName('users'))->where('id', $user2Id)->get()->getRowArray();
        $this->assertSame(0, (int) ($user2Record['active'] ?? 1));
    }

    public function testDeleteAcceptsPendingProfileAndCleansUp(): void
    {
        [$parishId, $dioceseId, $provinceId] = $this->seedHierarchy();
        $adminId = $this->insertUser('parish.admin4', 'admin4@example.org');
        $this->insertGroup($adminId, 'parishadmin');
        $this->insertProfile($adminId, $parishId, 'Admin', 'Four');
        $actorScope = ['parish_id' => $parishId, 'diocese_id' => $dioceseId, 'province_id' => $provinceId];

        $service = new RegistrationApprovalService();

        $userId = $this->insertUser('user.delete', 'user.del@example.org');
        $this->insertProfile($userId, null, 'User', 'Delete');
        $reqId = $this->insertRegistrationRequest($userId, $parishId, $dioceseId, $provinceId, 'pending_profile');

        $error = $service->delete($adminId, $reqId, $actorScope);
        $this->assertNull($error);

        $this->assertSame(0, $this->db->table($this->resolveTableName('user_registration_requests'))->where('id', $reqId)->countAllResults());
        $this->assertSame(0, $this->db->table($this->resolveTableName('users'))->where('id', $userId)->countAllResults());
        $this->assertNull(model('ProfileModel')->where('user_id', $userId)->first());
    }

    public function testAutoApproveForElevatedAdminEnforcesTopTierAndApprovesReviewReady(): void
    {
        [$parishId, $dioceseId, $provinceId] = $this->seedHierarchy();
        $service = new RegistrationApprovalService();

        // 1. Regular user trying to auto-approve fails
        $regularUserId = $this->insertUser('regular.user', 'regular@example.org');
        $this->insertGroup($regularUserId, 'member');
        $this->insertProfile($regularUserId, null, 'Regular', 'User');
        $req1Id = $this->insertRegistrationRequest($regularUserId, $parishId, $dioceseId, $provinceId, 'pending_review');

        $denied = $service->autoApproveForElevatedAdmin($regularUserId, $req1Id, 'member');
        $this->assertSame(lang('Diocesan.accessDeniedDiocesanAdmin'), $denied);

        // 2. Elevated system admin auto-approves
        $sysAdminId = $this->insertUser('sys.admin', 'sysadmin@example.org');
        $this->insertGroup($sysAdminId, 'systemadmin');
        $this->insertProfile($sysAdminId, null, 'System', 'Admin');
        $this->injectAuthUser($sysAdminId);
        $req2Id = $this->insertRegistrationRequest($sysAdminId, $parishId, $dioceseId, $provinceId, 'pending_review');

        $approved = $service->autoApproveForElevatedAdmin($sysAdminId, $req2Id, 'member');
        $this->assertNull($approved);

        $req2 = $this->db->table($this->resolveTableName('user_registration_requests'))->where('id', $req2Id)->get()->getRowArray();
        $this->assertSame('approved', (string) ($req2['status'] ?? ''));
    }

    private function seedHierarchy(): array
    {
        $provinceId = (int) $this->db->table($this->resolveTableName('province'))->insert([
            'name' => 'Hierarchy Province',
            'province_number' => 'HP-1',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $provinceId = (int) $this->db->insertID();

        $this->db->table($this->resolveTableName('diocese'))->insert([
            'province_id' => $provinceId,
            'name' => 'Hierarchy Diocese',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $dioceseId = (int) $this->db->insertID();

        $this->db->table($this->resolveTableName('parish'))->insert([
            'diocese_id' => $dioceseId,
            'name' => 'Hierarchy Parish',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $parishId = (int) $this->db->insertID();

        return [$parishId, $dioceseId, $provinceId];
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
        $this->db->table($this->resolveTableName('user_registration_requests'))->insert([
            'user_id' => $userId,
            'requested_parish_id' => $parishId,
            'diocese_id' => $dioceseId,
            'province_id' => $provinceId,
            'status' => $status,
            'requested_role' => 'member',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function injectAuthUser(int $userId): void
    {
        $fakeUser = new class($userId) extends User {
            public function __construct(private int $userIdForTest)
            {
                parent::__construct([
                    'id' => $userIdForTest,
                    'username' => 'auth.user.' . $userIdForTest,
                    'email' => 'auth.user.' . $userIdForTest . '@example.org',
                    'active' => 1,
                ]);
            }

            public function can(string ...$permissions): bool
            {
                return true;
            }

            public function inGroup(string ...$groups): bool
            {
                return true;
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
            first_name TEXT NULL,
            last_name TEXT NULL,
            phone TEXT NULL,
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
        $this->createTableIfNotExists('auth_groups_users', '
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            "group" TEXT NOT NULL,
            created_at TEXT NULL
        ');
        $this->createTableIfNotExists('parish_affiliation', '
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            linked_user_id INTEGER NOT NULL,
            parish_id INTEGER NOT NULL,
            diocese_id INTEGER NULL,
            membership_status TEXT NULL,
            is_home INTEGER DEFAULT 0,
            active INTEGER DEFAULT 1,
            created_at TEXT NULL,
            updated_at TEXT NULL
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
        $this->db->resetDataCache();
    }

    private function createTableIfNotExists(string $base, string $definition): void
    {
        $table = $this->resolveTableName($base);
        $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (' . $definition . ')');
    }

    private function resolveTableName(string $base): string
    {
        return $this->db->getPrefix() . $base;
    }
}
