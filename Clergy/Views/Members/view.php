<?= $this->extend('Clergy\Views\layouts/main') ?>
<?php
$profile = $profile ?? [];
$family = $family ?? null;
$affiliations = $affiliations ?? [];
?>

<?= $this->section('content') ?>

<style>
    .member-profile-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        object-fit: cover;
    }

    .member-profile-avatar-fallback {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        margin: 0 auto;
    }

    .member-profile-avatar-fallback-icon {
        font-size: 2.5rem;
        color: #adb5bd;
        line-height: 1;
    }
</style>

<?php
// Hoist portal detection so the top action bar can conditionally render the revoke button.
$portalAccessRaw = array_key_exists('portal_access', $profile)
    ? trim((string) ($profile['portal_access'] ?? ''))
    : '';
$portalAccess      = $portalAccessRaw !== '' ? strtolower($portalAccessRaw) : null;
$portalAccess = in_array($portalAccess, ['none', 'invited', 'enabled'], true) ? $portalAccess : 'none';
$hasPortalIdentity = trim((string) ($profile['email'] ?? '')) !== '';
$requiresReset = (int) ($profile['force_reset'] ?? 0) === 1;
if ($hasPortalIdentity) {
    if ($requiresReset) {
        $portalAccess = 'invited';
    } elseif (in_array($portalAccess, ['none', 'invited'], true)) {
        $portalAccess = 'enabled';
    }
}
$isNonPortal = in_array($portalAccess, ['none', 'invited'], true);
$isPortalFullyEnabled = $portalAccess === 'enabled';
$profileUserId = (int) ($profile['user_id'] ?? 0);
$profilePicturePath = trim((string) ($profile['profile_picture'] ?? ''));
$profilePictureUrl = ($profilePicturePath !== '' && $profileUserId > 0)
    ? base_url('media/profile-picture/' . $profileUserId)
    : '';
?>

<div class="d-flex justify-content-start align-items-center mb-3">
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <?php if ($can_edit_member ?? false): ?>
            <a href="<?= base_url('members/edit/' . $profile['id']) ?>" class="btn btn-warning">
                <i class="bi bi-pencil" aria-hidden="true"></i> <?= lang('App.edit') ?>
            </a>
        <?php endif; ?>
        <?php if (($can_revoke_portal ?? false) && $isPortalFullyEnabled): ?>
            <button type="button" class="btn btn-outline-danger" id="revokePortalBtn"
                data-profile-id="<?= (int) $profile['id'] ?>"
                data-member-name="<?= esc($profile['first_name'] . ' ' . $profile['last_name'], 'attr') ?>">
                <i class="bi bi-shield-x" aria-hidden="true"></i> Revoke Portal Access
            </button>
        <?php endif; ?>
        <?php if ($can_hard_delete ?? false): ?>
            <a href="<?= base_url('members/dedup/hard-delete/' . (int) $profile['id']) ?>"
                class="btn btn-outline-danger">
                <i class="bi bi-trash3" aria-hidden="true"></i> <?= lang('Members.hardDeleteExecuteBtn') ?>
            </a>
        <?php endif; ?>
        <a href="<?= base_url('members') ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('App.back') ?>
        </a>
    </div>
</div>

