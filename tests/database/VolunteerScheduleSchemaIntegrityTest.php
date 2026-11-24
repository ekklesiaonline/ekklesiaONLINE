<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Schema-level regression for the volunteer audit:
 *  - team_member must reject duplicate (team_id, user_id) at the index level.
 *  - volunteer_schedule must accept rows that only set service_date/
 *    service_time_slot (legacy `date` NOT NULL column removed) under strict
 *    SQL mode.
 *
 * @internal
 */
final class VolunteerScheduleSchemaIntegrityTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    // The already-migrated schema is used directly: a legacy migration's
    // down() path fails a full refresh (idx_service_parish_date FK), so the
    // suite cleans up its own fixtures instead.
    protected $migrate = false;
    protected $refresh = false;

    // Every fixture write runs inside a transaction that is rolled back in
    // tearDown(), so pre-existing rows (e.g. a shared id=1 parish/user) are
    // never deleted and no orphaned roster/schedule rows are left behind.
    protected function setUp(): void
    {
        parent::setUp();

        $db = db_connect();
        if ($db->DBDriver === 'MySQLi') {
            $db->query('START TRANSACTION');
        }
    }

    protected function tearDown(): void
    {
        $db = db_connect();
        if ($db->DBDriver === 'MySQLi') {
            $db->query('ROLLBACK');
        }

        parent::tearDown();
    }

    public function testTeamMemberUniqueIndexRejectsDuplicateRosterRow(): void
    {
        $db = db_connect();
        if ($db->DBDriver !== 'MySQLi' || ! $db->tableExists('team_member')) {
            $this->markTestSkipped('team_member requires MySQL fixture');
        }

        $this->seedTeamFixtures($db);

        $db->table('team_member')->insert(['team_id' => 1, 'user_id' => 1, 'person_id' => 1, 'role' => 'member']);

        $this->expectException(\Throwable::class);
        $db->table('team_member')->insert(['team_id' => 1, 'user_id' => 1, 'person_id' => 1, 'role' => 'member']);
    }

    public function testVolunteerScheduleInsertSucceedsWithServiceColumnsOnly(): void
    {
        $db = db_connect();
        if ($db->DBDriver !== 'MySQLi' || ! $db->tableExists('volunteer_schedule')) {
            $this->markTestSkipped('volunteer_schedule requires MySQL fixture');
        }

        $this->seedTeamFixtures($db);

        $ok = $db->table('volunteer_schedule')->insert([
            'team_id'           => 1,
            'user_id'           => 1,
            'status'            => 'scheduled',
            'service_date'      => '2026-12-24',
            'service_time_slot' => 'morning',
        ]);

        $this->assertTrue((bool) $ok, 'insert failed — legacy date column may still be NOT NULL');
        $id = (int) $db->insertID();
        $this->assertGreaterThan(0, $id);

        $fields = $db->getFieldNames('volunteer_schedule');
        $this->assertNotContains('date', $fields, 'legacy date column still present');
        $this->assertNotContains('time_slot', $fields, 'legacy time_slot column still present');

        $row = $db->table('volunteer_schedule')->where('id', $id)->get()->getRowArray();
        $this->assertSame('2026-12-24', $row['service_date']);
        $this->assertSame('morning', $row['service_time_slot']);
    }

    private function seedTeamFixtures($db): void
    {
        // Minimal parents for the FK chain: parish -> team, user -> team_member.
        if (! $db->table('province')->where('id', 1)->countAllResults(false)) {
            $db->query('INSERT INTO province (id, province_number, name, created_at) VALUES (1, \'P0001\', \'Test Province\', NOW())');
        }
        if (! $db->table('diocese')->where('id', 1)->countAllResults(false)) {
            $db->query('INSERT INTO diocese (id, province_id, name, created_at) VALUES (1, 1, \'Test Diocese\', NOW())');
        }
        if (! $db->table('parish')->where('id', 1)->countAllResults(false)) {
            $db->query('INSERT INTO parish (id, diocese_id, name, created_at) VALUES (1, 1, \'Test Parish\', NOW())');
        }
        if (! $db->table('users')->where('id', 1)->countAllResults(false)) {
            $db->query('INSERT INTO users (id, username, active, created_at) VALUES (1, \'vtest1\', 1, NOW())');
        }
        if (! $db->table('person')->where('id', 1)->countAllResults(false)) {
            $db->query('INSERT INTO person (id, first_name, last_name, created_at) VALUES (1, \'Test\', \'Person\', NOW())');
        }
        if (! $db->table('team')->where('id', 1)->countAllResults(false)) {
            $db->table('team')->insert(['id' => 1, 'parish_id' => 1, 'name' => 'Test Team']);
        }
    }
}
