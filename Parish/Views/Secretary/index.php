<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="row g-4">
    <!-- Recent vestry minutes -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-journal-text"></i> Recent Vestry Minutes</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($recent_minutes)): ?>
                    <div class="list-group">
                        <?php foreach ($recent_minutes as $minutes): ?>
                            <a href="<?= base_url('vestry/view/' . (int) ($minutes['id'] ?? 0)) ?>" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <small class="text-muted"><?= esc($minutes['meeting_date'] ?? '-') ?></small>
                                    <span class="badge bg-secondary"><?= lang('Dashboard.vestryMeetingType' . ucfirst($minutes['meeting_type'] ?? 'regular')) ?></span>
                                </div>
                                <p class="small mb-0 text-truncate"><?= esc(substr($minutes['minutes_text'] ?? '', 0, 100)) ?>...</p>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0">No vestry minutes available.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent reports -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> Recent Reports</h5>
                <?php if (!empty($can_export)): ?>
                    <a href="<?= base_url('reports/export') ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-download"></i> Export
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!empty($recent_reports)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead><tr><th>Year</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach ($recent_reports as $report): ?>
                                    <tr>
                                        <td><?= esc($report['report_year'] ?? '-') ?></td>
                                        <td><?= esc($report['status'] ?? '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0">No reports available.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
