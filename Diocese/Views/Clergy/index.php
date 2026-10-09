<?= $this->extend('Diocese\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="<?= base_url('diocese/clergy/create') ?>" class="btn btn-primary"><i class="bi bi-plus-circle" aria-hidden="true"></i> <?= lang('Clergy.btnInvite') ?></a>
</div>

<?php $stats = $stats ?? ['total' => 0, 'active' => 0, 'awaiting' => 0, 'invited' => 0, 'pending_requests' => 0, 'pending_candidates' => 0, 'expiring_grants' => 0]; ?>
<div class="row g-3 mb-3">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100"><div class="card-body py-3">
            <div class="text-muted small"><?= lang('Clergy.cardTotal') ?></div>
            <div class="fs-4"><?= (int) $stats['total'] ?></div>
            <div class="text-muted small"><?= lang('Clergy.cardTotalHelp') ?>: <?= (int) $stats['active'] ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100"><div class="card-body py-3">
            <div class="text-muted small"><?= lang('Clergy.cardAwaitingAppointment') ?></div>
            <div class="fs-4"><?= (int) $stats['awaiting'] ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100"><div class="card-body py-3">
            <div class="text-muted small"><?= lang('Clergy.cardPendingRequests') ?></div>
            <div class="fs-4"><?= (int) $stats['pending_requests'] ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100"><div class="card-body py-3">
            <div class="text-muted small"><?= lang('Clergy.cardPendingCandidates') ?></div>
            <div class="fs-4"><?= (int) $stats['pending_candidates'] ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100"><div class="card-body py-3">
            <div class="text-muted small"><?= lang('Clergy.cardPendingInvitations') ?></div>
            <div class="fs-4"><?= (int) $stats['invited'] ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100"><div class="card-body py-3">
            <div class="text-muted small"><?= lang('Clergy.cardExpiringGrants') ?></div>
            <div class="fs-4"><?= (int) $stats['expiring_grants'] ?></div>
        </div></div>
    </div>
</div>

<?php $clergyFiltersActive = trim((string) ($q ?? '')) !== ''; ?>
<div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-end filter-panel-header">
        <button class="btn btn-outline-secondary btn-sm filter-toggle-btn" type="button"
            data-bs-toggle="collapse" data-bs-target="#clergyFilterPanel"
            aria-expanded="<?= $clergyFiltersActive ? 'true' : 'false' ?>" aria-controls="clergyFilterPanel">
            <i class="bi bi-funnel" aria-hidden="true"></i> <?= lang('Services.apply') ?>
        </button>
    </div>
    <div id="clergyFilterPanel" class="collapse <?= $clergyFiltersActive ? 'show' : '' ?>">
        <div class="card-body border-top filter-panel-body">
            <form method="get" action="<?= base_url('diocese/clergy') ?>" class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label for="q" class="form-label"><?= lang('Clergy.searchLabel') ?></label>
                    <input type="text" class="form-control form-control-sm" id="q" name="q" value="<?= esc($q ?? '') ?>" placeholder="<?= esc(lang('Clergy.searchPlaceholder')) ?>">
                </div>
                <div class="col-md-6">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-primary flex-fill"><i class="bi bi-search" aria-hidden="true"></i> <?= lang('Services.apply') ?></button>
                        <a href="<?= base_url('diocese/clergy') ?>" class="btn btn-sm btn-outline-secondary flex-fill"><?= lang('App.clear') ?></a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th scope="col"><?= lang('Clergy.colName') ?></th>
                        <th scope="col"><?= lang('Clergy.colOffice') ?></th>
                        <th scope="col"><?= lang('Clergy.colOrder') ?></th>
                        <th scope="col"><?= lang('Clergy.colStanding') ?></th>
                        <th scope="col"><?= lang('Clergy.colLicensedAppointment') ?></th>
                        <th scope="col"><?= lang('Clergy.colPortalAccess') ?></th>
                        <th scope="col"><?= lang('Clergy.colInvitationSent') ?></th>
                        <th scope="col"><?= lang('Clergy.colStatus') ?></th>
                        <th scope="col"><?= lang('App.colActions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($clergy)): ?>
                        <?php foreach ($clergy as $row): ?>
                            <?php
                            $portalAccess = (string) ($row['portal_access'] ?? 'none');
                            $portalColor = ['enabled' => 'success', 'invited' => 'warning', 'none' => 'secondary'][$portalAccess] ?? 'secondary';
                            $activeColor = (int) ($row['active'] ?? 0) === 1 ? 'success' : 'secondary';
                            $standingCode = strtolower((string) ($row['standing']['status_code'] ?? ''));
                            $standingColor = in_array($standingCode, ['suspended', 'inhibited'], true) ? 'danger' : 'success';
                            $ordination = $row['ordination'] ?? null;
                            $ordinationVerified = ($ordination['verification_status'] ?? '') === 'verified';
                            $orderColor = $ordinationVerified ? 'primary' : 'secondary';
                            $appointment = $row['appointment'] ?? null;
                            ?>
                            <tr>
                                <td><?= esc(trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''))) ?><br>
                                    <small class="text-muted"><?= esc($row['email'] ?? '') ?></small></td>
                                <td>
                                    <?php if (!empty($row['positions'])): ?>
                                        <?php foreach ($row['positions'] as $pos): ?>
                                            <div><?= esc($pos['title'] ?? '') ?><small class="text-muted"> — <?= esc($pos['parish_name'] ?? $pos['deanery_name'] ?? lang('Clergy.scope.' . ($pos['scope_type'] ?? 'diocese'))) ?></small></div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-muted"><?= lang('Clergy.noActiveOffice') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($ordination !== null): ?>
                                        <span class="badge border border-<?= $orderColor ?> text-<?= $orderColor ?> fw-normal"><?= esc($ordination['order_code'] ?? '') ?><?= $ordinationVerified ? '' : ' · ' . lang('Clergy.unverified') ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($standingCode !== ''): ?>
                                        <span class="badge border border-<?= $standingColor ?> text-<?= $standingColor ?> fw-normal"><?= esc($standingCode) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($appointment !== null): ?>
                                        <?= esc($appointment['parish_name'] ?? '') ?><small class="text-muted"> — <?= esc($appointment['appointment_type'] ?? '') ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge border border-<?= $portalColor ?> text-<?= $portalColor ?> fw-normal"><?= esc(lang('Clergy.portalAccess.' . $portalAccess) !== 'Clergy.portalAccess.' . $portalAccess ? lang('Clergy.portalAccess.' . $portalAccess) : $portalAccess) ?></span></td>
                                <td><?= esc($row['invitation_sent_at'] ?? '-') ?></td>
                                <td><span class="badge border border-<?= $activeColor ?> text-<?= $activeColor ?> fw-normal"><?= (int) ($row['active'] ?? 0) === 1 ? lang('Clergy.statusActive') : lang('Clergy.statusDisabled') ?></span></td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="<?= esc(lang('App.colActions')) ?>">
                                            <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <li><a class="dropdown-item" href="<?= base_url('diocese/clergy/' . (int) $row['id']) ?>"><?= lang('Clergy.actionView') ?></a></li>
                                            <li><a class="dropdown-item" href="<?= base_url('diocese/clergy/' . (int) $row['id'] . '/edit') ?>"><?= lang('App.edit') ?></a></li>
                                            <li>
                                                <form action="<?= base_url('diocese/clergy/' . (int) $row['id'] . '/resend-invitation') ?>" method="post" class="m-0"><?= csrf_field() ?>
                                                    <button type="submit" class="dropdown-item"><?= lang('Clergy.actionResendInvitation') ?></button>
                                                </form>
                                            </li>
                                            <?php if ((int) ($row['active'] ?? 0) === 1): ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form action="<?= base_url('diocese/clergy/' . (int) $row['id'] . '/deactivate') ?>" method="post" class="m-0" onsubmit="return confirm('<?= esc(lang('Clergy.confirmDisablePortalAccess'), 'js') ?>');"><?= csrf_field() ?>
                                                        <button type="submit" class="dropdown-item text-danger"><?= lang('Clergy.actionDisablePortalAccess') ?></button>
                                                    </form>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4"><?= lang('Clergy.noClergyFound') ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
