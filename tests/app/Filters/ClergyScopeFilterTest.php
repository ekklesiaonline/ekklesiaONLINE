<?php

declare(strict_types=1);

use App\Filters\ClergyScopeFilter;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * Filter-path coverage for ClergyScopeFilter: the scoped ecclesial check
 * (clergy_access_grant office/delegation matching) stacked on the coarse
 * Shield permission gate. Coarse permission is granted directly through
 * auth_permissions_users so the scoped grant is the deciding factor.
 *
 * @internal
 */
final class ClergyScopeFilterTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    /** @var array<string, list<int>> */
    private array $created = [];
    private ?int $reviewerUserId = null;

    protected function setUp(): void
    {
        parent::setUp();
        helper('auth');
        Services::reset(true);
        $this->db->query('CREATE TABLE IF NOT EXISTS db_auth_remember_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            selector TEXT NOT NULL,
            hashedValidator TEXT NOT NULL,
            expires TEXT NOT NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL
        )');
        $this->db->query('CREATE TABLE IF NOT EXISTS db_diocese_admin_primary (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            diocese_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            created_by INTEGER NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL
        )');
    }

    protected function tearDown(): void
    {
        if (isset($this->created['diocese'])) {
            $this->db->table('deanery_parish_membership')->whereIn('deanery_id',
                array_column($this->db->table('deanery')->whereIn('diocese_id', $this->created['diocese'])->get()->getResultArray(), 'id') ?: [0]
            )->delete();
            $this->db->table('deanery')->whereIn('diocese_id', $this->created['diocese'])->delete();
        }
        foreach (['clergy_access_grant', 'clergy_ordination', 'clergy_canonical_status', 'leadership_position',
            'auth_permissions_users', 'auth_groups_users', 'diocese_admin_primary',
            'user_person', 'profile', 'auth_identities',
            'users', 'person', 'parish', 'diocese', 'province'] as $table) {
            if (! empty($this->created[$table])) {
                $this->db->table($table)->whereIn('id', $this->created[$table])->delete();
            }
        }
        $this->created = [];
        Services::reset(true);
        parent::tearDown();
    }

    public function testGrantMatchingParishScopeAllows(): void
    {
        [$diocese, , $parish] = $this->places();
        [$user, $person] = $this->personUser($parish);
        $this->order($person, 'priest');
        $this->standing($person, $diocese);
        $position = $this->position($user, $person, 'parish', $parish, 'rector');
        $this->grant($user, $position, 'pastoral.parish.manage', 'parish', $parish);
        $this->loginWithPermission($user, 'pastoral.parish.manage');

        self::assertNull($this->runFilter('pastoral.parish.manage'));
    }

    public function testGrantForDifferentParishDenies(): void
    {
        [$diocese, , $parishA, $parishB] = $this->places();
        [$user, $person] = $this->personUser($parishA);
        $this->order($person, 'priest');
        $this->standing($person, $diocese);
        $position = $this->position($user, $person, 'parish', $parishB, 'rector');
        $this->grant($user, $position, 'pastoral.parish.manage', 'parish', $parishB);
        $this->loginWithPermission($user, 'pastoral.parish.manage');

        self::assertInstanceOf(RedirectResponse::class, $this->runFilter('pastoral.parish.manage'));
    }

    public function testStaleSessionSelectionDoesNotOverrideHomeParish(): void
    {
        // A non-elevated user resolves to their home parish even when the
        // session holds a different selection (left by an elevated role):
        // matching getOperationalParishId(), which only honors the session
        // selection for province/diocesan admins.
        [$diocese, , $parishA, $parishB] = $this->places();
        [$user, $person] = $this->personUser($parishA);
        $this->order($person, 'priest');
        $this->standing($person, $diocese);
        $position = $this->position($user, $person, 'parish', $parishA, 'rector');
        $this->grant($user, $position, 'pastoral.parish.manage', 'parish', $parishA);
        $this->loginWithPermission($user, 'pastoral.parish.manage');
        session()->set('selected_parish_id', $parishB);

        self::assertNull($this->runFilter('pastoral.parish.manage'));
    }

    public function testDiocesanAdminFallsBackToFirstDioceseParish(): void
    {
        // Diocesan admin with no selected or home parish defaults to the
        // first parish of their assigned diocese, like getOperationalParishId().
        [$diocese, , $parishA, $parishB] = $this->places();
        [$user, $person] = $this->personUser();
        $this->order($person, 'priest');
        $this->standing($person, $diocese);
        $position = $this->position($user, $person, 'parish', $parishA, 'rector');
        $grant = $this->grant($user, $position, 'pastoral.parish.manage', 'parish', $parishA);
        $this->row('auth_groups_users', ['user_id' => $user, 'group' => 'dioceseadmin']);
        $this->row('diocese_admin_primary', ['user_id' => $user, 'diocese_id' => $diocese]);
        $this->loginWithPermission($user, 'pastoral.parish.manage');

        self::assertNull($this->runFilter('pastoral.parish.manage'));

        // A grant for a different parish than the resolved default still denies.
        $this->db->table('clergy_access_grant')->where('id', $grant)
            ->update(['scope_id' => $parishB]);
        self::assertInstanceOf(RedirectResponse::class, $this->runFilter('pastoral.parish.manage'));
    }

    public function testExpiredOrRevokedGrantDenies(): void
    {
        [$diocese, , $parish] = $this->places();
        [$user, $person] = $this->personUser($parish);
        $this->order($person, 'priest');
        $this->standing($person, $diocese);
        $position = $this->position($user, $person, 'parish', $parish, 'rector');
        $grant = $this->grant($user, $position, 'pastoral.parish.manage', 'parish', $parish);
        $this->loginWithPermission($user, 'pastoral.parish.manage');

        $this->db->table('clergy_access_grant')->where('id', $grant)
            ->update(['ends_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))]);
        self::assertInstanceOf(RedirectResponse::class, $this->runFilter('pastoral.parish.manage'));

        $this->db->table('clergy_access_grant')->where('id', $grant)
            ->update(['ends_at' => null, 'revoked_at' => date('Y-m-d H:i:s')]);
        self::assertInstanceOf(RedirectResponse::class, $this->runFilter('pastoral.parish.manage'));
    }

    public function testNoGrantDeniesEvenWithCoarsePermission(): void
    {
        [, , $parish] = $this->places();
        [$user] = $this->personUser($parish);
        $this->loginWithPermission($user, 'pastoral.parish.manage');

        self::assertInstanceOf(RedirectResponse::class, $this->runFilter('pastoral.parish.manage'));
    }

    public function testAjaxDenialUsesCanonicalErrorEnvelope(): void
    {
        [, , $parish] = $this->places();
        [$user] = $this->personUser($parish);
        $this->loginWithPermission($user, 'pastoral.parish.manage');
        service('request')->setHeader('X-Requested-With', 'XMLHttpRequest');

        $response = $this->runFilter('pastoral.parish.manage');
        $payload = json_decode((string) $response->getBody(), true);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame([], $payload['error']['details']);
        self::assertSame([], $payload['errors']);
    }

    public function testNoAuthoritativeParishScopeDenies(): void
    {
        [$diocese, , $parish] = $this->places();
        [$user, $person] = $this->personUser();
        $this->order($person, 'priest');
        $this->standing($person, $diocese);
        $position = $this->position($user, $person, 'parish', $parish, 'rector');
        $this->grant($user, $position, 'pastoral.parish.manage', 'parish', $parish);
        $this->loginWithPermission($user, 'pastoral.parish.manage');
        // No session selection, no affiliation, no profile parish: fail closed.
        self::assertInstanceOf(RedirectResponse::class, $this->runFilter('pastoral.parish.manage'));
    }

    public function testOrdinaryDelegationToVacantParishStillHoldsThroughFilter(): void
    {
        [$diocese, , $parish] = $this->places();
        [$bishop, $bishopPerson] = $this->personUser();
        $this->order($bishopPerson, 'bishop');
        $this->standing($bishopPerson, $diocese);
        $bishopPosition = $this->position($bishop, $bishopPerson, 'diocese', $diocese, 'diocesan_bishop');

        // Verified licensed priest with no office in the (vacant) parish.
        [$priest, $priestPerson] = $this->personUser($parish);
        $this->order($priestPerson, 'priest');
        $this->standing($priestPerson, $diocese);

        $this->row('clergy_access_grant', [
            'user_id' => $priest, 'source_position_id' => $bishopPosition,
            'permission' => 'governance.report.finalize_clergy',
            'scope_type' => 'parish', 'scope_id' => $parish,
            'grant_type' => 'delegation', 'granted_by_user_id' => $bishop,
            'starts_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);
        $this->loginWithPermission($priest, 'governance.report.finalize_clergy');

        self::assertNull($this->runFilter('governance.report.finalize_clergy'));

        $this->db->table('clergy_access_grant')->where('user_id', $priest)
            ->update(['revoked_at' => date('Y-m-d H:i:s')]);
        self::assertInstanceOf(RedirectResponse::class, $this->runFilter('governance.report.finalize_clergy'));
    }

    private function runFilter(string $permission, string $targetType = 'parish')
    {
        return (new ClergyScopeFilter())->before(service('request'), [$permission, $targetType]);
    }

    private function loginWithPermission(int $userId, string $permission): void
    {
        $this->row('auth_permissions_users', [
            'user_id' => $userId, 'permission' => $permission,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        auth()->loginById($userId);
    }

    private function places(): array
    {
        $province = $this->row('province', ['name' => 'Province IX', 'province_number' => 'IX']);
        $dioceseA = $this->row('diocese', ['province_id' => $province, 'name' => 'Diocese A']);
        $dioceseB = $this->row('diocese', ['province_id' => $province, 'name' => 'Diocese B']);
        $parishA = $this->row('parish', ['diocese_id' => $dioceseA, 'name' => 'Parish A']);
        $parishB = $this->row('parish', ['diocese_id' => $dioceseA, 'name' => 'Parish B']);
        return [$dioceseA, $dioceseB, $parishA, $parishB];
    }

    private function personUser(?int $homeParishId = null): array
    {
        $person = $this->row('person', ['first_name' => 'Test', 'last_name' => 'Clergy']);
        $user = $this->row('users', ['username' => 'clergy.' . uniqid(), 'active' => 1]);
        $this->row('auth_identities', [
            'user_id' => $user, 'type' => 'email_password', 'secret' => 'clergy.test@example.invalid',
        ]);
        $this->row('user_person', ['user_id' => $user, 'person_id' => $person, 'is_primary' => 1]);
        $profile = ['user_id' => $user, 'person_id' => $person, 'portal_access' => 'enabled'];
        if ($homeParishId !== null) {
            $profile['parish_id'] = $homeParishId;
        }
        $this->row('profile', $profile);
        return [$user, $person];
    }

    private function order(int $person, string $order): void
    {
        $this->row('clergy_ordination', [
            'person_id' => $person, 'order_code' => $order,
            'verification_status' => 'verified', 'verified_by_user_id' => $this->reviewerUserId(),
            'verified_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function standing(int $person, int $diocese): void
    {
        $this->row('clergy_canonical_status', [
            'person_id' => $person, 'diocese_id' => $diocese,
            'status_code' => 'licensed', 'effective_from' => date('Y-m-d', strtotime('-1 year')),
        ]);
    }

    private function position(int $user, int $person, string $scope, int $id, string $role): int
    {
        return $this->row('leadership_position', [
            'user_id' => $user, 'person_id' => $person, 'scope_type' => $scope,
            $scope . '_id' => $id, 'role_code' => $role, 'title' => $role,
            'active' => 1, 'started_at' => date('Y-m-d', strtotime('-1 month')),
            'approved_by_user_id' => $this->reviewerUserId(), 'approved_at' => date('Y-m-d H:i:s'),
            'approval_evidence_reference' => 'diocesan-appointment-register',
        ]);
    }

    private function grant(int $user, int $position, string $permission, string $scope, int $id): int
    {
        return $this->row('clergy_access_grant', [
            'user_id' => $user, 'source_position_id' => $position,
            'permission' => $permission, 'scope_type' => $scope, 'scope_id' => $id,
            'grant_type' => 'office', 'granted_by_user_id' => $this->reviewerUserId(),
            'starts_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);
    }

    private function reviewerUserId(): int
    {
        if ($this->reviewerUserId === null) {
            $this->reviewerUserId = $this->row('users', ['username' => 'reviewer.' . uniqid(), 'active' => 1]);
        }

        return $this->reviewerUserId;
    }

    private function row(string $table, array $fields): int
    {
        $this->db->table($table)->insert($fields);
        $id = (int) $this->db->insertID();
        $this->created[$table][] = $id;
        return $id;
    }
}
