<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
$locale = service('request')->getLocale() ?: config('App')->defaultLocale;

$formatDate = static function (?string $value) use ($locale): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return esc($value);
    }
    if (class_exists(\IntlDateFormatter::class)) {
        $fmt = new \IntlDateFormatter($locale, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE, date_default_timezone_get());
        $fmt->setPattern('MMM d, y');
        $out = $fmt->format($ts);
        if (is_string($out) && $out !== '') {
            return $out;
        }
    }
    return date('M j, Y', $ts);
};

$formatDateTime = static function (?string $value) use ($locale): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return esc($value);
    }
    if (class_exists(\IntlDateFormatter::class)) {
        $fmt = new \IntlDateFormatter($locale, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT, date_default_timezone_get());
        $out = $fmt->format($ts);
        if (is_string($out) && $out !== '') {
            return $out;
        }
    }
    return date('M j, Y g:i a', $ts);
};

$channelIcon = [
    'email' => 'bi-envelope',
    'text'  => 'bi-chat-dots',
    'sms'   => 'bi-chat-dots',
    'call'  => 'bi-telephone',
    'mail'  => 'bi-envelope-paper',
    'gift'  => 'bi-gift',
];

$statusConfig = [
    'pending' => ['badge' => 'warning', 'text-class' => 'text-warning', 'border' => 'border-warning', 'label' => lang('Visitors.statusPending')],
    'sent'    => ['badge' => 'success', 'text-class' => 'text-success', 'border' => 'border-success', 'label' => lang('Visitors.followupStatusSent')],
    'failed'  => ['badge' => 'danger',  'text-class' => 'text-danger',  'border' => 'border-danger',  'label' => lang('Visitors.followupStatusFailed')],
    'skipped' => ['badge' => 'secondary', 'text-class' => 'text-secondary', 'border' => 'border-secondary', 'label' => lang('Visitors.followupStatusSkipped')],
];

$pendingCount   = count(array_filter($timeline ?? [], fn($r) => $r['status'] === 'pending'));
$completedCount = count(array_filter($timeline ?? [], fn($r) => $r['status'] === 'sent'));

$canEdit = auth()->user()->can('visitor.edit');
?>

<!-- Top action row -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="<?= base_url('visitors/dashboard') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Visitors.backToVisitors') ?>
        </a>
        <a href="<?= base_url('visitors/view/' . (int) $visitor['id']) ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-person" aria-hidden="true"></i> <?= lang('Visitors.visitorLabel') ?>
        </a>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="fw-semibold text-body"><?= esc($visitor['first_name'] . ' ' . $visitor['last_name']) ?></span>
        <span id="topBadgePending" class="badge border border-warning text-warning fw-normal<?= $pendingCount === 0 ? ' d-none' : '' ?>"><?= $pendingCount ?> <?= lang('Visitors.statusPending') ?></span>
        <span id="topBadgeDone" class="badge border border-success text-success fw-normal<?= $completedCount === 0 ? ' d-none' : '' ?>"><?= $completedCount ?> <?= lang('Visitors.followupStatusSent') ?></span>
    </div>
</div>

