<?php
$eventData = $event ?? [];
$attachments = $attachments ?? [];
$calendarOptions = $calendar_options ?? [];
$audienceMembers = $audience_members ?? [];
$audienceTeams = $audience_teams ?? [];
$audienceFamilies = $audience_families ?? [];
$selectedInviteeIds = array_map('intval', (array) ($selected_invitee_ids ?? []));
$canManageCalendars = !empty($can_manage_calendars);
$canAssignDioceseScope = !empty($can_assign_diocese_scope);
$selectedScheduleMode = old('schedule_mode', (string) ($eventData['schedule_mode'] ?? ((int) ($eventData['is_all_day'] ?? 0) === 1 ? 'all_day' : 'one_day')));
$selectedScopeLevel = old('scope_level', (string) ($eventData['scope_level'] ?? 'parish'));
$selectedVisibility = old('visibility', (string) ($eventData['visibility'] ?? 'members'));
$selectedVisibility = in_array($selectedVisibility, ['members', 'public', 'staff', 'volunteers', 'invited', 'registered_only', 'household'], true)
    ? $selectedVisibility
    : 'members';
$selectedAudienceTeamId = (int) old('audience_team_id', (string) ($eventData['audience_team_id'] ?? '0'));
$selectedAudienceFamilyId = (int) old('audience_family_id', (string) ($eventData['audience_family_id'] ?? '0'));
$oldInviteeIds = old('invited_user_ids');
if (is_array($oldInviteeIds)) {
    $selectedInviteeIds = array_map('intval', $oldInviteeIds);
}
$selectedCalendarId = (int) old('calendar_id', (string) ($eventData['calendar_id'] ?? '0'));
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
$titleError = $fieldError('title');
$eventTypeError = $fieldError('event_type');
$calendarIdError = $fieldError('calendar_id');
$visibilityError = $fieldError('visibility');
$scopeLevelError = $fieldError('scope_level');
$scheduleModeError = $fieldError('schedule_mode');
$singleDateError = $fieldError('single_date');
$multiStartDateError = $fieldError('multi_start_date');
?>

