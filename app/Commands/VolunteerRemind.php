<?php

namespace App\Commands;

use App\Models\VolunteerScheduleModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * VolunteerRemind — spark volunteers:remind
 *
 * Sends an email reminder to each non-cancelled volunteer whose schedule date
 * falls exactly N days from now (default: 2).
 *
 * Usage:
 *   php spark volunteers:remind
 *   php spark volunteers:remind --days 1
 *   php spark volunteers:remind --days 7 --parish 5
 *   php spark volunteers:remind --dry-run
 *
 * Recommended cron (remind 2 days ahead, every morning at 8 AM):
 *   0 8 * * * php /path/to/spark volunteers:remind >> /path/to/writable/logs/volunteer_remind.log 2>&1
 */
class VolunteerRemind extends BaseCommand
{
    protected $group       = 'Volunteers';
    protected $name        = 'volunteers:remind';
    protected $description = 'Email reminder to volunteers whose shift is N days away (default: 2).';
    protected $usage       = 'volunteers:remind [--days <n>] [--parish <id>] [--dry-run]';
    protected $options     = [
        '--days'    => 'Days ahead to look for shifts (default: 2).',
        '--parish'  => 'Scope reminders to a single parish_id.',
        '--dry-run' => 'List who would receive an email without actually sending.',
    ];

    public function run(array $params): void
    {
        $daysOption   = CLI::getOption('days');
        $parishOption = CLI::getOption('parish');
        $isDryRun     = CLI::getOption('dry-run') !== null;

        $days     = ($daysOption !== null && $daysOption !== false) ? max(0, (int) $daysOption) : 2;
        $parishId = ($parishOption !== null && $parishOption !== false) ? (int) $parishOption : null;

        $targetDate = date('Y-m-d', strtotime("+{$days} days"));

        CLI::write("volunteers:remind — shifts on {$targetDate} (today + {$days} day(s))", 'cyan');

        if ($isDryRun) {
            CLI::write('[DRY-RUN] No emails will be sent.', 'yellow');
        }
        if ($parishId !== null) {
            CLI::write("Scoped to parish_id = {$parishId}", 'cyan');
        }

        /** @var VolunteerScheduleModel $schedModel */
        $schedModel = model(VolunteerScheduleModel::class);
        $rows       = $schedModel->getSchedulesDueForReminder($targetDate, $parishId);

        if (empty($rows)) {
            CLI::write('No non-cancelled shifts found for that date.', 'green');
            return;
        }

        CLI::write('Found ' . count($rows) . ' shift(s) to notify.', 'cyan');

        $counters  = ['sent' => 0, 'skipped' => 0, 'failed' => 0];
        $tableRows = [];

        foreach ($rows as $row) {
            $toEmail   = trim((string) ($row['email'] ?? ''));
            $firstName = trim((string) ($row['first_name'] ?? ''));
            $lastName  = trim((string) ($row['last_name'] ?? ''));
            $fullName  = trim("{$firstName} {$lastName}");
            $team      = (string) ($row['team_name'] ?? '—');
            $parish    = (string) ($row['parish_name'] ?? '—');
            $slot      = (string) ($row['time_slot'] ?? '');
            $status    = (string) ($row['status'] ?? 'scheduled');

            if ($toEmail === '') {
                $tableRows[] = [$fullName, $team, $parish, 'skipped — no email'];
                $counters['skipped']++;
                continue;
            }

            if ($isDryRun) {
                $tableRows[] = [$fullName, $team, $parish, "dry-run → {$toEmail}"];
                $counters['skipped']++;
                continue;
            }

            [$ok, $error] = $this->sendReminder(
                $toEmail,
                $firstName,
                $team,
                $parish,
                $targetDate,
                $slot,
                $status,
                $days
            );

            if ($ok) {
                $tableRows[] = [$fullName, $team, $parish, "sent → {$toEmail}"];
                $counters['sent']++;
            } else {
                $tableRows[] = [$fullName, $team, $parish, "failed — {$error}"];
                $counters['failed']++;
                log_message('error', '[volunteers:remind] user_id={id} team={team} email={em} error={err}', [
                    'id'   => (int) ($row['user_id'] ?? 0),
                    'team' => $team,
                    'em'   => $toEmail,
                    'err'  => $error,
                ]);
            }
        }

        if (!empty($tableRows)) {
            CLI::table($tableRows, ['Volunteer', 'Team', 'Parish', 'Result']);
        }

        CLI::newLine();
        $color = $counters['failed'] > 0 ? 'red' : 'green';
        CLI::write(
            sprintf('Complete — sent: %d | failed: %d | skipped: %d', $counters['sent'], $counters['failed'], $counters['skipped']),
            $color
        );
    }

    /**
     * Build and send the reminder email.
     *
     * @return array{0: bool, 1: string}  [success, errorMessage]
     */
    private function sendReminder(
        string $toEmail,
        string $firstName,
        string $teamName,
        string $parishName,
        string $shiftDate,
        string $timeSlot,
        string $status,
        int    $daysAhead
    ): array {
        $formattedDate = date('l, F j, Y', strtotime($shiftDate));
        $timeLabel     = $timeSlot !== '' ? " ({$timeSlot})" : '';
        $greeting      = $firstName !== '' ? "Hi {$firstName}," : 'Hello,';

        $subject = "Reminder: Volunteer shift on {$formattedDate} — {$teamName}";

        $bodyText = <<<TEXT
{$greeting}

This is a friendly reminder that you are scheduled as a volunteer with the {$teamName} team at {$parishName} on {$formattedDate}{$timeLabel}.

Your current status: {$status}

If you are unable to serve, please contact your team leader as soon as possible so a replacement can be arranged.

Thank you for your service!

— {$parishName}
TEXT;

        $bodyHtml = '<p>' . nl2br(esc($bodyText)) . '</p>';

        try {
            $mailer = service('email');
            $mailer->setTo($toEmail);
            $mailer->setSubject($subject);
            $mailer->setMessage($bodyHtml);
            $mailer->setAltMessage($bodyText);
            $sent = $mailer->send(false);

            if (!$sent) {
                return [false, strip_tags((string) $mailer->printDebugger(['headers']))];
            }

            return [true, ''];
        } catch (\Throwable $e) {
            return [false, $e->getMessage()];
        }
    }
}
