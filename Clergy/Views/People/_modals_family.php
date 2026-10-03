<?php /* Partial: family create/join/add-member modals. Required: $profileId, $userId, $profile, $family, $family_members, $parish_families, $parish_affiliated_people */ ?>
<?php if (auth()->user()->can('family.manage') && $profileId > 0 && $userId > 0): ?>
<div class="modal fade" id="createFamilyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="createFamilyForm" method="post" action="<?= base_url('people/family/' . $profileId . '/create') ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="createFamilyModalLabel"><?= lang('Families.create') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= lang('App.close') ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="cf_family_name" class="form-label"><?= lang('Families.familyName') ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="cf_family_name" name="family_name" required minlength="3" maxlength="255"
                            value="<?= esc(($profile['last_name'] ?? '') . ' Family') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="cf_address" class="form-label"><?= lang('Families.address') ?></label>
                        <textarea class="form-control" id="cf_address" name="address" rows="3"><?= esc($profile['address'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="submit" class="btn btn-primary" id="saveCreateFamilyBtn"><?= lang('App.save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="joinFamilyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="joinFamilyForm" method="post" action="<?= base_url('people/family/' . $profileId . '/join') ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="joinFamilyModalLabel"><?= lang('Families.join') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= lang('App.close') ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="jf_family_id" class="form-label"><?= lang('Families.family') ?> <span class="text-danger">*</span></label>
                        <select class="form-select" id="jf_family_id" name="family_id" required>
                            <option value=""><?= lang('Families.selectFamily') ?></option>
                            <?php foreach (($parish_families ?? []) as $pf): ?>
                                <option value="<?= (int) ($pf['id'] ?? 0) ?>"><?= esc($pf['family_name'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="jf_relationship" class="form-label"><?= lang('Families.relationship') ?></label>
                        <select class="form-select" id="jf_relationship" name="relationship">
                            <?php foreach (['Head', 'Spouse', 'Child', 'Parent', 'Sibling', 'Other'] as $rel): ?>
                                <option value="<?= $rel ?>"><?= lang('Families.relationship' . $rel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="submit" class="btn btn-primary" id="saveJoinFamilyBtn"><?= lang('App.save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="addFamilyMemberModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="addFamilyMemberForm" method="post" action="<?= base_url('families/addMember') ?>">
                <?= csrf_field() ?>
                <input type="hidden" id="afm_family_id" name="family_id" value="<?= (int) ($family['id'] ?? 0) ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="addFamilyMemberModalLabel"><?= lang('Families.addMember') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= lang('App.close') ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="afm_user_id" class="form-label"><?= lang('Families.member') ?> <span class="text-danger">*</span></label>
                        <select class="form-select" id="afm_user_id" name="user_id" required>
                            <option value=""><?= lang('Families.selectMember') ?></option>
                            <?php
                            $currentFamilyUserIds = array_map(static fn(array $m): int => (int) ($m['user_id'] ?? 0), $family_members ?? []);
                            ?>
                            <?php foreach (($parish_affiliated_people ?? []) as $pp):
                                $ppUserId = (int) ($pp['user_id'] ?? 0);
                                if ($ppUserId <= 0 || in_array($ppUserId, $currentFamilyUserIds, true)) {
                                    continue;
                                }
                            ?>
                                <option value="<?= $ppUserId ?>"><?= esc(($pp['last_name'] ?? '') . ', ' . ($pp['first_name'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="afm_relationship" class="form-label"><?= lang('Families.relationship') ?></label>
                        <select class="form-select" id="afm_relationship" name="relationship">
                            <?php foreach (['Head', 'Spouse', 'Child', 'Parent', 'Sibling', 'Other'] as $rel): ?>
                                <option value="<?= $rel ?>"><?= lang('Families.relationship' . $rel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="submit" class="btn btn-primary" id="saveAddFamilyMemberBtn"><?= lang('App.save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
