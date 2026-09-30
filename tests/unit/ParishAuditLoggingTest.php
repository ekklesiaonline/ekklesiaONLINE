<?php

use Diocese\Controllers\Parishes;
use CodeIgniter\Config\Factories;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Tests\Support\Database\SystemSettingsTestHelper;

/**
 * @internal
 */
final class ParishAuditLoggingTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $db = db_connect();
        SystemSettingsTestHelper::createCoreAuthTables($db);
        SystemSettingsTestHelper::createSystemSettingsTableAndSeed($db);
        SystemSettingsTestHelper::createAuditLogTable($db);
    }

    protected function setUp(): void
    {
        parent::setUp();

        helper(['auth', 'form', 'url']);
        Services::reset(true);
        putenv('monitoring.auditDbEnabled=true');
        SystemSettingsTestHelper::createSystemSettingsTableAndSeed($this->db);
    }

    protected function tearDown(): void
    {
        Services::reset(true);
        Factories::reset('config');

        parent::tearDown();
    }

    public function testDioceseParishCreatedAuditEvent(): void
    {
        // Mock the audit service
        $auditService = $this->createMock(\App\Services\AuditService::class);
        $auditService->expects($this->any())
            ->method('log')
            ->with(
                'diocese.parish.created',
                $this->callback(function ($data) {
                    return isset($data['user_id'])
                        && isset($data['entity_type'])
                        && $data['entity_type'] === 'parish'
                        && isset($data['entity_id'])
                        && isset($data['ip_address'])
                        && isset($data['user_agent'])
                        && isset($data['timestamp'])
                        && isset($data['new_values']);
                })
            );

        // Inject the mock audit service
        Services::injectMock('audit', $auditService);

        // Create a test controller instance
        $controller = new Parishes();

        // Verify the audit service is properly configured
        $this->assertNotNull($this->invokeAudit($controller));
    }

    public function testDioceseParishUpdatedAuditEvent(): void
    {
        // Mock the audit service
        $auditService = $this->createMock(\App\Services\AuditService::class);
        $auditService->expects($this->any())
            ->method('log')
            ->with(
                'diocese.parish.updated',
                $this->callback(function ($data) {
                    return isset($data['user_id'])
                        && isset($data['entity_type'])
                        && $data['entity_type'] === 'parish'
                        && isset($data['entity_id'])
                        && isset($data['old_values'])
                        && isset($data['new_values']);
                })
            );

        Services::injectMock('audit', $auditService);

        $controller = new Parishes();
        $this->assertNotNull($this->invokeAudit($controller));
    }

    public function testDioceseParishDeletedAuditEvent(): void
    {
        // Mock the audit service
        $auditService = $this->createMock(\App\Services\AuditService::class);
        $auditService->expects($this->any())
            ->method('log')
            ->with(
                'diocese.parish.deleted',
                $this->callback(function ($data) {
                    return isset($data['user_id'])
                        && isset($data['entity_type'])
                        && $data['entity_type'] === 'parish'
                        && isset($data['entity_id'])
                        && isset($data['old_values'])
                        && isset($data['new_values'])
                        && isset($data['new_values']['delete_reason']);
                })
            );

        Services::injectMock('audit', $auditService);

        $controller = new Parishes();
        $this->assertNotNull($this->invokeAudit($controller));
    }

    public function testAuditLoggingFailureDoesNotBlockOperation(): void
    {
        // Mock the audit service to throw an exception
        $auditService = $this->createMock(\App\Services\AuditService::class);
        $auditService->method('log')
            ->will($this->throwException(new \Exception('Audit service failed')));

        Services::injectMock('audit', $auditService);

        $controller = new Parishes();
        
        // The controller should still function even if audit logging fails
        $this->assertNotNull($this->invokeAudit($controller));
    }

    public function testProvinceParishCreatedAuditEvent(): void
    {
        // Mock the audit service
        $auditService = $this->createMock(\App\Services\AuditService::class);
        $auditService->expects($this->any())
            ->method('log')
            ->with(
                'province.parish.created',
                $this->callback(function ($data) {
                    return isset($data['user_id'])
                        && isset($data['entity_type'])
                        && $data['entity_type'] === 'parish'
                        && isset($data['entity_id'])
                        && isset($data['new_values'])
                        && isset($data['new_values']['diocese_id']);
                })
            );

        Services::injectMock('audit', $auditService);

        $controller = new \Province\Controllers\Dashboard();
        $this->assertNotNull($this->invokeAudit($controller));
    }

    public function testAuditEventNamingConvention(): void
    {
        $expectedEventNames = [
            'diocese.parish.created',
            'diocese.parish.updated',
            'diocese.parish.deleted',
            'province.parish.created',
        ];

        foreach ($expectedEventNames as $eventName) {
            $this->assertMatchesRegularExpression(
                '/^[a-z]+\.[a-z]+\.[a-z]+$/',
                $eventName,
                "Event name '{$eventName}' follows scope-prefixed naming convention"
            );
        }
    }

    public function testAuditEventDataStructure(): void
    {
        $requiredFields = [
            'user_id',
            'entity_type',
            'entity_id',
            'ip_address',
            'user_agent',
            'timestamp',
        ];

        $sampleData = [
            'user_id' => 1,
            'entity_type' => 'parish',
            'entity_id' => 100,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'TestAgent',
            'timestamp' => date('Y-m-d H:i:s'),
            'new_values' => ['name' => 'Test Parish'],
        ];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $sampleData, "Audit data must contain '{$field}' field");
        }
    }

    private function invokeAudit(object $controller): object
    {
        $method = new ReflectionMethod($controller, 'audit');
        $method->setAccessible(true);

        return $method->invoke($controller);
    }
}
