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
$iconMap = is_object($liturgicalIcons) && isset($liturgicalIcons->map) && is_array($liturgicalIcons->map)
    ? $liturgicalIcons->map
    : [];
$iconOptions = is_object($liturgicalIcons) && isset($liturgicalIcons->options) && is_array($liturgicalIcons->options)
    ? $liturgicalIcons->options
    : [];
$colorMap = is_object($liturgicalIcons) && isset($liturgicalIcons->colors) && is_array($liturgicalIcons->colors)
    ? $liturgicalIcons->colors
    : [];
$defaultIconClass = is_object($liturgicalIcons) && isset($liturgicalIcons->defaultIcon)
    ? (string) $liturgicalIcons->defaultIcon
    : 'bi-tag';
?>

<div class="d-flex justify-content-start align-items-center mb-3">
    <small class="text-muted"><?= lang('Diocesan.liturgicalSubtitle') ?></small>
</div>

<datalist id="liturgicalIconOptions">
    <?php foreach ($iconOptions as $iconOption => $iconOptionLabelKey): ?>
        <option value="<?= esc((string) $iconOption) ?>" label="<?= esc(lang((string) $iconOptionLabelKey), 'attr') ?>"></option>
    <?php endforeach; ?>
</datalist>

<?php if (!empty($can_manage_liturgical_source)): ?>
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0"><?= lang('Diocesan.liturgicalAddEntry') ?></h6>
        </div>
        <div class="card-body">
            <form action="<?= base_url(($liturgical_write_base ?? 'system/liturgical-source') . '/store') ?>" method="post" class="row g-3">
                <?= csrf_field() ?>
                <div class="col-md-4">
                    <label class="form-label"><?= lang('Diocesan.liturgicalLabelName') ?></label>
                    <input type="text" name="name" class="form-control" value="<?= old('name') ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= lang('Diocesan.liturgicalLabelSeason') ?></label>
                    <select name="season" class="form-select" required>
                        <option value=""><?= lang('Diocesan.liturgicalPlaceholderSelect') ?></option>
                        <?php foreach ($seasons as $season): ?>
                            <option value="<?= esc($season) ?>" <?= old('season') === $season ? 'selected' : '' ?>><?= esc($seasonLabels[$season] ?? $season) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= lang('Diocesan.liturgicalLabelType') ?></label>
                    <select name="observance_type" class="form-select" required>
                        <option value=""><?= lang('Diocesan.liturgicalPlaceholderSelect') ?></option>
                        <?php foreach ($observance_types as $type): ?>
                            <option value="<?= esc($type) ?>" <?= old('observance_type') === $type ? 'selected' : '' ?>><?= esc($typeLabels[$type] ?? ucfirst($type)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= lang('Diocesan.liturgicalLabelColor') ?></label>
                    <select name="liturgical_color" class="form-select" required>
                        <option value=""><?= lang('Diocesan.liturgicalPlaceholderSelect') ?></option>
                        <?php foreach ($liturgical_colors as $color): ?>
                            <?php
                            $rawColorOption = strtolower((string) $color);
                            $colorMetaOption = is_array($colorMap[$rawColorOption] ?? null) ? $colorMap[$rawColorOption] : [];
                            $colorLabelKey = (string) ($colorMetaOption['labelKey'] ?? '');
                            $colorLabel = $colorLabelKey !== '' ? lang($colorLabelKey) : ucfirst((string) $color);
                            ?>
                            <option value="<?= esc($color) ?>" <?= old('liturgical_color') === $color ? 'selected' : '' ?>><?= esc($colorLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= lang('Diocesan.liturgicalLabelSortOrder') ?></label>
                    <input type="number" min="0" name="sort_order" class="form-control" value="<?= old('sort_order', '0') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= lang('Diocesan.liturgicalLabelSeasonIcon') ?></label>
                    <input type="text" name="season_icon" class="form-control" value="<?= old('season_icon') ?>" placeholder="<?= esc(lang('Diocesan.liturgicalPlaceholderSeasonIcon')) ?>" list="liturgicalIconOptions">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= lang('Diocesan.liturgicalLabelObservanceIcon') ?></label>
                    <input type="text" name="observance_icon" class="form-control" value="<?= old('observance_icon') ?>" placeholder="<?= esc(lang('Diocesan.liturgicalPlaceholderObservanceIcon')) ?>" list="liturgicalIconOptions">
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
                <div class="col-md-2 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" value="1" id="is_active" name="is_active" <?= old('is_active', '1') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active"><?= lang('App.statusActive') ?></label>
                    </div>
                </div>
                <div class="col-md-2 d-flex align-items-end justify-content-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle" aria-hidden="true"></i> <?= lang('Diocesan.liturgicalBtnAdd') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h6 class="mb-0"><?= lang('Diocesan.liturgicalEntries') ?></h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr>
                        <th scope="col"><?= lang('Diocesan.liturgicalLabelName') ?></th>
                        <th scope="col"><?= lang('Diocesan.liturgicalLabelSeason') ?></th>
                        <th scope="col"><?= lang('Diocesan.liturgicalLabelType') ?></th>
                        <th scope="col"><?= lang('Diocesan.liturgicalLabelColor') ?></th>
                        <th scope="col"><?= lang('Diocesan.liturgicalColSort') ?></th>
                        <th scope="col"><?= lang('Diocesan.liturgicalColIcons') ?></th>
                        <th scope="col"><?= lang('Diocesan.liturgicalColScope') ?></th>
                        <th scope="col"><?= lang('Diocesan.liturgicalColStatus') ?></th>
                        <th scope="col" class="text-end"><?= lang('App.colActions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($entries)): ?>
                        <?php foreach ($entries as $entry): ?>
                            <?php
                            $sourceTier = (string) ($entry['source_tier'] ?? (!empty($entry['parish_id']) ? 'parish' : 'system'));
                            $isEditable = (bool) ($entry['can_edit'] ?? false);
                            $rawColor = strtolower((string) ($entry['liturgical_color'] ?? ''));
                            $colorMeta = is_array($colorMap[$rawColor] ?? null) ? $colorMap[$rawColor] : [];
                            $colorLabelKey = (string) ($colorMeta['labelKey'] ?? '');
                            $colorSwatchLabelKey = (string) ($colorMeta['swatchLabelKey'] ?? '');
                            $colorLabel = $colorLabelKey !== '' ? (string) lang($colorLabelKey) : ucfirst($rawColor);
                            $swatch = (string) ($colorMeta['swatch'] ?? '#6c757d');
                            $colorText = (string) ($colorMeta['text'] ?? '#ffffff');
                            $swatchLabel = $colorSwatchLabelKey !== '' ? (string) lang($colorSwatchLabelKey) : $colorLabel;
                            $seasonIconKey = trim((string) ($entry['season_icon'] ?? ''));
                            $observanceIconKey = trim((string) ($entry['observance_icon'] ?? ''));
                            $seasonIconClass = (string) ($iconMap[$seasonIconKey] ?? $defaultIconClass);
                            $observanceIconClass = (string) ($iconMap[$observanceIconKey] ?? $defaultIconClass);
                            ?>
                            <tr>
                                <td>
                                    <strong><?= esc($entry['name']) ?></strong>
                                    <br><small class="text-muted"><?= esc($entry['code']) ?></small>
                                </td>
                                <td><?= esc($seasonLabels[$entry['season']] ?? $entry['season']) ?></td>
                                <td><?= esc($typeLabels[$entry['observance_type']] ?? ucfirst($entry['observance_type'])) ?></td>
                                <td>
                                    <span class="badge fw-normal border" title="<?= esc($swatchLabel, 'attr') ?>" style="background-color: <?= esc($swatch, 'attr') ?>; color: <?= esc($colorText, 'attr') ?>; border-color: <?= esc($swatch, 'attr') ?> !important;">
                                        <?= esc($colorLabel) ?>
                                    </span>
                                </td>
                                <td><?= esc((string) $entry['sort_order']) ?></td>
                                <td>
                                    <small class="text-muted d-block">
                                        <?= lang('Diocesan.liturgicalIconSeasonPrefix') ?>:
                                        <?php if ($seasonIconKey !== ''): ?>
                                            <i class="bi <?= esc($seasonIconClass, 'attr') ?> me-1"></i><?= esc($seasonIconKey) ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </small>
                                    <small class="text-muted d-block">
                                        <?= lang('Diocesan.liturgicalIconObservancePrefix') ?>:
                                        <?php if ($observanceIconKey !== ''): ?>
                                            <i class="bi <?= esc($observanceIconClass, 'attr') ?> me-1"></i><?= esc($observanceIconKey) ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($sourceTier === 'province'): ?>
                                        <span class="badge border border-primary text-primary fw-normal"><?= lang('Diocesan.liturgicalScopeProvince') ?></span>
                                    <?php elseif ($sourceTier === 'diocese'): ?>
                                        <span class="badge border border-info text-info fw-normal"><?= lang('Diocesan.liturgicalScopeDiocese') ?></span>
                                    <?php elseif ($sourceTier === 'parish'): ?>
                                        <span class="badge border border-info text-info fw-normal"><?= lang('Diocesan.liturgicalScopeParish') ?></span>
                                    <?php else: ?>
                                        <span class="badge border border-secondary text-body fw-normal"><?= lang('Diocesan.liturgicalScopeSystem') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ((int) $entry['is_active'] === 1): ?>
                                        <span class="badge border border-success text-success fw-normal"><?= lang('App.statusActive') ?></span>
                                    <?php else: ?>
                                        <span class="badge border border-secondary text-secondary fw-normal"><?= lang('App.statusInactive') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if (!empty($can_manage_liturgical_source) && $isEditable): ?>
                                        <div class="d-inline-flex flex-nowrap gap-1 align-items-center">
                                            <a href="<?= base_url(($liturgical_write_base ?? 'system/liturgical-source') . '/edit/' . $entry['id']) ?>" class="btn btn-sm btn-outline-primary" aria-label="<?= esc(lang('App.edit')) ?>">
                                                <i class="bi bi-pencil" aria-hidden="true"></i>
                                            </a>
                                            <form action="<?= base_url(($liturgical_write_base ?? 'system/liturgical-source') . '/delete/' . $entry['id']) ?>" method="post" class="d-inline"
                                                data-confirm-title="<?= esc(lang('App.confirm'), 'attr') ?>"
                                                data-confirm-body="<?= esc(lang('Diocesan.liturgicalDeleteEntryConfirm'), 'attr') ?>">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="<?= esc(lang('App.delete')) ?>">
                                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        </div>
                                    <?php elseif ($sourceTier === 'system'): ?>
                                        <small class="text-muted"><?= lang('Diocesan.liturgicalSystemTemplateLocked') ?></small>
                                    <?php elseif (empty($can_manage_liturgical_source)): ?>
                                        <small class="text-muted"><?= lang('App.view') ?></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4"><?= lang('Diocesan.liturgicalNoEntriesFound') ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection() ?>