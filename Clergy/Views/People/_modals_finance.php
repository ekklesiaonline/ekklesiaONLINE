<?php /* Partial: donation + pledge modals. Required: $profileId, $userId, $funds, $chart_accounts */ ?>
<?php if (auth()->user()->can('donation.create') && $profileId > 0 && $userId > 0): ?>
<div class="modal fade" id="donationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="donationForm" method="post" action="<?= base_url('people/donation/' . $profileId) ?>">
                <?= csrf_field() ?>
                <input type="hidden" id="donation_id" name="donation_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="donationModalLabel"><?= lang('Finance.recordDonation') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= lang('App.close') ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="d_date" class="form-label"><?= lang('Finance.date') ?> <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="d_date" name="date" required value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="d_amount" class="form-label"><?= lang('Finance.amount') ?> <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" class="form-control" id="d_amount" name="amount" required>
                        </div>
                        <div class="col-md-6">
                            <label for="d_fund_id" class="form-label"><?= lang('Finance.fundLabel') ?> <span class="text-danger">*</span></label>
                            <select class="form-select" id="d_fund_id" name="fund_id" required>
                                <option value=""><?= lang('Finance.selectFund') ?></option>
                                <?php foreach (($funds ?? []) as $fund): ?>
                                    <option value="<?= (int) $fund['id'] ?>" data-fund-type="<?= esc($fund['fund_type'] ?? 'unrestricted', 'attr') ?>">
                                        <?= esc($fund['name'] ?? '') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="d_category" class="form-label"><?= lang('Finance.category') ?> <span class="text-danger">*</span></label>
                            <select class="form-select" id="d_category" name="category" required>
                                <option value="tithe"><?= lang('Finance.categoryTithe') ?></option>
                                <option value="offering"><?= lang('Finance.categoryOffering') ?></option>
                                <option value="building fund"><?= lang('Finance.categoryBuilding') ?></option>
                                <option value="mission"><?= lang('Finance.categoryMission') ?></option>
                                <option value="other"><?= lang('Finance.categoryOther') ?></option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="d_chart_account_id" class="form-label"><?= lang('Finance.chartOfAccountField') ?> <span class="text-danger">*</span></label>
                            <select class="form-select" id="d_chart_account_id" name="chart_account_id" required>
                                <option value=""><?= lang('Finance.selectAccount') ?></option>
                                <?php if (!empty($chart_accounts)): ?>
                                    <?php foreach ($chart_accounts as $groupLabel => $accounts): ?>
                                        <optgroup label="<?= esc($groupLabel) ?>">
                                            <?php foreach ($accounts as $account): ?>
                                                <option value="<?= (int) $account['id'] ?>" data-category="<?= esc((string) ($account['category'] ?? ''), 'attr') ?>">
                                                    <?= esc(($account['code'] ?? '') . ' - ' . ($account['name'] ?? '')) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="d_payment_method" class="form-label"><?= lang('Finance.paymentMethod') ?></label>
                            <select class="form-select" id="d_payment_method" name="payment_method">
                                <option value=""><?= lang('Finance.selectMethod') ?></option>
                                <option value="cash"><?= lang('Finance.methodCash') ?></option>
                                <option value="check"><?= lang('Finance.methodCheck') ?></option>
                                <option value="credit_card"><?= lang('Finance.methodCreditCard') ?></option>
                                <option value="bank_transfer"><?= lang('Finance.methodBankTransfer') ?></option>
                                <option value="online"><?= lang('Finance.methodOnline') ?></option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="d_notes" class="form-label"><?= lang('Finance.notes') ?></label>
                            <textarea class="form-control" id="d_notes" name="notes" rows="3"></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="d_is_restricted" name="is_restricted" value="1">
                                <label class="form-check-label" for="d_is_restricted"><?= lang('Finance.isRestricted') ?></label>
                            </div>
                        </div>
                        <div class="col-12" id="d_restriction_purpose_row">
                            <label for="d_restriction_purpose" class="form-label"><?= lang('Finance.restrictionPurpose') ?></label>
                            <input type="text" class="form-control" id="d_restriction_purpose" name="restriction_purpose">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="submit" class="btn btn-primary" id="saveDonationBtn"><?= lang('App.save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (auth()->user()->can('annual_pledge.manage') && $profileId > 0 && $userId > 0): ?>
<div class="modal fade" id="pledgeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="pledgeForm" method="post" action="<?= base_url('people/pledge/' . $profileId) ?>">
                <?= csrf_field() ?>
                <input type="hidden" id="p_pledge_id" name="pledge_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="pledgeModalLabel"><?= lang('Pledges.addAnnualPledge') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= lang('App.close') ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="p_pledge_year" class="form-label"><?= lang('Pledges.year') ?> <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="p_pledge_year" name="pledge_year" required value="<?= (int) date('Y') ?>" min="2000">
                        </div>
                        <div class="col-md-6">
                            <label for="p_fulfillment_status" class="form-label"><?= lang('Pledges.status') ?> <span class="text-danger">*</span></label>
                            <select class="form-select" id="p_fulfillment_status" name="fulfillment_status" required>
                                <option value="pledged"><?= lang('Pledges.statusPledged') ?></option>
                                <option value="partial"><?= lang('Pledges.statusPartial') ?></option>
                                <option value="fulfilled"><?= lang('Pledges.statusFulfilled') ?></option>
                                <option value="cancelled"><?= lang('Pledges.statusCancelled') ?></option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="p_pledged_amount" class="form-label"><?= lang('Pledges.pledged') ?></label>
                            <input type="number" step="0.01" min="0" class="form-control" id="p_pledged_amount" name="pledged_amount">
                        </div>
                        <div class="col-md-6">
                            <label for="p_fulfilled_amount" class="form-label"><?= lang('Pledges.fulfilled') ?></label>
                            <input type="number" step="0.01" min="0" class="form-control" id="p_fulfilled_amount" name="fulfilled_amount">
                        </div>
                        <div class="col-md-6">
                            <label for="p_pledged_at" class="form-label"><?= lang('Pledges.pledgedAt') ?></label>
                            <input type="date" class="form-control" id="p_pledged_at" name="pledged_at" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-12">
                            <label for="p_notes" class="form-label"><?= lang('Pledges.notes') ?></label>
                            <textarea class="form-control" id="p_notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="submit" class="btn btn-primary" id="savePledgeBtn"><?= lang('App.save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
