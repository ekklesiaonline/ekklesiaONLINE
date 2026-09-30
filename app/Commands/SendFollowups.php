<?php

namespace App\Commands;

use App\Libraries\DataEncryption;
use App\Models\FollowupTemplateModel;
use App\Models\FollowupLogModel;
use App\Services\FollowupDispatchService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * SendFollowups — spark follow-up dispatch command
 *
 * Runs through all pending due followup log entries and dispatches them
 * through the appropriate channel (email / text / call / mail / gift).
 *
 * Usage:
 *   php spark followup:send
 *   php spark followup:send --parish 5
 *   php spark followup:send --dry-run
 *   php spark followup:send --parish 5 --dry-run
 */
class SendFollowups extends BaseCommand
{
    protected $group       = 'Followup';
    protected $name        = 'followup:send';
    protected $description = 'Dispatch all pending due followup log entries (email, text, call, mail, gift).';
    protected $usage       = 'followup:send [--parish <id>] [--dry-run]';
    protected $options     = [
        '--parish'  => 'Scope dispatch to a single parish_id.',
        '--dry-run' => 'Print what would be dispatched without writing any records.',
    ];

    public function run(array $params): void
    {
        $parishOption = $params['parish'] ?? CLI::getOption('parish');
        $isDryRun     = array_key_exists('dry-run', $params) || CLI::getOption('dry-run') !== null;
        $parishId     = ($parishOption !== null && $parishOption !== false)
            ? (int) $parishOption
            : null;

        $today = date('Y-m-d');

        CLI::write("followup:send starting — as of {$today}", 'cyan');
        if ($isDryRun) {
            CLI::write('[DRY-RUN] No records will be written.', 'yellow');
        }
        if ($parishId !== null) {
            CLI::write("Scoped to parish_id = {$parishId}", 'cyan');
        }

        // ---------------------------------------------------------------
        // 1. Load pending log entries
        // ---------------------------------------------------------------
        /** @var FollowupLogModel $logModel */
        $logModel = model(FollowupLogModel::class);

        $rows = ($parishId !== null)
            ? $logModel->getPendingDueForParish($parishId, $today)
            : $logModel->getPendingDue($today);

        if (empty($rows)) {
            CLI::write('No pending entries due today or earlier.', 'green');
            return;
        }

        CLI::write('Found ' . count($rows) . ' pending entries.', 'cyan');

        // ---------------------------------------------------------------
        // 2. Set up services
        // ---------------------------------------------------------------
        /** @var FollowupTemplateModel $templateModel */
        $templateModel   = model(FollowupTemplateModel::class);
        $dispatcher      = new FollowupDispatchService();

        // ---------------------------------------------------------------
        // 3. Dispatch each entry
        // ---------------------------------------------------------------
        $counters = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        $tableRows = [];

        /** @var \App\Services\AuditService $audit */
        $audit = service('audit');

        foreach ($rows as $row) {
            $logId    = (int) $row['id'];
            $channel  = (string) ($row['channel'] ?? '');
            $name     = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));

            // Load template (may be null for call/gift channels)
            $template = null;
            if (!empty($row['template_id'])) {
                $template = $templateModel->find((int) $row['template_id']);
            }

            // Build a full person array with decrypted fields so the dispatcher
            // doesn't have to worry about encryption (it still handles it safely)
            $visitor = [
                'id'                   => (int)    ($row['person_id'] ?? 0),
                'person_id'            => (int)    ($row['person_id'] ?? 0),
                'parish_id'            => (int)    ($row['parish_id'] ?? 0),
                'first_name'           => (string) ($row['first_name'] ?? ''),
                'last_name'            => (string) ($row['last_name'] ?? ''),
                'email'                => (string) ($row['email'] ?? ''),
                'phone'                => (string) ($row['phone'] ?? ''),
                'opted_out_channels'   => (string) ($row['opted_out_channels'] ?? ''),
                'preferred_timezone'   => (string) ($row['preferred_timezone'] ?? ''),
                'interests'            => (string) ($row['interests'] ?? ''),
                'visit_date'           => (string) ($row['scheduled_date'] ?? ''),
            ];