<div class="row g-3">
    <div class="col-md-8">
        <label for="title" class="form-label"><?= lang('Events.title') ?></label>
        <input type="text" id="title" name="title" class="form-control<?= $titleError !== null ? ' is-invalid' : '' ?>" value="<?= esc(old('title', (string) ($eventData['title'] ?? ''))) ?>" required>
        <?php if ($titleError !== null): ?>
            <div class="invalid-feedback">
                <?= esc($titleError) ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="col-md-4">
        <label for="event_type" class="form-label"><?= lang('Events.type') ?></label>
        <input type="text" id="event_type" name="event_type" class="form-control<?= $eventTypeError !== null ? ' is-invalid' : '' ?>" value="<?= esc(old('event_type', (string) ($eventData['event_type'] ?? ''))) ?>" placeholder="<?= esc(lang('Events.typeExamples')) ?>">
        <?php if ($eventTypeError !== null): ?>
            <div class="invalid-feedback">
                <?= esc($eventTypeError) ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-md-4">
        <label for="calendar_id" class="form-label"><?= lang('Events.calendar') ?></label>
        <div class="d-flex gap-2 align-items-center">
            <select id="calendar_id" name="calendar_id" class="form-select<?= $calendarIdError !== null ? ' is-invalid' : '' ?>" required>
                <option value=""><?= lang('Events.selectCalendar') ?></option>
                <?php foreach ($calendarOptions as $calendar): ?>
                    <option value="<?= (int) $calendar['id'] ?>" data-scope="<?= esc((string) ($calendar['scope_level'] ?? 'parish')) ?>" <?= $selectedCalendarId === (int) $calendar['id'] ? 'selected' : '' ?>>
                        <?= esc((string) ($calendar['name'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($canManageCalendars): ?>
                <a href="<?= base_url('event-calendars') ?>" class="btn btn-outline-primary text-nowrap"><?= lang('Events.addCalendar') ?></a>
            <?php endif; ?>
        </div>
        <?php if ($calendarIdError !== null): ?>
            <div class="invalid-feedback d-block">
                <?= esc($calendarIdError) ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-md-4">
        <label for="visibility" class="form-label"><?= lang('Events.visibility') ?></label>
        <select id="visibility" name="visibility" class="form-select<?= $visibilityError !== null ? ' is-invalid' : '' ?>" required>
            <option value="members" <?= $selectedVisibility === 'members' ? 'selected' : '' ?>><?= lang('Events.visibilityMembers') ?></option>
            <option value="public" <?= $selectedVisibility === 'public' ? 'selected' : '' ?>><?= lang('Events.visibilityPublic') ?></option>
            <option value="staff" <?= $selectedVisibility === 'staff' ? 'selected' : '' ?>><?= lang('Events.visibilityStaff') ?></option>
            <option value="volunteers" <?= $selectedVisibility === 'volunteers' ? 'selected' : '' ?>><?= lang('Events.visibilityVolunteers') ?></option>
            <option value="invited" <?= $selectedVisibility === 'invited' ? 'selected' : '' ?>><?= lang('Events.visibilityInvited') ?></option>
            <option value="registered_only" <?= $selectedVisibility === 'registered_only' ? 'selected' : '' ?>><?= lang('Events.visibilityRegisteredOnly') ?></option>
            <option value="household" <?= $selectedVisibility === 'household' ? 'selected' : '' ?>><?= lang('Events.visibilityHousehold') ?></option>
        </select>
        <div class="form-text"><?= lang('Events.visibilityHelp') ?></div>
        <?php if ($visibilityError !== null): ?>
            <div class="invalid-feedback d-block">
                <?= esc($visibilityError) ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-md-4">
        <label for="scope_level" class="form-label"><?= lang('Events.scope') ?></label>
        <select id="scope_level" name="scope_level" class="form-select<?= $scopeLevelError !== null ? ' is-invalid' : '' ?>" required>
            <option value="parish" <?= $selectedScopeLevel === 'parish' ? 'selected' : '' ?>><?= lang('Events.scopeParish') ?></option>
            <?php if ($canAssignDioceseScope): ?>
                <option value="diocese" <?= $selectedScopeLevel === 'diocese' ? 'selected' : '' ?>><?= lang('Events.scopeDiocese') ?></option>
            <?php endif; ?>
        </select>
        <?php if ($scopeLevelError !== null): ?>
            <div class="invalid-feedback d-block">
                <?= esc($scopeLevelError) ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-md-4 audience-target audience-target-team<?= $selectedVisibility === 'volunteers' ? '' : ' d-none' ?>">
        <label for="audience_team_id" class="form-label"><?= lang('Events.audienceTeam') ?></label>
        <select id="audience_team_id" name="audience_team_id" class="form-select">
            <option value=""><?= lang('Events.selectAudienceTeam') ?></option>
            <?php foreach ($audienceTeams as $team): ?>
                <option value="<?= (int) ($team['id'] ?? 0) ?>" <?= $selectedAudienceTeamId === (int) ($team['id'] ?? 0) ? 'selected' : '' ?>>
                    <?= esc((string) ($team['name'] ?? '')) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-4 audience-target audience-target-family<?= $selectedVisibility === 'household' ? '' : ' d-none' ?>">
        <label for="audience_family_id" class="form-label"><?= lang('Events.audienceHousehold') ?></label>
        <select id="audience_family_id" name="audience_family_id" class="form-select">
            <option value=""><?= lang('Events.selectAudienceHousehold') ?></option>
            <?php foreach ($audienceFamilies as $family): ?>
                <option value="<?= (int) ($family['id'] ?? 0) ?>" <?= $selectedAudienceFamilyId === (int) ($family['id'] ?? 0) ? 'selected' : '' ?>>
                    <?= esc((string) ($family['family_name'] ?? '')) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-12 audience-target audience-target-invitees<?= $selectedVisibility === 'invited' ? '' : ' d-none' ?>">
        <label for="invited_user_ids" class="form-label"><?= lang('Events.invitedMembers') ?></label>
        <select id="invited_user_ids" name="invited_user_ids[]" class="form-select" multiple size="8">
            <?php foreach ($audienceMembers as $member): ?>
                <?php $memberId = (int) ($member['user_id'] ?? 0); ?>
                <?php $memberName = trim((string) (($member['nickname'] ?? $member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''))); ?>
                <option value="<?= $memberId ?>" <?= in_array($memberId, $selectedInviteeIds, true) ? 'selected' : '' ?>>
                    <?= esc($memberName !== '' ? $memberName : (string) ($member['email'] ?? $memberId)) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="form-text"><?= lang('Events.invitedMembersHelp') ?></div>
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
        <?php if ($scheduleModeError !== null): ?>
            <div class="invalid-feedback d-block">
                <?= esc($scheduleModeError) ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-12 event-schedule-panel" data-schedule-panel="one_day">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="single_date" class="form-label"><?= lang('Events.date') ?></label>
                <input type="date" id="single_date" name="single_date" class="form-control<?= $singleDateError !== null ? ' is-invalid' : '' ?>" value="<?= esc($singleDateValue) ?>">
                <?php if ($singleDateError !== null): ?>
                    <div class="invalid-feedback d-block">
                        <?= esc($singleDateError) ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-4 one-day-time-field">
                <label for="single_start_time" class="form-label"><?= lang('Events.fromTime') ?></label>
                <input type="time" id="single_start_time" name="single_start_time" class="form-control" value="<?= esc($singleStartTimeValue) ?>">
            </div>
            <div class="col-md-4 one-day-time-field">
                <label for="single_end_time" class="form-label"><?= lang('Events.toTime') ?></label>
                <input type="time" id="single_end_time" name="single_end_time" class="form-control" value="<?= esc($singleEndTimeValue) ?>">
            </div>
        </div>
    </div>

    <div class="col-12 event-schedule-panel d-none" data-schedule-panel="multi_day">
        <div class="row g-3">
            <div class="col-md-3">
                <label for="multi_start_date" class="form-label"><?= lang('Events.startDate') ?></label>
                <input type="date" id="multi_start_date" name="multi_start_date" class="form-control<?= $multiStartDateError !== null ? ' is-invalid' : '' ?>" value="<?= esc($multiStartDateValue) ?>">
                <?php if ($multiStartDateError !== null): ?>
                    <div class="invalid-feedback d-block">
                        <?= esc($multiStartDateError) ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-3">
                <label for="multi_start_time" class="form-label"><?= lang('Events.startTime') ?></label>
                <input type="time" id="multi_start_time" name="multi_start_time" class="form-control" value="<?= esc($multiStartTimeValue) ?>">
            </div>
            <div class="col-md-3">
                <label for="multi_end_date" class="form-label"><?= lang('Events.endDate') ?></label>
                <input type="date" id="multi_end_date" name="multi_end_date" class="form-control" value="<?= esc($multiEndDateValue) ?>">
            </div>
            <div class="col-md-3">
                <label for="multi_end_time" class="form-label"><?= lang('Events.endTime') ?></label>
                <input type="time" id="multi_end_time" name="multi_end_time" class="form-control" value="<?= esc($multiEndTimeValue) ?>">
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <label for="location" class="form-label"><?= lang('Events.location') ?></label>
        <input type="text" id="location" name="location" class="form-control" value="<?= esc(old('location', (string) ($eventData['location'] ?? ''))) ?>">
    </div>
    <div class="col-md-3">
        <label for="capacity" class="form-label"><?= lang('Events.capacity') ?></label>
        <input type="number" min="1" id="capacity" name="capacity" class="form-control" value="<?= esc(old('capacity', (string) ($eventData['capacity'] ?? ''))) ?>" placeholder="<?= esc(lang('App.optional')) ?>">
    </div>
    <div class="col-md-3">
        <label for="status" class="form-label"><?= lang('Events.status') ?></label>
        <select id="status" name="status" class="form-select" required>
            <option value="published" <?= $selectedStatus === 'published' ? 'selected' : '' ?>><?= lang('Events.statusPublished') ?></option>
            <option value="draft" <?= $selectedStatus === 'draft' ? 'selected' : '' ?>><?= lang('Events.statusDraft') ?></option>
            <option value="cancelled" <?= $selectedStatus === 'cancelled' ? 'selected' : '' ?>><?= lang('Events.statusCancelled') ?></option>
            <option value="completed" <?= $selectedStatus === 'completed' ? 'selected' : '' ?>><?= lang('Events.statusCompleted') ?></option>
        </select>
    </div>

    <div class="col-12">
        <label for="description" class="form-label"><?= lang('Events.description') ?></label>
        <textarea id="description" name="description" class="form-control" rows="6"><?= esc(old('description', (string) ($eventData['description'] ?? ''))) ?></textarea>
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
                <img src="<?= base_url('media/event-cover/' . (int) ($eventData['id'] ?? 0)) ?>" alt="<?= esc(lang('App.eventCover')) ?>" class="img-thumbnail w-100" style="max-height: 260px; object-fit: cover;">
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" name="remove_cover" id="remove_cover" value="1">
                    <label class="form-check-label" for="remove_cover"><?= lang('Events.removeCoverImage') ?></label>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="col-12">
        <label for="attachments" class="form-label"><?= lang('Events.attachments') ?></label>
        <input type="file" id="attachments" name="attachments[]" class="form-control" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt">
        <div class="form-text"><?= lang('Events.attachmentsHelp') ?></div>
    </div>

    <?php if ($attachments !== []): ?>
        <div class="col-12">
            <div class="border rounded p-3 bg-light-subtle">
                <strong class="d-block mb-2"><?= lang('Events.currentAttachments') ?></strong>
                <div class="row g-2">
                    <?php foreach ($attachments as $attachment): ?>
                        <div class="col-md-6">
                            <label class="form-check border rounded p-2 h-100 bg-white">
                                <input class="form-check-input me-2" type="checkbox" name="remove_attachment_ids[]" value="<?= (int) $attachment['id'] ?>">
                                <span class="fw-semibold"><?= esc((string) ($attachment['original_name'] ?? '')) ?></span>
                                <span class="d-block text-muted small"><?= lang('Events.removeAttachment') ?></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
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

    <div class="col-md-4 recurrence-days-group<?= $selectedRecurrence === 'none' || $selectedRecurrence === 'daily' || $selectedRecurrence === 'monthly' ? ' d-none' : '' ?>">
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
        <input type="date" id="recurrence_until" name="recurrence_until" class="form-control" value="<?= esc($recurrenceUntilValue) ?>">
    </div>

    <div class="col-md-6 d-flex align-items-center">
        <div class="form-check mt-4">
            <input class="form-check-input" type="checkbox" value="1" id="requires_registration" name="requires_registration" <?= old('requires_registration', (string) ((int) ($eventData['requires_registration'] ?? 1))) === '1' ? 'checked' : '' ?>>
            <label class="form-check-label" for="requires_registration">
                <?= lang('Events.allowRegistration') ?>
            </label>
        </div>
    </div>

    <div class="col-md-6">
        <label for="publish_at" class="form-label"><?= lang('Events.publishAt') ?></label>
        <input type="datetime-local" id="publish_at" name="publish_at" class="form-control" value="<?= esc($publishAtValue) ?>">
    </div>
</div>

<script>
    (() => {
        const scheduleInputs = Array.from(document.querySelectorAll('input[name="schedule_mode"]'));
        const schedulePanels = Array.from(document.querySelectorAll('[data-schedule-panel]'));
        const recurrenceSelect = document.getElementById('recurrence_pattern');
        const recurrenceDaysGroup = document.querySelector('.recurrence-days-group');
        const recurrenceEndGroup = document.querySelector('.recurrence-end-group');
        const scopeSelect = document.getElementById('scope_level');
        const visibilitySelect = document.getElementById('visibility');
        const calendarSelect = document.getElementById('calendar_id');
        const oneDayTimeFields = Array.from(document.querySelectorAll('.one-day-time-field'));
        const audienceTargetTeam = document.querySelector('.audience-target-team');
        const audienceTargetFamily = document.querySelector('.audience-target-family');
        const audienceTargetInvitees = document.querySelector('.audience-target-invitees');

        const syncSchedulePanels = () => {
            const selected = scheduleInputs.find((input) => input.checked)?.value || 'one_day';
            schedulePanels.forEach((panel) => {
                panel.classList.toggle('d-none', panel.dataset.schedulePanel !== selected && !(selected === 'all_day' && panel.dataset.schedulePanel === 'one_day'));
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

        const syncCalendarsForScope = () => {
            const selectedScope = scopeSelect?.value || 'parish';
            Array.from(calendarSelect?.options || []).forEach((option) => {
                if (!option.value) {
                    option.hidden = false;
                    return;
                }
                option.hidden = option.dataset.scope !== selectedScope;
            });

            const current = calendarSelect?.selectedOptions?.[0];
            if (current && current.hidden) {
                calendarSelect.value = '';
            }
        };

        const syncAudienceTargets = () => {
            const visibility = visibilitySelect?.value || 'members';
            audienceTargetTeam?.classList.toggle('d-none', visibility !== 'volunteers');
            audienceTargetFamily?.classList.toggle('d-none', visibility !== 'household');
            audienceTargetInvitees?.classList.toggle('d-none', visibility !== 'invited');
        };

        scheduleInputs.forEach((input) => input.addEventListener('change', syncSchedulePanels));
        recurrenceSelect?.addEventListener('change', syncRecurrenceGroups);
        scopeSelect?.addEventListener('change', syncCalendarsForScope);
        visibilitySelect?.addEventListener('change', syncAudienceTargets);

        syncSchedulePanels();
        syncRecurrenceGroups();
        syncCalendarsForScope();
        syncAudienceTargets();
    })();
</script>