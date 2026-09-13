<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php
$systemAdminPermissions = $systemAdminPermissions ?? [];
$permissionsHierarchy = $permissionsHierarchy ?? [];
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>
            <?= lang('System.sectionSystemAdmins') ?>
        </strong>
        <div>
            <button type="button" class="btn btn-sm btn-outline-info me-2" data-bs-toggle="modal"
                data-bs-target="#permissionsModal">
                <i class="bi bi-shield-check me-1"></i>View Permissions
            </button>
            <?php if ($can_create_system_admin ?? false): ?>
            <button type="button" class="btn btn-sm btn-primary me-2" data-bs-toggle="modal"
                data-bs-target="#createSystemAdminModal">
                <i class="bi bi-plus-lg me-1"></i>
                <?= lang('App.addNew') ?>
            </button>
            <?php endif; ?>
            <a href="<?= base_url('system/dashboard') ?>" class="btn btn-sm btn-outline-secondary" aria-label="<?= esc(lang('App.back')) ?>">
                <i class="bi bi-arrow-left me-1"></i><?= lang('App.back') ?>
            </a>
        </div>
    </div>
    <div class="card-body p-0">
        <form class="p-3 border-bottom" method="get" action="<?= base_url('system/system-admins') ?>">
            <div class="input-group">
                <input type="search" class="form-control" name="q" value="<?= esc((string) ($search ?? ''), 'attr') ?>" placeholder="<?= esc(lang('App.search'), 'attr') ?>">
                <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th><?= lang('App.colName') ?></th>
                        <th class="d-none d-md-table-cell"><?= lang('App.colEmail') ?></th>
                        <th class="d-none d-lg-table-cell"><?= lang('App.colStatus') ?></th>
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
                            <tr>
                                <td>
                                    <?= esc(trim((string) ($admin['first_name'] ?? '') . ' ' . (string) ($admin['last_name'] ?? ''))) ?>
                                </td>
                                <td class="d-none d-md-table-cell"><?= esc((string) ($admin['email'] ?? '')) ?></td>
                                <td class="d-none d-lg-table-cell">
                                    <?php
                                    $status = (string) ($admin['account_status'] ?? 'active');
                                    $statusClass = match ($status) {
                                        'invitation_pending' => 'text-bg-warning',
                                        'password_setup_required' => 'text-bg-info',
                                        'disabled' => 'text-bg-secondary',
                                        default => 'text-bg-success',
                                    };
                                    ?>
                                    <span class="badge <?= $statusClass ?>"><?= lang('System.adminStatus_' . $status) ?></span>
                                </td>
                                <td class="text-end">
                                    <?php
                                        $isSelf = (int) (auth()->id() ?? 0) === (int) $admin['user_id'];
                                        $isPrimaryAdmin = (int) ($current_user_id ?? 0) === (int) ($primary_system_admin_id ?? 0);
                                        $isPrimaryRow = (int) ($admin['is_primary'] ?? 0) === 1;
                                    ?>
                                    <?= $this->setData([
                                        'admin' => $admin,
                                        'editModalId' => 'editSystemAdminModal',
                                        'resetModalId' => 'resetPasswordModal',
                                        'transferPrimaryModalId' => 'transferPrimaryModal',
                                        'isSelf' => $isSelf,
                                        'canEdit' => $can_update_system_admin && ! $isPrimaryRow,
                                        'canResetPassword' => $can_reset_password && ! $isPrimaryRow && ! $isSelf,
                                        'showTransferPrimary' => ! $isPrimaryRow && $isPrimaryAdmin,
                                        'canDelete' => $can_delete_system_admin && ! $isPrimaryRow,
                                        'canManagePermissions' => $can_manage_permissions && ! $isPrimaryRow && ! $isSelf,
                                        'canResendInvitation' => $isPrimaryAdmin && ! $isPrimaryRow && (string) ($admin['account_status'] ?? '') === 'invitation_pending',
                                    ], 'raw')->include('System/partials/admin_actions', [], false) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (($total_pages ?? 1) > 1): ?>
            <?php $query = ($search ?? '') !== '' ? '&q=' . rawurlencode((string) $search) : ''; ?>
            <nav class="p-3" aria-label="<?= esc(lang('App.search'), 'attr') ?>">
                <ul class="pagination pagination-sm justify-content-end mb-0">
                    <li class="page-item <?= ($page ?? 1) <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= base_url('system/system-admins?page=' . max(1, (int) ($page ?? 1) - 1) . $query) ?>">&laquo;</a>
                    </li>
                    <?php for ($number = 1; $number <= (int) $total_pages; $number++): ?>
                        <li class="page-item <?= $number === (int) ($page ?? 1) ? 'active' : '' ?>">
                            <a class="page-link" href="<?= base_url('system/system-admins?page=' . $number . $query) ?>"><?= $number ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($page ?? 1) >= (int) $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= base_url('system/system-admins?page=' . min((int) $total_pages, (int) ($page ?? 1) + 1) . $query) ?>">&raquo;</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<?php if ($can_create_system_admin ?? false): ?>
