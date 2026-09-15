<?= $this->extend('Province\Views\layouts/main') ?>

<?= $this->section('content') ?>
<div class="container py-4">
    <div class="alert alert-warning" role="alert">
        <h4 class="alert-heading mb-2"><?= lang('Province.noParishesHeading') ?></h4>
        <p class="mb-0"><?= lang('Province.noParishesMessage') ?></p>
    </div>
</div>
<?= $this->endSection() ?>