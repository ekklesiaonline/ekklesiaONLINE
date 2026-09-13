<?= $this->extend('layouts/main') ?>

<?php
$provinces = $provinces ?? [];
$dioceses = $dioceses ?? [];
$has_audit_logs_table = $has_audit_logs_table ?? false;
$selectedProvinceId = (int) ($selectedProvinceId ?? 0);

$attendance_by_province = $attendance_by_province ?? [];

$totalAttendance = (int) array_sum(array_column($attendance_by_province, 'total_attendance'));
$auditEventCount = $has_audit_logs_table ? (int) ($audit_event_count ?? 0) : 0;
?>

<?= $this->section('content') ?>

<!-- <div class="d-flex justify-content-start align-items-center mb-3">
    <small class="text-muted\"><?= lang('System.pageSubtitle') ?></small>
</div> -->

<div class="row g-3 mb-3">
    <div class="col-md-4 col-xl-2">
        <div class="card stat-card primary h-100 dashboard-tile <?= $selectedProvinceId === 0 ? 'active' : '' ?> cursor-pointer"
            data-target="governanceProvince">
            <div class="card-body">
                <small class="text-muted d-block"><?= lang('System.totalProvinces') ?></small>
                <h4 class="mb-0"><?= esc((string) ($totals['provinces'] ?? 0)) ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-xl-2">
        <div class="card stat-card info h-100 dashboard-tile <?= $selectedProvinceId > 0 ? 'active' : '' ?> cursor-pointer" data-target="governanceDiocese">
            <div class="card-body">
                <small class="text-muted d-block"><?= lang('System.totalDioceses') ?></small>
                <h4 class="mb-0"><?= esc((string) ($totals['dioceses'] ?? 0)) ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-xl-2">
        <a href="<?= base_url('parish_list') ?>" class="text-decoration-none">
            <div class="card stat-card success h-100 cursor-pointer">
                <div class="card-body">
                    <small class="text-muted d-block"><?= lang('System.totalParishes') ?></small>
                    <h4 class="mb-0"><?= esc((string) ($totals['parishes'] ?? 0)) ?></h4>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4 col-xl-2">
        <a href="<?= base_url('system/system-admins') ?>" class="text-decoration-none">
            <div class="card stat-card dark h-100 cursor-pointer">
                <div class="card-body">
                    <small class="text-muted d-block"><?= lang('System.totalSystemAdmins') ?></small>
                    <h4 class="mb-0"><?= esc((string) ($totals['system_admins'] ?? 0)) ?></h4>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4 col-xl-2">
        <a href="<?= base_url('system/province-admins') ?>" class="text-decoration-none">
            <div class="card stat-card warning h-100 cursor-pointer">
                <div class="card-body">
                    <small class="text-muted d-block"><?= lang('System.totalProvinceAdmins') ?></small>
                    <h4 class="mb-0"><?= esc((string) ($totals['province_admins'] ?? 0)) ?></h4>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4 col-xl-2">
        <a href="<?= base_url('system/diocese-admins') ?>" class="text-decoration-none">
            <div class="card stat-card danger h-100 cursor-pointer">
                <div class="card-body">
                    <small class="text-muted d-block"><?= lang('System.totalDioceseAdmins') ?></small>
                    <h4 class="mb-0"><?= esc((string) ($totals['diocese_admins'] ?? 0)) ?></h4>
                </div>
            </div>
        </a>
    </div>
</div>

