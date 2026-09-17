<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php
$settings = $settings ?? [];
$categories = $categories ?? [
    'policy' => 'Password Policy',
    'security' => 'Security',
    'system' => 'System',
];

$booleanInput = static function (string $key, bool $value, string $label): string {
    $checked = $value ? 'checked' : '';
    $id = 'setting_' . str_replace('.', '_', $key);
    return <<<HTML
    <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" role="switch" id="{$id}" name="{$key}" value="1" {$checked}>
        <label class="form-check-label" for="{$id}">{$label}</label>
    </div>
HTML;
};

$textInput = static function (string $key, string $value, string $label, string $type = 'text'): string {
    $id = 'setting_' . str_replace('.', '_', $key);
    $safeValue = esc($value, 'attr');
    return <<<HTML
    <label for="{$id}" class="form-label">{$label}</label>
    <input type="{$type}" class="form-control" id="{$id}" name="{$key}" value="{$safeValue}">
HTML;
};

$textareaInput = static function (string $key, string $value, string $label): string {
    $id = 'setting_' . str_replace('.', '_', $key);
    $safeValue = esc($value);
    return <<<HTML
    <label for="{$id}" class="form-label">{$label}</label>
    <textarea class="form-control" id="{$id}" name="{$key}" rows="3">{$safeValue}</textarea>
HTML;
};
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><?= lang('System.sectionSettings') ?></h5>
    <a href="<?= base_url('system/dashboard') ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i><?= lang('App.back') ?>
    </a>
</div>

<?php if (session()->has('success')): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= esc(session('success')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (session()->has('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= esc(session('error')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php $errors = session('errors') ?? []; ?>
<?php if ($errors !== []): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= esc((string) $error) ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form action="<?= base_url('system/settings/update') ?>" method="post">
    <?= csrf_field() ?>

    <div class="row g-4">
        <?php foreach ($categories as $category => $categoryLabel): ?>
            <?php if (empty($settings[$category])): continue; endif; ?>
            <div class="col-12 col-xl-6">
                <div class="card h-100">
                    <div class="card-header"><strong><?= esc($categoryLabel) ?></strong></div>
                    <div class="card-body">
                        <?php foreach ($settings[$category] as $key => $setting): ?>
                            <?php
                                $value = $setting['value'];
                                $dataType = $setting['data_type'] ?? 'string';
                                $description = $setting['description'] ?? '';
                                $label = lang('System.setting_' . str_replace('.', '_', $key)) ?? ucwords(str_replace(['.', '_'], ' ', $key));
                            ?>
                            <div class="mb-3">
                                <?php if ($dataType === 'boolean'): ?>
                                    <?= $booleanInput($key, (bool) $value, $label) ?>
                                <?php elseif ($dataType === 'integer'): ?>
                                    <?= $textInput($key, (string) $value, $label, 'number') ?>
                                <?php elseif ($dataType === 'text'): ?>
                                    <?= $textareaInput($key, (string) $value, $label) ?>
                                <?php else: ?>
                                    <?= $textInput($key, (string) $value, $label) ?>
                                <?php endif; ?>
                                <?php if ($description !== ''): ?>
                                    <div class="form-text"><?= esc($description) ?></div>
                                <?php endif; ?>
                                <?php if (isset($errors[$key])): ?>
                                    <div class="text-danger small mt-1"><?= esc((string) $errors[$key]) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-4 d-flex justify-content-end">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save me-1" aria-hidden="true"></i><?= lang('App.save') ?>
        </button>
    </div>
</form>

<?= $this->endSection() ?>
