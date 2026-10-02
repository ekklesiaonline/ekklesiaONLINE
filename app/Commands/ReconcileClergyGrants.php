<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\ClergyGrantReconciliationService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class ReconcileClergyGrants extends BaseCommand
{
    protected $group = 'Governance';
    protected $name = 'clergy:reconcile-grants';
    protected $description = 'Dry-run or prune only provenance-tracked expired or revoked clergy office/delegation grants.';
    protected $usage = 'clergy:reconcile-grants [--apply]';
    protected $arguments = [];
    protected $options = ['--apply' => 'Apply removals of stale managed grants. Omit to inspect only.'];

    public function run(array $params)
    {
        $apply = CLI::getOption('apply') !== null;
        try {
            $result = (new ClergyGrantReconciliationService())->reconcile($apply);
        } catch (\Throwable $e) {
            CLI::error('Reconciliation failed; review the database log. No unrelated manual grants were targeted.');
            return;
        }
        CLI::write(($apply ? 'APPLIED' : 'DRY-RUN') . ': ' . json_encode($result, JSON_THROW_ON_ERROR));
        if (! $apply) {
            CLI::write('Run with --apply only after inspecting the dry-run and a database backup.');
        }
    }
}
