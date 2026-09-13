<?= $this->extend('Diocesan\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
// ----- helpers -----
$search          = (string) ($search ?? '');
$letter          = (string) ($letter ?? '');
$filterDioceseId = (int) ($filterDioceseId ?? 0);
$filterProvinceId = (int) ($filterProvinceId ?? 0);
$filterMailingDiffers = (bool) ($filterMailingDiffers ?? false);
$perPage         = (int) ($perPage ?? 25);
$isSystemAdmin   = (bool) ($isSystemAdmin ?? false);
$isProvinceAdmin = (bool) ($isProvinceAdmin ?? false);
$name = '';
$current_page = (int) ($current_page ?? 1);
$total_pages = (int) ($total_pages ?? 1);

$formatAddressSummary = static function (array $row, string $prefix = ''): string {
    $parts = array_filter([
        trim((string) ($row[$prefix . 'street'] ?? '')),
        trim((string) ($row[$prefix . 'city'] ?? '')),
        trim((string) ($row[$prefix . 'state'] ?? '')),
        trim((string) ($row[$prefix . 'zip'] ?? '')),
        trim((string) ($row[$prefix . 'county'] ?? '')),
        trim((string) ($row[$prefix . 'country'] ?? '')),
    ], static fn(string $value): bool => $value !== '');

    return $parts === [] ? '' : implode(', ', $parts);
};

$filtersActive = $search !== '' || $letter !== '' || $filterDioceseId > 0 || $filterProvinceId > 0 || $filterMailingDiffers;

// Build the base URL preserving per_page when clearing filters
$clearUrl = base_url('parish_list') . ($perPage !== 25 ? '?per_page=' . $perPage : '');

$chipParams = ['per_page' => $perPage];
if ($search !== '') {
    $chipParams['search'] = $search;
}
if ($letter !== '') {
    $chipParams['letter'] = $letter;
}
if ($filterDioceseId > 0) {
    $chipParams['diocese_id'] = $filterDioceseId;
}
if ($filterProvinceId > 0) {
    $chipParams['province_id'] = $filterProvinceId;
}

$mailingDiffersOnUrl = base_url('parish_list?' . http_build_query(array_merge($chipParams, ['mailing_differs' => 1])));
$mailingDiffersOffUrl = base_url('parish_list?' . http_build_query($chipParams));
?>

<div class="d-flex justify-content-start align-items-center mb-3">
    <?php if ($isProvinceAdmin || ($isDiocesanAdmin ?? false) || $isSystemAdmin): ?>
        <a href="<?= base_url('parish_list/create') ?>" class="btn btn-outline-primary me-2">
            <i class="bi bi-plus-circle"></i> <?= lang('Parishes.btn_addParish') ?>
        </a>
    <?php endif; ?>
    <a href="<?= $filterMailingDiffers ? $mailingDiffersOffUrl : $mailingDiffersOnUrl ?>"
        class="btn btn-sm <?= $filterMailingDiffers ? 'btn-primary' : 'btn-outline-secondary' ?> me-2">
        <i class="bi bi-signpost-split"></i> <?= lang('Parishes.filterChipMailingDiffers') ?>
    </a>
    <?php if (($isDiocesanAdmin ?? false)): ?>
        <button type="button" class="btn btn-outline-danger" id="bulkDeleteChurchesBtn" disabled title="<?= esc(lang('Parishes.bulkDeleteSelected')) ?>">
            <i class="bi bi-trash3"></i>
        </button>
    <?php endif; ?>
</div>

<!-- Filter panel -->
<div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-end filter-panel-header">
        <button class="btn btn-outline-secondary btn-sm" type="button"
            data-bs-toggle="collapse"
            data-bs-target="#churchFilterPanel"
            aria-expanded="<?= $filtersActive ? 'true' : 'false' ?>">
            <i class="bi bi-funnel"></i> <?= lang('App.filter') ?>
        </button>
    </div>
    <div id="churchFilterPanel" class="collapse <?= $filtersActive ? 'show' : '' ?>">
        <div class="card-body border-top">
            <form method="get" action="<?= base_url('parish_list') ?>" class="row g-2 align-items-end">
                <!-- Search -->
                <div class="col-md-4">
                    <label class="form-label form-label-sm"><?= lang('Parishes.filterSearch') ?></label>
                    <input type="text" name="search" class="form-control form-control-sm"
                        value="<?= esc($search) ?>"
                        placeholder="<?= esc(lang('Parishes.filterSearch')) ?>…">
                </div>

                <?php if ($isSystemAdmin && !empty($provincesForFilter)): ?>
                    <div class="col-md-3">
                        <label class="form-label form-label-sm"><?= lang('Parishes.filterByProvince') ?></label>
                        <select id="filterProvinceId" name="province_id" class="form-select form-select-sm">
                            <option value=""><?= lang('Parishes.filterAllProvinces') ?></option>
                            <?php foreach ($provincesForFilter as $prov): ?>
                                <option value="<?= (int) $prov['id'] ?>" <?= (int) $prov['id'] === $filterProvinceId ? 'selected' : '' ?>>
                                    <?= esc($prov['province_number'] . ' – ' . $prov['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <?php if (($isSystemAdmin || $isProvinceAdmin) && !empty($diocesesForFilter)): ?>
                    <div class="col-md-3">
                        <label class="form-label form-label-sm"><?= lang('Parishes.filterByDiocese') ?></label>
                        <select id="filterDioceseId" name="diocese_id" class="form-select form-select-sm">
                            <option value=""><?= lang('Parishes.filterAllDioceses') ?></option>
                            <?php foreach ($diocesesForFilter as $dio): ?>
                                <?php // Make sure name is a string
                                $name = is_array($dio['name']) ? ($dio['name'][0] ?? '') : $dio['name'];
                                $optionProvinceId = (int) ($dio['province_id'] ?? 0);
                                ?>
                                <option
                                    value="<?= (int) $dio['id'] ?>"
                                    data-province-id="<?= $optionProvinceId ?>"
                                    <?= (int) $dio['id'] === $filterDioceseId ? 'selected' : '' ?>>
                                    <?= esc((string) $name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <!-- Per page -->
                <div class="col-md-2">
                    <label class="form-label form-label-sm"><?= lang('Parishes.filterPerPage') ?></label>
                    <select name="per_page" class="form-select form-select-sm">
                        <?php foreach ([25, 50, 75, 100] as $opt): ?>
                            <option value="<?= $opt ?>" <?= $opt === $perPage ? 'selected' : '' ?>><?= $opt ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill">
                        <i class="bi bi-funnel"></i> <?= lang('Services.apply') ?>
                    </button>
                    <a href="<?= $clearUrl ?>" class="btn btn-sm btn-outline-secondary flex-fill"><?= lang('App.clear') ?></a>
                </div>
            </form>

            <!-- First-letter quick filter -->
            <div class="mt-2 d-flex flex-wrap gap-1">
                <?php
                $baseFilterUrl = base_url('parish_list') . '?per_page=' . $perPage;
                if ($filterDioceseId  > 0) $baseFilterUrl .= '&diocese_id=' . $filterDioceseId;
                if ($filterProvinceId > 0) $baseFilterUrl .= '&province_id=' . $filterProvinceId;
                if ($filterMailingDiffers) $baseFilterUrl .= '&mailing_differs=1';
                ?>
                <?php foreach (range('A', 'Z') as $ltr): ?>
                    <a href="<?= $baseFilterUrl . '&letter=' . $ltr ?>"
                        class="btn btn-sm <?= $letter === $ltr ? 'btn-primary' : 'btn-outline-secondary' ?>"
                        style="min-width:2rem;padding:0.15rem 0.3rem;font-size:0.75rem;"><?= $ltr ?></a>
                <?php endforeach; ?>
                <?php if ($letter !== ''): ?>
                    <a href="<?= $baseFilterUrl ?>" class="btn btn-sm btn-outline-danger" style="font-size:0.75rem;">
                        <i class="bi bi-x"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var provinceSelect = document.getElementById('filterProvinceId');
        var dioceseSelect = document.getElementById('filterDioceseId');

        if (!provinceSelect || !dioceseSelect) {
            return;
        }

        var allDioceseOptions = Array.from(dioceseSelect.options).map(function(option) {
            return {
                value: option.value,
                text: option.text,
                provinceId: option.getAttribute('data-province-id') || '',
                selected: option.selected
            };
        });

        function rebuildDioceses() {
            var selectedProvinceId = provinceSelect.value;
            var currentDioceseValue = dioceseSelect.value;

            dioceseSelect.innerHTML = '';

            allDioceseOptions.forEach(function(item) {
                var isPlaceholder = item.value === '';
                var belongsToProvince = selectedProvinceId === '' || item.provinceId === selectedProvinceId;
                if (!isPlaceholder && !belongsToProvince) {
                    return;
                }

                var option = document.createElement('option');
                option.value = item.value;
                option.text = item.text;
                if (item.provinceId !== '') {
                    option.setAttribute('data-province-id', item.provinceId);
                }

                dioceseSelect.appendChild(option);
            });

            var stillExists = Array.from(dioceseSelect.options).some(function(option) {
                return option.value === currentDioceseValue;
            });

            dioceseSelect.value = stillExists ? currentDioceseValue : '';
        }

        provinceSelect.addEventListener('change', rebuildDioceses);
        rebuildDioceses();
    });
</script>

<div class="card">
    <div class="card-body">
        <?php if (empty($churches)): ?>
            <p class="text-muted mb-0"><?= lang('Parishes.textNoChurchesFound') ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <?php if (($isDiocesanAdmin ?? false)): ?>
                                <th style="width: 40px;"><input type="checkbox" id="selectAllChurches" class="form-check-input"></th>
                            <?php endif; ?>
                            <th><?= lang('App.colName') ?></th>
                            <th><?= lang('App.colParishUeid') ?></th>
                            <th><?= lang('App.colEmail') ?></th>
                            <th><?= lang('App.colPhone') ?></th>
                            <th><?= lang('Diocesan.colMembers') ?></th>
                            <th><?= lang('App.colActions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($churches as $parish): ?>
                            <tr>
                                <?php if (($isDiocesanAdmin ?? false)): ?>
                                    <td>
                                        <input
                                            type="checkbox"
                                            class="form-check-input church-checkbox"
                                            value="<?= (int) $parish['id'] ?>"
                                            data-church-name="<?= esc((string) ($parish['name'] ?? ''), 'attr') ?>"
                                            <?= (int) $parish['id'] === $selectedChurchId ? 'disabled' : '' ?>>
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <strong><?= esc((string) ($parish['name'] ?? '')) ?></strong>
                                    <?php if ((int) $parish['id'] === $selectedChurchId): ?>
                                        <span class="badge border border-primary text-primary fw-normal ms-2"><?= lang('Parishes.textCurrent') ?></span>
                                    <?php endif; ?>
                                    <?php if ($isSystemAdmin && !empty($parish['diocese_name'])): ?>
                                        <br><small class="text-muted"><?= esc((string) ($parish['diocese_name'] ?? ''), 'attr') ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= esc((string) ($parish['parish_ueid'] ?? lang('Parishes.textNotApplicable'))) ?></td>
                                <td><?= esc((string) ($parish['email'] ?? lang('Parishes.textNotApplicable'))) ?></td>
                                <td><?= esc((string) ($parish['phone'] ?? lang('Parishes.textNotApplicable'))) ?></td>
                                <td><span class="badge border border-secondary text-secondary fw-normal"><?= (int) ($parish['member_count'] ?? 0) ?></span></td>
                                <td>
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
                                                <a class="dropdown-item" href="<?= base_url('dashboard/switch/' . $parish['id']) ?>">
                                                    <i class="bi bi-arrow-repeat me-2"></i><?= lang('Parishes.btn_switch') ?>
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="<?= base_url('parish_list/edit/' . $parish['id']) ?>">
                                                    <i class="bi bi-pencil me-2"></i><?= lang('Parishes.btn_edit') ?>
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="<?= base_url('parish_list/' . $parish['id'] . '/staff') ?>">
                                                    <i class="bi bi-people me-2"></i><?= lang('Parishes.btn_staff') ?>
                                                </a>
                                            </li>
                                            <?php if (($isDiocesanAdmin ?? false) && (int) $parish['id'] !== $selectedChurchId): ?>
                                                <li>
                                                    <hr class="dropdown-divider">
                                                </li>
                                                <li>
                                                    <button class="dropdown-item text-danger"
                                                        type="button"
                                                        onclick="deleteChurch(<?= (int) $parish['id'] ?>, '<?= esc((string) ($parish['name'] ?? ''), 'js') ?>')">
                                                        <i class="bi bi-trash3 me-2"></i><?= lang('Parishes.btn_delete') ?>
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
        <?php endif; ?>
    </div>
</div>

<!-- Pagination + per-page summary -->
<?php
$paginationBase = base_url('parish_list') . '?per_page=' . $perPage;
if ($search          !== '') $paginationBase .= '&search='     . urlencode($search);
if ($letter          !== '') $paginationBase .= '&letter='     . urlencode($letter);
if ($filterDioceseId  > 0)   $paginationBase .= '&diocese_id=' . $filterDioceseId;
if ($filterProvinceId > 0)   $paginationBase .= '&province_id=' . $filterProvinceId;
if ($filterMailingDiffers)   $paginationBase .= '&mailing_differs=1';
?>

<?php if (($total_pages ?? 1) > 1): ?>
    <nav aria-label="<?= esc(lang('Parishes.pageTitle')) ?> pagination" class="mt-3">
        <ul class="pagination justify-content-center">
            <?php if ($current_page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="<?= $paginationBase ?>&page=<?= $current_page - 1 ?>">&laquo;</a>
                </li>
            <?php endif; ?>
            <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                <li class="page-item <?= $i === $current_page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= $paginationBase ?>&page=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            <?php if ($current_page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="<?= $paginationBase ?>&page=<?= $current_page + 1 ?>">&raquo;</a>
                </li>
            <?php endif; ?>
        </ul>
        <p class="text-center text-muted small"><?= lang('Parishes.textShowingPageSummary', [(int) $current_page, (int) $total_pages, (int) $total]) ?></p>
    </nav>
<?php endif; ?>
<?php if (($isDiocesanAdmin ?? false)): ?>
    <div class="modal fade" id="deleteChurchImpactModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteChurchImpactTitle"><?= lang('Parishes.textDeleteImpactTitle') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2" id="deleteChurchImpactIntro"></p>
                    <div id="deleteChurchImpactLoading" class="text-muted small mb-2"><?= lang('Parishes.textLoadingDeleteImpact') ?></div>
                    <ul class="list-group list-group-flush" id="deleteChurchImpactList"></ul>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="deleteChurchPurgeNullableRefs">
                        <label class="form-check-label" for="deleteChurchPurgeNullableRefs">
                            <?= lang('Parishes.textDeletePurgeNullableRefsOption') ?>
                        </label>
                        <div class="small text-muted mt-1"><?= lang('Parishes.textDeletePurgeNullableRefsHelp') ?></div>
                        <div class="small text-info mt-1"><?= lang('Parishes.textDeletePurgeNullableRefsRecommended') ?></div>
                    </div>
                    <div class="alert alert-warning mt-3 mb-0 py-2">
                        <?= lang('Parishes.textDeleteImpactWarning') ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('Parishes.btn_cancel') ?></button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteChurchBtn"><?= lang('Parishes.btn_confirmDeleteChurch') ?></button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="bulkDeleteChurchesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i><?= lang('App.bulkDeleteWarningTitle') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="fw-semibold mb-2" id="bulkDeleteChurchesSummary"></p>
                    <div class="alert alert-warning py-2 mb-3"><?= lang('Parishes.textDeleteImpactWarning') ?></div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="bulkDeleteChurchesPurgeNullableRefs">
                        <label class="form-check-label" for="bulkDeleteChurchesPurgeNullableRefs"><?= lang('Parishes.textDeletePurgeNullableRefsOption') ?></label>
                        <div class="small text-muted mt-1"><?= lang('Parishes.textDeletePurgeNullableRefsHelp') ?></div>
                        <div class="small text-info mt-1"><?= lang('Parishes.textDeletePurgeNullableRefsRecommended') ?></div>
                    </div>
                    <label class="form-label" for="bulkDeleteChurchesReasonInput"><?= lang('App.bulkDeleteReasonLabel') ?></label>
                    <textarea id="bulkDeleteChurchesReasonInput" class="form-control" rows="3" maxlength="500" placeholder="<?= esc(lang('App.bulkDeleteReasonPlaceholder')) ?>" aria-describedby="bulkDeleteChurchesReasonFeedback"></textarea>
                    <div id="bulkDeleteChurchesReasonFeedback" class="invalid-feedback"></div>
                    <div class="small text-muted mt-1"><?= lang('App.bulkDeleteReasonHelp') ?></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="button" class="btn btn-danger" id="confirmBulkDeleteChurchesBtn"><?= lang('App.delete') ?></button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const deleteChurchUnableLabel = <?= json_encode(lang('Parishes.textUnableDeleteChurch')) ?>;
        const deleteChurchUnexpectedLabel = <?= json_encode(lang('Parishes.textUnexpectedError')) ?>;
        const deleteChurchImpactIntroTemplate = <?= json_encode(lang('Parishes.textDeleteImpactIntro', ['{name}'])) ?>;
        const deleteImpactLoadFailedLabel = <?= json_encode(lang('Parishes.textUnableLoadDeleteImpact')) ?>;
        const impactLabelMembers = <?= json_encode(lang('Parishes.impactMembers')) ?>;
        const impactLabelProfilesSetNull = <?= json_encode(lang('Parishes.impactProfilesSetNull')) ?>;
        const impactLabelProfilesDeleted = <?= json_encode(lang('Parishes.impactProfilesDeleted')) ?>;
        const impactLabelRoleAuditLogsSetNull = <?= json_encode(lang('Parishes.impactChurchRoleAuditLogsSetNull')) ?>;
        const impactLabelRoleAuditLogsDeleted = <?= json_encode(lang('Parishes.impactChurchRoleAuditLogsDeleted')) ?>;
        const impactLabelDonations = <?= json_encode(lang('Parishes.impactDonations')) ?>;
        const impactLabelServices = <?= json_encode(lang('Parishes.impactServices')) ?>;
        const impactLabelAttendance = <?= json_encode(lang('Parishes.impactAttendance')) ?>;
        const impactLabelEvents = <?= json_encode(lang('Parishes.impactEvents')) ?>;
        const impactLabelRegistrations = <?= json_encode(lang('Parishes.impactEventRegistrations')) ?>;
        const impactLabelGuests = <?= json_encode(lang('Parishes.impactGuests')) ?>;
        const impactLabelExpenses = <?= json_encode(lang('Parishes.impactExpenses')) ?>;
        const impactLabelFamilies = <?= json_encode(lang('Parishes.impactFamilies')) ?>;
        const impactLabelTeams = <?= json_encode(lang('Parishes.impactVolunteerTeams')) ?>;
        const bulkDeleteChurchesConfirm = <?= json_encode(lang('Parishes.bulkDeleteConfirm')) ?>;
        const bulkDeleteChurchesEmpty = <?= json_encode(lang('Diocesan.bulkDeleteSelectAtLeastOne')) ?>;
        const bulkDeleteReasonRequiredMsg = <?= json_encode(lang('App.bulkDeleteReasonRequired')) ?>;

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

        const deleteImpactModalEl = document.getElementById('deleteChurchImpactModal');
        let deleteImpactModal = null;
        const deleteImpactList = document.getElementById('deleteChurchImpactList');
        const deleteImpactIntro = document.getElementById('deleteChurchImpactIntro');
        const deleteImpactLoading = document.getElementById('deleteChurchImpactLoading');
        const confirmDeleteChurchBtn = document.getElementById('confirmDeleteChurchBtn');
        const deleteChurchPurgeNullableRefs = document.getElementById('deleteChurchPurgeNullableRefs');
        const bulkDeleteChurchesModalEl = document.getElementById('bulkDeleteChurchesModal');
        const bulkDeleteChurchesReasonInput = document.getElementById('bulkDeleteChurchesReasonInput');
        const bulkDeleteChurchesPurgeNullableRefs = document.getElementById('bulkDeleteChurchesPurgeNullableRefs');
        const bulkDeleteChurchesSummary = document.getElementById('bulkDeleteChurchesSummary');

        let pendingDeleteChurchId = null;
        let pendingDeleteChurchName = '';
        let currentDeleteImpact = null;
        let pendingBulkDeleteChurchIds = [];

        function getDeleteImpactModal() {
            if (deleteImpactModal) {
                return deleteImpactModal;
            }

            if (!deleteImpactModalEl) {
                return null;
            }

            if (typeof window.bootstrap === 'undefined' || typeof window.bootstrap.Modal !== 'function') {
                return null;
            }

            deleteImpactModal = new window.bootstrap.Modal(deleteImpactModalEl);
            return deleteImpactModal;
        }

        function getModalInstance(modalEl) {
            if (!modalEl || typeof window.bootstrap === 'undefined' || typeof window.bootstrap.Modal !== 'function') {
                return null;
            }

            return window.bootstrap.Modal.getOrCreateInstance(modalEl);
        }

        function resetDeleteImpactModal() {
            if (deleteImpactList) {
                deleteImpactList.innerHTML = '';
            }
            if (deleteImpactIntro) {
                deleteImpactIntro.textContent = '';
            }
            if (deleteImpactLoading) {
                deleteImpactLoading.style.display = 'block';
                deleteImpactLoading.textContent = <?= json_encode(lang('Parishes.textLoadingDeleteImpact')) ?>;
            }
            if (confirmDeleteChurchBtn) {
                confirmDeleteChurchBtn.disabled = true;
            }
            if (deleteChurchPurgeNullableRefs) {
                deleteChurchPurgeNullableRefs.checked = false;
            }
            currentDeleteImpact = null;
        }

        function impactItem(label, count) {
            return `<li class="list-group-item d-flex justify-content-between align-items-center"><span>${label}</span><span class="badge border border-secondary text-secondary fw-normal rounded-pill">${Number(count || 0)}</span></li>`;
        }

        function renderImpactCounts(impact) {
            if (!deleteImpactList) {
                return;
            }

            const purgeNullableRefs = !!(deleteChurchPurgeNullableRefs && deleteChurchPurgeNullableRefs.checked);
            const profileLabel = purgeNullableRefs ? impactLabelProfilesDeleted : impactLabelProfilesSetNull;
            const roleAuditLabel = purgeNullableRefs ? impactLabelRoleAuditLogsDeleted : impactLabelRoleAuditLogsSetNull;

            const html = [
                impactItem(impactLabelMembers, impact.members),
                impactItem(profileLabel, impact.profile_unscoped),
                impactItem(roleAuditLabel, impact.church_role_audit_logs_unscoped),
                impactItem(impactLabelDonations, impact.donations),
                impactItem(impactLabelServices, impact.services),
                impactItem(impactLabelAttendance, impact.attendance),
                impactItem(impactLabelEvents, impact.events),
                impactItem(impactLabelRegistrations, impact.event_registrations),
                impactItem(impactLabelGuests, impact.guests),
                impactItem(impactLabelExpenses, impact.expenses),
                impactItem(impactLabelFamilies, impact.families),
                impactItem(impactLabelTeams, impact.volunteer_teams),
            ];

            deleteImpactList.innerHTML = html.join('');
        }

        function loadDeleteImpact(churchId, churchName) {
            pendingDeleteChurchId = churchId;
            pendingDeleteChurchName = churchName;
            resetDeleteImpactModal();

            if (deleteImpactIntro) {
                deleteImpactIntro.textContent = deleteChurchImpactIntroTemplate.replace('{name}', churchName);
            }

            const modal = getDeleteImpactModal();
            if (!modal) {
                // Fallback for pages where Bootstrap modal JS is not yet available.
                deleteChurchLegacyConfirm(churchId, churchName);
                return;
            }

            modal.show();

            fetch(`<?= base_url('parish_list/delete-impact/') ?>${churchId}`, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (!data || data.success !== true || typeof data.impact !== 'object') {
                        throw new Error(contractErrorMessage(data, deleteImpactLoadFailedLabel));
                    }

                    currentDeleteImpact = data.impact;
                    renderImpactCounts(data.impact);
                    if (deleteImpactLoading) {
                        deleteImpactLoading.style.display = 'none';
                    }
                    if (confirmDeleteChurchBtn) {
                        confirmDeleteChurchBtn.disabled = false;
                    }
                })
                .catch(error => {
                    if (deleteImpactLoading) {
                        deleteImpactLoading.textContent = error.message || deleteImpactLoadFailedLabel;
                    }
                });
        }

        function deleteChurch(id, name) {
            loadDeleteImpact(id, name);
        }

        const churchSelectionStorageKey = `${window.location.pathname}:bulkDeleteChurchesSelection`;

        function loadStoredChurchSelection() {
            try {
                const raw = sessionStorage.getItem(churchSelectionStorageKey);
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

        function saveStoredChurchSelection() {
            sessionStorage.setItem(churchSelectionStorageKey, JSON.stringify(Array.from(selectedChurchIdsSet)));
        }

        function syncChurchCheckboxesFromSelection() {
            document.querySelectorAll('.church-checkbox').forEach((el) => {
                if (el.disabled) {
                    el.checked = false;
                    selectedChurchIdsSet.delete(String(el.value));
                    return;
                }

                el.checked = selectedChurchIdsSet.has(String(el.value));
            });
            saveStoredChurchSelection();
        }

        function getSelectedChurchIds() {
            return Array.from(selectedChurchIdsSet);
        }

        function updateSelectAllChurchesState() {
            if (!selectAllChurches) {
                return;
            }

            const all = Array.from(document.querySelectorAll('.church-checkbox')).filter((el) => !el.disabled);
            selectAllChurches.checked = all.length > 0 && all.every((el) => selectedChurchIdsSet.has(String(el.value)));
        }

        let selectedChurchIdsSet = loadStoredChurchSelection();

        function refreshBulkDeleteChurchesState() {
            const btn = document.getElementById('bulkDeleteChurchesBtn');
            if (!btn) {
                return;
            }

            const selected = getSelectedChurchIds();
            btn.disabled = selected.length === 0;
            btn.title = selected.length > 0 ?
                `<?= esc(lang('Parishes.bulkDeleteSelected')) ?> (${selected.length})` :
                `<?= esc(lang('Parishes.bulkDeleteSelected')) ?>`;
        }

        const selectAllChurches = document.getElementById('selectAllChurches');
        if (selectAllChurches) {
            selectAllChurches.addEventListener('change', function() {
                document.querySelectorAll('.church-checkbox').forEach((el) => {
                    if (!el.disabled) {
                        const id = String(el.value);
                        if (selectAllChurches.checked) {
                            selectedChurchIdsSet.add(id);
                            el.checked = true;
                        } else {
                            selectedChurchIdsSet.delete(id);
                            el.checked = false;
                        }
                    }
                });
                saveStoredChurchSelection();
                updateSelectAllChurchesState();
                refreshBulkDeleteChurchesState();
            });

            document.querySelectorAll('.church-checkbox').forEach((el) => {
                el.addEventListener('change', function() {
                    const id = String(el.value);
                    if (el.checked) {
                        selectedChurchIdsSet.add(id);
                    } else {
                        selectedChurchIdsSet.delete(id);
                    }

                    saveStoredChurchSelection();
                    updateSelectAllChurchesState();
                    refreshBulkDeleteChurchesState();
                });
            });

            syncChurchCheckboxesFromSelection();
            updateSelectAllChurchesState();
            refreshBulkDeleteChurchesState();
        }

        const bulkDeleteChurchesBtn = document.getElementById('bulkDeleteChurchesBtn');
        if (bulkDeleteChurchesBtn) {
            bulkDeleteChurchesBtn.addEventListener('click', function() {
                const selected = getSelectedChurchIds();
                if (selected.length === 0) {
                    showAppToast(bulkDeleteChurchesEmpty, 'warning');
                    return;
                }

                if (selected.length === 1) {
                    const oneId = String(selected[0]);
                    const oneCheckbox = document.querySelector(`.church-checkbox[value="${oneId}"]`);
                    const oneName = oneCheckbox ? (oneCheckbox.getAttribute('data-church-name') || `Church #${oneId}`) : `Church #${oneId}`;
                    loadDeleteImpact(Number(oneId), oneName);
                    return;
                }

                const modal = getModalInstance(bulkDeleteChurchesModalEl);
                if (!modal || !bulkDeleteChurchesReasonInput) {
                    showAppToast('<?= esc(lang('App.errorGeneric'), 'js') ?>', 'warning');
                    return;
                }

                pendingBulkDeleteChurchIds = selected;
                bulkDeleteChurchesReasonInput.value = '';
                if (bulkDeleteChurchesPurgeNullableRefs) {
                    bulkDeleteChurchesPurgeNullableRefs.checked = false;
                }
                if (bulkDeleteChurchesSummary) {
                    bulkDeleteChurchesSummary.textContent = `Delete ${selected.length} selected churches?`;
                }
                modal.show();
            });
        }

        const confirmBulkDeleteChurchesBtn = document.getElementById('confirmBulkDeleteChurchesBtn');

        function setReasonFieldError(input, feedbackId, message) {
            if (!input) return;
            input.classList.toggle('is-invalid', !!message);
            const feedback = document.getElementById(feedbackId);
            if (feedback) feedback.textContent = message || '';
        }

        if (confirmBulkDeleteChurchesBtn) {
            confirmBulkDeleteChurchesBtn.addEventListener('click', function() {
                const reason = (bulkDeleteChurchesReasonInput ? bulkDeleteChurchesReasonInput.value : '').trim();
                setReasonFieldError(bulkDeleteChurchesReasonInput, 'bulkDeleteChurchesReasonFeedback', '');
                if (reason.length < 8) {
                    setReasonFieldError(bulkDeleteChurchesReasonInput, 'bulkDeleteChurchesReasonFeedback', bulkDeleteReasonRequiredMsg);
                    if (bulkDeleteChurchesReasonInput) bulkDeleteChurchesReasonInput.focus();
                    return;
                }

                const body = new URLSearchParams();
                pendingBulkDeleteChurchIds.forEach((id) => body.append('church_ids[]', id));
                body.append('purge_nullable_refs', bulkDeleteChurchesPurgeNullableRefs && bulkDeleteChurchesPurgeNullableRefs.checked ? '1' : '0');
                body.append('delete_reason', reason);

                confirmBulkDeleteChurchesBtn.disabled = true;
                fetch('<?= base_url('parish_list/bulk-delete') ?>', {
                        method: 'POST',
                        body,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            '<?= csrf_header() ?>': '<?= csrf_hash() ?>'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data && data.errors && data.errors.delete_reason) {
                            setReasonFieldError(bulkDeleteChurchesReasonInput, 'bulkDeleteChurchesReasonFeedback', data.errors.delete_reason);
                            if (bulkDeleteChurchesReasonInput) bulkDeleteChurchesReasonInput.focus();
                            confirmBulkDeleteChurchesBtn.disabled = false;
                            return;
                        }
                        setReasonFieldError(bulkDeleteChurchesReasonInput, 'bulkDeleteChurchesReasonFeedback', '');
                        if (data && (data.success || Number(data.deleted || 0) > 0)) {
                            selectedChurchIdsSet.clear();
                            saveStoredChurchSelection();
                            location.reload();
                            return;
                        }
                        confirmBulkDeleteChurchesBtn.disabled = false;
                        showAppToast(contractErrorMessage(data, deleteChurchUnableLabel), 'danger');
                    })
                    .catch(() => {
                        confirmBulkDeleteChurchesBtn.disabled = false;
                        setReasonFieldError(bulkDeleteChurchesReasonInput, 'bulkDeleteChurchesReasonFeedback', '');
                        showAppToast(deleteChurchUnexpectedLabel, 'danger');
                    });
            });
        }

        if (confirmDeleteChurchBtn) {
            confirmDeleteChurchBtn.addEventListener('click', function() {
                if (!pendingDeleteChurchId) {
                    return;
                }

                confirmDeleteChurchBtn.disabled = true;
                const purgeNullableRefs = !!(deleteChurchPurgeNullableRefs && deleteChurchPurgeNullableRefs.checked);

                fetch(`<?= base_url('parish_list/delete/') ?>${pendingDeleteChurchId}`, {
                        method: 'POST',
                        body: new URLSearchParams({
                            purge_nullable_refs: purgeNullableRefs ? '1' : '0'
                        }),
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                            '<?= csrf_header() ?>': '<?= csrf_hash() ?>'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                            return;
                        }
                        confirmDeleteChurchBtn.disabled = false;
                        showAppToast(contractErrorMessage(data, deleteChurchUnableLabel), 'danger');
                    })
                    .catch(() => {
                        confirmDeleteChurchBtn.disabled = false;
                        showAppToast(deleteChurchUnexpectedLabel, 'danger');
                    });
            });
        }

        if (deleteChurchPurgeNullableRefs) {
            deleteChurchPurgeNullableRefs.addEventListener('change', function() {
                if (currentDeleteImpact) {
                    renderImpactCounts(currentDeleteImpact);
                }
            });
        }

        if (deleteImpactModalEl) {
            deleteImpactModalEl.addEventListener('hidden.bs.modal', function() {
                pendingDeleteChurchId = null;
                pendingDeleteChurchName = '';
                resetDeleteImpactModal();
            });
        }

        function deleteChurchLegacyConfirm(id, name) {
            const confirmMessage = <?= json_encode(lang('Parishes.textDeleteChurchConfirm', ['{name}'])) ?>.replace('{name}', name);
            showAppConfirm(
                '<?= esc(lang('App.confirm'), 'js') ?>',
                confirmMessage,
                function() {
                    fetch(`<?= base_url('parish_list/delete/') ?>${id}`, {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                '<?= csrf_header() ?>': '<?= csrf_hash() ?>'
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                location.reload();
                                return;
                            }
                            showAppToast(contractErrorMessage(data, deleteChurchUnableLabel), 'danger');
                        })
                        .catch(() => showAppToast(deleteChurchUnexpectedLabel, 'danger'));
                }
            );
        }
    </script>
<?php endif; ?>

<?= $this->endSection() ?>