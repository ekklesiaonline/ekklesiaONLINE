<?php

declare(strict_types=1);

namespace Tests;

use App\Services\ClergyAuthorizationService;
use App\Services\ClergyGrantReconciliationService;
use App\Services\ClergyReviewService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class ClergyReviewTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;
    /** @var array<string, list<int>> */
    private array $created = [];

    protected function tearDown(): void
    {
        $userIds = $this->created['users'] ?? [];
        if ($userIds !== []) {
            foreach (['clergy_access_grant', 'permission_role_sync_managed', 'auth_permissions_users'] as $table) {
                $this->db->table($table)->whereIn('user_id', $userIds)->delete();
            }
            $this->db->table('clergy_transition_candidate')->whereIn('user_id', $userIds)->delete();
        }
        $personIds = $this->created['person'] ?? [];
        if ($personIds !== []) {
            foreach (['clergy_ordination', 'clergy_canonical_status', 'leadership_position'] as $table) {
                $this->db->table($table)->whereIn('person_id', $personIds)->delete();
            }
        }
        if (! empty($this->created['diocese'])) {
            $this->db->table('diocese_setting')->whereIn('diocese_id', $this->created['diocese'])
                ->whereIn('key', ['governance.report.signatures_enabled', 'governance.report.signatures_policy_reference'])
                ->delete();
        }
        foreach (['user_person', 'profile', 'auth_identities', 'users', 'person', 'parish', 'diocese', 'province'] as $table) {
            if (! empty($this->created[$table])) {
                $this->db->table($table)->whereIn('id', $this->created[$table])->delete();
            }
        }
        parent::tearDown();
    }

    public function testApprovalGrantsOnlyExactParishAndRevocationCleansManagedDirectPermissions(): void
    {
        [$diocese, $parish, $other] = $this->places();
        [$reviewer] = $this->ordinary($diocese);
        [$priestUser, $priestPerson] = $this->account();
        $this->verified($priestPerson, $diocese, 'priest');
        $officeId = $this->office($priestUser, $priestPerson, 'parish', $parish, 'rector', null);
        $auth = $this->authorization();
        $review = new ClergyReviewService($this->db, $auth);
        self::assertTrue($review->approveOffice($officeId, $reviewer, 'appointment-letter-1'));
        self::assertFalse($review->approveOffice($officeId, $reviewer, 'appointment-letter-1'));
        self::assertSame(3, $this->db->table('clergy_access_grant')->where('source_position_id', $officeId)->countAllResults());
        self::assertTrue($auth->allows($priestUser, 'governance.report.finalize_clergy', 'parish', $parish));
        self::assertFalse($auth->allows($priestUser, 'governance.report.finalize_clergy', 'parish', $other));
        self::assertFalse($auth->allows($priestUser, 'governance.diocese.approve', 'diocese', $diocese));
        self::assertTrue($review->revokeOffice($officeId, $reviewer));
        self::assertFalse($auth->allows($priestUser, 'governance.report.finalize_clergy', 'parish', $parish));
        self::assertSame(0, $this->db->table('auth_permissions_users')->where('user_id', $priestUser)
            ->where('permission', 'governance.report.finalize_clergy')->countAllResults());
    }

    public function testOrdinaryCannotVerifySelfOrApproveAnotherConcurrentOrdinary(): void
    {
        [$diocese] = $this->places();
        [$reviewer, $reviewerPerson] = $this->ordinary($diocese);
        $review = new ClergyReviewService($this->db, $this->authorization());
        self::assertNull($review->verifyOrdination($reviewerPerson, $diocese, 'priest', null,
            date('Y-m-d'), 'diocesan register', $reviewer));
        [$bishopUser, $bishopPerson] = $this->account();
        $this->verified($bishopPerson, $diocese, 'bishop');
        $anotherOrdinary = $this->office($bishopUser, $bishopPerson, 'diocese', $diocese, 'diocesan_bishop', null);
        self::assertFalse($review->approveOffice($anotherOrdinary, $reviewer, 'appointment-letter-2'));
        self::assertSame(0, $this->db->table('clergy_access_grant')
            ->where('source_position_id', $anotherOrdinary)->countAllResults());
    }

    public function testPersonWithoutPortalAccountCanHaveDocumentedOrdinationButNoAppGrant(): void
    {
        [$diocese] = $this->places();
        [$reviewer] = $this->ordinary($diocese);
        $personId = $this->row('person', ['first_name' => 'No', 'last_name' => 'Portal']);
        $review = new ClergyReviewService($this->db, $this->authorization());
        $id = $review->verifyOrdination($personId, $diocese, 'deacon', 'vocational',
            date('Y-m-d', strtotime('-1 year')), 'register-1', $reviewer);
        self::assertNotNull($id);
        self::assertNotNull($review->recordStanding($personId, $diocese, 'licensed',
            date('Y-m-d'), null, 'license-1', $reviewer));
        self::assertSame(3, $this->db->table('clergy_access_grant')->countAllResults());
        self::assertSame(0, $this->db->table('leadership_position')->where('person_id', $personId)->countAllResults());
    }

    public function testVacancyDelegationExpiresWhenRectorArrivesAndCanBeRevoked(): void
    {
        [$diocese, $parish, $other] = $this->places();
        [$ordinary] = $this->ordinary($diocese);
        [$priest, $person] = $this->account();
        $this->verified($person, $diocese, 'priest');
        $auth = $this->authorization();
        $review = new ClergyReviewService($this->db, $auth);
        $id = $review->issueParishVacancyDelegation($ordinary, $priest, $parish,
            date('Y-m-d', strtotime('+7 days')), 'vacancy-appointment-1');
        self::assertNotNull($id);
        self::assertTrue($auth->allows($priest, 'governance.report.finalize_clergy', 'parish', $parish));
        self::assertFalse($auth->allows($priest, 'governance.report.finalize_clergy', 'parish', $other));
        [$rectorUser, $rectorPerson] = $this->account();
        $this->verified($rectorPerson, $diocese, 'priest');
        $this->office($rectorUser, $rectorPerson, 'parish', $parish, 'rector', 999);
        self::assertFalse($auth->allows($priest, 'governance.report.finalize_clergy', 'parish', $parish));
        self::assertTrue($review->revokeDelegation($id, $ordinary));
        self::assertSame(0, $this->db->table('auth_permissions_users')->where('user_id', $priest)
            ->where('permission', 'governance.report.finalize_clergy')->countAllResults());
    }

    public function testAcceptingLegacyClergyCandidateDoesNotProvisionAnyAccess(): void
    {
        [$diocese] = $this->places();
        [$reviewer] = $this->ordinary($diocese);
        [$candidateUser, $candidatePerson] = $this->account();
        $this->db->table('profile')->where('user_id', $candidateUser)
            ->update(['diocese_id' => $diocese]);
        $this->verified($candidatePerson, $diocese, 'priest');
        $candidateId = $this->row('clergy_transition_candidate', [
            'source_type' => 'legacy_group', 'source_id' => 301,
            'user_id' => $candidateUser, 'person_id' => $candidatePerson,
            'review_status' => 'pending',
        ]);
        self::assertTrue((new ClergyReviewService($this->db, $this->authorization()))
            ->reviewCandidate($candidateId, $diocese, 'approved', 'priest', null, $reviewer));
        self::assertSame('approved', $this->db->table('clergy_transition_candidate')
            ->where('id', $candidateId)->get()->getRow('review_status'));
        self::assertSame(0, $this->db->table('clergy_access_grant')
            ->where('user_id', $candidateUser)->countAllResults());
        self::assertSame(0, $this->db->table('auth_permissions_users')
            ->where('user_id', $candidateUser)->countAllResults());
    }

    public function testExpiryReconciliationDoesNotTouchManualPermissions(): void
    {
        [$diocese, $parish] = $this->places();
        [$ordinary] = $this->ordinary($diocese);
        [$priest, $person] = $this->account();
        $this->verified($person, $diocese, 'priest');
        $id = (new ClergyReviewService($this->db, $this->authorization()))
            ->issueParishVacancyDelegation($ordinary, $priest, $parish,
                date('Y-m-d', strtotime('+7 days')), 'vacancy-document');
        self::assertNotNull($id);
        $this->db->table('clergy_access_grant')->where('id', $id)
            ->update(['ends_at' => date('Y-m-d H:i:s', strtotime('-1 day'))]);
        $this->row('auth_permissions_users', [
            'user_id' => $priest, 'permission' => 'member.edit',
        ]);
        $reconcile = new ClergyGrantReconciliationService($this->db);
        self::assertSame(1, $reconcile->reconcile()['stale_sources']);
        self::assertSame(1, $this->db->table('permission_role_sync_managed')
            ->where('source_type', 'delegation')->where('source_key', (string) $id)->countAllResults());
        self::assertSame(1, $reconcile->reconcile(true)['removed_permissions']);
        self::assertSame(1, $this->db->table('auth_permissions_users')
            ->where('user_id', $priest)->where('permission', 'member.edit')->countAllResults());
        self::assertSame(0, $this->db->table('auth_permissions_users')
            ->where('user_id', $priest)
            ->where('permission', 'governance.report.finalize_clergy')->countAllResults());
    }

    public function testOnlyVerifiedOrdinaryCanEnableLocalReportSignaturePolicy(): void
    {
        [$diocese] = $this->places();
        [$ordinary] = $this->ordinary($diocese);
        [$admin] = $this->account();
        $review = new ClergyReviewService($this->db, $this->authorization());
        self::assertFalse($review->setReportSignaturePolicy($admin, $diocese, true, 'diocesan-decision-1'));
        self::assertTrue($review->setReportSignaturePolicy($ordinary, $diocese, true, 'diocesan-decision-1'));
        self::assertSame('1', $this->db->table('diocese_setting')->where('diocese_id', $diocese)
            ->where('key', 'governance.report.signatures_enabled')->get()->getRow('value'));
        self::assertTrue($review->setReportSignaturePolicy($ordinary, $diocese, false, 'diocesan-decision-2'));
        self::assertSame('0', $this->db->table('diocese_setting')->where('diocese_id', $diocese)
            ->where('key', 'governance.report.signatures_enabled')->get()->getRow('value'));
    }

    private function authorization(): ClergyAuthorizationService
    {
        return new ClergyAuthorizationService($this->db, static fn (): bool => true);
    }

    private function places(): array
    {
        $province = $this->row('province', ['name' => 'Province', 'province_number' => 'I']);
        $diocese = $this->row('diocese', ['province_id' => $province, 'name' => 'A Diocese']);
        $parish = $this->row('parish', ['diocese_id' => $diocese, 'name' => 'A Parish']);
        $other = $this->row('parish', ['diocese_id' => $diocese, 'name' => 'Another Parish']);
        return [$diocese, $parish, $other];
    }

    private function account(): array
    {
        $person = $this->row('person', ['first_name' => 'Test', 'last_name' => 'Cleric']);
        $user = $this->row('users', ['username' => 'review.' . uniqid(), 'active' => 1]);
        $this->row('auth_identities', ['user_id' => $user, 'type' => 'email_password', 'secret' => 'review@example.invalid']);
        $this->row('user_person', ['user_id' => $user, 'person_id' => $person, 'is_primary' => 1]);
        $this->row('profile', ['user_id' => $user, 'person_id' => $person, 'portal_access' => 'enabled']);
        return [$user, $person];
    }

    private function ordinary(int $diocese): array
    {
        [$user, $person] = $this->account();
        $this->verified($person, $diocese, 'bishop');
        $officeId = $this->office($user, $person, 'diocese', $diocese, 'diocesan_bishop', 999);
        foreach (['clergy.appointment.manage', 'clergy.ordination.verify', 'governance.diocese.approve'] as $permission) {
            $this->row('clergy_access_grant', [
                'user_id' => $user, 'permission' => $permission, 'scope_type' => 'diocese',
                'scope_id' => $diocese, 'source_position_id' => $officeId,
                'grant_type' => 'office', 'granted_by_user_id' => 999,
                'starts_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            ]);
        }
        return [$user, $person];
    }

    private function verified(int $person, int $diocese, string $order): void
    {
        $this->row('clergy_ordination', [
            'person_id' => $person, 'order_code' => $order, 'verification_status' => 'verified',
            'verified_by_user_id' => 999, 'verified_at' => date('Y-m-d H:i:s'),
        ]);
        $this->row('clergy_canonical_status', [
            'person_id' => $person, 'diocese_id' => $diocese, 'status_code' => 'licensed',
            'effective_from' => date('Y-m-d', strtotime('-1 year')),
        ]);
    }

    private function office(int $user, int $person, string $scope, int $id, string $role, ?int $approver): int
    {
        return $this->row('leadership_position', [
            'user_id' => $user, 'person_id' => $person, 'scope_type' => $scope,
            $scope . '_id' => $id, 'title' => $role, 'role_code' => $role,
            'role_category' => 'clergy', 'clergy_order' => $role === 'diocesan_bishop' ? 'bishop' : 'priest',
            'active' => 1, 'started_at' => date('Y-m-d', strtotime('-3 months')),
            'approved_by_user_id' => $approver,
            'approved_at' => $approver !== null ? date('Y-m-d H:i:s') : null,
            'approval_evidence_reference' => $approver !== null ? 'diocesan-register-1' : null,
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
