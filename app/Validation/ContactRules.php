<?php

namespace App\Validation;

use App\Libraries\AddressHelper;
use App\Libraries\PhoneHelper;

class ContactRules
{
    public function valid_calling_code(?string $value, ?string &$error = null): bool
    {
        $valid = $value !== null
            && $value !== ''
            && array_key_exists($value, PhoneHelper::callingCodeOptions());

        if (!$valid) {
            $error = lang('Profile.invalidCallingCode') ?: 'The selected phone country code is not valid.';
        }

        return $valid;
    }

    public function valid_country(?string $value, ?string &$error = null): bool
    {
        $valid = AddressHelper::isValidCountry($value);

        if (!$valid) {
            $error = lang('Profile.invalidCountry') ?: 'The selected country is not valid.';
        }

        return $valid;
    }

    /**
     * @param string|null $value The local phone number entered by the user.
     * @param string|null $param  Field name that holds the country code (default: phone_country_code).
     * @param array       $data   Full posted data; expects 'phone_country_code'.
     */
    public function valid_phone_e164(?string $value, ?string $param = null, array $data = [], ?string &$error = null): bool
    {
        $codeField = $param ?: 'phone_country_code';
        $callingCode = $data[$codeField] ?? null;

        if ($callingCode === null) {
            $callingCode = service('request')->getPost($codeField);
        }

        $e164 = PhoneHelper::toE164($callingCode, $value);

        $valid = $e164 !== null && PhoneHelper::isValid($e164);

        if (!$valid) {
            $error = lang('Profile.invalidPhone') ?: 'The phone number is not valid for the selected country code.';
        }

        return $valid;
    }
}
