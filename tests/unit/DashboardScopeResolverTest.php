<?php

use App\Controllers\BaseController;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Tests BaseController::resolveDashboardScope() — the structure-first
 * landing resolver that maps user groups to dashboard tiers.
 *
 * @internal
 */
final class DashboardScopeResolverTest extends CIUnitTestCase
{
    /**
     * @dataProvider scopeResolverProvider
     */
    public function testResolveDashboardScopeReturnsCorrectTierAndRoute(
        array $groups,
        array $permissions,
        string $expectedTier,
        string $expectedRoute
    ): void {
        $user = $this->createMockUser($groups, $permissions);

        $controller = new class extends BaseController {
            public function exposeResolveDashboardScope($user): array
            {
                return $this->resolveDashboardScope($user);
            }
        };

        $scope = $controller->exposeResolveDashboardScope($user);

        $this->assertSame($expectedTier, $scope['tier']);
        $this->assertSame($expectedRoute, $scope['route']);
    }

    public static function scopeResolverProvider(): array
    {
        return [
            'system admin' => [
                ['systemadmin'],
                ['system.admin'],
                'system',
                '/system/dashboard',
            ],
            'province admin' => [
                ['provinceadmin'],
                ['province.admin'],
                'province',
                '/province/dashboard',
            ],
            'diocese admin' => [
                ['dioceseadmin'],
                ['diocese.admin'],
                'diocese',
                '/diocese/dashboard',
            ],
            'parish admin' => [
                ['parishadmin'],
                ['dashboard.view'],
                'parish',
                '/parish/dashboard',
            ],
            'finance officer' => [
                ['finance'],
                ['finance.view'],
                'parish',
                '/parish/finance',
            ],
            'vestry secretary' => [
                ['vestry_secretary'],
                ['report.view'],
                'parish',
                '/parish/secretary',
            ],
            'vestry member' => [
                ['vestry_member'],
                ['governance.vestry.view'],
                'parish',
                '/parish/governance',
            ],
            'warden' => [
                ['warden'],
                ['governance.vestry.view'],
                'parish',
                '/parish/governance',
            ],
            'clergy' => [
                ['clergy'],
                ['service.view'],
                'parish',
                '/parish/worship',
            ],
            'volunteer coordinator' => [
                ['volunteer_coordinator'],
                ['volunteer.view'],
                'parish',
                '/parish/volunteers',
            ],
            'followup coordinator' => [
                ['followup_coordinator'],
                ['followup.manage'],
                'parish',
                '/parish/followup',
            ],
            'worship coordinator' => [
                ['worship_coordinator'],
                ['worship.view'],
                'parish',
                '/parish/worship',
            ],
            'member' => [
                ['member'],
                ['dashboard.view'],
                'personal',
                '/me',
            ],
            'system admin takes priority over parish admin' => [
                ['systemadmin', 'parishadmin'],
                ['system.admin', 'dashboard.view'],
                'system',
                '/system/dashboard',
            ],
            'parish admin takes priority over member' => [
                ['parishadmin', 'member'],
                ['dashboard.view'],
                'parish',
                '/parish/dashboard',
            ],
        ];
    }

    public function testResolveDashboardScopeReturnsGuestForNullUser(): void
    {
        $controller = new class extends BaseController {
            public function exposeResolveDashboardScope($user): array
            {
                return $this->resolveDashboardScope($user);
            }
        };

        $scope = $controller->exposeResolveDashboardScope(null);

        $this->assertSame('personal', $scope['tier']);
        $this->assertSame('guest', $scope['role']);
        $this->assertSame('/login', $scope['route']);
    }

    public function testCanAccessMyDashboardReturnsTrueForParishRoles(): void
    {
        $controller = new class extends BaseController {
            public function exposeCanAccessMyDashboard($user): bool
            {
                return $this->canAccessMyDashboard($user);
            }
        };

        $parishAdmin = $this->createMockUser(['parishadmin'], ['dashboard.view']);
        $this->assertTrue($controller->exposeCanAccessMyDashboard($parishAdmin));

        $member = $this->createMockUser(['member'], ['dashboard.view']);
        $this->assertTrue($controller->exposeCanAccessMyDashboard($member));

        $vestryMember = $this->createMockUser(['vestry_member'], ['governance.vestry.view']);
        $this->assertTrue($controller->exposeCanAccessMyDashboard($vestryMember));
    }

    public function testCanAccessMyDashboardReturnsFalseForNullUser(): void
    {
        $controller = new class extends BaseController {
            public function exposeCanAccessMyDashboard($user): bool
            {
                return $this->canAccessMyDashboard($user);
            }
        };

        $this->assertFalse($controller->exposeCanAccessMyDashboard(null));
    }

    private function createMockUser(array $groups, array $permissions): object
    {
        return new class($groups, $permissions) {
            public $id = 0;

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
    }
}
