<?php

namespace App\Services;

/**
 * SmsService — thin wrapper for outbound SMS dispatch.
 *
 * Integrate a real provider (e.g., Twilio) by:
 *   1. Add credentials to `.env`:
 *        sms.provider = twilio
 *        sms.twilio_sid = ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
 *        sms.twilio_token = xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
 *        sms.twilio_from = +15005550006
 *   2. Install the SDK: `composer require twilio/sdk`
 *   3. Replace the stub body below with the real API call.
 *
 * The send() method always returns an array so callers can inspect success/error
 * without catching exceptions at every call site.
 */
class SmsService
{
    private string $provider;
    private string $from;

    public function __construct()
    {
        $this->provider = (string) env('sms.provider', 'log_only');
        $this->from     = (string) env('sms.twilio_from', '');
    }

    /**
     * Send an SMS message.
     *
     * @param  string      $to    Destination phone number in E.164 format (+1XXXXXXXXXX)
     * @param  string      $body  Plain-text message body
     * @param  string|null $from  Override sender number (null = use env default)
     * @return array{success: bool, message_id: string, error: string}
     */
    public function send(string $to, string $body, ?string $from = null): array
    {
        $to = $this->normalisePhone($to);

        if ($to === '') {
            return ['success' => false, 'message_id' => '', 'error' => 'Phone number is empty or invalid.'];
        }

        if ($this->provider === 'twilio') {
            return $this->dispatchTwilio($to, $body, $from);
        }

        // Default: log-only stub (safe for development / staging without credentials)
        log_message('info', '[SmsService] LOG_ONLY — to={to} from={from} body={body}', [
            'to'   => $to,
            'from' => $from ?? $this->from,
            'body' => mb_substr($body, 0, 160),
        ]);

        return ['success' => true, 'message_id' => 'log_only_' . uniqid(), 'error' => ''];
    }

    // ------------------------------------------------------------------
    // Private
    // ------------------------------------------------------------------

    /**
     * Dispatch via Twilio REST API.
     * Requires `twilio/sdk` Composer package and credentials in .env.
     */
    private function dispatchTwilio(string $to, string $body, ?string $fromOverride = null): array
    {
        $sid   = (string) env('sms.twilio_sid',   '');
        $token = (string) env('sms.twilio_token', '');
        $from  = $fromOverride !== null && $fromOverride !== '' ? $fromOverride : $this->from;

        if ($sid === '' || $token === '' || $from === '') {
            return [
                'success'    => false,
                'message_id' => '',
                'error'      => 'Twilio credentials not configured (sms.twilio_sid / sms.twilio_token / sms.twilio_from).',
            ];
        }

        // Guard: only call if the SDK is present to avoid fatal autoload error.
        // Use a string class name so static analysers do not flag an undefined type.
        $twilioClientClass = 'Twilio\Rest\Client';
        if (!class_exists($twilioClientClass)) {
            return [
                'success'    => false,
                'message_id' => '',
                'error'      => 'Twilio SDK not installed. Run: composer require twilio/sdk',
            ];
        }

        try {
            /** @var object $client */
            $client  = new $twilioClientClass($sid, $token);
            $message = $client->messages->create($to, [
                'from' => $from,
                'body' => $body,
            ]);

            return ['success' => true, 'message_id' => (string) $message->sid, 'error' => ''];
        } catch (\Throwable $e) {
            return ['success' => false, 'message_id' => '', 'error' => $e->getMessage()];
        }
    }

    /**
     * Strip non-digit chars and add '+' prefix if missing, so callers can
     * pass numbers in various formats.
     */
    private function normalisePhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if ($digits === null || $digits === '') {
            return '';
        }

        // Add country code if not present (assumes US/DO +1 — adjust if needed)
        if (strlen($digits) === 10) {
            $digits = '1' . $digits;
        }

        return '+' . $digits;
    }
}
