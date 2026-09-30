<?= $this->extend('Diocese\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="<?= base_url('chart-accounts/create') ?>" class="btn btn-primary"><i class="bi bi-plus-circle" aria-hidden="true"></i> <?= lang('ChartAccounts.btn_newAccount') ?></a>
</div>

<?php $request = service('request'); ?>

<div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-end filter-panel-header">
        <button
            class="btn btn-outline-secondary btn-sm filter-toggle-btn"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#chartAccountsFilterPanel"
            aria-expanded="<?= ($request->getGet('type') !== null || $request->getGet('status') !== null || $request->getGet('section') !== null) ? 'true' : 'false' ?>"
            aria-controls="chartAccountsFilterPanel">
            <i class="bi bi-funnel" aria-hidden="true"></i> <?= lang('ChartAccounts.btn_filter') ?>
        </button>
    </div>
    <div id="chartAccountsFilterPanel" class="collapse <?= ($request->getGet('type') !== null || $request->getGet('status') !== null || $request->getGet('section') !== null) ? 'show' : '' ?>">
        <div class="card-body border-top filter-panel-body">
            <form method="get" action="<?= base_url('chart-accounts') ?>" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="type" class="form-label"><?= lang('ChartAccounts.labelAccountType') ?></label>
                    <select class="form-select" id="type" name="type">
                        <option value=""><?= lang('ChartAccounts.labelAllTypes') ?></option>
                        <?php foreach (
                            [
                                'income'    => lang('ChartAccounts.typeIncome'),
                                'expense'   => lang('ChartAccounts.typeExpense'),
                                'asset'     => lang('ChartAccounts.typeAsset'),
                                'liability' => lang('ChartAccounts.typeLiability'),
                                'equity'    => lang('ChartAccounts.typeEquity'),
                            ] as $val => $label
                        ): ?>
                            <option value="<?= esc($val) ?>" <?= ($selected_type ?? '') === $val ? 'selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label"><?= lang('ChartAccounts.labelStatus') ?></label>
                    <select class="form-select" id="status" name="status">
                        <option value=""><?= lang('ChartAccounts.labelAll') ?></option>
                        <option value="active" <?= ($selected_status ?? '') === 'active' ? 'selected' : '' ?>><?= lang('ChartAccounts.labelActive') ?></option>
                        <option value="inactive" <?= ($selected_status ?? '') === 'inactive' ? 'selected' : '' ?>><?= lang('ChartAccounts.labelInactive') ?></option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="section" class="form-label"><?= lang('ChartAccounts.labelEpiscopalSection') ?></label>
                    <select class="form-select" id="section" name="section">
                        <option value=""><?= lang('ChartAccounts.labelAllSections') ?></option>
                        <?php foreach (($section_options ?? []) as $key => $label): ?>
                            <option value="<?= esc($key) ?>" <?= ($selected_section ?? '') === $key ? 'selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2"><button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-filter" aria-hidden="true"></i> <?= lang('ChartAccounts.btn_filter') ?></button></div>
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
                        <th scope="col"><?= lang('ChartAccounts.colCode') ?></th>
                        <th scope="col"><?= lang('ChartAccounts.colName') ?></th>
                        <th scope="col"><?= lang('ChartAccounts.colType') ?></th>
                        <th scope="col"><?= lang('ChartAccounts.colCategory') ?></th>
                        <th scope="col"><?= lang('ChartAccounts.colBudgetable') ?></th>
                        <th scope="col"><?= lang('ChartAccounts.colStatus') ?></th>
                        <th scope="col"><?= lang('ChartAccounts.colSort') ?></th>
                        <th scope="col"><?= lang('ChartAccounts.colActions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($accounts)): ?>
                        <?php foreach ($accounts as $account): ?>
                            <?php
                            $accountCode = (string) ($account['code'] ?? '');
                            $fallbackName = (string) ($account['name'] ?? '');
                            $fallbackDescription = (string) ($account['description'] ?? '');
                            $fallbackCategory = (string) ($account['category'] ?? '-');
                            $localizedName = coa_name_label($accountCode, $fallbackName);
                            $localizedDescription = $fallbackDescription !== '' ? coa_description_label($accountCode, $fallbackDescription) : '';
                            $localizedCategory = coa_category_label($account['category'] ?? null, $fallbackCategory);
                            $accountType = (string) ($account['account_type'] ?? '');
                            $accountTypeLabels = [
                                'income' => lang('ChartAccounts.typeIncome'),
                                'expense' => lang('ChartAccounts.typeExpense'),
                                'asset' => lang('ChartAccounts.typeAsset'),
                                'liability' => lang('ChartAccounts.typeLiability'),
                                'equity' => lang('ChartAccounts.typeEquity'),
                            ];
                            $localizedType = (string) ($accountTypeLabels[$accountType] ?? ucfirst($accountType));
                            ?>
                            <tr>
                                <td><strong><?= esc($account['code']) ?></strong></td>
                                <td><?= esc($localizedName) ?><?php if ($localizedDescription !== ''): ?><div class="small text-muted"><?= esc($localizedDescription) ?></div><?php endif; ?></td>
                                <td><span class="badge border border-secondary text-secondary fw-normal"><?= esc($localizedType) ?></span></td>
                                <td><?= esc($localizedCategory) ?></td>
                                <td><?= (int) $account['allow_budget'] === 1 ? lang('ChartAccounts.valueYes') : lang('ChartAccounts.valueNo') ?></td>
                                <td><span class="badge border border-<?= (int) $account['is_active'] === 1 ? 'success' : 'secondary' ?> text-<?= (int) $account['is_active'] === 1 ? 'success' : 'secondary' ?> fw-normal"><?= (int) $account['is_active'] === 1 ? lang('ChartAccounts.labelActive') : lang('ChartAccounts.labelInactive') ?></span></td>
                                <td><?= (int) ($account['sort_order'] ?? 0) ?></td>
                                <td>
                                    <div class="d-inline-flex flex-nowrap gap-1 align-items-center">
                                        <a href="<?= base_url('chart-accounts/edit/' . $account['id']) ?>" class="btn btn-sm btn-warning" aria-label="<?= esc(lang('App.edit')) ?>"><i class="bi bi-pencil" aria-hidden="true"></i></a>
                                        <form action="<?= base_url('chart-accounts/toggle/' . $account['id']) ?>" method="post" class="d-inline"><?= csrf_field() ?><button type="submit" class="btn btn-sm btn-outline-secondary" aria-label="<?= esc(lang('ChartAccounts.actionToggle')) ?>"><i class="bi bi-arrow-repeat" aria-hidden="true"></i></button></form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4"><?= lang('ChartAccounts.noAccountsFound') ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection() ?>