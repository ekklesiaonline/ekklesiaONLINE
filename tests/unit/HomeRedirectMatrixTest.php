<?php

use MemberPortal\Controllers\PublicHome;
use CodeIgniter\Test\CIUnitTestCase;

class HomeRedirectTestController extends PublicHome
{
    public function resolvePathForTest($user, ?array $registrationRequest = null): string
    {
        return $this->resolveDashboardRedirectPath($user, $registrationRequest);
    }

    public function shouldLogoutForRegistrationStatusForTest(?array $registrationRequest): bool
    {
        return $this->shouldLogoutForRegistrationStatus($registrationRequest);
    }
}

/**
 * @internal
 */
final class HomeRedirectMatrixTest extends CIUnitTestCase
{
    public static function dashboardRedirectMatrixProvider(): array
    {
        return [
            'pending profile always lands on profile completion' => [['provinceadmin'], ['status' => 'pending_profile'], '/profile/complete'],
            'approved province admin lands on province dashboard' => [['provinceadmin'], ['status' => 'approved'], '/province/dashboard'],
            'approved diocesan admin lands on diocese dashboard' => [['dioceseadmin'], ['status' => 'approved'], '/diocese/dashboard'],
            'parish admin lands on parish dashboard' => [['parishadmin'], ['status' => 'approved'], '/parish/dashboard'],
            'clergy lands on worship dashboard' => [['clergy'], ['status' => 'approved'], '/parish/worship'],
            'finance officer lands on finance dashboard' => [['finance'], ['status' => 'approved'], '/parish/finance'],
            'vestry secretary lands on secretary dashboard' => [['vestry_secretary'], ['status' => 'approved'], '/parish/secretary'],
            'volunteer coordinator lands on volunteer dashboard' => [['volunteer_coordinator'], ['status' => 'approved'], '/parish/volunteers'],
            'followup coordinator lands on followup dashboard' => [['followup_coordinator'], ['status' => 'approved'], '/parish/followup'],
            'worship coordinator lands on worship dashboard' => [['worship_coordinator'], ['status' => 'approved'], '/parish/worship'],
            'member lands on personal dashboard' => [['member'], ['status' => 'approved'], '/me'],
            'province plus diocesan prefers province dashboard once approved' => [['provinceadmin', 'diocesanadmin'], ['status' => 'approved'], '/province/dashboard'],
            'pending review stays on dashboard gate' => [['member'], ['status' => 'pending_review'], '/me'],
            'no roles falls back to default dashboard' => [[], null, '/dashboard'],
        ];
    }

    /**
     * @dataProvider dashboardRedirectMatrixProvider
     */
    public function testDashboardRedirectMatrix(array $groups, ?array $registrationRequest, string $expectedPath): void
    {
        $user = new class($groups) {
            public $id = 0;

            public function __construct(private array $groups) {}

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
                    $hasPermission = in_array($permission, [
                        'dashboard.view',
                        'province.admin',
                        'diocese.admin',
                        'system.admin',
                        'admin.system',
                        'member.view',
                        'service.view',
                        'finance.view',
                        'volunteer.view',
                        'governance.vestry.view',
                        'report.view',
                    ], true) && (
                        $permission !== 'province.admin' || in_array('provinceadmin', $this->groups, true)
                    ) && (
                        $permission !== 'diocese.admin' || in_array('dioceseadmin', $this->groups, true)
                    ) && (
                        $permission !== 'system.admin' || in_array('systemadmin', $this->groups, true)
                    );
                    if (!$hasPermission) {
                        return false;
                    }
                }
                return true;
            }
        };

        $controller = new HomeRedirectTestController();

        $this->assertSame($expectedPath, $controller->resolvePathForTest($user, $registrationRequest));
    }

    public function testDashboardRedirectDefaultsWhenUserMissing(): void
    {
        $controller = new HomeRedirectTestController();

        $this->assertSame('/dashboard', $controller->resolvePathForTest(null));
    }

    public function testProvinceAdminWithoutPermissionStillLandsOnProvinceDashboard(): void
    {
        $user = new class {
            public $id = 0;
            public function inGroup(string ...$groups): bool
            {
                foreach ($groups as $group) {
                    if ($group === 'provinceadmin') return true;
                }
                return false;
            }

            public function can(string ...$permissions): bool
            {
                foreach ($permissions as $permission) {
                    if ($permission === 'dashboard.view') return true;
                }
                return false;
            }
        };

        $controller = new HomeRedirectTestController();

        $this->assertSame('/province/dashboard', $controller->resolvePathForTest($user, ['status' => 'approved']));
    }

    public function testSystemAdminWithoutPermissionStillLandsOnSystemDashboard(): void
    {
        $user = new class {
            public $id = 0;
            public function inGroup(string ...$groups): bool
            {
                foreach ($groups as $group) {
                    if ($group === 'systemadmin') return true;
                }
                return false;
            }

            public function can(string ...$permissions): bool
            {
                foreach ($permissions as $permission) {
                    if ($permission === 'dashboard.view') return true;
                }
                return false;
            }
        };

        $controller = new HomeRedirectTestController();

        $this->assertSame('/system/dashboard', $controller->resolvePathForTest($user, ['status' => 'approved']));
    }

    public function testDioceseAdminWithoutPermissionStillLandsOnDioceseDashboard(): void
    {
        $user = new class {
            public $id = 0;
            public function inGroup(string ...$groups): bool
            {
                foreach ($groups as $group) {
                    if ($group === 'dioceseadmin') return true;
                }
                return false;
            }

            public function can(string ...$permissions): bool
            {
                foreach ($permissions as $permission) {
                    if ($permission === 'dashboard.view') return true;
                }
                return false;
            }
        };

        $controller = new HomeRedirectTestController();

        $this->assertSame('/diocese/dashboard', $controller->resolvePathForTest($user, ['status' => 'approved']));
    }

    public function testDismissedOrBannedRegistrationRequiresLogout(): void
    {
        $controller = new HomeRedirectTestController();

        $this->assertTrue($controller->shouldLogoutForRegistrationStatusForTest(['status' => 'dismissed']));
        $this->assertTrue($controller->shouldLogoutForRegistrationStatusForTest(['status' => 'banned']));
        $this->assertFalse($controller->shouldLogoutForRegistrationStatusForTest(['status' => 'approved']));
    }
}
