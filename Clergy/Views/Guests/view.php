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

<div class="d-flex justify-content-start align-items-center mb-3">
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <?php if (empty($guest['followed_up']) && auth()->user()->can('guest.edit')): ?>
            <a href="<?= base_url('guests/convertToMember/' . $guest['id']) ?>" class="btn btn-success">
                <i class="bi bi-arrow-right-circle"></i> <?= lang('Guests.convertToMember') ?>
            </a>
        <?php endif; ?>
        <?php if (auth()->user()->can('guest.edit')): ?>
            <a href="<?= base_url('guests/edit/' . $guest['id']) ?>" class="btn btn-warning">
                <i class="bi bi-pencil"></i> <?= lang('App.edit') ?>
            </a>
        <?php endif; ?>
        <?php if (auth()->user()->can('guest.view')): ?>
            <a href="<?= base_url('guests/followup-manual/' . $guest['id']) ?>" class="btn btn-outline-primary">
                <i class="bi bi-clock-history"></i> <?= lang('Guests.followupManualBtn') ?>
            </a>
        <?php endif; ?>
        <a href="<?= base_url('guests/dashboard') ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> <?= lang('Guests.backToGuests') ?>
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <div class="text-center mb-3">
                    <i class="bi bi-person-circle text-primary" style="font-size: 80px;"></i>
                </div>
                <h4 class="text-center"><?= esc($guest['first_name'] . ' ' . $guest['last_name']) ?></h4>

                <div class="text-center mb-3">
                    <?php
                    $tl         = $followupTimeline ?? [];
                    $tlTotal    = count($tl);
                    $tlDone     = count(array_filter($tl, fn($r) => in_array($r['status'], ['sent', 'skipped'])));
                    $tlPending  = count(array_filter($tl, fn($r) => $r['status'] === 'pending'));
                    $isConverted = !empty($guest['converted_to_member_at']);

                    if ($isConverted) {
                        $badgeColor = 'success';
                        $badgeLabel = lang('Guests.statusConverted');
                    } elseif (!empty($guest['followed_up'])) {
                        $badgeColor = 'success';
                        $badgeLabel = lang('Guests.followedUpLabel');
                    } elseif ($tlTotal > 0 && $tlDone > 0 && $tlPending > 0) {
                        $badgeColor = 'primary';
                        $badgeLabel = lang('Guests.followupInProgress');
                    } else {
                        $badgeColor = 'warning';
                        $badgeLabel = lang('Guests.pendingFollowUp');
                    }
                    ?>
                    <span class="badge border border-<?= $badgeColor ?> text-<?= $badgeColor ?> fw-normal">
                        <?= $badgeLabel ?>
                    </span>
                </div>

                <hr>

                <div>
                    <p class="mb-2"><strong><i class="bi bi-envelope"></i> <?= lang('Guests.colEmail') ?>:</strong><br>
                        <?= esc($guest['email'] ?? lang('Guests.notProvided')) ?>
                    </p>

                    <p class="mb-2"><strong><i class="bi bi-telephone"></i> <?= lang('Guests.colPhone') ?>:</strong><br>
                        <?= esc($guest['phone'] ?? lang('Guests.notProvided')) ?>
                    </p>

                    <p class="mb-2"><strong><i class="bi bi-calendar-check"></i> <?= lang('Guests.colVisitDate') ?>:</strong><br>
                        <?= esc($formatLocalizedDate((string) ($guest['visit_date'] ?? ''), 'MMMM d, y', $locale)) ?>
                    </p>

                    <?php if (!empty($guest['service_attended'])): ?>
                        <p class="mb-2"><strong><i class="bi bi-signpost"></i> <?= lang('Guests.colService') ?>:</strong><br>
                            <?= esc($guest['service_attended']) ?>
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
                        <a class="nav-link active" data-bs-toggle="tab" href="#details"><?= lang('Guests.detailsTab') ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#followup">
                            <i class="bi bi-clock-history"></i> <?= lang('Guests.viewFollowupTab') ?>
                            <?php
                            $pendingCount = count(array_filter($followupTimeline ?? [], fn($r) => $r['status'] === 'pending'));
                            if ($pendingCount > 0):
                            ?>
                                <span class="badge border border-warning text-warning fw-normal ms-1"><?= $pendingCount ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#notes"><?= lang('Guests.notes') ?></a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="details">
                        <?php if (!empty($guest['service_attended'])): ?>
                            <h6><?= lang('Guests.serviceAttended') ?></h6>
                            <p><?= esc($guest['service_attended']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="tab-pane fade" id="followup">
                        <?php
                        $totalTimeline = count($followupTimeline ?? []);
                        $doneTimeline  = count(array_filter($followupTimeline ?? [], fn($r) => in_array($r['status'], ['sent', 'skipped'])));
                        ?>

                        <?php if ($nextPending): ?>
                            <div class="alert alert-warning d-flex align-items-center gap-3 mb-3 py-2">
                                <i class="bi bi-bell fs-5 flex-shrink-0"></i>
                                <div class="flex-grow-1 small">
                                    <strong><?= lang('Guests.viewFollowupNextTitle') ?>:</strong>
                                    <?= esc($nextPending['template_name'] ?? lang('Guests.channelCall')) ?>
                                    &mdash;
                                    <span class="text-capitalize"><?= esc($nextPending['channel'] ?? '') ?></span>
                                    &mdash;
                                    <?= lang('Guests.viewFollowupScheduled') ?>:
                                    <strong><?= esc($nextPending['scheduled_date'] ?? '') ?></strong>
                                </div>
                                <a href="<?= base_url('guests/followup-manual/' . (int) $guest['id']) ?>"
                                    class="btn btn-sm btn-outline-warning flex-shrink-0">
                                    <?= lang('Guests.viewFollowupOpenTimeline') ?>
                                </a>
                            </div>
                        <?php elseif ($totalTimeline > 0): ?>
                            <div class="alert alert-success d-flex align-items-center gap-2 mb-3 py-2 small">
                                <i class="bi bi-check-circle-fill flex-shrink-0"></i>
                                <?= lang('Guests.viewFollowupNoNext') ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($totalTimeline > 0): ?>
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <small class="text-muted"><?= lang('Guests.viewFollowupSummaryTitle') ?>: <?= $doneTimeline ?>/<?= $totalTimeline ?></small>
                                <div class="progress flex-grow-1 mx-3" style="height:6px;">
                                    <div class="progress-bar bg-success" style="width:<?= $totalTimeline > 0 ? round(($doneTimeline / $totalTimeline) * 100) : 0 ?>%"></div>
                                </div>
                                <a href="<?= base_url('guests/followup-manual/' . (int) $guest['id']) ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-clock-history"></i> <?= lang('Guests.viewFollowupOpenTimeline') ?>
                                </a>
                            </div>
                        <?php else: ?>
                            <p class="text-muted small"><?= lang('Guests.followupNoActionsScheduled') ?></p>
                        <?php endif; ?>

                        <?php if (auth()->user()->can('followup.manage') && !empty($sequences)): ?>
                            <hr>
                            <h6 class="small fw-semibold mb-2"><?= lang('Guests.viewAssignSequenceTitle') ?></h6>
                            <form id="assignSeqForm" class="d-flex gap-2 align-items-center">
                                <?= csrf_field() ?>
                                <select name="sequence_id" id="assignSeqSelect" class="form-select form-select-sm flex-grow-1">
                                    <?php foreach ($sequences as $seq): ?>
                                        <option value="<?= (int) $seq['id'] ?>"><?= esc($seq['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-sm btn-outline-primary flex-shrink-0">
                                    <i class="bi bi-plus-circle"></i> <?= lang('Guests.viewAssignSequenceBtn') ?>
                                </button>
                            </form>
                        <?php elseif (auth()->user()->can('followup.manage')): ?>
                            <p class="text-muted small mt-2"><?= lang('Guests.viewAssignSequenceNone') ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="tab-pane fade" id="notes">
                        <?php if ($guest['notes']): ?>
                            <p><?= nl2br((string) esc($guest['notes'])) ?></p>
                        <?php else: ?>
                            <p class="text-muted"><?= lang('Guests.noNotesYet') ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        'use strict';
        const form = document.getElementById('assignSeqForm');
        if (!form) {
            return;
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        const csrfHeader = document.querySelector('meta[name="csrf-header"]')?.content ?? 'X-CSRF-TOKEN';
        const assignUrl = <?= json_encode(base_url('guests/assign-sequence/' . (int) $guest['id'])) ?>;

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