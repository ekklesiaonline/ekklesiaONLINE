<?php

use App\Services\ProductionSecurityPosture;
use CodeIgniter\Config\Factories;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\ReflectionHelper;
use CodeIgniter\Test\TestLogger;
use Config\Services;

/**
 * Coverage for the production boot-time security posture guard registered
 * on the pre_system event. The real ENVIRONMENT constant stays 'testing'
 * under PHPUnit, so the environment is passed explicitly — the guard is
 * only ever invoked unqualified from Events.php.
 *
 * @internal
 */
final class ProductionSecurityPostureTest extends CIUnitTestCase
{
    private const FLAG = 'security.disableDeviceVerificationForTesting';
    private const BASE_URL = 'app.baseURL';

    protected function setUp(): void
    {
        parent::setUp();

        Services::reset(true);
        Factories::reset('config');

        // TestLogger history is process-wide static state; clear it so
        // "did not log" assertions stay deterministic.
        ReflectionHelper::setPrivateProperty(TestLogger::class, 'op_logs', []);
    }

    protected function tearDown(): void
    {
        unset($_SERVER[self::FLAG], $_ENV[self::FLAG], $_SERVER[self::BASE_URL], $_ENV[self::BASE_URL]);
        putenv(self::FLAG);
        putenv(self::BASE_URL);

        ReflectionHelper::setPrivateProperty(TestLogger::class, 'op_logs', []);
        Services::reset(true);
        Factories::reset('config');

        parent::tearDown();
    }

    public function testLogsCriticalWhenBypassFlagTruthyInProduction(): void
    {
        $_SERVER[self::FLAG]     = '1';
        $_SERVER[self::BASE_URL] = 'https://app.example.org/';
        Factories::reset('config');

        ProductionSecurityPosture::warnIfMisconfigured('production');

        $this->assertLogContains(
            'critical',
            'disableDeviceVerificationForTesting',
            'A truthy bypass flag in production must log a critical warning.'
        );
    }

    public function testLogsCriticalWhenBaseUrlIsInsecureHttpInProduction(): void
    {
        $_SERVER[self::FLAG]     = '0';
        $_SERVER[self::BASE_URL] = 'http://app.example.org/';
        Factories::reset('config');

        ProductionSecurityPosture::warnIfMisconfigured('production');

        $this->assertLogContains(
            'critical',
            'http://',
            'An http:// baseURL in production must log a critical warning.'
        );
    }

    public function testSilentWhenProductionPostureIsClean(): void
    {
        $_SERVER[self::FLAG]     = '0';
        $_SERVER[self::BASE_URL] = 'https://app.example.org/';
        Factories::reset('config');

        ProductionSecurityPosture::warnIfMisconfigured('production');

        $this->assertFalse(
            TestLogger::didLog('critical', 'Production security posture warning', false),
            'A clean production posture must not log a critical warning.'
        );
    }

    public function testSilentOutsideProductionEvenWhenFlagTruthy(): void
    {
        $_SERVER[self::FLAG]     = '1';
        $_SERVER[self::BASE_URL] = 'http://app.example.org/';
        Factories::reset('config');

        ProductionSecurityPosture::warnIfMisconfigured('testing');
        ProductionSecurityPosture::warnIfMisconfigured('development');

        $this->assertFalse(
            TestLogger::didLog('critical', 'Production security posture warning', false),
            'The guard must not fire outside production.'
        );
    }

    public function testGuardIsWiredIntoPreSystemEvent(): void
    {
        $src = file_get_contents(ROOTPATH . 'app/Config/Events.php');
        $this->assertNotFalse($src);

        $this->assertStringContainsString("Events::on('pre_system'", $src);
        $this->assertStringContainsString('ProductionSecurityPosture::warnIfMisconfigured', $src);
    }
}
