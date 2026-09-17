<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-start align-items-center mb-3">
    <?php if (auth()->user()->can('family.manage')): ?>
        <a href="<?= base_url('families/create') ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle" aria-hidden="true"></i> <?= lang('Families.create') ?>
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($families)): ?>
            <div class="text-center py-5">
                <i class="bi bi-people text-muted mb-3" style="font-size: 3rem;" aria-hidden="true"></i>
                <h5 class="text-muted"><?= lang('Families.notFound') ?></h5>
                <?php if (auth()->user()->can('family.manage')): ?>
                    <a href="<?= base_url('families/create') ?>" class="btn btn-primary mt-3">
                        <?= lang('Families.createFirst') ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th scope="col"><?= lang('Families.colFamilyName') ?></th>
                            <th scope="col"><?= lang('Families.colHeadOfHousehold') ?></th>
                            <th scope="col"><?= lang('Families.colMembers') ?></th>
                            <th scope="col"><?= lang('Families.colAddress') ?></th>
                            <th scope="col"><?= lang('App.colActions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($families as $family): ?>
                            <tr>
                                <td>
                                    <strong><?= esc($family['family_name']) ?></strong>
                                </td>
                                <td>
                                    <?php if ($family['head_name']): ?>
                                        <?= esc($family['head_name']) ?>
                                    <?php else: ?>
                                        <span class="text-muted"><?= lang('Families.notAssigned') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge border border-info text-info fw-normal"><?= lang('Families.membersCount', [(int) $family['member_count']]) ?></span>
                                </td>
                                <td>
                                    <?= esc($family['address'] ?: lang('App.notApplicable')) ?>
                                </td>
                                <td>
                                    <div class="table-actions d-inline-flex flex-nowrap gap-1 align-items-center">
                                        <a href="<?= base_url('families/view/' . $family['id']) ?>"
                                            class="btn btn-sm btn-info" title="<?= lang('App.view') ?>">
                                            <i class="bi bi-eye" aria-hidden="true"></i>
                                        </a>
                                        <?php if (auth()->user()->can('family.manage')): ?>
                                            <a href="<?= base_url('families/edit/' . $family['id']) ?>"
                                                class="btn btn-sm btn-warning" title="<?= lang('App.edit') ?>">
                                                <i class="bi bi-pencil" aria-hidden="true"></i>
                                            </a>
                                            <button onclick="deleteFamily(<?= $family['id'] ?>)"
                                                class="btn btn-sm btn-danger" title="<?= lang('App.delete') ?>">
                                                <i class="bi bi-trash" aria-hidden="true"></i>
                                            </button>
                                        <?php endif; ?>
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

<script>
    function renderContractErrors(container, data) {
        if (!container) {
            return;
        }

        const errors = Array.isArray(data?.errors) ? data.errors : [];
        if (!errors.length) {
            container.classList.add('d-none');
            container.innerHTML = '';
            return;
        }

        const list = errors.map((item) => {
            const field = item?.field ? `${item.field}: ` : '';
            const message = item?.message || 'Request failed.';
            return `<li>${field}${message}</li>`;
        }).join('');

        container.innerHTML = `<ul class="mb-0">${list}</ul>`;
        container.classList.remove('d-none');
    }

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

    function deleteFamily(id) {
        showAppConfirm(
            '<?= esc(lang('App.confirm'), 'js') ?>',
            '<?= esc(lang('Families.deleteConfirm'), 'js') ?>',
            function() {
                fetch(`<?= base_url('families/delete/') ?>${id}`, {
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
                        } else {
                            showAppToast(contractErrorMessage(data, '<?= esc(lang('Families.deleteFailed'), 'js') ?>'), 'danger');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showAppToast('<?= esc(lang('Families.unexpectedError'), 'js') ?>', 'danger');
                    });
            }
        );
    }
</script>

<?= $this->endSection() ?>