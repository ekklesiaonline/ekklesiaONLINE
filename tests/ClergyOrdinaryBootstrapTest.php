<?php

declare(strict_types=1);

namespace Tests;

use App\Services\ClergyAuthorizationService;
use App\Services\ClergyGrantReconciliationService;
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

    public function testEndingOrdinaryRevokesOnlyDelegationsIssuedWithinThatDiocese(): void
    {
        [, $dioceseA, $dioceseB, $parishA] = $this->places();
        $parishB = $this->row('parish', ['diocese_id' => $dioceseB, 'name' => 'Diocese B Parish']);
        $actor = $this->systemAdmin();
        [$bishopA] = $this->dioceseAdmin($dioceseA);
        [$bishopB] = $this->dioceseAdmin($dioceseB);
        $service = $this->reviewService();
        $positionA = $service->installOrdinary($actor, $dioceseA, $bishopA, null,
            date('Y-m-d', strtotime('-1 day')), 'ordinary-cascade-a');
        $positionB = $service->installOrdinary($actor, $dioceseB, $bishopB, null,
            date('Y-m-d', strtotime('-1 day')), 'ordinary-cascade-b');
        self::assertNotNull($positionA);
        self::assertNotNull($positionB);

        $dioceseDelegationId = $service->issueDelegation($bishopA, $bishopB, $dioceseA,
            'clergy.appointment.manage', date('Y-m-d', strtotime('+7 days')), 'delegation-cascade-diocese');
        [$priest, $priestPerson] = $this->personUser($dioceseA);
        $now = date('Y-m-d H:i:s');
        $this->row('clergy_ordination', [
            'person_id' => $priestPerson,
            'order_code' => 'priest',
            'verification_status' => 'verified',
            'verified_by_user_id' => $actor,
            'verified_at' => $now,
        ]);
        $this->row('clergy_canonical_status', [
            'person_id' => $priestPerson,
            'diocese_id' => $dioceseA,
            'status_code' => 'licensed',
            'effective_from' => date('Y-m-d', strtotime('-1 day')),
            'verified_by_user_id' => $actor,
        ]);
        $parishDelegationId = $service->issueParishVacancyDelegation($bishopA, $priest, $parishA,
            date('Y-m-d', strtotime('+7 days')), 'delegation-cascade-parish');
        $otherBishopDelegationId = $service->issueDelegation($bishopB, $bishopA, $dioceseB,
            'clergy.appointment.manage', date('Y-m-d', strtotime('+7 days')), 'delegation-other-diocese');
        self::assertNotNull($dioceseDelegationId);
        self::assertNotNull($parishDelegationId);
        self::assertNotNull($otherBishopDelegationId);

        $bishopBPermission = $this->db->table('auth_permissions_users')
            ->where('user_id', $bishopB)->where('permission', 'clergy.appointment.manage')
            ->get()->getRowArray();
        self::assertNotNull($bishopBPermission);
        self::assertSame(1, $this->db->table('permission_role_sync_managed')
            ->where('permission_user_id', $bishopBPermission['id'])
            ->where('source_type', 'office')->where('source_key', (string) $positionB)->countAllResults());

        self::assertTrue($service->endOrdinaryOffice($actor, $positionA, date('Y-m-d'), 'Ordinary office ended'));

        self::assertSame([$dioceseDelegationId, $parishDelegationId], $service->lastRevokedDelegationIds());
        self::assertSame(2, $this->db->table('clergy_access_grant')
            ->whereIn('id', [$dioceseDelegationId, $parishDelegationId])
            ->where('revoked_at IS NOT NULL', null, false)->countAllResults());
        self::assertNull($this->db->table('clergy_access_grant')->where('id', $otherBishopDelegationId)
            ->get()->getRow('revoked_at'));
        self::assertSame(1, $this->db->table('auth_permissions_users')
            ->where('id', $bishopBPermission['id'])->countAllResults());
        self::assertSame(1, $this->db->table('permission_role_sync_managed')
            ->where('permission_user_id', $bishopBPermission['id'])
            ->where('source_type', 'office')->where('source_key', (string) $positionB)->countAllResults());
        self::assertSame(1, $this->db->table('clergy_ordination')->where('person_id',
            $this->db->table('profile')->where('user_id', $bishopA)->get()->getRow('person_id'))
            ->where('order_code', 'bishop')->where('verification_status', 'verified')->countAllResults());
        self::assertTrue($this->authorization()->isCurrentOrdinary($bishopB, $dioceseB));

        self::assertFalse($service->endOrdinaryOffice($actor, $positionA, date('Y-m-d'), 'Already ended'));
        self::assertSame([], $service->lastRevokedDelegationIds());
    }

    public function testEndingOneOfficeKeepsDelegationsWhileIssuerHasAnotherCurrentOrdinaryOffice(): void
    {
        [, $dioceseA] = $this->places();
        $actor = $this->systemAdmin();
        [$bishop] = $this->dioceseAdmin($dioceseA);
        [$recipient] = $this->personUser($dioceseA);
        $service = $this->reviewService();
        $positionId = $service->installOrdinary($actor, $dioceseA, $bishop, null,
            date('Y-m-d', strtotime('-1 day')), 'ordinary-two-offices');
        self::assertNotNull($positionId);

        $now = date('Y-m-d H:i:s');
        $otherPositionId = $this->row('leadership_position', [
            'user_id' => $bishop,
            'person_id' => (int) $this->db->table('profile')->where('user_id', $bishop)->get()->getRow('person_id'),
            'scope_type' => 'diocese',
            'diocese_id' => $dioceseA,
            'title' => 'Diocesan Bishop',
            'role_code' => 'diocesan_bishop',
            'role_category' => 'clergy',
            'clergy_order' => 'bishop',
            'active' => 1,
            'started_at' => date('Y-m-d', strtotime('-1 day')),
            'approved_by_user_id' => $actor,
            'approved_at' => $now,
            'approval_evidence_reference' => 'second-current-office',
        ]);
        foreach (ClergyReviewService::OFFICE_PERMISSIONS['diocesan_bishop'] as $permission) {
            $this->row('clergy_access_grant', [
                'user_id' => $bishop,
                'permission' => $permission,
                'scope_type' => 'diocese',
                'scope_id' => $dioceseA,
                'source_position_id' => $otherPositionId,
                'grant_type' => 'office',
                'granted_by_user_id' => $actor,
                'starts_at' => $now,
            ]);
        }
        $delegationId = $service->issueDelegation($bishop, $recipient, $dioceseA,
            'clergy.ordination.verify', date('Y-m-d', strtotime('+7 days')), 'delegation-retained');
        self::assertNotNull($delegationId);
        self::assertTrue($this->authorization()->isCurrentOrdinary($bishop, $dioceseA));

        self::assertTrue($service->endOrdinaryOffice($actor, $positionId, date('Y-m-d'), 'One office ended'));

        self::assertSame([], $service->lastRevokedDelegationIds());
        self::assertNull($this->db->table('clergy_access_grant')->where('id', $delegationId)
            ->get()->getRow('revoked_at'));
        self::assertTrue($this->authorization()->isCurrentOrdinary($bishop, $dioceseA));
        self::assertTrue($this->authorization()->allows($recipient, 'clergy.ordination.verify', 'diocese', $dioceseA));
    }

    public function testRevokingOrdinaryOfficeCascadesItsDiocesanAndParishDelegations(): void
    {
        [, $dioceseA, , $parishA] = $this->places();
        $actor = $this->systemAdmin();
        [$bishop, $bishopPerson] = $this->dioceseAdmin($dioceseA);
        [$reviewer, $reviewerPerson] = $this->personUser($dioceseA);
        [$priest, $priestPerson] = $this->personUser($dioceseA);
        $service = $this->reviewService();
        $positionId = $service->installOrdinary($actor, $dioceseA, $bishop, null,
            date('Y-m-d', strtotime('-1 day')), 'ordinary-revoke-office');
        self::assertNotNull($positionId);
        $reviewerPositionId = $this->addCurrentOrdinaryOffice($reviewer, $reviewerPerson, $dioceseA, $actor);
        $now = date('Y-m-d H:i:s');
        $this->row('clergy_ordination', [
            'person_id' => $priestPerson,
            'order_code' => 'priest',
            'verification_status' => 'verified',
            'verified_by_user_id' => $actor,
            'verified_at' => $now,
        ]);
        $this->row('clergy_canonical_status', [
            'person_id' => $priestPerson,
            'diocese_id' => $dioceseA,
            'status_code' => 'licensed',
            'effective_from' => date('Y-m-d', strtotime('-1 day')),
            'verified_by_user_id' => $actor,
        ]);
        $dioceseDelegationId = $service->issueDelegation($bishop, $priest, $dioceseA,
            'clergy.appointment.manage', date('Y-m-d', strtotime('+7 days')), 'revoke-office-diocese');
        $parishDelegationId = $service->issueParishVacancyDelegation($bishop, $priest, $parishA,
            date('Y-m-d', strtotime('+7 days')), 'revoke-office-parish');
        self::assertNotNull($dioceseDelegationId);
        self::assertNotNull($parishDelegationId);

        self::assertTrue($service->revokeOffice($positionId, $reviewer));

        self::assertSame([$dioceseDelegationId, $parishDelegationId], $service->lastRevokedDelegationIds());
        self::assertSame(2, $this->db->table('clergy_access_grant')
            ->whereIn('id', [$dioceseDelegationId, $parishDelegationId])
            ->where('revoked_at IS NOT NULL', null, false)->countAllResults());
        self::assertSame(1, $this->db->table('leadership_position')->where('id', $reviewerPositionId)
            ->where('active', 1)->where('revoked_at', null)->countAllResults());
        self::assertSame(1, $this->db->table('clergy_ordination')->where('person_id', $bishopPerson)
            ->where('order_code', 'bishop')->where('verification_status', 'verified')->countAllResults());
        self::assertFalse($service->revokeOffice(0, $reviewer));
        self::assertSame([], $service->lastRevokedDelegationIds());
    }

    public function testReconciliationRevokesDelegationWhenIssuerAccountIsDeactivated(): void
    {
        [, $dioceseA] = $this->places();
        $actor = $this->systemAdmin();
        [$bishop] = $this->dioceseAdmin($dioceseA);
        [$recipient] = $this->personUser($dioceseA);
        $service = $this->reviewService();
        $positionId = $service->installOrdinary($actor, $dioceseA, $bishop, null,
            date('Y-m-d', strtotime('-1 day')), 'ordinary-deactivated-issuer');
        self::assertNotNull($positionId);
        $delegationId = $service->issueDelegation($bishop, $recipient, $dioceseA,
            'clergy.ordination.verify', date('Y-m-d', strtotime('+7 days')), 'deactivated-issuer-delegation');
        self::assertNotNull($delegationId);

        $this->db->table('users')->where('id', $bishop)->update(['active' => 0]);
        $reconciliation = (new ClergyGrantReconciliationService($this->db))->reconcile(true);

        self::assertGreaterThanOrEqual(1, $reconciliation['stale_sources']);
        self::assertNotEmpty($this->db->table('clergy_access_grant')->where('id', $delegationId)
            ->get()->getRow('revoked_at'));
        self::assertSame(0, $this->db->table('permission_role_sync_managed')
            ->where('source_type', 'delegation')->where('source_key', (string) $delegationId)->countAllResults());
        self::assertSame(0, $this->db->table('auth_permissions_users')->where('user_id', $recipient)
            ->where('permission', 'clergy.ordination.verify')->countAllResults());
        self::assertFalse($this->authorization()->allows($recipient, 'clergy.ordination.verify', 'diocese', $dioceseA));
    }

    public function testSystemWideSuspensionBlocksOrdinaryAuthorityAndBootstrapInOtherDiocese(): void
    {
        [, $dioceseA, $dioceseB] = $this->places();
        $actor = $this->systemAdmin();
        [$bishop, $personId] = $this->dioceseAdmin($dioceseB);
        $service = $this->reviewService();
        $positionId = $service->installOrdinary($actor, $dioceseB, $bishop, null,
            date('Y-m-d', strtotime('-1 day')), 'ordinary-before-suspension');
        self::assertNotNull($positionId);
        $this->row('clergy_ordination', [
            'person_id' => $personId,
            'order_code' => 'priest',
            'verification_status' => 'verified',
            'verified_by_user_id' => $actor,
            'verified_at' => date('Y-m-d H:i:s'),
        ]);
        $this->row('clergy_canonical_status', [
            'person_id' => $personId,
            'diocese_id' => $dioceseA,
            'status_code' => 'suspended',
            'effective_from' => date('Y-m-d', strtotime('-1 day')),
            'effective_to' => null,
            'evidence_reference' => 'system-wide-suspension',
            'verified_by_user_id' => $actor,
        ]);

        $authorization = $this->authorization();
        self::assertTrue($authorization->hasSystemWideSuspension($personId));
        self::assertFalse($authorization->isCurrentOrdinary($bishop, $dioceseB));
        self::assertFalse($authorization->canServeAsPriest($bishop, $dioceseB));
        self::assertTrue($service->endOrdinaryOffice($actor, $positionId, date('Y-m-d'), 'Ended during suspension'));
        self::assertNull($service->installOrdinary($actor, $dioceseB, $bishop, null,
            date('Y-m-d'), 'ordinary-during-suspension'));
        self::assertSame(1, $this->db->table('leadership_position')->where('user_id', $bishop)
            ->where('role_code', 'diocesan_bishop')->countAllResults());
    }

    public function testTransferAndExpiredSuspensionDoNotBlockBootstrapInAnotherDiocese(): void
    {
        [, $dioceseA, $dioceseB] = $this->places();
        $actor = $this->systemAdmin();
        $service = $this->reviewService();
        [$transferredBishop, $transferredPerson] = $this->dioceseAdmin($dioceseB);
        $this->row('clergy_canonical_status', [
            'person_id' => $transferredPerson,
            'diocese_id' => $dioceseA,
            'status_code' => 'transferred',
            'effective_from' => date('Y-m-d', strtotime('-1 day')),
            'verified_by_user_id' => $actor,
        ]);
        $transferredPosition = $service->installOrdinary($actor, $dioceseB, $transferredBishop, null,
            date('Y-m-d'), 'ordinary-after-local-transfer');
        self::assertNotNull($transferredPosition);
        self::assertTrue($this->authorization()->isCurrentOrdinary($transferredBishop, $dioceseB));
        self::assertTrue($service->endOrdinaryOffice($actor, $transferredPosition, date('Y-m-d'), 'Next case'));

        [$previouslySuspendedBishop, $previouslySuspendedPerson] = $this->dioceseAdmin($dioceseB);
        $this->row('clergy_canonical_status', [
            'person_id' => $previouslySuspendedPerson,
            'diocese_id' => $dioceseA,
            'status_code' => 'suspended',
            'effective_from' => date('Y-m-d', strtotime('-10 days')),
            'effective_to' => date('Y-m-d', strtotime('-1 day')),
            'verified_by_user_id' => $actor,
        ]);
        $previouslySuspendedPosition = $service->installOrdinary($actor, $dioceseB, $previouslySuspendedBishop, null,
            date('Y-m-d'), 'ordinary-after-expired-suspension');

        self::assertNotNull($previouslySuspendedPosition);
        self::assertFalse($this->authorization()->hasSystemWideSuspension($previouslySuspendedPerson));
        self::assertTrue($this->authorization()->isCurrentOrdinary($previouslySuspendedBishop, $dioceseB));
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

    private function addCurrentOrdinaryOffice(int $userId, int $personId, int $dioceseId, int $approverId): int
    {
        $now = date('Y-m-d H:i:s');
        $this->row('clergy_ordination', [
            'person_id' => $personId,
            'order_code' => 'bishop',
            'verification_status' => 'verified',
            'verified_by_user_id' => $approverId,
            'verified_at' => $now,
        ]);
        $this->row('clergy_canonical_status', [
            'person_id' => $personId,
            'diocese_id' => $dioceseId,
            'status_code' => 'licensed',
            'effective_from' => date('Y-m-d', strtotime('-1 day')),
            'verified_by_user_id' => $approverId,
        ]);
        $positionId = $this->row('leadership_position', [
            'user_id' => $userId,
            'person_id' => $personId,
            'scope_type' => 'diocese',
            'diocese_id' => $dioceseId,
            'title' => 'Diocesan Bishop',
            'role_code' => 'diocesan_bishop',
            'role_category' => 'clergy',
            'clergy_order' => 'bishop',
            'active' => 1,
            'started_at' => date('Y-m-d', strtotime('-1 day')),
            'approved_by_user_id' => $approverId,
            'approved_at' => $now,
            'approval_evidence_reference' => 'reviewer-ordinary-register',
        ]);
        foreach (ClergyReviewService::OFFICE_PERMISSIONS['diocesan_bishop'] as $permission) {
            $this->row('clergy_access_grant', [
                'user_id' => $userId,
                'permission' => $permission,
                'scope_type' => 'diocese',
                'scope_id' => $dioceseId,
                'source_position_id' => $positionId,
                'grant_type' => 'office',
                'granted_by_user_id' => $approverId,
                'starts_at' => $now,
            ]);
        }

        return $positionId;
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
