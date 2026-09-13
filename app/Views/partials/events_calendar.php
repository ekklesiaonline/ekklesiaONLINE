<?php

/**
 * Shared events calendar partial.
 *
 * Designed after the Diocese events calendar. Accepts data from any scope
 * (system, province, diocese/parish, user) and normalizes it to a common
 * format for rendering.
 *
 * Expected variables (all optional unless noted):
 *  - calendar_mode        string  'month' | 'week' | 'agenda'
 *  - anchor_date          string  Y-m-d
 *  - period_label         string  Human readable period label
 *  - period_start         string  Y-m-d (used as fallback for period_label)
 *  - period_end           string  Y-m-d (used as fallback for period_label)
 *  - prev_date            string  Y-m-d
 *  - next_date            string  Y-m-d
 *  - month_weeks          array   Rich month grid: [ [ day, ... ], ... ]
 *  - weeks                array   Simple month grid: [ [ day, ... ], ... ]
 *  - week_days            array   Rich week grid: [ day, ... ]
 *  - agenda_rows          array   Rich agenda rows
 *  - day_buckets          array   Simple day buckets for agenda fallback
 *  - calendar_legend      array   [ [ 'name' => '', 'color' => '' ], ... ]
 *  - dioceses             array   Province scope dioceses for legend
 *  - diocese_colors       array   Province scope color map
 *  - calendar_base_url    string  Route for calendar navigation
 *  - calendar_base_query  array   Extra query params to preserve in calendar navigation
 *  - list_url             string  Route for list view
 *  - create_url           string  Route for create action
 *  - manage_calendars_url string  Route for calendar management
 *  - can_create_event     bool    Whether the create button is shown
 *  - can_manage_calendars bool    Whether the manage calendars button is shown
 *  - show_agenda          bool    Show the agenda mode button (default true)
 *  - weekday_header_order array   Order of weekday short names for column headers (default Mon..Sun)
 *  - create_button_label  string  Label for the create button (default Events.createEvent)
 *  - list_button_label    string  Label for the list view button (default Events.calendarListView)
 */

$fallbackAnchor = (new DateTimeImmutable())->format('Y-m-d');
$calendarMode = in_array((string) ($calendar_mode ?? 'month'), ['month', 'week', 'agenda'], true)
    ? (string) ($calendar_mode ?? 'month')
    : 'month';
$anchorDate = (string) ($anchor_date ?? $fallbackAnchor);

try {
    $anchorDateTime = new DateTimeImmutable($anchorDate);
} catch (\Throwable $e) {
    $anchorDateTime = new DateTimeImmutable($fallbackAnchor);
}

$monthContext = $calendarMode === 'month' ? $anchorDateTime->format('Y-m') : null;
$locale = service('request')->getLocale() ?: config('App')->defaultLocale;

