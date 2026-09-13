<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<h4 class="mb-4">New Vestry Meeting</h4>

<div class="card">
    <div class="card-body">
        <form action="<?= base_url('vestry/store') ?>" method="post">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Meeting Date *</label>
                <input type="date" name="meeting_date" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Meeting Type *</label>
                <select name="meeting_type" class="form-select" required>
                    <option value="regular"><?= lang('Dashboard.vestryMeetingTypeRegular') ?></option>
                    <option value="special"><?= lang('Dashboard.vestryMeetingTypeSpecial') ?></option>
                    <option value="annual"><?= lang('Dashboard.vestryMeetingTypeAnnual') ?></option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Minutes</label>
                <textarea name="minutes_text" class="form-control" rows="8"></textarea>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Create Meeting</button>
                <a href="<?= base_url('/parish/governance') ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
