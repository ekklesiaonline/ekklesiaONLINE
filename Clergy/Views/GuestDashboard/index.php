<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<style>
    .hover-lift {
        transition: transform .15s ease, box-shadow .15s ease;
    }

    .hover-lift:hover {
        transform: translateY(-2px);
        box-shadow: 0 .35rem .75rem rgba(0, 0, 0, .12);
    }
</style>

<?php
// ─── KPI tile helper ──────────────────────────────────────────────────────────
// Returns Bootstrap outline colour: 'primary' | 'success' | 'warning' | 'info' | 'secondary'
$kpiTile = static function (
    string $value,
    string $label,
    string $icon,
    string $color,
    string $href = ''
): string {
    $inner = <<<HTML
        <div class="card h-100 border-{$color}">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="fs-2 text-{$color}"><i class="bi {$icon}"></i></span>
                <div>
                    <div class="fw-bold fs-4 text-{$color}">{$value}</div>
                    <div class="text-muted small">{$label}</div>
                </div>
            </div>
        </div>
    HTML;

    if ($href !== '') {
        return '<a href="' . esc($href) . '" class="text-decoration-none">' . $inner . '</a>';
    }

    return $inner;
};
?>

<p class="text-muted mb-4"><?= lang('Guests.hubSubtitle') ?></p>


<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <?= $kpiTile(
            (string) $notFollowedUp,
            lang('Guests.hubKpiNotFollowedUp'),
            'bi-hourglass-split',
            'warning',
            base_url('guests?followup_stage=pending')
        ) ?>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <?= $kpiTile(
            (string) $inProgressCount,
            lang('Guests.hubKpiFollowupInProgress'),
            'bi-arrow-repeat',
            'primary',
            base_url('guests?followup_stage=inprogress')
        ) ?>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <?= $kpiTile(
            (string) $convertedCount,
            lang('Guests.hubKpiConverted'),
            'bi-person-check-fill',
            'success',
            base_url('guest-conversions')
        ) ?>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <?= $kpiTile(
            (string) $pendingCalls,
            lang('Guests.hubKpiPendingCalls'),
            'bi-telephone-fill',
            $pendingCalls > 0 ? 'danger' : 'secondary',
            base_url('followup-workflow/call-tasks')
        ) ?>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <?= $kpiTile(
            (string) $sequenceCount,
            lang('Guests.hubKpiActiveSequences'),
            'bi-diagram-3-fill',
            'info',
            base_url('followup-workflow/sequences')
        ) ?>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <?= $kpiTile(
            (string) $templateCount,
            lang('Guests.hubKpiTemplates'),
            'bi-file-earmark-text-fill',
            'secondary',
            base_url('followup-workflow/templates')
        ) ?>
    </div>
</div>


<h5 class="mb-3"><?= lang('Guests.hubSectionActions') ?></h5>
<div class="row g-3 mb-4">

    <?php
    $cards = [
        [
            'href'  => base_url('guests'),
            'icon'  => 'bi-people',
            'color' => 'primary',
            'label' => lang('Guests.hubLinkAllGuests'),
            'desc'  => lang('Guests.hubDescAllGuests'),
            'perm'  => 'guest.view',
        ],
        [
            'href'  => base_url('guests/create'),
            'icon'  => 'bi-person-plus',
            'color' => 'success',
            'label' => lang('Guests.hubLinkAddGuest'),
            'desc'  => lang('Guests.hubDescAddGuest'),
            'perm'  => 'guest.create',
        ],
        [
            'href'  => base_url('guest-conversions'),
            'icon'  => 'bi-person-up',
            'color' => 'success',
            'label' => lang('Guests.hubLinkConversions'),
            'desc'  => lang('Guests.hubDescConversions'),
            'perm'  => 'guest.view',
        ],
        [
            'href'  => base_url('guests/import'),
            'icon'  => 'bi-upload',
            'color' => 'secondary',
            'label' => lang('Guests.hubLinkImport'),
            'desc'  => lang('Guests.hubDescImport'),
            'perm'  => 'guest.create',
        ],
    ];

    foreach ($cards as $card):
        if (! auth()->user()->can($card['perm'])) {
            continue;
        }
    ?>
        <div class="col-sm-6 col-lg-3">
            <a href="<?= esc($card['href']) ?>" class="text-decoration-none">
                <div class="card h-100 border-<?= esc($card['color']) ?> hover-lift">
                    <div class="card-body">
                        <i class="bi <?= esc($card['icon']) ?> fs-3 text-<?= esc($card['color']) ?> mb-2 d-block"></i>
                        <h6 class="card-title mb-1"><?= esc($card['label']) ?></h6>
                        <p class="card-text text-muted small mb-0"><?= esc($card['desc']) ?></p>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<?php if (auth()->user()->can('followup.view')): ?>
    <h5 class="mb-3"><?= lang('Guests.hubSectionFollowup') ?></h5>
    <div class="row g-3 mb-4">
        <?php
        $wfCards = [
            [
                'href'  => base_url('followup-workflow/call-tasks'),
                'icon'  => 'bi-telephone',
                'color' => 'warning',
                'label' => lang('Guests.hubLinkCallTasks'),
                'desc'  => lang('Guests.hubDescCallTasks'),
            ],
            [
                'href'  => base_url('followup-workflow/sequences'),
                'icon'  => 'bi-diagram-3',
                'color' => 'info',
                'label' => lang('Guests.hubLinkSequences'),
                'desc'  => lang('Guests.hubDescSequences'),
            ],
            [
                'href'  => base_url('followup-workflow/templates'),
                'icon'  => 'bi-file-earmark-text',
                'color' => 'secondary',
                'label' => lang('Guests.hubLinkTemplates'),
                'desc'  => lang('Guests.hubDescTemplates'),
            ],
        ];
        foreach ($wfCards as $wc):
        ?>
            <div class="col-sm-6 col-lg-4">
                <a href="<?= esc($wc['href']) ?>" class="text-decoration-none">
                    <div class="card h-100 border-<?= esc($wc['color']) ?> hover-lift">
                        <div class="card-body">
                            <i class="bi <?= esc($wc['icon']) ?> fs-3 text-<?= esc($wc['color']) ?> mb-2 d-block"></i>
                            <h6 class="card-title mb-1"><?= esc($wc['label']) ?></h6>
                            <p class="card-text text-muted small mb-0"><?= esc($wc['desc']) ?></p>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (auth()->user()->can('report.view')): ?>
    <h5 class="mb-3"><?= lang('Guests.hubSectionReports') ?></h5>
    <div class="row g-3 mb-4">
        <?php
        $reportCards = [
            [
                'href'  => base_url('guests/reports/followup-completion'),
                'icon'  => 'bi-bar-chart-line',
                'color' => 'primary',
                'label' => lang('Guests.hubLinkCompletionReport'),
                'desc'  => lang('Guests.hubDescCompletionReport'),
            ],
            [
                'href'  => base_url('guests/reports/roi-second-visit'),
                'icon'  => 'bi-trophy',
                'color' => 'warning',
                'label' => lang('Guests.hubLinkRoiReport'),
                'desc'  => lang('Guests.hubDescRoiReport'),
            ],
        ];
        foreach ($reportCards as $rc):
        ?>
            <div class="col-sm-6 col-lg-4">
                <a href="<?= esc($rc['href']) ?>" class="text-decoration-none">
                    <div class="card h-100 border-<?= esc($rc['color']) ?> hover-lift">
                        <div class="card-body">
                            <i class="bi <?= esc($rc['icon']) ?> fs-3 text-<?= esc($rc['color']) ?> mb-2 d-block"></i>
                            <h6 class="card-title mb-1"><?= esc($rc['label']) ?></h6>
                            <p class="card-text text-muted small mb-0"><?= esc($rc['desc']) ?></p>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>


