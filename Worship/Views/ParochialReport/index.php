<?= $this->extend('Worship\Views\layouts/main') ?>

<?= $this->section('content') ?>

<?php
$summary = $summary ?? [];
$parish = $parish ?? [];
$attendance = $attendance ?? [];
$membership = $membership ?? [];
$clergy = $clergy ?? [];
$sacraments = $sacraments ?? [];
$finance = $finance ?? [];
$languagesUsed = $languages_used ?? [];
$year = $year ?? ((int) date('Y') - 1);
$years = $available_years ?? [];
$isFinalized = (bool) ($is_finalized ?? false);
$usingFrozenSnapshot = (bool) ($using_frozen_snapshot ?? false);
$hasFrozenSnapshot = (bool) ($has_frozen_snapshot ?? false);
$forceRecompute = (bool) ($force_recompute ?? false);
$pdfEngine = 'dompdf';
$recomputeUrl = base_url('parochial-report?year=' . (int) $year . '&recompute=1&pdf_engine=' . rawurlencode($pdfEngine));

if ($years === []) {
    $years = [$year];
}

if (!in_array($year, $years, true)) {
    array_unshift($years, $year);
}

$value = static fn(string $key, $default = '') => $summary[$key] ?? $default;
$selectedPrograms = $summary['programs_list'] ?? [];
$selectedSubsidiaries = $summary['subsidiary_organizations_list'] ?? [];
$selectedClergySituations = $summary['clergy_situation_list'] ?? [];

$outreachBeneficiaries = (int) ($value('outreach_beneficiaries_weekly', 0) ?? 0);
$nonMemberVolunteers = (int) ($value('nonmember_volunteers_weekly', 0) ?? 0);
$totalImpact = (int) ($attendance['avg_sunday_inperson'] ?? 0)
    + (int) ($attendance['avg_sunday_online'] ?? 0)
    + (int) ($attendance['avg_weekday_inperson'] ?? 0)
    + (int) ($attendance['avg_weekday_online'] ?? 0)
    + (int) ($attendance['avg_nonlive_views'] ?? 0)
    + $outreachBeneficiaries
    + $nonMemberVolunteers;

$membershipPrevious = (int) ($value('membership_previous_total', $membership['member_total'] ?? 0) ?? 0);
$membershipIncrease = (int) ($value('membership_increase_total', 0) ?? 0);
$membershipDecrease = (int) ($value('membership_decrease_total', 0) ?? 0);
$membershipCurrent = $membershipPrevious + $membershipIncrease - $membershipDecrease;

$otherRevenue = (float) ($value('other_revenue_amount', 0) ?? 0);
$totalRevenue = (float) ($finance['revenue_unrestricted'] ?? 0)
    + (float) ($finance['revenue_restricted'] ?? 0)
    + $otherRevenue;
$totalExpenses = (float) ($finance['expenses_noncapital'] ?? 0)
    + (float) ($finance['expenses_capital'] ?? 0);

$totalEndowment = (float) ($value('endowment_restricted', 0) ?? 0) + (float) ($value('endowment_unrestricted', 0) ?? 0);
$totalNonEndowed = (float) ($value('investment_restricted', 0) ?? 0) + (float) ($value('investment_unrestricted', 0) ?? 0);
$totalInvestments = $totalEndowment + $totalNonEndowed;

$languageOptions = [
    'English' => lang('App.prLanguageEnglish'),
    'Spanish' => lang('App.prLanguageSpanish'),
    'French' => lang('App.prLanguageFrench'),
    'Mandarin' => lang('App.prLanguageMandarin'),
    'Haitian Creole' => lang('App.prLanguageHaitianCreole'),
    'Tagalog' => lang('App.prLanguageTagalog'),
    'American Sign Language (ASL)' => lang('App.prLanguageAsl'),
];
$programOptions = [
    'Sunday Morning Eucharist' => lang('App.prProgramSundayMorningEucharist'),
    'Sunday Morning Prayer' => lang('App.prProgramSundayMorningPrayer'),
    'Other Weekend Eucharist' => lang('App.prProgramOtherWeekendEucharist'),
    'Weekday Eucharist' => lang('App.prProgramWeekdayEucharist'),
    'Weekday Morning/Noonday/Evening Prayer' => lang('App.prProgramWeekdayPrayer'),
    'Streamed Service' => lang('App.prProgramStreamedService'),
    'Interactive Virtual Service' => lang('App.prProgramInteractiveVirtualService'),
    'Paid Musicians' => lang('App.prProgramPaidMusicians'),
    'Volunteer Musicians' => lang('App.prProgramVolunteerMusicians'),
    'Adult Choir' => lang('App.prProgramAdultChoir'),
    'Children’s Choir' => lang('App.prProgramChildrensChoir'),
    'Sunday School' => lang('App.prProgramSundaySchool'),
    'Sunday Childcare' => lang('App.prProgramSundayChildcare'),
    'Bible Study' => lang('App.prProgramBibleStudy'),
    'Virtual Formation' => lang('App.prProgramVirtualFormation'),
    'In Person Formation' => lang('App.prProgramInPersonFormation'),
    'Campus Ministry' => lang('App.prProgramCampusMinistry'),
    'Outreach Ministries' => lang('App.prProgramOutreachMinistries'),
    'Ecumenical Collaboration' => lang('App.prProgramEcumenicalCollaboration'),
];
$subsidiaryOptions = [
    'Preschool or Nursery Program' => lang('App.prSubsidiaryPreschool'),
    'K-8 School' => lang('App.prSubsidiaryK8School'),
    '9-12 School' => lang('App.prSubsidiary912School'),
    'Outreach Ministry' => lang('App.prSubsidiaryOutreachMinistry'),
];
$primaryWorshipOptions = [
    'Full Time Priest' => lang('App.prWorshipLeaderFullTimePriest'),
    'Part Time Priest' => lang('App.prWorshipLeaderPartTimePriest'),
    'Short Term Supply' => lang('App.prWorshipLeaderShortTermSupply'),
    'Long Term Supply' => lang('App.prWorshipLeaderLongTermSupply'),
    'Lay Person' => lang('App.prWorshipLeaderLayPerson'),
    'Deacon' => lang('App.prWorshipLeaderDeacon'),
    'Other' => lang('App.other'),
];
$clergySituationOptions = [
    'Long Term Supply or Interim' => lang('App.prClergySituationLongTermSupplyOrInterim'),
    'Call to Common Ministry' => lang('App.prClergySituationCommonMinistry'),
    'Clergy from elsewhere in The Anglican Communion' => lang('App.prClergySituationAnglicanCommunion'),
];

