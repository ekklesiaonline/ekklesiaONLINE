<?php

use App\Models\MemberParishAffiliationModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Integration tests for MemberParishAffiliationModel::setMemberTag()
 * and ::isRecordableMember().
 *
 * **Business policy locked by this suite:**
 *   - Granting is_member to a guest/attendee person is rejected — promotion
 *     to member goes through MembershipPathwayService transitions; a flag
 *     flip cannot manufacture membership.
 *   - Revoking is_member from a member/inactive person demotes status -> 'visitor'.
 *   - 'attendee' is not set by a member-tag toggle; it is reserved for
 *     pre-member lifecycle states.
 *   - is_member is a generated column (member|inactive) — contradictory
 *     flag/status combinations cannot be written.
 *
 * @internal
 */
final class MemberTagToggleIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchema();
    }

    // -------------------------------------------- grant is_member

    public function testGrantMemberTagOnGuestIsRejected(): void
    {
        $parishId = $this->insertParish();
        $userId   = $this->insertUser('guest.grant');
        $this->insertAffiliation($userId, $parishId, 'guest');

        $model = $this->makeModel();
        $ok = $model->setMemberTag($userId, $parishId, true);

        $this->assertFalse($ok, 'a flag flip cannot promote a guest — use pathway transitions');
        $row = $this->fetchAffiliation($userId, $parishId);
        $this->assertSame(0,       (int)    ($row['is_member']        ?? -1));
        $this->assertSame('guest', (string) ($row['membership_status'] ?? ''), 'guest stays guest on rejected grant');
    }

    public function testGrantMemberTagOnAttendeeIsRejected(): void
    {
        $parishId = $this->insertParish();
        $userId   = $this->insertUser('attendee.grant');
        $this->insertAffiliation($userId, $parishId, 'attendee');

        $model = $this->makeModel();
        $ok = $model->setMemberTag($userId, $parishId, true);

        $this->assertFalse($ok, 'a flag flip cannot promote an attendee — use pathway transitions');
        $row = $this->fetchAffiliation($userId, $parishId);
        $this->assertSame(0,          (int)    ($row['is_member']        ?? -1));
        $this->assertSame('attendee', (string) ($row['membership_status'] ?? ''), 'attendee stays attendee on rejected grant');
    }

    public function testGrantMemberTagOnAlreadyMemberLeavesStatusUnchanged(): void
    {
        $parishId = $this->insertParish();
        $userId   = $this->insertUser('already.member');
        $this->insertAffiliation($userId, $parishId, 'member');

        $model = $this->makeModel();
        $ok = $model->setMemberTag($userId, $parishId, true);

        $this->assertTrue($ok);
        $row = $this->fetchAffiliation($userId, $parishId);
        $this->assertSame(1,        (int)    ($row['is_member']        ?? -1));
        $this->assertSame('member', (string) ($row['membership_status'] ?? ''), 'member stays member on re-grant');
    }

    public function testGrantMemberTagOnInactiveLeavesInactiveStatus(): void
    {
        // An inactive person being re-tagged is intentional; their status
        // should remain inactive rather than being silently promoted.
        $parishId = $this->insertParish();
        $userId   = $this->insertUser('inactive.regrant');
        $this->insertAffiliation($userId, $parishId, 'inactive');

        $model = $this->makeModel();
        $ok = $model->setMemberTag($userId, $parishId, true);

        $this->assertTrue($ok);
        $row = $this->fetchAffiliation($userId, $parishId);
        $this->assertSame(1,          (int)    ($row['is_member']        ?? -1));
        $this->assertSame('inactive', (string) ($row['membership_status'] ?? ''), 'inactive stays inactive on re-grant');
    }

    // -------------------------------------------- revoke is_member (POLICY)

    /**
     * POLICY LOCK: revoking member tag on a member person must set
     * membership_status = 'guest', NOT 'attendee'.
     */
    public function testRevokeMemberTagFromMemberDemotesToGuest(): void
    {
        $parishId = $this->insertParish();
        $userId   = $this->insertUser('member.revoke');
        $this->insertAffiliation($userId, $parishId, 'member');

        $model = $this->makeModel();
        $ok = $model->setMemberTag($userId, $parishId, false);

        $this->assertTrue($ok);
        $row = $this->fetchAffiliation($userId, $parishId);
        $this->assertSame(0,         (int)    ($row['is_member']        ?? -1), 'is_member must be 0');
        $this->assertSame('visitor', (string) ($row['membership_status'] ?? ''), 'member -> visitor on revoke (POLICY)');
        $this->assertNotSame('attendee', (string) ($row['membership_status'] ?? ''), 'attendee must never result from a revoke');
    }

    /**
     * POLICY LOCK: revoking member tag on an inactive person must set
     * membership_status = 'visitor', NOT 'attendee'.
     */
    public function testRevokeMemberTagFromInactiveDemotesToVisitor(): void
    {
        $parishId = $this->insertParish();
        $userId   = $this->insertUser('inactive.revoke');
        $this->insertAffiliation($userId, $parishId, 'inactive');

        $model = $this->makeModel();
        $ok = $model->setMemberTag($userId, $parishId, false);

        $this->assertTrue($ok);
        $row = $this->fetchAffiliation($userId, $parishId);
        $this->assertSame(0,         (int)    ($row['is_member']        ?? -1));
        $this->assertSame('visitor', (string) ($row['membership_status'] ?? ''), 'inactive -> visitor on revoke (POLICY)');
        $this->assertNotSame('attendee', (string) ($row['membership_status'] ?? ''));
    }

    public function testRevokeMemberTagFromAlreadyNonMemberIsIdempotent(): void
    {
        $parishId = $this->insertParish();
        $userId   = $this->insertUser('guest.revoke');
        $this->insertAffiliation($userId, $parishId, 'guest');

        $model = $this->makeModel();
        $ok = $model->setMemberTag($userId, $parishId, false);

        $this->assertTrue($ok);
        $row = $this->fetchAffiliation($userId, $parishId);
        $this->assertSame(0,         (int)    ($row['is_member']        ?? -1));
        $this->assertSame('visitor', (string) ($row['membership_status'] ?? ''), 'guest stays visitor on re-revoke');
    }

    // -------------------------------------------- missing affiliation guard

    public function testSetMemberTagReturnsFalseWhenNoAffiliationExists(): void
    {
        $parishId = $this->insertParish();
        $userId   = $this->insertUser('orphan.user');
        // No affiliation row inserted.

        $model = $this->makeModel();
        $ok = $model->setMemberTag($userId, $parishId, true);

        $this->assertFalse($ok, 'must return false when no affiliation exists');
    }

    public function testSetMemberTagReturnsFalseForInactiveAffiliation(): void
    {
        $parishId = $this->insertParish();
        $userId   = $this->insertUser('deactivated.user');
        // Off-register row: guest status generates is_member=0, so the
        // setMemberTag() grant guard returns false.
        $this->db->table('parish_affiliation')->insert([
            'linked_user_id'    => $userId,
            'parish_id'         => $parishId,
            'person_id'         => null,
            'affiliation_type'  => 'member',
            'membership_status' => 'guest',
            'source'            => 'system',
            'start_date'        => date('Y-m-d'),
            'end_date'          => null,
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        $model = $this->makeModel();
        $ok = $model->setMemberTag($userId, $parishId, true);

        $this->assertFalse($ok, 'inactive affiliation must not be updatable via setMemberTag');
    }

    // -------------------------------------------- isRecordableMember

    public function testIsRecordableMemberReturnsTrueForActiveTaggedMember(): void
    {
        $parishId = $this->insertParish();
        $userId   = $this->insertUser('recordable.member');
        $this->insertAffiliation($userId, $parishId, 'member');

        $this->assertTrue(
            model(MemberParishAffiliationModel::class)->isRecordableMember($userId, $parishId)
        );
    }

    public function testIsRecordableMemberReturnsFalseForVisitor(): void
    {
        $parishId = $this->insertParish();
        $userId   = $this->insertUser('visitor.tagged');
        $this->insertAffiliation($userId, $parishId, 'guest');

        $this->assertFalse(
            model(MemberParishAffiliationModel::class)->isRecordableMember($userId, $parishId),
            'guest status must not qualify as recordable'
        );
    }

    public function testIsRecordableMemberReturnsFalseForInactiveMember(): void
    {
        // Inactive members are on the register (is_member=1) but are not
        // active canonical members — not recordable.
        $parishId = $this->insertParish();
        $userId   = $this->insertUser('member.inactive');
        $this->insertAffiliation($userId, $parishId, 'inactive');

        $this->assertFalse(
            model(MemberParishAffiliationModel::class)->isRecordableMember($userId, $parishId),
            'inactive status must not qualify as recordable even though is_member=1'
        );
    }

    public function testIsRecordableMemberReturnsFalseWhenNoAffiliation(): void
    {
        $parishId = $this->insertParish();
        $userId   = $this->insertUser('no.affiliation');

        $this->assertFalse(
            model(MemberParishAffiliationModel::class)->isRecordableMember($userId, $parishId)
        );
    }

    // -------------------------------------------- schema & helpers

    private function buildSchema(): void
    {
        $forge = \Config\Database::forge();

        $forge->dropTable('parish_affiliation', true);
        $forge->dropTable('users', true);
        $forge->dropTable('parish', true);

        $forge->addField([
            'id'         => ['type' => 'INTEGER', 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 255],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('parish', true);

        $forge->addField([
            'id'         => ['type' => 'INTEGER', 'auto_increment' => true],
            'username'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'active'     => ['type' => 'INTEGER', 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('users', true);

        $forge->addField([
            'id'                => ['type' => 'INTEGER', 'auto_increment' => true],
            'linked_user_id'    => ['type' => 'INTEGER', 'null' => true],
            'parish_id'         => ['type' => 'INTEGER'],
            'person_id'         => ['type' => 'INTEGER', 'null' => true],
            'affiliation_type'  => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'member'],
            'membership_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'guest'],
            'source'            => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'system'],
            'start_date'        => ['type' => 'DATE', 'null' => true],
            'end_date'          => ['type' => 'DATE', 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('parish_affiliation', true);

        \Tests\Support\Database\CanonicalTestSchema::ensureGeneratedIsMember($this->db);
    }

    private function insertParish(): int
    {
        $this->db->table('parish')->insert([
            'name'       => 'Toggle Parish',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $id = (int) $this->db->insertID();

        // Satisfies BaseTenantModel scoping in this test DB.
        session()->set('selected_parish_id', $id);

        return $id;
    }

    private function insertUser(string $username): int
    {
        $this->db->table('users')->insert([
            'username'   => $username,
            'active'     => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertAffiliation(int $userId, int $parishId, string $status): void
    {
        $this->db->table('parish_affiliation')->insert([
            'linked_user_id'    => $userId,
            'parish_id'         => $parishId,
            'person_id'         => null,
            'affiliation_type'  => 'member',
            'membership_status' => $status,
            'source'            => 'system',
            'start_date'        => date('Y-m-d'),
            'end_date'          => null,
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchAffiliation(int $userId, int $parishId): array
    {
        return (array) ($this->db->table('parish_affiliation')
            ->where('linked_user_id', $userId)
            ->where('parish_id', $parishId)
            ->get()
            ->getRowArray() ?? []);
    }

    private function makeModel(): MemberParishAffiliationModel
    {
        return new MemberParishAffiliationModel();
    }
}
