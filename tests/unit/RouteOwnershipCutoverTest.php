<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class RouteOwnershipCutoverTest extends CIUnitTestCase
{
    public function testSessionRoutesAreOwnedByMemberPortalModule(): void
    {
        $rootRoutes = file_get_contents(ROOTPATH . 'app/Config/Routes.php');
        $memberPortalRoutes = file_get_contents(ROOTPATH . 'MemberPortal/Config/Routes.php');

        $this->assertNotFalse($rootRoutes);
        $this->assertNotFalse($memberPortalRoutes);

        $this->assertStringNotContainsString("'session/keepalive'", $rootRoutes);
        $this->assertStringNotContainsString("'session/device-verify'", $rootRoutes);

        $this->assertStringContainsString('session/keepalive', $memberPortalRoutes);
        $this->assertStringContainsString('session/device-verify', $memberPortalRoutes);
        $this->assertStringContainsString('SessionSecurity::keepalive', $memberPortalRoutes);
        $this->assertStringContainsString('SessionSecurity::deviceVerifyForm', $memberPortalRoutes);
    }

    public function testHomeSurfaceRoutesAreOwnedByMemberPortalModule(): void
    {
        $rootRoutes = file_get_contents(ROOTPATH . 'app/Config/Routes.php');
        $memberPortalRoutes = file_get_contents(ROOTPATH . 'MemberPortal/Config/Routes.php');

        $this->assertNotFalse($rootRoutes);
        $this->assertNotFalse($memberPortalRoutes);

        $this->assertStringNotContainsString('PublicHome::index', $rootRoutes);
        $this->assertStringNotContainsString("'uploads/(:any)'", $rootRoutes);

        $this->assertStringContainsString("'/'", $memberPortalRoutes);
        $this->assertStringContainsString('uploads/(:any)', $memberPortalRoutes);
        $this->assertStringContainsString('PublicHome::index', $memberPortalRoutes);
        $this->assertStringContainsString('PublicAssets::serveUpload', $memberPortalRoutes);
    }

    public function testLocaleRouteIsOwnedByMemberPortalModule(): void
    {
        $rootRoutes = file_get_contents(ROOTPATH . 'app/Config/Routes.php');
        $memberPortalRoutes = file_get_contents(ROOTPATH . 'MemberPortal/Config/Routes.php');

        $this->assertNotFalse($rootRoutes);
        $this->assertNotFalse($memberPortalRoutes);

        $this->assertStringNotContainsString("'locale/set/(:segment)'", $rootRoutes);

        $this->assertStringContainsString('locale/set/(:segment)', $memberPortalRoutes);
        $this->assertStringContainsString('PublicLocale::set', $memberPortalRoutes);
    }

    public function testLegacyCompatibilityAliasesStayModuleOwnedWithoutRedirects(): void
    {
        $clergyRoutes = file_get_contents(ROOTPATH . 'Clergy/Config/Routes.php');
        $worshipRoutes = file_get_contents(ROOTPATH . 'Worship/Config/Routes.php');
        $volunteerRoutes = file_get_contents(ROOTPATH . 'Volunteer/Config/Routes.php');

        $this->assertNotFalse($clergyRoutes);
        $this->assertNotFalse($worshipRoutes);
        $this->assertNotFalse($volunteerRoutes);

        $this->assertStringContainsString("get('followup', static fn() => redirect()->to(base_url('followup-workflow/tasks')))", $clergyRoutes);
        $this->assertStringContainsString("post('followup/(:any)', static fn() => redirect()->to(base_url('followup-workflow/tasks')))", $clergyRoutes);
        // Retired queue slug keeps working: GET redirects (filters preserved),
        // POST aliases to the same action.
        $this->assertStringContainsString("get('call-tasks', static function ()", $clergyRoutes);
        $this->assertStringContainsString("post('call-tasks/complete/(:num)'", $clergyRoutes);
        $this->assertStringContainsString("get('tasks', '\\\\Clergy\\\\Controllers\\\\FollowupTasks::callTasks')", $clergyRoutes);

        $this->assertStringNotContainsString("addRedirect('attendance/bulkMark'", $worshipRoutes);
        $this->assertStringContainsString("post('attendance/bulkMark'", $worshipRoutes);

        $this->assertStringNotContainsString("addRedirect('volunteers/viewTeam/(:num)'", $volunteerRoutes);
        $this->assertStringContainsString("get('volunteers/viewTeam/(:num)'", $volunteerRoutes);
        $this->assertStringContainsString("post('volunteers/storeTeam'", $volunteerRoutes);
    }
}
