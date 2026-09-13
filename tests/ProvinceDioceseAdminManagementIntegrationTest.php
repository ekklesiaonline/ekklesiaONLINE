<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

final class ProvinceDioceseAdminManagementIntegrationTest extends CIUnitTestCase
{
    public function testProvinceAdminsReceiveOnlyTheScopedDioceseAdminPermission(): void
    {
        $authGroups = config('AuthGroups');

        $this->assertContains('province.diocese_admin.manage', $authGroups->matrix['provinceadmin']);
        $this->assertNotContains('province.diocese_admin.manage', $authGroups->matrix['dioceseadmin']);
        $this->assertNotContains('system.admin', $authGroups->matrix['provinceadmin']);
    }

    public function testProvinceRoutesRequireTheScopedPermission(): void
    {
        $routes = file_get_contents(ROOTPATH . 'Province/Config/Routes.php');

        $this->assertIsString($routes);
        $this->assertSame(7, substr_count($routes, 'permission:province.diocese_admin.manage'));
        $this->assertStringContainsString("'diocese-admins/reactivate/(:num)'", $routes);
        $this->assertStringContainsString("'diocese-admins/store'", $routes);
        $this->assertStringContainsString("'diocese-admins/update/(:num)'", $routes);
        $this->assertStringContainsString("'diocese-admins/reset-password/(:num)'", $routes);
        $this->assertStringContainsString("'diocese-admins/resend-invitation/(:num)'", $routes);
        $this->assertStringContainsString("'diocese-admins/deactivate/(:num)'", $routes);
    }

    public function testControllerAppliesScopeChecksToEveryTargetedWrite(): void
    {
        $controller = file_get_contents(APPPATH . 'Controllers/ProvinceDioceseAdmins.php');

        $this->assertIsString($controller);
        $this->assertSame(5, substr_count($controller, 'if (!$this->targetIsInActorScope($userId)'));
        $this->assertStringContainsString('if (!$this->dioceseIsInActorScope($dioceseId))', $controller);
        $this->assertStringContainsString('if (!$this->targetIsInActorScope($userId) || !$this->dioceseIsInActorScope($dioceseId))', $controller);
        $this->assertStringContainsString('if (!$this->targetIsInActorScope($userId) || (int) auth()->id() === $userId)', $controller);
        $this->assertStringContainsString("'group' => 'dioceseadmin'", $controller);
        $this->assertStringNotContainsString("'group' => 'provinceadmin'", $controller);
        $this->assertStringContainsString('public function reactivate(int $userId)', $controller);
        $this->assertStringContainsString("'province.diocese_admin.reactivated'", $controller);
    }
}
