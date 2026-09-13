<?php

namespace App\Commands;

use App\Models\SmsQueueModel;
use App\Services\SmsService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * ProcessSmsQueue — spark SMS queue processor
 *
 * Reads pending rows from the sms_queue table and dispatches them through
 * SmsService. Designed to run as a cron job (e.g., every minute) so that
 * callers can rate-limit outbound traffic by controlling the --limit flag.
 *
 * Usage:
 *   php spark sms:process
 *   php spark sms:process --limit 25
 *   php spark sms:process --dry-run
 *   php spark sms:process --parish 3 --limit 10
 *
 * Cron example (process up to 30 messages per minute):
 *   * * * * * /usr/bin/php /var/www/html/spark sms:process --limit 30 >> /var/log/sms_process.log 2>&1
 */
class ProcessSmsQueue extends BaseCommand
{
    protected $group       = 'Messaging';
    protected $name        = 'sms:process';
    protected $description = 'Process pending queued SMS messages from the sms_queue table.';
    protected $usage       = 'sms:process [--limit <n>] [--parish <id>] [--dry-run]';
    protected $options     = [
        '--limit'   => 'Maximum number of messages to send per run (default: 50).',
        '--parish'  => 'Restrict processing to a specific parish_id.',
        '--dry-run' => 'Show what would be sent without actually sending.',
    ];

    /** @var int Default batch size — tune to stay within provider rate limits */
    private const DEFAULT_LIMIT = 50;

    public function run(array $params): void
    {
        $limitOption  = CLI::getOption('limit');
        $parishOption = CLI::getOption('parish');
        $isDryRun     = CLI::getOption('dry-run') !== null;

        $limit    = ($limitOption !== null && $limitOption !== false && (int) $limitOption > 0)
            ? (int) $limitOption
            : self::DEFAULT_LIMIT;

        $parishFilter = ($parishOption !== null && $parishOption !== false && (int) $parishOption > 0)
            ? (int) $parishOption
            : null;

        CLI::write('sms:process starting — ' . date('Y-m-d H:i:s'), 'cyan');

        if ($isDryRun) {
            CLI::write('[DRY-RUN] No messages will be sent and no records updated.', 'yellow');
        }
        if ($parishFilter !== null) {
            CLI::write("Scoped to parish_id = {$parishFilter}", 'cyan');
        }

        CLI::write("Batch limit: {$limit}", 'cyan');

        // ---------------------------------------------------------------
        // 1. Load due messages
        // ---------------------------------------------------------------
        /** @var SmsQueueModel $queueModel */
        $queueModel = model(SmsQueueModel::class);

        $messages = $queueModel->getDueMessages($limit);

        // Apply parish filter in PHP to reuse the model query (usually small result set)
        if ($parishFilter !== null) {
            $messages = array_values(
                array_filter($messages, fn($m) => (int) $m['parish_id'] === $parishFilter)
            );
        }

        if (empty($messages)) {
            CLI::write('No messages due for processing.', 'green');
            return;
        }

        CLI::write('Found ' . count($messages) . ' message(s) to process.', 'cyan');

        // ---------------------------------------------------------------
        // 2. Process each message
        // ---------------------------------------------------------------
        $sms = new SmsService();

        $counters  = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        $tableRows = [];

        foreach ($messages as $msg) {
            $id    = (int) $msg['id'];
            $to    = (string) $msg['to_number'];
            $body  = (string) $msg['body'];
            $from  = ($msg['from_number'] ?? '') !== '' ? (string) $msg['from_number'] : null;
            $short = mb_substr($body, 0, 40) . (mb_strlen($body) > 40 ? '…' : '');

            if ($isDryRun) {
                $tableRows[] = [$id, $to, $short, 'dry-run'];
                $counters['skipped']++;
                continue;
            }

            $result = $sms->send($to, $body, $from);

            if ($result['success']) {
                $queueModel->markSent($id, $result['message_id']);
                $counters['sent']++;
                $tableRows[] = [$id, $to, $short, 'sent (' . $result['message_id'] . ')'];

                log_message('info', '[sms:process] sent queue_id={id} to={to} msg_id={mid}', [
                    'id'  => $id,
                    'to'  => $to,
                    'mid' => $result['message_id'],
                ]);
            } else {
                $queueModel->recordAttempt($id, $result['error']);
                $counters['failed']++;
                $tableRows[] = [$id, $to, $short, 'failed — ' . $result['error']];

                log_message('warning', '[sms:process] failed queue_id={id} to={to} err={err}', [
                    'id'  => $id,
                    'to'  => $to,
                    'err' => $result['error'],
                ]);
            }
        }

        // ---------------------------------------------------------------
        // 3. Summary table
        // ---------------------------------------------------------------
        if (!empty($tableRows)) {
            CLI::table($tableRows, ['Queue ID', 'To', 'Body (preview)', 'Result']);
        }

        CLI::newLine();

        $totalProcessed = $counters['sent'] + $counters['failed'];
        $colour         = $counters['failed'] > 0 ? 'red' : 'green';

        CLI::write(
            sprintf(
                'Complete — sent: %d | failed: %d | skipped: %d | total processed: %d',
                $counters['sent'],
                $counters['failed'],
                $counters['skipped'],
                $totalProcessed
            ),
            $colour
        );

        $remaining = $queueModel->countPending();
        if ($remaining > 0) {
            CLI::write("Remaining in queue: {$remaining}", 'yellow');
        }
    }
}
