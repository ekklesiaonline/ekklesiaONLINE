<?php

/**
 * Shared partial: Create Standalone Non-Portal Person Modal
 *
 * Required variables (set before including):
 *   string $modalId      — Bootstrap modal id attribute, e.g. 'createStandaloneNonPortalModal'
 *   string $fieldPrefix  — HTML id prefix for fields, e.g. 'nm' or 'np'
 *   string $formAction   — base_url()-resolved route, e.g. base_url('members/store-non-portal')
 */
?>
<div class="modal fade" id="<?= esc($modalId) ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="<?= esc($formAction) ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title"><?= lang('Members.createStandaloneNonPortal') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= esc(lang('App.close')) ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="<?= esc($fieldPrefix) ?>_first_name" class="form-label"><?= lang('Members.firstName') ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="<?= esc($fieldPrefix) ?>_first_name" name="first_name" required minlength="2" maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label for="<?= esc($fieldPrefix) ?>_last_name" class="form-label"><?= lang('Members.lastName') ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="<?= esc($fieldPrefix) ?>_last_name" name="last_name" required minlength="2" maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label for="<?= esc($fieldPrefix) ?>_nickname" class="form-label"><?= lang('Members.nicknameOptional') ?></label>
                        <input type="text" class="form-control" id="<?= esc($fieldPrefix) ?>_nickname" name="nickname" maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label for="<?= esc($fieldPrefix) ?>_birth_date" class="form-label"><?= lang('Members.birthDate') ?></label>
                        <input type="date" class="form-control" id="<?= esc($fieldPrefix) ?>_birth_date" name="birth_date">
                    </div>
                    <div class="mb-3">
                        <label for="<?= esc($fieldPrefix) ?>_username" class="form-label"><?= lang('Members.usernameOptional') ?></label>
                        <input type="text" class="form-control" id="<?= esc($fieldPrefix) ?>_username" name="username" maxlength="255" placeholder="<?= lang('Members.autoGenerateIfEmpty') ?>">
                    </div>
                    <div class="alert alert-info small mb-0">
                        <?= esc($modalHint ?? lang('Members.nonPortalPersonModalHint')) ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="submit" class="btn btn-primary"><?= lang('Members.createStandaloneNonPortalSubmit') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>