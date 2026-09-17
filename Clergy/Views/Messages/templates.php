<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
$channelLabels = [
    'email'        => 'Messages.channelEmail',
    'sms'          => 'Messages.channelSms',
    'notification' => 'Messages.channelNotification',
    'any'          => 'Messages.channelAny',
];
?>

<div class="d-flex justify-content-start align-items-center mb-3 gap-2">
    <a href="<?= base_url('messages') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Messages.backToMessages') ?>
    </a>
    <a href="<?= base_url('messages/templates/create') ?>" class="btn btn-outline-primary">
        <i class="bi bi-plus-circle" aria-hidden="true"></i> <?= lang('Messages.templateCreate') ?>
    </a>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($templates)): ?>
            <p class="text-muted mb-0"><?= lang('Messages.noTemplates') ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th scope="col"><?= lang('Messages.templateName') ?></th>
                            <th scope="col"><?= lang('Messages.templateChannel') ?></th>
                            <th scope="col"><?= lang('Messages.subject') ?></th>
                            <th scope="col"><?= lang('App.colStatus') ?></th>
                            <th scope="col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($templates as $template): ?>
                            <?php
                            $isActive = (int) ($template['is_active'] ?? 0) === 1;
                            $statusColor = $isActive ? 'success' : 'secondary';
                            $channelKey = $channelLabels[$template['channel'] ?? 'any'] ?? 'Messages.channelAny';
                            ?>
                            <tr>
                                <th scope="row" class="fw-normal"><?= esc($template['name']) ?></th>
                                <td><?= lang($channelKey) ?></td>
                                <td><?= esc($template['subject'] ?? '—') ?></td>
                                <td><span class="badge border border-<?= $statusColor ?> text-<?= $statusColor ?> fw-normal"><?= lang($isActive ? 'Groups.active' : 'Groups.inactive') ?></span></td>
                                <td class="text-end">
                                    <a href="<?= base_url('messages/templates/edit/' . (int) $template['id']) ?>" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-pencil" aria-hidden="true"></i> <?= lang('App.edit') ?>
                                    </a>
                                    <form method="post" action="<?= base_url('messages/templates/delete/' . (int) $template['id']) ?>" class="d-inline" onsubmit="return confirm('<?= esc(lang('Messages.confirmDeleteTemplate'), 'js') ?>');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="<?= esc(lang('App.delete'), 'attr') ?>">
                                            <i class="bi bi-trash" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
