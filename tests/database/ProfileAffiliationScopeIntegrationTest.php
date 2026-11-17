<?php

use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Tests\Support\Database\CanonicalTestSchema;

/**
 * Security regression: POST /profile/update previously honored a raw
 * `affiliations[]` array, letting a self-registered member attach themselves
 * to ANY parish with a self-chosen membership_status (including 'member').
 * Self-service secondary affiliations must be pinned to 'visitor' and the
 * parish must resolve to a real parish row.
 *
 * @internal
 */
final class ProfileAffiliationScopeIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    protected function setUp(): void
    {
        parent::setUp();
        helper('auth');
        Services::reset(true);
        // CanonicalTestSchema creates the shared tables with the full union
        // column set and repairs missing columns — a hand-rolled CREATE TABLE
        // here would poison every later test class in the process.
        CanonicalTestSchema::ensure($this->db);
    }

    protected function tearDown(): void
    {
        $this->cleanupFixtures();
        Services::reset(true);
        parent::tearDown();
    }

    /**
     * DatabaseTestTrait does not roll back between classes in this suite, so
     * every row this class writes must be removed explicitly — leftover
     * affiliations/users/parishes poison later classes (observed: orphaned
     * affiliation rows breaking SystemDiocesanOrdinariesFeatureTest).
     */
    private array $createdParishIds = [];

    private function cleanupFixtures(): void
    {
        $userIds = array_map('intval', array_column(
            $this->db->table($this->resolveTableName('users'))
                ->select('id')
                ->like('username', 'aff.scope.', 'after')
                ->get()->getResultArray(),
            'id'
        ));

        if ($userIds === []) {
            return;
        }

        $personIds = array_map('intval', array_column(
            $this->db->table($this->resolveTableName('user_person'))
                ->select('person_id')
                ->whereIn('user_id', $userIds)
                ->get()->getResultArray(),
            'person_id'
        ));
        $personIds = array_values(array_unique(array_filter($personIds)));

        $this->db->table($this->resolveTableName('user_person'))->whereIn('user_id', $userIds)->delete();
        $affiliationBuilder = $this->db->table($this->resolveTableName('parish_affiliation'))
            ->groupStart()->whereIn('linked_user_id', $userIds);
        if ($personIds !== []) {
            $affiliationBuilder->orGroupStart()->whereIn('person_id', $personIds)->groupEnd();
        }
        $affiliationBuilder->groupEnd()->delete();
        $this->db->table($this->resolveTableName('profile'))->whereIn('user_id', $userIds)->delete();
        if ($personIds !== []) {
            $this->db->table($this->resolveTableName('person'))->whereIn('id', $personIds)->delete();
        }
        $this->db->table($this->resolveTableName('users'))->whereIn('id', $userIds)->delete();

        if ($this->createdParishIds !== []) {
            $this->db->table($this->resolveTableName('parish'))->whereIn('id', $this->createdParishIds)->delete();
        }
    }

    public function testPostedMemberStatusIsPinnedToVisitor(): void
    {
        $parishA = $this->insertParish('Home Parish A');
        $parishB = $this->insertParish('Foreign Parish B');
        $userId = $this->insertUser('aff.scope.member');
        $this->insertProfile($userId, $parishA);
        $this->insertPrimaryAffiliation($userId, $parishA);
        $this->injectMemberAuth($userId);

        $request = service('request');
        $request->setGlobal('post', [
            'first_name' => 'Attach',
            'last_name'  => 'Test',
            'locale'     => 'en',
            'affiliations' => [
                ['parish_id' => (string) $parishB, 'membership_status' => 'member'],
            ],
        ]);

        $controller = new \MemberPortal\Controllers\Profile();
        $controller->initController($request, service('response'), service('logger'));
        $result = $controller->update();

        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $result);

        $row = $this->db->table($this->resolveTableName('parish_affiliation'))
            ->where('linked_user_id', $userId)
            ->where('parish_id', $parishB)
            ->get()
            ->getRowArray();

        $this->assertIsArray($row, 'Secondary affiliation was not written.');
        $this->assertSame('visitor', (string) ($row['membership_status'] ?? ''),
            'Self-service affiliation must not carry member status.');
    }

    public function testExistingApprovedStatusIsPreservedOnSave(): void
    {
        $parishA = $this->insertParish('Home Parish D');
        $parishB = $this->insertParish('Approved Parish E');
        $userId = $this->insertUser('aff.scope.existing');
        $this->insertProfile($userId, $parishA);
        $this->insertPrimaryAffiliation($userId, $parishA);
        // Operator-approved secondary membership that must survive a profile save.
        $this->db->table($this->resolveTableName('parish_affiliation'))->insert([
            'linked_user_id' => $userId,
            'parish_id' => $parishB,
            'person_id' => null,
            'membership_status' => 'member',
            'is_primary' => 0,
            'source' => 'operator',
            'start_date' => date('Y-m-d'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->injectMemberAuth($userId);

        $request = service('request');
        $request->setGlobal('post', [
            'first_name' => 'Attach',
            'last_name'  => 'Test',
            'locale'     => 'en',
            'affiliations' => [
                ['parish_id' => (string) $parishB, 'membership_status' => 'member'],
            ],
        ]);

        $controller = new \MemberPortal\Controllers\Profile();
        $controller->initController($request, service('response'), service('logger'));
        $result = $controller->update();

        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $result);

        $row = $this->db->table($this->resolveTableName('parish_affiliation'))
            ->where('linked_user_id', $userId)
            ->where('parish_id', $parishB)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        $this->assertIsArray($row, 'Existing affiliation row disappeared.');
        $this->assertSame('member', (string) ($row['membership_status'] ?? ''),
            'A plain profile save must not demote an approved membership.');
    }

    public function testRevokedStatusIsNotRestoredByProfileSave(): void
    {
        $parishA = $this->insertParish('Home Parish F');
        $parishB = $this->insertParish('Revoked Parish G');
        $userId = $this->insertUser('aff.scope.revoked');
        $this->insertProfile($userId, $parishA);
        $this->insertPrimaryAffiliation($userId, $parishA);
        // Simulates the post-revocation state: an operator already demoted
        // this secondary affiliation to visitor (or removed membership).
        $this->db->table($this->resolveTableName('parish_affiliation'))->insert([
            'linked_user_id' => $userId,
            'parish_id' => $parishB,
            'person_id' => null,
            'membership_status' => 'visitor',
            'is_primary' => 0,
            'source' => 'operator',
            'start_date' => date('Y-m-d'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->injectMemberAuth($userId);

        $request = service('request');
        $request->setGlobal('post', [
            'first_name' => 'Attach',
            'last_name'  => 'Test',
            'locale'     => 'en',
            'affiliations' => [
                ['parish_id' => (string) $parishB, 'membership_status' => 'member'],
            ],
        ]);

        $controller = new \MemberPortal\Controllers\Profile();
        $controller->initController($request, service('response'), service('logger'));
        $controller->update();

        $row = $this->db->table($this->resolveTableName('parish_affiliation'))
            ->where('linked_user_id', $userId)
            ->where('parish_id', $parishB)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        $this->assertIsArray($row);
        $this->assertSame('visitor', (string) ($row['membership_status'] ?? ''),
            'Profile save must not restore a revoked membership status.');
    }

    public function testUnknownParishIdIsRejected(): void
    {
        $parishA = $this->insertParish('Home Parish C');
        $userId = $this->insertUser('aff.scope.badparish');
        $this->insertProfile($userId, $parishA);
        $this->insertPrimaryAffiliation($userId, $parishA);
        $this->injectMemberAuth($userId);

        $request = service('request');
        $request->setGlobal('post', [
            'first_name' => 'Attach',
            'last_name'  => 'Test',
            'locale'     => 'en',
            'affiliations' => [
                ['parish_id' => '999999', 'membership_status' => 'member'],
            ],
        ]);

        $controller = new \MemberPortal\Controllers\Profile();
        $controller->initController($request, service('response'), service('logger'));
        $result = $controller->update();

        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $result);

        $count = $this->db->table($this->resolveTableName('parish_affiliation'))
            ->where('linked_user_id', $userId)
            ->where('parish_id', 999999)
            ->countAllResults();

        $this->assertSame(0, $count, 'No affiliation row may be written for a nonexistent parish.');
    }

    // ---------------------------------------------------------------
    // Fixtures
    // ---------------------------------------------------------------

    private function injectMemberAuth(int $userId): void
    {
        $fakeUser = new class($userId) extends User {
            public function __construct(private int $uid)
            {
                parent::__construct([
                    'id' => $uid,
                    'username' => 'aff.scope.user.' . $uid,
                    'email' => 'aff.scope.' . $uid . '@example.org',
                ]);
            }

            public function can(string ...$permissions): bool
            {
                // member tier: dashboard.view + profile.edit only.
                return in_array('dashboard.view', $permissions, true)
                    || in_array('profile.edit', $permissions, true);
            }

            public function inGroup(string ...$groups): bool
            {
                return in_array('member', $groups, true);
            }
        };

        $auth = new class(config('Auth'), $fakeUser, $userId) extends Auth {
            public function __construct($config, private User $fakeUser, private int $userId)
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
                return $this->userId;
            }

            public function user(): ?User
            {
                return $this->fakeUser;
            }
        };

        Services::injectMock('auth', $auth);
    }

    private function insertParish(string $name): int
    {
        $this->db->table($this->resolveTableName('parish'))->insert([
            'name' => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $id = (int) $this->db->insertID();
        $this->createdParishIds[] = $id;

        return $id;
    }

    private function insertUser(string $username): int
    {
        $this->db->table($this->resolveTableName('users'))->insert([
            'username' => $username,
            'active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertProfile(int $userId, int $parishId): int
    {
        $this->db->table($this->resolveTableName('profile'))->insert([
            'user_id' => $userId,
            'parish_id' => $parishId,
            'first_name' => 'Attach',
            'last_name' => 'Test',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertPrimaryAffiliation(int $userId, int $parishId): void
    {
        $this->db->table($this->resolveTableName('parish_affiliation'))->insert([
            'linked_user_id' => $userId,
            'parish_id' => $parishId,
            'person_id' => null,
            'membership_status' => 'member',
            'is_primary' => 1,
            'source' => 'system',
            'start_date' => date('Y-m-d'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function resolveTableName(string $base): string
    {
        return $this->db->getPrefix() !== '' ? $this->db->getPrefix() . $base : $base;
    }

}
