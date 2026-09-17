<?php

use App\Services\EventVisibilityService;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * @internal
 */
final class EventsAudienceIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    protected function setUp(): void
    {
        parent::setUp();

        helper(['auth', 'form']);
        Services::reset(true);
        unset($_SESSION['_ci_old_input']);
        $this->ensureEventAudienceTablesExist();
    }

    protected function tearDown(): void
    {
        Services::reset(true);

        parent::tearDown();
    }

    public function testEventFormRendersExpandedAudienceOptionsAndRetainsSelectedAudience(): void
    {
        [$provinceId, $dioceseId, $parishId] = $this->insertHierarchy();

        $adminId = $this->insertUser('event.audience.admin');
        $this->insertProfile($adminId, $parishId, $dioceseId, 'Admin', 'User');

        $calendarId = $this->insertCalendar($parishId, $dioceseId, 'Parish Calendar');
        $inviteeId = $this->insertUser('event.audience.invitee');
        $this->insertProfile($inviteeId, $parishId, $dioceseId, 'Maria', 'Lopez');
        $this->insertAffiliation($inviteeId, $parishId, 1);

        $teamId = $this->insertTeam($parishId, 'Choir Team', $inviteeId);
        $familyId = $this->insertFamily($parishId, 'Lopez Household', $inviteeId);

        $eventId = $this->insertEvent($parishId, $calendarId, [
            'title' => 'Invited Prayer Dinner',
            'visibility' => 'invited',
            'created_by' => $adminId,
        ]);
        $this->insertEventInvitee($eventId, $inviteeId);

        $calendarOptions = model('EventCalendarModel')->getAssignableCalendars($parishId, $dioceseId, false);
        $audienceMembers = model('MemberParishAffiliationModel')->getParishMembers($parishId);
        $audienceTeams = model('TeamModel')->getParishTeams($parishId);
        $audienceFamilies = model('FamilyModel')->getParishFamilies($parishId);
        $event = model('EventModel')->find($eventId);

        $createBody = view('Clergy\Views\Events/_form', [
            'calendar_options' => $calendarOptions,
            'audience_members' => $audienceMembers,
            'audience_teams' => $audienceTeams,
            'audience_families' => $audienceFamilies,
            'selected_invitee_ids' => [],
            'can_manage_calendars' => false,
            'can_assign_diocese_scope' => false,
            'attachments' => [],
        ]);

        $this->assertStringContainsString('value="staff"', $createBody);
        $this->assertStringContainsString('value="volunteers"', $createBody);
        $this->assertStringContainsString('value="invited"', $createBody);
        $this->assertStringContainsString('value="registered_only"', $createBody);
        $this->assertStringContainsString('value="household"', $createBody);
        $this->assertStringContainsString('Choir Team', $createBody);
        $this->assertStringContainsString('Lopez Household', $createBody);
        $this->assertStringContainsString('Maria Lopez', $createBody);

        $editBody = view('Clergy\Views\Events/_form', [
            'event' => is_array($event) ? $event : [],
            'calendar_options' => $calendarOptions,
            'audience_members' => $audienceMembers,
            'audience_teams' => $audienceTeams,
            'audience_families' => $audienceFamilies,
            'selected_invitee_ids' => [$inviteeId],
            'can_manage_calendars' => false,
            'can_assign_diocese_scope' => false,
            'attachments' => [],
        ]);

        $this->assertStringContainsString('option value="invited" selected', $editBody);
        $this->assertStringContainsString('option value="' . $inviteeId . '" selected', $editBody);
        $this->assertStringContainsString('Maria Lopez', $editBody);

        $this->assertGreaterThan(0, $provinceId);
        $this->assertGreaterThan(0, $teamId);
        $this->assertGreaterThan(0, $familyId);
    }

    public function testVisibilityServiceRespectsExpandedAudienceRules(): void
    {
        [, $dioceseId, $parishId] = $this->insertHierarchy();
        $calendarId = $this->insertCalendar($parishId, $dioceseId, 'Audience Rules Calendar');

        $memberId = $this->insertUser('event.audience.member');
        $this->insertProfile($memberId, $parishId, $dioceseId, 'Member', 'Visible');
        $this->insertAffiliation($memberId, $parishId, 1);

        $volunteerId = $this->insertUser('event.audience.volunteer');
        $this->insertProfile($volunteerId, $parishId, $dioceseId, 'Victor', 'Volunteer');
        $this->insertAffiliation($volunteerId, $parishId, 1);

        $invitedId = $this->insertUser('event.audience.invited');
        $this->insertProfile($invitedId, $parishId, $dioceseId, 'Ivy', 'Invitee');
        $this->insertAffiliation($invitedId, $parishId, 1);

        $registeredId = $this->insertUser('event.audience.registered');
        $this->insertProfile($registeredId, $parishId, $dioceseId, 'Rita', 'Registered');

        $staffId = $this->insertUser('event.audience.staff');
        $this->insertProfile($staffId, $parishId, $dioceseId, 'Sarah', 'Staff');

        $householdHeadId = $this->insertUser('event.audience.household.head');
        $this->insertProfile($householdHeadId, $parishId, $dioceseId, 'Hector', 'Household');
        $this->insertAffiliation($householdHeadId, $parishId, 1);

        $householdMemberId = $this->insertUser('event.audience.household.member');
        $this->insertProfile($householdMemberId, $parishId, $dioceseId, 'Helen', 'Household');
        $this->insertAffiliation($householdMemberId, $parishId, 1);

        $outsiderId = $this->insertUser('event.audience.outsider');
        $this->insertProfile($outsiderId, $parishId, $dioceseId, 'Oscar', 'Outsider');

        $teamId = $this->insertTeam($parishId, 'Welcome Team', $volunteerId);
        $this->insertTeamMember($teamId, $volunteerId);

        $familyId = $this->insertFamily($parishId, 'Household Family', $householdHeadId);
        $this->insertFamilyMember($familyId, $householdMemberId, 'Child');

        $staffEventId = $this->insertEvent($parishId, $calendarId, ['title' => 'Staff Briefing', 'visibility' => 'staff']);
        $volunteerEventId = $this->insertEvent($parishId, $calendarId, ['title' => 'Volunteer Huddle', 'visibility' => 'volunteers', 'audience_team_id' => $teamId]);
        $invitedEventId = $this->insertEvent($parishId, $calendarId, ['title' => 'Invited Supper', 'visibility' => 'invited']);
        $registeredEventId = $this->insertEvent($parishId, $calendarId, ['title' => 'Registered Retreat', 'visibility' => 'registered_only']);
        $householdEventId = $this->insertEvent($parishId, $calendarId, ['title' => 'Household Gathering', 'visibility' => 'household', 'audience_family_id' => $familyId]);
        $publicEventId = $this->insertEvent($parishId, $calendarId, ['title' => 'Public Festival', 'visibility' => 'public']);

        $this->insertEventInvitee($invitedEventId, $invitedId);
        $this->insertEventRegistration($registeredEventId, $parishId, $registeredId, 'registered');
        $this->insertLeadershipPosition($staffId, $parishId, $dioceseId, 'Staff Warden');

        $service = new EventVisibilityService();

        $staffEvent = $this->eventRow($staffEventId, $parishId, $dioceseId, ['visibility' => 'staff']);
        $volunteerEvent = $this->eventRow($volunteerEventId, $parishId, $dioceseId, ['visibility' => 'volunteers', 'audience_team_id' => $teamId]);
        $invitedEvent = $this->eventRow($invitedEventId, $parishId, $dioceseId, ['visibility' => 'invited']);
        $registeredEvent = $this->eventRow($registeredEventId, $parishId, $dioceseId, ['visibility' => 'registered_only']);
        $householdEvent = $this->eventRow($householdEventId, $parishId, $dioceseId, ['visibility' => 'household', 'audience_family_id' => $familyId]);
        $publicEvent = $this->eventRow($publicEventId, $parishId, $dioceseId, ['visibility' => 'public']);

        $this->assertTrue($service->isVisibleEvent($staffEvent, $this->visibilityContext($staffId, $parishId, $dioceseId)));
        $this->assertFalse($service->isVisibleEvent($staffEvent, $this->visibilityContext($memberId, $parishId, $dioceseId)));

        $this->assertTrue($service->isVisibleEvent($volunteerEvent, $this->visibilityContext($volunteerId, $parishId, $dioceseId)));
        $this->assertFalse($service->isVisibleEvent($volunteerEvent, $this->visibilityContext($memberId, $parishId, $dioceseId)));

        $this->assertTrue($service->isVisibleEvent($invitedEvent, $this->visibilityContext($invitedId, $parishId, $dioceseId)));
        $this->assertFalse($service->isVisibleEvent($invitedEvent, $this->visibilityContext($memberId, $parishId, $dioceseId)));

        $this->assertTrue($service->isVisibleEvent($registeredEvent, $this->visibilityContext($registeredId, $parishId, $dioceseId)));
        $this->assertFalse($service->isVisibleEvent($registeredEvent, $this->visibilityContext($memberId, $parishId, $dioceseId)));

        $this->assertTrue($service->isVisibleEvent($householdEvent, $this->visibilityContext($householdMemberId, $parishId, $dioceseId)));
        $this->assertTrue($service->isVisibleEvent($householdEvent, $this->visibilityContext($householdHeadId, $parishId, $dioceseId)));
        $this->assertFalse($service->isVisibleEvent($householdEvent, $this->visibilityContext($memberId, $parishId, $dioceseId)));

        $this->assertTrue($service->isVisibleEvent($publicEvent, $this->visibilityContext(0, $parishId, $dioceseId)));
        $this->assertTrue($service->isVisibleEvent($publicEvent, $this->visibilityContext($outsiderId, $parishId, $dioceseId)));
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function insertHierarchy(): array
    {
        $provinceId = $this->insertProvince('Audience Province');
        $dioceseId = $this->insertDiocese($provinceId, 'Audience Diocese');
        $parishId = $this->insertParish($dioceseId, 'Audience Parish');

        // BaseTenantModel::currentParishId() scopes find()/findAll() to this
        // session value; without it, EventModel::find() always resolves the
        // tenant scope to -1 and returns null.
        session()->set('selected_parish_id', $parishId);

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
            'membership_status' => 'active',
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

    private function insertAffiliation(int $userId, int $parishId, int $isMember): void
    {
        $this->db->table($this->resolveTableName('parish_affiliation'))->insert([
            'linked_user_id' => $userId,
            'parish_id' => $parishId,
            'membership_status' => $isMember === 1 ? 'member' : 'attendee',
            'is_member' => $isMember,
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
            'color_hex' => '#336699',
            'is_active' => 1,
            'sort_order' => 1,
            'created_by' => null,
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
            'calendar_id' => $calendarId,
            'scope_level' => 'parish',
            'scope_diocese_id' => null,
            'title' => 'Audience Event',
            'description' => 'Audience test event',
            'event_type' => 'general',
            'visibility' => 'members',
            'audience_team_id' => null,
            'audience_family_id' => null,
            'schedule_mode' => 'one_day',
            'is_all_day' => 0,
            'location' => 'Parish Hall',
            'start_at' => '2026-05-10 18:00:00',
            'end_at' => '2026-05-10 19:00:00',
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

    private function insertEventRegistration(int $eventId, int $parishId, int $userId, string $status): void
    {
        $this->db->table($this->resolveTableName('event_registration'))->insert([
            'event_id' => $eventId,
            'parish_id' => $parishId,
            'user_id' => $userId,
            'status' => $status,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
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

    private function insertTeamMember(int $teamId, int $userId): void
    {
        $this->db->table($this->resolveTableName('team_member'))->insert([
            'team_id' => $teamId,
            'user_id' => $userId,
            'role' => 'member',
        ]);
    }

    private function insertFamily(int $parishId, string $familyName, ?int $headOfHouseholdId = null): int
    {
        $this->db->table($this->resolveTableName('family'))->insert([
            'parish_id' => $parishId,
            'family_name' => $familyName,
            'head_of_household_id' => $headOfHouseholdId,
            'address' => '123 Main St',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertFamilyMember(int $familyId, int $userId, string $relationship): void
    {
        $this->db->table($this->resolveTableName('family_member'))->insert([
            'family_id' => $familyId,
            'user_id' => $userId,
            'relationship' => $relationship,
        ]);
    }

    private function insertLeadershipPosition(int $userId, int $parishId, int $dioceseId, string $title): void
    {
        $this->db->table($this->resolveTableName('leadership_position'))->insert([
            'user_id' => $userId,
            'scope_type' => 'parish',
            'parish_id' => $parishId,
            'diocese_id' => $dioceseId,
            'title' => $title,
            'role_code' => 'warden',
            'active' => 1,
            'started_at' => '2026-01-01',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    private function eventRow(int $eventId, int $parishId, int $dioceseId, array $overrides = []): array
    {
        return array_merge([
            'id' => $eventId,
            'parish_id' => $parishId,
            'scope_level' => 'parish',
            'scope_diocese_id' => $dioceseId,
            'status' => 'published',
            'publish_at' => null,
            'visibility' => 'members',
            'audience_team_id' => null,
            'audience_family_id' => null,
        ], $overrides);
    }

    /**
     * @return array<string,mixed>
     */
    private function visibilityContext(int $userId, int $parishId, int $dioceseId): array
    {
        return [
            'user_id' => $userId,
            'parish_id' => $parishId,
            'diocese_id' => $dioceseId,
            'can_edit_events' => false,
            'can_manage_diocese_scope' => false,
        ];
    }

    private function ensureEventAudienceTablesExist(): void
    {
        $this->ensureUsersTable();
        $this->ensureProvinceTable();
        $this->ensureDioceseTable();
        $this->ensureParishTable();
        $this->ensureProfilesTable();
        $this->ensureUserRegistrationRequestsTable();
        $this->ensureUserSessionTable();
        $this->ensureGroupsUsersTable();
        $this->ensureAuthIdentitiesTable();
        $this->ensureSettingsTable();
        $this->ensureParishAffiliationTable();
        $this->ensureEventCalendarTable();
        $this->ensureEventTable();
        $this->ensureEventInviteeTable();
        $this->ensureEventRegistrationTable();
        $this->ensureTeamTable();
        $this->ensureTeamMemberTable();
        $this->ensureFamilyTable();
        $this->ensureFamilyMemberTable();
        $this->ensureLeadershipPositionsTable();
        $this->ensureEventAttachmentsTable();
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
                parish_id INTEGER NULL,
                first_name TEXT NULL,
                last_name TEXT NULL,
                nickname TEXT NULL,
                membership_status TEXT NULL,
                locale TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        $profilesTable = $this->resolveTableName('profile');
        $this->ensureColumnExists($profilesTable, 'parish_id', 'INTEGER NULL');
        $this->ensureColumnExists($profilesTable, 'diocese_id', 'INTEGER NULL');
        $this->ensureColumnExists($profilesTable, 'parish_id', 'INTEGER NULL');
        $this->ensureColumnExists($profilesTable, 'nickname', 'TEXT NULL');
    }

    private function ensureGroupsUsersTable(): void
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

    private function ensureUserRegistrationRequestsTable(): void
    {
        $table = $this->createTableName('user_registration_request');
        if (!$this->tableExists('user_registration_request')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                status TEXT NULL,
                requested_parish_id INTEGER NULL,
                requested_parish_id INTEGER NULL,
                reviewed_by INTEGER NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }
    }

    private function ensureUserSessionTable(): void
    {
        $table = $this->createTableName('user_session');
        if (!$this->tableExists('user_session')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                session_token TEXT NOT NULL,
                ip_address TEXT NULL,
                user_agent TEXT NULL,
                device_fingerprint TEXT NULL,
                is_new_device INTEGER DEFAULT 0,
                last_active TEXT NULL,
                created_at TEXT NULL
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
                secret2 TEXT NULL,
                expires TEXT NULL,
                extra TEXT NULL,
                force_reset INTEGER DEFAULT 0,
                last_used_at TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        $resolved = $this->resolveTableName('auth_identities');
        $this->ensureColumnExists($resolved, 'secret2', 'TEXT NULL');
        $this->ensureColumnExists($resolved, 'expires', 'TEXT NULL');
        $this->ensureColumnExists($resolved, 'extra', 'TEXT NULL');
        $this->ensureColumnExists($resolved, 'last_used_at', 'TEXT NULL');
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

    private function ensureParishAffiliationTable(): void
    {
        $table = $this->createTableName('parish_affiliation');
        if (!$this->tableExists('parish_affiliation')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                linked_user_id INTEGER NOT NULL,
                parish_id INTEGER NOT NULL,
                membership_status TEXT NULL,
                is_member INTEGER DEFAULT 0,
                end_date TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }

        $this->ensureColumnExists($this->resolveTableName('parish_affiliation'), 'end_date', 'TEXT NULL');
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

        $eventTable = $this->resolveTableName('event');
        $this->ensureColumnExists($eventTable, 'audience_team_id', 'INTEGER NULL');
        $this->ensureColumnExists($eventTable, 'audience_family_id', 'INTEGER NULL');
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
                guest_name TEXT NULL,
                guest_email TEXT NULL,
                guest_phone TEXT NULL,
                status TEXT NOT NULL,
                notes TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
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

    private function ensureLeadershipPositionsTable(): void
    {
        $table = $this->createTableName('leadership_position');
        if (!$this->tableExists('leadership_position')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                scope_type TEXT NOT NULL,
                parish_id INTEGER NULL,
                diocese_id INTEGER NULL,
                province_id INTEGER NULL,
                title TEXT NOT NULL,
                role_code TEXT NULL,
                active INTEGER DEFAULT 1,
                started_at TEXT NULL,
                ended_at TEXT NULL,
                notes TEXT NULL,
                appointed_by_user_id INTEGER NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }

        $this->ensureColumnExists($this->createTableName('leadership_position'), 'person_id', 'INTEGER NULL');
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

    private function ensureColumnExists(string $table, string $column, string $definition): void
    {
        $hasColumn = false;
        try {
            $columns = $this->db->getFieldNames($table);
            $hasColumn = in_array($column, $columns, true);
        } catch (\Throwable $e) {
            $hasColumn = false;
        }

        if ($hasColumn) {
            return;
        }

        try {
            $this->db->query('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
        } catch (\Throwable $e) {
            // Ignore duplicate/unsupported ALTER failures in test sqlite.
        }
    }
}
