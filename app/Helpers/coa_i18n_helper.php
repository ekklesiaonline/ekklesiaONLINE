<?php

if (! function_exists('coa_name_label')) {
    /**
     * Resolve a localized chart account name by account code.
     */
    function coa_name_label(string $code, ?string $fallback = null): string
    {
        $labels = lang('ChartOfAccounts.name');
        if (is_array($labels) && isset($labels[$code]) && is_string($labels[$code]) && $labels[$code] !== '') {
            return $labels[$code];
        }

        return $fallback ?? $code;
    }
}

if (! function_exists('coa_category_label')) {
    /**
     * Resolve a localized chart account category label.
     */
    function coa_category_label(?string $category, ?string $fallback = null): string
    {
        $category = (string) ($category ?? '');
        $hierarchyLabels = lang('ChartAccountCategories.category');
        if ($category !== '' && is_array($hierarchyLabels) && isset($hierarchyLabels[$category])) {
            return (string) $hierarchyLabels[$category];
        }

        $labels = lang('ChartOfAccounts.category');
        if ($category !== '' && is_array($labels) && isset($labels[$category]) && is_string($labels[$category]) && $labels[$category] !== '') {
            return $labels[$category];
        }

        if ($fallback !== null && $fallback !== '') {
            return $fallback;
        }

        if ($category === '') {
            return '';
        }

        return ucwords(str_replace(['_', '-'], ' ', $category));
    }
}

if (! function_exists('coa_description_label')) {
    /**
     * Resolve a localized chart account description by account code.
     */
    function coa_description_label(string $code, ?string $fallback = null): string
    {
        $labels = lang('ChartOfAccounts.description');
        if (is_array($labels) && isset($labels[$code]) && is_string($labels[$code]) && $labels[$code] !== '') {
            return $labels[$code];
        }

        return $fallback ?? '';
    }
}
