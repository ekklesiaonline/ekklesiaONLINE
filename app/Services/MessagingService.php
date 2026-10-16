<?php

namespace App\Services;

use App\Libraries\EmailFailureClassifier;
use App\Models\SmsQueueModel;
use App\Services\EmailScopeService;

/**
 * MessagingService
 *
 * Unified façade for outbound SMS, email, and queued SMS dispatch.
 *
 * ─────────────────────────────────────────────────────────────────────
 * SMS — delegates to SmsService which supports Twilio and a log-only stub.
 *
 * Required .env keys (Twilio):
 *   sms.provider     = twilio
 *   sms.twilio_sid   = ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
 *   sms.twilio_token = xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
 *   sms.twilio_from  = +15005550006
 *
 * ─────────────────────────────────────────────────────────────────────
 * Email — uses CodeIgniter's built-in Email service.
 *
 * Configure in app/Config/Email.php or .env:
 *   email.protocol  = smtp
 *   email.SMTPHost  = smtp.mailgun.org
 *   email.SMTPUser  = postmaster@mg.example.com
 *   email.SMTPPass  = your-password
 *   email.SMTPPort  = 587
 *   email.fromEmail = no-reply@example.com
 *   email.fromName  = ekklesiaONLINE
 *
 * ─────────────────────────────────────────────────────────────────────
 * Queued SMS — stores in the `sms_queue` table for background processing
 * via `php spark sms:process` (suitable for rate-limited providers).
 */
class MessagingService
{
    private SmsService    $sms;
    private SmsQueueModel $queueModel;

    public function __construct()
    {
        $this->sms        = new SmsService();
        $this->queueModel = model(SmsQueueModel::class);
    }

    // ------------------------------------------------------------------
    // SMS — immediate dispatch
    // ------------------------------------------------------------------

    /**
     * Send an SMS immediately.
     *
     * @param  string      $to    Destination phone (any common format; normalised internally)
     * @param  string      $body  Up to 1600 characters (multi-part if > 160)
     * @param  string|null $from  Override sender number; null = use env default
     * @return array{success: bool, message_id: string, error: string}
     */
    public function sendSms(string $to, string $body, ?string $from = null): array
    {
        if (trim($to) === '') {
            return ['success' => false, 'message_id' => '', 'error' => 'Recipient phone number is required.'];
        }

        if (trim($body) === '') {
            return ['success' => false, 'message_id' => '', 'error' => 'Message body cannot be empty.'];
        }

        return $this->sms->send($to, $body, $from);
    }

    // ------------------------------------------------------------------
    // Email — immediate dispatch
    // ------------------------------------------------------------------

    /**
     * Send an HTML email via the CI4 email service.
     *
     * @param  string|string[] $to       One or more recipient addresses
     * @param  string          $subject  Email subject line
     * @param  string          $htmlBody Full HTML message body
     * @param  string|null     $textBody Plain-text alternative (auto-stripped if null)
     * @return array{success: bool, status: string, error: string}
     */
    public function sendEmail(
        string|array $to,
        string       $subject,
        string       $htmlBody,
        ?string      $textBody = null,
        array        $context = []
    ): array {
        if (empty($to)) {
            return ['success' => false, 'status' => 'failed', 'error' => 'Recipient address is required.'];
        }

        if (trim($subject) === '') {
            return ['success' => false, 'status' => 'failed', 'error' => 'Email subject is required.'];
        }

        if (trim($htmlBody) === '') {
            return ['success' => false, 'status' => 'failed', 'error' => 'Email body cannot be empty.'];
        }

        try {
            $scopeService = new EmailScopeService();
            $mailer = $scopeService->mailer($context, ['overrides' => ['mailType' => 'html']]);

            // Support both single and multiple recipients
            if (is_array($to)) {
                foreach ($to as $address) {
                    $mailer->setTo($address);
                }
            } else {
                $mailer->setTo($to);
            }

            $mailer->setSubject($subject);
            $mailer->setMessage($htmlBody);

            if ($textBody !== null) {
                $mailer->setAltMessage($textBody);
            }

            $sent = $mailer->send(false);

            if (!$sent) {
                $code = EmailFailureClassifier::classify((string) $mailer->printDebugger(['headers']));
                log_message('warning', '[MessagingService] email send failed; code={code}; parish_id={parishId}', [
                    'code'     => $code,
                    'parishId' => (int) ($context['parish_id'] ?? 0),
                ]);

                return [
                    'success' => false,
                    'status'  => 'failed',
                    'error'   => $code,
                ];
            }

            return ['success' => true, 'status' => 'sent', 'error' => ''];
        } catch (\Throwable $e) {
            log_message('error', '[MessagingService] email exception: {msg}', ['msg' => $e->getMessage()]);

            return [
                'success' => false,
                'status'  => 'failed',
                'error'   => $e->getMessage(),
            ];
        }
    }

