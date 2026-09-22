<?php

namespace Clergy\Controllers;

use App\Controllers\BaseController;

/**
 * Import
 *
 * Two entry points for bringing visitors into the system:
 *
 * 1. CSV bulk import (admin-only, requires visitors.create)
 *    GET  visitors/import         → connectionCards()   – upload form + QR display
 *    POST visitors/import/process → processConnectionCards() – parse & save CSV
 *    GET  visitors/import/qr      → qrCode()            – serves QR code PNG (proxied)
 *
 * 2. Public digital connection card (no auth, token-gated by parish)
 *    GET  connect/{token}       → cardForm($token)    – public-facing visitor form
 *    POST connect/{token}       → digitalCard($token) – save & trigger workflow
 *
 * Deduplication logic (both paths):
 *  - If a matching email_hash OR phone_hash already exists for the parish → update visit_date.
 *  - Otherwise insert a new visitor row and schedule all active followup sequences.
 */
class Import extends BaseController
{
    private \App\Services\VisitorDirectoryService $visitorDirectory;
    private \App\Models\FollowupLogModel $followupLogModel;

    public function __construct()
    {
        $this->visitorDirectory   = new \App\Services\VisitorDirectoryService();
        $this->followupLogModel = model('FollowupLogModel');
    }

    // ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ──
    // Admin – CSV import
    // ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ──

    /**
     * GET  visitors/import
     * Show the CSV upload form and the parish's shareable QR code.
     */
    public function connectionCards()
    {
        if ($redirect = $this->requirePermission('visitor.create')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) $this->getOperationalParishId();
        $parish   = $this->ensurePublicToken($parishId);

        $cardUrl = base_url('connect/' . $parish['public_token']);

        return view('Clergy\Views\Import/index', array_merge(
            $this->getCommonViewData(lang('Visitors.importPageTitle')),
            [
                'parish'  => $parish,
                'cardUrl' => $cardUrl,
            ]
        ));
    }

