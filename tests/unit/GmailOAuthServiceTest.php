<?php

use App\Libraries\DataEncryption;
use App\Libraries\Email\GmailApiException;
use App\Services\ConfigurationService;
use App\Services\GmailOAuthService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Email as EmailConfig;
use Google\Client;

/**
 * GmailOAuthService tests — Google calls are stubbed; no live API access.
 *
 * @internal
 */
final class GmailOAuthServiceTest extends CIUnitTestCase
{
    private FakeOAuthConfig $config;
    private TestableGmailOAuthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config  = new FakeOAuthConfig();
        $this->service = new TestableGmailOAuthService($this->config);
    }

    private function statePayload(array $overrides = []): array
    {
        return array_merge([
            'tier'     => 'parish',
            'scope_id' => 5,
            'user_id'  => 42,
            'nonce'    => 'nonce-1',
            'ts'       => time(),
        ], $overrides);
    }

    public function testConnectUrlProducesGoogleConsentUrlWithSignedState(): void
    {
        $url = $this->service->connectUrl(['type' => 'parish', 'id' => 5], 42);

        $this->assertStringContainsString('accounts.google.com', $url);
        $this->assertStringContainsString('gmail.send', urldecode($url));
        $this->assertStringContainsString('access_type=offline', $url);
        $this->assertStringContainsString('prompt=consent', $url);
        $this->assertCount(1, $this->service->nonces);
    }

    public function testStateRoundTripAndSingleUse(): void
    {
        $state = $this->service->signStateForTest($this->statePayload());
        $this->service->nonces[] = 'nonce-1';

        $payload = $this->service->parseAndConsumeState($state);

        $this->assertNotNull($payload);
        $this->assertSame('parish', $payload['tier']);
        $this->assertSame(5, $payload['scope_id']);
        $this->assertSame(42, $payload['user_id']);

        // Replay: the nonce was consumed — the same state must now fail.
        $this->assertNull($this->service->parseAndConsumeState($state));
    }

    public function testTamperedStateIsRejected(): void
    {
        $state = $this->service->signStateForTest($this->statePayload());
        $this->service->nonces[] = 'nonce-1';

        // Flip the last signature character.
        $tampered = substr($state, 0, -1) . ($state[-1] === 'a' ? 'b' : 'a');
        $this->assertNull($this->service->parseAndConsumeState($tampered));

        // Tamper with the payload portion.
        $parts   = explode('.', $state);
        $forged  = $parts[0] . 'x.' . $parts[1];
        $this->assertNull($this->service->parseAndConsumeState($forged));

        // Malformed input.
        $this->assertNull($this->service->parseAndConsumeState('not-a-state'));
        $this->assertNull($this->service->parseAndConsumeState(''));
    }

    public function testExpiredStateIsRejected(): void
    {
        $state = $this->service->signStateForTest($this->statePayload(['ts' => time() - 1000]));
        $this->service->nonces[] = 'nonce-1';

        $this->assertNull($this->service->parseAndConsumeState($state));
    }

    public function testStateWithoutSessionNonceIsRejected(): void
    {
        // Valid signature + fresh timestamp, but the nonce was never stored
        // for this session (e.g. state minted elsewhere or session lost).
        $state = $this->service->signStateForTest($this->statePayload());

        $this->assertNull($this->service->parseAndConsumeState($state));
    }

    public function testConnectUrlRejectsInvalidTier(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->connectUrl(['type' => 'bogus', 'id' => 1], 42);
    }

    public function testConnectUrlRequiresScopeIdForTenantTiers(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->connectUrl(['type' => 'parish', 'id' => null], 42);
    }

    public function testHandleCallbackPersistsEncryptedGrant(): void
    {
        $this->service->exchangeResult  = ['refresh_token' => 'rt-secret-1', 'access_token' => 'at-1'];
        $this->service->discoveredEmail = 'admin@gmail.com';

        $result = $this->service->handleCallback('auth-code', $this->statePayload());

        $this->assertTrue($result['ok']);
        $this->assertSame('admin@gmail.com', $result['email']);

        $key = 'parish:5:email.gmail_refresh_token';
        $stored = $this->config->store[$key] ?? null;
        $this->assertIsString($stored);
        $this->assertStringStartsWith('enc:v1:', $stored);
        $this->assertSame('rt-secret-1', DataEncryption::decrypt($stored));
        $this->assertTrue($this->config->encrypted[$key] ?? false);

        $this->assertSame('admin@gmail.com', $this->config->store['parish:5:email.gmail_account_email'] ?? null);
        $this->assertSame('gmail_api', $this->config->store['parish:5:email.mail_transport'] ?? null);
    }

    public function testHandleCallbackRequiresRefreshToken(): void
    {
        $this->service->exchangeResult  = ['access_token' => 'at-1'];
        $this->service->discoveredEmail = 'admin@gmail.com';

        $result = $this->service->handleCallback('auth-code', $this->statePayload());

        $this->assertFalse($result['ok']);
        $this->assertSame('GMAIL_NO_REFRESH_TOKEN', $result['error']);
        $this->assertArrayNotHasKey('parish:5:email.gmail_refresh_token', $this->config->store);
    }

    public function testHandleCallbackFailsWhenEmailDiscoveryFails(): void
    {
        $this->service->exchangeResult  = ['refresh_token' => 'rt-1', 'access_token' => 'at-1'];
        $this->service->discoveredEmail = null;

        $result = $this->service->handleCallback('auth-code', $this->statePayload());

        $this->assertFalse($result['ok']);
        $this->assertSame('GMAIL_ACCOUNT_DISCOVERY_FAILED', $result['error']);
        $this->assertArrayNotHasKey('parish:5:email.gmail_refresh_token', $this->config->store);
    }

    public function testAccessTokenRefreshesViaGrant(): void
    {
        $this->config->store['parish:5:email.gmail_refresh_token'] = 'rt-live';
        $this->service->tokenResponse = ['access_token' => 'at-fresh'];

        $this->assertSame('at-fresh', $this->service->accessToken('parish', 5));
        // Second call is served from the per-request cache — no second fetch.
        $this->assertSame('at-fresh', $this->service->accessToken('parish', 5));
        $this->assertSame(['rt-live'], $this->service->refreshCalls);
    }

    public function testAccessTokenFailsClosedOnInvalidGrant(): void
    {
        $this->config->store['parish:5:email.gmail_refresh_token'] = 'rt-dead';
        $this->service->tokenResponse = ['error' => 'invalid_grant'];

        try {
            $this->service->accessToken('parish', 5);
            $this->fail('Expected GmailApiException');
        } catch (GmailApiException $e) {
            $this->assertSame('GMAIL_GRANT_INVALID', $e->failureCode);
        }
    }

    public function testAccessTokenWithoutGrantThrows(): void
    {
        $this->expectException(GmailApiException::class);
        $this->expectExceptionMessage('GMAIL_GRANT_INVALID');
        $this->service->accessToken('parish', 5);
    }

    public function testDisconnectClearsGrantWithoutRemoteRevocation(): void
    {
        $this->config->store['parish:5:email.gmail_refresh_token'] = 'rt-live';
        $this->config->store['parish:5:email.gmail_account_email']   = 'admin@gmail.com';
        $this->config->store['parish:5:email.mail_transport']         = 'gmail_api';

        $this->service->disconnect(['type' => 'parish', 'id' => 5]);

        // Remote revocation would break other tiers sharing the mailbox.
        $this->assertSame('', $this->config->store['parish:5:email.gmail_refresh_token'] ?? null);
        $this->assertSame('', $this->config->store['parish:5:email.gmail_account_email'] ?? null);
        $this->assertSame('smtp_password', $this->config->store['parish:5:email.mail_transport'] ?? null);
    }

    public function testTransportAtDefaultsToSmtp(): void
    {
        $this->assertSame('smtp_password', $this->service->transportAt('parish', 5));

        $this->config->store['parish:5:email.mail_transport'] = 'gmail_api';
        $this->assertSame('gmail_api', $this->service->transportAt('parish', 5));
    }

    public function testStatusForSettingsReflectsGrant(): void
    {
        $this->config->store['parish:5:email.gmail_refresh_token'] = 'rt';
        $this->config->store['parish:5:email.gmail_account_email'] = 'admin@gmail.com';

        $status = $this->service->statusForSettings('parish', 5);

        $this->assertTrue($status['connected']);
        $this->assertSame('admin@gmail.com', $status['account_email']);
        $this->assertTrue($status['configured']);
    }
}

