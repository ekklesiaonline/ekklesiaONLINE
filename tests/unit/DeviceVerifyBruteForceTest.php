<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Contract guard: device-verification brute-force protection.
 *
 * 1. SessionSecurity must cap failed code attempts (MAX_VERIFY_ATTEMPTS = 5)
 *    and invalidate the pending code when the cap is reached, logging an
 *    audit event (security.device_verification.lockout).
 * 2. The loginRateLimit filter must cover the device-verify POST endpoints
 *    so per-IP throttling applies on top of the per-session cap.
 *
 * @internal
 */
final class DeviceVerifyBruteForceTest extends CIUnitTestCase
{
    private string $controllerSource;

    protected function setUp(): void
    {
        parent::setUp();
        $path = ROOTPATH . 'MemberPortal/Controllers/SessionSecurity.php';
        $this->assertFileExists($path);
        $source = file_get_contents($path);
        $this->assertNotFalse($source);
        $this->controllerSource = $source;
    }

    public function testMaxVerifyAttemptsConstantIsDefined(): void
    {
        $this->assertMatchesRegularExpression(
            '/MAX_VERIFY_ATTEMPTS\s*=\s*5\b/',
            $this->controllerSource,
            'SessionSecurity must define MAX_VERIFY_ATTEMPTS = 5.'
        );
    }

    public function testFailedAttemptsAreCountedAndCapped(): void
    {
        $this->assertStringContainsString(
            'device_verification_attempts',
            $this->controllerSource,
            'Failed attempts must be tracked in session.'
        );
        $this->assertStringContainsString(
            'self::MAX_VERIFY_ATTEMPTS',
            $this->controllerSource,
            'The attempt counter must be compared against MAX_VERIFY_ATTEMPTS.'
        );
    }

    public function testLockoutInvalidatesCodeAndAudits(): void
    {
        $this->assertStringContainsString(
            "'device_verification_code_hash'",
            $this->controllerSource,
            'Lockout must remove the stored code hash.'
        );
        $this->assertStringContainsString(
            'security.device_verification.lockout',
            $this->controllerSource,
            'Lockout must emit the security.device_verification.lockout audit event.'
        );
        $this->assertStringNotContainsString(
            "'device_verification_required'",
            $this->extractInvalidateMethodBody(),
            'Lockout must NOT clear device_verification_required — the user must request a new code.'
        );
    }

    public function testLoginRateLimitCoversDeviceVerifyEndpoints(): void
    {
        $filtersFile = ROOTPATH . 'app/Config/Filters.php';
        $content = file_get_contents($filtersFile);
        $this->assertNotFalse($content);

        preg_match(
            "/'loginRateLimit'\s*=>\s*\[\s*'before'\s*=>\s*\[(.*?)\]\s*,?\s*\]/s",
            $content,
            $match
        );

        $this->assertNotEmpty($match, 'Could not locate the loginRateLimit before block in Filters.php.');
        $block = $match[1];

        $this->assertStringContainsString(
            "'session/device-verify'",
            $block,
            'loginRateLimit must cover session/device-verify.'
        );
        $this->assertStringContainsString(
            "'session/device-verify/resend'",
            $block,
            'loginRateLimit must cover session/device-verify/resend.'
        );
    }

    public function testLoginRateLimitCoversMagicLinkEndpoint(): void
    {
        $filtersFile = ROOTPATH . 'app/Config/Filters.php';
        $content = file_get_contents($filtersFile);
        $this->assertNotFalse($content);

        preg_match(
            "/'loginRateLimit'\s*=>\s*\[\s*'before'\s*=>\s*\[(.*?)\]\s*,?\s*\]/s",
            $content,
            $match
        );

        $this->assertNotEmpty($match, 'Could not locate the loginRateLimit before block in Filters.php.');

        // Each POST to login/magic-link generates a token and sends an
        // email — without per-IP throttling it is an email-bombing vector.
        $this->assertStringContainsString(
            "'login/magic-link'",
            $match[1],
            'loginRateLimit must cover login/magic-link.'
        );
    }

    private function extractInvalidateMethodBody(): string
    {
        if (preg_match(
            '/function\s+invalidateVerificationCode\s*\([^)]*\)\s*:\s*void\s*\{(.*?)\n    \}/s',
            $this->controllerSource,
            $m
        )) {
            return $m[1];
        }

        return '';
    }
}
