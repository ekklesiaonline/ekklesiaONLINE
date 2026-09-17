<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
$isEdit = !empty($template);
$action = $isEdit ? base_url('messages/templates/save/' . (int) $template['id']) : base_url('messages/templates/save');
$channelOptions = [
    'any'          => 'Messages.channelAny',
    'email'        => 'Messages.channelEmail',
    'sms'          => 'Messages.channelSms',
    'notification' => 'Messages.channelNotification',
];
?>

<div class="d-flex justify-content-start align-items-center mb-3 gap-2">
    <a href="<?= base_url('messages/templates') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Messages.templates') ?>
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form method="post" action="<?= esc($action, 'attr') ?>">
            <?= csrf_field() ?>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="name" class="form-label"><?= lang('Messages.templateName') ?> <span class="text-danger">*</span></label>
                    <input type="text" class="form-control<?= session('errors.name') ? ' is-invalid' : '' ?>" id="name" name="name" value="<?= esc(old('name', $template['name'] ?? '')) ?>" required maxlength="120">
                    <?php if (session('errors.name')): ?>
                        <div class="invalid-feedback"><?= esc(session('errors.name')) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="channel" class="form-label"><?= lang('Messages.templateChannel') ?></label>
                    <select class="form-select" id="channel" name="channel">
                        <?php foreach ($channelOptions as $value => $labelKey): ?>
                            <option value="<?= esc($value, 'attr') ?>" <?= old('channel', $template['channel'] ?? 'any') === $value ? 'selected' : '' ?>><?= lang($labelKey) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label for="subject" class="form-label"><?= lang('Messages.subject') ?></label>
                <input type="text" class="form-control" id="subject" name="subject" value="<?= esc(old('subject', $template['subject'] ?? '')) ?>" maxlength="255">
            </div>

            <div class="mb-3">
                <label for="body" class="form-label"><?= lang('Messages.templateBody') ?> <span class="text-danger">*</span></label>
                <textarea class="form-control<?= session('errors.body') ? ' is-invalid' : '' ?>" id="body" name="body" rows="6" maxlength="8000" required><?= esc(old('body', $template['body'] ?? '')) ?></textarea>
                <div class="form-text"><?= lang('Messages.tokensHelp') ?></div>
                <?php if (session('errors.body')): ?>
                    <div class="invalid-feedback"><?= esc(session('errors.body')) ?></div>
                <?php endif; ?>
            </div>

            <?php if ($isEdit): ?>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= (int) old('is_active', $template['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_active"><?= lang('Groups.active') ?></label>
                </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-outline-primary">
                <i class="bi bi-check-circle" aria-hidden="true"></i> <?= lang('App.save') ?>
            </button>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
