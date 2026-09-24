<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
$locale = service('request')->getLocale() ?: config('App')->defaultLocale;
$formatLocalizedDate = static function (?string $value, string $pattern, string $locale): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return (string) $value;
    }

    if (class_exists(\IntlDateFormatter::class)) {
        $formatter = new \IntlDateFormatter(
            $locale,
            \IntlDateFormatter::MEDIUM,
            \IntlDateFormatter::NONE,
            date_default_timezone_get()
        );
        $formatter->setPattern($pattern);
        $formatted = $formatter->format($timestamp);
        if (is_string($formatted) && $formatted !== '') {
            return $formatted;
        }
    }

    return date('Y-m-d', $timestamp);
};
?>

<?php
$visitorStatus    = (string) ($visitor['membership_status'] ?? 'visitor');
$visitorConverted = !empty($visitor['converted_to_member_at']);
$visitorVisits    = (int) ($visitor['visit_count'] ?? count($visits ?? []));
$attenderThreshold = (int) ($attenderThreshold ?? 2);
$suggestAttender  = ! $visitorConverted && $visitorStatus === 'visitor' && $visitorVisits >= $attenderThreshold;
?>

<div class="d-flex justify-content-start align-items-center mb-3">
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <?php if (! $visitorConverted && $visitorStatus === 'visitor' && auth()->user()->can('visitor.edit')): ?>
            <form method="post" action="<?= base_url('visitors/promote-attender/' . $visitor['id']) ?>"
                onsubmit="return confirm(<?= json_encode(lang('Visitors.confirmPromoteAttender')) ?>);" class="d-inline">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-info">
                    <i class="bi bi-arrow-up-circle" aria-hidden="true"></i> <?= lang('Visitors.promoteToAttender') ?>
                </button>
            </form>
        <?php elseif (! $visitorConverted && $visitorStatus === 'regular_attender' && auth()->user()->can('visitor.edit')): ?>
            <form method="post" action="<?= base_url('visitors/demote/' . $visitor['id']) ?>"
                onsubmit="return confirm(<?= json_encode(lang('Visitors.confirmDemoteVisitor')) ?>);" class="d-inline">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-down-circle" aria-hidden="true"></i> <?= lang('Visitors.demoteToVisitor') ?>
                </button>
            </form>
        <?php endif; ?>
        <?php if (! $visitorConverted && auth()->user()->can('visitor.edit')): ?>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#promoteMemberModal">
                <i class="bi bi-arrow-right-circle" aria-hidden="true"></i> <?= lang('Visitors.convertToMember') ?>
            </button>
        <?php endif; ?>
        <?php if ($visitorConverted && auth()->user()->can('visitor.edit')): ?>
            <form method="post" action="<?= base_url('visitors/revert-conversion/' . $visitor['id']) ?>"
                onsubmit="return confirm(<?= json_encode(lang('Visitors.confirmRevertConversion')) ?>);" class="d-inline">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-danger">
                    <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> <?= lang('Visitors.revertConversionBtn') ?>
                </button>
            </form>
        <?php endif; ?>
        <?php if (auth()->user()->can('visitor.edit')): ?>
            <a href="<?= base_url('visitors/edit/' . $visitor['id']) ?>" class="btn btn-warning">
                <i class="bi bi-pencil" aria-hidden="true"></i> <?= lang('App.edit') ?>
            </a>
        <?php endif; ?>
        <?php if (auth()->user()->can('visitor.view')): ?>
            <a href="<?= base_url('visitors/followup-timeline/' . $visitor['id']) ?>" class="btn btn-outline-primary">
                <i class="bi bi-clock-history" aria-hidden="true"></i> <?= lang('Visitors.followupManualBtn') ?>
            </a>
        <?php endif; ?>
        <a href="<?= base_url('visitors/dashboard') ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Visitors.backToVisitors') ?>
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <div class="text-center mb-3">
                    <i class="bi bi-person-circle text-primary" style="font-size: 80px;" aria-hidden="true"></i>
                </div>
                <h4 class="text-center"><?= esc($visitor['first_name'] . ' ' . $visitor['last_name']) ?></h4>

                <div class="text-center mb-2">
                    <?php
                    $lifecycleBadge = $visitorConverted ? 'success' : ($visitorStatus === 'regular_attender' ? 'info' : 'secondary');
                    ?>
                    <span class="badge border border-<?= $lifecycleBadge ?> text-<?= $lifecycleBadge ?> fw-normal">
                        <?= esc(lang('App.status' . ucfirst($visitorStatus))) ?>
                    </span>
                    <?php if ($suggestAttender): ?>
                        <span class="badge border border-primary text-primary fw-normal">
                            <?= lang('Visitors.suggestedAttender') ?>
                        </span>
                    <?php endif; ?>
                    <div class="small text-muted mt-1"><?= lang('Visitors.colVisits') ?>: <?= $visitorVisits ?></div>
                </div>

                <div class="text-center mb-3">
                    <?php
                    $tl         = $followupTimeline ?? [];
                    $tlTotal    = count($tl);
                    $tlDone     = count(array_filter($tl, fn($r) => in_array($r['status'], ['sent', 'skipped'])));
                    $tlPending  = count(array_filter($tl, fn($r) => $r['status'] === 'pending'));
                    $isConverted = !empty($visitor['converted_to_member_at']);

                    if ($isConverted) {
                        $badgeColor = 'success';
                        $badgeLabel = lang('Visitors.statusConverted');
                    } elseif (!empty($visitor['followed_up'])) {
                        $badgeColor = 'success';
                        $badgeLabel = lang('Visitors.followedUpLabel');
                    } elseif ($tlTotal > 0 && $tlDone > 0 && $tlPending > 0) {
                        $badgeColor = 'primary';
                        $badgeLabel = lang('Visitors.followupInProgress');
                    } else {
                        $badgeColor = 'warning';
                        $badgeLabel = lang('Visitors.pendingFollowUp');
                    }
                    ?>
                    <span class="badge border border-<?= $badgeColor ?> text-<?= $badgeColor ?> fw-normal">
                        <?= $badgeLabel ?>
                    </span>
                </div>

                <hr>

                <div>
                    <p class="mb-2"><strong><i class="bi bi-envelope" aria-hidden="true"></i> <?= lang('Visitors.colEmail') ?>:</strong><br>
                        <?= esc($visitor['email'] ?? lang('Visitors.notProvided')) ?>
                    </p>

                    <p class="mb-2"><strong><i class="bi bi-telephone" aria-hidden="true"></i> <?= lang('Visitors.colPhone') ?>:</strong><br>
                        <?= esc($visitor['phone'] ?? lang('Visitors.notProvided')) ?>
                    </p>

                    <p class="mb-2"><strong><i class="bi bi-calendar-check" aria-hidden="true"></i> <?= lang('Visitors.colVisitDate') ?>:</strong><br>
                        <?= esc($formatLocalizedDate((string) ($visitor['visit_date'] ?? ''), 'MMMM d, y', $locale)) ?>
                    </p>

                    <?php if (!empty($visitor['service_attended'])): ?>
                        <p class="mb-2"><strong><i class="bi bi-signpost" aria-hidden="true"></i> <?= lang('Visitors.colService') ?>:</strong><br>
                            <?= esc($visitor['service_attended']) ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#details"><?= lang('Visitors.detailsTab') ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#followup">
                            <i class="bi bi-clock-history" aria-hidden="true"></i> <?= lang('Visitors.viewFollowupTab') ?>
                            <?php
                            $pendingCount = count(array_filter($followupTimeline ?? [], fn($r) => $r['status'] === 'pending'));
                            if ($pendingCount > 0):
                            ?>
                                <span class="badge border border-warning text-warning fw-normal ms-1"><?= $pendingCount ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#notes"><?= lang('Visitors.notes') ?></a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="details">
                        <?php if (!empty($visitor['service_attended'])): ?>
                            <h6><?= lang('Visitors.serviceAttended') ?></h6>
                            <p><?= esc($visitor['service_attended']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="tab-pane fade" id="followup">
                        <?php
                        $totalTimeline = count($followupTimeline ?? []);
                        $doneTimeline  = count(array_filter($followupTimeline ?? [], fn($r) => in_array($r['status'], ['sent', 'skipped'])));
                        ?>

                        <?php if ($nextPending): ?>
                            <div class="alert alert-warning d-flex align-items-center gap-3 mb-3 py-2">
                                <i class="bi bi-bell fs-5 flex-shrink-0" aria-hidden="true"></i>
                                <div class="flex-grow-1 small">
                                    <strong><?= lang('Visitors.viewFollowupNextTitle') ?>:</strong>
                                    <?= esc($nextPending['template_name'] ?? lang('Visitors.channelCall')) ?>
                                    &mdash;
                                    <span class="text-capitalize"><?= esc($nextPending['channel'] ?? '') ?></span>
                                    &mdash;
                                    <?= lang('Visitors.viewFollowupScheduled') ?>:
                                    <strong><?= esc($nextPending['scheduled_date'] ?? '') ?></strong>
                                </div>
                                <a href="<?= base_url('visitors/followup-timeline/' . (int) $visitor['id']) ?>"
                                    class="btn btn-sm btn-outline-warning flex-shrink-0">
                                    <?= lang('Visitors.viewFollowupOpenTimeline') ?>
                                </a>
                            </div>
                        <?php elseif ($totalTimeline > 0): ?>
                            <div class="alert alert-success d-flex align-items-center gap-2 mb-3 py-2 small">
                                <i class="bi bi-check-circle-fill flex-shrink-0" aria-hidden="true"></i>
                                <?= lang('Visitors.viewFollowupNoNext') ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($totalTimeline > 0): ?>
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <small class="text-muted"><?= lang('Visitors.viewFollowupSummaryTitle') ?>: <?= $doneTimeline ?>/<?= $totalTimeline ?></small>
                                <div class="progress flex-grow-1 mx-3" style="height:6px;">
                                    <div class="progress-bar bg-success" style="width:<?= $totalTimeline > 0 ? round(($doneTimeline / $totalTimeline) * 100) : 0 ?>%"></div>
                                </div>
                                <a href="<?= base_url('visitors/followup-timeline/' . (int) $visitor['id']) ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-clock-history" aria-hidden="true"></i> <?= lang('Visitors.viewFollowupOpenTimeline') ?>
                                </a>
                            </div>
                        <?php else: ?>
                            <p class="text-muted small"><?= lang('Visitors.followupNoActionsScheduled') ?></p>
                        <?php endif; ?>

                        <?php if (auth()->user()->can('followup.manage') && !empty($sequences)): ?>
                            <hr>
                            <h6 class="small fw-semibold mb-2"><?= lang('Visitors.viewAssignSequenceTitle') ?></h6>
                            <form id="assignSeqForm" class="d-flex gap-2 align-items-center">
                                <?= csrf_field() ?>
                                <select name="sequence_id" id="assignSeqSelect" class="form-select form-select-sm flex-grow-1">
                                    <?php foreach ($sequences as $seq): ?>
                                        <option value="<?= (int) $seq['id'] ?>"><?= esc($seq['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-sm btn-outline-primary flex-shrink-0">
                                    <i class="bi bi-plus-circle" aria-hidden="true"></i> <?= lang('Visitors.viewAssignSequenceBtn') ?>
                                </button>
                            </form>
                        <?php elseif (auth()->user()->can('followup.manage')): ?>
                            <p class="text-muted small mt-2"><?= lang('Visitors.viewAssignSequenceNone') ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="tab-pane fade" id="notes">
                        <?php if (auth()->user()->can('visitor.edit')): ?>
                            <form method="post" action="<?= base_url('visitors/notes/add/' . (int) $visitor['id']) ?>" class="mb-3">
                                <?= csrf_field() ?>
                                <label for="visitorNote" class="form-label small fw-semibold"><?= lang('Visitors.noteAddTitle') ?></label>
                                <textarea name="note" id="visitorNote" class="form-control form-control-sm" rows="2"
                                    maxlength="2000" placeholder="<?= esc(lang('Visitors.noteAddPlaceholder'), 'attr') ?>"></textarea>
                                <div class="d-flex justify-content-end mt-2">
                                    <button type="submit" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-plus-circle" aria-hidden="true"></i> <?= lang('Visitors.noteAddBtn') ?>
                                    </button>
                                </div>
                            </form>
                            <hr>
                        <?php endif; ?>

                        <?php
                        $feed = $notesFeed ?? [];
                        $srcBadgeMap = [
                            'note'     => 'primary',
                            'visit'    => 'info',
                            'followup' => 'warning',
                            'task'     => 'secondary',
                        ];
                        $statusLabelMap = [
                            'pending' => lang('Visitors.statusPending'),
                            'sent'    => lang('Visitors.followupStatusSent'),
                            'skipped' => lang('Visitors.followupStatusSkipped'),
                            'failed'  => lang('Visitors.followupStatusFailed'),
                        ];
                        ?>
                        <?php if (empty($feed)): ?>
                            <p class="text-muted"><?= lang('Visitors.noNotesYet') ?></p>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($feed as $item): ?>
                                    <?php
                                    $src      = (string) ($item['source'] ?? 'note');
                                    $srcBadge = $srcBadgeMap[$src] ?? 'secondary';
                                    $srcLabel = lang('Visitors.noteSource' . ucfirst($src));
                                    $ctx      = [];
                                    if ($src === 'followup' || $src === 'task') {
                                        $ch = (string) ($item['meta']['channel'] ?? '');
                                        if ($ch !== '') {
                                            $ctx[] = lang('Visitors.channel' . ucfirst($ch));
                                        }
                                        if (!empty($item['meta']['action'])) {
                                            $ctx[] = (string) $item['meta']['action'];
                                        }
                                        $st = (string) ($item['meta']['status'] ?? '');
                                        if (isset($statusLabelMap[$st])) {
                                            $ctx[] = $statusLabelMap[$st];
                                        }
                                    } elseif ($src === 'visit' && !empty($item['meta']['service'])) {
                                        $ctx[] = (string) $item['meta']['service'];
                                    }
                                    ?>
                                    <li class="list-group-item px-0">
                                        <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
                                            <span class="badge border border-<?= $srcBadge ?> text-<?= $srcBadge ?> fw-normal"><?= esc($srcLabel) ?></span>
                                            <small class="text-muted">
                                                <?= esc($item['date']) ?><?= $item['author'] ? ' · ' . esc(lang('Visitors.noteBy')) . ' ' . esc($item['author']) : '' ?>
                                            </small>
                                        </div>
                                        <?php if ($ctx !== []): ?>
                                            <div class="small text-muted mb-1"><?= esc(implode(' · ', $ctx)) ?></div>
                                        <?php endif; ?>
                                        <p class="mb-0 small"><?= nl2br((string) esc($item['body'])) ?></p>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (! $visitorConverted && auth()->user()->can('visitor.edit')): ?>
<!-- ── Promote to Member modal ─────────────────────────────────────────── -->
<div class="modal fade" id="promoteMemberModal" tabindex="-1"
    aria-labelledby="promoteMemberModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?= base_url('visitors/promote/' . (int) $visitor['id']) ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="promoteMemberModalLabel">
                        <i class="bi bi-arrow-right-circle me-2" aria-hidden="true"></i><?= esc(lang('Visitors.promoteModalTitle')) ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">
                        <?= esc(lang('Visitors.promoteModalIntro')) ?>
                        <strong><?= esc($visitor['first_name'] . ' ' . $visitor['last_name']) ?></strong>
                    </p>

                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="mode" id="modeMarkMember"
                            value="mark_member" checked>
                        <label class="form-check-label" for="modeMarkMember">
                            <?= esc(lang('Visitors.promoteModeMark')) ?>
                            <div class="form-text"><?= esc(lang('Visitors.promoteModeMarkHint')) ?></div>
                        </label>
                    </div>

                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="mode" id="modeLinkUser"
                            value="link_user" <?= empty($linkableMembers) ? 'disabled' : '' ?>>
                        <label class="form-check-label" for="modeLinkUser">
                            <?= esc(lang('Visitors.promoteModeLink')) ?>
                            <div class="form-text"><?= esc(lang('Visitors.promoteModeLinkHint')) ?></div>
                        </label>
                    </div>
                    <div class="ms-4 mb-3 promote-pane d-none" id="paneLinkUser">
                        <label for="link_user_id" class="form-label small"><?= esc(lang('Visitors.promoteLinkUserLabel')) ?></label>
                        <select name="link_user_id" id="link_user_id" class="form-select form-select-sm">
                            <option value=""><?= esc(lang('Visitors.promoteLinkUserSelect')) ?></option>
                            <?php foreach (($linkableMembers ?? []) as $m): ?>
                                <option value="<?= (int) ($m['user_id'] ?? 0) ?>">
                                    <?= esc(trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')) . ' — ' . ($m['email'] ?? $m['username'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="mode" id="modeCreateAccount"
                            value="create_account" <?= auth()->user()->can('member.create') ? '' : 'disabled' ?>>
                        <label class="form-check-label" for="modeCreateAccount">
                            <?= esc(lang('Visitors.promoteModeCreate')) ?>
                            <div class="form-text"><?= esc(lang('Visitors.promoteModeCreateHint')) ?></div>
                        </label>
                    </div>
                    <div class="ms-4 promote-pane d-none" id="paneCreateAccount">
                        <div class="mb-2">
                            <label for="account_email" class="form-label small"><?= esc(lang('Visitors.promoteAccountEmail')) ?></label>
                            <input type="email" name="account_email" id="account_email"
                                class="form-control form-control-sm"
                                value="<?= esc((string) ($visitor['email'] ?? '')) ?>">
                        </div>
                        <div class="mb-1">
                            <label for="account_password" class="form-label small"><?= esc(lang('Visitors.promoteAccountPassword')) ?></label>
                            <input type="password" name="account_password" id="account_password"
                                class="form-control form-control-sm" autocomplete="new-password">
                            <div class="form-text"><?= esc(lang('Visitors.promoteAccountPasswordHint')) ?></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <?= esc(lang('App.cancel')) ?>
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle me-1" aria-hidden="true"></i><?= esc(lang('Visitors.promoteConfirmBtn')) ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    (function() {
        'use strict';

        // ── Promote modal: toggle mode panes ──────────────────────────────
        document.querySelectorAll('#promoteMemberModal input[name="mode"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.promote-pane').forEach(function(pane) {
                    pane.classList.add('d-none');
                });
                const target = radio.value === 'link_user' ? 'paneLinkUser'
                    : (radio.value === 'create_account' ? 'paneCreateAccount' : null);
                if (target) {
                    document.getElementById(target)?.classList.remove('d-none');
                }
            });
        });

        const form = document.getElementById('assignSeqForm');
        if (!form) {
            return;
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        const csrfHeader = document.querySelector('meta[name="csrf-header"]')?.content ?? 'X-CSRF-TOKEN';
        const assignUrl = <?= json_encode(base_url('visitors/assign-sequence/' . (int) $visitor['id'])) ?>;

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = form.querySelector('[type="submit"]');
            const seqId = document.getElementById('assignSeqSelect')?.value ?? '';
            if (!seqId) {
                return;
            }

            btn.disabled = true;
            const headers = {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            };
            headers[csrfHeader] = csrfToken;

            fetch(assignUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers,
                    body: new URLSearchParams({
                        sequence_id: seqId
                    }).toString(),
                })
                .then(function(r) {
                    const t = r.headers.get(csrfHeader);
                    if (t) {
                        const m = document.querySelector('meta[name="csrf-token"]');
                        if (m) {
                            m.content = t;
                        }
                    }
                    return r.json();
                })
                .then(function(res) {
                    if (res.success) {
                        showAppToast(res.message, 'primary');
                        setTimeout(function() {
                            location.reload();
                        }, 800);
                    } else {
                        showAppToast(res.message ?? <?= json_encode(lang('App.errorGeneric')) ?>, 'danger');
                        btn.disabled = false;
                    }
                })
                .catch(function() {
                    showAppToast(<?= json_encode(lang('App.networkError')) ?>, 'danger');
                    btn.disabled = false;
                });
        });
    }());
</script>

<?= $this->endSection() ?>