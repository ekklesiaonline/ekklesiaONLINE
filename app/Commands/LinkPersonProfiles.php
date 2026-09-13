<?php

namespace App\Commands;

use App\Services\PersonProfileSyncService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class LinkPersonProfiles extends BaseCommand
{
    protected $group       = 'Maintenance';
    protected $name        = 'person:link-profiles';
    protected $description = 'Find profiles with missing person_id and link them to canonical person rows.';
    protected $usage       = 'person:link-profiles [--parish 9] [--dry-run]';
    protected $options     = [
        '--parish'  => 'Limit to profiles for a single parish ID.',
        '--dry-run' => 'Report how many profiles would be repaired without making changes.',
    ];

    public function run(array $params)
    {
        $db = db_connect();

        if (! $db->tableExists('profile')) {
            CLI::error('Table profile does not exist. Run migrations first.');
            return EXIT_ERROR;
        }

        if (! $db->fieldExists('person_id', 'profile')) {
            CLI::error('Column profile.person_id does not exist. Run migrations first.');
            return EXIT_ERROR;
        }

        $dryRun   = $this->hasOption($params, 'dry-run');
        $parishId = $this->optionValue('parish', $params);

        $builder = $db->table('profile')
            ->select('profile.id, profile.user_id, profile.first_name, profile.last_name, profile.parish_id')
            ->groupStart()
                ->where('profile.person_id', null)
                ->orWhere('profile.person_id', 0)
            ->groupEnd()
            ->where('profile.deleted_at', null);

        if ($parishId !== null && (int) $parishId > 0) {
            $builder->where('profile.parish_id', (int) $parishId);
        }

        $profiles = $builder->orderBy('profile.id', 'ASC')->get()->getResultArray();

        if ($profiles === []) {
            CLI::write('No unlinked profiles found.', 'green');
            return EXIT_SUCCESS;
        }

        CLI::write('Found ' . count($profiles) . ' unlinked profile(s).', 'cyan');

        if ($dryRun) {
            foreach ($profiles as $p) {
                CLI::write(
                    '  WOULD REPAIR profile #' . (int) $p['id']
                    . ' (user_id=' . (int) ($p['user_id'] ?? 0)
                    . ', name=' . trim((string) (($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''))) . ')',
                    'yellow'
                );
            }
            CLI::write('Dry run — no changes made. Run without --dry-run to repair.', 'yellow');
            return EXIT_SUCCESS;
        }

        $service  = new PersonProfileSyncService();
        $repaired = 0;
        $failed   = 0;

        foreach ($profiles as $p) {
            $profileId = (int) $p['id'];
            $name      = trim((string) (($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')));

            CLI::write('  Repairing profile #' . $profileId . ' (' . $name . ')...', 'cyan');

            try {
                $personId = $service->syncFromProfile($profileId);
            } catch (\Throwable $e) {
                $personId = null;
            }

            if ($personId !== null && $personId > 0) {
                $repaired++;
                CLI::write('    OK — linked to person #' . $personId, 'green');
            } else {
                $failed++;
                CLI::write('    FAILED — could not create or find a person row', 'red');
            }
        }

        CLI::write('');
        CLI::write('Repaired: ' . $repaired, 'green');
        if ($failed > 0) {
            CLI::write('Failed:   ' . $failed, 'red');
        }

        return EXIT_SUCCESS;
    }

    private function hasOption(array $params, string $name): bool
    {
        $needle = '--' . $name;
        foreach ($params as $param) {
            if ($param === $needle || str_starts_with($param, $needle . '=')) {
                return true;
            }
        }
        return CLI::getOption($name) !== null;
    }

    private function optionValue(string $name, array $params)
    {
        $needle = '--' . $name;
        foreach ($params as $index => $param) {
            if (! is_string($param)) {
                continue;
            }
            if (str_starts_with($param, $needle . '=')) {
                return substr($param, strlen($needle . '='));
            }
            if ($param === $needle) {
                $next = $params[$index + 1] ?? true;
                if (is_string($next) && ! str_starts_with($next, '--')) {
                    return $next;
                }
                return true;
            }
        }
        return CLI::getOption($name);
    }
}
