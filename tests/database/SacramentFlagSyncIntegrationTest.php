<?php

use App\Models\PersonMembershipDetailsModel;
use App\Models\PersonStatusHistoryModel;
use App\Models\ProfileModel;
use App\Models\SacramentalRecordModel;
use App\Services\SacramentFlagSyncService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Integration test for the sacrament -> membership flag + profile date sync
 * flow that {@see \Clergy\Controllers\People::addSacrament()} triggers via
 * {@see SacramentFlagSyncService::applyForPerson()}.
 *
 * Verifies the end-to-end behavior including person_status_history recording.
 *
 * @internal
 */
final class SacramentFlagSyncIntegrationTest extends CIUnitTestCase
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

        $this->parishId  = $this->insertParish();
        $this->personId  = $this->insertPerson();
        $this->profileId = $this->insertProfile($this->personId, $this->parishId);

        $this->service = new SacramentFlagSyncService();
    }

    /**
     * Mirrors the controller flow: insert a sacrament record, then call
     * applyForPerson. Asserts flags + profile dates + status history.
     */
    public function testAddBaptismFlowSyncsFlagsDatesAndHistory(): void
    {
        // Insert the sacrament record (as the controller does).
        model(SacramentalRecordModel::class)->insert([
            'parish_id'      => $this->parishId,
            'person_id'      => $this->personId,
            'sacrament_type' => 'baptism',
            'sacrament_date' => '2001-03-04',
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        $this->service->applyForPerson($this->personId, $this->parishId, 99, 'Sacrament record added');

        // Flags synced.
        $details = model(PersonMembershipDetailsModel::class)
            ->where('person_id', $this->personId)
            ->where('parish_id', $this->parishId)
            ->first();
        $this->assertSame(1, (int) $details['is_baptized']);

        // Profile date synced.
        $profile = model(ProfileModel::class)->find($this->profileId);
        $this->assertSame('2001-03-04', $profile['baptism_date']);

        // Status history recorded the flag transition.
        $history = model(PersonStatusHistoryModel::class)
            ->where('person_id', $this->personId)
            ->where('status_type', 'membership_detail')
            ->findAll();
        $this->assertNotEmpty($history);
        $last = $history[0];
        $this->assertStringContainsString('is_baptized:1', (string) $last['to_status']);
        $this->assertSame('Sacrament record added', $last['reason']);
    }

    public function testDeleteLastBaptismFlowClearsFlagDateAndRecordsHistory(): void
    {
        $recordId = $this->insertSacrament('baptism', '2001-03-04');
        $this->service->applyForPerson($this->personId, $this->parishId, 99, 'added');

        // Controller deletes the record, then calls applyForPerson.
        model(SacramentalRecordModel::class)->delete($recordId);
        $this->service->applyForPerson($this->personId, $this->parishId, 99, 'Sacrament record deleted');

        $details = model(PersonMembershipDetailsModel::class)
            ->where('person_id', $this->personId)
            ->where('parish_id', $this->parishId)
            ->first();
        $this->assertSame(0, (int) $details['is_baptized']);

        $profile = model(ProfileModel::class)->find($this->profileId);
        $this->assertNull($profile['baptism_date']);

        // A second history row records the 1 -> 0 transition.
        $history = model(PersonStatusHistoryModel::class)
            ->where('person_id', $this->personId)
            ->where('status_type', 'membership_detail')
            ->findAll();
        $this->assertGreaterThanOrEqual(2, count($history));
        // History may share the same second timestamp, so search all entries
        // rather than relying on $history[0].
        $toStatuses = array_map(static fn ($h) => (string) $h['to_status'], $history);
        $this->assertTrue(
            in_array('is_baptized:0', $toStatuses, true),
            'Expected an is_baptized:0 history entry after deleting the baptism record'
        );
    }

    public function testUpdateSacramentTypeFromOtherToBaptismSetsFlag(): void
    {
        // Start with an "other" record -> no baptism flag, no row created yet
        // (applyForPerson skips the write when nothing changes from defaults).
        $recordId = $this->insertSacrament('other', '2020-01-01');
        $this->service->applyForPerson($this->personId, $this->parishId);

        $details = model(PersonMembershipDetailsModel::class)
            ->where('person_id', $this->personId)
            ->where('parish_id', $this->parishId)
            ->first();
        $this->assertNull($details, 'No membership_details row should exist when no flag changed from defaults');

        // Controller updates the type to "baptism", then calls applyForPerson.
        model(SacramentalRecordModel::class)->update($recordId, [
            'sacrament_type' => 'baptism',
            'sacrament_date' => '2020-01-01',
        ]);
        $this->service->applyForPerson($this->personId, $this->parishId, 99, 'Sacrament record updated');

        $details = model(PersonMembershipDetailsModel::class)
            ->where('person_id', $this->personId)
            ->where('parish_id', $this->parishId)
            ->first();
        $this->assertNotNull($details, 'membership_details row should be created after baptism flag set');
        $this->assertSame(1, (int) $details['is_baptized']);
    }

    public function testPreservesCommunicantAndDependentFlagsOnSync(): void
    {
        // Pre-set communicant + dependent flags.
        model(PersonMembershipDetailsModel::class)->insert([
            'person_id'      => $this->personId,
            'parish_id'      => $this->parishId,
            'is_communicant' => 1,
            'is_dependent'   => 1,
            'has_pledge'     => 1,
            'good_standing_override' => 1,
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        $this->insertSacrament('baptism', '2001-03-04');
        $this->service->applyForPerson($this->personId, $this->parishId);

        $details = model(PersonMembershipDetailsModel::class)
            ->where('person_id', $this->personId)
            ->where('parish_id', $this->parishId)
            ->first();
        $this->assertSame(1, (int) $details['is_baptized'], 'baptism flag set');
        $this->assertSame(1, (int) $details['is_communicant'], 'communicant preserved');
        $this->assertSame(1, (int) $details['is_dependent'], 'dependent preserved');
        $this->assertSame(1, (int) $details['has_pledge'], 'has_pledge preserved');
        $this->assertSame(1, (int) $details['good_standing_override'], 'good_standing_override preserved');
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
            'name'       => 'Reconcile Parish',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return (int) $this->db->insertID();
    }

    private function insertPerson(): int
    {
        $this->db->table('person')->insert([
            'first_name' => 'John',
            'last_name'  => 'Smith',
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
            'first_name'       => 'John',
            'last_name'        => 'Smith',
            'membership_status' => 'member',
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);
        return (int) $this->db->insertID();
    }

    private function buildSchema(): void
    {
        $forge = \Config\Database::forge();

        foreach (['person_status_history', 'sacramental_record', 'person_membership_details', 'profile', 'person', 'parish'] as $t) {
            $forge->dropTable($t, true);
        }

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
            'first_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'last_name'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('person', true);

        $forge->addField([
            'id'                => ['type' => 'INTEGER', 'auto_increment' => true],
            'person_id'         => ['type' => 'INTEGER', 'null' => true],
            'parish_id'         => ['type' => 'INTEGER', 'null' => true],
            'first_name'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'last_name'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'birth_date'        => ['type' => 'DATE', 'null' => true],
            'baptism_date'      => ['type' => 'DATE', 'null' => true],
            'confirmation_date' => ['type' => 'DATE', 'null' => true],
            'is_confirmed'      => ['type' => 'INTEGER', 'default' => 0],
            'is_dependent'      => ['type' => 'INTEGER', 'default' => 0],
            'membership_status' => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'visitor'],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('profile', true);

        $forge->addField([
            'id'                  => ['type' => 'INTEGER', 'auto_increment' => true],
            'parish_id'           => ['type' => 'INTEGER'],
            'person_id'           => ['type' => 'INTEGER'],
            'sacrament_type'      => ['type' => 'VARCHAR', 'constraint' => 50],
            'sacrament_date'      => ['type' => 'DATE', 'null' => true],
            'register_ref'        => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'certificate_number'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'officiant_person_id' => ['type' => 'INTEGER', 'null' => true],
            'location_notes'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'notes'               => ['type' => 'TEXT', 'null' => true],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('sacramental_record', true);

        $forge->addField([
            'id'                     => ['type' => 'INTEGER', 'auto_increment' => true],
            'person_id'              => ['type' => 'INTEGER'],
            'parish_id'              => ['type' => 'INTEGER'],
            'is_baptized'            => ['type' => 'INTEGER', 'default' => 0],
            'is_confirmed'           => ['type' => 'INTEGER', 'default' => 0],
            'is_communicant'         => ['type' => 'INTEGER', 'default' => 0],
            'is_dependent'           => ['type' => 'INTEGER', 'default' => 0],
            'has_pledge'             => ['type' => 'INTEGER', 'default' => 0],
            'pledge_status'          => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'good_standing_override' => ['type' => 'INTEGER', 'default' => 0],
            'created_at'             => ['type' => 'DATETIME', 'null' => true],
            'updated_at'             => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('person_membership_details', true);

        $forge->addField([
            'id'          => ['type' => 'INTEGER', 'auto_increment' => true],
            'person_id'   => ['type' => 'INTEGER'],
            'parish_id'   => ['type' => 'INTEGER', 'null' => true],
            'status_type' => ['type' => 'VARCHAR', 'constraint' => 32],
            'from_status' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'to_status'   => ['type' => 'VARCHAR', 'constraint' => 100],
            'changed_by'  => ['type' => 'INTEGER', 'null' => true],
            'reason'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('person_status_history', true);
    }
}