<?php if (!empty($profile['is_confirmed']) && empty($profile['confirmation_date'])): ?>
    <div class="alert alert-warning d-flex align-items-center gap-2 mb-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill flex-shrink-0" aria-hidden="true"></i>
        <div>
            <?= lang('Members.confirmationDateMissing') ?>
            <?php if ($can_edit_member ?? false): ?>
                <a href="<?= base_url('members/edit/' . $profile['id']) ?>" class="alert-link ms-1"><?= lang('App.edit') ?></a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($show_profile_pending_indicator) && (int) ($profile['profile_pending_completion'] ?? 0) === 1): ?>
    <div class="alert alert-warning d-flex align-items-center gap-2 mb-3" role="alert">
        <i class="bi bi-exclamation-circle flex-shrink-0" aria-hidden="true"></i>
        <div><?= lang('Members.profilePendingHelp') ?></div>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Profile Information -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="mb-3">
                    <?php if ($profilePictureUrl !== ''): ?>
                        <img src="<?= esc($profilePictureUrl, 'attr') ?>" alt="" class="member-profile-avatar" onerror="this.classList.add('d-none');this.nextElementSibling.classList.remove('d-none');">
                        <div class="member-profile-avatar-fallback d-none">
                            <i class="bi bi-person-circle member-profile-avatar-fallback-icon" aria-hidden="true"></i>
                        </div>
                    <?php else: ?>
                        <div class="member-profile-avatar-fallback">
                            <i class="bi bi-person-circle member-profile-avatar-fallback-icon" aria-hidden="true"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <h4><?= esc($profile['first_name'] . ' ' . $profile['last_name']) ?></h4>
                <?php if (!empty($profile['nickname'])): ?>
                    <div class="text-muted small mb-2">&ldquo;<?= esc((string) $profile['nickname']) ?>&rdquo;</div>
                <?php endif; ?>
                <p class="text-muted"><?= esc($profile['email']) ?></p>
                <?php
                $profileStatus = strtolower(trim((string) ($profile['membership_status'] ?? 'regular_attender')));
                if (!in_array($profileStatus, ['active', 'inactive', 'visitor', 'pending', 'regular_attender'], true)) {
                    $profileStatus = 'regular_attender';
                }
                $profileStatusLabelKey = 'App.status' . ucfirst($profileStatus);
                $profileStatusLabel = lang($profileStatusLabelKey);
                if ($profileStatusLabel === $profileStatusLabelKey) {
                    $profileStatusLabel = ucfirst($profileStatus);
                }
                // $isNonPortal already computed at top of content section.
                ?>
                <?php $statusBadgeColor = match ($profileStatus) {
                    'active'           => 'success',
                    'inactive'         => 'secondary',
                    'visitor'          => 'info',
                    'regular_attender' => 'primary',
                    default            => 'warning',
                }; ?>
                <span class="badge border border-<?= $statusBadgeColor ?> text-<?= $statusBadgeColor ?> fw-normal mb-3">
                    <?= esc($profileStatusLabel) ?>
                </span>
                <?php if (!empty($show_profile_pending_indicator) && (int) ($profile['profile_pending_completion'] ?? 0) === 1): ?>
                    <span class="badge border border-warning text-warning fw-normal mb-3 ms-1"><?= lang('Members.profilePendingBadge') ?></span>
                <?php endif; ?>
                <?php if ($isNonPortal): ?>
                    <span class="badge border border-warning text-warning fw-normal mb-3 ms-1"><?= lang('People.nonPortalBadge') ?></span>
                <?php endif; ?>

                <hr>

                <div class="text-start">
                    <p class="mb-2"><strong><i class="bi bi-telephone" aria-hidden="true"></i> <?= lang('Members.phone') ?>:</strong><br>
                        <?= esc(\App\Libraries\PhoneHelper::display($profile['phone'] ?? null) ?: lang('Members.notProvided')) ?>
                    </p>

                    <p class="mb-2"><strong><i class="bi bi-cake2" aria-hidden="true"></i> <?= lang('Members.birthDate') ?>:</strong><br>
                        <?= $profile['birth_date'] ? date('F j, Y', strtotime($profile['birth_date'])) : lang('Members.notProvided') ?>
                    </p>

                    <p class="mb-2"><strong><i class="bi bi-droplet" aria-hidden="true"></i> <?= lang('Members.baptismDate') ?>:</strong><br>
                        <?= $profile['baptism_date'] ? date('F j, Y', strtotime($profile['baptism_date'])) : lang('Members.notProvided') ?>
                    </p>

                    <p class="mb-2"><strong><i class="bi bi-patch-check" aria-hidden="true"></i> <?= lang('Members.isConfirmed') ?>:</strong><br>
                        <?php if (!empty($profile['is_confirmed'])): ?>
                            <span class="badge border border-success text-success fw-normal"><?= lang('App.yes') ?></span>
                        <?php else: ?>
                            <span class="badge border border-secondary text-secondary fw-normal"><?= lang('App.no') ?></span>
                        <?php endif; ?>
                    </p>

                    <p class="mb-2"><strong><i class="bi bi-calendar-check" aria-hidden="true"></i> <?= lang('Members.confirmationDate') ?>:</strong><br>
                        <?php if (!empty($profile['is_confirmed']) && empty($profile['confirmation_date'])): ?>
                            <span class="text-warning fw-semibold"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> <?= lang('Members.notProvided') ?></span>
                        <?php else: ?>
                            <?= !empty($profile['confirmation_date']) ? date('F j, Y', strtotime($profile['confirmation_date'])) : lang('Members.notProvided') ?>
                        <?php endif; ?>
                    </p>

                    <p class="mb-0"><strong><i class="bi bi-calendar-plus" aria-hidden="true"></i> <?= lang('Members.colMemberSince') ?>:</strong><br>
                        <?= date('F j, Y', strtotime($profile['created_at'])) ?>
                    </p>
                </div>
            </div>
        </div>

        <?php if ($family): ?>
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-house-heart" aria-hidden="true"></i> <?= lang('Members.colFamily') ?></h6>
                </div>
                <div class="card-body">
                    <p class="mb-0">
                        <strong><?= esc((string) $family['family_name']) ?></strong><br>
                        <small class="text-muted">
                            <?= $family['first_name'] ? lang('Members.headPrefix') . ': ' . esc((string) $family['first_name'] . ' ' . (string) $family['last_name']) : lang('Members.noHeadAssigned') ?>
                        </small>
                    </p>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- Details Tabs -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#info"><?= lang('Members.tabInfo') ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#parishAffiliations"><?= lang('Members.parishAffiliations') ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#attendance"><?= lang('Members.tabAttendance') ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#giving"><?= lang('Members.tabGiving') ?></a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <!-- Information Tab -->
                    <div class="tab-pane fade show active" id="info">
                        <h5 class="mb-3"><?= lang('Members.contactInfo') ?></h5>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <?php
                                $countryOptions = \App\Libraries\AddressHelper::countryOptions();
                                $countryName = $countryOptions[$profile['country'] ?? ''] ?? ($profile['country'] ?? '');
                                ?>
                                <strong><?= lang('Members.address') ?>:</strong><br>
                                <?php if ($profile['address'] || $profile['address_line_2']): ?>
                                    <?= esc($profile['address'] ?: '') ?><br>
                                    <?php if ($profile['address_line_2']): ?>
                                        <?= esc($profile['address_line_2']) ?><br>
                                    <?php endif; ?>
                                    <?= esc($profile['city'] ?: '') ?>, <?= esc($profile['state'] ?: '') ?> <?= esc($profile['zip'] ?: '') ?><br>
                                    <?= esc($countryName ?: '') ?>
                                <?php else: ?>
                                    <span class="text-muted"><?= lang('Members.notProvided') ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <hr>

                        <h5 class="mb-3"><?= lang('Members.spiritualGiftsLabel') ?></h5>
                        <p><?= nl2br(esc((string) ($profile['spiritual_gifts'] ?? lang('Members.notProvided')))) ?></p>
                    </div>

                    <div class="tab-pane fade" id="parishAffiliations">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0"><i class="bi bi-geo" aria-hidden="true"></i> <?= lang('Members.parishAffiliations') ?></h5>
                            <?php if (auth()->user()->can('member.edit')): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#attachParishPanel" aria-expanded="false" aria-controls="attachParishPanel">
                                    <i class="bi bi-plus-circle" aria-hidden="true"></i> <?= lang('Members.attachParish') ?>
                                </button>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($affiliations)): ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($affiliations as $aff): ?>
                                    <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?= esc((string) ($aff['parish_name'] ?? $aff['parish_name'] ?? lang('Members.unassigned'))) ?></strong>
                                            <?php $affUeid = trim((string) ($aff['parish_ueid'] ?? '')); ?>
                                            <?php if ($affUeid !== ''): ?>
                                                <span class="small text-muted">(<?= esc($affUeid) ?>)</span>
                                            <?php endif; ?>
                                            <?php if (!empty($aff['is_primary'])): ?>
                                                <span class="badge border border-primary text-primary fw-normal ms-1"><?= lang('Members.homeLabel') ?></span>
                                            <?php endif; ?>
                                            <div class="small text-muted">
                                                <?php
                                                $affStatus = strtolower(trim((string) ($aff['membership_status'] ?? 'regular_attender')));
                                                if (!in_array($affStatus, ['active', 'inactive', 'visitor', 'pending', 'regular_attender'], true)) {
                                                    $affStatus = 'regular_attender';
                                                }
                                                $affStatusLabelKey = 'App.status' . ucfirst($affStatus);
                                                $affStatusLabel = lang($affStatusLabelKey);
                                                if ($affStatusLabel === $affStatusLabelKey) {
                                                    $affStatusLabel = ucfirst($affStatus);
                                                }
                                                ?>
                                                <?= esc($affStatusLabel) ?>
                                            </div>
                                        </div>
                                        <?php if (auth()->user()->can('member.edit')): ?>
                                            <div class="d-inline-flex gap-1">
                                                <?php if (empty($aff['is_primary'])): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-success" onclick="setHomeParish(<?= (int) $profile['id'] ?>, <?= (int) $aff['parish_id'] ?>)">
                                                        <?= lang('Members.setHome') ?>
                                                    </button>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="detachParish(<?= (int) $profile['id'] ?>, <?= (int) $aff['parish_id'] ?>)">
                                                    <?= lang('Members.detach') ?>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-muted mb-0"><?= lang('Members.noParishAffiliations') ?></p>
                        <?php endif; ?>

                        <?php if (auth()->user()->can('member.edit')): ?>
                            <div id="attachParishPanel" class="collapse mt-3">
                                <div class="border rounded p-3 bg-light">
                                    <form id="attachParishForm">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="profile_id" value="<?= (int) $profile['id'] ?>">
                                        <div class="mb-2">
                                            <label class="form-label"><?= lang('Members.parishLabel') ?></label>
                                            <select name="parish_id" class="form-select" required>
                                                <option value=""><?= lang('Members.selectParish') ?></option>
                                                <?php foreach (($attachableParishes ?? []) as $parish): ?>
                                                    <?php $parishUeid = trim((string) ($parish['parish_ueid'] ?? '')); ?>
                                                    <option value="<?= (int) $parish['id'] ?>"><?= esc($parish['name'] . ($parishUeid !== '' ? ' (' . $parishUeid . ')' : '')) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label"><?= lang('Members.membershipStatusLabel') ?></label>
                                            <select name="membership_status" class="form-select">
                                                <option value="regular_attender"><?= lang('Members.statusRegularAttender') ?></option>
                                                <option value="active"><?= lang('Members.statusActive') ?></option>
                                                <option value="inactive"><?= lang('Members.statusInactive') ?></option>
                                                <option value="visitor"><?= lang('Members.statusVisitor') ?></option>
                                                <option value="pending"><?= lang('Members.statusPending') ?></option>
                                            </select>
                                        </div>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" name="is_primary" value="1" id="attachAsHomeParish">
                                            <label class="form-check-label" for="attachAsHomeParish"><?= lang('Members.setAsHomeParish') ?></label>
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-primary"><?= lang('Members.attachParish') ?></button>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Attendance Tab -->
                    <div class="tab-pane fade" id="attendance">
                        <h5 class="mb-3"><?= lang('Members.recentAttendance') ?></h5>
                        <?php if (!empty($recent_attendance)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th scope="col"><?= lang('App.colDate') ?></th>
                                            <th scope="col"><?= lang('Members.colServiceType') ?></th>
                                            <th scope="col"><?= lang('Members.colLiturgicalDay') ?></th>
                                            <th scope="col"><?= lang('Members.colCommunion') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_attendance as $att): ?>
                                            <tr>
                                                <td><?= date('M j, Y', strtotime($att['service_date'])) ?></td>
                                                <td>
                                                    <?php
                                                    $services = json_decode($att['service'] ?? '[]', true);
                                                    if (!empty($services) && is_array($services)):
                                                        foreach ($services as $serviceType): ?>
                                                            <span class="badge border border-primary text-primary fw-normal me-1"><?= esc((string) $serviceType) ?></span>
                                                        <?php endforeach;
                                                    else: ?>
                                                        <span class="badge border border-secondary text-secondary fw-normal">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= esc(liturgical_day_label((string) ($att['liturgical_day'] ?? '')) ?: '-') ?></td>
                                                <td>
                                                    <?= $att['communicant'] ? '<span class="badge border border-success text-success fw-normal">' . lang('App.yes') . '</span>' : '<span class="badge border border-secondary text-secondary fw-normal">' . lang('App.no') . '</span>' ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center py-3"><?= lang('Members.noAttendanceRecords') ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Giving Tab -->
                    <div class="tab-pane fade" id="giving">
                        <h5 class="mb-3"><?= lang('Members.recentDonations') ?></h5>
                        <?php if (!empty($recent_donations)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th scope="col"><?= lang('App.colDate') ?></th>
                                            <th scope="col"><?= lang('Members.colCategory') ?></th>
                                            <th scope="col"><?= lang('Members.colAmount') ?></th>
                                            <th scope="col"><?= lang('Members.colMethod') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $givingCategoryMap = [
                                            'tithe'         => lang('Finance.categoryTithe'),
                                            'offering'      => lang('Finance.categoryOffering'),
                                            'building fund' => lang('Finance.categoryBuilding'),
                                            'mission'       => lang('Finance.categoryMission'),
                                            'other'         => lang('Finance.categoryOther'),
                                        ];
                                        $givingMethodMap = [
                                            'cash'          => lang('Finance.methodCash'),
                                            'check'         => lang('Finance.methodCheck'),
                                            'credit_card'   => lang('Finance.methodCreditCard'),
                                            'bank_transfer' => lang('Finance.methodBankTransfer'),
                                            'online'        => lang('Finance.methodOnline'),
                                        ];
                                        $total = 0;
                                        foreach ($recent_donations as $donation):
                                            $total += $donation['amount'];
                                            $givingCat    = (string) ($donation['category'] ?? '');
                                            $givingMethod = (string) ($donation['payment_method'] ?? '');
                                        ?>
                                            <tr>
                                                <td><?= date('M j, Y', strtotime($donation['date'])) ?></td>
                                                <td><?= esc((string) ($givingCategoryMap[$givingCat] ?? ucfirst(str_replace('_', ' ', $givingCat)))) ?></td>
                                                <td>$<?= number_format($donation['amount'], 2) ?></td>
                                                <td><?= esc((string) ($givingMethod !== '' ? ($givingMethodMap[$givingMethod] ?? $givingMethod) : '-')) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="2"><?= lang('Members.totalLastItems') ?></th>
                                            <th>$<?= number_format($total, 2) ?></th>
                                            <th></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center py-3"><?= lang('Members.noDonationRecords') ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        <h5 class="mb-0"><?= lang('Members.membershipDetailsHeading') ?? 'Membership Details' ?></h5>
    </div>
    <div class="card-body">
        <?php
        $details = $membership_details ?? [];
        $detailBadges = [
            'is_baptized' => 'Baptized',
            'is_confirmed' => 'Confirmed',
            'is_communicant' => 'Communicant',
            'is_dependent' => 'Dependent',
            'has_pledge' => 'Has Pledge',
        ];
        ?>
        <?php foreach ($detailBadges as $key => $label): ?>
            <?php if (!empty($details[$key])): ?>
                <span class="badge border border-success text-success fw-normal me-1"><?= esc($label) ?></span>
            <?php else: ?>
                <span class="badge border border-secondary text-secondary fw-normal me-1"><?= esc($label) ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if (isset($details['is_under_16']) && $details['is_under_16'] !== null): ?>
            <span class="badge border border-info text-info fw-normal me-1"><?= $details['is_under_16'] ? 'Under 16' : '16+' ?></span>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($leadership)): ?>
