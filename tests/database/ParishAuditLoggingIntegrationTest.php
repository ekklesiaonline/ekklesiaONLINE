<?php

use Diocesan\Controllers\Parishes;
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
final class ParishAuditLoggingIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    protected function setUp(): void
    {
        parent::setUp();

        helper(['auth', 'form', 'url']);
        Services::reset(true);
        cache()->clean();
        $this->ensureCoreTablesExist();
        service('systemSettings')->set('mfa.required_for_admin_ops', false, 'boolean', 'security');
    }

    protected function tearDown(): void
    {
        Services::reset(true);

        parent::tearDown();
    }

    public function testDioceseParishCreationLogsAuditEvent(): void
    {
        // Setup: Create a diocese admin user
        $actorUserId = $this->insertUser('diocese.admin.actor', 'diocese.admin.actor@example.org');
        $provinceId = $this->insertProvince('Test Province');
        $dioceseId = $this->insertDiocese($provinceId, 'Test Diocese');
        $this->insertProfile($actorUserId, null, $dioceseId, 'Diocese', 'Admin');
        $this->injectDioceseAdminAuth($actorUserId);

        // Create parish via controller
        $postData = [
            'name' => 'Test Parish',
            'diocese_id' => $dioceseId,
            'email' => 'test@parish.org',
            'phone' => '555-1234',
        ];

        $request = service('request');
        $request->setMethod('post');
        $request->setGlobal('post', $postData);
        $request->setGlobal('request', $postData);
        $controller = new Parishes();
        $controller->initController($request, service('response'), service('logger'));
        $response = $controller->store();

        // Verify parish was created
        $this->assertNotFalse($response);

        // Verify audit event was logged
        $auditLog = $this->db->table('audit_log')
            ->where('action', 'diocese.parish.created')
            ->where('user_id', $actorUserId)
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();

        $this->assertNotNull($auditLog, 'Audit event should be logged for parish creation');
        $this->assertEquals('parish', $auditLog['entity_type']);
        $this->assertIsArray(json_decode($auditLog['new_values'], true));
    }

    public function testDioceseParishUpdateLogsAuditEvent(): void
    {
        // Setup: Create parish and diocese admin
        $actorUserId = $this->insertUser('diocese.admin.update', 'diocese.admin.update@example.org');
        $provinceId = $this->insertProvince('Update Province');
        $dioceseId = $this->insertDiocese($provinceId, 'Update Diocese');
        $parishId = $this->insertParish($dioceseId, 'Original Parish Name');
        $this->insertProfile($actorUserId, null, $dioceseId, 'Diocese', 'Admin');
        $this->injectDioceseAdminAuth($actorUserId);

        // Update parish via controller
        $postData = [
            'email' => 'updated@parish.org',
            'phone' => '555-5678',
        ];

        $request = service('request');
        $request->setMethod('post');
        $request->setGlobal('post', $postData);
        $request->setGlobal('request', $postData);
        $controller = new Parishes();
        $controller->initController($request, service('response'), service('logger'));
        $response = $controller->update($parishId);

        // Verify audit event was logged
        $auditLog = $this->db->table('audit_log')
            ->where('action', 'diocese.parish.updated')
            ->where('user_id', $actorUserId)
            ->where('entity_id', $parishId)
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();

        $this->assertNotNull($auditLog, 'Audit event should be logged for parish update');
        $this->assertEquals('parish', $auditLog['entity_type']);
        $this->assertEquals($parishId, $auditLog['entity_id']);
        
        $newValues = json_decode($auditLog['new_values'], true);
        $oldValues = json_decode($auditLog['old_values'], true);
        
        $this->assertIsArray($newValues);
        $this->assertIsArray($oldValues);
        $this->assertEquals('Original Parish Name', $oldValues['name']);
    }

    public function testDioceseParishDeletionLogsAuditEvent(): void
    {
        // Setup: Create parish and diocese admin
        $actorUserId = $this->insertUser('diocese.admin.delete', 'diocese.admin.delete@example.org');
        $provinceId = $this->insertProvince('Delete Province');
        $dioceseId = $this->insertDiocese($provinceId, 'Delete Diocese');
        $homeParishId = $this->insertParish($dioceseId, 'Home Parish');
        $parishId = $this->insertParish($dioceseId, 'Parish To Delete');
        $this->insertProfile($actorUserId, null, $dioceseId, 'Diocese', 'Admin');
        $this->injectDioceseAdminAuth($actorUserId);
        session()->set('selected_parish_id', $homeParishId);

        // Delete parish via controller
        $postData = [];
        $request = service('request');
        $request->setMethod('post');
        $request->setGlobal('post', $postData);
        $request->setGlobal('request', $postData);
        $controller = new Parishes();
        $controller->initController($request, service('response'), service('logger'));
        $response = $controller->delete($parishId);

        // Verify audit event was logged
        $auditLog = $this->db->table('audit_log')
            ->where('action', 'diocese.parish.deleted')
            ->where('user_id', $actorUserId)
            ->where('entity_id', $parishId)
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();

        $this->assertNotNull($auditLog, 'Audit event should be logged for parish deletion');
        $this->assertEquals('parish', $auditLog['entity_type']);
        $this->assertEquals($parishId, $auditLog['entity_id']);
        
        $oldValues = json_decode($auditLog['old_values'], true);
        $this->assertEquals('Parish To Delete', $oldValues['name']);
        
        $newValues = json_decode($auditLog['new_values'], true);
        $this->assertEquals('individual_delete', $newValues['delete_reason']);
    }

    public function testProvinceParishCreationLogsAuditEvent(): void
    {
        // Setup: Create a diocesan admin user
        $actorUserId = $this->insertUser('diocese.admin.actor', 'diocese.admin.actor@example.org');
        $provinceId = $this->insertProvince('Province Test');
        $dioceseId = $this->insertDiocese($provinceId, 'Province Diocese');
        $this->insertProfile($actorUserId, $provinceId, $dioceseId, 'Province', 'Admin');
        $this->injectDioceseAdminAuth($actorUserId);

        // Create parish via province dashboard
        $postData = [
            'name' => 'Province Parish',
            'diocese_id' => $dioceseId,
            'email' => 'province@parish.org',
        ];

        $request = service('request');
        $request->setMethod('post');
        $request->setGlobal('post', $postData);
        $request->setGlobal('request', $postData);
        $controller = new \Province\Controllers\Dashboard();
        $controller->initController($request, service('response'), service('logger'));
        $response = $controller->quickCreateParish();

        // Verify audit event was logged
        $auditLog = $this->db->table('audit_log')
            ->where('action', 'province.parish.created')
            ->where('user_id', $actorUserId)
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();

        $this->assertNotNull($auditLog, 'Audit event should be logged for province parish creation');
        $this->assertEquals('parish', $auditLog['entity_type']);
        $this->assertEquals($dioceseId, json_decode($auditLog['new_values'], true)['diocese_id']);
    }

    public function testAuditEventsContainRequiredFields(): void
    {
        // Setup
        $actorUserId = $this->insertUser('diocese.admin.fields', 'diocese.admin.fields@example.org');
        $provinceId = $this->insertProvince('Fields Province');
        $dioceseId = $this->insertDiocese($provinceId, 'Fields Diocese');
        $this->insertProfile($actorUserId, null, $dioceseId, 'Diocese', 'Admin');
        $this->injectDioceseAdminAuth($actorUserId);

        // Create parish
        $postData = [
            'name' => 'Fields Test Parish',
            'diocese_id' => $dioceseId,
        ];

        $request = service('request');
        $request->setMethod('post');
        $request->setGlobal('post', $postData);
        $request->setGlobal('request', $postData);
        $controller = new Parishes();
        $controller->initController($request, service('response'), service('logger'));
        $controller->store();

        // Verify audit event structure
        $auditLog = $this->db->table('audit_log')
            ->where('action', 'diocese.parish.created')
            ->where('user_id', $actorUserId)
            ->get()
            ->getRowArray();

        $requiredFields = ['user_id', 'entity_type', 'entity_id', 'ip_address', 'user_agent', 'created_at', 'new_values'];
        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $auditLog, "Audit log must contain '{$field}' field");
        }
    }

    // Helper methods
    private function ensureCoreTablesExist(): void
    {
        $db = db_connect();
        SystemSettingsTestHelper::createCoreAuthTables($db);
        SystemSettingsTestHelper::createSystemSettingsTableAndSeed($db);
        SystemSettingsTestHelper::createAuditLogTable($db);
    }

    private function insertUser(string $username, string $email): int
    {
        $users = auth()->getProvider();
        $user = new User([
            'username' => $username,
            'email' => $email,
            'password' => 'password123',
        ]);
        $users->save($user);
        return $users->getInsertID();
    }

    private function insertProfile(int $userId, ?int $provinceId, ?int $dioceseId, string $firstName, string $lastName): void
    {
        $this->db->table('profile')->insert([
            'user_id' => $userId,
            'province_id' => $provinceId,
            'diocese_id' => $dioceseId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'membership_status' => 'active',
        ]);
    }

    private function insertProvince(string $name): int
    {
        $this->db->table('province')->insert([
            'province_number' => rand(1, 99),
            'name' => $name,
            'sort_order' => 0,
        ]);
        return $this->db->insertID();
    }

    private function insertDiocese(int $provinceId, string $name): int
    {
        $this->db->table('diocese')->insert([
            'province_id' => $provinceId,
            'name' => $name,
        ]);
        return $this->db->insertID();
    }

    private function insertParish(int $dioceseId, string $name): int
    {
        $this->db->table('parish')->insert([
            'diocese_id' => $dioceseId,
            'name' => $name,
        ]);
        return $this->db->insertID();
    }

    private function injectDioceseAdminAuth(int $userId): void
    {
        $this->db->table('auth_groups_users')->insert([
            'user_id' => $userId,
            'group' => 'dioceseadmin',
        ]);
        auth()->loginById($userId);
    }

    private function injectProvinceAdminAuth(int $userId): void
    {
        $this->db->table('auth_groups_users')->insert([
            'user_id' => $userId,
            'group' => 'provinceadmin',
        ]);
        auth()->loginById($userId);
    }
}
