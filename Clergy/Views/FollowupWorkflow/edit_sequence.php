<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
$sequenceId = (int) ($sequence['id'] ?? 0);
$isNew      = ($sequenceId === 0);
$saveUrl    = $isNew
    ? base_url('followup-workflow/sequences/save')
    : base_url('followup-workflow/sequences/save/' . $sequenceId);
?>

<div class="d-flex justify-content-start align-items-center flex-wrap gap-2 mb-3">
    <a href="<?= base_url('followup-workflow/sequences') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Visitors.pageTitleSequences') ?>
    </a>
</div>

<!-- ── Sequence details card ───────────────────────────────────────── -->
<div class="card mb-4">
    <div class="card-header fw-semibold"><?= lang('Visitors.seqCardDetails') ?></div>
    <div class="card-body">
        <form method="post" action="<?= $saveUrl ?>">
            <?= csrf_field() ?>

            <div class="row g-3">
                <div class="col-md-7">
                    <label class="form-label" for="seq_name"><?= lang('Visitors.seqFieldName') ?> <span class="text-danger">*</span></label>
                    <input type="text"
                        id="seq_name"
                        name="name"
                        class="form-control"
                        maxlength="150"
                        required
                        value="<?= esc(old('name', $sequence['name'] ?? '')) ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label" for="seq_active"><?= lang('Visitors.seqFieldStatus') ?></label>
                    <select id="seq_active" name="is_active" class="form-select">
                        <option value="1" <?= ((int) ($sequence['is_active'] ?? 1)) === 1 ? 'selected' : '' ?>><?= lang('Visitors.seqStatusActive') ?></option>
                        <option value="0" <?= ((int) ($sequence['is_active'] ?? 1)) === 0 ? 'selected' : '' ?>><?= lang('Visitors.seqStatusInactive') ?></option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-center">
                    <div class="form-check mt-md-4">
                        <input class="form-check-input" type="checkbox" id="seq_default" name="is_default" value="1" <?= !empty($sequence['is_default']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="seq_default">
                            <?= lang('Visitors.seqFieldDefault') ?>
                        </label>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label" for="seq_description"><?= lang('Visitors.seqFieldDescription') ?></label>
                    <textarea id="seq_description"
                        name="description"
                        class="form-control"
                        rows="2"
                        maxlength="500"><?= esc(old('description', $sequence['description'] ?? '')) ?></textarea>
                </div>
            </div>

            <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg" aria-hidden="true"></i> <?= $isNew ? lang('Visitors.seqBtnCreate') : lang('Visitors.seqBtnSave') ?>
                </button>
                <a href="<?= base_url('followup-workflow/sequences') ?>" class="btn btn-outline-secondary"><?= lang('Visitors.seqBtnCancel') ?></a>
            </div>
        </form>
    </div>
</div>

<?php if (!$isNew): ?>
    <!-- ── Action steps card ────────────────────────────────────────────── -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><?= lang('Visitors.seqCardSteps') ?></span>
            <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddAction">
                <i class="bi bi-plus-circle" aria-hidden="true"></i> <?= lang('Visitors.seqBtnAddStep') ?>
            </button>
        </div>
        <div class="card-body p-0">
            <div id="actionsTableWrapper">
                <?= view('Clergy\Views\FollowupWorkflow/_actions_table', [
                    'actions'    => $actions,
                    'sequenceId' => $sequenceId,
                ]) ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ── Action Modal ─────────────────────────────────────────────────── -->
<?php if (!$isNew): ?>
    <?= view('Clergy\Views\FollowupWorkflow/_action_modal', [
        'sequenceId' => $sequenceId,
        'templates'  => $templates,
        'leaders'    => $leaders ?? [],
        'isUpstream' => false,
        'saveUrl'    => base_url('followup-workflow/actions/save'),
        'deleteUrl'  => base_url('followup-workflow/actions/delete/'),
    ]) ?>
<?php endif; ?>

<?= $this->endSection() ?>