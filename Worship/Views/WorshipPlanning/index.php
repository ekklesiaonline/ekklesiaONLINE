<?= $this->extend('Worship\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-start align-items-center mb-3">
    <a href="<?= base_url('worship-planning/songs') ?>" class="btn btn-outline-primary">
        <i class="bi bi-music-note-list" aria-hidden="true"></i> <?= lang('Worship.songLibrary') ?>
    </a>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th scope="col"><?= lang('Worship.colDate') ?></th>
                        <th scope="col"><?= lang('Worship.colService') ?></th>
                        <th scope="col"><?= lang('Worship.colLiturgicalDay') ?></th>
                        <th scope="col"><?= lang('Worship.colPlanItems') ?></th>
                        <th scope="col"><?= lang('Worship.colActions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($services)): ?>
                        <?php foreach ($services as $service): ?>
                            <?php
                            $serviceTypes = [];
                            if (!empty($service['service'])) {
                                $decoded = json_decode((string) $service['service'], true);
                                $serviceTypes = is_array($decoded) ? $decoded : [];
                            }
                            ?>
                            <tr>
                                <td><?= esc(liturgy_format_date((string) ($service['service_date'] ?? ''))) ?></td>
                                <td>
                                    <?php if (!empty($serviceTypes)): ?>
                                        <?php $localizedServiceTypes = array_map(static fn($value): string => service_type_label((string) $value), $serviceTypes); ?>
                                        <?= esc(implode(', ', $localizedServiceTypes)) ?>
                                    <?php else: ?>
                                        <span class="text-muted"><?= lang('Worship.notSet') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= esc(liturgical_day_label((string) ($service['liturgical_day'] ?? '')) ?: '-') ?></td>
                                <td>
                                    <span class="badge border border-secondary text-secondary fw-normal">
                                        <?= (int) ($plan_counts[(int) $service['id']] ?? 0) ?> <?= lang('Worship.items') ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?= base_url('worship-planning/service/' . (int) $service['id']) ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-list-task" aria-hidden="true"></i> <?= lang('Worship.plan') ?>
                                    </a>
                                    <a href="<?= base_url('worship-planning/print/' . (int) $service['id']) ?>" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener noreferrer">
                                        <i class="bi bi-printer" aria-hidden="true"></i> <?= lang('Worship.print') ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4"><?= lang('Worship.noServicesFound') ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection() ?>