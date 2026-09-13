<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="container py-4">
    <div class="alert alert-warning" role="alert">
        <h4 class="alert-heading mb-2"><?= lang('Dashboard.noParishAssigned') ?></h4>
        <p class="mb-0"><?= lang('Dashboard.noParishBody') ?></p>
    </div>
</div>
<?= $this->endSection() ?>