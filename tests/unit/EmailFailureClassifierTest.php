<?php

use App\Libraries\Email\GmailApiException;
use App\Libraries\EmailFailureClassifier;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class EmailFailureClassifierTest extends CIUnitTestCase
{
    public function testGmailMarkersWinOverSmtpPatterns(): void
    {
        $this->assertSame(
            'GMAIL_GRANT_INVALID',
            EmailFailureClassifier::classify('sendFailureGmail_api GMAIL_GRANT_INVALID 535 noise')
        );
    }

    public function testAllGmailMarkersClassify(): void
    {
        foreach ([
            'GMAIL_GRANT_INVALID',
            'GMAIL_SCOPE_INSUFFICIENT',
            'GMAIL_RATE_LIMITED',
            'GMAIL_API_UNREACHABLE',
            'GMAIL_API_ERROR',
            'GMAIL_SENDER_NOT_PERMITTED',
        ] as $marker) {
            $this->assertSame($marker, EmailFailureClassifier::classify('prefix ' . $marker . ' suffix'));
        }
    }

    public function testClassifyGoogleApiErrorByHttpStatus(): void
    {
        $this->assertSame('GMAIL_GRANT_INVALID', EmailFailureClassifier::classifyGoogleApiError(new Exception('boom', 401)));
        $this->assertSame('GMAIL_SCOPE_INSUFFICIENT', EmailFailureClassifier::classifyGoogleApiError(new Exception('boom', 403)));
        $this->assertSame('GMAIL_RATE_LIMITED', EmailFailureClassifier::classifyGoogleApiError(new Exception('boom', 429)));
        $this->assertSame('GMAIL_API_ERROR', EmailFailureClassifier::classifyGoogleApiError(new Exception('boom', 500)));
        $this->assertSame('GMAIL_API_ERROR', EmailFailureClassifier::classifyGoogleApiError(new Exception('boom', 503)));
    }

    public function testClassifyGoogleApiErrorByMessage(): void
    {
        $this->assertSame('GMAIL_GRANT_INVALID', EmailFailureClassifier::classifyGoogleApiError(new Exception('invalid_grant: token revoked')));
        $this->assertSame('GMAIL_SCOPE_INSUFFICIENT', EmailFailureClassifier::classifyGoogleApiError(new Exception('Request had insufficient authentication scopes')));
        $this->assertSame('GMAIL_RATE_LIMITED', EmailFailureClassifier::classifyGoogleApiError(new Exception('User-rate limit exceeded')));
        $this->assertSame('GMAIL_RATE_LIMITED', EmailFailureClassifier::classifyGoogleApiError(new Exception('quota exceeded')));
        $this->assertSame('GMAIL_API_UNREACHABLE', EmailFailureClassifier::classifyGoogleApiError(new Exception('cURL error 28: Operation timed out')));
        $this->assertSame('GMAIL_API_UNREACHABLE', EmailFailureClassifier::classifyGoogleApiError(new Exception('Connection refused')));
        $this->assertSame('GMAIL_API_ERROR', EmailFailureClassifier::classifyGoogleApiError(new Exception('something else')));
    }

    public function testGmailApiExceptionPassesThrough(): void
    {
        $e = new GmailApiException('GMAIL_SENDER_NOT_PERMITTED');
        $this->assertSame('GMAIL_SENDER_NOT_PERMITTED', EmailFailureClassifier::classifyGoogleApiError($e));
    }

    public function testSmtpClassificationsUnchanged(): void
    {
        $this->assertSame('SMTP_AUTHENTICATION_FAILED', EmailFailureClassifier::classify('535 5.7.8 Username and password not accepted'));
        $this->assertSame('EMAIL_AUTHENTICATION_FAILED', EmailFailureClassifier::classify('550 5.7.30 DKIM did not pass'));
        $this->assertSame('RECIPIENT_POLICY_REJECTED', EmailFailureClassifier::classify('554 5.7.1 [BS01] local policy'));
        $this->assertSame('SMTP_CONNECTION_FAILED', EmailFailureClassifier::classify('Connection refused'));
        $this->assertSame('SMTP_TLS_FAILED', EmailFailureClassifier::classify('TLS handshake failed'));
        $this->assertSame('SMTP_SEND_FAILED', EmailFailureClassifier::classify('unknown failure'));
    }
}
