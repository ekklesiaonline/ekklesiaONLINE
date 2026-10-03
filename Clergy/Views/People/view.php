<?= $this->extend('Clergy\Views\layouts/main') ?>
<?php
$profile = $profile ?? [];
$person = $person ?? [];
$family = $family ?? [];
$affiliations = $affiliations ?? [];
$membership_details = $membership_details ?? [];
$group_memberships = $group_memberships ?? [];
$pathway_stage = $pathway_stage ?? null;
$status_history = $status_history ?? [];
$leadership = $leadership ?? [];
$onboarding = $onboarding ?? [];
$can_edit = $can_edit ?? false;
$can_delete = $can_delete ?? false;

$profileId = (int) ($profile['id'] ?? 0);
$personId = (int) ($profile['person_id'] ?? 0);
$userId = (int) ($profile['user_id'] ?? 0);
$parishId = (int) ($person['parish_id'] ?? 0);
$portalAccess = strtolower(trim((string) ($profile['portal_access'] ?? 'none')));

// Propagate template-computed values to included partials.
$this->setVar('profileId', $profileId);
$this->setVar('personId', $personId);
$this->setVar('userId', $userId);
$this->setVar('parishId', $parishId);
$this->setVar('portalAccess', $portalAccess);
?>

<?= $this->section('content') ?>

<style>
    .people-profile-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        object-fit: cover;
    }
    .people-profile-avatar-fallback {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
    }
    .people-profile-avatar-fallback-icon {
        font-size: 2.5rem;
        color: #adb5bd;
        line-height: 1;
    }
</style>

<div class="container-fluid">
    <div class="row g-3">
        <div class="col-12 d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h4 class="mb-0"><?= esc(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')) ?></h4>
            <div class="d-flex gap-2">
                <?php if ($can_edit && $profileId > 0): ?>
                    <a href="<?= base_url('members/edit/' . $profileId) ?>" class="btn btn-warning btn-sm">
                        <i class="bi bi-pencil" aria-hidden="true"></i> <?= lang('App.edit') ?>
                    </a>
                <?php endif; ?>
                <a href="<?= base_url('people') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('App.back') ?>
                </a>
            </div>
        </div>
    </div>

    <div class="row g-3">
<?= $this->include('Clergy\Views\People/_sidebar') ?>

        <div class="col-md-8">
<?= $this->include('Clergy\Views\People/_overview') ?>

<?= $this->include('Clergy\Views\People/_family_card') ?>

<?= $this->include('Clergy\Views\People/_leadership_card') ?>

<?= $this->include('Clergy\Views\People/_finance_cards') ?>

<?= $this->include('Clergy\Views\People/_sacraments_card') ?>

<?= $this->include('Clergy\Views\People/_history') ?>
        </div>
    </div>
</div>

<?= $this->include('Clergy\Views\People/_modal_leadership') ?>

<?= $this->include('Clergy\Views\People/_modals_finance') ?>

<?= $this->include('Clergy\Views\People/_modals_sacraments') ?>

<?= $this->include('Clergy\Views\People/_modals_family') ?>

<?= $this->include('Clergy\Views\People/_scripts_membership') ?>

<?= $this->include('Clergy\Views\People/_scripts_leadership') ?>

<?= $this->include('Clergy\Views\People/_scripts_domains') ?>

<?= $this->endSection() ?>
