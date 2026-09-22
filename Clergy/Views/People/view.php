<?= $this->extend('Clergy\Views\layouts/main') ?>
<?php
$profile = $profile ?? [];
$person = $person ?? [];
$family = $family ?? [];
$affiliations = $affiliations ?? [];
$membership_details = $membership_details ?? [];
$group_memberships = $group_memberships ?? [];
$pathway_stage = $pathway_stage ?? null;
$status_history = $status_history ?? [];
$leadership = $leadership ?? [];
$onboarding = $onboarding ?? [];
$can_edit = $can_edit ?? false;
$can_delete = $can_delete ?? false;

$profileId = (int) ($profile['id'] ?? 0);
$personId = (int) ($profile['person_id'] ?? 0);
$userId = (int) ($profile['user_id'] ?? 0);
$parishId = (int) ($person['parish_id'] ?? 0);
$portalAccess = strtolower(trim((string) ($profile['portal_access'] ?? 'none')));
?>

<?= $this->section('content') ?>

<style>
    .people-profile-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        object-fit: cover;
    }
    .people-profile-avatar-fallback {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
    }
    .people-profile-avatar-fallback-icon {
        font-size: 2.5rem;
        color: #adb5bd;
        line-height: 1;
    }
</style>

<div class="container-fluid">
    <div class="row g-3">
        <div class="col-12 d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h4 class="mb-0"><?= esc(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')) ?></h4>
            <div class="d-flex gap-2">
                <?php if ($can_edit && $profileId > 0): ?>
                    <a href="<?= base_url('members/edit/' . $profileId) ?>" class="btn btn-warning btn-sm">
                        <i class="bi bi-pencil" aria-hidden="true"></i> <?= lang('App.edit') ?>
                    </a>
                <?php endif; ?>
                <a href="<?= base_url('people') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('App.back') ?>
                </a>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <?php
                    $profileUserId = (int) ($profile['user_id'] ?? 0);
                    $profilePicturePath = trim((string) ($profile['profile_picture'] ?? ''));
                    $profilePictureUrl = ($profilePicturePath !== '' && $profileUserId > 0)
                        ? base_url('media/profile-picture/' . $profileUserId)
                        : '';
                    ?>
                    <?php if ($profilePictureUrl !== ''): ?>
                        <img src="<?= esc($profilePictureUrl, 'attr') ?>" alt="" class="people-profile-avatar mb-3">
                    <?php else: ?>
                        <div class="people-profile-avatar-fallback mb-3">
                            <i class="bi bi-person people-profile-avatar-fallback-icon" aria-hidden="true"></i>
                        </div>
                    <?php endif; ?>

                    <h5 class="card-title mb-1"><?= esc(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')) ?></h5>
                    <?php if (!empty($profile['nickname'])): ?>
                        <p class="text-muted mb-2">"<?= esc($profile['nickname']) ?>"</p>
                    <?php endif; ?>

                    <?php
                    $statusValue = strtolower(trim((string) ($person['membership_status'] ?? 'visitor')));
                    $statusLabel = \App\Services\MembershipStatusService::label($statusValue);
                    $statusColor = \App\Services\MembershipStatusService::badgeColor($statusValue);
                    ?>
                    <span class="badge bg-<?= esc($statusColor) ?> mb-2">
                        <?= esc($statusLabel) ?>
                    </span>
                    <?php if ((int) ($person['is_member'] ?? 0) === 0): ?>
                        <span class="badge bg-light text-dark border"><?= lang('People.notAMember') ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0"><?= lang('Members.contactInfo') ?></h6>
                </div>
                <div class="card-body">
                    <p class="mb-1"><strong><?= lang('Members.email') ?>:</strong><br>
                        <?= esc($person['email'] ?? $profile['email'] ?? lang('Members.notProvided')) ?></p>
                    <p class="mb-1"><strong><?= lang('Members.phone') ?>:</strong><br>
                        <?= esc((!empty($profile['phone']) ? \App\Libraries\PhoneHelper::display($profile['phone']) : lang('Members.notProvided'))) ?></p>
                    <?php if (!empty($profile['birth_date'])): ?>
                        <p class="mb-1"><strong><?= lang('Members.birthDate') ?>:</strong><br>
                            <?= esc($profile['birth_date']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($profile['address'])): ?>
                <div class="card mt-3">
                    <div class="card-header">
                        <h6 class="mb-0"><?= lang('Members.address') ?></h6>
                    </div>
                    <div class="card-body">
                        <?php
                        $countryOptions = \App\Libraries\AddressHelper::countryOptions();
                        $countryName = $countryOptions[$profile['country'] ?? ''] ?? ($profile['country'] ?? '');
                        ?>
                        <?= esc($profile['address'] ?: '') ?><br>
                        <?php if (!empty($profile['address_line_2'])): ?>
                            <?= esc($profile['address_line_2']) ?><br>
                        <?php endif; ?>
                        <?= esc(($profile['city'] ?? '') . ', ' . ($profile['state'] ?? '') . ' ' . ($profile['zip'] ?? '')) ?><br>
                        <?= esc($countryName) ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-md-8">
            <?php if (!empty($sacrament_discrepancies)): ?>
                <div class="alert alert-warning d-flex align-items-start gap-2 mb-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill flex-shrink-0" aria-hidden="true"></i>
                    <div class="small">
                        <strong><?= lang('People.sacramentDiscrepancyHeading') ?? 'Sacrament data mismatch' ?></strong>
                        <p class="mb-1"><?= lang('People.sacramentDiscrepancyIntro') ?? 'The sacramental register disagrees with the membership flags or profile dates:' ?></p>
                        <ul class="mb-1">
                            <?php foreach ($sacrament_discrepancies as $d): ?>
                                <li><?= esc($d['message'] ?? '') ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="mb-0 text-muted">
                            <?= lang('People.sacramentDiscrepancyFixHint') ?? 'Run "php spark sacraments:reconcile" or add/remove the matching sacrament record to re-sync.' ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($membership_details) || $can_edit): ?>
                <div class="card mb-3" id="membershipDetailsCard">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><?= lang('Members.membershipDetails') ?></h6>
                        <?php if ($can_edit): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="toggleMembershipDetailsEdit">
                                <i class="bi bi-pencil" aria-hidden="true"></i> <?= lang('App.edit') ?>
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div id="membershipDetailsDisplay">
                            <div class="row g-2 small">
                                <div class="col-6 col-md-4"><strong><?= lang('Members.isBaptized') ?>:</strong> <?= !empty($membership_details['is_baptized']) ? lang('App.yes') : lang('App.no') ?></div>
                                <div class="col-6 col-md-4"><strong><?= lang('Members.isConfirmed') ?>:</strong> <?= !empty($membership_details['is_confirmed']) ? lang('App.yes') : lang('App.no') ?></div>
                                <div class="col-6 col-md-4"><strong><?= lang('Members.isCommunicant') ?>:</strong> <?= !empty($membership_details['is_communicant']) ? lang('App.yes') : lang('App.no') ?></div>
                                <div class="col-6 col-md-4"><strong><?= lang('Members.isDependent') ?>:</strong> <?= !empty($membership_details['is_dependent']) ? lang('App.yes') : lang('App.no') ?></div>
                                <div class="col-6 col-md-4"><strong><?= lang('Members.hasPledge') ?>:</strong> <?= !empty($membership_details['has_pledge']) ? lang('App.yes') : lang('App.no') ?></div>
                                <div class="col-6 col-md-4"><strong><?= lang('Members.goodStandingOverride') ?>:</strong> <?= !empty($membership_details['good_standing_override']) ? lang('App.yes') : lang('App.no') ?></div>
                            </div>
                        </div>

                        <?php if ($can_edit && $personId > 0): ?>
                            <form id="membershipDetailsForm" class="d-none" method="post" action="<?= base_url('people/save-membership-details/' . $personId) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="parish_id" value="<?= $parishId ?>">
                                <div class="row g-3 mb-3">
                                    <div class="col-6 col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="md_is_baptized" name="is_baptized" value="1" <?= !empty($membership_details['is_baptized']) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="md_is_baptized"><?= lang('Members.isBaptized') ?></label>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="md_is_confirmed" name="is_confirmed" value="1" <?= !empty($membership_details['is_confirmed']) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="md_is_confirmed"><?= lang('Members.isConfirmed') ?></label>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="md_is_communicant" name="is_communicant" value="1" <?= !empty($membership_details['is_communicant']) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="md_is_communicant"><?= lang('Members.isCommunicant') ?></label>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="md_is_dependent" name="is_dependent" value="1" <?= !empty($membership_details['is_dependent']) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="md_is_dependent"><?= lang('Members.isDependent') ?></label>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="md_has_pledge" name="has_pledge" value="1" <?= !empty($membership_details['has_pledge']) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="md_has_pledge"><?= lang('Members.hasPledge') ?></label>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="md_good_standing_override" name="good_standing_override" value="1" <?= !empty($membership_details['good_standing_override']) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="md_good_standing_override"><?= lang('Members.goodStandingOverride') ?></label>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="md_reason" class="form-label small"><?= lang('Members.statusReason') ?></label>
                                    <textarea class="form-control form-control-sm" id="md_reason" name="reason" rows="2" placeholder="<?= esc(lang('Members.statusReasonPlaceholder'), 'attr') ?>"></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm"><?= lang('App.save') ?></button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="cancelMembershipDetailsEdit"><?= lang('App.cancel') ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($can_edit): ?>
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><?= lang('People.quickActions') ?></h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2">
                            <a href="<?= base_url('leadership') ?>" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-person-badge" aria-hidden="true"></i> <?= lang('App.navLeadership') ?>
                            </a>
                            <?php if (auth()->user()->can('donation.view') || auth()->user()->can('finance.manage')): ?>
                                <a href="<?= base_url('donations') ?>" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-cash-coin" aria-hidden="true"></i> <?= lang('App.navDonations') ?>
                                </a>
                            <?php endif; ?>
                            <?php if (auth()->user()->can('finance.manage') || auth()->user()->can('annual_pledge.manage')): ?>
                                <a href="<?= base_url('pledges') ?>" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-calendar-check" aria-hidden="true"></i> <?= lang('App.navPledges') ?>
                                </a>
                            <?php endif; ?>
                            <?php if (auth()->user()->can('family.view')): ?>
                                <a href="<?= base_url('families') ?>" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-people" aria-hidden="true"></i> <?= lang('App.navFamilies') ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($affiliations)): ?>
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><?= lang('Members.affiliations') ?></h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <?php foreach ($affiliations as $aff): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?= esc($aff['parish_name'] ?? $aff['parish_id'] ?? '') ?>
                                    <span class="badge border border-<?= \App\Services\MembershipStatusService::badgeColor((string) ($aff['membership_status'] ?? '')) ?> text-<?= \App\Services\MembershipStatusService::badgeColor((string) ($aff['membership_status'] ?? '')) ?> fw-normal"><?= esc(\App\Services\MembershipStatusService::label((string) ($aff['membership_status'] ?? ''))) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (auth()->user()->can('groups.view') && (!empty($group_memberships) || $pathway_stage !== null)): ?>
                <?php
                $stageMeta = \App\Services\PathwayStageService::stageMeta($pathway_stage);
                $gmRoleKeys = ['member' => 'Groups.roleMember', 'leader' => 'Groups.roleLeader', 'co_leader' => 'Groups.roleCoLeader', 'host' => 'Groups.roleHost'];
                ?>
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><?= lang('Groups.groupMemberships') ?></h6>
                        <?php $stageColor = $stageMeta['badge'] ?? 'secondary'; ?>
                        <span class="badge border border-<?= $stageColor ?> text-<?= $stageColor ?> fw-normal"><?= lang($stageMeta['labelKey'] ?? 'Groups.stageNone') ?></span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($group_memberships)): ?>
                            <p class="text-muted mb-0"><?= lang('Groups.noMembers') ?></p>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($group_memberships as $gm): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <a href="<?= base_url('groups/view/' . (int) $gm['small_group_id']) ?>"><?= esc($gm['group_name'] ?? '') ?></a>
                                        <span class="badge border border-secondary text-secondary fw-normal"><?= lang($gmRoleKeys[$gm['role'] ?? 'member'] ?? 'Groups.roleMember') ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php
            $isInFamily = !empty($family);
            $canManageFamily = auth()->user()->can('family.manage');
            ?>
            <?php if ($isInFamily || $canManageFamily): ?>
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><?= lang('Families.family') ?></h6>
                        <?php if ($canManageFamily && !$isInFamily && $userId > 0): ?>
                            <div class="d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="createFamilyBtn">
                                    <i class="bi bi-plus-lg" aria-hidden="true"></i> <?= lang('Families.create') ?>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="joinFamilyBtn">
                                    <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> <?= lang('Families.join') ?>
                                </button>
                            </div>
                        <?php elseif ($canManageFamily && $isInFamily): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="addFamilyMemberBtn">
                                <i class="bi bi-plus-lg" aria-hidden="true"></i> <?= lang('Families.addMember') ?>
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (!$isInFamily): ?>
                            <p class="text-muted small mb-0"><?= lang('Families.notInFamily') ?></p>
                        <?php else: ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong><?= esc($family['family_name'] ?? lang('Families.unnamed')) ?></strong>
                                <?php if ($canManageFamily): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger" id="leaveFamilyBtn">
                                        <?= lang('Families.leave') ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($family_members)): ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($family_members as $familyMember): ?>
                                        <?php
                                        $isCurrentMember = (int) ($familyMember['user_id'] ?? 0) === $userId;
                                        $memberRelationId = (int) ($familyMember['family_member_id'] ?? 0);
                                        ?>
                                        <li class="list-group-item px-0" data-family-member-id="<?= $memberRelationId ?>">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <span><?= esc(($familyMember['first_name'] ?? '') . ' ' . ($familyMember['last_name'] ?? '')) ?></span>
                                                    <?php if ($isCurrentMember): ?>
                                                        <span class="badge bg-light text-dark border ms-1"><?= lang('Families.you') ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($canManageFamily): ?>
                                                    <div class="d-flex gap-1 align-items-center">
                                                        <select class="form-select form-select-sm relationship-select"
                                                            data-family-member-id="<?= $memberRelationId ?>"
                                                            data-user-id="<?= (int) ($familyMember['user_id'] ?? 0) ?>"
                                                            style="width: auto; min-width: 110px;">
                                                            <?php foreach (['Head', 'Spouse', 'Child', 'Parent', 'Sibling', 'Other'] as $rel): ?>
                                                                <option value="<?= $rel ?>" <?= (($familyMember['relationship'] ?? '') === $rel) ? 'selected' : '' ?>><?= lang('Families.relationship' . $rel) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <?php if (!$isCurrentMember): ?>
                                                            <button type="button" class="btn btn-sm btn-link p-0 remove-family-member-btn"
                                                                data-family-member-id="<?= $memberRelationId ?>"
                                                                data-name="<?= esc(($familyMember['first_name'] ?? '') . ' ' . ($familyMember['last_name'] ?? ''), 'attr') ?>"
                                                                title="<?= esc(lang('App.delete'), 'attr') ?>">
                                                                <i class="bi bi-trash3 text-danger" aria-hidden="true"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted small"><?= esc($familyMember['relationship'] ?? '') ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted small mb-0"><?= lang('Families.noMembers') ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($leadership) || $can_edit): ?>
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><?= lang('Members.leadership') ?></h6>
                        <?php if ($can_edit && $profileId > 0 && $userId > 0): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="addLeadershipPositionBtn">
                                <i class="bi bi-plus-lg" aria-hidden="true"></i> <?= lang('Leadership.addPosition') ?>
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($leadership)): ?>
                            <p class="text-muted small mb-0"><?= lang('Leadership.noPositions') ?></p>
                        <?php else: ?>
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

                                    $roleCategoryLabels = [
                                        'clergy' => lang('Leadership.roleCategoryClergy'),
                                        'lay_staff' => lang('Leadership.roleCategoryLayStaff'),
                                    ];
                                    $clergyOrderLabels = [
                                        'bishop' => lang('Leadership.clergyOrderBishop'),
                                        'priest' => lang('Leadership.clergyOrderPriest'),
                                        'deacon' => lang('Leadership.clergyOrderDeacon'),
                                    ];
                                    $employmentTypeLabels = [
                                        'full_time' => lang('Leadership.employmentFullTime'),
                                        'part_time' => lang('Leadership.employmentPartTime'),
                                        'non_stipendiary' => lang('Leadership.employmentNonStipendiary'),
                                        'unpaid' => lang('Leadership.employmentUnpaid'),
                                    ];
                                    ?>
                                    <li class="list-group-item" data-position-id="<?= (int) ($position['id'] ?? 0) ?>">
                                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                            <div>
                                                <div class="fw-semibold"><?= esc($position['title'] ?? '') ?></div>
                                                <?php if (!empty($position['role_code'])): ?>
                                                    <small class="text-muted"><?= esc($position['role_code']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="d-flex gap-1 align-items-center">
                                                <span class="badge border border-<?= !empty($position['active']) ? 'success' : 'secondary' ?> text-<?= !empty($position['active']) ? 'success' : 'secondary' ?> fw-normal">
                                                    <?= !empty($position['active']) ? lang('App.statusActive') : lang('App.statusInactive') ?>
                                                </span>
                                                <?php if ($can_edit): ?>
                                                    <button type="button" class="btn btn-sm btn-link p-0 edit-leadership-position-btn"
                                                        data-position="<?= esc(json_encode($position), 'attr') ?>"
                                                        title="<?= esc(lang('App.edit'), 'attr') ?>">
                                                        <i class="bi bi-pencil text-warning" aria-hidden="true"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-link p-0 delete-leadership-position-btn"
                                                        data-position-id="<?= (int) ($position['id'] ?? 0) ?>"
                                                        data-title="<?= esc($position['title'] ?? '', 'attr') ?>"
                                                        title="<?= esc(lang('App.delete'), 'attr') ?>">
                                                        <i class="bi bi-trash3 text-danger" aria-hidden="true"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="mt-2 d-flex flex-wrap gap-1">
                                            <span class="badge border border-info text-info fw-normal">
                                                <?= esc($scopeLabel) ?><?= $scopeName !== '' ? ': ' . esc($scopeName) : '' ?>
                                            </span>
                                            <?php if (!empty($position['role_category']) && isset($roleCategoryLabels[$position['role_category']])): ?>
                                                <span class="badge border border-primary text-primary fw-normal"><?= esc($roleCategoryLabels[$position['role_category']]) ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($position['clergy_order']) && isset($clergyOrderLabels[$position['clergy_order']])): ?>
                                                <span class="badge border border-primary text-primary fw-normal"><?= esc($clergyOrderLabels[$position['clergy_order']]) ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($position['employment_type']) && isset($employmentTypeLabels[$position['employment_type']])): ?>
                                                <span class="badge border border-secondary text-secondary fw-normal"><?= esc($employmentTypeLabels[$position['employment_type']]) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mt-1 small text-muted">
                                            <?= lang('Leadership.period') ?>:
                                            <?= !empty($position['started_at']) ? esc($position['started_at']) : '-' ?>
                                            <?= lang('Leadership.to') ?>
                                            <?= !empty($position['ended_at']) ? esc($position['ended_at']) : lang('Leadership.present') ?>
                                        </div>
                                        <?php if (!empty($position['notes'])): ?>
                                            <p class="mb-0 mt-1 small text-muted"><?= esc($position['notes']) ?></p>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($donations) || auth()->user()->can('donation.create')): ?>
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><?= lang('Finance.donationsTitle') ?></h6>
                        <?php if (auth()->user()->can('donation.create') && $userId > 0): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="addDonationBtn">
                                <i class="bi bi-plus-lg" aria-hidden="true"></i> <?= lang('Finance.recordDonation') ?>
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($donations)): ?>
                            <p class="text-muted small mb-0"><?= lang('Finance.noDonationsFound') ?></p>
                        <?php else: ?>
                            <?php
                            $donationTotal = array_sum(array_map(static fn(array $d): float => (float) ($d['amount'] ?? 0), $donations));
                            ?>
                            <p class="fw-semibold small"><?= lang('Finance.totalDonationsLabel') ?>: <?= esc(number_format($donationTotal, 2)) ?></p>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($donations as $donation): ?>
                                    <li class="list-group-item" data-donation-id="<?= (int) ($donation['id'] ?? 0) ?>">
                                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                            <div>
                                                <div class="fw-semibold"><?= esc($donation['category'] ?? lang('Finance.categoryOther')) ?></div>
                                                <small class="text-muted"><?= esc($donation['date'] ?? '') ?> — <?= esc($donation['fund_name'] ?? lang('Finance.unassigned')) ?></small>
                                            </div>
                                            <div class="d-flex gap-1 align-items-center">
                                                <span class="fw-semibold"><?= esc(number_format((float) ($donation['amount'] ?? 0), 2)) ?></span>
                                                <?php if (auth()->user()->can('donation.edit')): ?>
                                                    <button type="button" class="btn btn-sm btn-link p-0 edit-donation-btn"
                                                        data-donation="<?= esc(json_encode($donation), 'attr') ?>"
                                                        title="<?= esc(lang('App.edit'), 'attr') ?>">
                                                        <i class="bi bi-pencil text-warning" aria-hidden="true"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if (auth()->user()->can('donation.delete')): ?>
                                                    <button type="button" class="btn btn-sm btn-link p-0 delete-donation-btn"
                                                        data-donation-id="<?= (int) ($donation['id'] ?? 0) ?>"
                                                        data-amount="<?= esc(number_format((float) ($donation['amount'] ?? 0), 2)) ?>"
                                                        title="<?= esc(lang('App.delete'), 'attr') ?>">
                                                        <i class="bi bi-trash3 text-danger" aria-hidden="true"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if (!empty($donation['payment_method'])): ?>
                                            <small class="text-muted"><?= esc($donation['payment_method']) ?></small>
                                        <?php endif; ?>
                                        <?php if (!empty($donation['notes'])): ?>
                                            <p class="mb-0 mt-1 small text-muted"><?= esc($donation['notes']) ?></p>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($pledges) || auth()->user()->can('annual_pledge.manage')): ?>
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><?= lang('Pledges.pageTitle') ?></h6>
                        <?php if (auth()->user()->can('annual_pledge.manage') && $userId > 0): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="addPledgeBtn">
                                <i class="bi bi-plus-lg" aria-hidden="true"></i> <?= lang('Pledges.addAnnualPledge') ?>
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pledges)): ?>
                            <p class="text-muted small mb-0"><?= lang('Pledges.noPledgesFound') ?></p>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($pledges as $pledge):
                                    $pledgeStatus = (string) ($pledge['fulfillment_status'] ?? 'pledged');
                                    $statusColorMap = [
                                        'pledged' => 'info',
                                        'partial' => 'warning',
                                        'fulfilled' => 'success',
                                        'cancelled' => 'secondary',
                                    ];
                                    $statusLabelMap = [
                                        'pledged' => lang('Pledges.statusPledged'),
                                        'partial' => lang('Pledges.statusPartial'),
                                        'fulfilled' => lang('Pledges.statusFulfilled'),
                                        'cancelled' => lang('Pledges.statusCancelled'),
                                    ];
                                ?>
                                    <li class="list-group-item" data-pledge-id="<?= (int) ($pledge['id'] ?? 0) ?>">
                                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                            <div>
                                                <div class="fw-semibold"><?= (int) ($pledge['pledge_year'] ?? 0) ?></div>
                                                <small class="text-muted">
                                                    <?= esc(number_format((float) ($pledge['pledged_amount'] ?? 0), 2)) ?> /
                                                    <?= esc(number_format((float) ($pledge['fulfilled_amount'] ?? 0), 2)) ?>
                                                </small>
                                            </div>
                                            <div class="d-flex gap-1 align-items-center">
                                                <span class="badge bg-<?= esc($statusColorMap[$pledgeStatus] ?? 'info') ?>"><?= esc($statusLabelMap[$pledgeStatus] ?? $pledgeStatus) ?></span>
                                                <?php if (auth()->user()->can('annual_pledge.manage')): ?>
                                                    <button type="button" class="btn btn-sm btn-link p-0 edit-pledge-btn"
                                                        data-pledge="<?= esc(json_encode($pledge), 'attr') ?>"
                                                        title="<?= esc(lang('App.edit'), 'attr') ?>">
                                                        <i class="bi bi-pencil text-warning" aria-hidden="true"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-link p-0 delete-pledge-btn"
                                                        data-pledge-id="<?= (int) ($pledge['id'] ?? 0) ?>"
                                                        data-year="<?= (int) ($pledge['pledge_year'] ?? 0) ?>"
                                                        title="<?= esc(lang('App.delete'), 'attr') ?>">
                                                        <i class="bi bi-trash3 text-danger" aria-hidden="true"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if (!empty($pledge['notes'])): ?>
                                            <p class="mb-0 mt-1 small text-muted"><?= esc($pledge['notes']) ?></p>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($sacraments) || auth()->user()->can('member.edit')): ?>
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><?= lang('Sacraments.title') ?></h6>
                        <?php if (auth()->user()->can('member.edit') && $personId > 0): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="addSacramentBtn">
                                <i class="bi bi-plus-lg" aria-hidden="true"></i> <?= lang('Sacraments.addSacrament') ?>
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($sacraments)): ?>
                            <p class="text-muted small mb-0"><?= lang('Sacraments.noSacraments') ?></p>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($sacraments as $sacrament): ?>
                                    <li class="list-group-item" data-sacrament-id="<?= (int) ($sacrament['id'] ?? 0) ?>">
                                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                            <div>
                                                <div class="fw-semibold"><?= esc(ucfirst((string) ($sacrament['sacrament_type'] ?? ''))) ?></div>
                                                <?php if (!empty($sacrament['sacrament_date'])): ?>
                                                    <small class="text-muted"><?= esc($sacrament['sacrament_date']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="d-flex gap-1 align-items-center">
                                                <?php if (auth()->user()->can('member.edit')): ?>
                                                    <button type="button" class="btn btn-sm btn-link p-0 edit-sacrament-btn"
                                                        data-sacrament="<?= esc(json_encode($sacrament), 'attr') ?>"
                                                        title="<?= esc(lang('App.edit'), 'attr') ?>">
                                                        <i class="bi bi-pencil text-warning" aria-hidden="true"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-link p-0 delete-sacrament-btn"
                                                        data-sacrament-id="<?= (int) ($sacrament['id'] ?? 0) ?>"
                                                        data-sacrament-type="<?= esc((string) ($sacrament['sacrament_type'] ?? ''), 'attr') ?>"
                                                        title="<?= esc(lang('App.delete'), 'attr') ?>">
                                                        <i class="bi bi-trash3 text-danger" aria-hidden="true"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if (!empty($sacrament['officiant_last_name']) || !empty($sacrament['officiant_first_name'])): ?>
                                            <small class="text-muted">
                                                <?= lang('Sacraments.officiant') ?>:
                                                <?= esc(trim(($sacrament['officiant_first_name'] ?? '') . ' ' . ($sacrament['officiant_last_name'] ?? ''))) ?>
                                            </small>
                                        <?php endif; ?>
                                        <?php if (!empty($sacrament['location_notes'])): ?>
                                            <small class="text-muted d-block"><?= esc($sacrament['location_notes']) ?></small>
                                        <?php endif; ?>
                                        <?php if (!empty($sacrament['certificate_number']) || !empty($sacrament['register_ref'])): ?>
                                            <small class="text-muted d-block">
                                                <?= !empty($sacrament['certificate_number']) ? lang('Sacraments.certificateNumber') . ': ' . esc($sacrament['certificate_number']) : '' ?>
                                                <?= !empty($sacrament['certificate_number']) && !empty($sacrament['register_ref']) ? ' | ' : '' ?>
                                                <?= !empty($sacrament['register_ref']) ? lang('Sacraments.registerRef') . ': ' . esc($sacrament['register_ref']) : '' ?>
                                            </small>
                                        <?php endif; ?>
                                        <?php if (!empty($sacrament['notes'])): ?>
                                            <p class="mb-0 mt-1 small text-muted"><?= esc($sacrament['notes']) ?></p>
                                        <?php endif; ?>

                                        <?php
                                        $sacramentId = (int) ($sacrament['id'] ?? 0);
                                        $participants = $sacrament_participants[$sacramentId] ?? [];
                                        ?>
                                        <div class="mt-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted fw-semibold"><?= lang('Sacraments.participants') ?></small>
                                                <?php if (auth()->user()->can('member.edit')): ?>
                                                    <button type="button" class="btn btn-sm btn-link p-0 add-sacrament-participant-btn"
                                                        data-sacrament-id="<?= $sacramentId ?>"
                                                        title="<?= esc(lang('Sacraments.addParticipant'), 'attr') ?>">
                                                        <i class="bi bi-plus-lg text-primary" aria-hidden="true"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (empty($participants)): ?>
                                                <small class="text-muted fst-italic"><?= lang('Sacraments.noParticipants') ?></small>
                                            <?php else: ?>
                                                <ul class="list-unstyled mb-0 small">
                                                    <?php foreach ($participants as $participant): ?>
                                                        <li class="d-flex justify-content-between align-items-center gap-2 py-1 border-bottom">
                                                            <span><?= esc(($participant['first_name'] ?? '') . ' ' . ($participant['last_name'] ?? '')) ?></span>
                                                            <?php if (auth()->user()->can('member.edit')): ?>
                                                                <div class="d-flex gap-1 align-items-center">
                                                                    <select class="form-select form-select-sm participant-role-select"
                                                                        data-participant-id="<?= (int) ($participant['id'] ?? 0) ?>"
                                                                        style="width: auto; min-width: 110px;">
                                                                        <?php foreach (['Godparent', 'Sponsor', 'Witness', 'Celebrant', 'Parent', 'Spouse', 'Other'] as $role): ?>
                                                                            <option value="<?= $role ?>" <?= (($participant['participant_role'] ?? '') === $role) ? 'selected' : '' ?>><?= lang('Sacraments.participant' . $role) ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                    <button type="button" class="btn btn-sm btn-link p-0 delete-sacrament-participant-btn"
                                                                        data-participant-id="<?= (int) ($participant['id'] ?? 0) ?>"
                                                                        data-name="<?= esc(($participant['first_name'] ?? '') . ' ' . ($participant['last_name'] ?? ''), 'attr') ?>"
                                                                        title="<?= esc(lang('App.delete'), 'attr') ?>">
                                                                        <i class="bi bi-trash3 text-danger" aria-hidden="true"></i>
                                                                    </button>
                                                                </div>
                                                            <?php else: ?>
                                                                <span class="text-muted"><?= esc($participant['participant_role'] ?? '') ?></span>
                                                            <?php endif; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($status_history)): ?>
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><?= lang('Members.statusHistory') ?></h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <?php foreach ($status_history as $entry): ?>
                                <?php
                                $actorName = trim((string) ($entry['actor'] ?? ''));
                                if ($actorName === '') {
                                    $actorId = (int) ($entry['changed_by'] ?? 0);
                                    $actorName = $actorId > 0 ? '#' . $actorId : lang('Members.statusHistorySystem');
                                }
                                $reasonText = trim((string) ($entry['reason'] ?? ''));
                                ?>
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-1">
                                        <div>
                                            <?php if (!empty($entry['from_status'])): ?>
                                                <span class="badge border border-secondary text-secondary fw-normal me-1"><?= esc($entry['from_status']) ?></span>
                                                <i class="bi bi-arrow-right small text-muted" aria-hidden="true"></i>
                                            <?php endif; ?>
                                            <strong><?= esc($entry['to_status'] ?? '') ?></strong>
                                            <?php
                                            $classification = (string) ($entry['classification'] ?? '');
                                            $classificationColors = [
                                                'forward'      => 'success',
                                                'backward'     => 'secondary',
                                                'reactivation' => 'info',
                                                'deactivation' => 'warning',
                                                'terminal'     => 'dark',
                                                'correction'   => 'danger',
                                            ];
                                            $classificationColor = $classificationColors[$classification] ?? '';
                                            if ($classification !== '' && $classification !== 'none' && $classificationColor !== ''):
                                            ?>
                                                <span class="badge border border-<?= $classificationColor ?> text-<?= $classificationColor ?> fw-normal ms-1"><?= esc(lang('Members.transition' . ucfirst($classification))) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($entry['created_at'])): ?>
                                            <small class="text-muted"><?= esc($entry['created_at']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-1 small">
                                        <span class="text-muted"><?= esc(lang('Members.statusHistoryBy')) ?>:</span>
                                        <span><?= esc($actorName) ?></span>
                                    </div>
                                    <?php if ($reasonText !== ''): ?>
                                        <div class="mt-1 small">
                                            <span class="text-muted"><?= esc(lang('Members.statusHistoryReason')) ?>:</span>
                                            <span class="text-muted"><?= esc($reasonText) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($onboarding)): ?>
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><?= lang('Members.onboarding') ?></h6>
                    </div>
                    <div class="card-body">
                        <pre class="bg-light p-2 rounded small" style="white-space: pre-wrap;"><?= esc(json_encode($onboarding, JSON_PRETTY_PRINT)) ?></pre>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($can_edit && $profileId > 0 && $userId > 0): ?>
