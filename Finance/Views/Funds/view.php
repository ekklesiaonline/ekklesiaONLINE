<?= $this->extend('Finance\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
/**
 * Variables: $fund, $donations, $expenses, $canManage
 */
$fundId     = (int) ($fund['id'] ?? 0);
$fundType   = (string) ($fund['fund_type'] ?? 'unrestricted');
$fundTypeLabelMap = [
    'restricted'   => lang('Treasurer.fundTypeRestricted'),
    'permanent'    => lang('Treasurer.fundTypePermanent'),
    'unrestricted' => lang('Treasurer.fundTypeUnrestricted'),
];
$fundTypeColorMap = ['restricted' => 'warning', 'permanent' => 'info', 'unrestricted' => 'secondary'];
$typeColor  = $fundTypeColorMap[$fundType] ?? 'secondary';
$isActive   = (int) ($fund['is_active'] ?? 0) === 1;
$isLocked   = (int) ($fund['is_locked'] ?? 0) === 1;
$statusColor = $isActive ? 'success' : 'secondary';
$balance    = (float) ($fund['balance'] ?? 0);
?>

<div class="d-flex justify-content-start align-items-center mb-3 gap-2">
    <a href="<?= base_url('funds') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('App.back') ?>
    </a>
    <?php if (!empty($canManage) && !$isLocked): ?>
        <a href="<?= base_url('funds/edit/' . $fundId) ?>" class="btn btn-outline-primary">
            <i class="bi bi-pencil" aria-hidden="true"></i> <?= lang('App.edit') ?>
        </a>
    <?php endif; ?>
</div>

<!-- KPI Summary Cards -->
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small"><?= esc(lang('Finance.fundBalance')) ?></div>
                <div class="h4 mb-0 <?= $balance < 0 ? 'text-danger' : 'text-success' ?>"><?= esc(format_currency($balance)) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small"><?= esc(lang('Finance.fundIncome')) ?></div>
                <div class="h4 mb-0"><?= esc(format_currency($fund['total_income'] ?? 0)) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small"><?= esc(lang('Finance.fundExpensesTotal')) ?></div>
                <div class="h4 mb-0"><?= esc(format_currency($fund['total_expenses'] ?? 0)) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small"><?= esc(lang('Finance.fundType')) ?></div>
                <div class="mt-1">
                    <span class="badge border border-<?= $typeColor ?> text-<?= $typeColor ?> fw-normal fs-6">
                        <?= esc($fundTypeLabelMap[$fundType] ?? ucfirst($fundType)) ?>
                    </span>
                    <?php if ($isLocked): ?>
                        <span class="badge border border-danger text-danger fw-normal ms-1 fs-6">
                            <i class="bi bi-lock" aria-hidden="true"></i> <?= lang('Finance.fundLockedBadge') ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Fund Details -->
<div class="card mb-3">
    <div class="card-header bg-transparent fw-semibold">
        <?= esc(lang('Finance.fundDetails')) ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="text-muted small"><?= esc(lang('Finance.fundName')) ?></div>
                <div class="fw-semibold"><?= esc(fund_name_label((string) ($fund['name'] ?? ''))) ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small"><?= esc(lang('App.colStatus')) ?></div>
                <div>
                    <span class="badge border border-<?= $statusColor ?> text-<?= $statusColor ?> fw-normal">
                        <?= lang($isActive ? 'Finance.fundStatusActive' : 'Finance.fundStatusInactive') ?>
                    </span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small"><?= esc(lang('Finance.fundChartAccount')) ?></div>
                <div>
                    <?php if (!empty($fund['chart_account_code'])): ?>
                        <span class="badge border border-secondary text-secondary fw-normal">
                            <i class="bi bi-tag" aria-hidden="true"></i> <?= esc($fund['chart_account_code'] . ' - ' . ($fund['chart_account_name'] ?? '')) ?>
                        </span>
                    <?php else: ?>
                        <span class="text-muted"><?= esc(lang('Finance.fundChartAccountNone')) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!empty($fund['restriction_purpose'])): ?>
                <div class="col-12">
                    <div class="text-muted small"><?= esc(lang('Finance.fundRestrictionPurpose')) ?></div>
                    <div><?= esc($fund['restriction_purpose']) ?></div>
                </div>
            <?php endif; ?>
            <?php if (!empty($fund['description'])): ?>
                <div class="col-12">
                    <div class="text-muted small"><?= esc(lang('Finance.fundDescription')) ?></div>
                    <div><?= esc($fund['description']) ?></div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Ledger Transactions Tabs -->
