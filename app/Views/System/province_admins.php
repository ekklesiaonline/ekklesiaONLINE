<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php
$provinces = $provinces ?? [];
$admins = $admins ?? [];
?>


<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong><?= lang('System.sectionProvinceAdmins') ?></strong>
        <div>
            <button type="button" class="btn btn-sm btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#createProvinceAdminModal">
                <i class="bi bi-plus-circle me-1"></i><?= lang('App.addNew') ?>
            </button>
            <a href="<?= base_url('system/dashboard') ?>" class="btn btn-sm btn-outline-secondary" aria-label="<?= esc(lang('App.back')) ?>">
                <i class="bi bi-arrow-left me-1"></i><?= lang('App.back') ?>
            </a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th><?= lang('App.colName') ?></th>
                        <th class="d-none d-md-table-cell"><?= lang('App.colEmail') ?></th>
                        <th><?= lang('System.provinceName') ?></th>
                        <th class="text-end"><?= lang('App.colActions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($admins)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted py-3"><?= lang('App.noResults') ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($admins as $admin): ?>
                            <?php $isPrimary = (int) ($admin['user_id'] ?? 0) === (int) ($primary_by_province[(int) ($admin['province_id'] ?? 0)] ?? 0); ?>
                            <tr>
                                <td>
                                    <?= esc(trim((string) ($admin['first_name'] ?? '') . ' ' . (string) ($admin['last_name'] ?? ''))) ?>
                                    <?php if ($isPrimary): ?>
                                        <span class="badge bg-success ms-1">Primary</span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-md-table-cell"><?= esc((string) ($admin['email'] ?? '')) ?></td>
                                <td>
                                    <?php if (!empty($admin['province_name'])): ?>
                                        <span class="badge border border-primary text-primary fw-normal">
                                            <?= esc((string) ($admin['province_number'] . ' - ' . $admin['province_name'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?= $this->setData([
                                        'admin' => $admin,
                                        'editModalId' => 'editProvinceAdminModal',
                                        'resetModalId' => 'resetPasswordModal',
                                        'isSelf'    => (int) (auth()->id() ?? 0) === (int) $admin['user_id'],
                                        'canDelete' => true,
                                    ], 'raw')->include('System/partials/admin_actions', [], false) ?>
                                    <?php if (! $isPrimary && !empty($admin['province_id'])): ?>
                                        <form action="<?= base_url('system/admins/grant-province-primary/' . (int) $admin['user_id']) ?>" method="post" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-success ms-1" onclick="return confirm('Make this user the primary province admin?')">Make primary</button>
                                        </form>
                                    <?php endif; ?>
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

<?= $this->include('System/partials/reset_password_modal') ?>

<!-- ===== Create Province Admin Modal ===== -->
<div class="modal fade" id="createProvinceAdminModal" tabindex="-1" aria-labelledby="createProvinceAdminModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= base_url('system/admins/store-province') ?>" method="post">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="createProvinceAdminModalLabel">
                        <i class="bi bi-plus-circle me-2"></i><?= lang('System.btnCreateAdminUser') ?> &ndash; <?= lang('System.roleProvinceAdmin') ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="createPAProvinceId" class="form-label"><?= lang('System.provinceName') ?> *</label>
                            <select id="createPAProvinceId" name="province_id" class="form-select" required>
                                <option value=""><?= lang('System.selectProvince') ?></option>
                                <?php foreach ($provinces as $province): ?>
                                    <option value="<?= (int) $province['id'] ?>"><?= esc((string) ($province['province_number'] . ' - ' . $province['name'])) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="createPAFirstName" class="form-label"><?= lang('System.firstName') ?> *</label>
                            <input id="createPAFirstName" name="first_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label for="createPALastName" class="form-label"><?= lang('System.lastName') ?> *</label>
                            <input id="createPALastName" name="last_name" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label for="createPAEmail" class="form-label"><?= lang('App.colEmail') ?> *</label>
                            <input id="createPAEmail" name="email" type="email" class="form-control" autocomplete="off" required>
                        </div>
                        <div class="col-12">
                            <label for="createPAPhone" class="form-label"><?= lang('App.colPhone') ?></label>
                            <input id="createPAPhone" name="phone" class="form-control" inputmode="tel">
                        </div>
                        <div class="col-12">
                            <div class="form-text"><?= lang('System.adminInvitationWillBeSent') ?></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="submit" id="createPASubmit" class="btn btn-primary"><?= lang('System.btnCreateAdminUser') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== Edit Province Admin Modal ===== -->
<div class="modal fade" id="editProvinceAdminModal" tabindex="-1" aria-labelledby="editProvinceAdminModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="editProvinceAdminForm" action="" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="role_group" value="provinceadmin">
                <input type="hidden" name="diocese_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="editProvinceAdminModalLabel"><?= lang('App.edit') ?> &ndash; <?= lang('System.roleProvinceAdmin') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="editPAProvinceId" class="form-label"><?= lang('System.provinceName') ?> *</label>
                            <select id="editPAProvinceId" name="province_id" class="form-select" required>
                                <option value=""><?= lang('System.selectProvince') ?></option>
                                <?php foreach ($provinces as $province): ?>
                                    <option value="<?= (int) $province['id'] ?>"><?= esc((string) ($province['province_number'] . ' - ' . $province['name'])) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="editPAFirstName" class="form-label"><?= lang('System.firstName') ?> *</label>
                            <input id="editPAFirstName" name="first_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editPALastName" class="form-label"><?= lang('System.lastName') ?> *</label>
                            <input id="editPALastName" name="last_name" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label for="editPAEmail" class="form-label"><?= lang('App.colEmail') ?></label>
                            <input id="editPAEmail" class="form-control" disabled>
                            <small class="text-muted"><?= lang('System.emailCannotChange') ?></small>
                        </div>
                        <div class="col-12">
                            <label for="editPAPhone" class="form-label"><?= lang('App.colPhone') ?></label>
                            <input id="editPAPhone" name="phone" class="form-control" inputmode="tel">
                        </div>
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

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    document.getElementById('editProvinceAdminModal').addEventListener('show.bs.modal', function(e) {
        const btn = e.relatedTarget;
        document.getElementById('editProvinceAdminForm').action = '<?= base_url('system/admins/update/') ?>' + btn.dataset.id;
        document.getElementById('editPAFirstName').value = btn.dataset.firstName;
        document.getElementById('editPALastName').value = btn.dataset.lastName;
        document.getElementById('editPAEmail').value = btn.dataset.email;
        document.getElementById('editPAPhone').value = btn.dataset.phone || '';
        const sel = document.getElementById('editPAProvinceId');
        if (sel) {
            sel.value = btn.dataset.provinceId;
        }
    });
</script>
<?= $this->endSection() ?>