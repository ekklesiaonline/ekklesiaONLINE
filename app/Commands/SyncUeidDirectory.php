<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SyncUeidDirectory extends BaseCommand
{
    protected $group = 'Data';
    protected $name = 'directory:sync-ueid';
    protected $description = 'Sync dioceses and parishes from UEID JSON/CSV using ParishUEID as the stable key.';
    protected $usage = 'directory:sync-ueid [--source <path>] [--format json|csv] [--apply] [--default-province-id <id>] [--deactivate-missing]';
    protected $options = [
        '--source' => 'Source file. Defaults to docs/UEID.json, then falls back to docs/UEID.csv.',
        '--format' => 'Input format: json or csv. Defaults to auto-detect by file extension.',
        '--apply' => 'Persist changes. Omit for dry-run.',
        '--default-province-id' => 'Required when new dioceses are detected and no existing diocese match is found.',
        '--deactivate-missing' => 'Deactivate parishes not present in source by setting parishes.is_active = 0 (only for rows with non-empty parish_ueid).',
    ];

    public function run(array $params)
    {
        $source = (string) (CLI::getOption('source') ?: '');
        $apply = CLI::getOption('apply') !== null;
        $format = strtolower(trim((string) (CLI::getOption('format') ?: '')));
        $defaultProvinceId = (int) (CLI::getOption('default-province-id') ?: 0);
        $deactivateMissing = CLI::getOption('deactivate-missing') !== null;

        if ($source === '') {
            $jsonDefault = ROOTPATH . 'docs/UEID.json';
            $csvDefault = ROOTPATH . 'docs/UEID.csv';
            $source = is_file($jsonDefault) ? $jsonDefault : $csvDefault;
        }

        if (!is_file($source)) {
            $candidate = ROOTPATH . ltrim($source, '/');
            if (is_file($candidate)) {
                $source = $candidate;
            }
        }

        if (!is_file($source)) {
            CLI::error('Source file not found: ' . $source);
            return;
        }

        if ($format === '') {
            $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION));
            $format = in_array($ext, ['json', 'csv'], true) ? $ext : 'json';
        }

        if (!in_array($format, ['json', 'csv'], true)) {
            CLI::error('Unsupported format. Use --format json|csv');
            return;
        }

        CLI::write('UEID Directory Sync', 'cyan');
        CLI::write('-------------------', 'cyan');
        CLI::write('Mode: ' . ($apply ? 'APPLY' : 'DRY-RUN'), $apply ? 'yellow' : 'light_gray');
        CLI::write('Source: ' . $source, 'light_gray');
        CLI::write('Format: ' . $format, 'light_gray');
        CLI::write('Deactivate missing: ' . ($deactivateMissing ? 'yes' : 'no'), $deactivateMissing ? 'yellow' : 'light_gray');
        CLI::newLine();

        $rows = $format === 'json' ? $this->parseJson($source) : $this->parseCsv($source);

        if ($rows === []) {
            CLI::error('No rows parsed from source.');
            return;
        }

        $db = db_connect();

        $summary = [
            'rows_parsed' => count($rows),
            'dioceses_created' => 0,
            'parishes_created' => 0,
            'parishes_updated' => 0,
            'parishes_unchanged' => 0,
            'parishes_deactivated' => 0,
            'errors' => 0,
        ];

        $errors = [];

        $dioceseMap = $this->loadDioceseMap($db);
        $hasParishIsActive = $this->columnExists($db, 'parish', 'is_active');
        $pendingDioceses = [];

        foreach ($rows as $row) {
            $norm = $this->normalize((string) $row['Diocese']);
            if ($norm === '' || isset($dioceseMap[$norm]) || isset($pendingDioceses[$norm])) {
                continue;
            }
            $pendingDioceses[$norm] = (string) $row['Diocese'];
        }

        if ($pendingDioceses !== [] && $defaultProvinceId <= 0) {
            $summary['errors'] += count($pendingDioceses);
            foreach ($pendingDioceses as $displayName) {
                $errors[] = 'Missing diocese: ' . $displayName . ' (provide --default-province-id to create)';
            }
        }

        if ($apply) {
            $db->transBegin();
        }

        if ($pendingDioceses !== [] && $defaultProvinceId > 0) {
            foreach ($pendingDioceses as $norm => $displayName) {
                $newId = $this->createDiocese($db, $displayName, $norm, $defaultProvinceId);
                if ($newId <= 0) {
                    $summary['errors']++;
                    $errors[] = 'Failed to create diocese: ' . $displayName;
                    continue;
                }

                $dioceseMap[$norm] = $newId;
                $summary['dioceses_created']++;
            }
        }

        foreach ($rows as $row) {
            $parishUeid = trim((string) ($row['ParishUEID'] ?? ''));
            $parishName = trim((string) ($row['Name'] ?? ''));

            if ($parishUeid === '' || $parishName === '') {
                $summary['errors']++;
                $errors[] = 'Invalid row (missing ParishUEID or Name): ' . json_encode($row);
                continue;
            }

            $dioceseNorm = $this->normalize((string) ($row['Diocese'] ?? ''));
            $dioceseId = $dioceseMap[$dioceseNorm] ?? null;

            if ($dioceseId === null) {
                $summary['errors']++;
                $errors[] = 'Unresolved diocese for ParishUEID ' . $parishUeid . ': ' . ((string) ($row['Diocese'] ?? ''));
                continue;
            }

            $payload = [
                'name' => $parishName,
                'parish_ueid' => $parishUeid,
                'diocese_id' => (int) $dioceseId,
                'city' => $this->nullableString($row['City'] ?? null),
                'state' => $this->nullableString($row['State'] ?? null),
                'county' => $this->nullableString($row['County'] ?? null),
            ];

            if ($hasParishIsActive) {
                $payload['is_active'] = 1;
            }

            $selectFields = 'id, name, parish_ueid, diocese_id, city, state, county';
            if ($hasParishIsActive) {
                $selectFields .= ', is_active';
            }

            $existing = $db->table('parish')
                ->select($selectFields)
                ->where('parish_ueid', $parishUeid)
                ->get()
                ->getRowArray();

            if ($existing === null) {
                $summary['parishes_created']++;
                if ($apply) {
                    $db->table('parish')->insert($payload);
                }
                continue;
            }

            $changes = [];
            foreach ($payload as $field => $value) {
                $current = $existing[$field] ?? null;
                if (!$this->sameValue($current, $value)) {
                    $changes[$field] = $value;
                }
            }

            if ($changes === []) {
                $summary['parishes_unchanged']++;
                continue;
            }

            $summary['parishes_updated']++;
            if ($apply) {
                $db->table('parish')->where('id', (int) $existing['id'])->update($changes);
            }
        }

        if ($deactivateMissing) {
            $sourceUeids = [];
            foreach ($rows as $row) {
                $parishUeid = trim((string) ($row['ParishUEID'] ?? ''));
                if ($parishUeid !== '') {
                    $sourceUeids[$parishUeid] = true;
                }
            }

            if (!$hasParishIsActive) {
                $summary['errors']++;
                $errors[] = 'Cannot deactivate missing parishes: parishes.is_active column not found.';
            } else {
                $summary['parishes_deactivated'] = $this->deactivateMissingParishes($db, array_keys($sourceUeids), $apply);
            }
        }

        if ($apply) {
            if ($db->transStatus() === false) {
                $db->transRollback();
                CLI::error('Transaction failed. No changes committed.');
                return;
            }
            $db->transCommit();
        }

        $reportPath = $this->writeReport($summary, $errors, $source, $apply, $defaultProvinceId, $deactivateMissing);

        CLI::write('Rows parsed:         ' . $summary['rows_parsed'], 'light_gray');
        CLI::write('Dioceses created:    ' . $summary['dioceses_created'], $summary['dioceses_created'] > 0 ? 'yellow' : 'light_gray');
        CLI::write('Parishes created:    ' . $summary['parishes_created'], $summary['parishes_created'] > 0 ? 'green' : 'light_gray');
        CLI::write('Parishes updated:    ' . $summary['parishes_updated'], $summary['parishes_updated'] > 0 ? 'yellow' : 'light_gray');
        CLI::write('Parishes unchanged:  ' . $summary['parishes_unchanged'], 'light_gray');
        CLI::write('Parishes deactivated:' . str_repeat(' ', 4) . $summary['parishes_deactivated'], $summary['parishes_deactivated'] > 0 ? 'yellow' : 'light_gray');
        CLI::write('Errors:              ' . $summary['errors'], $summary['errors'] > 0 ? 'red' : 'green');
        CLI::write('Report: ' . $reportPath, 'cyan');
    }

    private function parseJson(string $source): array
    {
        $raw = file_get_contents($source);
        if ($raw === false) {
            return [];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [];
        }

        $rows = [];
        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }

            $rows[] = [
                'ParishUEID' => trim((string) ($item['ParishUEID'] ?? '')),
                'Diocese' => trim((string) ($item['Diocese'] ?? '')),
                'Name' => trim((string) ($item['Name'] ?? '')),
                'City' => trim((string) ($item['City'] ?? '')),
                'State' => trim((string) ($item['State'] ?? '')),
                'County' => trim((string) ($item['County'] ?? '')),
            ];
        }

        return $rows;
    }

    private function parseCsv(string $source): array
    {
        $handle = fopen($source, 'rb');
        if ($handle === false) {
            return [];
        }

        $header = fgetcsv($handle, 0, ',', '"', '');
        if (!is_array($header)) {
            fclose($handle);
            return [];
        }

        $required = ['ParishUEID', 'Diocese', 'Name', 'City', 'State', 'County'];
        $indexes = [];
        foreach ($required as $column) {
            $idx = array_search($column, $header, true);
            if ($idx === false) {
                fclose($handle);
                return [];
            }
            $indexes[$column] = (int) $idx;
        }

        $rows = [];
        while (($line = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $rows[] = [
                'ParishUEID' => trim((string) ($line[$indexes['ParishUEID']] ?? '')),
                'Diocese' => trim((string) ($line[$indexes['Diocese']] ?? '')),
                'Name' => trim((string) ($line[$indexes['Name']] ?? '')),
                'City' => trim((string) ($line[$indexes['City']] ?? '')),
                'State' => trim((string) ($line[$indexes['State']] ?? '')),
                'County' => trim((string) ($line[$indexes['County']] ?? '')),
            ];
        }

        fclose($handle);

        return $rows;
    }

    private function normalize(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (class_exists('Transliterator')) {
            $transliterator = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC');
            if ($transliterator !== null) {
                $value = $transliterator->transliterate($value);
            }
        }

        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    private function nullableString($value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function sameValue($a, $b): bool
    {
        $left = $a === null ? null : trim((string) $a);
        $right = $b === null ? null : trim((string) $b);
        return $left === $right;
    }

    private function loadDioceseMap($db): array
    {
        $rows = $db->table('diocese')->select('id, name, alias_norm')->get()->getResultArray();
        $map = [];

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $nameNorm = $this->normalize((string) ($row['name'] ?? ''));
            $aliasNorm = $this->normalize((string) ($row['alias_norm'] ?? ''));

            if ($nameNorm !== '') {
                $map[$nameNorm] = $id;
            }
            if ($aliasNorm !== '') {
                $map[$aliasNorm] = $id;
            }
        }

        if ($this->tableExists($db, 'diocese_alias')) {
            $aliases = $db->table('diocese_alias')->select('diocese_id, alias, alias_norm')->get()->getResultArray();
            foreach ($aliases as $alias) {
                $id = (int) ($alias['diocese_id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }

                $norm = $this->normalize((string) ($alias['alias_norm'] ?? ''));
                if ($norm === '') {
                    $norm = $this->normalize((string) ($alias['alias'] ?? ''));
                }

                if ($norm !== '') {
                    $map[$norm] = $id;
                }
            }
        }

        return $map;
    }

    private function createDiocese($db, string $displayName, string $normName, int $provinceId): int
    {
        $existing = $db->table('diocese')
            ->select('id')
            ->where('LOWER(TRIM(name))', $normName)
            ->get()
            ->getRowArray();

        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $ok = $db->table('diocese')->insert([
            'province_id' => $provinceId,
            'name' => $displayName,
            'alias_norm' => $normName,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$ok) {
            return 0;
        }

        $id = (int) $db->insertID();

        if ($id > 0 && $this->tableExists($db, 'diocese_alias')) {
            $db->table('diocese_alias')->ignore(true)->insert([
                'diocese_id' => $id,
                'alias' => $displayName,
                'alias_norm' => $normName,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return $id;
    }

    private function tableExists($db, string $table): bool
    {
        try {
            $row = $db->query('SHOW TABLES LIKE ' . $db->escape($table))->getFirstRow('array');
            return $row !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function columnExists($db, string $table, string $column): bool
    {
        try {
            $row = $db->query("SHOW COLUMNS FROM `{$table}` LIKE " . $db->escape($column))->getFirstRow('array');
            return $row !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function deactivateMissingParishes($db, array $sourceUeids, bool $apply): int
    {
        $existingRows = $db->table('parish')
            ->select('id, parish_ueid')
            ->where('parish_ueid IS NOT NULL')
            ->where('parish_ueid !=', '')
            ->where('is_active', 1)
            ->get()
            ->getResultArray();

        if ($existingRows === []) {
            return 0;
        }

        $sourceMap = [];
        foreach ($sourceUeids as $ueid) {
            if ($ueid !== '') {
                $sourceMap[$ueid] = true;
            }
        }

        $idsToDeactivate = [];
        foreach ($existingRows as $row) {
            $ueid = trim((string) ($row['parish_ueid'] ?? ''));
            $id = (int) ($row['id'] ?? 0);

            if ($id <= 0 || $ueid === '') {
                continue;
            }

            if (!isset($sourceMap[$ueid])) {
                $idsToDeactivate[$id] = true;
            }
        }

        if ($idsToDeactivate === []) {
            return 0;
        }

        $ids = array_keys($idsToDeactivate);

        if (!$apply) {
            return count($ids);
        }

        $affected = 0;
        foreach (array_chunk($ids, 500) as $chunk) {
            $db->table('parish')->whereIn('id', $chunk)->update(['is_active' => 0]);
            $affected += $db->affectedRows();
        }

        return $affected;
    }

    private function writeReport(array $summary, array $errors, string $source, bool $apply, int $defaultProvinceId, bool $deactivateMissing): string
    {
        $reportDir = WRITEPATH . 'reports';
        if (!is_dir($reportDir)) {
            mkdir($reportDir, 0775, true);
        }

        $stamp = date('Ymd_His');
        $path = $reportDir . '/ueid_directory_sync_' . $stamp . '.md';

        $lines = [];
        $lines[] = '# UEID Directory Sync Report';
        $lines[] = '';
        $lines[] = '- Generated At: ' . date('c');
        $lines[] = '- Mode: ' . ($apply ? 'APPLY' : 'DRY-RUN');
        $lines[] = '- Source: ' . $source;
        $lines[] = '- Default Province ID: ' . ($defaultProvinceId > 0 ? (string) $defaultProvinceId : 'not set');
        $lines[] = '- Deactivate Missing: ' . ($deactivateMissing ? 'yes' : 'no');
        $lines[] = '- Rows Parsed: ' . $summary['rows_parsed'];
        $lines[] = '- Dioceses Created: ' . $summary['dioceses_created'];
        $lines[] = '- Parishes Created: ' . $summary['parishes_created'];
        $lines[] = '- Parishes Updated: ' . $summary['parishes_updated'];
        $lines[] = '- Parishes Unchanged: ' . $summary['parishes_unchanged'];
        $lines[] = '- Parishes Deactivated: ' . ($summary['parishes_deactivated'] ?? 0);
        $lines[] = '- Errors: ' . $summary['errors'];

        if ($errors !== []) {
            $lines[] = '';
            $lines[] = '## Errors (first 100)';
            $lines[] = '';
            foreach (array_slice($errors, 0, 100) as $error) {
                $lines[] = '- ' . str_replace('|', '\\|', (string) $error);
            }
        }

        file_put_contents($path, implode(PHP_EOL, $lines) . PHP_EOL);

        return $path;
    }
}
