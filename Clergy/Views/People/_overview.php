<?php /* Partial: right column top — sacrament discrepancy alert, membership details, quick actions, affiliations, group memberships. Required: $sacrament_discrepancies, $membership_details, $can_edit, $personId, $parishId, $affiliations, $group_memberships, $pathway_stage */ ?>
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
