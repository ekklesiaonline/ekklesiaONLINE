<?php

if (! function_exists('fund_name_label')) {
    /**
     * Resolve a localized label for seeded default fund names.
     * Custom user-entered fund names are returned unchanged.
     */
    function fund_name_label(?string $name): string
    {
        $name = trim((string) ($name ?? ''));
        if ($name === '') {
            return '';
        }

        $map = [
            'Unrestricted Operating Fund' => 'Finance.fundNameUnrestrictedOperating',
            'Building and Facility Fund'  => 'Finance.fundNameBuildingFacility',
            'Mission and Outreach Fund'   => 'Finance.fundNameMissionOutreach',
            'Youth and Formation Fund'    => 'Finance.fundNameYouthFormation',
            'Endowment Fund'              => 'Finance.fundNameEndowment',
            // Common seed/demo aliases
            'Demo General Fund'           => 'Finance.fundNameDemoGeneral',
            'General Fund'                => 'Finance.fundNameGeneral',
            'Building Fund'               => 'Finance.fundNameBuilding',
            'Mission Fund'                => 'Finance.fundNameMission',
            'Youth Fund'                  => 'Finance.fundNameYouth',
        ];

        $key = $map[$name] ?? null;
        if ($key === null) {
            return $name;
        }

        $translated = lang($key);
        return is_string($translated) && $translated !== '' ? $translated : $name;
    }
}

if (! function_exists('expense_description_label')) {
    /**
     * Resolve localized aliases for known seeded expense descriptions.
     * Custom user-entered descriptions are returned unchanged.
     */
    function expense_description_label(?string $description): string
    {
        $description = trim((string) ($description ?? ''));
        if ($description === '') {
            return '';
        }

        $map = [
            'Demo utility expense' => 'Finance.expenseDescDemoUtility',
        ];

        $key = $map[$description] ?? null;
        if ($key === null) {
            return $description;
        }

        $translated = lang($key);
        return is_string($translated) && $translated !== '' ? $translated : $description;
    }
}

if (! function_exists('expense_notes_label')) {
    /**
     * Resolve localized aliases for known seeded expense notes.
     * Custom user-entered notes are returned unchanged.
     */
    function expense_notes_label(?string $notes): string
    {
        $notes = trim((string) ($notes ?? ''));
        if ($notes === '') {
            return '';
        }

        $map = [
            'Demo module expense' => 'Finance.expenseNoteDemoModule',
        ];

        $key = $map[$notes] ?? null;
        if ($key === null) {
            return $notes;
        }

        $translated = lang($key);
        return is_string($translated) && $translated !== '' ? $translated : $notes;
    }
}
