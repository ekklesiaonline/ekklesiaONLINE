<?php

use App\Services\SystemSettingService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Tests\Support\Database\SystemSettingsTestHelper;

/**
 * @internal
 */
final class SystemSettingServiceTest extends CIUnitTestCase
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

        Services::reset();
        SystemSettingsTestHelper::createSystemSettingsTableAndSeed($this->db);
    }

    public function testGetPasswordPolicyReturnsDefaults(): void
    {
        $service = service('systemSettings');
        $policy = $service->getPasswordPolicy();

        $this->assertSame(12, $policy['min_length']);
        $this->assertTrue($policy['require_uppercase']);
        $this->assertTrue($policy['require_lowercase']);
        $this->assertTrue($policy['require_digit']);
        $this->assertTrue($policy['require_special']);
        $this->assertSame('@$!%*?&#^_-', $policy['special_chars']);
    }

    public function testGetLockoutConfigReturnsDefaults(): void
    {
        $service = service('systemSettings');
        $config = $service->getLockoutConfig();

        $this->assertSame(5, $config['max_attempts']);
        $this->assertSame(900, $config['window_seconds']);
        $this->assertSame(1800, $config['duration_seconds']);
    }

    public function testIsMaintenanceModeReturnsFalseByDefault(): void
    {
        $service = service('systemSettings');

        $this->assertFalse($service->isMaintenanceMode());
    }

    public function testIsMaintenanceModeReturnsTrueWhenEnabled(): void
    {
        $service = service('systemSettings');
        $service->set('maintenance.enabled', true, 'boolean', 'system');

        $this->assertTrue($service->isMaintenanceMode());
    }

    public function testIsMfaRequiredForAdminOpsReturnsTrueByDefault(): void
    {
        $service = service('systemSettings');

        $this->assertTrue($service->isMfaRequiredForAdminOps());
    }

    public function testIsMfaRequiredForAdminOpsCanBeDisabled(): void
    {
        $service = service('systemSettings');
        $service->set('mfa.required_for_admin_ops', false, 'boolean', 'security');

        $this->assertFalse($service->isMfaRequiredForAdminOps());
    }

    public function testGetMaintenanceMessageFallsBackToDefault(): void
    {
        $service = service('systemSettings');

        $this->assertStringContainsString('maintenance', strtolower($service->getMaintenanceMessage()));
    }
}
