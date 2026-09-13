<?php

use App\Libraries\DataEncryption;

/**
 * parse_template_vars()
 *
 * Build the full token-to-value replacement map for a guest + parish context.
 *
 * @param  array       $guest          Decrypted guest row (keys: first_name, last_name, phone, address, interests…)
 * @param  array       $parishSettings Flat key→value map (keys: clergy_name, service_time, parish_address, …)
 *                                     Typically produced by ParishSettingModel::getParishSettings() → key_value flattened.
 * @param  array|null  $parish         Row from the `parishes` table (name, address, …).  If null, name falls back to
 *                                     parishSettings['parish_name'] or an empty string.
 * @param  array|null  $nextEvent      Upcoming event row (title, start_at columns from the `events` table). Nullable.
 * @return array<string,string>        Map of {token} → replacement value (both keys and values are plain strings).
 */
if (! function_exists('parse_template_vars')) {
    function parse_template_vars(array $guest, array $parishSettings = [], ?array $parish = null, ?array $nextEvent = null): array
    {
        // ---------------------------------------------------------------
        // Guest fields
        // ---------------------------------------------------------------
        $firstName = (string) ($guest['first_name'] ?? '');
        $lastName  = (string) ($guest['last_name']  ?? '');
        $fullName  = trim($firstName . ' ' . $lastName);

        // Phone may be encrypted
        $phone = (string) ($guest['phone'] ?? '');
        if ($phone !== '' && class_exists(DataEncryption::class) && DataEncryption::isEncrypted($phone)) {
            $phone = (string) (DataEncryption::decrypt($phone) ?? '');
        }

        // ---------------------------------------------------------------
        // Parish fields — prefer the `parishes` row, fall back to settings
        // ---------------------------------------------------------------
        $parishName    = (string) ($parish['name']    ?? $parishSettings['parish_name']    ?? '');
        $parishAddress = (string) ($parish['address'] ?? $parishSettings['parish_address'] ?? '');

        // ---------------------------------------------------------------
        // Settings-backed tokens
        // ---------------------------------------------------------------
        $clergyName  = (string) ($parishSettings['clergy_name'] ?? $parishSettings['pastor_name'] ?? '');
        $serviceTime = (string) ($parishSettings['service_time'] ?? '');

        // parish_address from settings can override the `parishes.address` column
        if (isset($parishSettings['parish_address']) && $parishSettings['parish_address'] !== '') {
            $parishAddress = (string) $parishSettings['parish_address'];
        }

        // ---------------------------------------------------------------
        // Next event
        // ---------------------------------------------------------------
        $nextEventName = '';
        $eventDate     = '';
        if ($nextEvent !== null) {
            $nextEventName = (string) ($nextEvent['title'] ?? '');
            $rawStart      = (string) ($nextEvent['start_at'] ?? '');
            if ($rawStart !== '') {
                $ts = strtotime($rawStart);
                $eventDate = $ts !== false ? date('F j, Y', $ts) : $rawStart;
            }
        }

        // ---------------------------------------------------------------
        // Interests (from guest.interests JSON)
        //
        // If the guest has:  {"children": true, "youth": true, "music": false}
        // or a plain array:  ["children", "music"]
        //
        // We expose tokens like:
        //   {interest_children}  → "Children's Ministry"
        //   {interest_music}     → "Music"
        //   {interests_list}     → comma-joined list of active interest labels
        // ---------------------------------------------------------------
        $interestTokens = [];
        $interestLabels  = [];

        $rawInterests = $guest['interests'] ?? null;
        if (is_string($rawInterests) && $rawInterests !== '') {
            $decoded = json_decode($rawInterests, true);
            if (is_array($decoded)) {
                $rawInterests = $decoded;
            }
        }

        if (is_array($rawInterests)) {
            foreach ($rawInterests as $key => $value) {
                // Support both {"key": true} and ["key"] forms
                if (is_int($key)) {
                    // Indexed array — the value is the interest slug
                    $slug   = (string) $value;
                    $active = true;
                } else {
                    $slug   = (string) $key;
                    $active = (bool) $value;
                }

                if ($slug === '') {
                    continue;
                }

                $label = _interest_label($slug);

                $interestTokens['{interest_' . $slug . '}'] = $active ? $label : '';

                if ($active) {
                    $interestLabels[] = $label;
                }
            }
        }

        // ---------------------------------------------------------------
        // Base map
        // ---------------------------------------------------------------
        $map = [
            '{first_name}'    => $firstName,
            '{last_name}'     => $lastName,
            '{full_name}'     => $fullName,
            '{parish_name}'   => $parishName,
            '{clergy_name}'   => $clergyName,
            '{service_time}'  => $serviceTime,
            '{parish_address}' => $parishAddress,
            '{next_event_name}' => $nextEventName,
            '{event_date}'    => $eventDate,
            '{visit_date}'    => (string) ($guest['visit_date'] ?? ''),
            '{phone}'         => $phone,
            '{interests_list}' => implode(', ', $interestLabels),
        ];

        return array_merge($map, $interestTokens);
    }
}

