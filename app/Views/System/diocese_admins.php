<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php
$provinces = $provinces ?? [];
$dioceses = $dioceses ?? [];
$admins = $admins ?? [];
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong><?= lang('System.sectionDioceseAdmins') ?></strong>
        <div>
            <button type="button" class="btn btn-sm btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#createDioceseAdminModal">
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
                        <th class="d-none d-md-table-cell"><?= lang('System.provinceName') ?></th>
                        <th><?= lang('System.dioceseName') ?></th>
                        <th class="text-end"><?= lang('App.colActions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($admins)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-3"><?= lang('App.noResults') ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($admins as $admin): ?>
                            <?php $isPrimary = (int) ($admin['user_id'] ?? 0) === (int) ($primary_by_diocese[(int) ($admin['diocese_id'] ?? 0)] ?? 0); ?>
                            <tr>
                                <td>
                                    <?= esc(trim((string) ($admin['first_name'] ?? '') . ' ' . (string) ($admin['last_name'] ?? ''))) ?>
                                    <?php if ($isPrimary): ?>
                                        <span class="badge bg-success ms-1"><?= lang('System.primaryLabel') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-md-table-cell"><?= esc((string) ($admin['email'] ?? '')) ?></td>
                                <td class="d-none d-md-table-cell">
                                    <?php if (!empty($admin['province_name'])): ?>
                                        <span class="badge border border-primary text-primary fw-normal">
                                            <?= esc((string) ($admin['province_number'] . ' - ' . $admin['province_name'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($admin['diocese_name'])): ?>
                                        <span class="badge border border-info text-info fw-normal">
                                            <?= esc((string) ($admin['diocese_name'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?= $this->setData([
                                        'admin' => $admin,
                                        'editModalId' => 'editDioceseAdminModal',
                                        'resetModalId' => 'resetPasswordModal',
                                        'isSelf'    => (int) (auth()->id() ?? 0) === (int) $admin['user_id'],
                                        'canDelete' => true,
                                    ], 'raw')->include('System/partials/admin_actions', [], false) ?>
                                    <?php if (! $isPrimary && !empty($admin['diocese_id'])): ?>
                                        <form action="<?= base_url('system/admins/grant-diocese-primary/' . (int) $admin['user_id']) ?>" method="post" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-success ms-1" onclick="return confirm('Make this user the primary diocesan admin?')">Make primary</button>
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

<!-- ===== Create Diocese Admin Modal ===== -->
<div class="modal fade" id="createDioceseAdminModal" tabindex="-1" aria-labelledby="createDioceseAdminModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= base_url('system/admins/store-diocese') ?>" method="post">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="createDioceseAdminModalLabel">
                        <i class="bi bi-plus-circle me-2"></i><?= lang('System.btnCreateAdminUser') ?> &ndash; <?= lang('System.roleDioceseAdmin') ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="createDAProvinceId" class="form-label"><?= lang('System.provinceName') ?> *</label>
                            <select name="province_id" id="createDAProvinceId" class="form-select" required>
                                <option value=""><?= lang('System.selectProvince') ?></option>
                                <?php foreach ($provinces as $province): ?>
                                    <option value="<?= (int) $province['id'] ?>"><?= esc((string) ($province['province_number'] . ' - ' . $province['name'])) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="createDADioceseId" class="form-label"><?= lang('System.dioceseName') ?> *</label>
                            <select name="diocese_id" id="createDADioceseId" class="form-select" required>
                                <option value=""><?= lang('System.selectDiocese') ?></option>
                                <?php foreach ($dioceses as $diocese): ?>
                                    <option value="<?= (int) $diocese['id'] ?>" data-province="<?= (int) $diocese['province_id'] ?>">
                                        <?= esc((string) ($diocese['province_number'] . ' - ' . $diocese['name'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="createDAFirstName" class="form-label"><?= lang('System.firstName') ?> *</label>
                            <input id="createDAFirstName" name="first_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label for="createDALastName" class="form-label"><?= lang('System.lastName') ?> *</label>
                            <input id="createDALastName" name="last_name" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label for="createDAEmail" class="form-label"><?= lang('App.colEmail') ?> *</label>
                            <input id="createDAEmail" name="email" type="email" class="form-control" autocomplete="off" required>
                        </div>
                        <div class="col-12">
                            <label for="createDAPhone" class="form-label"><?= lang('App.colPhone') ?></label>
                            <input id="createDAPhone" name="phone" class="form-control" inputmode="tel">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="createDAMakePrimary" name="make_primary">
                                <label class="form-check-label" for="createDAMakePrimary">Set as primary diocesan admin (only if none exists)</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-text"><?= lang('System.adminInvitationWillBeSent') ?></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="submit" id="createDASubmit" class="btn btn-primary"><?= lang('System.btnCreateAdminUser') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editDioceseAdminModal" tabindex="-1" aria-labelledby="editDioceseAdminModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="editDioceseAdminForm" action="" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="role_group" value="dioceseadmin">
                <div class="modal-header">
                    <h5 class="modal-title" id="editDioceseAdminModalLabel"><?= lang('App.edit') ?> &ndash; <?= lang('System.roleDioceseAdmin') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="editDiaAProvinceId" class="form-label"><?= lang('System.provinceName') ?> *</label>
                            <select id="editDiaAProvinceId" name="province_id" class="form-select" required>
                                <option value=""><?= lang('System.selectProvince') ?></option>
                                <?php foreach ($provinces as $province): ?>
                                    <option value="<?= (int) $province['id'] ?>"><?= esc((string) ($province['province_number'] . ' - ' . $province['name'])) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="editDiaADioceseId" class="form-label"><?= lang('System.dioceseName') ?> *</label>
                            <select id="editDiaADioceseId" name="diocese_id" class="form-select" required>
                                <option value=""><?= lang('System.selectDiocese') ?></option>
                                <?php foreach ($dioceses as $diocese): ?>
                                    <option value="<?= (int) $diocese['id'] ?>" data-province="<?= (int) $diocese['province_id'] ?>">
                                        <?= esc((string) ($diocese['province_number'] . ' - ' . $diocese['name'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="editDiaAFirstName" class="form-label"><?= lang('System.firstName') ?> *</label>
                            <input id="editDiaAFirstName" name="first_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editDiaALastName" class="form-label"><?= lang('System.lastName') ?> *</label>
                            <input id="editDiaALastName" name="last_name" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label for="editDiaAEmail" class="form-label"><?= lang('App.colEmail') ?></label>
                            <input id="editDiaAEmail" class="form-control" disabled>
                            <small class="text-muted"><?= lang('System.emailCannotChange') ?></small>
                        </div>
                        <div class="col-12">
                            <label for="editDiaAPhone" class="form-label"><?= lang('App.colPhone') ?></label>
                            <input id="editDiaAPhone" name="phone" class="form-control" inputmode="tel">
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
    function filterDioceseOptions(selectId, provinceId) {
        const sel = document.getElementById(selectId);
        if (!sel) {
            return;
        }

        const cacheKey = '_dioceseOptions';
        if (!sel[cacheKey]) {
            sel[cacheKey] = Array.from(sel.options).filter(function(opt) {
                return opt.value !== '';
            }).map(function(opt) {
                return {
                    value: opt.value,
                    text: opt.text,
                    province: parseInt(opt.dataset.province || '0', 10) || 0
                };
            });
        }

        const previousValue = sel.value;
        const allOptions = sel[cacheKey];
        const placeholder = sel.options[0] ? sel.options[0].cloneNode(true) : document.createElement('option');

        sel.innerHTML = '';
        sel.appendChild(placeholder);

        allOptions.forEach(function(opt) {
            if (provinceId > 0 && opt.province !== provinceId) {
                return;
            }
            const option = document.createElement('option');
            option.value = opt.value;
            option.text = opt.text;
            option.dataset.province = String(opt.province);
            sel.appendChild(option);
        });

        if (previousValue) {
            const stillAvailable = Array.from(sel.options).some(function(opt) {
                return opt.value === previousValue;
            });
            sel.value = stillAvailable ? previousValue : '';
        } else {
            sel.value = '';
        }
    }

    function bindProvinceDioceseControls(provinceId, dioceseId) {
        const provinceSelect = document.getElementById(provinceId);
        const dioceseSelect = document.getElementById(dioceseId);
        if (!provinceSelect || !dioceseSelect) {
            return;
        }

        provinceSelect.addEventListener('change', function() {
            filterDioceseOptions(dioceseId, parseInt(this.value, 10) || 0);
        });

        dioceseSelect.addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            const selectedProvinceId = parseInt(selected?.dataset.province || '0', 10) || 0;
            if (selectedProvinceId > 0) {
                provinceSelect.value = String(selectedProvinceId);
                filterDioceseOptions(dioceseId, selectedProvinceId);
                dioceseSelect.value = selected.value;
            }
        });

        filterDioceseOptions(dioceseId, parseInt(provinceSelect.value, 10) || 0);
    }

    bindProvinceDioceseControls('createDAProvinceId', 'createDADioceseId');
    bindProvinceDioceseControls('editDiaAProvinceId', 'editDiaADioceseId');

    document.getElementById('editDioceseAdminModal').addEventListener('show.bs.modal', function(e) {
        const btn = e.relatedTarget;
        document.getElementById('editDioceseAdminForm').action = '<?= base_url('system/admins/update/') ?>' + btn.dataset.id;
        document.getElementById('editDiaAFirstName').value = btn.dataset.firstName;
        document.getElementById('editDiaALastName').value = btn.dataset.lastName;
        document.getElementById('editDiaAEmail').value = btn.dataset.email;
        document.getElementById('editDiaAPhone').value = btn.dataset.phone || '';
        const provSel = document.getElementById('editDiaAProvinceId');
        if (provSel) {
            provSel.value = btn.dataset.provinceId;
            filterDioceseOptions('editDiaADioceseId', parseInt(btn.dataset.provinceId, 10) || 0);
        }
        const diocSel = document.getElementById('editDiaADioceseId');
        if (diocSel) {
            diocSel.value = btn.dataset.dioceseId;
        }
    });
</script>
<?= $this->endSection() ?>