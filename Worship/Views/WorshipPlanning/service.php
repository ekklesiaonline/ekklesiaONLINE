<?= $this->extend('Worship\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
$existingRows = $items ?? [];
$oldTypes = old('item_type');
$oldTitles = old('title');
$oldLeaders = old('leader_name');
$oldNotes = old('notes');
$oldSongIds = old('song_id');

if (is_array($oldTypes) || is_array($oldTitles) || is_array($oldLeaders) || is_array($oldNotes) || is_array($oldSongIds)) {
    $existingRows = [];
    $oldTypes = is_array($oldTypes) ? $oldTypes : [];
    $oldTitles = is_array($oldTitles) ? $oldTitles : [];
    $oldLeaders = is_array($oldLeaders) ? $oldLeaders : [];
    $oldNotes = is_array($oldNotes) ? $oldNotes : [];
    $oldSongIds = is_array($oldSongIds) ? $oldSongIds : [];
    $maxRows = max(count($oldTypes), count($oldTitles), count($oldLeaders), count($oldNotes), count($oldSongIds));
    for ($i = 0; $i < $maxRows; $i++) {
        $existingRows[] = [
            'item_type' => $oldTypes[$i] ?? 'song',
            'title' => $oldTitles[$i] ?? '',
            'leader_name' => $oldLeaders[$i] ?? '',
            'notes' => $oldNotes[$i] ?? '',
            'song_id' => $oldSongIds[$i] ?? '',
        ];
    }
}

if (empty($existingRows)) {
    $existingRows[] = [
        'item_type' => 'song',
        'title' => '',
        'leader_name' => '',
        'notes' => '',
        'song_id' => '',
    ];
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0"><?= lang('Worship.serviceWorshipPlan') ?></h4>
        <small class="text-muted">
            <?= esc(liturgy_format_long_date((string) ($service['service_date'] ?? ''))) ?>
            <?php if (!empty($service['liturgical_day'])): ?>
                | <?= esc(liturgical_day_label((string) ($service['liturgical_day'] ?? ''))) ?>
            <?php endif; ?>
        </small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('worship-planning/print/' . (int) $service['id']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary">
            <i class="bi bi-printer" aria-hidden="true"></i> <?= lang('Worship.printRunSheet') ?>
        </a>
        <a href="<?= base_url('worship-planning') ?>" class="btn btn-outline-secondary"><?= lang('Worship.back') ?></a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (!empty(session('errors'))): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ((array) session('errors') as $error): ?>
                        <li><?= esc($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= base_url('worship-planning/service/' . (int) $service['id'] . '/save') ?>">
            <?= csrf_field() ?>

            <div id="plan-items" class="d-flex flex-column gap-2">
                <?php foreach ($existingRows as $row): ?>
                    <div class="row g-2 align-items-start plan-row">
                        <div class="col-md-2">
                            <select name="item_type[]" class="form-select form-select-sm item-type">
                                <?php $itemType = (string) ($row['item_type'] ?? 'song'); ?>
                                <option value="song" <?= $itemType === 'song' ? 'selected' : '' ?>><?= lang('Worship.itemTypeSong') ?></option>
                                <option value="reading" <?= $itemType === 'reading' ? 'selected' : '' ?>><?= lang('Worship.itemTypeReading') ?></option>
                                <option value="prayer" <?= $itemType === 'prayer' ? 'selected' : '' ?>><?= lang('Worship.itemTypePrayer') ?></option>
                                <option value="sermon" <?= $itemType === 'sermon' ? 'selected' : '' ?>><?= lang('Worship.itemTypeSermon') ?></option>
                                <option value="announcement" <?= $itemType === 'announcement' ? 'selected' : '' ?>><?= lang('Worship.itemTypeAnnouncement') ?></option>
                                <option value="other" <?= $itemType === 'other' ? 'selected' : '' ?>><?= lang('Worship.itemTypeOther') ?></option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="song_id[]" class="form-select form-select-sm song-select <?= $itemType === 'song' ? '' : 'd-none' ?>">
                                <option value=""><?= lang('Worship.selectSong') ?></option>
                                <?php foreach (($songs ?? []) as $song): ?>
                                    <?php $songIdValue = (string) ((int) ($song['id'] ?? 0)); ?>
                                    <option value="<?= $songIdValue ?>" <?= (string) ($row['song_id'] ?? '') === $songIdValue ? 'selected' : '' ?>>
                                        <?= esc((string) ($song['title'] ?? '')) ?><?= !empty($song['artist']) ? ' - ' . esc((string) $song['artist']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="title[]" class="form-control form-control-sm title-input mt-1" placeholder="<?= lang('Worship.title') ?>" value="<?= esc((string) ($row['title'] ?? ($row['song_title'] ?? ''))) ?>">
                        </div>
                        <div class="col-md-2">
                            <input type="text" name="leader_name[]" class="form-control form-control-sm" placeholder="<?= lang('Worship.leader') ?>" value="<?= esc((string) ($row['leader_name'] ?? '')) ?>">
                        </div>
                        <div class="col-md-4">
                            <input type="text" name="notes[]" class="form-control form-control-sm" placeholder="<?= lang('Worship.notes') ?>" value="<?= esc((string) ($row['notes'] ?? '')) ?>">
                        </div>
                        <div class="col-md-1 d-grid">
                            <button type="button" class="btn btn-outline-danger btn-sm remove-row">&times;</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-3 d-flex gap-2">
                <button type="button" id="add-row" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-plus-circle" aria-hidden="true"></i> <?= lang('Worship.addItem') ?>
                </button>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-save" aria-hidden="true"></i> <?= lang('Worship.savePlan') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<template id="plan-row-template">
    <div class="row g-2 align-items-start plan-row">
        <div class="col-md-2">
            <select name="item_type[]" class="form-select form-select-sm item-type">
                <option value="song" selected><?= lang('Worship.itemTypeSong') ?></option>
                <option value="reading"><?= lang('Worship.itemTypeReading') ?></option>
                <option value="prayer"><?= lang('Worship.itemTypePrayer') ?></option>
                <option value="sermon"><?= lang('Worship.itemTypeSermon') ?></option>
                <option value="announcement"><?= lang('Worship.itemTypeAnnouncement') ?></option>
                <option value="other"><?= lang('Worship.itemTypeOther') ?></option>
            </select>
        </div>
        <div class="col-md-3">
            <select name="song_id[]" class="form-select form-select-sm song-select">
                <option value=""><?= lang('Worship.selectSong') ?></option>
                <?php foreach (($songs ?? []) as $song): ?>
                    <option value="<?= (int) ($song['id'] ?? 0) ?>"><?= esc((string) ($song['title'] ?? '')) ?><?= !empty($song['artist']) ? ' - ' . esc((string) $song['artist']) : '' ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="title[]" class="form-control form-control-sm title-input mt-1" placeholder="<?= lang('Worship.title') ?>">
        </div>
        <div class="col-md-2">
            <input type="text" name="leader_name[]" class="form-control form-control-sm" placeholder="<?= lang('Worship.leader') ?>">
        </div>
        <div class="col-md-4">
            <input type="text" name="notes[]" class="form-control form-control-sm" placeholder="<?= lang('Worship.notes') ?>">
        </div>
        <div class="col-md-1 d-grid">
            <button type="button" class="btn btn-outline-danger btn-sm remove-row">&times;</button>
        </div>
    </div>
</template>

<script>
    (function() {
        const container = document.getElementById('plan-items');
        const template = document.getElementById('plan-row-template');
        const addBtn = document.getElementById('add-row');

        function updateRowVisibility(row) {
            const typeSelect = row.querySelector('.item-type');
            const songSelect = row.querySelector('.song-select');
            if (!typeSelect || !songSelect) {
                return;
            }

            const isSong = typeSelect.value === 'song';
            songSelect.classList.toggle('d-none', !isSong);
            if (!isSong) {
                songSelect.value = '';
            }
        }

        container.addEventListener('click', function(event) {
            const removeBtn = event.target.closest('.remove-row');
            if (!removeBtn) {
                return;
            }

            const rows = container.querySelectorAll('.plan-row');
            if (rows.length <= 1) {
                return;
            }

            const row = removeBtn.closest('.plan-row');
            if (row) {
                row.remove();
            }
        });

        container.addEventListener('change', function(event) {
            const typeSelect = event.target.closest('.item-type');
            if (!typeSelect) {
                return;
            }
            const row = typeSelect.closest('.plan-row');
            if (row) {
                updateRowVisibility(row);
            }
        });

        addBtn.addEventListener('click', function() {
            const clone = template.content.firstElementChild.cloneNode(true);
            container.appendChild(clone);
        });

        container.querySelectorAll('.plan-row').forEach(updateRowVisibility);
    })();
</script>

<?= $this->endSection() ?>