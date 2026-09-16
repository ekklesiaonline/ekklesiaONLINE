<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class AuthGroupsGovernanceBoundaryTest extends CIUnitTestCase
{
    public function testProvinceAndDiocesanGroupsAreStrictlyNonGovernanceAndNonFinance(): void
    {
        $authGroups = config('AuthGroups');

        $province = $authGroups->matrix['provinceadmin'] ?? [];
        $diocesan = $authGroups->matrix['dioceseadmin'] ?? [];

        $this->assertSame(
            [
                'province.admin',
                'province.diocese_admin.manage',
                'province.primary',
                'province.admin.create',
                'province.admin.update',
                'province.admin.delete',
                'province.admin.reset_password',
                'province.event.create',
                'province.event.edit',
                'province.event.delete',
                'province.calendar.manage',
                'member.view',
                'dashboard.view',
                'profile.edit',
                'report.export',
                'report.parochial',
                'governance.vestry.view',
            ],
            $province,
            'provinceadmin matrix must remain strict/read-only.'
        );

        $this->assertSame(
            ['diocese.admin', 'parishes.edit', 'parishes.delete', 'member.create', 'member.edit', 'member.view', 'service.view', 'event.view', 'event.create', 'event.edit', 'event.delete', 'event.register', 'event.checkin', 'dashboard.view', 'profile.edit', 'diocese.coa', 'diocese.calendar', 'diocese.dashboard', 'report.export', 'report.parochial', 'governance.vestry.view', 'governance.diocese.approve'],
            $diocesan,
            'diocesanadmin matrix must remain strict/read-only.'
        );

        $forbidden = [
            'finance.view',
            'finance.manage',
            'donation.view',
            'expense.view',
            'member.*',
            'member.delete',
            'attendance.mark',
            'attendance.reports',
            'parishes.manage',
        ];

        foreach ($forbidden as $permission) {
            $this->assertNotContains($permission, $province, 'provinceadmin must not include ' . $permission);
            $this->assertNotContains($permission, $diocesan, 'diocesanadmin must not include ' . $permission);
        }
    }
}