<div class="modal fade" id="leadershipPositionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="leadershipPositionForm" method="post" action="<?= base_url('people/leadership/' . $profileId) ?>">
                <?= csrf_field() ?>
                <input type="hidden" id="lp_position_id" name="position_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="leadershipPositionModalLabel"><?= lang('Leadership.addPosition') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= lang('App.close') ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="lp_title" class="form-label"><?= lang('Leadership.title') ?> <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="lp_title" name="title" required maxlength="150">
                        </div>
                        <div class="col-md-6">
                            <label for="lp_role_code" class="form-label"><?= lang('Leadership.roleCode') ?></label>
                            <input type="text" class="form-control" id="lp_role_code" name="role_code" maxlength="100">
                        </div>
                        <div class="col-md-6">
                            <label for="lp_scope_type" class="form-label"><?= lang('Leadership.scope') ?> <span class="text-danger">*</span></label>
                            <select class="form-select" id="lp_scope_type" name="scope_type" required>
                                <option value="parish"><?= lang('App.scopeParish') ?></option>
                                <option value="diocese"><?= lang('App.scopeDiocese') ?></option>
                                <option value="province"><?= lang('App.scopeProvince') ?></option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="lp_role_category" class="form-label"><?= lang('Leadership.roleCategory') ?></label>
                            <select class="form-select" id="lp_role_category" name="role_category">
                                <option value=""><?= lang('Leadership.selectPrompt') ?></option>
                                <option value="clergy"><?= lang('Leadership.roleCategoryClergy') ?></option>
                                <option value="lay_staff"><?= lang('Leadership.roleCategoryLayStaff') ?></option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="lp_clergy_order" class="form-label"><?= lang('Leadership.clergyOrder') ?></label>
                            <select class="form-select" id="lp_clergy_order" name="clergy_order">
                                <option value=""><?= lang('Leadership.selectPrompt') ?></option>
                                <option value="bishop"><?= lang('Leadership.clergyOrderBishop') ?></option>
                                <option value="priest"><?= lang('Leadership.clergyOrderPriest') ?></option>
                                <option value="deacon"><?= lang('Leadership.clergyOrderDeacon') ?></option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="lp_employment_type" class="form-label"><?= lang('Leadership.employmentType') ?></label>
                            <select class="form-select" id="lp_employment_type" name="employment_type">
                                <option value=""><?= lang('Leadership.selectPrompt') ?></option>
                                <option value="full_time"><?= lang('Leadership.employmentFullTime') ?></option>
                                <option value="part_time"><?= lang('Leadership.employmentPartTime') ?></option>
                                <option value="non_stipendiary"><?= lang('Leadership.employmentNonStipendiary') ?></option>
                                <option value="unpaid"><?= lang('Leadership.employmentUnpaid') ?></option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="lp_started_at" class="form-label"><?= lang('Leadership.startDate') ?></label>
                            <input type="date" class="form-control" id="lp_started_at" name="started_at">
                        </div>
                        <div class="col-md-6">
                            <label for="lp_ended_at" class="form-label"><?= lang('Leadership.endDate') ?></label>
                            <input type="date" class="form-control" id="lp_ended_at" name="ended_at">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="lp_active" name="active" value="1" checked>
                                <label class="form-check-label" for="lp_active"><?= lang('App.statusActive') ?></label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label for="lp_notes" class="form-label"><?= lang('Leadership.notes') ?></label>
                            <textarea class="form-control" id="lp_notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="submit" class="btn btn-primary" id="saveLeadershipPositionBtn"><?= lang('App.save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (auth()->user()->can('donation.create') && $profileId > 0 && $userId > 0): ?>
