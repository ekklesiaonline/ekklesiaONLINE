<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="card">
    <div class="card-header"><strong><?= lang('Events.pageTitleEditEvent') ?></strong></div>
    <div class="card-body">
        <form method="post" action="<?= base_url('system/events/update/' . (int) ($event['id'] ?? 0)) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <?= $this->include('App\Views\System\Events/_form') ?>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
