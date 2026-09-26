<?= $this->extend('Worship\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><?= lang('Services.serviceDetails') ?></h2>
    <div>
        <?php if (auth()->user()->can('worship.view')): ?>
            <a href="<?= base_url('worship-planning/service/' . $service['id']) ?>" class="btn btn-primary">
                <i class="bi bi-music-note-list" aria-hidden="true"></i> <?= lang('Services.worshipPlanning') ?>
            </a>
        <?php endif; ?>
        <?php
        // Only show to admins (systemadmin, provinceadmin, diocesanadmin, parishadmin)
        if (
            auth()->user()->inGroup('systemadmin') ||
            auth()->user()->inGroup('provinceadmin') ||
            auth()->user()->inGroup('dioceseadmin') ||
            auth()->user()->inGroup('parishadmin')
        ):
        ?>
            <a href="<?= base_url('attendance/manual?service_id=' . $service['id']) ?>" class="btn btn-outline-success">
                <i class="bi bi-check2-square" aria-hidden="true"></i> <?= lang('Services.markAttendance') ?>
            </a>
        <?php endif; ?>
        <?php if (auth()->user()->can('service.edit')): ?>
            <a href="<?= base_url('services/edit/' . $service['id']) ?>" class="btn btn-warning">
                <i class="bi bi-pencil" aria-hidden="true"></i> <?= lang('App.edit') ?>
            </a>
        <?php endif; ?>
        <a href="<?= base_url('services') ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Services.backToServices') ?>
        </a>
    </div>
</div>

<div class="row">
    <?php
    $metrics = $metrics ?? [];
    $servicePeople = $service_people ?? [];
    $formatMap = [
        'In-Person' => lang('Services.formatInPerson'),
        'Hybrid' => lang('Services.formatHybrid'),
        'Online' => lang('Services.formatOnline'),
    ];
    $classificationMap = [
        'Regular' => lang('Services.classificationRegular'),
        'Special' => lang('Services.classificationSpecial'),
    ];
    $familyMap = [
        'holy_communion' => lang('Services.familyHolyCommunion'),
        'daily_office' => lang('Services.familyDailyOffice'),
        'other' => lang('Services.familyOther'),
    ];
    $scheduleMap = [
        'sunday' => lang('Services.scheduleSunday'),
        'weekday' => lang('Services.scheduleWeekday'),
        'private' => lang('Services.schedulePrivate'),
    ];
    ?>
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <h4><?= esc(liturgical_day_label((string) ($service['liturgical_day'] ?? '')) ?: lang('Services.serviceFallback')) ?></h4>
                <p class="text-muted">
                    <i class="bi bi-calendar3" aria-hidden="true"></i> <?= esc(liturgy_format_long_date((string) ($service['service_date'] ?? ''))) ?><br>
                    <i class="bi bi-clock" aria-hidden="true"></i> <?= !empty($service['service_time']) ? esc(liturgy_format_time((string) ($service['service_time'] ?? ''))) : lang('Services.notAvailable') ?>
                    <?php if (!empty($service['place'])): ?>
                        <br><i class="bi bi-geo-alt" aria-hidden="true"></i> <?= esc($service['place']) ?>
                    <?php endif; ?>
                </p>

                <?php if (!empty($service['preacher']) || !empty($service['celebrant']) || !empty($service['notes'])): ?>
                    <hr>
                    <h5><?= lang('Services.serviceNotes') ?></h5>
                    <?php if (!empty($service['preacher'])): ?>
                        <p class="mb-2"><strong><?= lang('Services.preacher') ?>:</strong> <?= esc($service['preacher']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($service['celebrant'])): ?>
                        <p class="mb-2"><strong><?= lang('Services.celebrant') ?>:</strong> <?= esc($service['celebrant']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($service['notes'])): ?>
                        <p class="mb-0"><strong><?= lang('Services.notes') ?>:</strong><br><?= nl2br(esc((string) ($service['notes'] ?? ''))) ?></p>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (!empty($servicePeople)): ?>
                    <hr>
                    <h5><?= lang('Services.peopleAndRoles') ?></h5>
                    <ul class="mb-0">
                        <?php foreach ($servicePeople as $person): ?>
                            <li>
                                <strong><?= esc($person['person_name']) ?></strong>
                                <?php if (!empty($person['role_name'])): ?>
                                    <span class="text-muted">(<?= esc($person['role_name']) ?>)</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($metrics)): ?>
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0"><?= lang('Services.extendedServiceData') ?></h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($metrics)): ?>
                        <h6><?= lang('Services.metrics') ?></h6>
                        <div class="row small mb-3">
                            <div class="col-md-4"><strong><?= lang('Services.format') ?>:</strong> <?= esc($formatMap[$metrics['format_of_service'] ?? ''] ?? ($metrics['format_of_service'] ?? '-')) ?></div>
                            <div class="col-md-4"><strong><?= lang('Services.classification') ?>:</strong> <?= esc($classificationMap[$metrics['attendance_classification'] ?? ''] ?? ($metrics['attendance_classification'] ?? '-')) ?></div>
                        </div>

                        <?php if (!empty($metrics['service_family']) || !empty($metrics['service_schedule'])): ?>
                            <div class="row small mb-3">
                                <?php if (!empty($metrics['service_family'])): ?>
                                    <div class="col-md-6"><strong><?= lang('Services.serviceFamily') ?>:</strong> <?= esc($familyMap[$metrics['service_family']] ?? $metrics['service_family']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($metrics['service_schedule'])): ?>
                                    <div class="col-md-6"><strong><?= lang('Services.schedule') ?>:</strong> <?= esc($scheduleMap[$metrics['service_schedule']] ?? $metrics['service_schedule']) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="row small mb-3">
                            <div class="col-md-4"><strong><?= lang('Services.present') ?>:</strong> <?= esc((string) ($metrics['reported_present_count'] ?? '-')) ?></div>
                            <div class="col-md-4"><strong><?= lang('Services.communion') ?>:</strong> <?= esc((string) ($metrics['reported_communion_count'] ?? '-')) ?></div>
                            <div class="col-md-4"><strong><?= lang('Services.liveViews') ?>:</strong> <?= esc((string) ($metrics['online_live_views'] ?? '-')) ?></div>
                            <div class="col-md-4"><strong><?= lang('Services.recorded7d') ?>:</strong> <?= esc((string) ($metrics['online_recorded_views_7d'] ?? '-')) ?></div>
                            <div class="col-md-4"><strong><?= lang('Services.peakViewers') ?>:</strong> <?= esc((string) ($metrics['online_peak_viewers'] ?? '-')) ?></div>
                        </div>

                        <?php if (!empty($metrics['languages_json'])): ?>
                            <p class="mb-1"><strong><?= lang('Services.languages') ?>:</strong> <?= esc(implode(', ', $metrics['languages_json'])) ?></p>
                        <?php endif; ?>

                        <?php
                        $hasSacramental = ($metrics['baptism_adult'] ?? 0) || ($metrics['baptism_child'] ?? 0)
                            || ($metrics['confirmation_adult'] ?? 0) || ($metrics['confirmation_teen'] ?? 0)
                            || ($metrics['reception_count'] ?? 0) || ($metrics['marriage_count'] ?? 0)
                            || ($metrics['burial_count'] ?? 0) || ($metrics['eucharist_count'] ?? 0)
                            || ($metrics['daily_office_count'] ?? 0);
                        ?>
                        <?php if ($hasSacramental): ?>
                            <hr>
                            <h6><?= lang('Services.sacramentalCounts') ?></h6>
                            <div class="row small">
                                <div class="col-md-4"><strong><?= lang('Services.baptismAdult') ?>:</strong> <?= esc((string) ($metrics['baptism_adult'] ?? 0)) ?></div>
                                <div class="col-md-4"><strong><?= lang('Services.baptismChild') ?>:</strong> <?= esc((string) ($metrics['baptism_child'] ?? 0)) ?></div>
                                <div class="col-md-4"><strong><?= lang('Services.confirmationAdult') ?>:</strong> <?= esc((string) ($metrics['confirmation_adult'] ?? 0)) ?></div>
                                <div class="col-md-4"><strong><?= lang('Services.confirmationTeen') ?>:</strong> <?= esc((string) ($metrics['confirmation_teen'] ?? 0)) ?></div>
                                <div class="col-md-4"><strong><?= lang('Services.reception') ?>:</strong> <?= esc((string) ($metrics['reception_count'] ?? 0)) ?></div>
                                <div class="col-md-4"><strong><?= lang('Services.marriageCount') ?>:</strong> <?= esc((string) ($metrics['marriage_count'] ?? 0)) ?></div>
                                <div class="col-md-4"><strong><?= lang('Services.burialCount') ?>:</strong> <?= esc((string) ($metrics['burial_count'] ?? 0)) ?></div>
                                <div class="col-md-4"><strong><?= lang('Services.eucharistCount') ?>:</strong> <?= esc((string) ($metrics['eucharist_count'] ?? 0)) ?></div>
                                <div class="col-md-4"><strong><?= lang('Services.dailyOfficeCount') ?>:</strong> <?= esc((string) ($metrics['daily_office_count'] ?? 0)) ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($metrics['online_live_viewers']) || !empty($metrics['online_recorded_views'])): ?>
                            <hr>
                            <h6><?= lang('Services.parochialReportOnline') ?></h6>
                            <div class="row small">
                                <div class="col-md-6"><strong><?= lang('Services.onlineLiveViewers') ?>:</strong> <?= esc((string) ($metrics['online_live_viewers'] ?? '-')) ?></div>
                                <div class="col-md-6"><strong><?= lang('Services.onlineRecordedViews') ?>:</strong> <?= esc((string) ($metrics['online_recorded_views'] ?? '-')) ?></div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><?= lang('Services.serviceDetails') ?></h6>
            </div>
            <div class="card-body">
                <?php if (!empty($service['service'])): ?>
                    <p class="mb-2">
                        <strong><?= lang('Services.serviceTypes') ?>:</strong><br>
                        <?php foreach ($service['service'] as $serviceType): ?>
                            <span class="badge border border-primary text-primary fw-normal me-1"><?= esc(service_type_label((string) $serviceType)) ?></span>
                        <?php endforeach; ?>
                    </p>
                <?php endif; ?>

                <?php if ($service['liturgical_day']): ?>
                    <p class="mb-2">
                        <strong><?= lang('Services.liturgicalDay') ?>:</strong><br>
                        <?= esc(liturgical_day_label((string) ($service['liturgical_day'] ?? ''))) ?>
                    </p>
                <?php endif; ?>



                <hr>

                <p class="mb-0">
                    <strong><?= lang('Services.attendance') ?>:</strong><br>
                    <span class="badge border border-success text-success fw-normal fs-5"><?= $attendance_count ?></span>
                </p>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($attendance)): ?>
    <div class="card mt-3">
        <div class="card-header">
            <h5 class="mb-0"><?= lang('Services.attendanceListWithCount', [count($attendance)]) ?></h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th scope="col"><?= lang('Services.name') ?></th>
                            <th scope="col"><?= lang('Services.email') ?></th>
                            <th scope="col"><?= lang('Services.colCommunion') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance as $record): ?>
                            <tr>
                                <td><?= esc($record['first_name'] . ' ' . $record['last_name']) ?></td>
                                <td><?= esc($record['email']) ?></td>
                                <td>
                                    <?= $record['communicant'] ? '<span class="badge border border-success text-success fw-normal">' . esc(lang('Services.yes')) . '</span>' : '<span class="badge border border-secondary text-secondary fw-normal">' . esc(lang('Services.no')) . '</span>' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>