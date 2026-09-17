<?= $this->extend('Clergy\Views\layouts/main') ?>
<?php $event = $event ?? []; ?>
<?php $error = $error ?? null; ?>
<?= $this->section('content') ?>

<?php
$locale = service('request')->getLocale() ?: config('App')->defaultLocale;
$eventStatus = (string) ($event['status'] ?? 'published');
$eventStatusClass = $eventStatus === 'published' ? 'success' : ($eventStatus === 'draft' ? 'secondary' : ($eventStatus === 'completed' ? 'primary' : 'danger'));
$eventStatusLangKey = match ($eventStatus) {
    'draft' => 'Events.statusDraft',
    'completed' => 'Events.statusCompleted',
    'cancelled' => 'Events.statusCancelled',
    default => 'Events.statusPublished',
};
$formatLocalizedDateTime = static function (?string $value, string $locale): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return (string) $value;
    }

    if (class_exists(\IntlDateFormatter::class)) {
        $formatter = new \IntlDateFormatter(
            $locale,
            \IntlDateFormatter::MEDIUM,
            \IntlDateFormatter::SHORT,
            date_default_timezone_get()
        );
        $formatter->setPattern('MMM d, y h:mm a');
        $formatted = $formatter->format($timestamp);
        if (is_string($formatted) && $formatted !== '') {
            return $formatted;
        }
    }

    return date('Y-m-d H:i', $timestamp);
};
?>

<style>
    .events-type-badge {
        background-color: #0dcaf0;
        color: #212529;
    }

    [data-bs-theme="dark"] .events-type-badge {
        background-color: #3dd5f3;
        color: #0f172a;
    }
