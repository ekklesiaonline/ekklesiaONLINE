<?php

if (! function_exists('format_localized_date')) {
    /**
     * Format a date value using the current locale via IntlDateFormatter.
     *
     * Falls back to 'Y-m-d' when intl is not available. Returns '-' for
     * null/empty/zero dates.
     */
    function format_localized_date(?string $value, string $pattern = 'MMM d, yyyy'): string
    {
        if ($value === null || trim($value) === '' || $value === '0000-00-00') {
            return '-';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return (string) $value;
        }

        $locale = service('request')->getLocale() ?: config('App')->defaultLocale;

        if (class_exists(\IntlDateFormatter::class)) {
            $formatter = new \IntlDateFormatter($locale, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE, date_default_timezone_get());
            $formatter->setPattern($pattern);
            $formatted = $formatter->format($timestamp);
            if (is_string($formatted) && $formatted !== '') {
                return $formatted;
            }
        }

        return date('Y-m-d', $timestamp);
    }
}

if (! function_exists('format_currency')) {
    /**
     * Format a numeric amount as a plain decimal string.
     *
     * Uses number_format with 2 decimal places. Returns '0.00' for
     * null/empty values. Locale-aware currency symbol formatting should
     * be done in the view layer if needed.
     */
    function format_currency($amount): string
    {
        return number_format((float) ($amount ?? 0), 2);
    }
}