$totalClergy = (int) ($clergy['fulltime_clergy'] ?? 0) + (int) ($clergy['parttime_clergy'] ?? 0) + (int) ($clergy['nonstipendiary_clergy'] ?? 0);
$totalLay = (int) ($clergy['fulltime_lay'] ?? 0) + (int) ($clergy['parttime_lay'] ?? 0) + (int) ($clergy['unpaid_lay'] ?? 0);
$totalStaff = $totalClergy + $totalLay;
$emailRecipient = trim((string) (old('recipient_email', $summary['report_prepared_by_email'] ?? ($parish['email'] ?? ''))));
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
    <form method="get" action="<?= base_url('parochial-report') ?>" class="d-flex align-items-center gap-2">
        <label for="yearSelect" class="form-label mb-0 fw-semibold"><?= lang('App.reportYear') ?>:</label>
        <select id="yearSelect" name="year" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
            <?php foreach ($years as $yr): ?>
                <option value="<?= $yr ?>" <?= (int) $yr === (int) $year ? 'selected' : '' ?>><?= $yr ?></option>
            <?php endforeach; ?>
        </select>
        <input type="hidden" name="pdf_engine" value="dompdf">
        <?php if ($forceRecompute): ?>
            <input type="hidden" name="recompute" value="1">
        <?php endif; ?>
    </form>
    <div class="d-flex align-items-center flex-wrap gap-2 ms-auto">
        <form method="get" action="<?= base_url('parochial-report/print') ?>" target="_blank" class="d-flex align-items-center gap-2 flex-wrap">
            <input type="hidden" name="year" value="<?= (int) $year ?>">
            <input type="hidden" name="pdf_engine" value="<?= esc($pdfEngine) ?>">
            <?php if ($forceRecompute): ?>
                <input type="hidden" name="recompute" value="1">
            <?php endif; ?>
            <label for="parochialPaperSize" class="form-label mb-0 small fw-semibold">Paper</label>
            <select id="parochialPaperSize" name="paper_size" class="form-select form-select-sm" style="width:auto">
                <option value="letter">Letter</option>
                <option value="a4">A4</option>
            </select>
            <button type="submit" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-printer me-2"></i><?= lang('App.prPrint') ?>
            </button>
            <button type="submit" formaction="<?= base_url('parochial-report/export-pdf') ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-file-earmark-pdf me-2"></i><?= lang('Finance.exportPdf') ?>
            </button>
        </form>
        <form method="post" action="<?= base_url('parochial-report/email-pdf') ?>" class="d-flex align-items-center gap-2 flex-wrap">
            <?= csrf_field() ?>
            <input type="hidden" name="year" value="<?= (int) $year ?>">
            <input type="hidden" name="pdf_engine" value="<?= esc($pdfEngine) ?>">
            <?php if ($forceRecompute): ?>
                <input type="hidden" name="recompute" value="1">
            <?php endif; ?>
            <label for="parochialEmailRecipient" class="form-label mb-0 small fw-semibold"><?= lang('App.prEmailPdfRecipient') ?></label>
            <input
                id="parochialEmailRecipient"
                type="email"
                name="recipient_email"
                class="form-control form-control-sm"
                style="width:15rem"
                value="<?= esc($emailRecipient) ?>"
                placeholder="<?= esc(lang('App.prEmailPdfRecipientPlaceholder')) ?>"
                required>
            <label for="parochialEmailPaperSize" class="form-label mb-0 small fw-semibold">Paper</label>
            <select id="parochialEmailPaperSize" name="paper_size" class="form-select form-select-sm" style="width:auto">
                <option value="letter">Letter</option>
                <option value="a4">A4</option>
            </select>
            <button type="submit" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-envelope-paper me-2"></i><?= lang('App.prEmailPdfAction') ?>
            </button>
        </form>
        <span class="badge border border-secondary text-secondary fw-normal"><?= lang('App.prAuthoritativeSource') ?></span>
        <span class="badge border border-primary text-primary fw-normal"><?= lang('App.parochialReportAutoFilled') ?></span>
        <?php if ($isFinalized): ?>
            <span class="badge border border-success text-success fw-normal"><?= lang('App.prReportFinalizedBadge') ?></span>
        <?php endif; ?>
    </div>
</div>

<?php if ($isFinalized && $usingFrozenSnapshot): ?>
    <div class="alert alert-success border-0 mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <span><?= lang('App.prReportUsingSnapshot') ?></span>
            <a href="<?= $recomputeUrl ?>" class="btn btn-sm btn-outline-success"><?= lang('App.prReportRecomputeAction') ?></a>
        </div>
    </div>
<?php elseif ($isFinalized && !$usingFrozenSnapshot && $hasFrozenSnapshot): ?>
    <div class="alert alert-warning border-0 mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <span><?= lang('App.prReportUsingComputedByRequest') ?></span>
            <a href="<?= base_url('parochial-report?year=' . (int) $year) ?>" class="btn btn-sm btn-outline-secondary"><?= lang('App.prReportUseStoredSnapshotAction') ?></a>
        </div>
    </div>
<?php endif; ?>

<div class="alert alert-info border-0 mb-4">
    <div class="fw-semibold mb-1"><?= lang('App.prReferenceContractTitle') ?></div>
    <div class="small mb-0">
        <?= lang('App.prReferenceContractBody') ?>
    </div>
</div>

