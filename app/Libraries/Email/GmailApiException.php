<?php

namespace App\Libraries\Email;

use RuntimeException;

/**
 * GmailApiException
 *
 * Carries a stable, redacted GMAIL_* failure code for the Gmail API
 * transport. The message must never contain tokens, auth codes, response
 * bodies, or recipient addresses — only the failure code and an optional
 * safe detail string.
 */
class GmailApiException extends RuntimeException
{
    public function __construct(
        public readonly string $failureCode,
        string $detail = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($detail !== '' ? $detail : $failureCode, $code, $previous);
    }
}
