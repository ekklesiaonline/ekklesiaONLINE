<?php

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * @internal
 */
final class RegisterParishHardStopIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    protected function setUp(): void
    {
        parent::setUp();
        helper('auth');
        Services::reset(true);
        $this->ensureUsersTable();
        $this->ensureChurchesTable();
        $this->ensureSettingsTable();
    }

    protected function tearDown(): void
    {
        Services::reset(true);
        parent::tearDown();
    }

    public function testRegisterRejectsWhenParishIsMissingAndDoesNotCreateUser(): void
    {
        $before = $this->db->table($this->resolveTableName('users'))->countAllResults();

        $request = service('request');
        $request->setMethod('post');
        $postData = [
            'email' => 'missing.parish@example.org',
            'password' => 'Passw0rd!234',
            'password_confirm' => 'Passw0rd!234',
        ];
        $request->setGlobal('post', $postData);
        $request->setGlobal('request', $postData);

        $controller = new \App\Controllers\Auth\RegisterController();
        $controller->initController($request, service('response'), service('logger'));
        $result = $controller->registerAction();

        $after = $this->db->table($this->resolveTableName('users'))->countAllResults();

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertSame($before, $after);
    }

    public function testRegisterRejectsWhenParishIsInvalidAndDoesNotCreateUser(): void
    {
        $before = $this->db->table($this->resolveTableName('users'))->countAllResults();

        $request = service('request');
        $request->setMethod('post');
        $postData = [
            'email' => 'invalid.parish@example.org',
            'password' => 'Passw0rd!234',
            'password_confirm' => 'Passw0rd!234',
            'church_id' => 999999,
        ];
        $request->setGlobal('post', $postData);
        $request->setGlobal('request', $postData);

        $controller = new \App\Controllers\Auth\RegisterController();
        $controller->initController($request, service('response'), service('logger'));
        $result = $controller->registerAction();

        $after = $this->db->table($this->resolveTableName('users'))->countAllResults();

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertSame($before, $after);
    }

    private function ensureUsersTable(): void
    {
        $table = $this->createTableName('users');
        if (!$this->tableExists('users')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NULL,
                email TEXT NULL,
                active INTEGER DEFAULT 1,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }
    }

    private function tableExists(string $table): bool
    {
        return $this->db->tableExists($table) || $this->db->tableExists($this->db->getPrefix() . $table);
    }

    private function ensureSettingsTable(): void
    {
        $table = $this->createTableName('settings');
        if (!$this->tableExists('settings')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
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

    private function ensureChurchesTable(): void
    {
        $table = $this->createTableName('churches');
        if (!$this->tableExists('churches')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                diocese_id INTEGER NULL,
                parish_ueid TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }
    }

    private function resolveTableName(string $base): string
    {
        if ($this->db->tableExists($base)) {
            return $base;
        }

        return $this->createTableName($base);
    }

    private function createTableName(string $base): string
    {
        return $this->db->getPrefix() . $base;
    }
}
