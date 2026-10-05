<?= $this->extend('Diocese\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-start align-items-center gap-2 mb-3">
    <a href="<?= base_url('diocese/clergy') ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Clergy.backToClergy') ?>
    </a>
    <a href="<?= base_url('diocese/clergy/' . (int) $clergy['id'] . '/edit') ?>" class="btn btn-outline-primary">
        <i class="bi bi-pencil" aria-hidden="true"></i> <?= lang('App.edit') ?>
    </a>
</div>

<?php
$portalAccess = (string) ($clergy['portal_access'] ?? 'none');
$portalColor = ['enabled' => 'success', 'invited' => 'warning', 'none' => 'secondary'][$portalAccess] ?? 'secondary';
$activeColor = (int) ($clergy['active'] ?? 0) === 1 ? 'success' : 'secondary';
$fullName = trim((string) ($clergy['first_name'] ?? '') . ' ' . (string) ($clergy['middle_name'] ?? '') . ' ' . (string) ($clergy['last_name'] ?? ''));
$fullName = preg_replace('/\s+/', ' ', $fullName);
?>

<div class="row">
    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title"><?= esc($fullName) ?></h5>
                <dl class="row mb-0">
                    <dt class="col-sm-5"><?= lang('Clergy.email') ?></dt>
                    <dd class="col-sm-7"><?= esc($identity['secret'] ?? '-') ?></dd>
                    <dt class="col-sm-5"><?= lang('Clergy.colPortalAccess') ?></dt>
                    <dd class="col-sm-7"><span class="badge border border-<?= $portalColor ?> text-<?= $portalColor ?> fw-normal"><?= esc($portalAccess) ?></span></dd>
                    <dt class="col-sm-5"><?= lang('Clergy.colStatus') ?></dt>
                    <dd class="col-sm-7"><span class="badge border border-<?= $activeColor ?> text-<?= $activeColor ?> fw-normal"><?= (int) ($clergy['active'] ?? 0) === 1 ? lang('Clergy.statusActive') : lang('Clergy.statusDisabled') ?></span></dd>
                    <dt class="col-sm-5"><?= lang('Clergy.colInvitationSent') ?></dt>
                    <dd class="col-sm-7"><?= esc($identity['invitation_sent_at'] ?? '-') ?></dd>
                </dl>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <h6 class="card-subtitle mb-2 text-muted"><?= lang('Clergy.accountActions') ?></h6>
                <div class="d-flex flex-wrap gap-2">
                    <form action="<?= base_url('diocese/clergy/' . (int) $clergy['id'] . '/resend-invitation') ?>" method="post" class="m-0"><?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-envelope-paper" aria-hidden="true"></i> <?= lang('Clergy.actionResendInvitation') ?></button>
                    </form>
                    <?php if ((int) ($clergy['active'] ?? 0) === 1): ?>
                        <form action="<?= base_url('diocese/clergy/' . (int) $clergy['id'] . '/deactivate') ?>" method="post" class="m-0" onsubmit="return confirm('<?= esc(lang('Clergy.confirmDisablePortalAccess'), 'js') ?>');"><?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-slash-circle" aria-hidden="true"></i> <?= lang('Clergy.actionDisablePortalAccess') ?></button>
                        </form>
                    <?php endif; ?>
                </div>
                <small class="text-muted d-block mt-2"><?= lang('Clergy.disablePortalAccessHelp') ?></small>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="card-subtitle mb-3 text-muted"><?= lang('Clergy.ordinationSummary') ?></h6>
                <?php if (!empty($canonical['ordinations'])): ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($canonical['ordinations'] as $ordination): ?>
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

        <div class="card mb-3">
            <div class="card-body">
                <h6 class="card-subtitle mb-3 text-muted"><?= lang('Clergy.standingSummary') ?></h6>
                <?php if (!empty($canonical['standings'])): ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($canonical['standings'] as $standing): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span><?= esc($standing['status_code'] ?? '-') ?></span>
                                <span class="text-muted small"><?= esc($standing['effective_from'] ?? '') ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted mb-0 small"><?= lang('Clergy.noStanding') ?></p>
                <?php endif; ?>
                <small class="text-muted d-block mt-2"><?= lang('Clergy.canonicalFieldsReadOnlyHelp') ?></small>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
