<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regression coverage for the Volunteer module security audit.
 *
 * Source-level assertions (no HTTP fixtures needed): every finding's guard
 * must exist in code/config, and every mutation route must stay inside the
 * boundary + rate-limit filter lists.
 *
 * @internal
 */
final class VolunteerSecurityHardeningTest extends CIUnitTestCase
{
    private static function filtersSource(): string
    {
        return file_get_contents(ROOTPATH . 'app/Config/Filters.php');
    }

    private static function filtersSection(string $filterName): string
    {
        $src = self::filtersSource();
        $pattern = '/\'' . preg_quote($filterName, '/') . '\'\s*=>\s*\[\s*\'before\'\s*=>\s*\[(.*?)\]/s';
        self::assertSame(1, preg_match($pattern, $src, $m), 'missing ' . $filterName . ' before list');
        return $m[1];
    }

    public function testVolunteersCoveredByDiocesanOperationalBoundary(): void
    {
        $section = self::filtersSection('diocesanOperationalBoundary');
        $this->assertStringContainsString("'volunteers'", $section);
        $this->assertStringContainsString("'volunteers/*'", $section);
    }

    public function testVolunteersCoveredByParishScope(): void
    {
        $section = self::filtersSection('parishScope');
        $this->assertStringContainsString("'volunteers'", $section);
        $this->assertStringContainsString("'volunteers/*'", $section);
    }

    public function testEveryVolunteerMutationRouteIsRateLimited(): void
    {
        $routes = file_get_contents(ROOTPATH . 'Volunteer/Config/Routes.php');
        preg_match_all("/routes->post\('([^']+)'/", $routes, $m);
        $postUris = $m[1];
        $this->assertNotEmpty($postUris, 'no POST routes found in Volunteer routes');

        $section = self::filtersSection('apiRateLimit');

        foreach ($postUris as $uri) {
            // Routes inside the 'volunteers' group omit the prefix; add it back.
            $full = str_starts_with($uri, 'volunteers') ? $uri : 'volunteers/' . $uri;
            // Route placeholders map to the /* suffix used by the filter list.
            $pattern = preg_replace('/\((:\w+)\)/', '*', $full);
            $this->assertTrue(
                str_contains($section, "'" . $pattern . "'") || str_contains($section, "'" . $full . "'"),
                'unthrottled volunteer POST route: ' . $uri
            );
        }
    }

    public function testApiRateLimitPathLimitsCoverLegacyAliases(): void
    {
        $src = file_get_contents(ROOTPATH . 'app/Filters/ApiRateLimitFilter.php');
        foreach (['storeTeam', 'store-schedule', 'update-schedule', 'cancel-schedule', 'confirm-schedule'] as $alias) {
            $this->assertStringContainsString($alias, $src, 'legacy alias missing from path limits: ' . $alias);
        }
    }

    public function testScheduleWritesEnforceTeamMembership(): void
    {
        $src = file_get_contents(ROOTPATH . 'Volunteer/Controllers/VolunteerSchedules.php');
        $this->assertSame(2, substr_count($src, 'isUserOnTeam('), 'storeSchedule and updateSchedule must each check roster membership');
        $this->assertStringContainsString("lang('Volunteer.memberNotOnTeam')", $src);
    }

    public function testAddMemberValidatesRoleAndDuplicates(): void
    {
        $src = file_get_contents(ROOTPATH . 'Volunteer/Controllers/Volunteers.php');
        $this->assertStringContainsString('in_list[member,coordinator,leader]', $src);
        $this->assertStringContainsString('isUserOnTeam($teamId, $userId)', $src);
        $this->assertStringContainsString('TEAM_MEMBER_ALREADY_EXISTS', $src);
    }

    public function testStoreTeamValidatesLeaderScopeAndAudits(): void
    {
        $src = file_get_contents(ROOTPATH . 'Volunteer/Controllers/Volunteers.php');
        $this->assertStringContainsString("'leader_id' => 'permit_empty|is_not_unique[users.id]'", $src);
        $this->assertStringContainsString('isUserAffiliatedWithParish($leaderId', $src);
        $this->assertStringContainsString("'team.create'", $src);
    }

    public function testFormRepopulationIsParishScoped(): void
    {
        $src = file_get_contents(ROOTPATH . 'Volunteer/Controllers/VolunteerSchedules.php');
        $this->assertStringContainsString('getParishTeamMembersForForm', $src);
        // No raw getTeamMembers call may remain in the form-rendering paths.
        $this->assertSame(0, substr_count($src, 'getTeamMembers($teamId) : []'), 'unscoped old(team_id) repopulation remains');
        $this->assertSame(0, substr_count($src, 'getTeamMembers($oldTeamId) : []'), 'unscoped old(team_id) repopulation remains');
    }

    public function testApiErrorsUseContractEnvelopes(): void
    {
        $src = file_get_contents(ROOTPATH . 'Volunteer/Controllers/Volunteers.php')
            . file_get_contents(ROOTPATH . 'Volunteer/Controllers/VolunteerSchedules.php');
        $this->assertStringContainsString('apiErrorPayload', $src);
        $this->assertStringNotContainsString("['error' => ", $src, 'bare error-key JSON response remains');
        $this->assertStringContainsString('logAuthorizationDenial', $src);
    }

    public function testRosterQueriesDoNotFetchMemberEmail(): void
    {
        $src = file_get_contents(ROOTPATH . 'app/Models/TeamMemberModel.php');
        $this->assertStringNotContainsString('ai.secret', $src, 'email column returned to roster queries');
        $this->assertStringNotContainsString('auth_identities', $src);
    }

    public function testReminderLogsDoNotContainEmail(): void
    {
        $src = file_get_contents(ROOTPATH . 'app/Commands/VolunteerRemind.php');
        $this->assertStringNotContainsString('email={', $src, 'raw email still logged on send failure');
    }

    public function testDeadHelpersAndDashboardAliasRemoved(): void
    {
        $modelSrc = file_get_contents(ROOTPATH . 'app/Models/VolunteerScheduleModel.php');
        $this->assertStringNotContainsString('function getUserSchedule', $modelSrc);
        $this->assertStringNotContainsString('function getTeamSchedule(', $modelSrc);

        $teamSrc = file_get_contents(ROOTPATH . 'app/Models/TeamModel.php');
        $this->assertStringNotContainsString('function getTeamWithMembers', $teamSrc);

        $routes = file_get_contents(ROOTPATH . 'Volunteer/Config/Routes.php');
        $this->assertStringNotContainsString('Dashboard::index', $routes);
        $this->assertFileDoesNotExist(ROOTPATH . 'Volunteer/Controllers/Dashboard.php');
    }
}
