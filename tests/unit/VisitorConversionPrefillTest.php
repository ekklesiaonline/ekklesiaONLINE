<?php

use App\Services\PersonConversionService;
use App\Services\VisitorDirectoryService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Database\CanonicalTestSchema;

/**
 * Phase 3: consolidated visitor→member promotion.
 *
 * PersonConversionService::convertToMember() is the single conversion path:
 * it transitions the affiliation through the pathway, marks conversion
 * bookkeeping, and skips pending follow-ups. The retired surface —
 * the mutating GET convertToMember route, the visitor_data flashdata
 * handoff into Members::create, and recordManualConversion() — is gone.
 * VisitorDirectoryService::delete refuses converted/register rows.
 *
 * @internal
 */
final class VisitorConversionPrefillTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        CanonicalTestSchema::ensure($this->db);
        foreach ([
            'parish_affiliation',
            'person_status_history',
            'person',
            'person_visit',
            'followup_log',
            'user_person',
            'profile',
            'users',
            'auth_identities',
            'auth_groups_users',
            'parish',
        ] as $table) {
            CanonicalTestSchema::reset($this->db, $table);
        }

        session()->set('selected_parish_id', 301);
        $this->seedParish(301);
    }

    private function seedParish(int $parishId): void
    {
        $this->db->table('parish')->insert([
            'id'         => $parishId,
            'name'       => 'Test Parish ' . $parishId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function testConvertToMemberMarksVisitorConverted(): void
    {
        $personId = $this->insertVisitorPerson(301);

        $ok = PersonConversionService::convertToMember($personId, 301, null, null, null, 7);

        $this->assertTrue($ok);

        $row = $this->db->table('parish_affiliation')
            ->where('person_id', $personId)
            ->where('parish_id', 301)
            ->get()
            ->getRowArray();

        $this->assertSame('member', $row['membership_status']);
        $this->assertSame(1, (int) $row['followed_up']);
        $this->assertNotNull($row['converted_to_member_at']);
        $this->assertSame(7, (int) $row['converted_by_user_id']);
    }

    public function testConvertToMemberSkipsPendingFollowups(): void
    {
        $personId  = $this->insertVisitorPerson(301);
        $pendingId = $this->insertFollowup($personId, 'pending');
        $doneId    = $this->insertFollowup($personId, 'sent');
        $otherId   = $this->insertFollowup($this->insertVisitorPerson(301), 'pending');

        $this->assertTrue(PersonConversionService::convertToMember($personId, 301, null, null, null, 7));

        $pending = $this->db->table('followup_log')->where('id', $pendingId)->get()->getRowArray();
        $done    = $this->db->table('followup_log')->where('id', $doneId)->get()->getRowArray();
        $other   = $this->db->table('followup_log')->where('id', $otherId)->get()->getRowArray();

        $this->assertSame('skipped', $pending['status']);
        $this->assertSame('sent', $done['status']);
        $this->assertSame('pending', $other['status']);
    }

    public function testConvertToMemberRecordsPathwayHistory(): void
    {
        $personId = $this->insertVisitorPerson(301);

        $this->assertTrue(PersonConversionService::convertToMember($personId, 301, null, null, null, 7));

        $history = $this->db->table('person_status_history')
            ->where('person_id', $personId)
            ->where('status_type', 'affiliation')
            ->get()
            ->getResultArray();

        $this->assertCount(1, $history);
        $this->assertSame('visitor', $history[0]['from_status']);
        $this->assertSame('member', $history[0]['to_status']);
    }

    public function testDeleteRefusesConvertedRow(): void
    {
        $personId = $this->insertVisitorPerson(301);
        $this->assertTrue(PersonConversionService::convertToMember($personId, 301, null, null, null, 7));

        $directory = new VisitorDirectoryService();
        $this->assertFalse($directory->delete($personId, 301));

        $row = $this->db->table('parish_affiliation')
            ->where('person_id', $personId)
            ->get()
            ->getRowArray();
        $this->assertNull($row['deleted_at']);
    }

    public function testDeleteRefusesRegisterMemberRow(): void
    {
        $personId = $this->insertVisitorPerson(301, 'member');

        $directory = new VisitorDirectoryService();
        $this->assertFalse($directory->delete($personId, 301));
    }

    public function testDeleteStillWorksForPlainVisitor(): void
    {
        $personId = $this->insertVisitorPerson(301);

        $directory = new VisitorDirectoryService();
        $this->assertTrue($directory->delete($personId, 301));

        $row = $this->db->table('parish_affiliation')
            ->where('person_id', $personId)
            ->get()
            ->getRowArray();
        $this->assertNotNull($row['deleted_at']);
    }

    public function testConvertToMemberLinksExistingUser(): void
    {
        $personId = $this->insertVisitorPerson(301);
        $userId   = $this->insertLinkedUser(301);

        $this->assertTrue(PersonConversionService::convertToMember($personId, 301, $userId, null, null, 7));

        $row = $this->db->table('parish_affiliation')
            ->where('person_id', $personId)
            ->where('parish_id', 301)
            ->get()
            ->getRowArray();

        $this->assertSame('member', $row['membership_status']);
        $this->assertSame($userId, (int) $row['linked_user_id']);

        $this->assertSame(1, $this->db->table('user_person')
            ->where('user_id', $userId)
            ->where('person_id', $personId)
            ->countAllResults());
    }

    public function testConvertToMemberCreatesPortalAccount(): void
    {
        $personId = $this->insertVisitorPerson(301);

        $this->assertTrue(PersonConversionService::convertToMember(
            $personId, 301, null, 'new.member@example.com', 'Str0ng!Pass', 7
        ));

        $affiliation = $this->db->table('parish_affiliation')
            ->where('person_id', $personId)
            ->where('parish_id', 301)
            ->get()
            ->getRowArray();

        $this->assertSame('member', $affiliation['membership_status']);
        $this->assertGreaterThan(0, (int) $affiliation['linked_user_id']);

        $userId = (int) $affiliation['linked_user_id'];

        $this->assertSame(1, $this->db->table('auth_identities')
            ->where('user_id', $userId)
            ->where('type', 'email_password')
            ->where('secret', 'new.member@example.com')
            ->countAllResults());

        $this->assertSame(1, $this->db->table('auth_groups_users')
            ->where('user_id', $userId)
            ->where('group', 'member')
            ->countAllResults());

        $this->assertSame(1, $this->db->table('profile')
            ->where('user_id', $userId)
            ->where('person_id', $personId)
            ->countAllResults());
    }

    /**
     * Wiring contract: the consolidated POST promote/revert endpoints exist,
     * and the retired GET mutation + flashdata handoff are gone.
     */
    public function testPromoteEndpointContract(): void
    {
        $visitors = (string) file_get_contents(ROOTPATH . 'Clergy/Controllers/Visitors.php');
        $members  = (string) file_get_contents(ROOTPATH . 'Clergy/Controllers/Members.php');
        $view     = (string) file_get_contents(ROOTPATH . 'Clergy/Views/Members/create.php');
        $routes   = (string) file_get_contents(ROOTPATH . 'Clergy/Config/Routes.php');

        $this->assertStringContainsString('promote/(:num)', $routes);
        $this->assertStringContainsString('revert-conversion/(:num)', $routes);
        $this->assertStringNotContainsString('get(\'convertToMember', $routes);
        $this->assertStringNotContainsString('get(\'create-account', $routes);

        $this->assertStringContainsString('function promote(', $visitors);
        $this->assertStringContainsString('function revertConversion(', $visitors);
        $this->assertStringNotContainsString('function convertToMember(', $visitors);
        $this->assertStringNotContainsString('function createAccountFromVisitor(', $visitors);

        $this->assertStringNotContainsString("session('visitor_data')", $members);
        $this->assertStringNotContainsString("getPost('visitor_id')", $members);
        $this->assertStringNotContainsString('name="visitor_id"', $view);
    }

    private function insertVisitorPerson(int $parishId, string $status = 'visitor'): int
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
            'membership_status'=> $status,
            'followed_up'      => 0,
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);

        return $personId;
    }

    private function insertLinkedUser(int $parishId): int
    {
        $this->db->table('users')->insert([
            'username'   => 'linked_user_' . bin2hex(random_bytes(3)),
            'active'     => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $userId = (int) $this->db->insertID();

        // A parish affiliation makes the account linkable in-scope.
        $this->db->table('person')->insert([
            'first_name' => 'Linked',
            'last_name'  => 'User',
            'is_active'  => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $linkedPersonId = (int) $this->db->insertID();

        $this->db->table('parish_affiliation')->insert([
            'person_id'         => $linkedPersonId,
            'parish_id'         => $parishId,
            'linked_user_id'    => $userId,
            'membership_status' => 'visitor',
            'followed_up'       => 0,
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        return $userId;
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
