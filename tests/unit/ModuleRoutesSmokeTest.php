<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ModuleRoutesSmokeTest extends CIUnitTestCase
{
    public static function moduleRouteProvider(): array
    {
        return [
            'member-portal routes' => [
                ROOTPATH . 'MemberPortal/Config/Routes.php',
                [
                    'Profile::index',
                    'Family::index',
                ],
            ],
            'clergy routes' => [
                ROOTPATH . 'Clergy/Config/Routes.php',
                [
                    'Members::index',
                    'Guest::index',
                ],
            ],
            'worship routes' => [
                ROOTPATH . 'Worship/Config/Routes.php',
                [
                    'Service::index',
                    'Attendance::reports',
                ],
            ],
            'treasurer routes' => [
                ROOTPATH . 'Treasurer/Config/Routes.php',
                [
                    'Donation::index',
                    'Expense::index',
                ],
            ],
            'volunteer routes' => [
                ROOTPATH . 'Volunteer/Config/Routes.php',
                [
                    'Volunteer::index',
                    'Volunteer::schedules',
                ],
            ],
            'diocesan routes' => [
                ROOTPATH . 'Diocesan/Config/Routes.php',
                [
                    'ChartOfAccounts::index',
                    'LiturgicalCalendar::index',
                    'Dashboard::index',
                    'Dashboard::recentActivity',
                ],
            ],
        ];
    }

    /**
     * @dataProvider moduleRouteProvider
     */
    public function testModuleRoutesContainExpectedHandlers(string $routeFilePath, array $expectedHandlers): void
    {
        $content = file_get_contents($routeFilePath);
        $this->assertNotFalse($content, 'Unable to read route file: ' . $routeFilePath);

        foreach ($expectedHandlers as $handler) {
            $this->assertStringContainsString($handler, $content, 'Expected route handler not found: ' . $handler);
        }
    }

    public function testRootRoutesLoadAllModuleRouteFiles(): void
    {
        $content = file_get_contents(ROOTPATH . 'app/Config/Routes.php');
        $this->assertNotFalse($content);

        $expectedRequires = [
            "require ROOTPATH . 'MemberPortal/Config/Routes.php';",
            "require ROOTPATH . 'Clergy/Config/Routes.php';",
            "require ROOTPATH . 'Worship/Config/Routes.php';",
            "require ROOTPATH . 'Treasurer/Config/Routes.php';",
            "require ROOTPATH . 'Volunteer/Config/Routes.php';",
            "require ROOTPATH . 'Province/Config/Routes.php';",
            "require ROOTPATH . 'Diocesan/Config/Routes.php';",
        ];

        foreach ($expectedRequires as $snippet) {
            $this->assertStringContainsString($snippet, $content, 'Expected module routes include missing: ' . $snippet);
        }

        $this->assertStringContainsString('SystemOverview::index', $content);
        $this->assertStringContainsString("->get('overview'", $content);
        $this->assertStringContainsString('Parishes::index', $content);
        $this->assertStringContainsString("->group('parish_list'", $content);
    }

    public function testDiocesanRoutesDoNotExposeSystemOverview(): void
    {
        $content = file_get_contents(ROOTPATH . 'Diocesan/Config/Routes.php');
        $this->assertNotFalse($content, 'Unable to read diocesan route file.');

        $this->assertStringNotContainsString('SystemOverview::index', $content);
        $this->assertStringNotContainsString("diocesan/system-overview", $content);
        $this->assertStringNotContainsString('Parishes::index', $content);
    }

    public function testDiocesanLiturgicalRoutesAreReadOnly(): void
    {
        $content = file_get_contents(ROOTPATH . 'Diocesan/Config/Routes.php');
        $this->assertNotFalse($content, 'Unable to read diocesan route file.');

        $this->assertStringContainsString("liturgical-source', function", $content);
        $this->assertStringContainsString('LiturgicalCalendar::index', $content);
        $this->assertStringNotContainsString('LiturgicalCalendar::store', $content);
        $this->assertStringNotContainsString('LiturgicalCalendar::edit', $content);
        $this->assertStringNotContainsString('LiturgicalCalendar::update', $content);
        $this->assertStringNotContainsString('LiturgicalCalendar::delete', $content);
    }

    public function testSystemRoutesExposeLiturgicalWriteOperations(): void
    {
        $content = file_get_contents(ROOTPATH . 'app/Config/Routes.php');
        $this->assertNotFalse($content, 'Unable to read app route file.');

        $this->assertStringContainsString("system', ['filter' => 'permission:system.admin']", $content);
        $this->assertStringContainsString("->get('liturgical-source'", $content);
        $this->assertStringContainsString('LiturgicalCalendar::store', $content);
        $this->assertStringContainsString('LiturgicalCalendar::edit', $content);
        $this->assertStringContainsString('LiturgicalCalendar::update', $content);
        $this->assertStringContainsString('LiturgicalCalendar::delete', $content);
    }

    public function testClergyRoutesDoNotExposeProvinceWriteMemberEndpoints(): void
    {
        $content = file_get_contents(ROOTPATH . 'Clergy/Config/Routes.php');
        $this->assertNotFalse($content, 'Unable to read clergy route file.');

        $this->assertStringNotContainsString("->post('transfer'", $content);
        $this->assertStringNotContainsString("->post('reassign'", $content);
        $this->assertStringNotContainsString("province-staff/store", $content);
        $this->assertStringNotContainsString("province-staff/update", $content);
        $this->assertStringNotContainsString("province-staff/delete", $content);
        $this->assertStringNotContainsString('Members::transfer', $content);
        $this->assertStringNotContainsString('Members::reassign', $content);
        $this->assertStringNotContainsString('Members::storeProvinceStaff', $content);
        $this->assertStringNotContainsString('Members::updateProvinceStaff', $content);
        $this->assertStringNotContainsString('Members::deleteProvinceStaff', $content);
    }

    public function testDioceseRoutesAreGatedByDioceseAdminPermission(): void
    {
        $content = file_get_contents(ROOTPATH . 'Diocesan/Config/Routes.php');
        $this->assertNotFalse($content, 'Unable to read diocesan route file.');

        $this->assertStringContainsString("'diocese', ['filter' => 'permission:diocese.admin']", $content, 'Diocese dashboard route group must be gated by diocese.admin permission.');
        $this->assertStringContainsString('Dashboard::index', $content, 'Diocese dashboard index must be present in diocesan routes.');
        $this->assertStringContainsString('Dashboard::recentActivity', $content, 'Diocese recentActivity must be present in diocesan routes.');
        $this->assertStringNotContainsString("'dashboard/recent-activity'", file_get_contents(ROOTPATH . 'MemberPortal/Config/Routes.php') ?: '', 'dashboard/recent-activity must not exist in MemberPortal routes; it has moved to Diocesan module.');
    }

    public function testRegistrationModerationRoutesBelongToMembersModule(): void
    {
        $clergyContent = file_get_contents(ROOTPATH . 'Clergy/Config/Routes.php');
        $this->assertNotFalse($clergyContent, 'Unable to read clergy route file.');

        $diocesanContent = file_get_contents(ROOTPATH . 'Diocesan/Config/Routes.php');
        $this->assertNotFalse($diocesanContent, 'Unable to read diocesan route file.');

        $this->assertStringContainsString("'members', ['filter' => 'permission:member.view']", $clergyContent);
        $this->assertStringContainsString("registrations/approve", $clergyContent);
        $this->assertStringContainsString("registrations/dismiss", $clergyContent);
        $this->assertStringContainsString("registrations/ban", $clergyContent);
        $this->assertStringContainsString("registrations/delete", $clergyContent);

        $this->assertStringNotContainsString("registrations/approve", $diocesanContent);
        $this->assertStringNotContainsString("registrations/dismiss", $diocesanContent);
        $this->assertStringNotContainsString("registrations/ban", $diocesanContent);
        $this->assertStringNotContainsString("registrations/delete", $diocesanContent);
    }
}
