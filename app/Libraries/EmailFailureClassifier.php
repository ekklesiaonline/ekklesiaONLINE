<?php

namespace App\Libraries;

use App\Libraries\Email\GmailApiException;
use Throwable;

/**
 * EmailFailureClassifier
 *
 * Maps a CI4 Email debug transcript to a stable, redacted failure code.
 * The transcript itself must never be logged, stored, or returned to
 * callers — it can contain credentials, recipient addresses, message IDs,
 * and provider diagnostics.
 */
final class EmailFailureClassifier
{
    /**
     * Sanitized markers emitted by GmailApiEmail through setErrorMessage().
     * These are the only Gmail signals allowed into the debug transcript.
     */
    private const GMAIL_MARKERS = [
        'GMAIL_GRANT_INVALID',
        'GMAIL_SCOPE_INSUFFICIENT',
        'GMAIL_RATE_LIMITED',
        'GMAIL_API_UNREACHABLE',
        'GMAIL_API_ERROR',
        'GMAIL_SENDER_NOT_PERMITTED',
    ];

    public static function classify(string $debuggerOutput): string
    {
        foreach (self::GMAIL_MARKERS as $marker) {
            if (stripos($debuggerOutput, $marker) !== false) {
                return $marker;
            }
        }

        $normalized = strtolower($debuggerOutput);

        if (str_contains($normalized, '535') || str_contains($normalized, 'failed to authenticate') || str_contains($normalized, 'username and password not accepted')) {
            return 'SMTP_AUTHENTICATION_FAILED';
        }

        if (str_contains($normalized, '550 5.7.30') || (str_contains($normalized, 'dkim') && str_contains($normalized, 'did not pass'))) {
            return 'EMAIL_AUTHENTICATION_FAILED';
        }

        if (str_contains($normalized, '[bs01]') || (str_contains($normalized, '554 5.7.1') && str_contains($normalized, 'local policy'))) {
            return 'RECIPIENT_POLICY_REJECTED';
        }

        if (str_contains($normalized, 'connection refused') || str_contains($normalized, 'unable to connect') || str_contains($normalized, 'timed out')) {
            return 'SMTP_CONNECTION_FAILED';
        }

        if (str_contains($normalized, 'tls') && (str_contains($normalized, 'failed') || str_contains($normalized, 'error'))) {
            return 'SMTP_TLS_FAILED';
        }

        return 'SMTP_SEND_FAILED';
    }

    /**
     * Classify a Google API / OAuth-layer throwable into a GMAIL_* code.
     * Only the resulting code may be surfaced — never the exception
     * message, which can carry provider diagnostics.
     */
    public static function classifyGoogleApiError(Throwable $e): string
    {
        if ($e instanceof GmailApiException) {
            return $e->failureCode;
        }

        $status = self::googleHttpStatus($e);
        $message = strtolower($e->getMessage());

        if ($status === 401
            || str_contains($message, 'invalid_grant')
            || str_contains($message, 'unauthorized')
            || str_contains($message, 'invalid credentials')
        ) {
            return 'GMAIL_GRANT_INVALID';
        }

        if ($status === 403 || str_contains($message, 'insufficient')) {
            return 'GMAIL_SCOPE_INSUFFICIENT';
        }

        if ($status === 429 || str_contains($message, 'rate limit') || str_contains($message, 'quota')) {
            return 'GMAIL_RATE_LIMITED';
        }

        if ($status !== null && $status >= 500) {
            return 'GMAIL_API_ERROR';
        }

        if (str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
            || str_contains($message, 'connection')
            || str_contains($message, 'could not resolve')
            || str_contains($message, 'network')
        ) {
            return 'GMAIL_API_UNREACHABLE';
        }

        return 'GMAIL_API_ERROR';
    }

    /**
     * Best-effort HTTP status extraction from Google/Guzzle exceptions.
     */
    private static function googleHttpStatus(Throwable $e): ?int
    {
        $code = $e->getCode();
        if (is_int($code) && $code >= 100 && $code < 600) {
            return $code;
        }

        // Google\Service\Exception exposes the HTTP status via getCode()
        // already; Guzzle RequestException may carry a response object.
        if (method_exists($e, 'getResponse')) {
            try {
                $response = $e->getResponse();
                if (is_object($response) && method_exists($response, 'getStatusCode')) {
                    $status = (int) $response->getStatusCode();
                    if ($status >= 100 && $status < 600) {
                        return $status;
                    }
                }
            } catch (Throwable) {
                // Ignore — status stays unknown.
            }
        }

        return null;
    }
}
