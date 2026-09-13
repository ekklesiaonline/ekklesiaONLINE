<?php

namespace App\Libraries;

use Throwable;

class DataEncryption
{
    private const PREFIX = 'enc:v1:';

    public static function isEncrypted(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        return str_starts_with($value, self::PREFIX);
    }

    public static function encrypt(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return $value;
        }

        if (self::isEncrypted($value)) {
            return $value;
        }

        $cipher = service('encrypter')->encrypt($value);

        return self::PREFIX . base64_encode($cipher);
    }

    public static function decrypt(?string $value): ?string
    {
        if ($value === null || $value === '' || !self::isEncrypted($value)) {
            return $value;
        }

        $payload = substr($value, strlen(self::PREFIX));
        if ($payload === false || $payload === '') {
            return null;
        }

        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            return null;
        }

        try {
            $plain = service('encrypter')->decrypt($decoded);
            return is_string($plain) ? $plain : null;
        } catch (Throwable $e) {
            log_message('error', 'Failed to decrypt protected field: {message}', ['message' => $e->getMessage()]);
            return null;
        }
    }

    public static function blindIndex(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = mb_strtolower(trim($value));
        if ($normalized === '') {
            return null;
        }

        // Prefer a dedicated blind-index key; fall back to the data-encryption key.
        // Never fall back to a hardcoded literal — a public constant key would make
        // every phone/email blind index trivially forgeable and enumerable.
        $key = (string) (env('encryption.blindIndexKey') ?: env('encryption.key') ?: '');
        if ($key === '') {
            throw new \RuntimeException(
                'No blind-index key configured. Set encryption.blindIndexKey (or encryption.key) in .env.'
            );
        }

        return hash_hmac('sha256', $normalized, $key);
    }

    public static function phoneIndex(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if ($digits === '') {
            return null;
        }

        return self::blindIndex($digits);
    }

    public static function emailIndex(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return self::blindIndex($value);
    }
}
