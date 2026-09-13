<?php

use App\Models\MemberChurchAffiliationModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Integration tests for MemberChurchAffiliationModel::setMemberTag()
 * and ::isRecordableMember().
 *
 * **Business policy locked by this suite:**
 *   - Granting is_member to a guest/attendee person promotes status -> 'member'.
 *   - Revoking is_member from a member/inactive person demotes status -> 'guest'.
 *   - 'attendee' is not set by a member-tag toggle; it is reserved for
 *     pre-member lifecycle states.
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

    public function testGrantMemberTagOnGuestPromotesToMember(): void
    {
        $churchId = $this->insertChurch();
        $userId   = $this->insertUser('guest.grant');
        $this->insertAffiliation($userId, $churchId, 'guest', 1);

        $model = $this->makeModel();
        $ok = $model->setMemberTag($userId, $churchId, true);

        $this->assertTrue($ok);
        $row = $this->fetchAffiliation($userId, $churchId);
        $this->assertSame(1,        (int)    ($row['is_member']        ?? -1), 'is_member must be 1');
        $this->assertSame('member', (string) ($row['membership_status'] ?? ''), 'guest -> member on grant');
    }

    public function testGrantMemberTagOnAttendeePromotesToMember(): void
    {
        $churchId = $this->insertChurch();
        $userId   = $this->insertUser('attendee.grant');
        $this->insertAffiliation($userId, $churchId, 'attendee', 1);

        $model = $this->makeModel();
        $ok = $model->setMemberTag($userId, $churchId, true);

        $this->assertTrue($ok);
        $row = $this->fetchAffiliation($userId, $churchId);
        $this->assertSame(1,        (int)    ($row['is_member']        ?? -1));
        $this->assertSame('member', (string) ($row['membership_status'] ?? ''), 'attendee -> member on grant');
    }

    public function testGrantMemberTagOnAlreadyMemberLeavesStatusUnchanged(): void
    {
        $churchId = $this->insertChurch();
        $userId   = $this->insertUser('already.member');
        $this->insertAffiliation($userId, $churchId, 'member', 1);

        $model = $this->makeModel();
        $ok = $model->setMemberTag($userId, $churchId, true);

        $this->assertTrue($ok);
        $row = $this->fetchAffiliation($userId, $churchId);
        $this->assertSame(1,        (int)    ($row['is_member']        ?? -1));
        $this->assertSame('member', (string) ($row['membership_status'] ?? ''), 'member stays member on re-grant');
    }

    public function testGrantMemberTagOnInactiveLeavesInactiveStatus(): void
    {
        // An inactive person being re-tagged is intentional; their status
        // should remain inactive rather than being silently promoted.
        $churchId = $this->insertChurch();
        $userId   = $this->insertUser('inactive.regrant');
        $this->insertAffiliation($userId, $churchId, 'inactive', 1);

        $model = $this->makeModel();
        $ok = $model->setMemberTag($userId, $churchId, true);

        $this->assertTrue($ok);
        $row = $this->fetchAffiliation($userId, $churchId);
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
        $churchId = $this->insertChurch();
        $userId   = $this->insertUser('member.revoke');
        $this->insertAffiliation($userId, $churchId, 'member', 1);

        $model = $this->makeModel();
        $ok = $model->setMemberTag($userId, $churchId, false);

        $this->assertTrue($ok);
        $row = $this->fetchAffiliation($userId, $churchId);
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
        $churchId = $this->insertChurch();
        $userId   = $this->insertUser('inactive.revoke');
        $this->insertAffiliation($userId, $churchId, 'inactive', 1);

        $model = $this->makeModel();
        $ok = $model->setMemberTag($userId, $churchId, false);

        $this->assertTrue($ok);
        $row = $this->fetchAffiliation($userId, $churchId);
        $this->assertSame(0,         (int)    ($row['is_member']        ?? -1));
        $this->assertSame('visitor', (string) ($row['membership_status'] ?? ''), 'inactive -> visitor on revoke (POLICY)');
        $this->assertNotSame('attendee', (string) ($row['membership_status'] ?? ''));
    }

    public function testRevokeMemberTagFromAlreadyNonMemberIsIdempotent(): void
    {
        $churchId = $this->insertChurch();
        $userId   = $this->insertUser('guest.revoke');
        $this->insertAffiliation($userId, $churchId, 'guest', 1);

        $model = $this->makeModel();
        $ok = $model->setMemberTag($userId, $churchId, false);

        $this->assertTrue($ok);
        $row = $this->fetchAffiliation($userId, $churchId);
        $this->assertSame(0,         (int)    ($row['is_member']        ?? -1));
        $this->assertSame('visitor', (string) ($row['membership_status'] ?? ''), 'guest stays visitor on re-revoke');
    }

    // -------------------------------------------- missing affiliation guard

    public function testSetMemberTagReturnsFalseWhenNoAffiliationExists(): void
    {
        $churchId = $this->insertChurch();
        $userId   = $this->insertUser('orphan.user');
        // No affiliation row inserted.

        $model = $this->makeModel();
        $ok = $model->setMemberTag($userId, $churchId, true);

        $this->assertFalse($ok, 'must return false when no affiliation exists');
    }

    public function testSetMemberTagReturnsFalseForInactiveAffiliation(): void
    {
        $churchId = $this->insertChurch();
        $userId   = $this->insertUser('deactivated.user');
        // Insert with is_member=0 so setMemberTag() guard returns false.
        $this->db->table('parish_affiliation')->insert([
            'linked_user_id'    => $userId,
            'parish_id'         => $churchId,
            'person_id'         => null,
            'affiliation_type'  => 'member',
            'membership_status' => 'guest',
            'canonical_status'  => 'guest',
            'is_member'         => 0,
            'is_good_standing'  => 0,
            'source'            => 'system',
            'start_date'        => date('Y-m-d'),
            'end_date'          => null,
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        $model = $this->makeModel();
        $ok = $model->setMemberTag($userId, $churchId, true);

        $this->assertFalse($ok, 'inactive affiliation must not be updatable via setMemberTag');
    }

    // -------------------------------------------- isRecordableMember

    public function testIsRecordableMemberReturnsTrueForActiveTaggedMember(): void
    {
        $churchId = $this->insertChurch();
        $userId   = $this->insertUser('recordable.member');
        $this->insertAffiliation($userId, $churchId, 'member', 1);

        $this->assertTrue(
            model(MemberChurchAffiliationModel::class)->isRecordableMember($userId, $churchId)
        );
    }

    public function testIsRecordableMemberReturnsFalseForVisitorTaggedAsMember(): void
    {
        // is_member=1 but status=guest should not be recordable per policy
        $churchId = $this->insertChurch();
        $userId   = $this->insertUser('visitor.tagged');
        $this->insertAffiliation($userId, $churchId, 'guest', 1);

        $this->assertFalse(
            model(MemberChurchAffiliationModel::class)->isRecordableMember($userId, $churchId),
            'guest status must not qualify as recordable even if is_member=1'
        );
    }

    public function testIsRecordableMemberReturnsFalseForActiveNonTaggedPerson(): void
    {
        $churchId = $this->insertChurch();
        $userId   = $this->insertUser('member.not.tagged');
        $this->insertAffiliation($userId, $churchId, 'member', 0);

        $this->assertFalse(
            model(MemberChurchAffiliationModel::class)->isRecordableMember($userId, $churchId),
            'member status without is_member tag must not qualify as recordable'
        );
    }

    public function testIsRecordableMemberReturnsFalseWhenNoAffiliation(): void
    {
        $churchId = $this->insertChurch();
        $userId   = $this->insertUser('no.affiliation');

        $this->assertFalse(
            model(MemberChurchAffiliationModel::class)->isRecordableMember($userId, $churchId)
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
            'canonical_status'  => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'is_member'         => ['type' => 'INTEGER', 'default' => 0],
            'is_good_standing'  => ['type' => 'INTEGER', 'default' => 0],
            'source'            => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'system'],
            'start_date'        => ['type' => 'DATE', 'null' => true],
            'end_date'          => ['type' => 'DATE', 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('parish_affiliation', true);
    }

    private function insertChurch(): int
    {
        $this->db->table('parish')->insert([
            'name'       => 'Toggle Church',
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

    private function insertAffiliation(int $userId, int $churchId, string $status, int $isMember): void
    {
        $this->db->table('parish_affiliation')->insert([
            'linked_user_id'    => $userId,
            'parish_id'         => $churchId,
            'person_id'         => null,
            'affiliation_type'  => 'member',
            'membership_status' => $status,
            'canonical_status'  => $status,
            'is_member'         => $isMember,
            'is_good_standing'  => 0,
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
    private function fetchAffiliation(int $userId, int $churchId): array
    {
        return (array) ($this->db->table('parish_affiliation')
            ->where('linked_user_id', $userId)
            ->where('parish_id', $churchId)
            ->get()
            ->getRowArray() ?? []);
    }

    private function makeModel(): MemberChurchAffiliationModel
    {
        // Reset cached column detection so each test sees the fresh schema.
        $model = new MemberChurchAffiliationModel();
        $reflectionClass = new \ReflectionClass($model);
        if (!$reflectionClass->hasProperty('hasIsMemberColumn')) {
            $reflectionClass = new \ReflectionClass(\App\Models\MemberParishAffiliationModel::class);
        }
        $reflection = $reflectionClass->getProperty('hasIsMemberColumn');
        $reflection->setAccessible(true);
        $reflection->setValue($model, null);

        return $model;
    }
}
