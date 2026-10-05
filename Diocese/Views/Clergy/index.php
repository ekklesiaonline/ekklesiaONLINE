<?= $this->extend('Diocese\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="<?= base_url('diocese/clergy/create') ?>" class="btn btn-primary"><i class="bi bi-plus-circle" aria-hidden="true"></i> <?= lang('Clergy.btnInvite') ?></a>
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
                        <th scope="col"><?= lang('Clergy.colEmail') ?></th>
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
                            ?>
                            <tr>
                                <td><?= esc(trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''))) ?></td>
                                <td><?= esc($row['email'] ?? '') ?></td>
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
                            <td colspan="6" class="text-center text-muted py-4"><?= lang('Clergy.noClergyFound') ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
