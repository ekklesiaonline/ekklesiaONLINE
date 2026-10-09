<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php
$dioceses = $dioceses ?? [];
$ordinaryByDiocese = $ordinary_by_diocese ?? [];
$candidatesByDiocese = $candidates_by_diocese ?? [];
$today = date('Y-m-d');
$openModal = (string) session('open_modal');
?>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col"><?= lang('System.diocesanOrdinaryColDiocese') ?></th>
                        <th scope="col"><?= lang('System.diocesanOrdinaryColProvince') ?></th>
                        <th scope="col"><?= lang('System.diocesanOrdinaryColOrdinary') ?></th>
                        <th scope="col"><?= lang('System.diocesanOrdinaryColSince') ?></th>
                        <th scope="col"><?= lang('System.diocesanOrdinaryColActions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($dioceses === []): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-3"><?= lang('System.diocesanOrdinariesEmpty') ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($dioceses as $diocese): ?>
                            <?php
                            $dioceseId = (int) $diocese['id'];
                            $ordinary = $ordinaryByDiocese[$dioceseId] ?? null;
                            $candidates = $candidatesByDiocese[$dioceseId] ?? [];
                            $ordinaryEffective = $ordinary !== null && (bool) ($ordinary['effective'] ?? false);
                            ?>
                            <tr>
                                <td><?= esc((string) $diocese['diocese_name']) ?></td>
                                <td><?= esc((string) ($diocese['province_name'] ?? '')) ?></td>
                                <td>
                                    <?php if ($ordinary !== null): ?>
                                        <?= esc(trim((string) ($ordinary['first_name'] ?? '') . ' ' . (string) ($ordinary['last_name'] ?? ''))) ?>
                                        <?php if ($ordinaryEffective): ?>
                                            <span class="badge border border-success text-success fw-normal ms-1">
                                                <?= lang('System.diocesanOrdinaryInstalledBadge') ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge border border-danger text-danger fw-normal ms-1">
                                                <?= lang('System.diocesanOrdinaryNotEffective') ?>
                                            </span>
                                            <div class="small text-muted">
                                                <?= lang('System.diocesanOrdinaryNotEffectiveHint') ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge border border-warning text-warning fw-normal">
                                            <?= lang('System.diocesanOrdinaryVacant') ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($ordinary !== null): ?>
                                        <?= esc((string) $ordinary['started_at']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($ordinary !== null): ?>
                                        <button type="button" class="btn btn-outline-danger btn-sm"
                                            data-bs-toggle="modal" data-bs-target="#endOfficeModal<?= $dioceseId ?>">
                                            <?= lang('System.diocesanOrdinaryEndOffice') ?>
                                        </button>
                                    <?php else: ?>
                                        <?php if ($candidates === []): ?>
                                            <button type="button" class="btn btn-outline-primary btn-sm" disabled>
                                                <?= lang('System.installOrdinary') ?>
                                            </button>
                                            <div class="form-text"><?= lang('System.diocesanOrdinaryAccountHint') ?></div>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-outline-primary btn-sm"
                                                data-bs-toggle="modal" data-bs-target="#installOrdinaryModal<?= $dioceseId ?>">
                                                <?= lang('System.installOrdinary') ?>
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('modals') ?>

<?php foreach ($dioceses as $diocese): ?>
    <?php
    $dioceseId = (int) $diocese['id'];
    $ordinary = $ordinaryByDiocese[$dioceseId] ?? null;
    $candidates = $candidatesByDiocese[$dioceseId] ?? [];
    $installModalId = 'installOrdinaryModal' . $dioceseId;
    $restoreInstallModal = $openModal === $installModalId;
    $oldUserId = $restoreInstallModal ? (string) old('user_id', '') : '';
    if ($ordinary !== null || $candidates === []) {
        continue;
    }
    ?>
    <div class="modal fade" id="installOrdinaryModal<?= $dioceseId ?>" tabindex="-1"
        aria-labelledby="installOrdinaryModalLabel<?= $dioceseId ?>" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="<?= base_url('system/diocesan-ordinaries/install/' . $dioceseId) ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="modal-header">
                        <h5 class="modal-title" id="installOrdinaryModalLabel<?= $dioceseId ?>">
                            <?= lang('System.installDiocesanOrdinary') ?> &ndash; <?= esc((string) $diocese['diocese_name']) ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="ordinaryUser<?= $dioceseId ?>" class="form-label">
                                <?= lang('System.diocesanOrdinaryUser') ?>
                            </label>
                            <select name="user_id" id="ordinaryUser<?= $dioceseId ?>" class="form-select" required>
                                <option value="" <?= $oldUserId === '' ? 'selected' : '' ?>>
                                    <?= lang('System.diocesanOrdinarySelectUser') ?>
                                </option>
                                <?php foreach ($candidates as $candidate): ?>
                                    <?php $candidateName = trim((string) ($candidate['first_name'] ?? '') . ' ' . (string) ($candidate['last_name'] ?? '')); ?>
                                    <option value="<?= (int) $candidate['user_id'] ?>"
                                        <?= $oldUserId === (string) $candidate['user_id'] ? 'selected' : '' ?>>
                                        <?= esc($candidateName !== '' ? $candidateName : lang('System.diocesanOrdinaryUserNumber', [(int) $candidate['user_id']])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="ordinaryStartedOn<?= $dioceseId ?>" class="form-label">
                                <?= lang('System.diocesanOrdinaryOfficeStartedOn') ?>
                            </label>
                            <input type="date" name="office_started_on" id="ordinaryStartedOn<?= $dioceseId ?>"
                                class="form-control"
                                value="<?= esc((string) ($restoreInstallModal ? old('office_started_on', $today) : $today)) ?>"
                                max="<?= esc($today) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="ordinaryOrdainedOn<?= $dioceseId ?>" class="form-label">
                                <?= lang('System.diocesanOrdinaryOrdainedOn') ?>
                            </label>
                            <input type="date" name="ordained_on" id="ordinaryOrdainedOn<?= $dioceseId ?>" class="form-control"
                                value="<?= esc((string) ($restoreInstallModal ? old('ordained_on', '') : '')) ?>">
                        </div>
                        <div class="mb-3">
                            <label for="ordinaryEvidence<?= $dioceseId ?>" class="form-label">
                                <?= lang('System.diocesanOrdinaryEvidenceReference') ?>
                            </label>
                            <input type="text" name="evidence_reference" id="ordinaryEvidence<?= $dioceseId ?>"
                                class="form-control" maxlength="255"
                                value="<?= esc((string) ($restoreInstallModal ? old('evidence_reference', '') : '')) ?>"
                                required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <?= lang('App.cancel') ?>
                        </button>
                        <button type="submit" class="btn btn-outline-primary">
                            <?= lang('System.installOrdinary') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php foreach ($dioceses as $diocese): ?>
    <?php
    $dioceseId = (int) $diocese['id'];
    $ordinary = $ordinaryByDiocese[$dioceseId] ?? null;
    if ($ordinary === null) {
        continue;
    }
    $endModalId = 'endOfficeModal' . $dioceseId;
    $restoreEndModal = $openModal === $endModalId;
    ?>
    <div class="modal fade" id="<?= esc($endModalId) ?>" tabindex="-1"
        aria-labelledby="<?= esc($endModalId) ?>Label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="<?= base_url('system/diocesan-ordinaries/end/' . (int) $ordinary['position_id']) ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="modal-header">
                        <h5 class="modal-title" id="<?= esc($endModalId) ?>Label">
                            <?= lang('System.diocesanOrdinaryEndOfficeTitle') ?> &ndash; <?= esc((string) $diocese['diocese_name']) ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning" role="alert">
                            <?= lang('System.diocesanOrdinaryEndOfficeWarning') ?>
                        </div>
                        <div class="mb-3">
                            <label for="ordinaryEndedOn<?= $dioceseId ?>" class="form-label">
                                <?= lang('System.diocesanOrdinaryEndOn') ?>
                            </label>
                            <input type="date" name="ended_on" id="ordinaryEndedOn<?= $dioceseId ?>"
                                class="form-control"
                                value="<?= esc((string) ($restoreEndModal ? old('ended_on', $today) : $today)) ?>"
                                max="<?= esc($today) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="ordinaryEndReason<?= $dioceseId ?>" class="form-label">
                                <?= lang('System.diocesanOrdinaryEndReason') ?>
                            </label>
                            <textarea name="reason" id="ordinaryEndReason<?= $dioceseId ?>" class="form-control"
                                maxlength="255" required><?= esc((string) ($restoreEndModal ? old('reason', '') : '')) ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <?= lang('App.cancel') ?>
                        </button>
                        <button type="submit" class="btn btn-outline-danger">
                            <?= lang('System.diocesanOrdinaryEndOffice') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalId = <?= json_encode($openModal) ?>;
    if (!modalId) {
        return;
    }
    const modalElement = document.getElementById(modalId);
    if (modalElement) {
        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }
});
</script>
<?= $this->endSection() ?>
