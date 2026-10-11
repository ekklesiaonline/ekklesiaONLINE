<?php

namespace Finance\Controllers;

use App\Controllers\BaseController;
use App\Models\ChartOfAccountModel;
use App\Models\JournalEntryModel;

/**
 * General Ledger Journal Entries Explorer
 *
 * Allows parish treasurers and financial officers to view, search, and audit
 * balanced double-entry transactions posted to the General Ledger.
 */
class JournalEntries extends BaseController
{
    protected JournalEntryModel $journalEntryModel;
    protected ChartOfAccountModel $chartAccountModel;

    public function __construct()
    {
        $this->journalEntryModel = model(JournalEntryModel::class);
        $this->chartAccountModel = model(ChartOfAccountModel::class);
    }

    /**
     * List journal entries for the operational parish with filtering and summary KPIs.
     */
    public function index()
    {
        if ($redirect = $this->requirePermission('finance.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);

        $filters = $this->extractFilters();

        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = (int) ($this->request->getGet('per_page') ?? 25);
        if ($perPage < 10 || $perPage > 100) {
            $perPage = 25;
        }
        $offset = ($page - 1) * $perPage;

        $entries = $this->journalEntryModel->getFilteredEntries($parishId, $filters, $perPage, $offset);
        $totalRows = $this->journalEntryModel->countFilteredEntries($parishId, $filters);
        $totalPages = max(1, (int) ceil($totalRows / $perPage));
        if ($page > $totalPages && $totalPages > 0) {
            $page = $totalPages;
        }

        $summary = $this->journalEntryModel->getEntriesSummary($parishId, $filters);
        $chartAccounts = $this->chartAccountModel->where('is_active', 1)->orderBy('code', 'ASC')->findAll();

        $filtersActive = !empty($filters['start_date'])
            || !empty($filters['end_date'])
            || !empty($filters['source_type'])
            || !empty($filters['status'])
            || !empty($filters['chart_account_id'])
            || !empty($filters['search']);

        return view('Finance\Views\JournalEntries/index', array_merge($this->getCommonViewData(lang('Finance.journalEntriesTitle')), [
            'entries'         => $entries,
            'summary'         => $summary,
            'filters'         => $filters,
            'filtersActive'   => $filtersActive,
            'chartAccounts'   => $chartAccounts,
            'page'            => $page,
            'perPage'         => $perPage,
            'totalRows'       => $totalRows,
            'totalPages'      => $totalPages,
            'canManage'       => auth()->user() !== null && auth()->user()->can('finance.manage'),
        ]));
    }

    /**
     * View detailed line items for a specific journal entry.
     *
     * @param int|string $id
     */
    public function view($id)
    {
        if ($redirect = $this->requirePermission('finance.view')) {
            if ($this->request->isAJAX()) {
                return $this->contractResponse(false, 403, 'FORBIDDEN', lang('App.errorForbidden'));
            }
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            if ($this->request->isAJAX()) {
                return $this->contractResponse(false, 403, 'PARISH_SCOPE_REQUIRED', lang('App.errorNoParishScope'));
            }
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $entryId = (int) $id;

        $entry = $this->journalEntryModel->getEntryWithLines($entryId, $parishId);

        if (!$entry) {
            if ($this->request->isAJAX()) {
                return $this->contractResponse(false, 404, 'NOT_FOUND', lang('Finance.journalEntryNotFound'));
            }
            return redirect()->to('/journal-entries')->with('error', lang('Finance.journalEntryNotFound'));
        }

        if ($this->request->isAJAX() || $this->request->getGet('format') === 'json') {
            return $this->contractResponse(true, 200, 'OK', 'Success', [
                'entry' => $entry,
            ]);
        }

        return view('Finance\Views\JournalEntries/view', array_merge($this->getCommonViewData($entry['entry_number']), [
            'entry'     => $entry,
            'canManage' => auth()->user() !== null && auth()->user()->can('finance.manage'),
        ]));
    }

    /**
     * Export filtered journal entries to CSV.
     */
    public function export()
    {
        if ($redirect = $this->requirePermission('finance.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $filters = $this->extractFilters();

        $entries = $this->journalEntryModel->getFilteredEntries($parishId, $filters, 5000, 0);

        $filename = 'journal_entries_' . $parishId . '_' . date('Ymd_His') . '.csv';

        $output = fopen('php://temp', 'r+');
        if ($output === false) {
            return redirect()->back()->with('error', lang('App.errorGeneric'));
        }

        // CSV Header
        fputcsv($output, [
            lang('Finance.colEntryNumber'),
            lang('Finance.colEntryDate'),
            lang('Finance.colSource'),
            lang('Finance.colStatus'),
            lang('Finance.colMemo'),
            lang('Finance.colLineCount'),
            lang('Finance.colTotalDebits'),
            lang('Finance.colTotalCredits'),
        ]);

        foreach ($entries as $e) {
            fputcsv($output, [
                $e['entry_number'] ?? '',
                $e['entry_date'] ?? '',
                ucfirst((string) ($e['source_type'] ?? '')),
                ucfirst((string) ($e['status'] ?? '')),
                $e['memo'] ?? '',
                $e['line_count'] ?? 0,
                number_format((float) ($e['total_debit'] ?? 0), 2, '.', ''),
                number_format((float) ($e['total_credit'] ?? 0), 2, '.', ''),
            ]);
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($csvContent ?: '');
    }

    /**
     * Extract sanitized query filters.
     *
     * @return array<string, mixed>
     */
    protected function extractFilters(): array
    {
        return [
            'start_date'       => trim((string) ($this->request->getGet('start_date') ?? '')),
            'end_date'         => trim((string) ($this->request->getGet('end_date') ?? '')),
            'source_type'      => trim((string) ($this->request->getGet('source_type') ?? '')),
            'status'           => trim((string) ($this->request->getGet('status') ?? '')),
            'chart_account_id' => (int) ($this->request->getGet('chart_account_id') ?? 0),
            'search'           => trim((string) ($this->request->getGet('search') ?? '')),
        ];
    }

    /**
     * Standard API contract response helper.
     *
     * @param array<string, mixed> $details
     * @param array<string, mixed> $errors
     * @param array<string, mixed>|null $data
     */
    private function contractResponse(
        bool $success,
        int $httpStatus,
        string $code,
        string $message,
        ?array $data = null,
        array $details = [],
        array $errors = []
    ) {
        $payload = [
            'success' => $success,
            'status'  => $success ? 'success' : 'error',
            'message' => $message,
            'meta'    => [
                'http_status' => $httpStatus,
                'ts'          => date('c'),
            ],
        ];

        if ($success) {
            if ($data !== null) {
                $payload['data'] = $data;
            }
        } else {
            $payload['error'] = [
                'code'    => $code,
                'message' => $message,
                'details' => $details,
            ];
            $payload['errors'] = $errors;
        }

        return $this->response->setStatusCode($httpStatus)->setJSON($payload);
    }
}