<form method="post" action="<?= base_url('parochial-report/save') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="report_year" value="<?= $year ?>">

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><?= lang('App.prPage1Title') ?></h5>
            <span class="badge border border-secondary text-secondary fw-normal"><?= lang('App.prPage1Badge') ?></span>
        </div>
        <div class="card-body">
            <p class="small text-muted mb-3"><?= lang('App.prPage1Help') ?></p>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label"><?= lang('App.prFieldCongregationName') ?></label>
                    <input type="text" class="form-control form-control-sm" value="<?= esc($parish['name'] ?? '') ?>" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= lang('App.prFieldDiocese') ?></label>
                    <input type="text" class="form-control form-control-sm" value="<?= esc($parish['diocese'] ?? '') ?>" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= lang('App.prFieldTaxId') ?></label>
                    <input type="text" class="form-control form-control-sm" value="<?= esc($parish['tax_id'] ?? '') ?>" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= lang('App.prFieldStreetAddress1') ?></label>
                    <input type="text" class="form-control form-control-sm" value="<?= esc($parish['street'] ?? '') ?>" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= lang('App.prFieldCity') ?></label>
                    <input type="text" class="form-control form-control-sm" value="<?= esc($parish['city'] ?? '') ?>" readonly>
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= lang('App.prFieldState') ?></label>
                    <input type="text" class="form-control form-control-sm" value="<?= esc($parish['state'] ?? '') ?>" readonly>
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= lang('App.prFieldZipPlus4') ?></label>
                    <input type="text" class="form-control form-control-sm" value="<?= esc($parish['zip'] ?? '') ?>" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= lang('App.prFieldCounty') ?></label>
                    <input type="text" class="form-control form-control-sm" value="<?= esc($parish['county'] ?? '') ?>" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= lang('App.prFieldCountry') ?></label>
                    <input type="text" class="form-control form-control-sm" value="<?= esc($parish['country'] ?? '') ?>" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= lang('App.prFieldCongregationEmailAddress') ?></label>
                    <input type="text" class="form-control form-control-sm" value="<?= esc($parish['email'] ?? '') ?>" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= lang('App.prFieldCongregationWebAddress') ?></label>
                    <input type="text" class="form-control form-control-sm" value="<?= esc($parish['website'] ?? '') ?>" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= lang('App.prFieldPhone') ?></label>
                    <input type="text" class="form-control form-control-sm" value="<?= esc($parish['phone'] ?? '') ?>" readonly>
                </div>
            </div>

            <hr class="my-4">

            <h6 class="mb-3"><?= lang('App.prMailingAddressTitle') ?></h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label"><?= lang('App.prFieldMailingAddress1') ?></label>
                    <input type="text" name="mailing_address_1" class="form-control form-control-sm" value="<?= esc((string) $value('mailing_address_1', '')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= lang('App.prFieldMailingAddress2') ?></label>
                    <input type="text" name="mailing_address_2" class="form-control form-control-sm" value="<?= esc((string) $value('mailing_address_2', '')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= lang('App.prFieldMailingCity') ?></label>
                    <input type="text" name="mailing_city" class="form-control form-control-sm" value="<?= esc((string) $value('mailing_city', '')) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= lang('App.prFieldState') ?></label>
                    <input type="text" name="mailing_state" class="form-control form-control-sm" value="<?= esc((string) $value('mailing_state', '')) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= lang('App.prFieldZipPlus4') ?></label>
                    <input type="text" name="mailing_zip" class="form-control form-control-sm" value="<?= esc((string) $value('mailing_zip', '')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= lang('App.prFieldCounty') ?></label>
                    <input type="text" name="mailing_county" class="form-control form-control-sm" value="<?= esc((string) $value('mailing_county', '')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= lang('App.prFieldCountry') ?></label>
                    <input type="text" name="mailing_country" class="form-control form-control-sm" value="<?= esc((string) $value('mailing_country', '')) ?>">
                </div>
            </div>

            <hr class="my-4">

            <div class="row g-4">
                <div class="col-lg-6">
                    <h6 class="mb-3"><?= lang('App.prReportPreparationTitle') ?></h6>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label"><?= lang('App.prFieldDataAndDemographicsPreparedBy') ?></label>
                            <input type="text" name="report_prepared_by_name" class="form-control form-control-sm" value="<?= esc((string) $value('report_prepared_by_name', '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= lang('App.prFieldEmailAddress') ?></label>
                            <input type="email" name="report_prepared_by_email" class="form-control form-control-sm" value="<?= esc((string) $value('report_prepared_by_email', '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= lang('App.prFieldDaytimePhone') ?></label>
                            <input type="text" name="report_prepared_by_phone" class="form-control form-control-sm" value="<?= esc((string) $value('report_prepared_by_phone', '')) ?>">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label"><?= lang('App.prFieldStewardshipPreparedBy') ?></label>
                            <input type="text" name="financial_prepared_by_name" class="form-control form-control-sm" value="<?= esc((string) $value('financial_prepared_by_name', '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= lang('App.prFieldEmailAddress') ?></label>
                            <input type="email" name="financial_prepared_by_email" class="form-control form-control-sm" value="<?= esc((string) $value('financial_prepared_by_email', '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= lang('App.prFieldDaytimePhone') ?></label>
                            <input type="text" name="financial_prepared_by_phone" class="form-control form-control-sm" value="<?= esc((string) $value('financial_prepared_by_phone', '')) ?>">
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <h6 class="mb-3"><?= lang('App.prCompletionTitle') ?></h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label"><?= lang('App.prFieldCompletionParticipantsCount') ?></label>
                            <input type="number" min="0" name="completion_participants_count" class="form-control form-control-sm" value="<?= esc((string) ($value('completion_participants_count', '') ?? '')) ?>">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label"><?= lang('App.prFieldCompletionParticipantRoles') ?></label>
                            <input type="text" name="completion_participant_roles" class="form-control form-control-sm" value="<?= esc((string) $value('completion_participant_roles', '')) ?>" placeholder="<?= esc(lang('App.prParticipantRolesPlaceholder'), 'attr') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <hr class="my-4">

            <h6 class="mb-3"><?= lang('App.prCertificationTitle') ?></h6>
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="border rounded p-3 h-100">
                        <div class="fw-semibold mb-2"><?= lang('App.prCertificationClerkTitle') ?></div>
                        <div class="row g-3">
                            <div class="col-md-12"><input type="text" name="clerk_certified_by_name" class="form-control form-control-sm" placeholder="<?= esc(lang('App.prCertifiedByPlaceholder'), 'attr') ?>" value="<?= esc((string) $value('clerk_certified_by_name', '')) ?>"></div>
                            <div class="col-md-6"><input type="email" name="clerk_certified_by_email" class="form-control form-control-sm" placeholder="<?= esc(lang('App.prFieldEmailAddress'), 'attr') ?>" value="<?= esc((string) $value('clerk_certified_by_email', '')) ?>"></div>
                            <div class="col-md-6"><input type="text" name="clerk_certified_by_phone" class="form-control form-control-sm" placeholder="<?= esc(lang('App.prFieldDaytimePhone'), 'attr') ?>" value="<?= esc((string) $value('clerk_certified_by_phone', '')) ?>"></div>
                            <div class="col-md-12"><label class="form-label small"><?= lang('App.prFieldDateOfMeeting') ?></label><input type="date" name="clerk_certified_date" class="form-control form-control-sm" value="<?= esc((string) $value('clerk_certified_date', '')) ?>"></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="border rounded p-3 h-100">
                        <div class="fw-semibold mb-2"><?= lang('App.prCertificationTreasurerTitle') ?></div>
                        <div class="row g-3">
                            <div class="col-md-12"><input type="text" name="treasurer_certified_by_name" class="form-control form-control-sm" placeholder="<?= esc(lang('App.prCertifiedByPlaceholder'), 'attr') ?>" value="<?= esc((string) $value('treasurer_certified_by_name', '')) ?>"></div>
                            <div class="col-md-6"><input type="email" name="treasurer_certified_by_email" class="form-control form-control-sm" placeholder="<?= esc(lang('App.prFieldEmailAddress'), 'attr') ?>" value="<?= esc((string) $value('treasurer_certified_by_email', '')) ?>"></div>
                            <div class="col-md-6"><input type="text" name="treasurer_certified_by_phone" class="form-control form-control-sm" placeholder="<?= esc(lang('App.prFieldDaytimePhone'), 'attr') ?>" value="<?= esc((string) $value('treasurer_certified_by_phone', '')) ?>"></div>
                            <div class="col-md-12"><label class="form-label small"><?= lang('App.prFieldDate') ?></label><input type="date" name="treasurer_certified_date" class="form-control form-control-sm" value="<?= esc((string) $value('treasurer_certified_date', '')) ?>"></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="border rounded p-3 h-100">
                        <div class="fw-semibold mb-2"><?= lang('App.prCertificationRectorTitle') ?></div>
                        <div class="row g-3">
                            <div class="col-md-12"><input type="text" name="rector_certified_by_name" class="form-control form-control-sm" placeholder="<?= esc(lang('App.prCertifiedByPlaceholder'), 'attr') ?>" value="<?= esc((string) $value('rector_certified_by_name', '')) ?>"></div>
                            <div class="col-md-6"><input type="email" name="rector_certified_by_email" class="form-control form-control-sm" placeholder="<?= esc(lang('App.prFieldEmailAddress'), 'attr') ?>" value="<?= esc((string) $value('rector_certified_by_email', '')) ?>"></div>
                            <div class="col-md-6"><input type="text" name="rector_certified_by_phone" class="form-control form-control-sm" placeholder="<?= esc(lang('App.prFieldDaytimePhone'), 'attr') ?>" value="<?= esc((string) $value('rector_certified_by_phone', '')) ?>"></div>
                            <div class="col-md-12"><label class="form-label small"><?= lang('App.prFieldDate') ?></label><input type="date" name="rector_certified_date" class="form-control form-control-sm" value="<?= esc((string) $value('rector_certified_date', '')) ?>"></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="border rounded p-3 h-100">
                        <div class="fw-semibold mb-2"><?= lang('App.prWardenApprovalTitle') ?></div>
                        <div class="row g-3">
                            <div class="col-md-12"><input type="text" name="warden_approved_by_name" class="form-control form-control-sm" placeholder="<?= esc(lang('App.prApprovedByPlaceholder'), 'attr') ?>" value="<?= esc((string) $value('warden_approved_by_name', '')) ?>"></div>
                            <div class="col-md-6"><input type="email" name="warden_approved_by_email" class="form-control form-control-sm" placeholder="<?= esc(lang('App.prFieldEmailAddress'), 'attr') ?>" value="<?= esc((string) $value('warden_approved_by_email', '')) ?>"></div>
                            <div class="col-md-6"><input type="text" name="warden_approved_by_phone" class="form-control form-control-sm" placeholder="<?= esc(lang('App.prFieldDaytimePhone'), 'attr') ?>" value="<?= esc((string) $value('warden_approved_by_phone', '')) ?>"></div>
                            <div class="col-md-12"><label class="form-label small"><?= lang('App.prFieldWardenApprovedDate') ?></label><input type="date" name="warden_approved_date" class="form-control form-control-sm" value="<?= esc((string) $value('warden_approved_date', '')) ?>"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><?= lang('App.prPage2Title') ?></h5>
            <span class="badge border border-primary text-primary fw-normal"><?= lang('App.prPage2Badge') ?></span>
        </div>
        <div class="card-body">
            <p class="small text-muted mb-3"><?= lang('App.prPage2Help') ?></p>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label"><?= lang('App.prLine1Label') ?></label>
                    <input type="number" id="line_1" class="form-control form-control-sm bg-body-secondary" value="<?= (int) ($attendance['avg_sunday_inperson'] ?? 0) ?>" readonly>
                    <div class="form-text"><?= sprintf(lang('App.prLine1Help'), (int) ($attendance['sunday_inperson_total'] ?? 0), (int) ($attendance['sunday_inperson_services'] ?? 0)) ?></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= lang('App.prLine2Label') ?></label>
                    <input type="number" id="line_2" class="form-control form-control-sm bg-body-secondary" value="<?= (int) ($attendance['avg_sunday_online'] ?? 0) ?>" readonly>
                    <div class="form-text"><?= sprintf(lang('App.prLine2Help'), (int) ($attendance['sunday_online_total'] ?? 0), (int) ($attendance['sunday_online_services'] ?? 0)) ?></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= lang('App.prLine3Label') ?></label>
                    <input type="number" id="line_3" class="form-control form-control-sm bg-body-secondary" value="<?= (int) ($attendance['avg_weekday_inperson'] ?? 0) ?>" readonly>
                    <div class="form-text"><?= sprintf(lang('App.prLine3Help'), (int) ($attendance['weekday_inperson_total'] ?? 0), (int) ($attendance['weekday_inperson_services'] ?? 0)) ?></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= lang('App.prLine4Label') ?></label>
                    <input type="number" id="line_4" class="form-control form-control-sm bg-body-secondary" value="<?= (int) ($attendance['avg_weekday_online'] ?? 0) ?>" readonly>
                    <div class="form-text"><?= sprintf(lang('App.prLine4Help'), (int) ($attendance['weekday_online_total'] ?? 0), (int) ($attendance['weekday_online_services'] ?? 0)) ?></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= lang('App.prLine5Label') ?></label>
                    <input type="number" id="line_5" class="form-control form-control-sm bg-body-secondary" value="<?= (int) ($attendance['avg_nonlive_views'] ?? 0) ?>" readonly>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="nonlive_views_estimated" value="1" id="nonlive_views_estimated" <?= (int) ($value('nonlive_views_estimated', 0) ?? 0) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="nonlive_views_estimated"><?= lang('App.prEstimated') ?></label>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= lang('App.prLine6Label') ?></label>
                    <input type="number" min="0" id="line_6" name="outreach_beneficiaries_weekly" class="form-control form-control-sm" value="<?= $outreachBeneficiaries ?>">
                    <div class="form-text"><?= lang('App.prLine6Help') ?></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= lang('App.prLine7Label') ?></label>
                    <input type="number" min="0" id="line_7" name="nonmember_volunteers_weekly" class="form-control form-control-sm" value="<?= $nonMemberVolunteers ?>">
                    <div class="form-text"><?= lang('App.prLine7Help') ?></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= lang('App.prLine8Label') ?></label>
                    <input type="number" id="total_impact" class="form-control form-control-sm bg-body-secondary fw-semibold" value="<?= $totalImpact ?>" readonly>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><?= lang('App.prPage3Title') ?></h5>
            <span class="badge border border-primary text-primary fw-normal"><?= lang('App.prPage3Badge') ?></span>
        </div>
        <div class="card-body">
            <p class="small text-muted mb-3"><?= lang('App.prPage3Help') ?></p>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label"><?= lang('App.prLine9Label') ?></label>
                    <input type="number" class="form-control form-control-sm bg-body-secondary" value="<?= (int) ($membership['adult_communicants'] ?? 0) ?>" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= lang('App.prLine10Label') ?></label>
                    <input type="number" class="form-control form-control-sm bg-body-secondary" value="<?= (int) ($membership['youth_communicants'] ?? 0) ?>" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= lang('App.prLine11Label') ?></label>
                    <input type="number" class="form-control form-control-sm bg-body-secondary fw-semibold" value="<?= (int) ($membership['total_communicants'] ?? 0) ?>" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= lang('App.prLine14Label') ?></label>
                    <input type="number" name="active_participants_total" class="form-control form-control-sm" value="<?= esc((string) ($value('active_participants_total', $membership['active_participants'] ?? 0) ?? 0)) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= sprintf(lang('App.prFieldPreviousYearMembers'), $year - 1) ?></label>
                    <input type="number" id="membership_previous_total" name="membership_previous_total" class="form-control form-control-sm" value="<?= $membershipPrevious ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= lang('App.prLine12Label') ?></label>
                    <input type="number" id="membership_increase_total" name="membership_increase_total" class="form-control form-control-sm" value="<?= $membershipIncrease ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= lang('App.prLine13Label') ?></label>
                    <input type="number" id="membership_decrease_total" name="membership_decrease_total" class="form-control form-control-sm" value="<?= $membershipDecrease ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= sprintf(lang('App.prFieldCurrentYearMembers'), $year) ?></label>
                    <input type="number" id="membership_current_total" class="form-control form-control-sm bg-body-secondary fw-semibold" value="<?= $membershipCurrent ?>" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= lang('App.prLine15Label') ?></label>
                    <input type="number" class="form-control form-control-sm bg-body-secondary" value="<?= (int) ($membership['easter_attendance'] ?? 0) ?>" readonly>
                    <div class="form-text"><?= lang('App.prLine15Help') ?></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= lang('App.prLine16Label') ?></label>
                    <input type="number" class="form-control form-control-sm bg-body-secondary" value="<?= (int) ($membership['christmas_attendance'] ?? 0) ?>" readonly>
                </div>
            </div>

            <hr class="my-4">

            <h6 class="mb-3"><?= lang('App.prLine17Label') ?></h6>
            <div class="row g-3">
                <div class="col-md-2"><label class="form-label"><?= lang('App.prFieldFullTimeClergy') ?></label><input type="number" class="form-control form-control-sm bg-body-secondary" value="<?= (int) ($clergy['fulltime_clergy'] ?? 0) ?>" readonly></div>
                <div class="col-md-2"><label class="form-label"><?= lang('App.prFieldPartTimeClergy') ?></label><input type="number" class="form-control form-control-sm bg-body-secondary" value="<?= (int) ($clergy['parttime_clergy'] ?? 0) ?>" readonly></div>
                <div class="col-md-2"><label class="form-label"><?= lang('App.prFieldNonStipendiaryClergy') ?></label><input type="number" class="form-control form-control-sm bg-body-secondary" value="<?= (int) ($clergy['nonstipendiary_clergy'] ?? 0) ?>" readonly></div>
                <div class="col-md-2"><label class="form-label"><?= lang('App.prFieldFullTimeLayStaff') ?></label><input type="number" class="form-control form-control-sm bg-body-secondary" value="<?= (int) ($clergy['fulltime_lay'] ?? 0) ?>" readonly></div>
                <div class="col-md-2"><label class="form-label"><?= lang('App.prFieldPartTimeLayStaff') ?></label><input type="number" class="form-control form-control-sm bg-body-secondary" value="<?= (int) ($clergy['parttime_lay'] ?? 0) ?>" readonly></div>
                <div class="col-md-2"><label class="form-label"><?= lang('App.prFieldUnpaidLayStaff') ?></label><input type="number" class="form-control form-control-sm bg-body-secondary" value="<?= (int) ($clergy['unpaid_lay'] ?? 0) ?>" readonly></div>
                <div class="col-md-2"><label class="form-label"><?= lang('App.prFieldTotalClergy') ?></label><input type="number" class="form-control form-control-sm bg-body-secondary fw-semibold" value="<?= $totalClergy ?>" readonly></div>
                <div class="col-md-2"><label class="form-label"><?= lang('App.prFieldTotalLayStaff') ?></label><input type="number" class="form-control form-control-sm bg-body-secondary fw-semibold" value="<?= $totalLay ?>" readonly></div>
                <div class="col-md-2"><label class="form-label"><?= lang('App.prFieldTotalStaff') ?></label><input type="number" class="form-control form-control-sm bg-body-secondary fw-semibold" value="<?= $totalStaff ?>" readonly></div>
                <div class="col-md-2"><label class="form-label"><?= lang('App.prFieldDeacons') ?></label><input type="number" class="form-control form-control-sm bg-body-secondary" value="<?= (int) ($clergy['deacons'] ?? 0) ?>" readonly></div>
                <div class="col-md-2"><label class="form-label"><?= lang('App.prFieldPriests') ?></label><input type="number" class="form-control form-control-sm bg-body-secondary" value="<?= (int) ($clergy['priests'] ?? 0) ?>" readonly></div>
            </div>

            <div class="row g-4 mt-1">
                <div class="col-lg-6">
                    <label class="form-label"><?= lang('App.prLine18Label') ?></label>
                    <select name="primary_worship_leader" class="form-select form-select-sm">
                        <option value=""><?= lang('App.prSelectOne') ?></option>
                        <?php foreach ($primaryWorshipOptions as $option => $label): ?>
                            <option value="<?= esc($option) ?>" <?= (string) $value('primary_worship_leader', '') === $option ? 'selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-6">
                    <label class="form-label d-block"><?= lang('App.prLine19Label') ?></label>
                    <?php foreach ($clergySituationOptions as $option => $label): ?>
                        <?php $index = md5($option); ?>
                        <?php $id = 'clergySituation' . $index; ?>
                        <div class="form-check form-check-inline me-3">
                            <input class="form-check-input" type="checkbox" id="<?= esc($id) ?>" name="clergy_situation[]" value="<?= esc($option) ?>" <?= in_array($option, $selectedClergySituations, true) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="<?= esc($id) ?>"><?= esc($label) ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><?= lang('App.prPage4Title') ?></h5>
            <span class="badge border border-primary text-primary fw-normal"><?= lang('App.prPage4Badge') ?></span>
        </div>
        <div class="card-body">
            <p class="small text-muted mb-3"><?= lang('App.prPage4Help') ?></p>
            <div class="row g-3">
                <div class="col-md-2"><label class="form-label"><?= lang('App.prFieldBaptisms') ?></label><input type="number" class="form-control form-control-sm bg-body-secondary" value="<?= (int) ($sacraments['baptisms'] ?? 0) ?>" readonly></div>
                <div class="col-md-2"><label class="form-label"><?= lang('App.prFieldConfirmations') ?></label><input type="number" class="form-control form-control-sm bg-body-secondary" value="<?= (int) ($sacraments['confirmations'] ?? 0) ?>" readonly></div>
                <div class="col-md-2"><label class="form-label"><?= lang('App.prFieldReceptions') ?></label><input type="number" class="form-control form-control-sm bg-body-secondary" value="<?= (int) ($sacraments['receptions'] ?? 0) ?>" readonly></div>
                <div class="col-md-2"><label class="form-label"><?= lang('App.prFieldMarriages') ?></label><input type="number" class="form-control form-control-sm bg-body-secondary" value="<?= (int) ($sacraments['marriages'] ?? 0) ?>" readonly></div>
                <div class="col-md-2"><label class="form-label"><?= lang('App.prFieldBurials') ?></label><input type="number" class="form-control form-control-sm bg-body-secondary" value="<?= (int) ($sacraments['burials'] ?? 0) ?>" readonly></div>
                <div class="col-md-2"><label class="form-label"><?= lang('App.prFieldEucharists') ?></label><input type="number" class="form-control form-control-sm bg-body-secondary" value="<?= (int) ($sacraments['eucharists'] ?? 0) ?>" readonly></div>
                <div class="col-md-2"><label class="form-label"><?= lang('App.prFieldDailyOfficeServices') ?></label><input type="number" class="form-control form-control-sm bg-body-secondary" value="<?= (int) ($sacraments['daily_offices'] ?? 0) ?>" readonly></div>
            </div>

            <hr class="my-4">

            <h6 class="mb-3"><?= lang('App.prLine21Label') ?></h6>
            <div class="row g-2">
                <?php foreach ($programOptions as $option => $label): ?>
                    <?php $index = md5($option); ?>
                    <?php $id = 'programOption' . $index; ?>
                    <div class="col-md-4 col-lg-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="<?= esc($id) ?>" name="programs[]" value="<?= esc($option) ?>" <?= in_array($option, $selectedPrograms, true) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="<?= esc($id) ?>"><?= esc($label) ?></label>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="col-md-4 col-lg-3">
                    <label class="form-label small"><?= lang('App.other') ?></label>
                    <input type="text" name="programs_other" class="form-control form-control-sm" value="<?= esc((string) $value('programs_other', '')) ?>">
                </div>
            </div>

            <hr class="my-4">

            <h6 class="mb-3"><?= lang('App.prLine22Label') ?></h6>
            <div class="row g-2">
                <?php foreach ($subsidiaryOptions as $option => $label): ?>
                    <?php $index = md5($option); ?>
                    <?php $id = 'subsidiaryOption' . $index; ?>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="<?= esc($id) ?>" name="subsidiary_organizations[]" value="<?= esc($option) ?>" <?= in_array($option, $selectedSubsidiaries, true) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="<?= esc($id) ?>"><?= esc($label) ?></label>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="col-md-4">
                    <label class="form-label small"><?= lang('App.other') ?></label>
                    <input type="text" name="subsidiary_organizations_other" class="form-control form-control-sm" value="<?= esc((string) $value('subsidiary_organizations_other', '')) ?>">
                </div>
            </div>

            <hr class="my-4">

            <div class="row g-3">
                <div class="col-lg-8">
                    <label class="form-label d-block"><?= lang('App.prLine23Label') ?></label>
                    <?php foreach ($languageOptions as $option => $label): ?>
                        <?php $index = md5($option); ?>
                        <?php $id = 'languageOption' . $index; ?>
                        <div class="form-check form-check-inline me-3">
                            <input class="form-check-input" type="checkbox" id="<?= esc($id) ?>" <?= in_array($option, $languagesUsed, true) ? 'checked' : '' ?> disabled>
                            <label class="form-check-label small" for="<?= esc($id) ?>"><?= esc($label) ?></label>
                        </div>
                    <?php endforeach; ?>
                    <div class="form-text"><?= lang('App.prLine23Help') ?></div>
                </div>
                <div class="col-lg-4">
                    <label class="form-label"><?= lang('App.prFieldOtherLanguage') ?></label>
                    <input type="text" name="languages_other" class="form-control form-control-sm" value="<?= esc((string) $value('languages_other', '')) ?>">
                </div>
                <div class="col-md-4">
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="simultaneous_multilingual_service" name="simultaneous_multilingual_service" value="1" <?= (int) ($value('simultaneous_multilingual_service', 0) ?? 0) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="simultaneous_multilingual_service"><?= lang('App.prLine24Label') ?></label>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= lang('App.prLine25Label') ?></label>
                    <input type="number" step="0.01" min="0" max="100" name="percent_white" class="form-control form-control-sm" value="<?= esc((string) ($value('percent_white', '') ?? '')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= lang('App.prLine26Label') ?></label>
                    <input type="number" step="0.1" name="average_age_override" class="form-control form-control-sm" value="<?= esc((string) ($value('average_age_override', $membership['average_age'] ?? '') ?? '')) ?>">
                    <div class="form-text"><?= lang('App.prLine26Help') ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><?= lang('App.prPages5to8Title') ?></h5>
            <span class="badge border border-primary text-primary fw-normal"><?= lang('App.prPages5to8Badge') ?></span>
        </div>
        <div class="card-body">
            <p class="small text-muted mb-3"><?= lang('App.prPages5to8Help') ?></p>

            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label"><?= lang('App.prFieldReportingCurrency') ?></label>
                    <input type="text" name="reporting_currency" class="form-control form-control-sm" value="<?= esc((string) $value('reporting_currency', 'USD')) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= lang('App.prFinanceLine1Label') ?></label>
                    <input type="number" name="confirmed_pledges_count" class="form-control form-control-sm" value="<?= esc((string) ($value('confirmed_pledges_count', $finance['confirmed_pledges_count'] ?? 0) ?? 0)) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= lang('App.prFinanceLine2Label') ?></label>
                    <input type="number" step="0.01" name="confirmed_pledges_amount" class="form-control form-control-sm" value="<?= esc((string) ($value('confirmed_pledges_amount', $finance['confirmed_pledges_amount'] ?? 0) ?? 0)) ?>">
                </div>
            </div>

            <hr class="my-4">

            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label"><?= lang('App.prFinanceLine3Label') ?></label>
                    <input type="number" step="0.01" id="line_3_revenue" class="form-control form-control-sm bg-body-secondary" value="<?= number_format((float) ($finance['revenue_unrestricted'] ?? 0), 2, '.', '') ?>" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= lang('App.prFinanceLine4Label') ?></label>
                    <input type="number" step="0.01" id="line_4_revenue" class="form-control form-control-sm bg-body-secondary" value="<?= number_format((float) ($finance['revenue_restricted'] ?? 0), 2, '.', '') ?>" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= lang('App.prFinanceLine5Label') ?></label>
                    <input type="number" step="0.01" id="line_5_revenue" name="other_revenue_amount" class="form-control form-control-sm" value="<?= number_format($otherRevenue, 2, '.', '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= lang('App.prFinanceTotalRevenueLabel') ?></label>
                    <input type="number" step="0.01" id="total_revenue" class="form-control form-control-sm bg-body-secondary fw-semibold" value="<?= number_format($totalRevenue, 2, '.', '') ?>" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= lang('App.prFinanceLine6Label') ?></label>
                    <input type="number" step="0.01" id="line_6_expense" class="form-control form-control-sm bg-body-secondary" value="<?= number_format((float) ($finance['expenses_noncapital'] ?? 0), 2, '.', '') ?>" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= lang('App.prFinanceLine7Label') ?></label>
                    <input type="number" step="0.01" id="line_7_expense" class="form-control form-control-sm bg-body-secondary" value="<?= number_format((float) ($finance['expenses_capital'] ?? 0), 2, '.', '') ?>" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= lang('App.prFinanceTotalExpensesLabel') ?></label>
                    <input type="number" step="0.01" id="total_expenses" class="form-control form-control-sm bg-body-secondary fw-semibold" value="<?= number_format($totalExpenses, 2, '.', '') ?>" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= lang('App.prFinanceLine8Label') ?></label>
                    <input type="number" step="0.01" name="cash_balance" class="form-control form-control-sm" value="<?= esc((string) ($value('cash_balance', '') ?? '')) ?>">
                </div>
            </div>

            <hr class="my-4">

            <h6 class="mb-3"><?= lang('App.prFinanceLine9Title') ?></h6>
            <div class="row g-3">
                <div class="col-md-3"><label class="form-label"><?= lang('App.prFinanceLine9aEndowmentRestricted') ?></label><input type="number" step="0.01" id="endowment_restricted" name="endowment_restricted" class="form-control form-control-sm" value="<?= esc((string) ($value('endowment_restricted', '') ?? '')) ?>"></div>
                <div class="col-md-3"><label class="form-label"><?= lang('App.prFinanceLine9aEndowmentUnrestricted') ?></label><input type="number" step="0.01" id="endowment_unrestricted" name="endowment_unrestricted" class="form-control form-control-sm" value="<?= esc((string) ($value('endowment_unrestricted', '') ?? '')) ?>"></div>
                <div class="col-md-3"><label class="form-label"><?= lang('App.prFinanceLine9aNonEndowedRestricted') ?></label><input type="number" step="0.01" id="investment_restricted" name="investment_restricted" class="form-control form-control-sm" value="<?= esc((string) ($value('investment_restricted', '') ?? '')) ?>"></div>
                <div class="col-md-3"><label class="form-label"><?= lang('App.prFinanceLine9aNonEndowedUnrestricted') ?></label><input type="number" step="0.01" id="investment_unrestricted" name="investment_unrestricted" class="form-control form-control-sm" value="<?= esc((string) ($value('investment_unrestricted', '') ?? '')) ?>"></div>
                <div class="col-md-3"><label class="form-label"><?= lang('App.prFinanceFieldTotalEndowment') ?></label><input type="number" step="0.01" id="total_endowment" class="form-control form-control-sm bg-body-secondary" value="<?= number_format($totalEndowment, 2, '.', '') ?>" readonly></div>
                <div class="col-md-3"><label class="form-label"><?= lang('App.prFinanceFieldTotalNonEndowed') ?></label><input type="number" step="0.01" id="total_nonendowed" class="form-control form-control-sm bg-body-secondary" value="<?= number_format($totalNonEndowed, 2, '.', '') ?>" readonly></div>
                <div class="col-md-3"><label class="form-label"><?= lang('App.prFinanceFieldTotalInvestments') ?></label><input type="number" step="0.01" id="total_investments" class="form-control form-control-sm bg-body-secondary fw-semibold" value="<?= number_format($totalInvestments, 2, '.', '') ?>" readonly></div>
                <div class="col-md-3"><label class="form-label"><?= lang('App.prFinanceLine9bLabel') ?></label><input type="number" step="0.01" name="pct_draw" class="form-control form-control-sm" value="<?= esc((string) ($value('pct_draw', '') ?? '')) ?>"></div>
                <div class="col-md-3"><label class="form-label"><?= lang('App.prFinanceLine9cOperatingLabel') ?></label><input type="number" step="0.01" name="amount_drawn_operating" class="form-control form-control-sm" value="<?= esc((string) ($value('amount_drawn_operating', '') ?? '')) ?>"></div>
                <div class="col-md-3"><label class="form-label"><?= lang('App.prFinanceLine9cCapitalLabel') ?></label><input type="number" step="0.01" name="amount_drawn_capital" class="form-control form-control-sm" value="<?= esc((string) ($value('amount_drawn_capital', '') ?? '')) ?>"></div>
                <div class="col-md-3"><label class="form-label"><?= lang('App.prFinanceLine9dLabel') ?></label><input type="number" step="0.01" name="investment_net_gain_loss" class="form-control form-control-sm" value="<?= esc((string) ($value('investment_net_gain_loss', '') ?? '')) ?>"></div>
            </div>

            <hr class="my-4">

            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label"><?= lang('App.prFinanceLine10Label') ?></label>
                    <select id="has_debt" name="has_debt" class="form-select form-select-sm">
                        <option value="0" <?= (int) ($value('has_debt', 0) ?? 0) === 0 ? 'selected' : '' ?>><?= lang('App.no') ?></option>
                        <option value="1" <?= (int) ($value('has_debt', 0) ?? 0) === 1 ? 'selected' : '' ?>><?= lang('App.yes') ?></option>
                    </select>
                </div>
                <div class="col-md-3 debt-only">
                    <label class="form-label"><?= lang('App.prFinanceLine10aLabel') ?></label>
                    <input type="number" step="0.01" name="debt_amount" class="form-control form-control-sm" value="<?= esc((string) ($value('debt_amount', '') ?? '')) ?>">
                </div>
                <div class="col-md-6 debt-only">
                    <label class="form-label"><?= lang('App.prFinanceLine10bLabel') ?></label>
                    <input type="text" name="debt_description" class="form-control form-control-sm" value="<?= esc((string) $value('debt_description', '')) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= lang('App.prFinanceLine11Label') ?></label>
                    <select name="owns_property" class="form-select form-select-sm">
                        <option value="0" <?= (int) ($value('owns_property', 0) ?? 0) === 0 ? 'selected' : '' ?>><?= lang('App.no') ?></option>
                        <option value="1" <?= (int) ($value('owns_property', 0) ?? 0) === 1 ? 'selected' : '' ?>><?= lang('App.yes') ?></option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= lang('App.prFinanceLine12Label') ?></label>
                    <select name="rents_to_outside" class="form-select form-select-sm">
                        <option value="0" <?= (int) ($value('rents_to_outside', 0) ?? 0) === 0 ? 'selected' : '' ?>><?= lang('App.no') ?></option>
                        <option value="1" <?= (int) ($value('rents_to_outside', 0) ?? 0) === 1 ? 'selected' : '' ?>><?= lang('App.yes') ?></option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= lang('App.prFinanceLine12aLabel') ?></label>
                    <input type="number" step="0.01" name="rental_income" class="form-control form-control-sm" value="<?= esc((string) ($value('rental_income', '') ?? '')) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= lang('App.prFinanceLine12bLabel') ?></label>
                    <input type="number" step="0.01" name="rental_expense" class="form-control form-control-sm" value="<?= esc((string) ($value('rental_expense', '') ?? '')) ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><?= lang('App.prPage9Title') ?></h5>
            <span class="badge border border-secondary text-secondary fw-normal"><?= lang('App.prPage9Badge') ?></span>
        </div>
        <div class="card-body">
            <p class="small text-muted mb-3"><?= lang('App.prPage9Help') ?></p>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label"><?= lang('App.prNarrativeLine13Label') ?></label>
                    <textarea name="carbon_footprint_notes" rows="4" class="form-control form-control-sm"><?= esc((string) $value('carbon_footprint_notes', '')) ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label"><?= lang('App.prNarrativeLine14Label') ?></label>
                    <textarea name="exemplary_program_text" rows="4" class="form-control form-control-sm"><?= esc((string) $value('exemplary_program_text', '')) ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label"><?= lang('App.prNarrativeLine15Label') ?></label>
                    <textarea name="future_hope_text" rows="4" class="form-control form-control-sm"><?= esc((string) $value('future_hope_text', '')) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-start align-items-center gap-2 mb-4">
        <button type="submit" class="btn btn-outline-primary">
            <i class="bi bi-floppy me-2"></i><?= lang('App.prReportSaveDraft') ?>
        </button>
        <button type="submit" name="mark_done" value="1" class="btn btn-outline-success">
            <i class="bi bi-check2-circle me-2"></i><?= lang('App.prReportSaveAndMarkDone') ?>
        </button>
    </div>