$weekdayHeaderOrder = (array) ($weekday_header_order ?? ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']);
$showAgenda = (bool) ($show_agenda ?? true);
$createButtonLabel = (string) ($create_button_label ?? lang('Events.createEvent'));
$listButtonLabel = (string) ($list_button_label ?? lang('Events.calendarListView'));

$weekdayLabels = [
    'Mon' => lang('Events.dayMon'),
    'Tue' => lang('Events.dayTue'),
    'Wed' => lang('Events.dayWed'),
    'Thu' => lang('Events.dayThu'),
    'Fri' => lang('Events.dayFri'),
    'Sat' => lang('Events.daySat'),
    'Sun' => lang('Events.daySun'),
];

$statusClasses = [
    'published' => 'is-published',
    'draft' => 'is-draft',
    'completed' => 'is-completed',
    'cancelled' => 'is-cancelled',
];

$statusLangKeys = [
    'published' => 'Events.statusPublished',
    'draft' => 'Events.statusDraft',
    'completed' => 'Events.statusCompleted',
    'cancelled' => 'Events.statusCancelled',
];

$resolveStatusLabel = static function (string $status, array $statusLangKeys): string {
    if (isset($statusLangKeys[$status])) {
        $resolved = lang($statusLangKeys[$status]);
        if ($resolved !== $statusLangKeys[$status]) {
            return $resolved;
        }
    }
    return ucfirst($status);
};

$formatAgendaMonthYear = static function (string $dateValue, string $locale): string {
    $timestamp = strtotime($dateValue);
    if ($timestamp === false) {
        return '';
    }

    if (class_exists(\IntlDateFormatter::class)) {
        $formatter = new \IntlDateFormatter(
            $locale,
            \IntlDateFormatter::MEDIUM,
            \IntlDateFormatter::NONE,
            date_default_timezone_get()
        );
        $formatter->setPattern('MMM y');
        $formatted = $formatter->format($timestamp);
        if (is_string($formatted) && $formatted !== '') {
            return $formatted;
        }
    }

    return date('M Y', $timestamp);
};

$formatPeriodLabel = static function (string $start, string $end, string $mode) use ($locale): string {
    $startTs = strtotime($start);
    $endTs = strtotime($end);
    if ($startTs === false || $endTs === false) {
        return $start . ' — ' . $end;
    }

    if ($mode === 'month') {
        if (class_exists(\IntlDateFormatter::class)) {
            $formatter = new \IntlDateFormatter(
                $locale,
                \IntlDateFormatter::LONG,
                \IntlDateFormatter::NONE,
                date_default_timezone_get()
            );
            $formatter->setPattern('MMMM y');
            $formatted = $formatter->format($startTs);
            if (is_string($formatted) && $formatted !== '') {
                return $formatted;
            }
        }

        return date('F Y', $startTs);
    }

    if (date('Y-m', $startTs) === date('Y-m', $endTs)) {
        return date('M j', $startTs) . ' — ' . date('j, Y', $endTs);
    }

    if (date('Y', $startTs) === date('Y', $endTs)) {
        return date('M j', $startTs) . ' — ' . date('M j, Y', $endTs);
    }

    return date('M j, Y', $startTs) . ' — ' . date('M j, Y', $endTs);
};

$normalizeItem = static function (array $raw): array {
    $isAllDay = (bool) ($raw['is_all_day'] ?? 0);
    $startAt = (string) ($raw['start_at'] ?? '');

    if ($startAt === '') {
        $startAt = (string) ($raw['start_date'] ?? '') . ' 00:00:00';
    }

    $timeLabel = '';
    if ($isAllDay) {
        $timeLabel = lang('Events.allDayShort');
    } elseif ($startAt !== '') {
        $timeLabel = substr($startAt, 11, 5);
    }

    return [
        'id' => (int) ($raw['id'] ?? 0),
        'title' => (string) ($raw['title'] ?? ''),
        'status' => (string) ($raw['status'] ?? 'published'),
        'scope_level' => (string) ($raw['scope_level'] ?? 'parish'),
        'calendar_name' => (string) ($raw['calendar_name'] ?? $raw['source_label'] ?? ''),
        'calendar_color' => (string) ($raw['calendar_color'] ?? $raw['color'] ?? '#0d6efd'),
        'view_url' => (string) ($raw['view_url'] ?? $raw['list_url'] ?? '#'),
        'time_label' => $timeLabel,
    ];
};

$normalizeDay = static function (array $day) use ($normalizeItem, $monthContext): array {
    $date = (string) ($day['date'] ?? '');
    $dateTime = $date !== '' ? new DateTimeImmutable($date) : null;

    $dayNumber = (string) ($day['day_number'] ?? ($dateTime ? $dateTime->format('j') : ''));
    $weekdayShort = (string) ($day['weekday_short'] ?? ($dateTime ? $dateTime->format('D') : ''));
    $isToday = (bool) ($day['is_today'] ?? ($date !== '' && $date === date('Y-m-d')));
    $isCurrentMonth = (bool) ($day['is_current_month'] ?? ($monthContext !== null && $date !== '' && str_starts_with($date, $monthContext)));

    $rawItems = $day['items'] ?? $day['occurrences'] ?? [];
    $items = [];
    foreach ($rawItems as $raw) {
        $items[] = $normalizeItem($raw);
    }

    usort($items, static fn (array $a, array $b): int => strcmp($a['time_label'], $b['time_label']));

    return [
        'date' => $date,
        'day_number' => $dayNumber,
        'weekday_short' => $weekdayShort,
        'is_today' => $isToday,
        'is_current_month' => $isCurrentMonth,
        'items' => $items,
    ];
};

$monthWeeks = [];
$rawWeeks = $month_weeks ?? $weeks ?? [];
foreach ($rawWeeks as $week) {
    $normalized = [];
    foreach ($week as $day) {
        $normalized[] = $normalizeDay($day);
    }
    $monthWeeks[] = $normalized;
}

if (empty($monthWeeks) && $calendarMode === 'month' && !empty($day_buckets)) {
    $chunk = [];
    foreach ($day_buckets as $day) {
        $chunk[] = $normalizeDay($day);
        if (count($chunk) === 7) {
            $monthWeeks[] = $chunk;
            $chunk = [];
        }
    }
    if ($chunk !== []) {
        while (count($chunk) < 7) {
            $chunk[] = $normalizeDay(['date' => '']);
        }
        $monthWeeks[] = $chunk;
    }
}

$weekDays = [];
if (!empty($week_days)) {
    foreach ($week_days as $day) {
        $weekDays[] = $normalizeDay($day);
    }
} elseif ($calendarMode === 'week' && !empty($monthWeeks)) {
    $weekDays = $monthWeeks[0] ?? [];
} elseif ($calendarMode === 'week' && !empty($day_buckets)) {
    foreach ($day_buckets as $day) {
        $weekDays[] = $normalizeDay($day);
    }
}

$agendaRows = [];
if (!empty($agenda_rows)) {
    foreach ($agenda_rows as $raw) {
        $item = $normalizeItem($raw);
        $agendaRows[] = array_merge($item, [
            'date' => (string) ($raw['date'] ?? ''),
            'day_number' => (string) ($raw['day_number'] ?? ''),
            'weekday_short' => (string) ($raw['weekday_short'] ?? ''),
        ]);
    }
} elseif (!empty($day_buckets)) {
    foreach ($day_buckets as $day) {
        $normalizedDay = $normalizeDay($day);
        foreach ($normalizedDay['items'] as $item) {
            $agendaRows[] = array_merge($item, [
                'date' => $normalizedDay['date'],
                'day_number' => $normalizedDay['day_number'],
                'weekday_short' => $normalizedDay['weekday_short'],
            ]);
        }
    }
} elseif ($calendarMode === 'agenda' && !empty($monthWeeks)) {
    foreach ($monthWeeks as $week) {
        foreach ($week as $day) {
            foreach ($day['items'] as $item) {
                $agendaRows[] = array_merge($item, [
                    'date' => $day['date'],
                    'day_number' => $day['day_number'],
                    'weekday_short' => $day['weekday_short'],
                ]);
            }
        }
    }
}

usort($agendaRows, static fn (array $a, array $b): int => [($a['date'] ?? ''), ($a['time_label'] ?? '')] <=> [($b['date'] ?? ''), ($b['time_label'] ?? '')]);

$prevDate = (string) ($prev_date ?? '');
if ($prevDate === '') {
    $prevDate = $calendarMode === 'week'
        ? $anchorDateTime->modify('-7 days')->format('Y-m-d')
        : ($calendarMode === 'agenda'
            ? $anchorDateTime->modify('-30 days')->format('Y-m-d')
            : $anchorDateTime->modify('first day of previous month')->format('Y-m-d'));
}

$nextDate = (string) ($next_date ?? '');
if ($nextDate === '') {
    $nextDate = $calendarMode === 'week'
        ? $anchorDateTime->modify('+7 days')->format('Y-m-d')
        : ($calendarMode === 'agenda'
            ? $anchorDateTime->modify('+30 days')->format('Y-m-d')
            : $anchorDateTime->modify('first day of next month')->format('Y-m-d'));
}

$periodLabel = (string) ($period_label ?? '');
if ($periodLabel === '') {
    $periodStart = (string) ($period_start ?? '');
    $periodEnd = (string) ($period_end ?? '');
    if ($periodStart !== '' && $periodEnd !== '') {
        $periodLabel = $formatPeriodLabel($periodStart, $periodEnd, $calendarMode);
    }
}

$legend = $calendar_legend ?? [];
if (empty($legend) && !empty($dioceses) && !empty($diocese_colors) && is_array($diocese_colors)) {
    foreach ($dioceses as $diocese) {
        $legend[] = [
            'name' => (string) ($diocese['name'] ?? ''),
            'color' => (string) ($diocese_colors[(int) ($diocese['id'] ?? 0)] ?? '#6c757d'),
        ];
    }
}

$calendarBaseUrl = (string) ($calendar_base_url ?? 'events/calendar');
$calendarBaseQuery = (array) ($calendar_base_query ?? []);
$listUrl = (string) ($list_url ?? 'events');

$calendarNavUrl = static function (string $mode, string $date) use ($calendarBaseUrl, $calendarBaseQuery): string {
    $query = array_merge($calendarBaseQuery, ['mode' => $mode, 'date' => $date]);
    return base_url($calendarBaseUrl . '?' . http_build_query($query));
};
$createUrl = (string) ($create_url ?? 'events/create');
$manageCalendarsUrl = (string) ($manage_calendars_url ?? 'event-calendars');
$canCreateEvent = (bool) ($can_create_event ?? auth()->user()->can('event.create'));
$canManageCalendars = (bool) ($can_manage_calendars ?? false);

?>

<style>
    .events-calendar-shell {
        display: grid;
        gap: 1rem;
    }

    .events-calendar-nav,
    .events-calendar-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        align-items: center;
    }

    .events-calendar-legend-item {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-size: .85rem;
        color: var(--bs-body-color);
    }

    .events-calendar-legend-swatch {
        width: 0.8rem;
        height: 0.8rem;
        border-radius: 999px;
        display: inline-block;
    }

    .events-month-grid {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: .375rem;
    }

    .events-month-header-cell {
        border: 1px solid var(--bs-border-color);
        border-radius: .375rem;
        background-color: var(--bs-body-bg);
        padding: .2rem .25rem;
        text-align: center;
    }

    .events-month-header-label {
        font-size: .7rem;
        text-transform: uppercase;
        color: var(--bs-secondary-color);
        letter-spacing: .04em;
        font-weight: 600;
    }

    .events-month-day {
        border: 1px solid var(--bs-border-color);
        border-radius: .375rem;
        min-height: 140px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        background-color: var(--bs-body-bg);
    }

    .events-month-day.is-today {
        border-color: var(--bs-primary-border-subtle, #86b7fe);
        background-color: var(--bs-primary-bg-subtle, #cfe2ff);
    }

    .events-month-day.is-outside-month {
        opacity: .65;
        background-color: var(--bs-secondary-bg);
    }

    .events-month-dayhead {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: .2rem .25rem;
        border-bottom: 1px solid var(--bs-border-color);
        line-height: 1.2;
    }

    .events-month-date {
        font-size: 1.05rem;
        font-weight: 600;
        color: var(--bs-body-color);
    }

    .events-month-day.is-today .events-month-date {
        color: var(--bs-primary);
    }

    .events-month-items {
        padding: .25rem;
        display: flex;
        flex-direction: column;
        gap: .2rem;
        flex: 1;
        overflow-y: auto;
    }

    .events-month-item {
        display: block;
        padding: .18rem .35rem;
        border-radius: .25rem;
        border-left: 3px solid var(--event-color, #0d6efd);
        background-color: var(--bs-body-bg);
        color: var(--bs-body-color);
        font-size: .78rem;
        line-height: 1.3;
        text-decoration: none;
        box-shadow: 0 1px 2px rgba(0, 0, 0, .06);
        transition: background-color .12s;
    }

    .events-month-item:hover,
    .events-month-item:focus-visible {
        background-color: var(--bs-secondary-bg);
        color: var(--bs-body-color);
    }

    .events-month-item.is-draft {
        opacity: 0.85;
    }

    .events-month-item.is-cancelled {
        opacity: 0.65;
        text-decoration: line-through;
    }

    .events-month-item-meta {
        font-size: .7rem;
        color: var(--bs-secondary-color);
        margin-bottom: 0.15rem;
        display: flex;
        justify-content: space-between;
        gap: 0.35rem;
    }

    .events-month-item-title {
        font-weight: 600;
        font-size: .78rem;
    }

    .events-month-item-subtitle {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.35rem;
        margin-top: .12rem;
        font-size: .7rem;
    }

    .events-week-day {
        min-height: 220px;
    }

    .events-week-dayhead {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        width: 100%;
        gap: .5rem;
    }

    .events-week-weekday {
        font-size: .7rem;
        text-transform: uppercase;
        color: var(--bs-secondary-color);
        letter-spacing: .04em;
        font-weight: 600;
    }

    .events-agenda-list {
        display: grid;
        gap: 0.65rem;
    }

    .events-agenda-row {
        display: grid;
        grid-template-columns: 208px 120px minmax(0, 1fr);
        gap: 0.75rem;
        align-items: center;
        border: 1px solid var(--bs-border-color);
        border-radius: 0.9rem;
        background: var(--bs-body-bg);
        padding: 0.65rem;
    }

    .events-agenda-datebox {
        width: 208px;
        height: 65px;
        border-radius: 0.75rem;
        border: 1px solid var(--bs-border-color);
        background: var(--bs-secondary-bg);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .events-agenda-dategrid {
        display: grid;
        grid-template-columns: 47px 140px;
        column-gap: 8px;
        align-items: center;
        min-height: 42px;
    }

    .events-agenda-daynumber {
        width: 47px;
        min-height: 42px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        font-weight: 800;
        line-height: 1;
        color: var(--bs-body-color);
    }

    .events-agenda-datelines {
        width: 140px;
        min-height: 42px;
        display: grid;
        align-content: center;
        gap: 0.15rem;
    }

    .events-agenda-weekday {
        font-size: 0.92rem;
        font-weight: 700;
        color: var(--bs-body-color);
        line-height: 1;
    }

    .events-agenda-monthyear {
        font-size: 0.82rem;
        color: var(--bs-secondary-color);
        line-height: 1;
    }

    .events-agenda-time {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--bs-body-color);
    }

    .events-agenda-event {
        min-width: 0;
    }

    .events-agenda-event-link {
        font-weight: 700;
        color: var(--bs-body-color);
        text-decoration: none;
    }

    .events-agenda-event-link:hover,
    .events-agenda-event-link:focus-visible {
        text-decoration: underline;
    }

    .events-agenda-meta {
        margin-top: 0.35rem;
        display: flex;
        flex-wrap: wrap;
        gap: 0.45rem;
        align-items: center;
    }

    .events-agenda-scope-badge {
        border: 1px solid var(--bs-secondary-color);
        color: var(--bs-secondary-color);
        background-color: transparent;
        font-weight: 400;
    }

    .events-agenda-calendar-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        font-size: 0.8rem;
        color: var(--bs-secondary-color);
    }

    .events-agenda-calendar-chip::before {
        content: '';
        width: 0.55rem;
        height: 0.55rem;
        border-radius: 999px;
        background: var(--agenda-color, #0d6efd);
        display: inline-block;
    }

    @media (max-width: 991.98px) {
        .events-calendar-scroll {
            overflow-x: auto;
            padding-bottom: 0.25rem;
        }

        .events-month-grid {
            min-width: 70rem;
        }

        .events-week-day {
            min-height: 190px;
        }

        .events-agenda-row {
            grid-template-columns: 1fr;
            gap: 0.65rem;
            align-items: start;
        }

        .events-agenda-datebox {
            width: 100%;
            max-width: 208px;
            justify-self: start;
        }
    }

    @media (max-width: 575.98px) {
        .events-week-day {
            min-height: 160px;
        }

        .events-week-day .events-month-dayhead {
            padding: .15rem .2rem;
        }

        .events-week-day .events-month-items {
            padding: .2rem;
            gap: .15rem;
        }

        .events-week-day .events-month-item {
            padding: .14rem .3rem;
            font-size: .75rem;
        }

        .events-week-day .events-month-item-meta,
        .events-week-day .events-month-item-subtitle {
            font-size: .68rem;
        }

        .events-agenda-list {
            gap: 0.55rem;
        }

        .events-agenda-row {
            gap: 0.55rem;
            padding: 0.75rem;
            border-radius: 0.8rem;
        }

        .events-agenda-datebox {
            min-height: 65px;
            padding: 0 0.75rem;
        }

        .events-agenda-dategrid {
            width: 100%;
            grid-template-columns: 47px minmax(0, 140px);
            justify-content: start;
        }

        .events-agenda-time {
            font-size: 0.9rem;
            line-height: 1.2;
        }

        .events-agenda-event {
            display: grid;
            gap: 0.2rem;
        }

        .events-agenda-meta {
            margin-top: 0.2rem;
            gap: 0.35rem;
        }
    }
</style>

<div class="events-calendar-shell">
    <div class="d-flex justify-content-start align-items-center flex-wrap gap-2">
        <a href="<?= base_url($listUrl) ?>" class="btn btn-outline-secondary"><?= esc($listButtonLabel) ?></a>
        <a href="<?= $calendarNavUrl('month', $anchorDate) ?>" class="btn <?= $calendarMode === 'month' ? 'btn-outline-primary active' : 'btn-outline-primary' ?>"><?= lang('Events.calendarMonth') ?></a>
        <a href="<?= $calendarNavUrl('week', $anchorDate) ?>" class="btn <?= $calendarMode === 'week' ? 'btn-outline-primary active' : 'btn-outline-primary' ?>"><?= lang('Events.calendarWeek') ?></a>
        <?php if (!empty($showAgenda)): ?>
            <a href="<?= $calendarNavUrl('agenda', $anchorDate) ?>" class="btn <?= $calendarMode === 'agenda' ? 'btn-outline-primary active' : 'btn-outline-primary' ?>"><?= lang('Events.calendarAgenda') ?></a>
        <?php endif; ?>
        <?php if (!empty($canManageCalendars) && $manageCalendarsUrl !== ''): ?>
            <a href="<?= base_url($manageCalendarsUrl) ?>" class="btn btn-outline-secondary"><?= lang('Events.manageCalendars') ?></a>
        <?php endif; ?>
        <?php if (!empty($canCreateEvent) && $createUrl !== ''): ?>
            <a href="<?= base_url($createUrl) ?>" class="btn btn-outline-primary"><?= esc($createButtonLabel) ?></a>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-body events-calendar-nav justify-content-between">
            <div class="events-calendar-nav">
                <a href="<?= $calendarNavUrl($calendarMode, $prevDate) ?>" class="btn btn-outline-secondary"><?= lang('Events.calendarPrevious') ?></a>
                <a href="<?= $calendarNavUrl($calendarMode, date('Y-m-d')) ?>" class="btn btn-outline-primary"><?= lang('Events.calendarToday') ?></a>
                <a href="<?= $calendarNavUrl($calendarMode, $nextDate) ?>" class="btn btn-outline-secondary"><?= lang('Events.calendarNext') ?></a>
            </div>
            <div class="fw-semibold fs-5"><?= esc($periodLabel) ?></div>
        </div>
    </div>

    <?php if (!empty($legend)): ?>
        <div class="events-calendar-legend">
            <span class="fw-semibold text-muted"><?= lang('Events.calendarLegend') ?>:</span>
            <?php foreach ($legend as $legendItem): ?>
                <span class="events-calendar-legend-item">
                    <span class="events-calendar-legend-swatch" style="background-color: <?= esc((string) ($legendItem['color'] ?? '#0d6efd')) ?>;"></span>
                    <?= esc((string) ($legendItem['name'] ?? '')) ?>
                </span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($calendarMode === 'month' || $calendarMode === 'week'): ?>
        <div class="events-calendar-scroll">
            <div class="events-month-grid mb-2">
                <?php foreach ($weekdayHeaderOrder as $weekdayKey): ?>
                    <div class="events-month-header-cell">
                        <span class="events-month-header-label"><?= esc($weekdayLabels[$weekdayKey] ?? $weekdayKey) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="events-month-grid">
                <?php $daysToRender = ($calendarMode === 'week' ? [$weekDays] : $monthWeeks); ?>
                <?php foreach ($daysToRender as $week): ?>
                    <?php foreach ($week as $day): ?>
                        <div class="events-month-day<?= !empty($day['is_today']) ? ' is-today' : '' ?><?= empty($day['is_current_month']) && $calendarMode === 'month' ? ' is-outside-month' : '' ?>">
                            <div class="events-month-dayhead">
                                <span class="events-month-date"><?= esc((string) ($day['day_number'] ?? '')) ?></span>
                            </div>
                            <div class="events-month-items">
                                <?php if (!empty($day['items'])): ?>
                                    <?php foreach ($day['items'] as $item): ?>
                                        <?php $itemStatus = (string) ($item['status'] ?? 'published'); ?>
                                        <a href="<?= esc((string) ($item['view_url'] ?? '#')) ?>" class="events-month-item <?= esc($statusClasses[$itemStatus] ?? '') ?>" style="--event-color: <?= esc((string) ($item['calendar_color'] ?? '#0d6efd')) ?>;">
                                            <div class="events-month-item-meta">
                                                <span><?= esc((string) ($item['time_label'] ?? '')) ?></span>
                                            </div>
                                            <div class="events-month-item-title"><?= esc((string) ($item['title'] ?? '')) ?></div>
                                            <div class="events-month-item-subtitle">
                                                <span><?= esc((string) ($item['calendar_name'] ?? '')) ?></span>
                                                <?php
                                                $scopeLevel = (string) ($item['scope_level'] ?? 'parish');
                                                $scopeLangKey = 'Events.scope' . ucfirst($scopeLevel);
                                                $scopeLabel = lang($scopeLangKey);
                                                if ($scopeLabel === $scopeLangKey) {
                                                    $scopeLabel = ucfirst($scopeLevel);
                                                }
                                                ?>
                                                <span><?= esc($scopeLabel) ?></span>
                                                <span><?= esc($resolveStatusLabel($itemStatus, $statusLangKeys)) ?></span>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="events-agenda-list">
            <?php if (!empty($agendaRows)): ?>
                <?php foreach ($agendaRows as $item): ?>
                    <?php
                    $itemStatus = (string) ($item['status'] ?? 'published');
                    $weekdayShort = (string) ($item['weekday_short'] ?? '');
                    $monthYear = $formatAgendaMonthYear((string) ($item['date'] ?? ''), $locale);
                    ?>
                    <div class="events-agenda-row">
                        <div class="events-agenda-datebox">
                            <div class="events-agenda-dategrid" role="presentation">
                                <div class="events-agenda-daynumber"><?= esc((string) ($item['day_number'] ?? '')) ?></div>
                                <div class="events-agenda-datelines">
                                    <div class="events-agenda-weekday"><?= esc($weekdayLabels[$weekdayShort] ?? $weekdayShort) ?></div>
                                    <div class="events-agenda-monthyear"><?= esc($monthYear) ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="events-agenda-time"><?= esc((string) ($item['time_label'] ?? '')) ?></div>

                        <div class="events-agenda-event">
                            <a href="<?= esc((string) ($item['view_url'] ?? '#')) ?>" class="events-agenda-event-link"><?= esc((string) ($item['title'] ?? '')) ?></a>
                            <div class="events-agenda-meta">
                                <?php $agendaStatusColor = $itemStatus === 'cancelled' ? 'danger' : ($itemStatus === 'draft' ? 'secondary' : ($itemStatus === 'completed' ? 'primary' : 'success')); ?>
                                <span class="badge border border-<?= esc($agendaStatusColor) ?> text-<?= esc($agendaStatusColor) ?> fw-normal"><?= esc($resolveStatusLabel($itemStatus, $statusLangKeys)) ?></span>
                                <?php
                                $agendaScopeLevel = (string) ($item['scope_level'] ?? 'parish');
                                $agendaScopeLangKey = 'Events.scope' . ucfirst($agendaScopeLevel);
                                $agendaScopeLabel = lang($agendaScopeLangKey);
                                if ($agendaScopeLabel === $agendaScopeLangKey) {
                                    $agendaScopeLabel = ucfirst($agendaScopeLevel);
                                }
                                ?>
                                <span class="badge events-agenda-scope-badge"><?= esc($agendaScopeLabel) ?></span>
                                <span class="events-agenda-calendar-chip" style="--agenda-color: <?= esc((string) ($item['calendar_color'] ?? '#0d6efd')) ?>;"><?= esc((string) ($item['calendar_name'] ?? '')) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-muted small"><?= lang('Events.calendarNoEvents') ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
