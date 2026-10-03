<?php /* Partial: sacramental records + participants card. Required: $sacraments, $sacrament_participants, $personId */ ?>
            <?php if (!empty($sacraments) || auth()->user()->can('member.edit')): ?>
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><?= lang('Sacraments.title') ?></h6>
                        <?php if (auth()->user()->can('member.edit') && $personId > 0): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="addSacramentBtn">
                                <i class="bi bi-plus-lg" aria-hidden="true"></i> <?= lang('Sacraments.addSacrament') ?>
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($sacraments)): ?>
                            <p class="text-muted small mb-0"><?= lang('Sacraments.noSacraments') ?></p>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($sacraments as $sacrament): ?>
                                    <li class="list-group-item" data-sacrament-id="<?= (int) ($sacrament['id'] ?? 0) ?>">
                                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                            <div>
                                                <div class="fw-semibold"><?= esc(ucfirst((string) ($sacrament['sacrament_type'] ?? ''))) ?></div>
                                                <?php if (!empty($sacrament['sacrament_date'])): ?>
                                                    <small class="text-muted"><?= esc($sacrament['sacrament_date']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="d-flex gap-1 align-items-center">
                                                <?php if (auth()->user()->can('member.edit')): ?>
                                                    <button type="button" class="btn btn-sm btn-link p-0 edit-sacrament-btn"
                                                        data-sacrament="<?= esc(json_encode(array_intersect_key($sacrament, array_flip(['id', 'sacrament_type', 'sacrament_date', 'register_ref', 'certificate_number', 'officiant_person_id', 'location_notes', 'notes']))), 'attr') ?>"
                                                        title="<?= esc(lang('App.edit'), 'attr') ?>">
                                                        <i class="bi bi-pencil text-warning" aria-hidden="true"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-link p-0 delete-sacrament-btn"
                                                        data-sacrament-id="<?= (int) ($sacrament['id'] ?? 0) ?>"
                                                        data-sacrament-type="<?= esc((string) ($sacrament['sacrament_type'] ?? ''), 'attr') ?>"
                                                        title="<?= esc(lang('App.delete'), 'attr') ?>">
                                                        <i class="bi bi-trash3 text-danger" aria-hidden="true"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if (!empty($sacrament['officiant_last_name']) || !empty($sacrament['officiant_first_name'])): ?>
                                            <small class="text-muted">
                                                <?= lang('Sacraments.officiant') ?>:
                                                <?= esc(trim(($sacrament['officiant_first_name'] ?? '') . ' ' . ($sacrament['officiant_last_name'] ?? ''))) ?>
                                            </small>
                                        <?php endif; ?>
                                        <?php if (!empty($sacrament['location_notes'])): ?>
                                            <small class="text-muted d-block"><?= esc($sacrament['location_notes']) ?></small>
                                        <?php endif; ?>
                                        <?php if (!empty($sacrament['certificate_number']) || !empty($sacrament['register_ref'])): ?>
                                            <small class="text-muted d-block">
                                                <?= !empty($sacrament['certificate_number']) ? lang('Sacraments.certificateNumber') . ': ' . esc($sacrament['certificate_number']) : '' ?>
                                                <?= !empty($sacrament['certificate_number']) && !empty($sacrament['register_ref']) ? ' | ' : '' ?>
                                                <?= !empty($sacrament['register_ref']) ? lang('Sacraments.registerRef') . ': ' . esc($sacrament['register_ref']) : '' ?>
                                            </small>
                                        <?php endif; ?>
                                        <?php if (!empty($sacrament['notes'])): ?>
                                            <p class="mb-0 mt-1 small text-muted"><?= esc($sacrament['notes']) ?></p>
                                        <?php endif; ?>

                                        <?php
                                        $sacramentId = (int) ($sacrament['id'] ?? 0);
                                        $participants = $sacrament_participants[$sacramentId] ?? [];
                                        ?>
                                        <div class="mt-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted fw-semibold"><?= lang('Sacraments.participants') ?></small>
                                                <?php if (auth()->user()->can('member.edit')): ?>
                                                    <button type="button" class="btn btn-sm btn-link p-0 add-sacrament-participant-btn"
                                                        data-sacrament-id="<?= $sacramentId ?>"
                                                        title="<?= esc(lang('Sacraments.addParticipant'), 'attr') ?>">
                                                        <i class="bi bi-plus-lg text-primary" aria-hidden="true"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (empty($participants)): ?>
                                                <small class="text-muted fst-italic"><?= lang('Sacraments.noParticipants') ?></small>
                                            <?php else: ?>
                                                <ul class="list-unstyled mb-0 small">
                                                    <?php foreach ($participants as $participant): ?>
                                                        <li class="d-flex justify-content-between align-items-center gap-2 py-1 border-bottom">
                                                            <span><?= esc(($participant['first_name'] ?? '') . ' ' . ($participant['last_name'] ?? '')) ?></span>
                                                            <?php if (auth()->user()->can('member.edit')): ?>
                                                                <div class="d-flex gap-1 align-items-center">
                                                                    <select class="form-select form-select-sm participant-role-select"
                                                                        data-participant-id="<?= (int) ($participant['id'] ?? 0) ?>"
                                                                        style="width: auto; min-width: 110px;">
                                                                        <?php foreach (['Godparent', 'Sponsor', 'Witness', 'Celebrant', 'Parent', 'Spouse', 'Other'] as $role): ?>
                                                                            <option value="<?= $role ?>" <?= (($participant['participant_role'] ?? '') === $role) ? 'selected' : '' ?>><?= lang('Sacraments.participant' . $role) ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                    <button type="button" class="btn btn-sm btn-link p-0 delete-sacrament-participant-btn"
                                                                        data-participant-id="<?= (int) ($participant['id'] ?? 0) ?>"
                                                                        data-name="<?= esc(($participant['first_name'] ?? '') . ' ' . ($participant['last_name'] ?? ''), 'attr') ?>"
                                                                        title="<?= esc(lang('App.delete'), 'attr') ?>">
                                                                        <i class="bi bi-trash3 text-danger" aria-hidden="true"></i>
                                                                    </button>
                                                                </div>
                                                            <?php else: ?>
                                                                <span class="text-muted"><?= esc($participant['participant_role'] ?? '') ?></span>
                                                            <?php endif; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
