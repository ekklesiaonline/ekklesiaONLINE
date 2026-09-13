<?php

use App\Controllers\SystemDashboard;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * @internal
 */
final class SystemAdminSessionIsolationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        Services::reset(true);
        $this->ensureSettingsTableExists();
    }

    protected function tearDown(): void
    {
        Services::reset(true);
        parent::tearDown();
    }

    public function testTargetTestFileHasNoLeadingOutputBytes(): void
    {
        $path = ROOTPATH . 'tests/database/SystemAdminCreateAdminUiIntegrationTest.php';
        $contents = (string) file_get_contents($path);

        $this->assertNotSame('', $contents, 'Target test file is empty');
        $this->assertStringStartsWith('<?php', $contents, 'Target test file has bytes before <?php (likely BOM/whitespace)');
    }

    public function testSessionServiceInitializesWithoutHeadersSent(): void
    {
        $this->assertFalse(headers_sent($file, $line), 'Headers already sent at ' . $file . ':' . $line);

        $session = service('session');

        $this->assertNotNull($session);
        $this->assertFalse(headers_sent($file2, $line2), 'Headers sent after session init at ' . $file2 . ':' . $line2);
    }

    public function testSystemAdminControllerInitDoesNotEmitOutput(): void
    {
        $request = service('request');
        $response = service('response');
        $logger = service('logger');

        $controller = new SystemDashboard();
        $controller->initController($request, $response, $logger);

        $this->assertFalse(headers_sent($file, $line), 'Headers sent during controller init at ' . $file . ':' . $line);
    }

    private function ensureSettingsTableExists(): void
    {
        $prefixed = $this->db->getPrefix() . 'settings';

        if (!$this->db->tableExists($prefixed)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefixed . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                class TEXT NOT NULL,
                key TEXT NOT NULL,
                value TEXT NULL,
                type TEXT NULL,
                context TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }

        if (!$this->db->tableExists('settings')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                class TEXT NOT NULL,
                key TEXT NOT NULL,
                value TEXT NULL,
                type TEXT NULL,
                context TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }
    }
}
