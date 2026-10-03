<?php /* Partial: sacrament + sacrament-participant modals. Required: $profileId, $personId, $parish_affiliated_people */ ?>
<?php if (auth()->user()->can('member.edit') && $profileId > 0 && $personId > 0): ?>
<div class="modal fade" id="sacramentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="sacramentForm" method="post" action="<?= base_url('people/sacrament/' . $profileId) ?>">
                <?= csrf_field() ?>
                <input type="hidden" id="s_sacrament_id" name="sacrament_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="sacramentModalLabel"><?= lang('Sacraments.addSacrament') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= lang('App.close') ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="s_sacrament_type" class="form-label"><?= lang('Sacraments.type') ?> <span class="text-danger">*</span></label>
                            <select class="form-select" id="s_sacrament_type" name="sacrament_type" required>
                                <option value=""><?= lang('Sacraments.selectType') ?></option>
                                <option value="baptism"><?= lang('Sacraments.typeBaptism') ?></option>
                                <option value="confirmation"><?= lang('Sacraments.typeConfirmation') ?></option>
                                <option value="eucharist"><?= lang('Sacraments.typeEucharist') ?></option>
                                <option value="marriage"><?= lang('Sacraments.typeMarriage') ?></option>
                                <option value="burial"><?= lang('Sacraments.typeBurial') ?></option>
                                <option value="reception"><?= lang('Sacraments.typeReception') ?></option>
                                <option value="other"><?= lang('Sacraments.typeOther') ?></option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="s_sacrament_date" class="form-label"><?= lang('Sacraments.date') ?></label>
                            <input type="date" class="form-control" id="s_sacrament_date" name="sacrament_date">
                        </div>
                        <div class="col-md-6">
                            <label for="s_register_ref" class="form-label"><?= lang('Sacraments.registerRef') ?></label>
                            <input type="text" class="form-control" id="s_register_ref" name="register_ref" maxlength="100">
                        </div>
                        <div class="col-md-6">
                            <label for="s_certificate_number" class="form-label"><?= lang('Sacraments.certificateNumber') ?></label>
                            <input type="text" class="form-control" id="s_certificate_number" name="certificate_number" maxlength="100">
                        </div>
                        <div class="col-12">
                            <label for="s_officiant_person_id" class="form-label"><?= lang('Sacraments.officiant') ?></label>
                            <select class="form-select" id="s_officiant_person_id" name="officiant_person_id">
                                <option value=""><?= lang('Sacraments.selectOfficiant') ?></option>
                                <?php foreach (($parish_affiliated_people ?? []) as $pp): ?>
                                    <?php
                                    $ppPersonId = (int) ($pp['person_id'] ?? 0);
                                    if ($ppPersonId <= 0) {
                                        continue;
                                    }
                                    ?>
                                    <option value="<?= $ppPersonId ?>">
                                        <?= esc(($pp['last_name'] ?? '') . ', ' . ($pp['first_name'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="s_location_notes" class="form-label"><?= lang('Sacraments.locationNotes') ?></label>
                            <input type="text" class="form-control" id="s_location_notes" name="location_notes" maxlength="255">
                        </div>
                        <div class="col-12">
                            <label for="s_sacrament_notes" class="form-label"><?= lang('Sacraments.notes') ?></label>
                            <textarea class="form-control" id="s_sacrament_notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="submit" class="btn btn-primary" id="saveSacramentBtn"><?= lang('App.save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (auth()->user()->can('member.edit') && $profileId > 0 && $personId > 0): ?>
<div class="modal fade" id="sacramentParticipantModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="sacramentParticipantForm" method="post" action="<?= base_url('people/sacrament/0/participant') ?>">
                <?= csrf_field() ?>
                <input type="hidden" id="sp_sacrament_id" name="sacrament_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="sacramentParticipantModalLabel"><?= lang('Sacraments.addParticipant') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= lang('App.close') ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="sp_person_id" class="form-label"><?= lang('Sacraments.participant') ?> <span class="text-danger">*</span></label>
                        <select class="form-select" id="sp_person_id" name="person_id" required>
                            <option value=""><?= lang('Sacraments.selectParticipant') ?></option>
                            <?php foreach (($parish_affiliated_people ?? []) as $pp):
                                $ppPersonId = (int) ($pp['person_id'] ?? 0);
                                if ($ppPersonId <= 0) {
                                    continue;
                                }
                            ?>
                                <option value="<?= $ppPersonId ?>"><?= esc(($pp['last_name'] ?? '') . ', ' . ($pp['first_name'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="sp_participant_role" class="form-label"><?= lang('Sacraments.participantRole') ?> <span class="text-danger">*</span></label>
                        <select class="form-select" id="sp_participant_role" name="participant_role" required>
                            <?php foreach (['Godparent', 'Sponsor', 'Witness', 'Celebrant', 'Parent', 'Spouse', 'Other'] as $role): ?>
                                <option value="<?= $role ?>"><?= lang('Sacraments.participant' . $role) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="submit" class="btn btn-primary" id="saveSacramentParticipantBtn"><?= lang('App.save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
