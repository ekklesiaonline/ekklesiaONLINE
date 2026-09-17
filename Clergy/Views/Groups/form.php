<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
$isEdit = !empty($group);
$action = $isEdit ? base_url('groups/update/' . (int) $group['id']) : base_url('groups/store');
$days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
?>

<div class="d-flex justify-content-start align-items-center mb-3 gap-2">
    <a href="<?= base_url('groups') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Groups.backToGroups') ?>
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form method="post" action="<?= $action ?>">
            <?= csrf_field() ?>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="name" class="form-label"><?= lang('Groups.name') ?> <span class="text-danger">*</span></label>
                    <input type="text" class="form-control<?= session('errors.name') ? ' is-invalid' : '' ?>" id="name" name="name" value="<?= esc(old('name', $group['name'] ?? '')) ?>" required maxlength="120">
                    <?php if (session('errors.name')): ?>
                        <div class="invalid-feedback"><?= esc(session('errors.name')) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="location" class="form-label"><?= lang('Groups.location') ?></label>
                    <input type="text" class="form-control" id="location" name="location" value="<?= esc(old('location', $group['location'] ?? '')) ?>" maxlength="255">
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="meeting_day" class="form-label"><?= lang('Groups.meetingDay') ?></label>
                    <select class="form-select" id="meeting_day" name="meeting_day">
                        <option value="">—</option>
                        <?php foreach ($days as $day): ?>
                            <option value="<?= $day ?>" <?= old('meeting_day', $group['meeting_day'] ?? '') === $day ? 'selected' : '' ?>><?= lang('Groups.day' . ucfirst($day)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="meeting_time" class="form-label"><?= lang('Groups.meetingTime') ?></label>
                    <input type="time" class="form-control<?= session('errors.meeting_time') ? ' is-invalid' : '' ?>" id="meeting_time" name="meeting_time" value="<?= esc(old('meeting_time', isset($group['meeting_time']) ? substr((string) $group['meeting_time'], 0, 5) : '')) ?>">
                    <?php if (session('errors.meeting_time')): ?>
                        <div class="invalid-feedback"><?= esc(session('errors.meeting_time')) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="capacity" class="form-label"><?= lang('Groups.capacity') ?></label>
                    <input type="number" min="1" class="form-control<?= session('errors.capacity') ? ' is-invalid' : '' ?>" id="capacity" name="capacity" value="<?= esc(old('capacity', $group['capacity'] ?? '')) ?>" placeholder="<?= esc(lang('Groups.capacityUnlimited'), 'attr') ?>">
                    <?php if (session('errors.capacity')): ?>
                        <div class="invalid-feedback"><?= esc(session('errors.capacity')) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label"><?= lang('Groups.description') ?></label>
                <textarea class="form-control" id="description" name="description" rows="3" maxlength="2000"><?= esc(old('description', $group['description'] ?? '')) ?></textarea>
            </div>

            <?php if ($isEdit): ?>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= (int) old('is_active', $group['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
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
