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
        $strict = CLI::getOption('strict') !== null;
        $service = new DirectoryReadinessService();
        $result = $service->evaluate();
        $counts = $result['counts'];

        CLI::write('Directory Hierarchy Readiness Check', 'cyan');
        CLI::write('-----------------------------------', 'cyan');
        CLI::table([
            ['Provinces', (string) $counts['provinces']],
            ['Dioceses (total)', (string) $counts['dioceses']],
            ['Dioceses (linked to province)', (string) $counts['linked_dioceses']],
            ['Dioceses (orphaned)', (string) $counts['orphaned_dioceses']],
            ['Parishes (non-deleted)', (string) $counts['parishes']],
            ['Parishes (active)', (string) $counts['active_parishes']],
            ['Parishes (active + linked to diocese -> province)', (string) $counts['linked_active_parishes']],
            ['Parishes (active, unlinked)', (string) $counts['unlinked_active_parishes']],
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
}
