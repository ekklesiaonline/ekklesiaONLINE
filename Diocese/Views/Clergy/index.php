<?= $this->extend('Diocese\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="<?= base_url('diocese/clergy/create') ?>" class="btn btn-primary"><i class="bi bi-plus-circle" aria-hidden="true"></i> <?= lang('Clergy.btnInvite') ?></a>
</div>

<?php $stats = $stats ?? ['total' => 0, 'active' => 0, 'awaiting' => 0, 'invited' => 0, 'pending_requests' => 0, 'pending_candidates' => 0, 'expiring_grants' => 0]; ?>
<div class="row g-3 mb-3">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100"><div class="card-body py-3">
            <div class="text-muted small"><?= lang('Clergy.cardTotal') ?></div>
            <div class="fs-4"><?= (int) $stats['total'] ?></div>
            <div class="text-muted small"><?= lang('Clergy.cardTotalHelp') ?>: <?= (int) $stats['active'] ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100"><div class="card-body py-3">
            <div class="text-muted small"><?= lang('Clergy.cardAwaitingAppointment') ?></div>
            <div class="fs-4"><?= (int) $stats['awaiting'] ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100"><div class="card-body py-3">
            <div class="text-muted small"><?= lang('Clergy.cardPendingRequests') ?></div>
            <div class="fs-4"><?= (int) $stats['pending_requests'] ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100"><div class="card-body py-3">
            <div class="text-muted small"><?= lang('Clergy.cardPendingCandidates') ?></div>
            <div class="fs-4"><?= (int) $stats['pending_candidates'] ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100"><div class="card-body py-3">
            <div class="text-muted small"><?= lang('Clergy.cardPendingInvitations') ?></div>
            <div class="fs-4"><?= (int) $stats['invited'] ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100"><div class="card-body py-3">
            <div class="text-muted small"><?= lang('Clergy.cardExpiringGrants') ?></div>
            <div class="fs-4"><?= (int) $stats['expiring_grants'] ?></div>
        </div></div>
    </div>
</div>

<?php
$queues = $queues ?? [];
$queueName = static function (array $row): string {
    $name = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
    if ($name === '') {
        $name = trim((string) ($row['person_first_name'] ?? '') . ' ' . (string) ($row['person_last_name'] ?? ''));
    }
    return $name !== '' ? $name : '—';
};
?>

<?php if (($queues['can_appointments'] ?? false) || ($queues['can_ordination'] ?? false) || ($queues['can_governance'] ?? false)): ?>
<h2 class="h5 mb-3"><?= lang('Clergy.queueWorkQueues') ?></h2>
<div class="row g-3 mb-3">

<?php if ($queues['can_appointments'] ?? false): ?>
    <div class="col-12">
        <div class="card">
            <div class="card-header"><?= lang('Clergy.queueAppointmentRequests') ?> <span class="badge border border-primary text-primary fw-normal"><?= count($queues['appointment_requests'] ?? []) ?></span></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th><?= lang('Clergy.colPerson') ?></th><th><?= lang('Clergy.colParish') ?></th><th><?= lang('Clergy.colProposedOffice') ?></th><th><?= lang('App.colActions') ?></th></tr></thead>
                        <tbody>
                        <?php foreach (($queues['appointment_requests'] ?? []) as $req): ?>
                            <tr>
                                <td><?= esc($queueName($req)) ?></td>
                                <td><?= esc($req['parish_name'] ?? '—') ?></td>
                                <td><?= esc($req['proposed_office'] ?? '—') ?><br><small class="text-muted"><?= esc($req['created_at'] ?? '') ?></small></td>
                                <td class="text-nowrap">
                                    <button type="button" class="btn btn-outline-success btn-sm queue-action"
                                        data-url="<?= base_url('diocese/clergy-review/appointment-requests/' . $dioceseId . '/review/' . (int) $req['id']) ?>"
                                        data-decision="approved" data-modal="reviewRequestModal" data-name="<?= esc($queueName($req)) ?>"
                                        ><?= lang('Clergy.actionApprove') ?></button>
                                    <button type="button" class="btn btn-outline-danger btn-sm queue-action"
                                        data-url="<?= base_url('diocese/clergy-review/appointment-requests/' . $dioceseId . '/review/' . (int) $req['id']) ?>"
                                        data-decision="rejected" data-modal="reviewRequestModal" data-name="<?= esc($queueName($req)) ?>"
                                        ><?= lang('Clergy.actionReject') ?></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($queues['appointment_requests'])): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3"><?= lang('Clergy.queueEmpty') ?></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header"><?= lang('Clergy.queuePendingOffices') ?> <span class="badge border border-primary text-primary fw-normal"><?= count($queues['pending_offices'] ?? []) ?></span></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th><?= lang('Clergy.colPerson') ?></th><th><?= lang('Clergy.colOfficeTitle') ?></th><th><?= lang('Clergy.colScope') ?></th><th><?= lang('App.colActions') ?></th></tr></thead>
                        <tbody>
                        <?php foreach (($queues['pending_offices'] ?? []) as $office): ?>
                            <tr>
                                <td><?= esc($queueName($office)) ?></td>
                                <td><?= esc($office['title'] ?? '') ?><br><small class="text-muted"><?= esc($office['role_code'] ?? '') ?> · <?= esc($office['started_at'] ?? '') ?></small></td>
                                <td><?= esc($office['parish_name'] ?? $office['deanery_name'] ?? lang('Clergy.scope.' . ($office['scope_type'] ?? 'diocese'))) ?></td>
                                <td class="text-nowrap">
                                    <button type="button" class="btn btn-outline-success btn-sm queue-action"
                                        data-url="<?= base_url('diocese/clergy-review/office/' . (int) $office['id'] . '/approve') ?>"
                                        data-modal="officeApproveModal" data-name="<?= esc($queueName($office) . ' — ' . ($office['title'] ?? '')) ?>"
                                        ><?= lang('Clergy.actionApprove') ?></button>
                                    <button type="button" class="btn btn-outline-danger btn-sm queue-action"
                                        data-url="<?= base_url('diocese/clergy-review/office/' . (int) $office['id'] . '/revoke') ?>"
                                        data-modal="confirmModal" data-confirm="<?= esc(lang('Clergy.confirmRevokeOffice')) ?>" data-name="<?= esc($queueName($office) . ' — ' . ($office['title'] ?? '')) ?>"
                                        ><?= lang('Clergy.actionRevoke') ?></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($queues['pending_offices'])): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3"><?= lang('Clergy.queueEmpty') ?></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($queues['can_ordination'] ?? false): ?>
    <div class="col-12">
        <div class="card">
            <div class="card-header"><?= lang('Clergy.queueCandidates') ?> <span class="badge border border-primary text-primary fw-normal"><?= count($queues['candidates'] ?? []) ?></span></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th><?= lang('Clergy.colPerson') ?></th><th><?= lang('Clergy.colProposed') ?></th><th><?= lang('Clergy.colSource') ?></th><th><?= lang('App.colActions') ?></th></tr></thead>
                        <tbody>
                        <?php foreach (($queues['candidates'] ?? []) as $cand): ?>
                            <tr>
                                <td><?= esc($queueName($cand)) ?></td>
                                <td><?= esc(trim(($cand['proposed_order'] ?? '') . ' ' . ($cand['proposed_office'] ?? ''))) ?: '—' ?><br><small class="text-muted"><?= esc($cand['parish_name'] ?? lang('Clergy.scope.' . ($cand['scope_type'] ?? 'diocese'))) ?></small></td>
                                <td><span class="badge border border-secondary text-secondary fw-normal"><?= esc($cand['source_type'] ?? '') ?></span></td>
                                <td class="text-nowrap">
                                    <button type="button" class="btn btn-outline-success btn-sm queue-action"
                                        data-url="<?= base_url('diocese/clergy-review/candidates/' . (int) $cand['id'] . '/review/' . $dioceseId) ?>"
                                        data-decision="approved" data-modal="reviewCandidateModal" data-name="<?= esc($queueName($cand)) ?>"
                                        ><?= lang('Clergy.actionApprove') ?></button>
                                    <button type="button" class="btn btn-outline-danger btn-sm queue-action"
                                        data-url="<?= base_url('diocese/clergy-review/candidates/' . (int) $cand['id'] . '/review/' . $dioceseId) ?>"
                                        data-decision="rejected" data-modal="reviewCandidateModal" data-name="<?= esc($queueName($cand)) ?>"
                                        ><?= lang('Clergy.actionReject') ?></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($queues['candidates'])): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3"><?= lang('Clergy.queueEmpty') ?></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header"><?= lang('Clergy.queueUnverifiedOrdinations') ?> <span class="badge border border-primary text-primary fw-normal"><?= count($queues['unverified_ordinations'] ?? []) ?></span></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th><?= lang('Clergy.colPerson') ?></th><th><?= lang('Clergy.colOrder') ?></th><th><?= lang('App.colActions') ?></th></tr></thead>
                        <tbody>
                        <?php foreach (($queues['unverified_ordinations'] ?? []) as $ord): ?>
                            <tr>
                                <td><?= esc($ord['name']) ?></td>
                                <td><?= esc($ord['order_code'] ?? '—') ?></td>
                                <td class="text-nowrap">
                                    <button type="button" class="btn btn-outline-primary btn-sm queue-action"
                                        data-url="<?= base_url('diocese/clergy-review/ordination/' . $dioceseId) ?>"
                                        data-person-id="<?= (int) $ord['person_id'] ?>"
                                        data-order-code="<?= esc($ord['order_code'] ?? '') ?>"
                                        data-modal="verifyOrdinationModal" data-name="<?= esc($ord['name']) ?>"
                                        ><?= lang('Clergy.actionVerify') ?></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($queues['unverified_ordinations'])): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3"><?= lang('Clergy.queueEmpty') ?></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($queues['can_governance'] ?? false): ?>
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><?= lang('Clergy.queueDelegations') ?> <span class="badge border border-primary text-primary fw-normal"><?= count($queues['delegations'] ?? []) ?></span></span>
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#delegationIssueModal">
                    <i class="bi bi-plus-circle" aria-hidden="true"></i> <?= lang('Clergy.actionIssueDelegation') ?>
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th><?= lang('Clergy.colPerson') ?></th><th><?= lang('Clergy.colPermission') ?></th><th><?= lang('Clergy.colEnds') ?></th><th><?= lang('Clergy.colGrantedBy') ?></th><th><?= lang('App.colActions') ?></th></tr></thead>
                        <tbody>
                        <?php foreach (($queues['delegations'] ?? []) as $grant): ?>
                            <tr>
                                <td><?= esc($queueName($grant)) ?></td>
                                <td><span class="badge border border-secondary text-secondary fw-normal"><?= esc($grant['permission'] ?? '') ?></span></td>
                                <td><?= esc($grant['ends_at'] ?? '—') ?></td>
                                <td><?= esc(trim(($grant['grantor_first_name'] ?? '') . ' ' . ($grant['grantor_last_name'] ?? ''))) ?: '—' ?></td>
                                <td class="text-nowrap">
                                    <button type="button" class="btn btn-outline-danger btn-sm queue-action"
                                        data-url="<?= base_url('diocese/clergy-review/delegation/revoke/' . (int) $grant['id']) ?>"
                                        data-modal="confirmModal" data-confirm="<?= esc(lang('Clergy.confirmRevokeDelegation')) ?>" data-name="<?= esc($queueName($grant)) ?>"
                                        ><?= lang('Clergy.actionRevoke') ?></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($queues['delegations'])): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3"><?= lang('Clergy.queueEmpty') ?></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

