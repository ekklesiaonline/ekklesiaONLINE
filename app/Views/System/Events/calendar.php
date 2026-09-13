<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?= view('partials/events_calendar', [
    'calendar_mode' => $mode ?? 'month',
    'anchor_date' => $anchor ?? date('Y-m-d'),
    'period_start' => $period_start ?? '',
    'period_end' => $period_end ?? '',
    'weeks' => $weeks ?? [],
    'day_buckets' => $day_buckets ?? [],
    'can_manage_calendars' => auth()->user()->can('system.admin'),
    'can_create_event' => auth()->user()->can('system.admin'),
    'calendar_base_url' => 'system/events/calendar',
    'list_url' => 'system/events',
    'create_url' => 'system/events/create',
    'manage_calendars_url' => 'system/event-calendars',
]) ?>

<?= $this->endSection() ?>
