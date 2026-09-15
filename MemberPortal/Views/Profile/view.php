<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= view('MemberPortal\\Views\\Profile\\_tokens') ?>

<?php
$locale = service('request')->getLocale() ?: config('App')->defaultLocale;
$isElevatedAdmin = $isElevatedAdmin ?? false;
$adminScope = $adminScope ?? null;
// Elevated admins (system/province/diocese) are not tied to a single parish by
// design, so their real membership_status should be shown as-is rather than
// being forced to "pending" just because parish_id is empty.
$membershipStatus = $isElevatedAdmin
    ? (string) ($profile['membership_status'] ?? 'active')
    : (!empty($profile['parish_id']) ? (string) ($profile['membership_status'] ?? 'pending') : 'pending');
$profilePicturePath = trim((string) ($profile['profile_picture'] ?? ''));
$profilePictureUrl = ($profilePicturePath !== '' && !empty($user->id))
    ? base_url('media/profile-picture/' . (int) $user->id)
    : '';
$completionPct = (int) ($completionPct ?? 0);
$parishAffiliation = $parishAffiliation ?? ['province' => '', 'diocese' => '', 'parish' => ''];

$formatLocalizedDate = static function (?string $value, string $pattern, string $locale): string {
    if ($value === null || trim($value) === '' || $value === '0000-00-00') {
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
            \IntlDateFormatter::SHORT,
            date_default_timezone_get()
        );
        $formatter->setPattern($pattern);
        $formatted = $formatter->format($timestamp);
        if (is_string($formatted) && $formatted !== '') {
            return $formatted;
        }
    }

    return date('Y-m-d H:i', $timestamp);
};

$memberStatusColor = match ($membershipStatus) {
    'active' => 'success',
    'inactive' => 'danger',
    'visitor' => 'info',
    'pending' => 'warning',
    default => 'secondary'
};

$completionColor = match (true) {
    $completionPct >= 80 => 'success',
    $completionPct >= 50 => 'warning',
    default => 'danger'
};

$displayName = trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''));
if ($displayName === '') {
    $displayName = $user->username ?? $user->email ?? 'User';
}

$hasAffiliation = !empty($parishAffiliation['province']) || !empty($parishAffiliation['diocese']) || !empty($parishAffiliation['parish']);
$hasAdminScope = $isElevatedAdmin && !empty($adminScope['name']);
// Elevated admins administer a diocese/province/system scope, but may also
// be personally affiliated with one or more parishes (parish_people rows) —
// show both instead of only the admin scope.
$adminParishNames = $hasAdminScope
    ? array_values(array_filter(array_unique(array_column($otherAffiliations ?? [], 'parish_name'))))
    : [];
?>

