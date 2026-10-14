<?= $this->extend('Finance\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-start align-items-center mb-3">
    <a href="<?= base_url('expenses') ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('App.back') ?>
    </a>
</div>

<?php if (empty($funds)): ?>
    <?php $readinessStatus = (string) ($financeReadiness['status'] ?? 'no_funds'); ?>
    <?php $readinessMessageKey = match ($readinessStatus) {
        'no_active_funds' => 'Finance.expenseNoActiveFunds',
        'all_active_funds_locked' => 'Finance.expenseAllFundsLocked',
        default => 'Finance.expenseNoFunds',
    }; ?>
    <div class="alert alert-warning" role="alert">
        <h2 class="h5"><?= esc(lang('Finance.expenseFundSetupRequired')) ?></h2>
        <p class="mb-3"><?= esc(lang($readinessMessageKey)) ?></p>
        <?php if (!empty($canManageFunds)): ?>
            <?php if ($readinessStatus === 'no_funds'): ?>
                <form method="post" action="<?= base_url('funds/setup') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline-primary"><i class="bi bi-gear" aria-hidden="true"></i> <?= esc(lang('Finance.setupParishFinance')) ?></button>
                </form>
            <?php else: ?>
                <a href="<?= base_url('funds') ?>" class="btn btn-outline-primary"><i class="bi bi-piggy-bank" aria-hidden="true"></i> <?= esc(lang('Finance.manageFunds')) ?></a>
            <?php endif; ?>
        <?php else: ?>
            <p class="mb-0"><?= esc(lang('Finance.expenseContactFundManager')) ?></p>
        <?php endif; ?>
    </div>
<?php else: ?>
<div class="card">
    <div class="card-body">
        <form action="<?= base_url('expenses/store') ?>" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="date" class="form-label"><?= lang('Finance.date') ?> <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="date" name="date" value="<?= old('date', date('Y-m-d')) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="amount" class="form-label"><?= lang('Finance.amount') ?> <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" class="form-control" id="amount" name="amount" value="<?= old('amount') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="fund_id" class="form-label"><?= lang('Finance.fundLabel') ?> <span class="text-danger">*</span></label>
                        <select class="form-select" id="fund_id" name="fund_id" required>
                            <option value=""><?= lang('Finance.selectFund') ?></option>
                            <?php foreach (($funds ?? []) as $fund): ?>
                                <option value="<?= (int) $fund['id'] ?>" <?= old('fund_id') == $fund['id'] ? 'selected' : '' ?>>
                                    <?php $createFundType = (string) ($fund['fund_type'] ?? 'unrestricted'); ?>
                                    <?php $createFundTypeLabelMap = ['restricted' => lang('Treasurer.fundTypeRestricted'), 'permanent' => lang('Treasurer.fundTypePermanent'), 'unrestricted' => lang('Treasurer.fundTypeUnrestricted')]; ?>
                                    <?= esc(fund_name_label((string) ($fund['name'] ?? ''))) ?> (<?= esc($createFundTypeLabelMap[$createFundType] ?? ucfirst($createFundType)) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="category" class="form-label"><?= lang('Finance.category') ?> <span class="text-danger">*</span></label>
                        <select class="form-select" id="category" name="category" required>
                            <option value="utilities" <?= old('category') === 'utilities' ? 'selected' : '' ?>><?= lang('Finance.catUtilities') ?></option>
                            <option value="maintenance" <?= old('category') === 'maintenance' ? 'selected' : '' ?>><?= lang('Finance.catMaintenance') ?></option>
                            <option value="salaries" <?= old('category') === 'salaries' ? 'selected' : '' ?>><?= lang('Finance.catSalaries') ?></option>
                            <option value="ministry" <?= old('category') === 'ministry' ? 'selected' : '' ?>><?= lang('Finance.catMinistry') ?></option>
                            <option value="supplies" <?= old('category') === 'supplies' ? 'selected' : '' ?>><?= lang('Finance.catSupplies') ?></option>
                            <option value="other" <?= old('category') === 'other' ? 'selected' : '' ?>><?= lang('Finance.categoryOther') ?></option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="chart_account_id" class="form-label"><?= lang('Finance.chartOfAccountField') ?> <span class="text-danger">*</span></label>
                        <select class="form-select" id="chart_account_id" name="chart_account_id" required>
                            <option value=""><?= lang('Finance.selectAccount') ?></option>
                            <?php
                            $coaSectionLabelMap = [
                                'Unrestricted Income (4000s)'              => lang('Finance.sectionUnrestrictedIncome'),
                                'Restricted and Designated Income (5000s)' => lang('Finance.sectionRestrictedIncome'),
                                'Fund Transfers (5400s)'                   => lang('Finance.sectionFundTransfers'),
                                'Grant Tracking (5500s)'                   => lang('Finance.sectionGrantTracking'),
                                'Other Income'                             => lang('Finance.sectionOtherIncome'),
                                'Outreach Expenses (6000s)'                => lang('Finance.sectionOutreachExpenses'),
                                'Ministry and Program Expenses (7000s)'    => lang('Finance.sectionMinistryExpenses'),
                                'Payroll Overhead (7500s)'                 => lang('Finance.sectionPayrollOverhead'),
                                'Governance Expenses (7600s)'              => lang('Finance.sectionGovernanceExpenses'),
                                'Operations and Facilities (8000s)'        => lang('Finance.sectionOperationsFacilities'),
                                'Other Expenses'                           => lang('Finance.sectionOtherExpenses'),
                                'Other'                                    => lang('Finance.sectionOther'),
                            ];
                            ?>
                            <?php if (!empty($groupedChartAccounts)): ?>
                                <?php foreach ($groupedChartAccounts as $groupLabel => $accounts): ?>
                                    <optgroup label="<?= esc($coaSectionLabelMap[$groupLabel] ?? $groupLabel) ?>">
                                        <?php foreach ($accounts as $account): ?>
                                            <?php $accountCode = (string) ($account['code'] ?? ''); ?>
                                            <?php $accountName = (string) ($account['name'] ?? $accountCode); ?>
                                            <?php $accountNameLabel = coa_name_label($accountCode, $accountName); ?>
                                            <option value="<?= (int) $account['id'] ?>" data-category="<?= esc((string) ($account['category'] ?? '')) ?>" <?= old('chart_account_id') == $account['id'] ? 'selected' : '' ?>>
                                                <?= esc($accountCode . ' - ' . $accountNameLabel) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php foreach ($chartAccounts as $account): ?>
                                    <?php $accountCode = (string) ($account['code'] ?? ''); ?>
                                    <?php $accountName = (string) ($account['name'] ?? $accountCode); ?>
                                    <?php $accountNameLabel = coa_name_label($accountCode, $accountName); ?>
                                    <option value="<?= (int) $account['id'] ?>" data-category="<?= esc((string) ($account['category'] ?? '')) ?>" <?= old('chart_account_id') == $account['id'] ? 'selected' : '' ?>>
                                                <?= esc($accountCode . ' - ' . $accountNameLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label"><?= lang('Finance.description') ?> <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="description" name="description" rows="3" required><?= old('description') ?></textarea>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="vendor" class="form-label"><?= lang('Treasurer.vendor') ?></label>
                        <input type="text" class="form-control" id="vendor" name="vendor" value="<?= old('vendor') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="payment_method" class="form-label"><?= lang('Finance.paymentMethod') ?></label>
                        <select class="form-select" id="payment_method" name="payment_method">
                            <option value=""><?= lang('Finance.selectMethod') ?></option>
                            <option value="cash" <?= old('payment_method') === 'cash' ? 'selected' : '' ?>><?= lang('Finance.methodCash') ?></option>
                            <option value="check" <?= old('payment_method') === 'check' ? 'selected' : '' ?>><?= lang('Finance.methodCheck') ?></option>
                            <option value="credit_card" <?= old('payment_method') === 'credit_card' ? 'selected' : '' ?>><?= lang('Finance.methodCreditCard') ?></option>
                            <option value="bank_transfer" <?= old('payment_method') === 'bank_transfer' ? 'selected' : '' ?>><?= lang('Finance.methodBankTransfer') ?></option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="reference_number" class="form-label"><?= lang('Treasurer.referenceNumber') ?></label>
                        <input type="text" class="form-control" id="reference_number" name="reference_number" value="<?= old('reference_number') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="receipt" class="form-label">
                            <i class="bi bi-paperclip me-1" aria-hidden="true"></i><?= lang('Treasurer.receiptDocument') ?>
                        </label>
                        <input type="file" class="form-control" id="receipt" name="receipt" accept=".pdf,.jpg,.jpeg,.png,.webp">
                        <div class="form-text"><?= lang('Treasurer.receiptHelpText') ?></div>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label"><?= lang('Finance.notes') ?></label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?= old('notes') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_capital" name="is_capital" value="1" <?= old('is_capital') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_capital"><?= lang('Finance.isCapital') ?></label>
                            <div class="form-text"><?= lang('Finance.capitalExpenseHelp') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                <a href="<?= base_url('expenses') ?>" class="btn btn-outline-secondary"><?= lang('App.cancel') ?></a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1" aria-hidden="true"></i><?= lang('App.save') ?>
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
    const expenseCategory = document.getElementById('category');
    const expenseChartAccount = document.getElementById('chart_account_id');

    function syncExpenseAccount(force = false) {
        if (!expenseCategory || !expenseChartAccount) {
            return;
        }

        if (!force && expenseChartAccount.value) {
            return;
        }

        const match = expenseChartAccount.querySelector(`option[data-category="${expenseCategory.value}"]`);
        if (match) {
            expenseChartAccount.value = match.value;
        }
    }

    expenseCategory?.addEventListener('change', function() {
        expenseChartAccount.value = '';
        syncExpenseAccount(true);
    });

    syncExpenseAccount();
</script>

<?= $this->endSection() ?>