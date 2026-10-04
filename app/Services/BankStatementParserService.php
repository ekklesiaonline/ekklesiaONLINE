<?php

namespace App\Services;

/**
 * Parses bank statement files (OFX, QFX, CSV) into standardized transaction rows.
 *
 * Output row format:
 * [
 *     'transaction_date' => 'YYYY-MM-DD',
 *     'amount'           => float (positive for credits/deposits, negative for debits/withdrawals),
 *     'description'      => ?string,
 *     'reference'        => ?string,
 * ]
 */
class BankStatementParserService
{
    /**
     * Parse an uploaded statement file by detecting its format.
     *
     * @param string $filePath Absolute path to the file.
     * @param string $originalName Original filename to inspect extension.
     * @return array<int, array{transaction_date: string, amount: float, description: ?string, reference: ?string}>
     */
    public function parse(string $filePath, string $originalName = ''): array
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            return [];
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $content = file_get_contents($filePath);
        if ($content === false || trim($content) === '') {
            return [];
        }

        if (
            $extension === 'ofx' ||
            $extension === 'qfx' ||
            stripos($content, '<OFX>') !== false ||
            stripos($content, '<STMTTRN>') !== false ||
            stripos($content, 'OFXHEADER') !== false
        ) {
            return $this->parseOfx($content);
        }

