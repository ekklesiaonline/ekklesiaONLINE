<?php
$eventData = $event ?? [];
$calendarOptions = $calendars ?? [];
$canManageCalendars = !empty($can_manage_calendars);

$selectedScheduleMode = old('schedule_mode', (string) ($eventData['schedule_mode'] ?? ((int) ($eventData['is_all_day'] ?? 0) === 1 ? 'all_day' : 'one_day')));
$selectedRecurrence = old('recurrence_pattern', (string) ($eventData['recurrence_pattern'] ?? 'none'));
$selectedStatus = old('status', (string) ($eventData['status'] ?? 'published'));

$recurrenceDays = old('recurrence_days');
if (!is_array($recurrenceDays)) {
    $recurrenceDays = json_decode((string) ($eventData['recurrence_days_json'] ?? '[]'), true);
    if (!is_array($recurrenceDays)) {
        $recurrenceDays = [];
    }
}

$singleDateValue = old('single_date', !empty($eventData['start_at']) ? date('Y-m-d', strtotime((string) $eventData['start_at'])) : '');
$singleStartTimeValue = old('single_start_time', !empty($eventData['start_at']) ? date('H:i', strtotime((string) $eventData['start_at'])) : '');
$singleEndTimeValue = old('single_end_time', !empty($eventData['end_at']) ? date('H:i', strtotime((string) $eventData['end_at'])) : '');
$multiStartDateValue = old('multi_start_date', !empty($eventData['start_at']) ? date('Y-m-d', strtotime((string) $eventData['start_at'])) : '');
$multiStartTimeValue = old('multi_start_time', !empty($eventData['start_at']) ? date('H:i', strtotime((string) $eventData['start_at'])) : '');
$multiEndDateValue = old('multi_end_date', !empty($eventData['end_at']) ? date('Y-m-d', strtotime((string) $eventData['end_at'])) : '');
$multiEndTimeValue = old('multi_end_time', !empty($eventData['end_at']) ? date('H:i', strtotime((string) $eventData['end_at'])) : '');
$publishAtValue = old('publish_at', !empty($eventData['publish_at']) ? date('Y-m-d\TH:i', strtotime((string) $eventData['publish_at'])) : '');
$recurrenceUntilValue = old('recurrence_until', (string) ($eventData['recurrence_until'] ?? ''));

$sessionErrors = session('errors');
$sessionErrors = is_array($sessionErrors) ? $sessionErrors : [];
$fieldError = static function (string $key) use ($sessionErrors): ?string {
    $value = $sessionErrors[$key] ?? null;
    return is_string($value) ? $value : null;
};
?>

