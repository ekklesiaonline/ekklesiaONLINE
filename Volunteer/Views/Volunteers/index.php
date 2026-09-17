<?= $this->extend('Volunteer\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
$request = service('request');
$selectedPerPage = (int) ($per_page ?? (int) ($request->getGet('per_page') ?? 12));
if (!in_array($selectedPerPage, [25, 50, 100], true)) {
    $selectedPerPage = 25;
}
?>

<div class="d-flex justify-content-start align-items-center mb-3">
    <?php if (auth()->user()->can('volunteer.manage')): ?>
        <a href="<?= base_url('volunteers/createTeam') ?>" class="btn btn-outline-primary">
            <i class="bi bi-plus-circle" aria-hidden="true"></i> <?= lang('Volunteer.createTeam') ?>
        </a>
    <?php endif; ?>
</div>

<div class="d-flex justify-content-end mb-3">
    <form method="get" action="<?= base_url('volunteers') ?>" class="d-flex align-items-center gap-2">
        <label for="teamsPerPage" class="form-label mb-0 small text-muted"><?= lang('App.perPage') ?>:</label>
        <select id="teamsPerPage" name="per_page" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="25" <?= $selectedPerPage === 25 ? 'selected' : '' ?>>25</option>
            <option value="50" <?= $selectedPerPage === 50 ? 'selected' : '' ?>>50</option>
            <option value="100" <?= $selectedPerPage === 100 ? 'selected' : '' ?>>100</option>
        </select>
    </form>
</div>

<div class="row">
    <?php if (!empty($teams)): ?>
        <?php foreach ($teams as $team): ?>
            <div class="col-md-4 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><?= esc($team['name']) ?></h5>
                        <p class="text-muted mb-2">
                            <span class="badge border border-primary text-primary fw-normal"><?= lang('Volunteer.teamLabel') ?></span>
                        </p>
                        <?php if ($team['description']): ?>
                            <p class="card-text small"><?= esc(substr($team['description'], 0, 100)) ?><?= strlen($team['description']) > 100 ? '...' : '' ?></p>
                        <?php endif; ?>
                        <hr>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small">
                                <i class="bi bi-people" aria-hidden="true"></i> <?= lang('Volunteer.membersCount', [(int) ($team['member_count'] ?? 0)]) ?>
                            </span>
                            <a href="<?= base_url('volunteers/viewTeam/' . $team['id']) ?>" class="btn btn-sm btn-outline-primary">
                                <?= lang('Volunteer.viewTeam') ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center text-muted py-5">
                    <i class="bi bi-people display-1" aria-hidden="true"></i>
                    <p class="mt-3"><?= lang('Volunteer.noTeamsYet') ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
$currentPage = max(1, (int) ($current_page ?? 1));
$totalPages = max(1, (int) ($total_pages ?? 1));
?>
<?php if ($totalPages > 1): ?>
    <nav class="mt-3" aria-label="<?= esc(lang('App.pagination')) ?>">
        <ul class="pagination pagination-sm mb-0">
            <?php
            $prevDisabled = $currentPage <= 1;
            $nextDisabled = $currentPage >= $totalPages;
            $startPage = max(1, $currentPage - 2);
            $endPage = min($totalPages, $currentPage + 2);
            ?>
            <li class="page-item <?= $prevDisabled ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $prevDisabled ? '#' : base_url('volunteers?' . http_build_query(['per_page' => $selectedPerPage, 'page' => $currentPage - 1])) ?>">&laquo;</a>
            </li>
            <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                    <a class="page-link" href="<?= base_url('volunteers?' . http_build_query(['per_page' => $selectedPerPage, 'page' => $p])) ?>"><?= $p ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= $nextDisabled ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $nextDisabled ? '#' : base_url('volunteers?' . http_build_query(['per_page' => $selectedPerPage, 'page' => $currentPage + 1])) ?>">&raquo;</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>

<?= $this->endSection() ?>