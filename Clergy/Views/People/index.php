<?= $this->extend('Clergy\\Views\\layouts/main') ?>

<?= $this->section('content') ?>

<style>
    .people-name-link {
        text-decoration: none;
        font-size: inherit;
        font-weight: inherit;
        color: var(--bs-primary);
    }

    .people-name-link:hover {
        text-decoration: underline;
    }

    .person-avatar {
        width: 36px;
        height: 36px;
        border-radius: 6px;
        object-fit: cover;
        flex-shrink: 0;
    }

    .person-avatar-fallback {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        flex-shrink: 0;
        user-select: none;
    }

    .person-avatar-fallback-icon {
        font-size: 1.1rem;
        color: #adb5bd;
        line-height: 1;
    }
</style>

<?php
$scope = (string) ($selected_scope ?? 'all');
$status = (string) ($selected_status ?? '');
$selectedPerPage = (int) ($per_page ?? 25);
if (!in_array($selectedPerPage, [25, 50, 100], true)) {
    $selectedPerPage = 25;
}
$canPortalAccessManage = auth()->user()->can('member.edit');
if (!$canPortalAccessManage) {
    try {
        $canPortalAccessManage = auth()->user()->can('portal.manage');
    } catch (\Throwable $e) {
        $canPortalAccessManage = false;
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-3 gap-2">
    <div class="d-flex gap-2">
        <?php if (auth()->user()->can('member.create')): ?>
            <a href="<?= base_url('members/create') ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-person-plus" aria-hidden="true"></i> <?= lang('Services.addPerson') ?>
            </a>
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createNonPortalPersonModal">
                <i class="bi bi-person-exclamation" aria-hidden="true"></i> <?= lang('Members.createStandaloneNonPortal') ?>
            </button>
        <?php endif; ?>
        <?php if (auth()->user()->can('member.delete')): ?>
            <button type="button" id="bulk-delete-people-btn" class="btn btn-outline-danger btn-sm d-none">
                <i class="bi bi-trash3" aria-hidden="true"></i> <?= lang('Members.bulkDeleteSelected') ?>
            </button>
        <?php endif; ?>
        <?php if (auth()->user()->can('member.edit')): ?>
            <button type="button" id="bulk-mark-member-btn" class="btn btn-outline-success btn-sm d-none">
                <i class="bi bi-house-check" aria-hidden="true"></i> <?= lang('People.markAsMember') ?>
            </button>
            <button type="button" id="bulk-remove-member-btn" class="btn btn-outline-secondary btn-sm d-none">
                <i class="bi bi-person-dash" aria-hidden="true"></i> <?= lang('People.removeMemberTag') ?>
            </button>
        <?php endif; ?>
        <form id="people-export-form" method="post" action="<?= base_url('people/export') ?>">
            <?= csrf_field() ?>
            <div id="bulk-export-ids"></div>
            <button type="submit" id="bulk-export-btn" class="btn btn-outline-secondary btn-sm d-none">
                <i class="bi bi-download" aria-hidden="true"></i> <?= lang('App.export') ?>
            </button>
        </form>
    </div>
    <?php if (!empty($can_moderate_registrations) && (int) ($pending_registrations_count ?? 0) > 0): ?>
        <a href="#pendingRegistrationsCard" class="btn btn-outline-warning btn-sm">
            <i class="bi bi-person-check" aria-hidden="true"></i> <?= lang('Members.pendingRegistrationsTitle') ?>
            <span class="badge border border-warning text-warning fw-normal ms-1"><?= (int) ($pending_registrations_count ?? 0) ?></span>
        </a>
    <?php endif; ?>
</div>

<div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-end filter-panel-header">
        <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#peopleFilterPanel" aria-expanded="false">
            <i class="bi bi-funnel" aria-hidden="true"></i> <?= lang('App.filter') ?>
        </button>
    </div>
    <div id="peopleFilterPanel" class="collapse">
        <div class="card-body border-top">
            <form method="get" action="<?= base_url('people') ?>" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label mb-1"><?= lang('People.scopeFilter') ?></label>
                    <select name="scope" class="form-select form-select-sm">
                        <option value="all" <?= $scope === 'all' ? 'selected' : '' ?>><?= lang('People.scopeAll') ?></option>
                        <option value="members" <?= $scope === 'members' ? 'selected' : '' ?>><?= lang('People.scopeMembers') ?></option>
                        <option value="non_members" <?= $scope === 'non_members' ? 'selected' : '' ?>><?= lang('People.scopeNonMembers') ?></option>
                        <option value="visitors" <?= $scope === 'visitors' ? 'selected' : '' ?>><?= lang('People.scopeVisitors') ?></option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label mb-1"><?= lang('People.statusFilter') ?></label>
                    <select name="status" class="form-select form-select-sm">
                        <option value=""><?= lang('App.all') ?></option>
                        <?php foreach (($status_counts ?? []) as $row): ?>
                            <?php
                            $statusValue = strtolower(trim((string) ($row['membership_status'] ?? '')));
                            $statusLabel = \App\Services\MembershipStatusService::label($statusValue);
                            ?>
                            <option value="<?= esc($statusValue) ?>" <?= $status === $statusValue ? 'selected' : '' ?>>
                                <?= esc($statusLabel) ?> (<?= (int) ($row['count'] ?? 0) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1"><?= lang('People.perPage') ?></label>
                    <select name="per_page" class="form-select form-select-sm">
                        <option value="25" <?= $selectedPerPage === 25 ? 'selected' : '' ?>>25</option>
                        <option value="50" <?= $selectedPerPage === 50 ? 'selected' : '' ?>>50</option>
                        <option value="100" <?= $selectedPerPage === 100 ? 'selected' : '' ?>>100</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill"><?= lang('Services.apply') ?></button>
                    <a href="<?= base_url('people') ?>" class="btn btn-sm btn-outline-secondary flex-fill"><?= lang('App.clear') ?></a>
                </div>
                <?php $selectedFilters = $extra_filters ?? []; ?>
                <div class="col-12">
                    <hr class="my-2">
                    <div class="row g-2">
                        <?php
                        $filterBadges = [
                            'is_baptized'    => 'Baptized',
                            'is_confirmed'   => 'Confirmed',
                            'is_communicant' => 'Communicant',
                            'is_dependent'   => 'Dependent',
                            'is_under_16'    => 'Under 16',
                            'has_pledge'     => 'Has Pledge',
                            'is_leader'      => 'Leader',
                        ];
                        ?>
                        <?php foreach ($filterBadges as $filterKey => $filterLabel): ?>
                            <div class="col-md-3 col-sm-6">
                                <div class="form-check form-check-sm">
                                    <input class="form-check-input" type="checkbox" name="<?= esc($filterKey) ?>" value="1" id="people-filter-<?= esc($filterKey) ?>" <?= !empty($selectedFilters[$filterKey]) ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="people-filter-<?= esc($filterKey) ?>"><?= esc($filterLabel) ?></label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (!empty($people)): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th scope="col" style="width:2.5rem">
                                <input class="form-check-input" type="checkbox" id="people-select-all" title="Select all">
                            </th>
                            <th scope="col"><?= lang('People.colName') ?></th>
                            <th scope="col"><?= lang('People.colEmail') ?></th>
                            <th scope="col"><?= lang('Members.phone') ?></th>
                            <th scope="col"><?= lang('People.colTags') ?></th>
                            <?php if ($canPortalAccessManage): ?>
                                <th scope="col"><?= lang('App.colActions') ?></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($people as $person): ?>
                            <?php
                            $isMember = (int) ($person['is_member'] ?? 0) === 1;
                            $isGoodStanding = (int) ($person['is_good_standing'] ?? 0) === 1;
                            $portalAccess = strtolower((string) ($person['portal_access'] ?? 'none'));
                            if (!in_array($portalAccess, ['none', 'invited', 'enabled'], true)) {
                                $portalAccess = 'none';
                            }
                            $isNonPortal = in_array($portalAccess, ['none', 'invited'], true);
                            ?>
                            <tr>
                                <td>
                                    <input class="form-check-input people-row-check" type="checkbox"
                                        value="<?= (int) ($person['user_id'] ?? 0) ?>"
                                        data-user-id="<?= (int) ($person['user_id'] ?? 0) ?>"
                                        data-profile-id="<?= (int) ($person['profile_id'] ?? 0) ?>"
                                        data-is-member="<?= $isMember ? '1' : '0' ?>"
                                        data-person-name="<?= esc(trim((string) (($person['first_name'] ?? '') . ' ' . ($person['last_name'] ?? ''))), 'attr') ?>"
                                        data-person-email="<?= esc((string) ($person['email'] ?? ''), 'attr') ?>">
                                </td>
                                <td>
                                    <?php
                                    $profileId = (int) ($person['profile_id'] ?? 0);
                                    $userId = (int) ($person['user_id'] ?? 0);
                                    $fullName = trim((string) (($person['first_name'] ?? '') . ' ' . ($person['last_name'] ?? '')));
                                    $picFile = (string) ($person['profile_picture'] ?? '');
                                    $picUrl = ($picFile !== '' && $userId > 0)
                                        ? base_url('media/profile-picture/' . $userId)
                                        : '';
                                    ?>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if ($picUrl !== ''): ?>
                                            <img src="<?= $picUrl ?>" alt="" class="person-avatar" onerror="this.classList.add('d-none');this.nextElementSibling.classList.remove('d-none');">
                                            <span class="person-avatar-fallback d-none"><i class="bi bi-person-circle person-avatar-fallback-icon" aria-hidden="true"></i></span>
                                        <?php else: ?>
                                            <span class="person-avatar-fallback"><i class="bi bi-person-circle person-avatar-fallback-icon" aria-hidden="true"></i></span>
                                        <?php endif; ?>
                                        <div>
                                            <?php if ($profileId > 0): ?>
                                                <a href="<?= base_url('people/view/' . $profileId) ?>" class="people-name-link"><?= esc($fullName) ?></a>
                                            <?php else: ?>
                                                <?= esc($fullName) ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?= esc((string) ($person['email'] ?? '-')) ?></td>
                                <td><?= esc((string) ($person['phone'] ?? '-')) ?></td>
                                <td>
                                    <span class="badge border border-<?= $isMember ? 'success' : 'secondary' ?> text-<?= $isMember ? 'success' : 'secondary' ?> fw-normal me-1">
                                        <?= $isMember ? lang('People.memberTag') : lang('People.nonMemberTag') ?>
                                    </span>
                                    <?php if ($isMember && $isGoodStanding): ?>
                                        <span class="badge border border-info text-info fw-normal me-1"><?= lang('People.goodStandingBadge') ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($show_profile_pending_indicator) && (int) ($person['profile_pending_completion'] ?? 0) === 1): ?>
                                        <span class="badge border border-warning text-warning fw-normal me-1"><?= lang('Members.profilePendingBadge') ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($person['leader_titles'])): ?>
                                        <span class="badge border border-primary text-primary fw-normal me-1" title="<?= esc((string) $person['leader_titles'], 'attr') ?>">
                                            <i class="bi bi-award" aria-hidden="true"></i> <?= esc((string) $person['leader_titles']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($person['is_baptized'])): ?><span class="badge border border-success text-success fw-normal me-1">B</span><?php endif; ?>
                                    <?php if (!empty($person['is_confirmed'])): ?><span class="badge border border-success text-success fw-normal me-1">C</span><?php endif; ?>
                                    <?php if (!empty($person['is_communicant'])): ?><span class="badge border border-info text-info fw-normal me-1">Comm</span><?php endif; ?>
                                    <?php if (!empty($person['is_dependent'])): ?><span class="badge border border-secondary text-secondary fw-normal me-1">Dep</span><?php endif; ?>
                                    <?php if (!empty($person['is_under_16'])): ?><span class="badge border border-warning text-warning fw-normal me-1">&lt;16</span><?php endif; ?>
                                    <?php if (!empty($person['has_pledge'])): ?><span class="badge border border-success text-success fw-normal me-1">Pledge</span><?php endif; ?>
                                </td>
                                <?php if ($canPortalAccessManage): ?>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <?php if (auth()->user()->can('member.edit') && $profileId > 0): ?>
                                                <a href="<?= base_url('members/edit/' . $profileId) ?>"
                                                    class="btn btn-sm btn-outline-warning"
                                                    title="<?= esc(lang('App.edit'), 'attr') ?>">
                                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if (auth()->user()->can('member.edit') && !$isMember): ?>
                                                <button type="button"
                                                    class="btn btn-sm btn-outline-success mark-member-person-btn"
                                                    data-user-id="<?= (int) ($person['user_id'] ?? 0) ?>"
                                                    data-person-name="<?= esc($fullName, 'attr') ?>"
                                                    title="<?= esc(lang('People.markAsMember'), 'attr') ?>">
                                                    <i class="bi bi-house-check" aria-hidden="true"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($isNonPortal): ?>
                                                <button type="button"
                                                    class="btn btn-sm btn-outline-primary enable-portal-person-btn"
                                                    data-user-id="<?= (int) ($person['user_id'] ?? 0) ?>"
                                                    data-person-name="<?= esc($fullName, 'attr') ?>"
                                                    data-person-email="<?= esc((string) ($person['email'] ?? ''), 'attr') ?>"
                                                    title="<?= esc(lang('People.enablePortalAccess'), 'attr') ?>">
                                                    <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if (auth()->user()->can('member.delete') && $profileId > 0 && !$isMember): ?>
                                                <button type="button"
                                                    class="btn btn-sm btn-outline-danger row-delete-person-btn"
                                                    data-profile-id="<?= $profileId ?>"
                                                    data-person-name="<?= esc($fullName, 'attr') ?>"
                                                    data-person-email="<?= esc((string) ($person['email'] ?? ''), 'attr') ?>"
                                                    title="<?= esc(lang('App.delete'), 'attr') ?>">
                                                    <i class="bi bi-trash3" aria-hidden="true"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php
            $currentPage = max(1, (int) ($current_page ?? 1));
            $totalPages = max(1, (int) ($total_pages ?? 1));
            $queryBase = array_merge(
                ['scope' => $scope, 'status' => $status, 'per_page' => $selectedPerPage],
                $extra_filters ?? []
            );
            ?>
            <?php if ($totalPages > 1): ?>
                <nav class="mt-3" aria-label="People pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $currentPage <= 1 ? '#' : base_url('people?' . http_build_query(array_merge($queryBase, ['page' => $currentPage - 1]))) ?>">&laquo;</a>
                        </li>
                        <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
                            <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                                <a class="page-link" href="<?= base_url('people?' . http_build_query(array_merge($queryBase, ['page' => $p]))) ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $currentPage >= $totalPages ? '#' : base_url('people?' . http_build_query(array_merge($queryBase, ['page' => $currentPage + 1]))) ?>">&raquo;</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-people fs-1" aria-hidden="true"></i>
                <p class="mt-2 mb-0"><?= lang('People.noPeopleFound') ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (auth()->user()->can('member.delete')): ?>
    <div class="modal fade" id="singleDeletePersonModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill text-warning me-2" aria-hidden="true"></i><?= lang('App.delete') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="fw-semibold mb-2" id="singleDeletePersonInfo"></p>
                    <div class="alert alert-warning py-2 mb-3">This action cannot be undone.</div>
                    <label class="form-label" for="singleDeletePersonReasonInput"><?= lang('App.bulkDeleteReasonLabel') ?></label>
                    <textarea id="singleDeletePersonReasonInput" class="form-control" rows="3" maxlength="500" placeholder="<?= esc(lang('App.bulkDeleteReasonPlaceholder')) ?>" aria-describedby="singleDeletePersonReasonFeedback"></textarea>
                    <div id="singleDeletePersonReasonFeedback" class="invalid-feedback"></div>
                    <div class="small text-muted mt-1"><?= lang('App.bulkDeleteReasonHelp') ?></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="button" class="btn btn-danger" id="confirmSingleDeletePersonBtn"><?= lang('App.delete') ?></button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="bulkDeletePeopleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill text-warning me-2" aria-hidden="true"></i><?= lang('App.bulkDeleteWarningTitle') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="fw-semibold mb-2" id="bulkDeletePeopleSummary"></p>
                    <div class="alert alert-warning py-2 mb-3">These deletions cannot be undone.</div>
                    <label class="form-label" for="bulkDeletePeopleReasonInput"><?= lang('App.bulkDeleteReasonLabel') ?></label>
                    <textarea id="bulkDeletePeopleReasonInput" class="form-control" rows="3" maxlength="500" placeholder="<?= esc(lang('App.bulkDeleteReasonPlaceholder')) ?>" aria-describedby="bulkDeletePeopleReasonFeedback"></textarea>
                    <div id="bulkDeletePeopleReasonFeedback" class="invalid-feedback"></div>
                    <div class="small text-muted mt-1"><?= lang('App.bulkDeleteReasonHelp') ?></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="button" class="btn btn-danger" id="confirmBulkDeletePeopleBtn"><?= lang('App.delete') ?></button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($canPortalAccessManage): ?>
    <div class="modal fade" id="enablePersonPortalModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= lang('People.enablePortalAccess') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="enablePersonPortalForm">
                    <div class="modal-body">
                        <input type="hidden" name="user_id" value="">
                        <p id="enablePersonPortalLabel" class="small text-muted mb-2"></p>
                        <div class="mb-3">
                            <label for="enablePersonPortalEmail" class="form-label"><?= lang('App.colEmail') ?></label>
                            <input id="enablePersonPortalEmail" type="email" class="form-control" name="email" required>
                        </div>
                        <div class="alert alert-info py-2 mb-0"><?= esc(lang('People.portalEnableResetNotice')) ?></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                        <button type="submit" class="btn btn-outline-success" id="confirmEnablePersonPortalBtn"><?= lang('People.enablePortalAccess') ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($can_moderate_registrations) && !empty($pending_registrations_count)): ?>
    <div class="card mb-3" id="pendingRegistrationsCard">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong><?= lang('Members.pendingRegistrationsTitle') ?></strong>
            <span class="badge border border-warning text-warning fw-normal"><?= (int) ($pending_registrations_count ?? 0) ?></span>
        </div>
        <form id="bulkActionForm" action="<?= base_url('members/registrations/bulk-action') ?>" method="post" class="p-2 border-bottom bg-light d-flex align-items-center gap-2 flex-wrap">
            <?= csrf_field() ?>
            <input type="hidden" name="bulk_action" id="pendingBulkAction" value="">
            <button type="button" class="btn btn-sm btn-success pending-action-btn" data-action="approve" data-title="<?= esc(lang('Dashboard.approveLabel'), 'attr') ?>" data-template="<?= esc(lang('Dashboard.confirmBulkApprove'), 'attr') ?>"><i class="bi bi-person-check" aria-hidden="true"></i> <?= lang('Dashboard.approveLabel') ?></button>
            <button type="button" class="btn btn-sm btn-outline-secondary pending-action-btn" data-action="dismiss" data-title="<?= esc(lang('Dashboard.dismissLabel'), 'attr') ?>" data-template="<?= esc(lang('Dashboard.confirmBulkDismiss'), 'attr') ?>"><?= lang('Dashboard.dismissLabel') ?></button>
            <button type="button" class="btn btn-sm btn-outline-warning pending-action-btn" data-action="ban" data-title="<?= esc(lang('Dashboard.banLabel'), 'attr') ?>" data-template="<?= esc(lang('Dashboard.confirmBulkBan'), 'attr') ?>"><?= lang('Dashboard.banLabel') ?></button>
            <?php if (auth()->user()?->can('member.delete')): ?>
                <button type="button" class="btn btn-sm btn-outline-danger pending-action-btn" data-action="delete" data-title="<?= esc(lang('App.delete'), 'attr') ?>" data-template="<?= esc(lang('Dashboard.confirmBulkDelete'), 'attr') ?>"><?= lang('App.delete') ?></button>
            <?php endif; ?>
            <button type="button" class="btn btn-sm btn-outline-info pending-action-btn" data-action="escalate" data-title="<?= esc(lang('Dashboard.escalateLabel'), 'attr') ?>" data-template="<?= esc(lang('Dashboard.confirmBulkEscalate'), 'attr') ?>"><?= lang('Dashboard.escalateLabel') ?></button>
        </form>

        <div class="modal fade" id="pendingActionModal" tabindex="-1" aria-labelledby="pendingActionModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="pendingActionModalLabel"></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= lang('App.close') ?>"></button>
                    </div>
                    <div class="modal-body">
                        <p id="pendingActionText" class="mb-0"></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                        <button type="submit" form="bulkActionForm" id="pendingActionConfirm" class="btn btn-success"><?= lang('App.confirm') ?></button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col" class="text-center" style="width: 2.5rem;"><input type="checkbox" class="form-check-input" id="pending-select-all" onchange="document.querySelectorAll('.pending-reg-check').forEach(c => c.checked = this.checked)"></th>
                            <th scope="col"><?= lang('App.colName') ?></th>
                            <th scope="col"><?= lang('App.colEmail') ?></th>
                            <th scope="col"><?= lang('App.colPhone') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($pending_registrations ?? []) as $req): ?>
                            <tr>
                                <td class="text-center"><input type="checkbox" class="form-check-input pending-reg-check" name="request_ids[]" value="<?= (int) ($req['id'] ?? 0) ?>" form="bulkActionForm"></td>
                                <td><?= esc(trim((string) ($req['first_name'] ?? '') . ' ' . (string) ($req['last_name'] ?? ''))) ?></td>
                                <td><?= esc((string) ($req['email'] ?? '')) ?></td>
                                <td><?= esc((string) ($req['phone'] ?? '-')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
    // ── Bulk select ──────────────────────────────────────────────────────────
    const selectAllChk = document.getElementById('people-select-all');
    const exportBtn = document.getElementById('bulk-export-btn');
    const exportIdsDiv = document.getElementById('bulk-export-ids');
    const exportForm = document.getElementById('people-export-form');
    const bulkDeletePeopleBtn = document.getElementById('bulk-delete-people-btn');
    const bulkMarkMemberBtn = document.getElementById('bulk-mark-member-btn');
    const bulkRemoveMemberBtn = document.getElementById('bulk-remove-member-btn');
    const enablePersonPortalModalEl = document.getElementById('enablePersonPortalModal');
    const enablePersonPortalForm = document.getElementById('enablePersonPortalForm');
    const enablePersonPortalLabel = document.getElementById('enablePersonPortalLabel');
    const confirmEnablePersonPortalBtn = document.getElementById('confirmEnablePersonPortalBtn');

    function selectedPeopleCheckboxes() {
        return Array.from(document.querySelectorAll('.people-row-check:checked'));
    }

    function selectedPeopleProfileIds() {
        return selectedPeopleCheckboxes()
            .map(function(chk) {
                return parseInt(chk.getAttribute('data-profile-id') || '0', 10);
            })
            .filter(function(id) {
                return Number.isInteger(id) && id > 0;
            });
    }

    function syncExportButton() {
        const checked = selectedPeopleCheckboxes();
        exportBtn.classList.toggle('d-none', checked.length === 0);
        if (bulkDeletePeopleBtn) {
            bulkDeletePeopleBtn.classList.toggle('d-none', checked.length === 0);
        }
        if (bulkMarkMemberBtn) {
            bulkMarkMemberBtn.classList.toggle('d-none', checked.length === 0);
        }
        if (bulkRemoveMemberBtn) {
            bulkRemoveMemberBtn.classList.toggle('d-none', checked.length === 0);
        }

        // Rebuild hidden inputs for the export form
        exportIdsDiv.innerHTML = '';
        checked.forEach(function(chk) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'user_ids[]';
            input.value = chk.value;
            exportIdsDiv.appendChild(input);
        });
    }

    if (selectAllChk) {
        selectAllChk.addEventListener('change', function() {
            document.querySelectorAll('.people-row-check').forEach(function(chk) {
                chk.checked = selectAllChk.checked;
            });
            syncExportButton();
        });
    }

    document.querySelectorAll('.people-row-check').forEach(function(chk) {
        chk.addEventListener('change', function() {
            const all = document.querySelectorAll('.people-row-check');
            const done = document.querySelectorAll('.people-row-check:checked');
            if (selectAllChk) {
                selectAllChk.indeterminate = done.length > 0 && done.length < all.length;
                selectAllChk.checked = done.length === all.length && all.length > 0;
            }
            syncExportButton();
        });
    });

    // ── Export form: loading state + auto-reset after download starts ─────────
    if (exportForm) {
        exportForm.addEventListener('submit', function() {
            const originalLabel = exportBtn.innerHTML;
            exportBtn.disabled = true;
            exportBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> <?= lang('App.loading') ?>';

            // File downloads don't navigate the page, so reset UI after the
            // browser has had time to start receiving the attachment (~2 s).
            setTimeout(function() {
                // Uncheck all rows
                document.querySelectorAll('.people-row-check').forEach(function(chk) {
                    chk.checked = false;
                });
                if (selectAllChk) {
                    selectAllChk.checked = false;
                    selectAllChk.indeterminate = false;
                }
                exportIdsDiv.innerHTML = '';
                exportBtn.disabled = false;
                exportBtn.innerHTML = originalLabel;
                exportBtn.classList.add('d-none');
                if (bulkDeletePeopleBtn) {
                    bulkDeletePeopleBtn.classList.add('d-none');
                }
                if (bulkMarkMemberBtn) {
                    bulkMarkMemberBtn.classList.add('d-none');
                }
                if (bulkRemoveMemberBtn) {
                    bulkRemoveMemberBtn.classList.add('d-none');
                }
            }, 2000);
        });
    }

    // ── Split single/bulk delete on People screen ───────────────────────────
    function getModalInstance(modalEl) {
        if (!modalEl || typeof window.bootstrap === 'undefined' || typeof window.bootstrap.Modal !== 'function') {
            return null;
        }

        return window.bootstrap.Modal.getOrCreateInstance(modalEl);
    }

    function contractErrorMessage(data, fallback) {
        if (!data || typeof data !== 'object') {
            return fallback;
        }

        const code = data.error && typeof data.error.code === 'string' ? data.error.code : '';
        const message =
            (data.error && typeof data.error.message === 'string' && data.error.message) ||
            (typeof data.message === 'string' && data.message) ||
            fallback;

        return code ? `${message} (${code})` : message;
    }

    function setReasonFieldError(input, feedbackId, message) {
        if (!input) return;
        input.classList.toggle('is-invalid', !!message);
        const feedback = document.getElementById(feedbackId);
        if (feedback) feedback.textContent = message || '';
    }

    function submitPeopleDelete(profileIds, reason, triggerBtn) {
        if (!Array.isArray(profileIds) || profileIds.length === 0) {
            showAppToast('<?= esc(lang('Members.bulkDeleteSelectAtLeastOne'), 'js') ?>', 'warning');
            return;
        }

        let reasonInput = null,
            feedbackId = '';
        if (profileIds.length === 1) {
            reasonInput = document.getElementById('singleDeletePersonReasonInput');
            feedbackId = 'singleDeletePersonReasonFeedback';
        } else {
            reasonInput = document.getElementById('bulkDeletePeopleReasonInput');
            feedbackId = 'bulkDeletePeopleReasonFeedback';
        }
        setReasonFieldError(reasonInput, feedbackId, '');
        if (!reason || reason.trim().length < 8) {
            setReasonFieldError(reasonInput, feedbackId, '<?= esc(lang('App.bulkDeleteReasonRequired'), 'js') ?>');
            if (reasonInput) reasonInput.focus();
            return;
        }

        if (triggerBtn) {
            triggerBtn.disabled = true;
        }

        const body = new URLSearchParams();
        profileIds.forEach(function(id) {
            body.append('profile_ids[]', String(id));
        });
        body.append('delete_reason', reason.trim());
        body.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

        fetch('<?= base_url('people/bulk-delete') ?>', {
                method: 'POST',
                body,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function(response) {
                return response.json().then(function(payload) {
                    return {
                        ok: response.ok,
                        payload
                    };
                });
            })
            .then(function(result) {
                if (triggerBtn) {
                    triggerBtn.disabled = false;
                }
                if (result.payload && result.payload.errors && result.payload.errors.delete_reason) {
                    setReasonFieldError(reasonInput, feedbackId, result.payload.errors.delete_reason);
                    if (reasonInput) reasonInput.focus();
                    return;
                }
                setReasonFieldError(reasonInput, feedbackId, '');
                if (result.ok && (result.payload.success || Number(result.payload.deleted || 0) > 0)) {
                    window.location.reload();
                    return;
                }
                showAppToast(contractErrorMessage(result.payload, '<?= esc(lang('Members.deleteError'), 'js') ?>'), 'danger');
            })
            .catch(function() {
                if (triggerBtn) {
                    triggerBtn.disabled = false;
                }
                setReasonFieldError(reasonInput, feedbackId, '');
                showAppToast('<?= esc(lang('Members.deleteError'), 'js') ?>', 'danger');
            });
    }

    function openSingleDeletePersonModal(checkboxEl) {
        const modalEl = document.getElementById('singleDeletePersonModal');
        const infoEl = document.getElementById('singleDeletePersonInfo');
        const reasonInput = document.getElementById('singleDeletePersonReasonInput');
        const modal = getModalInstance(modalEl);

        const profileId = parseInt(checkboxEl.getAttribute('data-profile-id') || '0', 10);
        const name = (checkboxEl.getAttribute('data-person-name') || '').trim();
        const email = (checkboxEl.getAttribute('data-person-email') || '').trim();
        window.__pendingPeopleSingleDelete = profileId > 0 ? [profileId] : [];

        if (!modal || !reasonInput) {
            showAppToast('<?= esc(lang('App.errorGeneric'), 'js') ?>', 'warning');
            return;
        }

        if (infoEl) {
            infoEl.textContent = email ? `${name} (${email})` : name;
        }
        reasonInput.value = '';
        modal.show();
    }

    if (bulkDeletePeopleBtn) {
        bulkDeletePeopleBtn.addEventListener('click', function() {
            const selected = selectedPeopleCheckboxes();
            if (selected.length === 0) {
                showAppToast('<?= esc(lang('Members.bulkDeleteSelectAtLeastOne'), 'js') ?>', 'warning');
                return;
            }

            if (selected.length === 1) {
                openSingleDeletePersonModal(selected[0]);
                return;
            }

            const profileIds = selectedPeopleProfileIds();
            if (profileIds.length === 0) {
                showAppToast('<?= esc(lang('Members.bulkDeleteSelectAtLeastOne'), 'js') ?>', 'warning');
                return;
            }

            window.__pendingPeopleBulkDelete = profileIds;

            const modalEl = document.getElementById('bulkDeletePeopleModal');
            const summaryEl = document.getElementById('bulkDeletePeopleSummary');
            const reasonInput = document.getElementById('bulkDeletePeopleReasonInput');
            const modal = getModalInstance(modalEl);

            if (!modal || !reasonInput) {
                showAppToast('<?= esc(lang('App.errorGeneric'), 'js') ?>', 'warning');
                return;
            }

            summaryEl.textContent = `Delete ${profileIds.length} selected users?`;
            reasonInput.value = '';
            modal.show();
        });
    }

    const confirmSingleDeletePersonBtn = document.getElementById('confirmSingleDeletePersonBtn');
    if (confirmSingleDeletePersonBtn) {
        confirmSingleDeletePersonBtn.addEventListener('click', function() {
            const reason = (document.getElementById('singleDeletePersonReasonInput') || {
                value: ''
            }).value;
            submitPeopleDelete(window.__pendingPeopleSingleDelete || [], reason, this);
        });
    }

    const confirmBulkDeletePeopleBtn = document.getElementById('confirmBulkDeletePeopleBtn');
    if (confirmBulkDeletePeopleBtn) {
        confirmBulkDeletePeopleBtn.addEventListener('click', function() {
            const reason = (document.getElementById('bulkDeletePeopleReasonInput') || {
                value: ''
            }).value;
            submitPeopleDelete(window.__pendingPeopleBulkDelete || [], reason, this);
        });
    }

    // ── Single-row delete from people list ────────────────────────────────────
    document.querySelectorAll('.row-delete-person-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const profileId = parseInt(this.getAttribute('data-profile-id') || '0', 10);
            const name = (this.getAttribute('data-person-name') || '').trim();
            const email = (this.getAttribute('data-person-email') || '').trim();
            if (!profileId) {
                return;
            }
            window.__pendingPeopleSingleDelete = [profileId];

            const modalEl = document.getElementById('singleDeletePersonModal');
            const infoEl = document.getElementById('singleDeletePersonInfo');
            const reasonInput = document.getElementById('singleDeletePersonReasonInput');
            const modal = getModalInstance(modalEl);

            if (!modal || !reasonInput) {
                showAppToast('<?= esc(lang('App.errorGeneric'), 'js') ?>', 'warning');
                return;
            }

            if (infoEl) {
                infoEl.textContent = email ? `${name} (${email})` : name;
            }
            reasonInput.value = '';
            modal.show();
        });
    });

    // ── Bulk toggle member tag ────────────────────────────────────────────────
    function bulkToggleMemberTag(isMember) {
        const selected = selectedPeopleCheckboxes();
        if (selected.length === 0) {
            showAppToast('<?= esc(lang('Members.bulkDeleteSelectAtLeastOne'), 'js') ?>', 'warning');
            return;
        }

        const userIds = selected
            .map(function(chk) {
                return parseInt(chk.getAttribute('data-user-id') || '0', 10);
            })
            .filter(function(id) {
                return id > 0;
            });

        if (userIds.length === 0) {
            return;
        }

        const action = isMember === 1 ? '<?= esc(lang('People.markAsMember'), 'js') ?>' : '<?= esc(lang('People.removeMemberTag'), 'js') ?>';
        showAppConfirm(
            '<?= esc(lang('App.confirm'), 'js') ?>',
            action + ' (' + userIds.length + ' ' + (userIds.length === 1 ? 'person' : 'people') + ')?',
            function() {
                const body = new URLSearchParams();
                userIds.forEach(function(id) {
                    body.append('user_ids[]', String(id));
                });
                body.append('is_member', String(isMember));
                body.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

                fetch('<?= base_url('people/bulk-toggle-member-tag') ?>', {
                        method: 'POST',
                        body,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(function(r) {
                        return r.json();
                    })
                    .then(function(payload) {
                        if (payload.success) {
                            window.location.reload();
                        } else {
                            showAppToast(payload.message || 'Failed to update member tag.', 'danger');
                        }
                    })
                    .catch(function() {
                        showAppToast('<?= esc(lang('Members.deleteError'), 'js') ?>', 'danger');
                    });
            }
        );
    }

    if (bulkMarkMemberBtn) {
        bulkMarkMemberBtn.addEventListener('click', function() {
            bulkToggleMemberTag(1);
        });
    }
    if (bulkRemoveMemberBtn) {
        bulkRemoveMemberBtn.addEventListener('click', function() {
            bulkToggleMemberTag(0);
        });
    }

    // ── Single-row move to members ──────────────────────────────────────────
    function toggleSingleMemberTag(userId, isMember, personName) {
        if (!Number.isInteger(userId) || userId <= 0) {
            showAppToast('<?= esc(lang('App.errorGeneric'), 'js') ?>', 'warning');
            return;
        }

        const action = isMember === 1 ? '<?= esc(lang('People.markAsMember'), 'js') ?>' : '<?= esc(lang('People.removeMemberTag'), 'js') ?>';
        const label = (personName || '').trim() !== '' ? (personName + '?') : 'this person?';

        showAppConfirm(
            '<?= esc(lang('App.confirm'), 'js') ?>',
            action + ' ' + label,
            function() {
                const body = new URLSearchParams();
                body.append('is_member', String(isMember));
                body.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

                fetch('<?= base_url('people/toggle-member-tag/') ?>' + encodeURIComponent(String(userId)), {
                        method: 'POST',
                        body,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(function(r) {
                        return r.json();
                    })
                    .then(function(payload) {
                        if (payload.success) {
                            window.location.reload();
                        } else {
                            showAppToast(payload.message || 'Failed to update member tag.', 'danger');
                        }
                    })
                    .catch(function() {
                        showAppToast('<?= esc(lang('Members.deleteError'), 'js') ?>', 'danger');
                    });
            }
        );
    }

    document.querySelectorAll('.mark-member-person-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const userId = parseInt(this.getAttribute('data-user-id') || '0', 10);
            const personName = this.getAttribute('data-person-name') || '';
            toggleSingleMemberTag(userId, 1, personName);
        });
    });

    document.querySelectorAll('.enable-portal-person-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const enablePersonPortalModal = getModalInstance(enablePersonPortalModalEl);
            if (!enablePersonPortalForm || !enablePersonPortalModal) {
                showAppToast('<?= esc(lang('App.errorGeneric'), 'js') ?>', 'warning');
                return;
            }

            const userId = this.getAttribute('data-user-id') || '';
            const personName = (this.getAttribute('data-person-name') || '').trim();
            const currentEmail = (this.getAttribute('data-person-email') || '').trim();

            enablePersonPortalForm.querySelector('input[name="user_id"]').value = userId;
            enablePersonPortalForm.querySelector('input[name="email"]').value = currentEmail;
            if (enablePersonPortalLabel) {
                enablePersonPortalLabel.textContent = personName;
            }

            enablePersonPortalModal.show();
        });
    });

    if (enablePersonPortalForm) {
        enablePersonPortalForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const userId = (enablePersonPortalForm.querySelector('input[name="user_id"]') || {
                value: ''
            }).value;
            const email = (enablePersonPortalForm.querySelector('input[name="email"]') || {
                value: ''
            }).value;
            if (!userId) {
                showAppToast('<?= esc(lang('App.errorGeneric'), 'js') ?>', 'warning');
                return;
            }

            if (confirmEnablePersonPortalBtn) {
                confirmEnablePersonPortalBtn.disabled = true;
            }

            fetch('<?= base_url('people/enable-portal/') ?>' + encodeURIComponent(userId), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        '<?= csrf_header() ?>': '<?= csrf_hash() ?>',
                    },
                    body: JSON.stringify({
                        email: email
                    }),
                })
                .then(function(response) {
                    return response.json().then(function(payload) {
                        return {
                            ok: response.ok,
                            payload: payload,
                        };
                    });
                })
                .then(function(result) {
                    if (result.ok && result.payload && result.payload.success) {
                        showAppToast(result.payload.message || '<?= esc(lang('People.portalAccessEnabled'), 'js') ?>', 'primary');
                        window.location.reload();
                        return;
                    }

                    showAppToast(contractErrorMessage(result.payload, '<?= esc(lang('People.portalAccessEnableFailed'), 'js') ?>'), 'danger');
                })
                .catch(function() {
                    showAppToast('<?= esc(lang('People.portalAccessEnableFailed'), 'js') ?>', 'danger');
                })
                .finally(function() {
                    if (confirmEnablePersonPortalBtn) {
                        confirmEnablePersonPortalBtn.disabled = false;
                    }
                });
        });
    }
