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
        $this->ensureParishesTable();
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
            'parish_id' => 999999,
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

    public function testRegisterProceedsWhenParishIsValidAndCreatesUser(): void
    {
        $this->ensureHierarchyTables();

        $this->db->table('parish')->where('id', 42)->delete();
        $this->db->table('diocese')->where('id', 10)->delete();
        $this->db->table('province')->where('id', 1)->delete();

        $this->db->table('province')->insert([
            'id'         => 1,
            'name'       => 'Valid Province',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('diocese')->insert([
            'id'          => 10,
            'province_id' => 1,
            'name'        => 'Valid Diocese',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('parish')->insert([
            'id'         => 42,
            'diocese_id' => 10,
            'name'       => 'Valid Test Parish',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $before = $this->db->table($this->resolveTableName('users'))->countAllResults();

        $request = service('request');
        $request->setMethod('post');
        $postData = [
            'username'          => 'valid.parish',
            'email'             => 'valid.parish@example.org',
            'password'          => 'T7vL!pQz9$rWk2mX',
            'password_confirm'  => 'T7vL!pQz9$rWk2mX',
            'parish_id'         => 42,
        ];
        $request->setGlobal('post', $postData);
        $request->setGlobal('request', $postData);

        $controller = new \App\Controllers\Auth\RegisterController();
        $controller->initController($request, service('response'), service('logger'));
        $result = $controller->registerAction();

        $after = $this->db->table($this->resolveTableName('users'))->countAllResults();

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertSame($before + 1, $after);
    }

    public function testRegisterRejectsWhenParishLacksHierarchyAndDoesNotCreateUser(): void
    {
        $this->ensureHierarchyTables();

        $this->db->table('parish')->insert([
            'id'         => 99,
            'diocese_id' => null,
            'name'       => 'Orphan Parish',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $before = $this->db->table($this->resolveTableName('users'))->countAllResults();

        $request = service('request');
        $request->setMethod('post');
        $postData = [
            'username'         => 'orphan.parish',
            'email'            => 'orphan.parish@example.org',
            'password'         => 'T7vL!pQz9$rWk2mX',
            'password_confirm' => 'T7vL!pQz9$rWk2mX',
            'parish_id'        => 99,
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

    private function ensureParishesTable(): void
    {
        $table = $this->createTableName('parishes');
        if (!$this->tableExists('parishes')) {
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

    private function ensureHierarchyTables(): void
    {
        if (! $this->tableExists('province')) {
            $table = $this->createTableName('province');
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                province_number INTEGER DEFAULT 1,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }
        if (! $this->tableExists('diocese')) {
            $table = $this->createTableName('diocese');
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                province_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
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
