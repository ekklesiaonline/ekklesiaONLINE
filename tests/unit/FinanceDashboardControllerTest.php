<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\SiteURI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Diocese\Controllers\FinanceDashboard;
use Tests\Support\Database\CanonicalTestSchema;

/**
 * @internal
 */
final class FinanceDashboardControllerTest extends CIUnitTestCase
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
            'donation',
            'expense',
        ] as $table) {
            CanonicalTestSchema::reset($this->db, $table);
        }
    }

    private function seedDioceseAndParishes(int $parishCount = 12): void
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

    private function makeController(array $getData = []): FinanceDashboard
    {
        $this->injectCapturingRenderer();

        $queryString = $getData === [] ? '' : '?' . http_build_query($getData);
        $request = new IncomingRequest(
            config('App'),
            new SiteURI(config('App'), 'http://ekklesiaonline.local/diocese/finance' . $queryString),
            'php://input',
            new UserAgent()
        );
        $request->setMethod('GET');
        Services::injectMock('request', $request);

        $controller = new class() extends FinanceDashboard {
            protected function isDioceseAdmin(): bool
            {
                return true;
            }

            protected function resolveDiocesanScopeId(): int
            {
                return 1;
            }
        };
        $controller->initController($request, service('response'), service('logger'));

        return $controller;
    }

    public function testIndexPaginatesParishComparisonWithDefaultTen(): void
    {
        $this->seedDioceseAndParishes(12);
        $this->seedAdminUser();
        $this->injectDiocesanAdminAuth();

        $controller = $this->makeController();
        $controller->index();

        $pagination = $this->capturedViewData['summary_pagination'] ?? null;
        $this->assertNotNull($pagination);
        $this->assertSame(1, $pagination['page']);
        $this->assertSame(10, $pagination['per_page']);
        $this->assertSame(12, $pagination['total']);
        $this->assertSame(2, $pagination['last_page']);

        $pagedParishes = $this->capturedViewData['paged_parishes'] ?? [];
        $this->assertCount(10, $pagedParishes);

        $allParishes = $this->capturedViewData['all_parishes'] ?? [];
        $this->assertCount(12, $allParishes);
    }

}
