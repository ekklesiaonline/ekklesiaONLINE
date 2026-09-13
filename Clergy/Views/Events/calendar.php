<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?= view('partials/events_calendar', [
    'calendar_mode' => $calendar_mode,
    'anchor_date' => $anchor_date,
    'period_label' => $period_label,
    'prev_date' => $prev_date,
    'next_date' => $next_date,
    'month_weeks' => $month_weeks,
    'week_days' => $week_days,
    'agenda_rows' => $agenda_rows,
    'calendar_legend' => $calendar_legend,
    'can_manage_calendars' => $can_manage_calendars,
    'can_create_event' => auth()->user()->can('event.create'),
    'calendar_base_url' => 'events/calendar',
    'list_url' => 'events',
    'create_url' => 'events/create',
    'manage_calendars_url' => 'event-calendars',
]) ?>

<?= $this->endSection() ?>
