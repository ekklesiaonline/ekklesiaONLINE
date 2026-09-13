<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-md-6">
        <div class="card stat-card info">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-1"><?= lang('Standing.goodStandingMembers') ?></h6>
                    <h3 class="mb-0"><?= number_format((int) ($summary['good_standing'] ?? 0)) ?></h3>
                    <?php
                    $scopeKey = ($scope ?? 'church') === 'province'
                        ? lang('App.scopeProvince')
                        : (($scope ?? 'church') === 'diocese' ? lang('App.scopeDiocese') : lang('App.scopeParish'));
                    ?>
                    <small class="text-muted"><?= lang('Standing.scopeLabel') ?>: <?= esc($scopeKey) ?></small>
                </div>
                <div class="text-info fs-1">
                    <i class="bi bi-award"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card stat-card primary">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-1"><?= lang('Standing.reviewedMembers') ?></h6>
                    <h3 class="mb-0"><?= number_format((int) ($summary['total'] ?? 0)) ?></h3>
                    <small class="text-muted"><?= lang('Standing.yearLabel') ?>: <?= (int) ($selected_year ?? date('Y')) ?></small>
                </div>
                <div class="text-primary fs-1">
                    <i class="bi bi-clipboard-data"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-arrow-repeat"></i> <?= lang('Standing.refreshTitle') ?></h5>
    </div>
    <div class="card-body">
        <p class="text-muted mb-3">
            <?= lang('Standing.refreshDescription') ?>
        </p>
        <form method="post" action="<?= base_url('standing/refresh') ?>" class="row g-3 align-items-end"
            data-confirm-title="<?= esc(lang('App.confirm'), 'attr') ?>"
            data-confirm-body="<?= esc(lang('Standing.refreshDescription'), 'attr') ?>">
            <?= csrf_field() ?>
            <div class="col-md-3">
                <label for="year" class="form-label"><?= lang('Standing.yearLabel') ?></label>
                <input id="year" name="year" type="number" min="2000" class="form-control" value="<?= (int) ($selected_year ?? date('Y')) ?>" required>
            </div>
            <div class="col-md-9 text-md-end">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-arrow-repeat"></i> <?= lang('Standing.runRefresh') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>