</style>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <?php if (!empty($event['cover_path'])): ?>
                    <div class="mb-3">
                        <img src="<?= base_url('media/event-cover/' . (int) ($event['id'] ?? 0)) ?>" alt="Event cover" class="img-thumbnail w-100" style="max-height: 280px; object-fit: cover;">
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h4 class="mb-1"><?= esc((string) ($event['title'] ?? '')) ?></h4>
                        <div class="text-muted small">
                            <?= esc($formatLocalizedDateTime((string) ($event['start_at'] ?? ''), $locale)) ?>
                            <?php if (!empty($event['end_at'])): ?>
                                <?= lang('Events.scheduleTo') ?> <?= esc($formatLocalizedDateTime((string) $event['end_at'], $locale)) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="badge border border-<?= $eventStatusClass ?> text-<?= $eventStatusClass ?> fw-normal">
                        <?= lang($eventStatusLangKey) ?>
                    </span>
                </div>

                <div class="mb-2">
                    <span class="badge events-type-badge"><?= esc(ucfirst((string) ($event['event_type'] ?? 'general'))) ?></span>
                    <?php if (!empty($event['calendar_name'])): ?>
                        <span class="badge ms-1" style="background-color: <?= esc((string) ($event['calendar_color'] ?? '#0d6efd')) ?>; color: #fff;">
                            <?= esc((string) $event['calendar_name']) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <p class="mb-2"><strong><?= lang('Events.location') ?>:</strong> <?= esc((string) ($event['location'] ?? '-')) ?></p>
                <p class="mb-2"><strong><?= lang('Events.capacity') ?>:</strong> <?= !empty($event['capacity']) ? (int) $event['capacity'] : lang('Events.capacityUnlimited') ?></p>
                <p class="mb-2"><strong><?= lang('Events.allowRegistration') ?>:</strong> <?= (int) ($event['requires_registration'] ?? 1) === 1 ? lang('App.yes') : lang('App.no') ?></p>
                <p class="mb-2"><strong><?= lang('Events.visibility') ?>:</strong> <?= lang(match ((string) ($event['visibility'] ?? 'members')) {
                                                                                        'public' => 'Events.visibilityPublic',
                                                                                        'staff' => 'Events.visibilityStaff',
                                                                                        'volunteers' => 'Events.visibilityVolunteers',
                                                                                        'invited' => 'Events.visibilityInvited',
                                                                                        'registered_only' => 'Events.visibilityRegisteredOnly',
                                                                                        'household' => 'Events.visibilityHousehold',
                                                                                        default => 'Events.visibilityMembers'
                                                                                    }) ?></p>
                <p class="mb-2"><strong><?= lang('Events.scope') ?>:</strong> <?= lang((string) ($event['scope_level'] ?? 'parish') === 'diocese' ? 'Events.scopeDiocese' : 'Events.scopeParish') ?></p>
                <p class="mb-2"><strong><?= lang('Events.scheduleScenario') ?>:</strong> <?= lang(match ((string) ($event['schedule_mode'] ?? 'one_day')) {
                                                                                                'all_day' => 'Events.allDayEvent',
                                                                                                'multi_day' => 'Events.multiDayEvent',
                                                                                                default => 'Events.oneDayEvent'
                                                                                            }) ?></p>
                <?php if (!empty($event['publish_at'])): ?>
                    <p class="mb-2"><strong><?= lang('Events.publishAt') ?>:</strong> <?= esc($formatLocalizedDateTime((string) $event['publish_at'], $locale)) ?></p>
                <?php endif; ?>
                <?php if (($event['recurrence_pattern'] ?? 'none') !== 'none'): ?>
                    <p class="mb-2"><strong><?= lang('Events.repeats') ?>:</strong> <?= esc(ucfirst((string) $event['recurrence_pattern'])) ?><?php if (!empty($event['recurrence_until'])): ?>, <?= esc((string) $event['recurrence_until']) ?><?php endif; ?></p>
                <?php endif; ?>
                <p class="mb-0"><strong><?= lang('Events.description') ?>:</strong><br><?= nl2br(esc((string) ($event['description'] ?? lang('App.notApplicable')))) ?></p>

                <?php if (!empty($attachments ?? [])): ?>
                    <hr>
                    <strong class="d-block mb-2"><?= lang('Events.attachments') ?></strong>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach (($attachments ?? []) as $attachment): ?>
                            <a href="<?= base_url('media/event-attachment/' . (int) ($attachment['id'] ?? 0)) ?>" class="btn btn-outline-secondary justify-content-start" target="_blank" rel="noopener noreferrer">
                                <i class="bi <?= ($attachment['attachment_type'] ?? '') === 'image' ? 'bi-image' : 'bi-paperclip' ?>" aria-hidden="true"></i>
                                <span class="ms-2"><?= esc((string) ($attachment['original_name'] ?? '')) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <strong><?= lang('Events.registrations') ?></strong>
                <span class="text-muted small"><?= count($registrations ?? []) ?> <?= lang('Events.records') ?></span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th scope="col"><?= lang('Events.colParticipant') ?></th>
                                <th scope="col"><?= lang('Events.colContact') ?></th>
                                <th scope="col"><?= lang('Events.colStatus') ?></th>
                                <th scope="col"><?= lang('Events.colRegistered') ?></th>
                                <th scope="col"><?= lang('Events.colAction') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($registrations)): ?>
                                <?php foreach ($registrations as $registration): ?>
                                    <?php $participantName = trim((string) (($registration['first_name'] ?? '') . ' ' . ($registration['last_name'] ?? ''))); ?>
                                    <tr>
                                        <td><?= esc($participantName !== '' ? $participantName : ((string) ($registration['guest_name'] ?? '-'))) ?></td>
                                        <td>
                                            <?php if (!empty($registration['guest_email']) || !empty($registration['guest_phone'])): ?>
                                                <?= esc((string) ($registration['guest_email'] ?? '-')) ?><br>
                                                <small class="text-muted"><?= esc((string) ($registration['guest_phone'] ?? '-')) ?></small>
                                            <?php else: ?>
                                                <span class="text-muted"><?= lang('App.memberRecord') ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status = (string) ($registration['status'] ?? 'registered');
                                            $badgeClass = $status === 'checked_in' ? 'success' : ($status === 'cancelled' ? 'secondary' : 'warning text-dark');
                                            $statusLangKey = match ($status) {
                                                'checked_in' => 'Events.statusCheckedIn',
                                                'cancelled' => 'Events.statusCancelled',
                                                default => 'Events.statusRegistered',
                                            };
                                            ?>
                                            <span class="badge border border-<?= $badgeClass ?> text-<?= $badgeClass ?> fw-normal"><?= lang($statusLangKey) ?></span>
                                        </td>
                                        <td><?= esc($formatLocalizedDateTime((string) ($registration['created_at'] ?? ''), $locale)) ?></td>
                                        <td>
                                            <?php if (auth()->user()->can('event.checkin')): ?>
                                                <form method="post" action="<?= base_url('events/registrations/' . (int) $registration['id'] . '/status') ?>" class="d-flex gap-1 align-items-center">
                                                    <?= csrf_field() ?>
                                                    <select name="status" class="form-select form-select-sm" style="max-width: 140px;">
                                                        <option value="registered" <?= ($registration['status'] ?? '') === 'registered' ? 'selected' : '' ?>><?= lang('Events.statusRegistered') ?></option>
                                                        <option value="checked_in" <?= ($registration['status'] ?? '') === 'checked_in' ? 'selected' : '' ?>><?= lang('Events.statusCheckedIn') ?></option>
                                                        <option value="cancelled" <?= ($registration['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>><?= lang('Events.statusCancelled') ?></option>
                                                    </select>
                                                    <button type="submit" class="btn btn-sm btn-outline-primary"><?= lang('App.save') ?></button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-3"><?= lang('Events.noRegistrationYet') ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <?php if ((int) ($event['requires_registration'] ?? 1) === 1 && auth()->user()->can('event.register')): ?>
            <div class="card">
                <div class="card-header bg-light"><strong><?= lang('Events.addRegistration') ?></strong></div>
                <div class="card-body">
                    <?php if (!empty(session('errors'))): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ((array) session('errors') as $error): ?>
                                    <li><?= esc((string) $error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="<?= base_url('events/register/' . (int) $event['id']) ?>">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label for="user_id" class="form-label"><?= lang('Events.memberOptional') ?></label>
                            <select class="form-select" id="user_id" name="user_id">
                                <option value=""><?= lang('Events.registerAsGuest') ?></option>
                                <?php foreach (($members ?? []) as $member): ?>
                                    <?php $memberName = trim((string) (($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''))); ?>
                                    <option value="<?= (int) $member['user_id'] ?>" <?= (int) old('user_id') === (int) $member['user_id'] ? 'selected' : '' ?>>
                                        <?= esc($memberName !== '' ? $memberName : ('Member #' . $member['user_id'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text"><?= lang('Events.formTextMemberGuest') ?></div>
                        </div>

                        <div class="mb-3">
                            <label for="guest_name" class="form-label"><?= lang('Events.guestName') ?></label>
                            <input type="text" class="form-control" id="guest_name" name="guest_name" value="<?= esc(old('guest_name')) ?>">
                        </div>
                        <div class="mb-3">
                            <label for="guest_email" class="form-label"><?= lang('Events.guestEmail') ?></label>
                            <input type="email" class="form-control" id="guest_email" name="guest_email" value="<?= esc(old('guest_email')) ?>">
                        </div>
                        <div class="mb-3">
                            <label for="guest_phone" class="form-label"><?= lang('Events.guestPhone') ?></label>
                            <input type="text" class="form-control" id="guest_phone" name="guest_phone" value="<?= esc(old('guest_phone')) ?>">
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label"><?= lang('Events.notes') ?></label>
                            <textarea id="notes" name="notes" class="form-control" rows="3"><?= esc(old('notes')) ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><?= lang('Events.saveRegistration') ?></button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div class="card mt-3">
            <div class="card-header bg-light"><strong><?= lang('Events.quickActions') ?></strong></div>
            <div class="card-body d-grid gap-2">
                <a href="<?= base_url('events/calendar') ?>" class="btn btn-outline-primary"><?= lang('Events.calendarBoard') ?></a>
                <?php if (!empty($can_manage_event)): ?>
                    <a href="<?= base_url('events/edit/' . (int) $event['id']) ?>" class="btn btn-outline-warning"><?= lang('Events.edit') ?></a>
                <?php endif; ?>
                <a href="<?= base_url('events') ?>" class="btn btn-outline-secondary"><?= lang('Events.backToEvents') ?></a>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>