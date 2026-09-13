<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * One-time migration: copies all follow_up_tasks rows into call_tasks,
 * then optionally drops the legacy table.
 *
 * Usage:
 *   php spark followup:migrate-tasks
 *   php spark followup:migrate-tasks --dry-run      # preview without writing
 *   php spark followup:migrate-tasks --drop-table   # migrate + drop follow_up_tasks
 */
class MigrateFollowUpTasks extends BaseCommand
{
    protected $group       = 'Followup';
    protected $name        = 'followup:migrate-tasks';
    protected $description = 'Migrates legacy follow_up_tasks rows to call_tasks, then optionally drops the legacy table.';

    protected $options = [
        '--dry-run'    => 'Preview rows that would be migrated without writing.',
        '--drop-table' => 'Drop the follow_up_tasks table after a successful migration.',
    ];

    public function run(array $params): void
    {
        $db      = db_connect();
        $dryRun  = array_key_exists('dry-run', $params)
            || in_array('--dry-run', $params, true);
        $dropIt  = array_key_exists('drop-table', $params)
            || in_array('--drop-table', $params, true);

        if (!$db->tableExists('follow_up_tasks')) {
            CLI::write('follow_up_tasks table does not exist — nothing to migrate.', 'yellow');
            return;
        }

        $rows = $db->table('follow_up_tasks ft')
            ->select('ft.id, ft.guest_id, ft.assigned_to, ft.due_date, ft.status, ft.notes, ft.created_at, ft.updated_at, g.parish_id, g.phone')
            ->join('guest g', 'g.id = ft.guest_id', 'left')
            ->get()
            ->getResultArray();

        $total   = count($rows);
        $skipped = 0;
        $inserted = 0;

        CLI::write("Found {$total} row(s) in follow_up_tasks.", 'cyan');

        if ($dryRun) {
            CLI::write('[DRY RUN] No changes will be written.', 'yellow');
        }

        foreach ($rows as $row) {
            $parishId = (int) ($row['parish_id'] ?? 0);

            if ($parishId <= 0) {
                CLI::write("  SKIP  follow_up_tasks.id={$row['id']} — guest {$row['guest_id']} has no parish_id", 'yellow');
                $skipped++;
                continue;
            }

            // Dedup guard: skip if a call_task with same guest + assignee + date already exists.
            $exists = $db->table('call_task')
                ->where('guest_id',       (int) $row['guest_id'])
                ->where('assigned_to',    (int) $row['assigned_to'])
                ->where('scheduled_date', $row['due_date'])
                ->countAllResults() > 0;

            if ($exists) {
                CLI::write("  SKIP  follow_up_tasks.id={$row['id']} — duplicate already exists in call_tasks", 'yellow');
                $skipped++;
                continue;
            }

            $callTask = [
                'parish_id'      => $parishId,
                'guest_id'       => (int) $row['guest_id'],
                'log_id'         => null,
                'assigned_to'    => (int) $row['assigned_to'],
                'phone'          => $row['phone'] ?? null,
                'notes'          => $row['notes'],
                'status'         => $row['status'],
                'scheduled_date' => $row['due_date'],
                'completed_at'   => ($row['status'] === 'completed') ? $row['updated_at'] : null,
                'created_at'     => $row['created_at'],
                'updated_at'     => $row['updated_at'],
            ];

            CLI::write("  MIGRATE follow_up_tasks.id={$row['id']} → call_tasks (guest={$row['guest_id']}, date={$row['due_date']}, status={$row['status']})", 'green');

            if (!$dryRun) {
                $db->table('call_task')->insert($callTask);
                $inserted++;
            }
        }

        $action = $dryRun ? 'Would migrate' : 'Migrated';
        CLI::write("{$action}: {$inserted} row(s) inserted, {$skipped} skipped.", 'cyan');

        if ($dropIt && !$dryRun) {
            if ($db->tableExists('follow_up_tasks')) {
                $db->query('DROP TABLE follow_up_tasks');
                CLI::write('follow_up_tasks table dropped.', 'green');
            }
        } elseif ($dropIt && $dryRun) {
            CLI::write('[DRY RUN] Would drop follow_up_tasks table.', 'yellow');
        }

        CLI::write('Done.', 'green');
    }
}
