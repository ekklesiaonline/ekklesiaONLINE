<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-start align-items-center mb-3">
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="<?= base_url('members/dedup') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> <?= lang('Members.backToDedup') ?>
        </a>
    </div>
</div>

<?php
$mergeableFields = [
    'first_name'     => lang('Members.firstName'),
    'middle_name'    => lang('Members.middleName'),
    'last_name'      => lang('Members.lastName'),
    'nickname'       => lang('Members.nicknameOptional'),
    'phone'          => lang('Members.phone'),
    'address'        => lang('Members.addressLabel'),
    'city'           => lang('Members.city'),
    'birth_date'     => lang('Members.birthDate'),
    'baptism_date'   => lang('Members.baptismDate'),
    'spiritual_gifts' => lang('Members.spiritualGifts'),
];
?>

<div class="card mb-3">
    <div class="card-header">
        <i class="bi bi-arrows-collapse me-1"></i> <?= lang('Members.mergePreviewTitle') ?>
    </div>
    <div class="card-body pb-0">
        <p class="text-muted small mb-3">
            <?= lang('Members.mergePreviewHint') ?>
            <strong class="text-success"><?= lang('Members.mergeCanonicalLabel') ?></strong> <?= lang('Members.mergePreviewHint2') ?>
        </p>
    </div>
</div>

