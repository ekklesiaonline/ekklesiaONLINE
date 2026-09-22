<?php

use App\Models\MemberParishAffiliationModel;
use App\Services\MemberAffiliationService;
use App\Services\MembershipPathwayService;
use App\Services\MembershipStatusService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Database\CanonicalTestSchema;

/**
 * Invariant tests for the parish_affiliation status tuple.
 *
 * Every write path must produce rows where the derived columns agree with
 * membership_status:
 *   - affiliation_type  = affiliationTypeForStatus(status)
 *   - canonical_status  = status
 *   - is_member         = isOnRegister(status)   (member|inactive stay on the register)
 *
 * These invariants are what Phase 2 will enforce structurally (generated
 * columns / dropped mirrors); this suite locks them at the write-path level.
 *
 * @internal
 */
final class MembershipInvariantTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        CanonicalTestSchema::ensure($this->db);
        foreach ([
            'parish_affiliation',
            'person_status_history',
            'user_person',
            'profile',
            'person',
            'auth_identities',
            'users',
            'parish',
        ] as $table) {
            CanonicalTestSchema::reset($this->db, $table);
        }
    }

    public function testFieldsForStatusIsInternallyConsistent(): void
    {
        foreach (MembershipStatusService::affiliationStatuses() as $status) {
            $fields = MembershipStatusService::fieldsForStatus($status);

            $this->assertSame($status, $fields['membership_status']);
            $this->assertSame(
                MembershipStatusService::affiliationTypeForStatus($status),
                $fields['affiliation_type'],
                "affiliation_type drift for {$status}"
            );
            $this->assertSame($status, $fields['canonical_status']);
            $this->assertSame(
                MembershipStatusService::isOnRegister($status) ? 1 : 0,
                $fields['is_member'],
                "is_member drift for {$status}"
            );
        }
    }

    public function testIsMemberColumnTracksRegisterMembership(): void
    {
        // On the register: canonical members and inactive (lapsed) members.
        $this->assertTrue(MembershipStatusService::isOnRegister('member'));
        $this->assertTrue(MembershipStatusService::isOnRegister('inactive'));
        // Off the register: pre-member stages and terminal exits.
        $this->assertFalse(MembershipStatusService::isOnRegister('visitor'));
        $this->assertFalse(MembershipStatusService::isOnRegister('regular_attender'));
        $this->assertFalse(MembershipStatusService::isOnRegister('transferred'));
        $this->assertFalse(MembershipStatusService::isOnRegister('deceased'));
    }

    public function testAttachToParishProducesConsistentRowForEveryStatus(): void
    {
        foreach (['visitor', 'regular_attender', 'member', 'inactive'] as $i => $status) {
            $parishId = $this->insertParish();
            $userId   = $this->insertUser('attach.' . $status . $i);

            $ok = model(MemberParishAffiliationModel::class)->attachToParish($userId, $parishId, false, $status);

            $this->assertTrue($ok, "attachToParish failed for {$status}");
            $this->assertRowConsistent($this->fetchAffiliation($userId, $parishId), $status);
        }
    }

    /**
     * Regression: the approval bug class — a non-member status must never
     * be written with is_member=1.
     */
    public function testAttachToParishCannotWriteContradictoryRow(): void
    {
        $parishId = $this->insertParish();
        $userId   = $this->insertUser('attach.regular');

        model(MemberParishAffiliationModel::class)->attachToParish($userId, $parishId, false, 'regular_attender');

        $row = $this->fetchAffiliation($userId, $parishId);
        $this->assertSame('regular_attender', $row['membership_status']);
        $this->assertSame(0, (int) $row['is_member'], 'regular_attender must not be on the register');
        $this->assertSame('attendee', $row['affiliation_type']);
    }

    public function testAttachToParishHonorsIsHomeForMember(): void
    {
        $parishId = $this->insertParish();
        $userId   = $this->insertUser('attach.home');

        model(MemberParishAffiliationModel::class)->attachToParish($userId, $parishId, true, 'member');

        $row = $this->fetchAffiliation($userId, $parishId);
        $this->assertSame(1, (int) $row['is_primary']);
    }

    public function testAttachToParishIgnoresIsHomeForNonMember(): void
    {
        $parishId = $this->insertParish();
        $userId   = $this->insertUser('attach.home.attender');

        model(MemberParishAffiliationModel::class)->attachToParish($userId, $parishId, true, 'regular_attender');

        $row = $this->fetchAffiliation($userId, $parishId);
        $this->assertSame(0, (int) $row['is_primary'], 'non-member affiliation must not become primary');
    }

    public function testTransitionToInactiveKeepsPersonOnRegister(): void
    {
        $parishId = $this->insertParish();
        $userId   = $this->insertUser('pathway.inactive');

        model(MemberParishAffiliationModel::class)->attachToParish($userId, $parishId, false, 'member');

        $result = (new MembershipPathwayService())->transitionForUser($userId, $parishId, 'inactive');
        $this->assertTrue($result['ok'], 'transition failed: ' . json_encode($result));

        $this->assertRowConsistent($this->fetchAffiliation($userId, $parishId), 'inactive');
    }

    public function testTransitionToTerminalDropsFromRegister(): void
    {
        $parishId = $this->insertParish();
        $userId   = $this->insertUser('pathway.transferred');

        model(MemberParishAffiliationModel::class)->attachToParish($userId, $parishId, false, 'member');

        $result = (new MembershipPathwayService())->transitionForUser($userId, $parishId, 'transferred', null, 'Moved to another parish');
        $this->assertTrue($result['ok']);

        $row = $this->fetchAffiliation($userId, $parishId);
        $this->assertRowConsistent($row, 'transferred');
        $this->assertNotNull($row['end_date'], 'terminal transition must stamp end_date');
    }

    /**
     * Regression for the resolved is_member semantics: inactive members stay
     * on the parish register, so they remain in register counts and in the
     * member directory (with their inactive status visible), instead of
     * silently dropping out.
     */
    public function testInactiveMemberStaysOnRegisterCountsAndDirectory(): void
    {
        $parishId = $this->insertParish();
        $userId   = $this->insertUser('register.inactive');
        $this->insertProfile($userId, $parishId);

        $model = model(MemberParishAffiliationModel::class);
        $model->attachToParish($userId, $parishId, false, 'member');

        $result = (new MembershipPathwayService())->transitionForUser($userId, $parishId, 'inactive');
        $this->assertTrue($result['ok']);

        $this->assertSame(1, $model->countDistinctMembers($parishId));

        $members = $model->getParishMembers($parishId);
        $this->assertCount(1, $members);
        $this->assertSame('inactive', (string) $members[0]['affiliation_status']);
    }

    public function testVisitorStaysOffRegisterCountsAndDirectory(): void
    {
        $parishId = $this->insertParish();
        $userId   = $this->insertUser('register.visitor');
        $this->insertProfile($userId, $parishId);

        $model = model(MemberParishAffiliationModel::class);
        $model->attachToParish($userId, $parishId, false, 'visitor');

        $this->assertSame(0, $model->countDistinctMembers($parishId));
        $this->assertCount(0, $model->getParishMembers($parishId));
    }

    /**
     * An inactive (lapsed) member must be reactivatable through the pathway —
     * transitionForUser() deliberately does not require is_member=1 so that
     * legacy rows written before the register semantics can still move.
     */
    public function testInactiveMemberReactivatesThroughPathway(): void
    {
        $parishId = $this->insertParish();
        $userId   = $this->insertUser('register.reactivate');

        $model = model(MemberParishAffiliationModel::class);
        $model->attachToParish($userId, $parishId, false, 'member');

        $pathway = new MembershipPathwayService();
        $this->assertTrue($pathway->transitionForUser($userId, $parishId, 'inactive')['ok']);
        $this->assertTrue($pathway->transitionForUser($userId, $parishId, 'member')['ok']);

        $row = $this->fetchAffiliation($userId, $parishId);
        $this->assertRowConsistent($row, 'member');
        $this->assertNull($row['end_date'], 'reactivation must clear any end_date');
    }

    public function testDetachFromParishLeavesOffRegisterRow(): void
    {
        $parishId = $this->insertParish();
        $userId   = $this->insertUser('detach.user');

        $model = model(MemberParishAffiliationModel::class);
        $model->attachToParish($userId, $parishId, false, 'member');

        $this->assertTrue($model->detachFromParish($userId, $parishId));

        $row = $this->fetchAffiliation($userId, $parishId);
        $this->assertRowConsistent($row, 'visitor');
        $this->assertNotNull($row['end_date']);
    }

    public function testDeactivateAffiliationsLeavesConsistentRows(): void
    {
        $parishId = $this->insertParish();
        $userId   = $this->insertUser('deactivate.user');

        model(MemberParishAffiliationModel::class)->attachToParish($userId, $parishId, false, 'member');

        $service  = new MemberAffiliationService($this->db);
        $parishes = $service->deactivateActiveAffiliationsForUser($userId);

        $this->assertContains($parishId, $parishes);

        $row = $this->fetchAffiliation($userId, $parishId);
        $this->assertRowConsistent($row, 'visitor');
        $this->assertNotNull($row['end_date']);
    }

    public function testSetMemberTagProducesConsistentRow(): void
    {
        $parishId = $this->insertParish();
        $userId   = $this->insertUser('tag.user');

        $model = model(MemberParishAffiliationModel::class);
        $model->attachToParish($userId, $parishId, false, 'member');
        $model->setMemberTag($userId, $parishId, false);

        $row = $this->fetchAffiliation($userId, $parishId);
        $this->assertRowConsistent($row, 'visitor');

        $model->setMemberTag($userId, $parishId, true);
        $row = $this->fetchAffiliation($userId, $parishId);
        // Re-granting after a revoke: the row was already off-register, so
        // the guard promotes status to member — the result stays consistent.
        $this->assertRowConsistent($row, $row['membership_status']);
        $this->assertSame(
            MembershipStatusService::isOnRegister($row['membership_status']) ? 1 : 0,
            (int) $row['is_member']
        );
    }

    /**
     * The core invariant: derived columns agree with membership_status.
     *
     * @param array<string, mixed> $row
     */
    private function assertRowConsistent(array $row, string $expectedStatus): void
    {
        $this->assertSame($expectedStatus, (string) ($row['membership_status'] ?? ''));
        $this->assertSame(
            MembershipStatusService::affiliationTypeForStatus($expectedStatus),
            (string) ($row['affiliation_type'] ?? ''),
            "affiliation_type inconsistent for {$expectedStatus}"
        );
        $this->assertSame(
            $expectedStatus,
            (string) ($row['canonical_status'] ?? ''),
            "canonical_status inconsistent for {$expectedStatus}"
        );
        $this->assertSame(
            MembershipStatusService::isOnRegister($expectedStatus) ? 1 : 0,
            (int) ($row['is_member'] ?? -1),
            "is_member inconsistent for {$expectedStatus}"
        );
    }

    private function insertParish(): int
    {
        $this->db->table('parish')->insert([
            'name'       => 'Invariant Parish ' . bin2hex(random_bytes(3)),
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

    private function insertProfile(int $userId, int $parishId): void
    {
        $this->db->table('profile')->insert([
            'user_id'     => $userId,
            'parish_id'   => $parishId,
            'first_name'  => 'Test',
            'last_name'   => 'Person',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
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
            ->where('deleted_at', null)
            ->get()
            ->getRowArray() ?? []);
    }
}
