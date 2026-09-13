<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class QueryOptimizationAudit extends BaseCommand
{
    protected $group = 'Maintenance';
    protected $name = 'optimize:query-audit';
    protected $description = 'Checks optimization indexes and runs EXPLAIN plans for critical queries.';
    protected $usage = 'optimize:query-audit [--strict]';

    /**
     * @var array<string, array<int, string>>
     */
    private array $requiredIndexes = [
        'parish_affiliation' => ['idx_parish_active', 'idx_user_parish_active'],
        'attendance' => ['idx_service_user', 'idx_user_attended'],
        'donation' => ['idx_parish_date', 'idx_date_range'],
        'expense' => ['idx_parish_date', 'idx_date_range'],
        'service' => ['idx_parish_date', 'idx_date_range'],
        'member_standing_summary' => ['idx_member_standing_summary_year_status', 'idx_member_standing_summary_user_year'],
    ];

    /**
     * @var array<int, array{label: string, sql: string, sourceTable: string, expectedIndexes: array<int, string>}>
     */
    private array $explainQueries = [
        [
            'label' => 'Members list scoped by parish + active',
            'sql' => 'SELECT mca.linked_user_id FROM parish_affiliation mca WHERE mca.parish_id = 1 AND mca.is_member = 1 LIMIT 50',
            'sourceTable' => 'parish_affiliation',
            'expectedIndexes' => ['idx_parish_active', 'idx_user_parish_active'],
        ],
        [
            'label' => 'Donations date range by parish',
            'sql' => "SELECT id, amount FROM donation WHERE parish_id = 1 AND date >= '2026-01-01' AND date <= '2026-12-31' ORDER BY date DESC LIMIT 50",
            'sourceTable' => 'donation',
            'expectedIndexes' => ['idx_parish_date', 'idx_date_range'],
        ],
        [
            'label' => 'Expenses date range by parish',
            'sql' => "SELECT id, amount FROM expense WHERE parish_id = 1 AND date >= '2026-01-01' AND date <= '2026-12-31' ORDER BY date DESC LIMIT 50",
            'sourceTable' => 'expense',
            'expectedIndexes' => ['idx_parish_date', 'idx_date_range'],
        ],
        [
            'label' => 'Services date range by parish',
            'sql' => "SELECT id, date FROM service WHERE parish_id = 1 AND date >= '2026-01-01' AND date <= '2026-12-31' ORDER BY date DESC LIMIT 50",
            'sourceTable' => 'service',
            'expectedIndexes' => ['idx_parish_date', 'idx_date_range'],
        ],
        [
            'label' => 'Standing summary by year/status',
            'sql' => 'SELECT user_id, is_good_standing FROM member_standing_summary WHERE year = 2026 AND is_good_standing = 1 LIMIT 50',
            'sourceTable' => 'member_standing_summary',
            'expectedIndexes' => ['idx_member_standing_summary_year_status', 'idx_member_standing_summary_user_year'],
        ],
    ];

    public function run(array $params)
    {
        $strict = CLI::getOption('strict') !== null;
        $db = db_connect();

        CLI::write('Phase 4 Query Optimization Audit', 'cyan');
        CLI::write('--------------------------------', 'cyan');
        CLI::newLine();

        $missingIndexes = $this->auditIndexes($db);
        CLI::newLine();
        $planWarnings = $this->auditExplainPlans($db);

        CLI::newLine();
        if ($missingIndexes === 0 && $planWarnings === 0) {
            CLI::write('Audit result: GREEN (no missing indexes, no EXPLAIN warnings).', 'green');
            return;
        }

        CLI::write(
            'Audit result: ATTENTION (missing indexes: ' . $missingIndexes . ', explain warnings: ' . $planWarnings . ').',
            'yellow'
        );

        if ($strict) {
            CLI::error('Strict mode enabled; returning non-zero status due to warnings.');
            exit(1);
        }
    }

    private function auditIndexes($db): int
    {
        CLI::write('1) Index Presence Check', 'light_gray');

        $missingCount = 0;
        foreach ($this->requiredIndexes as $table => $required) {
            $existing = $this->fetchIndexNames($db, $table);
            if ($existing === null) {
                CLI::write('- ' . $table . ': unable to read indexes (table missing or inaccessible)', 'red');
                $missingCount += count($required);
                continue;
            }

            $missing = array_values(array_diff($required, $existing));
            if ($missing === []) {
                CLI::write('- ' . $table . ': OK', 'green');
                continue;
            }

            CLI::write('- ' . $table . ': missing [' . implode(', ', $missing) . ']', 'yellow');
            $missingCount += count($missing);
        }

        return $missingCount;
    }

    private function auditExplainPlans($db): int
    {
        CLI::write('2) EXPLAIN Plan Check', 'light_gray');

        $warningCount = 0;
        foreach ($this->explainQueries as $entry) {
            $label = $entry['label'];
            $sql = $entry['sql'];
            $sourceTable = $entry['sourceTable'];
            $expectedIndexes = $entry['expectedIndexes'];

            $tableRows = $this->fetchTableRowCount($db, $sourceTable);
            if ($tableRows === 0) {
                CLI::write('- ' . $label . ': skipped (table is empty in this environment)', 'light_gray');
                continue;
            }

            try {
                $rows = $db->query('EXPLAIN ' . $sql)->getResultArray();
            } catch (\Throwable $e) {
                CLI::write('- ' . $label . ': EXPLAIN failed (' . $e->getMessage() . ')', 'red');
                $warningCount++;
                continue;
            }

            if ($rows === []) {
                CLI::write('- ' . $label . ': EXPLAIN returned no rows', 'yellow');
                $warningCount++;
                continue;
            }

            $treeExplain = isset($rows[0]['EXPLAIN']);
            if ($treeExplain) {
                $planText = '';
                foreach ($rows as $row) {
                    $planText .= '\n' . (string) ($row['EXPLAIN'] ?? '');
                }

                $hasIndexUsage = stripos($planText, 'index lookup') !== false
                    || stripos($planText, 'index range scan') !== false
                    || stripos($planText, 'covering index') !== false;
                $hasTableScan = stripos($planText, 'table scan') !== false;

                if ($hasIndexUsage || !$hasTableScan) {
                    CLI::write('- ' . $label . ': OK', 'green');
                } else {
                    CLI::write('- ' . $label . ': review needed (table scan detected in tree explain)', 'yellow');
                    $warningCount++;
                }

                continue;
            }

            $allIndexed = true;
            foreach ($rows as $row) {
                $key = (string) ($row['key'] ?? '');
                $possibleKeysRaw = (string) ($row['possible_keys'] ?? '');
                $scanType = strtoupper((string) ($row['type'] ?? ''));
                $possibleKeys = array_filter(array_map('trim', explode(',', $possibleKeysRaw)));
                $indexAvailable = in_array($key, $expectedIndexes, true)
                    || count(array_intersect($expectedIndexes, $possibleKeys)) > 0;

                // On small datasets MySQL may still choose a full scan even when indexes exist.
                // Treat this as healthy if the expected index is available to the optimizer.
                if (!$indexAvailable && ($key === '' || $scanType === 'ALL')) {
                    $allIndexed = false;
                    break;
                }
            }

            if ($allIndexed) {
                CLI::write('- ' . $label . ': OK', 'green');
            } else {
                CLI::write('- ' . $label . ': review needed (missing key or full scan detected)', 'yellow');
                $warningCount++;
            }
        }

        return $warningCount;
    }

    private function fetchTableRowCount($db, string $table): ?int
    {
        try {
            $row = $db->query('SELECT COUNT(*) AS total FROM `' . $table . '`')->getFirstRow('array');
            if ($row === null) {
                return null;
            }

            return (int) ($row['total'] ?? 0);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return array<int, string>|null
     */
    private function fetchIndexNames($db, string $table): ?array
    {
        try {
            $rows = $db->query('SHOW INDEX FROM `' . $table . '`')->getResultArray();
        } catch (\Throwable $e) {
            return null;
        }

        $names = [];
        foreach ($rows as $row) {
            $keyName = (string) ($row['Key_name'] ?? $row['key_name'] ?? '');
            if ($keyName !== '' && !in_array($keyName, $names, true)) {
                $names[] = $keyName;
            }
        }

        return $names;
    }
}
