<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class SystemPrimaryTransferTest extends CIUnitTestCase
{
    public function testTransferPrimaryUsesCredentialMailer(): void
    {
        $source = file_get_contents(APPPATH . 'Controllers/SystemPrimary.php');

        $this->assertStringContainsString(
            'credentialMailer()',
            $source,
            'SystemPrimary must use credentialMailer() for sending OTPs per AGENTS.md security rules.'
        );

        $this->assertStringNotContainsString(
            'systemMailer()',
            $source,
            'SystemPrimary must not use systemMailer() for credential-bearing mail.'
        );
    }

    public function testSendStatusCheckedOnOtpDispatch(): void
    {
        $source = file_get_contents(APPPATH . 'Controllers/SystemPrimary.php');

        $this->assertStringContainsString(
            '!$emailService->send(false)',
            $source,
            'SystemPrimary must check the boolean return of $emailService->send(false).'
        );

        $this->assertStringContainsString(
            'clearPrimaryTransferOtpChallenge()',
            $source,
            'SystemPrimary must clear OTP challenge when sending fails.'
        );
    }
}
