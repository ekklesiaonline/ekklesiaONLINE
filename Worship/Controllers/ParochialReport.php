<?php

namespace Worship\Controllers;

use App\Controllers\BaseController;
use App\Models\ParishFinancialSummaryModel;
use App\Services\ParochialReportDataService;

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
    private ParochialReportDataService $reportData;

    public function __construct()
    {
        $this->reportData = new ParochialReportDataService();
    }

    public function index(): string|\CodeIgniter\HTTP\RedirectResponse
    {
        if ($redirect = $this->requirePermission('report.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $year = $this->normalizeReportYear($this->request->getGet('year'));
        $forceRecompute = (string) ($this->request->getGet('recompute') ?? '') === '1' && $this->canForceRecompute();
        $pdfEngine = $this->normalizePdfEngine((string) ($this->request->getGet('pdf_engine') ?? 'dompdf'));

        $data = array_merge(
            $this->getCommonViewData(lang('App.navParochialReport')),
            $this->reportData->buildReportViewData($parishId, $year, $forceRecompute),
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
        $year = $this->normalizeReportYear($this->request->getGet('year'));
        $forceRecompute = (string) ($this->request->getGet('recompute') ?? '') === '1' && $this->canForceRecompute();

        $data = $this->reportData->buildReportViewData($parishId, $year, $forceRecompute);
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
        $year = $this->normalizeReportYear($this->request->getGet('year'));
        $forceRecompute = (string) ($this->request->getGet('recompute') ?? '') === '1' && $this->canForceRecompute();
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
        $year = $this->normalizeReportYear($this->request->getPost('year'));
        $forceRecompute = (string) ($this->request->getPost('recompute') ?? '') === '1' && $this->canForceRecompute();
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
        if ($parishId <= 0) {
            return redirect()->to('/parochial-report')->with('error', lang('App.accessDenied'));
        }

        $year = (int) ($this->request->getPost('report_year') ?? ((int) date('Y') - 1));
        $markDone = (string) ($this->request->getPost('mark_done') ?? '') === '1';

        $intFields = 'permit_empty|integer|greater_than_equal_to[0]';
        $amountFields = 'permit_empty|numeric|greater_than_equal_to[0]';

        if (! $this->validate([
            'report_year' => 'required|integer|greater_than[1900]|less_than_equal_to[' . ((int) date('Y') + 1) . ']',
            'mailing_address_1' => 'permit_empty|max_length[255]',
            'mailing_address_2' => 'permit_empty|max_length[255]',
            'rector_certified_date' => 'permit_empty|valid_date',
            'warden_approved_by_name' => 'permit_empty|max_length[255]',
            'warden_approved_by_email' => 'permit_empty|valid_email|max_length[255]',
            'warden_approved_by_phone' => 'permit_empty|max_length[50]',
            'warden_approved_date' => 'permit_empty|valid_date',
            'completion_participants_count' => $intFields,
            'completion_participant_roles' => 'permit_empty|max_length[500]',
            'outreach_beneficiaries_weekly' => $intFields,
            'nonmember_volunteers_weekly' => $intFields,
            'membership_previous_total' => $intFields,
            'membership_increase_total' => $intFields,
            'membership_decrease_total' => $intFields,
            'active_participants_total' => $intFields,
            'primary_worship_leader' => 'permit_empty|max_length[255]',
            'programs_other' => 'permit_empty|max_length[500]',
            'subsidiary_organizations_other' => 'permit_empty|max_length[500]',
            'languages_other' => 'permit_empty|max_length[255]',
            'percent_white' => 'permit_empty|numeric|greater_than_equal_to[0]|less_than_equal_to[100]',
            'average_age_override' => 'permit_empty|numeric|greater_than_equal_to[0]|less_than_equal_to[150]',
            'reporting_currency' => 'permit_empty|max_length[3]',
            'confirmed_pledges_count' => $intFields,
            'confirmed_pledges_amount' => $amountFields,
            'other_revenue_amount' => $amountFields,
            'cash_balance' => 'permit_empty|numeric',
            'endowment_restricted' => $amountFields,
            'endowment_unrestricted' => $amountFields,
            'investment_restricted' => $amountFields,
            'investment_unrestricted' => $amountFields,
            'pct_draw' => 'permit_empty|numeric|greater_than_equal_to[0]|less_than_equal_to[100]',
            'amount_drawn_operating' => 'permit_empty|numeric',
            'amount_drawn_capital' => 'permit_empty|numeric',
            'investment_net_gain_loss' => 'permit_empty|numeric',
            'debt_amount' => $amountFields,
            'debt_description' => 'permit_empty|max_length[5000]',
            'rental_income' => 'permit_empty|numeric',
            'rental_expense' => 'permit_empty|numeric',
            'carbon_footprint_notes' => 'permit_empty|max_length[5000]',
            'exemplary_program_text' => 'permit_empty|max_length[5000]',
            'future_hope_text' => 'permit_empty|max_length[5000]',
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $existing = model(ParishFinancialSummaryModel::class)->getForParishYear($parishId, $year);

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
            // A draft save must not demote a finalized report or erase its
            // frozen snapshot; only mark_done may set finalization fields.
            'report_status' => $markDone ? 'finalized' : ($existing['report_status'] ?? 'draft'),
            'finalized_at' => $existing['finalized_at'] ?? null,
            'finalized_by' => $existing['finalized_by'] ?? null,
            'frozen_snapshot_json' => $existing['frozen_snapshot_json'] ?? null,
        ];

        if ($markDone) {
            $db = db_connect();
            $snapshot = [
                'report_year' => $year,
                'generated_at' => date(DATE_ATOM),
                'attendance' => $this->reportData->getAttendanceMetrics($db, $parishId, $year),
                'sacraments' => $this->reportData->getSacramentalMetrics($db, $parishId, $year),
                'membership' => $this->reportData->getMembershipMetrics($db, $parishId, $year),
                'clergy' => $this->reportData->getClergyMetrics($db, $parishId),
                'finance' => $this->reportData->getFinancialMetrics($db, $parishId, $year),
                'languages_used' => $this->reportData->getLanguagesUsed($db, $parishId, $year),
            ];

            $data['frozen_snapshot_json'] = json_encode($snapshot, JSON_UNESCAPED_SLASHES);
            $data['finalized_at'] = date('Y-m-d H:i:s');
            $data['finalized_by'] = auth()->id() !== null ? (int) auth()->id() : null;
        }

        if (! model(ParishFinancialSummaryModel::class)->upsertForYear($data)) {
            return redirect()->back()->withInput()->with('error', lang('App.accessDenied'));
        }

        // Field values are intentionally omitted from the audit payload —
        // they include financial figures; log only the action's meta.
        $this->audit()->log('parochial_report.save', [
            'parish_id' => $parishId,
            'entity_type' => 'parochial_report',
            'meta' => [
                'report_year' => $year,
                'mark_done' => $markDone,
            ],
        ]);

        return redirect()
            ->to(base_url('parochial-report?year=' . $year))
            ->with('success', lang($markDone ? 'App.prReportMarkedDone' : 'App.prReportDraftSaved'));
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
        $user = auth()->user();
        if ($user === null || ! $user->inGroup('systemadmin')) {
            return false;
        }

        $normalized = strtolower(trim($flag));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    private function normalizeReportYear($year): int
    {
        $value = (int) ($year ?? 0);
        $currentYear = (int) date('Y');

        return ($value > 1900 && $value <= $currentYear + 1) ? $value : $currentYear - 1;
    }

    private function canForceRecompute(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can('report.parochial');
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
        $data = $this->reportData->buildReportViewData($parishId, $year, $forceRecompute);
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
}
