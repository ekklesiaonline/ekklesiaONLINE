<?php /* Partial: leadership position modal. Required: $can_edit, $profileId, $userId */ ?>
<?php if ($can_edit && $profileId > 0 && $userId > 0): ?>
<div class="modal fade" id="leadershipPositionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="leadershipPositionForm" method="post" action="<?= base_url('people/leadership/' . $profileId) ?>">
                <?= csrf_field() ?>
                <input type="hidden" id="lp_position_id" name="position_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="leadershipPositionModalLabel"><?= lang('Leadership.addPosition') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= lang('App.close') ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12 d-none" id="lp_eligibility_alert">
                            <div class="alert alert-warning py-2 px-3 mb-0 small">
                                <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>
                                <span id="lp_eligibility_text"></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="lp_title" class="form-label"><?= lang('Leadership.title') ?> <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="lp_title" name="title" required maxlength="150">
                        </div>
                        <div class="col-md-6">
                            <label for="lp_role_code" class="form-label"><?= lang('Leadership.roleCode') ?></label>
                            <input type="text" class="form-control" id="lp_role_code" name="role_code" maxlength="100">
                        </div>
                        <div class="col-md-6">
                            <label for="lp_scope_type" class="form-label"><?= lang('Leadership.scope') ?> <span class="text-danger">*</span></label>
                            <select class="form-select" id="lp_scope_type" name="scope_type" required>
                                <option value="parish"><?= lang('App.scopeParish') ?></option>
                                <option value="diocese"><?= lang('App.scopeDiocese') ?></option>
                                <option value="province"><?= lang('App.scopeProvince') ?></option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="lp_role_category" class="form-label"><?= lang('Leadership.roleCategory') ?></label>
                            <select class="form-select" id="lp_role_category" name="role_category">
                                <option value=""><?= lang('Leadership.selectPrompt') ?></option>
                                <option value="clergy"><?= lang('Leadership.roleCategoryClergy') ?></option>
                                <option value="lay_staff"><?= lang('Leadership.roleCategoryLayStaff') ?></option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="lp_clergy_order" class="form-label"><?= lang('Leadership.clergyOrder') ?></label>
                            <select class="form-select" id="lp_clergy_order" name="clergy_order">
                                <option value=""><?= lang('Leadership.selectPrompt') ?></option>
                                <option value="bishop"><?= lang('Leadership.clergyOrderBishop') ?></option>
                                <option value="priest"><?= lang('Leadership.clergyOrderPriest') ?></option>
                                <option value="deacon"><?= lang('Leadership.clergyOrderDeacon') ?></option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="lp_employment_type" class="form-label"><?= lang('Leadership.employmentType') ?></label>
                            <select class="form-select" id="lp_employment_type" name="employment_type">
                                <option value=""><?= lang('Leadership.selectPrompt') ?></option>
                                <option value="full_time"><?= lang('Leadership.employmentFullTime') ?></option>
                                <option value="part_time"><?= lang('Leadership.employmentPartTime') ?></option>
                                <option value="non_stipendiary"><?= lang('Leadership.employmentNonStipendiary') ?></option>
                                <option value="unpaid"><?= lang('Leadership.employmentUnpaid') ?></option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="lp_started_at" class="form-label"><?= lang('Leadership.startDate') ?></label>
                            <input type="date" class="form-control" id="lp_started_at" name="started_at">
                        </div>
                        <div class="col-md-6">
                            <label for="lp_ended_at" class="form-label"><?= lang('Leadership.endDate') ?></label>
                            <input type="date" class="form-control" id="lp_ended_at" name="ended_at">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="lp_active" name="active" value="1" checked>
                                <label class="form-check-label" for="lp_active"><?= lang('App.statusActive') ?></label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label for="lp_notes" class="form-label"><?= lang('Leadership.notes') ?></label>
                            <textarea class="form-control" id="lp_notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="submit" class="btn btn-primary" id="saveLeadershipPositionBtn"><?= lang('App.save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