/**
 * parse_template()
 *
 * Replace all {tokens} in $templateBody with values derived from the
 * supplied guest, parish settings, and optional next-event row.
 *
 * @param  string      $templateBody   Raw template text containing {tokens}.
 * @param  array       $guest          Decrypted guest row.
 * @param  array       $parishSettings Flat key→value settings map.
 * @param  array|null  $parish         Optional `parishes` table row.
 * @param  array|null  $nextEvent      Optional upcoming event row (title, start_at).
 * @return string                      Template with all known tokens replaced.
 */
if (! function_exists('parse_template')) {
    function parse_template(string $templateBody, array $guest, array $parishSettings = [], ?array $parish = null, ?array $nextEvent = null): string
    {
        $vars = parse_template_vars($guest, $parishSettings, $parish, $nextEvent);
        return str_replace(array_keys($vars), array_values($vars), $templateBody);
    }
}

/**
 * Return all tokens supported by parse_template() with human-readable descriptions.
 * Used for template editor hint text.
 *
 * @return array<string,string>  [ '{token}' => 'Description' ]
 */
if (! function_exists('available_template_tokens')) {
    function available_template_tokens(): array
    {
        return [
            '{first_name}'     => "Guest's first name",
            '{last_name}'      => "Guest's last name",
            '{full_name}'      => "Guest's full name",
            '{parish_name}'    => "Parish name",
            '{clergy_name}'    => "Clergy name (from parish settings)",
            '{service_time}'   => "Main service time (from parish settings)",
            '{parish_address}' => "Parish address (from parish settings or parishes table)",
            '{next_event_name}' => "Upcoming event title",
            '{event_date}'     => "Upcoming event date",
            '{visit_date}'     => "Guest's visit date",
            '{phone}'          => "Guest's phone number",
            '{interests_list}' => "Comma-separated list of guest interests",
            '{interest_*}'     => "Individual interest flag, e.g. {interest_children}",
        ];
    }
}

// -----------------------------------------------------------------------
// Private label resolver — not exposed as a global to avoid collisions
// -----------------------------------------------------------------------
if (! function_exists('_interest_label')) {
    /** @internal */
    function _interest_label(string $slug): string
    {
        static $map = [
            'children'    => "Children's Ministry",
            'youth'       => 'Youth Ministry',
            'music'       => 'Music / Worship',
            'missions'    => 'Missions',
            'small_groups' => 'Small Groups',
            'prayer'      => 'Prayer Team',
            'serving'     => 'Serving / Volunteerism',
            'mens'        => "Men's Ministry",
            'women'       => "Women's Ministry",
            'seniors'     => "Seniors' Ministry",
            'counseling'  => 'Counseling & Support',
            'evangelism'  => 'Evangelism',
        ];

        if (isset($map[$slug])) {
            return $map[$slug];
        }

        // Fallback: convert snake_case to Title Case
        return ucwords(str_replace('_', ' ', $slug));
    }
}
