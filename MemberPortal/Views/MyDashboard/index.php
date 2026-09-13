<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php
$locale = service('request')->getLocale() ?: config('App')->defaultLocale;

$formatLocalizedDate = static function (?string $value, string $pattern, string $locale): string {
    if ($value === null || trim($value) === '' || $value === '0000-00-00') {
        return '-';
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return (string) $value;
    }
    if (class_exists(\IntlDateFormatter::class)) {
        $formatter = new \IntlDateFormatter($locale, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE, date_default_timezone_get());
        $formatter->setPattern($pattern);
        $formatted = $formatter->format($timestamp);
        if (is_string($formatted) && $formatted !== '') {
            return $formatted;
        }
    }
    return date('Y-m-d', $timestamp);
};

$formatCurrency = static function ($amount): string {
    return number_format((float) ($amount ?? 0), 2);
};

$profile      = $identity['profile'] ?? [];
$parishName   = $identity['parish_name'] ?? '';
$displayName  = trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''));
if ($displayName === '') {
    $displayName = auth()->user()->username ?? auth()->user()->email ?? 'User';
}

$membershipStatus = (string) ($profile['membership_status'] ?? 'pending');
$memberStatusColor = match ($membershipStatus) {
    'active' => 'success',
    'inactive' => 'danger',
    'visitor' => 'info',
    'pending' => 'warning',
    default => 'secondary'
};

$isBaptized   = (bool) ($membership['is_baptized'] ?? 0);
$isConfirmed  = (bool) ($membership['is_confirmed'] ?? 0);
$isCommunicant = (bool) ($membership['is_communicant'] ?? 0);
$isGoodStanding = (bool) ($membership['good_standing_override'] ?? 0) || $membershipStatus === 'active';

$attendancePct = 0;
if (!empty($attendance_summary['total_services']) && $attendance_summary['total_services'] > 0) {
    $attendancePct = (int) round(($attendance_summary['attended_count'] / $attendance_summary['total_services']) * 100);
}
?>

