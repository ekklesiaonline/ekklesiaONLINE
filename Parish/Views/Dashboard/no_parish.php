<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="alert alert-warning" role="alert">
    <i class="bi bi-exclamation-triangle" aria-hidden="true"></i> <?= lang('App.errorNotFound') ?>
</div>
<?= $this->endSection() ?>
