<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= base_url('leadership') ?>" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label for="scope" class="form-label"><?= lang('Leadership.scope') ?></label>
                <select class="form-select" id="scope" name="scope">
                    <option value="parish" <?= ($selected_scope ?? 'parish') === 'parish' ? 'selected' : '' ?>><?= lang('App.scopeParish') ?></option>
                    <option value="diocese" <?= ($selected_scope ?? '') === 'diocese' ? 'selected' : '' ?>><?= lang('App.scopeDiocese') ?></option>
                    <option value="province" <?= ($selected_scope ?? '') === 'province' ? 'selected' : '' ?>><?= lang('App.scopeProvince') ?></option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="per_page" class="form-label"><?= lang('Leadership.perPage') ?></label>
                <select class="form-select" id="per_page" name="per_page">
                    <?php foreach ([10, 25, 50, 100] as $size): ?>
                        <option value="<?= $size ?>" <?= (int) ($per_page ?? 25) === $size ? 'selected' : '' ?>><?= $size ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5 text-md-end">
                <button type="submit" class="btn btn-outline-primary"><i class="bi bi-funnel" aria-hidden="true"></i> <?= lang('Leadership.applyScope') ?></button>
            </div>
        </form>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-person-badge" aria-hidden="true"></i> <?= lang('Leadership.addLeadershipPosition') ?></h5>
        <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#addLeadershipPositionPanel" aria-expanded="false" aria-controls="addLeadershipPositionPanel">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> <?= lang('App.add') ?>
        </button>
    </div>
    <div id="addLeadershipPositionPanel" class="collapse">
    <div class="card-body">
        <form method="post" action="<?= base_url('leadership/store') ?>" class="row g-3">
            <?= csrf_field() ?>
            <div class="col-md-4">
                <label class="form-label"><?= lang('Leadership.member') ?></label>
                <?= view('partials/_member_select_ajax', [
                    'select_id'      => 'leadershipMemberSelect',
                    'name'           => 'user_id',
                    'required'       => true,
                    'mark_no_portal' => true,
                    'placeholder'    => lang('Leadership.selectMember'),
                ]) ?>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= lang('Leadership.scope') ?></label>
                <select name="scope_type" class="form-select" required>
                    <option value="parish"><?= lang('App.scopeParish') ?></option>
                    <option value="diocese"><?= lang('App.scopeDiocese') ?></option>
                    <option value="province"><?= lang('App.scopeProvince') ?></option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= lang('Leadership.title') ?></label>
                <input type="text" name="title" class="form-control" required placeholder="<?= lang('Leadership.titlePlaceholder') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= lang('Leadership.roleCode') ?></label>
                <input type="text" name="role_code" class="form-control" placeholder="<?= lang('Leadership.roleCodePlaceholder') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= lang('Leadership.roleCategory') ?></label>
                <select name="role_category" class="form-select">
                    <option value=""><?= lang('Leadership.roleCategorySelectPrompt') ?></option>
                    <option value="clergy"><?= lang('Leadership.roleCategoryClergy') ?></option>
                    <option value="lay_staff"><?= lang('Leadership.roleCategoryLayStaff') ?></option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= lang('Leadership.clergyOrder') ?></label>
                <select name="clergy_order" class="form-select">
                    <option value=""><?= lang('Leadership.clergyOrderSelectPrompt') ?></option>
                    <option value="bishop"><?= lang('Leadership.clergyOrderBishop') ?></option>
                    <option value="priest"><?= lang('Leadership.clergyOrderPriest') ?></option>
                    <option value="deacon"><?= lang('Leadership.clergyOrderDeacon') ?></option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= lang('Leadership.employmentType') ?></label>
                <select name="employment_type" class="form-select">
                    <option value=""><?= lang('Leadership.employmentSelectPrompt') ?></option>
                    <option value="full_time"><?= lang('Leadership.employmentFullTime') ?></option>
                    <option value="part_time"><?= lang('Leadership.employmentPartTime') ?></option>
                    <option value="non_stipendiary"><?= lang('Leadership.employmentNonStipendiary') ?></option>
                    <option value="unpaid"><?= lang('Leadership.employmentUnpaid') ?></option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= lang('Leadership.startDate') ?></label>
                <input type="date" name="started_at" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= lang('Leadership.endDate') ?></label>
                <input type="date" name="ended_at" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= lang('Leadership.notes') ?></label>
                <input type="text" name="notes" class="form-control" placeholder="<?= lang('Leadership.notesPlaceholder') ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" value="1" name="active" id="leadershipActive" checked>
                    <label class="form-check-label" for="leadershipActive"><?= lang('App.statusActive') ?></label>
                </div>
            </div>
            <div class="col-md-2 d-grid">
                <label class="form-label d-none d-md-block">&nbsp;</label>
                <button type="submit" class="btn btn-primary"><?= lang('Leadership.savePosition') ?></button>
            </div>
        </form>
    </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-list-ul" aria-hidden="true"></i> <?= lang('Leadership.positionsTitle') ?></h5>
        <span class="badge border border-secondary text-secondary fw-normal"><?= (int) ($total_rows ?? count($positions ?? [])) ?> <?= lang('Leadership.records') ?></span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($positions)): ?>
            <p class="text-muted text-center py-4 mb-0"><?= lang('Leadership.noPositionsForScope') ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th scope="col"><?= lang('Leadership.member') ?></th>
                            <th scope="col"><?= lang('Leadership.title') ?></th>
                            <th scope="col"><?= lang('Leadership.scope') ?></th>
                            <th scope="col"><?= lang('Leadership.period') ?></th>
                            <th scope="col"><?= lang('App.colStatus') ?></th>
                            <th scope="col"><?= lang('App.colActions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($positions as $position): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= esc(trim(($position['first_name'] ?? '') . ' ' . ($position['last_name'] ?? ''))) ?></div>
                                    <small class="text-muted"><?= esc($position['email'] ?? '-') ?></small>
                                </td>
                                <td>
                                    <div><?= esc($position['title'] ?? '-') ?></div>
                                    <small class="text-muted"><?= esc($position['role_code'] ?? '') ?></small>
                                </td>
                                <td>
                                    <?php
                                    $scopeType = (string) ($position['scope_type'] ?? 'parish');
                                    $scopeLabel = $scopeType === 'province'
                                        ? lang('App.scopeProvince')
                                        : ($scopeType === 'diocese' ? lang('App.scopeDiocese') : lang('App.scopeParish'));
                                    ?>
                                    <span class="badge border border-info text-info fw-normal"><?= esc($scopeLabel) ?></span>
                                </td>
                                <td>
                                    <?= !empty($position['started_at']) ? esc($position['started_at']) : '-' ?>
                                    <?= lang('Leadership.to') ?>
                                    <?= !empty($position['ended_at']) ? esc($position['ended_at']) : lang('Leadership.present') ?>
                                </td>
                                <td>
                                    <span class="badge border border-<?= !empty($position['active']) ? 'success' : 'secondary' ?> text-<?= !empty($position['active']) ? 'success' : 'secondary' ?> fw-normal">
                                        <?= !empty($position['active']) ? lang('App.statusActive') : lang('App.statusInactive') ?>
                                    </span>
                                </td>
                                <td>
                                    <details>
                                        <summary class="btn btn-sm btn-outline-primary"><?= lang('App.edit') ?></summary>
                                        <div class="mt-2">
                                            <form method="post" action="<?= base_url('leadership/update/' . (int) $position['id']) ?>" class="row g-2">
                                                <?= csrf_field() ?>
                                                <div class="col-12">
                                                    <input type="text" name="title" class="form-control form-control-sm" value="<?= esc((string) ($position['title'] ?? '')) ?>" required>
                                                </div>
                                                <div class="col-12">
                                                    <input type="text" name="role_code" class="form-control form-control-sm" value="<?= esc((string) ($position['role_code'] ?? '')) ?>" placeholder="<?= lang('Leadership.roleCode') ?>">
                                                </div>
                                                <div class="col-12">
                                                    <select name="role_category" class="form-select form-select-sm">
                                                        <option value=""><?= lang('Leadership.roleCategorySelectPrompt') ?></option>
                                                        <option value="clergy" <?= ($position['role_category'] ?? '') === 'clergy' ? 'selected' : '' ?>><?= lang('Leadership.roleCategoryClergy') ?></option>
                                                        <option value="lay_staff" <?= ($position['role_category'] ?? '') === 'lay_staff' ? 'selected' : '' ?>><?= lang('Leadership.roleCategoryLayStaff') ?></option>
                                                    </select>
                                                </div>
                                                <div class="col-12">
                                                    <select name="clergy_order" class="form-select form-select-sm">
                                                        <option value=""><?= lang('Leadership.clergyOrderSelectPrompt') ?></option>
                                                        <option value="bishop" <?= ($position['clergy_order'] ?? '') === 'bishop' ? 'selected' : '' ?>><?= lang('Leadership.clergyOrderBishop') ?></option>
                                                        <option value="priest" <?= ($position['clergy_order'] ?? '') === 'priest' ? 'selected' : '' ?>><?= lang('Leadership.clergyOrderPriest') ?></option>
                                                        <option value="deacon" <?= ($position['clergy_order'] ?? '') === 'deacon' ? 'selected' : '' ?>><?= lang('Leadership.clergyOrderDeacon') ?></option>
                                                    </select>
                                                </div>
                                                <div class="col-12">
                                                    <select name="employment_type" class="form-select form-select-sm">
                                                        <option value=""><?= lang('Leadership.employmentSelectPrompt') ?></option>
                                                        <option value="full_time" <?= ($position['employment_type'] ?? '') === 'full_time' ? 'selected' : '' ?>><?= lang('Leadership.employmentFullTime') ?></option>
                                                        <option value="part_time" <?= ($position['employment_type'] ?? '') === 'part_time' ? 'selected' : '' ?>><?= lang('Leadership.employmentPartTime') ?></option>
                                                        <option value="non_stipendiary" <?= ($position['employment_type'] ?? '') === 'non_stipendiary' ? 'selected' : '' ?>><?= lang('Leadership.employmentNonStipendiary') ?></option>
                                                        <option value="unpaid" <?= ($position['employment_type'] ?? '') === 'unpaid' ? 'selected' : '' ?>><?= lang('Leadership.employmentUnpaid') ?></option>
                                                    </select>
                                                </div>
                                                <div class="col-6">
                                                    <input type="date" name="started_at" class="form-control form-control-sm" value="<?= esc((string) ($position['started_at'] ?? '')) ?>">
                                                </div>
                                                <div class="col-6">
                                                    <input type="date" name="ended_at" class="form-control form-control-sm" value="<?= esc((string) ($position['ended_at'] ?? '')) ?>">
                                                </div>
                                                <div class="col-12">
                                                    <input type="text" name="notes" class="form-control form-control-sm" value="<?= esc((string) ($position['notes'] ?? '')) ?>" placeholder="<?= lang('Leadership.notes') ?>">
                                                </div>
                                                <div class="col-12 form-check ms-1">
                                                    <input class="form-check-input" type="checkbox" name="active" value="1" id="active<?= (int) $position['id'] ?>" <?= !empty($position['active']) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="active<?= (int) $position['id'] ?>"><?= lang('App.statusActive') ?></label>
                                                </div>
                                                <div class="col-12 d-flex gap-2">
                                                    <button type="submit" class="btn btn-sm btn-primary"><?= lang('App.save') ?></button>
                                            </form>
                                            <form method="post" action="<?= base_url('leadership/delete/' . (int) $position['id']) ?>"
                                                data-confirm-title="<?= esc(lang('App.confirm'), 'attr') ?>"
                                                data-confirm-body="<?= esc(lang('Leadership.deleteConfirm'), 'attr') ?>">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><?= lang('App.delete') ?></button>
                                            </form>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php if (($total_pages ?? 1) > 1): ?>
        <?php
        $currentPage = (int) ($page ?? 1);
        $query = [
            'scope' => (string) ($selected_scope ?? 'parish'),
            'per_page' => (int) ($per_page ?? 25),
        ];
        ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
            <small class="text-muted"><?= lang('Leadership.pageOf', [$currentPage, (int) ($total_pages ?? 1)]) ?></small>
            <div class="btn-group">
                <?php if ($currentPage > 1): ?>
                    <a class="btn btn-sm btn-outline-primary" href="<?= base_url('leadership') . '?' . http_build_query(array_merge($query, ['page' => $currentPage - 1])) ?>"><?= lang('App.previous') ?></a>
                <?php endif; ?>
                <?php if ($currentPage < (int) ($total_pages ?? 1)): ?>
                    <a class="btn btn-sm btn-outline-primary" href="<?= base_url('leadership') . '?' . http_build_query(array_merge($query, ['page' => $currentPage + 1])) ?>"><?= lang('App.next') ?></a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>