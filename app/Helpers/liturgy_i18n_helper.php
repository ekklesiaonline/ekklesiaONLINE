<?php

if (! function_exists('service_type_label')) {
    /**
     * Localize canonical worship service type labels; keep custom values unchanged.
     */
    function service_type_label(?string $serviceType): string
    {
        $serviceType = trim((string) ($serviceType ?? ''));
        if ($serviceType === '') {
            return '';
        }

        $map = [
            'Holy Eucharist Rite I' => 'Services.holyEucharistRiteI',
            'Holy Eucharist Rite II' => 'Services.holyEucharistRiteII',
            'Morning Prayer Rite I' => 'Services.morningPrayerRiteI',
            'Morning Prayer Rite II' => 'Services.morningPrayerRiteII',
            'Evening Prayer Rite I' => 'Services.eveningPrayerRiteI',
            'Evening Prayer Rite II' => 'Services.eveningPrayerRiteII',
            'Compline' => 'Services.compline',
            'Noonday Prayer' => 'Services.noondayPrayer',
            'Baptism' => 'Services.baptism',
            'Confirmation' => 'Services.confirmation',
            'Marriage' => 'Services.marriage',
            'Burial' => 'Services.burial',
            'Healing Service' => 'Services.healingService',
            'Reconciliation' => 'Services.reconciliation',
            'Ash Wednesday' => 'Services.ashWednesday',
            'Maundy Thursday' => 'Services.maundyThursday',
            'Good Friday' => 'Services.goodFriday',
            'Holy Saturday' => 'Services.holySaturday',
            'Easter Vigil' => 'Services.easterVigil',
            'Ascension Day' => 'Services.ascensionDay',
            'Pentecost' => 'Services.pentecost',
            'All Saints' => 'Services.allSaints',
            'Christmas Eve' => 'Services.christmasEve',
            'Christmas Day' => 'Services.christmasDay',
            'Epiphany' => 'Services.epiphany',
            'Stations of the Cross' => 'Services.stationsOfTheCross',
            'Benediction' => 'Services.benediction',
            'Ordination' => 'Services.ordination',
            'Consecration' => 'Services.consecration',
            'Other Service' => 'Services.otherService',
        ];

        $key = $map[$serviceType] ?? null;
        if ($key === null) {
            return $serviceType;
        }

        $translated = lang($key);
        return is_string($translated) && $translated !== '' ? $translated : $serviceType;
    }
}

if (! function_exists('liturgical_day_label')) {
    /**
     * Localize canonical liturgical day labels; keep custom values unchanged.
     */
    function liturgical_day_label(?string $liturgicalDay): string
    {
        $liturgicalDay = trim((string) ($liturgicalDay ?? ''));
        if ($liturgicalDay === '') {
            return '';
        }

        $directMap = [
            'Christmas Eve' => 'Services.litDayChristmasEve',
            'Christmas Day' => 'Services.litDayChristmasDay',
            'Holy Name of Our Lord Jesus Christ' => 'Services.litDayHolyName',
            'Epiphany' => 'Services.litDayEpiphany',
            'The Baptism of Our Lord' => 'Services.litDayBaptismLord',
            'Last Sunday after the Epiphany' => 'Services.litDayLastSundayAfterEpiphany',
            'Ash Wednesday' => 'Services.litDayAshWednesday',
            'Palm Sunday' => 'Services.litDayPalmSunday',
            'Maundy Thursday' => 'Services.litDayMaundyThursday',
            'Good Friday' => 'Services.litDayGoodFriday',
            'Holy Saturday' => 'Services.litDayHolySaturday',
            'Easter Day' => 'Services.litDayEasterDay',
            'Ascension Day' => 'Services.litDayAscensionDay',
            'Day of Pentecost' => 'Services.litDayDayOfPentecost',
            'Trinity Sunday' => 'Services.litDayTrinitySunday',
            'Last Sunday after Pentecost (Feast of Christ the King)' => 'Services.litDayChristKing',
        ];

        $key = $directMap[$liturgicalDay] ?? null;
        if ($key !== null) {
            $translated = lang($key);
            if (is_string($translated) && $translated !== '') {
                return $translated;
            }
        }

        if (preg_match('/^(\d+)(?:st|nd|rd|th) Sunday of Advent$/', $liturgicalDay, $m) === 1) {
            return (string) lang('Services.litDaySundayOfAdvent', [(int) $m[1]]);
        }

        if (preg_match('/^(\d+)(?:st|nd|rd|th) Sunday after Christmas$/', $liturgicalDay, $m) === 1) {
            return (string) lang('Services.litDaySundayAfterChristmas', [(int) $m[1]]);
        }

        if (preg_match('/^(\d+)(?:st|nd|rd|th) Sunday after the Epiphany$/', $liturgicalDay, $m) === 1) {
            return (string) lang('Services.litDaySundayAfterEpiphany', [(int) $m[1]]);
        }

        if (preg_match('/^(\d+)(?:st|nd|rd|th) Sunday in Lent$/', $liturgicalDay, $m) === 1) {
            return (string) lang('Services.litDaySundayInLent', [(int) $m[1]]);
        }

        if (preg_match('/^(\d+)(?:st|nd|rd|th) Sunday of Easter$/', $liturgicalDay, $m) === 1) {
            return (string) lang('Services.litDaySundayOfEaster', [(int) $m[1]]);
        }

        if (preg_match('/^Proper\s+(\d+)\s+\(Sunday after Pentecost\)$/', $liturgicalDay, $m) === 1) {
            return (string) lang('Services.litDayProperAfterPentecost', [(int) $m[1]]);
        }

        return $liturgicalDay;
    }
}