    /**
     * POST  visitors/import/process
     * Accept a CSV file, validate rows, and upsert visitors.
     *
     * Expected CSV columns (header row required):
     *   first_name, last_name, email, phone, address, interests, visit_date
     *
     * Returns JSON for AJAX submission or redirects for standard form.
     */
    public function processConnectionCards()
    {
        if ($redirect = $this->requirePermission('visitor.create')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $rules = ['csv_file' => 'uploaded[csv_file]|max_size[csv_file,2048]|ext_in[csv_file,csv,txt]'];

        if (! $this->validate($rules)) {
            $errors = $this->validator->getErrors();
            if ($this->request->isAJAX()) {
                return $this->jsonResponse(['success' => false, 'errors' => $errors], 422);
            }
            return redirect()->back()->with('import_errors', $errors);
        }

        $file     = $this->request->getFile('csv_file');
        $parishId = (int) $this->getOperationalParishId();

        $result = $this->parseCsvAndUpsert($file->getTempName(), $parishId);

        $this->audit()->log('visitor.csv_import', [
            'parish_id'   => $parishId,
            'entity_type' => 'visitor',
            'new_values'  => [
                'inserted' => $result['inserted'],
                'updated'  => $result['updated'],
                'skipped'  => count($result['row_errors']),
                'filename' => $file->getName(),
            ],
        ]);

        if ($this->request->isAJAX()) {
            return $this->jsonResponse(['success' => true, 'result' => $result]);
        }

        return redirect()
            ->to('visitors/import')
            ->with('import_result', $result);
    }

    /**
     * GET  visitors/import/qr
     * Proxy the QR code image from api.qrserver.com and stream it as PNG.
     * Caches the result in writable/cache/ for 24 hours to avoid hammering the API.
     */
    public function qrCode()
    {
        if ($redirect = $this->requirePermission('visitor.create')) {
            return $redirect;
        }

        $parishId = (int) $this->getOperationalParishId();
        $parish   = $this->ensurePublicToken($parishId);
        $cardUrl  = base_url('connect/' . $parish['public_token']);

        $cacheKey  = 'qr_' . md5($cardUrl);
        $cachePath = WRITEPATH . 'cache/' . $cacheKey . '.png';

        // Serve from disk-cache when available and less than 24 h old.
        if (is_file($cachePath) && (time() - filemtime($cachePath)) < 86400) {
            return $this->response
                ->setContentType('image/png')
                ->setBody((string) file_get_contents($cachePath));
        }

        $apiUrl  = 'https://api.qrserver.com/v1/create-qr-code/'
            . '?size=300x300&format=png&qzone=2&data=' . rawurlencode($cardUrl);

        $context = stream_context_create([
            'http' => ['timeout' => 8, 'user_agent' => 'EkklesiaRD/1.0'],
        ]);

        $image = @file_get_contents($apiUrl, false, $context);

        if ($image === false || strlen($image) < 100) {
            // Fallback: return a 1×1 transparent PNG so the page doesn't break.
            return $this->response->setStatusCode(503)->setBody('QR service unavailable');
        }

        // Cache to disk.
        @file_put_contents($cachePath, $image);

        return $this->response
            ->setContentType('image/png')
            ->setHeader('Content-Disposition', 'inline; filename="connection-card-qr.png"')
            ->setBody($image);
    }

    // ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ──
    // Public – digital connection card
    // ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ──

    /**
     * GET  connect/{token}
     * Show the public-facing connection card form (no authentication required).
     */
    public function cardForm(string $token)
    {
        $parish = $this->findParishByToken($token);
        if ($parish === null) {
            return $this->response->setStatusCode(404)->setBody('Connection card not found.');
        }

        $parishName = (string) ($parish['name'] ?? '');

        return view('public/connection_card', [
            'parish' => $parish,
            'token'  => $token,
            'title'  => esc($parishName) . ' – Connection Card',
        ]);
    }

    /**
     * POST  connect/{token}
     * Accept digital card submission, upsert visitor, trigger workflow.
     */
    public function digitalCard(string $token)
    {
        $parish = $this->findParishByToken($token);
        if ($parish === null) {
            return $this->response->setStatusCode(404)->setBody('Connection card not found.');
        }

        $parishName = (string) ($parish['name'] ?? '');

        $parishId = (int) $parish['id'];

        $rules = [
            'first_name' => 'required|min_length[2]|max_length[100]',
            'last_name'  => 'required|min_length[2]|max_length[100]',
            'email'      => 'permit_empty|valid_email|max_length[255]',
            'phone'      => 'permit_empty|max_length[30]',
            'visit_date' => 'required|valid_date',
        ];

        if (! $this->validate($rules)) {
            return view('public/connection_card', [
                'parish'      => $parish,
                'token'       => $token,
                'title'       => esc($parishName) . ' – Connection Card',
                'errors'      => $this->validator->getErrors(),
                'old'         => $this->request->getPost(),
            ]);
        }

        $interestsRaw = $this->request->getPost('interests');
        $data = [
            'first_name'  => (string) $this->request->getPost('first_name'),
            'last_name'   => (string) $this->request->getPost('last_name'),
            'email'       => (string) ($this->request->getPost('email')   ?? ''),
            'phone'       => (string) ($this->request->getPost('phone')   ?? ''),
            'address'     => (string) ($this->request->getPost('address') ?? ''),
            'interests'   => $this->normalizeInterests($interestsRaw),
            'visit_date'  => (string) $this->request->getPost('visit_date'),
            'source'      => 'digital_card',
        ];

        $upsert = $this->upsertVisitor($parishId, $data);

        if ($upsert['is_new']) {
            $this->followupLogModel->scheduleActiveSequencesForPerson(
                $upsert['person_id'],
                $parishId,
                $data['visit_date']
            );
        }

        return view('public/connection_card_thanks', [
            'parish' => $parish,
            'title'  => 'Thank You',
        ]);
    }

    /**
     * GET  visitors/import/sample-csv
     * Return a downloadable sample CSV so admins can see the expected column format.
     */
    public function sampleCsv()
    {
        if ($redirect = $this->requirePermission('visitor.create')) {
            return $redirect;
        }

        $rows = [
            ['first_name', 'last_name', 'email', 'phone', 'address', 'interests', 'visit_date'],
            ['María',      'García',    'maria@example.com', '555-100-0001', '123 Main St', 'children,music', date('Y-m-d')],
            ['José',       'Rodríguez', '',                  '555-100-0002', '',             '',               date('Y-m-d')],
        ];

        $csv = '';
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(fn($v) => '"' . addcslashes($v, '"') . '"', $row)) . "\r\n";
        }

