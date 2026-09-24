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
            <i class="bi bi-plus-circle" aria-hidden="true"></i> <?= lang('Parishes.btn_addParish') ?>
        </a>
    <?php endif; ?>
    <a href="<?= $filterMailingDiffers ? $mailingDiffersOffUrl : $mailingDiffersOnUrl ?>"
        class="btn btn-sm <?= $filterMailingDiffers ? 'btn-primary' : 'btn-outline-secondary' ?> me-2">
        <i class="bi bi-signpost-split" aria-hidden="true"></i> <?= lang('Parishes.filterChipMailingDiffers') ?>
    </a>
    <?php if (($isDiocesanAdmin ?? false)): ?>
        <button type="button" class="btn btn-outline-danger" id="bulkDeleteParishesBtn" disabled title="<?= esc(lang('Parishes.bulkDeleteSelected')) ?>">
            <i class="bi bi-trash3" aria-hidden="true"></i>
        </button>
    <?php endif; ?>
</div>

<!-- Filter panel -->
<div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-end filter-panel-header">
        <button class="btn btn-outline-secondary btn-sm" type="button"
            data-bs-toggle="collapse"
            data-bs-target="#parishFilterPanel"
            aria-expanded="<?= $filtersActive ? 'true' : 'false' ?>">
            <i class="bi bi-funnel" aria-hidden="true"></i> <?= lang('App.filter') ?>
        </button>
    </div>
    <div id="parishFilterPanel" class="collapse <?= $filtersActive ? 'show' : '' ?>">
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
                        <i class="bi bi-funnel" aria-hidden="true"></i> <?= lang('Services.apply') ?>
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
                    <a href="<?= $baseFilterUrl ?>" class="btn btn-sm btn-outline-danger" style="font-size:0.75rem;"
                        aria-label="<?= esc(lang('App.clear')) ?>">
                        <i class="bi bi-x" aria-hidden="true"></i>
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
        <?php if (empty($parishes)): ?>
            <p class="text-muted mb-0"><?= lang('Parishes.textNoParishesFound') ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <?php if (($isDiocesanAdmin ?? false)): ?>
                                <th scope="col" style="width: 40px;"><input type="checkbox" id="selectAllParishes" class="form-check-input"></th>
                            <?php endif; ?>
                            <th scope="col"><?= lang('App.colName') ?></th>
                            <th scope="col"><?= lang('App.colParishUeid') ?></th>
                            <th scope="col"><?= lang('App.colEmail') ?></th>
                            <th scope="col"><?= lang('App.colPhone') ?></th>
                            <th scope="col"><?= lang('Diocesan.colMembers') ?></th>
                            <th scope="col"><?= lang('App.colActions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($parishes as $parish): ?>
                            <tr>
                                <?php if (($isDiocesanAdmin ?? false)): ?>
                                    <td>
                                        <input
                                            type="checkbox"
                                            class="form-check-input parish-checkbox"
                                            value="<?= (int) $parish['id'] ?>"
                                            data-parish-name="<?= esc((string) ($parish['name'] ?? ''), 'attr') ?>"
                                            <?= (int) $parish['id'] === $selectedParishId ? 'disabled' : '' ?>>
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <strong><?= esc((string) ($parish['name'] ?? '')) ?></strong>
                                    <?php if ((int) $parish['id'] === $selectedParishId): ?>
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
                                            <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <li>
                                                <a class="dropdown-item" href="<?= base_url('dashboard/switch/' . $parish['id']) ?>">
                                                    <i class="bi bi-arrow-repeat me-2" aria-hidden="true"></i><?= lang('Parishes.btn_switch') ?>
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="<?= base_url('parish_list/edit/' . $parish['id']) ?>">
                                                    <i class="bi bi-pencil me-2" aria-hidden="true"></i><?= lang('Parishes.btn_edit') ?>
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="<?= base_url('parish_list/' . $parish['id'] . '/staff') ?>">
                                                    <i class="bi bi-people me-2" aria-hidden="true"></i><?= lang('Parishes.btn_staff') ?>
                                                </a>
                                            </li>
                                            <?php if (($isDiocesanAdmin ?? false) && (int) $parish['id'] !== $selectedParishId): ?>
                                                <li>
                                                    <hr class="dropdown-divider">
                                                </li>
                                                <li>
                                                    <button class="dropdown-item text-danger"
                                                        type="button"
                                                        onclick="deleteParish(<?= (int) $parish['id'] ?>, '<?= esc((string) ($parish['name'] ?? ''), 'js') ?>')">
                                                        <i class="bi bi-trash3 me-2" aria-hidden="true"></i><?= lang('Parishes.btn_delete') ?>
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
    <nav aria-label="<?= esc(lang('App.pagination')) ?>" class="mt-3">
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
    <div class="modal fade" id="deleteParishImpactModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteParishImpactTitle"><?= lang('Parishes.textDeleteImpactTitle') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= esc(lang('App.close')) ?>"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2" id="deleteParishImpactIntro"></p>
                    <div id="deleteParishImpactLoading" class="text-muted small mb-2"><?= lang('Parishes.textLoadingDeleteImpact') ?></div>
                    <ul class="list-group list-group-flush" id="deleteParishImpactList"></ul>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="deleteParishPurgeNullableRefs">
                        <label class="form-check-label" for="deleteParishPurgeNullableRefs">
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
                    <button type="button" class="btn btn-danger" id="confirmDeleteParishBtn"><?= lang('Parishes.btn_confirmDeleteParish') ?></button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="bulkDeleteParishesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill text-warning me-2" aria-hidden="true"></i><?= lang('App.bulkDeleteWarningTitle') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="fw-semibold mb-2" id="bulkDeleteParishesSummary"></p>
                    <div class="alert alert-warning py-2 mb-3"><?= lang('Parishes.textDeleteImpactWarning') ?></div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="bulkDeleteParishesPurgeNullableRefs">
                        <label class="form-check-label" for="bulkDeleteParishesPurgeNullableRefs"><?= lang('Parishes.textDeletePurgeNullableRefsOption') ?></label>
                        <div class="small text-muted mt-1"><?= lang('Parishes.textDeletePurgeNullableRefsHelp') ?></div>
                        <div class="small text-info mt-1"><?= lang('Parishes.textDeletePurgeNullableRefsRecommended') ?></div>
                    </div>
                    <label class="form-label" for="bulkDeleteParishesReasonInput"><?= lang('App.bulkDeleteReasonLabel') ?></label>
                    <textarea id="bulkDeleteParishesReasonInput" class="form-control" rows="3" maxlength="500" placeholder="<?= esc(lang('App.bulkDeleteReasonPlaceholder')) ?>" aria-describedby="bulkDeleteParishesReasonFeedback"></textarea>
                    <div id="bulkDeleteParishesReasonFeedback" class="invalid-feedback"></div>
                    <div class="small text-muted mt-1"><?= lang('App.bulkDeleteReasonHelp') ?></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="button" class="btn btn-danger" id="confirmBulkDeleteParishesBtn"><?= lang('App.delete') ?></button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const deleteParishUnableLabel = <?= json_encode(lang('Parishes.textUnableDeleteParish')) ?>;
        const deleteParishUnexpectedLabel = <?= json_encode(lang('Parishes.textUnexpectedError')) ?>;
        const deleteParishImpactIntroTemplate = <?= json_encode(lang('Parishes.textDeleteImpactIntro', ['{name}'])) ?>;
        const deleteImpactLoadFailedLabel = <?= json_encode(lang('Parishes.textUnableLoadDeleteImpact')) ?>;
        const impactLabelMembers = <?= json_encode(lang('Parishes.impactMembers')) ?>;
        const impactLabelProfilesSetNull = <?= json_encode(lang('Parishes.impactProfilesSetNull')) ?>;
        const impactLabelProfilesDeleted = <?= json_encode(lang('Parishes.impactProfilesDeleted')) ?>;
        const impactLabelRoleAuditLogsSetNull = <?= json_encode(lang('Parishes.impactParishRoleAuditLogsSetNull')) ?>;
        const impactLabelRoleAuditLogsDeleted = <?= json_encode(lang('Parishes.impactParishRoleAuditLogsDeleted')) ?>;
        const impactLabelDonations = <?= json_encode(lang('Parishes.impactDonations')) ?>;
        const impactLabelServices = <?= json_encode(lang('Parishes.impactServices')) ?>;
        const impactLabelAttendance = <?= json_encode(lang('Parishes.impactAttendance')) ?>;
        const impactLabelEvents = <?= json_encode(lang('Parishes.impactEvents')) ?>;
        const impactLabelRegistrations = <?= json_encode(lang('Parishes.impactEventRegistrations')) ?>;
        const impactLabelVisitors = <?= json_encode(lang('Parishes.impactVisitors')) ?>;
        const impactLabelExpenses = <?= json_encode(lang('Parishes.impactExpenses')) ?>;
        const impactLabelFamilies = <?= json_encode(lang('Parishes.impactFamilies')) ?>;
        const impactLabelTeams = <?= json_encode(lang('Parishes.impactVolunteerTeams')) ?>;
        const bulkDeleteParishesConfirm = <?= json_encode(lang('Parishes.bulkDeleteConfirm')) ?>;
        const bulkDeleteParishesEmpty = <?= json_encode(lang('Diocesan.bulkDeleteSelectAtLeastOne')) ?>;
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

        const deleteImpactModalEl = document.getElementById('deleteParishImpactModal');
        let deleteImpactModal = null;
        const deleteImpactList = document.getElementById('deleteParishImpactList');
        const deleteImpactIntro = document.getElementById('deleteParishImpactIntro');
        const deleteImpactLoading = document.getElementById('deleteParishImpactLoading');
        const confirmDeleteParishBtn = document.getElementById('confirmDeleteParishBtn');
        const deleteParishPurgeNullableRefs = document.getElementById('deleteParishPurgeNullableRefs');
        const bulkDeleteParishesModalEl = document.getElementById('bulkDeleteParishesModal');
        const bulkDeleteParishesReasonInput = document.getElementById('bulkDeleteParishesReasonInput');
        const bulkDeleteParishesPurgeNullableRefs = document.getElementById('bulkDeleteParishesPurgeNullableRefs');
        const bulkDeleteParishesSummary = document.getElementById('bulkDeleteParishesSummary');

        let pendingDeleteParishId = null;
        let pendingDeleteParishName = '';
        let currentDeleteImpact = null;
        let pendingBulkDeleteParishIds = [];

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
            if (confirmDeleteParishBtn) {
                confirmDeleteParishBtn.disabled = true;
            }
            if (deleteParishPurgeNullableRefs) {
                deleteParishPurgeNullableRefs.checked = false;
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

            const purgeNullableRefs = !!(deleteParishPurgeNullableRefs && deleteParishPurgeNullableRefs.checked);
            const profileLabel = purgeNullableRefs ? impactLabelProfilesDeleted : impactLabelProfilesSetNull;
            const roleAuditLabel = purgeNullableRefs ? impactLabelRoleAuditLogsDeleted : impactLabelRoleAuditLogsSetNull;

            const html = [
                impactItem(impactLabelMembers, impact.members),
                impactItem(profileLabel, impact.profile_unscoped),
                impactItem(roleAuditLabel, impact.parish_role_audit_logs_unscoped),
                impactItem(impactLabelDonations, impact.donations),
                impactItem(impactLabelServices, impact.services),
                impactItem(impactLabelAttendance, impact.attendance),
                impactItem(impactLabelEvents, impact.events),
                impactItem(impactLabelRegistrations, impact.event_registrations),
                impactItem(impactLabelVisitors, impact.visitors),
                impactItem(impactLabelExpenses, impact.expenses),
                impactItem(impactLabelFamilies, impact.families),
                impactItem(impactLabelTeams, impact.volunteer_teams),
            ];

            deleteImpactList.innerHTML = html.join('');
        }

        function loadDeleteImpact(parishId, parishName) {
            pendingDeleteParishId = parishId;
            pendingDeleteParishName = parishName;
            resetDeleteImpactModal();

            if (deleteImpactIntro) {
                deleteImpactIntro.textContent = deleteParishImpactIntroTemplate.replace('{name}', parishName);
            }

            const modal = getDeleteImpactModal();
            if (!modal) {
                // Fallback for pages where Bootstrap modal JS is not yet available.
                deleteParishLegacyConfirm(parishId, parishName);
                return;
            }

            modal.show();

            fetch(`<?= base_url('parish_list/delete-impact/') ?>${parishId}`, {
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
                    if (confirmDeleteParishBtn) {
                        confirmDeleteParishBtn.disabled = false;
                    }
                })
                .catch(error => {
                    if (deleteImpactLoading) {
                        deleteImpactLoading.textContent = error.message || deleteImpactLoadFailedLabel;
                    }
                });
        }

        function deleteParish(id, name) {
            loadDeleteImpact(id, name);
        }

        const parishSelectionStorageKey = `${window.location.pathname}:bulkDeleteParishesSelection`;

        function loadStoredParishSelection() {
            try {
                const raw = sessionStorage.getItem(parishSelectionStorageKey);
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

        function saveStoredParishSelection() {
            sessionStorage.setItem(parishSelectionStorageKey, JSON.stringify(Array.from(selectedParishIdsSet)));
        }

        function syncParishCheckboxesFromSelection() {
            document.querySelectorAll('.parish-checkbox').forEach((el) => {
                if (el.disabled) {
                    el.checked = false;
                    selectedParishIdsSet.delete(String(el.value));
                    return;
                }

                el.checked = selectedParishIdsSet.has(String(el.value));
            });
            saveStoredParishSelection();
        }

        function getSelectedParishIds() {
            return Array.from(selectedParishIdsSet);
        }

        function updateSelectAllParishesState() {
            if (!selectAllParishes) {
                return;
            }

            const all = Array.from(document.querySelectorAll('.parish-checkbox')).filter((el) => !el.disabled);
            selectAllParishes.checked = all.length > 0 && all.every((el) => selectedParishIdsSet.has(String(el.value)));
        }

        let selectedParishIdsSet = loadStoredParishSelection();

        function refreshBulkDeleteParishesState() {
            const btn = document.getElementById('bulkDeleteParishesBtn');
            if (!btn) {
                return;
            }

            const selected = getSelectedParishIds();
            btn.disabled = selected.length === 0;
            btn.title = selected.length > 0 ?
                `<?= esc(lang('Parishes.bulkDeleteSelected')) ?> (${selected.length})` :
                `<?= esc(lang('Parishes.bulkDeleteSelected')) ?>`;
        }

        const selectAllParishes = document.getElementById('selectAllParishes');
        if (selectAllParishes) {
            selectAllParishes.addEventListener('change', function() {
                document.querySelectorAll('.parish-checkbox').forEach((el) => {
                    if (!el.disabled) {
                        const id = String(el.value);
                        if (selectAllParishes.checked) {
                            selectedParishIdsSet.add(id);
                            el.checked = true;
                        } else {
                            selectedParishIdsSet.delete(id);
                            el.checked = false;
                        }
                    }
                });
                saveStoredParishSelection();
                updateSelectAllParishesState();
                refreshBulkDeleteParishesState();
            });

            document.querySelectorAll('.parish-checkbox').forEach((el) => {
                el.addEventListener('change', function() {
                    const id = String(el.value);
                    if (el.checked) {
                        selectedParishIdsSet.add(id);
                    } else {
                        selectedParishIdsSet.delete(id);
                    }

                    saveStoredParishSelection();
                    updateSelectAllParishesState();
                    refreshBulkDeleteParishesState();
                });
            });

            syncParishCheckboxesFromSelection();
            updateSelectAllParishesState();
            refreshBulkDeleteParishesState();
        }

        const bulkDeleteParishesBtn = document.getElementById('bulkDeleteParishesBtn');
        if (bulkDeleteParishesBtn) {
            bulkDeleteParishesBtn.addEventListener('click', function() {
                const selected = getSelectedParishIds();
                if (selected.length === 0) {
                    showAppToast(bulkDeleteParishesEmpty, 'warning');
                    return;
                }

                if (selected.length === 1) {
                    const oneId = String(selected[0]);
                    const oneCheckbox = document.querySelector(`.parish-checkbox[value="${oneId}"]`);
                    const oneName = oneCheckbox ? (oneCheckbox.getAttribute('data-parish-name') || `Parish #${oneId}`) : `Parish #${oneId}`;
                    loadDeleteImpact(Number(oneId), oneName);
                    return;
                }

                const modal = getModalInstance(bulkDeleteParishesModalEl);
                if (!modal || !bulkDeleteParishesReasonInput) {
                    showAppToast('<?= esc(lang('App.errorGeneric'), 'js') ?>', 'warning');
                    return;
                }

                pendingBulkDeleteParishIds = selected;
                bulkDeleteParishesReasonInput.value = '';
                if (bulkDeleteParishesPurgeNullableRefs) {
                    bulkDeleteParishesPurgeNullableRefs.checked = false;
                }
                if (bulkDeleteParishesSummary) {
                    bulkDeleteParishesSummary.textContent = `Delete ${selected.length} selected parishes?`;
                }
                modal.show();
            });
        }

        const confirmBulkDeleteParishesBtn = document.getElementById('confirmBulkDeleteParishesBtn');

        function setReasonFieldError(input, feedbackId, message) {
            if (!input) return;
            input.classList.toggle('is-invalid', !!message);
            const feedback = document.getElementById(feedbackId);
            if (feedback) feedback.textContent = message || '';
        }

        if (confirmBulkDeleteParishesBtn) {
            confirmBulkDeleteParishesBtn.addEventListener('click', function() {
                const reason = (bulkDeleteParishesReasonInput ? bulkDeleteParishesReasonInput.value : '').trim();
                setReasonFieldError(bulkDeleteParishesReasonInput, 'bulkDeleteParishesReasonFeedback', '');
                if (reason.length < 8) {
                    setReasonFieldError(bulkDeleteParishesReasonInput, 'bulkDeleteParishesReasonFeedback', bulkDeleteReasonRequiredMsg);
                    if (bulkDeleteParishesReasonInput) bulkDeleteParishesReasonInput.focus();
                    return;
                }

                const body = new URLSearchParams();
                pendingBulkDeleteParishIds.forEach((id) => body.append('parish_ids[]', id));
                body.append('purge_nullable_refs', bulkDeleteParishesPurgeNullableRefs && bulkDeleteParishesPurgeNullableRefs.checked ? '1' : '0');
                body.append('delete_reason', reason);

                confirmBulkDeleteParishesBtn.disabled = true;
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
                            setReasonFieldError(bulkDeleteParishesReasonInput, 'bulkDeleteParishesReasonFeedback', data.errors.delete_reason);
                            if (bulkDeleteParishesReasonInput) bulkDeleteParishesReasonInput.focus();
                            confirmBulkDeleteParishesBtn.disabled = false;
                            return;
                        }
                        setReasonFieldError(bulkDeleteParishesReasonInput, 'bulkDeleteParishesReasonFeedback', '');
                        if (data && (data.success || Number(data.deleted || 0) > 0)) {
                            selectedParishIdsSet.clear();
                            saveStoredParishSelection();
                            location.reload();
                            return;
                        }
                        confirmBulkDeleteParishesBtn.disabled = false;
                        showAppToast(contractErrorMessage(data, deleteParishUnableLabel), 'danger');
                    })
                    .catch(() => {
                        confirmBulkDeleteParishesBtn.disabled = false;
                        setReasonFieldError(bulkDeleteParishesReasonInput, 'bulkDeleteParishesReasonFeedback', '');
                        showAppToast(deleteParishUnexpectedLabel, 'danger');
                    });
            });
        }

        if (confirmDeleteParishBtn) {
            confirmDeleteParishBtn.addEventListener('click', function() {
                if (!pendingDeleteParishId) {
                    return;
                }

                confirmDeleteParishBtn.disabled = true;
                const purgeNullableRefs = !!(deleteParishPurgeNullableRefs && deleteParishPurgeNullableRefs.checked);

                fetch(`<?= base_url('parish_list/delete/') ?>${pendingDeleteParishId}`, {
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
                        confirmDeleteParishBtn.disabled = false;
                        showAppToast(contractErrorMessage(data, deleteParishUnableLabel), 'danger');
                    })
                    .catch(() => {
                        confirmDeleteParishBtn.disabled = false;
                        showAppToast(deleteParishUnexpectedLabel, 'danger');
                    });
            });
        }

        if (deleteParishPurgeNullableRefs) {
            deleteParishPurgeNullableRefs.addEventListener('change', function() {
                if (currentDeleteImpact) {
                    renderImpactCounts(currentDeleteImpact);
                }
            });
        }

        if (deleteImpactModalEl) {
            deleteImpactModalEl.addEventListener('hidden.bs.modal', function() {
                pendingDeleteParishId = null;
                pendingDeleteParishName = '';
                resetDeleteImpactModal();
            });
        }

        function deleteParishLegacyConfirm(id, name) {
            const confirmMessage = <?= json_encode(lang('Parishes.textDeleteParishConfirm', ['{name}'])) ?>.replace('{name}', name);
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
                            showAppToast(contractErrorMessage(data, deleteParishUnableLabel), 'danger');
                        })
                        .catch(() => showAppToast(deleteParishUnexpectedLabel, 'danger'));
                }
            );
        }
    </script>
<?php endif; ?>

<?= $this->endSection() ?>