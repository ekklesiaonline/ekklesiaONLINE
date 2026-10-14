<?php

namespace App\Commands;

use App\Services\FinanceReadinessService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class AuditFinanceReadiness extends BaseCommand
{
    protected $group       = 'Maintenance';
    protected $name        = 'finance:audit-readiness';
    protected $description = 'Audits parish fund readiness and optionally provisions a default fund for parishes with no funds.';
    protected $usage       = 'finance:audit-readiness [--parish <id>] [--provision] [--created-by <user-id>]';

    protected $options = [
        '--parish'    => 'Limit the audit to one parish id.',
        '--provision' => 'Create an Unrestricted Operating Fund only where no fund records exist.',
        '--created-by' => 'Optional user id recorded as the provisioning actor.',
    ];

    public function run(array $params)
    {
        $parishId = (int) (CLI::getOption('parish') ?? 0);
        $createdBy = (int) (CLI::getOption('created-by') ?? 0);
        $provision = CLI::getOption('provision') !== null;
        $service = new FinanceReadinessService();
        $rows = $service->audit($parishId > 0 ? $parishId : null);

        if ($rows === []) {
            CLI::error('No parishes found for the requested scope.');
            return;
        }

        $output = [];
        foreach ($rows as $row) {
            $action = '-';
            if ($provision && $row['status'] === 'no_funds') {
                $result = $service->provisionDefaultFund((int) $row['parish_id'], $createdBy > 0 ? $createdBy : null);
                $action = $result['created'] ? 'provisioned' : 'unchanged';
                $row = [
                    'parish_name' => $row['parish_name'],
                    ...$service->readinessForParish((int) $row['parish_id']),
                ];
            }

            $output[] = [
                (int) $row['parish_id'],
                (string) $row['parish_name'],
                (string) $row['status'],
                (int) $row['fund_count'],
                (int) $row['active_count'],
                (int) $row['usable_count'],
                $action,
            ];
        }

        CLI::table($output, ['Parish ID', 'Parish', 'Status', 'Funds', 'Active', 'Usable', 'Action']);

        $notReady = count(array_filter($output, static fn (array $row): bool => $row[2] !== 'ready'));
        CLI::write('Parishes scanned: ' . count($output));
        CLI::write('Parishes not ready: ' . $notReady, $notReady > 0 ? 'yellow' : 'green');
    }
}
