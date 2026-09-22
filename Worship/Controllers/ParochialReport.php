<?php

namespace Worship\Controllers;

use App\Controllers\BaseController;
use App\Libraries\DataEncryption;
use App\Models\AnnualPledgeModel;
use App\Models\ParishFinancialSummaryModel;

/**
 * Authoritative TEC parochial report controller.
 *
 * The field contract follows docs/EPISCOPAL_PAROCHIAL_REPORT_FOR_REFERENCE.md.
 * Computed values are auto-filled from parish data, service metrics, attendance,
 * member standing, pledges, donations, and expenses. Manual report-year fields
 * are persisted in parish_report_storage.
 */
class ParochialReport extends BaseController
{
    public function index(): string|\CodeIgniter\HTTP\RedirectResponse
    {
        if ($redirect = $this->requirePermission('report.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $year = (int) ($this->request->getGet('year') ?? ((int) date('Y') - 1));
        $forceRecompute = (string) ($this->request->getGet('recompute') ?? '') === '1';
        $pdfEngine = $this->normalizePdfEngine((string) ($this->request->getGet('pdf_engine') ?? 'dompdf'));

        $data = array_merge(
            $this->getCommonViewData(lang('App.navParochialReport')),
            $this->buildReportViewData($parishId, $year, $forceRecompute),
            ['pdf_engine' => $pdfEngine]
        );

        return view('Worship\Views\ParochialReport/index', $data);
    }

    public function print(): string|\CodeIgniter\HTTP\RedirectResponse
    {
        if ($redirect = $this->requirePermission('report.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $year = (int) ($this->request->getGet('year') ?? ((int) date('Y') - 1));
        $forceRecompute = (string) ($this->request->getGet('recompute') ?? '') === '1';

        $data = $this->buildReportViewData($parishId, $year, $forceRecompute);
        $data['paper_size'] = $this->normalizePaperSize((string) ($this->request->getGet('paper_size') ?? 'letter'));
        $data['pdf_engine'] = $this->normalizePdfEngine((string) ($this->request->getGet('pdf_engine') ?? 'dompdf'));
        $data['show_toolbar'] = true;

        return view('Worship\Views\ParochialReport/print', $data);
    }

    public function exportPdf(): \CodeIgniter\HTTP\ResponseInterface|\CodeIgniter\HTTP\RedirectResponse
    {
        if ($redirect = $this->requirePermission('report.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $year = (int) ($this->request->getGet('year') ?? ((int) date('Y') - 1));
        $forceRecompute = (string) ($this->request->getGet('recompute') ?? '') === '1';
        $paperSize = $this->normalizePaperSize((string) ($this->request->getGet('paper_size') ?? 'letter'));
        $pdfEngine = $this->normalizePdfEngine((string) ($this->request->getGet('pdf_engine') ?? 'dompdf'));
        $pdfDebug = $this->normalizeDebugFlag((string) ($this->request->getGet('pdf_debug') ?? '0'));
        $pdf = $this->buildPdfPayload($parishId, $year, $forceRecompute, $paperSize, $pdfEngine, $pdfDebug);

        $this->audit()->log('parochial_report.export_pdf', [
            'parish_id' => $parishId,
            'entity_type' => 'parochial_report',
            'meta' => [
                'report_year' => $year,
                'paper_size' => $paperSize,
                'pdf_engine_requested' => $pdfEngine,
                'pdf_engine_used' => $pdf['engine'],
                'pdf_debug' => $pdfDebug,
                'force_recompute' => $forceRecompute,
            ],
        ]);

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('X-PDF-Engine', $pdf['engine'])
            ->setHeader('X-PDF-Debug', $pdfDebug ? '1' : '0')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $pdf['filename'] . '"')
            ->setBody($pdf['bytes']);
    }

    public function emailPdf(): \CodeIgniter\HTTP\RedirectResponse
    {
        if ($redirect = $this->requirePermission('report.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $year = (int) ($this->request->getPost('year') ?? ((int) date('Y') - 1));
        $forceRecompute = (string) ($this->request->getPost('recompute') ?? '') === '1';
        $paperSize = $this->normalizePaperSize((string) ($this->request->getPost('paper_size') ?? 'letter'));
        $pdfEngine = $this->normalizePdfEngine((string) ($this->request->getPost('pdf_engine') ?? 'dompdf'));
        $pdfDebug = $this->normalizeDebugFlag((string) ($this->request->getPost('pdf_debug') ?? '0'));
        $recipient = trim((string) ($this->request->getPost('recipient_email') ?? ''));

        if ($recipient === '' || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            return redirect()
                ->to(base_url('parochial-report?year=' . $year . '&pdf_engine=' . rawurlencode($pdfEngine)))
                ->withInput()
                ->with('error', lang('App.prEmailPdfInvalidRecipient'));
        }

        $pdf = $this->buildPdfPayload($parishId, $year, $forceRecompute, $paperSize, $pdfEngine, $pdfDebug);
        $tempPath = tempnam(WRITEPATH . 'cache', 'pr_pdf_');

        if ($tempPath === false || file_put_contents($tempPath, $pdf['bytes']) === false) {
            return redirect()
                ->to(base_url('parochial-report?year=' . $year . '&pdf_engine=' . rawurlencode($pdfEngine)))
                ->with('error', lang('App.prEmailPdfSendFailed'));
        }

        try {
            $email = (new \App\Services\EmailScopeService())->mailer(
                ['parish_id' => $parishId],
                ['overrides' => ['mailType' => 'html']]
            );
            $email->clear(true);
            $email->setTo($recipient);
            $parishName = (string) ($pdf['parish_name'] ?? '');
            $email->setSubject('Parochial Report ' . $year . ' - ' . $parishName);
            $email->setMessage(
                '<p>Please find the attached parochial report PDF for <strong>' . esc($parishName) . '</strong>.</p>'
                    . '<p>Report year: ' . esc((string) $year) . '<br>Paper size: ' . esc(strtoupper($paperSize)) . '</p>'
            );
            $email->attach($tempPath, 'attachment', $pdf['filename'], 'application/pdf');

            $sent = $email->send(false);

            if (!$sent) {
                log_message('error', 'Parochial report PDF email failed for parish {parishId}: {code}', [
                    'parishId' => $parishId,
                    'code'     => \App\Libraries\EmailFailureClassifier::classify((string) $email->printDebugger(['headers'])),
                ]);
            }
        } catch (\Throwable $e) {
            log_message('error', 'Parochial report PDF email failed for parish {parishId}: {message}', [
                'parishId' => $parishId,
                'message' => $e->getMessage(),
            ]);
            $sent = false;
        }

        @unlink($tempPath);

        if (!$sent) {
            return redirect()
                ->to(base_url('parochial-report?year=' . $year . '&pdf_engine=' . rawurlencode($pdfEngine)))
                ->withInput()
                ->with('error', lang('App.prEmailPdfSendFailed'));
        }

        $this->audit()->log('parochial_report.email_pdf', [
            'parish_id' => $parishId,
            'entity_type' => 'parochial_report',
            'meta' => [
                'report_year' => $year,
                'paper_size' => $paperSize,
                'pdf_engine_requested' => $pdfEngine,
                'pdf_engine_used' => $pdf['engine'],
                'pdf_debug' => $pdfDebug,
                'recipient_email' => $recipient,
                'force_recompute' => $forceRecompute,
            ],
        ]);

        return redirect()
            ->to(base_url('parochial-report?year=' . $year . '&pdf_engine=' . rawurlencode($pdfEngine)))
            ->with('success', lang('App.prEmailPdfSent'));
    }

    public function save(): \CodeIgniter\HTTP\RedirectResponse
    {
        if ($redirect = $this->requirePermission('report.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $year = (int) ($this->request->getPost('report_year') ?? ((int) date('Y') - 1));
        $markDone = (string) ($this->request->getPost('mark_done') ?? '') === '1';

        $int = fn(string $field): ?int => ($value = $this->request->getPost($field)) !== null && $value !== '' ? (int) $value : null;
        $float = fn(string $field): ?float => ($value = $this->request->getPost($field)) !== null && $value !== '' ? (float) $value : null;
        $string = fn(string $field): ?string => ($value = $this->request->getPost($field)) !== null && trim((string) $value) !== '' ? trim((string) $value) : null;
        $date = fn(string $field): ?string => ($value = $this->request->getPost($field)) !== null && trim((string) $value) !== '' ? trim((string) $value) : null;
        $flag = fn(string $field): int => (int) ($this->request->getPost($field) === '1');
        $json = fn(string $field): ?string => ($values = $this->request->getPost($field)) !== null && $values !== []
            ? json_encode(array_values(array_filter((array) $values, static fn($item): bool => trim((string) $item) !== '')), JSON_UNESCAPED_SLASHES)
            : null;

        $amountDrawnOperating = $float('amount_drawn_operating');
        $amountDrawnCapital = $float('amount_drawn_capital');

        $data = [
            'parish_id' => $parishId,
            'report_year' => $year,
            'mailing_address_1' => $string('mailing_address_1'),
            'mailing_address_2' => $string('mailing_address_2'),
            'rector_certified_date' => $date('rector_certified_date'),
            'warden_approved_by_name' => $string('warden_approved_by_name'),
            'warden_approved_by_email' => $string('warden_approved_by_email'),
            'warden_approved_by_phone' => $string('warden_approved_by_phone'),
            'warden_approved_date' => $date('warden_approved_date'),
            'completion_participants_count' => $int('completion_participants_count'),
            'completion_participant_roles' => $string('completion_participant_roles'),
            'outreach_beneficiaries_weekly' => $int('outreach_beneficiaries_weekly'),
            'nonmember_volunteers_weekly' => $int('nonmember_volunteers_weekly'),
            'nonlive_views_estimated' => $flag('nonlive_views_estimated'),
            'membership_previous_total' => $int('membership_previous_total'),
            'membership_increase_total' => $int('membership_increase_total'),
            'membership_decrease_total' => $int('membership_decrease_total'),
            'active_participants_total' => $int('active_participants_total'),
            'primary_worship_leader' => $string('primary_worship_leader'),
            'clergy_situation_json' => $json('clergy_situation'),
            'programs_json' => $json('programs'),
            'programs_other' => $string('programs_other'),
            'subsidiary_organizations_json' => $json('subsidiary_organizations'),
            'subsidiary_organizations_other' => $string('subsidiary_organizations_other'),
            'languages_other' => $string('languages_other'),
            'simultaneous_multilingual_service' => $flag('simultaneous_multilingual_service'),
            'percent_white' => $float('percent_white'),
            'average_age_override' => $float('average_age_override'),
            'reporting_currency' => $string('reporting_currency'),
            'confirmed_pledges_count' => $int('confirmed_pledges_count'),
            'confirmed_pledges_amount' => $float('confirmed_pledges_amount'),
            'other_revenue_amount' => $float('other_revenue_amount'),
            'cash_balance' => $float('cash_balance'),
            'endowment_restricted' => $float('endowment_restricted'),
            'endowment_unrestricted' => $float('endowment_unrestricted'),
            'investment_restricted' => $float('investment_restricted'),
            'investment_unrestricted' => $float('investment_unrestricted'),
            'pct_draw' => $float('pct_draw'),
            'amount_drawn_operating' => $amountDrawnOperating,
            'amount_drawn_capital' => $amountDrawnCapital,
            'amount_drawn' => (($amountDrawnOperating ?? 0.0) + ($amountDrawnCapital ?? 0.0)) ?: null,
            'investment_net_gain_loss' => $float('investment_net_gain_loss'),
            'has_debt' => $flag('has_debt'),
            'debt_amount' => $float('debt_amount'),
            'debt_description' => $string('debt_description'),
            'owns_property' => $flag('owns_property'),
            'rents_to_outside' => $flag('rents_to_outside'),
            'rental_income' => $float('rental_income'),
            'rental_expense' => $float('rental_expense'),
            'carbon_footprint_notes' => $string('carbon_footprint_notes'),
            'exemplary_program_text' => $string('exemplary_program_text'),
            'future_hope_text' => $string('future_hope_text'),
            'report_status' => $markDone ? 'finalized' : 'draft',
            'finalized_at' => null,
            'finalized_by' => null,
            'frozen_snapshot_json' => null,
        ];

        if ($markDone) {
            $db = db_connect();
            $snapshot = [
                'report_year' => $year,
                'generated_at' => date(DATE_ATOM),
                'attendance' => $this->getAttendanceMetrics($db, $parishId, $year),
                'sacraments' => $this->getSacramentalMetrics($db, $parishId, $year),
                'membership' => $this->getMembershipMetrics($db, $parishId, $year),
                'clergy' => $this->getClergyMetrics($db, $parishId),
                'finance' => $this->getFinancialMetrics($db, $parishId, $year),
                'languages_used' => $this->getLanguagesUsed($db, $parishId, $year),
            ];

            $data['frozen_snapshot_json'] = json_encode($snapshot, JSON_UNESCAPED_SLASHES);
            $data['finalized_at'] = date('Y-m-d H:i:s');
            $data['finalized_by'] = auth()->id() !== null ? (int) auth()->id() : null;
        }

        model(ParishFinancialSummaryModel::class)->upsertForYear($data);

        return redirect()
            ->to(base_url('parochial-report?year=' . $year))
            ->with('success', lang($markDone ? 'App.prReportMarkedDone' : 'App.prReportDraftSaved'));
    }

    private function buildReportViewData(int $parishId, int $year, bool $forceRecompute): array
    {
        $db = db_connect();
        $summaryModel = model(ParishFinancialSummaryModel::class);

        $parish = $this->getParishData($db, $parishId);
        $summary = $summaryModel->getForParishYear($parishId, $year) ?? [];
        $previousSummary = $summaryModel->getForParishYear($parishId, $year - 1) ?? [];
        $snapshot = $this->decodeJsonObject($summary['frozen_snapshot_json'] ?? null);
        $isFinalized = ($summary['report_status'] ?? 'draft') === 'finalized';
        $usingFrozenSnapshot = !$forceRecompute && $isFinalized && $snapshot !== [];

        if ($usingFrozenSnapshot) {
            $attendance = is_array($snapshot['attendance'] ?? null) ? $snapshot['attendance'] : [];
            $sacraments = is_array($snapshot['sacraments'] ?? null) ? $snapshot['sacraments'] : [];
            $membership = is_array($snapshot['membership'] ?? null) ? $snapshot['membership'] : [];
            $clergy = is_array($snapshot['clergy'] ?? null) ? $snapshot['clergy'] : [];
            $finance = is_array($snapshot['finance'] ?? null) ? $snapshot['finance'] : [];
            $languages = is_array($snapshot['languages_used'] ?? null) ? $snapshot['languages_used'] : [];
        } else {
            $attendance = $this->getAttendanceMetrics($db, $parishId, $year);
            $sacraments = $this->getSacramentalMetrics($db, $parishId, $year);
            $membership = $this->getMembershipMetrics($db, $parishId, $year);
            $clergy = $this->getClergyMetrics($db, $parishId);
            $finance = $this->getFinancialMetrics($db, $parishId, $year);
            $languages = $this->getLanguagesUsed($db, $parishId, $year);
        }

        $summary['programs_list'] = $this->decodeJsonList($summary['programs_json'] ?? null);
        $summary['subsidiary_organizations_list'] = $this->decodeJsonList($summary['subsidiary_organizations_json'] ?? null);
        $summary['clergy_situation_list'] = $this->decodeJsonList($summary['clergy_situation_json'] ?? null);

        if (!isset($summary['membership_previous_total']) || $summary['membership_previous_total'] === null) {
            $summary['membership_previous_total'] = $this->deriveReportedMemberTotal($previousSummary) ?? ($membership['member_total'] ?? 0);
        }

        if (!isset($summary['reporting_currency']) || trim((string) $summary['reporting_currency']) === '') {
            $summary['reporting_currency'] = 'USD';
        }

        return [
            'year' => $year,
            'parish' => $parish,
            'summary' => $summary,
            'attendance' => $attendance,
            'sacraments' => $sacraments,
            'membership' => $membership,
            'clergy' => $clergy,
            'finance' => $finance,
            'languages_used' => $languages,
            'available_years' => $this->getAvailableYears($parishId, $db),
            'is_finalized' => $isFinalized,
            'using_frozen_snapshot' => $usingFrozenSnapshot,
            'force_recompute' => $forceRecompute,
            'has_frozen_snapshot' => $snapshot !== [],
        ];
    }

    private function normalizePaperSize(string $paperSize): string
    {
        $normalized = strtolower(trim($paperSize));

        return in_array($normalized, ['a4', 'letter'], true) ? $normalized : 'letter';
    }

    private function normalizePdfEngine(string $pdfEngine): string
    {
        return 'dompdf';
    }

    private function normalizeDebugFlag(string $flag): bool
    {
        $normalized = strtolower(trim($flag));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    private function buildPdfFilename(string $parishName, int $year): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '_', $parishName), '_'));
        if ($slug === '') {
            $slug = 'parish';
        }

        return 'parochial_report_' . $slug . '_' . $year . '.pdf';
    }

    private function buildPdfPayload(int $parishId, int $year, bool $forceRecompute, string $paperSize, string $pdfEngine = 'dompdf', bool $pdfDebug = false): array
    {
        $data = $this->buildReportViewData($parishId, $year, $forceRecompute);
        $data['paper_size'] = $paperSize;
        $data['show_toolbar'] = false;
        $data['pdf_engine'] = 'dompdf';

        $parishName = (string) ($data['parish']['name'] ?? 'parish');
        $html = view('Worship\Views\ParochialReport/print', $data);
        $bytes = $this->buildPdfWithDompdf($html, $paperSize);

        return [
            'parish_name' => $parishName,
            'filename' => $this->buildPdfFilename($parishName, $year),
            'engine' => 'dompdf',
            'bytes' => $bytes,
        ];
    }

    private function buildPdfWithDompdf(string $html, string $paperSize): string
    {
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper($paperSize === 'a4' ? 'A4' : 'letter', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function getParishData($db, int $parishId): array
    {
        $parish = $db->table('parish c')
            ->select('c.*, d.name AS diocese_name')
            ->join('diocese d', 'd.id = c.diocese_id', 'left')
            ->where('c.id', $parishId)
            ->get()
            ->getRowArray() ?? [];
        $parish['phone'] = DataEncryption::decrypt((string) ($parish['phone'] ?? ''));
        $parish['diocese'] = (string) ($parish['diocese_name'] ?? '');

        $addressParts = array_filter([
            trim((string) ($parish['street'] ?? '')),
            trim((string) ($parish['city'] ?? '')),
            trim((string) ($parish['state'] ?? '')),
            trim((string) ($parish['zip'] ?? '')),
            trim((string) ($parish['county'] ?? '')),
            trim((string) ($parish['country'] ?? '')),
        ], static fn(string $part): bool => $part !== '');

        $parish['formatted_address'] = $addressParts !== []
            ? implode(', ', $addressParts)
            : trim((string) ($parish['address'] ?? ''));

        return $parish;
    }

    private function getAttendanceMetrics($db, int $parishId, int $year): array
    {
        $sundayRows = $db->query(
            "SELECT sm.reported_present_count, sm.online_live_viewers, sm.online_recorded_views, sm.format_of_service
             FROM service_metric sm
             JOIN service s ON s.id = sm.service_id
             WHERE s.parish_id = ?
               AND YEAR(s.service_date) = ?
               AND sm.service_schedule = 'sunday'",
            [$parishId, $year]
        )->getResultArray();

        $weekdayRows = $db->query(
            "SELECT sm.reported_present_count, sm.online_live_viewers, sm.format_of_service
             FROM service_metric sm
             JOIN service s ON s.id = sm.service_id
             WHERE s.parish_id = ?
               AND YEAR(s.service_date) = ?
               AND sm.service_schedule = 'weekday'",
            [$parishId, $year]
        )->getResultArray();

        $sundayInPersonRows = array_values(array_filter($sundayRows, static fn(array $row): bool => ($row['format_of_service'] ?? '') !== 'Online'));
        $sundayOnlineRows = array_values(array_filter($sundayRows, static fn(array $row): bool => in_array((string) ($row['format_of_service'] ?? ''), ['Online', 'Hybrid'], true)));
        $weekdayInPersonRows = array_values(array_filter($weekdayRows, static fn(array $row): bool => ($row['format_of_service'] ?? '') !== 'Online'));
        $weekdayOnlineRows = array_values(array_filter($weekdayRows, static fn(array $row): bool => in_array((string) ($row['format_of_service'] ?? ''), ['Online', 'Hybrid'], true)));

        $recordedRows = $db->query(
            "SELECT sm.online_recorded_views
             FROM service_metric sm
             JOIN service s ON s.id = sm.service_id
             WHERE s.parish_id = ?
               AND YEAR(s.service_date) = ?
               AND sm.online_recorded_views IS NOT NULL",
            [$parishId, $year]
        )->getResultArray();

        return [
            'avg_sunday_inperson' => $this->averageOfColumn($sundayInPersonRows, 'reported_present_count'),
            'avg_sunday_online' => $this->averageOfColumn($sundayOnlineRows, 'online_live_viewers'),
            'avg_weekday_inperson' => $this->averageOfColumn($weekdayInPersonRows, 'reported_present_count'),
            'avg_weekday_online' => $this->averageOfColumn($weekdayOnlineRows, 'online_live_viewers'),
            'avg_nonlive_views' => $this->averageOfColumn($recordedRows, 'online_recorded_views'),
            'sunday_inperson_total' => $this->sumOfColumn($sundayInPersonRows, 'reported_present_count'),
            'sunday_inperson_services' => count($sundayInPersonRows),
            'sunday_online_total' => $this->sumOfColumn($sundayOnlineRows, 'online_live_viewers'),
            'sunday_online_services' => count($sundayOnlineRows),
            'weekday_inperson_total' => $this->sumOfColumn($weekdayInPersonRows, 'reported_present_count'),
            'weekday_inperson_services' => count($weekdayInPersonRows),
            'weekday_online_total' => $this->sumOfColumn($weekdayOnlineRows, 'online_live_viewers'),
            'weekday_online_services' => count($weekdayOnlineRows),
            'recorded_services_count' => count($recordedRows),
        ];
    }

    private function getSacramentalMetrics($db, int $parishId, int $year): array
    {
        $row = $db->query(
            "SELECT
                COALESCE(SUM(sm.baptism_adult + sm.baptism_child), 0) AS baptisms,
                COALESCE(SUM(sm.confirmation_adult + sm.confirmation_teen), 0) AS confirmations,
                COALESCE(SUM(sm.reception_count), 0) AS receptions,
                COALESCE(SUM(sm.marriage_count), 0) AS marriages,
                COALESCE(SUM(sm.burial_count), 0) AS burials,
                COALESCE(SUM(sm.eucharist_count), 0) AS eucharists,
                COALESCE(SUM(sm.daily_office_count), 0) AS daily_offices
             FROM service_metric sm
             JOIN service s ON s.id = sm.service_id
             WHERE s.parish_id = ?
               AND YEAR(s.service_date) = ?",
            [$parishId, $year]
        )->getRowArray() ?? [];

        return [
            'baptisms' => (int) ($row['baptisms'] ?? 0),
            'confirmations' => (int) ($row['confirmations'] ?? 0),
            'receptions' => (int) ($row['receptions'] ?? 0),
            'marriages' => (int) ($row['marriages'] ?? 0),
            'burials' => (int) ($row['burials'] ?? 0),
            'eucharists' => (int) ($row['eucharists'] ?? 0),
            'daily_offices' => (int) ($row['daily_offices'] ?? 0),
        ];
    }

    private function getMembershipMetrics($db, int $parishId, int $year): array
    {
        $asOfDate = sprintf('%04d-12-31', $year);

        $adultCommunicants = (int) ($db->query(
            "SELECT COUNT(*) AS cnt
             FROM profile p
             JOIN parish_affiliation mca ON mca.linked_user_id = p.user_id AND mca.parish_id = ?
             LEFT JOIN member_standing_summary mss ON mss.user_id = p.user_id AND mss.year = ?
             WHERE COALESCE(mss.is_good_standing, 0) = 1
               AND p.baptism_date IS NOT NULL
               AND (p.birth_date IS NULL OR TIMESTAMPDIFF(YEAR, p.birth_date, ?) >= 16)",
            [$parishId, $year, $asOfDate]
        )->getRowArray()['cnt'] ?? 0);

        $youthCommunicants = (int) ($db->query(
            "SELECT COUNT(*) AS cnt
             FROM profile p
             JOIN parish_affiliation mca ON mca.linked_user_id = p.user_id AND mca.parish_id = ?
             LEFT JOIN member_standing_summary mss ON mss.user_id = p.user_id AND mss.year = ?
             WHERE COALESCE(mss.is_good_standing, 0) = 1
               AND p.baptism_date IS NOT NULL
               AND p.birth_date IS NOT NULL
               AND TIMESTAMPDIFF(YEAR, p.birth_date, ?) < 16",
            [$parishId, $year, $asOfDate]
        )->getRowArray()['cnt'] ?? 0);

        $memberTotal = (int) ($db->query(
            'SELECT COUNT(*) AS cnt FROM parish_affiliation WHERE parish_id = ? AND is_member = 1',
            [$parishId]
        )->getRowArray()['cnt'] ?? 0);

        $activeMembersOther = (int) ($db->query(
            "SELECT COUNT(*) AS cnt
             FROM parish_affiliation mca
             LEFT JOIN member_standing_summary mss ON mss.user_id = mca.linked_user_id AND mss.year = ?
             WHERE mca.parish_id = ?
               AND mca.is_member = 1
               AND COALESCE(mss.is_good_standing, 0) = 0",
            [$year, $parishId]
        )->getRowArray()['cnt'] ?? 0);

        $activeParticipants = (int) ($db->query(
            "SELECT COUNT(DISTINCT p.user_id) AS cnt
             FROM profile p
             JOIN parish_affiliation mca ON mca.linked_user_id = p.user_id AND mca.parish_id = ?
             JOIN attendance a ON a.user_id = p.user_id AND a.attended = 1
             JOIN service s ON s.id = a.service_id AND s.parish_id = ? AND YEAR(s.service_date) = ?
             WHERE mca.is_member = 0
               AND mca.membership_status IN ('visitor', 'regular_attender')",
            [$parishId, $parishId, $year]
        )->getRowArray()['cnt'] ?? 0);

        $averageAge = $db->query(
            'SELECT AVG(TIMESTAMPDIFF(YEAR, birth_date, ?)) AS avg_age FROM profile WHERE parish_id = ? AND birth_date IS NOT NULL',
            [$asOfDate, $parishId]
        )->getRowArray();

        return [
            'adult_communicants' => $adultCommunicants,
            'youth_communicants' => $youthCommunicants,
            'total_communicants' => $adultCommunicants + $youthCommunicants,
            'member_total' => $memberTotal,
            'active_members_other' => $activeMembersOther,
            'active_participants' => $activeParticipants,
            'average_age' => isset($averageAge['avg_age']) && $averageAge['avg_age'] !== null ? round((float) $averageAge['avg_age'], 1) : null,
            'easter_attendance' => $this->getHolidayAttendance($db, $parishId, $year, 'easter'),
            'christmas_attendance' => $this->getHolidayAttendance($db, $parishId, $year, 'christmas'),
        ];
    }

    private function getClergyMetrics($db, int $parishId): array
    {
        $counts = [
            'fulltime_clergy' => 0,
            'parttime_clergy' => 0,
            'nonstipendiary_clergy' => 0,
            'fulltime_lay' => 0,
            'parttime_lay' => 0,
            'unpaid_lay' => 0,
            'deacons' => 0,
            'priests' => 0,
        ];

        if (!$this->tableExists('leadership_position')) {
            return $counts;
        }

        $hasClergyOrder = $this->columnExists('leadership_position', 'clergy_order');
        $rows = $db->query(
            'SELECT employment_type, role_category' . ($hasClergyOrder ? ', clergy_order' : '') . ' FROM leadership_position WHERE parish_id = ? AND active = 1',
            [$parishId]
        )->getResultArray();

        foreach ($rows as $row) {
            $roleCategory = strtolower((string) ($row['role_category'] ?? ''));
            $employmentType = strtolower((string) ($row['employment_type'] ?? ''));
            if ($roleCategory === 'clergy' && $employmentType === 'full_time') {
                $counts['fulltime_clergy']++;
            }
            if ($roleCategory === 'clergy' && $employmentType === 'part_time') {
                $counts['parttime_clergy']++;
            }
            if ($roleCategory === 'clergy' && in_array($employmentType, ['non_stipendiary', 'unpaid'], true)) {
                $counts['nonstipendiary_clergy']++;
            }
            if ($roleCategory === 'lay_staff' && $employmentType === 'full_time') {
                $counts['fulltime_lay']++;
            }
            if ($roleCategory === 'lay_staff' && $employmentType === 'part_time') {
                $counts['parttime_lay']++;
            }
            if ($roleCategory === 'lay_staff' && in_array($employmentType, ['volunteer', 'unpaid', 'non_stipendiary'], true)) {
                $counts['unpaid_lay']++;
            }

            $clergyOrder = strtolower((string) ($row['clergy_order'] ?? ''));
            if ($clergyOrder === 'deacon') {
                $counts['deacons']++;
            }
            if ($clergyOrder === 'priest') {
                $counts['priests']++;
            }
        }

        return $counts;
    }

    private function getFinancialMetrics($db, int $parishId, int $year): array
    {
        $startDate = sprintf('%04d-01-01', $year);
        $endDate = sprintf('%04d-12-31', $year);

        $donationRows = $db->table('donation')
            ->select('amount, is_restricted')
            ->where('parish_id', $parishId)
            ->where('date >=', $startDate)
            ->where('date <=', $endDate)
            ->get()
            ->getResultArray();

        $unrestricted = 0.0;
        $restricted = 0.0;
        foreach ($donationRows as $row) {
            $amount = $this->normalizeEncryptedNumeric($row['amount'] ?? null);
            if ((int) ($row['is_restricted'] ?? 0) === 1) {
                $restricted += $amount;
            } else {
                $unrestricted += $amount;
            }
        }

        $expenseRow = $db->query(
            "SELECT
                COALESCE(SUM(CASE WHEN is_capital = 0 THEN amount ELSE 0 END), 0) AS noncapital,
                COALESCE(SUM(CASE WHEN is_capital = 1 THEN amount ELSE 0 END), 0) AS capital
                         FROM expense
             WHERE parish_id = ?
               AND date >= ?
               AND date <= ?",
            [$parishId, $startDate, $endDate]
        )->getRowArray() ?? [];

        $pledges = model(AnnualPledgeModel::class)->getParishPledgeSummary($parishId, $year);

        return [
            'confirmed_pledges_count' => (int) ($pledges['total_pledges'] ?? 0),
            'confirmed_pledges_amount' => (float) ($pledges['pledged_total'] ?? 0),
            'revenue_unrestricted' => round($unrestricted, 2),
            'revenue_restricted' => round($restricted, 2),
            'expenses_noncapital' => (float) ($expenseRow['noncapital'] ?? 0),
            'expenses_capital' => (float) ($expenseRow['capital'] ?? 0),
        ];
    }

    private function getLanguagesUsed($db, int $parishId, int $year): array
    {
        $rows = $db->query(
            "SELECT sm.languages_json
             FROM service_metric sm
             JOIN service s ON s.id = sm.service_id
             WHERE s.parish_id = ?
               AND YEAR(s.service_date) = ?
               AND sm.languages_json IS NOT NULL",
            [$parishId, $year]
        )->getResultArray();

        $set = [];
        foreach ($rows as $row) {
            $decoded = json_decode((string) ($row['languages_json'] ?? ''), true);
            if (!is_array($decoded)) {
                continue;
            }

            foreach ($decoded as $language) {
                $language = trim((string) $language);
                if ($language !== '') {
                    $set[$language] = true;
                }
            }
        }

        return array_keys($set);
    }

    private function getHolidayAttendance($db, int $parishId, int $year, string $holiday): int
    {
        if ($holiday === 'christmas') {
            $row = $db->query(
                "SELECT COALESCE(SUM(sm.reported_present_count), 0) AS cnt
                 FROM service s
                 JOIN service_metric sm ON sm.service_id = s.id
                 WHERE s.parish_id = ?
                   AND YEAR(s.service_date) = ?
                   AND (
                        s.liturgical_day LIKE '%Christmas%'
                        OR JSON_SEARCH(s.service, 'one', 'Christmas Eve') IS NOT NULL
                        OR JSON_SEARCH(s.service, 'one', 'Christmas Day') IS NOT NULL
                   )",
                [$parishId, $year]
            )->getRowArray();

            return (int) ($row['cnt'] ?? 0);
        }

        $easterDayRow = $db->query(
            "SELECT MIN(s.service_date) AS easter_day_date
             FROM service s
             WHERE s.parish_id = ?
               AND YEAR(s.service_date) = ?
               AND (
                    s.liturgical_day LIKE '%Easter Day%'
                    OR JSON_SEARCH(s.service, 'one', 'Easter Day') IS NOT NULL
               )",
            [$parishId, $year]
        )->getRowArray();

        $easterDayDate = $easterDayRow['easter_day_date'] ?? null;
        $easterVigilDate = $easterDayDate !== null ? (string) date('Y-m-d', strtotime((string) $easterDayDate . ' -1 day')) : null;

        $row = $db->query(
            "SELECT COALESCE(SUM(sm.reported_present_count), 0) AS cnt
             FROM service s
             JOIN service_metric sm ON sm.service_id = s.id
             WHERE s.parish_id = ?
               AND YEAR(s.service_date) = ?
               AND (
                    (
                        s.liturgical_day LIKE '%Easter Day%'
                        OR JSON_SEARCH(s.service, 'one', 'Easter Day') IS NOT NULL
                    )
                    OR (
                        ? IS NOT NULL
                        AND s.service_date = ?
                        AND (
                            s.liturgical_day LIKE '%Easter Vigil%'
                            OR JSON_SEARCH(s.service, 'one', 'Easter Vigil') IS NOT NULL
                        )
                    )
               )",
            [$parishId, $year, $easterVigilDate, $easterVigilDate]
        )->getRowArray();

        return (int) ($row['cnt'] ?? 0);
    }

    private function averageOfColumn(array $rows, string $column): int
    {
        $values = array_filter(array_map(static fn(array $row): mixed => $row[$column] ?? null, $rows), static fn($value): bool => $value !== null && $value !== '');
        if ($values === []) {
            return 0;
        }

        return (int) round(array_sum($values) / count($values));
    }

    private function sumOfColumn(array $rows, string $column): int
    {
        return (int) round(array_sum(array_map(static fn(array $row): float => (float) ($row[$column] ?? 0), $rows)));
    }

    private function decodeJsonList(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? array_values(array_filter(array_map(static fn($item): string => trim((string) $item), $decoded), static fn(string $item): bool => $item !== '')) : [];
    }

    private function decodeJsonObject(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function deriveReportedMemberTotal(array $summary): ?int
    {
        if ($summary === []) {
            return null;
        }

        if (!isset($summary['membership_previous_total']) || $summary['membership_previous_total'] === null) {
            return null;
        }

        return (int) $summary['membership_previous_total']
            + (int) ($summary['membership_increase_total'] ?? 0)
            - (int) ($summary['membership_decrease_total'] ?? 0);
    }

    private function normalizeEncryptedNumeric(mixed $value): float
    {
        $plain = DataEncryption::decrypt($value !== null ? (string) $value : null);
        return is_numeric((string) $plain) ? (float) $plain : 0.0;
    }

    private function tableExists(string $table): bool
    {
        try {
            return db_connect()->tableExists($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            return in_array($column, db_connect()->getFieldNames($table), true);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function getAvailableYears(int $parishId, $db): array
    {
        $rows = $db->query(
            'SELECT DISTINCT YEAR(s.service_date) AS yr FROM service s WHERE s.parish_id = ? ORDER BY yr DESC LIMIT 10',
            [$parishId]
        )->getResultArray();

        return array_map('intval', array_column($rows, 'yr'));
    }
}
