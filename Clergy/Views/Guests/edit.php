<?= $this->extend('Clergy\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-start align-items-center mb-3">
    <a href="<?= base_url('guests/dashboard') ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= lang('Guests.backToGuests') ?>
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="<?= base_url('guests/update/' . $guest['id']) ?>" method="post">
            <?= csrf_field() ?>

            <div class="row">
                <div class="col-md-6">
                    <h5 class="mb-3"><?= lang('Guests.personalInformation') ?></h5>

                    <div class="mb-3">
                        <label for="first_name" class="form-label"><?= lang('Guests.firstName') ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="first_name" name="first_name" value="<?= esc($guest['first_name']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="last_name" class="form-label"><?= lang('Guests.lastName') ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="last_name" name="last_name" value="<?= esc($guest['last_name']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label"><?= lang('Guests.email') ?></label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= esc($guest['email']) ?>">
                    </div>

                    <div class="mb-3">
                        <label for="phone" class="form-label"><?= lang('Guests.phone') ?></label>
                        <input type="tel" class="form-control" id="phone" name="phone" value="<?= esc($guest['phone']) ?>">
                    </div>

                    <div class="mb-3">
                        <label for="visit_date" class="form-label"><?= lang('Guests.visitDate') ?> <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="visit_date" name="visit_date" value="<?= esc($guest['visit_date']) ?>" required>
                    </div>

                    <div class="mb-3 form-check">
                        <input class="form-check-input" type="checkbox" id="followed_up" name="followed_up" value="1" <?= !empty($guest['followed_up']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="followed_up"><?= lang('Guests.followUpCompleted') ?></label>
                    </div>
                </div>

                <div class="col-md-6">
                    <h5 class="mb-3"><?= lang('Guests.visitInformation') ?></h5>

                    <div class="mb-3">
                        <label for="service_attended" class="form-label"><?= lang('Guests.serviceAttended') ?></label>
                        <input type="text" class="form-control" id="service_attended" name="service_attended" value="<?= esc($guest['service_attended'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <hr class="my-4">

            <div class="row">
                <div class="col-md-6">
                    <h5 class="mb-3"><?= lang('Guests.additionalInformation') ?></h5>

                    <div class="mb-3">
                        <label for="source" class="form-label"><?= lang('Guests.sourceQuestion') ?></label>
                        <select class="form-select" id="source" name="source">
                            <option value=""><?= lang('Guests.selectSource') ?></option>
                            <option value="website" <?= $guest['source'] == 'website' ? 'selected' : '' ?>><?= lang('Guests.sourceWebsite') ?></option>
                            <option value="social_media" <?= $guest['source'] == 'social_media' ? 'selected' : '' ?>><?= lang('Guests.sourceSocialMedia') ?></option>
                            <option value="friend" <?= $guest['source'] == 'friend' ? 'selected' : '' ?>><?= lang('Guests.sourceFriendFamily') ?></option>
                            <option value="flyer" <?= $guest['source'] == 'flyer' ? 'selected' : '' ?>><?= lang('Guests.sourceFlyerAdvertisement') ?></option>
                            <option value="event" <?= $guest['source'] == 'event' ? 'selected' : '' ?>><?= lang('Guests.sourceSpecialEvent') ?></option>
                            <option value="other" <?= $guest['source'] == 'other' ? 'selected' : '' ?>><?= lang('Guests.sourceOther') ?></option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="interests" class="form-label"><?= lang('Guests.interestsMinistries') ?></label>
                        <textarea class="form-control" id="interests" name="interests" rows="3"><?= esc($guest['interests'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Communication Opt-Outs</label>
                        <div class="d-flex flex-wrap gap-3">
                            <?php
                            $optedOutRaw = $guest['opted_out_channels'] ?? '[]';
                            $optedOut    = is_array($optedOutRaw) ? $optedOutRaw : (json_decode((string) $optedOutRaw, true) ?: []);
                            $channels    = ['email' => 'Email', 'text' => 'Text / SMS', 'call' => 'Phone Call', 'mail' => 'Postal Mail'];
                            foreach ($channels as $key => $label): ?>
                                <div class="form-check">
                                    <input class="form-check-input opted-out-channel"
                                        type="checkbox"
                                        id="optout_<?= $key ?>"
                                        value="<?= $key ?>"
                                        <?= in_array($key, $optedOut, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="optout_<?= $key ?>"><?= esc($label) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="opted_out_channels" id="opted_out_channels_input" value="<?= esc(json_encode($optedOut)) ?>">
                        <small class="text-muted">Check channels this guest has asked <strong>not</strong> to be contacted through.</small>
                    </div>

                    <div class="mb-3">
                        <label for="preferred_timezone" class="form-label">Preferred Timezone</label>
                        <select class="form-select" id="preferred_timezone" name="preferred_timezone">
                            <option value="">— Use server default —</option>
                            <?php
                            $guestTz = $guest['preferred_timezone'] ?? '';
                            $tzGroups = [
                                'Americas' => [
                                    'America/New_York'     => 'Eastern (ET)',
                                    'America/Chicago'      => 'Central (CT)',
                                    'America/Denver'       => 'Mountain (MT)',
                                    'America/Los_Angeles'  => 'Pacific (PT)',
                                    'America/Santo_Domingo' => 'Atlantic / Santo Domingo',
                                    'America/Puerto_Rico'  => 'Puerto Rico (AST)',
                                    'America/Mexico_City'  => 'Mexico City (CST)',
                                    'America/Bogota'       => 'Bogotá (COT)',
                                    'America/Sao_Paulo'    => 'São Paulo (BRT)',
                                ],
                                'Europe' => [
                                    'Europe/London'   => 'London (GMT/BST)',
                                    'Europe/Madrid'   => 'Madrid (CET/CEST)',
                                ],
                                'Other' => [
                                    'UTC' => 'UTC',
                                ],
                            ];
                            foreach ($tzGroups as $group => $zones): ?>
                                <optgroup label="<?= esc($group) ?>">
                                    <?php foreach ($zones as $tz => $name): ?>
                                        <option value="<?= esc($tz) ?>" <?= $guestTz === $tz ? 'selected' : '' ?>><?= esc($name) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="col-md-6">
                    <h5 class="mb-3"><?= lang('Guests.notes') ?></h5>

                    <div class="mb-3">
                        <label for="family_info" class="form-label"><?= lang('Guests.familyInformation') ?></label>
                        <textarea class="form-control" id="family_info" name="family_info" rows="3"><?= esc($guest['family_info'] ?? '') ?></textarea>
                        <small class="text-muted"><?= lang('Guests.familyInformationHint') ?></small>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label"><?= lang('Guests.additionalNotes') ?></label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?= esc($guest['notes']) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="<?= base_url('guests') ?>" class="btn btn-secondary"><?= lang('App.cancel') ?></a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save" aria-hidden="true"></i> <?= lang('Guests.updateGuest') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>

<script>
    (function() {
        const form = document.querySelector('form[action*="guests/update"]');
        if (!form) return;

        form.addEventListener('submit', function() {
            const checked = [...document.querySelectorAll('.opted-out-channel:checked')].map(cb => cb.value);
            document.getElementById('opted_out_channels_input').value = JSON.stringify(checked);
        });

        // Keep hidden field in sync on checkbox change for UX clarity.
        document.querySelectorAll('.opted-out-channel').forEach(cb => {
            cb.addEventListener('change', function() {
                const checked = [...document.querySelectorAll('.opted-out-channel:checked')].map(c => c.value);
                document.getElementById('opted_out_channels_input').value = JSON.stringify(checked);
            });
        });
    })();
</script>