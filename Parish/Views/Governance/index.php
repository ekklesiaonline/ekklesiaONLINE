<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php if (empty($has_vestry_tables)): ?>
<div class="alert alert-info">
    <i class="bi bi-info-circle" aria-hidden="true"></i> <?= lang('Dashboard.vestryTablesMissing') ?> <code>php spark migrate</code>.
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4><?= lang('Dashboard.vestryMeetings') ?></h4>
    <?php if (!empty($can_manage_meetings) && !empty($has_vestry_tables)): ?>
        <a href="<?= base_url('vestry/create') ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle" aria-hidden="true"></i> <?= lang('Dashboard.vestryNewMeeting') ?>
        </a>
    <?php endif; ?>
</div>

<!-- Open motions -->
<?php if (!empty($open_motions)): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-lightning-fill text-warning" aria-hidden="true"></i> <?= lang('Dashboard.vestryOpenMotions') ?></h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm">
                <thead><tr><th scope="col"><?= lang('Dashboard.vestryColMotion') ?></th><th scope="col"><?= lang('Dashboard.vestryMeetingDate') ?></th><th scope="col"><?= lang('App.colStatus') ?></th><th scope="col"></th></tr></thead>
                <tbody>
                    <?php foreach ($open_motions as $motion): ?>
                        <tr>
                            <td><?= esc($motion['title'] ?? '') ?></td>
                            <td><?= esc($motion['meeting_date'] ?? '-') ?></td>
                            <td><span class="badge bg-warning"><?= lang('Dashboard.motionStatusOpen') ?></span></td>
                            <td>
                                <?php if (!empty($can_vote)): ?>
                                    <a href="<?= base_url('vestry/motion/' . (int) ($motion['id'] ?? 0)) ?>" class="btn btn-sm btn-outline-primary"><?= lang('Dashboard.vestryVoteBtn') ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Recent meetings -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-calendar-week" aria-hidden="true"></i> <?= lang('Dashboard.vestryRecentMeetings') ?></h5>
    </div>
    <div class="card-body">
        <?php if (!empty($recent_meetings)): ?>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th scope="col"><?= lang('App.colDate') ?></th><th scope="col"><?= lang('App.colType') ?></th><th scope="col"></th></tr></thead>
                    <tbody>
                        <?php foreach ($recent_meetings as $meeting): ?>
                            <tr>
                                <td><?= esc($meeting['meeting_date'] ?? '-') ?></td>
                                <td>
                                    <?php $typeKey = 'vestryMeetingType' . ucfirst($meeting['meeting_type'] ?? 'regular'); ?>
                                    <span class="badge bg-secondary"><?= lang('Dashboard.' . $typeKey) ?></span>
                                </td>
                                <td>
                                    <a href="<?= base_url('vestry/view/' . (int) ($meeting['id'] ?? 0)) ?>" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-eye" aria-hidden="true"></i> <?= lang('App.view') ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted small mb-0"><?= lang('Dashboard.vestryNoMeetings') ?></p>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
