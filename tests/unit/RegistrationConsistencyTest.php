<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regression guards for H6 (registration consistency) and H7 (direct-grant review).
 *
 * H6 (superseded by T4): RegistrationApprovalService used to refresh the
 * parish_people snapshot after approve/ban/delete. The snapshot is retired —
 * directory reads are canonical — so the service must no longer depend on it.
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
     * T4: RegistrationApprovalService must not depend on the retired
     * parish_people snapshot — directory reads are canonical.
     */
    public function testApprovalServiceDoesNotUseRetiredSnapshot(): void
    {
        $source = file_get_contents(APPPATH . 'Services/RegistrationApprovalService.php');

        $this->assertStringNotContainsString(
            'ParishPeopleRegistryService',
            $source,
            'RegistrationApprovalService must not reference the retired parish_people registry.'
        );
        $this->assertStringNotContainsString(
            'syncParish',
            $source,
            'RegistrationApprovalService must not call the retired syncParish.'
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
     * Approval must create the canonical parish_affiliation row: the people
     * directory is affiliation-driven, so an approved registration without
     * one stays invisible to parish member/people lists.
     */
    public function testPerformApproveCreatesParishAffiliation(): void
    {
        $reflection = new ReflectionMethod(
            \App\Services\RegistrationApprovalService::class,
            'performApprove'
        );
        $source = $this->getMethodSource($reflection);

        $this->assertStringContainsString(
            'MemberAffiliationService',
            $source,
            'performApprove must use MemberAffiliationService to create the parish_affiliation row.'
        );
        $this->assertStringContainsString(
            'ensurePrimaryAffiliationForUser',
            $source,
            'performApprove must attach the user to the parish so directory listings include them.'
        );
        $this->assertStringContainsString(
            'regular_attender',
            $source,
            'Approval must not confer canonical membership; regular_attender requires parish promotion to member.'
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
        $this->assertStringContainsString("UserRegistrationRequestModel", $source);
        $this->assertStringNotContainsString("user_registration_request')", $source, 'Do not hardcode the singular table name; the model resolves the real table.');
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
