<?php

namespace App\Libraries;

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
    public static function classify(string $debuggerOutput): string
    {
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
}
