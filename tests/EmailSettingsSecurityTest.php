<?php

namespace Tests;

use App\Services\EmailSettingsService;
use CodeIgniter\Test\CIUnitTestCase;
use ReflectionMethod;

/**
 * @internal
 */
final class EmailSettingsSecurityTest extends CIUnitTestCase
{
    public function testSmtpAuthenticationFailureIsClassifiedWithoutReturningTranscript(): void
    {
        $service = new EmailSettingsService();
        $method = new ReflectionMethod($service, 'classifySendFailure');
        $method->setAccessible(true);

        $code = $method->invoke($service, '535-5.7.8 Username and Password not accepted. Failed to authenticate password.');

        $this->assertSame('SMTP_AUTHENTICATION_FAILED', $code);

        $sendTest = new ReflectionMethod($service, 'sendTest');
        $source = $this->methodSource($sendTest);
        $this->assertStringNotContainsString('strip_tags', $source);
        $this->assertStringNotContainsString('$e->getMessage()', $source);
    }

    public function testSmtpPasswordUsesTextSafeEncryptionEnvelope(): void
    {
        $method = new ReflectionMethod(EmailSettingsService::class, 'persistSettings');
        $source = $this->methodSource($method);

        $this->assertStringContainsString('DataEncryption::encrypt', $source);
        $this->assertStringNotContainsString("service('encrypter')->encrypt", $source);
    }

    public function testAjaxTestFormOptOutsOfGlobalSubmitStateAndRestoresButton(): void
    {
        $source = file_get_contents(APPPATH . 'Views/partials/_email_settings.php');

        $this->assertNotFalse($source);
        $this->assertStringContainsString('data-allow-repeat-submit="true"', $source);
        $this->assertStringContainsString('.finally(function ()', $source);
        $this->assertStringContainsString('submit.disabled = false;', $source);
    }

    private function methodSource(ReflectionMethod $method): string
    {
        $lines = file((string) $method->getFileName());

        return implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
    }
}
