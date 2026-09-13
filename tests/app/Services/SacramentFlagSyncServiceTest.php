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
            'membership_status' => 'member',
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);
        return (int) $this->db->insertID();
    }

    private function buildSchema(): void
    {
        $forge = \Config\Database::forge();

        foreach (['sacramental_record', 'person_membership_details', 'profile', 'person', 'parish'] as $t) {
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
            'id'                => ['type' => 'INTEGER', 'auto_increment' => true],
            'parish_id'         => ['type' => 'INTEGER'],
            'person_id'         => ['type' => 'INTEGER'],
            'sacrament_type'    => ['type' => 'VARCHAR', 'constraint' => 50],
            'sacrament_date'    => ['type' => 'DATE', 'null' => true],
            'register_ref'      => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'certificate_number'=> ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'officiant_person_id' => ['type' => 'INTEGER', 'null' => true],
            'location_notes'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'notes'             => ['type' => 'TEXT', 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('sacramental_record', true);

        $forge->addField([
            'id'                      => ['type' => 'INTEGER', 'auto_increment' => true],
            'person_id'               => ['type' => 'INTEGER'],
            'parish_id'               => ['type' => 'INTEGER'],
            'is_baptized'             => ['type' => 'INTEGER', 'default' => 0],
            'is_confirmed'            => ['type' => 'INTEGER', 'default' => 0],
            'is_communicant'          => ['type' => 'INTEGER', 'default' => 0],
            'is_dependent'            => ['type' => 'INTEGER', 'default' => 0],
            'has_pledge'              => ['type' => 'INTEGER', 'default' => 0],
            'pledge_status'           => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'good_standing_override'  => ['type' => 'INTEGER', 'default' => 0],
            'created_at'              => ['type' => 'DATETIME', 'null' => true],
            'updated_at'              => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('person_membership_details', true);
    }
}
