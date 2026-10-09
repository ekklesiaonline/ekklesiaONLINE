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
                            <?php $ordColor = ($ordination['verification_status'] ?? '') === 'verified' ? 'primary' : 'secondary'; ?>
                            <li class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span><?= esc($ordination['order_code'] ?? '-') ?><?= trim((string) ($ordination['deacon_track'] ?? '')) !== '' ? ' · ' . esc($ordination['deacon_track']) : '' ?></span>
                                    <span class="badge border border-<?= $ordColor ?> text-<?= $ordColor ?> fw-normal"><?= esc($ordination['verification_status'] ?? '') ?></span>
                                </div>
                                <small class="text-muted d-block">
                                    <?= esc($ordination['ordained_on'] ?? '—') ?>
                                    <?php if (trim((string) ($ordination['evidence_reference'] ?? '')) !== ''): ?> · <?= esc(lang('Clergy.fieldEvidenceReference')) ?>: <?= esc($ordination['evidence_reference']) ?><?php endif; ?>
                                </small>
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
                            <?php $stCode = strtolower((string) ($standing['status_code'] ?? '')); $stColor = in_array($stCode, ['suspended', 'inhibited'], true) ? 'danger' : 'success'; ?>
                            <li class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span><?= esc($standing['status_code'] ?? '-') ?></span>
                                    <span class="badge border border-<?= $stColor ?> text-<?= $stColor ?> fw-normal"><?= esc($standing['effective_from'] ?? '') ?><?= ($standing['effective_to'] ?? null) !== null ? ' – ' . esc($standing['effective_to']) : '' ?></span>
                                </div>
                                <?php if (trim((string) ($standing['evidence_reference'] ?? '')) !== ''): ?>
                                    <small class="text-muted d-block"><?= esc(lang('Clergy.fieldEvidenceReference')) ?>: <?= esc($standing['evidence_reference']) ?></small>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted mb-0 small"><?= lang('Clergy.noStanding') ?></p>
                <?php endif; ?>
                <small class="text-muted d-block mt-2"><?= lang('Clergy.canonicalFieldsReadOnlyHelp') ?></small>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <h6 class="card-subtitle mb-3 text-muted"><?= lang('Clergy.detailOfficeHistory') ?></h6>
                <?php if (!empty($canonical['offices'])): ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($canonical['offices'] as $office): ?>
                            <?php
                            $today = date('Y-m-d');
                            $officeStatus = ($office['revoked_at'] ?? null) !== null ? 'revoked'
                                : (($office['approved_at'] ?? null) === null ? 'pending'
                                : (((int) ($office['active'] ?? 0) !== 1
                                    || (($office['ended_at'] ?? null) !== null && $office['ended_at'] < $today)) ? 'ended'
                                : ((($office['started_at'] ?? null) !== null && $office['started_at'] > $today) ? 'upcoming' : 'active')));
                            $officeColor = ['active' => 'success', 'pending' => 'warning', 'upcoming' => 'info', 'ended' => 'secondary', 'revoked' => 'danger'][$officeStatus];
                            ?>
                            <li class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span><?= esc($office['title'] ?? '') ?><small class="text-muted"> — <?= esc($office['parish_name'] ?? $office['deanery_name'] ?? lang('Clergy.scope.' . ($office['scope_type'] ?? 'diocese'))) ?></small></span>
                                    <span class="badge border border-<?= $officeColor ?> text-<?= $officeColor ?> fw-normal"><?= lang('Clergy.officeStatus.' . $officeStatus) ?></span>
                                </div>
                                <small class="text-muted d-block">
                                    <?= esc($office['role_code'] ?? '') ?> · <?= esc($office['started_at'] ?? '—') ?> – <?= esc($office['ended_at'] ?? '—') ?>
                                </small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted mb-0 small"><?= lang('Clergy.noOffices') ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <h6 class="card-subtitle mb-3 text-muted"><?= lang('Clergy.detailAppointments') ?></h6>
                <?php if (!empty($canonical['appointments'])): ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($canonical['appointments'] as $appt): ?>
                            <li class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span><?= esc($appt['parish_name'] ?? '') ?><small class="text-muted"> — <?= esc($appt['appointment_type'] ?? '') ?></small></span>
                                    <span class="badge border border-secondary text-secondary fw-normal"><?= esc($appt['status'] ?? '') ?></span>
                                </div>
                                <small class="text-muted d-block">
                                    <?= esc($appt['started_on'] ?? '—') ?> – <?= esc($appt['ended_on'] ?? '—') ?><?= trim((string) ($appt['licensed_on'] ?? '')) !== '' ? ' · ' . esc(lang('Clergy.detailLicensedOn')) . ' ' . esc($appt['licensed_on']) : '' ?>
                                </small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted mb-0 small"><?= lang('Clergy.noAppointments') ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <h6 class="card-subtitle mb-3 text-muted"><?= lang('Clergy.detailGrants') ?></h6>
                <?php if (!empty($canonical['grants'])): ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($canonical['grants'] as $grant): ?>
                            <?php
                            $now = date('Y-m-d H:i:s');
                            $grantStatus = ($grant['revoked_at'] ?? null) !== null ? 'revoked'
                                : ((($grant['starts_at'] ?? null) !== null && $grant['starts_at'] > $now) ? 'scheduled'
                                : ((($grant['ends_at'] ?? null) !== null && $grant['ends_at'] < $now) ? 'expired' : 'active'));
                            $gColor = ['active' => 'success', 'scheduled' => 'info', 'expired' => 'secondary', 'revoked' => 'danger'][$grantStatus];
                            ?>
                            <li class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge border border-secondary text-secondary fw-normal"><?= esc($grant['permission'] ?? '') ?></span>
                                    <span class="badge border border-<?= $gColor ?> text-<?= $gColor ?> fw-normal"><?= lang('Clergy.grantStatus.' . $grantStatus) ?></span>
                                </div>
                                <small class="text-muted d-block">
                                    <?= esc($grant['grant_type'] ?? '') ?> · <?= esc(lang('Clergy.colEnds')) ?> <?= esc($grant['ends_at'] ?? '—') ?><?php $gName = trim(($grant['grantor_first_name'] ?? '') . ' ' . ($grant['grantor_last_name'] ?? '')); if ($gName !== ''): ?> · <?= esc(lang('Clergy.colGrantedBy')) ?> <?= esc($gName) ?><?php endif; ?>
                                </small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted mb-0 small"><?= lang('Clergy.noGrants') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
