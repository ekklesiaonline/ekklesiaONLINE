<?php

namespace App\Libraries;

class AddressHelper
{
    /**
     * ISO 3166-1 alpha-2 country list for the address dropdown. The values are
     * the common English names used by the application.
     */
    public static function countryOptions(): array
    {
        $countries = [
            'DO' => 'Dominican Republic',
            'AR' => 'Argentina',
            'BO' => 'Bolivia',
            'BR' => 'Brazil',
            'CA' => 'Canada',
            'CL' => 'Chile',
            'CO' => 'Colombia',
            'CR' => 'Costa Rica',
            'CU' => 'Cuba',
            'EC' => 'Ecuador',
            'SV' => 'El Salvador',
            'ES' => 'Spain',
            'FR' => 'France',
            'DE' => 'Germany',
            'GT' => 'Guatemala',
            'HT' => 'Haiti',
            'HN' => 'Honduras',
            'IT' => 'Italy',
            'JM' => 'Jamaica',
            'MX' => 'Mexico',
            'NI' => 'Nicaragua',
            'PA' => 'Panama',
            'PY' => 'Paraguay',
            'PE' => 'Peru',
            'PR' => 'Puerto Rico',
            'GB' => 'United Kingdom',
            'US' => 'United States',
            'UY' => 'Uruguay',
            'VE' => 'Venezuela',
        ];

        asort($countries);

        return $countries;
    }

    /**
     * Validate a country code is a known ISO 3166-1 alpha-2 value.
     */
    public static function isValidCountry(?string $code): bool
    {
        return $code !== null && $code !== '' && array_key_exists(strtoupper($code), self::countryOptions());
    }
}
