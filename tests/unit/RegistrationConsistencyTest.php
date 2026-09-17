<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regression guards for H6 (registration consistency) and H7 (direct-grant review).
 *
 * H6: RegistrationApprovalService must refresh the parish_people snapshot
 * after approve, ban, and delete actions so the directory stays consistent
 * immediately, not just after the next cron run.
 *
 * H7: Direct permission grants (auth_permissions_users) are used only for
 * primary-admin markers and delegation permissions — not for granting
 * arbitrary role permissions that should come from the group matrix.
 *
 * @internal
 */
final class RegistrationConsistencyTest extends CIUnitTestCase
{
    /**
     * H6: performApprove must call syncParish after approving a registration.
     */
    public function testApproveTriggersSnapshotSync(): void
    {
        $reflection = new ReflectionMethod(
            \App\Services\RegistrationApprovalService::class,
            'performApprove'
        );
        $source = $this->getMethodSource($reflection);

        $this->assertStringContainsString(
            'ParishPeopleRegistryService',
            $source,
            'performApprove must call ParishPeopleRegistryService::syncParish after approving.'
        );
        $this->assertStringContainsString(
            'syncParish',
            $source,
            'performApprove must call syncParish to refresh the parish_people snapshot.'
        );
    }

    /**
     * H6: delete must call syncParish after deleting a registration.
     */
    public function testDeleteTriggersSnapshotSync(): void
    {
        $reflection = new ReflectionMethod(
            \App\Services\RegistrationApprovalService::class,
            'delete'
        );
        $source = $this->getMethodSource($reflection);

        $this->assertStringContainsString(
            'syncParish',
            $source,
            'delete must call syncParish to remove the deleted user from the snapshot.'
        );
    }

    /**
     * H6: markReviewed (ban) must call syncParish when deactivating a user.
     */
    public function testBanTriggersSnapshotSync(): void
    {
        $reflection = new ReflectionMethod(
            \App\Services\RegistrationApprovalService::class,
            'markReviewed'
        );
        $source = $this->getMethodSource($reflection);

        $this->assertStringContainsString(
            'syncParish',
            $source,
            'markReviewed must call syncParish when banning (deactivating) a user.'
        );
    }

    /**
     * H6: RegistrationApprovalService must use AdminHierarchyGuard.
     */
    public function testRegistrationApprovalServiceUsesHierarchyGuard(): void
    {
        $reflection = new ReflectionClass(\App\Services\RegistrationApprovalService::class);

        $this->assertContains(
            \App\Controllers\AdminHierarchyGuard::class,
            $reflection->getTraitNames(),
            'RegistrationApprovalService must use the AdminHierarchyGuard trait.'
        );
    }

    /**
     * H6: RegistrationApprovalService must define TOP_TIER_GROUPS for
     * auto-approval and hierarchy checks.
     */
    public function testTopTierGroupsDefined(): void
    {
        $reflection = new ReflectionClass(\App\Services\RegistrationApprovalService::class);
        $constants = $reflection->getConstants();

        $this->assertArrayHasKey('TOP_TIER_GROUPS', $constants);
        $this->assertContains('systemadmin', $constants['TOP_TIER_GROUPS']);
        $this->assertContains('provinceadmin', $constants['TOP_TIER_GROUPS']);
        $this->assertContains('dioceseadmin', $constants['TOP_TIER_GROUPS']);
    }

    /**
     * H6: escalate() must auto-approve top-tier admins instead of leaving
     * them in pending_review limbo.
     */
    public function testEscalateAutoApprovesTopTierAdmins(): void
    {
        $reflection = new ReflectionMethod(
            \App\Services\RegistrationApprovalService::class,
            'escalate'
        );
        $source = $this->getMethodSource($reflection);

        $this->assertStringContainsString(
            'isTopTierAdmin',
            $source,
            'escalate must check isTopTierAdmin to auto-approve higher-tier admins.'
        );
        $this->assertStringContainsString(
            'performApprove',
            $source,
            'escalate must call performApprove for top-tier admins instead of leaving them pending.'
        );
    }

    /**
     * H7: Direct permission grants in controllers must only use the
     * documented primary/delegation permissions, not arbitrary role permissions.
     */
    public function testDirectGrantsAreLimitedToPrimaryAndDelegation(): void
    {
        // The only permissions that should be directly granted to users
        // (via auth_permissions_users) are primary-admin markers and
        // system-admin delegation permissions. Role permissions should
        // come from the group matrix, not direct grants.
        $allowedDirectGrants = [
            'system.primary',
            'diocese.primary',
            'parish.primary',
            'province.primary',
            'system.admin.create',
            'system.admin.update',
            'system.admin.delete',
            'system.admin.reset_password',
            'system.province.create',
            'system.diocese.create',
            'diocese.admin.create',
            'diocese.admin.update',
            'diocese.admin.delete',
            'diocese.admin.reset_password',
            'parish.admin.create',
            'parish.admin.update',
            'parish.admin.delete',
            'parish.admin.reset_password',
        ];

        // Check SystemAdminService DELEGATION_PERMISSIONS constant
        $reflection = new ReflectionClass(\App\Controllers\SystemAdminService::class);
        $constants = $reflection->getConstants();

        if (isset($constants['DELEGATION_PERMISSIONS'])) {
            foreach ($constants['DELEGATION_PERMISSIONS'] as $permission) {
                $this->assertContains(
                    $permission,
                    $allowedDirectGrants,
                    "SystemAdminService delegation permission '{$permission}' is not in the allowed direct-grant list."
                );
            }
        }
    }

    /**
     * H7: Admin deletion controllers must clean up auth_permissions_users
     * when deleting an admin user, to prevent orphaned direct grants.
     */
    public function testAdminDeletionCleansUpDirectGrants(): void
    {
        $systemAdminUsersSource = file_get_contents(APPPATH . 'Controllers/SystemAdminUsers.php')
            . file_get_contents(APPPATH . 'Services/SystemAdminUserService.php');
        $dioceseParishAdminsSource = file_get_contents(APPPATH . 'Controllers/DioceseParishAdmins.php');
        $dioceseAdminUsersSource = file_get_contents(APPPATH . 'Controllers/DioceseAdminUsers.php');

        $this->assertStringContainsString(
            "auth_permissions_users",
            $systemAdminUsersSource,
            'SystemAdminUsers delete must clean up auth_permissions_users.'
        );
        $this->assertStringContainsString(
            "auth_permissions_users",
            $dioceseParishAdminsSource,
            'DioceseParishAdmins delete must clean up auth_permissions_users.'
        );
        $this->assertStringContainsString(
            "auth_permissions_users",
            $dioceseAdminUsersSource,
            'DioceseAdminUsers delete must clean up auth_permissions_users.'
        );
    }

    /**
     * H7: RegistrationGateFilter must pass through users with no
     * registration record (seeded/admin users) and block banned/dismissed.
     */
    public function testRegistrationGateFilterHandlesAllStatuses(): void
    {
        $source = file_get_contents(APPPATH . 'Filters/RegistrationGateFilter.php');

        $this->assertStringContainsString("dismissed", $source);
        $this->assertStringContainsString("banned", $source);
        $this->assertStringContainsString("pending_profile", $source);
        $this->assertStringContainsString("pending_review", $source);
        $this->assertStringContainsString("isStaffAdmin", $source);
    }

    private function getMethodSource(ReflectionMethod $method): string
    {
        $file = $method->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();

        $lines = file($file);

        return implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));
    }
}
