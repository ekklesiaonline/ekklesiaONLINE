<?= $this->extend('Worship\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-start align-items-center mb-3">
    <a href="<?= base_url('services') ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> <?= lang('Services.backToServices') ?>
    </a>
</div>

<div class="card">
    <div class="card-body">
        <?php
        $metrics = $metrics ?? [];
        $servicePeople = $service_people ?? [];

        $languagesCsv = old('languages_csv');
        if ($languagesCsv === null) {
            $languagesCsv = implode(', ', $metrics['languages_json'] ?? []);
        }

        // Decode service array
        $selectedServices = $service['service'] ?? [];

        $oldPeopleNames = old('people_name');
        $oldPeopleRoles = old('people_role');
        if (is_array($oldPeopleNames) || is_array($oldPeopleRoles)) {
            $servicePeople = [];
            $oldPeopleNames = is_array($oldPeopleNames) ? $oldPeopleNames : [];
            $oldPeopleRoles = is_array($oldPeopleRoles) ? $oldPeopleRoles : [];
            $maxRows = max(count($oldPeopleNames), count($oldPeopleRoles));
            for ($i = 0; $i < $maxRows; $i++) {
                $servicePeople[] = [
                    'person_name' => $oldPeopleNames[$i] ?? '',
                    'role_name' => $oldPeopleRoles[$i] ?? '',
                ];
            }
        }

        if (empty($servicePeople)) {
            $servicePeople = [['person_name' => '', 'role_name' => '']];
        }

        $liturgicalSeasons = $liturgical_seasons ?? [];
        $liturgicalDays = $liturgical_days ?? [];
        $selectedLiturgicalSeason = old('liturgical_season', $selected_liturgical_season ?? '');
        $selectedLiturgicalDay = old('liturgical_day', $service['liturgical_day'] ?? '');

        $liturgicalDayExists = false;
        foreach ($liturgicalDays as $dayOption) {
            if (($dayOption['name'] ?? '') === $selectedLiturgicalDay) {
                $liturgicalDayExists = true;
                break;
            }
        }
        ?>

        <?php if (session('errors')): ?>
            <div class="alert alert-danger">
                <strong><?= lang('Services.pleaseFixFollowing') ?></strong>
                <ul class="mb-0 mt-2">
                    <?php foreach (session('errors') as $error): ?>
                        <li><?= esc($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="<?= base_url('services/update/' . $service['id']) ?>" method="post">
            <?= csrf_field() ?>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="date" class="form-label"><?= lang('Services.date') ?> <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="date" name="date" value="<?= old('date', esc($service['service_date'])) ?>" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="time" class="form-label"><?= lang('Services.time') ?></label>
                            <input type="time" class="form-control" id="time" name="time" value="<?= old('time', esc($service['service_time'] ?? '')) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="place" class="form-label"><?= lang('Services.place') ?></label>
                            <input type="text" class="form-control" id="place" name="place" value="<?= old('place', esc($service['place'] ?? '')) ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="service" class="form-label"><?= lang('Services.serviceTypeWithPlural') ?></label>
                        <select class="form-select" id="service" name="service[]" multiple size="5">
                            <optgroup label="<?= lang('Services.mainServices') ?>">
                                <option value="Holy Eucharist Rite I" <?= in_array('Holy Eucharist Rite I', $selectedServices) ? 'selected' : '' ?>><?= lang('Services.holyEucharistRiteI') ?></option>
                                <option value="Holy Eucharist Rite II" <?= in_array('Holy Eucharist Rite II', $selectedServices) ? 'selected' : '' ?>><?= lang('Services.holyEucharistRiteII') ?></option>
                                <option value="Morning Prayer Rite I" <?= in_array('Morning Prayer Rite I', $selectedServices) ? 'selected' : '' ?>><?= lang('Services.morningPrayerRiteI') ?></option>
                                <option value="Morning Prayer Rite II" <?= in_array('Morning Prayer Rite II', $selectedServices) ? 'selected' : '' ?>><?= lang('Services.morningPrayerRiteII') ?></option>
                                <option value="Evening Prayer Rite I" <?= in_array('Evening Prayer Rite I', $selectedServices) ? 'selected' : '' ?>><?= lang('Services.eveningPrayerRiteI') ?></option>
                                <option value="Evening Prayer Rite II" <?= in_array('Evening Prayer Rite II', $selectedServices) ? 'selected' : '' ?>><?= lang('Services.eveningPrayerRiteII') ?></option>
                                <option value="Compline" <?= in_array('Compline', $selectedServices) ? 'selected' : '' ?>><?= lang('Services.compline') ?></option>
                                <option value="Noonday Prayer" <?= in_array('Noonday Prayer', $selectedServices) ? 'selected' : '' ?>><?= lang('Services.noondayPrayer') ?></option>
                            </optgroup>
                            <optgroup label="<?= lang('Services.sacramentalPastoral') ?>">
                                <option value="Baptism" <?= in_array('Baptism', $selectedServices) ? 'selected' : '' ?>><?= lang('Services.baptism') ?></option>
                                <option value="Confirmation" <?= in_array('Confirmation', $selectedServices) ? 'selected' : '' ?>><?= lang('Services.confirmation') ?></option>
                                <option value="Marriage" <?= in_array('Marriage', $selectedServices) ? 'selected' : '' ?>><?= lang('Services.marriage') ?></option>
                                <option value="Burial" <?= in_array('Burial', $selectedServices) ? 'selected' : '' ?>><?= lang('Services.burial') ?></option>
                                <option value="Healing Service" <?= in_array('Healing Service', $selectedServices) ? 'selected' : '' ?>><?= lang('Services.healingService') ?></option>
                                <option value="Reconciliation" <?= in_array('Reconciliation', $selectedServices) ? 'selected' : '' ?>><?= lang('Services.reconciliation') ?></option>
                            </optgroup>
                            <optgroup label="<?= lang('Services.specialLiturgies') ?>">
                                <option value="Ash Wednesday" <?= in_array('Ash Wednesday', $selectedServices) ? 'selected' : '' ?>><?= lang('Services.ashWednesday') ?></option>
                                <option value="Maundy Thursday" <?= in_array('Maundy Thursday', $selectedServices) ? 'selected' : '' ?>><?= lang('Services.maundyThursday') ?></option>
                                <option value="Good Friday" <?= in_array('Good Friday', $selectedServices) ? 'selected' : '' ?>><?= lang('Services.goodFriday') ?></option>
                                <option value="Holy Saturday" <?= in_array('Holy Saturday', $selectedServices) ? 'selected' : '' ?>><?= lang('Services.holySaturday') ?></option>
                                <option value="Easter Vigil" <?= in_array('Easter Vigil', $selectedServices) ? 'selected' : '' ?>><?= lang('Services.easterVigil') ?></option>
                                <option value="Ascension Day" <?= in_array('Ascension Day', $selectedServices) ? 'selected' : '' ?>><?= lang('Services.ascensionDay') ?></option>
                                <option value="Pentecost" <?= in_array('Pentecost', $selectedServices) ? 'selected' : '' ?>><?= lang('Services.pentecost') ?></option>
                                <option value="All Saints" <?= in_array('All Saints', $selectedServices) ? 'selected' : '' ?>><?= lang('Services.allSaints') ?></option>
                                <option value="Christmas Eve" <?= in_array('Christmas Eve', $selectedServices) ? 'selected' : '' ?>><?= lang('Services.christmasEve') ?></option>
                                <option value="Christmas Day" <?= in_array('Christmas Day', $selectedServices) ? 'selected' : '' ?>><?= lang('Services.christmasDay') ?></option>
                                <option value="Epiphany" <?= in_array('Epiphany', $selectedServices) ? 'selected' : '' ?>><?= lang('Services.epiphany') ?></option>
                            </optgroup>
                            <optgroup label="<?= lang('Services.otherGroup') ?>">
                                <option value="Stations of the Cross" <?= in_array('Stations of the Cross', $selectedServices) ? 'selected' : '' ?>><?= lang('Services.stationsOfTheCross') ?></option>
                                <option value="Benediction" <?= in_array('Benediction', $selectedServices) ? 'selected' : '' ?>><?= lang('Services.benediction') ?></option>
                                <option value="Ordination" <?= in_array('Ordination', $selectedServices) ? 'selected' : '' ?>><?= lang('Services.ordination') ?></option>
                                <option value="Consecration" <?= in_array('Consecration', $selectedServices) ? 'selected' : '' ?>><?= lang('Services.consecration') ?></option>
                                <option value="Other Service" <?= in_array('Other Service', $selectedServices) ? 'selected' : '' ?>><?= lang('Services.otherService') ?></option>
                            </optgroup>
                        </select>
                        <small class="form-text text-muted"><?= lang('Services.holdCtrlSelectMultiple') ?></small>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="liturgical_season" class="form-label"><?= lang('Services.liturgicalSeason') ?></label>
                        <select class="form-select" id="liturgical_season" name="liturgical_season">
                            <option value=""><?= lang('Services.selectSeason') ?></option>
                            <?php foreach ($liturgicalSeasons as $season): ?>
                                <option value="<?= esc($season) ?>" <?= $selectedLiturgicalSeason === $season ? 'selected' : '' ?>>
                                    <?= esc(liturgical_season_label((string) $season)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="liturgical_day" class="form-label"><?= lang('Services.liturgicalDayOrFeast') ?></label>
                        <select class="form-select" id="liturgical_day" name="liturgical_day" data-selected="<?= esc($selectedLiturgicalDay) ?>">
                            <option value=""><?= lang('Services.selectLiturgicalDay') ?></option>
                            <?php if (!$liturgicalDayExists && $selectedLiturgicalDay !== ''): ?>
                                <option value="<?= esc($selectedLiturgicalDay) ?>" selected><?= esc(liturgical_day_label($selectedLiturgicalDay)) ?> (<?= lang('Services.customSuffix') ?>)</option>
                            <?php endif; ?>
                            <?php foreach ($liturgicalDays as $day): ?>
                                <?php $dayName = (string) ($day['name'] ?? ''); ?>
                                <?php $dayColor = (string) ($day['liturgical_color'] ?? ''); ?>
                                <option value="<?= esc($dayName) ?>" <?= $selectedLiturgicalDay === $dayName ? 'selected' : '' ?>>
                                    <?= esc(liturgical_day_label($dayName)) ?><?= $dayColor !== '' ? ' (' . esc(liturgical_color_label($dayColor)) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted"><?= lang('Services.liturgicalFilteredHint') ?></small>
                    </div>

                    <div class="mb-3">
                        <label for="preacher" class="form-label"><?= lang('Services.preacher') ?></label>
                        <input type="text" class="form-control" id="preacher" name="preacher" value="<?= old('preacher', esc($service['preacher'] ?? '')) ?>">
                    </div>

                    <div class="mb-3">
                        <label for="celebrant" class="form-label"><?= lang('Services.celebrant') ?></label>
                        <input type="text" class="form-control" id="celebrant" name="celebrant" value="<?= old('celebrant', esc($service['celebrant'] ?? '')) ?>">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="mb-3">
                        <label for="notes" class="form-label"><?= lang('Services.notes') ?></label>
                        <textarea class="form-control" id="notes" name="notes" rows="4"><?= old('notes', esc($service['notes'] ?? '')) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label d-block"><?= lang('Services.formatOfService') ?></label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="format_of_service" id="format_in_person" value="In-Person" <?= old('format_of_service', $metrics['format_of_service'] ?? '') === 'In-Person' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="format_in_person"><?= lang('Services.formatInPerson') ?></label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="format_of_service" id="format_hybrid" value="Hybrid" <?= old('format_of_service', $metrics['format_of_service'] ?? '') === 'Hybrid' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="format_hybrid"><?= lang('Services.formatHybrid') ?></label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="format_of_service" id="format_online" value="Online" <?= old('format_of_service', $metrics['format_of_service'] ?? '') === 'Online' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="format_online"><?= lang('Services.formatOnline') ?></label>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label d-block"><?= lang('Services.attendanceClassification') ?></label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="attendance_classification" id="classification_regular" value="Regular" <?= old('attendance_classification', $metrics['attendance_classification'] ?? '') === 'Regular' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="classification_regular"><?= lang('Services.classificationRegular') ?></label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="attendance_classification" id="classification_special" value="Special" <?= old('attendance_classification', $metrics['attendance_classification'] ?? '') === 'Special' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="classification_special"><?= lang('Services.classificationSpecial') ?></label>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label d-block"><?= lang('Services.serviceFamily') ?></label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="service_family" id="family_holy_communion" value="holy_communion" <?= old('service_family', $metrics['service_family'] ?? '') === 'holy_communion' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="family_holy_communion"><?= lang('Services.familyHolyCommunion') ?></label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="service_family" id="family_daily_office" value="daily_office" <?= old('service_family', $metrics['service_family'] ?? '') === 'daily_office' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="family_daily_office"><?= lang('Services.familyDailyOffice') ?></label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="service_family" id="family_other" value="other" <?= old('service_family', $metrics['service_family'] ?? '') === 'other' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="family_other"><?= lang('Services.familyOther') ?></label>
                    </div>
                    <small class="form-text text-muted d-block"><?= lang('Services.primaryServiceCategoryHint') ?></small>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label d-block"><?= lang('Services.serviceSchedule') ?></label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="service_schedule" id="schedule_sunday" value="sunday" <?= old('service_schedule', $metrics['service_schedule'] ?? '') === 'sunday' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="schedule_sunday"><?= lang('Services.scheduleSunday') ?></label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="service_schedule" id="schedule_weekday" value="weekday" <?= old('service_schedule', $metrics['service_schedule'] ?? '') === 'weekday' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="schedule_weekday"><?= lang('Services.scheduleWeekday') ?></label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="service_schedule" id="schedule_private" value="private" <?= old('service_schedule', $metrics['service_schedule'] ?? '') === 'private' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="schedule_private"><?= lang('Services.schedulePrivate') ?></label>
                    </div>
                    <small class="form-text text-muted d-block"><?= lang('Services.privateOnlyHolyCommunionHint') ?></small>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="reported_present_count" class="form-label"><?= lang('Services.reportedPresentCount') ?></label>
                    <input type="number" min="0" class="form-control" id="reported_present_count" name="reported_present_count" value="<?= old('reported_present_count', $metrics['reported_present_count'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="reported_communion_count" class="form-label"><?= lang('Services.reportedCommunionCount') ?></label>
                    <input type="number" min="0" class="form-control" id="reported_communion_count" name="reported_communion_count" value="<?= old('reported_communion_count', $metrics['reported_communion_count'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3 online-field" style="display: none;">
                    <label for="online_live_views" class="form-label"><?= lang('Services.onlineLiveViews') ?></label>
                    <input type="number" min="0" class="form-control" id="online_live_views" name="online_live_views" value="<?= old('online_live_views', $metrics['online_live_views'] ?? '') ?>">
                </div>
            </div>

            <div class="row online-field" style="display: none;">
                <div class="col-md-6 mb-3">
                    <label for="online_recorded_views_7d" class="form-label"><?= lang('Services.onlineRecordedViews7d') ?></label>
                    <input type="number" min="0" class="form-control" id="online_recorded_views_7d" name="online_recorded_views_7d" value="<?= old('online_recorded_views_7d', $metrics['online_recorded_views_7d'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="online_peak_viewers" class="form-label"><?= lang('Services.onlinePeakViewers') ?></label>
                    <input type="number" min="0" class="form-control" id="online_peak_viewers" name="online_peak_viewers" value="<?= old('online_peak_viewers', $metrics['online_peak_viewers'] ?? '') ?>">
                </div>
            </div>

            <div class="row">
                <div class="col-md-12 mb-3">
                    <label for="languages_csv" class="form-label"><?= lang('Services.languagesCommaSeparated') ?></label>
                    <input type="text" class="form-control" id="languages_csv" name="languages_csv" value="<?= esc($languagesCsv) ?>" placeholder="<?= lang('Services.languagesPlaceholder') ?>">
                </div>
            </div>

            <hr class="my-4">

            <h5 class="mb-3"><?= lang('Services.sacramentalCounts') ?></h5>
            <div class="row">
                <div class="col-md-2 mb-3">
                    <label for="baptism_adult" class="form-label"><?= lang('Services.baptismAdult') ?></label>
                    <input type="number" min="0" class="form-control" id="baptism_adult" name="baptism_adult" value="<?= old('baptism_adult', $metrics['baptism_adult'] ?? '') ?>">
                </div>
                <div class="col-md-2 mb-3">
                    <label for="baptism_child" class="form-label"><?= lang('Services.baptismChild') ?></label>
                    <input type="number" min="0" class="form-control" id="baptism_child" name="baptism_child" value="<?= old('baptism_child', $metrics['baptism_child'] ?? '') ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="confirmation_adult" class="form-label"><?= lang('Services.confirmationAdult') ?></label>
                    <input type="number" min="0" class="form-control" id="confirmation_adult" name="confirmation_adult" value="<?= old('confirmation_adult', $metrics['confirmation_adult'] ?? '') ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="confirmation_teen" class="form-label"><?= lang('Services.confirmationTeen') ?></label>
                    <input type="number" min="0" class="form-control" id="confirmation_teen" name="confirmation_teen" value="<?= old('confirmation_teen', $metrics['confirmation_teen'] ?? '') ?>">
                </div>
                <div class="col-md-2 mb-3">
                    <label for="reception_count" class="form-label"><?= lang('Services.reception') ?></label>
                    <input type="number" min="0" class="form-control" id="reception_count" name="reception_count" value="<?= old('reception_count', $metrics['reception_count'] ?? '') ?>">
                </div>
                <div class="col-md-2 mb-3">
                    <label for="marriage_count" class="form-label"><?= lang('Services.marriageCount') ?></label>
                    <input type="number" min="0" class="form-control" id="marriage_count" name="marriage_count" value="<?= old('marriage_count', $metrics['marriage_count'] ?? '') ?>">
                </div>
                <div class="col-md-2 mb-3">
                    <label for="burial_count" class="form-label"><?= lang('Services.burialCount') ?></label>
                    <input type="number" min="0" class="form-control" id="burial_count" name="burial_count" value="<?= old('burial_count', $metrics['burial_count'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="eucharist_count" class="form-label"><?= lang('Services.eucharistCount') ?></label>
                    <input type="number" min="0" class="form-control" id="eucharist_count" name="eucharist_count" value="<?= old('eucharist_count', $metrics['eucharist_count'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="daily_office_count" class="form-label"><?= lang('Services.dailyOfficeCount') ?></label>
                    <input type="number" min="0" class="form-control" id="daily_office_count" name="daily_office_count" value="<?= old('daily_office_count', $metrics['daily_office_count'] ?? '') ?>">
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="online_live_viewers" class="form-label"><?= lang('Services.onlineLiveViewers') ?></label>
                    <input type="number" min="0" class="form-control" id="online_live_viewers" name="online_live_viewers" value="<?= old('online_live_viewers', $metrics['online_live_viewers'] ?? '') ?>">
                    <div class="form-text"><?= lang('App.parochialReportField') ?></div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="online_recorded_views" class="form-label"><?= lang('Services.onlineRecordedViews') ?></label>
                    <input type="number" min="0" class="form-control" id="online_recorded_views" name="online_recorded_views" value="<?= old('online_recorded_views', $metrics['online_recorded_views'] ?? '') ?>">
                    <div class="form-text"><?= lang('App.parochialReportField') ?></div>
                </div>

                <hr class="my-4">

                <h5 class="mb-3"><?= lang('Services.peopleAndRoles') ?></h5>
                <div id="people-rows" class="d-flex flex-column gap-2">
                    <?php foreach ($servicePeople as $person): ?>
                        <div class="row g-2 people-row">
                            <div class="col-md-6">
                                <input type="text" class="form-control" name="people_name[]" value="<?= esc($person['person_name'] ?? '') ?>" placeholder="<?= lang('Services.personName') ?>">
                            </div>
                            <div class="col-md-5">
                                <input type="text" class="form-control" name="people_role[]" value="<?= esc($person['role_name'] ?? '') ?>" placeholder="<?= lang('Services.role') ?>">
                            </div>
                            <div class="col-md-1 d-grid">
                                <button type="button" class="btn btn-outline-danger remove-person">&times;</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" id="add-person" class="btn btn-outline-secondary btn-sm mt-2">
                    <i class="bi bi-plus-circle"></i> <?= lang('Services.addPerson') ?>
                </button>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="<?= base_url('services') ?>" class="btn btn-secondary"><?= lang('App.cancel') ?></a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> <?= lang('Services.updateService') ?>
                    </button>
                </div>
        </form>
    </div>
</div>

<script>
    (function() {
        const peopleRows = document.getElementById('people-rows');
        const addBtn = document.getElementById('add-person');
        const seasonSelect = document.getElementById('liturgical_season');
        const daySelect = document.getElementById('liturgical_day');
        const liturgicalEndpoint = '<?= base_url('services/liturgical-days') ?>';
        const liturgicalColorLabels = <?= json_encode([
                                            'blue' => liturgical_color_label('blue'),
                                            'purple' => liturgical_color_label('purple'),
                                            'white' => liturgical_color_label('white'),
                                            'green' => liturgical_color_label('green'),
                                            'red' => liturgical_color_label('red'),
                                            'rose' => liturgical_color_label('rose'),
                                            'black' => liturgical_color_label('black'),
                                            'gold' => liturgical_color_label('gold'),
                                        ]) ?>;

        function wireRemoveButton(button) {
            button.addEventListener('click', function() {
                const rows = peopleRows.querySelectorAll('.people-row');
                if (rows.length <= 1) {
                    return;
                }

                this.closest('.people-row').remove();
                refreshRemoveButtons();
            });
        }

        function refreshRemoveButtons() {
            const rows = peopleRows.querySelectorAll('.people-row');
            rows.forEach((row, idx) => {
                const btn = row.querySelector('.remove-person');
                btn.disabled = rows.length === 1 && idx === 0;
            });
        }

        addBtn.addEventListener('click', function() {
            const row = document.createElement('div');
            row.className = 'row g-2 people-row';
            row.innerHTML = `
            <div class="col-md-6">
                <input type="text" class="form-control" name="people_name[]" placeholder="<?= esc(lang('Services.personName'), 'js') ?>">
            </div>
            <div class="col-md-5">
                <input type="text" class="form-control" name="people_role[]" placeholder="<?= esc(lang('Services.role'), 'js') ?>">
            </div>
            <div class="col-md-1 d-grid">
                <button type="button" class="btn btn-outline-danger remove-person">&times;</button>
            </div>
        `;

            peopleRows.appendChild(row);
            wireRemoveButton(row.querySelector('.remove-person'));
            refreshRemoveButtons();
        });

        peopleRows.querySelectorAll('.remove-person').forEach(wireRemoveButton);
        refreshRemoveButtons();

        async function loadLiturgicalDays(season, selectedDay = '') {
            daySelect.innerHTML = '<option value=""><?= esc(lang('Services.selectLiturgicalDay'), 'js') ?></option>';
            if (!season) {
                return;
            }

            try {
                const response = await fetch(`${liturgicalEndpoint}?season=${encodeURIComponent(season)}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!response.ok) {
                    return;
                }

                const payload = await response.json();
                const items = Array.isArray(payload.items) ? payload.items : [];
                let foundSelected = false;

                items.forEach((item) => {
                    const option = document.createElement('option');
                    const dayName = String(item.name || '');
                    const dayLabel = String(item.label || dayName);
                    const rawColor = String(item.liturgical_color || '').toLowerCase();
                    const translatedColor = liturgicalColorLabels[rawColor] || (rawColor ? rawColor.charAt(0).toUpperCase() + rawColor.slice(1) : '');
                    const colorLabel = translatedColor ? ` (${translatedColor})` : '';
                    option.value = dayName;
                    option.textContent = `${dayLabel}${colorLabel}`;

                    if (selectedDay && option.value === selectedDay) {
                        option.selected = true;
                        foundSelected = true;
                    }

                    daySelect.appendChild(option);
                });

                if (selectedDay && !foundSelected) {
                    const customOption = document.createElement('option');
                    customOption.value = selectedDay;
                    customOption.textContent = `${selectedDay} (<?= esc(lang('Services.customSuffix'), 'js') ?>)`;
                    customOption.selected = true;
                    daySelect.appendChild(customOption);
                }
            } catch (error) {
                // Silently keep fallback option when fetch fails.
            }
        }

        seasonSelect.addEventListener('change', function() {
            loadLiturgicalDays(this.value, '');
        });

        if (seasonSelect.value) {
            loadLiturgicalDays(seasonSelect.value, daySelect.dataset.selected || '');
        }
    })();

    // Service Schedule: Enable Private only when Holy Communion is selected
    (function() {
        const holyCommunionRadio = document.getElementById('family_holy_communion');
        const otherRadio = document.getElementById('family_other');
        const privateScheduleRadio = document.getElementById('schedule_private');
        const privateLabel = privateScheduleRadio.closest('.form-check-inline');

        function updatePrivateScheduleState() {
            if (holyCommunionRadio.checked) {
                privateScheduleRadio.disabled = false;
                privateLabel.style.opacity = '1';
            } else {
                privateScheduleRadio.disabled = true;
                privateLabel.style.opacity = '0.5';
                // Uncheck if currently selected
                if (privateScheduleRadio.checked) {
                    privateScheduleRadio.checked = false;
                }
            }
        }

        // Listen for changes
        holyCommunionRadio.addEventListener('change', updatePrivateScheduleState);
        otherRadio.addEventListener('change', updatePrivateScheduleState);

        // Set initial state
        updatePrivateScheduleState();
    })();

    // Format of Service: Show/hide online fields based on format selection
    (function() {
        const formatRadios = document.querySelectorAll('input[name="format_of_service"]');
        const onlineFields = document.querySelectorAll('.online-field');

        function updateOnlineFieldsVisibility() {
            const selectedFormat = document.querySelector('input[name="format_of_service"]:checked')?.value;
            const shouldShow = selectedFormat === 'Hybrid' || selectedFormat === 'Online';

            onlineFields.forEach(field => {
                field.style.display = shouldShow ? '' : 'none';
                // Clear values when hiding fields
                if (!shouldShow) {
                    const inputs = field.querySelectorAll('input');
                    inputs.forEach(input => {
                        input.value = '';
                    });
                }
            });
        }

        // Listen for changes
        formatRadios.forEach(radio => {
            radio.addEventListener('change', updateOnlineFieldsVisibility);
        });

        // Set initial state
        updateOnlineFieldsVisibility();
    })();

    // Service Family: auto-fill eucharist_count / daily_office_count
    // Only fills blank/zero fields; uses data-auto-set to avoid clobbering manual entries.
    (function() {
        const familyRadios = document.querySelectorAll('input[name="service_family"]');
        const eucharistInput = document.getElementById('eucharist_count');
        const dailyOfficeInput = document.getElementById('daily_office_count');

        if (!eucharistInput || !dailyOfficeInput) {
            return;
        }

        // Typing removes the auto-set flag so we know the user owns the value
        [eucharistInput, dailyOfficeInput].forEach(function(input) {
            input.addEventListener('input', function() {
                this.removeAttribute('data-auto-set');
            });
        });

        familyRadios.forEach(function(radio) {
            radio.addEventListener('change', function() {
                var family = this.value;

                if (family === 'holy_communion') {
                    if (!eucharistInput.value || eucharistInput.value === '0') {
                        eucharistInput.value = '1';
                        eucharistInput.setAttribute('data-auto-set', '1');
                    }
                    if (dailyOfficeInput.getAttribute('data-auto-set')) {
                        dailyOfficeInput.value = '';
                        dailyOfficeInput.removeAttribute('data-auto-set');
                    }
                } else if (family === 'daily_office') {
                    if (!dailyOfficeInput.value || dailyOfficeInput.value === '0') {
                        dailyOfficeInput.value = '1';
                        dailyOfficeInput.setAttribute('data-auto-set', '1');
                    }
                    if (eucharistInput.getAttribute('data-auto-set')) {
                        eucharistInput.value = '';
                        eucharistInput.removeAttribute('data-auto-set');
                    }
                } else {
                    if (eucharistInput.getAttribute('data-auto-set')) {
                        eucharistInput.value = '';
                        eucharistInput.removeAttribute('data-auto-set');
                    }
                    if (dailyOfficeInput.getAttribute('data-auto-set')) {
                        dailyOfficeInput.value = '';
                        dailyOfficeInput.removeAttribute('data-auto-set');
                    }
                }
            });
        });
    })();
</script>

<?= $this->endSection() ?>