<div class="modal fade" id="donationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="donationForm" method="post" action="<?= base_url('people/donation/' . $profileId) ?>">
                <?= csrf_field() ?>
                <input type="hidden" id="donation_id" name="donation_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="donationModalLabel"><?= lang('Finance.recordDonation') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= lang('App.close') ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="d_date" class="form-label"><?= lang('Finance.date') ?> <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="d_date" name="date" required value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="d_amount" class="form-label"><?= lang('Finance.amount') ?> <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" class="form-control" id="d_amount" name="amount" required>
                        </div>
                        <div class="col-md-6">
                            <label for="d_fund_id" class="form-label"><?= lang('Finance.fundLabel') ?> <span class="text-danger">*</span></label>
                            <select class="form-select" id="d_fund_id" name="fund_id" required>
                                <option value=""><?= lang('Finance.selectFund') ?></option>
                                <?php foreach (($funds ?? []) as $fund): ?>
                                    <option value="<?= (int) $fund['id'] ?>" data-fund-type="<?= esc($fund['fund_type'] ?? 'unrestricted', 'attr') ?>">
                                        <?= esc($fund['name'] ?? '') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="d_category" class="form-label"><?= lang('Finance.category') ?> <span class="text-danger">*</span></label>
                            <select class="form-select" id="d_category" name="category" required>
                                <option value="tithe"><?= lang('Finance.categoryTithe') ?></option>
                                <option value="offering"><?= lang('Finance.categoryOffering') ?></option>
                                <option value="building fund"><?= lang('Finance.categoryBuilding') ?></option>
                                <option value="mission"><?= lang('Finance.categoryMission') ?></option>
                                <option value="other"><?= lang('Finance.categoryOther') ?></option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="d_chart_account_id" class="form-label"><?= lang('Finance.chartOfAccountField') ?> <span class="text-danger">*</span></label>
                            <select class="form-select" id="d_chart_account_id" name="chart_account_id" required>
                                <option value=""><?= lang('Finance.selectAccount') ?></option>
                                <?php if (!empty($chart_accounts)): ?>
                                    <?php foreach ($chart_accounts as $groupLabel => $accounts): ?>
                                        <optgroup label="<?= esc($groupLabel) ?>">
                                            <?php foreach ($accounts as $account): ?>
                                                <option value="<?= (int) $account['id'] ?>" data-category="<?= esc((string) ($account['category'] ?? ''), 'attr') ?>">
                                                    <?= esc(($account['code'] ?? '') . ' - ' . ($account['name'] ?? '')) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="d_payment_method" class="form-label"><?= lang('Finance.paymentMethod') ?></label>
                            <select class="form-select" id="d_payment_method" name="payment_method">
                                <option value=""><?= lang('Finance.selectMethod') ?></option>
                                <option value="cash"><?= lang('Finance.methodCash') ?></option>
                                <option value="check"><?= lang('Finance.methodCheck') ?></option>
                                <option value="credit_card"><?= lang('Finance.methodCreditCard') ?></option>
                                <option value="bank_transfer"><?= lang('Finance.methodBankTransfer') ?></option>
                                <option value="online"><?= lang('Finance.methodOnline') ?></option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="d_notes" class="form-label"><?= lang('Finance.notes') ?></label>
                            <textarea class="form-control" id="d_notes" name="notes" rows="3"></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="d_is_restricted" name="is_restricted" value="1">
                                <label class="form-check-label" for="d_is_restricted"><?= lang('Finance.isRestricted') ?></label>
                            </div>
                        </div>
                        <div class="col-12" id="d_restriction_purpose_row">
                            <label for="d_restriction_purpose" class="form-label"><?= lang('Finance.restrictionPurpose') ?></label>
                            <input type="text" class="form-control" id="d_restriction_purpose" name="restriction_purpose">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="submit" class="btn btn-primary" id="saveDonationBtn"><?= lang('App.save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (auth()->user()->can('annual_pledge.manage') && $profileId > 0 && $userId > 0): ?>
<div class="modal fade" id="pledgeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="pledgeForm" method="post" action="<?= base_url('people/pledge/' . $profileId) ?>">
                <?= csrf_field() ?>
                <input type="hidden" id="p_pledge_id" name="pledge_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="pledgeModalLabel"><?= lang('Pledges.addAnnualPledge') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= lang('App.close') ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="p_pledge_year" class="form-label"><?= lang('Pledges.year') ?> <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="p_pledge_year" name="pledge_year" required value="<?= (int) date('Y') ?>" min="2000">
                        </div>
                        <div class="col-md-6">
                            <label for="p_fulfillment_status" class="form-label"><?= lang('Pledges.status') ?> <span class="text-danger">*</span></label>
                            <select class="form-select" id="p_fulfillment_status" name="fulfillment_status" required>
                                <option value="pledged"><?= lang('Pledges.statusPledged') ?></option>
                                <option value="partial"><?= lang('Pledges.statusPartial') ?></option>
                                <option value="fulfilled"><?= lang('Pledges.statusFulfilled') ?></option>
                                <option value="cancelled"><?= lang('Pledges.statusCancelled') ?></option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="p_pledged_amount" class="form-label"><?= lang('Pledges.pledged') ?></label>
                            <input type="number" step="0.01" min="0" class="form-control" id="p_pledged_amount" name="pledged_amount">
                        </div>
                        <div class="col-md-6">
                            <label for="p_fulfilled_amount" class="form-label"><?= lang('Pledges.fulfilled') ?></label>
                            <input type="number" step="0.01" min="0" class="form-control" id="p_fulfilled_amount" name="fulfilled_amount">
                        </div>
                        <div class="col-md-6">
                            <label for="p_pledged_at" class="form-label"><?= lang('Pledges.pledgedAt') ?></label>
                            <input type="date" class="form-control" id="p_pledged_at" name="pledged_at" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-12">
                            <label for="p_notes" class="form-label"><?= lang('Pledges.notes') ?></label>
                            <textarea class="form-control" id="p_notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="submit" class="btn btn-primary" id="savePledgeBtn"><?= lang('App.save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (auth()->user()->can('member.edit') && $profileId > 0 && $personId > 0): ?>
<div class="modal fade" id="sacramentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="sacramentForm" method="post" action="<?= base_url('people/sacrament/' . $profileId) ?>">
                <?= csrf_field() ?>
                <input type="hidden" id="s_sacrament_id" name="sacrament_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="sacramentModalLabel"><?= lang('Sacraments.addSacrament') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= lang('App.close') ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="s_sacrament_type" class="form-label"><?= lang('Sacraments.type') ?> <span class="text-danger">*</span></label>
                            <select class="form-select" id="s_sacrament_type" name="sacrament_type" required>
                                <option value=""><?= lang('Sacraments.selectType') ?></option>
                                <option value="baptism"><?= lang('Sacraments.typeBaptism') ?></option>
                                <option value="confirmation"><?= lang('Sacraments.typeConfirmation') ?></option>
                                <option value="eucharist"><?= lang('Sacraments.typeEucharist') ?></option>
                                <option value="marriage"><?= lang('Sacraments.typeMarriage') ?></option>
                                <option value="burial"><?= lang('Sacraments.typeBurial') ?></option>
                                <option value="reception"><?= lang('Sacraments.typeReception') ?></option>
                                <option value="other"><?= lang('Sacraments.typeOther') ?></option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="s_sacrament_date" class="form-label"><?= lang('Sacraments.date') ?></label>
                            <input type="date" class="form-control" id="s_sacrament_date" name="sacrament_date">
                        </div>
                        <div class="col-md-6">
                            <label for="s_register_ref" class="form-label"><?= lang('Sacraments.registerRef') ?></label>
                            <input type="text" class="form-control" id="s_register_ref" name="register_ref" maxlength="100">
                        </div>
                        <div class="col-md-6">
                            <label for="s_certificate_number" class="form-label"><?= lang('Sacraments.certificateNumber') ?></label>
                            <input type="text" class="form-control" id="s_certificate_number" name="certificate_number" maxlength="100">
                        </div>
                        <div class="col-12">
                            <label for="s_officiant_person_id" class="form-label"><?= lang('Sacraments.officiant') ?></label>
                            <select class="form-select" id="s_officiant_person_id" name="officiant_person_id">
                                <option value=""><?= lang('Sacraments.selectOfficiant') ?></option>
                                <?php foreach (($parish_affiliated_people ?? []) as $pp): ?>
                                    <?php
                                    $ppPersonId = (int) ($pp['person_id'] ?? 0);
                                    if ($ppPersonId <= 0) {
                                        continue;
                                    }
                                    ?>
                                    <option value="<?= $ppPersonId ?>">
                                        <?= esc(($pp['last_name'] ?? '') . ', ' . ($pp['first_name'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="s_location_notes" class="form-label"><?= lang('Sacraments.locationNotes') ?></label>
                            <input type="text" class="form-control" id="s_location_notes" name="location_notes" maxlength="255">
                        </div>
                        <div class="col-12">
                            <label for="s_sacrament_notes" class="form-label"><?= lang('Sacraments.notes') ?></label>
                            <textarea class="form-control" id="s_sacrament_notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="submit" class="btn btn-primary" id="saveSacramentBtn"><?= lang('App.save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (auth()->user()->can('member.edit') && $profileId > 0 && $personId > 0): ?>
<div class="modal fade" id="sacramentParticipantModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="sacramentParticipantForm" method="post" action="<?= base_url('people/sacrament/0/participant') ?>">
                <?= csrf_field() ?>
                <input type="hidden" id="sp_sacrament_id" name="sacrament_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="sacramentParticipantModalLabel"><?= lang('Sacraments.addParticipant') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= lang('App.close') ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="sp_person_id" class="form-label"><?= lang('Sacraments.participant') ?> <span class="text-danger">*</span></label>
                        <select class="form-select" id="sp_person_id" name="person_id" required>
                            <option value=""><?= lang('Sacraments.selectParticipant') ?></option>
                            <?php foreach (($parish_affiliated_people ?? []) as $pp):
                                $ppPersonId = (int) ($pp['person_id'] ?? 0);
                                if ($ppPersonId <= 0) {
                                    continue;
                                }
                            ?>
                                <option value="<?= $ppPersonId ?>"><?= esc(($pp['last_name'] ?? '') . ', ' . ($pp['first_name'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="sp_participant_role" class="form-label"><?= lang('Sacraments.participantRole') ?> <span class="text-danger">*</span></label>
                        <select class="form-select" id="sp_participant_role" name="participant_role" required>
                            <?php foreach (['Godparent', 'Sponsor', 'Witness', 'Celebrant', 'Parent', 'Spouse', 'Other'] as $role): ?>
                                <option value="<?= $role ?>"><?= lang('Sacraments.participant' . $role) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="submit" class="btn btn-primary" id="saveSacramentParticipantBtn"><?= lang('App.save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (auth()->user()->can('family.manage') && $profileId > 0 && $userId > 0): ?>
<div class="modal fade" id="createFamilyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="createFamilyForm" method="post" action="<?= base_url('people/family/' . $profileId . '/create') ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="createFamilyModalLabel"><?= lang('Families.create') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= lang('App.close') ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="cf_family_name" class="form-label"><?= lang('Families.familyName') ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="cf_family_name" name="family_name" required minlength="3" maxlength="255"
                            value="<?= esc(($profile['last_name'] ?? '') . ' Family') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="cf_address" class="form-label"><?= lang('Families.address') ?></label>
                        <textarea class="form-control" id="cf_address" name="address" rows="3"><?= esc($profile['address'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="submit" class="btn btn-primary" id="saveCreateFamilyBtn"><?= lang('App.save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="joinFamilyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="joinFamilyForm" method="post" action="<?= base_url('people/family/' . $profileId . '/join') ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="joinFamilyModalLabel"><?= lang('Families.join') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= lang('App.close') ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="jf_family_id" class="form-label"><?= lang('Families.family') ?> <span class="text-danger">*</span></label>
                        <select class="form-select" id="jf_family_id" name="family_id" required>
                            <option value=""><?= lang('Families.selectFamily') ?></option>
                            <?php foreach (($parish_families ?? []) as $pf): ?>
                                <option value="<?= (int) ($pf['id'] ?? 0) ?>"><?= esc($pf['family_name'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="jf_relationship" class="form-label"><?= lang('Families.relationship') ?></label>
                        <select class="form-select" id="jf_relationship" name="relationship">
                            <?php foreach (['Head', 'Spouse', 'Child', 'Parent', 'Sibling', 'Other'] as $rel): ?>
                                <option value="<?= $rel ?>"><?= lang('Families.relationship' . $rel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="submit" class="btn btn-primary" id="saveJoinFamilyBtn"><?= lang('App.save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="addFamilyMemberModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="addFamilyMemberForm" method="post" action="<?= base_url('families/addMember') ?>">
                <?= csrf_field() ?>
                <input type="hidden" id="afm_family_id" name="family_id" value="<?= (int) ($family['id'] ?? 0) ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="addFamilyMemberModalLabel"><?= lang('Families.addMember') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= lang('App.close') ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="afm_user_id" class="form-label"><?= lang('Families.member') ?> <span class="text-danger">*</span></label>
                        <select class="form-select" id="afm_user_id" name="user_id" required>
                            <option value=""><?= lang('Families.selectMember') ?></option>
                            <?php
                            $currentFamilyUserIds = array_map(static fn(array $m): int => (int) ($m['user_id'] ?? 0), $family_members ?? []);
                            ?>
                            <?php foreach (($parish_affiliated_people ?? []) as $pp):
                                $ppUserId = (int) ($pp['user_id'] ?? 0);
                                if ($ppUserId <= 0 || in_array($ppUserId, $currentFamilyUserIds, true)) {
                                    continue;
                                }
                            ?>
                                <option value="<?= $ppUserId ?>"><?= esc(($pp['last_name'] ?? '') . ', ' . ($pp['first_name'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="afm_relationship" class="form-label"><?= lang('Families.relationship') ?></label>
                        <select class="form-select" id="afm_relationship" name="relationship">
                            <?php foreach (['Head', 'Spouse', 'Child', 'Parent', 'Sibling', 'Other'] as $rel): ?>
                                <option value="<?= $rel ?>"><?= lang('Families.relationship' . $rel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="submit" class="btn btn-primary" id="saveAddFamilyMemberBtn"><?= lang('App.save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
(function() {
    const toggleBtn = document.getElementById('toggleMembershipDetailsEdit');
    const displayDiv = document.getElementById('membershipDetailsDisplay');
    const form = document.getElementById('membershipDetailsForm');
    const cancelBtn = document.getElementById('cancelMembershipDetailsEdit');

    function setEditMode(showForm) {
        if (!displayDiv || !form) return;
        displayDiv.classList.toggle('d-none', showForm);
        form.classList.toggle('d-none', !showForm);
        if (toggleBtn) {
            toggleBtn.classList.toggle('d-none', showForm);
        }
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            setEditMode(true);
        });
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            setEditMode(false);
        });
    }

    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.dataset.originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> <?= esc(lang('App.loading'), 'js') ?>';
            }

            const formData = new FormData(form);
            const body = new URLSearchParams();
            for (const [key, value] of formData.entries()) {
                if (key === '<?= csrf_token() ?>') {
                    body.append(key, value);
                } else {
                    body.append(key, value);
                }
            }

            fetch(form.action, {
                method: 'POST',
                body: body,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            })
            .then(function(response) {
                return response.json().then(function(payload) {
                    return { ok: response.ok, payload: payload };
                });
            })
            .then(function(result) {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = submitBtn.dataset.originalText || '<?= esc(lang('App.save'), 'js') ?>';
                }
                if (result.ok && result.payload && result.payload.success) {
                    if (typeof showAppToast === 'function') {
                        showAppToast(result.payload.message || '<?= esc(lang('People.membershipDetailsSaved'), 'js') ?>', 'success');
                    }
                    window.location.reload();
                    return;
                }
                const message = (result.payload && result.payload.message) ? result.payload.message : '<?= esc(lang('People.membershipDetailsSaveFailed'), 'js') ?>';
                if (typeof showAppToast === 'function') {
                    showAppToast(message, 'danger');
                } else {
                    alert(message);
                }
            })
            .catch(function() {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = submitBtn.dataset.originalText || '<?= esc(lang('App.save'), 'js') ?>';
                }
                if (typeof showAppToast === 'function') {
                    showAppToast('<?= esc(lang('People.membershipDetailsSaveFailed'), 'js') ?>', 'danger');
                } else {
                    alert('<?= esc(lang('People.membershipDetailsSaveFailed'), 'js') ?>');
                }
            });
        });
    }
})();
</script>

<script>
(function() {
    const modalEl = document.getElementById('leadershipPositionModal');
    const form = document.getElementById('leadershipPositionForm');
    const addBtn = document.getElementById('addLeadershipPositionBtn');
    const saveBtn = document.getElementById('saveLeadershipPositionBtn');
    const modalTitle = document.getElementById('leadershipPositionModalLabel');

    function getLeadershipModal() {
        if (!modalEl || typeof window.bootstrap === 'undefined' || typeof window.bootstrap.Modal !== 'function') {
            return null;
        }
        return window.bootstrap.Modal.getOrCreateInstance(modalEl);
    }

    function resetForm() {
        if (!form) return;
        form.reset();
        document.getElementById('lp_position_id').value = '';
        document.getElementById('lp_active').checked = true;
        form.action = '<?= base_url('people/leadership/' . $profileId) ?>';
        if (modalTitle) {
            modalTitle.textContent = '<?= esc(lang('Leadership.addPosition'), 'js') ?>';
        }
    }

    function setField(id, value) {
        const el = document.getElementById(id);
        if (!el) return;
        if (el.tagName === 'SELECT') {
            el.value = value || '';
        } else if (el.type === 'checkbox') {
            el.checked = Boolean(value);
        } else {
            el.value = value || '';
        }
    }

    if (addBtn) {
        addBtn.addEventListener('click', function() {
            resetForm();
            const modal = getLeadershipModal();
            if (modal) modal.show();
        });
    }

    document.querySelectorAll('.edit-leadership-position-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const position = JSON.parse(this.getAttribute('data-position') || '{}');
            const positionId = parseInt(position.id || '0', 10);
            if (!positionId) return;

            resetForm();
            document.getElementById('lp_position_id').value = positionId;
            setField('lp_title', position.title);
            setField('lp_role_code', position.role_code);
            setField('lp_scope_type', position.scope_type);
            setField('lp_role_category', position.role_category);
            setField('lp_clergy_order', position.clergy_order);
            setField('lp_employment_type', position.employment_type);
            setField('lp_started_at', position.started_at);
            setField('lp_ended_at', position.ended_at);
            setField('lp_active', position.active);
            setField('lp_notes', position.notes);

            form.action = '<?= base_url('people/leadership/') ?>' + positionId + '/update';
            if (modalTitle) {
                modalTitle.textContent = '<?= esc(lang('Leadership.editPosition'), 'js') ?>';
            }

            const modal = getLeadershipModal();
            if (modal) modal.show();
        });
    });

    document.querySelectorAll('.delete-leadership-position-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const positionId = parseInt(this.getAttribute('data-position-id') || '0', 10);
            const title = (this.getAttribute('data-title') || '').trim();
            if (!positionId) return;

            const message = title
                ? '<?= esc(lang('Leadership.deleteConfirmNamed'), 'js') ?>'.replace('{0}', title)
                : '<?= esc(lang('Leadership.deleteConfirm'), 'js') ?>';

            if (!confirm(message)) return;

            const body = new URLSearchParams();
            body.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

            fetch('<?= base_url('people/leadership/') ?>' + positionId + '/delete', {
                method: 'POST',
                body: body,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            })
            .then(function(response) {
                return response.json().then(function(payload) {
                    return { ok: response.ok, payload: payload };
                });
            })
            .then(function(result) {
                if (result.ok && result.payload && result.payload.success) {
                    if (typeof showAppToast === 'function') {
                        showAppToast(result.payload.message || '<?= esc(lang('Leadership.deleteSuccess'), 'js') ?>', 'success');
                    }
                    window.location.reload();
                    return;
                }
                const message = (result.payload && result.payload.message) ? result.payload.message : '<?= esc(lang('Leadership.deleteFailed'), 'js') ?>';
                if (typeof showAppToast === 'function') {
                    showAppToast(message, 'danger');
                } else {
                    alert(message);
                }
            })
            .catch(function() {
                if (typeof showAppToast === 'function') {
                    showAppToast('<?= esc(lang('Leadership.deleteFailed'), 'js') ?>', 'danger');
                } else {
                    alert('<?= esc(lang('Leadership.deleteFailed'), 'js') ?>');
                }
            });
        });
    });

    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.dataset.originalText = saveBtn.innerHTML;
                saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> <?= esc(lang('App.loading'), 'js') ?>';
            }

            const formData = new FormData(form);
            const body = new URLSearchParams();
            for (const [key, value] of formData.entries()) {
                body.append(key, value);
            }

            fetch(form.action, {
                method: 'POST',
                body: body,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            })
            .then(function(response) {
                return response.json().then(function(payload) {
                    return { ok: response.ok, payload: payload };
                });
            })
            .then(function(result) {
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = saveBtn.dataset.originalText || '<?= esc(lang('App.save'), 'js') ?>';
                }
                if (result.ok && result.payload && result.payload.success) {
                    if (typeof showAppToast === 'function') {
                        showAppToast(result.payload.message || '<?= esc(lang('Leadership.saveSuccess'), 'js') ?>', 'success');
                    }
                    window.location.reload();
                    return;
                }
                const message = (result.payload && result.payload.message) ? result.payload.message : '<?= esc(lang('Leadership.saveFailed'), 'js') ?>';
                if (typeof showAppToast === 'function') {
                    showAppToast(message, 'danger');
                } else {
                    alert(message);
                }
            })
            .catch(function() {
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = saveBtn.dataset.originalText || '<?= esc(lang('App.save'), 'js') ?>';
                }
                if (typeof showAppToast === 'function') {
                    showAppToast('<?= esc(lang('Leadership.saveFailed'), 'js') ?>', 'danger');
                } else {
                    alert('<?= esc(lang('Leadership.saveFailed'), 'js') ?>');
                }
            });
        });
    }
})();
</script>