    // ------------------------------------------------------------------
    // SMS — queued dispatch
    // ------------------------------------------------------------------

    /**
     * Store an SMS in the queue for background processing.
     *
     * Use this instead of sendSms() when:
     *  - You need to rate-limit traffic to your SMS gateway.
     *  - A message should be sent at a specific future time.
     *  - You want automatic retry on transient failures.
     *
     * @param  string      $to          Destination phone number
     * @param  string      $body        Message body
     * @param  string      $scheduledAt ISO-8601 datetime ("2026-04-15 09:00:00")
     * @param  string|null $from        Override sender number; null = env default
     * @param  int|null    $parishId    Scope for reporting
     * @param  int         $maxAttempts Max delivery attempts before marking failed
     * @return array{success: bool, queue_id: int, error: string}
     */
    public function queueSms(
        string  $to,
        string  $body,
        string  $scheduledAt,
        ?string $from        = null,
        ?int    $parishId    = null,
        int     $maxAttempts = 3
    ): array {
        if (trim($to) === '') {
            return ['success' => false, 'queue_id' => 0, 'error' => 'Recipient phone number is required.'];
        }

        if (trim($body) === '') {
            return ['success' => false, 'queue_id' => 0, 'error' => 'Message body cannot be empty.'];
        }

        if (!$this->isValidDatetime($scheduledAt)) {
            return ['success' => false, 'queue_id' => 0, 'error' => 'scheduledAt must be a valid datetime (Y-m-d H:i:s).'];
        }

        $data = [
            'to_number'    => $to,
            'body'         => $body,
            'from_number'  => ($from !== '' ? $from : null),
            'parish_id'    => $parishId,
            'scheduled_at' => $scheduledAt,
            'max_attempts' => max(1, min(10, $maxAttempts)),
            'status'       => 'pending',
            'attempts'     => 0,
        ];

        $insertId = $this->queueModel->insert($data, true);

        if ($insertId === false) {
            $errors = implode(', ', $this->queueModel->errors());
            return ['success' => false, 'queue_id' => 0, 'error' => 'DB insert failed: ' . $errors];
        }

        return ['success' => true, 'queue_id' => (int) $insertId, 'error' => ''];
    }

    // ------------------------------------------------------------------
    // Convenience helpers
    // ------------------------------------------------------------------

    /**
     * Schedule an SMS for immediate dispatch (scheduledAt = now).
     * Useful when you want the resilience of the queue but no deliberate delay.
     *
     * @return array{success: bool, queue_id: int, error: string}
     */
    public function queueSmsNow(string $to, string $body, ?string $from = null, ?int $parishId = null): array
    {
        return $this->queueSms($to, $body, date('Y-m-d H:i:s'), $from, $parishId);
    }

    /**
     * Return the count of messages currently waiting to be sent.
     */
    public function pendingQueueCount(?int $parishId = null): int
    {
        return $this->queueModel->countPending($parishId);
    }

    // ------------------------------------------------------------------
    // Private
    // ------------------------------------------------------------------

    private function isValidDatetime(string $value): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d H:i:s', $value);
        return $d !== false && $d->format('Y-m-d H:i:s') === $value;
    }
}
