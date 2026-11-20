<?php

use App\Libraries\Email\GmailApiEmail;
use App\Libraries\Email\GmailApiException;
use App\Services\GmailOAuthService;
use CodeIgniter\Email\Email;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Email as EmailConfig;

/**
 * GmailApiEmail transport tests — no live Google calls; the OAuth service
 * is replaced by an in-memory fake.
 *
 * @internal
 */
final class GmailApiEmailTest extends CIUnitTestCase
{
    private FakeGmailOAuthService $oauth;

    protected function setUp(): void
    {
        parent::setUp();
        $this->oauth = new FakeGmailOAuthService();
    }

    private function mailer(string $from = 'sender@example.test'): GmailApiEmail
    {
        $mailer = new GmailApiEmail(new EmailConfig());
        $mailer->initialize([
            'protocol' => 'gmail_api',
            'mailType' => 'text',
        ]);
        $mailer->setGmailTransport($this->oauth, 'parish', 5);
        $mailer->setFrom($from, 'Test Sender');
        $mailer->setTo('recipient@example.test');
        $mailer->setSubject('Hello');
        $mailer->setMessage('Plain body');

        return $mailer;
    }

    public function testSendSubmitsBase64UrlRfc822Message(): void
    {
        $mailer = $this->mailer();

        $this->assertTrue($mailer->send(false));
        $this->assertCount(1, $this->oauth->sentMessages);

        $sent = $this->oauth->sentMessages[0];
        $this->assertSame('parish', $sent['tier']);
        $this->assertSame(5, $sent['id']);

        // base64url decode must yield a valid RFC 822 message.
        $raw = base64_decode(strtr($sent['raw'], '-_', '+/'), true);
        $this->assertIsString($raw);
        $this->assertStringContainsString('From:', $raw);
        $this->assertStringContainsString('sender@example.test', $raw);
        $this->assertStringContainsString('To:', $raw);
        $this->assertStringContainsString('recipient@example.test', $raw);
        $this->assertStringContainsString('Subject:', $raw);
        $this->assertStringContainsString('Plain body', $raw);
    }

    public function testBase64UrlEncodingHasNoPadding(): void
    {
        $mailer = $this->mailer();
        $this->assertTrue($mailer->send(false));

        $raw = $this->oauth->sentMessages[0]['raw'];
        $this->assertDoesNotMatchRegularExpression('/[+\/=]/', $raw);
        $this->assertStringNotContainsString('=', $raw);
    }

    public function testFromMismatchFailsClosed(): void
    {
        $this->oauth->accountEmailValue = 'other@example.test';
        $mailer = $this->mailer('sender@example.test');

        $this->assertFalse($mailer->send(false));
        $this->assertCount(0, $this->oauth->sentMessages);
        $this->assertStringContainsString('GMAIL_SENDER_NOT_PERMITTED', (string) $mailer->printDebugger(['headers']));
    }

    public function testMissingGrantFailsClosed(): void
    {
        $this->oauth->sendException = new GmailApiException('GMAIL_GRANT_INVALID');
        $mailer = $this->mailer();

        $this->assertFalse($mailer->send(false));
        $this->assertStringContainsString('GMAIL_GRANT_INVALID', (string) $mailer->printDebugger(['headers']));
    }

    public function testApiFailureSurfacesClassifiedMarker(): void
    {
        $this->oauth->sendException = new GmailApiException('GMAIL_RATE_LIMITED');
        $mailer = $this->mailer();

        $this->assertFalse($mailer->send(false));
        $debugger = (string) $mailer->printDebugger(['headers']);
        $this->assertStringContainsString('GMAIL_RATE_LIMITED', $debugger);
        // Sanitized marker only — no exception payload leaks.
        $this->assertStringNotContainsString('refresh_token', $debugger);
    }

    public function testUnclassifiedThrowableIsNormalized(): void
    {
        $this->oauth->sendException = new RuntimeException('weird google failure');
        $mailer = $this->mailer();

        $this->assertFalse($mailer->send(false));
        $this->assertStringContainsString('GMAIL_API_ERROR', (string) $mailer->printDebugger(['headers']));
    }

    public function testGmailApiProtocolIsNotCoercedToMail(): void
    {
        $mailer = new GmailApiEmail(new EmailConfig());
        $mailer->initialize(['protocol' => 'gmail_api']);

        // The subclass whitelist must keep 'gmail_api' — getProtocol() would
        // otherwise silently coerce it to 'mail' (sendmail).
        $method = new ReflectionMethod(Email::class, 'getProtocol');
        $method->setAccessible(true);
        $this->assertSame('gmail_api', $method->invoke($mailer));
    }

    public function testSendWithoutTransportBindingFailsClosed(): void
    {
        $mailer = new GmailApiEmail(new EmailConfig());
        $mailer->initialize(['protocol' => 'gmail_api', 'mailType' => 'text']);
        $mailer->setFrom('sender@example.test');
        $mailer->setTo('recipient@example.test');
        $mailer->setMessage('Body');

        $this->assertFalse($mailer->send(false));
        $this->assertStringContainsString('GMAIL_GRANT_INVALID', (string) $mailer->printDebugger(['headers']));
    }

    public function testMultipartAlternativeAndAttachmentCompile(): void
    {
        $mailer = new GmailApiEmail(new EmailConfig());
        $mailer->initialize(['protocol' => 'gmail_api', 'mailType' => 'html']);
        $mailer->setGmailTransport($this->oauth, 'diocese', 9);
        $mailer->setFrom('sender@example.test');
        $mailer->setTo('recipient@example.test');
        $mailer->setSubject('Multipart');
        $mailer->setMessage('<p>HTML body</p>');
        $mailer->setAltMessage('Plain body');

        $tmp = tempnam(sys_get_temp_dir(), 'gmailatt') . '.txt';
        file_put_contents($tmp, 'attachment-bytes');
        $mailer->attach($tmp, 'attachment', 'notes.txt');

        try {
            $this->assertTrue($mailer->send(false));
        } finally {
            @unlink($tmp);
        }

        $raw = base64_decode(strtr($this->oauth->sentMessages[0]['raw'], '-_', '+/'), true);
        $this->assertIsString($raw);
        $this->assertStringContainsString('multipart/mixed', $raw);
        $this->assertStringContainsString('multipart/alternative', $raw);
        $this->assertStringContainsString('HTML body', $raw);
        $this->assertStringContainsString('Plain body', $raw);
        $this->assertStringContainsString('notes.txt', $raw);
        // Attachment payload is base64-encoded inside the MIME part.
        $this->assertStringContainsString(base64_encode('attachment-bytes'), $raw);
    }
}

// ------------------------------------------------------------------
// Test doubles
// ------------------------------------------------------------------

class FakeGmailOAuthService extends GmailOAuthService
{
    public ?string $accountEmailValue = 'sender@example.test';
    public ?\Throwable $sendException = null;

    /** @var list<array{tier: string, id: ?int, raw: string}> */
    public array $sentMessages = [];

    public function __construct()
    {
        // Skip parent constructor — no ConfigurationService needed.
    }

    public function accountEmail(string $tier, ?int $id): ?string
    {
        return $this->accountEmailValue;
    }

    public function sendRawMessage(string $tier, ?int $id, string $base64urlRaw): void
    {
        if ($this->sendException !== null) {
            throw $this->sendException;
        }

        $this->sentMessages[] = ['tier' => $tier, 'id' => $id, 'raw' => $base64urlRaw];
    }
}
