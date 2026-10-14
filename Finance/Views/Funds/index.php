<?= $this->extend('Finance\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
/**
 * Variables: $funds, $totals (balance per fund type), $status, $canManage
 */
$fundTypeLabelMap = [
    'restricted'   => lang('Treasurer.fundTypeRestricted'),
    'permanent'    => lang('Treasurer.fundTypePermanent'),
    'unrestricted' => lang('Treasurer.fundTypeUnrestricted'),
];
$fundTypeColorMap = ['restricted' => 'warning', 'permanent' => 'info', 'unrestricted' => 'secondary'];
$fundFiltersActive = ($status ?? 'active') !== 'active';
?>

<div class="d-flex justify-content-start align-items-center mb-3 gap-2">
    <?php if (!empty($canManage)): ?>
        <?php if (($readiness['status'] ?? '') === 'no_funds'): ?>
            <form method="post" action="<?= base_url('funds/setup') ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-primary"><i class="bi bi-gear" aria-hidden="true"></i> <?= esc(lang('Finance.setupParishFinance')) ?></button>
            </form>
        <?php else: ?>
            <a href="<?= base_url('funds/create') ?>" class="btn btn-outline-primary">
                <i class="bi bi-plus-circle" aria-hidden="true"></i> <?= lang('Finance.fundCreate') ?>
            </a>
        <?php endif; ?>
    <?php endif; ?>
    <a href="<?= base_url('funds/export' . (($status ?? 'active') !== 'active' ? '?status=' . esc($status, 'url') : '')) ?>" class="btn btn-outline-secondary">
        <i class="bi bi-download" aria-hidden="true"></i> <?= lang('Finance.fundExportCsv') ?>
    </a>
</div>

<div class="row g-3 mb-3">
    <?php foreach ($fundTypeLabelMap as $type => $label): ?>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small"><?= esc(lang('Finance.fundTotalsByType', [$label])) ?></div>
                    <div class="h4 mb-0 <?= ((float) ($totals[$type] ?? 0)) >= 0 ? '' : 'text-danger' ?>"><?= esc(format_currency($totals[$type] ?? 0)) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-end filter-panel-header">
        <button class="btn btn-outline-secondary btn-sm filter-toggle-btn" type="button" data-bs-toggle="collapse" data-bs-target="#fundsFilterPanel" aria-expanded="<?= $fundFiltersActive ? 'true' : 'false' ?>" aria-controls="fundsFilterPanel">
            <i class="bi bi-funnel" aria-hidden="true"></i> <?= lang('App.filter') ?>
        </button>
    </div>
    <div id="fundsFilterPanel" class="collapse <?= $fundFiltersActive ? 'show' : '' ?>">
        <div class="card-body border-top filter-panel-body">
            <form method="get" action="<?= base_url('funds') ?>" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label for="fundStatusFilter" class="form-label"><?= lang('App.colStatus') ?></label>
                    <select id="fundStatusFilter" name="status" class="form-select form-select-sm">
                        <option value="active" <?= ($status ?? '') === 'active' ? 'selected' : '' ?>><?= lang('Finance.fundFilterActive') ?></option>
                        <option value="inactive" <?= ($status ?? '') === 'inactive' ? 'selected' : '' ?>><?= lang('Finance.fundFilterInactive') ?></option>
                        <option value="all" <?= ($status ?? '') === 'all' ? 'selected' : '' ?>><?= lang('Finance.fundFilterAll') ?></option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill"><i class="bi bi-funnel" aria-hidden="true"></i> <?= lang('Services.apply') ?></button>
                    <a href="<?= base_url('funds') ?>" class="btn btn-sm btn-outline-secondary flex-fill"><?= lang('App.clear') ?></a>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($funds)): ?>
            <p class="text-muted mb-0"><?= lang('Finance.fundNoFunds') ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th scope="col"><?= lang('Finance.fundName') ?></th>
                            <th scope="col"><?= lang('Finance.fundType') ?></th>
                            <th scope="col" class="text-end"><?= lang('Finance.fundIncome') ?></th>
                            <th scope="col" class="text-end"><?= lang('Finance.fundExpensesTotal') ?></th>
                            <th scope="col" class="text-end"><?= lang('Finance.fundBalance') ?></th>
                            <th scope="col"><?= lang('Finance.fundActivity') ?></th>
                            <th scope="col"><?= lang('App.colStatus') ?></th>
                            <?php if (!empty($canManage)): ?><th scope="col"></th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($funds as $fund): ?>
                            <?php
                            $fundId     = (int) $fund['id'];
                            $fundType   = (string) ($fund['fund_type'] ?? 'unrestricted');
                            $typeColor  = $fundTypeColorMap[$fundType] ?? 'secondary';
                            $isActive   = (int) ($fund['is_active'] ?? 0) === 1;
                            $isLocked   = (int) ($fund['is_locked'] ?? 0) === 1;
                            $statusColor = $isActive ? 'success' : 'secondary';
                            $balance    = (float) ($fund['balance'] ?? 0);
                            ?>
                            <tr>
                                <th scope="row" class="fw-normal">
                                    <div>
                                        <a href="<?= base_url('funds/' . $fundId) ?>" class="text-decoration-none fw-semibold">
                                            <?= esc(fund_name_label((string) ($fund['name'] ?? ''))) ?>
                                        </a>
                                    </div>
                                    <?php if (!empty($fund['restriction_purpose'])): ?>
                                        <div class="small text-muted"><?= esc($fund['restriction_purpose']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($fund['chart_account_code'])): ?>
                                        <div class="mt-1">
                                            <span class="badge border border-secondary text-secondary fw-normal">
                                                <i class="bi bi-tag" aria-hidden="true"></i> <?= esc($fund['chart_account_code'] . ' ' . ($fund['chart_account_name'] ?? '')) ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </th>
                                <td><span class="badge border border-<?= $typeColor ?> text-<?= $typeColor ?> fw-normal"><?= esc($fundTypeLabelMap[$fundType] ?? ucfirst($fundType)) ?></span></td>
                                <td class="text-end"><?= esc(format_currency($fund['total_income'] ?? 0)) ?></td>
                                <td class="text-end"><?= esc(format_currency($fund['total_expenses'] ?? 0)) ?></td>
                                <td class="text-end <?= $balance < 0 ? 'text-danger' : '' ?>"><?= esc(format_currency($balance)) ?></td>
                                <td class="small text-muted"><?= esc(lang('Finance.fundTransactionsCount', [(int) ($fund['donation_count'] ?? 0), (int) ($fund['expense_count'] ?? 0)])) ?></td>
                                <td>
                                    <span class="badge border border-<?= $statusColor ?> text-<?= $statusColor ?> fw-normal"><?= lang($isActive ? 'Finance.fundStatusActive' : 'Finance.fundStatusInactive') ?></span>
                                    <?php if ($isLocked): ?>
                                        <span class="badge border border-danger text-danger fw-normal"><i class="bi bi-lock" aria-hidden="true"></i> <?= lang('Finance.fundLockedBadge') ?></span>
                                    <?php endif; ?>
                                </td>
                                <?php if (!empty($canManage)): ?>
                                    <td class="text-end">
                                        <div class="dropdown">
                                            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="<?= esc(lang('App.colActions')) ?>">
                                                <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                <li><a class="dropdown-item" href="<?= base_url('funds/' . $fundId) ?>"><i class="bi bi-journal-text" aria-hidden="true"></i> <?= lang('Finance.fundView') ?></a></li>
                                                <?php if (!$isLocked): ?>
                                                    <li><a class="dropdown-item" href="<?= base_url('funds/edit/' . $fundId) ?>"><i class="bi bi-pencil" aria-hidden="true"></i> <?= lang('App.edit') ?></a></li>
                                                <?php endif; ?>
                                                <li>
                                                    <form method="post" action="<?= base_url('funds/' . ($isActive ? 'deactivate' : 'activate') . '/' . $fundId) ?>">
                                                        <?= csrf_field() ?>
                                                        <button type="submit" class="dropdown-item"><?= lang($isActive ? 'Finance.fundDeactivate' : 'Finance.fundActivate') ?></button>
                                                    </form>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <?php if ($isLocked): ?>
                                                        <form method="post" action="<?= base_url('funds/unlock/' . $fundId) ?>">
                                                            <?= csrf_field() ?>
                                                            <button type="submit" class="dropdown-item"><i class="bi bi-unlock" aria-hidden="true"></i> <?= lang('Finance.fundUnlock') ?></button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="post" action="<?= base_url('funds/lock/' . $fundId) ?>" onsubmit="return confirm(<?= esc(json_encode(lang('Finance.fundLockConfirm')), 'attr') ?>);">
                                                            <?= csrf_field() ?>
                                                            <button type="submit" class="dropdown-item text-danger"><i class="bi bi-lock" aria-hidden="true"></i> <?= lang('Finance.fundLock') ?></button>
                                                        </form>
                                                    <?php endif; ?>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
