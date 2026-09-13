<?php

namespace App\Commands;

use App\Services\ParishPeopleRegistryService;
use App\Services\SacramentFlagSyncService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Reconcile baptism/confirmation status across sacramental_record (canonical),
 * person_membership_details flags, and profile dates.
 *
 * Usage:
 *   php spark sacraments:reconcile
 *   php spark sacraments:reconcile --parish=3
 *   php spark sacraments:reconcile --dry-run
 */
class SacramentsReconcile extends BaseCommand
{
    protected $group       = 'Database';
    protected $name        = 'sacraments:reconcile';
    protected $description = 'Reconcile baptism/confirmation flags + profile dates from sacramental_record (canonical).';
    protected $usage       = 'sacraments:reconcile [options]';
    protected $arguments   = [];
    protected $options     = [
        '--parish'  => 'Limit to a single parish ID.',
        '--dry-run' => 'Report discrepancies only; do not apply fixes.',
    ];

    public function run(array $params)
    {
        $dryRun  = CLI::getOption('dry-run') !== null;
        $parishId = CLI::getOption('parish');
        $parishId = $parishId !== null ? (int) $parishId : null;

        $db = db_connect();

        if (!$db->tableExists('sacramental_record')) {
            CLI::error('No sacramental_record table found.');
            return EXIT_ERROR;
        }

        // Gather candidate (person_id, parish_id) pairs that have either a
        // sacrament record OR a membership_details row OR a profile with
        // baptism/confirmation data.
        $builder = $db->table('profile p')
            ->distinct()
            ->select('p.person_id, p.parish_id')
            ->where('p.person_id IS NOT NULL', null, false)
            ->where('p.person_id >', 0)
            ->where('p.parish_id IS NOT NULL', null, false)
            ->where('p.parish_id >', 0)
            ->where('p.deleted_at', null);

        if ($parishId !== null && $parishId > 0) {
            $builder->where('p.parish_id', $parishId);
        }

        $pairs = $builder->get()->getResultArray();

        if ($pairs === []) {
            CLI::write('No person/parish pairs to reconcile.', 'yellow');
            return EXIT_SUCCESS;
        }

        $service   = new SacramentFlagSyncService();
        $registry  = new ParishPeopleRegistryService();

        $totalPairs         = count($pairs);
        $totalDiscrepancies = 0;
        $totalFixed         = 0;

        CLI::write("Reconciling {$totalPairs} person/parish pair(s)" . ($dryRun ? ' (dry-run)' : '') . '...');

        foreach ($pairs as $pair) {
            $pid = (int) ($pair['person_id'] ?? 0);
            $cid = (int) ($pair['parish_id'] ?? 0);
            if ($pid <= 0 || $cid <= 0) {
                continue;
            }

            $discrepancies = $service->discrepanciesForPerson($pid, $cid);

            if ($discrepancies === []) {
                continue;
            }

            $totalDiscrepancies += count($discrepancies);

            CLI::write("  person={$pid} parish={$cid}: " . count($discrepancies) . ' discrepancy(ies)', 'light_yellow');
            foreach ($discrepancies as $d) {
                CLI::write('    - ' . $d['message'], 'yellow');
            }

            if (!$dryRun) {
                $service->applyForPerson($pid, $cid, null, 'sacraments:reconcile');
                $totalFixed++;
            }
        }

        if (!$dryRun) {
            // Refresh the parish_people snapshot for the affected parishes.
            $parishesToSync = $parishId !== null && $parishId > 0
                ? [$parishId]
                : array_values(array_unique(array_map(static fn ($p) => (int) $p['parish_id'], $pairs)));
            foreach ($parishesToSync as $cid) {
                $registry->syncParish((int) $cid);
            }
        }

        CLI::write('');
        if ($dryRun) {
            CLI::write("Dry-run complete. {$totalDiscrepancies} discrepancy(ies) found across {$totalPairs} pair(s).", 'green');
        } else {
            CLI::write("Reconciled {$totalFixed} person/parish pair(s); {$totalDiscrepancies} discrepancy(ies) resolved.", 'green');
        }

        return EXIT_SUCCESS;
    }
}
