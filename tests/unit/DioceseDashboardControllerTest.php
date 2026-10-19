<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\DioceseDashboardDataService;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\SiteURI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Diocese\Controllers\Dashboard as DioceseDashboard;
use Tests\Support\Database\CanonicalTestSchema;

/**
 * @internal
 */
final class DioceseDashboardControllerTest extends CIUnitTestCase
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
            'service_metric',
            'user_registration_request',
        ] as $table) {
            CanonicalTestSchema::reset($this->db, $table);
        }
    }

    private function seedDioceseAndParishes(int $parishCount = 4): void
    {
        $this->db->table('province')->insert(['id' => 1, 'name' => 'Test Province']);
        $this->db->table('diocese')->insert(['id' => 1, 'province_id' => 1, 'name' => 'Test Diocese']);

        for ($i = 1; $i <= $parishCount; $i++) {
            $this->db->table('parish')->insert([
                'id'         => $i,
                'diocese_id' => 1,
                'name'       => "Parish {$i}",
            ]);
        }
    }

    private function seedAdminUser(int $userId = 1): void
    {
        $this->db->table('users')->insert([
            'id'       => $userId,
            'username' => 'diocesanadmin',
            'active'   => 1,
        ]);
        $this->db->table('person')->insert([
            'id'         => $userId,
            'first_name' => 'Diocesan',
            'last_name'  => 'Admin',
            'is_active'  => 1,
        ]);
        $this->db->table('profile')->insert([
            'user_id'    => $userId,
            'person_id'  => $userId,
            'diocese_id' => 1,
            'first_name' => 'Diocesan',
            'last_name'  => 'Admin',
        ]);
        $this->db->table('auth_groups_users')->insert([
            'user_id' => $userId,
            '"group"' => 'dioceseadmin',
        ]);
    }

    private function injectDiocesanAdminAuth(int $userId = 1): void
    {
        $fakeUser = new class($userId) extends User {
            public function __construct(private int $testUserId)
            {
                $this->attributes['id'] = $testUserId;
                $this->attributes['username'] = 'diocesanadmin';
                $this->attributes['email'] = 'diocesanadmin@example.org';
            }

            public function inGroup(string ...$groups): bool
            {
                return in_array('dioceseadmin', $groups, true);
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

    private function makeController(array $getData = []): DioceseDashboard
    {
        $this->injectCapturingRenderer();

        $queryString = $getData === [] ? '' : '?' . http_build_query($getData);
        $request = new IncomingRequest(
            config('App'),
            new SiteURI(config('App'), 'http://ekklesiaonline.local/diocese/dashboard' . $queryString),
            'php://input',
            new UserAgent()
        );
        $request->setMethod('GET');
        Services::injectMock('request', $request);

        $fakeService = new class() extends DioceseDashboardDataService {
            public function getDioceseAttendancePresets(int $dioceseId, ?int $parishId = null): array
            {
                return [
                    'today'    => ['total_attendance' => 0, 'total_services' => 0, 'average_per_service' => 0],
                    'week'     => ['total_attendance' => 0, 'total_services' => 0, 'average_per_service' => 0],
                    'month'    => ['total_attendance' => 0, 'total_services' => 0, 'average_per_service' => 0],
                    'year'     => ['total_attendance' => 0, 'total_services' => 0, 'average_per_service' => 0],
                    'sundays'  => ['total_attendance' => 0, 'total_services' => 0, 'average_per_service' => 0],
                    'weekdays' => ['total_attendance' => 0, 'total_services' => 0, 'average_per_service' => 0],
                ];
            }

            public function getDiocesanSundayAttendanceAverageTrend(
                int $dioceseId,
                string $period = 'this_year',
                ?string $customStartDate = null,
                ?string $customEndDate = null,
                string $granularity = 'month'
            ): array {
                return [
                    'labels'                       => [],
                    'sunday_averages'              => [],
                    'communion_averages'         => [],
                    'sunday_overall_average'     => 0.0,
                    'communion_overall_average'  => 0.0,
                    'has_data'                   => false,
                ];
            }

            public function getRecentNetworkActivity(int $dioceseId, int $limit = 5, int $offset = 0, array $filters = []): array
            {
                return [];
            }

            public function localizeRecentNetworkActivity(array $rows): array
            {
                return $rows;
            }
        };

        $controller = new class() extends DioceseDashboard {
            protected function isDioceseAdmin(): bool
            {
                return true;
            }
        };
        $controller->initController($request, service('response'), service('logger'));

        $reflection = new \ReflectionClass(DioceseDashboard::class);
        $property = $reflection->getProperty('dashboardData');
        $property->setAccessible(true);
        $property->setValue($controller, $fakeService);

        return $controller;
    }

    public function testIndexPassesDefaultPaginationDataToView(): void
    {
        $this->seedDioceseAndParishes(4);
        $this->seedAdminUser();
        $this->injectDiocesanAdminAuth();

        $controller = $this->makeController();
        $controller->index();

        $pagination = $this->capturedViewData['summary_pagination'] ?? null;
        $this->assertNotNull($pagination);
        $this->assertSame(1, $pagination['page']);
        $this->assertSame(10, $pagination['per_page']);
        $this->assertSame(4, $pagination['total']);
        $this->assertSame(1, $pagination['last_page']);

        $rows = $this->capturedViewData['parish_summary_rows'] ?? [];
        $this->assertCount(4, $rows);
    }
}
