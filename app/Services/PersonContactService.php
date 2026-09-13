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
     * Generate a searchable hash for an email or phone number.
     */
    public static function hash(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return hash('sha256', strtolower(trim($value)));
    }
}
