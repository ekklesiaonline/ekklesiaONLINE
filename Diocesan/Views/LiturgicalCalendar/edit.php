<?= $this->extend('Diocesan\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
$seasonLabels = [
    'Advent' => lang('Diocesan.liturgicalSeasonAdvent'),
    'Christmas' => lang('Diocesan.liturgicalSeasonChristmas'),
    'Epiphany' => lang('Diocesan.liturgicalSeasonEpiphany'),
    'Lent' => lang('Diocesan.liturgicalSeasonLent'),
    'Holy Week' => lang('Diocesan.liturgicalSeasonHolyWeek'),
    'Easter' => lang('Diocesan.liturgicalSeasonEaster'),
    'Pentecost' => lang('Diocesan.liturgicalSeasonPentecost'),
    'Season after Pentecost' => lang('Diocesan.liturgicalSeasonAfterPentecost'),
];
$typeLabels = [
    'sunday' => lang('Diocesan.liturgicalTypeSunday'),
    'feast' => lang('Diocesan.liturgicalTypeFeast'),
    'day' => lang('Diocesan.liturgicalTypeDay'),
    'other' => lang('Diocesan.liturgicalTypeOther'),
];
$liturgicalIcons = config('LiturgicalIcons');
$iconOptions = is_object($liturgicalIcons) && isset($liturgicalIcons->options) && is_array($liturgicalIcons->options)
    ? $liturgicalIcons->options
    : [];
$colorMap = is_object($liturgicalIcons) && isset($liturgicalIcons->colors) && is_array($liturgicalIcons->colors)
    ? $liturgicalIcons->colors
    : [];
?>

<div class="d-flex justify-content-start align-items-center mb-3">
    <a href="<?= base_url($liturgical_write_base ?? 'system/liturgical-source') ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Diocesan.liturgicalBtnBack') ?>
    </a>
</div>

<datalist id="liturgicalIconOptions">
    <?php foreach ($iconOptions as $iconOption => $iconOptionLabelKey): ?>
        <option value="<?= esc((string) $iconOption) ?>" label="<?= esc(lang((string) $iconOptionLabelKey), 'attr') ?>"></option>
    <?php endforeach; ?>
</datalist>

<div class="card">
    <div class="card-body">
        <form action="<?= base_url(($liturgical_write_base ?? 'system/liturgical-source') . '/update/' . $entry['id']) ?>" method="post" class="row g-3">
            <?= csrf_field() ?>
            <div class="col-md-6">
                <label class="form-label"><?= lang('Diocesan.liturgicalLabelName') ?></label>
                <input type="text" name="name" class="form-control" value="<?= old('name', esc($entry['name'])) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= lang('Diocesan.liturgicalLabelSeason') ?></label>
                <select name="season" class="form-select" required>
                    <?php $seasonValue = old('season', $entry['season']); ?>
                    <?php foreach ($seasons as $season): ?>
                        <option value="<?= esc($season) ?>" <?= $seasonValue === $season ? 'selected' : '' ?>><?= esc($seasonLabels[$season] ?? $season) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= lang('Diocesan.liturgicalLabelType') ?></label>
                <?php $typeValue = old('observance_type', $entry['observance_type']); ?>
                <select name="observance_type" class="form-select" required>
                    <?php foreach ($observance_types as $type): ?>
                        <option value="<?= esc($type) ?>" <?= $typeValue === $type ? 'selected' : '' ?>><?= esc($typeLabels[$type] ?? ucfirst($type)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label"><?= lang('Diocesan.liturgicalLabelColor') ?></label>
                <?php $colorValue = old('liturgical_color', $entry['liturgical_color']); ?>
                <select name="liturgical_color" class="form-select" required>
                    <?php foreach ($liturgical_colors as $color): ?>
                        <?php
                        $rawColorOption = strtolower((string) $color);
                        $colorMetaOption = is_array($colorMap[$rawColorOption] ?? null) ? $colorMap[$rawColorOption] : [];
                        $colorLabelKey = (string) ($colorMetaOption['labelKey'] ?? '');
                        $colorLabel = $colorLabelKey !== '' ? lang($colorLabelKey) : ucfirst((string) $color);
                        ?>
                        <option value="<?= esc($color) ?>" <?= $colorValue === $color ? 'selected' : '' ?>><?= esc($colorLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= lang('Diocesan.liturgicalLabelSortOrder') ?></label>
                <input type="number" min="0" name="sort_order" class="form-control" value="<?= old('sort_order', esc((string) $entry['sort_order'])) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= lang('Diocesan.liturgicalLabelSeasonIcon') ?></label>
                <input type="text" name="season_icon" class="form-control" value="<?= old('season_icon', esc($entry['season_icon'] ?? '')) ?>" list="liturgicalIconOptions">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= lang('Diocesan.liturgicalLabelObservanceIcon') ?></label>
                <input type="text" name="observance_icon" class="form-control" value="<?= old('observance_icon', esc($entry['observance_icon'] ?? '')) ?>" list="liturgicalIconOptions">
            </div>
            <div class="col-12">
                <small class="text-muted d-block mb-1"><?= lang('Diocesan.liturgicalIconHelperText') ?></small>
                <div class="small text-muted d-flex flex-wrap gap-2">
                    <?php foreach ($iconOptions as $iconOption => $iconOptionLabelKey): ?>
                        <span class="border rounded px-2 py-1">
                            <strong><?= esc((string) $iconOption) ?></strong>: <?= esc(lang((string) $iconOptionLabelKey)) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-12">
                <?php $activeValue = old('is_active', (string) $entry['is_active']); ?>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="is_active" name="is_active" <?= $activeValue === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_active"><?= lang('App.statusActive') ?></label>
                </div>
            </div>

            <div class="col-12 text-end">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save" aria-hidden="true"></i> <?= lang('Diocesan.liturgicalBtnUpdateEntry') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>