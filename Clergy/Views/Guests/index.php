<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-start align-items-center flex-wrap gap-2 mb-3">
    <a href="<?= base_url('guests/dashboard') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Guests.backToGuests') ?>
    </a>
    <?php if (auth()->user()->can('guest.create')): ?>
        <a href="<?= base_url('guests/create') ?>" class="btn btn-outline-primary">
            <i class="bi bi-plus-circle" aria-hidden="true"></i> <?= lang('Guests.pageTitleAdd') ?>
        </a>
    <?php endif; ?>
</div>

<?php $request = service('request'); ?>

<?php
$locale = $request->getLocale() ?: config('App')->defaultLocale;
$formatLocalizedDate = static function (?string $value, string $pattern, string $locale): string {
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
            \IntlDateFormatter::NONE,
            date_default_timezone_get()
        );
        $formatter->setPattern($pattern);
        $formatted = $formatter->format($timestamp);
        if (is_string($formatted) && $formatted !== '') {
            return $formatted;
        }
    }

    return date('Y-m-d', $timestamp);
};
?>

<div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-end filter-panel-header">
        <button
            class="btn btn-outline-secondary btn-sm filter-toggle-btn"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#guestsFilterPanel"
            aria-expanded="<?= ($request->getGet('search') !== null || $request->getGet('followed_up') !== null) ? 'true' : 'false' ?>"
            aria-controls="guestsFilterPanel">
            <i class="bi bi-funnel" aria-hidden="true"></i> <?= lang('App.filter') ?>
        </button>
    </div>
    <div id="guestsFilterPanel" class="collapse <?= ($request->getGet('search') !== null || $request->getGet('followed_up') !== null) ? 'show' : '' ?>">
        <div class="card-body border-top filter-panel-body">
            <form method="get" action="<?= base_url('guests') ?>">
                <div class="row g-2">
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="search" placeholder="<?= lang('Guests.searchByNameEmailPhone') ?>" value="<?= esc($search ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="followed_up">
                            <option value=""><?= lang('Guests.allGuests') ?></option>
                            <option value="0" <?= ($followed_up ?? '') === '0' ? 'selected' : '' ?>><?= lang('Guests.pendingFollowUp') ?></option>
                            <option value="1" <?= ($followed_up ?? '') === '1' ? 'selected' : '' ?>><?= lang('Guests.followedUpLabel') ?></option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search" aria-hidden="true"></i> <?= lang('App.filter') ?>
                        </button>
                    </div>
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
                        <th scope="col"><?= lang('Guests.colName') ?></th>
                        <th scope="col"><?= lang('Guests.colContact') ?></th>
                        <th scope="col"><?= lang('Guests.colVisitDate') ?></th>
                        <th scope="col"><?= lang('Guests.colFollowUp') ?></th>
                        <th scope="col"><?= lang('Guests.colService') ?></th>
                        <th scope="col"><?= lang('App.colActions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($guests)): ?>
                        <?php foreach ($guests as $guest): ?>
                            <tr>
                                <td>
                                    <strong><?= esc($guest['first_name'] . ' ' . $guest['last_name']) ?></strong>
                                </td>
                                <td>
                                    <?= esc($guest['email']) ?><br>
                                    <small class="text-muted"><?= esc($guest['phone']) ?></small>
                                </td>
                                <td><?= esc($formatLocalizedDate((string) ($guest['visit_date'] ?? ''), 'MMM d, y', $locale)) ?></td>
                                <td>
                                    <?php
                                    $prog = $progressMap[(int) ($guest['person_id'] ?? 0)] ?? null;
                                    if (!empty($guest['converted_to_member_at'])) {
                                        $bc = 'success';
                                        $bl = lang('Guests.statusConverted');
                                    } elseif (!empty($guest['followed_up'])) {
                                        $bc = 'success';
                                        $bl = lang('Guests.statusFollowedUp');
                                    } elseif ($prog && $prog['done'] > 0 && $prog['pending'] > 0) {
                                        $bc = 'primary';
                                        $bl = lang('Guests.followupInProgress');
                                    } else {
                                        $bc = 'warning';
                                        $bl = lang('Guests.statusPending');
                                    }
                                    ?>
                                    <span class="badge border border-<?= $bc ?> text-<?= $bc ?> fw-normal">
                                        <?= esc($bl) ?>
                                    </span>
                                </td>
                                <td><?= esc($guest['service_attended'] ?? '-') ?></td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary"
                                            type="button"
                                            data-bs-toggle="dropdown"
                                            aria-expanded="false"
                                            title="<?= esc(lang('App.colActions')) ?>">
                                            <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <li>
                                                <a class="dropdown-item" href="<?= base_url('guests/view/' . $guest['id']) ?>">
                                                    <i class="bi bi-eye me-2" aria-hidden="true"></i><?= lang('App.view') ?>
                                                </a>
                                            </li>
                                            <?php if (auth()->user()->can('guest.edit')): ?>
                                                <li>
                                                    <a class="dropdown-item" href="<?= base_url('guests/edit/' . $guest['id']) ?>">
                                                        <i class="bi bi-pencil me-2" aria-hidden="true"></i><?= lang('App.edit') ?>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                            <?php if (auth()->user()->can('guest.delete')): ?>
                                                <li>
                                                    <hr class="dropdown-divider">
                                                </li>
                                                <li>
                                                    <button class="dropdown-item text-danger"
                                                        type="button"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#guestDeleteModal"
                                                        data-guest-id="<?= $guest['id'] ?>"
                                                        data-guest-name="<?= esc($guest['first_name'] . ' ' . $guest['last_name']) ?>"
                                                        data-pending-sequences="<?= (int) ($progressMap[(int) ($guest['person_id'] ?? 0)]['pending'] ?? 0) ?>">
                                                        <i class="bi bi-trash me-2" aria-hidden="true"></i><?= lang('App.delete') ?>
                                                    </button>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4"><?= lang('Guests.noGuestsFound') ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (isset($pager)): ?>
            <div class="mt-3">
                <?= $pager->links() ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Guest delete modal ───────────────────────────────────────────── -->
<div class="modal fade" id="guestDeleteModal" tabindex="-1"
    aria-labelledby="guestDeleteModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="guestDeleteModalLabel">
                    <i class="bi bi-trash3 me-2 text-danger" aria-hidden="true"></i><?= esc(lang('Guests.deleteModalTitle')) ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">
                    <strong><?= esc(lang('Guests.deleteModalGuestLabel')) ?>:</strong>
                    <span id="guestDeleteName" class="ms-1"></span>
                </p>

                <div id="guestDeleteSequenceWarning" class="alert alert-warning d-none py-2 mt-2" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>
                    <span id="guestDeleteSequenceMsg"></span>
                </div>

                <p class="text-danger small mt-3 mb-2">
                    <i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>
                    <?= esc(lang('Guests.deleteModalWarningIrreversible')) ?>
                </p>

                <label for="guestDeleteReason" class="form-label fw-semibold">
                    <?= esc(lang('Guests.deleteReasonLabel')) ?> <span class="text-danger">*</span>
                </label>
                <textarea id="guestDeleteReason" class="form-control form-control-sm" rows="3"
                    maxlength="500"
                    placeholder="<?= esc(lang('Guests.deleteReasonPlaceholder')) ?>"></textarea>
                <div class="form-text text-end">
                    <span id="guestDeleteReasonCount">0</span>/500
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <?= esc(lang('App.cancel')) ?>
                </button>
                <button type="button" class="btn btn-danger" id="guestDeleteConfirmBtn" disabled>
                    <i class="bi bi-trash3 me-1" aria-hidden="true"></i><?= esc(lang('Guests.deleteConfirmBtn')) ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function contractErrorMessage(data, fallback) {
        if (!data || typeof data !== 'object') {
            return fallback;
        }
        const code = data.error && typeof data.error.code === 'string' ? data.error.code : '';
        const message = (data.error && typeof data.error.message === 'string' && data.error.message) ||
            (typeof data.message === 'string' && data.message) ||
            fallback;
        return code ? `${message} (${code})` : message;
    }

    (function() {
        const modal = document.getElementById('guestDeleteModal');
        const nameEl = document.getElementById('guestDeleteName');
        const warningEl = document.getElementById('guestDeleteSequenceWarning');
        const warningMsg = document.getElementById('guestDeleteSequenceMsg');
        const reasonEl = document.getElementById('guestDeleteReason');
        const reasonCount = document.getElementById('guestDeleteReasonCount');
        const confirmBtn = document.getElementById('guestDeleteConfirmBtn');

        const seqTemplate = <?= json_encode(lang('Guests.deleteModalWarningSequences')) ?>;
        const deleteUrl = <?= json_encode(base_url('guests/delete/')) ?>;
        const errFallback = <?= json_encode(lang('Guests.guestDeleteFailed')) ?>;
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrfHeader = document.querySelector('meta[name="csrf-header"]')?.content ?? 'X-CSRF-TOKEN';

        let pendingGuestId = null;

        // ── Populate modal when triggered ──────────────────────────────────
        if (modal) {
            modal.addEventListener('show.bs.modal', function(e) {
                const btn = e.relatedTarget;
                pendingGuestId = btn ? btn.getAttribute('data-guest-id') : null;
                const name = btn ? btn.getAttribute('data-guest-name') : '';
                const pending = parseInt(btn ? btn.getAttribute('data-pending-sequences') : '0', 10);

                nameEl.textContent = name;

                if (pending > 0) {
                    warningMsg.innerHTML = seqTemplate.replace('{0}', pending);
                    warningEl.classList.remove('d-none');
                } else {
                    warningEl.classList.add('d-none');
                }

                reasonEl.value = '';
                reasonCount.textContent = '0';
                confirmBtn.disabled = true;
            });

            modal.addEventListener('hidden.bs.modal', function() {
                pendingGuestId = null;
            });
        }

        // ── Reason validation ──────────────────────────────────────────────
        if (reasonEl) {
            reasonEl.addEventListener('input', function() {
                const len = this.value.trim().length;
                reasonCount.textContent = this.value.length;
                confirmBtn.disabled = len < 10;
            });
        }

        // ── Confirm delete ─────────────────────────────────────────────────
        if (confirmBtn) {
            confirmBtn.addEventListener('click', function() {
                if (!pendingGuestId) return;

                const reason = reasonEl.value.trim();
                if (reason.length < 10) {
                    showAppToast(<?= json_encode(lang('Guests.deleteReasonRequired')) ?>, 'warning');
                    return;
                }

                confirmBtn.disabled = true;
                confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span><?= esc(lang('Guests.deleteConfirmBtn'), 'js') ?>';

                const headers = {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded',
                };
                if (csrfMeta) {
                    headers[csrfHeader] = csrfMeta.content;
                }

                fetch(deleteUrl + pendingGuestId, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers,
                        body: new URLSearchParams({
                            delete_reason: reason
                        }).toString(),
                    })
                    .then(function(r) {
                        // Refresh the CSRF token from the response header (CI4 regenerates it).
                        const newToken = r.headers.get(csrfHeader);
                        if (newToken && csrfMeta) {
                            csrfMeta.content = newToken;
                        }
                        return r.json();
                    })
                    .then(function(data) {
                        if (data.success) {
                            bootstrap.Modal.getInstance(modal)?.hide();
                            showAppToast(data.message || <?= json_encode(lang('Guests.guestDeleteSuccess')) ?>, 'primary');
                            setTimeout(() => location.reload(), 900);
                        } else {
                            showAppToast(contractErrorMessage(data, errFallback), 'danger');
                            confirmBtn.disabled = false;
                            confirmBtn.innerHTML = '<i class="bi bi-trash3 me-1" aria-hidden="true"></i><?= esc(lang('Guests.deleteConfirmBtn'), 'js') ?>';
                        }
                    })
                    .catch(function() {
                        showAppToast(errFallback, 'danger');
                        confirmBtn.disabled = false;
                        confirmBtn.innerHTML = '<i class="bi bi-trash3 me-1" aria-hidden="true"></i><?= esc(lang('Guests.deleteConfirmBtn'), 'js') ?>';
                    });
            });
        }
    }());
</script>

<?= $this->endSection() ?>