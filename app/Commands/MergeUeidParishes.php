<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class MergeUeidParishes extends BaseCommand
{
    protected $group = 'Data';
    protected $name = 'parish:merge-ueid';
    protected $description = 'Parses docs/UEID.md and merges parishes into parishes using diocesan alias matching.';
    protected $usage = 'parish:merge-ueid [--source <path>] [--apply]';
    protected $options = [
        '--source' => 'Path to UEID markdown source file. Defaults to docs/UEID.md.',
        '--apply' => 'Apply writes. Omit for dry-run report only.',
    ];

    public function run(array $params)
    {
        $source = CLI::getOption('source') ?: ROOTPATH . 'docs/UEID.md';
        $apply = CLI::getOption('apply') !== null;

        CLI::write('UEID Parish Merge', 'cyan');
        CLI::write('---------------', 'cyan');
        CLI::write('Mode: ' . ($apply ? 'APPLY' : 'DRY-RUN'), $apply ? 'yellow' : 'light_gray');
        CLI::write('Source: ' . $source, 'light_gray');
        CLI::newLine();

        if (!is_file($source)) {
            CLI::error('Source file not found: ' . $source);
            return;
        }

        $content = (string) file_get_contents($source);
        $rows = $this->extractRows($content);

        if ($rows === []) {
            CLI::error('No UEID rows were parsed from source.');
            return;
        }

        $db = db_connect();
        $lookup = $this->buildDioceseLookup($db);

        $summary = [
            'parsed' => count($rows),
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'unresolved' => 0,
        ];
        $unresolved = [];

        if ($apply) {
            $db->transBegin();
        }

        foreach ($rows as $row) {
            $dioceseNorm = $this->normalize((string) $row['diocese']);
            $dioceseIds = $lookup[$dioceseNorm] ?? [];

            if ($dioceseNorm === '' || $dioceseIds === []) {
                $summary['unresolved']++;
                $unresolved[] = $this->buildUnresolved($row, 'diocese_not_found');
                continue;
            }

            if (count($dioceseIds) > 1) {
                $summary['unresolved']++;
                $unresolved[] = $this->buildUnresolved($row, 'diocese_ambiguous');
                continue;
            }

            $dioceseId = (int) $dioceseIds[0];
            $existing = $this->findExistingParish($db, $row);

            if (($existing['ambiguous'] ?? false) === true) {
                $summary['unresolved']++;
                $unresolved[] = $this->buildUnresolved($row, 'parish_ambiguous');
                continue;
            }

            $payload = [
                'name' => $row['name'],
                'city' => $row['city'] ?: null,
                'state' => $row['state'] ?: null,
                'county' => $row['county'] ?: null,
                'diocese_id' => $dioceseId,
                'parish_ueid' => $row['parish_ueid'],
            ];

            if (($existing['row'] ?? null) === null) {
                $summary['created']++;
                if ($apply) {
                    $db->table('parish')->insert($payload);
                }
                continue;
            }

            $current = $existing['row'];
            $changes = [];
            foreach ($payload as $field => $value) {
                $currentValue = $current[$field] ?? null;
                if (!$this->valuesEqual($field, $currentValue, $value)) {
                    $changes[$field] = $value;
                }
            }

            if ($changes === []) {
                $summary['skipped']++;
                continue;
            }

            $summary['updated']++;
            if ($apply) {
                $db->table('parish')->where('id', (int) $current['id'])->update($changes);
            }
        }

        if ($apply) {
            if ($db->transStatus() === false) {
                $db->transRollback();
                CLI::error('Transaction failed. No changes were committed.');
                return;
            }
            $db->transCommit();
        }

        $reportPath = $this->writeReport($summary, $unresolved, $source, $apply);

        CLI::write('Parsed rows:    ' . $summary['parsed'], 'light_gray');
        CLI::write('Created rows:   ' . $summary['created'], 'green');
        CLI::write('Updated rows:   ' . $summary['updated'], 'yellow');
        CLI::write('Skipped rows:   ' . $summary['skipped'], 'light_gray');
        CLI::write('Unresolved rows:' . $summary['unresolved'], $summary['unresolved'] > 0 ? 'red' : 'green');
        CLI::write('Report: ' . $reportPath, 'cyan');
    }

    /**
     * @return array<int, array{parish_ueid:string,diocese:string,name:string,city:string,state:string,county:string}>
     */
    private function extractRows(string $content): array
    {
        if (preg_match('/```php(.*?)```/is', $content, $matches) === 1) {
            $content = (string) $matches[1];
        }

        preg_match_all('/<tr\\b[^>]*>(.*?)<\\/tr>/is', $content, $rowMatches);
        $rows = [];

        foreach (($rowMatches[1] ?? []) as $rowHtml) {
            preg_match_all('/<t[dh]\\b[^>]*>(.*?)<\\/t[dh]>/is', (string) $rowHtml, $cellMatches);
            $cells = array_map(function ($cell): string {
                $text = html_entity_decode(strip_tags((string) $cell), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                return trim($text);
            }, $cellMatches[1] ?? []);

            if (count($cells) < 6) {
                continue;
            }

            if (strcasecmp($cells[0], 'ParishUEID') === 0) {
                continue;
            }

            $rows[] = [
                'parish_ueid' => $cells[0],
                'diocese' => $cells[1],
                'name' => $cells[2],
                'city' => $cells[3],
                'state' => $cells[4],
                'county' => $cells[5],
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, array<int, int>>
     */
    private function buildDioceseLookup($db): array
    {
        $rows = $db->table('diocese')->select('id, name, alias_norm')->get()->getResultArray();
        $lookup = [];

        foreach ($rows as $row) {
            $norm = $this->normalize((string) ($row['alias_norm'] ?? ''));
            if ($norm === '') {
                $norm = $this->normalize((string) ($row['name'] ?? ''));
            }

            if ($norm === '') {
                continue;
            }

            $lookup[$norm] ??= [];
            $lookup[$norm][] = (int) $row['id'];
        }

        if ($this->tableExists($db, 'diocese_alias')) {
            $aliases = $db->table('diocese_alias')
                ->select('diocese_id, alias, alias_norm')
                ->get()
                ->getResultArray();

            foreach ($aliases as $alias) {
                $norm = $this->normalize((string) ($alias['alias_norm'] ?? ''));
                if ($norm === '') {
                    $norm = $this->normalize((string) ($alias['alias'] ?? ''));
                }

                if ($norm === '') {
                    continue;
                }

                $lookup[$norm] ??= [];
                $lookup[$norm][] = (int) ($alias['diocese_id'] ?? 0);
            }
        }

        foreach ($lookup as $norm => $ids) {
            $lookup[$norm] = array_values(array_unique($ids));
        }

        return $lookup;
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

    /**
     * @return array{row: array<string, mixed>|null, ambiguous: bool}
     */
    private function findExistingParish($db, array $row): array
    {
        $parishUeid = trim((string) ($row['parish_ueid'] ?? ''));
        if ($parishUeid !== '') {
            $byUeid = $db->table('parish')
                ->select('id, name, city, state, county, diocese_id, parish_ueid')
                ->where('parish_ueid', $parishUeid)
                ->get()
                ->getResultArray();

            if (count($byUeid) > 1) {
                return ['row' => null, 'ambiguous' => true];
            }

            if ($byUeid !== []) {
                return ['row' => $byUeid[0], 'ambiguous' => false];
            }
        }

        $name = strtolower(trim((string) ($row['name'] ?? '')));
        $city = strtolower(trim((string) ($row['city'] ?? '')));
        $state = strtolower(trim((string) ($row['state'] ?? '')));

        $query = $db->query(
            'SELECT id, name, city, state, county, diocese_id, parish_ueid
                         FROM parish
             WHERE LOWER(TRIM(name)) = ?
               AND LOWER(TRIM(COALESCE(city, ""))) = ?
               AND LOWER(TRIM(COALESCE(state, ""))) = ?
             LIMIT 2',
            [$name, $city, $state]
        );
        $rows = $query->getResultArray();

        if (count($rows) > 1) {
            return ['row' => null, 'ambiguous' => true];
        }

        return ['row' => $rows[0] ?? null, 'ambiguous' => false];
    }

    /**
     * @return array{parish_ueid:string,diocese:string,name:string,city:string,state:string,county:string,reason:string}
     */
    private function buildUnresolved(array $row, string $reason): array
    {
        return [
            'parish_ueid' => (string) ($row['parish_ueid'] ?? ''),
            'diocese' => (string) ($row['diocese'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'city' => (string) ($row['city'] ?? ''),
            'state' => (string) ($row['state'] ?? ''),
            'county' => (string) ($row['county'] ?? ''),
            'reason' => $reason,
        ];
    }

    /**
     * @param array<string, int> $summary
     * @param array<int, array<string, string>> $unresolved
     */
    private function writeReport(array $summary, array $unresolved, string $source, bool $apply): string
    {
        $reportDir = WRITEPATH . 'reports';
        if (!is_dir($reportDir)) {
            mkdir($reportDir, 0775, true);
        }

        $stamp = date('Ymd_His');
        $path = $reportDir . '/ueid_merge_report_' . $stamp . '.md';

        $lines = [];
        $lines[] = '# UEID Merge Report';
        $lines[] = '';
        $lines[] = '- Generated At: ' . date('c');
        $lines[] = '- Mode: ' . ($apply ? 'APPLY' : 'DRY-RUN');
        $lines[] = '- Source: ' . $source;
        $lines[] = '- Parsed: ' . $summary['parsed'];
        $lines[] = '- Created: ' . $summary['created'];
        $lines[] = '- Updated: ' . $summary['updated'];
        $lines[] = '- Skipped: ' . $summary['skipped'];
        $lines[] = '- Unresolved: ' . $summary['unresolved'];

        if ($unresolved !== []) {
            $lines[] = '';
            $lines[] = '## Unresolved (first 50)';
            $lines[] = '';
            $lines[] = '| ParishUEID | Diocese | Name | City | State | County | Reason |';
            $lines[] = '| --- | --- | --- | --- | --- | --- | --- |';
            foreach (array_slice($unresolved, 0, 50) as $row) {
                $lines[] = '| ' . $this->escapePipe($row['parish_ueid'])
                    . ' | ' . $this->escapePipe($row['diocese'])
                    . ' | ' . $this->escapePipe($row['name'])
                    . ' | ' . $this->escapePipe($row['city'])
                    . ' | ' . $this->escapePipe($row['state'])
                    . ' | ' . $this->escapePipe($row['county'])
                    . ' | ' . $this->escapePipe($row['reason']) . ' |';
            }
        }

        file_put_contents($path, implode(PHP_EOL, $lines) . PHP_EOL);
        return $path;
    }

    private function escapePipe(string $value): string
    {
        return str_replace('|', '\\|', $value);
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

    private function valuesEqual(string $field, $currentValue, $newValue): bool
    {
        if ($field === 'diocese_id') {
            return (int) ($currentValue ?? 0) === (int) ($newValue ?? 0);
        }

        $left = trim((string) ($currentValue ?? ''));
        $right = trim((string) ($newValue ?? ''));

        return $left === $right;
    }
}
