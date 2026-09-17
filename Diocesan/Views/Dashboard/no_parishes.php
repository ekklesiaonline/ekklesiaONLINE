<?= $this->extend('Diocesan\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-center align-items-center" style="min-height: 60vh;">
    <div class="text-center text-muted">
        <i class="bi bi-building fs-1 d-block mb-3" aria-hidden="true"></i>
        <h4><?= lang('Dashboard.noParishesTitle') ?></h4>
        <p><?= lang('Dashboard.noParishesDesc') ?></p>
    </div>
</div>

<?= $this->endSection() ?>
