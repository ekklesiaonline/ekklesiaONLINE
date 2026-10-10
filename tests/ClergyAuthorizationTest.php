<?php

declare(strict_types=1);

namespace Tests;

use App\Services\ClergyAuthorizationService;
use App\Services\ClergyReviewService;
use App\Services\DeaneryService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class ClergyAuthorizationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    /** @var array<string, list<int>> */
    private array $created = [];

    protected function tearDown(): void
    {
        if (isset($this->created['diocese'])) {
            $this->db->table('deanery_parish_membership')->whereIn('deanery_id',
                array_column($this->db->table('deanery')->whereIn('diocese_id', $this->created['diocese'])->get()->getResultArray(), 'id') ?: [0]
            )->delete();
            $this->db->table('deanery')->whereIn('diocese_id', $this->created['diocese'])->delete();
        }
        foreach (['clergy_access_grant', 'clergy_ordination', 'clergy_canonical_status', 'leadership_position',
            'user_person', 'profile', 'auth_identities', 'users', 'person', 'parish', 'diocese', 'province'] as $table) {
            if (! empty($this->created[$table])) {
                $this->db->table($table)->whereIn('id', $this->created[$table])->delete();
            }
        }
        $this->created = [];
        parent::tearDown();
    }

    public function testVerifiedRectorNeedsCurrentOfficeAndExactParishGrant(): void
    {
        [$dioceseA, , $parishA, $parishB] = $this->places();
        [$user, $person] = $this->personUser();
        $this->order($person, 'priest');
        $this->standing($person, $dioceseA);
        $position = $this->position($user, $person, 'parish', $parishA, 'rector');
        $this->grant($user, $position, 'governance.report.finalize_clergy', 'parish', $parishA);
        $service = $this->service();
        self::assertTrue($service->allows($user, 'governance.report.finalize_clergy', 'parish', $parishA));
        self::assertFalse($service->allows($user, 'governance.report.finalize_clergy', 'parish', $parishB));
        self::assertFalse($service->allows($user, 'governance.diocese.approve', 'diocese', $dioceseA));
        $this->db->table('leadership_position')->where('id', $position)->update(['approved_at' => null]);
        self::assertFalse($service->allows($user, 'governance.report.finalize_clergy', 'parish', $parishA));
        $this->db->table('leadership_position')->where('id', $position)->update(['approved_at' => date('Y-m-d H:i:s')]);
        $this->db->table('clergy_canonical_status')->where('person_id', $person)->update(['status_code' => 'suspended']);
        self::assertFalse($service->allows($user, 'governance.report.finalize_clergy', 'parish', $parishA));
    }

    public function testBishopOnlyApprovesOwnDioceseAndNotByAdminGroup(): void
    {
        [$dioceseA, $dioceseB] = $this->places();
        [$user, $person] = $this->personUser();
        $this->order($person, 'bishop');
        $this->standing($person, $dioceseA);
        $position = $this->position($user, $person, 'diocese', $dioceseA, 'diocesan_bishop');
        $this->grant($user, $position, 'governance.diocese.approve', 'diocese', $dioceseA);
        $service = $this->service();
        self::assertTrue($service->allows($user, 'governance.diocese.approve', 'diocese', $dioceseA));
        self::assertFalse($service->allows($user, 'governance.diocese.approve', 'diocese', $dioceseB));
        $this->db->table('leadership_position')->where('id', $position)->update(['role_code' => 'suffragan_bishop']);
        self::assertFalse($service->allows($user, 'governance.diocese.approve', 'diocese', $dioceseA));
        $this->db->table('leadership_position')->where('id', $position)->update(['role_code' => 'diocesan_bishop']);
        $this->db->table('clergy_access_grant')->where('source_position_id', $position)->update(['revoked_at' => date('Y-m-d H:i:s')]);
        self::assertFalse($service->allows($user, 'governance.diocese.approve', 'diocese', $dioceseA));
    }

    public function testSuspensionIsSystemWideWhileTransferAndExpiredSuspensionAreNot(): void
    {
        [$dioceseA, $dioceseB] = $this->places();
        [$user, $person] = $this->personUser();
        [$approver] = $this->personUser();
        $this->order($person, 'bishop');
        $this->order($person, 'priest');
        $this->standing($person, $dioceseB);
        $position = $this->position($user, $person, 'diocese', $dioceseB, 'diocesan_bishop');
        $this->db->table('leadership_position')->where('id', $position)
            ->update(['approved_by_user_id' => $approver]);
        $this->grant($user, $position, 'governance.diocese.approve', 'diocese', $dioceseB);
        $service = $this->service();

        self::assertTrue($service->isCurrentOrdinary($user, $dioceseB));
        self::assertTrue($service->canServeAsPriest($user, $dioceseB));

        $suspensionId = $this->row('clergy_canonical_status', [
            'person_id' => $person,
            'diocese_id' => $dioceseA,
            'status_code' => 'suspended',
            'effective_from' => date('Y-m-d', strtotime('-1 day')),
            'effective_to' => null,
            'verified_by_user_id' => $approver,
        ]);
        self::assertTrue($service->hasSystemWideSuspension($person));
        self::assertTrue($service->hasDisqualifyingStanding($person, $dioceseB));
        self::assertFalse($service->hasCurrentStanding($person, $dioceseB));
        self::assertFalse($service->isCurrentOrdinary($user, $dioceseB));
        self::assertFalse($service->canServeAsPriest($user, $dioceseB));

        $this->db->table('clergy_canonical_status')->where('id', $suspensionId)
            ->update(['effective_to' => date('Y-m-d', strtotime('-1 day'))]);
        self::assertFalse($service->hasSystemWideSuspension($person));
        self::assertTrue($service->hasCurrentStanding($person, $dioceseB));
        self::assertTrue($service->isCurrentOrdinary($user, $dioceseB));
        self::assertTrue($service->canServeAsPriest($user, $dioceseB));

        $this->row('clergy_canonical_status', [
            'person_id' => $person,
            'diocese_id' => $dioceseA,
            'status_code' => 'transferred',
            'effective_from' => date('Y-m-d', strtotime('-1 day')),
            'verified_by_user_id' => $approver,
        ]);
        self::assertFalse($service->hasSystemWideSuspension($person));
        self::assertFalse($service->hasDisqualifyingStanding($person, $dioceseB));
        self::assertTrue($service->hasCurrentStanding($person, $dioceseB));
        self::assertTrue($service->isCurrentOrdinary($user, $dioceseB));
    }

    public function testRegionalDeanGetsOnlyCurrentMemberParishView(): void
    {
        [$dioceseA, , $parishA, $parishB] = $this->places();
        [$user, $person] = $this->personUser();
        $this->order($person, 'priest');
        $this->standing($person, $dioceseA);
        $deanery = (new DeaneryService($this->db))->create($dioceseA, 'north', 'North');
        self::assertNotNull($deanery);
        self::assertTrue((new DeaneryService($this->db))->assignParish($deanery, $parishA, date('Y-m-d', strtotime('-2 days')), null, 77));
        $position = $this->position($user, $person, 'deanery', $deanery, 'regional_dean');
        $this->grant($user, $position, 'pastoral.parish.view', 'deanery', $deanery);
        $service = $this->service();
        self::assertTrue($service->allows($user, 'pastoral.parish.view', 'parish', $parishA));
        self::assertFalse($service->allows($user, 'pastoral.parish.view', 'parish', $parishB));
        self::assertFalse($service->allows($user, 'governance.report.finalize_clergy', 'parish', $parishA));
        $this->db->table('deanery_parish_membership')->where('parish_id', $parishA)
            ->update(['ended_at' => date('Y-m-d', strtotime('-1 day'))]);
        self::assertFalse($service->allows($user, 'pastoral.parish.view', 'parish', $parishA));
    }

    public function testLegacyClergyGroupOrUnverifiedOrderNeverSuffices(): void
    {
        [$dioceseA, , $parishA] = $this->places();
        [$user, $person] = $this->personUser();
        $this->standing($person, $dioceseA);
        $position = $this->position($user, $person, 'parish', $parishA, 'rector');
        $this->grant($user, $position, 'governance.report.finalize_clergy', 'parish', $parishA);
        self::assertFalse($this->service()->allows($user, 'governance.report.finalize_clergy', 'parish', $parishA));
        $this->order($person, 'priest');
        $this->db->table('clergy_access_grant')->where('source_position_id', $position)->delete();
        self::assertFalse($this->service()->allows($user, 'governance.report.finalize_clergy', 'parish', $parishA));
    }

    public function testCathedralDeanNeedsExplicitPastoralChargeForReportFinalization(): void
    {
        [$diocese, , $cathedral] = $this->places();
        $this->db->table('parish')->where('id', $cathedral)->update(['congregation_type' => 'cathedral']);
        [$user, $person] = $this->personUser();
        $this->order($person, 'priest');
        $this->standing($person, $diocese);
        $position = $this->position($user, $person, 'parish', $cathedral, 'cathedral_dean');
        $this->grant($user, $position, 'governance.report.finalize_clergy', 'parish', $cathedral);
        $this->grant($user, $position, 'pastoral.parish.view', 'parish', $cathedral);
        $service = $this->service();
        self::assertTrue($service->allows($user, 'pastoral.parish.view', 'parish', $cathedral));
        self::assertFalse($service->allows($user, 'governance.report.finalize_clergy', 'parish', $cathedral));
        $this->db->table('leadership_position')->where('id', $position)
            ->update(['pastoral_in_charge' => 1]);
        self::assertTrue($service->allows($user, 'governance.report.finalize_clergy', 'parish', $cathedral));
        $this->db->table('parish')->where('id', $cathedral)->update(['congregation_type' => 'parish']);
        self::assertFalse($service->allows($user, 'governance.report.finalize_clergy', 'parish', $cathedral));
    }

    public function testLiftingSuspensionRestoresStandingOnlyWhenPositiveRowRemains(): void
    {
        [$dioceseA] = $this->places();
        $auth = $this->service();
        $review = new ClergyReviewService($this->db, $auth);

        // Reviewer: verified ordinary of diocese A.
        [$reviewer, $reviewerPerson] = $this->personUser();
        $this->order($reviewerPerson, 'bishop');
        $this->standing($reviewerPerson, $dioceseA);
        $office = $this->position($reviewer, $reviewerPerson, 'diocese', $dioceseA, 'diocesan_bishop');
        $this->grant($reviewer, $office, 'clergy.ordination.verify', 'diocese', $dioceseA);

        // Case 1: a still-open licensed row — lifting restores current standing automatically.
        [, $personA] = $this->personUser();
        $this->standing($personA, $dioceseA);
        $suspensionA = $this->row('clergy_canonical_status', [
            'person_id' => $personA, 'diocese_id' => $dioceseA, 'status_code' => 'suspended',
            'effective_from' => date('Y-m-d', strtotime('-1 month')), 'verified_by_user_id' => 77,
        ]);
        self::assertFalse($auth->hasCurrentStanding($personA, $dioceseA));
        self::assertTrue($review->liftStanding($suspensionA, date('Y-m-d', strtotime('-1 day')), 'lift-decree', $reviewer));
        self::assertFalse($auth->hasSystemWideSuspension($personA));
        self::assertTrue($auth->hasCurrentStanding($personA, $dioceseA));
        self::assertSame(1, $review->activePositiveStandingCount($personA, $dioceseA));

        // Case 2: no other positive row — lifting leaves the person in an
        // intended limbo (no active standing) until new standing is recorded.
        [, $personB] = $this->personUser();
        $suspensionB = $this->row('clergy_canonical_status', [
            'person_id' => $personB, 'diocese_id' => $dioceseA, 'status_code' => 'inhibited',
            'effective_from' => date('Y-m-d', strtotime('-1 month')), 'verified_by_user_id' => 77,
        ]);
        self::assertTrue($auth->hasSystemWideSuspension($personB));
        self::assertTrue($review->liftStanding($suspensionB, date('Y-m-d', strtotime('-1 day')), 'lift-decree', $reviewer));
        self::assertFalse($auth->hasSystemWideSuspension($personB));
        self::assertFalse($auth->hasCurrentStanding($personB, $dioceseA));
        self::assertSame(0, $review->activePositiveStandingCount($personB, $dioceseA));
    }

    private function service(): ClergyAuthorizationService
    {
        // Coarse Shield permission is mocked true to prove the independent scope gate.
        return new ClergyAuthorizationService($this->db, static fn (): bool => true);
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

    private function personUser(): array
    {
        $person = $this->row('person', ['first_name' => 'Test', 'last_name' => 'Clergy']);
        $user = $this->row('users', ['username' => 'clergy.' . uniqid(), 'active' => 1]);
        $this->row('auth_identities', [
            'user_id' => $user, 'type' => 'email_password', 'secret' => 'clergy.test@example.invalid',
        ]);
        $this->row('user_person', ['user_id' => $user, 'person_id' => $person, 'is_primary' => 1]);
        $this->row('profile', ['user_id' => $user, 'person_id' => $person, 'portal_access' => 'enabled']);
        return [$user, $person];
    }

    private function order(int $person, string $order): void
    {
        $this->row('clergy_ordination', [
            'person_id' => $person, 'order_code' => $order,
            'verification_status' => 'verified', 'verified_by_user_id' => 77,
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
            'approved_by_user_id' => 77, 'approved_at' => date('Y-m-d H:i:s'),
            'approval_evidence_reference' => 'diocesan-appointment-register',
        ]);
    }

    private function grant(int $user, int $position, string $permission, string $scope, int $id): void
    {
        $this->row('clergy_access_grant', [
            'user_id' => $user, 'source_position_id' => $position,
            'permission' => $permission, 'scope_type' => $scope, 'scope_id' => $id,
            'grant_type' => 'office', 'granted_by_user_id' => 77,
            'starts_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);
    }

    private function row(string $table, array $fields): int
    {
        $this->db->table($table)->insert($fields);
        $id = (int) $this->db->insertID();
        $this->created[$table][] = $id;
        return $id;
    }
}