// ------------------------------------------------------------------
// Test doubles
// ------------------------------------------------------------------

class FakeOAuthConfig extends ConfigurationService
{
    /** @var array<string,mixed> "tier:id:key" => stored value */
    public array $store = [];

    /** @var array<string,bool> "tier:id:key" => is_encrypted flag */
    public array $encrypted = [];

    public function __construct()
    {
        // Skip parent constructor; avoid DB-backed model instantiation.
    }

    public function isSettingLocked(string $key): bool
    {
        return false;
    }

    public function getSettingForTier(string $tier, ?int $id, string $key, $default = null): mixed
    {
        $mapKey = $tier . ':' . ($id ?? 0) . ':' . $key;
        if (!array_key_exists($mapKey, $this->store)) {
            return $default;
        }

        $value = $this->store[$mapKey];
        if (is_string($value) && str_starts_with($value, 'enc:v1:')) {
            $value = DataEncryption::decrypt($value);
        }

        return $value ?? $default;
    }

    public function setSettingForTier(string $tier, ?int $id, string $key, $value, bool $isEncrypted = false): bool
    {
        $mapKey = $tier . ':' . ($id ?? 0) . ':' . $key;
        $this->store[$mapKey]     = $value;
        $this->encrypted[$mapKey] = $isEncrypted;

        return true;
    }
}

class TestableGmailOAuthService extends GmailOAuthService
{
    /** @var list<string> */
    public array $nonces = [];

    /** @var list<string> refresh tokens passed to fetchAccessToken */
    public array $refreshCalls = [];

    public ?array $exchangeResult  = null;
    public ?string $discoveredEmail = null;
    public ?array $tokenResponse    = null;
    public ?Throwable $tokenException = null;

    public function __construct(private FakeOAuthConfig $cfg)
    {
        $emailConfig            = new EmailConfig();
        $emailConfig->googleClientId     = 'test-client-id';
        $emailConfig->googleClientSecret = 'test-client-secret';

        parent::__construct($cfg, $emailConfig);
    }

    public function signStateForTest(array $payload): string
    {
        return $this->signState($payload);
    }

    protected function storeStateNonce(string $nonce): void
    {
        $this->nonces[] = $nonce;
    }

    protected function consumeStateNonce(string $nonce): bool
    {
        $index = array_search($nonce, $this->nonces, true);
        if ($index === false) {
            return false;
        }

        unset($this->nonces[$index]);
        $this->nonces = array_values($this->nonces);

        return true;
    }

    protected function exchangeCode(string $code): ?array
    {
        return $this->exchangeResult;
    }

    protected function discoverAccountEmail(Client $client, array $tokens): ?string
    {
        return $this->discoveredEmail;
    }

    protected function fetchAccessToken(string $refreshToken): ?array
    {
        $this->refreshCalls[] = $refreshToken;

        if ($this->tokenException !== null) {
            throw $this->tokenException;
        }

        return $this->tokenResponse;
    }
}