<div class="card mb-3">
    <div class="card-header">
        <h5 class="mb-0"><?= lang('Members.leadershipHeading') ?? 'Leadership' ?></h5>
    </div>
    <ul class="list-group list-group-flush">
        <?php foreach ($leadership as $position): ?>
            <?php
            $scopeType = (string) ($position['scope_type'] ?? 'parish');
            $scopeLabel = $scopeType === 'province'
                ? lang('App.scopeProvince')
                : ($scopeType === 'diocese' ? lang('App.scopeDiocese') : lang('App.scopeParish'));
            $scopeName = $scopeType === 'province'
                ? ($position['province_name'] ?? '')
                : ($scopeType === 'diocese' ? ($position['diocese_name'] ?? '') : ($position['parish_name'] ?? ''));
            ?>
            <li class="list-group-item">
                <div class="d-flex justify-content-between align-items-center">
                    <span><?= esc((string) ($position['title'] ?? '')) ?></span>
                    <span class="badge border border-primary text-primary fw-normal">
                        <?= esc($scopeLabel) ?><?= $scopeName !== '' ? ': ' . esc($scopeName) : '' ?>
                    </span>
                </div>
                <div class="small text-muted mt-1">
                    <?= lang('Leadership.period') ?>:
                    <?= !empty($position['started_at']) ? esc($position['started_at']) : '-' ?>
                    <?= lang('Leadership.to') ?>
                    <?= !empty($position['ended_at']) ? esc($position['ended_at']) : lang('Leadership.present') ?>
                    <?php if (empty($position['active'])): ?>
                        <span class="badge border border-secondary text-secondary fw-normal ms-1"><?= lang('App.statusInactive') ?></span>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if (!empty($status_history)): ?>
