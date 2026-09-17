<?php
$adminRawName = trim((string) ($admin['first_name'] ?? '') . ' ' . (string) ($admin['last_name'] ?? ''));
$adminFullName = esc($adminRawName);
$editModalId = $editModalId ?? 'editAdminModal';
$resetModalId = $resetModalId ?? 'resetPasswordModal';
$transferPrimaryModalId = $transferPrimaryModalId ?? 'transferPrimaryModal';
$showTransferPrimary = $showTransferPrimary ?? false;
$canEdit = $canEdit ?? true;
$canResetPassword = $canResetPassword ?? true;
$canDelete = $canDelete ?? true;
$canManagePermissions = $canManagePermissions ?? false;
$canResendInvitation = $canResendInvitation ?? false;
$isSelf = $isSelf ?? false;
?>

<div class="d-flex align-items-center justify-content-end gap-1 table-actions">
    <?php if ($canEdit): ?>
    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal"
        data-bs-target="#<?= esc($editModalId, 'attr') ?>"
        data-id="<?= (int) $admin['user_id'] ?>"
        data-first-name="<?= esc((string) ($admin['first_name'] ?? ''), 'attr') ?>"
        data-last-name="<?= esc((string) ($admin['last_name'] ?? ''), 'attr') ?>"
        data-email="<?= esc((string) ($admin['email'] ?? ''), 'attr') ?>"
        data-phone="<?= esc((string) ($admin['phone'] ?? ''), 'attr') ?>"
        <?php if (!empty($admin['province_id'])): ?>data-province-id="<?= (int) $admin['province_id'] ?>"<?php endif; ?>
        <?php if (!empty($admin['diocese_id'])): ?>data-diocese-id="<?= (int) $admin['diocese_id'] ?>"<?php endif; ?>
        title="<?= esc(lang('App.edit')) ?>">
        <i class="bi bi-pencil" aria-hidden="true"></i>
    </button>
    <?php endif; ?>
    <?php if ($canResendInvitation): ?>
    <form action="<?= base_url('system/admins/resend-invitation/' . (int) $admin['user_id']) ?>" method="post">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-outline-info" title="<?= esc(lang('System.resendAdminInvitation')) ?>">
            <i class="bi bi-envelope-arrow-up" aria-hidden="true"></i>
        </button>
    </form>
    <?php endif; ?>
    <?php if ($canManagePermissions): ?>
    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal"
        data-bs-target="#managePermissionsModal"
        data-id="<?= (int) $admin['user_id'] ?>"
        data-name="<?= esc($adminRawName, 'attr') ?>"
        data-can-create="<?= !empty($admin['delegation_permissions']['system.admin.create']) ? '1' : '0' ?>"
        data-can-update="<?= !empty($admin['delegation_permissions']['system.admin.update']) ? '1' : '0' ?>"
        data-can-delete="<?= !empty($admin['delegation_permissions']['system.admin.delete']) ? '1' : '0' ?>"
        data-can-reset-password="<?= !empty($admin['delegation_permissions']['system.admin.reset_password']) ? '1' : '0' ?>"
        title="<?= esc(lang('System.managePermissions')) ?>">
        <i class="bi bi-shield-lock" aria-hidden="true"></i>
    </button>
    <?php endif; ?>
    <?php if ($canResetPassword): ?>
    <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal"
        data-bs-target="#<?= esc($resetModalId, 'attr') ?>"
        data-id="<?= (int) $admin['user_id'] ?>"
        data-name="<?= esc($adminRawName, 'attr') ?>"
        title="<?= esc(lang('System.resetPasswordTitle')) ?>">
        <i class="bi bi-key" aria-hidden="true"></i>
    </button>
    <?php endif; ?>
    <?php if ($showTransferPrimary): ?>
        <button type="button"
            class="btn btn-outline-info<?= $isSelf ? ' disabled' : '' ?>"
            <?php if (!$isSelf): ?>
                data-bs-toggle="modal"
                data-bs-target="#<?= esc($transferPrimaryModalId, 'attr') ?>"
                data-id="<?= (int) $admin['user_id'] ?>"
                data-name="<?= esc($adminRawName, 'attr') ?>"
            <?php else: ?>
                aria-disabled="true"
                tabindex="-1"
            <?php endif; ?>
            title="<?= esc(lang('System.btnTransferPrimary')) ?>">
            <i class="bi bi-arrow-left-right" aria-hidden="true"></i>
        </button>
    <?php endif; ?>
    <?php if ($canDelete): ?>
        <?php if ($isSelf): ?>
            <button type="button" class="btn btn-outline-danger disabled" aria-disabled="true" tabindex="-1"
                title="<?= esc(lang('App.cannotDeleteSelf')) ?>">
                <i class="bi bi-trash3" aria-hidden="true"></i>
            </button>
        <?php else: ?>
            <form action="<?= base_url('system/admins/delete/' . (int) $admin['user_id']) ?>" method="post"
                data-confirm-title="<?= esc(lang('App.confirm'), 'attr') ?>"
                data-confirm-body="<?= esc(lang('System.confirmDeleteAdminUserNamed', [$adminFullName]), 'attr') ?>"
                data-confirm-double
                data-confirm-swal-title="<?= esc(lang('System.confirmDeleteAdminUserFinal', [$adminFullName]), 'attr') ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-danger" title="<?= esc(lang('App.delete')) ?>">
                    <i class="bi bi-trash3" aria-hidden="true"></i>
                </button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>
