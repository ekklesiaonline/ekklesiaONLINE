<?php

use App\Services\LeadershipPositionService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * T3.1: leadership appointment good-standing gate.
 *
 * Lay leadership requires a communicant in good standing (or an explicit
 * good_standing_override on the person's membership details); clergy
 * appointments are exempt.
 *
 * @internal
 */
final class LeadershipPositionGateTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    private LeadershipPositionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LeadershipPositionService();
    }

    public function testRequiresGoodStandingForLayStaffAndUnsetCategory(): void
    {
        $this->assertTrue($this->service->requiresGoodStanding(['role_category' => 'lay_staff']));
        $this->assertTrue($this->service->requiresGoodStanding(['role_category' => '']));
        $this->assertTrue($this->service->requiresGoodStanding([]));
    }

    public function testClergyIsExemptFromGoodStandingGate(): void
    {
        $this->assertFalse($this->service->requiresGoodStanding(['role_category' => 'clergy']));
    }

    public function testIsInGoodStandingTrueWithCurrentYearSummaryRow(): void
    {
        // The gate recomputes the snapshot before reading it, so the row
        // must be backed by live data: active user, confirmed profile,
        // current-year pledge, and >= 3 communions.
        $userId = $this->insertPortalUser('enabled');
        $this->db->table('profile')->where('user_id', $userId)->update(['is_confirmed' => 1]);
        $this->insertPledge($userId, 0, 301);
        $this->insertCommunions($userId, 301, 3);
        $this->insertStandingRow($userId, (int) date('Y'), 1);

        $this->assertTrue($this->service->isInGoodStanding($userId, 201, 301));
    }

    public function testIsInGoodStandingFalseWithoutSummaryRow(): void
    {
        $this->assertFalse($this->service->isInGoodStanding(999, 999, 301));
    }

    public function testIsInGoodStandingFalseWhenSummaryRowIsNotGoodStanding(): void
    {
        $this->insertStandingRow(102, (int) date('Y'), 0);

        $this->assertFalse($this->service->isInGoodStanding(102, 202, 301));
    }

    public function testIsInGoodStandingFalseWhenOnlyPriorYearRowExists(): void
    {
        $this->insertStandingRow(103, (int) date('Y') - 1, 1);

        $this->assertFalse($this->service->isInGoodStanding(103, 203, 301));
    }

    public function testIsInGoodStandingTrueWithOverrideEvenWithoutSummaryRow(): void
    {
        $this->insertMembershipDetailsRow(204, 301, 1);

        $this->assertTrue($this->service->isInGoodStanding(0, 204, 301));
    }

    public function testOverrideIsScopedToTheAppointmentParish(): void
    {
        $this->insertMembershipDetailsRow(205, 999, 1);

        $this->assertFalse($this->service->isInGoodStanding(105, 205, 301));
        $this->assertTrue($this->service->isInGoodStanding(105, 205, 999));
        $this->assertTrue($this->service->isInGoodStanding(105, 205, null));
    }

    public function testClergyEligibilityRejectsUnverifiedOrderDespitePortalAccess(): void
    {
        $userId = $this->insertPortalUser('enabled');

        $result = $this->service->eligibilityFor($userId, 0, 301, ['role_category' => 'clergy']);

        $this->assertFalse($result['eligible'], json_encode($result));
        $this->assertTrue($result['checks']['portal_access']);
        $this->assertNull($result['checks']['baptized']);
        $this->assertNull($result['checks']['confirmed']);
        $this->assertNull($result['checks']['pledged']);
        $this->assertNull($result['checks']['good_standing']);
        $this->assertContains('verified_order', $result['failed']);
    }

    public function testUsersRowWithoutPortalIdentityIsRejected(): void
    {
        $userId = $this->insertUserRowOnly();

        $result = $this->service->eligibilityFor($userId, 0, 301, ['role_category' => 'clergy']);

        $this->assertFalse($result['eligible']);
        $this->assertContains('portal_access', $result['failed']);
        $this->assertContains('verified_order', $result['failed']);
    }

    public function testInvitedPortalAccessIsRejected(): void
    {
        $userId = $this->insertPortalUser('invited');

        $result = $this->service->eligibilityFor($userId, 0, 301, ['role_category' => 'clergy']);

        $this->assertFalse($result['eligible']);
        $this->assertContains('portal_access', $result['failed']);
    }

    public function testInactiveUserIsRejected(): void
    {
        $userId = $this->insertPortalUser('enabled');
        $this->db->table('users')->where('id', $userId)->update(['active' => 0]);

        $result = $this->service->eligibilityFor($userId, 0, 301, ['role_category' => 'clergy']);

        $this->assertFalse($result['eligible']);
        $this->assertContains('portal_access', $result['failed']);
    }

    public function testLayEligibilityFailsWithoutBaptismEvenWithOverride(): void
    {
        $userId   = $this->insertPortalUser('enabled');
        $personId = $this->insertPerson();
        $this->insertMembershipDetailsRow($personId, 301, 1, 0);

        $result = $this->service->eligibilityFor($userId, $personId, 301, []);

        $this->assertFalse($result['eligible']);
        $this->assertSame(['baptized'], $result['failed']);
        $this->assertTrue($result['checks']['confirmed']);
        $this->assertTrue($result['checks']['pledged']);
        $this->assertTrue($result['checks']['good_standing']);
    }

    public function testLayEligibilityPassesWithPortalAccessBaptismAndOverride(): void
    {
        $userId   = $this->insertPortalUser('enabled');
        $personId = $this->insertPerson();
        $this->insertMembershipDetailsRow($personId, 301, 1, 1);

        $result = $this->service->eligibilityFor($userId, $personId, 301, []);

        $this->assertTrue($result['eligible'], json_encode($result));
        $this->assertSame([], $result['failed']);
    }

    public function testOverrideDoesNotBypassPortalAccess(): void
    {
        $userId   = $this->insertUserRowOnly();
        $personId = $this->insertPerson();
        $this->insertMembershipDetailsRow($personId, 301, 1, 1);

        $result = $this->service->eligibilityFor($userId, $personId, 301, []);

        $this->assertFalse($result['eligible']);
        $this->assertSame(['portal_access'], $result['failed']);
    }

    public function testLayEligibilityReportsUnconfirmedUnpledgedAndNoStanding(): void
    {
        $userId   = $this->insertPortalUser('enabled');
        $personId = $this->insertPerson();
        $this->insertSacrament($personId, 301, 'baptism');

        $result = $this->service->eligibilityFor($userId, $personId, 301, []);

        $this->assertFalse($result['eligible']);
        $this->assertTrue($result['checks']['portal_access']);
        $this->assertTrue($result['checks']['baptized']);
        $this->assertFalse($result['checks']['confirmed']);
        $this->assertFalse($result['checks']['pledged']);
        $this->assertFalse($result['checks']['good_standing']);
        $this->assertNotContains('baptized', $result['failed']);
        $this->assertContains('confirmed', $result['failed']);
        $this->assertContains('pledged', $result['failed']);
        $this->assertContains('good_standing', $result['failed']);
    }

    public function testLayEligibilityPassesWithLiveStandingData(): void
    {
        $userId   = $this->insertPortalUser('enabled');
        $personId = $this->insertPerson();
        $this->db->table('profile')->where('user_id', $userId)->update(['is_confirmed' => 1]);
        $this->insertSacrament($personId, 301, 'baptism');
        $this->insertSacrament($personId, 301, 'confirmation');
        $this->insertPledge($userId, $personId, 301);
        $this->insertCommunions($userId, 301, 3);

        $result = $this->service->eligibilityFor($userId, $personId, 301, []);

        $this->assertTrue($result['eligible'], json_encode($result));
        $this->assertSame([], $result['failed']);
    }

    public function testIsInGoodStandingRecomputesStaleSnapshot(): void
    {
        $userId = $this->insertPortalUser('enabled');
        $this->db->table('profile')->where('user_id', $userId)->update(['is_confirmed' => 1]);
        $this->insertPledge($userId, 0, 301);
        $this->insertCommunions($userId, 301, 3);

        // No member_standing_summary row exists — the gate recomputes live data.
        $this->assertTrue($this->service->isInGoodStanding($userId, 0, 301));
    }

    public function testEligibilityMessageIsLocalizedWithoutRawCheckCodes(): void
    {
        $userId = $this->insertUserRowOnly();

        $result = $this->service->eligibilityFor($userId, 0, 301, []);

        $message = $this->service->eligibilityMessage($result);
        $this->assertNotSame('', $message);
        $this->assertStringNotContainsString('portal_access', $message);
    }

    /**
     * @return void
     */
    private function insertStandingRow(int $userId, int $year, int $isGoodStanding)
    {
        $this->db->table('member_standing_summary')->insert([
            'user_id'            => $userId,
            'year'               => $year,
            'is_active_member'   => 1,
            'attendance_count'   => 10,
            'communion_count'    => 5,
            'has_pledged'        => 1,
            'is_under_discipline' => 0,
            'is_good_standing'   => $isGoodStanding,
            'last_updated'       => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return void
     */
    private function insertMembershipDetailsRow(int $personId, int $parishId, int $override, int $baptized = 1)
    {
        $this->db->table('person_membership_details')->insert([
            'person_id'              => $personId,
            'parish_id'              => $parishId,
            'is_baptized'            => $baptized,
            'is_confirmed'           => 1,
            'is_communicant'         => 1,
            'is_dependent'           => 0,
            'has_pledge'             => 1,
            'pledge_status'          => 'pledged',
            'good_standing_override' => $override,
            'created_at'             => date('Y-m-d H:i:s'),
            'updated_at'             => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * An active users row with an email_password identity and a profile
     * carrying the given portal_access state — the "can actually log in"
     * shape the portal-access check requires.
     */
    private function insertPortalUser(string $portalAccess = 'enabled'): int
    {
        static $seq = 0;
        $seq++;

        $userId = $this->insertUserRowOnly();

        $this->db->table('auth_identities')->insert([
            'user_id'    => $userId,
            'type'       => 'email_password',
            'secret'     => 'leadership.gate' . $seq . '@example.test',
            'secret2'    => password_hash('Passw0rd!' . $seq, PASSWORD_DEFAULT),
            'force_reset' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->db->table('profile')->insert([
            'user_id'       => $userId,
            'first_name'    => 'Gate',
            'last_name'     => 'Leader' . $seq,
            'portal_access' => $portalAccess,
            'is_confirmed'  => 0,
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        return $userId;
    }

    /**
     * A bare users row — the shape non-portal people get: the row exists so
     * leadership_position.user_id passes is_not_unique, but there is no
     * login identity and no enabled portal access.
     */
    private function insertUserRowOnly(): int
    {
        static $seq = 0;
        $seq++;

        $this->db->table('users')->insert([
            'username'   => 'leadership.gate.user' . $seq,
            'active'     => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertPerson(): int
    {
        $this->db->table('person')->insert([
            'first_name' => 'Gate',
            'last_name'  => 'Person',
            'is_active'  => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    /**
     * @return void
     */
    private function insertSacrament(int $personId, int $parishId, string $type)
    {
        $this->db->table('sacramental_record')->insert([
            'person_id'      => $personId,
            'parish_id'      => $parishId,
            'sacrament_type' => $type,
            'sacrament_date' => '2010-01-01',
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return void
     */
    private function insertPledge(int $userId, int $personId, int $parishId)
    {
        $this->db->table('annual_pledge')->insert([
            'user_id'            => $userId > 0 ? $userId : null,
            'person_id'          => $personId > 0 ? $personId : null,
            'parish_id'          => $parishId,
            'pledge_year'        => (int) date('Y'),
            'pledged_amount'     => 1200.0,
            'fulfilled_amount'   => 0.0,
            'fulfillment_status' => 'pledged',
            'created_at'         => date('Y-m-d H:i:s'),
            'updated_at'         => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return void
     */
    private function insertCommunions(int $userId, int $parishId, int $count)
    {
        for ($i = 0; $i < $count; $i++) {
            $this->db->table('service')->insert([
                'parish_id'    => $parishId,
                'service_date' => date('Y-m-d', strtotime('-' . ($i + 1) . ' weeks')),
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);
            $serviceId = (int) $this->db->insertID();

            $this->db->table('attendance')->insert([
                'service_id'  => $serviceId,
                'parish_id'   => $parishId,
                'user_id'     => $userId,
                'attended'    => 1,
                'communicant' => 1,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
