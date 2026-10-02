<?= $this->extend('Diocese\Views\layouts/main') ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-start align-items-center mb-3 gap-2">
    <a href="<?= base_url('diocese/dashboard') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('App.back') ?></a>
    <?php if (!empty($can_manage_deaneries)): ?>
        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#deaneryCreatePanel" aria-expanded="false" aria-controls="deaneryCreatePanel"><i class="bi bi-plus-lg" aria-hidden="true"></i> New deanery</button>
    <?php endif; ?>
</div>

<?php if (!empty($can_manage_deaneries)): ?>
<div id="deaneryCreatePanel" class="collapse mb-3">
    <div class="card card-body">
        <form class="row g-2 align-items-end deanery-action" data-endpoint="<?= base_url('diocese/deaneries/store') ?>">
            <div class="col-md-3"><label for="deaneryCode" class="form-label">Code</label><input id="deaneryCode" name="code" class="form-control form-control-sm" maxlength="64" pattern="[A-Za-z0-9_-]+" required></div>
            <div class="col-md-6"><label for="deaneryName" class="form-label">Deanery name</label><input id="deaneryName" name="name" class="form-control form-control-sm" maxlength="150" required></div>
            <div class="col-md-3"><button class="btn btn-sm btn-outline-primary w-100" type="submit">Create deanery</button></div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if (empty($deaneries)): ?>
    <div class="alert alert-info" role="status">No deaneries have been configured for this diocese.</div>
<?php endif; ?>
<?php foreach ($deaneries as $deanery): ?>
    <section class="card mb-3" aria-label="<?= esc($deanery['name'], 'attr') ?>">
        <div class="card-header d-flex justify-content-between align-items-center"><strong><?= esc($deanery['name']) ?></strong><code><?= esc($deanery['code']) ?></code></div>
        <div class="card-body">
            <?php if (empty($deanery['parishes'])): ?>
                <p class="text-muted mb-3">No current parish memberships.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th scope="col">Congregation</th><th scope="col">Since</th><?php if (!empty($can_manage_deaneries)): ?><th scope="col">Membership</th><?php endif; ?></tr></thead>
                        <tbody>
                        <?php foreach ($deanery['parishes'] as $parish): ?>
                            <tr><td><?= esc($parish['name']) ?></td><td><?= esc($parish['started_at']) ?></td>
                            <?php if (!empty($can_manage_deaneries)): ?>
                                <td><form class="d-flex gap-2 deanery-action" data-endpoint="<?= base_url('diocese/deaneries/end/' . (int) $parish['membership_id']) ?>">
                                    <label class="visually-hidden" for="endMembership<?= (int) $parish['membership_id'] ?>">End membership on</label>
                                    <input id="endMembership<?= (int) $parish['membership_id'] ?>" name="ended_at" type="date" class="form-control form-control-sm" min="<?= esc($parish['started_at']) ?>" required>
                                    <button type="submit" class="btn btn-sm btn-outline-secondary text-nowrap">End membership</button>
                                </form></td>
                            <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <?php if (!empty($can_manage_deaneries)): ?>
                <form class="row g-2 align-items-end deanery-action" data-endpoint="<?= base_url('diocese/deaneries/assign/' . (int) $deanery['id']) ?>">
                    <div class="col-md-6"><label for="parishInDeanery<?= (int) $deanery['id'] ?>" class="form-label">Add a congregation in this diocese</label>
                        <select id="parishInDeanery<?= (int) $deanery['id'] ?>" name="parish_id" class="form-select form-select-sm" required>
                            <option value="">Select a congregation</option>
                            <?php foreach ($parishes as $parish): ?><option value="<?= (int) $parish['id'] ?>"><?= esc($parish['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3"><label for="memberStart<?= (int) $deanery['id'] ?>" class="form-label">Effective from</label><input id="memberStart<?= (int) $deanery['id'] ?>" name="started_at" class="form-control form-control-sm" type="date" value="<?= date('Y-m-d') ?>" required></div>
                    <div class="col-md-3"><button type="submit" class="btn btn-sm btn-outline-primary w-100">Assign</button></div>
                </form>
            <?php endif; ?>
        </div>
    </section>
<?php endforeach; ?>

<script>
document.querySelectorAll('.deanery-action').forEach(function (form) {
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrfHeader = document.querySelector('meta[name="csrf-header"]')?.content ?? 'X-CSRF-TOKEN';
        const headers = {'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded'};
        if (csrfMeta) headers[csrfHeader] = csrfMeta.content;
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        fetch(form.dataset.endpoint, {
            method: 'POST', credentials: 'same-origin', headers,
            body: new URLSearchParams(new FormData(form)).toString(),
        }).then(function (response) {
            const token = response.headers.get(csrfHeader);
            if (token && csrfMeta) csrfMeta.content = token;
            return response.json();
        }).then(function (body) {
            if (!body.success) {
                window.showAppToast?.(body.message ?? 'Unable to save deanery.', 'danger');
                return;
            }
            window.showAppToast?.(body.message, 'success');
            window.location.reload();
        }).catch(function () {
            window.showAppToast?.('Unable to save deanery.', 'danger');
        }).finally(function () { button.disabled = false; });
    });
});
</script>
<?= $this->endSection() ?>