<div class="row g-4">
    <!-- Identity Card -->
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="flex-shrink-0">
                        <?php $profilePicturePath = trim((string) ($profile['profile_picture'] ?? ''));
                        $profilePictureUrl = ($profilePicturePath !== '' && !empty($identity['user_id']))
                            ? base_url('media/profile-picture/' . (int) $identity['user_id'])
                            : '';
                        ?>
                        <?php if ($profilePictureUrl !== ''): ?>
                            <img src="<?= esc($profilePictureUrl) ?>" alt="" class="rounded-circle" style="width: 64px; height: 64px; object-fit: cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 64px; height: 64px;">
                                <i class="bi bi-person-fill fs-3"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="flex-grow-1">
                        <h4 class="mb-1"><?= esc($displayName) ?></h4>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge bg-<?= $memberStatusColor ?>"><?= esc(ucfirst($membershipStatus)) ?></span>
                            <?php if ($isGoodStanding): ?>
                                <span class="badge bg-success"><?= lang('Dashboard.goodStanding') ?></span>
                            <?php endif; ?>
                            <?php if (!empty($parishName)): ?>
                                <small class="text-muted"><i class="bi bi-geo-alt"></i> <?= esc($parishName) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <a href="<?= base_url('profile') ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil"></i> <?= lang('App.edit') ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Membership Status + Sacrament Flags -->
    <div class="col-md-6">
        <?php if (!empty($sacrament_discrepancies)): ?>
            <div class="alert alert-warning small mb-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <strong><?= lang('Dashboard.sacramentDiscrepancyHeading') ?? 'Sacrament data mismatch' ?></strong>
                <p class="mb-1"><?= lang('Dashboard.sacramentDiscrepancyIntro') ?? 'Your sacramental records disagree with the membership flags shown below:' ?></p>
                <ul class="mb-0">
                    <?php foreach ($sacrament_discrepancies as $d): ?>
                        <li><?= esc($d['message'] ?? '') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-person-badge"></i> <?= lang('Dashboard.myMembership') ?></h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-droplet-fill <?= $isBaptized ? 'text-primary' : 'text-muted' ?>"></i>
                            <span><?= lang('Dashboard.sacramentBaptism') ?></span>
                            <?php if ($isBaptized): ?>
                                <i class="bi bi-check-circle-fill text-success ms-auto"></i>
                            <?php else: ?>
                                <i class="bi bi-circle text-muted ms-auto"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-droplet-half <?= $isConfirmed ? 'text-primary' : 'text-muted' ?>"></i>
                            <span><?= lang('Dashboard.sacramentConfirmation') ?></span>
                            <?php if ($isConfirmed): ?>
                                <i class="bi bi-check-circle-fill text-success ms-auto"></i>
                            <?php else: ?>
                                <i class="bi bi-circle text-muted ms-auto"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-bread-slice <?= $isCommunicant ? 'text-primary' : 'text-muted' ?>"></i>
                            <span><?= lang('Dashboard.sacramentCommunicant') ?></span>
                            <?php if ($isCommunicant): ?>
                                <i class="bi bi-check-circle-fill text-success ms-auto"></i>
                            <?php else: ?>
                                <i class="bi bi-circle text-muted ms-auto"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (!empty($status_history)): ?>
                    <hr>
                    <h6 class="text-muted"><?= lang('Dashboard.statusHistory') ?></h6>
                    <div class="list-group list-group-flush" style="max-height: 200px; overflow-y: auto;">
                        <?php foreach (array_slice($status_history, 0, 10) as $entry): ?>
                            <div class="list-group-item px-0 py-2">
                                <small class="text-muted d-block"><?= esc($formatLocalizedDate($entry['created_at'] ?? '', 'MMM d, y', $locale)) ?></small>
                                <span class="small"><?= esc(($entry['old_value'] ?? '-') . ' → ' . ($entry['new_value'] ?? '-')) ?></span>
                                <?php if (!empty($entry['reason'])): ?>
                                    <small class="text-muted d-block"><?= esc($entry['reason']) ?></small>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <hr>
                    <p class="text-muted small mb-0"><?= lang('Dashboard.noStatusHistory') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Attendance Summary -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-calendar-check"></i> <?= lang('Dashboard.myAttendance') ?></h5>
            </div>
            <div class="card-body">
                <div class="row text-center g-3 mb-3">
                    <div class="col-4">
                        <h3 class="mb-0 text-primary"><?= (int) ($attendance_summary['attended_count'] ?? 0) ?></h3>
                        <small class="text-muted"><?= lang('Dashboard.attendedServices') ?></small>
                    </div>
                    <div class="col-4">
                        <h3 class="mb-0"><?= (int) ($attendance_summary['total_services'] ?? 0) ?></h3>
                        <small class="text-muted"><?= lang('Dashboard.totalServices') ?></small>
                    </div>
                    <div class="col-4">
                        <h3 class="mb-0 text-success"><?= (int) ($attendance_summary['communion_count'] ?? 0) ?></h3>
                        <small class="text-muted"><?= lang('Dashboard.communions') ?></small>
                    </div>
                </div>
                <div class="progress mb-3" style="height: 8px;">
                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?= min(100, $attendancePct) ?>%;" aria-valuenow="<?= $attendancePct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <small class="text-muted d-block mb-2"><?= lang('Dashboard.attendanceRate', [$attendancePct]) ?></small>

                <?php if (!empty($attendance_recent)): ?>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th><?= lang('App.colDate') ?></th>
                                <th><?= lang('Dashboard.colService') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($attendance_recent, 0, 5) as $record): ?>
                                <tr>
                                    <td><?= esc($formatLocalizedDate($record['service_date'] ?? '', 'MMM d, y', $locale)) ?></td>
                                    <td><?= esc($record['service'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-muted small mb-0"><?= lang('Dashboard.noAttendanceRecords') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- My Sacraments -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-bible"></i> <?= lang('Dashboard.mySacraments') ?></h5>
            </div>
            <div class="card-body">
                <?php if (!empty($sacraments)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th><?= lang('Dashboard.colSacrament') ?></th>
                                    <th><?= lang('App.colDate') ?></th>
                                    <th><?= lang('Dashboard.colOfficiant') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sacraments as $record): ?>
                                    <tr>
                                        <td><?= esc(ucfirst((string) ($record['sacrament_type'] ?? ''))) ?></td>
                                        <td><?= esc($formatLocalizedDate($record['sacrament_date'] ?? '', 'MMM d, y', $locale)) ?></td>
                                        <td><?= esc(trim(($record['officiant_first_name'] ?? '') . ' ' . ($record['officiant_last_name'] ?? '')) ?: '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0"><?= lang('Dashboard.noSacramentRecords') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- My Leadership Positions -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-award"></i> <?= lang('Dashboard.myLeadership') ?></h5>
            </div>
            <div class="card-body">
                <?php if (!empty($leadership)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th><?= lang('Dashboard.colTitle') ?></th>
                                    <th><?= lang('Dashboard.colScope') ?></th>
                                    <th><?= lang('Dashboard.colStatus') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($leadership as $pos): ?>
                                    <tr>
                                        <td><?= esc($pos['title'] ?? '') ?></td>
                                        <td>
                                            <?php
                                            $scopeName = $pos['parish_name'] ?? $pos['diocese_name'] ?? $pos['province_name'] ?? '';
                                            echo esc($scopeName ?: ucfirst($pos['scope_type'] ?? ''));
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ((int) ($pos['active'] ?? 0) === 1): ?>
                                                <span class="badge bg-success"><?= lang('Dashboard.active') ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><?= lang('Dashboard.inactive') ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0"><?= lang('Dashboard.noLeadershipPositions') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- My Family -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-house-heart"></i> <?= lang('Dashboard.myFamily') ?></h5>
            </div>
            <div class="card-body">
                <?php if (!empty($family)): ?>
                    <h6><?= esc($family['family_name'] ?? '') ?></h6>
                    <?php if (!empty($family_members)): ?>
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th><?= lang('Dashboard.colName') ?></th>
                                    <th><?= lang('Dashboard.colRelationship') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($family_members as $member): ?>
                                    <tr>
                                        <td><?= esc(trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')) ?: '-') ?></td>
                                        <td><?= esc($member['relationship'] ?? '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-muted small mb-0"><?= lang('Dashboard.noFamilyMembers') ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-muted small mb-0"><?= lang('Dashboard.noFamilyAssigned') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- My Pledges -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-cash-coin"></i> <?= lang('Dashboard.myPledges') ?></h5>
            </div>
            <div class="card-body">
                <?php if (!empty($pledges)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th><?= lang('Dashboard.colYear') ?></th>
                                    <th class="text-end"><?= lang('Dashboard.colPledged') ?></th>
                                    <th class="text-end"><?= lang('Dashboard.colFulfilled') ?></th>
                                    <th><?= lang('Dashboard.colStatus') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pledges as $pledge): ?>
                                    <tr>
                                        <td><?= (int) ($pledge['pledge_year'] ?? 0) ?></td>
                                        <td class="text-end"><?= esc($formatCurrency($pledge['pledged_amount'] ?? 0)) ?></td>
                                        <td class="text-end"><?= esc($formatCurrency($pledge['fulfilled_amount'] ?? 0)) ?></td>
                                        <td>
                                            <?php
                                            $statusColor = match ($pledge['fulfillment_status'] ?? '') {
                                                'fulfilled' => 'success',
                                                'partial' => 'warning',
                                                'pledged' => 'info',
                                                'cancelled' => 'danger',
                                                default => 'secondary',
                                            };
                                            ?>
                                            <span class="badge bg-<?= $statusColor ?>"><?= esc(ucfirst($pledge['fulfillment_status'] ?? '')) ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0"><?= lang('Dashboard.noPledges') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- My Giving History -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-receipt"></i> <?= lang('Dashboard.myGiving') ?></h5>
            </div>
            <div class="card-body">
                <?php if (!empty($donations)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th><?= lang('App.colDate') ?></th>
                                    <th class="text-end"><?= lang('Dashboard.colAmount') ?></th>
                                    <th><?= lang('Dashboard.colFund') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($donations, 0, 10) as $donation): ?>
                                    <tr>
                                        <td><?= esc($formatLocalizedDate($donation['date'] ?? '', 'MMM d, y', $locale)) ?></td>
                                        <td class="text-end"><?= esc($formatCurrency($donation['amount'] ?? 0)) ?></td>
                                        <td><?= esc($donation['fund_name'] ?? '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0"><?= lang('Dashboard.noDonations') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- My Onboarding Milestones -->
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-list-check"></i> <?= lang('Dashboard.myOnboarding') ?></h5>
            </div>
            <div class="card-body">
                <?php if (!empty($onboarding)): ?>
                    <div class="row g-3">
                        <?php
                        $onboardingFields = [
                            'profile_completed' => lang('Dashboard.onboardingProfileComplete'),
                            'parish_affiliated' => lang('Dashboard.onboardingParishAffiliated'),
                            'membership_status_set' => lang('Dashboard.onboardingMembershipStatus'),
                            'family_linked' => lang('Dashboard.onboardingFamilyLinked'),
                        ];
                        foreach ($onboardingFields as $field => $label):
                            $done = (bool) ($onboarding[$field] ?? false);
                        ?>
                            <div class="col-md-3">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi <?= $done ? 'bi-check-circle-fill text-success' : 'bi-circle text-muted' ?>"></i>
                                    <span class="small <?= $done ? '' : 'text-muted' ?>"><?= esc($label) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0"><?= lang('Dashboard.noOnboardingData') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
