<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="card">
    <div class="card-body">
        <?php if (!empty(session('errors'))): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ((array) session('errors') as $error): ?>
                        <li><?= esc($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= base_url('events/update/' . (int) $event['id']) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <?= $this->include('Clergy\Views\Events/_form') ?>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?= lang('Events.update') ?></button>
                <a href="<?= base_url('events/view/' . (int) $event['id']) ?>" class="btn btn-outline-secondary"><?= lang('Events.cancel') ?></a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>