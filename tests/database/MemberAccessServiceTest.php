<?php

use App\Services\MemberAccessService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class MemberAccessServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTablesExist();
    }

    public function testCreateNonPortalUserWritesCoreTablesWithoutAuthRecords(): void
    {
        $churchId = $this->insertChurch('Non Portal Church');
        $familyId = $this->insertFamily($churchId, 'Family A');

        $service = new MemberAccessService($this->db);
        $result = $service->createNonPortalUser([
            'family_id' => $familyId,
            'first_name' => 'Test',
            'last_name' => 'Child',
            'relationship' => 'Child',
            'birth_date' => '2018-01-01',
            'portal_access' => 'none',
            'is_child' => true,
        ], $churchId);

        $this->assertTrue((bool) ($result['ok'] ?? false), $this->diagnosticContext($result));
        $this->assertSame('NON_PORTAL_MEMBER_CREATED', (string) ($result['code'] ?? ''));

        $userId = (int) ($result['data']['user_id'] ?? 0);
        $this->assertGreaterThan(0, $userId, json_encode($result));

        $this->assertNotNull($this->db->table('users')->where('id', $userId)->get()->getRowArray());
        $profile = $this->db->table('profile')->where('user_id', $userId)->get()->getRowArray();
        $this->assertNotNull($profile);
        $this->assertContains((string) ($profile['portal_access'] ?? ''), ['none', '']);
        $this->assertNull($profile['portal_enabled_at'] ?? null);

        $affiliationColumns = array_map('strtolower', $this->db->getFieldNames($this->resolveTableName('parish_affiliation')));
        $userColumn = in_array('linked_user_id', $affiliationColumns, true) ? 'linked_user_id' : 'user_id';
        $churchColumn = in_array('parish_id', $affiliationColumns, true) ? 'parish_id' : 'church_id';

        $affiliation = $this->db->table('parish_affiliation')
            ->where($userColumn, $userId)
            ->where($churchColumn, $churchId)
            ->get()
            ->getRowArray();
        $this->assertNotNull($affiliation);
        $this->assertSame('regular_attender', (string) ($affiliation['membership_status'] ?? ''));

        $relation = $this->db->table('family_member')
            ->where('family_id', $familyId)
            ->where('user_id', $userId)
            ->get()
            ->getRowArray();
        $this->assertNotNull($relation);

        $identityCount = (int) $this->db->table('auth_identities')
            ->where('user_id', $userId)
            ->where('type', 'email_password')
            ->countAllResults();
        $this->assertSame(0, $identityCount);

        $groupCount = (int) $this->db->table('auth_groups_users')
            ->where('user_id', $userId)
            ->where('group', 'member')
            ->countAllResults();
        $this->assertSame(0, $groupCount);
    }

    public function testEnablePortalAccessIsIdempotentAndNoDuplicateGroupRows(): void
    {
        $churchId = $this->insertChurch('Enable Portal Church');
        $familyId = $this->insertFamily($churchId, 'Family B');

        $service = new MemberAccessService($this->db);
        $created = $service->createNonPortalUser([
            'family_id' => $familyId,
            'first_name' => 'Portal',
            'last_name' => 'Candidate',
            'relationship' => 'Child',
            'portal_access' => 'none',
        ], $churchId);

        $this->assertTrue((bool) ($created['ok'] ?? false), $this->diagnosticContext($created));
        $userId = (int) ($created['data']['user_id'] ?? 0);
        $this->assertGreaterThan(0, $userId, json_encode($created));

        $enabled = $service->enablePortalAccess($userId, [
            'family_id' => $familyId,
            'email' => 'candidate@example.org',
            'password' => 'MyStrongPass1',
        ], $churchId);

        $this->assertTrue((bool) ($enabled['ok'] ?? false));
        $this->assertSame('PORTAL_ACCESS_ENABLED', (string) ($enabled['code'] ?? ''));

        $identity = $this->db->table('auth_identities')
            ->where('user_id', $userId)
            ->where('type', 'email_password')
            ->get()
            ->getRowArray();
        $this->assertNotNull($identity);
        $this->assertSame('candidate@example.org', (string) ($identity['secret'] ?? ''));

        $groupRows = (int) $this->db->table('auth_groups_users')
            ->where('user_id', $userId)
            ->where('group', 'member')
            ->countAllResults();
        $this->assertSame(1, $groupRows);

        $profile = $this->db->table('profile')->where('user_id', $userId)->get()->getRowArray();
        $this->assertNotNull($profile);
        $this->assertSame('invited', (string) ($profile['portal_access'] ?? ''));
        $this->assertNull($profile['portal_enabled_at'] ?? null);

        $repeat = $service->enablePortalAccess($userId, [
            'family_id' => $familyId,
            'email' => 'candidate@example.org',
            'password' => 'MyStrongPass1',
        ], $churchId);

        $this->assertTrue((bool) ($repeat['ok'] ?? false));
        $this->assertSame('PORTAL_INVITE_RESENT', (string) ($repeat['code'] ?? ''));

        $identityRows = (int) $this->db->table('auth_identities')
            ->where('user_id', $userId)
            ->where('type', 'email_password')
            ->countAllResults();
        $this->assertSame(1, $identityRows);

        $groupRowsAfter = (int) $this->db->table('auth_groups_users')
            ->where('user_id', $userId)
            ->where('group', 'member')
            ->countAllResults();
        $this->assertSame(1, $groupRowsAfter);
    }

    public function testScopeGuardReturnsOutOfScopeForCrossChurchFamilyAccess(): void
    {
        $churchA = $this->insertChurch('Scope A');
        $churchB = $this->insertChurch('Scope B');
        $familyA = $this->insertFamily($churchA, 'Scoped Family');

        $service = new MemberAccessService($this->db);
        $result = $service->createNonPortalUser([
            'family_id' => $familyA,
            'first_name' => 'Scope',
            'last_name' => 'Mismatch',
            'portal_access' => 'none',
        ], $churchB);

        $this->assertFalse((bool) ($result['ok'] ?? true));
        $this->assertSame('OUT_OF_SCOPE', (string) ($result['code'] ?? ''));
        $this->assertSame(403, (int) ($result['http_status'] ?? 0));
    }

    public function testCreateNonPortalUserSupportsStandaloneCreationWithoutFamilyLink(): void
    {
        $churchId = $this->insertChurch('Standalone Service Church');

        $service = new MemberAccessService($this->db);
        $result = $service->createNonPortalUser([
            'first_name' => 'Standalone',
            'last_name' => 'Member',
            'portal_access' => 'none',
        ], $churchId);

        $this->assertTrue((bool) ($result['ok'] ?? false), $this->diagnosticContext($result));
        $this->assertSame('NON_PORTAL_MEMBER_CREATED_STANDALONE', (string) ($result['code'] ?? ''));

        $userId = (int) ($result['data']['user_id'] ?? 0);
        $this->assertGreaterThan(0, $userId);
        $this->assertNull($result['data']['family_id'] ?? null);
        $this->assertNull($result['data']['family_member_id'] ?? null);

        $familyLinks = (int) $this->db->table('family_member')->where('user_id', $userId)->countAllResults();
        $this->assertSame(0, $familyLinks);
    }

    private function insertChurch(string $name): int
    {
        $this->db->table('parish')->insert([
            'name' => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertFamily(int $churchId, string $familyName): int
    {
        $this->db->table('family')->insert([
            'parish_id' => $churchId,
            'family_name' => $familyName,
            'head_of_household_id' => null,
            'address' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function ensureTablesExist(): void
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

        $churchesTable = $this->resolveTableName('parish');
        $this->ensureColumnExists($churchesTable, 'diocese_id', 'INTEGER NULL');
        $this->ensureColumnExists($this->db->getPrefix() . 'parish', 'diocese_id', 'INTEGER NULL');
        $this->ensureColumnExists('parish', 'diocese_id', 'INTEGER NULL');

        if (!in_array($prefix . 'users', $tables, true) && !in_array('users', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NULL,
                active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        $usersTable = $this->resolveTableName('users');
        $this->ensureColumnExists($usersTable, 'username', 'TEXT NULL');
        $this->ensureColumnExists($usersTable, 'active', 'INTEGER NOT NULL DEFAULT 1');
        $this->ensureColumnExists($usersTable, 'deleted_at', 'TEXT NULL');

        if (!in_array($prefix . 'family', $tables, true) && !in_array('family', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'family (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                parish_id INTEGER NOT NULL,
                family_name TEXT NOT NULL,
                head_of_household_id INTEGER NULL,
                address TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        $familiesTable = $this->resolveTableName('family');
        $this->ensureColumnExists($familiesTable, 'parish_id', 'INTEGER NOT NULL DEFAULT 0');
        $this->ensureColumnExists($familiesTable, 'family_name', 'TEXT NULL');
        $this->ensureColumnExists($familiesTable, 'head_of_household_id', 'INTEGER NULL');
        $this->ensureColumnExists($familiesTable, 'address', 'TEXT NULL');

        if (!in_array($prefix . 'profile', $tables, true) && !in_array('profile', $tables, true)) {
            $this->db->query("CREATE TABLE IF NOT EXISTS " . $prefix . "profile (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                parish_id INTEGER NULL,
                first_name TEXT NULL,
                nickname TEXT NULL,
                last_name TEXT NULL,
                birth_date TEXT NULL,
                membership_status TEXT NULL,
                portal_access TEXT NOT NULL DEFAULT 'none',
                portal_enabled_at TEXT NULL,
                is_dependent INTEGER DEFAULT 0,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )");
        } else {
            // Table exists from a prior test — check whether parish_id has the NOT NULL
            // constraint without a default (common DDL pollution from earlier test runs).
            // In SQLite we cannot ALTER a column, so we recreate the table if needed.
            $pragmaRows = $this->db->query('PRAGMA table_info(' . $prefix . 'profile)')->getResultArray();
            foreach ($pragmaRows as $col) {
                if (
                    (string) ($col['name'] ?? '') === 'parish_id'
                    && (int) ($col['notnull'] ?? 0) === 1
                    && (string) ($col['dflt_value'] ?? '') === ''
                ) {
                    // Recreate with nullable parish_id to prevent NOT NULL constraint
                    // from polluting subsequent tests that insert profiles without it.
                    $this->db->query('DROP TABLE IF EXISTS ' . $prefix . 'profile');
                    $this->db->query("CREATE TABLE " . $prefix . "profile (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        user_id INTEGER NOT NULL,
                        parish_id INTEGER NULL,
                        first_name TEXT NULL,
                        nickname TEXT NULL,
                        last_name TEXT NULL,
                        birth_date TEXT NULL,
                        membership_status TEXT NULL,
                        portal_access TEXT NOT NULL DEFAULT 'none',
                        portal_enabled_at TEXT NULL,
                        is_dependent INTEGER DEFAULT 0,
                        created_at TEXT NULL,
                        updated_at TEXT NULL,
                        deleted_at TEXT NULL
                    )");
                    $this->db->resetDataCache();
                    break;
                }
            }
        }

        $profilesResolved = $this->resolveTableName('profile');
        $this->ensureColumnExists($profilesResolved, 'parish_id', 'INTEGER NULL');
        $this->ensureColumnExists($profilesResolved, 'first_name', 'TEXT NULL');
        $this->ensureColumnExists($profilesResolved, 'last_name', 'TEXT NULL');
        $this->ensureColumnExists($profilesResolved, 'birth_date', 'TEXT NULL');
        $this->ensureColumnExists($profilesResolved, 'membership_status', 'TEXT NULL');
        $this->ensureColumnExists($profilesResolved, 'portal_access', 'TEXT NOT NULL DEFAULT "none"');
        $this->ensureColumnExists($profilesResolved, 'portal_enabled_at', 'TEXT NULL');
        $prefixedProfiles = $this->db->getPrefix() . 'profile';
        $this->ensureColumnExists($prefixedProfiles, 'parish_id', 'INTEGER NULL');
        $this->ensureColumnExists($prefixedProfiles, 'first_name', 'TEXT NULL');
        $this->ensureColumnExists($prefixedProfiles, 'last_name', 'TEXT NULL');
        $this->ensureColumnExists($prefixedProfiles, 'birth_date', 'TEXT NULL');
        $this->ensureColumnExists($prefixedProfiles, 'membership_status', 'TEXT NULL');
        $this->ensureColumnExists($prefixedProfiles, 'portal_access', 'TEXT NOT NULL DEFAULT "none"');
        $this->ensureColumnExists($prefixedProfiles, 'portal_enabled_at', 'TEXT NULL');
        $this->ensureColumnExists($profilesResolved, 'nickname', 'TEXT NULL');
        $this->ensureColumnExists($prefixedProfiles, 'nickname', 'TEXT NULL');
        $this->ensureColumnExists($profilesResolved, 'is_dependent', 'INTEGER DEFAULT 0');
        $this->ensureColumnExists($prefixedProfiles, 'is_dependent', 'INTEGER DEFAULT 0');
        $this->db->resetDataCache();

        foreach ([$prefix . 'profile', 'profile'] as $profilesTable) {
            try {
                $this->db->query("ALTER TABLE {$profilesTable} ADD COLUMN portal_access TEXT NOT NULL DEFAULT 'none'");
            } catch (\Throwable $e) {
                // Ignore when table missing or column already exists.
            }
            try {
                $this->db->query('ALTER TABLE ' . $profilesTable . ' ADD COLUMN portal_enabled_at TEXT NULL');
            } catch (\Throwable $e) {
                // Ignore when table missing or column already exists.
            }
            try {
                $this->db->query('ALTER TABLE ' . $profilesTable . ' ADD COLUMN person_id INTEGER NULL');
            } catch (\Throwable $e) {
                // Ignore when table missing or column already exists.
            }
            try {
                $this->db->query('ALTER TABLE ' . $profilesTable . ' ADD COLUMN email TEXT NULL');
            } catch (\Throwable $e) {
                // Ignore when table missing or column already exists.
            }
            try {
                $this->db->query('ALTER TABLE ' . $profilesTable . ' ADD COLUMN phone TEXT NULL');
            } catch (\Throwable $e) {
                // Ignore when table missing or column already exists.
            }
            try {
                $this->db->query('ALTER TABLE ' . $profilesTable . ' ADD COLUMN address TEXT NULL');
            } catch (\Throwable $e) {
                // Ignore when table missing or column already exists.
            }
        }

        $profilesResolved = $this->resolveTableName('profile');
        $this->ensureColumnExists($profilesResolved, 'person_id', 'INTEGER NULL');
        $this->ensureColumnExists($profilesResolved, 'email', 'TEXT NULL');
        $this->ensureColumnExists($profilesResolved, 'phone', 'TEXT NULL');
        $this->ensureColumnExists($profilesResolved, 'address', 'TEXT NULL');

        if (!in_array($prefix . 'person', $tables, true) && !in_array('person', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'person (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                first_name TEXT NULL,
                last_name TEXT NULL,
                email TEXT NULL,
                email_hash TEXT NULL,
                phone TEXT NULL,
                phone_hash TEXT NULL,
                birthdate TEXT NULL,
                gender TEXT NULL,
                address TEXT NULL,
                preferred_contact_method TEXT NULL,
                preferred_timezone TEXT NULL,
                opted_out_channels TEXT NULL,
                source TEXT NOT NULL DEFAULT "system",
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        $personTable = $this->resolveTableName('person');
        $this->ensureColumnExists($personTable, 'first_name', 'TEXT NULL');
        $this->ensureColumnExists($personTable, 'last_name', 'TEXT NULL');
        $this->ensureColumnExists($personTable, 'email', 'TEXT NULL');
        $this->ensureColumnExists($personTable, 'email_hash', 'TEXT NULL');
        $this->ensureColumnExists($personTable, 'phone', 'TEXT NULL');
        $this->ensureColumnExists($personTable, 'phone_hash', 'TEXT NULL');
        $this->ensureColumnExists($personTable, 'birthdate', 'TEXT NULL');
        $this->ensureColumnExists($personTable, 'address', 'TEXT NULL');
        $this->ensureColumnExists($personTable, 'source', 'TEXT NOT NULL DEFAULT "system"');
        $this->ensureColumnExists($personTable, 'is_active', 'INTEGER NOT NULL DEFAULT 1');

        if (!in_array($prefix . 'user_person', $tables, true) && !in_array('user_person', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'user_person (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                person_id INTEGER NOT NULL,
                is_primary INTEGER NOT NULL DEFAULT 1,
                linked_at TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }

        if (!in_array($prefix . 'parish_affiliation', $tables, true) && !in_array('parish_affiliation', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'parish_affiliation (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                linked_user_id INTEGER NULL,
                parish_id INTEGER NOT NULL,
                person_id INTEGER NULL,
                affiliation_type TEXT DEFAULT "member",
                membership_status TEXT DEFAULT "guest",
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

        $affiliationsTable = $this->resolveTableName('parish_affiliation');
        $this->ensureColumnExists($affiliationsTable, 'linked_user_id', 'INTEGER NULL');
        $this->ensureColumnExists($affiliationsTable, 'parish_id', 'INTEGER NOT NULL DEFAULT 0');
        $this->ensureColumnExists($affiliationsTable, 'membership_status', 'TEXT NULL');
        $this->ensureColumnExists($affiliationsTable, 'canonical_status', 'TEXT NULL');
        $this->ensureColumnExists($affiliationsTable, 'is_member', 'INTEGER NOT NULL DEFAULT 0');
        $this->ensureColumnExists($affiliationsTable, 'start_date', 'TEXT NULL');
        $this->ensureColumnExists($affiliationsTable, 'end_date', 'TEXT NULL');

        if (!in_array($prefix . 'family_member', $tables, true) && !in_array('family_member', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'family_member (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                family_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                relationship TEXT NULL,
                created_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        $familyMembersTable = $this->resolveTableName('family_member');
        $this->ensureColumnExists($familyMembersTable, 'family_id', 'INTEGER NOT NULL DEFAULT 0');
        $this->ensureColumnExists($familyMembersTable, 'user_id', 'INTEGER NOT NULL DEFAULT 0');
        $this->ensureColumnExists($familyMembersTable, 'relationship', 'TEXT NULL');

        if (!in_array($prefix . 'auth_identities', $tables, true) && !in_array('auth_identities', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'auth_identities (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                type TEXT NOT NULL,
                name TEXT NULL,
                secret TEXT NOT NULL,
                secret2 TEXT NULL,
                expires TEXT NULL,
                extra TEXT NULL,
                force_reset INTEGER NOT NULL DEFAULT 0,
                last_used_at TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        $identitiesTable = $this->resolveTableName('auth_identities');
        $this->ensureColumnExists($identitiesTable, 'secret', 'TEXT NULL');
        $this->ensureColumnExists($identitiesTable, 'secret2', 'TEXT NULL');
        $this->ensureColumnExists($identitiesTable, 'expires', 'TEXT NULL');
        $this->ensureColumnExists($identitiesTable, 'extra', 'TEXT NULL');
        $this->ensureColumnExists($identitiesTable, 'force_reset', 'INTEGER NOT NULL DEFAULT 0');
        $this->ensureColumnExists($identitiesTable, 'last_used_at', 'TEXT NULL');

        if (!in_array($prefix . 'auth_groups_users', $tables, true) && !in_array('auth_groups_users', $tables, true)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefix . 'auth_groups_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                `group` TEXT NOT NULL,
                created_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        $groupsUsersTable = $this->resolveTableName('auth_groups_users');
        $this->ensureColumnExists($groupsUsersTable, 'group', 'TEXT NOT NULL DEFAULT "member"');
    }

    private function ensureColumnExists(string $table, string $column, string $definition): void
    {
        $prefix = $this->db->getPrefix();
        $unprefixed = str_starts_with($table, $prefix) ? substr($table, strlen($prefix)) : $table;
        $targets = array_values(array_unique([$table, $unprefixed, $prefix . $unprefixed]));

        foreach ($targets as $target) {
            $hasColumn = false;
            try {
                $columns = $this->db->getFieldNames($target);
                $hasColumn = in_array($column, $columns, true);
            } catch (\Throwable $e) {
                continue;
            }

            if ($hasColumn) {
                return;
            }

            try {
                $this->db->query('ALTER TABLE ' . $target . ' ADD COLUMN ' . $column . ' ' . $definition);
                return;
            } catch (\Throwable $e) {
                // Try next table variant.
            }
        }

        try {
            $this->db->query('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
        } catch (\Throwable $e) {
            // Ignore schema drift failures in tests.
        }
    }

    private function resolveTableName(string $table): string
    {
        $candidates = array_values(array_unique([$this->db->getPrefix() . $table, $table]));
        foreach ($candidates as $candidate) {
            try {
                $this->db->getFieldNames($candidate);
                return $candidate;
            } catch (\Throwable $e) {
                // Try next variant.
            }
        }

        return $this->db->getPrefix() . $table;
    }

    /**
     * Temporary debug helper for mixed-order full-suite failures.
     *
     * @param array<string,mixed> $result
     */
    private function diagnosticContext(array $result): string
    {
        $error = $this->db->error();

        $tables = [
            'parish',
            'family',
            'users',
            'profile',
            'parish_affiliation',
            'family_member',
            'auth_identities',
            'auth_groups_users',
        ];

        $schema = [];
        foreach ($tables as $table) {
            $resolved = $this->resolveTableName($table);
            try {
                $schema[$resolved] = $this->db->getFieldNames($resolved);
            } catch (\Throwable $e) {
                $schema[$resolved] = ['<unavailable: ' . $e->getMessage() . '>'];
            }
        }

        return json_encode([
            'result' => $result,
            'db_error' => $error,
            'schema' => $schema,
        ]);
    }
}