<form id="mergeForm">
    <?= csrf_field() ?>
    <input type="hidden" name="canonical_profile_id" value="<?= (int) $canonical['id'] ?>">
    <input type="hidden" name="duplicate_profile_id" value="<?= (int) $duplicate['id'] ?>">

    <div class="row g-3 mb-3">
        <!-- Canonical header -->
        <div class="col-md-1"></div>
        <div class="col-md-1 text-center fw-bold text-muted small pt-2"><?= lang('Members.mergeField') ?></div>
        <div class="col-md-4">
            <div class="card border-success">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-check-circle-fill me-1"></i> <?= lang('Members.mergeCanonicalLabel') ?></span>
                    <span class="badge border border-success text-success fw-normal">ID #<?= (int) $canonical['id'] ?></span>
                </div>
                <div class="card-body pb-1">
                    <div class="fw-semibold"><?= esc($canonical['display_first'] . ' ' . $canonical['last_name']) ?></div>
                    <small class="text-muted"><?= esc($canonical['email'] ?: '—') ?></small><br>
                    <small class="text-muted"><?= esc($canonical['phone_display'] ?: '—') ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-danger">
                <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-trash-fill me-1"></i> <?= lang('Members.mergeDuplicateLabel') ?></span>
                    <span class="badge border border-danger text-danger fw-normal">ID #<?= (int) $duplicate['id'] ?></span>
                </div>
                <div class="card-body pb-1">
                    <div class="fw-semibold"><?= esc($duplicate['display_first'] . ' ' . $duplicate['last_name']) ?></div>
                    <small class="text-muted"><?= esc($duplicate['email'] ?: '—') ?></small><br>
                    <small class="text-muted"><?= esc($duplicate['phone_display'] ?: '—') ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-2 text-center fw-bold text-muted small pt-2"><?= lang('Members.mergeKeep') ?></div>
    </div>

    <?php foreach ($mergeableFields as $field => $label):
        $valA = (string) ($canonical[$field] ?? '');
        $valB = (string) ($duplicate[$field] ?? '');
        if ($field === 'phone') {
            $valA = $canonical['phone_display'] ?? '';
            $valB = $duplicate['phone_display'] ?? '';
        }
        $same = (trim($valA) === trim($valB));
    ?>
        <div class="row g-2 mb-2 align-items-center <?= $same ? 'opacity-50' : '' ?>">
            <div class="col-md-1 text-end">
                <?php if (!$same): ?>
                    <i class="bi bi-exclamation-triangle-fill text-warning" title="<?= lang('Members.mergeFieldDiffers') ?>"></i>
                <?php endif; ?>
            </div>
            <div class="col-md-1">
                <span class="badge border border-secondary text-body fw-normal small"><?= esc($label) ?></span>
            </div>

            <!-- Canonical value (radio: keep canonical = default) -->
            <div class="col-md-4">
                <div class="form-check <?= !$same ? 'p-2 rounded border border-success-subtle bg-success-subtle' : '' ?>">
                    <input class="form-check-input" type="radio"
                        name="field_choice[<?= esc($field, 'attr') ?>]"
                        id="fc_canonical_<?= esc($field, 'attr') ?>"
                        value="canonical"
                        <?= !$same ? 'checked' : 'checked disabled' ?>>
                    <label class="form-check-label" for="fc_canonical_<?= esc($field, 'attr') ?>">
                        <?= $valA !== '' ? esc($valA) : '<span class="text-muted fst-italic">empty</span>' ?>
                    </label>
                    <input type="hidden"
                        name="field_value[<?= esc($field, 'attr') ?>]"
                        value="<?= esc($valA, 'attr') ?>"
                        data-canonical="<?= esc($valA, 'attr') ?>">
                </div>
            </div>

            <!-- Duplicate value -->
            <div class="col-md-4">
                <?php if (!$same): ?>
                    <div class="form-check p-2 rounded border border-danger-subtle">
                        <input class="form-check-input" type="radio"
                            name="field_choice[<?= esc($field, 'attr') ?>]"
                            id="fc_duplicate_<?= esc($field, 'attr') ?>"
                            value="duplicate"
                            data-value="<?= esc($valB, 'attr') ?>">
                        <label class="form-check-label" for="fc_duplicate_<?= esc($field, 'attr') ?>">
                            <?= $valB !== '' ? esc($valB) : '<span class="text-muted fst-italic">empty</span>' ?>
                        </label>
                    </div>
                <?php else: ?>
                    <div class="text-muted small fst-italic ps-2"><?= lang('Members.mergeSame') ?></div>
                <?php endif; ?>
            </div>

            <div class="col-md-2"></div>
        </div>
    <?php endforeach; ?>

    <!-- Portal/email note -->
    <?php
    $canonicalHasPortal = trim($canonical['email'] ?? '') !== '';
    $duplicateHasPortal = trim($duplicate['email'] ?? '') !== '';
    ?>
    <?php if ($duplicateHasPortal && !$canonicalHasPortal): ?>
        <div class="alert alert-warning mt-3">
            <i class="bi bi-shield-exclamation me-1"></i>
            <strong><?= lang('Members.mergePortalWarning') ?></strong>
            <?= lang('Members.mergePortalWarningBody') ?>
        </div>
    <?php endif; ?>

    <!-- Reason + confirm -->
    <div class="card mt-4 border-warning">
        <div class="card-header bg-warning-subtle">
            <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>
            <?= lang('Members.mergeConfirmSection') ?>
        </div>
        <div class="card-body">
            <p class="text-muted small"><?= lang('Members.mergeConfirmBody') ?></p>
            <div class="mb-3">
                <label class="form-label fw-semibold"><?= lang('Members.mergeReasonLabel') ?> <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="merge_reason" id="mergeReason"
                    placeholder="<?= lang('Members.mergeReasonPlaceholder') ?>" required minlength="8">
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-success" id="mergeBtnSubmit">
                    <i class="bi bi-arrows-collapse"></i> <?= lang('Members.executeMerge') ?>
                </button>
                <a href="<?= base_url('members/dedup') ?>" class="btn btn-outline-secondary"><?= lang('App.cancel') ?></a>
            </div>
        </div>
    </div>
</form>

