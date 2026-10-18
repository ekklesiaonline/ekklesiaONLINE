<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ProvinceDashboardDataService;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\SiteURI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Province\Controllers\Dashboard as ProvinceDashboard;
use Tests\Support\Database\CanonicalTestSchema;

/**
 * @internal
 */
final class ProvinceDashboardControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    private array $capturedViewData = [];
    private ?string $capturedViewName = null;

    protected function setUp(): void
    {
        parent::setUp();

        CanonicalTestSchema::ensure($this->db);
        foreach ([
            'province',
            'diocese',
            'parish',
            'users',
            'person',
            'profile',
            'auth_groups_users',
            'parish_affiliation',
            'service',
            'attendance',
        ] as $table) {
            CanonicalTestSchema::reset($this->db, $table);
        }
    }

    private function seedProvinceAndDioceses(int $dioceseCount = 4): void
    {
        $this->db->table('province')->insert(['id' => 1, 'name' => 'Test Province']);

        for ($i = 1; $i <= $dioceseCount; $i++) {
            $this->db->table('diocese')->insert([
                'id'          => $i,
                'province_id' => 1,
                'name'        => "Diocese {$i}",
            ]);
            $this->db->table('parish')->insert([
                'id'         => $i,
                'diocese_id' => $i,
                'name'       => "Parish {$i}",
            ]);
        }
    }

    private function seedAdminUser(int $userId = 1): void
    {
        $this->db->table('users')->insert([
            'id'       => $userId,
            'username' => 'provinceadmin',
            'active'   => 1,
        ]);
        $this->db->table('person')->insert([
            'id'         => $userId,
            'first_name' => 'Province',
            'last_name'  => 'Admin',
            'is_active'  => 1,
        ]);
        $this->db->table('profile')->insert([
            'user_id'    => $userId,
            'person_id'  => $userId,
            'province_id' => 1,
            'first_name' => 'Province',
            'last_name'  => 'Admin',
        ]);
        $this->db->table('auth_groups_users')->insert([
            'user_id' => $userId,
            '"group"' => 'provinceadmin',
        ]);
    }

    private function injectProvinceAdminAuth(int $userId = 1): void
    {
        $fakeUser = new class($userId) extends User {
            public function __construct(private int $testUserId)
            {
                $this->attributes['id'] = $testUserId;
                $this->attributes['username'] = 'provinceadmin';
                $this->attributes['email'] = 'provinceadmin@example.org';
            }

            public function inGroup(string ...$groups): bool
            {
                return in_array('provinceadmin', $groups, true);
            }

            public function can(string ...$permissions): bool
            {
                return true;
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

    private function injectCapturingRenderer(): void
    {
        $captured = &$this->capturedViewData;
        $capturedName = &$this->capturedViewName;

        $renderer = new class($captured, $capturedName) implements \CodeIgniter\View\RendererInterface {
            public function __construct(private array &$captured, private ?string &$capturedName)
            {
            }

            public function render(string $view, ?array $options = null, ?bool $saveData = null): string
            {
                $this->capturedName = $view;
                return 'RENDERED';
            }

            public function renderString(string $view, ?array $options = null, ?bool $saveData = null): string
            {
                $this->capturedName = $view;
                return 'RENDERED';
            }

            public function setData(array $data = [], ?string $context = null): \CodeIgniter\View\RendererInterface
            {
                $this->captured = $data;
                return $this;
            }

            public function setVar(string $name, $value = null, ?string $context = null): \CodeIgniter\View\RendererInterface
            {
                return $this;
            }

            public function resetData(): \CodeIgniter\View\RendererInterface
            {
                return $this;
            }

            public function getData(): array
            {
                return $this->captured;
            }

            public function startPerformanceTimer()
            {
            }

            public function stopPerformanceTimer()
            {
            }
        };

        Services::injectMock('renderer', $renderer);
    }

    private function makeController(array $getData = []): ProvinceDashboard
    {
        $this->injectCapturingRenderer();

        $queryString = $getData === [] ? '' : '?' . http_build_query($getData);
        $request = new IncomingRequest(
            config('App'),
            new SiteURI(config('App'), 'http://ekklesiaonline.local/province/dashboard' . $queryString),
            'php://input',
            new UserAgent()
        );
        $request->setMethod('GET');
        Services::injectMock('request', $request);

        $fakeService = new class() extends ProvinceDashboardDataService {
            public function getMembershipOverview(int $provinceId, ?int $dioceseId = null): array
            {
                return [
                    'total_dioceses' => 4,
                    'total_parishes' => 4,
                    'total_people'   => 0,
                    'visitor_count'  => 0,
                    'total_members'  => 0,
                    'active_members' => 0,
                    'good_standing_members' => 0,
                    'leadership_members' => 0,
                ];
            }

            public function getStatusCounts(int $provinceId, ?int $dioceseId = null): array
            {
                return [];
            }

            public function getAttendanceAggregate(string $period, ?string $startDate, ?string $endDate, int $provinceId, ?int $dioceseId = null): array
            {
                return [
                    'total_attendance' => 0,
                    'total_services' => 0,
                    'total_communicants' => 0,
                    'average_per_service' => 0,
                    'period' => $period,
                ];
            }

            public function getRecentServicesForDiocese(int $provinceId, int $dioceseId, int $limit = 20): array
            {
                return [];
            }
        };

        $controller = new class() extends ProvinceDashboard {
            protected function isProvinceAdmin(): bool
            {
                return true;
            }

            protected function resolveProvinceScope(): array
            {
                return ['province_id' => 1, 'diocese_id' => null, 'parish_id' => null];
            }
        };
        $controller->initController($request, service('response'), service('logger'));

        $reflection = new \ReflectionClass(ProvinceDashboard::class);
        $property = $reflection->getProperty('dashboardData');
        $property->setAccessible(true);
        $property->setValue($controller, $fakeService);

        return $controller;
    }

    public function testIndexPassesDefaultPaginationDataToView(): void
    {
        $this->seedProvinceAndDioceses(4);
        $this->seedAdminUser();
        $this->injectProvinceAdminAuth();

        $controller = $this->makeController();
        $controller->index();

        $pagination = $this->capturedViewData['summary_pagination'] ?? null;
        $this->assertNotNull($pagination);
        $this->assertSame(1, $pagination['page']);
        $this->assertSame(25, $pagination['per_page']);
        $this->assertSame(4, $pagination['total']);
        $this->assertSame(1, $pagination['last_page']);

        $rows = $this->capturedViewData['diocese_summary_rows'] ?? [];
        $this->assertCount(4, $rows);
    }
}