<div class="card">
    <div class="card-header bg-transparent">
        <ul class="nav nav-tabs card-header-tabs" id="fundLedgerTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="donations-tab" data-bs-toggle="tab" data-bs-target="#donations-pane" type="button" role="tab" aria-controls="donations-pane" aria-selected="true">
                    <i class="bi bi-cash-stack" aria-hidden="true"></i> <?= esc(lang('Finance.fundDonations')) ?>
                    <span class="badge rounded-pill bg-light text-dark border ms-1"><?= count($donations ?? []) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="expenses-tab" data-bs-toggle="tab" data-bs-target="#expenses-pane" type="button" role="tab" aria-controls="expenses-pane" aria-selected="false">
                    <i class="bi bi-receipt" aria-hidden="true"></i> <?= esc(lang('Finance.fundExpenses')) ?>
                    <span class="badge rounded-pill bg-light text-dark border ms-1"><?= count($expenses ?? []) ?></span>
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content" id="fundLedgerTabsContent">
            <!-- Donations Table -->
            <div class="tab-pane fade show active" id="donations-pane" role="tabpanel" aria-labelledby="donations-tab" tabindex="0">
                <?php if (empty($donations)): ?>
                    <p class="text-muted mb-0"><?= esc(lang('Finance.fundNoDonations')) ?></p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th scope="col"><?= esc(lang('Finance.colDate')) ?></th>
                                    <th scope="col"><?= esc(lang('Finance.colDonor')) ?></th>
                                    <th scope="col"><?= esc(lang('Finance.colCategory')) ?></th>
                                    <th scope="col" class="text-end"><?= esc(lang('Finance.colAmount')) ?></th>
                                    <th scope="col"><?= esc(lang('Finance.colPaymentMethod')) ?></th>
                                    <th scope="col"><?= esc(lang('Finance.colReceiptNumber')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($donations as $donation): ?>
                                    <tr>
                                        <td><?= esc($donation['date'] ?? '') ?></td>
                                        <td>
                                            <?php if (!empty($donation['first_name']) || !empty($donation['last_name'])): ?>
                                                <?= esc(trim(($donation['first_name'] ?? '') . ' ' . ($donation['last_name'] ?? ''))) ?>
                                            <?php elseif (!empty($donation['user_id'])): ?>
                                                <?= esc(lang('Finance.memberPrefix') . (string) $donation['user_id']) ?>
                                            <?php else: ?>
                                                <em><?= esc(lang('Finance.anonymous')) ?></em>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge border border-primary text-primary fw-normal"><?= esc(ucfirst(str_replace('_', ' ', (string) ($donation['category'] ?? 'other')))) ?></span></td>
                                        <td class="text-end fw-semibold"><?= esc(format_currency($donation['amount'] ?? 0)) ?></td>
                                        <td><?= esc(ucfirst((string) ($donation['payment_method'] ?? '-'))) ?></td>
                                        <td>
                                            <?php if (!empty($donation['receipt_number'])): ?>
                                                <a href="<?= base_url('donations/receipt/' . (int) $donation['id']) ?>" class="badge border border-success text-success text-decoration-none">
                                                    <?= esc($donation['receipt_number']) ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Expenses Table -->
            <div class="tab-pane fade" id="expenses-pane" role="tabpanel" aria-labelledby="expenses-tab" tabindex="0">
                <?php if (empty($expenses)): ?>
                    <p class="text-muted mb-0"><?= esc(lang('Finance.fundNoExpenses')) ?></p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th scope="col"><?= esc(lang('Finance.colDate')) ?></th>
                                    <th scope="col"><?= esc(lang('Finance.colDescription')) ?></th>
                                    <th scope="col"><?= esc(lang('Finance.expenseCategory')) ?></th>
                                    <th scope="col" class="text-end"><?= esc(lang('Finance.colAmount')) ?></th>
                                    <th scope="col"><?= esc(lang('Finance.colApprovedBy')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($expenses as $expense): ?>
                                    <tr>
                                        <td><?= esc($expense['date'] ?? '') ?></td>
                                        <td><?= esc($expense['description'] ?? '') ?></td>
                                        <td><span class="badge border border-secondary text-secondary fw-normal"><?= esc(ucfirst(str_replace('_', ' ', (string) ($expense['category'] ?? 'other')))) ?></span></td>
                                        <td class="text-end fw-semibold text-danger"><?= esc(format_currency($expense['amount'] ?? 0)) ?></td>
                                        <td><?= !empty($expense['approved_by']) ? esc('#' . $expense['approved_by']) : '-' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