<div class="row g-3">
    <!-- Visitor summary -->
    <div class="col-md-3">
        <div class="card mb-3">
            <div class="card-header py-2">
                <h6 class="mb-0"><i class="bi bi-person-circle" aria-hidden="true"></i> <?= lang('Visitors.visitorLabel') ?></h6>
            </div>
            <div class="card-body small">
                <p class="mb-1"><strong><?= esc($visitor['first_name'] . ' ' . $visitor['last_name']) ?></strong></p>
                <?php if (!empty($visitor['email'])): ?>
                    <p class="mb-1"><i class="bi bi-envelope text-muted" aria-hidden="true"></i> <?= esc($visitor['email']) ?></p>
                <?php endif; ?>
                <?php if (!empty($visitor['phone'])): ?>
                    <p class="mb-1"><i class="bi bi-telephone text-muted" aria-hidden="true"></i> <?= esc($visitor['phone']) ?></p>
                <?php endif; ?>
                <?php if (!empty($visitor['address'])): ?>
                    <p class="mb-1"><i class="bi bi-geo-alt text-muted" aria-hidden="true"></i> <?= nl2br((string) esc($visitor['address'])) ?></p>
                <?php endif; ?>
                <hr class="my-2">
                <p class="mb-1 text-muted"><?= lang('Visitors.colVisitDate') ?>: <?= $formatDate($visitor['visit_date'] ?? null) ?></p>
                <?php if (!empty($visitor['source'])): ?>
                    <p class="mb-0 text-muted"><?= lang('Visitors.source') ?>: <?= esc($visitor['source']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stats mini card -->
        <?php
        $counts = ['pending' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];
        foreach ($timeline as $row) {
            $s = $row['status'] ?? 'pending';
            if (isset($counts[$s])) {
                $counts[$s]++;
            }
        }
        $totalActions = count($timeline ?? []);
        $doneActions  = $counts['sent'] + $counts['skipped'];
        $progressPct  = $totalActions > 0 ? (int) round(($doneActions / $totalActions) * 100) : 0;
        ?>
        <div class="card">
            <div class="card-header py-2">
                <h6 class="mb-0 small"><i class="bi bi-bar-chart-line" aria-hidden="true"></i> <?= lang('Visitors.followupProgressTitle') ?></h6>
            </div>
            <div class="card-body py-2 small">
                <?php foreach ($counts as $status => $n): ?>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted"><?= esc($statusConfig[$status]['label'] ?? ucfirst($status)) ?></span>
                        <span id="sidebarCount-<?= $status ?>" class="badge border border-<?= $statusConfig[$status]['badge'] ?> text-<?= $statusConfig[$status]['badge'] ?> fw-normal"><?= $n ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if ($totalActions > 0): ?>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="text-muted"><?= lang('Visitors.followupProgressLabel') ?></small>
                        <small id="progressRatio" class="text-muted"><?= $doneActions ?>/<?= $totalActions ?></small>
                    </div>
                    <div class="progress" style="height: 6px;" role="progressbar"
                        aria-label="<?= esc(lang('Visitors.followupProgressLabel'), 'attr') ?>"
                        aria-valuenow="<?= $progressPct ?>" aria-valuemin="0" aria-valuemax="100">
                        <div id="progressBar" class="progress-bar bg-success" style="width: <?= $progressPct ?>%"></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Timeline -->
    <div class="col-md-9">
        <div class="card">
            <div class="card-header py-2 d-flex align-items-center gap-2">
                <h6 class="mb-0"><i class="bi bi-clock-history" aria-hidden="true"></i> <?= lang('Visitors.followupTimelineTitle') ?></h6>
            </div>
            <div class="card-body p-3">
                <?php if (empty($timeline)): ?>
                    <p class="text-muted text-center py-4"><?= lang('Visitors.followupNoActionsScheduled') ?></p>
                <?php else: ?>
                    <?php
                    $today          = date('Y-m-d');
                    $lastWeekNum    = null;
                    ?>
                    <div class="list-group border rounded overflow-hidden" id="followupTimeline">
                        <?php foreach ($timeline as $row): ?>
                            <?php
                            $isCallTask  = !empty($row['_source']) && $row['_source'] === 'call_task';
                            $status      = $row['status'] ?? 'pending';
                            $cfg         = $statusConfig[$status] ?? $statusConfig['pending'];
                            $channel     = $row['channel'] ?? '';
                            $icon        = $channelIcon[$channel] ?? 'bi-circle';
                            $isPending   = $status === 'pending';
                            $isOverdue   = $isPending && !empty($row['scheduled_date']) && $row['scheduled_date'] < $today;
                            $isAutomated = !$isCallTask && !empty($row['is_automated']);
                            $weekNum     = !$isCallTask ? ($row['week_number'] ?? null) : null;
                            $meta        = !empty($row['response_metadata']) ? json_decode((string) $row['response_metadata'], true) : [];
                            $rowElemId   = ($isCallTask ? 'ctRow' : 'logRow') . '-' . (int) $row['id'];
                            $borderClass = $isOverdue ? 'border-danger' : $cfg['border'];
                            $textClass   = $isOverdue ? 'text-danger' : $cfg['text-class'];
                            ?>

                            <?php if ($weekNum !== null && $weekNum !== $lastWeekNum): ?>
                                <?php $lastWeekNum = $weekNum; ?>
                                <div class="list-group-item bg-body py-1 px-3 d-flex align-items-center gap-2">
                                    <i class="bi bi-layers text-body-secondary small" aria-hidden="true"></i>
                                    <small class="fw-semibold text-uppercase">
                                        <?= lang('Visitors.followupWeekLabel') ?> <?= (int) $weekNum ?>
                                    </small>
                                </div>
                            <?php endif; ?>

                            <div class="list-group-item border-start border-1 <?= $borderClass ?> border-opacity-25 py-3"
                                id="<?= $rowElemId ?>"
                                data-row-status="<?= esc($row['status'] ?? 'pending', 'attr') ?>">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <!-- Info block -->
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                            <i class="bi <?= $icon ?> <?= $textClass ?>"></i>
                                            <strong><?= $isCallTask
                                                        ? esc(lang('Visitors.channelCall'))
                                                        : esc($row['template_name'] ?? lang('Visitors.followupManualAction')) ?></strong>
                                            <?php if ($isCallTask): ?>
                                                <span class="badge border border-secondary text-secondary fw-normal small"><?= lang('Visitors.callTaskSourceBadge') ?></span>
                                            <?php else: ?>
                                                <?php if ($isAutomated): ?>
                                                    <span class="badge border border-info text-info fw-normal small"><i class="bi bi-lightning-charge" aria-hidden="true"></i> <?= lang('Visitors.automatedBadge') ?></span>
                                                <?php else: ?>
                                                    <span class="badge border border-secondary text-secondary fw-normal small"><i class="bi bi-person" aria-hidden="true"></i> <?= lang('Visitors.manualBadge') ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($row['sequence_name'])): ?>
                                                    <span class="badge border border-secondary text-secondary fw-normal small"><?= esc($row['sequence_name']) ?></span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="small text-muted mb-1">
                                            <span class="me-2 text-capitalize"><?= esc(lang('Visitors.channel' . ucfirst($channel)) ?: ucfirst($channel)) ?></span>
                                            <span id="scheduledDate-<?= (int) $row['id'] ?>">
                                                <i class="bi bi-calendar3<?= $isOverdue ? ' text-danger' : '' ?>" aria-hidden="true"></i>
                                                <?= lang('Visitors.scheduledLabel') ?>:
                                                <span class="<?= $isOverdue ? 'text-danger fw-semibold' : '' ?>">
                                                    <?= esc($formatDate($row['scheduled_date'] ?? null)) ?>
                                                </span>
                                            </span>
                                            <span class="badge border border-<?= $isOverdue ? 'danger' : $cfg['badge'] ?> text-<?= $isOverdue ? 'danger' : $cfg['badge'] ?> fw-normal ms-1 status-badge">
                                                <?= $isOverdue ? esc(lang('Visitors.overdueLabel')) : esc($cfg['label']) ?>
                                            </span>
                                            <?php if (!$isPending && !empty($row['executed_at'])): ?>
                                                <span class="ms-2"><i class="bi bi-check2-circle" aria-hidden="true"></i> <?= lang('Visitors.completedLabel') ?>: <?= esc($formatDateTime($row['executed_at'])) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($row['notes'])): ?>
                                            <div class="small text-muted mb-1">
                                                <i class="bi bi-sticky" aria-hidden="true"></i>
                                                <?= nl2br((string) esc($row['notes'])) ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($meta['left_voicemail'])): ?>
                                            <div class="small text-muted">
                                                <i class="bi bi-voicemail" aria-hidden="true"></i> <?= lang('Visitors.leftVoicemailLabel') ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Actions indicator -->
                                    <?php if ($canEdit && $isPending): ?>
                                        <?php if ($isCallTask): ?>
                                            <button type="button"
                                                class="btn btn-sm btn-outline-success flex-shrink-0"
                                                data-ct-id="<?= (int) $row['id'] ?>"
                                                aria-label="<?= esc(lang('Visitors.markDoneBtn')) ?>">
                                                <i class="bi bi-check-circle" aria-hidden="true"></i>
                                            </button>
                                        <?php else: ?>
                                            <div class="dropdown flex-shrink-0">
                                                <button class="btn btn-sm btn-outline-secondary"
                                                    type="button"
                                                    data-bs-toggle="dropdown"
                                                    aria-expanded="false"
                                                    title="<?= esc(lang('App.colActions')) ?>">
                                                    <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                    <li>
                                                        <button class="dropdown-item text-success"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#markDoneModal"
                                                            data-log-id="<?= (int) $row['id'] ?>"
                                                            data-channel="<?= esc($channel, 'attr') ?>"
                                                            data-scheduled="<?= esc($row['scheduled_date'] ?? '', 'attr') ?>">
                                                            <i class="bi bi-check-circle me-2" aria-hidden="true"></i><?= lang('Visitors.markDoneBtn') ?>
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <button class="dropdown-item"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#rescheduleModal"
                                                            data-log-id="<?= (int) $row['id'] ?>"
                                                            data-current-date="<?= esc($row['scheduled_date'] ?? '', 'attr') ?>">
                                                            <i class="bi bi-calendar-plus me-2" aria-hidden="true"></i><?= lang('Visitors.rescheduleBtn') ?>
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <button class="dropdown-item"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#skipModal"
                                                            data-log-id="<?= (int) $row['id'] ?>">
                                                            <i class="bi bi-skip-forward me-2" aria-hidden="true"></i><?= lang('Visitors.skipBtn') ?>
                                                        </button>
                                                    </li>
                                                    <?php if ($channel === 'mail'): ?>
                                                        <li>
                                                            <button class="dropdown-item"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#printLabelModal">
                                                                <i class="bi bi-printer me-2" aria-hidden="true"></i><?= lang('Visitors.printLabelBtn') ?>
                                                            </button>
                                                        </li>
                                                    <?php endif; ?>
                                                    <li>
                                                        <hr class="dropdown-divider">
                                                    </li>
                                                    <li>
                                                        <button class="dropdown-item text-danger"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#markFailedModal"
                                                            data-log-id="<?= (int) $row['id'] ?>">
                                                            <i class="bi bi-x-circle me-2" aria-hidden="true"></i><?= lang('Visitors.markFailedBtn') ?>
                                                        </button>
                                                    </li>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($canEdit): ?>

    <!-- ===== MODAL: Mark as Done ===== -->
    <div class="modal fade" id="markDoneModal" tabindex="-1" aria-labelledby="markDoneModalLabel" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="markDoneModalLabel">
                        <i class="bi bi-check-circle-fill text-success" aria-hidden="true"></i>
                        <?= lang('Visitors.markDoneTitle') ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="mdLogId" value="">

                    <!-- Notes (label changes per channel) -->
                    <div class="mb-3">
                        <label for="mdNotes" class="form-label" id="mdNotesLabel"><?= lang('Visitors.followupNotesLabel') ?></label>
                        <textarea id="mdNotes" class="form-control" rows="3"
                            placeholder="<?= esc(lang('Visitors.followupNotesPlaceholder'), 'attr') ?>"></textarea>
                    </div>

                    <!-- Call-specific fields (shown only for 'call' channel) -->
                    <div id="mdCallFields" class="d-none">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="mdLeftVoicemail">
                            <label class="form-check-label" for="mdLeftVoicemail">
                                <i class="bi bi-voicemail" aria-hidden="true"></i> <?= lang('Visitors.leftVoicemailCheckbox') ?>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="button" class="btn btn-success" id="mdConfirmBtn">
                        <span class="md-spinner spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                        <i class="bi bi-check-circle md-icon" aria-hidden="true"></i>
                        <?= lang('Visitors.markDoneConfirmBtn') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== MODAL: Reschedule ===== -->
    <div class="modal fade" id="rescheduleModal" tabindex="-1" aria-labelledby="rescheduleModalLabel" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rescheduleModalLabel">
                        <i class="bi bi-calendar-plus" aria-hidden="true"></i>
                        <?= lang('Visitors.rescheduleTitle') ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="rsLogId" value="">
                    <div class="mb-3">
                        <label for="rsNewDate" class="form-label"><?= lang('Visitors.rescheduleNewDateLabel') ?></label>
                        <input type="date" id="rsNewDate" class="form-control"
                            min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="rsReason" class="form-label"><?= lang('Visitors.rescheduleReasonLabel') ?></label>
                        <textarea id="rsReason" class="form-control" rows="2"
                            placeholder="<?= esc(lang('Visitors.rescheduleReasonPlaceholder'), 'attr') ?>"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="button" class="btn btn-primary" id="rsConfirmBtn">
                        <span class="rs-spinner spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                        <i class="bi bi-calendar-check rs-icon" aria-hidden="true"></i>
                        <?= lang('Visitors.rescheduleConfirmBtn') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== MODAL: Skip ===== -->
    <div class="modal fade" id="skipModal" tabindex="-1" aria-labelledby="skipModalLabel" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="skipModalLabel">
                        <i class="bi bi-skip-forward-circle" aria-hidden="true"></i>
                        <?= lang('Visitors.skipTitle') ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="skLogId" value="">
                    <p class="text-muted small"><?= lang('Visitors.skipDescription') ?></p>
                    <div class="mb-3">
                        <label for="skNotes" class="form-label"><?= lang('Visitors.skipReasonLabel') ?></label>
                        <textarea id="skNotes" class="form-control" rows="2"
                            placeholder="<?= esc(lang('Visitors.skipReasonPlaceholder'), 'attr') ?>"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="button" class="btn btn-secondary" id="skConfirmBtn">
                        <span class="sk-spinner spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                        <i class="bi bi-skip-forward sk-icon" aria-hidden="true"></i>
                        <?= lang('Visitors.skipConfirmBtn') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== MODAL: Mark as Failed ===== -->
    <div class="modal fade" id="markFailedModal" tabindex="-1" aria-labelledby="markFailedModalLabel" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="markFailedModalLabel">
                        <i class="bi bi-x-circle-fill text-danger" aria-hidden="true"></i>
                        <?= lang('Visitors.markFailedTitle') ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="mfLogId" value="">
                    <div class="mb-3">
                        <label for="mfReason" class="form-label">
                            <?= lang('Visitors.markFailedReasonLabel') ?>
                            <span class="text-danger">*</span>
                        </label>
                        <textarea id="mfReason" class="form-control" rows="2"
                            placeholder="<?= esc(lang('Visitors.markFailedReasonPlaceholder'), 'attr') ?>"></textarea>
                        <div class="invalid-feedback" id="mfReasonError"><?= lang('Visitors.markFailedReasonRequired') ?></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="button" class="btn btn-danger" id="mfConfirmBtn">
                        <span class="mf-spinner spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                        <i class="bi bi-x-circle mf-icon" aria-hidden="true"></i>
                        <?= lang('Visitors.markFailedConfirmBtn') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== MODAL: Print Address Label ===== -->
    <div class="modal fade" id="printLabelModal" tabindex="-1" aria-labelledby="printLabelModalLabel" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="printLabelModalLabel">
                        <i class="bi bi-printer" aria-hidden="true"></i>
                        <?= lang('Visitors.printLabelTitle') ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Preview -->
                    <div class="border rounded p-3 mb-3" id="printLabelPreview" style="font-family: monospace; font-size: 0.95rem; white-space: pre-wrap; min-height: 80px;">
                        <strong><?= esc($visitor['first_name'] . ' ' . $visitor['last_name']) ?></strong>
                        <?php if (!empty($visitor['address'])): ?>
                            <br><?= nl2br((string) esc($visitor['address'])) ?>
                        <?php else: ?>
                            <br><em class="text-muted"><?= lang('Visitors.noAddressOnFile') ?></em>
                        <?php endif; ?>
                    </div>
                    <p class="text-muted small mb-0"><?= lang('Visitors.printLabelHint') ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="button" class="btn btn-outline-info" id="printLabelBtn">
                        <i class="bi bi-printer" aria-hidden="true"></i> <?= lang('Visitors.printLabelConfirmBtn') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    (function() {
        'use strict';

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        const csrfHeader = document.querySelector('meta[name="csrf-header"]')?.content ?? 'X-CSRF-TOKEN';

        function ajaxPost(url, body) {
            const headers = {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
            };
            headers[csrfHeader] = csrfToken;

            return fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers,
                body: new URLSearchParams(body).toString(),
            }).then(function(r) {
                // Refresh CSRF token from response header if present
                const newToken = r.headers.get(csrfHeader);
                if (newToken) {
                    const meta = document.querySelector('meta[name="csrf-token"]');
                    if (meta) {
                        meta.content = newToken;
                    }
                }
                return r.json();
            });
        }

        function setLoading(spinnerEl, iconEl, btnEl, on) {
            spinnerEl.classList.toggle('d-none', !on);
            iconEl.classList.toggle('d-none', on);
            btnEl.disabled = on;
        }

        // Recount statuses from DOM and refresh sidebar + top badges live
        const _summaryLabels = {
            pending: <?= json_encode(lang('Visitors.statusPending')) ?>,
            sent: <?= json_encode(lang('Visitors.followupStatusSent')) ?>,
        };

        function refreshSummary() {
            const counts = {
                pending: 0,
                sent: 0,
                failed: 0,
                skipped: 0
            };
            document.querySelectorAll('[data-row-status]').forEach(function(el) {
                const s = el.dataset.rowStatus;
                if (s in counts) {
                    counts[s]++;
                }
            });

            // Sidebar count badges
            Object.keys(counts).forEach(function(s) {
                const el = document.getElementById('sidebarCount-' + s);
                if (el) {
                    el.textContent = counts[s];
                }
            });

            // Progress ratio text + bar
            const total = Object.values(counts).reduce(function(a, b) {
                return a + b;
            }, 0);
            const done = counts.sent + counts.skipped;
            const pct = total > 0 ? Math.round((done / total) * 100) : 0;
            const ratioEl = document.getElementById('progressRatio');
            const barEl = document.getElementById('progressBar');
            if (ratioEl) {
                ratioEl.textContent = done + '/' + total;
            }
            if (barEl) {
                barEl.style.width = pct + '%';
                barEl.setAttribute('aria-valuenow', pct);
            }

            // Top row pending / done badges
            const pendingBadge = document.getElementById('topBadgePending');
            const doneBadge = document.getElementById('topBadgeDone');
            if (pendingBadge) {
                pendingBadge.textContent = counts.pending + ' ' + _summaryLabels.pending;
                pendingBadge.classList.toggle('d-none', counts.pending === 0);
            }
            if (doneBadge) {
                doneBadge.textContent = counts.sent + ' ' + _summaryLabels.sent;
                doneBadge.classList.toggle('d-none', counts.sent === 0);
            }
        }

        // -------------------------------------------------------------------
        // Mark Done Modal
        // -------------------------------------------------------------------
        const markDoneModal = document.getElementById('markDoneModal');
        if (markDoneModal) {
            markDoneModal.addEventListener('show.bs.modal', function(e) {
                const btn = e.relatedTarget;
                const logId = btn?.dataset?.logId ?? '';
                const channel = btn?.dataset?.channel ?? '';

                document.getElementById('mdLogId').value = logId;

                // Reset fields
                document.getElementById('mdNotes').value = '';
                const cbVoicemail = document.getElementById('mdLeftVoicemail');
                if (cbVoicemail) {
                    cbVoicemail.checked = false;
                }

                // Toggle call-specific fields
                const callFields = document.getElementById('mdCallFields');
                const notesLabel = document.getElementById('mdNotesLabel');
                if (channel === 'call') {
                    callFields?.classList.remove('d-none');
                    if (notesLabel) {
                        notesLabel.textContent = <?= json_encode(lang('Visitors.callNotesLabel')) ?>;
                    }
                } else {
                    callFields?.classList.add('d-none');
                    if (notesLabel) {
                        notesLabel.textContent = <?= json_encode(lang('Visitors.followupNotesLabel')) ?>;
                    }
                }
            });

            document.getElementById('mdConfirmBtn')?.addEventListener('click', function() {
                const logId = document.getElementById('mdLogId').value;
                const notes = document.getElementById('mdNotes').value;
                const voicemail = document.getElementById('mdLeftVoicemail')?.checked ? '1' : '0';
                const spinner = this.querySelector('.md-spinner');
                const icon = this.querySelector('.md-icon');

                if (!logId) {
                    return;
                }

                setLoading(spinner, icon, this, true);

                const url = <?= json_encode(base_url('visitors/followup-log/mark-done/')) ?> + logId;
                ajaxPost(url, {
                        notes,
                        left_voicemail: voicemail
                    })
                    .then(function(res) {
                        if (res.success) {
                            bootstrap.Modal.getInstance(markDoneModal)?.hide();
                            const row = document.getElementById('logRow-' + logId);
                            if (row) {
                                row.classList.replace('border-warning', 'border-success');
                                row.dataset.rowStatus = 'sent';
                                row.querySelectorAll('button').forEach(b => b.remove());
                                const badge = row.querySelector('.badge');
                                if (badge) {
                                    badge.className = 'badge border border-success text-success fw-normal';
                                    badge.textContent = <?= json_encode(lang('Visitors.followupStatusSent')) ?>;
                                }
                            }
                            refreshSummary();
                            showAppToast(res.message ?? <?= json_encode(lang('Visitors.followupMarkedDone')) ?>, 'primary');
                        } else {
                            showAppToast(res.message ?? <?= json_encode(lang('App.errorGeneric')) ?>, 'danger');
                        }
                    })
                    .catch(function() {
                        showAppToast(<?= json_encode(lang('App.networkError')) ?>, 'danger');
                    })
                    .finally(() => setLoading(spinner, icon, document.getElementById('mdConfirmBtn'), false));
            });
        }

        // -------------------------------------------------------------------
        // Reschedule Modal
        // -------------------------------------------------------------------
        const rescheduleModal = document.getElementById('rescheduleModal');
        if (rescheduleModal) {
            rescheduleModal.addEventListener('show.bs.modal', function(e) {
                const btn = e.relatedTarget;
                const logId = btn?.dataset?.logId ?? '';
                const currentDate = btn?.dataset?.currentDate ?? '';

                document.getElementById('rsLogId').value = logId;
                document.getElementById('rsNewDate').value = currentDate;
                document.getElementById('rsReason').value = '';
            });

            document.getElementById('rsConfirmBtn')?.addEventListener('click', function() {
                const logId = document.getElementById('rsLogId').value;
                const newDate = document.getElementById('rsNewDate').value;
                const spinner = this.querySelector('.rs-spinner');
                const icon = this.querySelector('.rs-icon');

                if (!logId || !newDate) {
                    showAppToast(<?= json_encode(lang('Visitors.followupInvalidDate')) ?>, 'warning');
                    return;
                }

                setLoading(spinner, icon, this, true);

                const url = <?= json_encode(base_url('visitors/followup-log/reschedule/')) ?> + logId;
                ajaxPost(url, {
                        new_date: newDate,
                        notes: document.getElementById('rsReason')?.value?.trim() ?? '',
                    })
                    .then(function(res) {
                        if (res.success) {
                            bootstrap.Modal.getInstance(rescheduleModal)?.hide();
                            const dateSpan = document.getElementById('scheduledDate-' + logId);
                            if (dateSpan) {
                                const formatted = new Date(res.new_date + 'T00:00:00')
                                    .toLocaleDateString(<?= json_encode($locale) ?>, {
                                        year: 'numeric',
                                        month: 'short',
                                        day: 'numeric'
                                    });
                                dateSpan.innerHTML = '<i class="bi bi-calendar3" aria-hidden="true"></i> ' +
                                    <?= json_encode(lang('Visitors.scheduledLabel') . ': ') ?> +
                                    formatted;
                            }
                            showAppToast(res.message ?? <?= json_encode(lang('Visitors.followupRescheduled')) ?>, 'primary');
                        } else {
                            showAppToast(res.message ?? <?= json_encode(lang('App.errorGeneric')) ?>, 'danger');
                        }
                    })
                    .catch(function() {
                        showAppToast(<?= json_encode(lang('App.networkError')) ?>, 'danger');
                    })
                    .finally(() => setLoading(spinner, icon, document.getElementById('rsConfirmBtn'), false));
            });
        }

        // -------------------------------------------------------------------
        // Skip Modal
        // -------------------------------------------------------------------
        const skipModal = document.getElementById('skipModal');
        if (skipModal) {
            skipModal.addEventListener('show.bs.modal', function(e) {
                const btn = e.relatedTarget;
                const logId = btn?.dataset?.logId ?? '';
                document.getElementById('skLogId').value = logId;
                document.getElementById('skNotes').value = '';
            });

            document.getElementById('skConfirmBtn')?.addEventListener('click', function() {
                const logId = document.getElementById('skLogId').value;
                const notes = document.getElementById('skNotes').value;
                const spinner = this.querySelector('.sk-spinner');
                const icon = this.querySelector('.sk-icon');

                if (!logId) {
                    return;
                }

                setLoading(spinner, icon, this, true);

                const url = <?= json_encode(base_url('visitors/followup-log/skip/')) ?> + logId;
                ajaxPost(url, {
                        notes
                    })
                    .then(function(res) {
                        if (res.success) {
                            bootstrap.Modal.getInstance(skipModal)?.hide();
                            const row = document.getElementById('logRow-' + logId);
                            if (row) {
                                row.classList.replace('border-warning', 'border-secondary');
                                row.dataset.rowStatus = 'skipped';
                                row.querySelectorAll('button').forEach(b => b.remove());
                                const badge = row.querySelector('.badge');
                                if (badge) {
                                    badge.className = 'badge border border-secondary text-secondary fw-normal';
                                    badge.textContent = <?= json_encode(lang('Visitors.followupStatusSkipped')) ?>;
                                }
                            }
                            refreshSummary();
                            showAppToast(res.message ?? <?= json_encode(lang('Visitors.followupSkipped')) ?>, 'primary');
                        } else {
                            showAppToast(res.message ?? <?= json_encode(lang('App.errorGeneric')) ?>, 'danger');
                        }
                    })
                    .catch(function() {
                        showAppToast(<?= json_encode(lang('App.networkError')) ?>, 'danger');
                    })
                    .finally(() => setLoading(spinner, icon, document.getElementById('skConfirmBtn'), false));
            });
        }

        // -------------------------------------------------------------------
        // Mark Failed Modal
        // -------------------------------------------------------------------
        const markFailedModal = document.getElementById('markFailedModal');
        if (markFailedModal) {
            markFailedModal.addEventListener('show.bs.modal', function(e) {
                const btn = e.relatedTarget;
                document.getElementById('mfLogId').value = btn?.dataset?.logId ?? '';
                document.getElementById('mfReason').value = '';
                document.getElementById('mfReason').classList.remove('is-invalid');
            });

            document.getElementById('mfConfirmBtn')?.addEventListener('click', function() {
                const logId = document.getElementById('mfLogId').value;
                const reason = document.getElementById('mfReason').value.trim();
                const spinner = this.querySelector('.mf-spinner');
                const icon = this.querySelector('.mf-icon');
                const textarea = document.getElementById('mfReason');

                if (!reason) {
                    textarea.classList.add('is-invalid');
                    return;
                }
                textarea.classList.remove('is-invalid');

                setLoading(spinner, icon, this, true);

                const url = <?= json_encode(base_url('visitors/followup-log/mark-failed/')) ?> + logId;
                ajaxPost(url, {
                        reason
                    })
                    .then(function(res) {
                        if (res.success) {
                            bootstrap.Modal.getInstance(markFailedModal)?.hide();
                            const row = document.getElementById('logRow-' + logId);
                            if (row) {
                                row.classList.replace('border-warning', 'border-danger');
                                row.classList.replace('border-opacity-75', 'border-opacity-75');
                                row.dataset.rowStatus = 'failed';
                                row.querySelectorAll('button').forEach(b => b.remove());
                                const badge = row.querySelector('.status-badge');
                                if (badge) {
                                    badge.className = 'badge border border-danger text-danger fw-normal status-badge';
                                    badge.textContent = <?= json_encode(lang('Visitors.followupStatusFailed')) ?>;
                                }
                            }
                            refreshSummary();
                            showAppToast(res.message ?? <?= json_encode(lang('Visitors.markFailedSuccess')) ?>, 'primary');
                        } else {
                            showAppToast(res.message ?? <?= json_encode(lang('App.errorGeneric')) ?>, 'danger');
                        }
                    })
                    .catch(function() {
                        showAppToast(<?= json_encode(lang('App.networkError')) ?>, 'danger');
                    })
                    .finally(() => setLoading(spinner, icon, document.getElementById('mfConfirmBtn'), false));
            });
        }

        // -------------------------------------------------------------------
        // Call Task inline Mark Done (legacy/orphaned tasks not in visitor_followup_log)
        // -------------------------------------------------------------------
        (function() {
            const ctConfirmBody = <?= json_encode(lang('Visitors.callTasksCompleteBody', [$visitor['first_name'] . ' ' . $visitor['last_name']])) ?>;
            const completeBaseUrl = <?= json_encode(base_url('followup-workflow/call-tasks/complete/')) ?>;
            const markedDoneMsg = <?= json_encode(lang('Visitors.callTaskMarkedDone')) ?>;
            const failedMsg = <?= json_encode(lang('Visitors.callTasksUpdateFailed')) ?>;
            const networkErrMsg = <?= json_encode(lang('Visitors.callTasksNetworkError')) ?>;
            const sentLabel = <?= json_encode(lang('Visitors.followupStatusSent')) ?>;

            document.querySelectorAll('[data-ct-id]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const ctId = this.dataset.ctId;
                    const self = this;

                    showAppConfirm(
                        <?= json_encode(lang('Visitors.callTasksCompleteTitle')) ?>,
                        ctConfirmBody,
                        function() {
                            self.disabled = true;
                            const headers = {
                                'Accept': 'application/json',
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'X-Requested-With': 'XMLHttpRequest',
                            };
                            headers[csrfHeader] = csrfToken;

                            fetch(completeBaseUrl + ctId, {
                                    method: 'POST',
                                    credentials: 'same-origin',
                                    headers,
                                    body: new URLSearchParams({
                                        notes: ''
                                    }).toString(),
                                })
                                .then(function(r) {
                                    const newToken = r.headers.get(csrfHeader);
                                    if (newToken) {
                                        const meta = document.querySelector('meta[name="csrf-token"]');
                                        if (meta) {
                                            meta.content = newToken;
                                        }
                                    }
                                    return r.json();
                                })
                                .then(function(res) {
                                    if (res.success) {
                                        const row = document.getElementById('ctRow-' + ctId);
                                        if (row) {
                                            row.classList.replace('border-warning', 'border-success');
                                            row.dataset.rowStatus = 'sent';
                                            row.querySelectorAll('button').forEach(function(b) {
                                                b.remove();
                                            });
                                            const badge = row.querySelector('.status-badge');
                                            if (badge) {
                                                badge.className = 'badge border border-success text-success fw-normal status-badge';
                                                badge.textContent = sentLabel;
                                            }
                                        }
                                        refreshSummary();
                                        showAppToast(markedDoneMsg, 'primary');
                                    } else {
                                        showAppToast(res.message ?? failedMsg, 'danger');
                                        self.disabled = false;
                                    }
                                })
                                .catch(function() {
                                    showAppToast(networkErrMsg, 'danger');
                                    self.disabled = false;
                                });
                        }
                    );
                });
            });
        }());

        // -------------------------------------------------------------------
        // Print Label
        // -------------------------------------------------------------------
        document.getElementById('printLabelBtn')?.addEventListener('click', function() {
            const visitorName = <?= json_encode(esc($visitor['first_name'] . ' ' . $visitor['last_name'])) ?>;
            const visitorAddress = <?= json_encode(esc($visitor['address'] ?? '')) ?>;

            const printWin = window.open('', '_blank', 'width=480,height=320');
            if (!printWin) {
                showAppToast(<?= json_encode(lang('Visitors.printPopupBlocked')) ?>, 'warning');
                return;
            }
            printWin.document.write(`<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title><?= esc(lang('Visitors.printLabelTitle'), 'js') ?></title>
<style>
  @page { size: 4in 2in; margin: 0.25in; }
  body { font-family: Arial, sans-serif; font-size: 14pt; margin: 0; padding: 0.25in; }
  .label-name { font-weight: bold; margin-bottom: 4px; }
  .label-address { white-space: pre-wrap; }
</style>
</head>
<body>
<div class="label-name">${visitorName}</div>
<div class="label-address">${visitorAddress.replace(/&amp;/g,'&').replace(/&lt;/g,'<').replace(/&gt;/g,'>').replace(/&#039;/g,"'")}</div>
</body>
</html>`);
            printWin.document.close();
            printWin.focus();
            printWin.print();
        });
    }());
</script>

<!-- Inline print for address labels (media print hook) -->
<style>
    @media print {
        /* Suppress main layout — only #printLabelArea is targeted via popup window */
    }
</style>
<?= $this->endSection() ?>