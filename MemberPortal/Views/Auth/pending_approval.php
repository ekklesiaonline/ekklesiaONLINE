<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?><?= lang('Auth.pendingTitle') ?><?= $this->endSection() ?>

<?= $this->section('main') ?>
<div class="container d-flex justify-content-center p-5">
    <div class="card col-12 col-md-6 shadow-sm">
        <div class="card-body text-center">
            <h4 class="mb-3"><?= lang('Auth.pendingHeading') ?></h4>
            <p class="text-muted mb-4">
                <?= lang('Auth.pendingBody') ?>
            </p>
            <a href="<?= base_url('logout') ?>" class="btn btn-outline-secondary"><?= lang('Auth.pendingLogout') ?></a>
        </div>
    </div>
</div>
<?= $this->endSection() ?>