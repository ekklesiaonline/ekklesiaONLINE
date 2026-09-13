<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php
$admins = $admins ?? [];
$dioceses = $dioceses ?? [];
$search = $search ?? '';
$status = $status ?? 'all';
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong><?= lang('System.sectionDioceseAdmins') ?></strong>
        <a href="<?= base_url('province/dashboard') ?>" class="btn btn-sm btn-outline-secondary" aria-label="<?= esc(lang('App.back')) ?>">
            <i class="bi bi-arrow-left me-1"></i><?= lang('App.back') ?>
        </a>
    </div>
    <div class="card-body border-bottom">
        <form method="get" class="row g-2">
            <div class="col-md-6"><label class="visually-hidden" for="adminSearch"><?= lang('App.search') ?></label><input id="adminSearch" name="q" value="<?= esc((string) $search) ?>" class="form-control" placeholder="<?= esc(lang('App.search'), 'attr') ?>"></div>
            <div class="col-md-4"><select name="status" class="form-select"><option value="all" <?= $status === 'all' ? 'selected' : '' ?>><?= lang('App.all') ?></option><option value="active" <?= $status === 'active' ? 'selected' : '' ?>><?= lang('System.adminStatus_active') ?></option><option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>><?= lang('System.adminInvitationPending') ?></option><option value="disabled" <?= $status === 'disabled' ? 'selected' : '' ?>><?= lang('System.adminStatus_disabled') ?></option></select></div>
            <div class="col-md-2"><button class="btn btn-outline-primary w-100" type="submit"><?= lang('App.filter') ?></button></div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th><?= lang('App.colName') ?></th>
                        <th class="d-none d-md-table-cell"><?= lang('App.colEmail') ?></th>
                        <th><?= lang('System.dioceseName') ?></th>
                        <th><?= lang('App.colStatus') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($admins)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3"><?= lang('App.noResults') ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($admins as $admin): ?>
                            <tr>
                                <td><?= esc(trim((string) ($admin['first_name'] ?? '') . ' ' . (string) ($admin['last_name'] ?? ''))) ?></td>
                                <td class="d-none d-md-table-cell"><?= esc((string) ($admin['email'] ?? '')) ?></td>
                                <td><span class="badge border border-info text-info fw-normal"><?= esc((string) ($admin['diocese_name'] ?? '')) ?></span></td>
                                <td>
                                    <?php if (!empty($admin['active']) && !empty($admin['force_reset'])): ?>
                                        <span class="badge text-bg-warning"><?= lang('System.adminInvitationPending') ?></span>
                                    <?php elseif (!empty($admin['active'])): ?>
                                        <span class="badge text-bg-success"><?= lang('System.adminStatus_active') ?></span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary"><?= lang('System.adminStatus_disabled') ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if (($total_pages ?? 1) > 1): ?>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <span class="text-muted small"><?= number_format((int) ($total_admins ?? 0)) ?> <?= lang('System.sectionDioceseAdmins') ?></span>
            <nav aria-label="<?= esc(lang('System.sectionDioceseAdmins'), 'attr') ?>"><ul class="pagination pagination-sm mb-0">
                <?php for ($pageNumber = 1; $pageNumber <= (int) $total_pages; $pageNumber++): ?>
                    <li class="page-item <?= $pageNumber === (int) $page ? 'active' : '' ?>"><a class="page-link" href="<?= base_url('province/diocese-admins?' . http_build_query(['q' => $search, 'status' => $status, 'page' => $pageNumber])) ?>"><?= $pageNumber ?></a></li>
                <?php endfor; ?>
            </ul></nav>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>

<?= $this->section('modals') ?>
<?= $this->endSection() ?>