<script>
(function() {
    function getModalInstance(modalEl) {
        if (!modalEl || typeof window.bootstrap === 'undefined' || typeof window.bootstrap.Modal !== 'function') {
            return null;
        }
        return window.bootstrap.Modal.getOrCreateInstance(modalEl);
    }

    function setFieldValue(id, value) {
        const el = document.getElementById(id);
        if (!el) return;
        if (el.tagName === 'SELECT') {
            el.value = value || '';
        } else if (el.type === 'checkbox') {
            el.checked = Boolean(value);
        } else {
            el.value = value || '';
        }
    }

    function ajaxFormSubmit(form, saveBtn, successMessageKey, errorMessageKey) {
        return new Promise(function(resolve) {
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.dataset.originalText = saveBtn.innerHTML;
                saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> <?= esc(lang('App.loading'), 'js') ?>';
            }

            const body = new URLSearchParams();
            const formData = new FormData(form);
            for (const [key, value] of formData.entries()) {
                body.append(key, value);
            }

            fetch(form.action, {
                method: 'POST',
                body: body,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            })
            .then(function(response) {
                return response.json().then(function(payload) {
                    return { ok: response.ok, payload: payload };
                });
            })
            .then(function(result) {
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = saveBtn.dataset.originalText || '<?= esc(lang('App.save'), 'js') ?>';
                }
                if (result.ok && result.payload && result.payload.success) {
                    if (typeof showAppToast === 'function') {
                        showAppToast(result.payload.message || successMessageKey, 'success');
                    }
                    window.location.reload();
                    return;
                }
                const message = (result.payload && result.payload.message) ? result.payload.message : errorMessageKey;
                if (typeof showAppToast === 'function') {
                    showAppToast(message, 'danger');
                } else {
                    alert(message);
                }
                resolve();
            })
            .catch(function() {
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = saveBtn.dataset.originalText || '<?= esc(lang('App.save'), 'js') ?>';
                }
                if (typeof showAppToast === 'function') {
                    showAppToast(errorMessageKey, 'danger');
                } else {
                    alert(errorMessageKey);
                }
                resolve();
            });
        });
    }

    // ── Donations ─────────────────────────────────────────────────────────────
    const donationModalEl = document.getElementById('donationModal');
    const donationForm = document.getElementById('donationForm');
    const addDonationBtn = document.getElementById('addDonationBtn');
    const donationModalTitle = document.getElementById('donationModalLabel');
    const donationSaveBtn = document.getElementById('saveDonationBtn');
    const isRestrictedChk = document.getElementById('d_is_restricted');
    const restrictionRow = document.getElementById('d_restriction_purpose_row');

    function toggleRestrictionPurpose() {
        if (restrictionRow) {
            restrictionRow.style.display = isRestrictedChk && isRestrictedChk.checked ? '' : 'none';
        }
    }
    if (isRestrictedChk) {
        isRestrictedChk.addEventListener('change', toggleRestrictionPurpose);
        toggleRestrictionPurpose();
    }

    function syncDonationChartAccount() {
        const category = document.getElementById('d_category')?.value || '';
        const select = document.getElementById('d_chart_account_id');
        if (!select) return;
        if (select.value) return;
        const match = select.querySelector('option[data-category="' + category + '"]');
        if (match) {
            select.value = match.value;
        }
    }
    document.getElementById('d_category')?.addEventListener('change', function() {
        const select = document.getElementById('d_chart_account_id');
        if (select) select.value = '';
        syncDonationChartAccount();
    });

    function resetDonationForm() {
        if (!donationForm) return;
        donationForm.reset();
        document.getElementById('donation_id').value = '';
        setFieldValue('d_date', '<?= date('Y-m-d') ?>');
        if (donationModalTitle) {
            donationModalTitle.textContent = '<?= esc(lang('Finance.recordDonation'), 'js') ?>';
        }
        donationForm.action = '<?= base_url('people/donation/' . $profileId) ?>';
        syncDonationChartAccount();
        toggleRestrictionPurpose();
    }

    if (addDonationBtn) {
        addDonationBtn.addEventListener('click', function() {
            resetDonationForm();
            const modal = getModalInstance(donationModalEl);
            if (modal) modal.show();
        });
    }

    document.querySelectorAll('.edit-donation-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const donation = JSON.parse(this.getAttribute('data-donation') || '{}');
            const donationId = parseInt(donation.id || '0', 10);
            if (!donationId) return;

            resetDonationForm();
            document.getElementById('donation_id').value = donationId;
            setFieldValue('d_date', donation.date);
            setFieldValue('d_amount', donation.amount);
            setFieldValue('d_fund_id', donation.fund_id);
            setFieldValue('d_category', donation.category);
            setFieldValue('d_chart_account_id', donation.chart_account_id);
            setFieldValue('d_payment_method', donation.payment_method);
            setFieldValue('d_notes', donation.notes);
            setFieldValue('d_is_restricted', donation.is_restricted);
            setFieldValue('d_restriction_purpose', donation.restriction_purpose);

            donationForm.action = '<?= base_url('people/donation/') ?>' + donationId + '/update';
            if (donationModalTitle) {
                donationModalTitle.textContent = '<?= esc(lang('App.edit'), 'js') ?> <?= esc(lang('Finance.donationsTitle'), 'js') ?>';
            }
            toggleRestrictionPurpose();

            const modal = getModalInstance(donationModalEl);
            if (modal) modal.show();
        });
    });

    document.querySelectorAll('.delete-donation-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const donationId = parseInt(this.getAttribute('data-donation-id') || '0', 10);
            const amount = (this.getAttribute('data-amount') || '').trim();
            if (!donationId) return;

            const message = amount
                ? '<?= esc(lang('Finance.deleteDonationConfirm'), 'js') ?>'.replace('{0}', amount)
                : '<?= esc(lang('Finance.deleteDonationConfirmNoAmount'), 'js') ?>';

            if (!confirm(message)) return;

            const body = new URLSearchParams();
            body.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

            fetch('<?= base_url('people/donation/') ?>' + donationId + '/delete', {
                method: 'POST',
                body: body,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            })
            .then(function(response) {
                return response.json().then(function(payload) {
                    return { ok: response.ok, payload: payload };
                });
            })
            .then(function(result) {
                if (result.ok && result.payload && result.payload.success) {
                    if (typeof showAppToast === 'function') {
                        showAppToast(result.payload.message || '<?= esc(lang('Treasurer.donationDeleted'), 'js') ?>', 'success');
                    }
                    window.location.reload();
                    return;
                }
                const message = (result.payload && result.payload.message) ? result.payload.message : '<?= esc(lang('Treasurer.donationDeleteFailed'), 'js') ?>';
                if (typeof showAppToast === 'function') {
                    showAppToast(message, 'danger');
                } else {
                    alert(message);
                }
            })
            .catch(function() {
                if (typeof showAppToast === 'function') {
                    showAppToast('<?= esc(lang('Treasurer.donationDeleteFailed'), 'js') ?>', 'danger');
                } else {
                    alert('<?= esc(lang('Treasurer.donationDeleteFailed'), 'js') ?>');
                }
            });
        });
    });

    if (donationForm) {
        donationForm.addEventListener('submit', function(e) {
            e.preventDefault();
            ajaxFormSubmit(donationForm, donationSaveBtn, '<?= esc(lang('Treasurer.donationRecorded'), 'js') ?>', '<?= esc(lang('Treasurer.donationRecordFailed'), 'js') ?>');
        });
    }

    // ── Pledges ───────────────────────────────────────────────────────────────
    const pledgeModalEl = document.getElementById('pledgeModal');
    const pledgeForm = document.getElementById('pledgeForm');
    const addPledgeBtn = document.getElementById('addPledgeBtn');
    const pledgeModalTitle = document.getElementById('pledgeModalLabel');
    const pledgeSaveBtn = document.getElementById('savePledgeBtn');

    function resetPledgeForm() {
        if (!pledgeForm) return;
        pledgeForm.reset();
        document.getElementById('p_pledge_id').value = '';
        setFieldValue('p_pledge_year', '<?= (int) date('Y') ?>');
        if (pledgeModalTitle) {
            pledgeModalTitle.textContent = '<?= esc(lang('Pledges.addAnnualPledge'), 'js') ?>';
        }
        pledgeForm.action = '<?= base_url('people/pledge/' . $profileId) ?>';
    }

    if (addPledgeBtn) {
        addPledgeBtn.addEventListener('click', function() {
            resetPledgeForm();
            const modal = getModalInstance(pledgeModalEl);
            if (modal) modal.show();
        });
    }

    document.querySelectorAll('.edit-pledge-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const pledge = JSON.parse(this.getAttribute('data-pledge') || '{}');
            const pledgeId = parseInt(pledge.id || '0', 10);
            if (!pledgeId) return;

            resetPledgeForm();
            document.getElementById('p_pledge_id').value = pledgeId;
            setFieldValue('p_pledge_year', pledge.pledge_year);
            setFieldValue('p_pledged_amount', pledge.pledged_amount);
            setFieldValue('p_fulfilled_amount', pledge.fulfilled_amount);
            setFieldValue('p_fulfillment_status', pledge.fulfillment_status);
            setFieldValue('p_pledged_at', pledge.pledged_at);
            setFieldValue('p_notes', pledge.notes);

            pledgeForm.action = '<?= base_url('people/pledge/') ?>' + pledgeId + '/update';
            if (pledgeModalTitle) {
                pledgeModalTitle.textContent = '<?= esc(lang('Pledges.edit'), 'js') ?> <?= esc(lang('Pledges.pageTitle'), 'js') ?>';
            }

            const modal = getModalInstance(pledgeModalEl);
            if (modal) modal.show();
        });
    });

    document.querySelectorAll('.delete-pledge-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const pledgeId = parseInt(this.getAttribute('data-pledge-id') || '0', 10);
            const year = this.getAttribute('data-year') || '';
            if (!pledgeId) return;

            const message = year
                ? '<?= esc(lang('Pledges.deleteConfirmNamed'), 'js') ?>'.replace('{0}', year)
                : '<?= esc(lang('Pledges.deleteConfirm'), 'js') ?>';

            if (!confirm(message)) return;

            const body = new URLSearchParams();
            body.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

            fetch('<?= base_url('people/pledge/') ?>' + pledgeId + '/delete', {
                method: 'POST',
                body: body,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            })
            .then(function(response) {
                return response.json().then(function(payload) {
                    return { ok: response.ok, payload: payload };
                });
            })
            .then(function(result) {
                if (result.ok && result.payload && result.payload.success) {
                    if (typeof showAppToast === 'function') {
                        showAppToast(result.payload.message || '<?= esc(lang('Pledges.deleteSuccess'), 'js') ?>', 'success');
                    }
                    window.location.reload();
                    return;
                }
                const message = (result.payload && result.payload.message) ? result.payload.message : '<?= esc(lang('Pledges.deleteFailed'), 'js') ?>';
                if (typeof showAppToast === 'function') {
                    showAppToast(message, 'danger');
                } else {
                    alert(message);
                }
            })
            .catch(function() {
                if (typeof showAppToast === 'function') {
                    showAppToast('<?= esc(lang('Pledges.deleteFailed'), 'js') ?>', 'danger');
                } else {
                    alert('<?= esc(lang('Pledges.deleteFailed'), 'js') ?>');
                }
            });
        });
    });

    if (pledgeForm) {
        pledgeForm.addEventListener('submit', function(e) {
            e.preventDefault();
            ajaxFormSubmit(pledgeForm, pledgeSaveBtn, '<?= esc(lang('App.pledgeSavedSuccess'), 'js') ?>', '<?= esc(lang('Pledges.saveFailed'), 'js') ?>');
        });
    }

    // ── Sacraments ────────────────────────────────────────────────────────────
    const sacramentModalEl = document.getElementById('sacramentModal');
    const sacramentForm = document.getElementById('sacramentForm');
    const addSacramentBtn = document.getElementById('addSacramentBtn');
    const sacramentModalTitle = document.getElementById('sacramentModalLabel');
    const sacramentSaveBtn = document.getElementById('saveSacramentBtn');

    function resetSacramentForm() {
        if (!sacramentForm) return;
        sacramentForm.reset();
        document.getElementById('s_sacrament_id').value = '';
        if (sacramentModalTitle) {
            sacramentModalTitle.textContent = '<?= esc(lang('Sacraments.addSacrament'), 'js') ?>';
        }
        sacramentForm.action = '<?= base_url('people/sacrament/' . $profileId) ?>';
    }

    if (addSacramentBtn) {
        addSacramentBtn.addEventListener('click', function() {
            resetSacramentForm();
            const modal = getModalInstance(sacramentModalEl);
            if (modal) modal.show();
        });
    }

    document.querySelectorAll('.edit-sacrament-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const sacrament = JSON.parse(this.getAttribute('data-sacrament') || '{}');
            const sacramentId = parseInt(sacrament.id || '0', 10);
            if (!sacramentId) return;

            resetSacramentForm();
            document.getElementById('s_sacrament_id').value = sacramentId;
            setFieldValue('s_sacrament_type', sacrament.sacrament_type);
            setFieldValue('s_sacrament_date', sacrament.sacrament_date);
            setFieldValue('s_register_ref', sacrament.register_ref);
            setFieldValue('s_certificate_number', sacrament.certificate_number);
            setFieldValue('s_officiant_person_id', sacrament.officiant_person_id);
            setFieldValue('s_location_notes', sacrament.location_notes);
            setFieldValue('s_sacrament_notes', sacrament.notes);

            sacramentForm.action = '<?= base_url('people/sacrament/') ?>' + sacramentId + '/update';
            if (sacramentModalTitle) {
                sacramentModalTitle.textContent = '<?= esc(lang('Sacraments.editSacrament'), 'js') ?>';
            }

            const modal = getModalInstance(sacramentModalEl);
            if (modal) modal.show();
        });
    });

    document.querySelectorAll('.delete-sacrament-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const sacramentId = parseInt(this.getAttribute('data-sacrament-id') || '0', 10);
            const sacramentType = (this.getAttribute('data-sacrament-type') || '').trim();
            if (!sacramentId) return;

            const message = sacramentType
                ? '<?= esc(lang('Sacraments.deleteConfirmNamed'), 'js') ?>'.replace('{0}', sacramentType)
                : '<?= esc(lang('Sacraments.deleteConfirm'), 'js') ?>';

            if (!confirm(message)) return;

            const body = new URLSearchParams();
            body.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

            fetch('<?= base_url('people/sacrament/') ?>' + sacramentId + '/delete', {
                method: 'POST',
                body: body,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            })
            .then(function(response) {
                return response.json().then(function(payload) {
                    return { ok: response.ok, payload: payload };
                });
            })
            .then(function(result) {
                if (result.ok && result.payload && result.payload.success) {
                    if (typeof showAppToast === 'function') {
                        showAppToast(result.payload.message || '<?= esc(lang('Sacraments.deleteSuccess'), 'js') ?>', 'success');
                    }
                    window.location.reload();
                    return;
                }
                const message = (result.payload && result.payload.message) ? result.payload.message : '<?= esc(lang('Sacraments.deleteFailed'), 'js') ?>';
                if (typeof showAppToast === 'function') {
                    showAppToast(message, 'danger');
                } else {
                    alert(message);
                }
            })
            .catch(function() {
                if (typeof showAppToast === 'function') {
                    showAppToast('<?= esc(lang('Sacraments.deleteFailed'), 'js') ?>', 'danger');
                } else {
                    alert('<?= esc(lang('Sacraments.deleteFailed'), 'js') ?>');
                }
            });
        });
    });

    if (sacramentForm) {
        sacramentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            ajaxFormSubmit(sacramentForm, sacramentSaveBtn, '<?= esc(lang('Sacraments.saveSuccess'), 'js') ?>', '<?= esc(lang('Sacraments.saveFailed'), 'js') ?>');
        });
    }

    // ── Sacrament participants ──────────────────────────────────────────────────
    const sacramentParticipantModalEl = document.getElementById('sacramentParticipantModal');
    const sacramentParticipantForm = document.getElementById('sacramentParticipantForm');
    const saveSacramentParticipantBtn = document.getElementById('saveSacramentParticipantBtn');
    const sacramentParticipantRecordIdField = document.getElementById('sp_sacrament_id');

    document.querySelectorAll('.add-sacrament-participant-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const recordId = this.getAttribute('data-sacrament-id');
            if (!recordId || !sacramentParticipantForm || !sacramentParticipantRecordIdField) return;
            sacramentParticipantForm.reset();
            sacramentParticipantRecordIdField.value = recordId;
            sacramentParticipantForm.action = '<?= base_url('people/sacrament/') ?>' + recordId + '/participant';
            const modal = getModalInstance(sacramentParticipantModalEl);
            if (modal) modal.show();
        });
    });

    if (sacramentParticipantForm) {
        sacramentParticipantForm.addEventListener('submit', function(e) {
            e.preventDefault();
            ajaxFormSubmit(sacramentParticipantForm, saveSacramentParticipantBtn, '<?= esc(lang('Sacraments.participantAddSuccess'), 'js') ?>', '<?= esc(lang('Sacraments.participantAddFailed'), 'js') ?>');
        });
    }

    document.querySelectorAll('.participant-role-select').forEach(function(select) {
        select.addEventListener('change', function() {
            const participantId = this.getAttribute('data-participant-id');
            const newRole = this.value;
            const originalValue = this.dataset.originalValue || '';
            if (!participantId || !newRole) return;

            const body = new URLSearchParams();
            body.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
            body.append('participant_role', newRole);

            fetch('<?= base_url('people/sacrament/participant/') ?>' + participantId + '/update', {
                method: 'POST',
                body: body,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            })
            .then(function(response) {
                return response.json().then(function(payload) {
                    return { ok: response.ok, payload: payload };
                });
            })
            .then(function(result) {
                if (result.ok && result.payload && result.payload.success) {
                    if (typeof showAppToast === 'function') {
                        showAppToast(result.payload.message || '<?= esc(lang('Sacraments.participantUpdateSuccess'), 'js') ?>', 'success');
                    }
                    select.dataset.originalValue = newRole;
                    return;
                }
                if (typeof showAppToast === 'function') {
                    showAppToast((result.payload && result.payload.message) || '<?= esc(lang('Sacraments.participantUpdateFailed'), 'js') ?>', 'danger');
                }
                select.value = originalValue;
            })
            .catch(function() {
                if (typeof showAppToast === 'function') {
                    showAppToast('<?= esc(lang('Sacraments.participantUpdateFailed'), 'js') ?>', 'danger');
                }
                select.value = originalValue;
            });
        });
        select.dataset.originalValue = select.value;
    });

    document.querySelectorAll('.delete-sacrament-participant-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const participantId = parseInt(this.getAttribute('data-participant-id') || '0', 10);
            const name = (this.getAttribute('data-name') || '').trim();
            if (!participantId) return;

            const message = name
                ? '<?= esc(lang('Sacraments.participantDeleteConfirmNamed'), 'js') ?>'.replace('{0}', name)
                : '<?= esc(lang('Sacraments.participantDeleteConfirm'), 'js') ?>';

            if (!confirm(message)) return;

            const body = new URLSearchParams();
            body.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

            fetch('<?= base_url('people/sacrament/participant/') ?>' + participantId + '/delete', {
                method: 'POST',
                body: body,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            })
            .then(function(response) {
                return response.json().then(function(payload) {
                    return { ok: response.ok, payload: payload };
                });
            })
            .then(function(result) {
                if (result.ok && result.payload && result.payload.success) {
                    if (typeof showAppToast === 'function') {
                        showAppToast(result.payload.message || '<?= esc(lang('Sacraments.participantDeleteSuccess'), 'js') ?>', 'success');
                    }
                    window.location.reload();
                    return;
                }
                const message = (result.payload && result.payload.message) ? result.payload.message : '<?= esc(lang('Sacraments.participantDeleteFailed'), 'js') ?>';
                if (typeof showAppToast === 'function') {
                    showAppToast(message, 'danger');
                } else {
                    alert(message);
                }
            })
            .catch(function() {
                if (typeof showAppToast === 'function') {
                    showAppToast('<?= esc(lang('Sacraments.participantDeleteFailed'), 'js') ?>', 'danger');
                } else {
                    alert('<?= esc(lang('Sacraments.participantDeleteFailed'), 'js') ?>');
                }
            });
        });
    });

    // ── Family management ─────────────────────────────────────────────────────
    const createFamilyBtn = document.getElementById('createFamilyBtn');
    const joinFamilyBtn = document.getElementById('joinFamilyBtn');
    const addFamilyMemberBtn = document.getElementById('addFamilyMemberBtn');
    const leaveFamilyBtn = document.getElementById('leaveFamilyBtn');
    const createFamilyModalEl = document.getElementById('createFamilyModal');
    const joinFamilyModalEl = document.getElementById('joinFamilyModal');
    const addFamilyMemberModalEl = document.getElementById('addFamilyMemberModal');
    const createFamilyForm = document.getElementById('createFamilyForm');
    const joinFamilyForm = document.getElementById('joinFamilyForm');
    const addFamilyMemberForm = document.getElementById('addFamilyMemberForm');
    const saveCreateFamilyBtn = document.getElementById('saveCreateFamilyBtn');
    const saveJoinFamilyBtn = document.getElementById('saveJoinFamilyBtn');
    const saveAddFamilyMemberBtn = document.getElementById('saveAddFamilyMemberBtn');

    if (createFamilyBtn) {
        createFamilyBtn.addEventListener('click', function() {
            if (createFamilyForm) createFamilyForm.reset();
            const modal = getModalInstance(createFamilyModalEl);
            if (modal) modal.show();
        });
    }

    if (joinFamilyBtn) {
        joinFamilyBtn.addEventListener('click', function() {
            if (joinFamilyForm) joinFamilyForm.reset();
            const modal = getModalInstance(joinFamilyModalEl);
            if (modal) modal.show();
        });
    }

    if (addFamilyMemberBtn) {
        addFamilyMemberBtn.addEventListener('click', function() {
            if (addFamilyMemberForm) addFamilyMemberForm.reset();
            const modal = getModalInstance(addFamilyMemberModalEl);
            if (modal) modal.show();
        });
    }

    if (createFamilyForm) {
        createFamilyForm.addEventListener('submit', function(e) {
            e.preventDefault();
            ajaxFormSubmit(createFamilyForm, saveCreateFamilyBtn, '<?= esc(lang('Families.createSuccess'), 'js') ?>', '<?= esc(lang('Families.createFailed'), 'js') ?>');
        });
    }

    if (joinFamilyForm) {
        joinFamilyForm.addEventListener('submit', function(e) {
            e.preventDefault();
            ajaxFormSubmit(joinFamilyForm, saveJoinFamilyBtn, '<?= esc(lang('Families.memberAddSuccess'), 'js') ?>', '<?= esc(lang('Families.failedAddMember'), 'js') ?>');
        });
    }

    if (addFamilyMemberForm) {
        addFamilyMemberForm.addEventListener('submit', function(e) {
            e.preventDefault();
            ajaxFormSubmit(addFamilyMemberForm, saveAddFamilyMemberBtn, '<?= esc(lang('Families.memberAddSuccess'), 'js') ?>', '<?= esc(lang('Families.failedAddMember'), 'js') ?>');
        });
    }

    if (leaveFamilyBtn) {
        leaveFamilyBtn.addEventListener('click', function() {
            if (!confirm('<?= esc(lang('Families.leaveConfirm'), 'js') ?>')) return;

            const body = new URLSearchParams();
            body.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

            fetch('<?= base_url('people/family/' . $profileId . '/leave') ?>', {
                method: 'POST',
                body: body,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            })
            .then(function(response) {
                return response.json().then(function(payload) {
                    return { ok: response.ok, payload: payload };
                });
            })
            .then(function(result) {
                if (result.ok && result.payload && result.payload.success) {
                    if (typeof showAppToast === 'function') {
                        showAppToast(result.payload.message || '<?= esc(lang('Families.memberRemoveSuccess'), 'js') ?>', 'success');
                    }
                    window.location.reload();
                    return;
                }
                const message = (result.payload && result.payload.message) ? result.payload.message : '<?= esc(lang('Families.failedRemoveMember'), 'js') ?>';
                if (typeof showAppToast === 'function') {
                    showAppToast(message, 'danger');
                } else {
                    alert(message);
                }
            })
            .catch(function() {
                if (typeof showAppToast === 'function') {
                    showAppToast('<?= esc(lang('Families.failedRemoveMember'), 'js') ?>', 'danger');
                } else {
                    alert('<?= esc(lang('Families.failedRemoveMember'), 'js') ?>');
                }
            });
        });
    }

    document.querySelectorAll('.relationship-select').forEach(function(select) {
        select.addEventListener('change', function() {
            const familyMemberId = this.getAttribute('data-family-member-id');
            const newRelationship = this.value;
            const originalValue = this.dataset.originalValue || '';
            if (!familyMemberId || !newRelationship) return;

            const body = new URLSearchParams();
            body.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
            body.append('relationship', newRelationship);
            body.append('family_member_id', familyMemberId);

            fetch('<?= base_url('people/family/' . $profileId . '/relationship') ?>', {
                method: 'POST',
                body: body,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            })
            .then(function(response) {
                return response.json().then(function(payload) {
                    return { ok: response.ok, payload: payload };
                });
            })
            .then(function(result) {
                if (result.ok && result.payload && result.payload.success) {
                    if (typeof showAppToast === 'function') {
                        showAppToast(result.payload.message || '<?= esc(lang('Families.updateSuccess'), 'js') ?>', 'success');
                    }
                    return;
                }
                if (typeof showAppToast === 'function') {
                    showAppToast((result.payload && result.payload.message) || '<?= esc(lang('Families.updateFailed'), 'js') ?>', 'danger');
                }
                select.value = originalValue;
            })
            .catch(function() {
                if (typeof showAppToast === 'function') {
                    showAppToast('<?= esc(lang('Families.updateFailed'), 'js') ?>', 'danger');
                }
                select.value = originalValue;
            });
        });
        select.dataset.originalValue = select.value;
    });

    document.querySelectorAll('.remove-family-member-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const familyMemberId = parseInt(this.getAttribute('data-family-member-id') || '0', 10);
            const name = (this.getAttribute('data-name') || '').trim();
            if (!familyMemberId) return;

            const message = name
                ? '<?= esc(lang('Families.removeMemberConfirmNamed'), 'js') ?>'.replace('{0}', name)
                : '<?= esc(lang('Families.removeMemberConfirm'), 'js') ?>';

            if (!confirm(message)) return;

            const body = new URLSearchParams();
            body.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

            fetch('<?= base_url('families/removeMember/') ?>' + familyMemberId, {
                method: 'POST',
                body: body,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            })
            .then(function(response) {
                return response.json().then(function(payload) {
                    return { ok: response.ok, payload: payload };
                });
            })
            .then(function(result) {
                if (result.ok && result.payload && result.payload.success) {
                    if (typeof showAppToast === 'function') {
                        showAppToast(result.payload.message || '<?= esc(lang('Families.memberRemoveSuccess'), 'js') ?>', 'success');
                    }
                    window.location.reload();
                    return;
                }
                const message = (result.payload && result.payload.message) ? result.payload.message : '<?= esc(lang('Families.failedRemoveMember'), 'js') ?>';
                if (typeof showAppToast === 'function') {
                    showAppToast(message, 'danger');
                } else {
                    alert(message);
                }
            })
            .catch(function() {
                if (typeof showAppToast === 'function') {
                    showAppToast('<?= esc(lang('Families.failedRemoveMember'), 'js') ?>', 'danger');
                } else {
                    alert('<?= esc(lang('Families.failedRemoveMember'), 'js') ?>');
                }
            });
        });
    });
})();
</script>

<?= $this->endSection() ?>
