<?php

use App\Controllers\SystemSettings;
use App\Models\SystemSettingModel;
use CodeIgniter\Config\Factories;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Tests\Support\Database\SystemSettingsTestHelper;

/**
 * @internal
 */
final class SystemSettingsControllerTest extends CIUnitTestCase
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
        service('systemSettings')->set('mfa.required_for_admin_ops', false, 'boolean', 'security');
    }

    protected function tearDown(): void
    {
        Services::reset(true);
        Factories::reset();

        parent::tearDown();
    }

    public function testIndexRedirectsWhenNotAuthenticated(): void
    {
        $controller = $this->makeController('get');
        $response = $controller->index();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(base_url('/'), $response->getHeaderLine('Location'));
    }

    public function testIndexRendersForSystemAdmin(): void
    {
        $userId = $this->insertUser('system.settings.actor', 'system.settings.actor@example.org');
        $this->insertProfile($userId, null, null, 'System', 'Settings');
        $this->injectSystemAdminAuth($userId);

        $controller = $this->makeController('get');
        $response = $controller->index();

        $this->assertIsString($response);
        $this->assertStringContainsString(lang('System.sectionSettings'), $response);
    }

    public function testUpdatePersistsValuesAndWritesAuditEntry(): void
    {
        $userId = $this->insertUser('system.settings.updater', 'system.settings.updater@example.org');
        $this->insertProfile($userId, null, null, 'System', 'Updater');
        $this->injectSystemAdminAuth($userId);

        $controller = $this->makeController('post', [
            'password_policy.min_length' => '20',
            'password_policy.require_uppercase' => '1',
            'password_policy.require_lowercase' => '1',
            'password_policy.require_digit' => '1',
            'password_policy.require_special' => '0',
            'password_policy.special_chars' => '@$!%*?&#^_-',
            'mfa.required_for_admin_ops' => '1',
            'mfa.required_for_primary_transfer' => '1',
            'lockout.max_attempts' => '3',
            'lockout.window_seconds' => '600',
            'lockout.duration_seconds' => '900',
            'maintenance.enabled' => '0',
            'maintenance.message' => '',
        ]);

        $capturedLogs = [];
        $mockAudit = new class($capturedLogs) extends \App\Services\AuditService {
            public function __construct(private array &$captured)
            {
                parent::__construct();
            }

            public function log(string $action, array $payload = []): void
            {
                $this->captured[] = ['action' => $action, 'payload' => $payload];
            }
        };
        Services::injectMock('audit', $mockAudit);

        $response = $controller->update();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(base_url('system/settings'), $response->getHeaderLine('Location'));

        $model = model(SystemSettingModel::class);
        $this->assertSame(20, $model->getValue('password_policy.min_length'));
        $this->assertFalse($model->getValue('password_policy.require_special'));
        $this->assertSame(3, $model->getValue('lockout.max_attempts'));

        $auditEntry = null;
        foreach ($capturedLogs as $log) {
            if ($log['action'] === 'system.settings.updated') {
                $auditEntry = $log;
                break;
            }
        }
        $this->assertNotNull($auditEntry, 'No audit entry was captured for system.settings.updated');
        $this->assertSame($userId, (int) ($auditEntry['payload']['user_id'] ?? 0));
    }

    public function testUpdateRejectsInvalidMinLength(): void
    {
        $userId = $this->insertUser('system.settings.invalid', 'system.settings.invalid@example.org');
        $this->insertProfile($userId, null, null, 'System', 'Invalid');
        $this->injectSystemAdminAuth($userId);

        $controller = $this->makeController('post', [
            'password_policy.min_length' => '0',
        ]);
        $response = $controller->update();

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testUpdatePersistsValuesFromUnderscorePostKeys(): void
    {
        $userId = $this->insertUser('system.settings.underscore', 'system.settings.underscore@example.org');
        $this->insertProfile($userId, null, null, 'System', 'Underscore');
        $this->injectSystemAdminAuth($userId);

        // PHP/HTML convert dotted input names like "password_policy.min_length"
        // to "password_policy_min_length" in $_POST. Ensure the controller still
        // maps them back to the dotted setting keys.
        $controller = $this->makeController('post', [
            'password_policy_min_length' => '20',
            'password_policy_require_uppercase' => '1',
            'password_policy_require_lowercase' => '1',
            'password_policy_require_digit' => '1',
            'password_policy_require_special' => '0',
            'password_policy_special_chars' => '@$!%*?&#^_-',
            'mfa_required_for_admin_ops' => '1',
            'mfa_required_for_primary_transfer' => '1',
            'lockout_max_attempts' => '3',
            'lockout_window_seconds' => '600',
            'lockout_duration_seconds' => '900',
            'maintenance_enabled' => '0',
            'maintenance_message' => '',
        ]);

        $response = $controller->update();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(base_url('system/settings'), $response->getHeaderLine('Location'));

        $model = model(SystemSettingModel::class);
        $this->assertSame(20, $model->getValue('password_policy.min_length'));
        $this->assertFalse($model->getValue('password_policy.require_special'));
        $this->assertSame(3, $model->getValue('lockout.max_attempts'));
    }

    public function testUpdatePersistsUncheckedBooleansAsFalse(): void
    {
        $userId = $this->insertUser('system.settings.unchecked', 'system.settings.unchecked@example.org');
        $this->insertProfile($userId, null, null, 'System', 'Unchecked');
        $this->injectSystemAdminAuth($userId);

        // First set some booleans to true
        $model = model(SystemSettingModel::class);
        $model->setValue('password_policy.require_uppercase', true, 'boolean', 'policy');
        $model->setValue('password_policy.require_lowercase', true, 'boolean', 'policy');
        $model->setValue('mfa.required_for_admin_ops', true, 'boolean', 'security');

        // Submit form with those checkboxes unchecked (absent from POST).
        // Only non-boolean fields are submitted; boolean switches omitted = OFF.
        $controller = $this->makeController('post', [
            'password_policy_min_length' => '12',
            'password_policy_special_chars' => '@$!%*?&#^_-',
            'lockout_max_attempts' => '5',
            'lockout_window_seconds' => '900',
            'lockout_duration_seconds' => '1800',
            'maintenance_message' => '',
        ]);

        $mockAudit = new class() extends \App\Services\AuditService {
            public function __construct() { parent::__construct(); }
            public function log(string $action, array $payload = []): void {}
        };
        Services::injectMock('audit', $mockAudit);

        $response = $controller->update();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertFalse($model->getValue('password_policy.require_uppercase'));
        $this->assertFalse($model->getValue('password_policy.require_lowercase'));
        $this->assertFalse($model->getValue('password_policy.require_digit'));
        $this->assertFalse($model->getValue('password_policy.require_special'));
        $this->assertFalse($model->getValue('mfa.required_for_admin_ops'));
        $this->assertFalse($model->getValue('mfa.required_for_primary_transfer'));
        $this->assertFalse($model->getValue('maintenance.enabled'));
    }

    private function makeController(string $method, array $postData = []): SystemSettings
    {
        $request = service('request');
        $request->setMethod($method);

        if ($postData !== []) {
            $request->setGlobal('post', $postData);
            $request->setGlobal('request', $postData);
        }

        $controller = new SystemSettings();
        $controller->initController($request, service('response'), service('logger'));

        return $controller;
    }

    private function injectSystemAdminAuth(int $userId): void
    {
        $fakeUser = new class($userId) extends User {
            public function __construct(private int $userIdForTest)
            {
                // Avoid parent constructor side-effects; populate only the
                // attributes required by the views/helpers under test.
                $this->attributes['id']       = $userIdForTest;
                $this->attributes['username'] = 'system.admin.' . $userIdForTest;
                $this->attributes['email']      = 'system.admin.' . $userIdForTest . '@example.org';
            }

            public function can(string ...$permissions): bool
            {
                foreach ($permissions as $permission) {
                    if ($permission === 'system.admin') {
                        continue;
                    }

                    return false;
                }

                return true;
            }

            public function inGroup(string ...$groups): bool
            {
                return in_array('systemadmin', $groups, true);
            }
        };

        $auth = new class($fakeUser, $userId) extends Auth {
            public function __construct(private User $fakeUser, private int $userIdForTest)
            {
                // Avoid parent constructor side-effects (authenticator initialization).
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

        Services::injectMock('auth', $auth);
    }

    private function insertUser(string $username, string $email): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table($this->resolveTableName('users'))->insert([
            'username' => $username,
            'email' => $email,
            'active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $userId = (int) $this->db->insertID();
        $this->db->table($this->resolveTableName('auth_identities'))->insert([
            'user_id' => $userId,
            'type' => 'email_password',
            'secret' => $email,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $userId;
    }

    private function insertProfile(int $userId, ?int $provinceId, ?int $dioceseId, string $firstName, string $lastName): void
    {
        $this->db->table($this->resolveTableName('profile'))->insert([
            'user_id' => $userId,
            'province_id' => $provinceId,
            'diocese_id' => $dioceseId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function resolveTableName(string $base): string
    {
        if ($this->db->tableExists($base)) {
            return $base;
        }

        return $this->db->getPrefix() . $base;
    }
}
