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

        $this->assertStringContainsString('isPrimaryParishAdmin', $src);
        foreach (['cannotDeletePrimaryParishAdmin', 'cannotDeactivatePrimaryParishAdmin', 'cannotResetPrimaryParishAdminPassword'] as $key) {
            $this->assertStringContainsString($key, $src, 'missing primary guard message: ' . $key);
        }
        $this->assertSame(3, substr_count($src, 'if ($this->isPrimaryParishAdmin($userId))'), 'delete, deactivate and resetPassword must each check the primary guard');
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
}