<input type="hidden" name="organizer_type" value="province">
<div class="row g-3">
    <div class="col-md-8">
        <label for="title" class="form-label"><?= lang('Events.title') ?> *</label>
        <input type="text" id="title" name="title" class="form-control<?= $fieldError('title') !== null ? ' is-invalid' : '' ?>" value="<?= esc(old('title', (string) ($eventData['title'] ?? '')), 'attr') ?>" required>
        <?php if ($fieldError('title') !== null): ?>
            <div class="invalid-feedback"><?= esc($fieldError('title')) ?></div>
        <?php endif; ?>
    </div>
    <div class="col-md-4">
        <label for="event_type" class="form-label"><?= lang('Events.type') ?></label>
        <input type="text" id="event_type" name="event_type" class="form-control" value="<?= esc(old('event_type', (string) ($eventData['event_type'] ?? '')), 'attr') ?>" placeholder="<?= esc(lang('Events.typeExamples') ?? '') ?>">
    </div>

    <div class="col-md-6">
        <label for="calendar_id" class="form-label"><?= lang('Events.calendar') ?> *</label>
        <div class="d-flex gap-2 align-items-center">
            <select id="calendar_id" name="calendar_id" class="form-select" required>
                <option value=""><?= lang('Events.selectCalendar') ?></option>
                <?php foreach ($calendarOptions as $calendar): ?>
                    <option value="<?= (int) $calendar['id'] ?>" <?= (int) old('calendar_id', (string) ($eventData['calendar_id'] ?? '0')) === (int) $calendar['id'] ? 'selected' : '' ?>><?= esc($calendar['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($canManageCalendars): ?>
                <a href="<?= base_url('province/event-calendars') ?>" class="btn btn-outline-primary text-nowrap"><?= lang('Events.addCalendar') ?></a>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-md-6">
        <label class="form-label"><?= lang('Province.eventScope') ?></label>
        <input type="text" class="form-control" value="<?= lang('Province.calendarProvinceLabel') ?>" disabled readonly>
    </div>

    <div class="col-md-6">
        <label for="visibility" class="form-label"><?= lang('Events.visibility') ?></label>
        <select id="visibility" name="visibility" class="form-select">
            <option value="public" <?= old('visibility', (string) ($eventData['visibility'] ?? '')) === 'public' ? 'selected' : '' ?>><?= lang('Events.visibilityPublic') ?></option>
            <option value="members" <?= old('visibility', (string) ($eventData['visibility'] ?? '')) === 'members' ? 'selected' : '' ?>><?= lang('Events.visibilityMembers') ?></option>
            <option value="staff" <?= old('visibility', (string) ($eventData['visibility'] ?? '')) === 'staff' ? 'selected' : '' ?>><?= lang('Events.visibilityStaff') ?></option>
        </select>
    </div>
    <div class="col-md-6">
        <label for="status" class="form-label"><?= lang('Events.status') ?></label>
        <select id="status" name="status" class="form-select" required>
            <option value="published" <?= $selectedStatus === 'published' ? 'selected' : '' ?>><?= lang('Events.statusPublished') ?></option>
            <option value="draft" <?= $selectedStatus === 'draft' ? 'selected' : '' ?>><?= lang('Events.statusDraft') ?></option>
            <option value="cancelled" <?= $selectedStatus === 'cancelled' ? 'selected' : '' ?>><?= lang('Events.statusCancelled') ?></option>
            <option value="completed" <?= $selectedStatus === 'completed' ? 'selected' : '' ?>><?= lang('Events.statusCompleted') ?></option>
        </select>
    </div>

    <div class="col-12">
        <label class="form-label d-block"><?= lang('Events.scheduleScenario') ?></label>
        <div class="btn-group flex-wrap" role="group" aria-label="<?= esc(lang('Events.scheduleScenario')) ?>">
            <input type="radio" class="btn-check" name="schedule_mode" id="schedule_mode_one_day" value="one_day" <?= $selectedScheduleMode === 'one_day' ? 'checked' : '' ?>>
            <label class="btn btn-outline-primary" for="schedule_mode_one_day"><?= lang('Events.oneDayEvent') ?></label>

            <input type="radio" class="btn-check" name="schedule_mode" id="schedule_mode_all_day" value="all_day" <?= $selectedScheduleMode === 'all_day' ? 'checked' : '' ?>>
            <label class="btn btn-outline-primary" for="schedule_mode_all_day"><?= lang('Events.allDayEvent') ?></label>

            <input type="radio" class="btn-check" name="schedule_mode" id="schedule_mode_multi_day" value="multi_day" <?= $selectedScheduleMode === 'multi_day' ? 'checked' : '' ?>>
            <label class="btn btn-outline-primary" for="schedule_mode_multi_day"><?= lang('Events.multiDayEvent') ?></label>
        </div>
    </div>

    <div class="col-12 event-schedule-panel<?= $selectedScheduleMode !== 'multi_day' ? ' d-none' : '' ?>" data-schedule-panel="one_day">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="single_date" class="form-label"><?= lang('Events.date') ?></label>
                <input type="date" id="single_date" name="single_date" class="form-control" value="<?= esc($singleDateValue, 'attr') ?>">
            </div>
            <div class="col-md-4 one-day-time-field">
                <label for="single_start_time" class="form-label"><?= lang('Events.fromTime') ?></label>
                <input type="time" id="single_start_time" name="single_start_time" class="form-control" value="<?= esc($singleStartTimeValue, 'attr') ?>">
            </div>
            <div class="col-md-4 one-day-time-field">
                <label for="single_end_time" class="form-label"><?= lang('Events.toTime') ?></label>
                <input type="time" id="single_end_time" name="single_end_time" class="form-control" value="<?= esc($singleEndTimeValue, 'attr') ?>">
            </div>
        </div>
    </div>

    <div class="col-12 event-schedule-panel<?= $selectedScheduleMode !== 'multi_day' ? ' d-none' : '' ?>" data-schedule-panel="multi_day">
        <div class="row g-3">
            <div class="col-md-3">
                <label for="multi_start_date" class="form-label"><?= lang('Events.startDate') ?></label>
                <input type="date" id="multi_start_date" name="multi_start_date" class="form-control" value="<?= esc($multiStartDateValue, 'attr') ?>">
            </div>
            <div class="col-md-3">
                <label for="multi_start_time" class="form-label"><?= lang('Events.startTime') ?></label>
                <input type="time" id="multi_start_time" name="multi_start_time" class="form-control" value="<?= esc($multiStartTimeValue, 'attr') ?>">
            </div>
            <div class="col-md-3">
                <label for="multi_end_date" class="form-label"><?= lang('Events.endDate') ?></label>
                <input type="date" id="multi_end_date" name="multi_end_date" class="form-control" value="<?= esc($multiEndDateValue, 'attr') ?>">
            </div>
            <div class="col-md-3">
                <label for="multi_end_time" class="form-label"><?= lang('Events.endTime') ?></label>
                <input type="time" id="multi_end_time" name="multi_end_time" class="form-control" value="<?= esc($multiEndTimeValue, 'attr') ?>">
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <label for="location" class="form-label"><?= lang('Events.location') ?></label>
        <input type="text" id="location" name="location" class="form-control" value="<?= esc(old('location', (string) ($eventData['location'] ?? '')), 'attr') ?>">
    </div>
    <div class="col-md-6">
        <label for="capacity" class="form-label"><?= lang('Events.capacity') ?></label>
        <input type="number" min="1" id="capacity" name="capacity" class="form-control" value="<?= esc(old('capacity', (string) ($eventData['capacity'] ?? '')), 'attr') ?>" placeholder="<?= esc(lang('App.optional') ?? '') ?>">
    </div>

    <div class="col-12">
        <label for="description" class="form-label"><?= lang('Events.description') ?></label>
        <textarea id="description" name="description" class="form-control" rows="6"><?= esc(old('description', (string) ($eventData['description'] ?? '')), 'html') ?></textarea>
    </div>

    <div class="col-12">
        <label for="cover" class="form-label"><?= lang('Events.coverImage') ?></label>
        <input type="file" id="cover" name="cover" class="form-control" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
        <div class="form-text"><?= lang('Events.coverImageHelp') ?></div>
    </div>

    <?php if (!empty($eventData['cover_path'])): ?>
        <div class="col-12">
            <div class="border rounded p-3 bg-light-subtle">
                <strong class="d-block mb-2"><?= lang('Events.currentCoverImage') ?></strong>
                <img src="<?= base_url('media/event-cover/' . (int) ($eventData['id'] ?? 0)) ?>" alt="Event cover" class="img-thumbnail w-100" style="max-height: 260px; object-fit: cover;">
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" name="remove_cover" id="remove_cover" value="1" <?= old('remove_cover') ? 'checked' : '' ?>>
                    <label class="form-check-label" for="remove_cover"><?= lang('Events.removeCoverImage') ?></label>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="col-md-4">
        <label for="recurrence_pattern" class="form-label"><?= lang('Events.repeats') ?></label>
        <select id="recurrence_pattern" name="recurrence_pattern" class="form-select">
            <option value="none" <?= $selectedRecurrence === 'none' ? 'selected' : '' ?>><?= lang('Events.noRepeat') ?></option>
            <option value="daily" <?= $selectedRecurrence === 'daily' ? 'selected' : '' ?>><?= lang('Events.repeatDaily') ?></option>
            <option value="weekly" <?= $selectedRecurrence === 'weekly' ? 'selected' : '' ?>><?= lang('Events.repeatWeekly') ?></option>
            <option value="biweekly" <?= $selectedRecurrence === 'biweekly' ? 'selected' : '' ?>><?= lang('Events.repeatBiweekly') ?></option>
            <option value="monthly" <?= $selectedRecurrence === 'monthly' ? 'selected' : '' ?>><?= lang('Events.repeatMonthly') ?></option>
        </select>
    </div>

    <div class="col-md-4 recurrence-days-group<?= in_array($selectedRecurrence, ['none', 'daily', 'monthly'], true) ? ' d-none' : '' ?>">
        <label class="form-label d-block"><?= lang('Events.repeatEvery') ?></label>
        <div class="d-flex flex-wrap gap-2">
            <?php $weekdayLabels = ['sun' => 'Events.daySun', 'mon' => 'Events.dayMon', 'tue' => 'Events.dayTue', 'wed' => 'Events.dayWed', 'thu' => 'Events.dayThu', 'fri' => 'Events.dayFri', 'sat' => 'Events.daySat']; ?>
            <?php foreach ($weekdayLabels as $weekdayValue => $weekdayLabel): ?>
                <label class="form-check form-check-inline me-0">
                    <input class="form-check-input" type="checkbox" name="recurrence_days[]" value="<?= esc($weekdayValue) ?>" <?= in_array($weekdayValue, $recurrenceDays, true) ? 'checked' : '' ?>>
                    <span class="form-check-label"><?= lang($weekdayLabel) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="col-md-4 recurrence-end-group<?= $selectedRecurrence === 'none' ? ' d-none' : '' ?>">
        <label for="recurrence_until" class="form-label"><?= lang('Events.repeatEndDate') ?></label>
        <input type="date" id="recurrence_until" name="recurrence_until" class="form-control" value="<?= esc($recurrenceUntilValue, 'attr') ?>">
    </div>

    <div class="col-md-6 d-flex align-items-center">
        <div class="form-check mt-4">
            <input class="form-check-input" type="checkbox" value="1" id="requires_registration" name="requires_registration" <?= old('requires_registration', (string) ((int) ($eventData['requires_registration'] ?? 1))) === '1' ? 'checked' : '' ?>>
            <label class="form-check-label" for="requires_registration"><?= lang('Events.allowRegistration') ?></label>
        </div>
    </div>

    <div class="col-md-6">
        <label for="publish_at" class="form-label"><?= lang('Events.publishAt') ?></label>
        <input type="datetime-local" id="publish_at" name="publish_at" class="form-control" value="<?= esc($publishAtValue, 'attr') ?>">
    </div>

    <div class="col-12">
        <button class="btn btn-primary me-2" type="submit"><?= lang('App.save') ?></button>
        <a class="btn btn-outline-secondary" href="<?= base_url('province/events/calendar') ?>"><?= lang('App.cancel') ?></a>
    </div>
</div>

<script>
    (() => {
        const scheduleInputs = Array.from(document.querySelectorAll('input[name="schedule_mode"]'));
        const schedulePanels = Array.from(document.querySelectorAll('[data-schedule-panel]'));
        const recurrenceSelect = document.getElementById('recurrence_pattern');
        const recurrenceDaysGroup = document.querySelector('.recurrence-days-group');
        const recurrenceEndGroup = document.querySelector('.recurrence-end-group');
        const oneDayTimeFields = Array.from(document.querySelectorAll('.one-day-time-field'));

        const syncSchedulePanels = () => {
            const selected = scheduleInputs.find((input) => input.checked)?.value || 'one_day';
            schedulePanels.forEach((panel) => {
                const panelMode = panel.dataset.schedulePanel;
                const isAllDayOneDay = selected === 'all_day' && panelMode === 'one_day';
                panel.classList.toggle('d-none', panelMode !== selected && !isAllDayOneDay);
            });
            oneDayTimeFields.forEach((field) => {
                field.classList.toggle('d-none', selected === 'all_day');
            });
        };

        const syncRecurrenceGroups = () => {
            const value = recurrenceSelect?.value || 'none';
            recurrenceDaysGroup?.classList.toggle('d-none', value === 'none' || value === 'daily' || value === 'monthly');
            recurrenceEndGroup?.classList.toggle('d-none', value === 'none');
        };

        scheduleInputs.forEach((input) => input.addEventListener('change', syncSchedulePanels));
        recurrenceSelect?.addEventListener('change', syncRecurrenceGroups);

        syncSchedulePanels();
        syncRecurrenceGroups();
    })();
</script>