<div class="card mb-3">
    <div class="card-header">
        <h5 class="mb-0"><?= lang('Members.statusHistoryHeading') ?? 'Status History' ?></h5>
    </div>
    <ul class="list-group list-group-flush">
        <?php foreach ($status_history as $history): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span>
                    <span class="badge border border-secondary text-secondary fw-normal me-1"><?= esc((string) ($history['status_type'] ?? '')) ?></span>
                    <?php if (!empty($history['from_status'])): ?>
                        <?= esc((string) $history['from_status']) ?> &rarr;
                    <?php endif; ?>
                    <?= esc((string) ($history['to_status'] ?? '')) ?>
                    <?php if (!empty($history['reason'])): ?>
                        <span class="text-muted small ms-1">(<?= esc((string) $history['reason']) ?>)</span>
                    <?php endif; ?>
                </span>
                <span class="text-muted small"><?= esc((string) ($history['created_at'] ?? '')) ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if (!empty($onboarding)): ?>
<div class="card mb-3">
    <div class="card-header">
        <h5 class="mb-0"><?= lang('Members.onboardingHeading') ?? 'Onboarding' ?></h5>
    </div>
    <div class="card-body">
        <p class="mb-0">
            <span class="badge border border-warning text-warning fw-normal"><?= esc((string) ($onboarding['onboarding_status'] ?? '')) ?></span>
        </p>
    </div>
