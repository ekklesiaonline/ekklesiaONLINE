<?= $this->extend('Volunteer\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-start align-items-center mb-3">
    <a href="<?= base_url('volunteers') ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> <?= lang('App.back') ?>
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="<?= base_url('volunteers/storeTeam') ?>" method="post">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="name" class="form-label"><?= lang('Volunteer.teamName') ?> <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name" value="<?= old('name') ?>" required>
            </div>



            <div class="mb-3">
                <label for="description" class="form-label"><?= lang('Volunteer.description') ?></label>
                <textarea class="form-control" id="description" name="description" rows="4"><?= old('description') ?></textarea>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="<?= base_url('volunteers') ?>" class="btn btn-secondary"><?= lang('App.cancel') ?></a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> <?= lang('Volunteer.createTeam') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>