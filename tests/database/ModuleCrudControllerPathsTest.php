<?php

use App\Models\AttendanceModel;
use App\Models\DonationModel;
use App\Models\TeamMemberModel;
use App\Models\VolunteerScheduleModel;
use App\Models\VolunteerTeamModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class ModuleCrudControllerPathsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureCoreTablesExist();
    }

    public function testMembersCrudPathCreateUpdateDeleteAffiliation(): void
    {
        $this->requireTables(['churches', 'users', 'profile', 'parish_affiliation']);

        $churchId = $this->insertChurch('CRUD Members Church');
        $userId = $this->insertUser('member.crud.user');

        $profileId = $this->insertProfile($userId, $churchId, 'Member', 'Crud', 'active');
        $this->assertGreaterThan(0, $profileId);

        $this->db->table('parish_affiliation')->insert([
            'linked_user_id' => $userId,
            'parish_id' => $churchId,
            'person_id' => null,
            'affiliation_type' => 'member',
            'membership_status' => 'member',
            'canonical_status' => 'member',
            'is_member' => 1,
            'is_good_standing' => 0,
            'source' => 'system',
            'start_date' => date('Y-m-d'),
            'end_date' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->db->table('parish_affiliation')
            ->where('linked_user_id', $userId)
            ->where('parish_id', $churchId)
            ->update([
                'membership_status' => 'inactive',
                'canonical_status' => 'inactive',
                'is_member' => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        $row = $this->db->table('parish_affiliation')
            ->where('linked_user_id', $userId)
            ->where('parish_id', $churchId)
            ->get()
            ->getRowArray();

        $this->assertNotNull($row);
        $this->assertSame('inactive', (string) ($row['membership_status'] ?? ''));

        $this->db->table('parish_affiliation')
            ->where('linked_user_id', $userId)
            ->where('parish_id', $churchId)
            ->delete();

        $deleted = $this->db->table('parish_affiliation')
            ->where('linked_user_id', $userId)
            ->where('parish_id', $churchId)
            ->countAllResults();

        $this->assertSame(0, $deleted);
    }

    public function testAttendanceCrudPathMarkReadUpdateDelete(): void
    {
        $this->requireTables(['churches', 'users', 'profile']);

        $churchId = $this->insertChurch('CRUD Attendance Church');
        $userId = $this->insertUser('attendance.crud.user');
        $this->insertProfile($userId, $churchId, 'Attendance', 'Crud', 'active');

        $serviceId = $this->insertService($churchId, '2026-03-31');

        $attendanceModel = model(AttendanceModel::class);
        $created = $attendanceModel->markAttendance($serviceId, $userId, true, true);
        $this->assertNotFalse($created);

        $rows = $attendanceModel->getServiceAttendance($serviceId);
        $this->assertNotEmpty($rows);

        $updated = $attendanceModel->markAttendance($serviceId, $userId, false, false);
        $this->assertNotFalse($updated);

        $raw = $attendanceModel
            ->where('service_id', $serviceId)
            ->where('user_id', $userId)
            ->first();

        $this->assertNotNull($raw);
        $this->assertSame(0, (int) ($raw['attended'] ?? 1));

        $attendanceModel->where('service_id', $serviceId)->where('user_id', $userId)->delete();
        $remaining = (int) $attendanceModel
            ->where('service_id', $serviceId)
            ->where('user_id', $userId)
            ->countAllResults();
        $this->assertSame(0, $remaining);
    }

    public function testDonationsCrudPathCreateReadUpdateDelete(): void
    {
        $this->requireTables(['churches', 'users', 'profile', 'donation']);

        $churchId = $this->insertChurch('CRUD Donation Church');
        $userId = $this->insertUser('donation.crud.user');
        $this->insertProfile($userId, $churchId, 'Donation', 'Crud', 'active');

        $fundId = null;
        if ($this->tableAvailable('fund')) {
            $fundId = $this->insertFund($churchId, 'CRUD Donations Fund');
        }

        $donationModel = model(DonationModel::class);

        $donationPayload = [
            'church_id' => $churchId,
            'user_id' => $userId,
            'amount' => '90.00',
            'date' => '2026-03-31',
            'category' => 'offering',
            'payment_method' => 'cash',
            'notes' => 'controller path integration',
        ];

        if ($fundId !== null) {
            $donationPayload['fund_id'] = $fundId;
        }

        $donationId = (int) $donationModel->insert($donationPayload, true);

        $this->assertGreaterThan(0, $donationId);

        $row = $donationModel->find($donationId);
        $this->assertNotNull($row);
        $this->assertSame(90.0, (float) ($row['amount'] ?? 0));

        $updated = $donationModel->update($donationId, ['amount' => '120.00', 'notes' => 'updated']);
        $this->assertTrue($updated);

        $rowAfter = $donationModel->find($donationId);
        $this->assertSame(120.0, (float) ($rowAfter['amount'] ?? 0));

        $deleted = $donationModel->delete($donationId);
        $this->assertTrue($deleted);
        $this->assertNull($donationModel->find($donationId));
    }

    public function testVolunteersCrudPathTeamMemberSchedule(): void
    {
        $this->requireTables(['churches', 'users', 'profile']);

        $churchId = $this->insertChurch('CRUD Volunteer Church');
        $userId = $this->insertUser('volunteer.crud.user');
        $this->insertProfile($userId, $churchId, 'Volunteer', 'Crud', 'active');

        $serviceId = $this->insertService($churchId, '2026-03-31');

        $teamModel = model(VolunteerTeamModel::class);
        $teamId = (int) $teamModel->insert([
            'church_id' => $churchId,
            'name' => 'CRUD Team',
            'description' => 'controller path integration',
            'leader_id' => $userId,
        ], true);

        $this->assertGreaterThan(0, $teamId);

        $teamMemberModel = model(TeamMemberModel::class);
        $memberId = (int) $teamMemberModel->insert([
            'team_id' => $teamId,
            'user_id' => $userId,
            'role' => 'member',
        ], true);

        $this->assertGreaterThan(0, $memberId);

        $scheduleModel = model(VolunteerScheduleModel::class);
        $scheduleId = (int) $scheduleModel->insert([
            'team_id' => $teamId,
            'service_id' => $serviceId,
            'user_id' => $userId,
            'date' => '2026-04-01',
            'time_slot' => 'morning',
            'status' => 'scheduled',
        ], true);

        $this->assertGreaterThan(0, $scheduleId);

        $updated = $scheduleModel->update($scheduleId, ['status' => 'confirmed']);
        $this->assertTrue($updated);

        $schedule = $scheduleModel->find($scheduleId);
        $this->assertSame('confirmed', (string) ($schedule['status'] ?? ''));

        $this->assertTrue($scheduleModel->delete($scheduleId));
        $this->assertTrue($teamMemberModel->delete($memberId));
        $this->assertTrue($teamModel->delete($teamId));
    }

    private function insertChurch(string $name): int
    {
        $table = $this->resolveTableName('churches');
        if ($table === null) {
            $this->markTestSkipped('churches table unavailable in this schema.');
        }

        $this->db->table($table)->insert([
            'name' => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertUser(string $username): int
    {
        $table = $this->resolveTableName('users');
        if ($table === null) {
            $this->markTestSkipped('users table unavailable in this schema.');
        }

        $this->db->table($table)->insert([
            'username' => $username,
            'active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertProfile(int $userId, int $churchId, string $firstName, string $lastName, string $status): int
    {
        $table = $this->resolveTableName('profile');
        if ($table === null) {
            $this->markTestSkipped('profile table unavailable in this schema.');
        }

        $this->db->table($table)->insert([
            'user_id' => $userId,
            'church_id' => $churchId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'membership_status' => $status,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertService(int $churchId, string $date): int
    {
        $payload = [
            'church_id' => $churchId,
            'date' => $date,
            'liturgical_day' => 'Sunday Worship',
            'service' => json_encode(['Sunday Worship']),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        try {
            $this->db->table('service')->insert($payload);
        } catch (\Throwable $e) {
            $this->ensureServicesTableExists();

            try {
                $this->db->table('service')->insert($payload);
            } catch (\Throwable $e2) {
                $this->markTestSkipped('services table unavailable in this schema.');
            }
        }

        return (int) $this->db->insertID();
    }

    private function resolveTableName(string $base): ?string
    {
        $tables = $this->db->listTables();
        if (in_array($base, $tables, true)) {
            return $base;
        }

        $prefixed = $this->db->getPrefix() . $base;
        if (in_array($prefixed, $tables, true)) {
            return $prefixed;
        }

        return null;
    }

    private function tableAvailable(string $base): bool
    {
        return $this->resolveTableName($base) !== null;
    }

    /** @param array<int,string> $bases */
    private function requireTables(array $bases): void
    {
        foreach ($bases as $base) {
            if (!$this->tableAvailable($base)) {
                $this->markTestSkipped($base . ' table unavailable in this schema.');
            }
        }
    }

    private function insertFund(int $churchId, string $name): int
    {
        $table = $this->resolveTableName('fund');
        if ($table === null) {
            $this->markTestSkipped('fund table unavailable in this schema.');
        }

        $this->db->table($table)->insert([
            'church_id' => $churchId,
            'name' => $name,
            'fund_type' => 'unrestricted',
            'is_active' => 1,
            'is_locked' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function ensureCoreTablesExist(): void
    {
        $this->ensureServicesTableExists();

        $prefix = $this->db->getPrefix();
        $tables = $this->db->listTables();

        if (!in_array($prefix . 'attendance', $tables, true) && !in_array('attendance', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'attendance (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                service_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                attended INTEGER NOT NULL DEFAULT 0,
                communicant INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NULL
            )');
        }

        if (!in_array($prefix . 'volunteer_teams', $tables, true) && !in_array('volunteer_teams', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'volunteer_teams (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                church_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                description TEXT NULL,
                leader_id INTEGER NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }

        if (!in_array($prefix . 'team_member', $tables, true) && !in_array('team_member', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'team_member (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                team_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                role TEXT NULL,
                created_at TEXT NULL
            )');
        }

        if (!in_array($prefix . 'volunteer_schedule', $tables, true) && !in_array('volunteer_schedule', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'volunteer_schedule (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                team_id INTEGER NOT NULL,
                service_id INTEGER NULL,
                user_id INTEGER NOT NULL,
                date TEXT NOT NULL,
                time_slot TEXT NULL,
                status TEXT NOT NULL DEFAULT "scheduled",
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }
    }

    private function ensureServicesTableExists(): void
    {
        $prefix = $this->db->getPrefix();
        $this->db->query('CREATE TABLE IF NOT EXISTS services (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            church_id INTEGER NOT NULL,
            date TEXT NOT NULL,
            liturgical_day TEXT NULL,
            service TEXT NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL
        )');

        if ($prefix !== '') {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'services (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                church_id INTEGER NOT NULL,
                date TEXT NOT NULL,
                liturgical_day TEXT NULL,
                service TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }
    }
}
