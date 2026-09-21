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
        $this->assertSame(
            'EMAIL_AUTHENTICATION_FAILED',
            $method->invoke($service, '550 5.7.30 Message blocked because DKIM authentication did not pass.')
        );

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
        $this->assertStringContainsString('csrfInput.value = csrfMeta.content;', $source);
        $this->assertStringContainsString('csrfInput.value = newToken;', $source);
        $this->assertStringNotContainsString("session()->has('success')", $source);
        $this->assertStringNotContainsString("session()->has('error')", $source);

        $traitSource = file_get_contents(APPPATH . 'Controllers/EmailSettingsTrait.php');
        $this->assertNotFalse($traitSource);
        $this->assertStringContainsString("setHeader(config('Security')->headerName, csrf_hash())", $traitSource);
        $this->assertStringContainsString('SMTP provider accepted', lang('Email.testSent'));
    }

    private function methodSource(ReflectionMethod $method): string
    {
        $lines = file((string) $method->getFileName());

        return implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
    }
}