<!-- ===== Create System Admin Modal ===== -->
<div class="modal fade" id="createSystemAdminModal" tabindex="-1" aria-labelledby="createSystemAdminModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= base_url('system/admins/store-system') ?>" method="post">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="createSystemAdminModalLabel">
                        <i class="bi bi-plus-circle me-2"></i>
                        <?= lang('System.btnCreateAdminUser') ?> &ndash;
                        <?= lang('System.roleSystemAdmin') ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">
                                <?= lang('System.firstName') ?> *
                            </label>
                            <input name="first_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">
                                <?= lang('System.lastName') ?> *
                            </label>
                            <input name="last_name" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">
                                <?= lang('App.colEmail') ?> *
                            </label>
                            <input name="email" type="email" class="form-control" autocomplete="off" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?= lang('App.colPhone') ?></label>
                            <input name="phone" type="tel" class="form-control" inputmode="tel">
                        </div>
                        <div class="col-12">
                            <div class="form-text"><?= lang('System.adminInvitationWillBeSent') ?></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <?= lang('App.cancel') ?>
                    </button>
                    <button type="submit" id="createSASubmit" class="btn btn-primary">
                        <?= lang('App.save') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?= $this->include('System/partials/reset_password_modal') ?>

<?php if (!empty($can_manage_permissions) && $can_manage_permissions): ?>
<div class="modal fade" id="managePermissionsModal" tabindex="-1" aria-labelledby="managePermissionsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="managePermissionsForm" action="" method="post">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="managePermissionsModalLabel">
                        <i class="bi bi-shield-lock me-2"></i>
                        <?= lang('System.managePermissions') ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted" id="managePermissionsTargetName"></p>
                    <?php
                    $delegationOptions = [
                        'system.admin.create' => 'permissionCreateSystemAdmin',
                        'system.admin.update' => 'permissionUpdateSystemAdmin',
                        'system.admin.delete' => 'permissionDeleteSystemAdmin',
                        'system.admin.reset_password' => 'permissionResetPasswordSystemAdmin',
                    ];
                    foreach ($delegationOptions as $permission => $labelKey):
                    ?>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="perm_<?= esc(str_replace('.', '_', $permission), 'attr') ?>" name="permissions[]" value="<?= esc($permission, 'attr') ?>">
                            <label class="form-check-label" for="perm_<?= esc(str_replace('.', '_', $permission), 'attr') ?>"><?= lang('System.' . $labelKey) ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <?= lang('App.cancel') ?>
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <?= lang('App.save') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ===== Edit System Admin Modal ===== -->
<div class="modal fade" id="editSystemAdminModal" tabindex="-1" aria-labelledby="editSystemAdminModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="editSystemAdminForm" action="" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="role_group" value="systemadmin">
                <input type="hidden" name="province_id" value="">
                <input type="hidden" name="diocese_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="editSystemAdminModalLabel"><?= lang('App.edit') ?> &ndash;
                        <?= lang('System.roleSystemAdmin') ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><?= lang('System.firstName') ?> *</label>
                            <input name="first_name" id="editSAFirstName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= lang('System.lastName') ?> *</label>
                            <input name="last_name" id="editSALastName" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?= lang('App.colEmail') ?></label>
                            <input id="editSAEmail" class="form-control" disabled>
                            <small class="text-muted"><?= lang('System.emailCannotChange') ?></small>
                        </div>
                        <div class="col-12">
                            <label for="editSAPhone" class="form-label"><?= lang('App.colPhone') ?></label>
                            <input name="phone" id="editSAPhone" class="form-control" inputmode="tel">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary"
                        data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="submit" class="btn btn-primary"><?= lang('App.save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== Permissions Modal ===== -->
<div class="modal fade" id="permissionsModal" tabindex="-1" aria-labelledby="permissionsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="permissionsModalLabel">
                    <i class="bi bi-shield-check me-2"></i>System Admin Permissions
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">All system administrators have full access to all system permissions.</p>
                <div id="permissionsModalContent">
                    <?php if (!empty($permissionsHierarchy)): ?>
                        <?php foreach ($permissionsHierarchy as $category => $data): ?>
                            <?php
                            $categoryPerms = [];
                            foreach ($data['permissions'] as $perm) {
                                if (isset($systemAdminPermissions[$perm['key']])) {
                                    $categoryPerms[] = $perm;
                                }
                            }
                            ?>
                            <?php if (!empty($categoryPerms)): ?>
                                <div class="mb-3">
                                    <strong class="text-primary"><?= ucfirst($category) ?></strong>
                                    <div class="ms-3 mt-1">
                                        <?php foreach ($categoryPerms as $perm): ?>
                                            <div class="small text-muted">• <?= esc($perm['description']) ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">No permissions data available</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary"
                    data-bs-dismiss="modal"><?= lang('App.close') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- ===== Transfer Primary Modal ===== -->
<div class="modal fade" id="transferPrimaryModal" tabindex="-1" aria-labelledby="transferPrimaryModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="transferPrimaryForm" action="" method="post">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="transferPrimaryModalLabel"><?= lang('System.btnTransferPrimary') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">
                        <?= lang('System.confirmTransferPrimary') ?>
                        <strong id="transferPrimaryTargetName"></strong>
                    </p>
                    <div class="mb-3">
                        <label class="form-label"><?= lang('System.currentPassword') ?> *</label>
                        <input name="current_password" type="password" class="form-control" required
                            autocomplete="current-password" minlength="8">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= lang('System.otpCode') ?></label>
                        <input name="otp_code" type="text" class="form-control" inputmode="numeric" maxlength="6"
                            pattern="[0-9]{6}" autocomplete="one-time-code">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary"
                        data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="submit" class="btn btn-warning"><?= lang('System.btnTransferPrimary') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>

<script>
    document.getElementById('editSystemAdminModal').addEventListener('show.bs.modal', function (e) {
        const btn = e.relatedTarget;
        document.getElementById('editSystemAdminForm').action = '<?= base_url('system/admins/update/') ?>' + btn.dataset.id;
        document.getElementById('editSAFirstName').value = btn.dataset.firstName;
        document.getElementById('editSALastName').value = btn.dataset.lastName;
        document.getElementById('editSAEmail').value = btn.dataset.email;
        document.getElementById('editSAPhone').value = btn.dataset.phone || '';
    });

    document.getElementById('transferPrimaryModal').addEventListener('show.bs.modal', function (e) {
        const btn = e.relatedTarget;
        document.getElementById('transferPrimaryForm').action = '<?= base_url('system/admins/transfer-primary/') ?>' + btn.dataset.id;
        document.getElementById('transferPrimaryTargetName').textContent = btn.dataset.name;
    });

    const managePermissionsModal = document.getElementById('managePermissionsModal');
    if (managePermissionsModal) {
        managePermissionsModal.addEventListener('show.bs.modal', function (e) {
            const btn = e.relatedTarget;
            document.getElementById('managePermissionsForm').action = '<?= base_url('system/admins/permissions/') ?>' + btn.dataset.id;
            document.getElementById('managePermissionsTargetName').textContent = btn.dataset.name;
            document.getElementById('perm_system_admin_create').checked = btn.dataset.canCreate === '1';
            document.getElementById('perm_system_admin_update').checked = btn.dataset.canUpdate === '1';
            document.getElementById('perm_system_admin_delete').checked = btn.dataset.canDelete === '1';
            document.getElementById('perm_system_admin_reset_password').checked = btn.dataset.canResetPassword === '1';
        });
    }

</script>
<?= $this->endSection() ?>