</div>
<?php endif; ?>

<?php $clergyFiltersActive = trim((string) ($q ?? '')) !== ''; ?>
<div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-end filter-panel-header">
        <button class="btn btn-outline-secondary btn-sm filter-toggle-btn" type="button"
            data-bs-toggle="collapse" data-bs-target="#clergyFilterPanel"
            aria-expanded="<?= $clergyFiltersActive ? 'true' : 'false' ?>" aria-controls="clergyFilterPanel">
            <i class="bi bi-funnel" aria-hidden="true"></i> <?= lang('Services.apply') ?>
        </button>
    </div>
    <div id="clergyFilterPanel" class="collapse <?= $clergyFiltersActive ? 'show' : '' ?>">
        <div class="card-body border-top filter-panel-body">
            <form method="get" action="<?= base_url('diocese/clergy') ?>" class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label for="q" class="form-label"><?= lang('Clergy.searchLabel') ?></label>
                    <input type="text" class="form-control form-control-sm" id="q" name="q" value="<?= esc($q ?? '') ?>" placeholder="<?= esc(lang('Clergy.searchPlaceholder')) ?>">
                </div>
                <div class="col-md-6">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-primary flex-fill"><i class="bi bi-search" aria-hidden="true"></i> <?= lang('Services.apply') ?></button>
                        <a href="<?= base_url('diocese/clergy') ?>" class="btn btn-sm btn-outline-secondary flex-fill"><?= lang('App.clear') ?></a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th scope="col"><?= lang('Clergy.colName') ?></th>
                        <th scope="col"><?= lang('Clergy.colOffice') ?></th>
                        <th scope="col"><?= lang('Clergy.colOrder') ?></th>
                        <th scope="col"><?= lang('Clergy.colStanding') ?></th>
                        <th scope="col"><?= lang('Clergy.colLicensedAppointment') ?></th>
                        <th scope="col"><?= lang('Clergy.colPortalAccess') ?></th>
                        <th scope="col"><?= lang('Clergy.colInvitationSent') ?></th>
                        <th scope="col"><?= lang('Clergy.colStatus') ?></th>
                        <th scope="col"><?= lang('App.colActions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($clergy)): ?>
                        <?php foreach ($clergy as $row): ?>
                            <?php
                            $portalAccess = (string) ($row['portal_access'] ?? 'none');
                            $portalColor = ['enabled' => 'success', 'invited' => 'warning', 'none' => 'secondary'][$portalAccess] ?? 'secondary';
                            $activeColor = (int) ($row['active'] ?? 0) === 1 ? 'success' : 'secondary';
                            $standingCode = strtolower((string) ($row['standing']['status_code'] ?? ''));
                            $standingColor = in_array($standingCode, ['suspended', 'inhibited'], true) ? 'danger' : 'success';
                            $ordination = $row['ordination'] ?? null;
                            $ordinationVerified = ($ordination['verification_status'] ?? '') === 'verified';
                            $orderColor = $ordinationVerified ? 'primary' : 'secondary';
                            $appointment = $row['appointment'] ?? null;
                            ?>
                            <tr>
                                <td><?= esc(trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''))) ?><br>
                                    <small class="text-muted"><?= esc($row['email'] ?? '') ?></small></td>
                                <td>
                                    <?php if (!empty($row['positions'])): ?>
                                        <?php foreach ($row['positions'] as $pos): ?>
                                            <div><?= esc($pos['title'] ?? '') ?><small class="text-muted"> — <?= esc($pos['parish_name'] ?? $pos['deanery_name'] ?? lang('Clergy.scope.' . ($pos['scope_type'] ?? 'diocese'))) ?></small></div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-muted"><?= lang('Clergy.noActiveOffice') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($ordination !== null): ?>
                                        <span class="badge border border-<?= $orderColor ?> text-<?= $orderColor ?> fw-normal"><?= esc($ordination['order_code'] ?? '') ?><?= $ordinationVerified ? '' : ' · ' . lang('Clergy.unverified') ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($standingCode !== ''): ?>
                                        <span class="badge border border-<?= $standingColor ?> text-<?= $standingColor ?> fw-normal"><?= esc($standingCode) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($appointment !== null): ?>
                                        <?= esc($appointment['parish_name'] ?? '') ?><small class="text-muted"> — <?= esc($appointment['appointment_type'] ?? '') ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge border border-<?= $portalColor ?> text-<?= $portalColor ?> fw-normal"><?= esc(lang('Clergy.portalAccess.' . $portalAccess) !== 'Clergy.portalAccess.' . $portalAccess ? lang('Clergy.portalAccess.' . $portalAccess) : $portalAccess) ?></span></td>
                                <td><?= esc($row['invitation_sent_at'] ?? '-') ?></td>
                                <td><span class="badge border border-<?= $activeColor ?> text-<?= $activeColor ?> fw-normal"><?= (int) ($row['active'] ?? 0) === 1 ? lang('Clergy.statusActive') : lang('Clergy.statusDisabled') ?></span></td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="dropdown" data-bs-strategy="fixed" aria-expanded="false" title="<?= esc(lang('App.colActions')) ?>">
                                            <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <li><a class="dropdown-item" href="<?= base_url('diocese/clergy/' . (int) $row['id']) ?>"><?= lang('Clergy.actionView') ?></a></li>
                                            <li><a class="dropdown-item" href="<?= base_url('diocese/clergy/' . (int) $row['id'] . '/edit') ?>"><?= lang('App.edit') ?></a></li>
                                            <li>
                                                <form action="<?= base_url('diocese/clergy/' . (int) $row['id'] . '/resend-invitation') ?>" method="post" class="m-0"><?= csrf_field() ?>
                                                    <button type="submit" class="dropdown-item"><?= lang('Clergy.actionResendInvitation') ?></button>
                                                </form>
                                            </li>
                                            <?php if ((int) ($row['active'] ?? 0) === 1): ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form action="<?= base_url('diocese/clergy/' . (int) $row['id'] . '/deactivate') ?>" method="post" class="m-0" onsubmit="return confirm('<?= esc(lang('Clergy.confirmDisablePortalAccess'), 'js') ?>');"><?= csrf_field() ?>
                                                        <button type="submit" class="dropdown-item text-danger"><?= lang('Clergy.actionDisablePortalAccess') ?></button>
                                                    </form>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4"><?= lang('Clergy.noClergyFound') ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (($queues['can_appointments'] ?? false) || ($queues['can_ordination'] ?? false) || ($queues['can_governance'] ?? false)): ?>