</script>

<!-- Create Non-Portal Person Modal -->
<?= view('Clergy\Views\Members/_non_portal_modal', [
    'modalId'     => 'createNonPortalPersonModal',
    'fieldPrefix' => 'np',
    'formAction'  => base_url('people/store-non-portal'),
    'modalHint'   => lang('Members.nonPortalPersonModalHint'),
]) ?>

<script>
    (function() {
        const modalEl = document.getElementById('pendingActionModal');
        const modalTitle = document.getElementById('pendingActionModalLabel');
        const modalText = document.getElementById('pendingActionText');
        const modalConfirm = document.getElementById('pendingActionConfirm');
        const bulkActionInput = document.getElementById('pendingBulkAction');
        if (!modalEl || !modalTitle || !modalText || !modalConfirm || !bulkActionInput) {
            return;
        }

        const modal = (typeof getModalInstance === 'function')
            ? getModalInstance(modalEl)
            : (typeof window.bootstrap !== 'undefined' && typeof window.bootstrap.Modal === 'function')
                ? new window.bootstrap.Modal(modalEl)
                : null;

        document.querySelectorAll('.pending-action-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const count = document.querySelectorAll('.pending-reg-check:checked').length;
                if (count === 0) {
                    alert('<?= esc(lang('Dashboard.pleaseSelectAtLeastOne'), 'js') ?>');
                    return;
                }

                const action = btn.dataset.action || '';
                const title = btn.dataset.title || '';
                const template = btn.dataset.template || '';
                const colorMap = { approve: 'success', dismiss: 'secondary', ban: 'warning', delete: 'danger', escalate: 'info' };
                const color = colorMap[action] || 'secondary';

                if (modalTitle) {
                    modalTitle.textContent = title;
                }
                if (modalText) {
                    modalText.textContent = template.replace('{0}', String(count));
                }
                if (bulkActionInput) {
                    bulkActionInput.value = action;
                }

                // Match the confirm button color to the action
                modalConfirm.className = 'btn btn-' + color;
                if (modal) {
                    modal.show();
                } else if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: title,
                        text: template.replace('{0}', String(count)),
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: title,
                        cancelButtonText: '<?= esc(lang('App.cancel'), 'js') ?>',
                        confirmButtonColor: getSwalColor(color)
                    }).then(function(result) {
                        if (result.isConfirmed) {
                            document.getElementById('bulkActionForm').submit();
                        }
                    });
                } else if (confirm(template.replace('{0}', String(count)))) {
                    document.getElementById('bulkActionForm').submit();
                }
            });
        });

        function getSwalColor(color) {
            const colors = {
                success: '#198754',
                warning: '#ffc107',
                danger: '#dc3545',
                info: '#0dcaf0',
                secondary: '#6c757d'
            };
            return colors[color] || '#6c757d';
        }
    })();
</script>

<?= $this->endSection() ?>