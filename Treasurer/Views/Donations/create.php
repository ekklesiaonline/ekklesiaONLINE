<?= $this->extend('Treasurer\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-start align-items-center mb-3">
    <a href="<?= base_url('donations') ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('App.back') ?>
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="<?= base_url('donations/store') ?>" method="post">
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
                            <option value="tithe"><?= lang('Finance.categoryTithe') ?></option>
                            <option value="offering"><?= lang('Finance.categoryOffering') ?></option>
                            <option value="building fund"><?= lang('Finance.categoryBuilding') ?></option>
                            <option value="mission"><?= lang('Finance.categoryMission') ?></option>
                            <option value="other"><?= lang('Finance.categoryOther') ?></option>
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
                            <option value="cash"><?= lang('Finance.methodCash') ?></option>
                            <option value="check"><?= lang('Finance.methodCheck') ?></option>
                            <option value="credit_card"><?= lang('Finance.methodCreditCard') ?></option>
                            <option value="bank_transfer"><?= lang('Finance.methodBankTransfer') ?></option>
                            <option value="online"><?= lang('Finance.methodOnline') ?></option>
                        </select>
                    </div>



                    <div class="mb-3">
                        <label for="notes" class="form-label"><?= lang('Finance.notes') ?></label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?= old('notes') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_restricted" name="is_restricted" value="1" <?= old('is_restricted') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_restricted"><?= lang('Finance.isRestricted') ?></label>
                            <div class="form-text"><?= lang('Finance.restrictedGiftHelp') ?></div>
                        </div>
                    </div>
                    <div class="mb-3" id="restriction_purpose_row">
                        <label for="restriction_purpose" class="form-label"><?= lang('Finance.restrictionPurpose') ?></label>
                        <input type="text" class="form-control" id="restriction_purpose" name="restriction_purpose"
                            value="<?= old('restriction_purpose') ?>"
                            placeholder="<?= esc(lang('Finance.restrictionPurposePlaceholder')) ?>">
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-3">
                        <a href="<?= base_url('donations') ?>" class="btn btn-secondary"><?= lang('App.cancel') ?></a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save" aria-hidden="true"></i> <?= lang('Finance.recordDonation') ?>
                        </button>
                    </div>
        </form>
    </div>
</div>

<script>
    const donationCategory = document.getElementById('category');
    const donationChartAccount = document.getElementById('chart_account_id');
    const isRestrictedChk = document.getElementById('is_restricted');
    const restrictionPurposeRow = document.getElementById('restriction_purpose_row');

    function toggleRestrictionPurpose() {
        if (restrictionPurposeRow) {
            restrictionPurposeRow.style.display = isRestrictedChk && isRestrictedChk.checked ? '' : 'none';
        }
    }

    isRestrictedChk?.addEventListener('change', toggleRestrictionPurpose);
    toggleRestrictionPurpose();

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

    syncDonationAccount();
</script>


<?= $this->endSection() ?>