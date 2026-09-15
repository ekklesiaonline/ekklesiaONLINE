<?php

namespace App\Commands;

use App\Models\ParishPeopleModel;
use App\Models\MemberParishAffiliationModel;
use App\Models\PersonMembershipDetailsModel;
use App\Services\ParishPeopleRegistryService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class ParishPeopleIntegrityCheck extends BaseCommand
{
    protected $group       = 'Database';
    protected $name        = 'parish_people:integrity';
    protected $description = 'Check and optionally repair parish_people snapshot drift.';
    protected $usage       = 'parish_people:integrity [options]';
    protected $arguments   = [];
    protected $options     = [
        '--repair' => 'Re-sync drifted parishes instead of only reporting.',
        '--parish' => 'Limit to a single parish ID.',
    ];

    public function run(array $params)
    {
        $repair = (bool) array_search('--repair', $params, true);
        $parishId = null;
        foreach ($params as $param) {
            if (str_starts_with($param, '--parish=')) {
                $parishId = (int) substr($param, 9);
            }
        }

        $db = db_connect();
        $parishTable = $this->resolveExistingTable(['parish']);
        if ($parishTable === null) {
            CLI::error('No parish table found.');
            return EXIT_ERROR;
        }

        $query = $db->table($parishTable)->select('id, name')->orderBy('id', 'ASC');
        if ($parishId !== null && $parishId > 0) {
            $query->where('id', $parishId);
        }
        $parishes = $query->get()->getResultArray();

        if ($parishes === []) {
            CLI::write('No parishes to check.', 'yellow');
            return EXIT_SUCCESS;
        }

        $registry = new ParishPeopleRegistryService();
        $totalDrift = 0;
        $totalMissingDetails = 0;

        foreach ($parishes as $parish) {
            $id = (int) $parish['id'];
            $name = (string) $parish['name'];

            CLI::write("Checking parish {$id}: {$name}");

            $registry->syncParish($id);

            $drift = $this->detectDrift($id);
            $missing = $this->detectMissingDetails($id);

            foreach ($drift as $row) {
                $totalDrift++;
                CLI::write(
                    "  DRIFT profile_id={$row['profile_id']} "
                    . "affiliation_status={$row['affiliation_status']} "
                    . "snapshot_status={$row['snapshot_status']}",
                    'red'
                );
            }

            foreach ($missing as $row) {
                $totalMissingDetails++;
                CLI::write(
                    "  MISSING person_membership_details for profile_id={$row['profile_id']}",
                    'yellow'
                );
            }

            if ($repair && ($drift !== [] || $missing !== [])) {
                CLI::write("  Re-syncing parish {$id}...", 'green');
                $registry->syncParish($id);
            }
        }

        CLI::write('');
        CLI::write("Drifted rows: {$totalDrift}");
        CLI::write("Missing person_membership_details rows: {$totalMissingDetails}");

        if ($repair) {
            CLI::write('Repair mode was enabled; all drifted parishes were re-synced.', 'green');
        } else {
            CLI::write('Run with --repair to re-sync drifted parishes.', 'yellow');
        }

        return EXIT_SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function detectDrift(int $parishId): array
    {
        $snapshotTable = $this->resolveExistingTable(['parish_people', 'parish_people']);
        $affiliationTable = $this->resolveExistingTable(['parish_affiliation']);

        if ($snapshotTable === null || $affiliationTable === null) {
            return [];
        }

        $columns = $this->db()->getFieldNames($snapshotTable);
        $scopeColumn = in_array('parish_id', array_map('strtolower', $columns), true) ? 'parish_id' : 'parish_id';

        $sql = 'SELECT pp.profile_id, mca.membership_status AS affiliation_status, pp.membership_status AS snapshot_status';
        $sql .= ' FROM ' . $snapshotTable . ' pp';
        $sql .= ' INNER JOIN ' . $affiliationTable . ' mca ON mca.linked_user_id = pp.user_id AND mca.parish_id = pp.' . $scopeColumn;
        $sql .= ' WHERE pp.' . $scopeColumn . ' = ' . $parishId;
        $sql .= ' AND pp.deleted_at IS NULL';
        $sql .= ' AND mca.deleted_at IS NULL';
        $sql .= ' AND mca.membership_status != pp.membership_status';

        return $this->db()->query($sql)->getResultArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function detectMissingDetails(int $parishId): array
    {
        $snapshotTable = $this->resolveExistingTable(['parish_people', 'parish_people']);

        if ($snapshotTable === null) {
            return [];
        }

        $columns = $this->db()->getFieldNames($snapshotTable);
        $scopeColumn = in_array('parish_id', array_map('strtolower', $columns), true) ? 'parish_id' : 'parish_id';

        $sql = 'SELECT pp.profile_id';
        $sql .= ' FROM ' . $snapshotTable . ' pp';
        $sql .= ' LEFT JOIN person_membership_details pmd ON pmd.person_id = pp.profile_id AND pmd.parish_id = pp.' . $scopeColumn;
        $sql .= ' WHERE pp.' . $scopeColumn . ' = ' . $parishId;
        $sql .= ' AND pp.deleted_at IS NULL';
        $sql .= ' AND pmd.id IS NULL';

        return $this->db()->query($sql)->getResultArray();
    }

    private function resolveExistingTable(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            $prefixed = $this->db()->DBPrefix . $candidate;
            foreach ([$prefixed, $candidate] as $table) {
                try {
                    if ($this->db()->tableExists($table)) {
                        return $table;
                    }
                } catch (\Throwable $e) {
                    // Try next.
                }
            }
        }

        return null;
    }

    private function db(): \CodeIgniter\Database\BaseConnection
    {
        return db_connect();
    }
}
