<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regression guard for C8: every permission string checked by controllers,
 * route filters, and views must be defined in Config\AuthGroups::$permissions,
 * and permissions used by controllers with clear migration intent must be
 * present in the group matrix.
 *
 * @internal
 */
final class PermissionMatrixNamesTest extends CIUnitTestCase
{
    /**
     * Every permission string used in can() or checkPermission() calls across
     * controllers and views must be registered in $permissions.
     */
    public function testAllUsedPermissionsAreDefined(): void
    {
        $authGroups = config('AuthGroups');
        $defined = array_keys($authGroups->permissions);

        // These permission strings are actively checked in controllers/views.
        // Each must appear in $permissions so Shield's addPermission/syncPermissions
        // don't reject them, and so the seeder can discover them via wildcards.
        $required = [
            'admin.system',
            'annual_pledge.manage',
            'attendance.mark',
            'attendance.view',
            'dashboard.view',
            'diocese.admin',
            'diocese.primary',
            'donation.create',
            'donation.edit',
            'donation.view',
            'event.create',
            'event.delete',
            'event.edit',
            'event.view',
            'expense.view',
            'family.manage',
            'family.view',
            'finance.manage',
            'finance.view',
            'governance.vestry.manage',
            'governance.vestry.view',
            'visitor.view',
            'member.delete',
            'member.edit',
            'member.view',
            'national.calendar.manage',
            'national.event.manage',
            'parish.edit',
            'parishes.edit',
            'parishes.delete',
            'parishes.manage.official',
            'portal.manage',
            'profile.edit',
            'province.admin',
            'province.calendar.manage',
            'province.diocese_admin.manage',
            'report.export',
            'report.view',
            'service.view',
            'system.admin',
            'system.admin.create',
            'system.admin.delete',
            'system.admin.reset_password',
            'system.admin.update',
            'system.diocese.create',
            'system.province.create',
            'vestry.motion.vote',
            'volunteer.manage',
            'volunteer.schedule',
            'volunteer.view',
            'worship.view',
        ];

        $missing = array_diff($required, $defined);
        $this->assertSame(
            [],
            $missing,
            'Permission strings used in code but not defined in $permissions: ' . implode(', ', $missing)
        );
    }

    /**
     * Province event permissions must be in the provinceadmin matrix so that
     * $user->can() resolves them (Shield checks the config matrix, not the
     * auth_groups_permissions table).
     */
    public function testProvinceAdminMatrixIncludesEventPermissions(): void
    {
        $authGroups = config('AuthGroups');
        $provinceMatrix = $authGroups->matrix['provinceadmin'] ?? [];

        $required = [
            'province.event.create',
            'province.event.edit',
            'province.event.delete',
            'province.calendar.manage',
        ];

        foreach ($required as $permission) {
            $this->assertContains(
                $permission,
                $provinceMatrix,
                "provinceadmin matrix must include {$permission} (migration intent)."
            );
        }
    }

    /**
     * National event permissions and parishes.manage.official must be in the
     * systemadmin matrix.
     */
    public function testSystemAdminMatrixIncludesNationalAndOfficialPermissions(): void
    {
        $authGroups = config('AuthGroups');
        $systemMatrix = $authGroups->matrix['systemadmin'] ?? [];

        $required = [
            'national.event.manage',
            'national.calendar.manage',
            'parishes.manage.official',
        ];

        foreach ($required as $permission) {
            $this->assertContains(
                $permission,
                $systemMatrix,
                "systemadmin matrix must include {$permission}."
            );
        }
    }

    /**
     * The dioceseadmin matrix must still include parishes.edit and parishes.delete.
     */
    public function testDioceseAdminMatrixIncludesParishesPermissions(): void
    {
        $authGroups = config('AuthGroups');
        $dioceseMatrix = $authGroups->matrix['dioceseadmin'] ?? [];

        $this->assertContains('parishes.edit', $dioceseMatrix);
        $this->assertContains('parishes.delete', $dioceseMatrix);
    }

    /**
     * No duplicate permission entries in any group matrix.
     */
    public function testNoDuplicateMatrixEntries(): void
    {
        $authGroups = config('AuthGroups');

        foreach ($authGroups->matrix as $group => $permissions) {
            $counts = array_count_values($permissions);
            $duplicates = array_filter($counts, static fn(int $count): bool => $count > 1);

            $this->assertSame(
                [],
                $duplicates,
                "Duplicate permission entries in {$group} matrix: " . implode(', ', array_keys($duplicates))
            );
        }
    }
}
