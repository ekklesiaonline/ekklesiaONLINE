<?php

use App\Models\AuditLogModel;
use App\Services\AuditService;
use App\Services\MonitoringService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * @internal
 */
final class AuditServiceRedactionTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    private array $originalEnv = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->setEnvValue('monitoring.enabled', 'true');
        $this->setEnvValue('monitoring.fileLogEnabled', 'true');
        $this->setEnvValue('monitoring.auditDbEnabled', 'true');
        $this->setEnvValue('monitoring.auditLogEnabled', 'true');

        $this->cleanupMonitoringLog();
        Services::reset(true);
        $this->rebuildAuditSchema();
    }

    protected function tearDown(): void
    {
        $this->cleanupMonitoringLog();
        $this->restoreEnv();
        Services::reset(true);

        parent::tearDown();
    }

    public function testAuditServiceRedactsSensitiveFieldsBeforePersistingAndLogging(): void
    {
        $service = new AuditService(model(AuditLogModel::class), new MonitoringService());

        $service->log('donation.create', [
            'user_id' => 5,
            'parish_id' => 8,
            'entity_type' => 'donation',
            'entity_id' => 21,
            'old_values' => [
                'amount' => '100.00',
                'notes' => 'Old pastoral note',
            ],
            'new_values' => [
                'amount' => '250.00',
                'email' => 'donor@example.org',
                'phone' => '809-555-9911',
                'address' => '1 Faith Avenue',
                'notes' => 'Private giving note',
                'pledge_total' => '900.00',
                'category' => 'offering',
            ],
            'meta' => [
                'viewer_email' => 'auditor@example.org',
                'phone_last4' => '9911',
                'category' => 'offering',
            ],
        ]);

        $row = $this->db->table('audit_log')->where('action', 'donation.create')->get()->getRowArray();

        $this->assertNotNull($row);
        $this->assertSame(5, (int) ($row['user_id'] ?? 0));
        $this->assertSame(8, (int) ($row['parish_id'] ?? 0));

        $oldValues = json_decode((string) $row['old_values'], true, 512, JSON_THROW_ON_ERROR);
        $newValues = json_decode((string) $row['new_values'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('[REDACTED]', $oldValues['amount']);
        $this->assertSame('[REDACTED]', $oldValues['notes']);
        $this->assertSame('[REDACTED]', $newValues['amount']);
        $this->assertSame('[REDACTED]', $newValues['email']);
        $this->assertSame('[REDACTED]', $newValues['phone']);
        $this->assertSame('[REDACTED]', $newValues['address']);
        $this->assertSame('[REDACTED]', $newValues['notes']);
        $this->assertSame('[REDACTED]', $newValues['pledge_total']);
        $this->assertSame('offering', $newValues['category']);

        $monitorContents = $this->readMonitoringLog();

        $this->assertStringContainsString('"event":"audit.log"', $monitorContents);
        $this->assertStringContainsString('"action":"donation.create"', $monitorContents);
        $this->assertStringContainsString('"viewer_email":"[REDACTED]"', $monitorContents);
        $this->assertStringContainsString('"phone_last4":"[REDACTED]"', $monitorContents);
        $this->assertStringContainsString('"category":"offering"', $monitorContents);
        $this->assertStringNotContainsString('donor@example.org', $monitorContents);
        $this->assertStringNotContainsString('Private giving note', $monitorContents);
    }

    private function rebuildAuditSchema(): void
    {
        $forge = \Config\Database::forge();

        $forge->dropTable('audit_log', true);

        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'user_id' => ['type' => 'INTEGER', 'null' => true],
            'parish_id' => ['type' => 'INTEGER', 'null' => true],
            'action' => ['type' => 'VARCHAR', 'constraint' => 120],
            'entity_type' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'entity_id' => ['type' => 'INTEGER', 'null' => true],
            'old_values' => ['type' => 'TEXT', 'null' => true],
            'new_values' => ['type' => 'TEXT', 'null' => true],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'user_agent' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('audit_log', true);
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
