<?php /* Partial: directory filter card. Required: $scope, $status, $selectedPerPage, $status_counts, $extra_filters */ ?>
<div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-end filter-panel-header">
        <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#peopleFilterPanel" aria-expanded="false">
            <i class="bi bi-funnel" aria-hidden="true"></i> <?= lang('App.filter') ?>
        </button>
    </div>
    <div id="peopleFilterPanel" class="collapse">
        <div class="card-body border-top">
            <form method="get" action="<?= base_url('people') ?>" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label mb-1"><?= lang('People.scopeFilter') ?></label>
                    <select name="scope" class="form-select form-select-sm">
                        <option value="all" <?= $scope === 'all' ? 'selected' : '' ?>><?= lang('People.scopeAll') ?></option>
                        <option value="members" <?= $scope === 'members' ? 'selected' : '' ?>><?= lang('People.scopeMembers') ?></option>
                        <option value="non_members" <?= $scope === 'non_members' ? 'selected' : '' ?>><?= lang('People.scopeNonMembers') ?></option>
                        <option value="visitors" <?= $scope === 'visitors' ? 'selected' : '' ?>><?= lang('People.scopeVisitors') ?></option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label mb-1"><?= lang('People.statusFilter') ?></label>
                    <select name="status" class="form-select form-select-sm">
                        <option value=""><?= lang('App.all') ?></option>
                        <?php foreach (($status_counts ?? []) as $row): ?>
                            <?php
                            $statusValue = strtolower(trim((string) ($row['membership_status'] ?? '')));
                            $statusLabel = \App\Services\MembershipStatusService::label($statusValue);
                            ?>
                            <option value="<?= esc($statusValue) ?>" <?= $status === $statusValue ? 'selected' : '' ?>>
                                <?= esc($statusLabel) ?> (<?= (int) ($row['count'] ?? 0) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1"><?= lang('People.perPage') ?></label>
                    <select name="per_page" class="form-select form-select-sm">
                        <option value="25" <?= $selectedPerPage === 25 ? 'selected' : '' ?>>25</option>
                        <option value="50" <?= $selectedPerPage === 50 ? 'selected' : '' ?>>50</option>
                        <option value="100" <?= $selectedPerPage === 100 ? 'selected' : '' ?>>100</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill"><?= lang('Services.apply') ?></button>
                    <a href="<?= base_url('people') ?>" class="btn btn-sm btn-outline-secondary flex-fill"><?= lang('App.clear') ?></a>
                </div>
                <?php $selectedFilters = $extra_filters ?? []; ?>
                <div class="col-12">
                    <hr class="my-2">
                    <div class="row g-2">
                        <?php
                        $filterBadges = [
                            'is_baptized'    => lang('People.filterBaptized'),
                            'is_confirmed'   => lang('People.filterConfirmed'),
                            'is_communicant' => lang('People.filterCommunicant'),
                            'is_dependent'   => lang('People.filterDependent'),
                            'is_under_16'    => lang('People.filterUnder16'),
                            'has_pledge'     => lang('People.filterHasPledge'),
                            'is_leader'      => lang('People.filterLeader'),
                        ];
                        ?>
                        <?php foreach ($filterBadges as $filterKey => $filterLabel): ?>
                            <div class="col-md-3 col-sm-6">
                                <div class="form-check form-check-sm">
                                    <input class="form-check-input" type="checkbox" name="<?= esc($filterKey) ?>" value="1" id="people-filter-<?= esc($filterKey) ?>" <?= !empty($selectedFilters[$filterKey]) ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="people-filter-<?= esc($filterKey) ?>"><?= esc($filterLabel) ?></label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
