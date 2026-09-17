<?php

use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;
use MemberPortal\Controllers\PublicHome;

class SidebarLandingPathTestController extends PublicHome
{
    public function resolvePathForTest($user, ?array $registrationRequest = null): string
    {
        return $this->resolveDashboardRedirectPath($user, $registrationRequest);
    }
}

/**
 * @internal
 */
final class SidebarLandingPathAlignmentTest extends CIUnitTestCase
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
     * @dataProvider roleLandingProvider
     */
    public function testSidebarDashboardLinkMatchesResolvedLandingPath(
        array $groups,
        array $permissions,
        string $expectedLandingPath
    ): void {
        $user = $this->injectAuthUser($groups, $permissions);

        $controller = new SidebarLandingPathTestController();
        $resolvedPath = $controller->resolvePathForTest($user, ['status' => 'approved']);

        $sidebarHtml = (string) view('partials/sidebar');
        $dashboardHref = $this->extractDashboardHref($sidebarHtml);
        $dashboardPath = $this->toPath($dashboardHref);

        $this->assertSame($expectedLandingPath, $resolvedPath);
        $this->assertSame(
            $expectedLandingPath,
            $dashboardPath,
            'Sidebar dashboard link and resolved landing path are not aligned for this role context.'
        );
    }

    public static function roleLandingProvider(): array
    {
        return [
            'province admin' => [
                ['provinceadmin'],
                ['province.admin', 'member.view'],
                '/province/dashboard',
            ],
            'diocesan admin' => [
                ['dioceseadmin'],
                ['diocese.admin', 'member.view'],
                '/diocese/dashboard',
            ],
            'parish admin' => [
                ['parishadmin'],
                ['member.view', 'service.view', 'dashboard.view'],
                '/parish/dashboard',
            ],
            'clergy' => [
                ['clergy'],
                ['member.view', 'service.view', 'attendance.view'],
                '/parish/worship',
            ],
            'treasurer' => [
                ['treasurer'],
                ['finance.view', 'donation.view', 'expense.view'],
                '/parish/finance',
            ],
            'volunteer coordinator' => [
                ['volunteer_coordinator'],
                ['volunteer.manage', 'volunteer.schedule', 'volunteer.view'],
                '/parish/volunteers',
            ],
            'member' => [
                ['member'],
                ['dashboard.view'],
                '/me',
            ],
        ];
    }

    private function injectAuthUser(array $groups, array $permissions): User
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

        return $fakeUser;
    }

    private function extractDashboardHref(string $html): string
    {
        if (!preg_match('/bi bi-speedometer2<\/i>\s*<\/a>/m', $html)) {
            // Continue with href extraction below; this guard keeps intent explicit.
        }

        if (!preg_match('/<a class="nav-link[^\"]*" href="([^"]+)">\s*<i class="bi bi-speedometer2"[^>]*><\/i>/m', $html, $matches)) {
            $this->fail('Could not find dashboard link in sidebar markup.');
        }

        return (string) $matches[1];
    }

    private function toPath(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $normalized = is_string($path) ? trim($path) : '';

        if ($normalized === '') {
            return '/';
        }

        return '/' . ltrim($normalized, '/');
    }
}
