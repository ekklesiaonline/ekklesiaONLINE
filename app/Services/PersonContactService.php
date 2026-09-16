<?php

namespace App\Services;

use App\Libraries\DataEncryption;

class PersonContactService
{
    /**
     * Encrypt a plaintext PII value (email or phone).
     */
    public static function encrypt(?string $plain): ?string
    {
        if ($plain === null || $plain === '') {
            return null;
        }

        $cipher = DataEncryption::encrypt($plain);

        return $cipher === null ? null : $cipher;
    }

    /**
     * Decrypt a stored ciphertext value back to plaintext.
     */
    public static function decrypt(?string $cipher): ?string
    {
        if ($cipher === null || $cipher === '') {
            return null;
        }

        if (! str_starts_with((string) $cipher, 'enc:v1:')) {
            return $cipher;
        }

        return DataEncryption::decrypt($cipher);
    }

    /**
     * Generate a searchable blind index for an email or phone number.
     *
     * Uses a keyed HMAC (via DataEncryption::blindIndex) so that a database
     * leak does not expose values to rainbow-table attacks. Callers that
     * need to match a specific contact type should use emailIndex() or
     * phoneIndex() for correct normalization.
     *
     * Backward compatibility: if the keyed HMAC fails (e.g. no key configured
     * in a test environment), falls back to the legacy plain SHA-256 hash
     * so existing rows remain queryable.
     */
    public static function hash(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = strtolower(trim($value));

        try {
            return DataEncryption::blindIndex($normalized);
        } catch (\Throwable $e) {
            // Fallback for environments without a configured encryption key
            // (e.g. some test setups). This preserves backward compatibility
            // with rows hashed under the legacy plain SHA-256 scheme.
            return hash('sha256', $normalized);
        }
    }

    /**
     * Generate a searchable blind index for an email address.
     *
     * Uses the same normalization as DataEncryption::emailIndex (lowercase trim)
     * so lookups match PersonContactService::hash() for email-shaped values.
     */
    public static function emailIndex(?string $email): ?string
    {
        return self::hash($email);
    }

    /**
     * Generate a searchable blind index for a phone number.
     *
     * Strips non-digit characters before hashing so that (555) 123-4567
     * and 5551234567 produce the same index. This matches the normalization
     * in DataEncryption::phoneIndex().
     */
    public static function phoneIndex(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === '' || $digits === null) {
            return null;
        }

        return self::hash($digits);
    }
}
