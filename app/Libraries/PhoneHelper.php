<?php

namespace App\Libraries;

class PhoneHelper
{
    /**
     * Common international direct dialling (IDD) prefixes for the regions this
     * application serves. Keys are the E.164 calling code (including the leading +);
     * values are a human-readable label.
     */
    public static function callingCodeOptions(): array
    {
        return [
            '+1'   => 'North America / Caribbean (+1)',
            '+54'  => 'Argentina (+54)',
            '+55'  => 'Brazil (+55)',
            '+56'  => 'Chile (+56)',
            '+57'  => 'Colombia (+57)',
            '+58'  => 'Venezuela (+58)',
            '+34'  => 'Spain (+34)',
            '+33'  => 'France (+33)',
            '+49'  => 'Germany (+49)',
            '+44'  => 'United Kingdom (+44)',
            '+39'  => 'Italy (+39)',
            '+52'  => 'Mexico (+52)',
            '+51'  => 'Peru (+51)',
            '+593' => 'Ecuador (+593)',
            '+506' => 'Costa Rica (+506)',
            '+503' => 'El Salvador (+503)',
            '+502' => 'Guatemala (+502)',
            '+504' => 'Honduras (+504)',
            '+505' => 'Nicaragua (+505)',
            '+507' => 'Panama (+507)',
        ];
    }

    /**
     * Combine a calling code and a local number into a full E.164 string.
     * The local number is stripped of all non-digits and any leading national
     * trunk prefix (0) before being combined.
     */
    public static function toE164(?string $callingCode, ?string $localNumber): ?string
    {
        $callingCode = self::normalizeCallingCode($callingCode);
        $localNumber = self::digits($localNumber);

        if ($callingCode === null) {
            return null;
        }

        if ($localNumber === null) {
            return null;
        }

        $localNumber = ltrim($localNumber, '0');

        if ($localNumber === '') {
            return null;
        }

        $codeDigits = ltrim($callingCode, '+');

        if (str_starts_with($localNumber, $codeDigits)) {
            return '+' . $localNumber;
        }

        return $callingCode . $localNumber;
    }

    /**
     * Split a full E.164 number into [callingCode, localNumber].
     * Returns [null, $fullNumber] if no known calling code matches.
     */
    public static function fromE164(?string $fullNumber): array
    {
        if ($fullNumber === null || trim($fullNumber) === '') {
            return [null, null];
        }

        $fullNumber = trim($fullNumber);
        $codes = array_keys(self::callingCodeOptions());
        usort($codes, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($codes as $code) {
            if (str_starts_with($fullNumber, $code)) {
                return [$code, substr($fullNumber, strlen($code))];
            }
        }

        return [null, $fullNumber];
    }

    /**
     * Format a raw phone value for display. If the value already looks like an
     * E.164 number with a known calling code, the code is split out with a space.
     */
    public static function display(?string $phone): string
    {
        if ($phone === null || trim($phone) === '') {
            return '';
        }

        [$code, $local] = self::fromE164($phone);

        if ($code === null) {
            return $phone;
        }

        return $code . ' ' . $local;
    }

    /**
     * Return a plain digit string, or null if there are no digits.
     */
    public static function digits(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return $digits === '' ? null : $digits;
    }

    /**
     * Validate that a full phone number is a plausible E.164 number.
     */
    public static function isValid(?string $e164): bool
    {
        if ($e164 === null || trim($e164) === '') {
            return true;
        }

        if (!str_starts_with($e164, '+')) {
            return false;
        }

        $digits = self::digits($e164);

        return $digits !== null && strlen($digits) >= 7 && strlen($digits) <= 15;
    }

    private static function normalizeCallingCode(?string $code): ?string
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        $code = trim($code);

        if (!str_starts_with($code, '+')) {
            $code = '+' . $code;
        }

        return in_array($code, array_keys(self::callingCodeOptions()), true) ? $code : null;
    }
}
