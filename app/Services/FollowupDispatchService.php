<?php

namespace App\Services;

use App\Libraries\DataEncryption;
use App\Libraries\EmailFailureClassifier;
use App\Models\CallTaskModel;
use App\Models\SmsQueueModel;
use App\Services\EmailScopeService;

/**
 * FollowupDispatchService
 *
 * Handles channel-specific dispatch for a single followup log entry.
 *
 * Usage (from the spark command or a controller):
 * -----------------------------------------------
 * $service = new FollowupDispatchService();
 * $result  = $service->dispatch($logRow, $template, $visitor);
 * // $result: ['success' => bool, 'metadata' => array, 'error' => string]
 *
 * Channel routing:
 *   text  → SmsService::send()
 *   email → CI4 email service
 *   call   → inserts a row in call_task (task_type = 'call')
 *   manual → inserts an assigned row in call_task (task_type = 'manual')
 *   mail   → generates a PDF letter and stores it in writable/mail_queue/
 *   gift   → marks the log with requires_manual_gift flag
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
    private VisitorDirectoryService $visitorDirectory;

    public function __construct()
    {
        $this->sms            = new SmsService();
        $this->callTaskModel  = model('CallTaskModel');
        $this->smsQueueModel  = model('SmsQueueModel');
        $this->visitorDirectory = new VisitorDirectoryService();
    }

    // ------------------------------------------------------------------
    // Public
    // ------------------------------------------------------------------

    /**
     * Dispatch a single followup log entry.
     *
     * @param  array      $logRow   Row from visitor_followup_log joined with visitors + followup_action
     * @param  array|null $template Row from followup_template (may be null for call/gift/mail)
     * @param  array      $visitor    Full decrypted visitor row (email, phone already decrypted)
     * @return array{success: bool, metadata: array, error: string}
     */
    public function dispatch(array $logRow, ?array $template, array $visitor): array
    {
        $channel   = (string) ($logRow['channel'] ?? '');
        $parishId  = (int)    ($visitor['parish_id'] ?? 0);
        $logId     = (int)    ($logRow['id'] ?? 0);

        $parish = $this->loadParish($parishId);
        $vars   = $this->buildVarMap($visitor, $parish);

        // Opt-out guard: skip silently if visitor has opted out of this channel.
        if ($this->visitorDirectory->isOptedOut($visitor, $channel)) {
            return [
                'success'  => false,
                'metadata' => ['channel' => $channel, 'skipped' => true, 'reason' => 'opted_out'],
                'error'    => "Visitor has opted out of channel: {$channel}",
            ];
        }

        return match ($channel) {
            'text'  => $this->dispatchText($logRow, $template, $visitor, $vars),
            'email' => $this->dispatchEmail($logRow, $template, $visitor, $vars, $parishId),
            'call'   => $this->dispatchCall($logRow, $visitor, $parishId),
            'manual' => $this->dispatchManual($logRow, $visitor, $vars, $parishId),
            'mail'   => $this->dispatchMail($logRow, $template, $visitor, $vars, $logId),
            'gift'  => $this->dispatchGift($logRow, $visitor),
            default => ['success' => false, 'metadata' => [], 'error' => "Unknown channel: {$channel}"],
        };
    }

    /**
     * Replace {tokens} in a template string using visitor + parish data.
     */
    public function applyVars(string $text, array $vars): string
    {
        return str_replace(array_keys($vars), array_values($vars), $text);
    }

    // ------------------------------------------------------------------
    // Channel handlers
    // ------------------------------------------------------------------

    private function dispatchText(array $logRow, ?array $template, array $visitor, array $vars): array
    {
        if ($template === null) {
            return ['success' => false, 'metadata' => [], 'error' => 'No template assigned for text action.'];
        }

        $phone = $this->decryptField($visitor['phone'] ?? '');
        if ($phone === '' || $phone === null) {
            return ['success' => false, 'metadata' => [], 'error' => 'Visitor has no phone number.'];
        }

        $body = $this->applyVars((string) $template['body_text'], $vars);

        // Route through sms_queue instead of sending directly so the
        // sms:process cron job can rate-limit outbound traffic.
        $queueId = $this->smsQueueModel->insert([
            'to_number'    => $phone,
            'body'         => $body,
            'parish_id'    => (int) ($visitor['parish_id'] ?? 0),
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

    private function dispatchEmail(array $logRow, ?array $template, array $visitor, array $vars, int $parishId): array
    {
        if ($template === null) {
            return ['success' => false, 'metadata' => [], 'error' => 'No template assigned for email action.'];
        }

        $email = $this->decryptField($visitor['email'] ?? '');
        if ($email === '' || $email === null) {
            return ['success' => false, 'metadata' => [], 'error' => 'Visitor has no email address.'];
        }

        $subject  = $this->applyVars((string) ($template['subject'] ?? 'A message for you'), $vars);
        $bodyText = $this->applyVars((string) $template['body_text'], $vars);
        $bodyHtml = !empty($template['body_html'])
            ? $this->applyVars((string) $template['body_html'], $vars)
            : nl2br(esc($bodyText));

        try {
            $mailer = (new EmailScopeService())->mailer(['parish_id' => $parishId], ['overrides' => ['mailType' => 'html']]);
            $mailer->setTo($email);
            $mailer->setSubject($subject);
            $mailer->setMessage($bodyHtml);
            $mailer->setAltMessage($bodyText);
            $sent = $mailer->send(false);

            if (!$sent) {
                return [
                    'success'  => false,
                    'metadata' => ['channel' => 'email'],
                    'error'    => EmailFailureClassifier::classify((string) $mailer->printDebugger(['headers'])),
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

    private function dispatchCall(array $logRow, array $visitor, int $parishId): array
    {
        $phone = $this->decryptField($visitor['phone'] ?? '');

        $personId = (int) ($visitor['person_id'] ?? $logRow['person_id'] ?? 0) ?: null;

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

    /**
     * Manual channel: create an assigned task in call_task
     * (task_type = 'manual'). Nothing is sent to the visitor — a parish
     * leader performs the action (e.g. a social-media invitation) and
     * completes the task in the queue.
     */
    private function dispatchManual(array $logRow, array $visitor, array $vars, int $parishId): array
    {
        $personId   = (int) ($visitor['person_id'] ?? $logRow['person_id'] ?? 0) ?: null;
        $assignedTo = (int) ($logRow['assigned_to'] ?? 0) ?: null;

        $instructions = trim((string) ($logRow['instructions'] ?? ''));
        $title        = $instructions !== '' ? $this->applyVars($instructions, $vars) : null;

        $taskData = [
            'parish_id'      => $parishId,
            'person_id'      => $personId,
            'log_id'         => (int) ($logRow['id'] ?? 0) ?: null,
            'assigned_to'    => $assignedTo,
            'task_type'      => 'manual',
            'title'          => $title,
            'phone'          => null,
            'notes'          => 'Manual task from followup sequence.',
            'status'         => 'pending',
            'scheduled_date' => (string) ($logRow['scheduled_date'] ?? date('Y-m-d')),
        ];

        $inserted = $this->callTaskModel->insert($taskData, true);

        if ($inserted === false) {
            $errors = implode(', ', $this->callTaskModel->errors());
            return ['success' => false, 'metadata' => ['channel' => 'manual'], 'error' => 'Failed to create manual task: ' . $errors];
        }

        return [
            'success'  => true,
            'metadata' => ['channel' => 'manual', 'call_task_id' => (int) $inserted, 'assigned_to' => $assignedTo],
            'error'    => '',
        ];
    }

    private function dispatchMail(
        array  $logRow,
        ?array $template,
        array  $visitor,
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

    private function dispatchGift(array $logRow, array $visitor): array
    {
        // Gifts require manual dispatch; we mark the log with metadata and
        // let the admin see it as a pending task in the follow-up timeline.
        return [
            'success'  => true,
            'metadata' => [
                'channel'           => 'gift',
                'requires_manual'   => true,
                'visitor_name'        => trim(($visitor['first_name'] ?? '') . ' ' . ($visitor['last_name'] ?? '')),
            ],
            'error' => '',
        ];
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Build the token-to-value replacement map for a visitor + parish combo.
     */
    private function buildVarMap(array $visitor, ?array $parish): array
    {
        return [
            '{first_name}'  => esc((string) ($visitor['first_name'] ?? '')),
            '{last_name}'   => esc((string) ($visitor['last_name'] ?? '')),
            '{parish_name}' => esc((string) ($parish['name'] ?? '')),
            '{visit_date}'  => esc((string) ($visitor['visit_date'] ?? '')),
            '{clergy_name}' => '',                // can be populated from parish config in future
            '{phone}'       => esc((string) $this->decryptField($visitor['phone'] ?? '')),
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
