<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4><?= lang('Dashboard.vestryMeetings') ?></h4>
    <?php if (!empty($can_manage)): ?>
        <a href="<?= base_url('vestry/create') ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle" aria-hidden="true"></i> <?= lang('Dashboard.vestryNewMeeting') ?>
        </a>
    <?php endif; ?>
</div>

<?php if (!empty($meetings)): ?>
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead>
                    <tr>
                        <th scope="col"><?= lang('App.colDate') ?></th>
                        <th scope="col"><?= lang('App.colType') ?></th>
                        <th scope="col"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($meetings as $meeting): ?>
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
    </div>
</div>
<?php else: ?>
<div class="alert alert-info"><?= lang('Dashboard.vestryNoMeetings') ?></div>
<?php endif; ?>

<?= $this->endSection() ?>
