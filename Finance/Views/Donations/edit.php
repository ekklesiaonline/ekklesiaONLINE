<?= $this->extend('Finance\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
/**
 * Variables: $donation, $funds, $chartAccounts, $groupedChartAccounts, $donor_option
 */
$donationId         = (int) ($donation['id'] ?? 0);
$currentFundId      = (int) old('fund_id', $donation['fund_id'] ?? 0);
$currentCategory    = (string) old('category', $donation['category'] ?? 'other');
$currentChartAccId  = (int) old('chart_account_id', $donation['chart_account_id'] ?? 0);
$currentMethod      = (string) old('payment_method', $donation['payment_method'] ?? '');
?>

<div class="d-flex justify-content-start align-items-center mb-3">
    <a href="<?= base_url('donations') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('App.back') ?>
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="<?= base_url('donations/update/' . $donationId) ?>" method="post">
            <?= csrf_field() ?>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="date" class="form-label"><?= lang('Finance.date') ?> <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="date" name="date" value="<?= esc(old('date', $donation['date'] ?? '')) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="amount" class="form-label"><?= lang('Finance.amount') ?> <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" class="form-control" id="amount" name="amount" value="<?= esc(old('amount', $donation['amount'] ?? '')) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="fund_id" class="form-label"><?= lang('Finance.fundLabel') ?> <span class="text-danger">*</span></label>
                        <select class="form-select" id="fund_id" name="fund_id" required>
                            <option value=""><?= lang('Finance.selectFund') ?></option>
                            <?php foreach (($funds ?? []) as $fund): ?>
                                <option value="<?= (int) $fund['id'] ?>" data-default-chart-account="<?= (int) ($fund['chart_account_id'] ?? 0) ?>" <?= $currentFundId === (int) $fund['id'] ? 'selected' : '' ?>>
                                    <?php $fundType = (string) ($fund['fund_type'] ?? 'unrestricted'); ?>
                                    <?php $fundTypeLabelMap = ['restricted' => lang('Treasurer.fundTypeRestricted'), 'permanent' => lang('Treasurer.fundTypePermanent'), 'unrestricted' => lang('Treasurer.fundTypeUnrestricted')]; ?>
                                    <?= esc(fund_name_label((string) ($fund['name'] ?? ''))) ?> (<?= esc($fundTypeLabelMap[$fundType] ?? ucfirst($fundType)) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="category" class="form-label"><?= lang('Finance.category') ?> <span class="text-danger">*</span></label>
                        <select class="form-select" id="category" name="category" required>
                            <option value="tithe" <?= $currentCategory === 'tithe' ? 'selected' : '' ?>><?= lang('Finance.categoryTithe') ?></option>
                            <option value="offering" <?= $currentCategory === 'offering' ? 'selected' : '' ?>><?= lang('Finance.categoryOffering') ?></option>
                            <option value="building fund" <?= in_array($currentCategory, ['building fund', 'building_fund'], true) ? 'selected' : '' ?>><?= lang('Finance.categoryBuilding') ?></option>
                            <option value="mission" <?= $currentCategory === 'mission' ? 'selected' : '' ?>><?= lang('Finance.categoryMission') ?></option>
                            <option value="other" <?= $currentCategory === 'other' ? 'selected' : '' ?>><?= lang('Finance.categoryOther') ?></option>
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
                                            <option value="<?= (int) $account['id'] ?>" data-category="<?= esc((string) ($account['category'] ?? '')) ?>" <?= $currentChartAccId === (int) $account['id'] ? 'selected' : '' ?>>
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
                                    <option value="<?= (int) $account['id'] ?>" data-category="<?= esc((string) ($account['category'] ?? '')) ?>" <?= $currentChartAccId === (int) $account['id'] ? 'selected' : '' ?>>
                                        <?= esc($accountCode . ' - ' . $accountNameLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <div class="form-text"><?= lang('Finance.chartOfAccounts') ?></div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="mb-3" id="donor_field">
                        <label for="user_id" class="form-label"><?= lang('Finance.donorOptional') ?></label>
                        <?= view('partials/_member_select_ajax', [
                            'select_id'   => 'user_id',
                            'name'        => 'user_id',
                            'placeholder' => lang('Finance.selectMember'),
                            'selected'    => $donor_option ?? [],
                        ]) ?>
                    </div>

                    <div class="mb-3">
                        <label for="payment_method" class="form-label"><?= lang('Finance.paymentMethod') ?></label>
                        <select class="form-select" id="payment_method" name="payment_method">
                            <option value=""><?= lang('Finance.selectMethod') ?></option>
                            <option value="cash" <?= $currentMethod === 'cash' ? 'selected' : '' ?>><?= lang('Finance.methodCash') ?></option>
                            <option value="check" <?= $currentMethod === 'check' ? 'selected' : '' ?>><?= lang('Finance.methodCheck') ?></option>
                            <option value="credit_card" <?= $currentMethod === 'credit_card' ? 'selected' : '' ?>><?= lang('Finance.methodCreditCard') ?></option>
                            <option value="bank_transfer" <?= $currentMethod === 'bank_transfer' ? 'selected' : '' ?>><?= lang('Finance.methodBankTransfer') ?></option>
                            <option value="online" <?= $currentMethod === 'online' ? 'selected' : '' ?>><?= lang('Finance.methodOnline') ?></option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label"><?= lang('Finance.notes') ?></label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?= esc(old('notes', $donation['notes'] ?? '')) ?></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-3">
                        <a href="<?= base_url('donations') ?>" class="btn btn-outline-secondary"><?= lang('App.cancel') ?></a>
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="bi bi-check-circle" aria-hidden="true"></i> <?= lang('Treasurer.updateDonation') ?>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    const donationCategory = document.getElementById('category');
    const donationFund = document.getElementById('fund_id');
    const donationChartAccount = document.getElementById('chart_account_id');

    function applyFundDefault(force = false) {
        if (!donationFund || !donationChartAccount || (!force && donationChartAccount.value)) {
            return;
        }

        const defaultAccountId = donationFund.selectedOptions[0]?.dataset.defaultChartAccount ?? '';
        donationChartAccount.value = defaultAccountId && donationChartAccount.querySelector(`option[value="${defaultAccountId}"]`) ? defaultAccountId : '';
        const selectedAccount = donationChartAccount.selectedOptions[0];
        if (selectedAccount?.dataset.category && donationCategory) {
            donationCategory.value = selectedAccount.dataset.category;
        }
    }

    donationFund?.addEventListener('change', function() {
        applyFundDefault(true);
    });

    function syncDonationAccount(force = false) {
        if (!donationCategory || !donationChartAccount) {
            return;
        }

        if (!force && donationChartAccount.value) {
            return;
        }

        const match = donationChartAccount.querySelector(`option[data-category="${donationCategory.value}"]`);
        if (match) {
            donationChartAccount.value = match.value;
        }
    }

    donationCategory?.addEventListener('change', function() {
        donationChartAccount.value = '';
        syncDonationAccount(true);
    });

    applyFundDefault();
    syncDonationAccount();
</script>

<?= $this->endSection() ?>
