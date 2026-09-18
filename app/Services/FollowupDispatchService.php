<?php

namespace App\Services;

use App\Libraries\DataEncryption;
use App\Models\CallTaskModel;
use App\Models\SmsQueueModel;

/**
 * FollowupDispatchService
 *
 * Handles channel-specific dispatch for a single followup log entry.
 *
 * Usage (from the spark command or a controller):
 * -----------------------------------------------
 * $service = new FollowupDispatchService();
 * $result  = $service->dispatch($logRow, $template, $guest);
 * // $result: ['success' => bool, 'metadata' => array, 'error' => string]
 *
 * Channel routing:
 *   text  → SmsService::send()
 *   email → CI4 email service
 *   call  → inserts a row in call_tasks
 *   mail  → generates a PDF letter and stores it in writable/mail_queue/
 *   gift  → marks the log with requires_manual_gift flag
 */
class FollowupDispatchService
{
    /** Tokens replaced in template bodies */
    private const VARIABLE_MAP = [
        '{first_name}',
        '{last_name}',
        '{parish_name}',
        '{visit_date}',
        '{clergy_name}',
        '{phone}',
    ];

    private SmsService   $sms;
    private CallTaskModel $callTaskModel;
    private SmsQueueModel $smsQueueModel;
    private GuestDirectoryService $guestDirectory;

    public function __construct()
    {
        $this->sms            = new SmsService();
        $this->callTaskModel  = model('CallTaskModel');
        $this->smsQueueModel  = model('SmsQueueModel');
        $this->guestDirectory = new GuestDirectoryService();
    }

    // ------------------------------------------------------------------
    // Public
    // ------------------------------------------------------------------

    /**
     * Dispatch a single followup log entry.
     *
     * @param  array      $logRow   Row from guest_followup_log joined with guests + followup_action
     * @param  array|null $template Row from followup_template (may be null for call/gift/mail)
     * @param  array      $guest    Full decrypted guest row (email, phone already decrypted)
     * @return array{success: bool, metadata: array, error: string}
     */
    public function dispatch(array $logRow, ?array $template, array $guest): array
    {
        $channel   = (string) ($logRow['channel'] ?? '');
        $parishId  = (int)    ($guest['parish_id'] ?? 0);
        $logId     = (int)    ($logRow['id'] ?? 0);

        $parish = $this->loadParish($parishId);
        $vars   = $this->buildVarMap($guest, $parish);

        // Opt-out guard: skip silently if guest has opted out of this channel.
        if ($this->guestDirectory->isOptedOut($guest, $channel)) {
            return [
                'success'  => false,
                'metadata' => ['channel' => $channel, 'skipped' => true, 'reason' => 'opted_out'],
                'error'    => "Guest has opted out of channel: {$channel}",
            ];
        }

        return match ($channel) {
            'text'  => $this->dispatchText($logRow, $template, $guest, $vars),
            'email' => $this->dispatchEmail($logRow, $template, $guest, $vars),
            'call'  => $this->dispatchCall($logRow, $guest, $parishId),
            'mail'  => $this->dispatchMail($logRow, $template, $guest, $vars, $logId),
            'gift'  => $this->dispatchGift($logRow, $guest),
            default => ['success' => false, 'metadata' => [], 'error' => "Unknown channel: {$channel}"],
        };
    }

    /**
     * Replace {tokens} in a template string using guest + parish data.
     */
    public function applyVars(string $text, array $vars): string
    {
        return str_replace(array_keys($vars), array_values($vars), $text);
    }

    // ------------------------------------------------------------------
    // Channel handlers
    // ------------------------------------------------------------------

    private function dispatchText(array $logRow, ?array $template, array $guest, array $vars): array
    {
        if ($template === null) {
            return ['success' => false, 'metadata' => [], 'error' => 'No template assigned for text action.'];
        }

        $phone = $this->decryptField($guest['phone'] ?? '');
        if ($phone === '' || $phone === null) {
            return ['success' => false, 'metadata' => [], 'error' => 'Guest has no phone number.'];
        }

        $body = $this->applyVars((string) $template['body_text'], $vars);

        // Route through sms_queue instead of sending directly so the
        // sms:process cron job can rate-limit outbound traffic.
        $queueId = $this->smsQueueModel->insert([
            'to_number'    => $phone,
            'body'         => $body,
            'parish_id'    => (int) ($guest['parish_id'] ?? 0),
            'status'       => 'pending',
            'attempts'     => 0,
            'max_attempts' => 3,
            'scheduled_at' => date('Y-m-d H:i:s'),
        ], true);

        if ($queueId === false) {
            return ['success' => false, 'metadata' => ['channel' => 'text'], 'error' => 'Failed to enqueue SMS.'];
        }

        return [
            'success'  => true,
            'metadata' => ['channel' => 'text', 'queued' => true, 'sms_queue_id' => (int) $queueId],
            'error'    => '',
        ];
    }

