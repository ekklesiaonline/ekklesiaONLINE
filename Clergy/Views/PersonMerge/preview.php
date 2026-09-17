<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><?= lang('People.personMergePreviewTitle') ?></h2>
    <a href="<?= base_url('/people/merge') ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('App.back') ?>
    </a>
</div>

<?php if (($conflicts ?? []) !== []): ?>
    <div class="alert alert-warning">
        <strong><?= lang('People.personMergeConflicts') ?></strong>
        <ul class="mb-0">
            <?php foreach ($conflicts as $conflict): ?>
                <li><?= esc($conflict) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php $source = $source ?? []; $target = $target ?? []; ?>

<form action="<?= base_url('/people/merge/execute') ?>" method="post" id="personMergeForm">
    <?= csrf_field() ?>
    <input type="hidden" name="source_person_id" value="<?= (int) ($source['id'] ?? 0) ?>">
    <input type="hidden" name="target_person_id" value="<?= (int) ($target['id'] ?? 0) ?>">

    <div class="row">
        <div class="col-md-6">
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <?= lang('People.personMergeSource') ?>
                    <span class="badge bg-light text-dark font-monospace">P<?= (int) ($source['id'] ?? 0) ?></span>
                </div>
                <div class="card-body">
                    <p class="text-muted"><?= lang('People.personMergeSourceHint') ?></p>
                    <dl class="row mb-0">
                        <?php foreach (['first_name', 'last_name', 'email', 'phone', 'address', 'birthdate', 'gender'] as $field): ?>
                            <?php $value = $source[$field] ?? null; ?>
                            <?php if ($value !== null && $value !== '' && $value !== '0000-00-00'): ?>
                                <dt class="col-sm-5"><?= lang('People.personMergeField_' . $field) ?></dt>
                                <dd class="col-sm-7"><?= esc($value) ?></dd>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-success">
                <div class="card-header bg-success text-white">
                    <?= lang('People.personMergeTarget') ?>
                    <span class="badge bg-light text-dark font-monospace">P<?= (int) ($target['id'] ?? 0) ?></span>
                </div>
                <div class="card-body">
                    <p class="text-muted"><?= lang('People.personMergeTargetHint') ?></p>
                    <dl class="row mb-0">
                        <?php foreach (['first_name', 'last_name', 'email', 'phone', 'address', 'birthdate', 'gender'] as $field): ?>
                            <?php $value = $target[$field] ?? null; ?>
                            <?php if ($value !== null && $value !== '' && $value !== '0000-00-00'): ?>
                                <dt class="col-sm-5"><?= lang('People.personMergeField_' . $field) ?></dt>
                                <dd class="col-sm-7">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="override_<?= $field ?>" id="target_<?= $field ?>" value="" checked>
                                        <label class="form-check-label" for="target_<?= $field ?>"><?= esc($value) ?></label>
                                    </div>
                                </dd>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-body">
            <h5><?= lang('People.personMergeFieldOverrides') ?></h5>
            <p class="text-muted"><?= lang('People.personMergeOverridesHint') ?></p>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th scope="col"><?= lang('People.personMergeField') ?></th>
                            <th scope="col"><?= lang('People.personMergeKeepSource') ?></th>
                            <th scope="col"><?= lang('People.personMergeKeepTarget') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (['first_name', 'last_name', 'email', 'phone', 'address', 'birthdate', 'gender'] as $field): ?>
                            <?php $sourceValue = $source[$field] ?? null; ?>
                            <?php $targetValue = $target[$field] ?? null; ?>
                            <?php if ($sourceValue !== null && $sourceValue !== '' && $targetValue !== null && $sourceValue !== $targetValue): ?>
                                <tr>
                                    <td><?= lang('People.personMergeField_' . $field) ?></td>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="override_<?= $field ?>" id="source_<?= $field ?>" value="<?= esc($sourceValue, 'attr') ?>">
                                            <label class="form-check-label" for="source_<?= $field ?>"><?= esc($sourceValue) ?></label>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="override_<?= $field ?>" id="target_<?= $field ?>" value="<?= esc($targetValue, 'attr') ?>" checked>
                                            <label class="form-check-label" for="target_<?= $field ?>"><?= esc($targetValue) ?></label>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mb-3 mt-3">
        <label for="merge_reason" class="form-label"><?= lang('People.personMergeReason') ?> <span class="text-danger">*</span></label>
        <textarea class="form-control" id="merge_reason" name="merge_reason" rows="2" minlength="8" required></textarea>
    </div>

    <button type="submit" class="btn btn-danger" <?= ($conflicts ?? []) !== [] ? 'disabled' : '' ?>>
        <?= lang('People.personMergeExecute') ?>
    </button>
</form>

<?= $this->endSection() ?>
