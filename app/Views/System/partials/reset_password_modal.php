<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="resetPasswordForm" action="" method="post">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="resetPasswordModalLabel">
                        <i class="bi bi-key me-2"></i><?= lang('System.resetPasswordTitle') ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">
                        <?= lang('System.resetPasswordFor') ?>
                        <strong id="resetPasswordTargetName"></strong>
                    </p>
                    <div class="row g-3">
                        <?= $this->setData([
                            'prefix' => 'resetPW',
                            'passwordName' => 'new_password',
                            'confirmName' => 'new_password_confirm',
                            'passwordLabel' => lang('System.newPassword'),
                            'confirmLabel' => lang('System.newPasswordConfirm'),
                        ], 'raw')->include('System/partials/admin_password_create', [], false) ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <?= lang('App.cancel') ?>
                    </button>
                    <button type="submit" id="resetPWSubmit" class="btn btn-warning">
                        <?= lang('System.resetPasswordBtn') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.getElementById('resetPasswordModal').addEventListener('show.bs.modal', function (e) {
        const btn = e.relatedTarget;
        document.getElementById('resetPasswordForm').action = '<?= base_url('system/admins/reset-password/') ?>' + btn.dataset.id;
        document.getElementById('resetPasswordTargetName').textContent = btn.dataset.name;

        const form = document.getElementById('resetPasswordForm');
        form.reset();

        const password = document.getElementById('resetPWPassword');
        const confirm = document.getElementById('resetPWConfirmPassword');
        if (password) {
            password.dispatchEvent(new Event('input'));
        }
        if (confirm) {
            confirm.dispatchEvent(new Event('input'));
        }
    });
</script>