if (! function_exists('liturgical_season_label')) {
    /**
     * Localize canonical liturgical season labels from source entries.
     */
    function liturgical_season_label(?string $season): string
    {
        $season = trim((string) ($season ?? ''));
        if ($season === '') {
            return '';
        }

        $map = [
            'Advent' => 'Diocesan.liturgicalSeasonAdvent',
            'Christmas' => 'Diocesan.liturgicalSeasonChristmas',
            'Epiphany' => 'Diocesan.liturgicalSeasonEpiphany',
            'Lent' => 'Diocesan.liturgicalSeasonLent',
            'Holy Week' => 'Diocesan.liturgicalSeasonHolyWeek',
            'Easter' => 'Diocesan.liturgicalSeasonEaster',
            'Pentecost' => 'Diocesan.liturgicalSeasonPentecost',
            'Season after Pentecost' => 'Diocesan.liturgicalSeasonAfterPentecost',
        ];

        $key = $map[$season] ?? null;
        if ($key === null) {
            return $season;
        }

        $translated = lang($key);
        return is_string($translated) && $translated !== '' ? $translated : $season;
    }
}

if (! function_exists('liturgical_color_label')) {
    /**
     * Localize canonical liturgical color labels.
     */
    function liturgical_color_label(?string $color): string
    {
        $color = strtolower(trim((string) ($color ?? '')));
        if ($color === '') {
            return '';
        }

        $map = [
            'blue' => 'Diocesan.liturgicalColorBlue',
            'purple' => 'Diocesan.liturgicalColorPurple',
            'white' => 'Diocesan.liturgicalColorWhite',
            'green' => 'Diocesan.liturgicalColorGreen',
            'red' => 'Diocesan.liturgicalColorRed',
            'rose' => 'Diocesan.liturgicalColorRose',
            'black' => 'Diocesan.liturgicalColorBlack',
            'gold' => 'Diocesan.liturgicalColorGold',
        ];

        $key = $map[$color] ?? null;
        if ($key === null) {
            return ucfirst($color);
        }

        $translated = lang($key);
        return is_string($translated) && $translated !== '' ? $translated : ucfirst($color);
    }
}

if (! function_exists('liturgy_format_localized')) {
    /**
     * Locale-aware formatter for date/time values used in liturgy surfaces.
     */
    function liturgy_format_localized(?string $value, string $pattern, string $fallbackPattern = 'Y-m-d H:i', ?string $locale = null): string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return '-';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        $resolvedLocale = $locale;
        if ($resolvedLocale === null || $resolvedLocale === '') {
            if (function_exists('service')) {
                try {
                    $request = service('request');
                    if (is_object($request) && method_exists($request, 'getLocale')) {
                        $resolvedLocale = (string) $request->getLocale();
                    }
                } catch (\Throwable $e) {
                    $resolvedLocale = '';
                }
            }

            if ($resolvedLocale === null || $resolvedLocale === '') {
                if (function_exists('config')) {
                    try {
                        $appConfig = config('App');
                        $resolvedLocale = is_object($appConfig) && isset($appConfig->defaultLocale)
                            ? (string) $appConfig->defaultLocale
                            : '';
                    } catch (\Throwable $e) {
                        $resolvedLocale = '';
                    }
                }
            }

            if ($resolvedLocale === null || $resolvedLocale === '') {
                $resolvedLocale = 'en';
            }
        }

        if (class_exists(\IntlDateFormatter::class)) {
            $formatter = new \IntlDateFormatter(
                $resolvedLocale,
                \IntlDateFormatter::MEDIUM,
                \IntlDateFormatter::SHORT,
                date_default_timezone_get()
            );
            $formatter->setPattern($pattern);
            $formatted = $formatter->format($timestamp);
            if (is_string($formatted) && $formatted !== '') {
                return $formatted;
            }
        }

        return date($fallbackPattern, $timestamp);
    }
}

if (! function_exists('liturgy_format_date')) {
    /**
     * Localized date-only format helper.
     */
    function liturgy_format_date(?string $value, ?string $locale = null): string
    {
        return liturgy_format_localized($value, 'MMM d, y', 'Y-m-d', $locale);
    }
}

if (! function_exists('liturgy_format_long_date')) {
    /**
     * Localized long date format helper.
     */
    function liturgy_format_long_date(?string $value, ?string $locale = null): string
    {
        return liturgy_format_localized($value, 'EEEE, MMMM d, y', 'Y-m-d', $locale);
    }
}

if (! function_exists('liturgy_format_time')) {
    /**
     * Localized time-only format helper.
     */
    function liturgy_format_time(?string $value, ?string $locale = null): string
    {
        return liturgy_format_localized($value, 'h:mm a', 'H:i', $locale);
    }
}
