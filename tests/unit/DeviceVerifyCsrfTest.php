<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regression guard: the device-verification resend endpoint must NOT be
 * excluded from CSRF protection. The resend form already includes
 * csrf_field(), so the server-side filter must enforce the token.
 *
 * The primary device-verify POST remains excluded because the user may
 * be challenged before they have a valid CSRF token.
 *
 * @internal
 */
final class DeviceVerifyCsrfTest extends CIUnitTestCase
{
    public function testResendRouteIsNotExcludedFromCsrf(): void
    {
        $filtersFile = ROOTPATH . 'app/Config/Filters.php';
        $this->assertFileExists($filtersFile);
        $content = file_get_contents($filtersFile);
        $this->assertNotFalse($content);

        // The csrf except block must NOT contain the resend path.
        // Extract the csrf except block to avoid matching the deviceVerification filter block.
        preg_match(
            "/'csrf'\s*=>\s*\[\s*'except'\s*=>\s*\[(.*?)\]\s*,?\s*\]/s",
            $content,
            $csrfBlockMatch
        );

        $this->assertNotEmpty(
            $csrfBlockMatch,
            'Could not locate the csrf except block in Filters.php.'
        );

        $csrfExceptBlock = $csrfBlockMatch[1];

        $this->assertStringNotContainsString(
            "session/device-verify/resend",
            $csrfExceptBlock,
            'session/device-verify/resend must NOT be excluded from CSRF — it should be token-protected.'
        );

        // The primary device-verify path should still be excluded.
        $this->assertStringContainsString(
            'session/device-verify',
            $csrfExceptBlock,
            'session/device-verify should remain excluded from CSRF (challenge flow).'
        );
    }

    public function testResendFormIncludesCsrfField(): void
    {
        $views = [
            ROOTPATH . 'MemberPortal/Views/Session/device_verify.php',
            ROOTPATH . 'app/Views/session/device_verify.php',
        ];

        foreach ($views as $view) {
            $this->assertFileExists($view);
            $content = file_get_contents($view);
            $this->assertNotFalse($content);

            $this->assertStringContainsString(
                'session/device-verify/resend',
                $content,
                "View {$view} must reference the resend endpoint."
            );
            $this->assertStringContainsString(
                'csrf_field()',
                $content,
                "View {$view} must include csrf_field() in the resend form."
            );
        }
    }
}