        return $this->parseCsv($content);
    }

    /**
     * Parse OFX / QFX content.
     *
     * Handles both OFX 1.x (SGML unclosed tags) and OFX 2.x (XML closed tags).
     *
     * @return array<int, array{transaction_date: string, amount: float, description: ?string, reference: ?string}>
     */
    public function parseOfx(string $content): array
    {
        $transactions = [];

        // Match each <STMTTRN> block
        preg_match_all('/<STMTTRN>(.*?)(?:<\/STMTTRN>|(?=<STMTTRN>)|(?=<\/BANKTRANLIST>)|$)/is', $content, $matches);

        if (empty($matches[1])) {
            return [];
        }

        foreach ($matches[1] as $block) {
            $type     = $this->extractOfxTag($block, 'TRNTYPE');
            $dtPosted = $this->extractOfxTag($block, 'DTPOSTED');
            $trnAmt   = $this->extractOfxTag($block, 'TRNAMT');
            $fitId    = $this->extractOfxTag($block, 'FITID');
            $name     = $this->extractOfxTag($block, 'NAME');
            $memo     = $this->extractOfxTag($block, 'MEMO');
            $checkNum = $this->extractOfxTag($block, 'CHECKNUM');

            if ($dtPosted === null || $trnAmt === null) {
                continue;
            }

            $date = $this->parseOfxDate($dtPosted);
            if ($date === null) {
                continue;
            }

            $amount = (float) $trnAmt;
            if (strtoupper((string) $type) === 'DEBIT' && $amount > 0) {
                $amount = -$amount;
            } elseif (strtoupper((string) $type) === 'CREDIT' && $amount < 0) {
                $amount = abs($amount);
            }

            $descriptionParts = array_filter([$name, $memo]);
            $description = implode(' - ', array_unique($descriptionParts));
            if ($description === '') {
                $description = null;
            } else {
                $description = mb_substr($description, 0, 255);
            }

            $reference = $checkNum ?: $fitId;
            if ($reference !== null) {
                $reference = mb_substr($reference, 0, 100);
            }

            $transactions[] = [
                'transaction_date' => $date,
                'amount'           => $amount,
                'description'      => $description,
                'reference'        => $reference,
            ];
        }

        return $transactions;
    }

    private function extractOfxTag(string $block, string $tag): ?string
    {
        if (preg_match('/<' . preg_quote($tag, '/') . '>([^<\r\n]+)(?:<\/' . preg_quote($tag, '/') . '>)?/i', $block, $match)) {
            $val = trim($match[1]);
            return $val !== '' ? html_entity_decode($val, ENT_QUOTES | ENT_XML1, 'UTF-8') : null;
        }

        return null;
    }

    private function parseOfxDate(string $raw): ?string
    {
        $clean = preg_replace('/[^0-9]/', '', $raw) ?? '';
        if (strlen($clean) >= 8) {
            $year  = (int) substr($clean, 0, 4);
            $month = (int) substr($clean, 4, 2);
            $day   = (int) substr($clean, 6, 2);

            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        return null;
    }

    /**
     * Parse CSV statement content.
     *
     * Detects delimiters, headers (Date, Amount, Debit, Credit, Description, Reference).
     *
     * @return array<int, array{transaction_date: string, amount: float, description: ?string, reference: ?string}>
     */
    public function parseCsv(string $content): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (empty($lines)) {
            return [];
        }

        $firstLine = $lines[0];
        $delimiter = $this->detectDelimiter($firstLine);

        $headerIndex = -1;
        $columnMap = [];

        foreach ($lines as $idx => $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = str_getcsv($line, $delimiter);
            $map = $this->detectColumns($cols);
            if (isset($map['date']) && (isset($map['amount']) || (isset($map['debit']) && isset($map['credit'])))) {
                $headerIndex = $idx;
                $columnMap = $map;
                break;
            }
        }

        if ($headerIndex === -1) {
            $columnMap = ['date' => 0, 'description' => 1, 'amount' => 2];
            $headerIndex = 0;
        }

        $transactions = [];

        for ($i = $headerIndex + 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if ($line === '') {
                continue;
            }

            $cols = str_getcsv($line, $delimiter);
            if (count($cols) < 2) {
                continue;
            }

            $rawDate = trim((string) ($cols[$columnMap['date']] ?? ''));
            $date = $this->parseCsvDate($rawDate);
            if ($date === null) {
                continue;
            }

            $amount = null;
            if (isset($columnMap['amount']) && isset($cols[$columnMap['amount']])) {
                $amount = $this->parseCsvAmount($cols[$columnMap['amount']]);
            } elseif (isset($columnMap['debit']) || isset($columnMap['credit'])) {
                $debitVal  = isset($columnMap['debit'], $cols[$columnMap['debit']]) ? $this->parseCsvAmount($cols[$columnMap['debit']]) : null;
                $creditVal = isset($columnMap['credit'], $cols[$columnMap['credit']]) ? $this->parseCsvAmount($cols[$columnMap['credit']]) : null;

                if ($debitVal !== null && $debitVal != 0) {
                    $amount = -abs($debitVal);
                } elseif ($creditVal !== null && $creditVal != 0) {
                    $amount = abs($creditVal);
                }
            }

            if ($amount === null) {
                continue;
            }

            $description = null;
            if (isset($columnMap['description']) && isset($cols[$columnMap['description']])) {
                $desc = trim((string) $cols[$columnMap['description']]);
                if ($desc !== '') {
                    $description = mb_substr($desc, 0, 255);
                }
            }

            $reference = null;
            if (isset($columnMap['reference']) && isset($cols[$columnMap['reference']])) {
                $ref = trim((string) $cols[$columnMap['reference']]);
                if ($ref !== '') {
                    $reference = mb_substr($ref, 0, 100);
                }
            }

            $transactions[] = [
                'transaction_date' => $date,
                'amount'           => $amount,
                'description'      => $description,
                'reference'        => $reference,
            ];
        }

        return $transactions;
    }

    private function detectDelimiter(string $sample): string
    {
        $delimiters = [',', ';', "\t"];
        $bestDelimiter = ',';
        $maxCount = 0;

        foreach ($delimiters as $delim) {
            $count = substr_count($sample, $delim);
            if ($count > $maxCount) {
                $maxCount = $count;
                $bestDelimiter = $delim;
            }
        }

        return $bestDelimiter;
    }

    /**
     * Map CSV column headers to roles.
     *
     * @param array<int, string> $cols
     * @return array<string, int>
     */
    private function detectColumns(array $cols): array
    {
        $map = [];

        foreach ($cols as $idx => $rawHeader) {
            $h = strtolower(trim((string) $rawHeader));
            $h = preg_replace('/[^\w\s]/u', '', $h) ?? '';
            $h = trim($h);

            // Date
            if (!isset($map['date']) && preg_match('/^(date|transaction\s*date|post\s*date|posting\s*date|fecha|trans\s*date)$/i', $h)) {
                $map['date'] = $idx;
                continue;
            }

            // Debit
            if (!isset($map['debit']) && preg_match('/^(debit|withdrawal|outflow|cargo|debito|retiro)$/i', $h)) {
                $map['debit'] = $idx;
                continue;
            }

            // Credit
            if (!isset($map['credit']) && preg_match('/^(credit|deposit|inflow|abono|credito|deposito)$/i', $h)) {
                $map['credit'] = $idx;
                continue;
            }

            // Single Amount
            if (!isset($map['amount']) && preg_match('/^(amount|net\s*amount|monto|importe|valor|total)$/i', $h)) {
                $map['amount'] = $idx;
                continue;
            }

            // Description / Payee / Memo
            if (!isset($map['description']) && preg_match('/^(description|desc|memo|payee|name|concepto|detalle|detalles|detail|details|narrative|transaction\s*details)$/i', $h)) {
                $map['description'] = $idx;
                continue;
            }

            // Reference / Check
            if (!isset($map['reference']) && preg_match('/^(reference|ref|check\s*num|check\s*number|check|chk\s*no|id|trans\s*id|transaction\s*id|referencia)$/i', $h)) {
                $map['reference'] = $idx;
                continue;
            }
        }

        return $map;
    }

    private function parseCsvDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        // Standard YYYY-MM-DD
        if (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})$/', $raw, $m)) {
            $year = (int) $m[1];
            $month = (int) $m[2];
            $day = (int) $m[3];
            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        // MM/DD/YYYY or DD/MM/YYYY
        if (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})$/', $raw, $m)) {
            $part1 = (int) $m[1];
            $part2 = (int) $m[2];
            $year  = (int) $m[3];

            if ($part1 > 12 && checkdate($part2, $part1, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $part2, $part1);
            }

            if (checkdate($part1, $part2, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $part1, $part2);
            }
        }

        // Fallback strtotime
        $ts = strtotime($raw);
        if ($ts !== false && $ts > 0) {
            $y = (int) date('Y', $ts);
            if ($y >= 1990 && $y <= 2100) {
                return date('Y-m-d', $ts);
            }
        }

        return null;
    }

    private function parseCsvAmount(string $raw): ?float
    {
        $raw = trim($raw);
        if ($raw === '' || $raw === '—' || $raw === '-') {
            return null;
        }

        $isNegative = false;
        if (preg_match('/^\((.+)\)$/', $raw, $m)) {
            $isNegative = true;
            $raw = $m[1];
        }

        if (str_ends_with($raw, '-')) {
            $isNegative = true;
            $raw = rtrim($raw, '-');
        }

        if (str_starts_with($raw, '-')) {
            $isNegative = true;
            $raw = ltrim($raw, '-');
        }

        $raw = preg_replace('/[^\d.,]/', '', $raw) ?? '';
        if ($raw === '') {
            return null;
        }

        if (strpos($raw, ',') !== false && strpos($raw, '.') !== false) {
            if (strrpos($raw, ',') > strrpos($raw, '.')) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '', $raw);
            }
        } elseif (strpos($raw, ',') !== false) {
            if (preg_match('/,\d{2}$/', $raw)) {
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '', $raw);
            }
        }

        $val = (float) $raw;
        return $isNegative ? -$val : $val;
    }
}