</div>
<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    const actionFailedMsg = <?= json_encode(lang('App.errorFailed')) ?>;
    const requestFailedMsg = <?= json_encode(lang('App.httpRequestFailedRetry')) ?>;
    const memberDetachConstraintMsg = <?= json_encode(lang('Clergy.memberMustRemainAttached')) ?>;

    const errorCodeMessageMap = {
        MEMBER_LAST_PARISH_CONSTRAINT: memberDetachConstraintMsg,
    };

    function contractErrorMessage(data, fallback) {
        if (!data || typeof data !== 'object') {
            return fallback;
        }

        const code = data.error && typeof data.error.code === 'string' ? data.error.code : '';
        if (code && Object.prototype.hasOwnProperty.call(errorCodeMessageMap, code)) {
            return `${errorCodeMessageMap[code]} (${code})`;
        }

        const message =
            (data.error && typeof data.error.message === 'string' && data.error.message) ||
            (typeof data.message === 'string' && data.message) ||
            fallback;

        return code ? `${message} (${code})` : message;
    }

    function postMemberParishAction(url, payload, successMessage) {
        payload['<?= csrf_token() ?>'] = '<?= csrf_hash() ?>';

        $.ajax({
            url,
            type: 'POST',
            data: payload,
            success: function(response) {
                if (response && response.success) {
                    showAppToast(successMessage, 'primary');
                    window.location.reload();
                    return;
                }
                showAppToast(contractErrorMessage(response, actionFailedMsg), 'danger');
            },
            error: function(xhr) {
                const message = contractErrorMessage(xhr.responseJSON, requestFailedMsg);
                showAppToast(message, 'danger');
            }
        });
    }

    function detachParish(profileId, parishId) {
        showAppConfirm(
            '<?= esc(lang('App.confirm'), 'js') ?>',
            '<?= esc(lang('Members.detachParishConfirm'), 'js') ?>',
            function() {
                postMemberParishAction('<?= base_url('members/detach-parish') ?>', {
                    profile_id: profileId,
                    parish_id: parishId
                }, 'Parish detached successfully.');
            }
        );
    }

    function setHomeParish(profileId, parishId) {
        postMemberParishAction('<?= base_url('members/set-home-parish') ?>', {
            profile_id: profileId,
            parish_id: parishId
        }, 'Home parish updated successfully.');
    }

    $('#attachParishForm').on('submit', function(e) {
        e.preventDefault();
        const data = $(this).serializeArray().reduce(function(acc, item) {
            acc[item.name] = item.value;
            return acc;
        }, {});

        postMemberParishAction('<?= base_url('members/attach-parish') ?>', data, 'Parish attached successfully.');
    });

    // Revoke Portal Access
    const revokePortalBtn = document.getElementById('revokePortalBtn');
    if (revokePortalBtn) {
        revokePortalBtn.addEventListener('click', function() {
            const profileId = this.dataset.profileId;
            const memberName = this.dataset.memberName;
            showAppConfirm(
                '<?= esc(lang('App.confirm'), 'js') ?>',
                `Remove portal access for ${memberName}? They will no longer be able to log in. This action cannot be undone unless you re-provision their account.`,
                function() {
                    const payload = {};
                    payload['<?= csrf_token() ?>'] = '<?= csrf_hash() ?>';

                    $.ajax({
                        url: '<?= base_url('members/revoke-portal-access/') ?>' + profileId,
                        type: 'POST',
                        data: payload,
                        success: function(response) {
                            if (response && response.success) {
                                window.location.reload();
                                return;
                            }
                            showAppToast(contractErrorMessage(response, actionFailedMsg), 'danger');
                        },
                        error: function(xhr) {
                            showAppToast(contractErrorMessage(xhr.responseJSON, requestFailedMsg), 'danger');
                        }
                    });
                }
            );
        });
    }
</script>
<?= $this->endSection() ?>