<?php

namespace App\Libraries\Email;

use App\Libraries\EmailFailureClassifier;
use App\Services\GmailOAuthService;
use CodeIgniter\Email\Email;

/**
 * GmailApiEmail
 *
 * CI4 Email transport that submits the fully assembled RFC 822 message via
 * the Gmail API (users.messages.send) instead of SMTP. Reuses CI4's
 * buildHeaders()/buildMessage() assembly — headerStr + finalBody is the
 * exact payload sendWithSmtp() would place on the wire (minus SMTP
 * dot-unstuffing, which is transport-specific and not needed here).
 *
 * Security notes:
 * - The 'gmail_api' protocol must stay inside $protocols: getProtocol()
 *   silently coerces unknown protocols to 'mail' (sendmail), which would
 *   leak the message through a different transport.
 * - sendWithGmail_api() never logs or returns tokens, response bodies, or
 *   recipient addresses — failures surface only as sanitized GMAIL_*
 *   markers through setErrorMessage() so EmailFailureClassifier and
 *   printDebugger() keep working.
 */
class GmailApiEmail extends Email
{
    /**
     * Extend the parent whitelist so 'gmail_api' is a real protocol instead
     * of being coerced to 'mail' (sendmail) by getProtocol()/setProtocol().
     *
     * @var list<string>
     */
    protected $protocols = [
        'mail',
        'sendmail',
        'smtp',
        'gmail_api',
    ];

    private ?GmailOAuthService $gmailOAuth = null;
    private string $gmailTier = '';
    private ?int $gmailScopeId = null;

    /**
     * Attach the OAuth service + resolved tier used to fetch credentials at
     * send time (tokens are refreshed lazily — never persisted here).
     */
    public function setGmailTransport(GmailOAuthService $oauth, string $tier, ?int $scopeId): self
    {
        $this->gmailOAuth  = $oauth;
        $this->gmailTier   = $tier;
        $this->gmailScopeId = $scopeId;

        return $this;
    }

    /**
     * Gmail API send transport. Dispatched by spoolEmail() as
     * 'sendWith' . ucfirst('gmail_api').
     */
    protected function sendWithGmail_api()
    {
        if ($this->gmailOAuth === null) {
            $this->setErrorMessage('GMAIL_GRANT_INVALID');

            return false;
        }

        // The Gmail API sends as the authenticated account ('me'); a From
        // header that does not match the connected mailbox is rejected by
        // Google (or worse, misdelivers). Enforce alignment up front. The
        // address is read from the assembled From header — setFrom() stores
        // it there, not in $this->fromEmail (which is only the config
        // fallback used by send()).
        $accountEmail = $this->gmailOAuth->accountEmail($this->gmailTier, $this->gmailScopeId);
        if ($accountEmail !== null && $accountEmail !== '') {
            $fromHeader = (string) ($this->headers['From'] ?? '');
            $fromEmail  = '';
            if (preg_match('/<([^<>\s]+@[^<>\s]+)>/', $fromHeader, $m) === 1) {
                $fromEmail = $m[1];
            } elseif (filter_var(trim($fromHeader), FILTER_VALIDATE_EMAIL)) {
                $fromEmail = trim($fromHeader);
            }

            if ($fromEmail === '' || strcasecmp($fromEmail, $accountEmail) !== 0) {
                $this->setErrorMessage('GMAIL_SENDER_NOT_PERMITTED');

                return false;
            }
        }

        $raw = $this->headerStr . $this->finalBody;
        $encoded = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');

        try {
            $this->gmailOAuth->sendRawMessage($this->gmailTier, $this->gmailScopeId, $encoded);
        } catch (GmailApiException $e) {
            $this->setErrorMessage($e->failureCode);

            return false;
        } catch (\Throwable $e) {
            $this->setErrorMessage(EmailFailureClassifier::classifyGoogleApiError($e));

            return false;
        }

        return true;
    }
}
