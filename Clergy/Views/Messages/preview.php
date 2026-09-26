<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
$channelLabels = [
    'email'        => 'Messages.channelEmail',
    'sms'          => 'Messages.channelSms',
    'notification' => 'Messages.channelNotification',
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

$recipientCounts = $recipientCounts ?? [];
$deliverable = (int) ($recipientCounts['deliverable'] ?? 0);
$skippedOptedOut = (int) ($recipientCounts['opted_out'] ?? 0);
$skippedNoAddress = (int) ($recipientCounts['no_address'] ?? 0);
$skippedNoUser = (int) ($recipientCounts['no_user'] ?? 0);
$channelKey = $channelLabels[$campaign['channel'] ?? ''] ?? null;
?>

<div class="d-flex justify-content-start align-items-center mb-3 gap-2">
    <a href="<?= base_url('messages') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Messages.backToMessages') ?>
    </a>
    <form method="post" action="<?= base_url('messages/send/' . (int) $campaign['id']) ?>" class="d-inline" onsubmit="return confirm('<?= esc(lang('Messages.confirmSend'), 'js') ?>');">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-outline-primary" <?= $deliverable === 0 ? 'disabled' : '' ?>>
            <i class="bi bi-send" aria-hidden="true"></i> <?= lang('Messages.sendNow') ?>
        </button>
    </form>
    <form method="post" action="<?= base_url('messages/cancel/' . (int) $campaign['id']) ?>" class="d-inline" onsubmit="return confirm('<?= esc(lang('Messages.cancelDraft') . '?', 'js') ?>');">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-outline-danger">
            <i class="bi bi-x-circle" aria-hidden="true"></i> <?= lang('Messages.cancelDraft') ?>
        </button>
    </form>
</div>

<p class="text-muted"><?= lang('Messages.previewHelp') ?></p>

<div class="row mb-3">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="text-muted"><?= lang('Messages.deliverable') ?></h6>
                <h5 class="mb-0"><?= $deliverable ?></h5>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="text-muted"><?= lang('Messages.skippedOptedOut') ?></h6>
                <h5 class="mb-0"><?= $skippedOptedOut ?></h5>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="text-muted"><?= lang('Messages.skippedNoAddress') ?></h6>
                <h5 class="mb-0"><?= $skippedNoAddress ?></h5>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="text-muted"><?= lang('Messages.skippedNoUser') ?></h6>
                <h5 class="mb-0"><?= $skippedNoUser ?></h5>
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
            <div class="col-md-4">
                <h6 class="text-muted"><?= lang('Messages.subject') ?></h6>
                <p class="mb-0"><?= esc($campaign['subject'] ?? '—') ?></p>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted"><?= lang('Messages.body') ?></h6>
                <p class="mb-0 text-break"><?= esc($campaign['body']) ?></p>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h6 class="mb-0"><?= lang('Messages.colRecipients') ?></h6></div>
    <div class="card-body">
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
                    <?php foreach (($recipients ?? []) as $recipient): ?>
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
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (isset($pager)): ?>
            <div class="mt-3"><?= $pager->links() ?></div>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
