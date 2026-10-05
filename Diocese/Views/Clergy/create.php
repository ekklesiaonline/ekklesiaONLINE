<?= $this->extend('Diocese\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-start align-items-center mb-3">
    <a href="<?= base_url('diocese/clergy') ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Clergy.backToClergy') ?>
    </a>
</div>

<div class="card">
    <div class="card-body">
        <p class="text-muted"><?= lang('Clergy.createHelp') ?></p>
        <form action="<?= base_url('diocese/clergy/store') ?>" method="post">
            <?= csrf_field() ?>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="first_name" class="form-label"><?= lang('Clergy.firstName') ?> <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="first_name" name="first_name" value="<?= esc(old('first_name')) ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="middle_name" class="form-label"><?= lang('Clergy.middleName') ?></label>
                    <input type="text" class="form-control" id="middle_name" name="middle_name" value="<?= esc(old('middle_name')) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="last_name" class="form-label"><?= lang('Clergy.lastName') ?> <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="last_name" name="last_name" value="<?= esc(old('last_name')) ?>" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label"><?= lang('Clergy.email') ?> <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= esc(old('email')) ?>" required>
                    <small class="text-muted d-block mt-1"><?= lang('Clergy.emailInviteHelp') ?></small>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label"><?= lang('Clergy.diocese') ?></label>
                    <input type="text" class="form-control" value="<?= esc($diocese['name'] ?? '') ?>" disabled>
                    <small class="text-muted d-block mt-1"><?= lang('Clergy.dioceseImplicitHelp') ?></small>
                </div>
            </div>

            <div class="mb-3">
                <label for="person_search" class="form-label"><?= lang('Clergy.linkExistingPerson') ?></label>
                <input type="text" class="form-control" id="person_search" autocomplete="off" placeholder="<?= esc(lang('Clergy.linkExistingPersonHelp')) ?>">
                <input type="hidden" id="person_id" name="person_id" value="<?= esc(old('person_id')) ?>">
                <div id="person_results" class="list-group mt-1" style="display:none;"></div>
                <div id="person_selected" class="mt-1" style="display:none;"></div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="bi bi-envelope-paper" aria-hidden="true"></i> <?= lang('Clergy.btnSendInvitation') ?></button>
        </form>
    </div>
</div>

<script>
(function () {
    const input = document.getElementById('person_search');
    const hidden = document.getElementById('person_id');
    const results = document.getElementById('person_results');
    const selected = document.getElementById('person_selected');
    let timer = null;

    function clearSelection() {
        hidden.value = '';
        selected.style.display = 'none';
        selected.innerHTML = '';
    }

    input.addEventListener('input', function () {
        clearSelection();
        clearTimeout(timer);
        const q = input.value.trim();
        if (q.length < 2) { results.style.display = 'none'; return; }
        timer = setTimeout(function () {
            fetch('<?= base_url('diocese/clergy/person-search') ?>?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
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
