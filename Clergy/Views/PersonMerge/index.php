<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><?= lang('People.personMergeTitle') ?></h2>
    <a href="<?= base_url('/people') ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('People.backToPeople') ?>
    </a>
</div>

<?php if ($candidates === []): ?>
    <div class="alert alert-info">
        <?= lang('People.personMergeNoCandidates') ?>
    </div>
<?php else: ?>
    <p class="text-muted"><?= lang('People.personMergeCandidatesHint') ?></p>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th scope="col"><?= lang('People.canonicalPersonId') ?> A</th>
                    <th scope="col"><?= lang('People.canonicalPersonName') ?></th>
                    <th scope="col"><?= lang('People.canonicalPersonId') ?> B</th>
                    <th scope="col"><?= lang('People.canonicalPersonName') ?></th>
                    <th scope="col"><?= lang('People.personMergeReason') ?></th>
                    <th scope="col"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($candidates as $c): ?>
                    <?php $sourceId = (int) ($c['source']['id'] ?? 0); ?>
                    <?php $targetId = (int) ($c['target']['id'] ?? 0); ?>
                    <?php if ($sourceId <= 0 || $targetId <= 0): continue; endif; ?>
                    <tr>
                        <td><span class="badge bg-light text-dark border font-monospace">P<?= $sourceId ?></span></td>
                        <td><?= esc(($c['source']['first_name'] ?? '') . ' ' . ($c['source']['last_name'] ?? '')) ?></td>
                        <td><span class="badge bg-light text-dark border font-monospace">P<?= $targetId ?></span></td>
                        <td><?= esc(($c['target']['first_name'] ?? '') . ' ' . ($c['target']['last_name'] ?? '')) ?></td>
                        <td><?= esc($c['reason'] ?? '') ?></td>
                        <td>
                            <a href="<?= base_url('/people/merge/preview?source=' . $sourceId . '&target=' . $targetId) ?>" class="btn btn-sm btn-primary">
                                <?= lang('People.personMergeReview') ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>