<div class="profile-page">

    <!-- Profile Header Card -->
    <div class="card profile-header-card mb-3">
        <div class="card-body">
            <div class="d-flex flex-column flex-md-row align-items-center gap-3">
                <div class="flex-shrink-0">
                    <?php if ($profilePictureUrl !== ''): ?>
                        <img src="<?= esc($profilePictureUrl, 'attr') ?>"
                            alt="<?= lang('Profile.profilePicture') ?>"
                            class="profile-avatar"
                            onerror="this.classList.add('d-none');this.nextElementSibling.classList.remove('d-none');">
                    <?php endif; ?>
                    <div class="profile-avatar-wrap <?= $profilePictureUrl !== '' ? 'd-none' : '' ?>">
                        <i class="bi bi-person-circle profile-avatar-placeholder"></i>
                    </div>
                </div>
                <div class="flex-grow-1 text-center text-md-start">
                    <h3 class="mb-1"><?= esc($displayName) ?></h3>
                    <p class="text-muted mb-2"><?= esc($user->email) ?></p>
                    <span class="badge border border-<?= $memberStatusColor ?> text-<?= $memberStatusColor ?> fw-normal">
                        <?= lang('Profile.status' . ucfirst($membershipStatus)) ?>
                    </span>
                </div>
                <div class="flex-shrink-0 text-center" style="min-width: 160px;">
                    <div class="completion-pct text-<?= $completionColor ?>"><?= $completionPct ?>%</div>
                    <div class="text-muted small mb-2"><?= lang('Profile.profileCompletion') ?></div>
                    <div class="completion-bar">
                        <div class="completion-bar-fill bg-<?= $completionColor ?>" style="width: <?= $completionPct ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Bar -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <nav aria-label="breadcrumb" class="d-none d-md-block">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>"><?= lang('App.navDashboard') ?></a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= lang('Profile.pageTitle') ?></li>
            </ol>
        </nav>
        <div class="d-flex gap-2">
            <?php if (empty($profile['parish_id']) && !$isElevatedAdmin): ?>
                <?php if (($registrationStatus ?? '') === 'pending_review'): ?>
                    <button type="button" class="btn btn-warning" disabled>
                        <i class="bi bi-hourglass-split"></i> <?= lang('Profile.joinParishPendingApproval') ?>
                    </button>
                <?php else: ?>
                    <a href="<?= base_url('profile/join-parish') ?>" class="btn btn-success">
                        <i class="bi bi-building-add"></i> <?= lang('Profile.joinParishTitle') ?>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
            <a href="<?= base_url('profile/edit') ?>" class="btn btn-primary">
                <i class="bi bi-pencil"></i> <?= lang('Profile.editProfile') ?>
            </a>
            <a href="<?= base_url('dashboard') ?>" class="btn btn-outline-secondary">
                <i class="bi bi-house"></i> <?= lang('Profile.backToDashboard') ?>
            </a>
        </div>
    </div>

    <!-- Tab Navigation -->
    <ul class="nav nav-tabs profile-tabs mb-0" id="profileTabPanes" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview-pane" type="button" role="tab" aria-selected="true">
                <i class="bi bi-grid-3x3-gap"></i> <?= lang('Profile.tabOverview') ?>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal-pane" type="button" role="tab">
                <i class="bi bi-person"></i> <?= lang('Profile.tabPersonal') ?>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="contact-tab" data-bs-toggle="tab" data-bs-target="#contact-pane" type="button" role="tab">
                <i class="bi bi-telephone"></i> <?= lang('Profile.tabContact') ?>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="spiritual-tab" data-bs-toggle="tab" data-bs-target="#spiritual-pane" type="button" role="tab">
                <i class="bi bi-heart"></i> <?= lang('Profile.tabSpiritual') ?>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="account-tab" data-bs-toggle="tab" data-bs-target="#account-pane" type="button" role="tab">
                <i class="bi bi-gear"></i> <?= lang('Profile.tabAccount') ?>
            </button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="profileTabContent">

        <!-- Overview Tab -->
        <div class="tab-pane fade show active" id="overview-pane" role="tabpanel">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-person"></i> <?= lang('Profile.sectionPersonal') ?></h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item px-0">
                                    <div class="text-muted small"><?= lang('Profile.firstName') ?></div>
                                    <div><?= esc($profile['first_name'] ?? '-') ?></div>
                                </li>
                                <li class="list-group-item px-0">
                                    <div class="text-muted small"><?= lang('Profile.lastName') ?></div>
                                    <div><?= esc($profile['last_name'] ?? '-') ?></div>
                                </li>
                                <li class="list-group-item px-0">
                                    <div class="text-muted small"><?= lang('Profile.phone') ?></div>
                                    <div><?= esc(\App\Libraries\PhoneHelper::display($profile['phone'] ?? null) ?: '-') ?></div>
                                </li>
                                <li class="list-group-item px-0">
                                    <div class="text-muted small"><?= lang('Profile.email') ?></div>
                                    <div class="text-truncate"><?= esc($user->email) ?></div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-building"></i> <?= lang('Profile.parishAffiliation') ?></h5>
                        </div>
                        <div class="card-body">
                            <?php if ($hasAdminScope): ?>
                                <dl class="row affiliation-list mb-0">
                                    <dt class="col-sm-4"><?= lang('Profile.adminScopeLabel') ?></dt>
                                    <dd class="col-sm-8"><?= esc($adminScope['name']) ?></dd>
                                    <?php if (!empty($adminParishNames)): ?>
                                        <dt class="col-sm-4"><?= lang('Profile.parishesLabel') ?></dt>
                                        <dd class="col-sm-8"><?= esc(implode(', ', $adminParishNames)) ?></dd>
                                    <?php endif; ?>
                                </dl>
                            <?php elseif ($hasAffiliation): ?>
                                <dl class="row affiliation-list mb-0">
                                    <dt class="col-sm-4"><?= lang('Profile.province') ?></dt>
                                    <dd class="col-sm-8"><?= esc($parishAffiliation['province'] ?: '-') ?></dd>
                                    <dt class="col-sm-4"><?= lang('Profile.diocese') ?></dt>
                                    <dd class="col-sm-8"><?= esc($parishAffiliation['diocese'] ?: '-') ?></dd>
                                    <dt class="col-sm-4"><?= lang('Profile.parish') ?></dt>
                                    <dd class="col-sm-8"><?= esc($parishAffiliation['parish'] ?: '-') ?></dd>
                                </dl>
                            <?php else: ?>
                                <p class="text-muted mb-0"><i class="bi bi-info-circle"></i> <?= lang('Profile.noAffiliation') ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="bi bi-gear"></i> <?= lang('Profile.accountInformation') ?></h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item px-0">
                                    <div class="text-muted small"><?= lang('Profile.canonicalPersonId') ?></div>
                                    <div>
                                        <?php $personId = (int) ($profile['person_id'] ?? 0); ?>
                                        <?php if ($personId > 0): ?>
                                            <span class="badge bg-light text-dark border font-monospace">P<?= $personId ?></span>
                                            <small class="text-muted d-block"><?= lang('Profile.canonicalPersonIdHint') ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </div>
                                </li>
                                <li class="list-group-item px-0">
                                    <div class="text-muted small"><?= lang('Profile.username') ?></div>
                                    <div><?= esc($user->username ?? $user->email) ?></div>
                                </li>
                                <li class="list-group-item px-0">
                                    <div class="text-muted small"><?= lang('Profile.memberSince') ?></div>
                                    <div><?= esc($formatLocalizedDate((string) ($profile['created_at'] ?? ''), 'MMMM d, y', $locale)) ?></div>
                                </li>
                                <li class="list-group-item px-0">
                                    <div class="text-muted small"><?= lang('Profile.lastUpdated') ?></div>
                                    <div><?= esc($formatLocalizedDate((string) ($profile['updated_at'] ?? ''), 'MMMM d, y h:mm a', $locale)) ?></div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-heart"></i> <?= lang('Profile.spiritualInformation') ?></h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item px-0">
                                    <div class="text-muted small"><?= lang('Profile.spiritualGifts') ?></div>
                                    <div><?= esc($profile['spiritual_gifts'] ?? '-') ?></div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-12">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-buildings"></i> <?= lang('Profile.otherParishes') ?></h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($otherAffiliations)): ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($otherAffiliations as $affiliation): ?>
                                        <?php if (!empty($affiliation['is_primary'])) continue; ?>
                                        <?php
                                        $affStatus = (string) ($affiliation['membership_status'] ?? 'regular_attender');
                                        $affStatusColor = match ($affStatus) {
                                            'active' => 'success',
                                            'inactive' => 'danger',
                                            'visitor' => 'info',
                                            'pending' => 'warning',
                                            default => 'secondary'
                                        };
                                        ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <?= esc($affiliation['parish_name'] ?? '-') ?>
                                            <span class="badge border border-<?= $affStatusColor ?> text-<?= $affStatusColor ?> fw-normal">
                                                <?= lang('Profile.status' . ucfirst($affStatus)) ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted mb-0"><i class="bi bi-info-circle"></i> <?= lang('Profile.noOtherParishes') ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Personal Tab -->
        <div class="tab-pane fade" id="personal-pane" role="tabpanel">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><?= lang('Profile.sectionPersonal') ?></h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item px-0">
                            <div class="text-muted small"><?= lang('Profile.firstName') ?></div>
                            <div><?= esc($profile['first_name'] ?? '-') ?></div>
                        </li>
                        <li class="list-group-item px-0">
                            <div class="text-muted small"><?= lang('Profile.middleName') ?></div>
                            <div><?= esc($profile['middle_name'] ?? '-') ?></div>
                        </li>
                        <li class="list-group-item px-0">
                            <div class="text-muted small"><?= lang('Profile.lastName') ?></div>
                            <div><?= esc($profile['last_name'] ?? '-') ?></div>
                        </li>
                        <li class="list-group-item px-0">
                            <div class="text-muted small"><?= lang('Profile.email') ?></div>
                            <div><?= esc($user->email) ?></div>
                        </li>
                        <li class="list-group-item px-0">
                            <div class="text-muted small"><?= lang('Profile.membershipStatus') ?></div>
                            <div>
                                <span class="badge border border-<?= $memberStatusColor ?> text-<?= $memberStatusColor ?> fw-normal">
                                    <?= lang('Profile.status' . ucfirst($membershipStatus)) ?>
                                </span>
                            </div>
                        </li>
                        <li class="list-group-item px-0">
                            <div class="text-muted small"><?= lang('Profile.birthDate') ?></div>
                            <div>
                                <?php if (!empty($profile['birth_date']) && $profile['birth_date'] !== '0000-00-00'): ?>
                                    <?= esc($formatLocalizedDate((string) ($profile['birth_date'] ?? ''), 'MMMM d, y', $locale)) ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </div>
                        </li>
                        <li class="list-group-item px-0">
                            <div class="text-muted small"><?= lang('Profile.baptismDate') ?></div>
                            <div>
                                <?php if (!empty($profile['baptism_date']) && $profile['baptism_date'] !== '0000-00-00'): ?>
                                    <?= esc($formatLocalizedDate((string) ($profile['baptism_date'] ?? ''), 'MMMM d, y', $locale)) ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Contact Tab -->
        <div class="tab-pane fade" id="contact-pane" role="tabpanel">
            <?php
            $countryOptions = \App\Libraries\AddressHelper::countryOptions();
            $countryName = $countryOptions[$profile['country'] ?? ''] ?? ($profile['country'] ?? '');
            ?>
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><?= lang('Profile.sectionContact') ?></h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item px-0">
                            <div class="text-muted small"><i class="bi bi-telephone"></i> <?= lang('Profile.phone') ?></div>
                            <div><?= esc(\App\Libraries\PhoneHelper::display($profile['phone'] ?? null) ?: '-') ?></div>
                        </li>
                        <li class="list-group-item px-0">
                            <div class="text-muted small"><?= lang('Profile.country') ?></div>
                            <div><?= esc($countryName ?: '-') ?></div>
                        </li>
                        <li class="list-group-item px-0">
                            <div class="text-muted small"><?= lang('Profile.address') ?></div>
                            <div><?= esc($profile['address'] ?: '-') ?></div>
                        </li>
                        <li class="list-group-item px-0">
                            <div class="text-muted small"><?= lang('Profile.addressLine2') ?></div>
                            <div><?= esc(($profile['address_line_2'] ?? '') ?: '-') ?></div>
                        </li>
                        <li class="list-group-item px-0">
                            <div class="text-muted small"><?= lang('Profile.city') ?></div>
                            <div><?= esc($profile['city'] ?: '-') ?></div>
                        </li>
                        <li class="list-group-item px-0">
                            <div class="text-muted small"><?= lang('Profile.state') ?></div>
                            <div><?= esc($profile['state'] ?: '-') ?></div>
                        </li>
                        <li class="list-group-item px-0">
                            <div class="text-muted small"><?= lang('Profile.zip') ?></div>
                            <div><?= esc($profile['zip'] ?: '-') ?></div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Spiritual Tab -->
        <div class="tab-pane fade" id="spiritual-pane" role="tabpanel">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><?= lang('Profile.spiritualInformation') ?></h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item px-0">
                            <div class="text-muted small"><?= lang('Profile.spiritualGifts') ?></div>
                            <div><?= esc($profile['spiritual_gifts'] ?? '-') ?></div>
                        </li>
                        <li class="list-group-item px-0">
                            <div class="text-muted small"><?= lang('Profile.birthDate') ?></div>
                            <div>
                                <?php if (!empty($profile['birth_date']) && $profile['birth_date'] !== '0000-00-00'): ?>
                                    <?= esc($formatLocalizedDate((string) ($profile['birth_date'] ?? ''), 'MMMM d, y', $locale)) ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </div>
                        </li>
                        <li class="list-group-item px-0">
                            <div class="text-muted small"><?= lang('Profile.baptismDate') ?></div>
                            <div>
                                <?php if (!empty($profile['baptism_date']) && $profile['baptism_date'] !== '0000-00-00'): ?>
                                    <?= esc($formatLocalizedDate((string) ($profile['baptism_date'] ?? ''), 'MMMM d, y', $locale)) ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Account Tab -->
        <div class="tab-pane fade" id="account-pane" role="tabpanel">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><?= lang('Profile.accountInformation') ?></h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item px-0">
                                    <div class="text-muted small"><?= lang('Profile.username') ?></div>
                                    <div><?= esc($user->username ?? $user->email) ?></div>
                                </li>
                                <li class="list-group-item px-0">
                                    <div class="text-muted small"><?= lang('Profile.email') ?></div>
                                    <div><?= esc($user->email) ?></div>
                                </li>
                                <li class="list-group-item px-0">
                                    <div class="text-muted small"><?= lang('Profile.membershipStatus') ?></div>
                                    <div>
                                        <span class="badge border border-<?= $memberStatusColor ?> text-<?= $memberStatusColor ?> fw-normal">
                                            <?= lang('Profile.status' . ucfirst($membershipStatus)) ?>
                                        </span>
                                    </div>
                                </li>
                                <li class="list-group-item px-0">
                                    <div class="text-muted small"><?= lang('Profile.memberSince') ?></div>
                                    <div><?= esc($formatLocalizedDate((string) ($profile['created_at'] ?? ''), 'MMMM d, y', $locale)) ?></div>
                                </li>
                                <li class="list-group-item px-0">
                                    <div class="text-muted small"><?= lang('Profile.lastUpdated') ?></div>
                                    <div><?= esc($formatLocalizedDate((string) ($profile['updated_at'] ?? ''), 'MMMM d, y h:mm a', $locale)) ?></div>
                                </li>
                                <li class="list-group-item px-0">
                                    <div class="text-muted small"><?= lang('Profile.rolesAndGroups') ?></div>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php $userGroups = $userGroups ?? []; ?>
                                        <?php if (!empty($userGroups)): ?>
                                            <?php foreach ($userGroups as $groupName => $groupTitle): ?>
                                                <span class="badge text-bg-secondary" title="<?= esc($groupName) ?>"><?= esc($groupTitle) ?></span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="text-muted"><?= esc(lang('Profile.noRolesAssigned')) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-building"></i> <?= lang('Profile.parishAffiliation') ?></h5>
                        </div>
                        <div class="card-body">
                            <?php if ($hasAdminScope): ?>
                                <dl class="row affiliation-list mb-0">
                                    <dt class="col-sm-4"><?= lang('Profile.adminScopeLabel') ?></dt>
                                    <dd class="col-sm-8"><?= esc($adminScope['name']) ?></dd>
                                    <?php if (!empty($adminParishNames)): ?>
                                        <dt class="col-sm-4"><?= lang('Profile.parishesLabel') ?></dt>
                                        <dd class="col-sm-8"><?= esc(implode(', ', $adminParishNames)) ?></dd>
                                    <?php endif; ?>
                                </dl>
                            <?php elseif ($hasAffiliation): ?>
                                <dl class="row affiliation-list mb-0">
                                    <dt class="col-sm-4"><?= lang('Profile.province') ?></dt>
                                    <dd class="col-sm-8"><?= esc($parishAffiliation['province'] ?: '-') ?></dd>
                                    <dt class="col-sm-4"><?= lang('Profile.diocese') ?></dt>
                                    <dd class="col-sm-8"><?= esc($parishAffiliation['diocese'] ?: '-') ?></dd>
                                    <dt class="col-sm-4"><?= lang('Profile.parish') ?></dt>
                                    <dd class="col-sm-8"><?= esc($parishAffiliation['parish'] ?: '-') ?></dd>
                                </dl>
                            <?php else: ?>
                                <p class="text-muted mb-0"><i class="bi bi-info-circle"></i> <?= lang('Profile.noAffiliation') ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>

<?= $this->endSection() ?>