<?php
/**
 * Modal partial for member self-service annual pledge submission and editing.
 *
 * Variables required:
 *   - None (self-contained with CSRF and locale helpers).
 */
?>
<div class="modal fade" id="selfPledgeModal" tabindex="-1" aria-labelledby="selfPledgeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="selfPledgeForm" action="<?= base_url('me/pledge') ?>" method="post">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="selfPledgeModalLabel">
                        <i class="bi bi-cash-coin me-1" aria-hidden="true"></i>
                        <span id="pledgeModalTitleText"><?= esc(lang('Pledges.pledgeModalTitle')) ?></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= esc(lang('App.cancel')) ?>"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        <?= esc(lang('Pledges.pledgeAmountHelp')) ?>
                    </p>

                    <div id="selfPledgeAlert" class="alert alert-danger d-none" role="alert"></div>

                    <div class="mb-3">
                        <label for="self_pledge_year" class="form-label small fw-semibold"><?= esc(lang('Pledges.pledgeYear')) ?> <span class="text-danger">*</span></label>
                        <select name="pledge_year" id="self_pledge_year" class="form-select form-select-sm" required>
                            <?php
                            $currentYear = (int) date('Y');
                            $nextYear = $currentYear + 1;
                            ?>
                            <option value="<?= $currentYear ?>"><?= $currentYear ?></option>
                            <option value="<?= $nextYear ?>"><?= $nextYear ?></option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="self_pledged_amount" class="form-label small fw-semibold"><?= esc(lang('Pledges.pledgedAmount')) ?> <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">$</span>
                            <input type="number" step="0.01" min="1" max="10000000" name="pledged_amount" id="self_pledged_amount" class="form-control form-control-sm" placeholder="0.00" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="self_pledge_notes" class="form-label small fw-semibold"><?= esc(lang('Pledges.notes')) ?></label>
                        <textarea name="notes" id="self_pledge_notes" class="form-control form-control-sm" rows="3" maxlength="500" placeholder="<?= esc(lang('Pledges.optionalNote')) ?>"></textarea>
                    </div>

                    <div class="text-muted small">
                        <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                        <?= esc(lang('Pledges.automatedFulfillmentHelp')) ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal"><?= esc(lang('App.cancel')) ?></button>
                    <button type="submit" class="btn btn-primary btn-sm" id="selfPledgeSubmitBtn">
                        <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>
                        <span id="selfPledgeSubmitText"><?= esc(lang('Pledges.submitPledge')) ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
