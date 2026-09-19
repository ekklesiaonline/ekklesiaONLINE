<?php

namespace Tests;

use App\Controllers\Health;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

final class HealthControllerTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        Services::reset(true);
        parent::tearDown();
    }

    public function testLivenessReturnsMinimalSafePayload(): void
    {
        $response = $this->controller(false, false, false)->index();
        $payload = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['success', 'status', 'message', 'meta'], array_keys($payload));
        $this->assertTrue($payload['success']);
        $this->assertSame('healthy', $payload['status']);
        $this->assertSame(200, $payload['meta']['http_status']);
        $this->assertStringNotContainsString('version', strtolower($response->getBody()));
        $this->assertStringNotContainsString('path', strtolower($response->getBody()));
    }

    public function testReadinessRejectsUnauthorizedCaller(): void
    {
        $response = $this->controller(false, true, true)->ready();
        $payload = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('HEALTH_READINESS_FORBIDDEN', $payload['error']['code']);
    }

    public function testReadinessReturnsComponentStatesForSystemAdmin(): void
    {
        $response = $this->controller(true, true, true)->ready();
        $payload = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ready', $payload['status']);
        $this->assertSame(['database' => 'ready', 'writable' => 'ready'], $payload['components']);
    }

    public function testReadinessReturnsGeneric503WithoutExceptionDetails(): void
    {
        $response = $this->controller(true, false, true)->ready();
        $payload = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame('HEALTH_READINESS_FAILED', $payload['error']['code']);
        $this->assertSame('unavailable', $payload['error']['details']['components']['database']);
        $this->assertStringNotContainsString('exception', strtolower($response->getBody()));
        $this->assertStringNotContainsString('sql', strtolower($response->getBody()));
    }

    public function testRoutesDeclarePublicAndProtectedHealthEndpoints(): void
    {
        $routes = (string) file_get_contents(ROOTPATH . 'app/Config/Routes.php');
        $filters = (string) file_get_contents(ROOTPATH . 'app/Config/Filters.php');
        $maintenance = (string) file_get_contents(ROOTPATH . 'app/Filters/MaintenanceMode.php');

        $this->assertStringContainsString("get('health', '\\\\App\\\\Controllers\\\\Health::index')", $routes);
        $this->assertStringContainsString("'filter' => ['session', 'permission:system.admin']", $routes);
        $this->assertStringContainsString("'health'", $filters);
        $this->assertStringContainsString("'health'", $maintenance);
    }

    private function controller(bool $authorized, bool $databaseReady, bool $writableReady): Health
    {
        $controller = new class($authorized, $databaseReady, $writableReady) extends Health {
            public function __construct(
                private readonly bool $authorized,
                private readonly bool $databaseAvailable,
                private readonly bool $writableAvailable,
            ) {
            }

            protected function isSystemAdmin(): bool
            {
                return $this->authorized;
            }

            protected function databaseReady(): bool
            {
                return $this->databaseAvailable;
            }

            protected function writableReady(): bool
            {
                return $this->writableAvailable;
            }
        };
        $controller->initController(
            Services::request(),
            Services::response(),
            Services::logger(),
        );

        return $controller;
    }
}