<!-- Review appointment request (approve / reject) -->
<div class="modal fade" id="reviewRequestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content queue-form" method="post">
        <div class="modal-header"><h5 class="modal-title"></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
            <p class="queue-subject small text-muted mb-2"></p>
            <input type="hidden" name="decision" value="">
            <div class="mb-2 queue-reason" hidden>
                <label class="form-label"><?= lang('Clergy.fieldReasonCode') ?></label>
                <input type="text" class="form-control form-control-sm" name="reason_code" maxlength="32">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
            <button type="submit" class="btn btn-primary"></button>
        </div>
    </form></div>
</div>

<!-- Approve / revoke pending office -->
<div class="modal fade" id="officeApproveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content queue-form" method="post">
        <div class="modal-header"><h5 class="modal-title"><?= lang('Clergy.modalOfficeApproveTitle') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
            <p class="queue-subject small text-muted mb-2"></p>
            <label class="form-label"><?= lang('Clergy.fieldEvidenceReference') ?> <span class="text-danger">*</span></label>
            <input type="text" class="form-control form-control-sm" name="evidence_reference" maxlength="255" required>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
            <button type="submit" class="btn btn-primary"><?= lang('Clergy.actionApprove') ?></button>
        </div>
    </form></div>
</div>

<!-- Review transition candidate (approve / reject) -->
<div class="modal fade" id="reviewCandidateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content queue-form" method="post">
        <div class="modal-header"><h5 class="modal-title"></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
            <p class="queue-subject small text-muted mb-2"></p>
            <input type="hidden" name="decision" value="">
            <div class="mb-2 queue-order" hidden>
                <label class="form-label"><?= lang('Clergy.fieldConfirmedOrder') ?></label>
                <select class="form-select form-select-sm" name="confirmed_order">
                    <option value=""></option>
                    <option value="deacon"><?= lang('Clergy.order.deacon') ?></option>
                    <option value="priest"><?= lang('Clergy.order.priest') ?></option>
                    <option value="bishop"><?= lang('Clergy.order.bishop') ?></option>
                </select>
            </div>
            <div class="mb-2 queue-reason" hidden>
                <label class="form-label"><?= lang('Clergy.fieldReasonCode') ?></label>
                <select class="form-select form-select-sm" name="reason_code">
                    <option value=""></option>
                    <option value="wrong_person"><?= lang('Clergy.reason.wrong_person') ?></option>
                    <option value="invalid_order"><?= lang('Clergy.reason.invalid_order') ?></option>
                    <option value="not_in_diocese"><?= lang('Clergy.reason.not_in_diocese') ?></option>
                    <option value="duplicate"><?= lang('Clergy.reason.duplicate') ?></option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
            <button type="submit" class="btn btn-primary"></button>
        </div>
    </form></div>
