<?php

namespace App\Commands;

use App\Models\FollowupLogModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * BackfillFollowups — spark followup:backfill command
 *
 * Finds all guests that have zero guest_followup_log entries and schedules
 * the currently active follow-up sequences for them, using the guest's
 * earliest recorded visit date (or created_at as a fallback).
 *
 * Usage:
 *   php spark followup:backfill
 *   php spark followup:backfill --parish 5
 *   php spark followup:backfill --dry-run
 *   php spark followup:backfill --parish 5 --dry-run
 */
class BackfillFollowups extends BaseCommand
{
    protected $group       = 'Followup';
    protected $name        = 'followup:backfill';
    protected $description = 'Schedule follow-up sequences for guests that have no log entries yet.';
    protected $usage       = 'followup:backfill [--parish <id>] [--dry-run]';
    protected $options     = [
        '--parish'  => 'Scope backfill to a single parish_id.',
        '--dry-run' => 'Print what would be scheduled without writing any records.',
    ];

    public function run(array $params): void
    {
        $parishOption = CLI::getOption('parish');
        $isDryRun     = CLI::getOption('dry-run') !== null;
        $parishId     = ($parishOption !== null && $parishOption !== false)
            ? (int) $parishOption
            : null;

        CLI::write('followup:backfill starting', 'cyan');

        if ($isDryRun) {
            CLI::write('[DRY-RUN] No records will be written.', 'yellow');
        }

        if ($parishId !== null) {
            CLI::write("Scoped to parish_id = {$parishId}", 'cyan');
        }

        $db = db_connect();

        // ---------------------------------------------------------------
        // 1. Find guests with zero guest_followup_log rows
        // ---------------------------------------------------------------
        $query = $db->table('guest g')
            ->select('g.id, g.parish_id, g.first_name, g.last_name, g.visit_date, g.created_at, g.preferred_timezone')
            ->join('followup_log gfl', 'gfl.person_id = g.person_id', 'left')
            ->where('gfl.id IS NULL', null, false);

        if ($parishId !== null) {
            $query->where('g.parish_id', $parishId);
        }

        $guests = $query->get()->getResultArray();

        if (empty($guests)) {
            CLI::write('No guests need backfilling — all guests already have log entries.', 'green');
            return;
        }

        CLI::write('Found ' . count($guests) . ' guest(s) with no log entries.', 'cyan');

        // ---------------------------------------------------------------
        // 2. Fetch available active sequences per parish (cache per parish)
        // ---------------------------------------------------------------
        /** @var FollowupLogModel $logModel */
        $logModel         = model(FollowupLogModel::class);
        $seqCountByParish = [];

        $totalScheduled = 0;
        $skippedNoSeq   = 0;

        // ---------------------------------------------------------------
        // 3. Schedule each guest
        // ---------------------------------------------------------------
        foreach ($guests as $guest) {
            $guestId   = (int) $guest['id'];
            $cId       = (int) $guest['parish_id'];
            $name      = trim(($guest['first_name'] ?? '') . ' ' . ($guest['last_name'] ?? ''));

            // Use earliest recorded visit, then guests.visit_date, then created_at
            $visitDate = $this->resolveVisitDate($db, $guestId, $guest);

            $tz = (string) ($guest['preferred_timezone'] ?? '');

            // Check active sequences for this parish (cache result)
            if (!isset($seqCountByParish[$cId])) {
                $seqCountByParish[$cId] = (int) $db->table('followup_sequence')
                    ->groupStart()
                    ->where('parish_id', $cId)
                    ->orWhere('parish_id IS NULL', null, false)
                    ->groupEnd()
                    ->where('is_active', 1)
                    ->countAllResults();
            }

            if ($seqCountByParish[$cId] === 0) {
                CLI::write(
                    "  SKIP  #{$guestId} {$name} — no active sequences for parish {$cId}",
                    'yellow'
                );
                $skippedNoSeq++;
                continue;
            }

            $tzNote = $tz !== '' ? " (tz: {$tz})" : '';
            CLI::write("  Queue #{$guestId} {$name} — visit: {$visitDate}{$tzNote}, parish: {$cId}", 'white');

            if (!$isDryRun) {
                $count = $logModel->scheduleActiveSequencesForGuest($guestId, $cId, $visitDate, $tz);
                $totalScheduled += $count;
                CLI::write("        → Scheduled {$count} action(s)", 'green');
            }
        }

        if ($isDryRun) {
            CLI::write(
                '[DRY-RUN] Would process ' . count($guests) . ' guest(s). Skipped (no sequences): ' . $skippedNoSeq . '.',
                'yellow'
            );
        } else {
            CLI::write(
                'Done. Total log entries created: ' . $totalScheduled . '. Skipped (no sequences): ' . $skippedNoSeq . '.',
                'green'
            );
        }
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Determine the best visit date to use as the base for scheduling.
     * Priority: earliest guest_visit row → guests.visit_date → guests.created_at.
     */
    private function resolveVisitDate(\CodeIgniter\Database\ConnectionInterface $db, int $guestId, array $guest): string
    {
        $earliest = $db->table('guest_visit')
            ->select('visit_date')
            ->where('guest_id', $guestId)
            ->orderBy('visit_date', 'ASC')
            ->limit(1)
            ->get()
            ->getRowArray();

        if ($earliest !== null && !empty($earliest['visit_date'])) {
            return (string) $earliest['visit_date'];
        }

        if (!empty($guest['visit_date'])) {
            return (string) $guest['visit_date'];
        }

        // Fall back to the date portion of created_at
        return substr((string) ($guest['created_at'] ?? date('Y-m-d')), 0, 10);
    }
}
