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
        $this->insertStandingRow(101, (int) date('Y'), 1);

        $this->assertTrue($this->service->isInGoodStanding(101, 201, 301));
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
    private function insertMembershipDetailsRow(int $personId, int $parishId, int $override)
    {
        $this->db->table('person_membership_details')->insert([
            'person_id'              => $personId,
            'parish_id'              => $parishId,
            'is_baptized'            => 1,
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
}
