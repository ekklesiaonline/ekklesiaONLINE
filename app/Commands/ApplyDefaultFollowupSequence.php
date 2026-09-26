<?php

namespace App\Commands;

use App\Services\FollowupCloneService;
use App\Services\FollowupSequenceService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class ApplyDefaultFollowupSequence extends BaseCommand
{
    protected $group       = 'Follow-up';
    protected $name        = 'followup:apply-default';
    protected $description = 'Clone the best upstream default follow-up sequence (diocesan → global) into parishes that do not have a default sequence.';
    protected $usage       = 'followup:apply-default [options]';

    protected $options = [
        '--parish'  => 'Apply only to a specific parish ID',
        '--dry-run' => 'Show what would happen without making changes',
    ];

    public function run(array $params)
    {
        $parishOption = CLI::getOption('parish');
        $parishId     = $parishOption !== null ? (int) $parishOption : null;
        $dryRun       = CLI::getOption('dry-run') !== null;

        $cloneService = new FollowupCloneService();
        $sequenceService = new FollowupSequenceService();

        if ($parishId !== null) {
            $parishes = [(int) $parishId];
        } else {
            $db = db_connect();
            $rows = $db->table('parish')
                ->select('id')
                ->where('deleted_at IS NULL', null, false)
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();
            $parishes = array_map(static fn(array $r): int => (int) $r['id'], $rows);
        }

        $globalSequence = $sequenceService->getGlobalDefaultTemplateSequence();
        if (!$globalSequence) {
            CLI::write('No global default exists — parishes will use their diocesan default if one is set.', 'yellow');
        }

        $processed = 0;
        $skipped   = 0;
        $failed    = 0;

        foreach ($parishes as $id) {
            if ($sequenceService->hasDefaultForParish($id)) {
                CLI::write("Parish {$id}: already has a default sequence — skipped.");
                $skipped++;
                continue;
            }

            $upstream = $sequenceService->resolveUpstreamDefaultForParish($id);
            if (!$upstream) {
                CLI::write("Parish {$id}: no upstream default (diocesan/global) — skipped.", 'yellow');
                $skipped++;
                continue;
            }

            if ($dryRun) {
                CLI::write("Parish {$id}: would clone upstream default sequence #{$upstream['id']} ({$upstream['name']}).");
                $processed++;
                continue;
            }

            $newId = $cloneService->cloneUpstreamDefaultIntoParish($id);
            if ($newId) {
                CLI::write("Parish {$id}: cloned default sequence as #{$newId}.");
                $processed++;
            } else {
                CLI::error("Parish {$id}: cloning failed.");
                $failed++;
            }
        }

        CLI::newLine();
        CLI::write("Processed: {$processed} | Skipped: {$skipped} | Failed: {$failed}");

        return $failed > 0 ? 1 : 0;
    }
}
