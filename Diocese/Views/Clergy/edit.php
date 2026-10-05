<?= $this->extend('Diocese\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-start align-items-center mb-3">
    <a href="<?= base_url('diocese/clergy/' . (int) $clergy['id']) ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Clergy.backToDetail') ?>
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="<?= base_url('diocese/clergy/' . (int) $clergy['id'] . '/edit') ?>" method="post">
            <?= csrf_field() ?>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="first_name" class="form-label"><?= lang('Clergy.firstName') ?> <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="first_name" name="first_name" value="<?= esc(old('first_name', $clergy['first_name'] ?? '')) ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="middle_name" class="form-label"><?= lang('Clergy.middleName') ?></label>
                    <input type="text" class="form-control" id="middle_name" name="middle_name" value="<?= esc(old('middle_name', $clergy['middle_name'] ?? '')) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="last_name" class="form-label"><?= lang('Clergy.lastName') ?> <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="last_name" name="last_name" value="<?= esc(old('last_name', $clergy['last_name'] ?? '')) ?>" required>
                </div>
            </div>

            <p class="text-muted small"><?= lang('Clergy.canonicalFieldsReadOnlyHelp') ?></p>

            <button type="submit" class="btn btn-primary"><i class="bi bi-check2" aria-hidden="true"></i> <?= lang('App.save') ?></button>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
