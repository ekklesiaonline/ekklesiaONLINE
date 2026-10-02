<?php

use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

/**
 * @internal
 */
final class SidebarRoleVisibilityTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        helper('auth');
        Services::reset(true);
    }

    protected function tearDown(): void
    {
        Services::reset(true);

        parent::tearDown();
    }

    /**
     * @dataProvider roleVisibilityProvider
     */
    public function testSidebarLinkVisibilityByRole(
        array $groups,
        array $permissions,
        array $expectedVisiblePaths,
        array $expectedHiddenPaths
    ): void {
        $this->injectAuthUser($groups, $permissions);

        $html = (string) view('partials/sidebar');

        foreach ($expectedVisiblePaths as $path) {
            $this->assertContainsHref($html, $path);
        }

        foreach ($expectedHiddenPaths as $path) {
            $this->assertNotContainsHref($html, $path);
        }
    }

    public function testSystemAdminDashboardLinkAppearsOnlyOnce(): void
    {
        $this->injectAuthUser(['systemadmin'], ['system.admin', 'dashboard.view']);

        $html = (string) view('partials/sidebar');

        $this->assertSame(
            1,
            substr_count($html, 'href="' . base_url('system/dashboard') . '"'),
            'The system dashboard should only appear once in the sidebar.'
        );
        $this->assertStringNotContainsString(
            '>' . lang('App.navSystemAdmin') . '<',
            $html,
            'The separate System Admin menu item should not duplicate the dashboard.'
        );
    }

    public function testDiocesanSidebarShowsScopedLeadershipButNotBlockedParishReports(): void
    {
        $this->injectAuthUser(['dioceseadmin', 'clergy'], [
            'diocese.admin', 'deanery.view', 'leadership_position.manage', 'report.view',
        ]);

        $html = (string) view('partials/sidebar');
        $this->assertContainsHref($html, 'diocese/deaneries/manage');
        $this->assertContainsHref($html, 'leadership?scope=diocese');
        $this->assertNotContainsHref($html, 'reports');
        $this->assertNotContainsHref($html, 'leadership');
    }

    public function testLeadershipSidebarRequiresMatchingRoutePermissionAndDiocesanGroup(): void
    {
        $this->injectAuthUser(['parishadmin'], ['member.edit']);
        $html = (string) view('partials/sidebar');
        $this->assertNotContainsHref($html, 'leadership');
        $this->assertContainsHref($html, 'standing');

        $this->injectAuthUser(['member'], ['diocese.admin', 'leadership_position.manage', 'deanery.view']);
        $html = (string) view('partials/sidebar');
        $this->assertNotContainsHref($html, 'leadership?scope=diocese');
        $this->assertNotContainsHref($html, 'diocese/deaneries/manage');
    }

    public static function roleVisibilityProvider(): array
    {
        return [
            'system admin' => [
                ['systemadmin'],
                ['system.admin', 'dashboard.view'],
                ['system/dashboard', 'system/overview', 'system/settings', 'system/liturgical-source', 'parish_list', 'admin/audit-logs', 'profile', 'logout'],
                ['province/dashboard', 'dashboard', 'members', 'services', 'families', 'liturgical-source', 'chart-accounts', 'reports', 'donations', 'expenses', 'budgets', 'pledges'],
            ],
            'province admin' => [
                ['provinceadmin'],
                ['province.admin', 'member.view'],
                ['province/dashboard', 'province/events/calendar', 'province/events', 'province/health', 'province/directory', 'admin/audit-logs', 'profile', 'logout', 'liturgical-source'],
                ['services', 'visitors', 'followup', 'volunteers', 'events', 'worship-planning', 'reports'],
            ],
            'diocesan admin' => [
                ['dioceseadmin'],
                ['diocese.admin', 'member.view', 'service.view', 'parishes.edit'],
                ['diocese/dashboard', 'diocese/finance', 'chart-accounts', 'liturgical-source', 'admin/audit-logs', 'parish_list'],
                ['province/dashboard', 'dioceses', 'volunteers', 'worship-planning', 'members', 'donations', 'expenses', 'budgets', 'pledges', 'reports'],
            ],
            'parish admin' => [
                ['parishadmin'],
                ['member.view', 'family.view', 'service.view', 'attendance.view', 'visitor.view', 'visitor.edit', 'member.edit', 'leadership_position.manage', 'followup.manage', 'finance.view', 'volunteer.manage', 'volunteer.schedule', 'event.view', 'worship.view', 'report.view', 'dashboard.view'],
                ['parish/dashboard', 'me', 'members', 'families', 'services', 'attendance/reports', 'visitors/dashboard', 'leadership', 'standing', 'donations', 'expenses', 'budgets', 'volunteers', 'volunteers/schedules', 'events', 'worship-planning', 'reports', 'admin/audit-logs'],
                ['province/dashboard', 'diocese/finance', 'dioceses', 'liturgical-source', 'parish_list', 'dashboard'],
            ],
            'clergy' => [
                ['clergy'],
                ['member.view', 'family.view', 'service.view', 'attendance.view', 'visitor.view', 'followup.manage', 'event.view', 'worship.view', 'report.view'],
                ['parish/worship', 'me', 'members', 'families', 'services', 'attendance/reports', 'visitors/dashboard', 'events', 'worship-planning', 'reports'],
                ['donations', 'volunteers/schedules', 'admin/audit-logs', 'diocese/finance', 'dioceses', 'province/dashboard', 'dashboard'],
            ],
            'finance officer' => [
                ['finance'],
                ['member.view', 'finance.view', 'finance.manage', 'donation.view', 'expense.view', 'report.view'],
                ['parish/finance', 'me', 'members', 'donations', 'expenses', 'budgets', 'pledges', 'reports'],
                ['followup', 'volunteers/schedules', 'worship-planning', 'diocese/finance', 'dioceses', 'province/dashboard', 'dashboard'],
            ],
            'vestry secretary' => [
                ['vestry_secretary'],
                ['governance.vestry.view', 'governance.vestry.manage', 'governance.report.certify_clerk', 'report.view', 'report.export'],
                ['parish/secretary', 'me', 'reports'],
                ['donations', 'volunteers/schedules', 'diocese/finance', 'dioceses', 'province/dashboard', 'dashboard'],
            ],
            'volunteer coordinator' => [
                ['volunteer_coordinator'],
                ['volunteer.manage', 'volunteer.schedule', 'service.view', 'event.view', 'worship.view', 'volunteer.view'],
                ['parish/volunteers', 'me', 'services', 'volunteers', 'volunteers/schedules', 'events', 'worship-planning'],
                ['donations', 'admin/audit-logs', 'diocese/finance', 'dioceses', 'province/dashboard', 'dashboard'],
            ],
            'followup coordinator' => [
                ['followup_coordinator'],
                ['visitor.view', 'followup.view', 'followup.manage'],
                ['parish/followup', 'me', 'visitors/dashboard'],
                ['donations', 'volunteers/schedules', 'diocese/finance', 'dioceses', 'province/dashboard', 'dashboard'],
            ],
            'worship coordinator' => [
                ['worship_coordinator'],
                ['worship.view', 'worship.manage', 'service.view', 'attendance.view'],
                ['parish/worship', 'me', 'services', 'worship-planning'],
                ['donations', 'volunteers/schedules', 'diocese/finance', 'dioceses', 'province/dashboard', 'dashboard'],
            ],
            'member' => [
                ['member'],
                ['dashboard.view'],
                ['me', 'profile', 'logout'],
                ['members', 'services', 'donations', 'volunteers', 'events', 'admin/audit-logs', 'diocese/finance', 'dioceses', 'province/dashboard', 'dashboard'],
            ],
        ];
    }

    private function injectAuthUser(array $groups, array $permissions): void
    {
        $fakeUser = new class($groups, $permissions) extends User {
            public function __construct(private array $groups, private array $permissions) {}

            public function inGroup(string ...$groups): bool
            {
                foreach ($groups as $group) {
                    if (in_array($group, $this->groups, true)) {
                        return true;
                    }
                }

                return false;
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
        };

        $auth = new class(config('Auth'), $fakeUser) extends Auth {
            public function __construct($config, private User $fakeUser)
            {
                parent::__construct($config);
            }

            public function setAuthenticator(?string $alias = null): self
            {
                return $this;
            }

            public function user(): ?User
            {
                return $this->fakeUser;
            }
        };

        Services::injectMock('auth', $auth);
    }

    private function assertContainsHref(string $html, string $path): void
    {
        $this->assertStringContainsString('href="' . base_url($path) . '"', $html, 'Expected sidebar link missing: ' . $path);
    }

    private function assertNotContainsHref(string $html, string $path): void
    {
        $this->assertStringNotContainsString('href="' . base_url($path) . '"', $html, 'Unexpected sidebar link visible: ' . $path);
    }
}
