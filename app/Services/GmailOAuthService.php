<?php

namespace App\Services;

use App\Libraries\DataEncryption;
use App\Libraries\Email\GmailApiException;
use App\Libraries\EmailFailureClassifier;
use Config\Email as EmailConfig;
use Google\Client;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;

/**
 * GmailOAuthService
 *
 * OAuth2 lifecycle for the Gmail API outbound transport:
 *   - builds the Google consent URL with a signed, single-use state payload
 *   - exchanges the auth code, discovers the authorized mailbox address,
 *     and persists ONLY the refresh token (encrypted, enc:v1:) plus the
 *     account email per settings tier
 *   - refreshes access tokens lazily at send time (never persisted)
 *   - revokes and clears grants on disconnect
 *
 * Security invariants:
 *   - refresh tokens are stored exclusively through
 *     ConfigurationService::setSettingForTier() with is_encrypted=1 and a
 *     DataEncryption::encrypt() payload; access tokens never touch disk.
 *   - OAuth client id/secret come from Config\Email (.env only) — never
 *     from db_settings.
 *   - state is HMAC-signed, time-boxed (15 min), and nonce-bound to the
 *     initiating session so it cannot be forged or replayed.
 *   - no token, auth code, or Google response body is ever logged.
 */
class GmailOAuthService
{
    public const TRANSPORT_GMAIL_API = 'gmail_api';
    public const TRANSPORT_SMTP      = 'smtp_password';

    public const SCOPE_GMAIL_SEND = 'https://www.googleapis.com/auth/gmail.send';

    /** openid+email are non-sensitive scopes used only to identify the account. */
    private const OAUTH_SCOPES = [
        'openid',
        'email',
        self::SCOPE_GMAIL_SEND,
    ];

    private const SETTING_TRANSPORT     = 'email.mail_transport';
    private const SETTING_REFRESH_TOKEN = 'email.gmail_refresh_token';
    private const SETTING_ACCOUNT_EMAIL = 'email.gmail_account_email';

    private const STATE_TTL_SECONDS = 900;

    private const VALID_TIERS = ['parish', 'diocese', 'province', 'organisation', 'system'];

    private ConfigurationService $config;
    private EmailConfig $emailConfig;

    /** @var array<string,string> per-request access-token cache */
    private array $tokenCache = [];

    /** @var array<string,?string> per-request account-email cache */
    private array $accountCache = [];

    public function __construct(?ConfigurationService $config = null, ?EmailConfig $emailConfig = null)
    {
        $this->config      = $config ?? new ConfigurationService();
        $this->emailConfig = $emailConfig ?? new EmailConfig();
    }

    /**
     * Whether Google OAuth client credentials are configured (.env only).
     */
    public function isConfigured(): bool
    {
        return $this->emailConfig->googleClientId !== '' && $this->emailConfig->googleClientSecret !== '';
    }

    /**
     * Registered Google redirect endpoint. Must match the authorized
     * redirect URI in the Google Cloud console exactly.
     */
    public function redirectUri(): string
    {
        return site_url('email/oauth/google/callback');
    }

    // ------------------------------------------------------------------
    // OAuth flow
    // ------------------------------------------------------------------

    /**
     * Build the Google consent URL for a settings tier. Stores a single-use
     * nonce in the session and returns the HMAC-signed state payload.
     *
     * @param array{type: string, id: int|null} $scope
     */
    public function connectUrl(array $scope, int $userId): string
    {
        $tier    = (string) ($scope['type'] ?? '');
        $scopeId = isset($scope['id']) ? (int) $scope['id'] : null;
        $this->assertValidScope($tier, $scopeId);

        $nonce = bin2hex(random_bytes(16));
        $state = $this->signState([
            'tier'     => $tier,
            'scope_id' => $scopeId,
            'user_id'  => $userId,
            'nonce'    => $nonce,
            'ts'       => time(),
        ]);

        $this->storeStateNonce($nonce);

        $client = $this->makeClient();
        $client->setState($state);

        return $client->createAuthUrl();
    }

    /**
     * Verify signature + freshness of a state payload, then consume its
     * session nonce so each state works exactly once.
     *
     * @return array{tier: string, scope_id: int|null, user_id: int, nonce: string, ts: int}|null
     */
    public function parseAndConsumeState(string $state): ?array
    {
        $payload = $this->verifyState($state);
        if ($payload === null) {
            return null;
        }

        if (!$this->consumeStateNonce((string) ($payload['nonce'] ?? ''))) {
            return null;
        }

        return $payload;
    }

