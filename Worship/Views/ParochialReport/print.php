<?php
$summary = $summary ?? [];
$church = $church ?? [];
$attendance = $attendance ?? [];
$membership = $membership ?? [];
$clergy = $clergy ?? [];
$sacraments = $sacraments ?? [];
$finance = $finance ?? [];
$languagesUsed = $languages_used ?? [];
$year = (int) ($year ?? ((int) date('Y') - 1));
$paperSize = strtolower((string) ($paper_size ?? 'letter'));
$pdfEngine = 'dompdf';
$showToolbar = (bool) ($show_toolbar ?? true);

$value = static fn(string $key, $default = '') => $summary[$key] ?? $default;
$display = static function ($raw, string $default = '—'): string {
    if ($raw === null) {
        return $default;
    }

    $text = trim((string) $raw);

    return $text !== '' ? $text : $default;
};
$boolText = static fn($flag): string => (int) $flag === 1 ? 'Yes' : 'No';
$currency = static fn($amount): string => number_format((float) ($amount ?? 0), 2);

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

$totalClergy = (int) ($clergy['fulltime_clergy'] ?? 0) + (int) ($clergy['parttime_clergy'] ?? 0) + (int) ($clergy['nonstipendiary_clergy'] ?? 0);
$totalLay = (int) ($clergy['fulltime_lay'] ?? 0) + (int) ($clergy['parttime_lay'] ?? 0) + (int) ($clergy['unpaid_lay'] ?? 0);
$totalStaff = $totalClergy + $totalLay;

