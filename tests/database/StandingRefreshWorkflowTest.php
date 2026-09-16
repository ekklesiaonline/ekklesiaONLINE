<?php

use App\Models\MemberStandingSummaryModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class StandingRefreshWorkflowTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureStandingTablesExist();
    }

    public function testRefreshForYearMarksEligibleMemberAsGoodStanding(): void
    {
        $year = 2026;
        $parishId = $this->insertParish('Eligible Parish');
        $userId = $this->insertUser('eligible.member');

        $this->insertAffiliation($userId, $parishId, 'active');
        $this->insertCommunionAttendances($userId, $parishId, $year, 3);
        $this->insertPledge($userId, $parishId, $year - 1);
        $this->insertPledge($userId, $parishId, $year);

        $model = model(MemberStandingSummaryModel::class);
        $count = $model->refreshForYear($year);

        $this->assertGreaterThanOrEqual(1, $count);

        $summary = $this->db->table('member_standing_summary')
            ->where('user_id', $userId)
            ->where('year', $year)
            ->get()
            ->getRowArray();

        $this->assertNotNull($summary);
        $this->assertSame(1, (int) ($summary['is_good_standing'] ?? 0));
        $this->assertSame(3, (int) ($summary['attendance_count'] ?? 0));
        $this->assertSame(3, (int) ($summary['communion_count'] ?? 0));
        $this->assertSame(1, (int) ($summary['has_pledged'] ?? 0));
    }

    public function testRefreshForYearMarksMissingPledgeAsNotGoodStanding(): void
    {
        $year = 2026;
        $parishId = $this->insertParish('Pending Pledge Parish');
        $userId = $this->insertUser('pending.pledge.member');

        $this->insertAffiliation($userId, $parishId, 'active');
        $this->insertCommunionAttendances($userId, $parishId, $year, 3);
        // Do not insert current-year pledge; summary model requires has_pledged=true.

        $model = model(MemberStandingSummaryModel::class);
        $count = $model->refreshForYear($year);

        $this->assertGreaterThanOrEqual(1, $count);

        $summary = $this->db->table('member_standing_summary')
            ->where('user_id', $userId)
            ->where('year', $year)
            ->get()
            ->getRowArray();

        $this->assertNotNull($summary);
        $this->assertSame(0, (int) ($summary['is_good_standing'] ?? 0));
        $this->assertSame(0, (int) ($summary['has_pledged'] ?? 0));
    }

    private function insertParish(string $name): int
    {
        $this->db->table($this->resolveTableName('parish'))->insert([
            'name' => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertUser(string $username): int
    {
        $this->db->table($this->resolveTableName('users'))->insert([
            'username' => $username,
            'active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $userId = (int) $this->db->insertID();

        // Create a profile for the user
        $profileTable = $this->resolveTableName('profile');
        $this->db->resetDataCache();
        $profileColumns = array_map('strtolower', $this->db->getFieldNames($profileTable) ?: []);
        $profilePayload = [
            'user_id' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if (in_array('is_confirmed', $profileColumns, true)) {
            $profilePayload['is_confirmed'] = 1;
        }
        // Provide parish_id / parish_id with a safe default to satisfy any NOT NULL
        // constraint left by a prior test class in the shared SQLite in-memory db.
        if (in_array('parish_id', $profileColumns, true)) {
            $profilePayload['parish_id'] = 0;
        }
        if (in_array('parish_id', $profileColumns, true)) {
            $profilePayload['parish_id'] = 0;
        }
        $this->db->table($profileTable)->insert($profilePayload);

        return $userId;
    }

    private function insertAffiliation(int $userId, int $parishId, string $status): void
    {
        $table = $this->resolveTableName('parish_affiliation');
        $columns = array_map('strtolower', $this->db->getFieldNames($table));

        $payload = [];

        if (in_array('linked_user_id', $columns, true)) {
            $payload['linked_user_id'] = $userId;
        } else {
            $payload['user_id'] = $userId;
        }

        if (in_array('parish_id', $columns, true)) {
            $payload['parish_id'] = $parishId;
        } else {
            $payload['parish_id'] = $parishId;
        }

        if (in_array('membership_status', $columns, true)) {
            $payload['membership_status'] = $status;
        }
        if (in_array('created_at', $columns, true)) {
            $payload['created_at'] = date('Y-m-d H:i:s');
        }
        if (in_array('updated_at', $columns, true)) {
            $payload['updated_at'] = date('Y-m-d H:i:s');
        }

        $this->db->table($table)->insert($payload);

        $this->db->table($this->resolveTableName('auth_groups_users'))->insert([
            'user_id' => $userId,
            'group' => 'member',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function insertCommunionAttendances(int $userId, int $parishId, int $year, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $date = sprintf('%d-01-%02d', $year, $i);
            $this->db->table($this->resolveTableName('service'))->insert([
                'parish_id' => $parishId,
                'date' => $date,
                'service_date' => $date,
                'service' => json_encode(['Sunday Worship']),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $serviceId = (int) $this->db->insertID();

            $this->db->table($this->resolveTableName('attendance'))->insert([
                'service_id' => $serviceId,
                'user_id' => $userId,
                'attended' => 1,
                'communicant' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function insertPledge(int $userId, int $parishId, int $year): void
    {
        $this->db->table($this->resolveTableName('annual_pledge'))->insert([
            'user_id' => $userId,
            'parish_id' => $parishId,
            'pledge_year' => $year,
            'pledged_amount' => 100.00,
            'fulfilled_amount' => 0.00,
            'fulfillment_status' => 'pledged',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function ensureStandingTablesExist(): void
    {
        $prefix = $this->db->getPrefix();
        $tables = $this->db->listTables();

        if (!in_array($prefix . 'parish', $tables, true) && !in_array('parish', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'parish (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                diocese_id INTEGER NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        if (!in_array($prefix . 'users', $tables, true) && !in_array('users', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NULL,
                active INTEGER NOT NULL DEFAULT 1,
                deleted_at TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }

        $usersTable = $this->resolveTableName('users');
        $this->ensureColumnExists($usersTable, 'active', 'INTEGER NOT NULL DEFAULT 1');
        $this->ensureColumnExists($usersTable, 'deleted_at', 'TEXT NULL');

        if (!in_array($prefix . 'profile', $tables, true) && !in_array('profile', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'profile (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                first_name TEXT NULL,
                last_name TEXT NULL,
                is_confirmed INTEGER DEFAULT 0,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        $profilesTable = $this->resolveTableName('profile');
        $this->ensureColumnExists($profilesTable, 'is_confirmed', 'INTEGER DEFAULT 0');
        // A previously-run test class may have created a person-centric profile
        // table (person_id, no user_id) in this shared in-memory database.
        $this->ensureColumnExists($profilesTable, 'user_id', 'INTEGER NULL');
        $prefixedProfiles = $prefix . 'profile';
        $this->ensureColumnExists($prefixedProfiles, 'is_confirmed', 'INTEGER DEFAULT 0');
        $this->ensureColumnExists($prefixedProfiles, 'user_id', 'INTEGER NULL');

        if (!in_array($prefix . 'parish_affiliation', $tables, true) && !in_array('parish_affiliation', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'parish_affiliation (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                linked_user_id INTEGER NULL,
                parish_id INTEGER NOT NULL,
                person_id INTEGER NULL,
                affiliation_type TEXT DEFAULT "member",
                membership_status TEXT NULL,
                canonical_status TEXT NULL,
                is_member INTEGER DEFAULT 0,
                is_good_standing INTEGER DEFAULT 0,
                source TEXT DEFAULT "system",
                start_date TEXT NULL,
                end_date TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        $affiliationTable = $this->resolveTableName('parish_affiliation');
        $this->ensureColumnExists($affiliationTable, 'linked_user_id', 'INTEGER NULL');
        $this->ensureColumnExists($affiliationTable, 'parish_id', 'INTEGER NULL');
        $this->ensureColumnExists($affiliationTable, 'parish_id', 'INTEGER NULL');
        $this->ensureColumnExists($affiliationTable, 'person_id', 'INTEGER NULL');
        $this->ensureColumnExists($affiliationTable, 'is_member', 'INTEGER NOT NULL DEFAULT 0');
        $this->ensureColumnExists($affiliationTable, 'affiliation_type', 'TEXT NULL');
        $this->ensureColumnExists($affiliationTable, 'canonical_status', 'TEXT NULL');
        $this->ensureColumnExists($affiliationTable, 'source', 'TEXT NULL');
        $this->ensureColumnExists($affiliationTable, 'start_date', 'TEXT NULL');
        $this->ensureColumnExists($affiliationTable, 'end_date', 'TEXT NULL');
        $prefixedAffiliation = $prefix . 'parish_affiliation';
        $this->ensureColumnExists($prefixedAffiliation, 'linked_user_id', 'INTEGER NULL');
        $this->ensureColumnExists($prefixedAffiliation, 'parish_id', 'INTEGER NULL');
        $this->ensureColumnExists($prefixedAffiliation, 'parish_id', 'INTEGER NULL');
        $this->ensureColumnExists($prefixedAffiliation, 'person_id', 'INTEGER NULL');
        $this->ensureColumnExists($prefixedAffiliation, 'is_member', 'INTEGER NOT NULL DEFAULT 0');

        if (!in_array($prefix . 'auth_groups_users', $tables, true) && !in_array('auth_groups_users', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'auth_groups_users (
                user_id INTEGER NOT NULL,
                `group` TEXT NOT NULL,
                created_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        if (!in_array($prefix . 'service', $tables, true) && !in_array('service', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'service (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                parish_id INTEGER NOT NULL,
                date TEXT NOT NULL,
                service_date TEXT NULL,
                service_time TEXT NULL,
                service TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }

        $servicesTable = $this->resolveTableName('service');
        $this->ensureColumnExists($servicesTable, 'id', 'INTEGER');
        $this->ensureColumnExists($servicesTable, 'parish_id', 'INTEGER NULL');
        $this->ensureColumnExists($servicesTable, 'date', 'TEXT NULL');
        $this->ensureColumnExists($servicesTable, 'service_date', 'TEXT NULL');
        $this->ensureColumnExists($servicesTable, 'service_time', 'TEXT NULL');
        $prefixedServices = $prefix . 'service';
        $this->ensureColumnExists($prefixedServices, 'parish_id', 'INTEGER NULL');
        $this->ensureColumnExists($prefixedServices, 'date', 'TEXT NULL');
        $this->ensureColumnExists($prefixedServices, 'service_date', 'TEXT NULL');
        $this->ensureColumnExists($prefixedServices, 'service_time', 'TEXT NULL');

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

        $attendanceTable = $this->resolveTableName('attendance');
        $this->ensureColumnExists($attendanceTable, 'service_id', 'INTEGER NULL');
        $this->ensureColumnExists($attendanceTable, 'user_id', 'INTEGER NULL');
        $this->ensureColumnExists($attendanceTable, 'attended', 'INTEGER NOT NULL DEFAULT 0');
        $this->ensureColumnExists($attendanceTable, 'communicant', 'INTEGER NOT NULL DEFAULT 0');
        $prefixedAttendance = $prefix . 'attendance';
        $this->ensureColumnExists($prefixedAttendance, 'service_id', 'INTEGER NULL');
        $this->ensureColumnExists($prefixedAttendance, 'user_id', 'INTEGER NULL');
        $this->ensureColumnExists($prefixedAttendance, 'attended', 'INTEGER NOT NULL DEFAULT 0');
        $this->ensureColumnExists($prefixedAttendance, 'communicant', 'INTEGER NOT NULL DEFAULT 0');

        if (!in_array($prefix . 'annual_pledge', $tables, true) && !in_array('annual_pledge', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'annual_pledge (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                parish_id INTEGER NOT NULL,
                pledge_year INTEGER NOT NULL,
                pledged_amount REAL NULL,
                fulfilled_amount REAL NULL,
                fulfillment_status TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }

        $pledgesTable = $this->resolveTableName('annual_pledge');
        $this->ensureColumnExists($pledgesTable, 'user_id', 'INTEGER NULL');
        $this->ensureColumnExists($pledgesTable, 'pledge_year', 'INTEGER NULL');
        $this->ensureColumnExists($pledgesTable, 'fulfillment_status', 'TEXT NULL');
        $prefixedPledges = $prefix . 'annual_pledge';
        $this->ensureColumnExists($prefixedPledges, 'user_id', 'INTEGER NULL');
        $this->ensureColumnExists($prefixedPledges, 'pledge_year', 'INTEGER NULL');
        $this->ensureColumnExists($prefixedPledges, 'fulfillment_status', 'TEXT NULL');

        if (!in_array($prefix . 'member_standing_summary', $tables, true) && !in_array('member_standing_summary', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'member_standing_summary (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                year INTEGER NOT NULL,
                is_active_member INTEGER NOT NULL DEFAULT 0,
                attendance_count INTEGER NOT NULL DEFAULT 0,
                communion_count INTEGER NOT NULL DEFAULT 0,
                has_pledged INTEGER NOT NULL DEFAULT 0,
                is_under_discipline INTEGER NOT NULL DEFAULT 0,
                is_good_standing INTEGER NOT NULL DEFAULT 0,
                last_updated TEXT NULL
            )');
        }

        $standingTable = $this->resolveTableName('member_standing_summary');
        $this->ensureColumnExists($standingTable, 'user_id', 'INTEGER NOT NULL');
        $this->ensureColumnExists($standingTable, 'year', 'INTEGER NOT NULL');
        $this->ensureColumnExists($standingTable, 'is_active_member', 'INTEGER NOT NULL DEFAULT 0');
        $this->ensureColumnExists($standingTable, 'attendance_count', 'INTEGER NOT NULL DEFAULT 0');
        $this->ensureColumnExists($standingTable, 'communion_count', 'INTEGER NOT NULL DEFAULT 0');
        $this->ensureColumnExists($standingTable, 'has_pledged', 'INTEGER NOT NULL DEFAULT 0');
        $this->ensureColumnExists($standingTable, 'is_under_discipline', 'INTEGER NOT NULL DEFAULT 0');
        $this->ensureColumnExists($standingTable, 'is_good_standing', 'INTEGER NOT NULL DEFAULT 0');
        $this->ensureColumnExists($standingTable, 'last_updated', 'TEXT NULL');

        $prefixedStanding = $prefix . 'member_standing_summary';
        $this->ensureColumnExists($prefixedStanding, 'user_id', 'INTEGER NOT NULL');
        $this->ensureColumnExists($prefixedStanding, 'year', 'INTEGER NOT NULL');
        $this->ensureColumnExists($prefixedStanding, 'is_active_member', 'INTEGER NOT NULL DEFAULT 0');
        $this->ensureColumnExists($prefixedStanding, 'attendance_count', 'INTEGER NOT NULL DEFAULT 0');
        $this->ensureColumnExists($prefixedStanding, 'communion_count', 'INTEGER NOT NULL DEFAULT 0');
        $this->ensureColumnExists($prefixedStanding, 'has_pledged', 'INTEGER NOT NULL DEFAULT 0');
        $this->ensureColumnExists($prefixedStanding, 'is_under_discipline', 'INTEGER NOT NULL DEFAULT 0');
        $this->ensureColumnExists($prefixedStanding, 'is_good_standing', 'INTEGER NOT NULL DEFAULT 0');
        $this->ensureColumnExists($prefixedStanding, 'last_updated', 'TEXT NULL');

        if (!in_array($prefix . 'member_discipline', $tables, true) && !in_array('member_discipline', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'member_discipline (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                reason TEXT NULL,
                start_date TEXT NOT NULL,
                end_date TEXT NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                notes TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }

        $disciplineTable = $this->resolveTableName('member_discipline');
        $this->ensureColumnExists($disciplineTable, 'start_date', 'TEXT NULL');
        $this->ensureColumnExists($disciplineTable, 'end_date', 'TEXT NULL');
    }

    private function resolveTableName(string $base): string
    {
        $prefixed = $this->db->getPrefix() . $base;
        if ($this->db->tableExists($prefixed)) {
            return $prefixed;
        }

        return $base;
    }

    private function ensureColumnExists(string $table, string $column, string $definition): void
    {
        try {
            $columns = $this->db->getFieldNames($table);
            if (in_array($column, $columns, true)) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }

        try {
            $this->db->query('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
        } catch (\Throwable $e) {
            // Ignore DB engine limitations in tests.
        }
    }
}
