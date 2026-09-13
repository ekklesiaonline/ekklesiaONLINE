<?php

use App\Filters\RequestMonitoringFilter;
use App\Services\MonitoringService;
use CodeIgniter\Database\QueryInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\URI;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

/**
 * @internal
 */
final class MonitoringBehaviorTest extends CIUnitTestCase
{
    private array $originalEnv = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->setEnvValue('monitoring.enabled', 'true');
        $this->setEnvValue('monitoring.fileLogEnabled', 'true');
        $this->setEnvValue('monitoring.slowQueryMs', '100');
        $this->setEnvValue('monitoring.slowRequestMs', '800');

        $this->cleanupMonitoringLog();
        Services::reset(true);

        $auth = new class(config('Auth')) extends Auth {
            public function setAuthenticator(?string $alias = null): self
            {
                return $this;
            }

            public function __call(string $method, array $args)
            {
                if ($method === 'loggedIn') {
                    return false;
                }

                return null;
            }
        };
        Services::injectMock('auth', $auth);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_TIME_FLOAT']);
        $this->cleanupMonitoringLog();
        $this->restoreEnv();
        Services::reset(true);

        parent::tearDown();
    }

    public function testSlowQueryLoggingWritesSanitizedStructuredEntry(): void
    {
        $query = $this->createMock(QueryInterface::class);
        $query->method('getDuration')->with(6)->willReturn('0.150000');
        $query->method('getQuery')->willReturn("SELECT * FROM donation WHERE email = 'alice@example.org' AND amount > 45.50 AND user_id = 99");
        $query->method('isWriteType')->willReturn(false);
        $query->method('hasError')->willReturn(false);

        service('monitoring')->logSlowQuery($query);

        $contents = $this->readMonitoringLog();

        $this->assertStringContainsString('"event":"db.query.slow"', $contents);
        $this->assertStringContainsString('"duration_ms":150', $contents);
        $this->assertStringContainsString('"is_write":false', $contents);
        $this->assertStringContainsString('"sql_hash":', $contents);
        $this->assertStringNotContainsString('alice@example.org', $contents);
        $this->assertStringNotContainsString('45.50', $contents);
        $this->assertStringNotContainsString('99', $contents);
    }

    public function testRequestMonitoringLogsSlowRequestsAndServerErrors(): void
    {
        $filter = new RequestMonitoringFilter();
        $request = $this->createMock(RequestInterface::class);
        $request->method('getMethod')->willReturn('post');
        $request->method('getUri')->willReturn(new URI('https://example.test/follow-up/tasks'));
        $request->method('getIPAddress')->willReturn('127.0.0.1');

        $slowResponse = $this->createMock(ResponseInterface::class);
        $slowResponse->method('getStatusCode')->willReturn(200);

        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true) - 1.2;
        $filter->after($request, $slowResponse);

        $errorResponse = $this->createMock(ResponseInterface::class);
        $errorResponse->method('getStatusCode')->willReturn(503);

        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true);
        $filter->after($request, $errorResponse);

        $contents = $this->readMonitoringLog();

        $this->assertSame(2, substr_count($contents, '"event":"request.monitor"'));
        $this->assertStringContainsString('"status":200', $contents);
        $this->assertStringContainsString('"status":503', $contents);
        $this->assertStringContainsString('"path":"/follow-up/tasks"', $contents);
        $this->assertStringContainsString('"method":"POST"', $contents);
    }

    private function monitoringLogPath(): string
    {
        return WRITEPATH . 'logs/monitoring-' . date('Y-m-d') . '.log';
    }

    private function cleanupMonitoringLog(): void
    {
        $path = $this->monitoringLogPath();
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function readMonitoringLog(): string
    {
        $path = $this->monitoringLogPath();
        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        $this->assertNotFalse($contents);

        return (string) $contents;
    }

    private function setEnvValue(string $key, string $value): void
    {
        if (!array_key_exists($key, $this->originalEnv)) {
            $current = getenv($key);
            $this->originalEnv[$key] = $current === false ? null : $current;
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    private function restoreEnv(): void
    {
        foreach ($this->originalEnv as $key => $value) {
            if ($value === null) {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);
                continue;
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}
