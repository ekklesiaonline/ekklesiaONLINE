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
        $this->assertSame(
            'RECIPIENT_POLICY_REJECTED',
            $method->invoke($service, '554 5.7.1 [BS01] Message rejected due to local policy.')
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
        $this->assertStringContainsString('input.value = csrfMeta.content;', $source);
        $this->assertStringContainsString('input.value = newToken;', $source);
        $this->assertStringNotContainsString("session()->has('success')", $source);
        $this->assertStringNotContainsString("session()->has('error')", $source);

        $traitSource = file_get_contents(APPPATH . 'Controllers/EmailSettingsTrait.php');
        $this->assertNotFalse($traitSource);
        $this->assertStringContainsString("setHeader(config('Security')->headerName, csrf_hash())", $traitSource);
        $this->assertStringContainsString('SMTP provider accepted', lang('Email.testSent'));
    }

    public function testSettingsSectionsRenderDisabledWithPerSectionEditToggles(): void
    {
        $source = file_get_contents(APPPATH . 'Views/partials/_email_settings.php');

        $this->assertNotFalse($source);
        $this->assertStringContainsString('data-email-section="identity"', $source);
        $this->assertStringContainsString('data-email-section="smtp"', $source);
        $this->assertStringContainsString('data-edit-toggle', $source);
        $this->assertStringContainsString('data-edit-actions', $source);
        $this->assertStringContainsString('data-edit-cancel', $source);
        $this->assertStringContainsString('name="section"', $source);
        $this->assertStringContainsString('data-editing="1"', $source);
    }

    public function testSendPathsClassifyDebuggerOutputInsteadOfExposingTranscripts(): void
    {
        $files = [
            APPPATH . 'Services/MessagingService.php',
            APPPATH . 'Services/AdminInvitationService.php',
            APPPATH . 'Services/MemberAccessService.php',
            APPPATH . 'Services/FollowupDispatchService.php',
            APPPATH . 'Services/EmailSettingsService.php',
            APPPATH . 'Commands/VolunteerRemind.php',
            APPPATH . 'Controllers/Auth/ScopedMagicLinkController.php',
            APPPATH . 'Authentication/ScopedEmailActivator.php',
            ROOTPATH . 'Worship/Controllers/ParochialReport.php',
        ];

        foreach ($files as $file) {
            $lines = file($file);
            $this->assertNotFalse($lines, "Cannot read {$file}");
            foreach ($lines as $i => $line) {
                if (str_contains($line, 'printDebugger')) {
                    $this->assertStringContainsString(
                        'classify',
                        $line,
                        "{$file} line " . ($i + 1) . ' handles raw debugger output'
                    );
                }
            }
        }
    }

    private function methodSource(ReflectionMethod $method): string
    {
        $lines = file((string) $method->getFileName());

        return implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
    }
}
