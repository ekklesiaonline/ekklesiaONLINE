<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;

/**
 * Shared schema/table resolution utility.
 *
 * Centralizes the duplicated table-name resolution logic that was
 * copy-pasted across MemberAccessService, NonPortalMemberProvisioningService,
 * MemberMergeService, ParishPeopleRegistryService, PeopleDirectoryService,
 * and UserRegistrationRequestModel.
 *
 * Handles:
 * - Prefixed vs unprefixed table names (SQLite test prefix vs production).
 * - Legacy table-name variants (singular vs plural).
 * - Column existence checks for schema-sensitive queries.
 */
class SchemaResolver
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * Get the column names for a base table, trying prefixed and
     * unprefixed variants. Returns [] if the table doesn't exist.
     *
     * @return array<int, string>
     */
    public function getColumnsForTable(string $baseTable): array
    {
        $prefixed = $this->db->getPrefix() . $baseTable;
        $candidates = array_values(array_unique([$prefixed, $baseTable]));

        foreach ($candidates as $table) {
            try {
                $columns = $this->db->getFieldNames($table);
                if ($columns !== []) {
                    return $columns;
                }
            } catch (\Throwable $e) {
                // Try next table-name variant.
            }
        }

        return [];
    }

    /**
     * Resolve the first existing table from a list of base candidates.
     * Returns the base name (without prefix) or null if none exist.
     *
     * @param array<int, string> $baseCandidates
     */
    public function resolveExistingTable(array $baseCandidates): ?string
    {
        foreach ($baseCandidates as $candidate) {
            if ($this->getColumnsForTable($candidate) !== []) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Resolve the existing profile table, trying prefixed and unprefixed
     * variants of both 'profile' and 'profiles'. Returns the full table
     * name (with prefix if applicable) or null if none exist.
     */
    public function resolveExistingProfileTable(): ?string
    {
        $candidates = [
            $this->db->getPrefix() . 'profile',
            'profile',
            $this->db->getPrefix() . 'profiles',
            'profiles',
        ];

        foreach (array_values(array_unique($candidates)) as $table) {
            try {
                $columns = $this->db->getFieldNames($table);
                if ($columns !== []) {
                    return $table;
                }
            } catch (\Throwable $e) {
                // Try next table-name variant.
            }
        }

        return null;
    }

    /**
     * Drop payload keys that are not columns of the target table. Tries
     * prefixed/unprefixed and singular/plural name variants; returns the
     * payload untouched when no table variant can be resolved.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function filterPayloadByTableColumns(string $baseTable, array $payload): array
    {
        $tableVariants = [$baseTable];
        if (str_ends_with($baseTable, 's')) {
            $tableVariants[] = substr($baseTable, 0, -1);
        } else {
            $tableVariants[] = $baseTable . 's';
        }

        $candidates = [];
        foreach (array_values(array_unique($tableVariants)) as $variant) {
            $candidates[] = $this->db->getPrefix() . $variant;
            $candidates[] = $variant;
        }

        $columns = [];
        foreach (array_values(array_unique($candidates)) as $table) {
            try {
                $found = array_map('strtolower', $this->db->getFieldNames($table));
                if ($found !== []) {
                    $columns = $found;
                    break;
                }
            } catch (\Throwable $e) {
                // Try next table-name variant.
            }
        }

        if ($columns === []) {
            return $payload;
        }

        foreach (array_keys($payload) as $key) {
            if (! in_array(strtolower((string) $key), $columns, true)) {
                unset($payload[$key]);
            }
        }

        return $payload;
    }

    /**
     * Resolve the existing parish_people snapshot table.
     * Returns the table name or null if it doesn't exist.
     */
    public function resolveSnapshotTable(): ?string
    {
        return $this->resolveExistingTable(['parish_people']);
    }

    /**
     * Resolve the scope column for the parish_people snapshot table.
     * Always returns 'parish_id' since that's the only valid scope column.
     */
    public function resolveSnapshotScopeColumn(string $table): string
    {
        return 'parish_id';
    }
}