<div class="card mb-4" id="governanceProvince">
    <div class="card-header d-flex justify-content-between align-items-center ">
        <strong><?= lang('System.sectionManageProvinces') ?></strong>
        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
            data-bs-target="#addProvinceModal">
            <i class="bi bi-plus-circle me-1"></i><?= lang('System.btnCreateProvince') ?>
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th><?= lang('System.provinceNumber') ?></th>
                        <th><?= lang('System.provinceName') ?></th>
                        <th><?= lang('System.sortOrder') ?></th>
                        <th><?= lang('System.totalDioceses') ?></th>
                        <th><?= lang('System.totalParishes') ?></th>
                        <th class="text-end"><?= lang('App.colActions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($provinces as $province): ?>
                        <tr>
                            <td><?= esc((string) ($province['province_number'] ?? '')) ?></td>
                            <td><?= esc((string) ($province['name'] ?? '')) ?></td>
                            <td><?= esc((string) ($province['sort_order'] ?? 0)) ?></td>
                            <td><span
                                    class="badge border border-secondary text-secondary fw-normal"><?= esc((string) ($province['diocese_count'] ?? 0)) ?></span>
                            </td>
                            <td><span
                                    class="badge border border-secondary text-secondary fw-normal"><?= esc((string) ($province['church_count'] ?? 0)) ?></span>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-secondary me-1" type="button" data-bs-toggle="modal"
                                    data-bs-target="#editProvinceModal" data-id="<?= (int) $province['id'] ?>"
                                    data-province-number="<?= esc((string) ($province['province_number'] ?? ''), 'attr') ?>"
                                    data-name="<?= esc((string) ($province['name'] ?? ''), 'attr') ?>"
                                    data-sort-order="<?= esc((string) ($province['sort_order'] ?? 0), 'attr') ?>"
                                    title="<?= esc(lang('App.edit')) ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form action="<?= base_url('system/provinces/delete/' . (int) $province['id']) ?>"
                                    method="post" class="d-inline"
                                    data-confirm-title="<?= esc(lang('App.confirm'), 'attr') ?>"
                                    data-confirm-body="<?= esc(lang('System.confirmDeleteProvince'), 'attr') ?>">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-danger" type="submit"
                                        title="<?= esc(lang('App.delete')) ?>"><i class="bi bi-trash3"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mb-4" id="governanceDiocese">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <strong><?= lang('System.sectionManageDioceses') ?></strong>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <form method="get" action="<?= base_url('system/dashboard') ?>" class="d-flex flex-nowrap align-items-center gap-2">
                <select name="province_id" class="form-select form-select-sm" style="width: auto; min-width: 220px;" onchange="this.form.submit()">
                    <option value=""><?= lang('Parishes.filterAllProvinces') ?></option>
                    <?php foreach ($provinces as $province): ?>
                        <option value="<?= (int) $province['id'] ?>" <?= (int) $province['id'] === $selectedProvinceId ? 'selected' : '' ?>>
                            <?= esc((string) ($province['province_number'] . ' - ' . $province['name'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                data-bs-target="#addDioceseModal">
                <i class="bi bi-plus-circle me-1"></i><?= lang('System.btnCreateDiocese') ?>
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th><?= lang('System.provinceName') ?></th>
                        <th><?= lang('System.dioceseName') ?></th>
                        <th><?= lang('System.totalParishes') ?></th>
                        <th class="text-end"><?= lang('App.colActions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dioceses as $diocese): ?>
                        <tr>
                            <td><?= esc((string) ($diocese['province_number'] . ' – ' . $diocese['province_name'])) ?></td>
                            <td><?= esc((string) ($diocese['name'] ?? '')) ?></td>
                            <td><span
                                    class="badge border border-secondary text-secondary fw-normal"><?= esc((string) ($diocese['church_count'] ?? 0)) ?></span>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-secondary me-1" type="button" data-bs-toggle="modal"
                                    data-bs-target="#editDioceseModal" data-id="<?= (int) $diocese['id'] ?>"
                                    data-province-id="<?= (int) ($diocese['province_id'] ?? 0) ?>"
                                    data-name="<?= esc((string) ($diocese['name'] ?? ''), 'attr') ?>"
                                    title="<?= esc(lang('App.edit')) ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form action="<?= base_url('system/dioceses/delete/' . (int) $diocese['id']) ?>"
                                    method="post" class="d-inline"
                                    data-confirm-title="<?= esc(lang('App.confirm'), 'attr') ?>"
                                    data-confirm-body="<?= esc(lang('System.confirmDeleteDiocese'), 'attr') ?>">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-danger"
                                        type="submit"><?= lang('App.delete') ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>



<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><?= lang('App.navMissionOutcomes') ?></strong>
                <a href="<?= base_url('system/mission-outcomes') ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-graph-up-arrow me-1"></i><?= lang('System.openMissionOutcomes') ?>
                </a>
            </div>
            <div class="card-body">
                <small class="text-muted d-block"><?= lang('System.totalAttendance') ?></small>
                <h4 class="mb-0"><?= esc((string) $totalAttendance) ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><?= lang('App.navSystemOverview') ?></strong>
                <a href="<?= base_url('system/overview') ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-speedometer me-1"></i><?= lang('App.navSystemOverview') ?>
                </a>
            </div>
            <div class="card-body">
                <small class="text-muted d-block"><?= lang('System.sectionPrimaryTransferAudit') ?></small>
                <h4 class="mb-0"><?= esc((string) $auditEventCount) ?></h4>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('modals') ?>
<!-- ===== Add Province Modal ===== -->
<div class="modal fade" id="addProvinceModal" tabindex="-1" aria-labelledby="addProvinceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= base_url('system/provinces/store') ?>" method="post">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="addProvinceModalLabel"><?= lang('System.btnCreateProvince') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?= lang('System.provinceNumber') ?> *</label>
                        <input name="province_number" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= lang('System.provinceName') ?> *</label>
                        <input name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= lang('System.sortOrder') ?></label>
                        <input name="sort_order" type="number" min="0" class="form-control" value="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary"
                        data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="submit" class="btn btn-primary"><?= lang('System.btnCreateProvince') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== Edit Province Modal ===== -->
<div class="modal fade" id="editProvinceModal" tabindex="-1" aria-labelledby="editProvinceModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="editProvinceForm" action="" method="post">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="editProvinceModalLabel"><?= lang('App.edit') ?> –
                        <?= lang('System.sectionManageProvinces') ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?= lang('System.provinceNumber') ?> *</label>
                        <input name="province_number" id="editProvinceNumber" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= lang('System.provinceName') ?> *</label>
                        <input name="name" id="editProvinceName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= lang('System.sortOrder') ?></label>
                        <input name="sort_order" id="editProvinceSortOrder" type="number" min="0" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary"
                        data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="submit" class="btn btn-primary"><?= lang('App.save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== Add Diocese Modal ===== -->
<div class="modal fade" id="addDioceseModal" tabindex="-1" aria-labelledby="addDioceseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= base_url('system/dioceses/store') ?>" method="post">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="addDioceseModalLabel"><?= lang('System.btnCreateDiocese') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?= lang('System.provinceName') ?> *</label>
                        <select name="province_id" class="form-select" required>
                            <option value=""><?= lang('System.selectProvince') ?></option>
                            <?php foreach ($provinces as $province): ?>
                                <option value="<?= (int) $province['id'] ?>">
                                    <?= esc((string) ($province['province_number'] . ' – ' . $province['name'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= lang('System.dioceseName') ?> *</label>
                        <input name="name" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary"
                        data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="submit" class="btn btn-primary"><?= lang('System.btnCreateDiocese') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== Edit Diocese Modal ===== -->
<div class="modal fade" id="editDioceseModal" tabindex="-1" aria-labelledby="editDioceseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="editDioceseForm" action="" method="post">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="editDioceseModalLabel"><?= lang('App.edit') ?> –
                        <?= lang('System.sectionManageDioceses') ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?= lang('System.provinceName') ?> *</label>
                        <select name="province_id" id="editDioceseProvinceId" class="form-select" required>
                            <option value=""><?= lang('System.selectProvince') ?></option>
                            <?php foreach ($provinces as $province): ?>
                                <option value="<?= (int) $province['id'] ?>">
                                    <?= esc((string) ($province['province_number'] . ' – ' . $province['name'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= lang('System.dioceseName') ?> *</label>
                        <input name="name" id="editDioceseName" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary"
                        data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="submit" class="btn btn-primary"><?= lang('App.save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.getElementById('editProvinceModal').addEventListener('show.bs.modal', function (e) {
        const btn = e.relatedTarget;
        document.getElementById('editProvinceForm').action = '<?= base_url('system/provinces/update/') ?>' + btn.dataset.id;
        document.getElementById('editProvinceNumber').value = btn.dataset.provinceNumber;
        document.getElementById('editProvinceName').value = btn.dataset.name;
        document.getElementById('editProvinceSortOrder').value = btn.dataset.sortOrder;
    });

    document.getElementById('editDioceseModal').addEventListener('show.bs.modal', function (e) {
        const btn = e.relatedTarget;
        document.getElementById('editDioceseForm').action = '<?= base_url('system/dioceses/update/') ?>' + btn.dataset.id;
        document.getElementById('editDioceseName').value = btn.dataset.name;
        const sel = document.getElementById('editDioceseProvinceId');
        if (sel) {
            sel.value = btn.dataset.provinceId;
        }
    });

    // Dashboard tiles tab functionality
    document.querySelectorAll('.dashboard-tile').forEach(tile => {
        tile.addEventListener('click', function () {
            document.querySelectorAll('.dashboard-tile').forEach(t => t.classList.remove('active'));
            this.classList.add('active');

            const sections = [
                'governanceProvince',
                'governanceDiocese'
            ];

            sections.forEach(sectionId => {
                const section = document.getElementById(sectionId);
                if (section) {
                    section.style.display = 'none';
                }
            });

            const targetId = this.dataset.target;
            const targetSection = document.getElementById(targetId);
            if (targetSection) {
                targetSection.style.display = 'block';
            }
        });
    });

    // Show the active tile's section on page load
    document.addEventListener('DOMContentLoaded', function () {
        const sections = [
            'governanceProvince',
            'governanceDiocese'
        ];

        const activeTile = document.querySelector('.dashboard-tile.active');
        const visibleSection = activeTile ? activeTile.dataset.target : 'governanceProvince';

        sections.forEach(sectionId => {
            const section = document.getElementById(sectionId);
            if (section) {
                section.style.display = sectionId === visibleSection ? 'block' : 'none';
            }
        });
    });
</script>

<style>
    .dashboard-tile {
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .dashboard-tile:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    }

    .dashboard-tile.active {
        border: 2px solid var(--bs-primary);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.2);
        transform: translateY(-2px);
    }

    .dashboard-tile.active .card-body {
        background-color: rgba(13, 110, 253, 0.05);
    }
</style>
<?= $this->endSection() ?>