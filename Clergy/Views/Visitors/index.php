<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-start align-items-center flex-wrap gap-2 mb-3">
    <a href="<?= base_url('visitors/dashboard') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Visitors.backToVisitors') ?>
    </a>
    <?php if (auth()->user()->can('visitor.create')): ?>
        <a href="<?= base_url('visitors/create') ?>" class="btn btn-outline-primary">
            <i class="bi bi-plus-circle" aria-hidden="true"></i> <?= lang('Visitors.pageTitleAdd') ?>
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
            data-bs-target="#visitorsFilterPanel"
            aria-expanded="<?= ($request->getGet('search') !== null || $request->getGet('followed_up') !== null || $request->getGet('lifecycle') !== null) ? 'true' : 'false' ?>"
            aria-controls="visitorsFilterPanel">
            <i class="bi bi-funnel" aria-hidden="true"></i> <?= lang('App.filter') ?>
        </button>
    </div>
    <div id="visitorsFilterPanel" class="collapse <?= ($request->getGet('search') !== null || $request->getGet('followed_up') !== null || $request->getGet('lifecycle') !== null) ? 'show' : '' ?>">
        <div class="card-body border-top filter-panel-body">
            <form method="get" action="<?= base_url('visitors') ?>">
                <div class="row g-2">
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="search" placeholder="<?= lang('Visitors.searchByNameEmailPhone') ?>" value="<?= esc($search ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="lifecycle" aria-label="<?= esc(lang('Visitors.lifecycleFilter')) ?>">
                            <option value=""><?= lang('Visitors.lifecyclePipeline') ?></option>
                            <option value="visitor" <?= ($lifecycle ?? '') === 'visitor' ? 'selected' : '' ?>><?= lang('App.statusVisitor') ?></option>
                            <option value="regular_attender" <?= ($lifecycle ?? '') === 'regular_attender' ? 'selected' : '' ?>><?= lang('App.statusRegular_attender') ?></option>
                            <option value="converted" <?= ($lifecycle ?? '') === 'converted' ? 'selected' : '' ?>><?= lang('Visitors.statusConverted') ?></option>
                            <option value="all" <?= ($lifecycle ?? '') === 'all' ? 'selected' : '' ?>><?= lang('Visitors.lifecycleAll') ?></option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="followed_up">
                            <option value=""><?= lang('Visitors.allVisitors') ?></option>
                            <option value="0" <?= ($followed_up ?? '') === '0' ? 'selected' : '' ?>><?= lang('Visitors.pendingFollowUp') ?></option>
                            <option value="1" <?= ($followed_up ?? '') === '1' ? 'selected' : '' ?>><?= lang('Visitors.followedUpLabel') ?></option>
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
                        <th scope="col"><?= lang('Visitors.colName') ?></th>
                        <th scope="col"><?= lang('Visitors.colContact') ?></th>
                        <th scope="col"><?= lang('Visitors.colVisitDate') ?></th>
                        <th scope="col"><?= lang('Visitors.colFollowUp') ?></th>
                        <th scope="col"><?= lang('Visitors.colService') ?></th>
                        <th scope="col"><?= lang('App.colActions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($visitors)): ?>
                        <?php foreach ($visitors as $visitor): ?>
                            <tr>
                                <td>
                                    <strong><?= esc($visitor['first_name'] . ' ' . $visitor['last_name']) ?></strong>
                                    <br>
                                    <?php
                                    $rowStatus   = (string) ($visitor['membership_status'] ?? 'visitor');
                                    $rowVisits   = (int) ($visitor['visit_count'] ?? 0);
                                    $rowConverted = !empty($visitor['converted_to_member_at']);
                                    $statusKey   = 'App.status' . ucfirst($rowStatus);
                                    $statusBadge = $rowConverted ? 'success' : ($rowStatus === 'regular_attender' ? 'info' : 'secondary');
                                    ?>
                                    <span class="badge border border-<?= $statusBadge ?> text-<?= $statusBadge ?> fw-normal">
                                        <?= esc(lang($statusKey)) ?>
                                    </span>
                                    <?php if (! $rowConverted && $rowStatus === 'visitor' && $rowVisits >= (int) ($attenderThreshold ?? 2)): ?>
                                        <span class="badge border border-primary text-primary fw-normal">
                                            <?= lang('Visitors.suggestedAttender') ?>
                                        </span>
                                    <?php endif; ?>
                                    <small class="text-muted d-block"><?= lang('Visitors.colVisits') ?>: <?= $rowVisits ?></small>
                                </td>
                                <td>
                                    <?= esc($visitor['email']) ?><br>
                                    <small class="text-muted"><?= esc($visitor['phone']) ?></small>
                                </td>
                                <td><?= esc($formatLocalizedDate((string) ($visitor['visit_date'] ?? ''), 'MMM d, y', $locale)) ?></td>
                                <td>
                                    <?php
                                    $prog = $progressMap[(int) ($visitor['person_id'] ?? 0)] ?? null;
                                    if (!empty($visitor['converted_to_member_at'])) {
                                        $bc = 'success';
                                        $bl = lang('Visitors.statusConverted');
                                    } elseif (!empty($visitor['followed_up'])) {
                                        $bc = 'success';
                                        $bl = lang('Visitors.statusFollowedUp');
                                    } elseif ($prog && $prog['done'] > 0 && $prog['pending'] > 0) {
                                        $bc = 'primary';
                                        $bl = lang('Visitors.followupInProgress');
                                    } else {
                                        $bc = 'warning';
                                        $bl = lang('Visitors.statusPending');
                                    }
                                    ?>
                                    <span class="badge border border-<?= $bc ?> text-<?= $bc ?> fw-normal">
                                        <?= esc($bl) ?>
                                    </span>
                                </td>
                                <td><?= esc($visitor['service_attended'] ?? '-') ?></td>
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
                                                <a class="dropdown-item" href="<?= base_url('visitors/view/' . $visitor['id']) ?>">
                                                    <i class="bi bi-eye me-2" aria-hidden="true"></i><?= lang('App.view') ?>
                                                </a>
                                            </li>
                                            <?php if (auth()->user()->can('visitor.edit')): ?>
                                                <li>
                                                    <a class="dropdown-item" href="<?= base_url('visitors/edit/' . $visitor['id']) ?>">
                                                        <i class="bi bi-pencil me-2" aria-hidden="true"></i><?= lang('App.edit') ?>
                                                    </a>
                                                </li>
                                                <?php if (! $rowConverted && $rowStatus === 'visitor'): ?>
                                                    <li>
                                                        <form method="post" action="<?= base_url('visitors/promote-attender/' . $visitor['id']) ?>"
                                                            onsubmit="return confirm(<?= json_encode(lang('Visitors.confirmPromoteAttender')) ?>);">
                                                            <?= csrf_field() ?>
                                                            <button type="submit" class="dropdown-item">
                                                                <i class="bi bi-arrow-up-circle me-2" aria-hidden="true"></i><?= lang('Visitors.promoteToAttender') ?>
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php elseif (! $rowConverted && $rowStatus === 'regular_attender'): ?>
                                                    <li>
                                                        <form method="post" action="<?= base_url('visitors/demote/' . $visitor['id']) ?>"
                                                            onsubmit="return confirm(<?= json_encode(lang('Visitors.confirmDemoteVisitor')) ?>);">
                                                            <?= csrf_field() ?>
                                                            <button type="submit" class="dropdown-item">
                                                                <i class="bi bi-arrow-down-circle me-2" aria-hidden="true"></i><?= lang('Visitors.demoteToVisitor') ?>
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <?php if (auth()->user()->can('visitor.delete')): ?>
                                                <li>
                                                    <hr class="dropdown-divider">
                                                </li>
                                                <li>
                                                    <button class="dropdown-item text-danger"
                                                        type="button"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#visitorDeleteModal"
                                                        data-visitor-id="<?= $visitor['id'] ?>"
                                                        data-visitor-name="<?= esc($visitor['first_name'] . ' ' . $visitor['last_name']) ?>"
                                                        data-pending-sequences="<?= (int) ($progressMap[(int) ($visitor['person_id'] ?? 0)]['pending'] ?? 0) ?>">
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
                            <td colspan="6" class="text-center text-muted py-4"><?= lang('Visitors.noVisitorsFound') ?></td>
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

<!-- ── Visitor delete modal ───────────────────────────────────────────── -->
<div class="modal fade" id="visitorDeleteModal" tabindex="-1"
    aria-labelledby="visitorDeleteModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="visitorDeleteModalLabel">
                    <i class="bi bi-trash3 me-2 text-danger" aria-hidden="true"></i><?= esc(lang('Visitors.deleteModalTitle')) ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">
                    <strong><?= esc(lang('Visitors.deleteModalVisitorLabel')) ?>:</strong>
                    <span id="visitorDeleteName" class="ms-1"></span>
                </p>

                <div id="visitorDeleteSequenceWarning" class="alert alert-warning d-none py-2 mt-2" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>
                    <span id="visitorDeleteSequenceMsg"></span>
                </div>

                <p class="text-danger small mt-3 mb-2">
                    <i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>
                    <?= esc(lang('Visitors.deleteModalWarningIrreversible')) ?>
                </p>

                <label for="visitorDeleteReason" class="form-label fw-semibold">
                    <?= esc(lang('Visitors.deleteReasonLabel')) ?> <span class="text-danger">*</span>
                </label>
                <textarea id="visitorDeleteReason" class="form-control form-control-sm" rows="3"
                    maxlength="500"
                    placeholder="<?= esc(lang('Visitors.deleteReasonPlaceholder')) ?>"></textarea>
                <div class="form-text text-end">
                    <span id="visitorDeleteReasonCount">0</span>/500
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <?= esc(lang('App.cancel')) ?>
                </button>
                <button type="button" class="btn btn-danger" id="visitorDeleteConfirmBtn" disabled>
                    <i class="bi bi-trash3 me-1" aria-hidden="true"></i><?= esc(lang('Visitors.deleteConfirmBtn')) ?>
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
        const modal = document.getElementById('visitorDeleteModal');
        const nameEl = document.getElementById('visitorDeleteName');
        const warningEl = document.getElementById('visitorDeleteSequenceWarning');
        const warningMsg = document.getElementById('visitorDeleteSequenceMsg');
        const reasonEl = document.getElementById('visitorDeleteReason');
        const reasonCount = document.getElementById('visitorDeleteReasonCount');
        const confirmBtn = document.getElementById('visitorDeleteConfirmBtn');

        const seqTemplate = <?= json_encode(lang('Visitors.deleteModalWarningSequences')) ?>;
        const deleteUrl = <?= json_encode(base_url('visitors/delete/')) ?>;
        const errFallback = <?= json_encode(lang('Visitors.visitorDeleteFailed')) ?>;
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrfHeader = document.querySelector('meta[name="csrf-header"]')?.content ?? 'X-CSRF-TOKEN';

        let pendingVisitorId = null;

        // ── Populate modal when triggered ──────────────────────────────────
        if (modal) {
            modal.addEventListener('show.bs.modal', function(e) {
                const btn = e.relatedTarget;
                pendingVisitorId = btn ? btn.getAttribute('data-visitor-id') : null;
                const name = btn ? btn.getAttribute('data-visitor-name') : '';
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
                pendingVisitorId = null;
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
                if (!pendingVisitorId) return;

                const reason = reasonEl.value.trim();
                if (reason.length < 10) {
                    showAppToast(<?= json_encode(lang('Visitors.deleteReasonRequired')) ?>, 'warning');
                    return;
                }

                confirmBtn.disabled = true;
                confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span><?= esc(lang('Visitors.deleteConfirmBtn'), 'js') ?>';

                const headers = {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded',
                };
                if (csrfMeta) {
                    headers[csrfHeader] = csrfMeta.content;
                }

                fetch(deleteUrl + pendingVisitorId, {
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
                            showAppToast(data.message || <?= json_encode(lang('Visitors.visitorDeleteSuccess')) ?>, 'primary');
                            setTimeout(() => location.reload(), 900);
                        } else {
                            showAppToast(contractErrorMessage(data, errFallback), 'danger');
                            confirmBtn.disabled = false;
                            confirmBtn.innerHTML = '<i class="bi bi-trash3 me-1" aria-hidden="true"></i><?= esc(lang('Visitors.deleteConfirmBtn'), 'js') ?>';
                        }
                    })
                    .catch(function() {
                        showAppToast(errFallback, 'danger');
                        confirmBtn.disabled = false;
                        confirmBtn.innerHTML = '<i class="bi bi-trash3 me-1" aria-hidden="true"></i><?= esc(lang('Visitors.deleteConfirmBtn'), 'js') ?>';
                    });
            });
        }
    }());
</script>

<?= $this->endSection() ?>