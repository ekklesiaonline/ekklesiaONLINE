<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regression guard: the device-verification endpoints must NOT be excluded
 * from CSRF protection. Both forms include csrf_field(), and the challenge
 * page is rendered after the session exists so the token is valid.
 *
 * @internal
 */
final class DeviceVerifyCsrfTest extends CIUnitTestCase
{
    public function testDeviceVerifyRoutesAreNotExcludedFromCsrf(): void
    {
        $filtersFile = ROOTPATH . 'app/Config/Filters.php';
        $this->assertFileExists($filtersFile);
        $content = file_get_contents($filtersFile);
        $this->assertNotFalse($content);

        // Extract the csrf filter entry from the globals before block.
        preg_match(
            "/'csrf'\s*=>\s*\[\s*'except'\s*=>\s*\[(.*?)\]\s*,?\s*\]/s",
            $content,
            $csrfBlockMatch
        );

        $csrfExceptBlock = $csrfBlockMatch[1] ?? '';

        $this->assertStringNotContainsString(
            'session/device-verify',
            $csrfExceptBlock,
            'session/device-verify must NOT be excluded from CSRF — the form includes csrf_field().'
        );
        $this->assertStringNotContainsString(
            'session/device-verify/resend',
            $csrfExceptBlock,
            'session/device-verify/resend must NOT be excluded from CSRF — it should be token-protected.'
        );
    }

    public function testResendFormIncludesCsrfField(): void
    {
        $views = [
            ROOTPATH . 'MemberPortal/Views/Session/device_verify.php',
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
