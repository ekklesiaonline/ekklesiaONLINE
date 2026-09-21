<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php
/** @var array<int, array<string, mixed>> $devices */
$devices = $devices ?? [];
$currentDeviceId = $currentDeviceId ?? null;
$hasOthers = false;
foreach ($devices as $d) {
    if ((int) $d['id'] !== $currentDeviceId) {
        $hasOthers = true;
        break;
    }
}
?>

<div class="d-flex justify-content-start align-items-center mb-3 gap-2">
    <a href="<?= base_url('profile') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i><?= lang('App.back') ?>
    </a>
    <?php if ($hasOthers): ?>
        <form method="post" action="<?= base_url('session/devices/revoke-others') ?>"
              onsubmit="return confirm('<?= esc(lang('Auth.confirmRevokeOtherDevices')) ?>');">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-shield-x me-1" aria-hidden="true"></i><?= lang('Auth.revokeOtherDevices') ?>
            </button>
        </form>
    <?php endif; ?>
    <?php if ($currentDeviceId !== null): ?>
        <form method="post" action="<?= base_url('session/devices/forget') ?>"
              onsubmit="return confirm('<?= esc(lang('Auth.confirmForgetDevice')) ?>');">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-x-circle me-1" aria-hidden="true"></i><?= lang('Auth.forgetThisDevice') ?>
            </button>
        </form>
    <?php endif; ?>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <p class="text-muted"><?= lang('Auth.trustedDevicesIntro') ?></p>

        <?php if (empty($devices)): ?>
            <p class="text-muted mb-0"><?= lang('Auth.noTrustedDevices') ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th scope="col"><?= lang('Auth.deviceColumn') ?></th>
                            <th scope="col"><?= lang('Auth.lastIpColumn') ?></th>
                            <th scope="col"><?= lang('Auth.firstSeenColumn') ?></th>
                            <th scope="col"><?= lang('Auth.lastSeenColumn') ?></th>
                            <th scope="col"><?= lang('Auth.expiresColumn') ?></th>
                            <th scope="col"><?= lang('App.colActions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($devices as $device): ?>
                            <?php $isCurrent = $currentDeviceId !== null && (int) $device['id'] === $currentDeviceId; ?>
                            <tr>
                                <td>
                                    <?= esc($device['display_name'] ?? lang('Auth.unknownDevice')) ?>
                                    <?php if ($isCurrent): ?>
                                        <span class="border border-success text-success fw-normal badge ms-1">
                                            <?= lang('Auth.thisDevice') ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= esc($device['last_ip_address'] ?? '—') ?></td>
                                <td><?= esc($device['first_seen_at'] ?? '—') ?></td>
                                <td><?= esc($device['last_seen_at'] ?? '—') ?></td>
                                <td><?= esc($device['expires_at'] ?? '—') ?></td>
                                <td>
                                    <?php if (! $isCurrent): ?>
                                        <form method="post"
                                              action="<?= base_url('session/devices/revoke/' . (int) $device['id']) ?>"
                                              onsubmit="return confirm('<?= esc(lang('Auth.confirmRevokeDevice')) ?>');">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                                <?= lang('Auth.revokeDevice') ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
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
