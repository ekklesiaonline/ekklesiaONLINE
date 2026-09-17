<?= $this->extend('Treasurer\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <?php if ($canManageBudget): ?>
        <button type="submit" form="budgetForm" class="btn btn-primary">
            <i class="bi bi-save" aria-hidden="true"></i> <?= lang('Treasurer.saveBudget') ?>
        </button>
    <?php endif; ?>
</div>

<?php $request = service('request'); ?>

<div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-end filter-panel-header">
        <button
            class="btn btn-outline-secondary btn-sm filter-toggle-btn"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#budgetFilterPanel"
            aria-expanded="<?= $request->getGet('year') !== null ? 'true' : 'false' ?>"
            aria-controls="budgetFilterPanel">
            <i class="bi bi-funnel" aria-hidden="true"></i> <?= lang('App.filter') ?>
        </button>
    </div>
    <div id="budgetFilterPanel" class="collapse <?= $request->getGet('year') !== null ? 'show' : '' ?>">
        <div class="card-body border-top filter-panel-body">
            <form method="get" action="<?= base_url('budgets') ?>" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="year" class="form-label"><?= lang('Treasurer.fiscalYear') ?></label>
                    <select class="form-select" id="year" name="year">
                        <?php foreach ($year_options as $year): ?>
                            <option value="<?= (int) $year ?>" <?= (int) $selected_year === (int) $year ? 'selected' : '' ?>><?= (int) $year ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-primary w-100">
                        <i class="bi bi-filter" aria-hidden="true"></i> <?= lang('Treasurer.loadYear') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-3">
        <div class="card text-center border-success">
            <div class="card-body">
                <div class="text-muted small"><?= lang('Treasurer.incomeBudget') ?></div>
                <div class="h4 mb-0 text-success">$<?= number_format((float) ($summary['income_budget'] ?? 0), 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-primary">
            <div class="card-body">
                <div class="text-muted small"><?= lang('Treasurer.incomeActual') ?></div>
                <div class="h4 mb-0 text-primary">$<?= number_format((float) ($summary['income_actual'] ?? 0), 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-danger">
            <div class="card-body">
                <div class="text-muted small"><?= lang('Treasurer.expenseBudget') ?></div>
                <div class="h4 mb-0 text-danger">$<?= number_format((float) ($summary['expense_budget'] ?? 0), 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-warning">
            <div class="card-body">
                <div class="text-muted small"><?= lang('Treasurer.expenseActual') ?></div>
                <div class="h4 mb-0 text-warning">$<?= number_format((float) ($summary['expense_actual'] ?? 0), 2) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-6">
        <div class="card text-center">
            <div class="card-body">
                <div class="text-muted small"><?= lang('Treasurer.plannedNet') ?></div>
                <div class="h4 mb-0 <?= ($summary['net_budget'] ?? 0) >= 0 ? 'text-success' : 'text-danger' ?>">$<?= number_format((float) ($summary['net_budget'] ?? 0), 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card text-center">
            <div class="card-body">
                <div class="text-muted small"><?= lang('Treasurer.actualNet') ?></div>
                <div class="h4 mb-0 <?= ($summary['net_actual'] ?? 0) >= 0 ? 'text-success' : 'text-danger' ?>">$<?= number_format((float) ($summary['net_actual'] ?? 0), 2) ?></div>
                <div class="small <?= ($summary['net_variance'] ?? 0) >= 0 ? 'text-success' : 'text-danger' ?>"><?= lang('Treasurer.varianceLabel') ?>: $<?= number_format((float) ($summary['net_variance'] ?? 0), 2) ?></div>
            </div>
        </div>
    </div>
</div>

<?php if (empty($budget_lines)): ?>
    <div class="alert alert-warning"><?= lang('Treasurer.noBudgetableAccounts') ?></div>
<?php else: ?>
    <form method="post" action="<?= base_url('budgets/save') ?>" id="budgetForm">
        <?= csrf_field() ?>
        <input type="hidden" name="fiscal_year" value="<?= (int) $selected_year ?>">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th scope="col"><?= lang('Treasurer.colCode') ?></th>
                                <th scope="col"><?= lang('Treasurer.colAccount') ?></th>
                                <th scope="col"><?= lang('Treasurer.colType') ?></th>
                                <th scope="col" class="text-end"><?= lang('Finance.budgetAmount') ?></th>
                                <th scope="col" class="text-end"><?= lang('Finance.actualAmount') ?></th>
                                <th scope="col" class="text-end"><?= lang('Finance.variance') ?></th>
                                <th scope="col" class="text-end"><?= lang('Treasurer.colUtilization') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($budget_lines as $line): ?>
                                <?php $variance = (float) ($line['variance_amount'] ?? 0); ?>
                                <?php $lineCode = (string) ($line['code'] ?? ''); ?>
                                <?php $lineName = (string) ($line['name'] ?? $lineCode); ?>
                                <?php $lineCategory = (string) ($line['category'] ?? ''); ?>
                                <tr>
                                    <td><strong><?= esc($line['code']) ?></strong></td>
                                    <td><?= esc(coa_name_label($lineCode, $lineName)) ?><?php if ($lineCategory !== ''): ?><div class="small text-muted"><?= lang('Treasurer.categoryLabel') ?>: <?= esc(coa_category_label($lineCategory, $lineCategory)) ?></div><?php endif; ?></td>
                                    <td>
                                        <?php $accountType = (string) ($line['account_type'] ?? 'expense'); ?>
                                        <?php $accountTypeLabelMap = ['income' => lang('Treasurer.accountTypeIncome'), 'expense' => lang('Treasurer.accountTypeExpense')]; ?>
                                        <span class="badge border border-<?= $accountType === 'income' ? 'success' : 'danger' ?> text-<?= $accountType === 'income' ? 'success' : 'danger' ?> fw-normal"><?= esc($accountTypeLabelMap[$accountType] ?? ucfirst($accountType)) ?></span>
                                    </td>
                                    <td class="text-end" style="min-width: 180px;">
                                        <?php if ($canManageBudget): ?>
                                            <input type="number" step="0.01" min="0" class="form-control text-end" name="budget_amounts[<?= (int) $line['chart_account_id'] ?>]" value="<?= number_format((float) ($line['budget_amount'] ?? 0), 2, '.', '') ?>">
                                        <?php else: ?>
                                            <strong>$<?= number_format((float) ($line['budget_amount'] ?? 0), 2) ?></strong>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">$<?= number_format((float) ($line['actual_amount'] ?? 0), 2) ?></td>
                                    <td class="text-end <?= $variance >= 0 ? 'text-success' : 'text-danger' ?>">$<?= number_format($variance, 2) ?></td>
                                    <td class="text-end"><?php if ($line['utilization_percentage'] !== null): ?><span class="<?= $line['utilization_percentage'] > 100 ? 'text-danger' : 'text-muted' ?>"><?= number_format((float) $line['utilization_percentage'], 1) ?>%</span><?php else: ?><span class="text-muted">-</span><?php endif; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($canManageBudget): ?>
                    <div class="d-flex justify-content-end mt-3"><button type="submit" class="btn btn-primary"><i class="bi bi-save" aria-hidden="true"></i> <?= lang('Treasurer.saveBudget') ?></button></div>
                <?php endif; ?>
            </div>
        </div>
    </form>
<?php endif; ?>

<?= $this->endSection() ?>