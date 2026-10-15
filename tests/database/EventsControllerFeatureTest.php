<?php

use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * @internal
 */
final class EventsControllerFeatureTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    protected function setUp(): void
    {
        parent::setUp();

        helper(['auth', 'form']);
        Services::reset(true);
        $this->ensureEventsFeatureTablesExist();
        $this->withRoutes([
            ['GET', 'events/create', '\\Clergy\\Controllers\\Events::create'],
            ['GET', 'events/edit/(:num)', '\\Clergy\\Controllers\\Events::edit/$1'],
            ['GET', 'events/view/(:num)', '\\Clergy\\Controllers\\Events::view/$1'],
        ]);
    }

    protected function tearDown(): void
    {
        Services::reset(true);

        parent::tearDown();
    }

    public function testCreateRouteRendersExpandedAudienceControls(): void
    {
        [, $dioceseId, $parishId] = $this->insertHierarchy();

        $editorId = $this->insertUser('event.feature.editor.create');
        $this->insertProfile($editorId, $parishId, $dioceseId, 'Editor', 'Create');
        $this->injectEventsAuth($editorId, ['event.view', 'event.create', 'event.edit']);

        $memberId = $this->insertUser('event.feature.member.create');
        $this->insertProfile($memberId, $parishId, $dioceseId, 'Martha', 'Member');
        $this->insertAffiliation($memberId, $parishId, 'member');

        $this->insertCalendar($parishId, $dioceseId, 'Feature Calendar');
        $this->insertTeam($parishId, 'Feature Team', $memberId);
        $this->insertFamily($parishId, 'Feature Family', $memberId);

        $response = $this->get('events/create');

        $response->assertOK();
        $body = $response->getBody();
        $this->assertStringContainsString('value="staff"', $body);
        $this->assertStringContainsString('value="volunteers"', $body);
        $this->assertStringContainsString('value="invited"', $body);
        $this->assertStringContainsString('value="registered_only"', $body);
        $this->assertStringContainsString('value="household"', $body);
        $response->assertSee('Feature Team');
        $response->assertSee('Feature Family');
        // The invitee picker is AJAX-backed: the parish roster must not be
        // embedded in the page payload.
        $this->assertStringContainsString('id="invited_user_ids"', $body);
        $this->assertStringContainsString('member-search', $body);
        $this->assertStringNotContainsString('Martha Member', $body);
    }

    public function testEditRouteRetainsInvitedAudienceSelection(): void
    {
        [, $dioceseId, $parishId] = $this->insertHierarchy();

        $editorId = $this->insertUser('event.feature.editor.edit');
        $this->insertProfile($editorId, $parishId, $dioceseId, 'Editor', 'Edit');
        $this->injectEventsAuth($editorId, ['event.view', 'event.create', 'event.edit']);

        $inviteeId = $this->insertUser('event.feature.invitee.edit');
        $this->insertProfile($inviteeId, $parishId, $dioceseId, 'Irene', 'Invitee');
        $this->insertAffiliation($inviteeId, $parishId, 'member');

        $calendarId = $this->insertCalendar($parishId, $dioceseId, 'Feature Edit Calendar');
        $eventId = $this->insertEvent($parishId, $calendarId, [
            'title' => 'Feature Edit Event',
            'visibility' => 'invited',
            'created_by' => $editorId,
        ]);
        $this->insertEventInvitee($eventId, $inviteeId);

        $response = $this->get('events/edit/' . $eventId);

        $response->assertOK();
        $body = $response->getBody();
        $this->assertStringContainsString('option value="invited" selected', $body);
        $this->assertStringContainsString('option value="' . $inviteeId . '" selected', $body);
        $response->assertSee('Irene Invitee');
    }

    public function testDiocesanEventCanBeListedAndOpenedFromAnotherParishInSameDiocese(): void
    {
        [, $dioceseId, $hostParishId] = $this->insertHierarchy();
        $viewerParishId = $this->insertParish($dioceseId, 'Viewer Parish');

        $viewerId = $this->insertUser('event.feature.cross.parish.viewer');
        $this->insertProfile($viewerId, $viewerParishId, $dioceseId, 'Cross', 'Parish');
        $this->injectEventsAuth($viewerId, ['event.view']);
        session()->set('selected_parish_id', $viewerParishId);

        $calendarId = $this->insertCalendar($hostParishId, $dioceseId, 'Diocesan Calendar');
        $eventId = $this->insertEvent($hostParishId, $calendarId, [
            'organizer_type' => 'diocese',
            'organizer_diocese_id' => $dioceseId,
            'organizer_parish_id' => null,
            'scope_level' => 'diocese',
            'scope_diocese_id' => $dioceseId,
            'title' => 'Same Diocese Gathering',
            'visibility' => 'public',
        ]);

        $listedEvents = model('EventModel')->getParishEventsWithCounts($viewerParishId, $dioceseId);
        $this->assertContains($eventId, array_map('intval', array_column($listedEvents, 'id')));

        $viewResponse = $this->get('events/view/' . $eventId);
        $viewResponse->assertOK();
        $viewResponse->assertSee('Same Diocese Gathering');
    }

    public function testEventDetailRejectsOtherParishAndOtherDioceseCandidates(): void
    {
        [$provinceId, $dioceseId, $viewerParishId] = $this->insertHierarchy();
        $otherParishId = $this->insertParish($dioceseId, 'Other Parish');
        $otherDioceseId = $this->insertDiocese($provinceId, 'Other Diocese');
        $otherDioceseParishId = $this->insertParish($otherDioceseId, 'Other Diocese Parish');

        $viewerId = $this->insertUser('event.feature.isolation.viewer');
        $this->insertProfile($viewerId, $viewerParishId, $dioceseId, 'Scope', 'Viewer');
        $this->injectEventsAuth($viewerId, ['event.view']);
        session()->set('selected_parish_id', $viewerParishId);

        $otherParishCalendarId = $this->insertCalendar($otherParishId, $dioceseId, 'Other Parish Calendar');
        $otherParishEventId = $this->insertEvent($otherParishId, $otherParishCalendarId, [
            'organizer_type' => 'parish',
            'organizer_parish_id' => $otherParishId,
            'title' => 'Hidden Parish Event',
            'visibility' => 'public',
        ]);

        $otherDioceseCalendarId = $this->insertCalendar($otherDioceseParishId, $otherDioceseId, 'Other Diocese Calendar');
        $otherDioceseEventId = $this->insertEvent($otherDioceseParishId, $otherDioceseCalendarId, [
            'organizer_type' => 'diocese',
            'organizer_diocese_id' => $otherDioceseId,
            'organizer_parish_id' => null,
            'scope_level' => 'diocese',
            'scope_diocese_id' => $otherDioceseId,
            'title' => 'Hidden Diocese Event',
            'visibility' => 'public',
        ]);

        $this->get('events/view/' . $otherParishEventId)->assertRedirectTo('/events');
        $this->get('events/view/' . $otherDioceseEventId)->assertRedirectTo('/events');
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function insertHierarchy(): array
    {
        $provinceId = $this->insertProvince('Feature Province');
        $dioceseId = $this->insertDiocese($provinceId, 'Feature Diocese');
        $parishId = $this->insertParish($dioceseId, 'Feature Parish');

        return [$provinceId, $dioceseId, $parishId];
    }

    private function insertProvince(string $name): int
    {
        $this->db->table($this->resolveTableName('province'))->insert([
            'name' => $name,
            'province_number' => '1',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertDiocese(int $provinceId, string $name): int
    {
        $this->db->table($this->resolveTableName('diocese'))->insert([
            'province_id' => $provinceId,
            'name' => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertParish(int $dioceseId, string $name): int
    {
        $this->db->table($this->resolveTableName('parish'))->insert([
            'diocese_id' => $dioceseId,
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)) . '@example.org',
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

        return (int) $this->db->insertID();
    }

    private function insertProfile(int $userId, int $parishId, int $dioceseId, string $firstName, string $lastName): void
    {
        $profileTable = $this->resolveTableName('profile');
        $columns = array_map('strtolower', $this->db->getFieldNames($profileTable));

        $payload = [
            'user_id' => $userId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if (in_array('locale', $columns, true)) {
            $payload['locale'] = 'en';
        }

        if (in_array('parish_id', $columns, true)) {
            $payload['parish_id'] = $parishId;
        } elseif (in_array('parish_id', $columns, true)) {
            $payload['parish_id'] = $parishId;
        }
        if (in_array('diocese_id', $columns, true)) {
            $payload['diocese_id'] = $dioceseId;
        }
        if (in_array('nickname', $columns, true)) {
            $payload['nickname'] = null;
        }

        $this->db->table($profileTable)->insert($payload);
    }

    private function insertAffiliation(int $userId, int $parishId, string $status): void
    {
        $this->db->table($this->resolveTableName('parish_affiliation'))->insert([
            'linked_user_id' => $userId,
            'parish_id' => $parishId,
            'membership_status' => $status,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function insertCalendar(int $parishId, int $dioceseId, string $name): int
    {
        $this->db->table($this->resolveTableName('event_calendar'))->insert([
            'parish_id' => $parishId,
            'diocese_id' => $dioceseId,
            'scope_level' => 'parish',
            'name' => $name,
            'color_hex' => '#225588',
            'is_active' => 1,
            'sort_order' => 1,
            'created_by' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertTeam(int $parishId, string $name, ?int $leaderId = null): int
    {
        $this->db->table($this->resolveTableName('team'))->insert([
            'parish_id' => $parishId,
            'name' => $name,
            'description' => $name . ' description',
            'leader_id' => $leaderId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertFamily(int $parishId, string $name, ?int $headId = null): int
    {
        $this->db->table($this->resolveTableName('family'))->insert([
            'parish_id' => $parishId,
            'family_name' => $name,
            'head_of_household_id' => $headId,
            'address' => '123 Main St',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    /**
     * @param array<string,mixed> $overrides
     */
    private function insertEvent(int $parishId, int $calendarId, array $overrides = []): int
    {
        $payload = array_merge([
            'parish_id' => $parishId,
            'organizer_type' => 'parish',
            'organizer_parish_id' => $parishId,
            'calendar_id' => $calendarId,
            'scope_level' => 'parish',
            'scope_diocese_id' => null,
            'title' => 'Controller Feature Event',
            'description' => 'Controller feature event',
            'event_type' => 'general',
            'visibility' => 'members',
            'audience_team_id' => null,
            'audience_family_id' => null,
            'schedule_mode' => 'one_day',
            'is_all_day' => 0,
            'location' => 'Feature Hall',
            'start_at' => '2026-05-10 16:00:00',
            'end_at' => '2026-05-10 17:00:00',
            'capacity' => null,
            'requires_registration' => 0,
            'status' => 'published',
            'publish_at' => null,
            'recurrence_pattern' => 'none',
            'recurrence_days_json' => null,
            'recurrence_until' => null,
            'created_by' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides);

        $this->db->table($this->resolveTableName('event'))->insert($payload);

        return (int) $this->db->insertID();
    }

    private function insertEventInvitee(int $eventId, int $userId): void
    {
        $this->db->table($this->resolveTableName('event_invitee'))->insert([
            'event_id' => $eventId,
            'user_id' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array<int,string> $permissions
     */
    private function injectEventsAuth(int $userId, array $permissions): void
    {
        $fakeUser = new class($userId, $permissions) extends User {
            /**
             * @param array<int,string> $permissions
             */
            public function __construct(private int $testUserId, private array $permissions)
            {
                parent::__construct([
                    'id' => $testUserId,
                    'username' => 'event.controller.feature.' . $testUserId,
                    'email' => 'event.controller.feature.' . $testUserId . '@example.org',
                ]);
            }

            public function can(string ...$permissions): bool
            {
                foreach ($permissions as $permission) {
                    if (!in_array($permission, $this->permissions, true)) {
                        return false;
                    }
                }

                return true;
            }

            public function inGroup(string ...$groups): bool
            {
                return in_array('parishadmin', $groups, true);
            }
        };

        $auth = new class(config('Auth'), $fakeUser, $userId) extends Auth {
            public function __construct($config, private User $fakeUser, private int $testUserId)
            {
                parent::__construct($config);
            }

            public function setAuthenticator(?string $alias = null): self
            {
                return $this;
            }

            public function loggedIn(): bool
            {
                return true;
            }

            public function id(): ?int
            {
                return $this->testUserId;
            }

            public function user(): ?User
            {
                return $this->fakeUser;
            }
        };

        Services::injectMock('auth', $auth);
    }

    private function ensureEventsFeatureTablesExist(): void
    {
        $this->ensureUsersTable();
        $this->ensureProvinceTable();
        $this->ensureDioceseTable();
        $this->ensureParishTable();
        $this->ensureProfilesTable();
        $this->ensureSettingsTable();
        $this->ensureAuthGroupsUsersTable();
        $this->ensureAuthIdentitiesTable();
        $this->ensureParishAffiliationTable();
        $this->ensureEventCalendarTable();
        $this->ensureEventTable();
        $this->ensureEventInviteeTable();
        $this->ensureEventRegistrationTable();
        $this->ensureEventAttachmentsTable();
        $this->ensureTeamTable();
        $this->ensureTeamMemberTable();
        $this->ensureFamilyTable();
        $this->ensureFamilyMemberTable();
    }

    private function ensureUsersTable(): void
    {
        $table = $this->createTableName('users');
        if (!$this->tableExists('users')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NULL,
                email TEXT NULL,
                active INTEGER DEFAULT 1,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }
    }

    private function ensureProvinceTable(): void
    {
        $table = $this->createTableName('province');
        if (!$this->tableExists('province')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                province_number TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }
    }

    private function ensureDioceseTable(): void
    {
        $table = $this->createTableName('diocese');
        if (!$this->tableExists('diocese')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                province_id INTEGER NULL,
                name TEXT NOT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }
    }

    private function ensureParishTable(): void
    {
        $table = $this->createTableName('parish');
        if (!$this->tableExists('parish')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                diocese_id INTEGER NULL,
                name TEXT NOT NULL,
                email TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        $parishTable = $this->resolveTableName('parish');
        $this->ensureColumnExists($parishTable, 'diocese_id', 'INTEGER NULL');
        $this->ensureColumnExists($parishTable, 'email', 'TEXT NULL');
        $this->ensureColumnExists($this->createTableName('parish'), 'diocese_id', 'INTEGER NULL');
        $this->ensureColumnExists($this->createTableName('parish'), 'email', 'TEXT NULL');
        $this->ensureColumnExists('parish', 'diocese_id', 'INTEGER NULL');
        $this->ensureColumnExists('parish', 'email', 'TEXT NULL');
    }

    private function ensureProfilesTable(): void
    {
        $table = $this->createTableName('profile');
        if (!$this->tableExists('profile')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                parish_id INTEGER NULL,
                diocese_id INTEGER NULL,
                first_name TEXT NULL,
                last_name TEXT NULL,
                nickname TEXT NULL,
                profile_picture TEXT NULL,
                phone TEXT NULL,
                locale TEXT NULL,
                portal_access TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }
    }

    private function ensureSettingsTable(): void
    {
        $table = $this->createTableName('settings');
        if (!$this->tableExists('settings')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                class TEXT NOT NULL,
                key TEXT NOT NULL,
                value TEXT NULL,
                type TEXT NULL,
                context TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }
    }

    private function ensureAuthGroupsUsersTable(): void
    {
        $table = $this->createTableName('auth_groups_users');
        if (!$this->tableExists('auth_groups_users')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                "group" TEXT NOT NULL,
                created_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }
    }

    private function ensureAuthIdentitiesTable(): void
    {
        $table = $this->createTableName('auth_identities');
        if (!$this->tableExists('auth_identities')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                type TEXT NOT NULL,
                secret TEXT NULL,
                force_reset INTEGER DEFAULT 0,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }
    }

    private function ensureParishAffiliationTable(): void
    {
        $table = $this->createTableName('parish_affiliation');
        if (!$this->tableExists('parish_affiliation')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                linked_user_id INTEGER NOT NULL,
                parish_id INTEGER NOT NULL,
                membership_status TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }
    }

    private function ensureEventCalendarTable(): void
    {
        $table = $this->createTableName('event_calendar');
        if (!$this->tableExists('event_calendar')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                parish_id INTEGER NULL,
                diocese_id INTEGER NULL,
                scope_level TEXT NOT NULL,
                name TEXT NOT NULL,
                color_hex TEXT NOT NULL,
                is_active INTEGER DEFAULT 1,
                sort_order INTEGER DEFAULT 0,
                created_by INTEGER NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }
    }

    private function ensureEventTable(): void
    {
        $table = $this->createTableName('event');
        if (!$this->tableExists('event')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                parish_id INTEGER NOT NULL,
                calendar_id INTEGER NULL,
                scope_level TEXT NULL,
                scope_diocese_id INTEGER NULL,
                title TEXT NOT NULL,
                description TEXT NULL,
                event_type TEXT NULL,
                visibility TEXT NULL,
                audience_team_id INTEGER NULL,
                audience_family_id INTEGER NULL,
                schedule_mode TEXT NULL,
                is_all_day INTEGER DEFAULT 0,
                location TEXT NULL,
                start_at TEXT NOT NULL,
                end_at TEXT NULL,
                capacity INTEGER NULL,
                requires_registration INTEGER DEFAULT 0,
                status TEXT NOT NULL,
                publish_at TEXT NULL,
                recurrence_pattern TEXT NULL,
                recurrence_days_json TEXT NULL,
                recurrence_until TEXT NULL,
                created_by INTEGER NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }
    }

    private function ensureEventInviteeTable(): void
    {
        $table = $this->createTableName('event_invitee');
        if (!$this->tableExists('event_invitee')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                created_at TEXT NULL
            )');
        }
    }

    private function ensureEventRegistrationTable(): void
    {
        $table = $this->createTableName('event_registration');
        if (!$this->tableExists('event_registration')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id INTEGER NOT NULL,
                parish_id INTEGER NOT NULL,
                user_id INTEGER NULL,
                status TEXT NOT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }
    }

    private function ensureEventAttachmentsTable(): void
    {
        $table = $this->createTableName('event_attachments');
        if (!$this->tableExists('event_attachments')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id INTEGER NOT NULL,
                original_name TEXT NULL,
                stored_name TEXT NULL,
                mime_type TEXT NULL,
                file_size INTEGER NULL,
                attachment_type TEXT NULL,
                created_at TEXT NULL
            )');
        }
    }

    private function ensureTeamTable(): void
    {
        $table = $this->createTableName('team');
        if (!$this->tableExists('team')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                parish_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                description TEXT NULL,
                leader_id INTEGER NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }
    }

    private function ensureTeamMemberTable(): void
    {
        $table = $this->createTableName('team_member');
        if (!$this->tableExists('team_member')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                team_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                role TEXT NULL
            )');
        }
    }

    private function ensureFamilyTable(): void
    {
        $table = $this->createTableName('family');
        if (!$this->tableExists('family')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
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
    }

    private function ensureFamilyMemberTable(): void
    {
        $table = $this->createTableName('family_member');
        if (!$this->tableExists('family_member')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                family_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                relationship TEXT NULL,
                deleted_at TEXT NULL
            )');
        }
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

    private function tableExists(string $table): bool
    {
        return $this->db->tableExists($table) || $this->db->tableExists($this->db->getPrefix() . $table);
    }

    private function resolveTableName(string $base): string
    {
        if ($this->db->tableExists($base)) {
            return $base;
        }

        return $this->createTableName($base);
    }

    private function createTableName(string $base): string
    {
        return $this->db->getPrefix() . $base;
    }
}
