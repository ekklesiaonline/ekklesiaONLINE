<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
$locale = service('request')->getLocale() ?: config('App')->defaultLocale;
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



<div class="d-flex justify-content-start align-items-center flex-wrap gap-2 mb-3">
    <a href="<?= base_url('events/calendar') ?>" class="btn btn-outline-primary">
        <i class="bi bi-calendar3" aria-hidden="true"></i> <?= lang('Events.calendarBoard') ?>
    </a>
    <?php if (auth()->user()->can('event.create')): ?>
        <a href="<?= base_url('events/create') ?>" class="btn btn-outline-primary">
            <i class="bi bi-plus-circle" aria-hidden="true"></i> <?= lang('Events.createEvent') ?>
        </a>
    <?php endif; ?>
    <?php if (auth()->user()->can('event.edit')): ?>
        <a href="<?= base_url('event-calendars') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-palette" aria-hidden="true"></i> <?= lang('Events.manageCalendars') ?>
        </a>
    <?php endif; ?>
</div>

<?php
$eventsFiltersActive = trim((string) ($search ?? '')) !== '' || trim((string) ($status ?? '')) !== '';
?>
<div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-end filter-panel-header">
        <button class="btn btn-outline-secondary btn-sm" type="button"
            data-bs-toggle="collapse"
            data-bs-target="#eventsFilterPanel"
            aria-expanded="<?= $eventsFiltersActive ? 'true' : 'false' ?>">
            <i class="bi bi-funnel" aria-hidden="true"></i> <?= lang('App.filter') ?>
        </button>
    </div>
    <div id="eventsFilterPanel" class="collapse <?= $eventsFiltersActive ? 'show' : '' ?>">
        <div class="card-body border-top">
            <form method="get" action="<?= base_url('events') ?>" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label mb-1"><?= lang('Events.searchPlaceholder') ?></label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="<?= lang('Events.searchPlaceholder') ?>" value="<?= esc($search ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1"><?= lang('Events.colStatus') ?></label>
                    <select class="form-select form-select-sm" name="status">
                        <option value=""><?= lang('Events.allStatuses') ?></option>
                        <option value="published" <?= ($status ?? '') === 'published' ? 'selected' : '' ?>><?= lang('Events.statusPublished') ?></option>
                        <option value="draft" <?= ($status ?? '') === 'draft' ? 'selected' : '' ?>><?= lang('Events.statusDraft') ?></option>
                        <option value="completed" <?= ($status ?? '') === 'completed' ? 'selected' : '' ?>><?= lang('Events.statusCompleted') ?></option>
                        <option value="cancelled" <?= ($status ?? '') === 'cancelled' ? 'selected' : '' ?>><?= lang('Events.statusCancelled') ?></option>
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill">
                        <i class="bi bi-funnel" aria-hidden="true"></i> <?= lang('Services.apply') ?>
                    </button>
                    <a href="<?= base_url('events') ?>" class="btn btn-sm btn-outline-secondary flex-fill"><?= lang('App.clear') ?></a>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th scope="col"><?= lang('Events.colTitle') ?></th>
                        <th scope="col"><?= lang('Events.colType') ?></th>
                        <th scope="col"><?= lang('Events.colSchedule') ?></th>
                        <th scope="col"><?= lang('Events.colLocation') ?></th>
                        <th scope="col"><?= lang('Events.colStatus') ?></th>
                        <th scope="col"><?= lang('Events.colRegistrations') ?></th>
                        <th scope="col"><?= lang('Events.colActions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($events)): ?>
                        <?php foreach ($events as $event): ?>
                            <tr>
                                <td>
                                    <strong><?= esc((string) ($event['title'] ?? '')) ?></strong>
                                    <?php if (!empty($event['calendar_name'])): ?>
                                        <div class="mt-1">
                                            <span class="badge" style="background-color: <?= esc((string) ($event['calendar_color'] ?? '#0d6efd')) ?>; color: #fff;">
                                                <?= esc((string) $event['calendar_name']) ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($event['description'])): ?>
                                        <div class="text-muted small"><?= esc(character_limiter(strip_tags((string) $event['description']), 90)) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge border border-info text-info fw-normal"><?= esc(ucfirst((string) ($event['event_type'] ?? 'general'))) ?></span></td>
                                <td>
                                    <?= esc($formatLocalizedDateTime((string) ($event['start_at'] ?? ''), $locale)) ?>
                                    <?php if (!empty($event['end_at'])): ?>
                                        <div class="text-muted small"><?= lang('Events.scheduleTo') ?> <?= esc($formatLocalizedDateTime((string) $event['end_at'], $locale)) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= esc((string) ($event['location'] ?? '-')) ?></td>
                                <td>
                                    <?php $statusClass = $event['status'] === 'published' ? 'success' : ($event['status'] === 'draft' ? 'secondary' : ($event['status'] === 'completed' ? 'primary' : 'danger')); ?>
                                    <?php
                                    $statusLangKey = match ((string) ($event['status'] ?? 'published')) {
                                        'draft' => 'Events.statusDraft',
                                        'completed' => 'Events.statusCompleted',
                                        'cancelled' => 'Events.statusCancelled',
                                        default => 'Events.statusPublished',
                                    };
                                    ?>
                                    <span class="badge border border-<?= $statusClass ?> text-<?= $statusClass ?> fw-normal"><?= lang($statusLangKey) ?></span>
                                </td>
                                <td>
                                    <strong><?= (int) ($event['registration_count'] ?? 0) ?></strong>
                                    <div class="text-muted small"><?= lang('Events.checkIns') ?>: <?= (int) ($event['checkin_count'] ?? 0) ?></div>
                                </td>
                                <td>
                                    <div class="d-inline-flex flex-nowrap gap-1 align-items-center">
                                        <a href="<?= base_url('events/view/' . (int) $event['id']) ?>" class="btn btn-sm btn-info" title="<?= lang('App.view') ?>">
                                            <i class="bi bi-eye" aria-hidden="true"></i>
                                        </a>
                                        <?php if (!empty($event['can_manage'])): ?>
                                            <a href="<?= base_url('events/edit/' . (int) $event['id']) ?>" class="btn btn-sm btn-warning" title="<?= lang('App.edit') ?>">
                                                <i class="bi bi-pencil" aria-hidden="true"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($event['can_delete'])): ?>
                                            <button type="button" class="btn btn-sm btn-danger" onclick="showDeleteEventModal(<?= (int) $event['id'] ?>, this)" title="<?= lang('App.delete') ?>">
                                                <i class="bi bi-trash" aria-hidden="true"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4"><?= lang('Events.noEvents') ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    const eventsDeleteCsrfName = '<?= csrf_token() ?>';
    const eventsDeleteCsrfHash = '<?= csrf_hash() ?>';

    function contractErrorMessage(data, fallback) {
        if (!data || typeof data !== 'object') {
            return fallback;
        }

        const code = data.error && typeof data.error.code === 'string' ? data.error.code : '';
        const message =
            (data.error && typeof data.error.message === 'string' && data.error.message) ||
            (typeof data.message === 'string' && data.message) ||
            fallback;

        return code ? `${message} (${code})` : message;
    }

    let deleteEventId = null;
    let deleteEventRow = null;

    function showDeleteEventModal(id, btn) {
        deleteEventId = id;
        // Find the row for later removal
        deleteEventRow = btn.closest('tr');
        document.getElementById('deleteEventReason').value = '';
        document.getElementById('deleteEventReason').classList.remove('is-invalid');
        const modal = new bootstrap.Modal(document.getElementById('deleteEventModal'));
        modal.show();
    }

    function confirmDeleteEvent() {
        const reasonInput = document.getElementById('deleteEventReason');
        const reason = reasonInput.value.trim();
        if (reason.length < 8) {
            reasonInput.classList.add('is-invalid');
            return;
        }
        reasonInput.classList.remove('is-invalid');
        const payload = new URLSearchParams();
        // CodeIgniter expects CSRF token as the correct field name
        payload.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
        payload.append('delete_reason', reason);
        fetch('<?= base_url('events/delete/') ?>' + deleteEventId, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: payload.toString()
            })
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                // Always close the modal first
                try {
                    const modalEl = document.getElementById('deleteEventModal');
                    let modalInstance = bootstrap.Modal.getInstance(modalEl);
                    if (!modalInstance) {
                        modalInstance = new bootstrap.Modal(modalEl);
                    }
                    modalInstance.hide();
                } catch (e) {
                    // fallback: forcibly hide
                    document.getElementById('deleteEventModal').classList.remove('show');
                    document.body.classList.remove('modal-open');
                    const backdrop = document.querySelector('.modal-backdrop');
                    if (backdrop) backdrop.remove();
                }
                if (data.success) {
                    // Remove row from DOM
                    if (deleteEventRow) deleteEventRow.remove();
                    showAppToast('<?= esc(lang('Events.errorDeleteMessage'), 'js') ?>', 'primary');
                    return;
                }
                // Show backend error message if present
                showAppToast(data.message || contractErrorMessage(data, '<?= esc(lang('App.errorGeneric'), 'js') ?>'), 'danger');
            })
            .catch(err => {
                showAppToast('<?= esc(lang('App.errorGeneric'), 'js') ?>', 'danger');
                // Defensive: forcibly hide modal if stuck
                try {
                    document.getElementById('deleteEventModal').classList.remove('show');
                    document.body.classList.remove('modal-open');
                    const backdrop = document.querySelector('.modal-backdrop');
                    if (backdrop) backdrop.remove();
                } catch (e) {}
                // Log error for debugging
                if (window.console) console.error('Delete event AJAX error:', err);
            });
    }
</script>

<!-- Delete Event Modal -->
<div class="modal fade" id="deleteEventModal" tabindex="-1" aria-labelledby="deleteEventModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteEventModalLabel"><?= lang('Events.confirmDelete') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="deleteEventReason" class="form-label"><?= lang('Events.deleteReason') ?> <span class="text-danger">*</span></label>
                    <textarea id="deleteEventReason" class="form-control" rows="3" minlength="8" required placeholder="<?= lang('Events.deleteReasonPlaceholder') ?>"></textarea>
                    <div class="invalid-feedback">Reason must be at least 8 characters.</div>
                </div>
                <div class="alert alert-warning small mb-0">
                    <?= lang('Events.deleteIrreversibleWarning') ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmDeleteEvent()">Delete</button>
            </div>
        </div>
    </div>
</div>
</script>

<?= $this->endSection() ?>