<?= $this->extend('Worship\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
$request = service('request');
$selectedPerPage = (int) ($per_page ?? (int) ($request->getGet('per_page') ?? 20));
if (!in_array($selectedPerPage, [25, 50, 100], true)) {
    $selectedPerPage = 25;
}
?>

<div class="d-flex justify-content-start align-items-center mb-3 gap-2">
    <?php if (auth()->user()->can('service.create')): ?>
        <a href="<?= base_url('services/create') ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle" aria-hidden="true"></i> <?= lang('Services.pageTitleAdd') ?>
        </a>
    <?php endif; ?>
    <!-- Attendance registration button removed as requested -->
</div>

<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-end mb-3">
            <form method="get" action="<?= base_url('services') ?>" class="d-flex align-items-center gap-2">
                <label for="servicesPerPage" class="form-label mb-0 small text-muted"><?= lang('App.perPage') ?>:</label>
                <select id="servicesPerPage" name="per_page" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="25" <?= $selectedPerPage === 25 ? 'selected' : '' ?>>25</option>
                    <option value="50" <?= $selectedPerPage === 50 ? 'selected' : '' ?>>50</option>
                    <option value="100" <?= $selectedPerPage === 100 ? 'selected' : '' ?>>100</option>
                </select>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th scope="col"><?= lang('Services.colDate') ?></th>
                        <th scope="col"><?= lang('Services.colTime') ?></th>
                        <th scope="col"><?= lang('Services.colServiceType') ?></th>
                        <th scope="col"><?= lang('Services.colLiturgicalDay') ?></th>
                        <th scope="col"><?= lang('Services.colPlace') ?></th>
                        <th scope="col"><?= lang('Services.colPresent') ?></th>
                        <th scope="col"><?= lang('Services.colCommunicants') ?></th>
                        <th scope="col"><?= lang('Services.colEdit') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($services)): ?>
                        <?php foreach ($services as $service): ?>
                            <tr>
                                <td><?= esc(liturgy_format_date((string) ($service['service_date'] ?? ''))) ?></td>
                                <td><?= !empty($service['service_time']) ? esc(liturgy_format_time((string) ($service['service_time'] ?? ''))) : '-' ?></td>
                                <td>
                                    <?php
                                    $serviceTypes = json_decode($service['service'] ?? '[]', true);
                                    if (!empty($serviceTypes) && is_array($serviceTypes)):
                                        $localizedServiceTypes = array_map(static fn($value): string => service_type_label((string) $value), $serviceTypes);
                                        echo esc(implode(', ', $localizedServiceTypes));
                                    else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?= esc(liturgical_day_label((string) ($service['liturgical_day'] ?? '')) ?: '-') ?></td>
                                <td><?= esc($service['place'] ?: '-') ?></td>
                                <td>
                                    <?php if (isset($service['attendance_count']) && $service['attendance_count'] > 0): ?>
                                        <?= (int) $service['attendance_count'] ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($service['total_communicants']) && $service['total_communicants'] > 0): ?>
                                        <?= (int) $service['total_communicants'] ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-inline-flex flex-nowrap gap-1 align-items-center">
                                        <?php if (auth()->user()->can('service.edit')): ?>
                                            <a href="<?= base_url('services/edit/' . $service['id']) ?>" class="btn btn-sm btn-warning" title="<?= lang('App.edit') ?>">
                                                <i class="bi bi-pencil" aria-hidden="true"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4"><?= lang('Services.noServicesFound') ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php
        $currentPage = max(1, (int) ($current_page ?? 1));
        $totalPages = max(1, (int) ($total_pages ?? 1));
        ?>
        <?php if ($totalPages > 1): ?>
            <nav class="mt-3" aria-label="Services pagination">
                <ul class="pagination pagination-sm mb-0">
                    <?php
                    $prevDisabled = $currentPage <= 1;
                    $nextDisabled = $currentPage >= $totalPages;
                    $startPage = max(1, $currentPage - 2);
                    $endPage = min($totalPages, $currentPage + 2);
                    ?>
                    <li class="page-item <?= $prevDisabled ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $prevDisabled ? '#' : base_url('services?' . http_build_query(['per_page' => $selectedPerPage, 'page' => $currentPage - 1])) ?>">&laquo;</a>
                    </li>
                    <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                        <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                            <a class="page-link" href="<?= base_url('services?' . http_build_query(['per_page' => $selectedPerPage, 'page' => $p])) ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $nextDisabled ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $nextDisabled ? '#' : base_url('services?' . http_build_query(['per_page' => $selectedPerPage, 'page' => $currentPage + 1])) ?>">&raquo;</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<script>
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

    function deleteService(id) {
        showAppConfirm(
            '<?= esc(lang('App.confirm'), 'js') ?>',
            <?= json_encode(lang('Services.deleteConfirmWithMetrics')) ?>,
            function() {
                fetch('<?= base_url('services/delete/') ?>' + id, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/json'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            showAppToast(<?= json_encode(lang('Services.errorPrefix') . ' ') ?> + contractErrorMessage(data, <?= json_encode(lang('App.errorGeneric')) ?>), 'danger');
                        }
                    });
            }
        );
    }
</script>

<?= $this->endSection() ?>