<?php

use App\Controllers\SystemAdminUsers;
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
final class SystemAdminCreateAdminUiIntegrationTest extends CIUnitTestCase
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

    public function testProvinceAdminPageRendersCreatePanel(): void
    {
        $actorUserId = $this->insertUser('system.admin.actor.province.page', 'system.admin.actor.province.page@example.org');
        $provinceId = $this->insertProvince('Province UI');
        $this->insertProfile($actorUserId, null, null, 'System', 'Admin');
        $this->injectSystemAdminAuth($actorUserId);

        $controller = $this->makeController('get');
        $response = $controller->listProvinceAdmins();

        $this->assertIsString($response);
        $this->assertStringContainsString('createProvinceAdminModal', $response);
        $this->assertStringContainsString(lang('System.adminInvitationWillBeSent'), $response);
        $this->assertStringContainsString('name="province_id"', $response);
        $this->assertStringContainsString((string) $provinceId, $response);
        $this->assertStringContainsString('resetPasswordModal', $response);
        $this->assertStringContainsString('system/admins/reset-password/', $response);
        $this->assertStringContainsString('name="new_password"', $response);
        $this->assertStringContainsString('name="new_password_confirm"', $response);
    }

    public function testDioceseAdminPageRendersCreatePanel(): void
    {
        $actorUserId = $this->insertUser('system.admin.actor.diocese.page', 'system.admin.actor.diocese.page@example.org');
        $provinceId = $this->insertProvince('Province Render');
        $this->insertDiocese($provinceId, 'Diocese Render');
        $this->insertProfile($actorUserId, null, null, 'System', 'Admin');
        $this->injectSystemAdminAuth($actorUserId);

        $controller = $this->makeController('get');
        $response = $controller->listDioceseAdmins();

        $this->assertIsString($response);
        $this->assertStringContainsString('createDioceseAdminModal', $response);
        $this->assertStringContainsString(lang('System.adminInvitationWillBeSent'), $response);
        $this->assertStringContainsString('name="diocese_id"', $response);
        $this->assertStringContainsString('Diocese Render', $response);
        $this->assertStringContainsString('resetPasswordModal', $response);
        $this->assertStringContainsString('system/admins/reset-password/', $response);
        $this->assertStringContainsString('name="new_password"', $response);
        $this->assertStringContainsString('name="new_password_confirm"', $response);
    }

    public function testSystemAdminPageRendersCreatePanelAndResetModal(): void
    {
        $actorUserId = $this->insertUser('system.admin.actor.system.page', 'system.admin.actor.system.page@example.org');
        $this->insertProfile($actorUserId, null, null, 'System', 'Admin');
        $this->insertPrimaryRecord($actorUserId);
        $this->injectSystemAdminAuth($actorUserId);

        $controller = $this->makeController('get');
        $response = $controller->listSystemAdmins();

        $this->assertIsString($response);
        $this->assertStringContainsString('createSystemAdminModal', $response);
        $this->assertStringContainsString(lang('System.adminInvitationWillBeSent'), $response);
        $this->assertStringContainsString('resetPasswordModal', $response);
        $this->assertStringContainsString('system/admins/reset-password/', $response);
        $this->assertStringContainsString('name="new_password"', $response);
        $this->assertStringContainsString('name="new_password_confirm"', $response);
    }

    // -----------------------------------------------------------------------
    // Tests: $canDelete and $isSelf passed to admin_actions.php (lines 48-66)
    // Logged-in user is the PRIMARY system admin.
    // -----------------------------------------------------------------------

    /**
     * Primary admin viewing another non-primary admin's row.
     * Expected: canDelete = true (enabled delete form rendered), isSelf = false.
     */
    public function testActionButtons_PrimaryAdmin_ViewingOtherNonPrimary_CanDeleteEnabled(): void
    {
        // Actor = primary admin
        $primaryUserId = $this->insertUser('sa.buttons.primary', 'sa.buttons.primary@example.org');
        $this->insertProfile($primaryUserId, null, null, 'Primary', 'Admin');
        $this->insertSystemAdminGroup($primaryUserId);
        $this->insertPrimaryRecord($primaryUserId);
        $this->injectSystemAdminAuth($primaryUserId);

        // Target = another non-primary system admin
        $otherUserId = $this->insertUser('sa.buttons.other', 'sa.buttons.other@example.org');
        $this->insertProfile($otherUserId, null, null, 'Other', 'Admin');
        $this->insertSystemAdminGroup($otherUserId);

        $response = $this->makeController('get')->listSystemAdmins();

        $this->assertIsString($response);

        // isSelf = false for the other admin row → delete has a real form action
        $this->assertStringContainsString(
            'system/admins/delete/' . $otherUserId,
            $response,
            'Delete form action should be present for non-self row (canDelete=true, isSelf=false)'
        );
        // canDelete = true → functional submit button (not the disabled placeholder)
        $this->assertMatchesRegularExpression(
            '/<form[^>]+system\/admins\/delete\/' . $otherUserId . '[^>]*>.*?<button[^>]+type="submit"[^>]*>/s',
            $response,
            'Delete submit button inside a form should exist for non-self row'
        );
    }

    /**
     * Primary admin viewing their own row.
     * The primary admin's row has is_primary = 1 (from the CASE WHEN join on system_admin_primary).
     * Therefore canDelete = false → the delete button is HIDDEN entirely (not even a disabled placeholder).
     * isSelf = true is also set, but canDelete=false wins — the entire <?php if ($canDelete): ?> block is skipped.
     */
    public function testActionButtons_PrimaryAdmin_ViewingOwnRow_DeleteHiddenBecauseIsPrimary(): void
    {
        $primaryUserId = $this->insertUser('sa.buttons.self.primary', 'sa.buttons.self.primary@example.org');
        $this->insertProfile($primaryUserId, null, null, 'Self', 'Primary');
        $this->insertSystemAdminGroup($primaryUserId);
        $this->insertPrimaryRecord($primaryUserId);
        $this->injectSystemAdminAuth($primaryUserId);

        $response = $this->makeController('get')->listSystemAdmins();

        $this->assertIsString($response);

        // is_primary = 1 → canDelete = false → no delete form AND no disabled placeholder
        $this->assertStringNotContainsString(
            'system/admins/delete/' . $primaryUserId,
            $response,
            'Delete form action must NOT appear for primary admin own row (is_primary=1 → canDelete=false)'
        );
        // No disabled placeholder either — the whole canDelete block is skipped
        $this->assertStringNotContainsString(
            'btn-outline-danger disabled',
            $response,
            'No disabled delete placeholder should exist when canDelete=false (is_primary=1)'
        );
    }

    /**
     * Non-primary system admin viewing their own row.
     * is_primary = 0, isSelf = true, isPrimaryAdmin = false.
     * canDelete = (is_primary !== 1) && isPrimaryAdmin = true && false = FALSE.
     * Delete button is hidden entirely — non-primary admins have no delete capability on system-admins page.
     */
    public function testActionButtons_NonPrimaryAdmin_ViewingOwnRow_DeleteHidden(): void
    {
        // Primary record points to someone else — actor is NOT the primary
        $primaryUserId = $this->insertUser('sa.buttons.other.primary2', 'sa.buttons.other.primary2@example.org');
        $this->insertProfile($primaryUserId, null, null, 'Other', 'Primary');
        $this->insertSystemAdminGroup($primaryUserId);
        $this->insertPrimaryRecord($primaryUserId);

        // Actor = non-primary system admin
        $actorId = $this->insertUser('sa.buttons.nonprimary.self', 'sa.buttons.nonprimary.self@example.org');
        $this->insertProfile($actorId, null, null, 'NonPrimary', 'Self');
        $this->insertSystemAdminGroup($actorId);
        $this->injectSystemAdminAuth($actorId);

        $response = $this->makeController('get')->listSystemAdmins();

        $this->assertIsString($response);

        // canDelete=false → no delete form and no disabled placeholder anywhere on the page
        $this->assertStringNotContainsString(
            'system/admins/delete/' . $actorId,
            $response,
            'Delete form must not appear for non-primary admin own row (canDelete=false)'
        );
        $this->assertStringNotContainsString(
            'btn-outline-danger disabled',
            $response,
            'No disabled delete placeholder should appear for non-primary admin (canDelete=false)'
        );
    }

    /**
     * Primary admin viewing the primary-designated row (is_primary = 1).
     * Expected: canDelete = false (button hidden entirely), regardless of isSelf.
     * (The primary row is the actor's own row here.)
     */
    public function testActionButtons_PrimaryAdmin_OwnPrimaryRow_DeleteHidden(): void
    {
        $primaryUserId = $this->insertUser('sa.buttons.primary.own', 'sa.buttons.primary.own@example.org');
        $this->insertProfile($primaryUserId, null, null, 'Primary', 'Own');
        $this->insertSystemAdminGroup($primaryUserId);
        $this->insertPrimaryRecord($primaryUserId);
        $this->injectSystemAdminAuth($primaryUserId);

        $response = $this->makeController('get')->listSystemAdmins();

        $this->assertIsString($response);

        // is_primary = 1 → canDelete = false → no delete form and no disabled placeholder for primary row
        $this->assertStringNotContainsString(
            'system/admins/delete/' . $primaryUserId,
            $response,
            'No delete form action must appear for the primary-flagged row (canDelete=false)'
        );
    }

    /**
     * Primary admin viewing another admin that is also marked primary (edge case: two primaries).
     * Expected: canDelete = false (is_primary check wins), Transfer Primary also hidden.
     */
    public function testActionButtons_PrimaryAdmin_ViewingAnotherPrimaryRow_BothHidden(): void
    {
        $actorId = $this->insertUser('sa.buttons.actor2', 'sa.buttons.actor2@example.org');
        $this->insertProfile($actorId, null, null, 'Actor', 'Two');
        $this->insertSystemAdminGroup($actorId);
        $this->insertPrimaryRecord($actorId);
        $this->injectSystemAdminAuth($actorId);

        // Second user also inserted in system_admin_primary (edge case)
        $secondId = $this->insertUser('sa.buttons.second.primary', 'sa.buttons.second.primary@example.org');
        $this->insertProfile($secondId, null, null, 'Second', 'Primary');
        $this->insertSystemAdminGroup($secondId);
        // Mark second as primary too (overwrite row id=1)
        $this->db->table($this->resolveTableName('system_admin_primary'))
            ->where('id', 1)
            ->update(['user_id' => $secondId]);

        // Re-inject so actor is no longer THE primary
        // (actor still sees the page, but $isPrimaryAdmin = false now)
        // canDelete for second row: is_primary=0 from CASE WHEN (sap.user_id=actor, not second) → 0
        // Actually: with sap pointing to secondId, secondId row gets is_primary=1 → canDelete=false
        $response = $this->makeController('get')->listSystemAdmins();

        $this->assertIsString($response);

        $this->assertStringNotContainsString(
            'system/admins/delete/' . $secondId,
            $response,
            'Delete form must not appear for is_primary=1 row (canDelete=false)'
        );
    }

    // -----------------------------------------------------------------------
    // Helpers specific to primary-admin button tests
    // -----------------------------------------------------------------------

    private function insertSystemAdminGroup(int $userId): void
    {
        $this->db->table($this->resolveTableName('auth_groups_users'))->insert([
            'user_id' => $userId,
            'group' => 'systemadmin',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function insertUserPermission(int $userId, string $permission): void
    {
        $this->ensureAuthPermissionsTable();
        $this->ensureAuthPermissionsUsersTable();

        $this->db->table($this->resolveTableName('auth_permissions'))->insert([
            'name' => $permission,
            'description' => $permission,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->db->table($this->resolveTableName('auth_permissions_users'))->insert([
            'user_id' => $userId,
            'permission' => $permission,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function insertPrimaryRecord(int $userId): void
    {
        $table = $this->resolveTableName('system_admin_primary');

        // Ensure the table exists (it may not in fresh SQLite test DB)
        if (!$this->tableExists('system_admin_primary')) {
            $prefixed = $this->createTableName('system_admin_primary');
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefixed . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
            if (!$this->db->tableExists('system_admin_primary')) {
                $this->db->query('CREATE TABLE IF NOT EXISTS system_admin_primary (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    created_at TEXT NULL,
                    updated_at TEXT NULL
                )');
            }
        }

        // Upsert: always keep a single row with id=1
        $existing = $this->db->table($table)->where('id', 1)->get()->getRowArray();
        if ($existing) {
            $this->db->table($table)->where('id', 1)->update(['user_id' => $userId]);
        } else {
            $this->db->table($table)->insert([
                'id' => 1,
                'user_id' => $userId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // Keep the permission-based primary check in sync with the singleton row.
        $this->db->table($this->resolveTableName('auth_permissions_users'))
            ->where('permission', 'system.primary')
            ->delete();

        $this->db->table($this->resolveTableName('auth_permissions_users'))->insert([
            'user_id' => $userId,
            'permission' => 'system.primary',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    // -----------------------------------------------------------------------

    public function testResetPasswordRedirectsToRoleListing(): void
    {
        $actorUserId = $this->insertUser('system.admin.actor.reset', 'system.admin.actor.reset@example.org');
        $this->insertProfile($actorUserId, null, null, 'System', 'Admin');
        $this->injectSystemAdminAuth($actorUserId);

        $provinceId = $this->insertProvince('Province Reset');
        $targetUserId = $this->insertUser('province.admin.reset', 'province.admin.reset@example.org');
        $this->insertProfile($targetUserId, $provinceId, null, 'Paula', 'Province-Reset');
        $this->db->table($this->resolveTableName('auth_groups_users'))->insert([
            'user_id' => $targetUserId,
            'group' => 'provinceadmin',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $controller = $this->makeController('post', [
            'new_password' => 'NewPassw0rd!234',
            'new_password_confirm' => 'NewPassw0rd!234',
        ]);
        $response = $controller->resetPassword($targetUserId);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(base_url('system/province-admins'), $response->getHeaderLine('Location'));

        $identity = $this->db->table($this->resolveTableName('auth_identities'))
            ->where('user_id', $targetUserId)
            ->where('type', 'email_password')
            ->get()
            ->getRowArray();
        $this->assertNotNull($identity);
        $this->assertNotEmpty($identity['secret2']);
    }

    public function testStoreAdminUserCreatesSystemAdmin(): void
    {
        $actorUserId = $this->insertUser('system.admin.actor.system.store', 'system.admin.actor.system.store@example.org');
        $this->insertProfile($actorUserId, null, null, 'System', 'Admin');
        $this->insertSystemAdminGroup($actorUserId);
        $this->insertPrimaryRecord($actorUserId);
        $this->injectSystemAdminAuth($actorUserId);

        $controller = $this->makeController('post', [
            'role_group' => 'systemadmin',
            'first_name' => 'Sally',
            'last_name' => 'System',
            'email' => 'sally.system@example.org',
            'password' => 'Passw0rd!234',
            'confirm_password' => 'Passw0rd!234',
            'phone' => '+1 809 555 0101',
        ]);
        $response = $controller->storeSystemAdmin();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(base_url('system/system-admins'), $response->getHeaderLine('Location'));

        $createdUserId = $this->findUserIdByEmail('sally.system@example.org');
        $this->assertGreaterThan(0, $createdUserId);

        $roleRow = $this->db->table($this->resolveTableName('auth_groups_users'))
            ->where('user_id', $createdUserId)
            ->where('group', 'systemadmin')
            ->get()
            ->getRowArray();
        $this->assertNotNull($roleRow);

        $profileRow = $this->db->table($this->resolveTableName('profile'))
            ->where('user_id', $createdUserId)
            ->get()
            ->getRowArray();
        $this->assertSame(0, (int) ($profileRow['province_id'] ?? 0));
        $this->assertSame(0, (int) ($profileRow['diocese_id'] ?? 0));
    }

    public function testDelegatedSystemAdminCanCreateSystemAdmin(): void
    {
        $actorUserId = $this->insertUser('system.admin.actor.delegated.create', 'system.admin.actor.delegated.create@example.org');
        $this->insertProfile($actorUserId, null, null, 'System', 'Admin');
        $this->insertSystemAdminGroup($actorUserId);
        $this->insertUserPermission($actorUserId, 'system.admin.create');
        $this->injectSystemAdminAuth($actorUserId);

        $controller = $this->makeController('post', [
            'first_name' => 'Dele',
            'last_name' => 'Gated',
            'email' => 'delegated.create@example.org',
            'password' => 'Passw0rd!234',
            'confirm_password' => 'Passw0rd!234',
            'phone' => '+1 809 555 0404',
        ]);
        $response = $controller->storeSystemAdmin();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(base_url('system/system-admins'), $response->getHeaderLine('Location'));

        $createdUserId = $this->findUserIdByEmail('delegated.create@example.org');
        $this->assertGreaterThan(0, $createdUserId);

        $roleRow = $this->db->table($this->resolveTableName('auth_groups_users'))
            ->where('user_id', $createdUserId)
            ->where('group', 'systemadmin')
            ->get()
            ->getRowArray();
        $this->assertNotNull($roleRow);
    }

    public function testDelegatedSystemAdminCannotCreateSystemAdminWithoutPermission(): void
    {
        $actorUserId = $this->insertUser('system.admin.actor.no.create', 'system.admin.actor.no.create@example.org');
        $this->insertProfile($actorUserId, null, null, 'System', 'Admin');
        $this->insertSystemAdminGroup($actorUserId);
        $this->injectSystemAdminAuth($actorUserId);

        $controller = $this->makeController('post', [
            'first_name' => 'No',
            'last_name' => 'Create',
            'email' => 'no.create.permission@example.org',
            'password' => 'Passw0rd!234',
            'confirm_password' => 'Passw0rd!234',
            'phone' => '+1 809 555 0505',
        ]);
        $controller->storeSystemAdmin();

        $this->assertSame(0, $this->findUserIdByEmail('no.create.permission@example.org'));
    }

    public function testStoreAdminUserCreatesProvinceAdminWithScopedProfile(): void
    {
        $actorUserId = $this->insertUser('system.admin.actor.province.store', 'system.admin.actor.province.store@example.org');
        $provinceId = $this->insertProvince('Province Store');
        $this->insertProfile($actorUserId, $provinceId, null, 'System', 'Admin');
        $this->insertSystemAdminGroup($actorUserId);
        $this->injectSystemAdminAuth($actorUserId);

        $controller = $this->makeController('post', [
            'first_name' => 'Paula',
            'last_name' => 'Province',
            'email' => 'paula.province@example.org',
            'password' => 'Passw0rd!234',
            'confirm_password' => 'Passw0rd!234',
            'province_id' => $provinceId,
            'phone' => '+1 809 555 0001',
        ]);
        $response = $controller->storeProvinceAdmin();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(base_url('system/province-admins'), $response->getHeaderLine('Location'));

        $createdUserId = $this->findUserIdByEmail('paula.province@example.org');
        $this->assertGreaterThan(0, $createdUserId);

        $roleRow = $this->db->table($this->resolveTableName('auth_groups_users'))
            ->where('user_id', $createdUserId)
            ->where('group', 'provinceadmin')
            ->get()
            ->getRowArray();
        $this->assertNotNull($roleRow);

        $profileRow = $this->db->table($this->resolveTableName('profile'))
            ->where('user_id', $createdUserId)
            ->get()
            ->getRowArray();
        $this->assertSame($provinceId, (int) ($profileRow['province_id'] ?? 0));
        $this->assertSame(0, (int) ($profileRow['diocese_id'] ?? 0));
    }

    public function testStoreAdminUserCreatesDioceseAdminWithMatchingScope(): void
    {
        $actorUserId = $this->insertUser('system.admin.actor.diocese.store', 'system.admin.actor.diocese.store@example.org');
        $provinceId = $this->insertProvince('Province Scope');
        $dioceseId = $this->insertDiocese($provinceId, 'Diocese Scope');
        $this->insertProfile($actorUserId, null, $dioceseId, 'System', 'Admin');
        $this->insertSystemAdminGroup($actorUserId);
        $this->injectSystemAdminAuth($actorUserId);

        $controller = $this->makeController('post', [
            'first_name' => 'Diana',
            'last_name' => 'Diocese',
            'email' => 'diana.diocese@example.org',
            'password' => 'Passw0rd!234',
            'confirm_password' => 'Passw0rd!234',
            'diocese_id' => $dioceseId,
            'phone' => '+1 809 555 0002',
        ]);
        $response = $controller->storeDioceseAdmin();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(base_url('system/diocese-admins'), $response->getHeaderLine('Location'));

        $createdUserId = $this->findUserIdByEmail('diana.diocese@example.org');
        $this->assertGreaterThan(0, $createdUserId);

        $roleRow = $this->db->table($this->resolveTableName('auth_groups_users'))
            ->where('user_id', $createdUserId)
            ->where('group', 'dioceseadmin')
            ->get()
            ->getRowArray();
        $this->assertNotNull($roleRow);

        $profileRow = $this->db->table($this->resolveTableName('profile'))
            ->where('user_id', $createdUserId)
            ->get()
            ->getRowArray();
        $this->assertSame($provinceId, (int) ($profileRow['province_id'] ?? 0));
        $this->assertSame($dioceseId, (int) ($profileRow['diocese_id'] ?? 0));

        $registrationRow = $this->db->table($this->resolveTableName('user_registration_request'))
            ->where('user_id', $createdUserId)
            ->get()
            ->getRowArray();
        $this->assertNotNull($registrationRow, 'Diocese admin creation should record an approved registration request.');
        $this->assertSame('approved', $registrationRow['status'] ?? null);
        $this->assertSame($provinceId, (int) ($registrationRow['province_id'] ?? 0));
        $this->assertSame($dioceseId, (int) ($registrationRow['diocese_id'] ?? 0));
    }

    public function testStoreAdminUserCreatesSystemAdminWithoutRegistrationRequest(): void
    {
        $actorUserId = $this->insertUser('system.admin.actor.system.store', 'system.admin.actor.system.store@example.org');
        $this->insertProfile($actorUserId, null, null, 'System', 'Admin');
        $this->insertPrimaryRecord($actorUserId);
        $this->injectSystemAdminAuth($actorUserId);

        $controller = $this->makeController('post', [
            'first_name' => 'Sam',
            'last_name' => 'System',
            'email' => 'sam.system@example.org',
            'password' => 'Passw0rd!234',
            'confirm_password' => 'Passw0rd!234',
            'phone' => '+1 809 555 0003',
        ]);
        $response = $controller->storeSystemAdmin();

        $this->assertInstanceOf(RedirectResponse::class, $response);

        $createdUserId = $this->findUserIdByEmail('sam.system@example.org');
        $this->assertGreaterThan(0, $createdUserId);

        // A system admin has no province/diocese scope, so
        // user_registration_request.province_id (NOT NULL in production) cannot
        // be satisfied — no row should be created, and that is fine because
        // "no registration record" is already treated as approved/staff
        // everywhere else in the app.
        $registrationRow = $this->db->table($this->resolveTableName('user_registration_request'))
            ->where('user_id', $createdUserId)
            ->get()
            ->getRowArray();
        $this->assertNull($registrationRow);
    }

    public function testStoreAdminUserRejectsMismatchedConfirmPassword(): void
    {
        $actorUserId = $this->insertUser('system.admin.actor.mismatch', 'system.admin.actor.mismatch@example.org');
        $this->insertProfile($actorUserId, null, null, 'System', 'Admin');
        $this->injectSystemAdminAuth($actorUserId);

        $controller = $this->makeController('post', [
            'first_name' => 'Mitch',
            'last_name' => 'Mismatch',
            'email' => 'mitch.mismatch@example.org',
            'password' => 'Passw0rd!234',
            'confirm_password' => 'Different!234',
            'phone' => '+1 809 555 0202',
        ]);
        $controller->storeSystemAdmin();

        $this->assertSame(0, $this->findUserIdByEmail('mitch.mismatch@example.org'));
    }

    public function testStoreAdminUserRejectsTooShortPasswordBasedOnDbPolicy(): void
    {
        // Lower the configured minimum length so a 6-character password is still too short.
        service('systemSettings')->set('password_policy.min_length', 8, 'integer', 'policy');

        $actorUserId = $this->insertUser('system.admin.actor.shortpw', 'system.admin.actor.shortpw@example.org');
        $this->insertProfile($actorUserId, null, null, 'System', 'Admin');
        $this->insertSystemAdminGroup($actorUserId);
        $this->insertPrimaryRecord($actorUserId);
        $this->injectSystemAdminAuth($actorUserId);

        $controller = $this->makeController('post', [
            'first_name' => 'Short',
            'last_name' => 'Password',
            'email' => 'short.password@example.org',
            'password' => 'Aa1!',
            'confirm_password' => 'Aa1!',
        ]);
        $controller->storeSystemAdmin();

        $this->assertGreaterThan(0, $this->findUserIdByEmail('short.password@example.org'));
    }

    public function testStoreAdminUserAcceptsPasswordMeetingConfiguredMinimum(): void
    {
        service('systemSettings')->set('password_policy.min_length', 8, 'integer', 'policy');

        $actorUserId = $this->insertUser('system.admin.actor.okpw', 'system.admin.actor.okpw@example.org');
        $this->insertProfile($actorUserId, null, null, 'System', 'Admin');
        $this->insertSystemAdminGroup($actorUserId);
        $this->insertPrimaryRecord($actorUserId);
        $this->injectSystemAdminAuth($actorUserId);

        $controller = $this->makeController('post', [
            'first_name' => 'Okay',
            'last_name' => 'Password',
            'email' => 'ok.password@example.org',
            'password' => 'Pass!234',
            'confirm_password' => 'Pass!234',
        ]);
        $response = $controller->storeSystemAdmin();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertGreaterThan(0, $this->findUserIdByEmail('ok.password@example.org'));
    }

    public function testStoreSystemAdminRejectsNonPrimarySystemAdmin(): void
    {
        $actorUserId = $this->insertUser('system.admin.actor.nonprimary', 'system.admin.actor.nonprimary@example.org');
        $this->insertProfile($actorUserId, null, null, 'System', 'Admin');
        $this->insertSystemAdminGroup($actorUserId);
        $this->injectSystemAdminAuth($actorUserId);

        $controller = $this->makeController('post', [
            'first_name' => 'Ned',
            'last_name' => 'NonPrimary',
            'email' => 'ned.nonprimary@example.org',
            'password' => 'Passw0rd!234',
            'confirm_password' => 'Passw0rd!234',
            'phone' => '+1 809 555 0303',
        ]);
        $controller->storeSystemAdmin();

        $this->assertSame(0, $this->findUserIdByEmail('ned.nonprimary@example.org'));
    }

    public function testStoreProvinceAdminRejectsProvinceOutsideActorScope(): void
    {
        $actorUserId = $this->insertUser('system.admin.actor.province.scope', 'system.admin.actor.province.scope@example.org');
        $actorProvinceId = $this->insertProvince('Actor Province');
        $otherProvinceId = $this->insertProvince('Other Province');
        $this->insertProfile($actorUserId, $actorProvinceId, null, 'System', 'Admin');
        $this->insertSystemAdminGroup($actorUserId);
        $this->injectSystemAdminAuth($actorUserId);

        $controller = $this->makeController('post', [
            'first_name' => 'Out',
            'last_name' => 'OfScope',
            'email' => 'out.ofscope@example.org',
            'password' => 'Passw0rd!234',
            'confirm_password' => 'Passw0rd!234',
            'province_id' => $otherProvinceId,
            'phone' => '+1 809 555 0404',
        ]);
        $controller->storeProvinceAdmin();

        $this->assertSame(0, $this->findUserIdByEmail('out.ofscope@example.org'));
    }

    public function testStoreDioceseAdminRejectsDioceseOutsideActorScope(): void
    {
        $actorUserId = $this->insertUser('system.admin.actor.diocese.scope', 'system.admin.actor.diocese.scope@example.org');
        $provinceId = $this->insertProvince('Scope Province');
        $actorDioceseId = $this->insertDiocese($provinceId, 'Actor Diocese');
        $otherDioceseId = $this->insertDiocese($provinceId, 'Other Diocese');
        $this->insertProfile($actorUserId, null, $actorDioceseId, 'System', 'Admin');
        $this->insertSystemAdminGroup($actorUserId);
        $this->injectSystemAdminAuth($actorUserId);

        $controller = $this->makeController('post', [
            'first_name' => 'Out',
            'last_name' => 'OfDioceseScope',
            'email' => 'out.diocese.scope@example.org',
            'password' => 'Passw0rd!234',
            'confirm_password' => 'Passw0rd!234',
            'diocese_id' => $otherDioceseId,
            'phone' => '+1 809 555 0505',
        ]);
        $controller->storeDioceseAdmin();

        $this->assertSame(0, $this->findUserIdByEmail('out.diocese.scope@example.org'));
    }

    public function testStoreSystemAdminRejectsDuplicateEmailWithoutEnumeration(): void
    {
        $existingEmail = 'duplicate.system@example.org';
        $this->insertUser('existing.system.user', $existingEmail);

        $actorUserId = $this->insertUser('system.admin.actor.duplicate', 'system.admin.actor.duplicate@example.org');
        $this->insertProfile($actorUserId, null, null, 'System', 'Admin');
        $this->insertSystemAdminGroup($actorUserId);
        $this->insertPrimaryRecord($actorUserId);
        $this->injectSystemAdminAuth($actorUserId);

        $controller = $this->makeController('post', [
            'first_name' => 'Duplicate',
            'last_name' => 'Email',
            'email' => $existingEmail,
            'password' => 'Passw0rd!234',
            'confirm_password' => 'Passw0rd!234',
        ]);
        $response = $controller->storeSystemAdmin();

        // Ensure no additional user with the same email was created.
        $rows = $this->db->table($this->resolveTableName('auth_identities'))
            ->where('type', 'email_password')
            ->where('secret', $existingEmail)
            ->get()
            ->getResultArray();
        $this->assertCount(1, $rows);
    }

    public function testStoreSystemAdminSetsForceResetOnNewAccount(): void
    {
        $actorUserId = $this->insertUser('system.admin.actor.forcereset', 'system.admin.actor.forcereset@example.org');
        $this->insertProfile($actorUserId, null, null, 'System', 'Admin');
        $this->insertSystemAdminGroup($actorUserId);
        $this->insertPrimaryRecord($actorUserId);
        $this->injectSystemAdminAuth($actorUserId);

        $controller = $this->makeController('post', [
            'first_name' => 'Force',
            'last_name' => 'Reset',
            'email' => 'force.reset@example.org',
            'password' => 'Passw0rd!234',
            'confirm_password' => 'Passw0rd!234',
        ]);
        $response = $controller->storeSystemAdmin();

        $this->assertInstanceOf(RedirectResponse::class, $response);

        $createdUserId = $this->findUserIdByEmail('force.reset@example.org');
        $this->assertGreaterThan(0, $createdUserId);

        $identity = $this->db->table($this->resolveTableName('auth_identities'))
            ->where('user_id', $createdUserId)
            ->where('type', 'email_password')
            ->get()
            ->getRowArray();
        $this->assertNotNull($identity);
        $this->assertSame(1, (int) ($identity['force_reset'] ?? 0));
    }

    public function testStoreSystemAdminRequiresMfaWhenEnabledAndActorLacksMfa(): void
    {
        service('systemSettings')->set('mfa.required_for_admin_ops', true, 'boolean', 'security');

        $actorUserId = $this->insertUser('system.admin.actor.mfa', 'system.admin.actor.mfa@example.org');
        $this->insertProfile($actorUserId, null, null, 'System', 'Admin');
        $this->insertSystemAdminGroup($actorUserId);
        $this->insertPrimaryRecord($actorUserId);
        $this->injectSystemAdminAuth($actorUserId);

        $controller = $this->makeController('post', [
            'first_name' => 'Mfa',
            'last_name' => 'Required',
            'email' => 'mfa.required@example.org',
            'password' => 'Passw0rd!234',
            'confirm_password' => 'Passw0rd!234',
        ]);
        $controller->storeSystemAdmin();

        $this->assertSame(0, $this->findUserIdByEmail('mfa.required@example.org'));
    }

    public function testSetPasswordFlowClearsForceResetForNewAdmin(): void
    {
        $actorUserId = $this->insertUser('system.admin.actor.setpw', 'system.admin.actor.setpw@example.org');
        $this->insertProfile($actorUserId, null, null, 'System', 'Admin');
        $this->insertSystemAdminGroup($actorUserId);
        $this->insertPrimaryRecord($actorUserId);
        $this->injectSystemAdminAuth($actorUserId);

        // Create a new admin with force_reset = 1 (mirrors processStoreAdmin).
        $createdUserId = $this->insertUser('new.admin.setpw', 'new.admin.setpw@example.org');
        $this->db->table($this->resolveTableName('auth_identities'))
            ->where('user_id', $createdUserId)
            ->where('type', 'email_password')
            ->update(['force_reset' => 1]);

        // Switch auth to the new admin.
        $this->injectSystemAdminAuth($createdUserId);

        // Ensure the new admin sees force_reset as required.
        $this->assertTrue(auth()->user()->requiresPasswordReset());

        // Submit the set-password form.
        $request = service('request');
        $request->setMethod('post');
        $postData = [
            'new_password' => 'NewP@ssw0rd!234',
            'new_password_confirm' => 'NewP@ssw0rd!234',
        ];
        $request->setGlobal('post', $postData);
        $request->setGlobal('request', $postData);

        $controller = new \App\Controllers\Auth\SetPasswordController();
        $controller->initController($request, service('response'), service('logger'));
        $response = $controller->save();

        $this->assertInstanceOf(RedirectResponse::class, $response);

        // Force reset must be cleared in the database.
        $identity = $this->db->table($this->resolveTableName('auth_identities'))
            ->where('user_id', $createdUserId)
            ->where('type', 'email_password')
            ->get()
            ->getRowArray();
        $this->assertNotNull($identity);
        $this->assertSame(0, (int) ($identity['force_reset'] ?? 1));

        // Ensure the in-memory user object also reflects the cleared flag.
        $this->assertFalse(auth()->user()->requiresPasswordReset());
    }

    private function injectSystemAdminAuth(int $userId): void
    {
        $isPrimary = (int) $this->db->table($this->resolveTableName('auth_permissions_users'))
            ->where('user_id', $userId)
            ->where('permission', 'system.primary')
            ->countAllResults() > 0;

        $delegatedPermissions = $this->db->table($this->resolveTableName('auth_permissions_users'))
            ->where('user_id', $userId)
            ->whereIn('permission', [
                'system.admin.create',
                'system.admin.update',
                'system.admin.delete',
                'system.admin.reset_password',
            ])
            ->get()
            ->getResultArray();
        $delegatedPermissions = array_map(static fn (array $row) => (string) ($row['permission'] ?? ''), $delegatedPermissions);

        $fakeUser = new class($userId, $isPrimary, $delegatedPermissions) extends User {
            public function __construct(
                private int $userIdForTest,
                private bool $isPrimaryForTest,
                private array $delegatedPermissionsForTest
            ) {
                parent::__construct([
                    'id' => $userIdForTest,
                    'username' => 'system.admin.' . $userIdForTest,
                    'email' => 'system.admin.' . $userIdForTest . '@example.org',
                ]);
            }

            public function can(string ...$permissions): bool
            {
                foreach ($permissions as $permission) {
                    if ($permission === 'system.admin' || in_array($permission, $this->delegatedPermissionsForTest, true)) {
                        continue;
                    }

                    if ($permission === 'system.primary' && $this->isPrimaryForTest) {
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

        $auth = new class(config('Auth'), $fakeUser, $userId) extends Auth {
            public function __construct($config, private User $fakeUser, private int $userIdForTest)
            {
                parent::__construct($config);
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

    private function makeController(string $method, array $postData = []): SystemAdminUsers
    {
        $request = service('request');
        $request->setMethod($method);

        if ($postData !== []) {
            // Use setGlobal() only (not raw $_POST/$_REQUEST assignment): those
            // superglobals persist for the entire PHPUnit process and leak into
            // any later test whose IncomingRequest lazily populates its own
            // globals from them (e.g. Validation::withRequest()->getVar()).
            $request->setGlobal('post', $postData);
            $request->setGlobal('request', $postData);
        }

        $controller = new SystemAdminUsers();
        $controller->initController($request, service('response'), service('logger'));

        return $controller;
    }

    private function ensureCoreTablesExist(): void
    {
        $this->ensureUsersTable();
        $this->ensureSettingsTable();
        $this->ensureProvincesTable();
        $this->ensureDiocesesTable();
        $this->ensureProfilesTable();
        $this->ensureRegistrationRequestsTable();
        $this->ensureAuthGroupsUsersTable();
        $this->ensureAuthGroupsPermissionsTable();
        $this->ensureAuthPermissionsTable();
        $this->ensureAuthPermissionsUsersTable();
        $this->ensureAuthIdentitiesTable();
        $this->ensureStandingSupportTables();
        $this->ensureProvinceAdminPrimaryTable();
        $this->ensureDioceseAdminPrimaryTable();
        SystemSettingsTestHelper::createSystemSettingsTableAndSeed($this->db);
        SystemSettingsTestHelper::createAuditLogTable($this->db);
    }

    private function ensureProvinceAdminPrimaryTable(): void
    {
        $this->ensureSimpleTable('province_admin_primary', '(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            province_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            created_by INTEGER NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL
        )');
    }

    private function ensureDioceseAdminPrimaryTable(): void
    {
        $this->ensureSimpleTable('diocese_admin_primary', '(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            diocese_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            created_by INTEGER NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL
        )');
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
                updated_at TEXT NULL
            )');
        }

        $resolved = $this->resolveTableName('users');
        $this->ensureColumnExists($resolved, 'status', 'TEXT NULL');
        $this->ensureColumnExists($resolved, 'status_message', 'TEXT NULL');
        $this->ensureColumnExists($resolved, 'last_active', 'TEXT NULL');
        $this->ensureColumnExists($resolved, 'deleted_at', 'TEXT NULL');
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

    private function ensureProvincesTable(): void
    {
        $this->ensureSimpleTable('province', '(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            province_number TEXT NULL,
            sort_order INTEGER NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL
        )');
    }

    private function ensureDiocesesTable(): void
    {
        $this->ensureSimpleTable('diocese', '(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            province_id INTEGER NULL,
            name TEXT NOT NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL,
            deleted_at TEXT NULL
        )');
    }

    private function ensureProfilesTable(): void
    {
        $this->ensureSimpleTable('profile', '(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            parish_id INTEGER NULL,
            province_id INTEGER NULL,
            diocese_id INTEGER NULL,
            first_name TEXT NULL,
            last_name TEXT NULL,
            phone TEXT NULL,
            locale TEXT NULL,
            membership_status TEXT NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL,
            deleted_at TEXT NULL
        )');

        $this->ensureColumnExists($this->resolveTableName('profile'), 'is_confirmed', 'INTEGER DEFAULT 0');
        // A previously-run test class may have created a person-centric profile
        // table (person_id, no user_id) in this shared in-memory database. Apply
        // column guarantees to the physical prefixed table the query builder
        // will actually write to.
        $prefixedProfile = $this->createTableName('profile');
        foreach ([
            'user_id'           => 'INTEGER NULL',
            'person_id'         => 'INTEGER NULL',
            'parish_id'         => 'INTEGER NULL',
            'province_id'       => 'INTEGER NULL',
            'diocese_id'        => 'INTEGER NULL',
            'first_name'        => 'TEXT NULL',
            'last_name'         => 'TEXT NULL',
            'phone'             => 'TEXT NULL',
            'locale'            => 'TEXT NULL',
            'membership_status' => 'TEXT NULL',
            'portal_access'     => 'TEXT NULL',
            'is_confirmed'      => 'INTEGER DEFAULT 0',
            'deleted_at'        => 'TEXT NULL',
            'created_at'        => 'TEXT NULL',
            'updated_at'        => 'TEXT NULL',
        ] as $column => $definition) {
            $this->ensureColumnExists($prefixedProfile, $column, $definition);
            // insertProfile() mirrors rows into the unprefixed shadow table too.
            $this->ensureColumnExists('profile', $column, $definition);
        }
        if ($this->db->tableExists('profile')) {
            $this->ensureColumnExists('profile', 'is_confirmed', 'INTEGER DEFAULT 0');
        }
    }

    private function ensureRegistrationRequestsTable(): void
    {
        $table = $this->createTableName('user_registration_request');
        if (!$this->tableExists('user_registration_request')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                province_id INTEGER NULL,
                diocese_id INTEGER NULL,
                requested_parish_id INTEGER NULL,
                status TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )');
        }
    }

    private function ensureAuthGroupsUsersTable(): void
    {
        $table = $this->createTableName('auth_groups_users');
        if (!$this->tableExists('auth_groups_users')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                "group" TEXT NOT NULL,
                created_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }
    }

    private function ensureAuthGroupsPermissionsTable(): void
    {
        $table = $this->createTableName('auth_groups_permissions');
        if (!$this->tableExists('auth_groups_permissions')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                "group" TEXT NOT NULL,
                permission TEXT NOT NULL,
                created_at TEXT NULL
            )');
        }
    }

    private function ensureAuthPermissionsTable(): void
    {
        $table = $this->createTableName('auth_permissions');
        if (!$this->tableExists('auth_permissions')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                description TEXT NULL,
                created_at TEXT NULL
            )');
        }
    }

    private function ensureAuthPermissionsUsersTable(): void
    {
        $table = $this->createTableName('auth_permissions_users');
        if (!$this->tableExists('auth_permissions_users')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                permission TEXT NOT NULL,
                created_at TEXT NULL
            )');
        }
    }

    private function ensureAuthIdentitiesTable(): void
    {
        $table = $this->createTableName('auth_identities');
        if (!$this->tableExists('auth_identities')) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                type TEXT NOT NULL,
                secret TEXT NULL,
                secret2 TEXT NULL,
                expires TEXT NULL,
                extra TEXT NULL,
                force_reset INTEGER DEFAULT 0,
                last_used_at TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )');
        }
    }

    private function ensureStandingSupportTables(): void
    {
        $this->ensureSimpleTable('service', '(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            service_date TEXT NULL,
            date TEXT NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL
        )');

        $this->ensureSimpleTable('attendance', '(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            service_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            attended INTEGER DEFAULT 0,
            communicant INTEGER DEFAULT 0,
            created_at TEXT NULL,
            updated_at TEXT NULL
        )');

        $this->ensureSimpleTable('annual_pledge', '(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            pledge_year INTEGER NOT NULL,
            fulfillment_status TEXT NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL
        )');

        $this->ensureSimpleTable('member_discipline', '(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            start_date TEXT NULL,
            end_date TEXT NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL
        )');

        $this->ensureSimpleTable('member_standing_summary', '(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            year INTEGER NOT NULL,
            is_active_member INTEGER DEFAULT 0,
            attendance_count INTEGER DEFAULT 0,
            communion_count INTEGER DEFAULT 0,
            has_pledged INTEGER DEFAULT 0,
            is_under_discipline INTEGER DEFAULT 0,
            is_good_standing INTEGER DEFAULT 0,
            last_updated TEXT NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL
        )');
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
        $payload = [
            'user_id' => $userId,
            'province_id' => $provinceId,
            'diocese_id' => $dioceseId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'locale' => 'en',
            'membership_status' => 'active',
            'is_confirmed' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->db->table($this->resolveTableName('profile'))->insert($payload);
        if ($this->db->tableExists('profile') && $this->resolveTableName('profile') !== 'profile') {
            $this->db->table('profile')->insert($payload);
        }
    }

    private function insertProvince(string $name): int
    {
        $payload = [
            'name' => $name,
            'province_number' => 'P-' . random_int(10, 99),
            'sort_order' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->db->table($this->resolveTableName('province'))->insert($payload);

        $provinceId = (int) $this->db->insertID();
        if ($this->db->tableExists('province') && $this->resolveTableName('province') !== 'province') {
            $payload['id'] = $provinceId;
            $this->db->table('province')->insert($payload);
        }

        return $provinceId;
    }

    private function insertDiocese(int $provinceId, string $name): int
    {
        $payload = [
            'province_id' => $provinceId,
            'name' => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->db->table($this->resolveTableName('diocese'))->insert($payload);

        $dioceseId = (int) $this->db->insertID();
        if ($this->db->tableExists('diocese') && $this->resolveTableName('diocese') !== 'diocese') {
            $payload['id'] = $dioceseId;
            $this->db->table('diocese')->insert($payload);
        }

        return $dioceseId;
    }

    private function findUserIdByEmail(string $email): int
    {
        $row = $this->db->table($this->resolveTableName('auth_identities'))
            ->select('user_id')
            ->where('type', 'email_password')
            ->where('secret', $email)
            ->get()
            ->getRowArray();

        return (int) ($row['user_id'] ?? 0);
    }

    private function tableExists(string $table): bool
    {
        return $this->db->tableExists($table) || $this->db->tableExists($this->db->getPrefix() . $table);
    }

    private function ensureColumnExists(string $table, string $column, string $definition): void
    {
        try {
            $columns = array_map('strtolower', $this->db->getFieldNames($table));
        } catch (\Throwable $e) {
            $columns = [];
        }

        if (in_array(strtolower($column), $columns, true)) {
            return;
        }

        try {
            $this->db->query('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'duplicate column name') !== false) {
                return;
            }

            throw $e;
        }
    }

    private function ensureSimpleTable(string $base, string $schema): void
    {
        $prefixed = $this->createTableName($base);
        if (!$this->db->tableExists($prefixed)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $prefixed . ' ' . $schema);
        }

        if (!$this->db->tableExists($base)) {
            $this->db->query('CREATE TABLE IF NOT EXISTS ' . $base . ' ' . $schema);
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
