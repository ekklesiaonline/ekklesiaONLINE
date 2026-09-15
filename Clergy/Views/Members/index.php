<?= $this->extend('Clergy\Views\layouts/main') ?>

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

    .member-avatar {
        width: 36px;
        height: 36px;
        border-radius: 6px;
        object-fit: cover;
        flex-shrink: 0;
    }

    .member-avatar-fallback {
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

    .member-avatar-fallback-icon {
        font-size: 1.1rem;
        color: #adb5bd;
        line-height: 1;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 gap-2">
    <div class="d-flex gap-2">
        <?php if (auth()->user()->can('member.create') && !auth()->user()->inGroup('provinceadmin')): ?>
            <a href="<?= base_url('members/create') ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-plus-circle"></i> <?= lang('Members.createPortalMember') ?>
            </a>
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createStandaloneNonPortalModal">
                <i class="bi bi-person-plus"></i> <?= lang('Members.createStandaloneNonPortal') ?>
            </button>
        <?php endif; ?>

        <?php if (!empty($members) && auth()->user()->can('member.delete')): ?>
            <button type="button" class="btn btn-outline-danger btn-sm d-none" id="bulkDeleteMembersBtn">
                <i class="bi bi-trash3"></i> <?= lang('Members.bulkDeleteSelected') ?>
            </button>
        <?php endif; ?>

        <?php if (!empty($members) && auth()->user()->can('member.edit')): ?>
            <button type="button" id="bulk-mark-member-btn" class="btn btn-outline-success btn-sm d-none">
                <i class="bi bi-person-check"></i> <?= lang('People.markAsMember') ?>
            </button>
            <button type="button" id="bulk-remove-member-btn" class="btn btn-outline-secondary btn-sm d-none">
                <i class="bi bi-person-dash"></i> <?= lang('People.removeMemberTag') ?>
            </button>
        <?php endif; ?>

        <?php if (!empty($members)): ?>
            <form id="members-export-form" method="post" action="<?= base_url('people/export') ?>">
                <?= csrf_field() ?>
                <div id="bulk-export-ids"></div>
                <button type="submit" id="bulk-export-btn" class="btn btn-outline-secondary btn-sm d-none">
                    <i class="bi bi-download"></i> <?= lang('App.export') ?>
                </button>
            </form>
        <?php endif; ?>

        <?php if (!empty($members) && !empty($isProvinceAdmin)): ?>
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#bulkTransferModal">
                <i class="bi bi-arrow-left-right"></i> <?= lang('Members.bulkTransferSelected') ?>
            </button>
        <?php endif; ?>
    </div>
    <?php if (!empty($can_moderate_registrations)): ?>
        <a href="#pendingRegistrationsCard" class="btn btn-outline-warning btn-sm">
            <i class="bi bi-person-check"></i> <?= lang('Members.pendingRegistrationsTitle') ?>
            <span class="badge border border-warning text-warning fw-normal ms-1"><?= (int) ($pending_registrations_count ?? 0) ?></span>
        </a>
    <?php endif; ?>
</div>

<?php if (auth()->user()->can('member.create') && !auth()->user()->inGroup('provinceadmin')): ?>
    <?= view('Clergy\Views\Members/_non_portal_modal', [
        'modalId'     => 'createStandaloneNonPortalModal',
        'fieldPrefix' => 'nm',
        'formAction'  => base_url('members/store-non-portal'),
        'modalHint'   => lang('Members.nonPortalMemberModalHint'),
    ]) ?>
<?php endif; ?>

<?php if (!empty($isProvinceAdmin)): ?>
    <div class="alert alert-info py-2 mb-3 d-flex justify-content-between align-items-center">
        <span>
            <strong><?= lang('Members.bulkTransferLabel') ?>:</strong> <?= lang('Members.bulkTransferDesc') ?>
        </span>
        <span class="badge border border-primary text-primary fw-normal" id="selectedCountBadge">0 selected</span>
    </div>
<?php endif; ?>

<?php $request = service('request'); ?>
<?php
$selectedStatus = (string) ($request->getGet('status') ?? '');
$selectedPerPage = (int) ($per_page ?? 25);
$allowBulkSelection = !empty($isProvinceAdmin) || (empty($isProvinceAdmin) && auth()->user()->can('member.delete'));
$status_counts = (isset($status_counts) && is_array($status_counts)) ? $status_counts : [];
$availableParishes = (isset($availableParishes) && is_array($availableParishes)) ? $availableParishes : [];
$parish_id = (int) ($parish_id ?? $parish_id ?? 0);
if (!in_array($selectedPerPage, [25, 50, 100], true)) {
    $selectedPerPage = 25;
}
?>

<!-- Filter by Status -->
<div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-end filter-panel-header">
        <button
            class="btn btn-outline-secondary btn-sm filter-toggle-btn"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#membersFilterPanel"
            aria-expanded="<?= $request->getGet('status') !== null ? 'true' : 'false' ?>"
            aria-controls="membersFilterPanel">
            <i class="bi bi-funnel"></i> <?= lang('App.filter') ?>
        </button>
    </div>
    <div id="membersFilterPanel" class="collapse <?= $request->getGet('status') !== null ? 'show' : '' ?>">
        <div class="card-body border-top filter-panel-body">
            <div class="row align-items-center g-2">
                <div class="col-md-6">
                    <div class="btn-group" role="group">
                        <a href="<?= base_url('members' . ($selectedPerPage !== 25 ? '?' . http_build_query(['per_page' => $selectedPerPage]) : '')) ?>" class="btn btn-sm <?= $selectedStatus === '' ? 'btn-primary' : 'btn-outline-primary' ?>">
                            <?= lang('App.all') ?> (<?= array_sum(array_column($status_counts, 'count')) ?>)
                        </a>
                        <?php foreach ($status_counts as $status): ?>
                            <?php
                            $statusSlug = strtolower(trim((string) ($status['membership_status'] ?? 'regular_attender')));
                            $statusLabel = \App\Services\MembershipStatusService::label($statusSlug);
                            ?>
                            <a href="<?= base_url('members?' . http_build_query(['status' => $status['membership_status'], 'per_page' => $selectedPerPage])) ?>"
                                class="btn btn-sm <?= $selectedStatus === (string) $status['membership_status'] ? 'btn-primary' : 'btn-outline-primary' ?>">
                                <?= esc($statusLabel) ?> (<?= (int) ($status['count'] ?? 0) ?>)
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="text" class="form-control" id="memberSearch" placeholder="<?= lang('Members.searchPlaceholder') ?>">
                        <button class="btn btn-outline-secondary" type="button">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Members Table -->

<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-end mb-3">
            <form method="get" action="<?= base_url('members') ?>" class="d-flex align-items-center gap-2">
                <?php if ($selectedStatus !== ''): ?>
                    <input type="hidden" name="status" value="<?= esc($selectedStatus) ?>">
                <?php endif; ?>
                <label for="membersPerPage" class="form-label mb-0 small text-muted"><?= lang('App.filter') ?>:</label>
                <select id="membersPerPage" name="per_page" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="25" <?= $selectedPerPage === 25 ? 'selected' : '' ?>>25</option>
                    <option value="50" <?= $selectedPerPage === 50 ? 'selected' : '' ?>>50</option>
                    <option value="100" <?= $selectedPerPage === 100 ? 'selected' : '' ?>>100</option>
                </select>
            </form>
        </div>

        <?php if (!empty($members)): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <?php if ($allowBulkSelection): ?>
                                <th style="width: 40px;">
                                    <input type="checkbox" id="selectAllMembers" class="form-check-input">
                                </th>
                            <?php endif; ?>
                            <th><?= lang('Members.colName') ?></th>
                            <th><?= lang('Members.colEmail') ?></th>
                            <th><?= lang('Members.colPhone') ?></th>
                            <th><?= lang('Members.colStatus') ?></th>
                            <th><?= lang('People.colTags') ?></th>
                            <th class="text-end"><?= lang('App.colActions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $member): ?>
                            <tr>
                                <?php if ($allowBulkSelection): ?>
                                    <td>
                                        <input
                                            type="checkbox"
                                            class="form-check-input member-checkbox"
                                            value="<?= (int) $member['id'] ?>"
                                            data-user-id="<?= (int) ($member['user_id'] ?? 0) ?>"
                                            data-member-name="<?= esc(trim((string) (($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''))), 'attr') ?>"
                                            data-member-email="<?= esc((string) ($member['email'] ?? ''), 'attr') ?>">
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <?php
                                    $memberUserId = (int) ($member['user_id'] ?? 0);
                                    $memberPicFile = trim((string) ($member['profile_picture'] ?? ''));
                                    $memberPicUrl = ($memberPicFile !== '' && $memberUserId > 0)
                                        ? base_url('media/profile-picture/' . $memberUserId)
                                        : '';
                                    $memberProfileId = (int) ($member['id'] ?? 0);
                                    ?>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if ($memberPicUrl !== ''): ?>
                                            <img src="<?= $memberPicUrl ?>" alt="" class="member-avatar" onerror="this.classList.add('d-none');this.nextElementSibling.classList.remove('d-none');">
                                            <span class="member-avatar-fallback d-none"><i class="bi bi-person-circle member-avatar-fallback-icon"></i></span>
                                        <?php else: ?>
                                            <span class="member-avatar-fallback"><i class="bi bi-person-circle member-avatar-fallback-icon"></i></span>
                                        <?php endif; ?>
                                        <div>
                                            <?php if ($memberProfileId > 0): ?>
                                                <a href="<?= base_url('members/view/' . $memberProfileId) ?>" class="people-name-link"><?= esc($member['first_name'] . ' ' . $member['last_name']) ?></a>
                                            <?php else: ?>
                                                <?= esc($member['first_name'] . ' ' . $member['last_name']) ?>
                                            <?php endif; ?>
                                            <?php if ($member['middle_name']): ?>
                                                <small class="text-muted">(<?= esc((string) ($member['middle_name'] ?? '')) ?>)</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?= esc((string) ($member['email'] ?? '')) ?></td>
                                <td><?= esc((string) ($member['phone'] ?? '-')) ?></td>
                                <td>
                                    <?php
                                    $statusValue = strtolower(trim((string) ($member['affiliation_status'] ?? $member['membership_status'] ?? 'regular_attender')));
                                    $statusLabel = \App\Services\MembershipStatusService::label($statusValue);

                                    $portalAccessRaw = array_key_exists('portal_access', $member)
                                        ? trim((string) ($member['portal_access'] ?? ''))
                                        : '';
                                    $portalAccess = $portalAccessRaw !== '' ? strtolower($portalAccessRaw) : null;
                                    $portalAccess = in_array($portalAccess, ['none', 'invited', 'enabled'], true) ? $portalAccess : 'none';
                                    $hasPortalIdentity = trim((string) ($member['email'] ?? '')) !== '';
                                    $requiresReset = (int) ($member['force_reset'] ?? 0) === 1;

                                    if ($hasPortalIdentity) {
                                        if ($requiresReset) {
                                            $portalAccess = 'invited';
                                        } elseif (in_array($portalAccess, ['none', 'invited'], true)) {
                                            $portalAccess = 'enabled';
                                        }
                                    }
                                    $isNonPortal = in_array($portalAccess, ['none', 'invited'], true);
                                    $isGoodStanding = (int) ($member['is_good_standing'] ?? 0) === 1;
                                    $statusBadgeColor = \App\Services\MembershipStatusService::badgeColor($statusValue);
                                    ?>
                                    <span class="badge border border-<?= $statusBadgeColor ?> text-<?= $statusBadgeColor ?> fw-normal">
                                        <?= esc($statusLabel) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($isGoodStanding): ?>
                                        <span class="badge border border-info text-info fw-normal me-1"><?= lang('People.goodStandingBadge') ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($show_profile_pending_indicator) && (int) ($member['profile_pending_completion'] ?? 0) === 1): ?>
                                        <span class="badge border border-warning text-warning fw-normal me-1"><?= lang('Members.profilePendingBadge') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end table-actions">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary"
                                            type="button"
                                            data-bs-toggle="dropdown"
                                            aria-expanded="false"
                                            title="<?= esc(lang('App.colActions')) ?>">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <li>
                                                <a class="dropdown-item" href="<?= base_url('members/view/' . (int) $member['id']) ?>">
                                                    <i class="bi bi-eye me-2"></i><?= lang('App.view') ?>
                                                </a>
                                            </li>
                                            <?php if (auth()->user()->can('member.edit')): ?>
                                                <li>
                                                    <a class="dropdown-item" href="<?= base_url('members/edit/' . (int) $member['id']) ?>">
                                                        <i class="bi bi-pencil me-2"></i><?= lang('App.edit') ?>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                            <?php if (auth()->user()->can('member.delete')): ?>
                                                <li>
                                                    <hr class="dropdown-divider">
                                                </li>
                                                <li>
                                                    <button class="dropdown-item text-danger"
                                                        type="button"
                                                        onclick="openSingleDeleteMemberModal(<?= (int) $member['id'] ?>, '<?= esc(trim((string) (($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''))), 'js') ?>', '<?= esc((string) ($member['email'] ?? ''), 'js') ?>'); return false;">
                                                        <i class="bi bi-trash me-2"></i><?= lang('App.delete') ?>
                                                    </button>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php
            $currentPage = max(1, (int) ($current_page ?? 1));
            $totalPages = max(1, (int) ($total_pages ?? 1));
            $pageQueryBase = [];
            if ($selectedStatus !== '') {
                $pageQueryBase['status'] = $selectedStatus;
            }
            $pageQueryBase['per_page'] = $selectedPerPage;
            ?>
            <?php if ($totalPages > 1): ?>
                <nav class="mt-3" aria-label="Members pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <?php
                        $prevDisabled = $currentPage <= 1;
                        $nextDisabled = $currentPage >= $totalPages;
                        $startPage = max(1, $currentPage - 2);
                        $endPage = min($totalPages, $currentPage + 2);
                        ?>
                        <li class="page-item <?= $prevDisabled ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $prevDisabled ? '#' : base_url('members?' . http_build_query(array_merge($pageQueryBase, ['page' => $currentPage - 1]))) ?>">&laquo;</a>
                        </li>
                        <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                            <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                                <a class="page-link" href="<?= base_url('members?' . http_build_query(array_merge($pageQueryBase, ['page' => $p]))) ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $nextDisabled ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $nextDisabled ? '#' : base_url('members?' . http_build_query(array_merge($pageQueryBase, ['page' => $currentPage + 1]))) ?>">&raquo;</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-people fs-1 text-muted"></i>
                <p class="text-muted mt-3"><?= lang('Members.noMembersFound') ?></p>
                <?php if (auth()->user()->can('member.create')): ?>
                    <a href="<?= base_url('members/create') ?>" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> <?= lang('Members.createFirstPortalMember') ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($can_moderate_registrations) && !empty($pending_registrations_count)): ?>
    <div class="card mb-3" id="pendingRegistrationsCard">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong><?= lang('Members.pendingRegistrationsTitle') ?></strong>
            <span class="badge border border-warning text-warning fw-normal"><?= (int) ($pending_registrations_count ?? 0) ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th><?= lang('App.colName') ?></th>
                            <th><?= lang('App.colEmail') ?></th>
                            <th><?= lang('App.colPhone') ?></th>
                            <th class="text-end"><?= lang('App.colActions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_registrations as $req): ?>
                            <tr>
                                <td><?= esc(trim((string) ($req['first_name'] ?? '') . ' ' . (string) ($req['last_name'] ?? ''))) ?></td>
                                <td><?= esc((string) ($req['email'] ?? '')) ?></td>
                                <td><?= esc((string) ($req['phone'] ?? '-')) ?></td>
                                <td class="text-end">
                                    <form action="<?= base_url('members/registrations/approve/' . (int) ($req['id'] ?? 0)) ?>" method="post" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('<?= esc(lang('Dashboard.confirmApproveRegistration'), 'js') ?>')"><?= lang('Dashboard.approveLabel') ?></button>
                                    </form>
                                    <form action="<?= base_url('members/registrations/dismiss/' . (int) ($req['id'] ?? 0)) ?>" method="post" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-secondary ms-1" onclick="return confirm('<?= esc(lang('Dashboard.confirmDismissRegistration'), 'js') ?>')"><?= lang('Dashboard.dismissLabel') ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($isProvinceAdmin)): ?>
    <div class="modal fade" id="bulkTransferModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="bulkTransferForm" action="<?= base_url('members/transfer') ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="modal-header">
                        <h5 class="modal-title"><?= lang('Members.bulkTransferTitle') ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning py-2">
                            <?= lang('Members.bulkTransferWarning') ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><?= lang('Members.destinationParish') ?></label>
                            <select name="destination_parish_id" class="form-select" required>
                                <option value=""><?= lang('Members.selectDestination') ?></option>
                                <?php foreach ($availableParishes as $parish): ?>
                                    <?php if ((int) $parish['id'] !== (int) $parish_id): ?>
                                        <?php $parishUeid = trim((string) ($parish['parish_ueid'] ?? '')); ?>
                                        <option value="<?= (int) $parish['id'] ?>\"><?= esc($parish['name'] . ($parishUeid !== '' ? ' (' . $parishUeid . ')' : '')) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-2">
                            <label class="form-label"><?= lang('Members.reasonForTransfer') ?></label>
                            <textarea name="reason" class="form-control" rows="3" maxlength="500" required placeholder="<?= lang('Members.transferReasonPlaceholder') ?>"></textarea>
                            <small class="text-muted"><?= lang('App.minimumCharsHint') ?></small>
                        </div>

                        <p class="mb-0"><strong id="transferSelectionCount">0</strong> <?= lang('Members.membersSelected') ?></p>
                        <div id="selectedMemberInputs"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                        <button type="submit" class="btn btn-primary"><?= lang('Members.transferMembers') ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (empty($isProvinceAdmin) && auth()->user()->can('member.delete')): ?>
    <div class="modal fade" id="singleDeleteMemberModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i><?= lang('App.delete') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="fw-semibold mb-2" id="singleDeleteMemberInfo"></p>
                    <div class="alert alert-warning py-2 mb-3">This action cannot be undone.</div>
                    <label class="form-label" for="singleDeleteMemberReasonInput"><?= lang('App.bulkDeleteReasonLabel') ?></label>
                    <textarea id="singleDeleteMemberReasonInput" class="form-control" rows="3" maxlength="500" placeholder="<?= esc(lang('App.bulkDeleteReasonPlaceholder')) ?>" aria-describedby="singleDeleteMemberReasonFeedback"></textarea>
                    <div id="singleDeleteMemberReasonFeedback" class="invalid-feedback"></div>
                    <div class="small text-muted mt-1"><?= lang('App.bulkDeleteReasonHelp') ?></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="button" class="btn btn-danger" id="confirmSingleDeleteMemberBtn"><?= lang('App.delete') ?></button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="bulkDeleteMembersModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i><?= lang('App.bulkDeleteWarningTitle') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="fw-semibold mb-2" id="bulkDeleteMembersSummary"></p>
                    <div class="alert alert-warning py-2 mb-3">These deletions cannot be undone.</div>
                    <div class="small text-info mb-2"><?= lang('Members.bulkDeleteRecommendedHint') ?></div>
                    <label class="form-label" for="bulkDeleteMembersReasonInput"><?= lang('App.bulkDeleteReasonLabel') ?></label>
                    <textarea id="bulkDeleteMembersReasonInput" class="form-control" rows="3" maxlength="500" placeholder="<?= esc(lang('App.bulkDeleteReasonPlaceholder')) ?>" aria-describedby="bulkDeleteMembersReasonFeedback"></textarea>
                    <div id="bulkDeleteMembersReasonFeedback" class="invalid-feedback"></div>
                    <div class="small text-muted mt-1"><?= lang('App.bulkDeleteReasonHelp') ?></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="button" class="btn btn-danger" id="confirmBulkDeleteMembersBtn"><?= lang('App.delete') ?></button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
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

    function submitMemberDelete(memberIds, reason, btn) {
        if (!Array.isArray(memberIds) || memberIds.length === 0) {
            showAppToast('<?= esc(lang('Members.bulkDeleteSelectAtLeastOne'), 'js') ?>', 'warning');
            return;
        }

        if (btn && btn.length) {
            btn.prop('disabled', true);
        }

        $.ajax({
            url: '<?= base_url('members/bulk-delete') ?>',
            type: 'POST',
            data: {
                'member_ids': memberIds,
                'delete_reason': reason.trim(),
                <?= csrf_token() ?>: '<?= csrf_hash() ?>'
            },
            success: function(response) {
                if (btn && btn.length) {
                    btn.prop('disabled', false);
                }
                // Inline error feedback for modal reason field
                var reasonField = null,
                    feedbackId = '';
                if (memberIds.length === 1) {
                    reasonField = document.getElementById('singleDeleteMemberReasonInput');
                    feedbackId = 'singleDeleteMemberReasonFeedback';
                } else {
                    reasonField = document.getElementById('bulkDeleteMembersReasonInput');
                    feedbackId = 'bulkDeleteMembersReasonFeedback';
                }
                if (response && response.errors && response.errors.delete_reason) {
                    setReasonFieldError(reasonField, feedbackId, response.errors.delete_reason);
                    if (reasonField) reasonField.focus();
                    return;
                }
                setReasonFieldError(reasonField, feedbackId, '');
                if (response.success || Number(response.deleted || 0) > 0) {
                    selectedMemberIdsSet.clear();
                    saveStoredMemberSelection();
                    showAppToast(response.message || '<?= esc(lang('Members.deleted'), 'js') ?>', 'primary');
                    location.reload();
                    return;
                }
                showAppToast('<?= esc(lang('App.errorPrefix'), 'js') ?>' + contractErrorMessage(response, '<?= esc(lang('Members.deleteError'), 'js') ?>'), 'danger');
            },
            error: function(xhr) {
                if (btn && btn.length) {
                    btn.prop('disabled', false);
                }
                var reasonField = null,
                    feedbackId = '';
                if (memberIds.length === 1) {
                    reasonField = document.getElementById('singleDeleteMemberReasonInput');
                    feedbackId = 'singleDeleteMemberReasonFeedback';
                } else {
                    reasonField = document.getElementById('bulkDeleteMembersReasonInput');
                    feedbackId = 'bulkDeleteMembersReasonFeedback';
                }
                if (xhr && xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors.delete_reason) {
                    setReasonFieldError(reasonField, feedbackId, xhr.responseJSON.errors.delete_reason);
                    if (reasonField) reasonField.focus();
                    return;
                }
                setReasonFieldError(reasonField, feedbackId, '');
                showAppToast('<?= esc(lang('Members.deleteError'), 'js') ?>', 'danger');
            }
        });
    }

    function openSingleDeleteMemberModal(id, name, email) {
        const modalEl = document.getElementById('singleDeleteMemberModal');
        const infoEl = document.getElementById('singleDeleteMemberInfo');
        const reasonInput = document.getElementById('singleDeleteMemberReasonInput');
        const modal = getModalInstance(modalEl);

        window.__pendingSingleDeleteMemberIds = [String(id)];

        if (!modal || !reasonInput) {
            showAppToast('<?= esc(lang('App.errorGeneric'), 'js') ?>', 'warning');
            return;
        }

        if (infoEl) {
            const safeName = (name || '').trim();
            const safeEmail = (email || '').trim();
            infoEl.textContent = safeEmail ? `${safeName} (${safeEmail})` : safeName;
        }
        reasonInput.value = '';
        modal.show();
    }

    // Search functionality
    $('#memberSearch').on('keyup', function() {
        const value = $(this).val().toLowerCase();
        $('table tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });

    const usePersistentBulkDeleteSelection = $('#bulkDeleteMembersBtn').length > 0;
    const memberSelectionStorageKey = `${window.location.pathname}:bulkDeleteMembersSelection`;
    const exportBtn = document.getElementById('bulk-export-btn');
    const exportIdsDiv = document.getElementById('bulk-export-ids');
    const exportForm = document.getElementById('members-export-form');
    const bulkMarkMemberBtn = document.getElementById('bulk-mark-member-btn');
    const bulkRemoveMemberBtn = document.getElementById('bulk-remove-member-btn');

    function loadStoredMemberSelection() {
        if (!usePersistentBulkDeleteSelection) {
            return new Set();
        }

        try {
            const raw = sessionStorage.getItem(memberSelectionStorageKey);
            if (!raw) {
                return new Set();
            }

            const parsed = JSON.parse(raw);
            if (!Array.isArray(parsed)) {
                return new Set();
            }

            return new Set(parsed.map((value) => String(value)).filter((value) => /^\d+$/.test(value) && Number(value) > 0));
        } catch (e) {
            return new Set();
        }
    }

    function saveStoredMemberSelection() {
        if (!usePersistentBulkDeleteSelection) {
            return;
        }

        sessionStorage.setItem(memberSelectionStorageKey, JSON.stringify(Array.from(selectedMemberIdsSet)));
    }

    function syncMemberCheckboxesFromSelection() {
        if (!usePersistentBulkDeleteSelection) {
            return;
        }

        $('.member-checkbox').each(function() {
            $(this).prop('checked', selectedMemberIdsSet.has(String($(this).val())));
        });
    }

    function getSelectedMemberIds() {
        if (usePersistentBulkDeleteSelection) {
            return Array.from(selectedMemberIdsSet);
        }

        return $('.member-checkbox:checked').map(function() {
            return $(this).val();
        }).get();
    }

    function updateSelectAllMembersState() {
        const total = $('.member-checkbox').length;
        if (total === 0) {
            $('#selectAllMembers').prop('checked', false);
            return;
        }

        if (usePersistentBulkDeleteSelection) {
            const allChecked = $('.member-checkbox').toArray().every((el) => selectedMemberIdsSet.has(String(el.value)));
            $('#selectAllMembers').prop('checked', allChecked);
            return;
        }

        const checked = $('.member-checkbox:checked').length;
        $('#selectAllMembers').prop('checked', total === checked);
    }

    let selectedMemberIdsSet = loadStoredMemberSelection();

    function getModalInstance(modalEl) {
        if (!modalEl || typeof window.bootstrap === 'undefined' || typeof window.bootstrap.Modal !== 'function') {
            return null;
        }

        return window.bootstrap.Modal.getOrCreateInstance(modalEl);
    }

    function refreshBulkDeleteMembersButton() {
        const bulkDeleteBtn = $('#bulkDeleteMembersBtn');
        const selected = getSelectedMemberIds();

        if (bulkDeleteBtn.length) {
            bulkDeleteBtn.toggleClass('d-none', selected.length === 0);
        }

        if (bulkMarkMemberBtn) {
            bulkMarkMemberBtn.classList.toggle('d-none', selected.length === 0);
        }

        if (bulkRemoveMemberBtn) {
            bulkRemoveMemberBtn.classList.toggle('d-none', selected.length === 0);
        }

        if (exportBtn) {
            exportBtn.classList.toggle('d-none', selected.length === 0);
        }

        if (exportIdsDiv) {
            exportIdsDiv.innerHTML = '';
            getSelectedUserIds().forEach(function(id) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'user_ids[]';
                input.value = String(id);
                exportIdsDiv.appendChild(input);
            });
        }
    }

    function selectedMemberCheckboxes() {
        return Array.from(document.querySelectorAll('.member-checkbox:checked'));
    }

    function getSelectedUserIds() {
        return selectedMemberCheckboxes()
            .map(function(chk) {
                return parseInt(chk.getAttribute('data-user-id') || '0', 10);
            })
            .filter(function(id) {
                return Number.isInteger(id) && id > 0;
            });
    }

    function bulkToggleMemberTag(isMember) {
        const userIds = getSelectedUserIds();
        if (userIds.length === 0) {
            showAppToast('<?= esc(lang('Members.bulkDeleteSelectAtLeastOne'), 'js') ?>', 'warning');
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

    $('#bulkDeleteMembersBtn').on('click', function() {
        const selected = getSelectedMemberIds();

        if (selected.length === 0) {
            showAppToast('<?= esc(lang('Members.bulkDeleteSelectAtLeastOne'), 'js') ?>', 'warning');
            return;
        }

        if (selected.length === 1) {
            const rowCheckbox = document.querySelector(`.member-checkbox[value="${selected[0]}"]`);
            const singleName = rowCheckbox ? (rowCheckbox.getAttribute('data-member-name') || '') : '';
            const singleEmail = rowCheckbox ? (rowCheckbox.getAttribute('data-member-email') || '') : '';
            openSingleDeleteMemberModal(selected[0], singleName, singleEmail);
            return;
        }

        const warningModalEl = document.getElementById('bulkDeleteMembersModal');
        const summaryEl = document.getElementById('bulkDeleteMembersSummary');
        const reasonInput = document.getElementById('bulkDeleteMembersReasonInput');
        const warningModal = getModalInstance(warningModalEl);

        window.__pendingBulkDeleteMemberIds = selected;

        if (!warningModal || !reasonInput) {
            showAppToast('<?= esc(lang('App.errorGeneric'), 'js') ?>', 'warning');
            return;
        }

        reasonInput.value = '';
        if (summaryEl) {
            summaryEl.textContent = `Delete ${selected.length} selected users?`;
        }
        warningModal.show();
    });

    function setReasonFieldError(input, feedbackId, message) {
        if (!input) return;
        input.classList.toggle('is-invalid', !!message);
        const feedback = document.getElementById(feedbackId);
        if (feedback) feedback.textContent = message || '';
    }

    $('#confirmSingleDeleteMemberBtn').on('click', function() {
        const selected = window.__pendingSingleDeleteMemberIds || [];
        const reasonInput = document.getElementById('singleDeleteMemberReasonInput');
        const reason = (reasonInput ? reasonInput.value : '').trim();
        setReasonFieldError(reasonInput, 'singleDeleteMemberReasonFeedback', '');
        if (!reason || reason.length < 8) {
            setReasonFieldError(reasonInput, 'singleDeleteMemberReasonFeedback', '<?= esc(lang('App.bulkDeleteReasonRequired'), 'js') ?>');
            reasonInput.focus();
            return;
        }
        submitMemberDelete(selected, reason, $(this));
    });

    $('#confirmBulkDeleteMembersBtn').on('click', function() {
        const selected = window.__pendingBulkDeleteMemberIds || [];
        const reasonInput = document.getElementById('bulkDeleteMembersReasonInput');
        const reason = (reasonInput ? reasonInput.value : '').trim();
        setReasonFieldError(reasonInput, 'bulkDeleteMembersReasonFeedback', '');
        if (!reason || reason.length < 8) {
            setReasonFieldError(reasonInput, 'bulkDeleteMembersReasonFeedback', '<?= esc(lang('App.bulkDeleteReasonRequired'), 'js') ?>');
            reasonInput.focus();
            return;
        }
        submitMemberDelete(selected, reason, $(this));
    });

    $('#selectAllMembers').on('change', function() {
        const isChecked = $(this).is(':checked');
        $('.member-checkbox').each(function() {
            const id = String($(this).val());
            if (isChecked) {
                selectedMemberIdsSet.add(id);
                $(this).prop('checked', true);
            } else {
                selectedMemberIdsSet.delete(id);
                $(this).prop('checked', false);
            }
        });
        saveStoredMemberSelection();
        updateSelectAllMembersState();
        refreshBulkDeleteMembersButton();
    });

    $(document).on('change', '.member-checkbox', function() {
        const id = String($(this).val());
        if ($(this).is(':checked')) {
            selectedMemberIdsSet.add(id);
        } else {
            selectedMemberIdsSet.delete(id);
        }

        saveStoredMemberSelection();
        updateSelectAllMembersState();
        refreshBulkDeleteMembersButton();
    });

    syncMemberCheckboxesFromSelection();
    updateSelectAllMembersState();
    refreshBulkDeleteMembersButton();

    if (exportForm) {
        exportForm.addEventListener('submit', function() {
            if (!exportBtn) {
                return;
            }

            const originalLabel = exportBtn.innerHTML;
            exportBtn.disabled = true;
            exportBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> <?= lang('App.loading') ?>';

            setTimeout(function() {
                document.querySelectorAll('.member-checkbox').forEach(function(chk) {
                    chk.checked = false;
                });
                selectedMemberIdsSet = new Set();
                saveStoredMemberSelection();
                updateSelectAllMembersState();
                refreshBulkDeleteMembersButton();
                exportBtn.disabled = false;
                exportBtn.innerHTML = originalLabel;
            }, 2000);
        });
    }

    <?php if (!empty($isProvinceAdmin)): ?>

        function refreshSelectionSummary() {
            const selected = getSelectedMemberIds();
            $('#selectedCountBadge').text(selected.length + ' selected');
            $('#transferSelectionCount').text(selected.length);
        }

        $(document).on('change', '#selectAllMembers, .member-checkbox', function() {
            refreshSelectionSummary();
        });

        $('#bulkTransferForm').on('submit', function(e) {
            const selected = getSelectedMemberIds();
            const holder = $('#selectedMemberInputs');

            if (selected.length === 0) {
                e.preventDefault();
                showAppToast('<?= esc(lang('Members.selectMembersRequired'), 'js') ?>', 'warning');
                return;
            }

            holder.empty();
            selected.forEach(function(id) {
                holder.append('<input type="hidden" name="member_ids[]" value="' + id + '">');
            });
        });

        refreshSelectionSummary();
    <?php endif; ?>
</script>
<?= $this->endSection() ?>