<?php

declare(strict_types=1);

namespace Tests;

use App\Services\ClergyAuthorizationService;
use App\Services\ClergyReviewService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class ClergyOrdinaryBootstrapTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    /** @var array<string, list<int>> */
    private array $created = [];

    protected function tearDown(): void
    {
        $userIds = array_values(array_unique($this->created['users'] ?? []));
        if ($userIds !== []) {
            foreach (['clergy_access_grant', 'permission_role_sync_managed', 'auth_permissions_users',
                'auth_groups_users', 'user_person', 'profile', 'auth_identities'] as $table) {
                $this->db->table($table)->whereIn('user_id', $userIds)->delete();
            }
            $this->db->table('leadership_position')->whereIn('user_id', $userIds)->delete();
        }

        $personIds = array_values(array_unique($this->created['person'] ?? []));
        if ($personIds !== []) {
            foreach (['clergy_ordination', 'clergy_canonical_status', 'leadership_position'] as $table) {
                $this->db->table($table)->whereIn('person_id', $personIds)->delete();
            }
        }

        foreach (['users', 'person', 'parish', 'diocese', 'province'] as $table) {
            if (! empty($this->created[$table])) {
                $this->db->table($table)->whereIn('id', array_values(array_unique($this->created[$table])))->delete();
            }
        }
        parent::tearDown();
    }

    public function testBootstrapInstallsVerifiedOrdinaryAndEnablesDelegation(): void
    {
        [, $dioceseA, $dioceseB] = $this->places();
        $actor = $this->systemAdmin();
        [$bishop] = $this->dioceseAdmin($dioceseA);
        $service = $this->reviewService();

        $positionId = $service->installOrdinary($actor, $dioceseA, $bishop, date('Y-m-d', strtotime('-12 years')),
            date('Y-m-d'), 'diocesan-ordination-register-42');

        self::assertNotNull($positionId);
        $authorization = $this->authorization();
        foreach (ClergyReviewService::OFFICE_PERMISSIONS['diocesan_bishop'] as $permission) {
            self::assertTrue($authorization->allows($bishop, $permission, 'diocese', $dioceseA));
            self::assertSame(1, $this->db->table('auth_permissions_users')
                ->where('user_id', $bishop)->where('permission', $permission)->countAllResults());
        }
        self::assertTrue($authorization->isCurrentOrdinary($bishop, $dioceseA));
        self::assertFalse($authorization->allows($bishop, 'governance.diocese.approve', 'diocese', $dioceseB));

        [$otherUser] = $this->personUser($dioceseA);
        $delegationId = $service->issueDelegation($bishop, $otherUser, $dioceseA,
            'clergy.ordination.verify', date('Y-m-d', strtotime('+7 days')), 'delegation-register-7');
        self::assertNotNull($delegationId);
    }

    public function testBootstrapCreatesAndLinksMissingCanonicalPerson(): void
    {
        [, $dioceseA, $dioceseB] = $this->places();
        $actor = $this->systemAdmin();
        [$bishop] = $this->dioceseAdmin($dioceseA, 'enabled', false);

        $positionId = $this->reviewService()->installOrdinary($actor, $dioceseA, $bishop, null,
            date('Y-m-d'), 'ordinary-register-43');

        self::assertNotNull($positionId);
        $profile = $this->db->table('profile')->where('user_id', $bishop)->get()->getRowArray();
        self::assertGreaterThan(0, (int) ($profile['person_id'] ?? 0));
        $personId = (int) $profile['person_id'];
        $this->created['person'][] = $personId;
        self::assertSame(1, $this->db->table('user_person')->where('user_id', $bishop)
            ->where('person_id', $personId)->where('is_primary', 1)->countAllResults());
        self::assertSame('Bishop', $this->db->table('person')->where('id', $personId)->get()->getRow('first_name'));
        $this->assertOrdinaryInstalled($bishop, $dioceseA, $dioceseB);
        [$otherUser] = $this->personUser($dioceseA);
        self::assertNotNull($this->reviewService()->issueDelegation($bishop, $otherUser, $dioceseA,
            'clergy.ordination.verify', date('Y-m-d', strtotime('+7 days')), 'delegation-register-8'));
    }

    public function testNonSystemAdminCannotBootstrapAnOrdinary(): void
    {
        [, $dioceseA] = $this->places();
        [$actor] = $this->personUser($dioceseA);
        [$bishop] = $this->dioceseAdmin($dioceseA);

        self::assertNull($this->reviewService()->installOrdinary($actor, $dioceseA, $bishop, null,
            date('Y-m-d'), 'ordinary-register-44'));
        self::assertSame(0, $this->db->table('leadership_position')->where('user_id', $bishop)->countAllResults());
    }

    public function testSecondBootstrapIsRefusedWhenDioceseHasCurrentOrdinary(): void
    {
        [, $dioceseA] = $this->places();
        $actor = $this->systemAdmin();
        [$firstBishop] = $this->dioceseAdmin($dioceseA);
        [$secondBishop] = $this->dioceseAdmin($dioceseA);
        $service = $this->reviewService();

        self::assertNotNull($service->installOrdinary($actor, $dioceseA, $firstBishop, null,
            date('Y-m-d'), 'ordinary-register-45'));
        self::assertNull($service->installOrdinary($actor, $dioceseA, $secondBishop, null,
            date('Y-m-d'), 'ordinary-register-46'));
        self::assertSame(0, $this->db->table('leadership_position')->where('user_id', $secondBishop)->countAllResults());
    }

    public function testInvitedDioceseAdminCannotBeInstalled(): void
    {
        [, $dioceseA] = $this->places();
        $actor = $this->systemAdmin();
        [$bishop] = $this->dioceseAdmin($dioceseA, 'invited');

        self::assertNull($this->reviewService()->installOrdinary($actor, $dioceseA, $bishop, null,
            date('Y-m-d'), 'ordinary-register-47'));
        self::assertSame(0, $this->db->table('leadership_position')->where('user_id', $bishop)->countAllResults());
    }

    public function testBootstrapRejectsSelfInstallEmptyEvidenceAndMismatchedDiocese(): void
    {
        [, $dioceseA, $dioceseB] = $this->places();
        $actor = $this->systemAdmin();
        $this->addGroup($actor, 'dioceseadmin');
        $service = $this->reviewService();

        self::assertNull($service->installOrdinary($actor, $dioceseA, $actor, null,
            date('Y-m-d'), 'ordinary-register-48'));

        [$bishop] = $this->dioceseAdmin($dioceseA);
        self::assertNull($service->installOrdinary($actor, $dioceseA, $bishop, null, date('Y-m-d'), '  '));
        self::assertSame(0, $this->db->table('leadership_position')->where('user_id', $bishop)->countAllResults());

        [$otherDioceseAdmin] = $this->dioceseAdmin($dioceseB);
        self::assertNull($service->installOrdinary($actor, $dioceseA, $otherDioceseAdmin, null,
            date('Y-m-d'), 'ordinary-register-49'));
        self::assertSame(0, $this->db->table('leadership_position')->where('user_id', $otherDioceseAdmin)->countAllResults());
    }

    public function testCurrentSuspendedStandingBlocksBootstrapWithoutCreatingPosition(): void
    {
        [, $dioceseA] = $this->places();
        $actor = $this->systemAdmin();
        [$bishop, $personId] = $this->dioceseAdmin($dioceseA);
        $this->row('clergy_canonical_status', [
            'person_id' => $personId,
            'diocese_id' => $dioceseA,
            'status_code' => 'suspended',
            'effective_from' => date('Y-m-d', strtotime('-1 day')),
            'effective_to' => null,
            'evidence_reference' => 'suspension-order-1',
            'verified_by_user_id' => $actor,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        self::assertNull($this->reviewService()->installOrdinary($actor, $dioceseA, $bishop, null,
            date('Y-m-d'), 'ordinary-register-suspended'));
        self::assertSame(0, $this->db->table('leadership_position')->where('user_id', $bishop)->countAllResults());
    }

    public function testBootstrapEvidenceLengthCountsUtf8Characters(): void
    {
        [, $dioceseA, $dioceseB] = $this->places();
        $actor = $this->systemAdmin();
        [$bishop255] = $this->dioceseAdmin($dioceseA);
        [$bishop256] = $this->dioceseAdmin($dioceseB);
        $service = $this->reviewService();

        self::assertNotNull($service->installOrdinary($actor, $dioceseA, $bishop255, null,
            date('Y-m-d'), str_repeat('é', 255)));
        self::assertNull($service->installOrdinary($actor, $dioceseB, $bishop256, null,
            date('Y-m-d'), str_repeat('é', 256)));
        self::assertSame(0, $this->db->table('leadership_position')->where('user_id', $bishop256)->countAllResults());
    }

    public function testSystemAdminCanEndOrdinaryOfficeAndInstallSuccessor(): void
    {
        [, $dioceseA] = $this->places();
        $actor = $this->systemAdmin();
        [$bishop, $personId] = $this->dioceseAdmin($dioceseA);
        $service = $this->reviewService();
        $authorization = $this->authorization();
        $positionId = $service->installOrdinary($actor, $dioceseA, $bishop, null,
            date('Y-m-d', strtotime('-12 years')), 'ordinary-register-end');

        self::assertNotNull($positionId);
        $endedOn = date('Y-m-d', strtotime('-1 day'));
        $reason = 'Transferred to another diocese';
        self::assertTrue($service->endOrdinaryOffice($actor, $positionId, $endedOn, $reason));

        $position = $this->db->table('leadership_position')->where('id', $positionId)->get()->getRowArray();
        self::assertSame(0, (int) $position['active']);
        self::assertSame($endedOn, $position['ended_at']);
        self::assertNotEmpty($position['revoked_at']);
        self::assertStringContainsString("Office ended {$endedOn}: {$reason}", (string) $position['notes']);
        self::assertSame(3, $this->db->table('clergy_access_grant')->where('source_position_id', $positionId)
            ->where('revoked_at IS NOT NULL', null, false)->countAllResults());
        self::assertSame(0, $this->db->table('auth_permissions_users')->where('user_id', $bishop)
            ->whereIn('permission', ClergyReviewService::OFFICE_PERMISSIONS['diocesan_bishop'])->countAllResults());
        self::assertSame(1, $this->db->table('clergy_ordination')->where('person_id', $personId)
            ->where('order_code', 'bishop')->where('verification_status', 'verified')->countAllResults());
        self::assertFalse($authorization->isCurrentOrdinary($bishop, $dioceseA));

        [$successor] = $this->dioceseAdmin($dioceseA);
        self::assertNotNull($service->installOrdinary($actor, $dioceseA, $successor, null,
            date('Y-m-d'), 'ordinary-register-successor'));
        self::assertTrue($authorization->isCurrentOrdinary($successor, $dioceseA));
    }

    public function testEndOrdinaryOfficeRefusesNonAdminInvalidDateEmptyReasonAndAlreadyEndedOffice(): void
    {
        [, $dioceseA] = $this->places();
        $actor = $this->systemAdmin();
        [$bishop] = $this->dioceseAdmin($dioceseA);
        [$nonAdmin] = $this->personUser($dioceseA);
        $service = $this->reviewService();
        $positionId = $service->installOrdinary($actor, $dioceseA, $bishop, null,
            date('Y-m-d'), 'ordinary-register-end-refusals');

        self::assertNotNull($positionId);
        self::assertFalse($service->endOrdinaryOffice($nonAdmin, $positionId, date('Y-m-d'), 'Valid reason'));
        self::assertFalse($service->endOrdinaryOffice($actor, $positionId, date('Y-m-d', strtotime('-1 day')), 'Valid reason'));
        self::assertFalse($service->endOrdinaryOffice($actor, $positionId, date('Y-m-d'), '   '));
        self::assertTrue($service->endOrdinaryOffice($actor, $positionId, date('Y-m-d'), 'Valid reason'));
        self::assertFalse($service->endOrdinaryOffice($actor, $positionId, date('Y-m-d'), 'Already ended'));
    }

    private function assertOrdinaryInstalled(int $bishop, int $dioceseA, int $dioceseB): void
    {
        $authorization = $this->authorization();
        foreach (ClergyReviewService::OFFICE_PERMISSIONS['diocesan_bishop'] as $permission) {
            self::assertTrue($authorization->allows($bishop, $permission, 'diocese', $dioceseA));
            self::assertSame(1, $this->db->table('auth_permissions_users')
                ->where('user_id', $bishop)->where('permission', $permission)->countAllResults());
        }
        self::assertTrue($authorization->isCurrentOrdinary($bishop, $dioceseA));
        self::assertFalse($authorization->allows($bishop, 'governance.diocese.approve', 'diocese', $dioceseB));
    }

    private function reviewService(): ClergyReviewService
    {
        return new ClergyReviewService($this->db, new ClergyAuthorizationService($this->db, static fn (): bool => true));
    }

    private function authorization(): ClergyAuthorizationService
    {
        return new ClergyAuthorizationService($this->db, static fn (): bool => true);
    }

    private function places(): array
    {
        $province = $this->row('province', ['name' => 'Province IX', 'province_number' => 'IX']);
        $dioceseA = $this->row('diocese', ['province_id' => $province, 'name' => 'Diocese A']);
        $dioceseB = $this->row('diocese', ['province_id' => $province, 'name' => 'Diocese B']);
        $parish = $this->row('parish', ['diocese_id' => $dioceseA, 'name' => 'Test Parish']);
        return [$province, $dioceseA, $dioceseB, $parish];
    }

    private function systemAdmin(): int
    {
        $user = $this->row('users', ['username' => 'system.' . uniqid(), 'active' => 1]);
        $this->addGroup($user, 'systemadmin');
        return $user;
    }

    private function dioceseAdmin(int $diocese, string $portalAccess = 'enabled', bool $withPerson = true): array
    {
        return $this->personUser($diocese, $portalAccess, $withPerson, 'dioceseadmin');
    }

    private function personUser(?int $diocese, string $portalAccess = 'enabled',
        bool $withPerson = true, ?string $group = null): array
    {
        $person = $withPerson
            ? $this->row('person', ['first_name' => 'Bishop', 'last_name' => 'Bootstrap'])
            : 0;
        $user = $this->row('users', ['username' => 'ordinary.' . uniqid(), 'active' => 1]);
        $this->row('auth_identities', [
            'user_id' => $user, 'type' => 'email_password', 'secret' => uniqid() . '@example.invalid',
        ]);
        $this->row('profile', [
            'user_id' => $user,
            'person_id' => $person > 0 ? $person : null,
            'diocese_id' => $diocese,
            'first_name' => 'Bishop',
            'last_name' => 'Bootstrap',
            'portal_access' => $portalAccess,
        ]);
        if ($person > 0) {
            $this->row('user_person', ['user_id' => $user, 'person_id' => $person, 'is_primary' => 1]);
        }
        if ($group !== null) {
            $this->addGroup($user, $group);
        }
        return [$user, $person > 0 ? $person : null];
    }

    private function addGroup(int $user, string $group): void
    {
        $this->row('auth_groups_users', ['user_id' => $user, 'group' => $group]);
    }

    private function row(string $table, array $fields): int
    {
        $this->db->table($table)->insert($fields);
        $id = (int) $this->db->insertID();
        $this->created[$table][] = $id;
        return $id;
    }
}
