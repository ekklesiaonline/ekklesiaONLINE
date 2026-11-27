<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Throwable;

class DirectoryReadinessService
{
    /**
     * Evaluate whether the ecclesiastical hierarchy (province -> diocese -> active parish)
     * is populated and ready for production traffic.
     *
     * @return array{
     *     ready: bool,
     *     counts: array{
     *         provinces: int,
     *         dioceses: int,
     *         linked_dioceses: int,
     *         orphaned_dioceses: int,
     *         parishes: int,
     *         active_parishes: int,
     *         linked_active_parishes: int,
     *         unlinked_active_parishes: int
     *     },
     *     blocking_issues: list<string>,
     *     warnings: list<string>
     * }
     */
    public function evaluate(?BaseConnection $db = null): array
    {
        $counts = [
            'provinces' => 0,
            'dioceses' => 0,
            'linked_dioceses' => 0,
            'orphaned_dioceses' => 0,
            'parishes' => 0,
            'active_parishes' => 0,
            'linked_active_parishes' => 0,
            'unlinked_active_parishes' => 0,
        ];
        $blocking = [];
        $warnings = [];

        try {
            $connection = $db ?? db_connect();

            foreach (['province', 'diocese', 'parish'] as $table) {
                if (!$connection->tableExists($table)) {
                    $blocking[] = sprintf('Required table `%s` does not exist.', $table);
                }
            }

            if ($blocking !== []) {
                return [
                    'ready' => false,
                    'counts' => $counts,
                    'blocking_issues' => $blocking,
                    'warnings' => $warnings,
                ];
            }

            $counts['provinces'] = (int) $connection->table('province')->countAllResults();
            $counts['dioceses'] = (int) $connection->table('diocese')->countAllResults();
            $counts['linked_dioceses'] = (int) $connection->table('diocese')
                ->join('province', 'province.id = diocese.province_id', 'inner')
                ->countAllResults();
            $counts['orphaned_dioceses'] = max(0, $counts['dioceses'] - $counts['linked_dioceses']);

            $hasDeletedAt = $connection->fieldExists('deleted_at', 'parish');
            $hasIsActive = $connection->fieldExists('is_active', 'parish');

            $parishBuilder = $connection->table('parish');
            if ($hasDeletedAt) {
                $parishBuilder->where('parish.deleted_at', null);
            }
            $counts['parishes'] = (int) $parishBuilder->countAllResults();

            $activeParishBuilder = $connection->table('parish');
            if ($hasDeletedAt) {
                $activeParishBuilder->where('parish.deleted_at', null);
            }
            if ($hasIsActive) {
                $activeParishBuilder->where('parish.is_active', 1);
            }
            $counts['active_parishes'] = (int) $activeParishBuilder->countAllResults();

            $linkedParishBuilder = $connection->table('parish')
                ->join('diocese', 'diocese.id = parish.diocese_id', 'inner')
                ->join('province', 'province.id = diocese.province_id', 'inner');
            if ($hasDeletedAt) {
                $linkedParishBuilder->where('parish.deleted_at', null);
            }
            if ($hasIsActive) {
                $linkedParishBuilder->where('parish.is_active', 1);
            }
            $counts['linked_active_parishes'] = (int) $linkedParishBuilder->countAllResults();
            $counts['unlinked_active_parishes'] = max(0, $counts['active_parishes'] - $counts['linked_active_parishes']);
        } catch (Throwable) {
            return [
                'ready' => false,
                'counts' => $counts,
                'blocking_issues' => ['Unable to query the province/diocese/parish hierarchy.'],
                'warnings' => [],
            ];
        }

        if ($counts['provinces'] < 1) {
            $blocking[] = 'The `province` table is empty (at least 1 province is required).';
        }
        if ($counts['linked_dioceses'] < 1) {
            $blocking[] = 'No `diocese` rows are linked to a valid `province` (at least 1 is required).';
        }
        if ($counts['linked_active_parishes'] < 1) {
            $blocking[] = 'No active `parish` rows are linked to a valid `diocese` -> `province` parent chain (at least 1 is required before go-live).';
        }

        if ($counts['orphaned_dioceses'] > 0) {
            $warnings[] = sprintf('%d diocese row(s) have no matching parent province.', $counts['orphaned_dioceses']);
        }
        if ($counts['unlinked_active_parishes'] > 0) {
            $warnings[] = sprintf('%d active parish row(s) have no valid diocese -> province parent chain.', $counts['unlinked_active_parishes']);
        }

        return [
            'ready' => $blocking === [],
            'counts' => $counts,
            'blocking_issues' => $blocking,
            'warnings' => $warnings,
        ];
    }

    public function isReady(?BaseConnection $db = null): bool
    {
        return $this->evaluate($db)['ready'];
    }
}