</form>

<script>
    (function() {
        function numberValue(selector) {
            var element = document.querySelector(selector);
            return element ? (parseFloat(element.value) || 0) : 0;
        }

        function toggleDebtFields() {
            var hasDebt = document.getElementById('has_debt');
            var debtOnly = document.querySelectorAll('.debt-only');
            if (!hasDebt) {
                return;
            }

            var visible = hasDebt.value === '1';
            debtOnly.forEach(function(node) {
                node.style.display = visible ? '' : 'none';
            });
        }

        function updateComputedFields() {
            var totalImpact = numberValue('#line_1') +
                numberValue('#line_2') +
                numberValue('#line_3') +
                numberValue('#line_4') +
                numberValue('#line_5') +
                numberValue('#line_6') +
                numberValue('#line_7');
            var totalImpactField = document.getElementById('total_impact');
            if (totalImpactField) {
                totalImpactField.value = totalImpact;
            }

            var membershipCurrent = numberValue('#membership_previous_total') + numberValue('#membership_increase_total') - numberValue('#membership_decrease_total');
            var membershipField = document.getElementById('membership_current_total');
            if (membershipField) {
                membershipField.value = membershipCurrent;
            }

            var totalRevenue = numberValue('#line_3_revenue') + numberValue('#line_4_revenue') + numberValue('#line_5_revenue');
            var totalRevenueField = document.getElementById('total_revenue');
            if (totalRevenueField) {
                totalRevenueField.value = totalRevenue.toFixed(2);
            }

            var totalExpenses = numberValue('#line_6_expense') + numberValue('#line_7_expense');
            var totalExpensesField = document.getElementById('total_expenses');
            if (totalExpensesField) {
                totalExpensesField.value = totalExpenses.toFixed(2);
            }

            var totalEndowment = numberValue('#endowment_restricted') + numberValue('#endowment_unrestricted');
            var totalNonEndowed = numberValue('#investment_restricted') + numberValue('#investment_unrestricted');
            var totalInvestments = totalEndowment + totalNonEndowed;

            var totalEndowmentField = document.getElementById('total_endowment');
            var totalNonEndowedField = document.getElementById('total_nonendowed');
            var totalInvestmentsField = document.getElementById('total_investments');

            if (totalEndowmentField) {
                totalEndowmentField.value = totalEndowment.toFixed(2);
            }
            if (totalNonEndowedField) {
                totalNonEndowedField.value = totalNonEndowed.toFixed(2);
            }
            if (totalInvestmentsField) {
                totalInvestmentsField.value = totalInvestments.toFixed(2);
            }
        }

        document.querySelectorAll('input, select, textarea').forEach(function(element) {
            element.addEventListener('input', updateComputedFields);
            element.addEventListener('change', function() {
                updateComputedFields();
                toggleDebtFields();
            });
        });

        toggleDebtFields();
        updateComputedFields();
    }());
</script>

<?= $this->endSection() ?>