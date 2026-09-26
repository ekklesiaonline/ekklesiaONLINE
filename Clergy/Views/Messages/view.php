<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
$channelLabels = [
    'email'        => 'Messages.channelEmail',
    'sms'          => 'Messages.channelSms',
    'notification' => 'Messages.channelNotification',
];
$campaignStatusMeta = [
    'draft'     => ['Messages.statusDraft', 'secondary'],
    'sending'   => ['Messages.statusSending', 'info'],
    'sent'      => ['Messages.statusSent', 'success'],
    'cancelled' => ['Messages.statusCancelled', 'dark'],
];
$recipientStatusMeta = [
    'pending' => ['Messages.recipientPending', 'secondary'],
    'queued'  => ['Messages.recipientQueued', 'info'],
    'sent'    => ['Messages.recipientSent', 'success'],
    'skipped' => ['Messages.recipientSkipped', 'warning'],
    'failed'  => ['Messages.recipientFailed', 'danger'],
];
$skipReasonLabels = [
    'opted_out'  => 'Messages.skipOptedOut',
    'no_address' => 'Messages.skipNoAddress',
    'no_user'    => 'Messages.skipNoUser',
];

$status = (string) ($campaign['status'] ?? 'draft');
[$statusKey, $statusColor] = $campaignStatusMeta[$status] ?? $campaignStatusMeta['draft'];
$channelKey = $channelLabels[$campaign['channel'] ?? ''] ?? null;
$isDraft = $status === 'draft';
?>

<div class="d-flex justify-content-start align-items-center mb-3 gap-2">
    <a href="<?= base_url('messages') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Messages.backToMessages') ?>
    </a>
    <?php if ($isDraft && !empty($canSend)): ?>
        <a href="<?= base_url('messages/preview/' . (int) $campaign['id']) ?>" class="btn btn-outline-primary">
            <i class="bi bi-send" aria-hidden="true"></i> <?= lang('Messages.preview') ?>
        </a>
    <?php endif; ?>
</div>

<div class="row mb-3">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="text-muted"><?= lang('Messages.colRecipients') ?></h6>
                <h5 class="mb-0"><?= (int) ($campaign['total_recipients'] ?? 0) ?></h5>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="text-muted"><?= lang('Messages.colSent') ?></h6>
                <h5 class="mb-0"><?= (int) ($campaign['sent_count'] ?? 0) ?></h5>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="text-muted"><?= lang('Messages.recipientSkipped') ?></h6>
                <h5 class="mb-0"><?= (int) ($campaign['skipped_count'] ?? 0) ?></h5>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="text-muted"><?= lang('Messages.recipientFailed') ?></h6>
                <h5 class="mb-0"><?= (int) ($campaign['failed_count'] ?? 0) ?></h5>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="row">
            <div class="col-md-2">
                <h6 class="text-muted"><?= lang('Messages.channel') ?></h6>
                <p class="mb-0"><?= $channelKey !== null ? esc(lang($channelKey)) : esc((string) $campaign['channel']) ?></p>
            </div>
            <div class="col-md-2">
                <h6 class="text-muted"><?= lang('App.colStatus') ?></h6>
                <p class="mb-0"><span class="badge border border-<?= $statusColor ?> text-<?= $statusColor ?> fw-normal"><?= lang($statusKey) ?></span></p>
            </div>
            <div class="col-md-3">
                <h6 class="text-muted"><?= lang('Messages.subject') ?></h6>
                <p class="mb-0"><?= esc($campaign['subject'] ?? '—') ?></p>
            </div>
            <div class="col-md-5">
                <h6 class="text-muted"><?= lang('Messages.body') ?></h6>
                <p class="mb-0 text-break"><?= esc($campaign['body']) ?></p>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h6 class="mb-0"><?= lang('Messages.colRecipients') ?></h6></div>
    <div class="card-body">
        <?php if (empty($recipients)): ?>
            <p class="text-muted mb-0"><?= lang('Messages.errorEmptyAudience') ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th scope="col"><?= lang('Messages.recipientName') ?></th>
                            <th scope="col"><?= lang('Messages.recipientAddress') ?></th>
                            <th scope="col"><?= lang('Messages.recipientStatus') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recipients as $recipient): ?>
                            <?php
                            $rStatus = (string) ($recipient['status'] ?? 'pending');
                            [$rKey, $rColor] = $recipientStatusMeta[$rStatus] ?? $recipientStatusMeta['pending'];
                            $skipKey = $skipReasonLabels[$recipient['skip_reason'] ?? ''] ?? null;
                            ?>
                            <tr>
                                <th scope="row" class="fw-normal"><?= esc($recipient['name'] ?? '—') ?></th>
                                <td><?= esc($recipient['address'] ?? '—') ?></td>
                                <td>
                                    <span class="badge border border-<?= $rColor ?> text-<?= $rColor ?> fw-normal"><?= lang($rKey) ?></span>
                                    <?php if ($skipKey !== null): ?>
                                        <span class="text-muted small ms-1"><?= lang($skipKey) ?></span>
                                    <?php endif; ?>
                                    <?php if ($rStatus === 'failed' && !empty($recipient['error'])): ?>
                                        <span class="text-muted small ms-1" title="<?= esc((string) $recipient['error'], 'attr') ?>"><?= esc(mb_strimwidth((string) $recipient['error'], 0, 60, '…')) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (isset($pager)): ?>
                <div class="mt-3"><?= $pager->links() ?></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
