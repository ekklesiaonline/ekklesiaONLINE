<?php

use App\Services\PersonConversionService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * T3.2: guest-to-member conversion pre-fill.
 *
 * Covers the bookkeeping side: recordManualConversion() marks the guest row
 * converted and skips pending follow-ups for the linked person, and the
 * wiring that carries guest_data into the member create form exists.
 *
 * @internal
 */
final class GuestConversionPrefillTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    public function testRecordManualConversionMarksGuestConverted(): void
    {
        $guestId = $this->insertGuest(301, null);

        PersonConversionService::recordManualConversion(
            ['id' => $guestId, 'person_id' => null],
            55,
            7
        );

        $row = $this->db->table('guest')->where('id', $guestId)->get()->getRowArray();

        $this->assertSame(1, (int) $row['followed_up']);
        $this->assertNotNull($row['converted_to_member_at']);
        $this->assertSame(55, (int) $row['converted_user_id']);
        $this->assertSame(7, (int) $row['converted_by_user_id']);
    }

    public function testRecordManualConversionSkipsPendingFollowups(): void
    {
        $guestId = $this->insertGuest(301, 42);
        $pendingId = $this->insertFollowup(42, 'pending');
        $doneId = $this->insertFollowup(42, 'sent');
        $otherId = $this->insertFollowup(43, 'pending');

        PersonConversionService::recordManualConversion(
            ['id' => $guestId, 'person_id' => 42],
            55,
            7
        );

        $pending = $this->db->table('followup_log')->where('id', $pendingId)->get()->getRowArray();
        $done = $this->db->table('followup_log')->where('id', $doneId)->get()->getRowArray();
        $other = $this->db->table('followup_log')->where('id', $otherId)->get()->getRowArray();

        $this->assertSame('skipped', $pending['status']);
        $this->assertSame('sent', $done['status']);
        $this->assertSame('pending', $other['status']);
    }

    /**
     * Wiring contract: the create-account endpoint sets guest_data flashdata,
     * Members::create consumes it into old input, and the form carries
     * guest_id back to store().
     */
    public function testPrefillWiringExists(): void
    {
        $guests = (string) file_get_contents(ROOTPATH . 'Clergy/Controllers/Guests.php');
        $members = (string) file_get_contents(ROOTPATH . 'Clergy/Controllers/Members.php');
        $view = (string) file_get_contents(ROOTPATH . 'Clergy/Views/Members/create.php');
        $routes = (string) file_get_contents(ROOTPATH . 'Clergy/Config/Routes.php');

        $this->assertStringContainsString('create-account', $routes);
        $this->assertStringContainsString("setFlashdata('guest_data'", $guests);
        $this->assertStringContainsString("session('guest_data')", $members);
        $this->assertStringContainsString('name="guest_id"', $view);
        $this->assertStringContainsString("getPost('guest_id')", $members);
    }

    private function insertGuest(int $parishId, ?int $personId): int
    {
        $this->db->table('guest')->insert([
            'parish_id'  => $parishId,
            'person_id'  => $personId,
            'first_name' => 'Ana',
            'last_name'  => 'Perez',
            'visit_date' => date('Y-m-d'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
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
