<?= $this->extend('Finance\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-start align-items-center mb-3">
    <a href="<?= base_url('expenses') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('App.back') ?>
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="<?= base_url('expenses/update/' . $expense['id']) ?>" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="date" class="form-label"><?= lang('Finance.date') ?> <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="date" name="date" value="<?= esc($expense['date']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="amount" class="form-label"><?= lang('Finance.amount') ?> <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" class="form-control" id="amount" name="amount" value="<?= esc($expense['amount']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="fund_id" class="form-label"><?= lang('Finance.fundLabel') ?> <span class="text-danger">*</span></label>
                        <select class="form-select" id="fund_id" name="fund_id" required>
                            <option value=""><?= lang('Finance.selectFund') ?></option>
                            <?php foreach (($funds ?? []) as $fund): ?>
                                <option value="<?= (int) $fund['id'] ?>" <?= (int) ($expense['fund_id'] ?? 0) === (int) $fund['id'] ? 'selected' : '' ?>>
                                    <?php $editFundType = (string) ($fund['fund_type'] ?? 'unrestricted'); ?>
                                    <?php $editFundTypeLabelMap = ['restricted' => lang('Treasurer.fundTypeRestricted'), 'permanent' => lang('Treasurer.fundTypePermanent'), 'unrestricted' => lang('Treasurer.fundTypeUnrestricted')]; ?>
                                    <?= esc(fund_name_label((string) ($fund['name'] ?? ''))) ?> (<?= esc($editFundTypeLabelMap[$editFundType] ?? ucfirst($editFundType)) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="category" class="form-label"><?= lang('Finance.category') ?> <span class="text-danger">*</span></label>
                        <select class="form-select" id="category" name="category" required>
                            <option value="utilities" <?= $expense['category'] == 'utilities' ? 'selected' : '' ?>><?= lang('Finance.catUtilities') ?></option>
                            <option value="maintenance" <?= $expense['category'] == 'maintenance' ? 'selected' : '' ?>><?= lang('Finance.catMaintenance') ?></option>
                            <option value="salaries" <?= $expense['category'] == 'salaries' ? 'selected' : '' ?>><?= lang('Finance.catSalaries') ?></option>
                            <option value="ministry" <?= $expense['category'] == 'ministry' ? 'selected' : '' ?>><?= lang('Finance.catMinistry') ?></option>
                            <option value="supplies" <?= $expense['category'] == 'supplies' ? 'selected' : '' ?>><?= lang('Finance.catSupplies') ?></option>
                            <option value="other" <?= $expense['category'] == 'other' ? 'selected' : '' ?>><?= lang('Finance.categoryOther') ?></option>
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
                                            <option value="<?= (int) $account['id'] ?>" data-category="<?= esc((string) ($account['category'] ?? '')) ?>" <?= (int) ($expense['chart_account_id'] ?? 0) === (int) $account['id'] ? 'selected' : '' ?>>
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
                                    <option value="<?= (int) $account['id'] ?>" data-category="<?= esc((string) ($account['category'] ?? '')) ?>" <?= (int) ($expense['chart_account_id'] ?? 0) === (int) $account['id'] ? 'selected' : '' ?>>
                                        <?= esc($accountCode . ' - ' . $accountNameLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label"><?= lang('Finance.description') ?> <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="description" name="description" rows="3" required><?= esc($expense['description']) ?></textarea>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="vendor" class="form-label"><?= lang('Treasurer.vendor') ?></label>
                        <input type="text" class="form-control" id="vendor" name="vendor" value="<?= esc($expense['vendor'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="payment_method" class="form-label"><?= lang('Finance.paymentMethod') ?></label>
                        <select class="form-select" id="payment_method" name="payment_method">
                            <option value=""><?= lang('Finance.selectMethod') ?></option>
                            <option value="cash" <?= ($expense['payment_method'] ?? '') == 'cash' ? 'selected' : '' ?>><?= lang('Finance.methodCash') ?></option>
                            <option value="check" <?= ($expense['payment_method'] ?? '') == 'check' ? 'selected' : '' ?>><?= lang('Finance.methodCheck') ?></option>
                            <option value="credit_card" <?= ($expense['payment_method'] ?? '') == 'credit_card' ? 'selected' : '' ?>><?= lang('Finance.methodCreditCard') ?></option>
                            <option value="bank_transfer" <?= ($expense['payment_method'] ?? '') == 'bank_transfer' ? 'selected' : '' ?>><?= lang('Finance.methodBankTransfer') ?></option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="reference_number" class="form-label"><?= lang('Treasurer.referenceNumber') ?></label>
                        <input type="text" class="form-control" id="reference_number" name="reference_number" value="<?= esc($expense['reference_number'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="receipt" class="form-label">
                            <i class="bi bi-paperclip me-1" aria-hidden="true"></i><?= lang('Treasurer.receiptDocument') ?>
                        </label>
                        <?php if (!empty($expense['receipt_path'])): ?>
                            <div class="card bg-body-tertiary border mb-2">
                                <div class="card-body p-2 d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-2 overflow-hidden">
                                        <i class="bi bi-file-earmark-check text-success fs-5 flex-shrink-0" aria-hidden="true"></i>
                                        <div class="small text-truncate">
                                            <div class="fw-semibold text-truncate"><?= esc($expense['receipt_original_name'] ?? basename($expense['receipt_path'])) ?></div>
                                            <div class="text-body-secondary">
                                                <?= !empty($expense['receipt_file_size']) ? esc(number_format($expense['receipt_file_size'] / 1024, 1)) . ' KB' : '' ?>
                                                <?= !empty($expense['receipt_mime_type']) ? ' &bull; ' . esc($expense['receipt_mime_type']) : '' ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-1 flex-shrink-0">
                                        <a href="<?= base_url('expenses/receipt/' . $expense['id']) ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="<?= esc(lang('Treasurer.viewReceipt')) ?>">
                                            <i class="bi bi-eye" aria-hidden="true"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="remove_receipt" name="remove_receipt" value="1">
                                <label class="form-check-label text-danger small" for="remove_receipt">
                                    <i class="bi bi-trash me-1" aria-hidden="true"></i><?= lang('Treasurer.removeReceipt') ?>
                                </label>
                            </div>
                        <?php endif; ?>
                        <input type="file" class="form-control" id="receipt" name="receipt" accept=".pdf,.jpg,.jpeg,.png,.webp">
                        <div class="form-text"><?= !empty($expense['receipt_path']) ? lang('Treasurer.replaceReceipt') . ' &bull; ' . lang('Treasurer.receiptHelpText') : lang('Treasurer.receiptHelpText') ?></div>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label"><?= lang('Finance.notes') ?></label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?= esc($expense['notes']) ?></textarea>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_capital" name="is_capital" value="1" <?= !empty($expense['is_capital']) ? 'checked' : '' ?>>
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

<script>
    const editExpenseCategory = document.getElementById('category');
    const editExpenseChartAccount = document.getElementById('chart_account_id');

    editExpenseCategory?.addEventListener('change', function() {
        if (!editExpenseChartAccount || editExpenseChartAccount.value) {
            return;
        }

        const match = editExpenseChartAccount.querySelector(`option[data-category="${this.value}"]`);
        if (match) {
            editExpenseChartAccount.value = match.value;
        }
    });
</script>

<?= $this->endSection() ?>