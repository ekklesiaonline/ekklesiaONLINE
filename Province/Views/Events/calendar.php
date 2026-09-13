<?= $this->extend('Province\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?= view('partials/events_calendar', [
    'calendar_mode' => $mode ?? 'month',
    'anchor_date' => $anchor ?? date('Y-m-d'),
    'period_start' => $period_start ?? '',
    'period_end' => $period_end ?? '',
    'weeks' => $weeks ?? [],
    'day_buckets' => $day_buckets ?? [],
    'dioceses' => $dioceses ?? [],
    'diocese_colors' => $diocese_colors ?? [],
    'can_manage_calendars' => auth()->user()->can('province.calendar.manage'),
    'can_create_event' => auth()->user()->can('province.event.create'),
    'calendar_base_url' => 'province/events/calendar',
    'list_url' => 'province/events',
    'create_url' => 'province/events/create',
    'manage_calendars_url' => 'province/event-calendars',
]) ?>

<?= $this->endSection() ?>