            if ($isDryRun) {
                $tableRows[] = [$channel, $name, 'dry-run', $row['scheduled_date'] ?? ''];
                $counters['skipped']++;
                continue;
            }

            $result = $dispatcher->dispatch($row, $template, $visitor);

            if ($result['success']) {
                $metadata = $result['metadata'] ?? [];
                $logModel->markExecuted($logId, $metadata);
                $counters['sent']++;
                $tableRows[] = [$channel, $name, 'sent', $row['scheduled_date'] ?? ''];

                $audit->log('followup.cron_dispatched', [
                    'parish_id'   => (int) ($row['parish_id'] ?? 0),
                    'entity_type' => 'person',
                    'entity_id'   => (int) ($row['person_id'] ?? 0),
                    'meta'        => [
                        'log_id'         => $logId,
                        'channel'        => $channel,
                        'scheduled_date' => $row['scheduled_date'] ?? '',
                        'visitor_name'     => $name,
                    ],
                ]);
            } elseif (!empty($result['metadata']['skipped'])) {
                // Opt-outs are intentional skips, not infrastructure failures.
                $logModel->markSkipped($logId, $result['metadata']);
                $counters['skipped']++;
                $tableRows[] = [$channel, $name, 'skipped — ' . ($result['metadata']['reason'] ?? 'opted_out'), $row['scheduled_date'] ?? ''];

                $audit->log('followup.cron_dispatch_skipped', [
                    'parish_id'   => (int) ($row['parish_id'] ?? 0),
                    'entity_type' => 'person',
                    'entity_id'   => (int) ($row['person_id'] ?? 0),
                    'meta'        => [
                        'log_id'         => $logId,
                        'channel'        => $channel,
                        'scheduled_date' => $row['scheduled_date'] ?? '',
                        'visitor_name'     => $name,
                        'reason'         => $result['metadata']['reason'] ?? 'opted_out',
                    ],
                ]);
            } else {
                $logModel->markFailed($logId, ['error' => $result['error']]);
                $counters['failed']++;
                $tableRows[] = [$channel, $name, 'failed — ' . $result['error'], $row['scheduled_date'] ?? ''];

                log_message('error', '[followup:send] logId={id} channel={ch} visitor={nm} error={err}', [
                    'id'  => $logId,
                    'ch'  => $channel,
                    'nm'  => $name,
                    'err' => $result['error'],
                ]);

                $audit->log('followup.cron_dispatch_failed', [
                    'parish_id'   => (int) ($row['parish_id'] ?? 0),
                    'entity_type' => 'person',
                    'entity_id'   => (int) ($row['person_id'] ?? 0),
                    'meta'        => [
                        'log_id'         => $logId,
                        'channel'        => $channel,
                        'scheduled_date' => $row['scheduled_date'] ?? '',
                        'visitor_name'     => $name,
                        'error'          => $result['error'],
                    ],
                ]);
            }
        }

        // ---------------------------------------------------------------
        // 4. Summary
        // ---------------------------------------------------------------
        if (!empty($tableRows)) {
            CLI::table($tableRows, ['Channel', 'Visitor', 'Status', 'Scheduled']);
        }

        CLI::newLine();
        $colour = $counters['failed'] > 0 ? 'red' : 'green';
        CLI::write(
            sprintf(
                'Complete — sent: %d | failed: %d | skipped: %d',
                $counters['sent'],
                $counters['failed'],
                $counters['skipped']
            ),
            $colour
        );

        // Write one summary audit entry so admins can see cron run history
        // even on days with no pending entries.
        $audit->log('followup.cron_run', [
            'parish_id' => $parishId,
            'meta'      => [
                'sent'      => $counters['sent'],
                'failed'    => $counters['failed'],
                'skipped'   => $counters['skipped'],
                'dry_run'   => $isDryRun,
                'date'      => $today,
            ],
        ]);
    }
}
