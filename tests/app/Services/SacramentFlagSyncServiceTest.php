<?php

use App\Models\PersonMembershipDetailsModel;
use App\Models\ProfileModel;
use App\Models\SacramentalRecordModel;
use App\Services\SacramentFlagSyncService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class SacramentFlagSyncServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    private SacramentFlagSyncService $service;
    private int $parishId;
    private int $personId;
    private int $profileId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchema();

        $this->parishId = $this->insertParish();
        $this->personId = $this->insertPerson();
        $this->profileId = $this->insertProfile($this->personId, $this->parishId);

        $this->service = new SacramentFlagSyncService();
    }

    public function testAddBaptismRecordSetsFlagAndProfileDate(): void
    {
        $this->insertSacrament('baptism', '2000-04-09');

        $applied = $this->service->applyForPerson($this->personId, $this->parishId);

        $this->assertTrue($applied['is_baptized']);
        $this->assertSame('2000-04-09', $applied['baptism_date']);

        $details = model(PersonMembershipDetailsModel::class)
            ->where('person_id', $this->personId)
            ->where('parish_id', $this->parishId)
            ->first();
        $this->assertSame(1, (int) $details['is_baptized']);

        $profile = model(ProfileModel::class)->find($this->profileId);
        $this->assertSame('2000-04-09', $profile['baptism_date']);
    }

    public function testDeleteOnlyBaptismRecordClearsFlagAndProfileDate(): void
    {
        $recordId = $this->insertSacrament('baptism', '2000-04-09');
        $this->service->applyForPerson($this->personId, $this->parishId);

        // Confirm the flag is set first.
        $details = model(PersonMembershipDetailsModel::class)
            ->where('person_id', $this->personId)
            ->where('parish_id', $this->parishId)
            ->first();
        $this->assertSame(1, (int) $details['is_baptized']);

        // Delete the only baptism record.
        model(SacramentalRecordModel::class)->delete($recordId);

        $applied = $this->service->applyForPerson($this->personId, $this->parishId);
        $this->assertFalse($applied['is_baptized']);

        $details = model(PersonMembershipDetailsModel::class)
            ->where('person_id', $this->personId)
            ->where('parish_id', $this->parishId)
            ->first();
        $this->assertSame(0, (int) $details['is_baptized']);

        $profile = model(ProfileModel::class)->find($this->profileId);
        $this->assertNull($profile['baptism_date']);
    }

    public function testConfirmationRecordSetsIsConfirmedAndDate(): void
    {
        $this->insertSacrament('confirmation', '2015-06-01');

        $applied = $this->service->applyForPerson($this->personId, $this->parishId);

        $this->assertTrue($applied['is_confirmed']);
        $this->assertSame('2015-06-01', $applied['confirmation_date']);

        $profile = model(ProfileModel::class)->find($this->profileId);
        $this->assertSame('2015-06-01', $profile['confirmation_date']);
        $this->assertSame(1, (int) $profile['is_confirmed']);
    }

    public function testEucharistRecordDoesNotSetCommunicantFlag(): void
    {
        $this->insertSacrament('eucharist', '2020-01-01');

        $applied = $this->service->applyForPerson($this->personId, $this->parishId);

        // is_baptized / is_confirmed should remain false; communicant untouched.
        $this->assertFalse($applied['is_baptized']);
        $this->assertFalse($applied['is_confirmed']);

        $details = model(PersonMembershipDetailsModel::class)
            ->where('person_id', $this->personId)
            ->where('parish_id', $this->parishId)
            ->first();
        $this->assertSame(0, (int) ($details['is_communicant'] ?? 0));
    }

    public function testDiscrepanciesDetectFlagOnRecordOff(): void
    {
        // Set the flag manually without a sacrament record.
        model(PersonMembershipDetailsModel::class)->insert([
            'person_id'   => $this->personId,
            'parish_id'   => $this->parishId,
            'is_baptized' => 1,
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        $discrepancies = $this->service->discrepanciesForPerson($this->personId, $this->parishId);

        $keys = array_column($discrepancies, 'key');
        $this->assertContains('is_baptized', $keys);
    }

    public function testDiscrepanciesDetectRecordOnFlagOff(): void
    {
        $this->insertSacrament('baptism', '2000-04-09');
        // No membership_details row yet -> defaults to is_baptized=0.

        $discrepancies = $this->service->discrepanciesForPerson($this->personId, $this->parishId);

        $keys = array_column($discrepancies, 'key');
        $this->assertContains('is_baptized', $keys);
    }

    public function testNoDiscrepanciesAfterApply(): void
    {
        $this->insertSacrament('baptism', '2000-04-09');
        $this->service->applyForPerson($this->personId, $this->parishId);

        $discrepancies = $this->service->discrepanciesForPerson($this->personId, $this->parishId);
        $this->assertSame([], $discrepancies);
    }

    public function testCaseInsensitiveSacramentType(): void
    {
        // Legacy/manual rows may use mixed case.
        $this->insertSacrament('Baptism', '2000-04-09');

        $applied = $this->service->applyForPerson($this->personId, $this->parishId);
        $this->assertTrue($applied['is_baptized']);
    }

    // ---------- helpers ----------

    private function insertSacrament(string $type, string $date): int
    {
        model(SacramentalRecordModel::class)->insert([
            'parish_id'      => $this->parishId,
            'person_id'      => $this->personId,
            'sacrament_type' => $type,
            'sacrament_date' => $date,
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);
        return (int) model(SacramentalRecordModel::class)->getInsertID();
    }

    private function insertParish(): int
    {
        $this->db->table('parish')->insert([
            'name'       => 'Sync Parish',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return (int) $this->db->insertID();
    }

    private function insertPerson(): int
    {
        $this->db->table('person')->insert([
            'first_name' => 'Jane',
            'last_name'  => 'Doe',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return (int) $this->db->insertID();
    }

    private function insertProfile(int $personId, int $parishId): int
    {
        $this->db->table('profile')->insert([
            'person_id'        => $personId,
            'parish_id'        => $parishId,
            'first_name'       => 'Jane',
            'last_name'        => 'Doe',
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);
        return (int) $this->db->insertID();
    }

    private function buildSchema(): void
    {
        // Reset the shared tables to the canonical union schema so this test
        // neither depends on nor pollutes the shared :memory: schema shape.
        foreach (['sacramental_record', 'person_membership_details', 'profile', 'person', 'parish'] as $t) {
            \Tests\Support\Database\CanonicalTestSchema::reset($this->db, $t);
        }
    }
}
