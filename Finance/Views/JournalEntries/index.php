<?= $this->extend('Finance\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
/**
 * Variables:
 * - $entries: array of journal entries
 * - $summary: array with total_entries, total_debits, total_credits
 * - $filters: array of active filters
 * - $filtersActive: bool
 * - $chartAccounts: array of active CoA accounts
 * - $page: int
 * - $perPage: int
 * - $totalRows: int
 * - $totalPages: int
 * - $canManage: bool
 */
$sourceColorMap = [
    'donation' => 'success',
    'expense'  => 'danger',
    'bank'     => 'info',
    'manual'   => 'secondary',
];
$statusColorMap = [
    'posted' => 'success',
    'draft'  => 'warning',
    'void'   => 'danger',
];
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex gap-2">
        <a href="<?= base_url('parish/finance') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Finance.backToFinanceDashboard') ?>
        </a>
        <a href="<?= base_url('reports/trial-balance') ?>" class="btn btn-outline-info">
            <i class="bi bi-scale" aria-hidden="true"></i> <?= lang('Finance.trialBalanceTitle') ?>
        </a>
    </div>
    <div>
        <a href="<?= base_url('journal-entries/export?' . http_build_query($filters)) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-download" aria-hidden="true"></i> <?= lang('Finance.exportCsv') ?>
        </a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small"><?= lang('Finance.colTotalEntries') ?></div>
                <div class="h4 mb-0"><?= number_format((int) ($summary['total_entries'] ?? 0)) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small"><?= lang('Finance.colTotalDebits') ?></div>
                <div class="h4 mb-0 text-primary">$<?= number_format((float) ($summary['total_debits'] ?? 0.0), 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small"><?= lang('Finance.colTotalCredits') ?></div>
                <div class="h4 mb-0 text-primary">$<?= number_format((float) ($summary['total_credits'] ?? 0.0), 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small"><?= lang('Finance.trialBalanceStatus') ?></div>
                <?php
                $isBalanced = abs(((float) ($summary['total_debits'] ?? 0.0)) - ((float) ($summary['total_credits'] ?? 0.0))) <= 0.005;
                $statusColor = $isBalanced ? 'success' : 'danger';
                ?>
                <div class="h5 mb-0 text-<?= $statusColor ?>">
                    <i class="bi bi-<?= $isBalanced ? 'check-circle' : 'exclamation-triangle' ?> me-1"></i>
                    <?= $isBalanced ? lang('Finance.trialBalanceBalanced') : lang('Finance.trialBalanceUnbalanced') ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-between filter-panel-header">
        <span class="fw-semibold text-muted small text-uppercase"><?= lang('Finance.journalEntriesTitle') ?></span>
        <button class="btn btn-outline-secondary btn-sm filter-toggle-btn" type="button" data-bs-toggle="collapse" data-bs-target="#jeFilterPanel" aria-expanded="<?= $filtersActive ? 'true' : 'false' ?>" aria-controls="jeFilterPanel">
            <i class="bi bi-funnel" aria-hidden="true"></i> <?= lang('App.filter') ?>
        </button>
    </div>
    <div id="jeFilterPanel" class="collapse <?= $filtersActive ? 'show' : '' ?>">
        <div class="card-body border-top filter-panel-body">
            <form method="get" action="<?= base_url('journal-entries') ?>" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label for="startDateFilter" class="form-label small"><?= lang('Finance.startDate') ?></label>
                    <input type="date" id="startDateFilter" name="start_date" class="form-control form-control-sm" value="<?= esc($filters['start_date'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label for="endDateFilter" class="form-label small"><?= lang('Finance.endDate') ?></label>
                    <input type="date" id="endDateFilter" name="end_date" class="form-control form-control-sm" value="<?= esc($filters['end_date'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label for="sourceTypeFilter" class="form-label small"><?= lang('Finance.colSource') ?></label>
                    <select id="sourceTypeFilter" name="source_type" class="form-select form-select-sm">
                        <option value=""><?= lang('Finance.allSources') ?></option>
                        <option value="donation" <?= ($filters['source_type'] ?? '') === 'donation' ? 'selected' : '' ?>><?= lang('Finance.sourceDonation') ?></option>
                        <option value="expense" <?= ($filters['source_type'] ?? '') === 'expense' ? 'selected' : '' ?>><?= lang('Finance.sourceExpense') ?></option>
                        <option value="bank" <?= ($filters['source_type'] ?? '') === 'bank' ? 'selected' : '' ?>><?= lang('Finance.sourceBank') ?></option>
                        <option value="manual" <?= ($filters['source_type'] ?? '') === 'manual' ? 'selected' : '' ?>><?= lang('Finance.sourceManual') ?></option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="statusFilter" class="form-label small"><?= lang('Finance.colStatus') ?></label>
                    <select id="statusFilter" name="status" class="form-select form-select-sm">
                        <option value=""><?= lang('Finance.allStatuses') ?></option>
                        <option value="posted" <?= ($filters['status'] ?? '') === 'posted' ? 'selected' : '' ?>>Posted</option>
                        <option value="draft" <?= ($filters['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="void" <?= ($filters['status'] ?? '') === 'void' ? 'selected' : '' ?>>Void</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="chartAccountFilter" class="form-label small"><?= lang('Finance.colAccount') ?></label>
                    <select id="chartAccountFilter" name="chart_account_id" class="form-select form-select-sm">
                        <option value=""><?= lang('Finance.allAccounts') ?></option>
                        <?php foreach ($chartAccounts as $acc): ?>
                            <option value="<?= (int) $acc['id'] ?>" <?= ((int) ($filters['chart_account_id'] ?? 0)) === (int) $acc['id'] ? 'selected' : '' ?>>
                                <?= esc($acc['code'] . ' - ' . $acc['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="searchFilter" class="form-label small"><?= lang('App.search') ?></label>
                    <input type="text" id="searchFilter" name="search" class="form-control form-control-sm" placeholder="<?= esc(lang('Finance.searchEntryPlaceholder')) ?>" value="<?= esc($filters['search'] ?? '') ?>">
                </div>
                <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                    <a href="<?= base_url('journal-entries') ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x-circle" aria-hidden="true"></i> <?= lang('App.clear') ?>
                    </a>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-funnel" aria-hidden="true"></i> <?= lang('Services.apply') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col" style="width: 14%;"><?= lang('Finance.colEntryNumber') ?></th>
                        <th scope="col" style="width: 11%;"><?= lang('Finance.colEntryDate') ?></th>
                        <th scope="col" style="width: 11%;"><?= lang('Finance.colSource') ?></th>
                        <th scope="col" style="width: 26%;"><?= lang('Finance.colMemo') ?></th>
                        <th scope="col" class="text-center" style="width: 8%;"><?= lang('Finance.colLineCount') ?></th>
                        <th scope="col" class="text-end" style="width: 11%;"><?= lang('Finance.colDebit') ?></th>
                        <th scope="col" class="text-end" style="width: 11%;"><?= lang('Finance.colCredit') ?></th>
                        <th scope="col" style="width: 8%;"><?= lang('Finance.colStatus') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($entries)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="bi bi-journal-x fs-3 d-block mb-2 text-secondary"></i>
                                <?= lang('Finance.noJournalEntriesFound') ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($entries as $entry): ?>
                            <?php
                            $source = (string) ($entry['source_type'] ?? 'manual');
                            $sColor = $sourceColorMap[$source] ?? 'secondary';
                            $status = (string) ($entry['status'] ?? 'draft');
                            $stColor = $statusColorMap[$status] ?? 'secondary';
                            ?>
                            <tr role="button" class="cursor-pointer js-view-entry" data-id="<?= (int) $entry['id'] ?>" title="<?= esc(lang('Finance.viewEntryLines')) ?>">
                                <td>
                                    <a href="<?= base_url('journal-entries/' . (int) $entry['id']) ?>" class="fw-semibold text-decoration-none">
                                        <i class="bi bi-journal-text me-1 text-muted"></i><?= esc($entry['entry_number']) ?>
                                    </a>
                                </td>
                                <td><?= esc($entry['entry_date']) ?></td>
                                <td>
                                    <span class="badge border border-<?= $sColor ?> text-<?= $sColor ?> fw-normal">
                                        <?= esc(ucfirst($source)) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="text-truncate" style="max-width: 320px;" title="<?= esc($entry['memo'] ?? '') ?>">
                                        <?= esc($entry['memo'] ?: '—') ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge border border-secondary text-secondary fw-normal">
                                        <?= (int) ($entry['line_count'] ?? 0) ?>
                                    </span>
                                </td>
                                <td class="text-end font-monospace">$<?= number_format((float) ($entry['total_debit'] ?? 0), 2) ?></td>
                                <td class="text-end font-monospace">$<?= number_format((float) ($entry['total_credit'] ?? 0), 2) ?></td>
                                <td>
                                    <span class="badge border border-<?= $stColor ?> text-<?= $stColor ?> fw-normal">
                                        <?= esc(ucfirst($status)) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($totalPages > 1): ?>
        <div class="card-footer d-flex justify-content-between align-items-center py-2">
            <span class="text-muted small">
                <?= lang('Finance.showingRecords', [($page - 1) * $perPage + 1, min($page * $perPage, $totalRows), $totalRows]) ?>
            </span>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= base_url('journal-entries?' . http_build_query(array_merge($filters, ['page' => $page - 1]))) ?>">
                        &laquo;
                    </a>
                </li>
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= base_url('journal-entries?' . http_build_query(array_merge($filters, ['page' => $p]))) ?>">
                            <?= $p ?>
                        </a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= base_url('journal-entries?' . http_build_query(array_merge($filters, ['page' => $page + 1]))) ?>">
                        &raquo;
                    </a>
                </li>
            </ul>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Drilldown for Journal Entry Lines -->
<div class="modal fade" id="entryDetailModal" tabindex="-1" aria-labelledby="entryDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header">
                <h5 class="modal-title" id="entryDetailModalLabel">
                    <i class="bi bi-journal-text me-2 text-primary"></i>
                    <span id="modalEntryNumber"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div id="modalLoading" class="text-center py-4">
                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                    <span class="ms-2 text-muted small"><?= lang('App.loading') ?></span>
                </div>
                <div id="modalContent" class="d-none">
                    <div class="p-3 bg-body-tertiary border-bottom d-flex flex-wrap gap-4 small text-muted">
                        <div><strong><?= lang('Finance.colEntryDate') ?>:</strong> <span id="modalEntryDate"></span></div>
                        <div><strong><?= lang('Finance.colSource') ?>:</strong> <span id="modalEntrySource"></span></div>
                        <div><strong><?= lang('Finance.colStatus') ?>:</strong> <span id="modalEntryStatus"></span></div>
                        <div><strong><?= lang('Finance.colMemo') ?>:</strong> <span id="modalEntryMemo"></span></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col" style="width: 20%;"><?= lang('Finance.colAccountCode') ?></th>
                                    <th scope="col" style="width: 40%;"><?= lang('Finance.colAccountName') ?></th>
                                    <th scope="col" class="text-end" style="width: 20%;"><?= lang('Finance.colDebit') ?></th>
                                    <th scope="col" class="text-end" style="width: 20%;"><?= lang('Finance.colCredit') ?></th>
                                </tr>
                            </thead>
                            <tbody id="modalLinesBody"></tbody>
                            <tfoot>
                                <tr class="fw-bold border-top">
                                    <td colspan="2"><?= lang('Finance.reportTotal') ?></td>
                                    <td class="text-end text-primary font-monospace" id="modalTotalDebit"></td>
                                    <td class="text-end text-primary font-monospace" id="modalTotalCredit"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a id="modalFullPageLink" href="#" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-box-arrow-up-right me-1"></i><?= lang('Finance.fullPageView') ?>
                </a>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal"><?= lang('App.close') ?></button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('entryDetailModal');
    if (!modalEl) return;
    const bsModal = new bootstrap.Modal(modalEl);

    document.querySelectorAll('.js-view-entry').forEach(function (row) {
        row.addEventListener('click', function (e) {
            if (e.target.closest('a')) return;
            const entryId = this.dataset.id;
            openEntryModal(entryId);
        });
    });

    function openEntryModal(entryId) {
        document.getElementById('modalLoading').classList.remove('d-none');
        document.getElementById('modalContent').classList.add('d-none');
        bsModal.show();

        fetch('<?= base_url('journal-entries') ?>/' + entryId + '?format=json', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(res => {
            if (!res.success || !res.data || !res.data.entry) {
                alert('Failed to load entry details.');
                bsModal.hide();
                return;
            }
            const entry = res.data.entry;
            document.getElementById('modalEntryNumber').textContent = entry.entry_number;
            document.getElementById('modalEntryDate').textContent = entry.entry_date;
            document.getElementById('modalEntrySource').textContent = (entry.source_type || 'manual').toUpperCase();
            document.getElementById('modalEntryStatus').textContent = (entry.status || 'draft').toUpperCase();
            document.getElementById('modalEntryMemo').textContent = entry.memo || '—';
            document.getElementById('modalTotalDebit').textContent = '$' + Number(entry.total_debit || 0).toFixed(2);
            document.getElementById('modalTotalCredit').textContent = '$' + Number(entry.total_credit || 0).toFixed(2);
            document.getElementById('modalFullPageLink').href = '<?= base_url('journal-entries') ?>/' + entry.id;

            const tbody = document.getElementById('modalLinesBody');
            tbody.innerHTML = '';
            (entry.lines || []).forEach(line => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="font-monospace">${line.account_code || '—'}</td>
                    <td>${line.account_name || '—'} ${line.memo ? '<div class="text-muted small">' + line.memo + '</div>' : ''}</td>
                    <td class="text-end font-monospace">${Number(line.debit_amount || 0) > 0 ? '$' + Number(line.debit_amount).toFixed(2) : '—'}</td>
                    <td class="text-end font-monospace">${Number(line.credit_amount || 0) > 0 ? '$' + Number(line.credit_amount).toFixed(2) : '—'}</td>
                `;
                tbody.appendChild(tr);
            });

            document.getElementById('modalLoading').classList.add('d-none');
            document.getElementById('modalContent').classList.remove('d-none');
        })
        .catch(() => {
            alert('Error loading journal entry.');
            bsModal.hide();
        });
    }
});
</script>

<?= $this->endSection() ?>
