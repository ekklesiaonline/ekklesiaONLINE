<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
$channelLabels = [
    'email'        => 'Messages.channelEmail',
    'sms'          => 'Messages.channelSms',
    'notification' => 'Messages.channelNotification',
];
$statusLabels = [
    'draft'     => ['Messages.statusDraft', 'secondary'],
    'sending'   => ['Messages.statusSending', 'info'],
    'sent'      => ['Messages.statusSent', 'success'],
    'cancelled' => ['Messages.statusCancelled', 'dark'],
];
?>

<div class="d-flex justify-content-start align-items-center mb-3 gap-2">
    <?php if (!empty($canSend)): ?>
        <a href="<?= base_url('messages/compose') ?>" class="btn btn-outline-primary">
            <i class="bi bi-pencil-square" aria-hidden="true"></i> <?= lang('Messages.compose') ?>
        </a>
        <a href="<?= base_url('messages/templates') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-layout-text-window" aria-hidden="true"></i> <?= lang('Messages.templates') ?>
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($campaigns)): ?>
            <p class="text-muted mb-0"><?= lang('Messages.noCampaigns') ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th scope="col"><?= lang('Messages.colDate') ?></th>
                            <th scope="col"><?= lang('Messages.colSubject') ?></th>
                            <th scope="col"><?= lang('Messages.colChannel') ?></th>
                            <th scope="col"><?= lang('Messages.colRecipients') ?></th>
                            <th scope="col"><?= lang('App.colStatus') ?></th>
                            <th scope="col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($campaigns as $campaign): ?>
                            <?php
                            $status = (string) ($campaign['status'] ?? 'draft');
                            [$statusKey, $statusColor] = $statusLabels[$status] ?? $statusLabels['draft'];
                            $channelKey = $channelLabels[$campaign['channel'] ?? ''] ?? null;
                            $title = trim((string) ($campaign['subject'] ?? ''));
                            if ($title === '') {
                                $title = mb_strimwidth((string) ($campaign['body'] ?? ''), 0, 60, '…');
                            }
                            $date = $campaign['sent_at'] ?? $campaign['created_at'] ?? null;
                            ?>
                            <tr>
                                <td><?= esc($date !== null ? substr((string) $date, 0, 10) : '—') ?></td>
                                <th scope="row" class="fw-normal"><?= esc($title) ?></th>
                                <td><?= $channelKey !== null ? esc(lang($channelKey)) : esc((string) $campaign['channel']) ?></td>
                                <td><?= (int) ($campaign['total_recipients'] ?? 0) ?></td>
                                <td><span class="badge border border-<?= $statusColor ?> text-<?= $statusColor ?> fw-normal"><?= lang($statusKey) ?></span></td>
                                <td class="text-end">
                                    <a href="<?= base_url('messages/view/' . (int) $campaign['id']) ?>" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-eye" aria-hidden="true"></i> <?= lang('App.view') ?>
                                    </a>
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
