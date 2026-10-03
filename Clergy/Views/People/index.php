<?= $this->extend('Clergy\\Views\\layouts/main') ?>

<?= $this->section('content') ?>

<style>
    .people-name-link {
        text-decoration: none;
        font-size: inherit;
        font-weight: inherit;
        color: var(--bs-primary);
    }

    .people-name-link:hover {
        text-decoration: underline;
    }

    .person-avatar {
        width: 36px;
        height: 36px;
        border-radius: 6px;
        object-fit: cover;
        flex-shrink: 0;
    }

    .person-avatar-fallback {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        flex-shrink: 0;
        user-select: none;
    }

    .person-avatar-fallback-icon {
        font-size: 1.1rem;
        color: #adb5bd;
        line-height: 1;
    }
</style>

<?php
$scope = (string) ($selected_scope ?? 'all');
$status = (string) ($selected_status ?? '');
$selectedPerPage = (int) ($per_page ?? 25);
if (!in_array($selectedPerPage, [25, 50, 100], true)) {
    $selectedPerPage = 25;
}
$canPortalAccessManage = auth()->user()->can('member.edit');
if (!$canPortalAccessManage) {
    try {
        $canPortalAccessManage = auth()->user()->can('portal.manage');
    } catch (\Throwable $e) {
        $canPortalAccessManage = false;
    }
}

// Propagate template-computed values to included partials.
$this->setVar('scope', $scope);
$this->setVar('status', $status);
$this->setVar('selectedPerPage', $selectedPerPage);
$this->setVar('canPortalAccessManage', $canPortalAccessManage);
?>

<div class="d-flex justify-content-between align-items-center mb-3 gap-2">
    <div class="d-flex gap-2">
        <?php if (auth()->user()->can('member.create')): ?>
            <a href="<?= base_url('members/create') ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-person-plus" aria-hidden="true"></i> <?= lang('Services.addPerson') ?>
            </a>
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createNonPortalPersonModal">
                <i class="bi bi-person-exclamation" aria-hidden="true"></i> <?= lang('Members.createStandaloneNonPortal') ?>
            </button>
        <?php endif; ?>
        <?php if (auth()->user()->can('member.delete')): ?>
            <button type="button" id="bulk-delete-people-btn" class="btn btn-outline-danger btn-sm d-none">
                <i class="bi bi-trash3" aria-hidden="true"></i> <?= lang('Members.bulkDeleteSelected') ?>
            </button>
        <?php endif; ?>
        <?php if (auth()->user()->can('member.edit')): ?>
            <button type="button" id="bulk-mark-member-btn" class="btn btn-outline-success btn-sm d-none">
                <i class="bi bi-house-check" aria-hidden="true"></i> <?= lang('People.markAsMember') ?>
            </button>
            <button type="button" id="bulk-remove-member-btn" class="btn btn-outline-secondary btn-sm d-none">
                <i class="bi bi-person-dash" aria-hidden="true"></i> <?= lang('People.removeMemberTag') ?>
            </button>
        <?php endif; ?>
        <form id="people-export-form" method="post" action="<?= base_url('people/export') ?>">
            <?= csrf_field() ?>
            <div id="bulk-export-ids"></div>
            <button type="submit" id="bulk-export-btn" class="btn btn-outline-secondary btn-sm d-none">
                <i class="bi bi-download" aria-hidden="true"></i> <?= lang('App.export') ?>
            </button>
        </form>
    </div>
    <?php
        $totalPendingRegistrations = (int) ($pending_registrations_count ?? 0) + (int) ($incomplete_registrations_count ?? 0);
    ?>
    <?php if (!empty($can_moderate_registrations) && $totalPendingRegistrations > 0): ?>
        <a href="#pendingRegistrationsCard" class="btn btn-outline-warning btn-sm">
            <i class="bi bi-person-check" aria-hidden="true"></i> <?= lang('Members.pendingRegistrationsTitle') ?>
            <span class="badge border border-warning text-warning fw-normal ms-1"><?= $totalPendingRegistrations ?></span>
        </a>
    <?php endif; ?>
</div>

<?= $this->include('Clergy\Views\People/_index_filters') ?>

<?= $this->include('Clergy\Views\People/_index_table') ?>

<?= $this->include('Clergy\Views\People/_index_modals') ?>

<?= $this->include('Clergy\Views\People/_index_pending') ?>

<?= $this->include('Clergy\Views\People/_scripts_index') ?>

<!-- Create Non-Portal Person Modal -->
<?= view('Clergy\Views\Members/_non_portal_modal', [
    'modalId'     => 'createNonPortalPersonModal',
    'fieldPrefix' => 'np',
    'formAction'  => base_url('people/store-non-portal'),
    'modalHint'   => lang('Members.nonPortalPersonModalHint'),
]) ?>

<?= $this->include('Clergy\Views\People/_scripts_pending') ?>

<?= $this->endSection() ?>