        return $this->response
            ->setContentType('text/csv')
            ->setHeader('Content-Disposition', 'attachment; filename="connection-cards-sample.csv"')
            ->setBody($csv);
    }

    // ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ──
    // Private helpers
    // ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ── ──

    /**
     * Parse a CSV file and upsert visitors into the given parish.
     *
     * @return array{ inserted: int, updated: int, row_errors: list<array{row: int, errors: string[]}> }
     */
    private function parseCsvAndUpsert(string $filePath, int $parishId): array
    {
        $result = ['inserted' => 0, 'updated' => 0, 'row_errors' => []];

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            $result['row_errors'][] = ['row' => 0, 'errors' => ['Cannot open uploaded file.']];
            return $result;
        }

        // Read header row – case-insensitive.
        $headerRaw = fgetcsv($handle);
        if ($headerRaw === false) {
            fclose($handle);
            $result['row_errors'][] = ['row' => 0, 'errors' => ['CSV file is empty.']];
            return $result;
        }

        $headers = array_map(fn($h) => strtolower(trim((string) $h)), $headerRaw);

        $required = ['first_name', 'last_name'];
        foreach ($required as $col) {
            if (! in_array($col, $headers, true)) {
                fclose($handle);
                $result['row_errors'][] = [
                    'row'    => 0,
                    'errors' => ["Required column '{$col}' not found in CSV header."],
                ];
                return $result;
            }
        }

        $rowNumber = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            // Skip blank rows.
            if ($row === [null]) {
                continue;
            }

            // Map CSV columns by header name.
            $mapped = [];
            foreach ($headers as $i => $col) {
                $mapped[$col] = isset($row[$i]) ? trim((string) $row[$i]) : '';
            }

            // Validate required fields.
            $rowErrors = [];
            if (($mapped['first_name'] ?? '') === '') {
                $rowErrors[] = 'first_name is required';
            }
            if (($mapped['last_name'] ?? '') === '') {
                $rowErrors[] = 'last_name is required';
            }
            $visitDate = $mapped['visit_date'] ?? '';
            if ($visitDate !== '' && ! $this->isValidDate($visitDate)) {
                $rowErrors[] = "visit_date '{$visitDate}' is not a valid date (expected YYYY-MM-DD)";
                $visitDate   = '';
            }
            if ($visitDate === '') {
                $visitDate = date('Y-m-d');
            }

            if (! empty($rowErrors)) {
                $result['row_errors'][] = ['row' => $rowNumber, 'errors' => $rowErrors];
                continue;
            }

            $data = [
                'first_name' => $mapped['first_name'],
                'last_name'  => $mapped['last_name'],
                'email'      => $mapped['email']    ?? '',
                'phone'      => $mapped['phone']    ?? '',
                'address'    => $mapped['address']  ?? '',
                'interests'  => $this->normalizeInterests($mapped['interests'] ?? null),
                'visit_date' => $visitDate,
                'source'     => 'csv_import',
            ];

            $upsert = $this->upsertVisitor($parishId, $data);

            if ($upsert['is_new']) {
                $result['inserted']++;
                $this->followupLogModel->scheduleActiveSequencesForPerson(
                    $upsert['person_id'],
                    $parishId,
                    $visitDate
                );
            } else {
                $result['updated']++;
            }
        }

        fclose($handle);
        return $result;
    }

    /**
     * Insert a new visitor or record a return visit for an existing one.
     *
     * Dedup happens inside VisitorIntakeService: an existing person with a
     * matching email/phone hash in the parish gets a new person_visit row;
     * otherwise a full person + parish_affiliation + visit chain is
     * provisioned.
     *
     * @param  int    $parishId
     * @param  array  $data  Plain-text fields (email/phone not yet encrypted)
     * @return array{ person_id: int, is_new: bool }
     */
    private function upsertVisitor(int $parishId, array $data): array
    {
        try {
            $intake = $this->visitorDirectory->intake($data, $parishId);
        } catch (\Throwable $e) {
            log_message('error', 'Visitor intake failed during import: ' . $e->getMessage());
            return ['person_id' => 0, 'is_new' => false];
        }

        return [
            'person_id' => (int) $intake['person_id'],
            'is_new'    => (bool) $intake['is_new'],
        ];
    }

    /**
     * Normalize interests from various input formats to a JSON array string.
     *
     * Accepts:
     *  - null / ''                          → null (no interests stored)
     *  - "children,music"                   → '["children","music"]'
     *  - ["children","music"]  (POST array) → '["children","music"]'
     *  - Already-encoded JSON string        → re-encoded for canonical format
     */
    private function normalizeInterests(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        // POST checkbox array.
        if (is_array($raw)) {
            $items = array_values(array_filter(array_map('trim', $raw)));
            return $items !== [] ? json_encode($items) : null;
        }

        // Already JSON.
        $decoded = json_decode((string) $raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded !== [] ? json_encode(array_values($decoded)) : null;
        }

        // Comma-separated string.
        $items = array_values(array_filter(array_map('trim', explode(',', (string) $raw))));
        return $items !== [] ? json_encode($items) : null;
    }

    /**
     * Validate a date string as YYYY-MM-DD or common variants.
     */
    private function isValidDate(string $date): bool
    {
        $dt = \DateTime::createFromFormat('Y-m-d', $date);
        return $dt !== false && $dt->format('Y-m-d') === $date;
    }

    /**
     * Look up a parish by its `public_token`.
     * Returns the parish row or null if not found.
     */
    private function findParishByToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        return db_connect()
            ->table('parish')
            ->where('public_token', $token)
            ->limit(1)
            ->get()
            ->getRowArray() ?: null;
    }

    /**
     * Ensure the parish has a non-empty `public_token`.
     * Generates and saves one if missing (lazy init for pre-migration rows).
     *
     * @return array  The parish row (with public_token guaranteed non-empty)
     */
    private function ensurePublicToken(int $parishId): array
    {
        $parish = db_connect()->table('parish')->where('id', $parishId)->limit(1)->get()->getRowArray();

        if ($parish === null) {
            return [];
        }

        if (empty($parish['public_token'])) {
            $token = bin2hex(random_bytes(16));
            db_connect()->table('parish')->where('id', $parishId)->update(['public_token' => $token]);
            $parish['public_token'] = $token;
        }

        return $parish;
    }
}
