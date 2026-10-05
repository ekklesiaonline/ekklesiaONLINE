<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php if (session('errors')): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ((array) session('errors') as $error): ?>
                <li><?= esc($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-body">
        <h6 class="card-subtitle mb-2 text-muted"><?= lang('ParishClergy.appointedTitle') ?></h6>
        <p class="text-muted small mb-3"><?= lang('ParishClergy.readOnlyHelp') ?></p>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th scope="col"><?= lang('Clergy.colName') ?></th>
                        <th scope="col"><?= lang('ParishClergy.colOffice') ?></th>
                        <th scope="col"><?= lang('ParishClergy.colOrder') ?></th>
                        <th scope="col"><?= lang('ParishClergy.colContact') ?></th>
                        <th scope="col"><?= lang('ParishClergy.colSince') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($clergy)): ?>
                        <?php foreach ($clergy as $row): ?>
                            <tr>
                                <td><?= esc(trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''))) ?></td>
                                <td><?= esc($row['title'] ?? $row['role_code'] ?? '-') ?></td>
                                <td><?php $order = (string) ($row['clergy_order'] ?? ''); ?><?= $order !== '' ? esc($order) : '-' ?></td>
                                <td>
                                    <div class="small"><?= esc($row['email'] ?? '') ?></div>
                                    <div class="small text-muted"><?= esc($row['phone'] ?? '') ?></div>
                                </td>
                                <td><?= esc($row['started_at'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4"><?= lang('ParishClergy.noClergy') ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <h6 class="card-subtitle mb-2 text-muted"><?= lang('ParishClergy.requestTitle') ?></h6>
        <p class="text-muted small mb-3"><?= lang('ParishClergy.requestHelp') ?></p>
        <form action="<?= base_url('parish/clergy/request') ?>" method="post" class="row g-3 align-items-end">
            <?= csrf_field() ?>
            <div class="col-md-5">
                <label for="request_person_search" class="form-label"><?= lang('ParishClergy.personLabel') ?></label>
                <input type="text" class="form-control" id="request_person_search" autocomplete="off" placeholder="<?= esc(lang('ParishClergy.personSearchPlaceholder')) ?>">
                <input type="hidden" id="request_person_id" name="person_id" value="<?= esc(old('person_id')) ?>">
                <div id="request_person_results" class="list-group mt-1" style="display:none;"></div>
                <div id="request_person_selected" class="mt-1" style="display:none;"></div>
            </div>
            <div class="col-md-4">
                <label for="proposed_office" class="form-label"><?= lang('ParishClergy.officeLabel') ?></label>
                <select class="form-select" id="proposed_office" name="proposed_office" required>
                    <option value=""><?= lang('ParishClergy.officePlaceholder') ?></option>
                    <?php foreach ($officeOptions as $office): ?>
                        <option value="<?= esc($office) ?>" <?= old('proposed_office') === $office ? 'selected' : '' ?>><?= esc(lang('ParishClergy.office.' . $office)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-send" aria-hidden="true"></i> <?= lang('ParishClergy.btnSubmitRequest') ?></button>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($requests)): ?>
<div class="card">
    <div class="card-body">
        <h6 class="card-subtitle mb-3 text-muted"><?= lang('ParishClergy.requestsTitle') ?></h6>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th scope="col"><?= lang('Clergy.colName') ?></th>
                        <th scope="col"><?= lang('ParishClergy.colOffice') ?></th>
                        <th scope="col"><?= lang('ParishClergy.colRequestStatus') ?></th>
                        <th scope="col"><?= lang('ParishClergy.colRequestedAt') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $request): ?>
                        <?php
                        $status = (string) ($request['review_status'] ?? 'pending');
                        $statusColor = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'][$status] ?? 'secondary';
                        ?>
                        <tr>
                            <td><?= esc(trim((string) ($request['first_name'] ?? '') . ' ' . (string) ($request['last_name'] ?? ''))) ?></td>
                            <td><?= esc($request['proposed_office'] ?? '-') ?></td>
                            <td><span class="badge border border-<?= $statusColor ?> text-<?= $statusColor ?> fw-normal"><?= esc(lang('ParishClergy.status.' . $status)) ?></span></td>
                            <td><?= esc($request['created_at'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    const input = document.getElementById('request_person_search');
    const hidden = document.getElementById('request_person_id');
    const results = document.getElementById('request_person_results');
    const selected = document.getElementById('request_person_selected');
    let timer = null;

    input.addEventListener('input', function () {
        hidden.value = '';
        selected.style.display = 'none';
        selected.innerHTML = '';
        clearTimeout(timer);
        const q = input.value.trim();
        if (q.length < 2) { results.style.display = 'none'; return; }
        timer = setTimeout(function () {
            fetch('<?= base_url('parish/clergy/person-search') ?>?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    const items = (res && res.data) || [];
                    results.innerHTML = '';
                    if (!items.length) { results.style.display = 'none'; return; }
                    items.forEach(function (p) {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'list-group-item list-group-item-action';
                        btn.textContent = p.first_name + ' ' + p.last_name + ' (#' + p.id + ')';
                        btn.addEventListener('click', function () {
                            hidden.value = p.id;
                            selected.innerHTML = '<span class="badge border border-success text-success fw-normal">' + btn.textContent + '</span>';
                            selected.style.display = 'block';
                            results.style.display = 'none';
                        });
                        results.appendChild(btn);
                    });
                    results.style.display = 'block';
                })
                .catch(function () { results.style.display = 'none'; });
        }, 250);
    });

    document.addEventListener('click', function (e) {
        if (!results.contains(e.target) && e.target !== input) { results.style.display = 'none'; }
    });
})();
</script>

<?= $this->endSection() ?>
