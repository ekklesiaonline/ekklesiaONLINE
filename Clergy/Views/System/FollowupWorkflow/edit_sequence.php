<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
$sequenceId = (int) ($sequence['id'] ?? 0);
$isNew      = ($sequenceId === 0);
$saveUrl    = $isNew
    ? base_url($routeBase . '/sequences/save')
    : base_url('system/followup-workflow/sequences/save/' . $sequenceId);
?>

<div class="d-flex justify-content-start align-items-center flex-wrap gap-2 mb-3">
    <a href="<?= base_url($routeBase . '/sequences') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Visitors.pageTitleSequences') ?>
    </a>
</div>

<div class="card mb-4">
    <div class="card-header fw-semibold"><?= lang('Visitors.seqCardDetails') ?></div>
    <div class="card-body">
        <form method="post" action="<?= $saveUrl ?>">
            <?= csrf_field() ?>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="seq_name"><?= lang('Visitors.seqFieldName') ?> <span class="text-danger">*</span></label>
                    <input type="text"
                        id="seq_name"
                        name="name"
                        class="form-control"
                        maxlength="150"
                        required
                        value="<?= esc(old('name', $sequence['name'] ?? '')) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="seq_active"><?= lang('Visitors.seqFieldStatus') ?></label>
                    <select id="seq_active" name="is_active" class="form-select">
                        <option value="1" <?= ((int) ($sequence['is_active'] ?? 1)) === 1 ? 'selected' : '' ?>><?= lang('Visitors.seqStatusActive') ?></option>
                        <option value="0" <?= ((int) ($sequence['is_active'] ?? 1)) === 0 ? 'selected' : '' ?>><?= lang('Visitors.seqStatusInactive') ?></option>
                    </select>
                </div>
                <?php if ($isNew): ?>
                <div class="col-md-5">
                    <label class="form-label"><?= lang('Visitors.seqFieldScope') ?></label>
                    <div>
                        <?php if (($scopeMode ?? 'system') === 'diocese'): ?>
                            <span class="badge border border-primary text-primary fw-normal"><?= esc($dioceseName ?? '') ?: lang('Visitors.seqScopeDiocese') ?></span>
                        <?php else: ?>
                            <span class="badge border border-info text-info fw-normal"><?= lang('Visitors.seqScopeGlobal') ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-text"><?= lang('Visitors.' . (($scopeMode ?? 'system') === 'diocese' ? 'seqScopeDioceseHint' : 'seqScopeGlobalHint')) ?></div>
                </div>
                <?php endif; ?>
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
                <a href="<?= base_url($routeBase . '/sequences') ?>" class="btn btn-outline-secondary"><?= lang('Visitors.seqBtnCancel') ?></a>
            </div>
        </form>
    </div>
</div>

<?php if (!$isNew): ?>
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

<?= view('Clergy\Views\FollowupWorkflow/_action_modal', [
    'sequenceId' => $sequenceId,
    'templates'  => $templates,
    'leaders'    => [],
    'isUpstream' => true,
    'saveUrl'    => base_url($routeBase . '/actions/save'),
    'deleteUrl'  => base_url($routeBase . '/actions/delete/'),
]) ?>
<?php endif; ?>

<?= $this->endSection() ?>
