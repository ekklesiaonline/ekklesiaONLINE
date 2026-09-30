<?= $this->extend('Diocese\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
$admins = $admins ?? [];
$parishes = $parishes ?? [];
$search = $search ?? '';
$status = $status ?? 'all';
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong><?= lang('System.sectionParishAdmins') ?></strong>
        <div>
            <button type="button" class="btn btn-sm btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#createParishAdminModal">
                <i class="bi bi-plus-circle me-1" aria-hidden="true"></i><?= lang('App.addNew') ?>
            </button>
            <a href="<?= base_url('diocese/dashboard') ?>" class="btn btn-sm btn-outline-secondary" aria-label="<?= esc(lang('App.back')) ?>">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i><?= lang('App.back') ?>
            </a>
        </div>
    </div>
    <div class="card-body border-bottom">
        <form method="get" class="row g-2">
            <div class="col-md-6"><input name="q" value="<?= esc((string) $search) ?>" class="form-control" placeholder="<?= esc(lang('App.search'), 'attr') ?>"></div>
            <div class="col-md-4">
                <select name="status" class="form-select">
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>><?= lang('App.all') ?></option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>><?= lang('System.adminStatus_active') ?></option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>><?= lang('System.adminInvitationPending') ?></option>
                    <option value="disabled" <?= $status === 'disabled' ? 'selected' : '' ?>><?= lang('System.adminStatus_disabled') ?></option>
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-outline-primary w-100" type="submit"><?= lang('App.filter') ?></button></div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col"><?= lang('App.colName') ?></th>
                        <th scope="col" class="d-none d-md-table-cell"><?= lang('App.colEmail') ?></th>
                        <th scope="col"><?= lang('App.navParishes') ?></th>
                        <th scope="col"><?= lang('App.colStatus') ?></th>
                        <th scope="col" class="text-end"><?= lang('App.colActions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($admins)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3"><?= lang('App.noResults') ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($admins as $admin): ?>
                            <?php $isPrimary = !empty($admin['primary_user_id']) && (int) ($admin['primary_user_id'] ?? 0) === (int) ($admin['user_id'] ?? 0); ?>
                            <tr>
                                <td>
                                    <?= esc(trim((string) ($admin['first_name'] ?? '') . ' ' . (string) ($admin['last_name'] ?? ''))) ?>
                                    <?php if ($isPrimary): ?>
                                        <span class="badge bg-success ms-1"><?= lang('System.primaryLabel') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-md-table-cell"><?= esc((string) ($admin['email'] ?? '')) ?></td>
                                <td><?= esc((string) ($admin['parish_name'] ?? '')) ?></td>
                                <td>
                                    <?php if (!empty($admin['active']) && !empty($admin['force_reset'])): ?>
                                        <span class="badge text-bg-warning"><?= lang('System.adminInvitationPending') ?></span>
                                    <?php elseif (!empty($admin['active'])): ?>
                                        <span class="badge text-bg-success"><?= lang('System.adminStatus_active') ?></span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary"><?= lang('System.adminStatus_disabled') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#editParishAdminModal"
                                        data-id="<?= (int) $admin['user_id'] ?>" data-first-name="<?= esc((string) ($admin['first_name'] ?? ''), 'attr') ?>"
                                        data-last-name="<?= esc((string) ($admin['last_name'] ?? ''), 'attr') ?>" data-email="<?= esc((string) ($admin['email'] ?? ''), 'attr') ?>"
                                        data-phone="<?= esc((string) ($admin['phone'] ?? ''), 'attr') ?>" data-parish-id="<?= (int) ($admin['parish_id'] ?? 0) ?>">
                                        <?= lang('App.edit') ?>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary me-1" data-bs-toggle="modal" data-bs-target="#resetPasswordModal"
                                        data-id="<?= (int) $admin['user_id'] ?>">
                                        <?= lang('System.resetPasswordBtn') ?>
                                    </button>
                                    <?php if (!empty($admin['active'])): ?>
                                        <form action="<?= base_url('diocese/parish-admins/deactivate/' . (int) $admin['user_id']) ?>" method="post" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-warning me-1"><?= lang('System.deactivateAdminUser') ?></button>
                                        </form>
                                    <?php else: ?>
                                        <form action="<?= base_url('diocese/parish-admins/reactivate/' . (int) $admin['user_id']) ?>" method="post" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-success me-1"><?= lang('System.reactivateAdminUser') ?></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php $adminFullName = trim((string) ($admin['first_name'] ?? '') . ' ' . (string) ($admin['last_name'] ?? '')); ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteParishAdminModal"
                                        data-id="<?= (int) $admin['user_id'] ?>" data-name="<?= esc($adminFullName, 'attr') ?>">
                                        <?= lang('App.delete') ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('modals') ?>

<div class="modal fade" id="createParishAdminModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= base_url('diocese/parish-admins/store') ?>" method="post">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title"><?= lang('System.btnCreateAdminUser') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label"><?= lang('System.firstName') ?> *</label><input name="first_name" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label"><?= lang('System.lastName') ?> *</label><input name="last_name" class="form-control" required></div>
                        <div class="col-12"><label class="form-label"><?= lang('App.colEmail') ?> *</label><input name="email" type="email" class="form-control" required></div>
                        <div class="col-12">
                            <label class="form-label"><?= lang('App.navParishes') ?> *</label>
                            <select name="parish_id" class="form-select" required>
                                <option value=""><?= lang('Members.selectParish') ?></option>
                                <?php foreach ($parishes as $parish): ?>
                                    <option value="<?= (int) $parish['id'] ?>"><?= esc((string) $parish['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12"><label class="form-label"><?= lang('App.colPhone') ?></label><input name="phone" class="form-control" inputmode="tel"></div>
                        <div class="col-12"><div class="form-text"><?= lang('System.adminInvitationWillBeSent') ?></div></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="submit" class="btn btn-primary"><?= lang('System.btnCreateAdminUser') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editParishAdminModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="editParishAdminForm" action="" method="post">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title"><?= lang('App.edit') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label"><?= lang('System.firstName') ?> *</label><input id="editFirstName" name="first_name" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label"><?= lang('System.lastName') ?> *</label><input id="editLastName" name="last_name" class="form-control" required></div>
                        <div class="col-12"><label class="form-label"><?= lang('App.colEmail') ?></label><input id="editEmail" class="form-control" disabled><small class="text-muted"><?= lang('System.emailCannotChange') ?></small></div>
                        <div class="col-12">
                            <label class="form-label"><?= lang('App.navParishes') ?> *</label>
                            <select id="editParishId" name="parish_id" class="form-select" required>
                                <?php foreach ($parishes as $parish): ?>
                                    <option value="<?= (int) $parish['id'] ?>"><?= esc((string) $parish['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12"><label class="form-label"><?= lang('App.colPhone') ?></label><input id="editPhone" name="phone" class="form-control" inputmode="tel"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="submit" class="btn btn-outline-primary"><?= lang('App.save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="resetPasswordForm" action="" method="post">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title"><?= lang('System.resetPassword') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?= lang('System.newPassword') ?> *</label>
                        <input type="password" name="new_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= lang('System.confirmPassword') ?> *</label>
                        <input type="password" name="new_password_confirm" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="submit" class="btn btn-outline-primary"><?= lang('System.resetPassword') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteParishAdminModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="deleteParishAdminForm" action="" method="post">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title"><?= lang('App.delete') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>
                        <span id="deleteParishAdminBody"></span>
                    </div>
                    <div class="mb-3">
                        <label for="deleteParishAdminReason" class="form-label"><?= lang('System.adminDeleteReasonLabel') ?> <span class="text-danger">*</span></label>
                        <textarea id="deleteParishAdminReason" name="delete_reason" class="form-control" rows="3" minlength="10" maxlength="500" required placeholder="<?= esc(lang('System.adminDeleteReasonPlaceholder'), 'attr') ?>"></textarea>
                        <div class="form-text text-end"><span id="deleteParishAdminReasonCount">0</span>/500</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="button" id="deleteParishAdminConfirmBtn" class="btn btn-danger" disabled><?= lang('App.delete') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    document.getElementById('editParishAdminModal').addEventListener('show.bs.modal', function (e) {
        const btn = e.relatedTarget;
        document.getElementById('editParishAdminForm').action = '<?= base_url('diocese/parish-admins/update/') ?>' + btn.dataset.id;
        document.getElementById('editFirstName').value = btn.dataset.firstName;
        document.getElementById('editLastName').value = btn.dataset.lastName;
        document.getElementById('editEmail').value = btn.dataset.email;
        document.getElementById('editPhone').value = btn.dataset.phone || '';
        document.getElementById('editParishId').value = btn.dataset.parishId || '';
    });

    document.getElementById('resetPasswordModal').addEventListener('show.bs.modal', function (e) {
        const btn = e.relatedTarget;
        document.getElementById('resetPasswordForm').action = '<?= base_url('diocese/parish-admins/reset-password/') ?>' + btn.dataset.id;
    });

    document.getElementById('deleteParishAdminModal').addEventListener('show.bs.modal', function (e) {
        const btn = e.relatedTarget;
        const name = btn ? btn.dataset.name : '';
        const form = document.getElementById('deleteParishAdminForm');
        form.action = '<?= base_url('diocese/parish-admins/delete/') ?>' + (btn ? btn.dataset.id : '');
        form.dataset.name = name;
        document.getElementById('deleteParishAdminBody').textContent = '<?= esc(lang('System.confirmDeleteAdminUserNamed', ['{0}']), 'js') ?>'.replace('{0}', name);
        document.getElementById('deleteParishAdminReason').value = '';
        document.getElementById('deleteParishAdminReasonCount').textContent = '0';
        document.getElementById('deleteParishAdminConfirmBtn').disabled = true;
    });

    document.getElementById('deleteParishAdminReason').addEventListener('input', function () {
        const len = this.value.trim().length;
        document.getElementById('deleteParishAdminReasonCount').textContent = this.value.length;
        document.getElementById('deleteParishAdminConfirmBtn').disabled = len < 10;
    });

    document.getElementById('deleteParishAdminConfirmBtn').addEventListener('click', function () {
        const reason = document.getElementById('deleteParishAdminReason').value.trim();
        if (reason.length < 10) {
            return;
        }
        if (typeof Swal === 'undefined') {
            document.getElementById('deleteParishAdminForm').submit();
            return;
        }
        Swal.fire({
            title: '<?= esc(lang('System.confirmDeleteAdminUserFinal'), 'js') ?>'.replace('{0}', document.getElementById('deleteParishAdminForm').dataset.name || ''),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<?= esc(lang('App.confirm'), 'js') ?>',
            cancelButtonText: '<?= esc(lang('App.cancel'), 'js') ?>'
        }).then(function (result) {
            if (result.isConfirmed) {
                document.getElementById('deleteParishAdminForm').submit();
            }
        });
    });
</script>
<?= $this->endSection() ?>