<div class="modal fade" id="mergeConfirmModal" tabindex="-1" aria-labelledby="mergeConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-warning">
            <div class="modal-header">
                <h5 class="modal-title" id="mergeConfirmModalLabel">
                    <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>
                    <?= lang('Members.mergeConfirmSection') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2 small text-muted"><?= lang('Members.mergeConfirmBody') ?></p>
                <div class="alert alert-light border mb-0">
                    <div><strong><?= esc($canonical['display_first'] . ' ' . $canonical['last_name']) ?></strong> (<?= lang('Members.mergeCanonicalLabel') ?>)</div>
                    <div><strong><?= esc($duplicate['display_first'] . ' ' . $duplicate['last_name']) ?></strong> (<?= lang('Members.mergeDuplicateLabel') ?>)</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                <button type="button" class="btn btn-success" id="confirmMergeBtn">
                    <i class="bi bi-arrows-collapse"></i> <?= lang('Members.executeMerge') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        const mergeForm = document.getElementById('mergeForm');
        const mergeReason = document.getElementById('mergeReason');
        const mergeBtnSubmit = document.getElementById('mergeBtnSubmit');
        const mergeConfirmModalEl = document.getElementById('mergeConfirmModal');
        const confirmMergeBtn = document.getElementById('confirmMergeBtn');
        let mergeConfirmModal = null;

        function ensureModal() {
            if (mergeConfirmModal || !mergeConfirmModalEl) {
                return;
            }
            if (typeof bootstrap !== 'undefined' && bootstrap && typeof bootstrap.Modal === 'function') {
                mergeConfirmModal = new bootstrap.Modal(mergeConfirmModalEl);
            }
        }

        function hideModalIfReady() {
            if (mergeConfirmModal && typeof mergeConfirmModal.hide === 'function') {
                mergeConfirmModal.hide();
            }
        }

        // When duplicate radio is selected, update the hidden field_value so the
        // controller receives the correct source value.
        document.querySelectorAll('input[type=radio][value=duplicate]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                const field = this.name.replace('field_choice[', '').replace(']', '');
                const hiddenInput = document.querySelector('input[name="field_value[' + field + ']"]');
                if (hiddenInput) {
                    hiddenInput.value = this.dataset.value || '';
                }
            });
        });
        document.querySelectorAll('input[type=radio][value=canonical]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                const field = this.name.replace('field_choice[', '').replace(']', '');
                const originalHidden = document.querySelector('input[name="field_value[' + field + ']"]');
                // Restore canonical value (stored in data-canonical attr on the hidden input)
                if (originalHidden && originalHidden.dataset.canonical !== undefined) {
                    originalHidden.value = originalHidden.dataset.canonical;
                }
            });
        });

        mergeBtnSubmit.addEventListener('click', function() {
            const reason = mergeReason.value.trim();
            if (reason.length < 8) {
                if (typeof showAppToast === 'function') {
                    showAppToast('<?= esc(lang('Members.mergeReasonRequired'), 'js') ?>', 'warning');
                }
                mergeReason.focus();
                return;
            }

            ensureModal();
            if (mergeConfirmModal && typeof mergeConfirmModal.show === 'function') {
                mergeConfirmModal.show();
            } else {
                confirmMergeBtn.click();
            }
        });

        confirmMergeBtn.addEventListener('click', function() {
            const data = new FormData(mergeForm);
            const btn = confirmMergeBtn;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> <?= esc(lang('App.processingLabel'), 'js') ?>';
            mergeBtnSubmit.disabled = true;

            fetch('<?= base_url('members/dedup/merge') ?>', {
                    method: 'POST',
                    body: data,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                })
                .then(r => r.json())
                .then(function(resp) {
                    if (resp && resp.success) {
                        hideModalIfReady();
                        if (typeof showAppToast === 'function') {
                            showAppToast(resp.message || 'Profiles merged successfully.', 'primary');
                        }
                        setTimeout(function() {
                            window.location.href = resp.redirect || '<?= base_url('members') ?>';
                        }, 700);
                    } else {
                        hideModalIfReady();
                        if (typeof showAppToast === 'function') {
                            showAppToast((resp && resp.message) || '<?= esc(lang('App.httpRequestFailedRetry'), 'js') ?>', 'danger');
                        }
                        btn.disabled = false;
                        mergeBtnSubmit.disabled = false;
                        btn.innerHTML = '<i class="bi bi-arrows-collapse"></i> <?= esc(lang('Members.executeMerge'), 'js') ?>';
                    }
                })
                .catch(function() {
                    hideModalIfReady();
                    if (typeof showAppToast === 'function') {
                        showAppToast('<?= esc(lang('App.httpRequestFailedRetry'), 'js') ?>', 'danger');
                    }
                    btn.disabled = false;
                    mergeBtnSubmit.disabled = false;
                    btn.innerHTML = '<i class="bi bi-arrows-collapse"></i> <?= esc(lang('Members.executeMerge'), 'js') ?>';
                });
        });
    }());
</script>

<?= $this->endSection() ?>