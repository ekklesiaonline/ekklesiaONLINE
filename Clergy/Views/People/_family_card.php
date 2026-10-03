<?php /* Partial: family/household card. Required: $family, $family_members, $userId */ ?>
            <?php
            $isInFamily = !empty($family);
            $canManageFamily = auth()->user()->can('family.manage');
            ?>
            <?php if ($isInFamily || $canManageFamily): ?>
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><?= lang('Families.family') ?></h6>
                        <?php if ($canManageFamily && !$isInFamily && $userId > 0): ?>
                            <div class="d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="createFamilyBtn">
                                    <i class="bi bi-plus-lg" aria-hidden="true"></i> <?= lang('Families.create') ?>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="joinFamilyBtn">
                                    <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> <?= lang('Families.join') ?>
                                </button>
                            </div>
                        <?php elseif ($canManageFamily && $isInFamily): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="addFamilyMemberBtn">
                                <i class="bi bi-plus-lg" aria-hidden="true"></i> <?= lang('Families.addMember') ?>
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (!$isInFamily): ?>
                            <p class="text-muted small mb-0"><?= lang('Families.notInFamily') ?></p>
                        <?php else: ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong><?= esc($family['family_name'] ?? lang('Families.unnamed')) ?></strong>
                                <?php if ($canManageFamily): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger" id="leaveFamilyBtn">
                                        <?= lang('Families.leave') ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($family_members)): ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($family_members as $familyMember): ?>
                                        <?php
                                        $isCurrentMember = (int) ($familyMember['user_id'] ?? 0) === $userId;
                                        $memberRelationId = (int) ($familyMember['family_member_id'] ?? 0);
                                        ?>
                                        <li class="list-group-item px-0" data-family-member-id="<?= $memberRelationId ?>">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <span><?= esc(($familyMember['first_name'] ?? '') . ' ' . ($familyMember['last_name'] ?? '')) ?></span>
                                                    <?php if ($isCurrentMember): ?>
                                                        <span class="badge bg-light text-dark border ms-1"><?= lang('Families.you') ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($canManageFamily): ?>
                                                    <div class="d-flex gap-1 align-items-center">
                                                        <select class="form-select form-select-sm relationship-select"
                                                            data-family-member-id="<?= $memberRelationId ?>"
                                                            data-user-id="<?= (int) ($familyMember['user_id'] ?? 0) ?>"
                                                            style="width: auto; min-width: 110px;">
                                                            <?php foreach (['Head', 'Spouse', 'Child', 'Parent', 'Sibling', 'Other'] as $rel): ?>
                                                                <option value="<?= $rel ?>" <?= (($familyMember['relationship'] ?? '') === $rel) ? 'selected' : '' ?>><?= lang('Families.relationship' . $rel) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <?php if (!$isCurrentMember): ?>
                                                            <button type="button" class="btn btn-sm btn-link p-0 remove-family-member-btn"
                                                                data-family-member-id="<?= $memberRelationId ?>"
                                                                data-name="<?= esc(($familyMember['first_name'] ?? '') . ' ' . ($familyMember['last_name'] ?? ''), 'attr') ?>"
                                                                title="<?= esc(lang('App.delete'), 'attr') ?>">
                                                                <i class="bi bi-trash3 text-danger" aria-hidden="true"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted small"><?= esc($familyMember['relationship'] ?? '') ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted small mb-0"><?= lang('Families.noMembers') ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
