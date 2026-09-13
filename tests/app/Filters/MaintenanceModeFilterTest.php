<?php

use App\Filters\MaintenanceMode;
use CodeIgniter\Config\Factories;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Tests\Support\Database\SystemSettingsTestHelper;

/**
 * @internal
 */
final class MaintenanceModeFilterTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        SystemSettingsTestHelper::createSystemSettingsTableAndSeed(db_connect());
    }

    protected function setUp(): void
    {
        parent::setUp();

        helper('auth');
        Services::reset(true);
        SystemSettingsTestHelper::createSystemSettingsTableAndSeed($this->db);
    }

    protected function tearDown(): void
    {
        Services::reset(true);
        Factories::reset('config');

        parent::tearDown();
    }

    public function testFilterAllowsRequestWhenMaintenanceModeIsOff(): void
    {
        $filter = new MaintenanceMode();
        $request = $this->makeRequest('dashboard');

        $result = $filter->before($request);

        $this->assertNull($result);
    }

    public function testFilterAllowsAllowedPathDuringMaintenance(): void
    {
        service('systemSettings')->set('maintenance.enabled', true, 'boolean', 'system');

        $filter = new MaintenanceMode();
        $request = $this->makeRequest('login');

        $result = $filter->before($request);

        $this->assertNull($result);
    }

    public function testFilterBlocksUnauthenticatedUserDuringMaintenance(): void
    {
        service('systemSettings')->set('maintenance.enabled', true, 'boolean', 'system');
        $this->injectUnauthenticatedAuth();

        $filter = new MaintenanceMode();
        $request = $this->makeRequest('dashboard');

        $result = $filter->before($request);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(503, $result->getStatusCode());
        $this->assertStringContainsString('maintenance', strtolower((string) $result->getBody()));
    }

    public function testFilterAllowsSystemAdminDuringMaintenance(): void
    {
        $userId = 1;
        $this->injectSystemAdminAuth($userId);
        service('systemSettings')->set('maintenance.enabled', true, 'boolean', 'system');

        $filter = new MaintenanceMode();
        $request = $this->makeRequest('dashboard');

        $result = $filter->before($request);

        $this->assertNull($result);
    }

    public function testFilterBlocksRegularUserDuringMaintenance(): void
    {
        $userId = 2;
        $this->injectRegularUserAuth($userId);
        service('systemSettings')->set('maintenance.enabled', true, 'boolean', 'system');

        $filter = new MaintenanceMode();
        $request = $this->makeRequest('dashboard');

        $result = $filter->before($request);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(503, $result->getStatusCode());
    }

    private function makeRequest(string $path): IncomingRequest
    {
        $config = new \Config\App();
        $uri = new URI('http://example.com/' . $path);
        $request = new IncomingRequest($config, $uri, 'php://input', new UserAgent());
        $request->setMethod('GET');

        Services::injectMock('request', $request);

        return $request;
    }

    private function injectSystemAdminAuth(int $userId): void
    {
        $fakeUser = new class($userId) extends User {
            public function __construct(private int $userIdForTest)
            {
                $this->attributes['id']       = $userIdForTest;
                $this->attributes['username'] = 'sa.' . $userIdForTest;
                $this->attributes['email']      = 'sa.' . $userIdForTest . '@example.org';
            }

            public function can(string ...$permissions): bool
            {
                return in_array('system.admin', $permissions, true);
            }

            public function inGroup(string ...$groups): bool
            {
                return in_array('systemadmin', $groups, true);
            }
        };

        Services::injectMock('auth', $this->makeFakeAuth($fakeUser, $userId));
    }

    private function injectRegularUserAuth(int $userId): void
    {
        $fakeUser = new class($userId) extends User {
            public function __construct(private int $userIdForTest)
            {
                $this->attributes['id']       = $userIdForTest;
                $this->attributes['username'] = 'user.' . $userIdForTest;
                $this->attributes['email']      = 'user.' . $userIdForTest . '@example.org';
            }

            public function can(string ...$permissions): bool
            {
                return false;
            }

            public function inGroup(string ...$groups): bool
            {
                return false;
            }
        };

        Services::injectMock('auth', $this->makeFakeAuth($fakeUser, $userId));
    }

    private function makeFakeAuth(User $fakeUser, int $userId): Auth
    {
        return new class($fakeUser, $userId) extends Auth {
            public function __construct(private User $fakeUser, private int $userIdForTest)
            {
                // Avoid parent constructor side-effects.
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
                return $this->userIdForTest;
            }

            public function user(): ?User
            {
                return $this->fakeUser;
            }
        };
    }

    private function injectUnauthenticatedAuth(): void
    {
        $auth = new class(config('Auth')) extends Auth {
            public function __construct($config)
            {
                // Avoid parent constructor side-effects.
            }

            public function setAuthenticator(?string $alias = null): self
            {
                return $this;
            }

            public function loggedIn(): bool
            {
                return false;
            }

            public function id(): ?int
            {
                return null;
            }

            public function user(): ?User
            {
                return null;
            }
        };

        Services::injectMock('auth', $auth);
    }
}