$dueYear = $year + 1;
$paperCss = $paperSize === 'a4' ? 'A4' : 'letter';
$reportIdentity = implode(' • ', array_values(array_filter([
    trim((string) ($church['name'] ?? '')),
    trim((string) ($church['city'] ?? '')),
    trim((string) ($church['diocese'] ?? '')),
], static fn(string $part): bool => $part !== '')));
$reportDueText = 'Reports are due to your diocese by March 1, ' . $dueYear . ' and to the General Convention Office by May 1, ' . $dueYear;
$pageLabel = static fn(int $page): string => 'Page ' . $page . ' of 9';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?= esc(($church['name'] ?? 'Church') . ' Parochial Report ' . $year) ?></title>
    <style>
        @page {
            size: <?= $paperCss ?> portrait;
            margin: 0.45in;
        }

        body {
            font-family: Helvetica, Arial, sans-serif;
            color: #111827;
            font-size: 10.5px;
            line-height: 1.28;
            margin: 0;
            background: #fff;
        }

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-bottom: 1px solid #d1d5db;
            background: #f8fafc;
            position: sticky;
            top: 0;
        }

        .toolbar button {
            border: 1px solid #6b7280;
            background: #fff;
            color: #111827;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
        }

        .report-page {
            min-height: 9.85in;
            page-break-after: always;
            display: flex;
            flex-direction: column;
            padding: 0.04in 0;
        }

        .report-page:last-child {
            page-break-after: auto;
        }

        .page-header {
            border-bottom: 1px solid #111827;
            padding-bottom: 6px;
            margin-bottom: 10px;
        }

        .page-header h1,
        .page-header h2,
        .page-header h3,
        .page-header p {
            margin: 0;
        }

        .page-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .page-subtitle {
            font-size: 12px;
            color: #374151;
        }

        .page-meta {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 12px;
            margin-top: 4px;
            font-size: 10px;
            color: #4b5563;
        }

        .page-number {
            font-weight: 700;
            white-space: nowrap;
        }

        .grid {
            display: table;
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .row {
            display: table-row;
        }

        .cell {
            display: table-cell;
            vertical-align: top;
            padding: 4px 8px 4px 0;
        }

        .field {
            margin-bottom: 8px;
        }

        .label {
            display: block;
            font-size: 10px;
            font-weight: 700;
            color: #374151;
            margin-bottom: 2px;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .value {
            border: 1px solid #d1d5db;
            min-height: 16px;
            padding: 4px 6px;
            border-radius: 3px;
            background: #fff;
        }

        .section-title {
            font-size: 12px;
            font-weight: 700;
            margin: 12px 0 7px;
            padding-bottom: 4px;
            border-bottom: 1px solid #e5e7eb;
        }

        .narrative {
            min-height: 82px;
            white-space: pre-wrap;
        }

        .line-item {
            margin-bottom: 8px;
            padding-bottom: 6px;
            border-bottom: 1px dashed #e5e7eb;
        }

        .line-item:last-child {
            border-bottom: 0;
        }

        .line-item strong {
            display: block;
            margin-bottom: 2px;
        }

        .line-help {
            color: #4b5563;
            font-size: 10px;
            margin-bottom: 4px;
        }

        .footer {
            margin-top: auto;
            padding-top: 8px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 16px;
            font-size: 9.5px;
            color: #4b5563;
            border-top: 1px solid #e5e7eb;
        }

        .small {
            font-size: 10px;
        }

        .text-right {
            text-align: right;
        }

        @media print {
            .toolbar {
                display: none;
            }
        }
    </style>
</head>

<body>
    <?php if ($showToolbar): ?>
        <?php $pdfEngineLabel = lang('App.prPdfEngineDompdf'); ?>
        <div class="toolbar">
            <div>
                <strong><?= esc($church['name'] ?? 'Church') ?></strong>
                <span class="small">Parochial Report <?= $year ?> • <?= strtoupper(esc($paperSize)) ?> • <?= esc(lang('App.prPdfEngineLabel')) ?>: <?= esc($pdfEngineLabel) ?></span>
            </div>
            <button type="button" onclick="window.print()">Print</button>
        </div>
    <?php endif; ?>

    <section class="report-page">
        <div class="page-header">
            <div class="page-title">THE <?= esc($year) ?> REPORT OF EPISCOPAL CONGREGATIONS AND MISSIONS</div>
            <div class="page-subtitle">According to Canons I.6, I.7, and I.17 (Otherwise known as the Parochial Report)</div>
            <div class="page-meta">
                <span><?= esc($reportIdentity !== '' ? $reportIdentity : 'Episcopal congregation report') ?></span>
                <span class="page-number"><?= esc($pageLabel(1)) ?></span>
            </div>
        </div>
        <div class="section-title"><?= esc(lang('App.prPage1Title')) ?></div>
        <div class="grid">
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldCongregationName')) ?></span>
                        <div class="value"><?= esc($display($church['name'] ?? '')) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldDiocese')) ?></span>
                        <div class="value"><?= esc($display($church['diocese'] ?? '')) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="grid">
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldStreetAddress1')) ?></span>
                        <div class="value"><?= esc($display($church['street'] ?? '')) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldCity')) ?></span>
                        <div class="value"><?= esc($display($church['city'] ?? '')) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldState')) ?></span>
                        <div class="value"><?= esc($display($church['state'] ?? '')) ?></div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label">Street Address 2</span>
                        <div class="value">—</div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldZipPlus4')) ?></span>
                        <div class="value"><?= esc($display($church['zip'] ?? '')) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldCounty')) ?></span>
                        <div class="value"><?= esc($display($church['county'] ?? '')) ?></div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldCountry')) ?></span>
                        <div class="value"><?= esc($display($church['country'] ?? '')) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldCongregationEmailAddress')) ?></span>
                        <div class="value"><?= esc($display($church['email'] ?? '')) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldCongregationWebAddress')) ?></span>
                        <div class="value"><?= esc($display($church['website'] ?? '')) ?></div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldPhone')) ?></span>
                        <div class="value"><?= esc($display($church['phone'] ?? '')) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldTaxId')) ?></span>
                        <div class="value"><?= esc($display($church['tax_id'] ?? '')) ?></div>
                    </div>
                </div>
                <div class="cell"></div>
            </div>
        </div>

        <div class="section-title"><?= esc(lang('App.prMailingAddressTitle')) ?></div>
        <div class="grid">
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldMailingAddress1')) ?></span>
                        <div class="value"><?= esc($display($value('mailing_address_1', ''))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldMailingAddress2')) ?></span>
                        <div class="value"><?= esc($display($value('mailing_address_2', ''))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldMailingCity')) ?></span>
                        <div class="value"><?= esc($display($value('mailing_city', ''))) ?></div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldState')) ?></span>
                        <div class="value"><?= esc($display($value('mailing_state', ''))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldZipPlus4')) ?></span>
                        <div class="value"><?= esc($display($value('mailing_zip', ''))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldCounty')) ?></span>
                        <div class="value"><?= esc($display($value('mailing_county', ''))) ?></div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldCountry')) ?></span>
                        <div class="value"><?= esc($display($value('mailing_country', ''))) ?></div>
                    </div>
                </div>
                <div class="cell"></div>
                <div class="cell"></div>
            </div>
        </div>

        <div class="section-title"><?= esc(lang('App.prReportPreparationTitle')) ?></div>
        <div class="grid">
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldDataAndDemographicsPreparedBy')) ?></span>
                        <div class="value"><?= esc($display($value('report_prepared_by_name', ''))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldEmailAddress')) ?></span>
                        <div class="value"><?= esc($display($value('report_prepared_by_email', ''))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldDaytimePhone')) ?></span>
                        <div class="value"><?= esc($display($value('report_prepared_by_phone', ''))) ?></div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldStewardshipPreparedBy')) ?></span>
                        <div class="value"><?= esc($display($value('financial_prepared_by_name', ''))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldEmailAddress')) ?></span>
                        <div class="value"><?= esc($display($value('financial_prepared_by_email', ''))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldDaytimePhone')) ?></span>
                        <div class="value"><?= esc($display($value('financial_prepared_by_phone', ''))) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="section-title"><?= esc(lang('App.prCertificationTitle')) ?></div>
        <div class="grid">
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prCertificationClerkTitle')) ?></span>
                        <div class="value"><?= esc($display($value('clerk_certified_by_name', ''))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldEmailAddress')) ?></span>
                        <div class="value"><?= esc($display($value('clerk_certified_by_email', ''))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldDaytimePhone')) ?></span>
                        <div class="value"><?= esc($display($value('clerk_certified_by_phone', ''))) ?></div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prCertificationTreasurerTitle')) ?></span>
                        <div class="value"><?= esc($display($value('treasurer_certified_by_name', ''))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldEmailAddress')) ?></span>
                        <div class="value"><?= esc($display($value('treasurer_certified_by_email', ''))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldDaytimePhone')) ?></span>
                        <div class="value"><?= esc($display($value('treasurer_certified_by_phone', ''))) ?></div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prCertificationRectorTitle')) ?></span>
                        <div class="value"><?= esc($display($value('rector_certified_by_name', ''))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldEmailAddress')) ?></span>
                        <div class="value"><?= esc($display($value('rector_certified_by_email', ''))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldDaytimePhone')) ?></span>
                        <div class="value"><?= esc($display($value('rector_certified_by_phone', ''))) ?></div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prWardenApprovalTitle')) ?></span>
                        <div class="value"><?= esc($display($value('warden_approved_by_name', ''))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldEmailAddress')) ?></span>
                        <div class="value"><?= esc($display($value('warden_approved_by_email', ''))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldDaytimePhone')) ?></span>
                        <div class="value"><?= esc($display($value('warden_approved_by_phone', ''))) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="section-title"><?= esc(lang('App.prCompletionTitle')) ?></div>
        <div class="grid">
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldCompletionParticipantsCount')) ?></span>
                        <div class="value"><?= esc($display($value('completion_participants_count', ''))) ?></div>
                    </div>
                </div>
                <div class="cell" colspan="2">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldCompletionParticipantRoles')) ?></span>
                        <div class="value"><?= esc($display($value('completion_participant_roles', ''))) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="footer">
            <div><?= esc($reportDueText) ?></div>
            <div class="text-right">To file online visit: https://reports.dfms.org</div>
        </div>
    </section>

    <section class="report-page">
        <div class="page-header">
            <div class="page-title"><?= esc(lang('App.prPage2Title')) ?></div>
            <div class="page-meta">
                <span><?= esc($reportIdentity) ?></span>
                <span class="page-number"><?= esc($pageLabel(2)) ?></span>
            </div>
        </div>
        <div class="line-item"><strong><?= esc(lang('App.prLine1Label')) ?></strong>
            <div class="line-help"><?= esc(sprintf(lang('App.prLine1Help'), (int) ($attendance['sunday_inperson_total'] ?? 0), (int) ($attendance['sunday_inperson_services'] ?? 0))) ?></div>
            <div class="value"><?= esc((string) ((int) ($attendance['avg_sunday_inperson'] ?? 0))) ?></div>
        </div>
        <div class="line-item"><strong><?= esc(lang('App.prLine2Label')) ?></strong>
            <div class="line-help"><?= esc(sprintf(lang('App.prLine2Help'), (int) ($attendance['sunday_online_total'] ?? 0), (int) ($attendance['sunday_online_services'] ?? 0))) ?></div>
            <div class="value"><?= esc((string) ((int) ($attendance['avg_sunday_online'] ?? 0))) ?></div>
        </div>
        <div class="line-item"><strong><?= esc(lang('App.prLine3Label')) ?></strong>
            <div class="line-help"><?= esc(sprintf(lang('App.prLine3Help'), (int) ($attendance['weekday_inperson_total'] ?? 0), (int) ($attendance['weekday_inperson_services'] ?? 0))) ?></div>
            <div class="value"><?= esc((string) ((int) ($attendance['avg_weekday_inperson'] ?? 0))) ?></div>
        </div>
        <div class="line-item"><strong><?= esc(lang('App.prLine4Label')) ?></strong>
            <div class="line-help"><?= esc(sprintf(lang('App.prLine4Help'), (int) ($attendance['weekday_online_total'] ?? 0), (int) ($attendance['weekday_online_services'] ?? 0))) ?></div>
            <div class="value"><?= esc((string) ((int) ($attendance['avg_weekday_online'] ?? 0))) ?></div>
        </div>
        <div class="line-item"><strong><?= esc(lang('App.prLine5Label')) ?></strong>
            <div class="value"><?= esc((string) ((int) ($attendance['avg_nonlive_views'] ?? 0))) ?><?= (int) ($value('nonlive_views_estimated', 0) ?? 0) === 1 ? ' (Estimated)' : '' ?></div>
        </div>
        <div class="line-item"><strong><?= esc(lang('App.prLine6Label')) ?></strong>
            <div class="line-help"><?= esc(lang('App.prLine6Help')) ?></div>
            <div class="value"><?= esc((string) $outreachBeneficiaries) ?></div>
        </div>
        <div class="line-item"><strong><?= esc(lang('App.prLine7Label')) ?></strong>
            <div class="line-help"><?= esc(lang('App.prLine7Help')) ?></div>
            <div class="value"><?= esc((string) $nonMemberVolunteers) ?></div>
        </div>
        <div class="line-item"><strong><?= esc(lang('App.prLine8Label')) ?></strong>
            <div class="value"><?= esc((string) $totalImpact) ?></div>
        </div>
        <div class="footer">
            <div><?= esc($reportDueText) ?></div>
            <div class="text-right">To file online visit: https://reports.dfms.org</div>
        </div>
    </section>

    <section class="report-page">
        <div class="page-header">
            <div class="page-title"><?= esc(lang('App.prPage3Title')) ?></div>
            <div class="page-meta">
                <span><?= esc($reportIdentity) ?></span>
                <span class="page-number"><?= esc($pageLabel(3)) ?></span>
            </div>
        </div>
        <div class="grid">
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prLine9Label')) ?></span>
                        <div class="value"><?= esc((string) ((int) ($membership['adult_communicants'] ?? 0))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prLine10Label')) ?></span>
                        <div class="value"><?= esc((string) ((int) ($membership['youth_communicants'] ?? 0))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prLine11Label')) ?></span>
                        <div class="value"><?= esc((string) ((int) ($membership['total_communicants'] ?? 0))) ?></div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(sprintf(lang('App.prFieldPreviousYearMembers'), $year - 1)) ?></span>
                        <div class="value"><?= esc((string) $membershipPrevious) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prLine12Label')) ?></span>
                        <div class="value"><?= esc((string) $membershipIncrease) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prLine13Label')) ?></span>
                        <div class="value"><?= esc((string) $membershipDecrease) ?></div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(sprintf(lang('App.prFieldCurrentYearMembers'), $year)) ?></span>
                        <div class="value"><?= esc((string) $membershipCurrent) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prLine14Label')) ?></span>
                        <div class="value"><?= esc((string) ((int) ($value('active_participants_total', $membership['active_participants'] ?? 0) ?? 0))) ?></div>
                    </div>
                </div>
                <div class="cell"></div>
            </div>
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prLine15Label')) ?></span>
                        <div class="value"><?= esc((string) ((int) ($membership['easter_attendance'] ?? 0))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prLine16Label')) ?></span>
                        <div class="value"><?= esc((string) ((int) ($membership['christmas_attendance'] ?? 0))) ?></div>
                    </div>
                </div>
                <div class="cell"></div>
            </div>
        </div>
        <div class="section-title"><?= esc(lang('App.prLine17Label')) ?></div>
        <div class="grid">
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldFullTimeClergy')) ?></span>
                        <div class="value"><?= esc((string) ((int) ($clergy['fulltime_clergy'] ?? 0))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldPartTimeClergy')) ?></span>
                        <div class="value"><?= esc((string) ((int) ($clergy['parttime_clergy'] ?? 0))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldNonStipendiaryClergy')) ?></span>
                        <div class="value"><?= esc((string) ((int) ($clergy['nonstipendiary_clergy'] ?? 0))) ?></div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldFullTimeLayStaff')) ?></span>
                        <div class="value"><?= esc((string) ((int) ($clergy['fulltime_lay'] ?? 0))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldPartTimeLayStaff')) ?></span>
                        <div class="value"><?= esc((string) ((int) ($clergy['parttime_lay'] ?? 0))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldUnpaidLayStaff')) ?></span>
                        <div class="value"><?= esc((string) ((int) ($clergy['unpaid_lay'] ?? 0))) ?></div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldTotalClergy')) ?></span>
                        <div class="value"><?= esc((string) $totalClergy) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldTotalLayStaff')) ?></span>
                        <div class="value"><?= esc((string) $totalLay) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldTotalStaff')) ?></span>
                        <div class="value"><?= esc((string) $totalStaff) ?></div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldDeacons')) ?></span>
                        <div class="value"><?= esc((string) ((int) ($clergy['deacons'] ?? 0))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldPriests')) ?></span>
                        <div class="value"><?= esc((string) ((int) ($clergy['priests'] ?? 0))) ?></div>
                    </div>
                </div>
                <div class="cell"></div>
            </div>
        </div>
        <div class="line-item"><strong><?= esc(lang('App.prLine18Label')) ?></strong>
            <div class="value"><?= esc($display($value('primary_worship_leader', ''))) ?></div>
        </div>
        <div class="line-item"><strong><?= esc(lang('App.prLine19Label')) ?></strong>
            <div class="value"><?= esc($display(implode(', ', $summary['clergy_situation_list'] ?? []))) ?></div>
        </div>
        <div class="footer">
            <div><?= esc($reportDueText) ?></div>
            <div class="text-right">To file online visit: https://reports.dfms.org</div>
        </div>
    </section>

    <section class="report-page">
        <div class="page-header">
            <div class="page-title"><?= esc(lang('App.prPage4Title')) ?></div>
            <div class="page-meta">
                <span><?= esc($reportIdentity) ?></span>
                <span class="page-number"><?= esc($pageLabel(4)) ?></span>
            </div>
        </div>
        <div class="grid">
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldBaptisms')) ?></span>
                        <div class="value"><?= esc((string) ((int) ($sacraments['baptisms'] ?? 0))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldConfirmations')) ?></span>
                        <div class="value"><?= esc((string) ((int) ($sacraments['confirmations'] ?? 0))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldReceptions')) ?></span>
                        <div class="value"><?= esc((string) ((int) ($sacraments['receptions'] ?? 0))) ?></div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldMarriages')) ?></span>
                        <div class="value"><?= esc((string) ((int) ($sacraments['marriages'] ?? 0))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldBurials')) ?></span>
                        <div class="value"><?= esc((string) ((int) ($sacraments['burials'] ?? 0))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldEucharists')) ?></span>
                        <div class="value"><?= esc((string) ((int) ($sacraments['eucharists'] ?? 0))) ?></div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldDailyOfficeServices')) ?></span>
                        <div class="value"><?= esc((string) ((int) ($sacraments['daily_offices'] ?? 0))) ?></div>
                    </div>
                </div>
                <div class="cell"></div>
                <div class="cell"></div>
            </div>
        </div>
        <div class="line-item"><strong><?= esc(lang('App.prLine21Label')) ?></strong>
            <div class="value"><?= esc($display(implode(', ', $summary['programs_list'] ?? []), '—')) ?><?php if ($display($value('programs_other', ''), '') !== ''): ?>; Other: <?= esc($value('programs_other', '')) ?><?php endif; ?></div>
        </div>
        <div class="line-item"><strong><?= esc(lang('App.prLine22Label')) ?></strong>
            <div class="value"><?= esc($display(implode(', ', $summary['subsidiary_organizations_list'] ?? []), '—')) ?><?php if ($display($value('subsidiary_organizations_other', ''), '') !== ''): ?>; Other: <?= esc($value('subsidiary_organizations_other', '')) ?><?php endif; ?></div>
        </div>
        <div class="line-item"><strong><?= esc(lang('App.prLine23Label')) ?></strong>
            <div class="value"><?= esc($display(implode(', ', $languagesUsed), '—')) ?><?php if ($display($value('languages_other', ''), '') !== ''): ?>; Other: <?= esc($value('languages_other', '')) ?><?php endif; ?></div>
        </div>
        <div class="line-item"><strong><?= esc(lang('App.prLine24Label')) ?></strong>
            <div class="value"><?= esc($boolText($value('simultaneous_multilingual_service', 0))) ?></div>
        </div>
        <div class="line-item"><strong><?= esc(lang('App.prLine25Label')) ?></strong>
            <div class="value"><?= esc($display($value('percent_white', ''))) ?></div>
        </div>
        <div class="line-item"><strong><?= esc(lang('App.prLine26Label')) ?></strong>
            <div class="value"><?= esc($display($value('average_age_override', $membership['average_age'] ?? ''))) ?></div>
        </div>
        <div class="footer">
            <div>Reports are due to your diocese by March 1, <?= esc((string) $dueYear) ?> and to the General Convention Office by May 1, <?= esc((string) $dueYear) ?></div>
            <div class="text-right">To file online visit: https://reports.dfms.org</div>
        </div>
    </section>

    <section class="report-page">
        <div class="page-header">
            <div class="page-title">Page 5. Giving Information and Revenue</div>
            <div class="page-meta">
                <span><?= esc($reportIdentity) ?></span>
                <span class="page-number"><?= esc($pageLabel(5)) ?></span>
            </div>
        </div>
        <div class="grid">
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFieldReportingCurrency')) ?></span>
                        <div class="value"><?= esc($display($value('reporting_currency', 'USD'))) ?></div>
                    </div>
                </div>
                <div class="cell"></div>
                <div class="cell"></div>
            </div>
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFinanceLine1Label')) ?></span>
                        <div class="value"><?= esc((string) ((int) ($value('confirmed_pledges_count', $finance['confirmed_pledges_count'] ?? 0) ?? 0))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFinanceLine2Label')) ?></span>
                        <div class="value"><?= esc($currency($value('confirmed_pledges_amount', $finance['confirmed_pledges_amount'] ?? 0))) ?></div>
                    </div>
                </div>
                <div class="cell"></div>
            </div>
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFinanceLine3Label')) ?></span>
                        <div class="value">$<?= esc($currency($finance['revenue_unrestricted'] ?? 0)) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFinanceLine4Label')) ?></span>
                        <div class="value">$<?= esc($currency($finance['revenue_restricted'] ?? 0)) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFinanceLine5Label')) ?></span>
                        <div class="value">$<?= esc($currency($otherRevenue)) ?></div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFinanceTotalRevenueLabel')) ?></span>
                        <div class="value">$<?= esc($currency($totalRevenue)) ?></div>
                    </div>
                </div>
                <div class="cell"></div>
                <div class="cell"></div>
            </div>
        </div>
        <div class="footer">
            <div>Reports are due to your diocese by March 1, <?= esc((string) $dueYear) ?> and to the General Convention Office by May 1, <?= esc((string) $dueYear) ?></div>
            <div class="text-right">To file online visit: https://reports.dfms.org</div>
        </div>
    </section>

    <section class="report-page">
        <div class="page-header">
            <div class="page-title">Page 6. Expenses and Investments</div>
            <div class="page-meta">
                <span><?= esc($reportIdentity) ?></span>
                <span class="page-number"><?= esc($pageLabel(6)) ?></span>
            </div>
        </div>
        <div class="grid">
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFinanceLine6Label')) ?></span>
                        <div class="value">$<?= esc($currency($finance['expenses_noncapital'] ?? 0)) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFinanceLine7Label')) ?></span>
                        <div class="value">$<?= esc($currency($finance['expenses_capital'] ?? 0)) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFinanceTotalExpensesLabel')) ?></span>
                        <div class="value">$<?= esc($currency($totalExpenses)) ?></div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFinanceLine8Label')) ?></span>
                        <div class="value">$<?= esc($currency($value('cash_balance', 0))) ?></div>
                    </div>
                </div>
                <div class="cell"></div>
                <div class="cell"></div>
            </div>
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFinanceLine9aEndowmentRestricted')) ?></span>
                        <div class="value">$<?= esc($currency($value('endowment_restricted', 0))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFinanceLine9aEndowmentUnrestricted')) ?></span>
                        <div class="value">$<?= esc($currency($value('endowment_unrestricted', 0))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFinanceFieldTotalEndowment')) ?></span>
                        <div class="value">$<?= esc($currency($totalEndowment)) ?></div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFinanceLine9aNonEndowedRestricted')) ?></span>
                        <div class="value">$<?= esc($currency($value('investment_restricted', 0))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFinanceLine9aNonEndowedUnrestricted')) ?></span>
                        <div class="value">$<?= esc($currency($value('investment_unrestricted', 0))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFinanceFieldTotalNonEndowed')) ?></span>
                        <div class="value">$<?= esc($currency($totalNonEndowed)) ?></div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFinanceFieldTotalInvestments')) ?></span>
                        <div class="value">$<?= esc($currency($totalInvestments)) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFinanceLine9bLabel')) ?></span>
                        <div class="value"><?= esc($display($value('pct_draw', '0'))) ?>%</div>
                    </div>
                </div>
                <div class="cell"></div>
            </div>
        </div>
        <div class="footer">
            <div>Reports are due to your diocese by March 1, <?= esc((string) $dueYear) ?> and to the General Convention Office by May 1, <?= esc((string) $dueYear) ?></div>
            <div class="text-right">To file online visit: https://reports.dfms.org</div>
        </div>
    </section>

    <section class="report-page">
        <div class="page-header">
            <div class="page-title">Page 7. Investments, Debt, and Property</div>
            <div class="page-meta">
                <span><?= esc($reportIdentity) ?></span>
                <span class="page-number"><?= esc($pageLabel(7)) ?></span>
            </div>
        </div>
        <div class="grid">
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFinanceLine9cOperatingLabel')) ?></span>
                        <div class="value">$<?= esc($currency($value('amount_drawn_operating', 0))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFinanceLine9cCapitalLabel')) ?></span>
                        <div class="value">$<?= esc($currency($value('amount_drawn_capital', 0))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFinanceLine9dLabel')) ?></span>
                        <div class="value">$<?= esc($currency($value('investment_net_gain_loss', 0))) ?></div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFinanceLine10Label')) ?></span>
                        <div class="value"><?= esc($boolText($value('has_debt', 0))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFinanceLine10aLabel')) ?></span>
                        <div class="value">$<?= esc($currency($value('debt_amount', 0))) ?></div>
                    </div>
                </div>
                <div class="cell"></div>
            </div>
            <div class="row">
                <div class="cell" colspan="3">
                    <div class="field"><span class="label"><?= esc(lang('App.prFinanceLine10bLabel')) ?></span>
                        <div class="value narrative"><?= esc($display($value('debt_description', ''))) ?></div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFinanceLine11Label')) ?></span>
                        <div class="value"><?= esc($boolText($value('owns_property', 0))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFinanceLine12Label')) ?></span>
                        <div class="value"><?= esc($boolText($value('rents_to_outside', 0))) ?></div>
                    </div>
                </div>
                <div class="cell">
                    <div class="field"><span class="label"><?= esc(lang('App.prFinanceLine12aLabel')) ?></span>
                        <div class="value">$<?= esc($currency($value('rental_income', 0))) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="footer">
            <div>Reports are due to your diocese by March 1, <?= esc((string) $dueYear) ?> and to the General Convention Office by May 1, <?= esc((string) $dueYear) ?></div>
            <div class="text-right">To file online visit: https://reports.dfms.org</div>
        </div>
    </section>

    <section class="report-page">
        <div class="page-header">
            <div class="page-title">Page 8. Rental Activity Expenses</div>
            <div class="page-meta">
                <span><?= esc($reportIdentity) ?></span>
                <span class="page-number"><?= esc($pageLabel(8)) ?></span>
            </div>
        </div>
        <div class="line-item">
            <strong><?= esc(lang('App.prFinanceLine12bLabel')) ?></strong>
            <div class="value">$<?= esc($currency($value('rental_expense', 0))) ?></div>
        </div>
        <div class="footer">
            <div>Reports are due to your diocese by March 1, <?= esc((string) $dueYear) ?> and to the General Convention Office by May 1, <?= esc((string) $dueYear) ?></div>
            <div class="text-right">To file online visit: https://reports.dfms.org</div>
        </div>
    </section>

    <section class="report-page">
        <div class="page-header">
            <div class="page-title"><?= esc(lang('App.prPage9Title')) ?></div>
            <div class="page-meta">
                <span><?= esc($reportIdentity) ?></span>
                <span class="page-number"><?= esc($pageLabel(9)) ?></span>
            </div>
        </div>
        <div class="field">
            <span class="label"><?= esc(lang('App.prNarrativeLine13Label')) ?></span>
            <div class="value narrative"><?= esc($display($value('carbon_footprint_notes', ''))) ?></div>
        </div>
        <div class="field">
            <span class="label"><?= esc(lang('App.prNarrativeLine14Label')) ?></span>
            <div class="value narrative"><?= esc($display($value('exemplary_program_text', ''))) ?></div>
        </div>
        <div class="field">
            <span class="label"><?= esc(lang('App.prNarrativeLine15Label')) ?></span>
            <div class="value narrative"><?= esc($display($value('future_hope_text', ''))) ?></div>
        </div>
        <div class="footer">
            <div>Reports are due to your diocese by March 1, <?= esc((string) $dueYear) ?> and to the General Convention Office by May 1, <?= esc((string) $dueYear) ?></div>
            <div class="text-right">To file online visit: https://reports.dfms.org</div>
        </div>
    </section>
</body>

</html>