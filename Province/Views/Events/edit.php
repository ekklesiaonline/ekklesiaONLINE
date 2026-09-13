<?= $this->extend('Province\Views\layouts/main') ?>
<?= $this->section('content') ?>
<div class="card">
    <div class="card-header"><strong><?= lang('App.navProvinceCalendarEdit') ?></strong></div>
    <div class="card-body">
        <form method="post" action="<?= base_url('province/events/update/' . (int) ($event['id'] ?? 0)) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <?= $this->include('Province\Views\Events/_form') ?>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
