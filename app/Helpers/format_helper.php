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

if (! function_exists('csv_safe_row')) {
    /**
     * Neutralize CSV formula injection: prefix cells whose first character is
     * =, +, -, @, tab, or CR with a single quote so spreadsheet applications
     * render them as text instead of formulas.
     *
     * @param array<int, mixed> $row
     * @return array<int, mixed>
     */
    function csv_safe_row(array $row): array
    {
        foreach ($row as $i => $cell) {
            if (! is_string($cell) || $cell === '') {
                continue;
            }
            // Plain signed numbers (e.g. negative amounts) are not formulas.
            if (preg_match('/^-?\d+(\.\d+)?$/', $cell) === 1) {
                continue;
            }
            // Formulas may hide behind leading whitespace.
            $trimmed = ltrim($cell);
            if ($trimmed === '') {
                continue;
            }
            if (in_array($trimmed[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                $row[$i] = "'" . $cell;
            }
        }

        return $row;
    }
}

if (! function_exists('fputcsv_safe')) {
    /**
     * fputcsv() with formula-injection escaping applied to every string cell.
     *
     * @param resource $handle
     * @param array<int, mixed> $row
     */
    function fputcsv_safe($handle, array $row, string $separator = ',', string $enclosure = '"', string $escape = '\\', string $eol = "\n"): int|false
    {
        return fputcsv($handle, csv_safe_row($row), $separator, $enclosure, $escape, $eol);
    }
}
