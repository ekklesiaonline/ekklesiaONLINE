<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php if (!empty($profileIncomplete)): ?>
    <div class="alert alert-info d-flex justify-content-between align-items-center">
        <span><i class="bi bi-person-lines-fill" aria-hidden="true"></i> <?= lang('ClergyPortal.completeProfilePrompt') ?></span>
        <a href="<?= base_url('clergy/profile') ?>" class="btn btn-sm btn-outline-primary"><?= lang('ClergyPortal.goToProfile') ?></a>
    </div>
<?php endif; ?>

<?php if (!empty($awaitingAppointment)): ?>
    <div class="card mb-3">
        <div class="card-body text-center py-4">
            <h5 class="card-title"><?= lang('ClergyPortal.awaitingAppointmentTitle') ?></h5>
            <p class="card-text text-muted mb-0"><?= lang('ClergyPortal.awaitingAppointmentBody') ?></p>
        </div>
    </div>
<?php else: ?>
    <div class="card mb-3">
        <div class="card-body">
            <h6 class="card-subtitle mb-3 text-muted"><?= lang('ClergyPortal.currentOffices') ?></h6>
            <ul class="list-group list-group-flush">
                <?php foreach ($positions as $position): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span><?= esc($position['title'] ?? '-') ?></span>
                        <span class="text-muted small"><?= esc($position['parish_name'] ?? $position['diocese_name'] ?? '') ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="card-subtitle mb-3 text-muted"><?= lang('Clergy.ordinationSummary') ?></h6>
                <?php if (!empty($ordinations)): ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($ordinations as $ordination): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span><?= esc($ordination['order_code'] ?? '-') ?></span>
                                <span class="text-muted small"><?= esc($ordination['ordained_on'] ?? '') ?> · <?= esc($ordination['verification_status'] ?? '') ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted mb-0 small"><?= lang('Clergy.noOrdinations') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="card-subtitle mb-3 text-muted"><?= lang('Clergy.standingSummary') ?></h6>
                <?php if (!empty($standings)): ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($standings as $standing): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span><?= esc($standing['status_code'] ?? '-') ?></span>
                                <span class="text-muted small"><?= esc($standing['effective_from'] ?? '') ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted mb-0 small"><?= lang('Clergy.noStanding') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
