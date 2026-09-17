<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-start align-items-center mb-3">
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="<?= base_url('members/view/' . (int) $profile['id']) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('App.back') ?>
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <i class="bi bi-exclamation-octagon-fill me-1" aria-hidden="true"></i>
                <?= lang('Members.hardDeleteTitle') ?>
            </div>
            <div class="card-body">

                <!-- Member identity summary -->
                <div class="d-flex align-items-center gap-3 p-3 bg-danger-subtle rounded mb-4">
                    <i class="bi bi-person-fill text-danger" style="font-size:2.5rem;" aria-hidden="true"></i>
                    <div>
                        <div class="fw-bold fs-5"><?= esc($profile['first_name'] . ' ' . $profile['last_name']) ?></div>
                        <small class="text-muted"><?= esc($profile['email'] ?: lang('Members.noPortalEmail')) ?></small>
                        <div>
                            <span class="badge border border-secondary text-secondary fw-normal mt-1">ID #<?= (int) $profile['id'] ?></span>
                        </div>
                    </div>
                </div>

                <!-- What will be deleted -->
                <div class="alert alert-danger">
                    <strong><?= lang('Members.hardDeleteWillDelete') ?></strong>
                    <ul class="mb-0 mt-2 small">
                        <li><?= lang('Members.hardDeleteListProfile') ?></li>
                        <li><?= lang('Members.hardDeleteListAuth') ?></li>
                        <li><?= lang('Members.hardDeleteListAttendance') ?></li>
                        <li><?= lang('Members.hardDeleteListAffiliations') ?></li>
                        <li><?= lang('Members.hardDeleteListVolunteer') ?></li>
                        <li><?= lang('Members.hardDeleteListAuditRoles') ?></li>
                    </ul>
                    <hr class="my-2">
                    <strong><?= lang('Members.hardDeleteWillNull') ?></strong>
                    <ul class="mb-0 mt-1 small">
                        <li><?= lang('Members.hardDeleteListDonations') ?></li>
                        <li><?= lang('Members.hardDeleteListExpenses') ?></li>
                        <li><?= lang('Members.hardDeleteListAuditLogs') ?></li>
                    </ul>
                </div>

                <div class="alert alert-warning d-flex gap-2 align-items-start">
                    <i class="bi bi-shield-exclamation fs-5 flex-shrink-0 mt-1" aria-hidden="true"></i>
                    <div>
                        <strong><?= lang('Members.hardDeleteIrreversibleTitle') ?></strong><br>
                        <span class="small"><?= lang('Members.hardDeleteIrreversibleBody') ?></span>
                    </div>
                </div>

                <!-- Security gates -->
                <form id="hardDeleteForm">
                    <?= csrf_field() ?>

                    <!-- Gate 1: type the name -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            <?= lang('Members.hardDeleteTypeNameLabel') ?>
                            <span class="text-danger">*</span>
                        </label>
                        <p class="text-muted small mb-1">
                            <?= lang('Members.hardDeleteTypeNameHint') ?>
                            <code><?= esc(strtolower($profile['first_name'] . ' ' . $profile['last_name'])) ?></code>
                        </p>
                        <input type="text" class="form-control" name="confirm_name" id="confirmName"
                            autocomplete="off" required
                            placeholder="<?= esc(strtolower($profile['first_name'] . ' ' . $profile['last_name']), 'attr') ?>">
                    </div>

                    <!-- Gate 2: reason -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            <?= lang('Members.hardDeleteReasonLabel') ?>
                            <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" name="delete_reason" id="deleteReason"
                            rows="3" required minlength="10"
                            placeholder="<?= lang('Members.hardDeleteReasonPlaceholder') ?>"></textarea>
                    </div>

                    <!-- Gate 3: acknowledgment -->
                    <div class="mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="acknowledge_irreversible"
                                value="1" id="ackCheck" required>
                            <label class="form-check-label" for="ackCheck">
                                <?= lang('Members.hardDeleteAckLabel') ?>
                            </label>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-danger" id="hardDeleteSubmitBtn" disabled>
                            <i class="bi bi-trash3-fill" aria-hidden="true"></i> <?= lang('Members.hardDeleteExecuteBtn') ?>
                        </button>
                        <a href="<?= base_url('members/view/' . (int) $profile['id']) ?>"
                            class="btn btn-outline-secondary"><?= lang('App.cancel') ?></a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="hardDeleteConfirmModal" tabindex="-1" aria-labelledby="hardDeleteConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-danger">
            <div class="modal-header">
                <h5 class="modal-title" id="hardDeleteConfirmModalLabel">
                    <i class="bi bi-exclamation-octagon-fill text-danger me-1" aria-hidden="true"></i>
                    <?= lang('Members.hardDeleteTitle') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= esc(lang('App.close')) ?>"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2 small text-muted"><?= lang('Members.hardDeleteIrreversibleBody') ?></p>
                <div class="alert alert-danger mb-0">
                    <strong><?= esc($profile['first_name'] . ' ' . $profile['last_name']) ?></strong><br>
                    <small>ID #<?= (int) $profile['id'] ?></small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                <button type="button" class="btn btn-danger" id="confirmHardDeleteBtn">
                    <i class="bi bi-trash3-fill" aria-hidden="true"></i> <?= lang('Members.hardDeleteExecuteBtn') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        const expectedName = <?= json_encode(strtolower($profile['first_name'] . ' ' . $profile['last_name'])) ?>;
        const submitBtn = document.getElementById('hardDeleteSubmitBtn');
        const confirmHardDeleteBtn = document.getElementById('confirmHardDeleteBtn');
        const hardDeleteConfirmModalEl = document.getElementById('hardDeleteConfirmModal');
        let hardDeleteConfirmModal = null;
        const ackCheck = document.getElementById('ackCheck');
        const confirmName = document.getElementById('confirmName');
        const deleteReason = document.getElementById('deleteReason');

        function ensureModal() {
            if (hardDeleteConfirmModal || !hardDeleteConfirmModalEl) {
                return;
            }
            if (typeof bootstrap !== 'undefined' && bootstrap && typeof bootstrap.Modal === 'function') {
                hardDeleteConfirmModal = new bootstrap.Modal(hardDeleteConfirmModalEl);
            }
        }

        function hideModalIfReady() {
            if (hardDeleteConfirmModal && typeof hardDeleteConfirmModal.hide === 'function') {
                hardDeleteConfirmModal.hide();
            }
        }

        function checkGates() {
            const nameOk = confirmName.value.trim().toLowerCase() === expectedName;
            const reasonOk = deleteReason.value.trim().length >= 10;
            const ackOk = ackCheck.checked;
            submitBtn.disabled = !(nameOk && reasonOk && ackOk);
        }

        confirmName.addEventListener('input', checkGates);
        deleteReason.addEventListener('input', checkGates);
        ackCheck.addEventListener('change', checkGates);

        submitBtn.addEventListener('click', function() {
            if (submitBtn.disabled) {
                if (typeof showAppToast === 'function') {
                    showAppToast('Please complete all confirmation fields first.', 'warning');
                }
                return;
            }

            ensureModal();
            if (hardDeleteConfirmModal && typeof hardDeleteConfirmModal.show === 'function') {
                hardDeleteConfirmModal.show();
            } else {
                confirmHardDeleteBtn.click();
            }
        });

        confirmHardDeleteBtn.addEventListener('click', function() {
            submitBtn.disabled = true;
            confirmHardDeleteBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> <?= esc(lang('App.processingLabel'), 'js') ?>';
            confirmHardDeleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> <?= esc(lang('App.processingLabel'), 'js') ?>';

            const form = document.getElementById('hardDeleteForm');
            const data = new FormData(form);

            fetch('<?= base_url('members/dedup/hard-delete/' . (int) $profile['id']) ?>', {
                    method: 'POST',
                    body: data,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                })
                .then(function(r) {
                    return r.json();
                })
                .then(function(resp) {
                    if (resp && resp.success) {
                        hideModalIfReady();
                        if (typeof showAppToast === 'function') {
                            showAppToast(resp.message || 'Member permanently deleted.', 'primary');
                        }
                        setTimeout(function() {
                            window.location.href = resp.redirect || '<?= base_url('members') ?>';
                        }, 700);
                    } else {
                        hideModalIfReady();
                        if (typeof showAppToast === 'function') {
                            showAppToast((resp && resp.message) || '<?= esc(lang('App.httpRequestFailedRetry'), 'js') ?>', 'danger');
                        }
                        submitBtn.disabled = false;
                        confirmHardDeleteBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="bi bi-trash3-fill" aria-hidden="true"></i> <?= esc(lang('Members.hardDeleteExecuteBtn'), 'js') ?>';
                        confirmHardDeleteBtn.innerHTML = '<i class="bi bi-trash3-fill" aria-hidden="true"></i> <?= esc(lang('Members.hardDeleteExecuteBtn'), 'js') ?>';
                    }
                })
                .catch(function() {
                    hideModalIfReady();
                    if (typeof showAppToast === 'function') {
                        showAppToast('<?= esc(lang('App.httpRequestFailedRetry'), 'js') ?>', 'danger');
                    }
                    submitBtn.disabled = false;
                    confirmHardDeleteBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-trash3-fill" aria-hidden="true"></i> <?= esc(lang('Members.hardDeleteExecuteBtn'), 'js') ?>';
                    confirmHardDeleteBtn.innerHTML = '<i class="bi bi-trash3-fill" aria-hidden="true"></i> <?= esc(lang('Members.hardDeleteExecuteBtn'), 'js') ?>';
                });
        });
    }());
</script>

<?= $this->endSection() ?>