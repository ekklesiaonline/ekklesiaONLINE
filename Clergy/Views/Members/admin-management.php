<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
$csrfHeader = csrf_header();
$csrfHash   = csrf_hash();
$request    = service('request');
$canManageProvinceStaff = (bool) ($canManageProvinceStaff ?? false);
$dioceses_in_province = $dioceses_in_province ?? [];
$staff_roles = $staff_roles ?? [];
?>

<!-- =========================================================
     SECTION 1: PROVINCE STAFF  (full CRUD — provinceadmin)
     ========================================================= -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0"><i class="bi bi-building me-2" aria-hidden="true"></i><?= lang('Members.provinceAdministrators') ?></h5>
            <small class="text-muted"><?= lang('Members.provinceAdminsManagedDesc') ?></small>
        </div>
        <?php if ($canManageProvinceStaff): ?>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createProvinceStaffModal">
                <i class="bi bi-plus-circle me-1" aria-hidden="true"></i><?= lang('Members.addProvinceAdmin') ?>
            </button>
        <?php else: ?>
            <span class="badge border border-secondary text-secondary fw-normal"><?= lang('Diocesan.readOnlyProvinceScope') ?></span>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if (!empty($province_staff)): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="provinceStaffTable">
                    <thead>
                        <tr>
                            <th scope="col"><?= lang('App.colName') ?></th>
                            <th scope="col"><?= lang('App.colEmail') ?></th>
                            <th scope="col"><?= lang('App.colPhone') ?></th>
                            <?php if ($canManageProvinceStaff): ?>
                                <th scope="col" class="text-end"><?= lang('App.colActions') ?></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($province_staff as $staff): ?>
                            <?php $isSelf = ((int) $staff['user_id'] === (int) ($profile['user_id'] ?? 0)); ?>
                            <tr id="province-row-<?= (int) $staff['profile_id'] ?>">
                                <td>
                                    <strong><?= esc(trim($staff['first_name'] . ' ' . $staff['last_name'])) ?></strong>
                                    <?php if ($isSelf): ?>
                                        <span class="badge border border-secondary text-secondary fw-normal ms-1"><?= lang('Members.youLabel') ?></span>
                                    <?php endif; ?>
                                    <?php if ((int) ($staff['profile_pending_completion'] ?? 0) === 1): ?>
                                        <span class="badge border border-warning text-warning fw-normal ms-1"><?= lang('Members.profilePendingBadge') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= esc((string) ($staff['email'] ?? '—')) ?></td>
                                <td><?= esc((string) ($staff['phone'] ?? '—')) ?></td>
                                <?php if ($canManageProvinceStaff): ?>
                                    <td class="text-end">
                                        <div class="d-inline-flex flex-nowrap gap-1 align-items-center">
                                            <button class="btn btn-sm btn-outline-primary"
                                                onclick="openEditProvinceStaff(<?= (int) $staff['profile_id'] ?>)"
                                                title="<?= lang('App.edit') ?>">
                                                <i class="bi bi-pencil" aria-hidden="true"></i>
                                            </button>
                                            <?php if (!$isSelf): ?>
                                                <button class="btn btn-sm btn-outline-danger"
                                                    onclick="confirmDeleteProvinceStaff(<?= (int) $staff['profile_id'] ?>, '<?= esc(addslashes(trim((string) ($staff['first_name'] ?? '') . ' ' . (string) ($staff['last_name'] ?? '')))) ?>')"
                                                    title="<?= lang('App.delete') ?>">
                                                    <i class="bi bi-trash" aria-hidden="true"></i>
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
        <?php else: ?>
            <div class="p-4 text-center text-muted">
                <i class="bi bi-person-x fs-3 d-block mb-2" aria-hidden="true"></i>
                <?= lang('Members.noProvinceAdminsFound') ?>
                <?php if ($canManageProvinceStaff): ?>
                    <a href="#" data-bs-toggle="modal" data-bs-target="#createProvinceStaffModal" class="ms-1"><?= lang('Members.addFirstOne') ?></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- =========================================================
     SECTION 2: DIOCESAN STAFF  (read-only — diocesanadmin, parishadmin, clergy)
     ========================================================= -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2 filter-panel-header">
        <div>
            <h5 class="mb-0"><i class="bi bi-diagram-3 me-2" aria-hidden="true"></i><?= lang('Members.diocesanStaffTitle') ?></h5>
            <small class="text-muted"><?= lang('Members.diocesanStaffReadonlyDesc') ?></small>
        </div>
        <button
            class="btn btn-outline-secondary btn-sm filter-toggle-btn"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#diocesanStaffFilterPanel"
            aria-expanded="<?= $request->getGet('diocese_scope_id') !== null ? 'true' : 'false' ?>"
            aria-controls="diocesanStaffFilterPanel">
            <i class="bi bi-funnel" aria-hidden="true"></i> <?= lang('App.filter') ?>
        </button>
    </div>
    <div id="diocesanStaffFilterPanel" class="collapse <?= $request->getGet('diocese_scope_id') !== null ? 'show' : '' ?>">
        <div class="card-body border-bottom filter-panel-body">
            <form method="get" action="<?= base_url('members') ?>" class="d-flex align-items-center gap-2 flex-wrap">
                <label class="form-label mb-0 text-nowrap fw-semibold small"><?= lang('Members.filterByDiocese') ?></label>
                <select name="diocese_scope_id" class="form-select form-select-sm" style="min-width:220px;" onchange="this.form.submit()">
                    <option value="">— <?= lang('Members.allDiocesesOption') ?> —</option>
                    <?php foreach ($dioceses_in_province as $dioRow): ?>
                        <option value="<?= (int) $dioRow['id'] ?>"
                            <?= ((int) ($scope_diocese_id ?? 0) === (int) $dioRow['id']) ? 'selected' : '' ?>>
                            <?= esc((string) $dioRow['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (!empty($diocesan_staff)): ?>
            <div class="alert alert-info m-3 mb-2 py-2 small">
                <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                <?= lang('Members.provinceAdminsCannotManageDiocesanStaff') ?>
                <?= !empty($scope_diocese_id) ? lang('Members.showingSelectedDioceseOnly') : lang('Members.showingAllDiocesanStaffInProvince') ?>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="diocesanStaffTable">
                    <thead>
                        <tr>
                            <th scope="col"><?= lang('App.colName') ?></th>
                            <th scope="col"><?= lang('App.colEmail') ?></th>
                            <th scope="col"><?= lang('App.colPhone') ?></th>
                            <th scope="col"><?= lang('Members.role') ?></th>
                            <th scope="col"><?= lang('Dashboard.colDiocese') ?></th>
                            <th scope="col"><?= lang('Dashboard.parishLabel') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($diocesan_staff as $staff): ?>
                            <tr>
                                <td>
                                    <strong><?= esc(trim((string) ($staff['first_name'] ?? '') . ' ' . (string) ($staff['last_name'] ?? ''))) ?></strong>
                                    <?php if ((int) ($staff['profile_pending_completion'] ?? 0) === 1): ?>
                                        <span class="badge border border-warning text-warning fw-normal ms-1"><?= lang('Members.profilePendingBadge') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= esc((string) ($staff['email'] ?? '—')) ?></td>
                                <td><?= esc((string) ($staff['phone'] ?? '—')) ?></td>
                                <td>
                                    <?php
                                    $roleBadge = match ($staff['role'] ?? '') {
                                        'diocesanadmin' => 'primary',
                                        'parishadmin'   => 'warning',
                                        'clergy'        => 'success',
                                        default         => 'secondary',
                                    };
                                    ?>
                                    <span class="badge border border-<?= $roleBadge ?> text-<?= $roleBadge ?> fw-normal">
                                        <?= esc($staff_roles[$staff['role']] ?? ucfirst(str_replace('_', ' ', $staff['role'] ?? ''))) ?>
                                    </span>
                                </td>
                                <td><?= esc((string) ($staff['diocese_name'] ?? '—')) ?></td>
                                <td><?= esc((string) ($staff['parish_name'] ?? $staff['parish_name'] ?? lang('Members.unassigned'))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="p-4 text-center text-muted">
                <i class="bi bi-diagram-3 fs-3 d-block mb-2" aria-hidden="true"></i>
                <?= lang('Members.noDiocesanStaffFound') ?>
                <?= !empty($scope_diocese_id) ? lang('Members.forSelectedDiocese') : lang('Members.withinYourProvince') ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($canManageProvinceStaff): ?>
    <!-- =========================================================
    MODALS
    ========================================================= -->

    <!-- Create Province Staff Modal -->
    <div class="modal fade" id="createProvinceStaffModal" tabindex="-1" aria-labelledby="createProvinceStaffLabel">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createProvinceStaffLabel">
                        <i class="bi bi-person-plus me-2" aria-hidden="true"></i><?= lang('Members.addProvinceAdministrator') ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="createProvinceStaffForm">
                    <div class="modal-body">
                        <div id="createErrors" class="alert alert-danger d-none"></div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label"><?= lang('Members.firstName') ?> <span class="text-danger">*</span></label>
                                <input type="text" name="first_name" class="form-control" required minlength="2">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?= lang('Members.lastName') ?> <span class="text-danger">*</span></label>
                                <input type="text" name="last_name" class="form-control" required minlength="2">
                            </div>
                            <div class="col-12">
                                <label class="form-label"><?= lang('Members.colEmail') ?> <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label"><?= lang('Members.password') ?> <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control" required minlength="8">
                                <div class="form-text"><?= lang('App.minimumCharsHint') ?>.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label"><?= lang('Members.colPhone') ?></label>
                                <input type="text" name="phone" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                        <button type="submit" class="btn btn-primary" id="createProvinceStaffBtn">
                            <span class="spinner-border spinner-border-sm d-none me-1" id="createSpinner"></span>
                            <?= lang('Members.createAdmin') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Province Staff Modal -->
    <div class="modal fade" id="editProvinceStaffModal" tabindex="-1" aria-labelledby="editProvinceStaffLabel">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editProvinceStaffLabel">
                        <i class="bi bi-pencil me-2" aria-hidden="true"></i><?= lang('Members.editProvinceAdministrator') ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editProvinceStaffForm">
                    <input type="hidden" id="editProfileId">
                    <div class="modal-body">
                        <div id="editErrors" class="alert alert-danger d-none"></div>
                        <div class="mb-2 text-muted small">
                            <i class="bi bi-envelope me-1" aria-hidden="true"></i><span id="editEmailDisplay"></span>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label"><?= lang('Members.firstName') ?> <span class="text-danger">*</span></label>
                                <input type="text" name="first_name" id="editFirstName" class="form-control" required minlength="2">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?= lang('Members.lastName') ?> <span class="text-danger">*</span></label>
                                <input type="text" name="last_name" id="editLastName" class="form-control" required minlength="2">
                            </div>
                            <div class="col-12">
                                <label class="form-label"><?= lang('Members.colPhone') ?></label>
                                <input type="text" name="phone" id="editPhone" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                        <button type="submit" class="btn btn-primary" id="editProvinceStaffBtn">
                            <span class="spinner-border spinner-border-sm d-none me-1" id="editSpinner"></span>
                            <?= lang('App.save') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteProvinceStaffModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-trash me-2" aria-hidden="true"></i><?= lang('App.confirm') ?> <?= lang('App.delete') ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><?= lang('App.delete') ?> <strong id="deleteStaffName"></strong>?</p>
                    <p class="text-muted small"><?= lang('Members.provinceAdminDeleteWarning') ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="button" class="btn btn-danger btn-sm" id="confirmDeleteBtn">
                        <span class="spinner-border spinner-border-sm d-none me-1" id="deleteSpinner"></span>
                        <?= lang('App.delete') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const CSRF_HEADER = '<?= $csrfHeader ?>';
        let csrfHash = '<?= $csrfHash ?>';
        const staffCreateFailedMsg = <?= json_encode(lang('App.errorFailedCreate')) ?>;
        const profileLoadFailedMsg = <?= json_encode(lang('App.errorNotFound')) ?>;
        const staffUpdateFailedMsg = <?= json_encode(lang('App.errorFailedUpdate')) ?>;
        const deleteFailedMsg = <?= json_encode(lang('App.errorFailedDelete')) ?>;
        const requestFailedMsg = <?= json_encode(lang('App.httpRequestFailedRetry')) ?>;
        const errorPrefixMsg = <?= json_encode(lang('App.errorPrefix')) ?>;
        const deleteFallbackConfirmTemplate = <?= json_encode(lang('App.delete') . ' {name}?') ?>;

        function buildHeaders(json = false) {
            const h = {
                [CSRF_HEADER]: csrfHash,
                'X-Requested-With': 'XMLHttpRequest'
            };
            if (json) h['Content-Type'] = 'application/json';
            return h;
        }

        function refreshCsrf(json) {
            if (json && json.csrf_hash) csrfHash = json.csrf_hash;
        }

        function showErrors(containerId, errors) {
            const el = document.getElementById(containerId);
            const msgs = typeof errors === 'object' ?
                Object.values(errors).join('<br>') :
                (errors || 'An error occurred.');
            el.innerHTML = msgs;
            el.classList.remove('d-none');
        }

        function contractErrorMessage(json, fallback) {
            if (!json || typeof json !== 'object') {
                return fallback;
            }

            const code = json.error && typeof json.error.code === 'string' ? json.error.code : '';
            const message =
                (json.error && typeof json.error.message === 'string' && json.error.message) ||
                (typeof json.message === 'string' && json.message) ||
                fallback;

            return code ? `${message} (${code})` : message;
        }

        function hideErrors(containerId) {
            const el = document.getElementById(containerId);
            el.innerHTML = '';
            el.classList.add('d-none');
        }

        function getLazyModal(modalId) {
            const modalEl = document.getElementById(modalId);
            if (!modalEl || typeof window.bootstrap === 'undefined' || typeof window.bootstrap.Modal !== 'function') {
                return null;
            }

            return window.bootstrap.Modal.getOrCreateInstance(modalEl);
        }

        // ---- CREATE ----
        document.getElementById('createProvinceStaffForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            hideErrors('createErrors');
            const btn = document.getElementById('createProvinceStaffBtn');
            const spinner = document.getElementById('createSpinner');
            btn.disabled = true;
            spinner.classList.remove('d-none');

            const fd = new FormData(this);
            try {
                const res = await fetch('<?= base_url('members/province-staff/store') ?>', {
                    method: 'POST',
                    headers: buildHeaders(),
                    body: fd,
                });
                const json = await res.json();
                refreshCsrf(json);
                if (json.success) {
                    location.reload();
                } else {
                    showErrors('createErrors', json.errors || contractErrorMessage(json, staffCreateFailedMsg));
                }
            } catch (err) {
                showErrors('createErrors', requestFailedMsg + ': ' + err.message);
            } finally {
                btn.disabled = false;
                spinner.classList.add('d-none');
            }
        });

        // ---- EDIT (open modal & pre-populate) ----
        async function openEditProvinceStaff(profileId) {
            hideErrors('editErrors');
            document.getElementById('editProfileId').value = profileId;
            document.getElementById('editFirstName').value = '';
            document.getElementById('editLastName').value = '';
            document.getElementById('editPhone').value = '';
            document.getElementById('editEmailDisplay').textContent = '';

            try {
                const res = await fetch(`<?= base_url('members/province-staff/') ?>${profileId}`, {
                    headers: buildHeaders(),
                });
                const json = await res.json();
                refreshCsrf(json);
                if (json.success && json.profile) {
                    const p = json.profile;
                    document.getElementById('editFirstName').value = p.first_name || '';
                    document.getElementById('editLastName').value = p.last_name || '';
                    document.getElementById('editPhone').value = p.phone || '';
                    document.getElementById('editEmailDisplay').textContent = p.email || '';
                } else {
                    showErrors('editErrors', contractErrorMessage(json, profileLoadFailedMsg));
                }
            } catch (err) {
                showErrors('editErrors', requestFailedMsg + ': ' + err.message);
            }

            new bootstrap.Modal(document.getElementById('editProvinceStaffModal')).show();
        }

        // ---- EDIT (submit) ----
        document.getElementById('editProvinceStaffForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            hideErrors('editErrors');
            const btn = document.getElementById('editProvinceStaffBtn');
            const spinner = document.getElementById('editSpinner');
            btn.disabled = true;
            spinner.classList.remove('d-none');

            const profileId = document.getElementById('editProfileId').value;
            const fd = new FormData(this);
            try {
                const res = await fetch(`<?= base_url('members/province-staff/update/') ?>${profileId}`, {
                    method: 'POST',
                    headers: buildHeaders(),
                    body: fd,
                });
                const json = await res.json();
                refreshCsrf(json);
                if (json.success) {
                    location.reload();
                } else {
                    showErrors('editErrors', json.errors || contractErrorMessage(json, staffUpdateFailedMsg));
                }
            } catch (err) {
                showErrors('editErrors', requestFailedMsg + ': ' + err.message);
            } finally {
                btn.disabled = false;
                spinner.classList.add('d-none');
            }
        });

        // ---- DELETE ----
        let pendingDeleteProfileId = null;

        async function executeDeleteProvinceStaff() {
            if (!pendingDeleteProfileId) {
                return;
            }

            const btn = document.getElementById('confirmDeleteBtn');
            const spinner = document.getElementById('deleteSpinner');
            btn.disabled = true;
            spinner.classList.remove('d-none');

            try {
                const res = await fetch(`<?= base_url('members/province-staff/delete/') ?>${pendingDeleteProfileId}`, {
                    method: 'POST',
                    headers: buildHeaders(),
                });
                const json = await res.json();
                refreshCsrf(json);
                if (json.success) {
                    const row = document.getElementById('province-row-' + pendingDeleteProfileId);
                    if (row) row.remove();

                    const deleteModal = getLazyModal('deleteProvinceStaffModal');
                    if (deleteModal) {
                        deleteModal.hide();
                    }

                    pendingDeleteProfileId = null;
                } else {
                    showAppToast(errorPrefixMsg + ': ' + contractErrorMessage(json, deleteFailedMsg), 'danger');
                }
            } catch (err) {
                showAppToast(requestFailedMsg + ': ' + err.message, 'danger');
            } finally {
                btn.disabled = false;
                spinner.classList.add('d-none');
            }
        }

        function confirmDeleteProvinceStaff(profileId, name) {
            pendingDeleteProfileId = profileId;
            document.getElementById('deleteStaffName').textContent = name;

            const deleteModal = getLazyModal('deleteProvinceStaffModal');
            if (!deleteModal) {
                const fallbackConfirm = deleteFallbackConfirmTemplate.replace('{name}', name);
                showAppConfirm(
                    '<?= esc(lang('App.confirm'), 'js') ?>',
                    fallbackConfirm,
                    function() {
                        executeDeleteProvinceStaff();
                    },
                    '<?= esc(lang('App.confirm'), 'js') ?>'
                );
                return;
            }

            deleteModal.show();
        }

        document.getElementById('confirmDeleteBtn').addEventListener('click', async function() {
            await executeDeleteProvinceStaff();
        });
    </script>
<?php endif; ?>

<?= $this->endSection() ?>