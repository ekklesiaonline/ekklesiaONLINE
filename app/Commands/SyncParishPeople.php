<?php

namespace App\Commands;

use App\Services\ParishPeopleRegistryService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SyncParishPeople extends BaseCommand
{
    protected $group = 'Maintenance';
    protected $name = 'parish-people:sync';
    protected $description = 'Synchronizes parish_people snapshots from users/profile/affiliations.';
    protected $usage = 'parish-people:sync [--parish 9] [--user 42]';

    public function run(array $params)
    {
        $db = db_connect();
        if (!$db->tableExists('parish_people')) {
            CLI::error('Table parish_people does not exist. Run migrations first.');
            return;
        }

        $service = new ParishPeopleRegistryService();

        $parishId = (int) ($this->optionValue('parish', $params) ?? 0);
        $userId   = (int) ($this->optionValue('user', $params) ?? 0);

        if ($userId > 0) {
            CLI::write('Sync parish_people for user_id=' . $userId, 'cyan');
            $affiliatedParishIds = $db->table('parish_affiliation')
                ->distinct()
                ->select('parish_id')
                ->where('linked_user_id', $userId)
                ->get()
                ->getResultArray();
            foreach ($affiliatedParishIds as $row) {
                $service->syncParish((int) $row['parish_id']);
            }
            CLI::write('Done. Synced parishes for user_id=' . $userId, 'green');
            return;
        }

        if ($parishId > 0) {
            CLI::write('Sync parish_people for parish_id=' . $parishId, 'cyan');
            $service->syncParish($parishId);
            CLI::write('Done. Synced parish_id=' . $parishId, 'green');
            return;
        }

        CLI::write('Sync parish_people for all parishes', 'cyan');
        $result = $service->syncAll();
        CLI::write('Done. Parishes synced: ' . (int) ($result['parishes_synced'] ?? 0), 'green');
    }

    /**
     * Resolve an option from command params first, then fallback to CLI options.
     *
     * @param array<int|string, mixed> $params
     * @return mixed
     */
    private function optionValue(string $name, array $params)
    {
        $needle = '--' . $name;

        foreach ($params as $index => $param) {
            if (!is_string($param)) {
                continue;
            }

            if (str_starts_with($param, $needle . '=')) {
                return substr($param, strlen($needle . '='));
            }

            if ($param === $needle) {
                $next = $params[$index + 1] ?? true;
                if (is_string($next) && !str_starts_with($next, '--')) {
                    return $next;
                }

                return true;
            }
        }

        return CLI::getOption($name);
    }
}
