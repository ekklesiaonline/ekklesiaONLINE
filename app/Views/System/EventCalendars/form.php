<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php
$isEdit = !empty($calendar);
$title = $isEdit ? lang('App.edit') : lang('App.addNew');
?>
<div class="card">
    <div class="card-header"><strong><?= esc($title) ?></strong></div>
    <div class="card-body">
        <form method="post" action="<?= base_url($isEdit ? 'system/event-calendars/update/' . (int) $calendar['id'] : 'system/event-calendars/store') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="scope_level" value="national">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label"><?= lang('Events.calendarName') ?> *</label>
                    <input name="name" class="form-control" value="<?= esc((string) ($calendar['name'] ?? ''), 'attr') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= lang('Events.calendarColor') ?> *</label>
                    <input type="color" name="color_hex" class="form-control form-control-color" value="<?= esc((string) ($calendar['color_hex'] ?? '#0d6efd'), 'attr') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?= lang('App.colStatus') ?> *</label>
                    <select name="is_active" class="form-select">
                        <option value="1" <?= ((int) ($calendar['is_active'] ?? 1) === 1) ? 'selected' : '' ?>><?= lang('App.active') ?></option>
                        <option value="0" <?= ((int) ($calendar['is_active'] ?? 1) === 0) ? 'selected' : '' ?>><?= lang('App.inactive') ?></option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?= lang('App.sortOrder') ?> *</label>
                    <input type="number" name="sort_order" class="form-control" value="<?= (int) ($calendar['sort_order'] ?? 0) ?>" required>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary me-2" type="submit"><?= lang('App.save') ?></button>
                    <a class="btn btn-outline-secondary" href="<?= base_url('system/event-calendars') ?>"><?= lang('App.cancel') ?></a>
                </div>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