</div>

<!-- Verify ordination -->
<div class="modal fade" id="verifyOrdinationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content queue-form" method="post">
        <div class="modal-header"><h5 class="modal-title"><?= lang('Clergy.modalVerifyOrdinationTitle') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
            <p class="queue-subject small text-muted mb-2"></p>
            <input type="hidden" name="person_id" value="">
            <div class="row g-2">
                <div class="col-6">
                    <label class="form-label"><?= lang('Clergy.fieldOrderCode') ?> <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm" name="order_code" required>
                        <option value="deacon"><?= lang('Clergy.order.deacon') ?></option>
                        <option value="priest"><?= lang('Clergy.order.priest') ?></option>
                        <option value="bishop"><?= lang('Clergy.order.bishop') ?></option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label"><?= lang('Clergy.fieldDeaconTrack') ?></label>
                    <select class="form-select form-select-sm" name="deacon_track">
                        <option value=""></option>
                        <option value="vocational"><?= lang('Clergy.track.vocational') ?></option>
                        <option value="transitional"><?= lang('Clergy.track.transitional') ?></option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label"><?= lang('Clergy.fieldOrdainedOn') ?></label>
                    <input type="date" class="form-control form-control-sm" name="ordained_on">
                </div>
                <div class="col-12">
                    <label class="form-label"><?= lang('Clergy.fieldEvidenceReference') ?> <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" name="evidence_reference" maxlength="255" required>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
            <button type="submit" class="btn btn-primary"><?= lang('Clergy.actionVerify') ?></button>
        </div>
    </form></div>
