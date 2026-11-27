<?php

namespace App\Commands;

use App\Services\DirectoryReadinessService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class VerifyDirectoryReadiness extends BaseCommand
{
    protected $group       = 'Data';
    protected $name        = 'directory:verify-readiness';
    protected $description = 'Verify that province, diocese, and active parish tables are populated with valid parent links before go-live.';
    protected $usage       = 'directory:verify-readiness [--strict]';
    protected $options     = [
        '--strict' => 'Also exit non-zero when warnings exist (orphaned dioceses or unlinked active parishes).',
    ];

    public function run(array $params)
    {
        $strict = $this->isStrictMode($params);
        $result = $this->evaluateReadiness();
        $counts = $result['counts'];

        CLI::write('Directory Hierarchy Readiness Check', 'cyan');
        CLI::write('-----------------------------------', 'cyan');
        CLI::table([
            ['Provinces', (string) $counts['provinces']],
            ['Dioceses (total)', (string) $counts['dioceses']],
            ['Dioceses (linked to province)', (string) $counts['linked_dioceses']],
            ['Dioceses (orphaned)', (string) $counts['orphaned_dioceses']],
            ['Dioceses (placeholder sample)', (string) $counts['placeholder_dioceses']],
            ['Parishes (non-deleted)', (string) $counts['parishes']],
            ['Parishes (active)', (string) $counts['active_parishes']],
            ['Parishes (active + linked to diocese -> province)', (string) $counts['linked_active_parishes']],
            ['Parishes (active, unlinked)', (string) $counts['unlinked_active_parishes']],
            ['Parishes (non-deleted, unlinked)', (string) $counts['unlinked_parishes']],
        ], ['Metric', 'Count']);

        foreach ($result['warnings'] as $warning) {
            CLI::write('WARNING: ' . $warning, 'yellow');
        }

        if (!$result['ready']) {
            foreach ($result['blocking_issues'] as $issue) {
                CLI::error('NO-GO: ' . $issue);
            }
            CLI::error('Directory readiness FAILED. Populate province, diocese, and parish before go-live.');
            return EXIT_ERROR;
        }

        if ($strict && $result['warnings'] !== []) {
            CLI::error('Directory readiness FAILED (--strict): resolve orphaned/unlinked rows before go-live.');
            return EXIT_ERROR;
        }

        CLI::write('Directory readiness PASSED.', 'green');
        return EXIT_SUCCESS;
    }

    protected function isStrictMode(array $params): bool
    {
        return array_key_exists('strict', $params) || CLI::getOption('strict') !== null;
    }

    /**
     * @return array{
     *     ready: bool,
     *     counts: array{
     *         provinces: int,
     *         dioceses: int,
     *         linked_dioceses: int,
     *         orphaned_dioceses: int,
     *         placeholder_dioceses: int,
     *         parishes: int,
     *         active_parishes: int,
     *         linked_active_parishes: int,
     *         unlinked_active_parishes: int,
     *         unlinked_parishes: int
     *     },
     *     blocking_issues: list<string>,
     *     warnings: list<string>
     * }
     */
    protected function evaluateReadiness(): array
    {
        return (new DirectoryReadinessService())->evaluate();
    }
}
