<?php
/**
 * Shared modal partial for Bank Statement Import (OFX/QFX/CSV).
 *
 * Variables:
 *   - $accounts (array<int, array<string, mixed>>): Available bank accounts for parish.
 *   - $preselectedAccountId (int|null): Optional preselected account ID.
 */
?>
<div class="modal fade" id="importStatementModal" tabindex="-1" aria-labelledby="importStatementModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="importStatementForm" action="<?= base_url('banking/import') ?>" method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="importStatementModalLabel">
                        <i class="bi bi-file-earmark-arrow-up me-2" aria-hidden="true"></i>
                        <?= esc(lang('Finance.bankImportModalTitle')) ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= esc(lang('App.cancel')) ?>"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        <?= esc(lang('Finance.bankImportStatementHelp')) ?>
                    </p>

                    <div class="mb-3">
                        <label for="import_bank_account_id" class="form-label small fw-semibold">
                            <?= esc(lang('Finance.bankSelectAccount')) ?> <span class="text-danger">*</span>
                        </label>
                        <select name="bank_account_id" id="import_bank_account_id" class="form-select form-select-sm" required>
                            <?php if (empty($preselectedAccountId)): ?>
                                <option value=""><?= esc(lang('Finance.bankSelectAccount')) ?>...</option>
                            <?php endif; ?>
                            <?php foreach (($accounts ?? []) as $acc): ?>
                                <option value="<?= (int) $acc['id'] ?>" <?= (!empty($preselectedAccountId) && (int) $preselectedAccountId === (int) $acc['id']) ? 'selected' : '' ?>>
                                    <?= esc($acc['name']) ?> (<?= esc($acc['currency_code'] ?? 'USD') ?>) <?= !empty($acc['institution_name']) ? '— ' . esc($acc['institution_name']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="statement_file" class="form-label small fw-semibold">
                            <?= esc(lang('Finance.bankSelectFile')) ?> <span class="text-danger">*</span>
                        </label>
                        <input type="file" name="statement_file" id="statement_file" class="form-control form-control-sm" accept=".ofx,.qfx,.csv,.txt" required>
                        <div class="form-text small">
                            <?= esc(lang('Finance.bankFileHelp')) ?>
                        </div>
                    </div>

                    <div class="alert alert-info py-2 px-3 small mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-info-circle flex-shrink-0" aria-hidden="true"></i>
                        <div>
                            <?= esc(lang('Finance.bankAutoDeduplicateNotice')) ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal"><?= esc(lang('App.cancel')) ?></button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnSubmitImport">
                        <i class="bi bi-upload me-1" aria-hidden="true"></i>
                        <?= esc(lang('Finance.bankImportSubmit')) ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