    /**
     * Signature + freshness check only (no nonce consumption).
     *
     * @return array<string,mixed>|null
     */
    public function verifyState(string $state): ?array
    {
        $parts = explode('.', $state);
        if (count($parts) !== 2) {
            return null;
        }

        [$encoded, $signature] = $parts;
        $expected = hash_hmac('sha256', $encoded, $this->stateKey());
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $json = base64_decode(strtr($encoded, '-_', '+/'), true);
        $payload = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($payload)) {
            return null;
        }

        $required = ['tier', 'scope_id', 'user_id', 'nonce', 'ts'];
        foreach ($required as $key) {
            if (!array_key_exists($key, $payload)) {
                return null;
            }
        }

        $ts = (int) $payload['ts'];
        if ($ts <= 0 || (time() - $ts) > self::STATE_TTL_SECONDS || $ts > time() + 60) {
            return null;
        }

        if (!is_string($payload['nonce']) || $payload['nonce'] === '') {
            return null;
        }

        return $payload;
    }

    /**
     * Exchange the authorization code and persist the grant for the tier
     * carried in the (already verified) state payload.
     *
     * @param array{tier: string, scope_id: int|null, user_id: int} $state
     * @return array{ok: bool, tier: string, scope_id: int|null, email: ?string, error: ?string}
     */
    public function handleCallback(string $code, array $state): array
    {
        $tier    = (string) ($state['tier'] ?? '');
        $scopeId = isset($state['scope_id']) ? (int) $state['scope_id'] : null;
        $result  = ['ok' => false, 'tier' => $tier, 'scope_id' => $scopeId, 'email' => null, 'error' => null];

        try {
            $this->assertValidScope($tier, $scopeId);
        } catch (\Throwable) {
            $result['error'] = 'GMAIL_OAUTH_BAD_STATE';
            return $result;
        }

        if (!$this->isConfigured()) {
            $result['error'] = 'GMAIL_OAUTH_NOT_CONFIGURED';
            return $result;
        }

        $tokens = $this->exchangeCode($code);
        if ($tokens === null) {
            $result['error'] = 'GMAIL_OAUTH_EXCHANGE_FAILED';
            return $result;
        }

        $refreshToken = $tokens['refresh_token'] ?? null;
        if (!is_string($refreshToken) || $refreshToken === '') {
            // prompt=consent should guarantee a refresh token; without one
            // the grant cannot be renewed and must not be stored.
            $result['error'] = 'GMAIL_NO_REFRESH_TOKEN';
            return $result;
        }

        $email = $this->discoverAccountEmail($this->makeClient(), $tokens);
        if ($email === null) {
            $result['error'] = 'GMAIL_ACCOUNT_DISCOVERY_FAILED';
            return $result;
        }

        $this->persistGrant($tier, $scopeId, $refreshToken, $email);

        $result['ok']    = true;
        $result['email'] = $email;

        return $result;
    }

    // ------------------------------------------------------------------
    // Grant storage & token lifecycle
    // ------------------------------------------------------------------

    /**
     * Decrypted refresh token for a tier, or null when absent/undecryptable.
     */
    public function refreshToken(string $tier, ?int $id): ?string
    {
        $value = $this->config->getSettingForTier($tier, $id, self::SETTING_REFRESH_TOKEN, null);

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /**
     * Connected Gmail account address for a tier (plaintext setting).
     */
    public function accountEmail(string $tier, ?int $id): ?string
    {
        $key = $tier . ':' . ($id ?? 0);
        if (array_key_exists($key, $this->accountCache)) {
            return $this->accountCache[$key];
        }

        $value = $this->config->getSettingForTier($tier, $id, self::SETTING_ACCOUNT_EMAIL, null);
        $value = is_string($value) ? trim($value) : '';

        return $this->accountCache[$key] = ($value !== '' ? $value : null);
    }

    /**
     * Whether a usable grant (refresh token + account email) exists at the tier.
     */
    public function hasGrant(string $tier, ?int $id): bool
    {
        return $this->refreshToken($tier, $id) !== null && $this->accountEmail($tier, $id) !== null;
    }

    /**
     * Transport selector value stored at exactly this tier.
     */
    public function transportAt(string $tier, ?int $id): string
    {
        $value = $this->config->getSettingForTier($tier, $id, self::SETTING_TRANSPORT, null);

        return $value === self::TRANSPORT_GMAIL_API ? self::TRANSPORT_GMAIL_API : self::TRANSPORT_SMTP;
    }

    /**
     * Connection status for the settings UI of one tier.
     *
     * @return array{configured: bool, transport: string, connected: bool, account_email: ?string}
     */
    public function statusForSettings(string $tier, ?int $id): array
    {
        return [
            'configured'    => $this->isConfigured(),
            'transport'     => $this->transportAt($tier, $id),
            'connected'     => $this->hasGrant($tier, $id),
            'account_email' => $this->accountEmail($tier, $id),
        ];
    }

    /**
     * Fresh access token for a tier grant. Throws GmailApiException with a
     * classified GMAIL_* code on any failure — callers must not fall back
     * to another transport.
     */
    public function accessToken(string $tier, ?int $id): string
    {
        $cacheKey = $tier . ':' . ($id ?? 0);
        if (isset($this->tokenCache[$cacheKey])) {
            return $this->tokenCache[$cacheKey];
        }

        $refreshToken = $this->refreshToken($tier, $id);
        if ($refreshToken === null) {
            throw new GmailApiException('GMAIL_GRANT_INVALID');
        }

        $result = $this->fetchAccessToken($refreshToken);

        if (!is_array($result) || !isset($result['access_token']) || $result['access_token'] === '') {
            $error = is_array($result) ? strtolower((string) ($result['error'] ?? '')) : '';
            if (in_array($error, ['invalid_grant', 'invalid_client', 'unauthorized_client'], true)) {
                throw new GmailApiException('GMAIL_GRANT_INVALID');
            }

            throw new GmailApiException('GMAIL_API_ERROR');
        }

        return $this->tokenCache[$cacheKey] = (string) $result['access_token'];
    }

    /**
     * Authenticated Gmail API service for a tier grant.
     */
    public function gmailService(string $tier, ?int $id): Gmail
    {
        $client = $this->makeClient();
        $client->setAccessToken($this->accessToken($tier, $id));

        return new Gmail($client);
    }

    /**
     * Submit a base64url-encoded RFC 822 message through the Gmail API.
     * All Google-layer failures are normalized to GmailApiException codes.
     */
    public function sendRawMessage(string $tier, ?int $id, string $base64urlRaw): void
    {
        $gmail = $this->gmailService($tier, $id);

        $message = new Message();
        $message->setRaw($base64urlRaw);

        try {
            $gmail->users_messages->send('me', $message);
        } catch (\Throwable $e) {
            throw new GmailApiException(
                EmailFailureClassifier::classifyGoogleApiError($e),
                '',
                0,
                $e
            );
        }
    }

    /**
     * Clear all stored credentials for the tier (no remote revocation — see
     * body), resetting the transport selector to SMTP password auth.
     *
     * @param array{type: string, id: int|null} $scope
     */
    public function disconnect(array $scope): void
    {
        $tier    = (string) ($scope['type'] ?? '');
        $scopeId = isset($scope['id']) ? (int) $scope['id'] : null;
        if (!in_array($tier, self::VALID_TIERS, true)) {
            return;
        }

        // Never revoke at Google here: revocation invalidates every token the
        // mailbox granted to this OAuth client, so it would silently break any
        // other tier (System credential mail included) connected to the same
        // account. Deleting this tier's encrypted token makes it unusable to
        // us; the account owner can revoke app access from their Google
        // account if desired.
        $this->config->setSettingForTier($tier, $scopeId, self::SETTING_REFRESH_TOKEN, '', true);
        $this->config->setSettingForTier($tier, $scopeId, self::SETTING_ACCOUNT_EMAIL, '', false);
        $this->config->setSettingForTier($tier, $scopeId, self::SETTING_TRANSPORT, self::TRANSPORT_SMTP, false);

        unset($this->tokenCache[$tier . ':' . ($scopeId ?? 0)], $this->accountCache[$tier . ':' . ($scopeId ?? 0)]);
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    private function assertValidScope(string $tier, ?int $scopeId): void
    {
        if (!in_array($tier, self::VALID_TIERS, true)) {
            throw new \InvalidArgumentException('Invalid email settings tier');
        }
        if (!in_array($tier, ['system', 'organisation'], true) && ($scopeId === null || $scopeId <= 0)) {
            throw new \InvalidArgumentException('Email settings scope requires an id');
        }
    }

    private function makeClient(): Client
    {
        $client = new Client();
        $client->setClientId($this->emailConfig->googleClientId);
        $client->setClientSecret($this->emailConfig->googleClientSecret);
        $client->setRedirectUri($this->redirectUri());
        $client->setScopes(self::OAUTH_SCOPES);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setIncludeGrantedScopes(false);

        return $client;
    }

    /**
     * Exchange a refresh token for an access-token response array.
     * Extracted for testability — subclasses may override to avoid live
     * Google calls. Throws GmailApiException on transport/API failure.
     *
     * @return array<string,mixed>|null
     */
    protected function fetchAccessToken(string $refreshToken): ?array
    {
        try {
            $result = $this->makeClient()->fetchAccessTokenWithRefreshToken($refreshToken);
        } catch (\Throwable $e) {
            throw new GmailApiException(
                EmailFailureClassifier::classifyGoogleApiError($e),
                '',
                0,
                $e
            );
        }

        return is_array($result) ? $result : null;
    }

    /**
     * Persist a state nonce for single-use replay protection. Backed by the
     * session; extracted so tests can substitute an in-memory store.
     */
    protected function storeStateNonce(string $nonce): void
    {
        $nonces   = (array) (session()->get('gmail_oauth_nonces') ?? []);
        $nonces[] = $nonce;
        // Keep the session nonce list bounded — entries are single-use and
        // expire with the 15-minute state TTL anyway.
        $nonces = array_slice(array_values(array_unique($nonces, SORT_REGULAR)), -50);
        session()->set('gmail_oauth_nonces', $nonces);
    }

    /**
     * Consume a state nonce exactly once. Returns false for unknown,
     * already-consumed, or empty nonces.
     */
    protected function consumeStateNonce(string $nonce): bool
    {
        if ($nonce === '') {
            return false;
        }

        $nonces = (array) (session()->get('gmail_oauth_nonces') ?? []);
        $index  = array_search($nonce, $nonces, true);
        if ($index === false) {
            return false;
        }

        unset($nonces[$index]);
        session()->set('gmail_oauth_nonces', array_values($nonces));

        return true;
    }

    /**
     * Exchange the authorization code. Extracted for testability —
     * subclasses may override to avoid live Google calls.
     *
     * @return array<string,mixed>|null token response or null on failure
     */
    protected function exchangeCode(string $code): ?array
    {
        try {
            $result = $this->makeClient()->fetchAccessTokenWithAuthCode($code);
        } catch (\Throwable) {
            return null;
        }

        return is_array($result) ? $result : null;
    }

    /**
     * Discover the authorized mailbox address. Prefers the verified
     * id_token email claim (openid scope), falls back to the OIDC userinfo
     * endpoint. Extracted for testability.
     */
    protected function discoverAccountEmail(Client $client, array $tokens): ?string
    {
        $idToken = $tokens['id_token'] ?? null;
        if (is_string($idToken) && $idToken !== '') {
            try {
                $payload = $client->verifyIdToken($idToken);
                if (is_array($payload)
                    && !empty($payload['email'])
                    && filter_var($payload['email'], FILTER_VALIDATE_EMAIL)
                    && ($payload['email_verified'] ?? true) !== false
                ) {
                    return strtolower((string) $payload['email']);
                }
            } catch (\Throwable) {
                // Fall through to the userinfo endpoint.
            }
        }

        $accessToken = (string) ($tokens['access_token'] ?? '');
        if ($accessToken === '') {
            return null;
        }

        try {
            $response = service('curlrequest')->get('https://openidconnect.googleapis.com/v1/userinfo', [
                'headers'     => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept'        => 'application/json',
                ],
                'http_errors' => false,
                'timeout'     => 10,
            ]);
            $body = json_decode((string) $response->getBody(), true);
            if (is_array($body)
                && !empty($body['email'])
                && filter_var($body['email'], FILTER_VALIDATE_EMAIL)
            ) {
                return strtolower((string) $body['email']);
            }
        } catch (\Throwable) {
            // Discovery failed — handled by caller.
        }

        return null;
    }

    private function persistGrant(string $tier, ?int $id, string $refreshToken, string $email): void
    {
        $encrypted = DataEncryption::encrypt($refreshToken) ?? '';

        $this->config->setSettingForTier($tier, $id, self::SETTING_REFRESH_TOKEN, $encrypted, true);
        $this->config->setSettingForTier($tier, $id, self::SETTING_ACCOUNT_EMAIL, $email, false);
        $this->config->setSettingForTier($tier, $id, self::SETTING_TRANSPORT, self::TRANSPORT_GMAIL_API, false);
    }

    protected function signState(array $payload): string
    {
        $encoded = rtrim(strtr(base64_encode((string) json_encode($payload)), '+/', '-_'), '=');

        return $encoded . '.' . hash_hmac('sha256', $encoded, $this->stateKey());
    }

    /**
     * HMAC key for OAuth state. Reuses the app's blind-index/encryption key
     * material — never a hardcoded literal.
     */
    protected function stateKey(): string
    {
        $key = (string) (env('encryption.blindIndexKey') ?: env('encryption.key') ?: '');
        if ($key === '') {
            throw new \RuntimeException('No OAuth state signing key configured (encryption.key).');
        }

        return $key;
    }

}
