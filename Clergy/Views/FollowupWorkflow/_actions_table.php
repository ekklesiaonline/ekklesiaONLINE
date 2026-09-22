<?php

/**
 * Partial: _actions_table.php
 *
 * Server-side render of the action steps table inside edit_sequence.php.
 * Also used as the initial HTML state before JavaScript takes over.
 *
 * Required variables:
 *   array  $actions      – rows from FollowupActionModel::getBySequenceWithTemplates()
 *   int    $sequenceId   – parent sequence ID
 */
?>
<?php if (empty($actions)): ?>
    <div class="text-center py-4 text-muted" id="actionsEmpty">
        <i class="bi bi-list-task fs-2 d-block mb-2" aria-hidden="true"></i>
        <?= lang('Visitors.seqStepsEmpty') ?>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col"><?= lang('Visitors.seqColWeek') ?></th>
                    <th scope="col"><?= lang('Visitors.seqColDayOffset') ?></th>
                    <th scope="col"><?= lang('Visitors.seqColChannel') ?></th>
                    <th scope="col"><?= lang('Visitors.seqColTemplate') ?></th>
                    <th scope="col"><?= lang('Visitors.seqColType') ?></th>
                    <th scope="col" class="text-end"><?= lang('App.colActions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $channelBadge = [
                    'text'  => 'bg-primary',
                    'email' => 'bg-info text-dark',
                    'call'  => 'bg-warning text-dark',
                    'mail'  => 'bg-secondary',
                    'gift'  => 'bg-success',
                ];
                ?>
                <?php foreach ($actions as $a): ?>
                    <tr>
                        <td><?= (int) $a['week_number'] ?></td>
                        <td><?= (int) $a['day_offset'] ?></td>
                        <td>
                            <span class="badge <?= $channelBadge[$a['channel']] ?? 'bg-light text-dark' ?>">
                                <?= esc($a['channel']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($a['template_name'])): ?>
                                <?= esc($a['template_name']) ?>
                            <?php else: ?>
                                <em class="text-muted"><?= lang('Visitors.seqStepNone') ?></em>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($a['is_automated']): ?>
                                <span class="badge border border-success text-success fw-normal"><?= lang('Visitors.seqTypeAuto') ?></span>
                            <?php else: ?>
                                <span class="badge border border-secondary text-body fw-normal"><?= lang('Visitors.seqTypeManual') ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <button type="button"
                                class="btn btn-sm btn-outline-secondary me-1 btn-edit-action"
                                data-id="<?= (int) $a['id'] ?>"
                                data-week="<?= (int) $a['week_number'] ?>"
                                data-day-offset="<?= (int) $a['day_offset'] ?>"
                                data-channel="<?= esc($a['channel'], 'attr') ?>"
                                data-template-id="<?= esc((string) ($a['template_id'] ?? ''), 'attr') ?>"
                                data-automated="<?= (int) $a['is_automated'] ?>" aria-label="<?= esc(lang('App.edit')) ?>">
                                <i class="bi bi-pencil" aria-hidden="true"></i>
                            </button>
                            <button type="button"
                                class="btn btn-sm btn-outline-danger btn-delete-action"
                                data-id="<?= (int) $a['id'] ?>"
                                data-channel="<?= esc($a['channel'], 'attr') ?>" aria-label="<?= esc(lang('App.delete')) ?>">
                                <i class="bi bi-trash" aria-hidden="true"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>