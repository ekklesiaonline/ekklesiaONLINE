<?php

use App\Services\PersonConversionService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * T3.2/T4.5: visitor-to-member conversion pre-fill.
 *
 * Covers the bookkeeping side: recordManualConversion() marks the canonical
 * visitor affiliation converted and skips pending follow-ups for the person,
 * and the wiring that carries visitor_data into the member create form exists.
 *
 * @internal
 */
final class VisitorConversionPrefillTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    public function testRecordManualConversionMarksVisitorConverted(): void
    {
        $personId = $this->insertVisitorPerson(301);

        PersonConversionService::recordManualConversion($personId, 301, 55, 7);

        $row = $this->db->table('parish_affiliation')
            ->where('person_id', $personId)
            ->where('parish_id', 301)
            ->get()
            ->getRowArray();

        $this->assertSame(1, (int) $row['followed_up']);
        $this->assertNotNull($row['converted_to_member_at']);
        $this->assertSame(55, (int) $row['linked_user_id']);
        $this->assertSame(7, (int) $row['converted_by_user_id']);
    }

    public function testRecordManualConversionSkipsPendingFollowups(): void
    {
        $personId = $this->insertVisitorPerson(301);
        $pendingId = $this->insertFollowup($personId, 'pending');
        $doneId = $this->insertFollowup($personId, 'sent');
        $otherId = $this->insertFollowup(43, 'pending');

        PersonConversionService::recordManualConversion($personId, 301, 55, 7);

        $pending = $this->db->table('followup_log')->where('id', $pendingId)->get()->getRowArray();
        $done = $this->db->table('followup_log')->where('id', $doneId)->get()->getRowArray();
        $other = $this->db->table('followup_log')->where('id', $otherId)->get()->getRowArray();

        $this->assertSame('skipped', $pending['status']);
        $this->assertSame('sent', $done['status']);
        $this->assertSame('pending', $other['status']);
    }

    /**
     * Wiring contract: the create-account endpoint sets visitor_data flashdata,
     * Members::create consumes it into old input, and the form carries
     * visitor_id (the canonical person_id) back to store().
     */
    public function testPrefillWiringExists(): void
    {
        $visitors = (string) file_get_contents(ROOTPATH . 'Clergy/Controllers/Visitors.php');
        $members = (string) file_get_contents(ROOTPATH . 'Clergy/Controllers/Members.php');
        $view = (string) file_get_contents(ROOTPATH . 'Clergy/Views/Members/create.php');
        $routes = (string) file_get_contents(ROOTPATH . 'Clergy/Config/Routes.php');

        $this->assertStringContainsString('create-account', $routes);
        $this->assertStringContainsString("setFlashdata('visitor_data'", $visitors);
        $this->assertStringContainsString("session('visitor_data')", $members);
        $this->assertStringContainsString('name="visitor_id"', $view);
        $this->assertStringContainsString("getPost('visitor_id')", $members);
    }

    private function insertVisitorPerson(int $parishId): int
    {
        $this->db->table('person')->insert([
            'first_name' => 'Ana',
            'last_name'  => 'Perez',
            'is_active'  => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $personId = (int) $this->db->insertID();

        $this->db->table('parish_affiliation')->insert([
            'person_id'        => $personId,
            'parish_id'        => $parishId,
            'membership_status'=> 'visitor',
            'followed_up'      => 0,
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);

        return $personId;
    }

    private function insertFollowup(int $personId, string $status): int
    {
        $this->db->table('followup_log')->insert([
            'person_id'      => $personId,
            'parish_id'      => 301,
            'action_type'    => 'call',
            'status'         => $status,
            'scheduled_date' => date('Y-m-d'),
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }
}