<div class="row g-3">


    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history me-1"></i><?= lang('Guests.pageTitle') ?></span>
                <a href="<?= base_url('guests') ?>" class="btn btn-sm btn-outline-primary">
                    <?= lang('App.viewAll') ?>
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentGuests)): ?>
                    <p class="text-muted text-center py-4 mb-0">
                        <i class="bi bi-people fs-2 d-block mb-2"></i>
                        <?= lang('Guests.noGuestsFound') ?>
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th><?= lang('Guests.colName') ?></th>
                                    <th><?= lang('Guests.colVisitDate') ?></th>
                                    <th><?= lang('Guests.colStatus') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentGuests as $g):
                                    $name = esc(trim($g['first_name'] . ' ' . $g['last_name']));
                                    $prog = $recentProgressMap[(int) ($g['person_id'] ?? 0)] ?? null;
                                    if (!empty($g['converted_to_member_at'])) {
                                        $statusColor = 'success';
                                        $statusLabel = lang('Guests.statusConverted');
                                    } elseif ($g['followed_up']) {
                                        $statusColor = 'success';
                                        $statusLabel = lang('Guests.statusFollowedUp');
                                    } elseif ($prog && $prog['done'] > 0 && $prog['pending'] > 0) {
                                        $statusColor = 'primary';
                                        $statusLabel = lang('Guests.followupInProgress');
                                    } else {
                                        $statusColor = 'warning';
                                        $statusLabel = lang('Guests.statusPending');
                                    }
                                ?>
                                    <tr>
                                        <td>
                                            <a href="<?= base_url('guests/view/' . (int) $g['id']) ?>"
                                                class="text-decoration-none">
                                                <?= $name ?>
                                            </a>
                                        </td>
                                        <td><?= esc($g['visit_date'] ?? '-') ?></td>
                                        <td>
                                            <span class="badge border border-<?= $statusColor ?> text-<?= $statusColor ?> fw-normal">
                                                <?= esc($statusLabel) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>


    <?php if (auth()->user()->can('followup.view')): ?>
        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-telephone me-1"></i><?= lang('Guests.hubLinkCallTasks') ?></span>
                    <?php if ($pendingCalls > 0): ?>
                        <span class="badge border border-warning text-warning fw-normal">
                            <?= (int) $pendingCalls ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($pendingCallTasks)): ?>
                        <p class="text-muted text-center py-4 mb-0">
                            <i class="bi bi-telephone-x fs-2 d-block mb-2"></i>
                            <?= lang('Guests.callTasksEmpty') ?>
                        </p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($pendingCallTasks as $ct): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-semibold small">
                                            <?= esc(trim(($ct['guest_first_name'] ?? '') . ' ' . ($ct['guest_last_name'] ?? ''))) ?>
                                        </div>
                                        <div class="text-muted" style="font-size:.75rem;">
                                            <?= esc($ct['scheduled_date'] ?? '') ?>
                                        </div>
                                    </div>
                                    <a href="<?= base_url('followup-workflow/call-tasks') ?>"
                                        class="btn btn-sm btn-outline-warning">
                                        <i class="bi bi-telephone"></i>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if ($pendingCalls > 5): ?>
                            <div class="text-center py-2">
                                <a href="<?= base_url('followup-workflow/call-tasks') ?>" class="btn btn-sm btn-outline-secondary">
                                    <?= lang('App.viewAll') ?> (<?= (int) $pendingCalls ?>)
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<?= $this->endSection() ?>