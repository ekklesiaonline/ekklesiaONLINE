<?php /* Partial: delete + portal-enable modals. Required: $canPortalAccessManage */ ?>
<?php if (auth()->user()->can('member.delete')): ?>
    <div class="modal fade" id="singleDeletePersonModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill text-warning me-2" aria-hidden="true"></i><?= lang('App.delete') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= esc(lang('App.close')) ?>"></button>
                </div>
                <div class="modal-body">
                    <p class="fw-semibold mb-2" id="singleDeletePersonInfo"></p>
                    <div class="alert alert-warning py-2 mb-3"><?= lang('App.actionCannotBeUndone') ?></div>
                    <label class="form-label" for="singleDeletePersonReasonInput"><?= lang('App.bulkDeleteReasonLabel') ?></label>
                    <textarea id="singleDeletePersonReasonInput" class="form-control" rows="3" maxlength="500" placeholder="<?= esc(lang('App.bulkDeleteReasonPlaceholder')) ?>" aria-describedby="singleDeletePersonReasonFeedback"></textarea>
                    <div id="singleDeletePersonReasonFeedback" class="invalid-feedback"></div>
                    <div class="small text-muted mt-1"><?= lang('App.bulkDeleteReasonHelp') ?></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="button" class="btn btn-danger" id="confirmSingleDeletePersonBtn"><?= lang('App.delete') ?></button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="bulkDeletePeopleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill text-warning me-2" aria-hidden="true"></i><?= lang('App.bulkDeleteWarningTitle') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= esc(lang('App.close')) ?>"></button>
                </div>
                <div class="modal-body">
                    <p class="fw-semibold mb-2" id="bulkDeletePeopleSummary"></p>
                    <div class="alert alert-warning py-2 mb-3"><?= lang('App.deletionsCannotBeUndone') ?></div>
                    <label class="form-label" for="bulkDeletePeopleReasonInput"><?= lang('App.bulkDeleteReasonLabel') ?></label>
                    <textarea id="bulkDeletePeopleReasonInput" class="form-control" rows="3" maxlength="500" placeholder="<?= esc(lang('App.bulkDeleteReasonPlaceholder')) ?>" aria-describedby="bulkDeletePeopleReasonFeedback"></textarea>
                    <div id="bulkDeletePeopleReasonFeedback" class="invalid-feedback"></div>
                    <div class="small text-muted mt-1"><?= lang('App.bulkDeleteReasonHelp') ?></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="button" class="btn btn-danger" id="confirmBulkDeletePeopleBtn"><?= lang('App.delete') ?></button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($canPortalAccessManage): ?>
    <div class="modal fade" id="enablePersonPortalModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= lang('People.enablePortalAccess') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= esc(lang('App.close')) ?>"></button>
                </div>
                <form id="enablePersonPortalForm">
                    <div class="modal-body">
                        <input type="hidden" name="user_id" value="">
                        <p id="enablePersonPortalLabel" class="small text-muted mb-2"></p>
                        <div class="mb-3">
                            <label for="enablePersonPortalEmail" class="form-label"><?= lang('App.colEmail') ?></label>
                            <input id="enablePersonPortalEmail" type="email" class="form-control" name="email" required>
                        </div>
                        <div class="alert alert-info py-2 mb-0"><?= esc(lang('People.portalEnableResetNotice')) ?></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                        <button type="submit" class="btn btn-outline-success" id="confirmEnablePersonPortalBtn"><?= lang('People.enablePortalAccess') ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>