</div>

<!-- Issue delegation -->
<div class="modal fade" id="delegationIssueModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content queue-form" method="post" action="<?= base_url('diocese/clergy-review/delegation/' . $dioceseId) ?>">
        <div class="modal-header"><h5 class="modal-title"><?= lang('Clergy.modalDelegationIssueTitle') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
            <div class="row g-2">
                <div class="col-12">
                    <label class="form-label"><?= lang('Clergy.fieldUser') ?> <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm" name="user_id" required>
                        <option value=""></option>
                        <?php foreach (($queues['delegation_targets'] ?? []) as $t): ?>
                            <option value="<?= (int) $t['id'] ?>"><?= esc(trim(($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? ''))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label"><?= lang('Clergy.fieldPermission') ?> <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm" name="permission" required>
                        <option value="governance.diocese.approve">governance.diocese.approve</option>
                        <option value="clergy.ordination.verify">clergy.ordination.verify</option>
                        <option value="clergy.appointment.manage">clergy.appointment.manage</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label"><?= lang('Clergy.fieldEndsOn') ?> <span class="text-danger">*</span></label>
                    <input type="date" class="form-control form-control-sm" name="ends_on" required>
                </div>
                <div class="col-12">
                    <label class="form-label"><?= lang('Clergy.fieldEvidenceReference') ?> <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" name="evidence_reference" maxlength="255" required>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
            <button type="submit" class="btn btn-primary"><?= lang('Clergy.actionIssueDelegation') ?></button>
        </div>
    </form></div>
</div>

<!-- Generic confirm (revoke office / delegation) -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content queue-form" method="post">
        <div class="modal-header"><h5 class="modal-title"><?= lang('Clergy.modalConfirmTitle') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
            <p class="queue-subject small text-muted mb-1"></p>
            <p class="queue-confirm mb-0"></p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
            <button type="submit" class="btn btn-danger"><?= lang('Clergy.actionRevoke') ?></button>
        </div>
    </form></div>
</div>

<script>
(function () {
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfHeader = document.querySelector('meta[name="csrf-header"]')?.content ?? 'X-CSRF-TOKEN';
    const approveLabel = <?= json_encode(lang('Clergy.actionApprove')) ?>;
    const rejectLabel = <?= json_encode(lang('Clergy.actionReject')) ?>;
    const approveTitle = <?= json_encode(lang('Clergy.modalApproveTitle')) ?>;
    const rejectTitle = <?= json_encode(lang('Clergy.modalRejectTitle')) ?>;

    document.querySelectorAll('.queue-action[data-modal]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const modalEl = document.getElementById(btn.dataset.modal);
            if (!modalEl) return;
            const form = modalEl.querySelector('form.queue-form');
            if (btn.dataset.url) form.action = btn.dataset.url;
            const subject = modalEl.querySelector('.queue-subject');
            if (subject) subject.textContent = btn.dataset.name || '';
            const confirm = modalEl.querySelector('.queue-confirm');
            if (confirm) confirm.textContent = btn.dataset.confirm || '';
            const decision = btn.dataset.decision;
            if (decision) {
                form.querySelector('input[name="decision"]').value = decision;
                const title = modalEl.querySelector('.modal-title');
                if (title) title.textContent = decision === 'approved' ? approveTitle : rejectTitle;
                const submit = modalEl.querySelector('button[type="submit"].btn-primary');
                if (submit) submit.textContent = decision === 'approved' ? approveLabel : rejectLabel;
                const reason = modalEl.querySelector('.queue-reason');
                if (reason) reason.hidden = decision !== 'rejected';
                const order = modalEl.querySelector('.queue-order');
                if (order) order.hidden = decision !== 'approved';
            }
            if (btn.dataset.personId) form.querySelector('input[name="person_id"]').value = btn.dataset.personId;
            if (btn.dataset.orderCode) form.querySelector('select[name="order_code"]').value = btn.dataset.orderCode;
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        });
    });

    document.querySelectorAll('form.queue-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const headers = { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' };
            if (csrfMeta) headers[csrfHeader] = csrfMeta.content;
            fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers,
                body: new URLSearchParams(new FormData(form)).toString(),
            }).then(function (r) {
                const newToken = r.headers.get(csrfHeader);
                if (newToken && csrfMeta) csrfMeta.content = newToken;
                return r.json().then(function (data) { return { ok: r.ok, data }; });
            }).then(function (res) {
                if (res.ok && res.data.success) {
                    window.location.reload();
                } else {
                    const msg = (res.data && res.data.message) || <?= json_encode(lang('Clergy.queueActionFailed')) ?>;
                    if (typeof showAppToast === 'function') showAppToast(msg, 'danger'); else alert(msg);
                }
            }).catch(function () {
                const msg = <?= json_encode(lang('Clergy.queueActionFailed')) ?>;
                if (typeof showAppToast === 'function') showAppToast(msg, 'danger'); else alert(msg);
            });
        });
    });
})();
</script>
<?php endif; ?>

<?= $this->endSection() ?>