    private function dispatchEmail(array $logRow, ?array $template, array $guest, array $vars): array
    {
        if ($template === null) {
            return ['success' => false, 'metadata' => [], 'error' => 'No template assigned for email action.'];
        }

        $email = $this->decryptField($guest['email'] ?? '');
        if ($email === '' || $email === null) {
            return ['success' => false, 'metadata' => [], 'error' => 'Guest has no email address.'];
        }

        $subject  = $this->applyVars((string) ($template['subject'] ?? 'A message for you'), $vars);
        $bodyText = $this->applyVars((string) $template['body_text'], $vars);
        $bodyHtml = !empty($template['body_html'])
            ? $this->applyVars((string) $template['body_html'], $vars)
            : nl2br(esc($bodyText));

        try {
            $mailer = service('email');
            $mailer->setTo($email);
            $mailer->setSubject($subject);
            $mailer->setMessage($bodyHtml);
            $mailer->setAltMessage($bodyText);
            $sent = $mailer->send(false);

            if (!$sent) {
                return [
                    'success'  => false,
                    'metadata' => ['channel' => 'email'],
                    'error'    => (string) $mailer->printDebugger(['headers']),
                ];
            }

            return [
                'success'  => true,
                'metadata' => ['channel' => 'email', 'to' => $email],
                'error'    => '',
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'metadata' => ['channel' => 'email'], 'error' => $e->getMessage()];
        }
    }

    private function dispatchCall(array $logRow, array $guest, int $parishId): array
    {
        $phone = $this->decryptField($guest['phone'] ?? '');

        $personId = (int) ($guest['person_id'] ?? $logRow['person_id'] ?? 0) ?: null;

        $taskData = [
            'parish_id'      => $parishId,
            'person_id'      => $personId,
            'log_id'         => (int) ($logRow['id'] ?? 0) ?: null,
            'assigned_to'    => (int) ($logRow['assigned_to'] ?? 0) ?: null,
            'phone'          => $phone ?: null,
            'notes'          => 'Auto-generated from followup sequence.',
            'status'         => 'pending',
            'scheduled_date' => (string) ($logRow['scheduled_date'] ?? date('Y-m-d')),
        ];

        $inserted = $this->callTaskModel->insert($taskData, true);

        if ($inserted === false) {
            $errors = implode(', ', $this->callTaskModel->errors());
            return ['success' => false, 'metadata' => [], 'error' => 'Failed to create call task: ' . $errors];
        }

        return [
            'success'  => true,
            'metadata' => ['channel' => 'call', 'call_task_id' => (int) $inserted],
            'error'    => '',
        ];
    }

    private function dispatchMail(
        array  $logRow,
        ?array $template,
        array  $guest,
        array  $vars,
        int    $logId
    ): array {
        if ($template === null) {
            return ['success' => false, 'metadata' => [], 'error' => 'No template assigned for mail action.'];
        }

        $bodyHtml = !empty($template['body_html'])
            ? $this->applyVars((string) $template['body_html'], $vars)
            : '<pre>' . esc($this->applyVars((string) $template['body_text'], $vars)) . '</pre>';

        $queueDir = WRITEPATH . 'mail_queue';
        if (!is_dir($queueDir)) {
            mkdir($queueDir, 0755, true);
        }

        $filename = 'letter_log' . $logId . '_' . date('Ymd_His') . '.pdf';
        $filePath = $queueDir . DIRECTORY_SEPARATOR . $filename;

        try {
            if (!class_exists(\Dompdf\Dompdf::class)) {
                // Dompdf not installed: store HTML for later rendering
                $htmlFile = $queueDir . DIRECTORY_SEPARATOR . 'letter_log' . $logId . '_' . date('Ymd_His') . '.html';
                file_put_contents($htmlFile, $bodyHtml);

                return [
                    'success'  => true,
                    'metadata' => ['channel' => 'mail', 'queued_html' => basename($htmlFile)],
                    'error'    => '',
                ];
            }

            $pdf = new \Dompdf\Dompdf();
            $pdf->loadHtml($bodyHtml);
            $pdf->setPaper('letter', 'portrait');
            $pdf->render();
            file_put_contents($filePath, $pdf->output());

            return [
                'success'  => true,
                'metadata' => ['channel' => 'mail', 'queued_pdf' => $filename],
                'error'    => '',
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'metadata' => ['channel' => 'mail'], 'error' => $e->getMessage()];
        }
    }

    private function dispatchGift(array $logRow, array $guest): array
    {
        // Gifts require manual dispatch; we mark the log with metadata and
        // let the admin see it as a pending task in the follow-up timeline.
        return [
            'success'  => true,
            'metadata' => [
                'channel'           => 'gift',
                'requires_manual'   => true,
                'guest_name'        => trim(($guest['first_name'] ?? '') . ' ' . ($guest['last_name'] ?? '')),
            ],
            'error' => '',
        ];
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Build the token-to-value replacement map for a guest + parish combo.
     */
    private function buildVarMap(array $guest, ?array $parish): array
    {
        return [
            '{first_name}'  => esc((string) ($guest['first_name'] ?? '')),
            '{last_name}'   => esc((string) ($guest['last_name'] ?? '')),
            '{parish_name}' => esc((string) ($parish['name'] ?? '')),
            '{visit_date}'  => esc((string) ($guest['visit_date'] ?? '')),
            '{clergy_name}' => '',                // can be populated from parish config in future
            '{phone}'       => esc((string) $this->decryptField($guest['phone'] ?? '')),
        ];
    }

    /**
     * Load parish row by ID. Returns null on miss.
     */
    private function loadParish(int $parishId): ?array
    {
        if ($parishId <= 0) {
            return null;
        }

        return db_connect()->table('parish')->where('id', $parishId)->get()->getRowArray() ?: null;
    }

    /**
     * Safely decrypt a field that may be encrypted or plain-text.
     */
    private function decryptField(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return (string) (DataEncryption::isEncrypted($value)
            ? (DataEncryption::decrypt($value) ?? '')
            : $value);
    }
}
