<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Source-level regression coverage for the Diocese module security audit
 * findings that are not behavior-testable without a full HTTP fixture.
 *
 * @internal
 */
final class DioceseSecurityHardeningTest extends CIUnitTestCase
{
    public function testParishAdminMutationsGuardThePrimary(): void
    {
        $src = file_get_contents(ROOTPATH . 'app/Controllers/DioceseParishAdmins.php');

        $this->assertStringContainsString('targetIsPrimaryParishAdmin', $src);
        foreach (['cannotDeletePrimaryParishAdmin', 'cannotDeactivatePrimaryParishAdmin', 'cannotResetPrimaryParishAdminPassword'] as $key) {
            $this->assertStringContainsString($key, $src, 'missing primary guard message: ' . $key);
        }
        $this->assertSame(3, substr_count($src, 'if ($this->targetIsPrimaryParishAdmin($userId))'), 'delete, deactivate and resetPassword must each check the primary guard');
    }

    public function testParishesJsonErrorsCarryErrorCode(): void
    {
        $src = file_get_contents(ROOTPATH . 'Diocese/Controllers/Parishes.php');
        $this->assertStringContainsString('apiErrorPayload', $src);
        $this->assertStringNotContainsString("jsonResponse(['success' => false", $src, 'bare error-key JSON response remains');
    }

    public function testStaffPasswordRuleIsTwelveCharacters(): void
    {
        $src = file_get_contents(ROOTPATH . 'app/Services/ParishStaffService.php');
        $this->assertStringContainsString('required|min_length[12]', $src);
        $this->assertStringNotContainsString('min_length[8]', $src);
    }

    public function testLiturgicalSourceReadsStaySessionOnlyAndWritesAreSystemGated(): void
    {
        $moduleRoutes = file_get_contents(ROOTPATH . 'Diocese/Config/Routes.php');
        // No write routes may be added to the session-only liturgical-source group.
        $pattern = '/group\(\'liturgical-source\'.*?\{(.*?)\}\);/s';
        self::assertSame(1, preg_match($pattern, $moduleRoutes, $m));
        $this->assertSame(0, preg_match('/routes->(post|put|delete|patch)\(/', $m[1]), 'write route added to session-only liturgical-source group');

        // Manage endpoints must remain under the permission:system.admin group.
        $appRoutes = file_get_contents(ROOTPATH . 'app/Config/Routes.php');
        $sysPattern = '/group\(\'system\',\s*\[\'filter\'\s*=>\s*\'permission:system\.admin\'\].*?\{(.*?)\n    \}\);/s';
        self::assertSame(1, preg_match($sysPattern, $appRoutes, $sm), 'system group not found or lost its permission filter');
        $this->assertStringContainsString('liturgical-source/store', $sm[1]);
        $this->assertStringContainsString('liturgical-source/update/', $sm[1]);
        $this->assertStringContainsString('liturgical-source/delete/', $sm[1]);
    }

    public function testDeadDiocesanScopeFilterAliasRemoved(): void
    {
        $this->assertFileDoesNotExist(ROOTPATH . 'app/Filters/DiocesanScopeFilter.php');
        $filters = file_get_contents(ROOTPATH . 'app/Config/Filters.php');
        $this->assertStringNotContainsString('DiocesanScopeFilter', $filters);
    }

    public function testDioceseRoutesCoveredByDioceseScope(): void
    {
        $src = file_get_contents(ROOTPATH . 'app/Config/Filters.php');
        $pattern = '/\'dioceseScope\'\s*=>\s*\[\s*\'before\'\s*=>\s*\[(.*?)\]/s';
        self::assertSame(1, preg_match($pattern, $src, $m), 'missing dioceseScope before list');
        $this->assertStringContainsString("'diocese'", $m[1]);
        $this->assertStringContainsString("'diocese/*'", $m[1]);
    }
}
