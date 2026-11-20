<?php

use App\Libraries\DataEncryption;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use Tests\Support\Database\SystemSettingsTestHelper;

/**
 * Feature coverage for the Gmail API OAuth2 email transport:
 * settings save/connect/disconnect are scope-guarded per tier, grant
 * secrets never cross the form boundary, refresh tokens stay encrypted at
 * rest, and the OAuth callback only accepts signed, fresh, single-use
 * state from an authorized session. No live Google calls are made — every
 * path exercised here resolves before the token exchange.
 *
 * @internal
 */
final class GmailOAuthSettingsFeatureTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    protected function setUp(): void
    {
        parent::setUp();

        helper(['auth', 'form']);
        Services::reset(true);
        SystemSettingsTestHelper::createAuditLogTable($this->db);
        $this->withRoutes([
            ['POST', 'diocese/email-settings', '\\App\\Controllers\\Diocese\\EmailSettingsController::save'],
            ['GET', 'diocese/email-settings/google/connect', '\\App\\Controllers\\Diocese\\EmailSettingsController::googleConnect'],
            ['POST', 'diocese/email-settings/google/disconnect', '\\App\\Controllers\\Diocese\\EmailSettingsController::googleDisconnect'],
            ['GET', 'system/email/google/connect', '\\App\\Controllers\\SystemEmailSettingsController::googleConnect'],
            ['GET', 'email/oauth/google/callback', '\\App\\Controllers\\EmailOAuthController::callback'],
        ]);
    }

    protected function tearDown(): void
    {
        Services::reset(true);

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Settings save: transport selector + credential boundary
    // ------------------------------------------------------------------

    public function testSaveGmailTransportRejectedWithoutGrant(): void
    {
        $dioceseId = $this->insertDiocese($this->insertProvince('GP1'), 'Grantless Diocese');
        $adminId   = $this->insertPrimaryDioceseAdmin('gmail.save.nogrant', $dioceseId);

        $this->withHeaders([csrf_header() => csrf_hash()])
            ->post('diocese/email-settings', [
                'scope_type'     => 'diocese',
                'scope_id'       => $dioceseId,
                'mail_transport' => 'gmail_api',
            ]);

        $this->assertStoredSetting($dioceseId, 'email.mail_transport', null);
    }

    public function testSaveGmailTransportPersistsWithGrant(): void
    {
        $dioceseId = $this->insertDiocese($this->insertProvince('GP2'), 'Granted Diocese');
        $adminId   = $this->insertPrimaryDioceseAdmin('gmail.save.grant', $dioceseId);
        $this->seedGmailGrant($dioceseId);

        $response = $this->withHeaders([csrf_header() => csrf_hash()])
            ->post('diocese/email-settings', [
                'scope_type'     => 'diocese',
                'scope_id'       => $dioceseId,
                'mail_transport' => 'gmail_api',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertStoredSetting($dioceseId, 'email.mail_transport', 'gmail_api');
    }

    public function testSaveGmailTransportRejectsForeignDiocese(): void
    {
        $dioceseId      = $this->insertDiocese($this->insertProvince('GP3'), 'Own Diocese');
        $victimDiocese  = $this->insertDiocese($this->insertProvince('GP4'), 'Victim Diocese');
        $adminId        = $this->insertPrimaryDioceseAdmin('gmail.save.idor', $dioceseId);
        $this->seedGmailGrant($victimDiocese);

        $response = $this->withHeaders([csrf_header() => csrf_hash()])
            ->post('diocese/email-settings', [
                'scope_type'     => 'diocese',
                'scope_id'       => $victimDiocese,
                'mail_transport' => 'gmail_api',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStoredSetting($victimDiocese, 'email.mail_transport', 'gmail_api');
    }

    public function testSaveNeverPersistsGmailCredentialsFromPost(): void
    {
        $dioceseId = $this->insertDiocese($this->insertProvince('GP5'), 'Credential Diocese');
        $adminId   = $this->insertPrimaryDioceseAdmin('gmail.save.creds', $dioceseId);
        $this->seedGmailGrant($dioceseId);

        $this->withHeaders([csrf_header() => csrf_hash()])
            ->post('diocese/email-settings', [
                'scope_type'           => 'diocese',
                'scope_id'             => $dioceseId,
                'mail_transport'       => 'gmail_api',
                'gmail_refresh_token'  => 'forged-token',
                'gmail_account_email'  => 'attacker@example.org',
            ]);

        // The forged refresh token is outside EMAIL_KEYS and the account
        // email is stripped in save() — both keep their seeded values.
        $tokenRow = $this->db->table('diocese_setting')
            ->where('diocese_id', $dioceseId)
            ->where('key', 'email.gmail_refresh_token')
            ->get()
            ->getRowArray();
        self::assertNotNull($tokenRow);
        self::assertStringStartsWith('enc:v1:', (string) $tokenRow['value']);
        self::assertSame('seeded-refresh-token', DataEncryption::decrypt((string) $tokenRow['value']));
        $this->assertStoredSetting($dioceseId, 'email.gmail_account_email', 'diocese@gmail.com');
    }

    // ------------------------------------------------------------------
    // Connect / disconnect endpoints
    // ------------------------------------------------------------------

    public function testGoogleConnectRejectsForeignDiocese(): void
    {
        $dioceseId     = $this->insertDiocese($this->insertProvince('GP6'), 'Connect Diocese');
        $otherDiocese  = $this->insertDiocese($this->insertProvince('GP7'), 'Foreign Diocese');
        $adminId       = $this->insertPrimaryDioceseAdmin('gmail.connect.idor', $dioceseId);

        $response = $this->get('diocese/email-settings/google/connect?diocese_id=' . $otherDiocese);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function testGoogleConnectRequiresTransportEditPermission(): void
    {
        $dioceseId = $this->insertDiocese($this->insertProvince('GP8'), 'Readonly Diocese');
        $adminId   = $this->insertUser('gmail.connect.readonly');
        $this->insertProfile($adminId, $dioceseId);
        // diocese.admin reaches the controller, but without diocese.primary
        // or system.admin the transport cannot be edited.
        $this->injectAuth($adminId, ['diocese.admin'], ['dioceseadmin']);

        $response = $this->get('diocese/email-settings/google/connect?diocese_id=' . $dioceseId);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function testGoogleConnectRequiresTierPermission(): void
    {
        $adminId = $this->insertUser('gmail.connect.noperm');
        $this->injectAuth($adminId, [], []);

        $response = $this->get('system/email/google/connect');

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function testGoogleDisconnectClearsGrantAndResetsTransport(): void
    {
        $dioceseId = $this->insertDiocese($this->insertProvince('GP9'), 'Disconnect Diocese');
        $adminId   = $this->insertPrimaryDioceseAdmin('gmail.disconnect', $dioceseId);
        $this->seedGmailGrant($dioceseId);

        $response = $this->withHeaders([csrf_header() => csrf_hash()])
            ->post('diocese/email-settings/google/disconnect', [
                'scope_id' => $dioceseId,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertStoredSetting($dioceseId, 'email.gmail_refresh_token', '');
        $this->assertStoredSetting($dioceseId, 'email.gmail_account_email', '');
        $this->assertStoredSetting($dioceseId, 'email.mail_transport', 'smtp_password');
    }

    public function testGoogleDisconnectRejectsForeignDiocese(): void
    {
        $dioceseId     = $this->insertDiocese($this->insertProvince('GP10'), 'Disconnect Own');
        $victimDiocese = $this->insertDiocese($this->insertProvince('GP11'), 'Disconnect Victim');
        $adminId       = $this->insertPrimaryDioceseAdmin('gmail.disconnect.idor', $dioceseId);
        $this->seedGmailGrant($victimDiocese);

        $response = $this->withHeaders([csrf_header() => csrf_hash()])
            ->post('diocese/email-settings/google/disconnect', [
                'scope_id' => $victimDiocese,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStoredSetting($victimDiocese, 'email.mail_transport', 'gmail_api');
        $this->assertStoredSetting($victimDiocese, 'email.gmail_account_email', 'diocese@gmail.com');
    }

    // ------------------------------------------------------------------
    // OAuth callback: signed, fresh, single-use state + scope re-auth
    // ------------------------------------------------------------------

    public function testCallbackRejectsTamperedState(): void
    {
        $adminId = $this->insertUser('gmail.cb.tampered');
        $this->injectAuth($adminId, ['diocese.admin'], ['dioceseadmin']);

        $response = $this->get('email/oauth/google/callback?state=forged.payload&code=x');

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function testCallbackRejectsExpiredState(): void
    {
        $adminId = $this->insertUser('gmail.cb.expired');
        $this->injectAuth($adminId, ['diocese.admin'], ['dioceseadmin']);

        $nonce = 'expired-nonce';
        $state = $this->signState([
            'tier'     => 'diocese',
            'scope_id' => 1,
            'user_id'  => $adminId,
            'nonce'    => $nonce,
            'ts'       => time() - 2000,
        ]);

        $response = $this->get('email/oauth/google/callback?state=' . $state . '&code=x');

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function testCallbackRejectsUnknownNonce(): void
    {
        $adminId = $this->insertUser('gmail.cb.nonce');
        $this->injectAuth($adminId, ['diocese.admin'], ['dioceseadmin']);

        // A validly signed state whose nonce was never issued to this
        // session (replay of a captured state) must not be consumed.
        $state = $this->signState([
            'tier'     => 'diocese',
            'scope_id' => 1,
            'user_id'  => $adminId,
            'nonce'    => 'never-issued',
            'ts'       => time(),
        ]);

        $response = $this->get('email/oauth/google/callback?state=' . $state . '&code=x');

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function testCallbackRejectsScopeBeyondAuthorization(): void
    {
        $dioceseId    = $this->insertDiocese($this->insertProvince('GP12'), 'Callback Diocese');
        $otherDiocese = $this->insertDiocese($this->insertProvince('GP13'), 'Callback Victim');
        $adminId      = $this->insertPrimaryDioceseAdmin('gmail.cb.scope', $dioceseId);

        $nonce = 'scoped-nonce';
        $state = $this->signState([
            'tier'     => 'diocese',
            'scope_id' => $otherDiocese,
            'user_id'  => $adminId,
            'nonce'    => $nonce,
            'ts'       => time(),
        ]);
        // FeatureTestTrait::call() only seeds $_SESSION; if the session
        // service is (re)created during the request, session_start()
        // repopulates it from the handler and wipes the seed. Start the
        // session first so the request reuses the active one.
        service('session')->set('gmail_oauth_nonces', [$nonce]);
        $response = $this->withSession(['gmail_oauth_nonces' => [$nonce]])
            ->get('email/oauth/google/callback?state=' . $state . '&code=x');
        $response->assertRedirect();
        $response->assertSessionHas('error');
        // The scope guard itself must be the rejection path: an unrelated
        // failure (e.g. unconfigured OAuth client) audits a different action.
        $audit = $this->db->table('audit_log')
            ->where('action', 'email_settings.gmail_oauth_denied')
            ->where('entity_id', $otherDiocese)
            ->where('user_id', $adminId)
            ->get()
            ->getRowArray();
        self::assertNotNull($audit, 'Scope re-authorization must deny the foreign scope');
        // The foreign scope's grant table must stay empty.
        $this->assertStoredSetting($otherDiocese, 'email.gmail_refresh_token', null);
        $this->assertStoredSetting($otherDiocese, 'email.mail_transport', null);
    }

    public function testCallbackRejectsMissingCode(): void
    {
        $dioceseId = $this->insertDiocese($this->insertProvince('GP14'), 'Missing Code Diocese');
        $adminId   = $this->insertPrimaryDioceseAdmin('gmail.cb.nocode', $dioceseId);

        $nonce = 'nocode-nonce';
        $state = $this->signState([
            'tier'     => 'diocese',
            'scope_id' => $dioceseId,
            'user_id'  => $adminId,
            'nonce'    => $nonce,
            'ts'       => time(),
        ]);
        // Same session seeding contract as the scope test above.
        service('session')->set('gmail_oauth_nonces', [$nonce]);
        $response = $this->withSession(['gmail_oauth_nonces' => [$nonce]])
            ->get('email/oauth/google/callback?state=' . $state);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStoredSetting($dioceseId, 'email.gmail_refresh_token', null);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * HMAC-sign a state payload exactly like GmailOAuthService::signState —
     * the only way to hand the callback a state Google never issued.
     *
     * @param array<string,mixed> $payload
     */
    private function signState(array $payload): string
    {
        $key     = (string) (env('encryption.blindIndexKey') ?: env('encryption.key'));
        $encoded = rtrim(strtr(base64_encode((string) json_encode($payload)), '+/', '-_'), '=');

        return $encoded . '.' . hash_hmac('sha256', $encoded, $key);
    }

    /**
     * Seed a connected Gmail grant at the diocese tier exactly as the OAuth
     * callback persists it: refresh token encrypted (is_encrypted=1,
     * enc:v1: envelope), account email plaintext, transport selected.
     */
    private function seedGmailGrant(int $dioceseId): void
    {
        $encrypted = DataEncryption::encrypt('seeded-refresh-token');
        self::assertIsString($encrypted, 'Test encryption must be configured');
        self::assertStringStartsWith('enc:v1:', $encrypted);

        $now = date('Y-m-d H:i:s');
        foreach ([
            ['email.gmail_refresh_token', $encrypted, 1],
            ['email.gmail_account_email', 'diocese@gmail.com', 0],
            ['email.mail_transport', 'gmail_api', 0],
        ] as [$key, $value, $encryptedFlag]) {
            $this->db->table('diocese_setting')->insert([
                'diocese_id'   => $dioceseId,
                'key'          => $key,
                'value'        => $value,
                'is_encrypted' => $encryptedFlag,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }
    }

    /**
     * Assert a stored diocese_setting value (decrypted view).
     */
    private function assertStoredSetting(int $dioceseId, string $key, ?string $expected): void
    {
        $row = $this->db->table('diocese_setting')
            ->where('diocese_id', $dioceseId)
            ->where('key', $key)
            ->get()
            ->getRowArray();

        if ($expected === null) {
            self::assertTrue(
                $row === null || (string) $row['value'] === '',
                "Expected no stored value for {$key}"
            );

            return;
        }

        self::assertNotNull($row, "Expected {$key} to be stored");

        $value = (string) $row['value'];
        if (!empty($row['is_encrypted']) && $value !== '') {
            $value = (string) DataEncryption::decrypt($value);
        }

        self::assertSame($expected, $value);
    }

    private function insertPrimaryDioceseAdmin(string $username, int $dioceseId): int
    {
        $adminId = $this->insertUser($username);
        $this->insertProfile($adminId, $dioceseId);
        $this->injectAuth($adminId, ['diocese.admin', 'diocese.primary'], ['dioceseadmin']);

        return $adminId;
    }

    private function insertProvince(string $name): int
    {
        $this->db->table('province')->insert([
            'name'            => $name,
            'province_number' => '1',
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertDiocese(int $provinceId, string $name): int
    {
        $this->db->table('diocese')->insert([
            'province_id' => $provinceId,
            'name'        => $name,
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertUser(string $username): int
    {
        $this->db->table('users')->insert([
            'username'   => $username,
            'active'     => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertProfile(int $userId, int $dioceseId): void
    {
        $this->db->table('profile')->insert([
            'user_id'    => $userId,
            'diocese_id' => $dioceseId,
            'first_name' => 'Dio',
            'last_name'  => 'Admin',
            'locale'     => 'en',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array<int,string> $permissions
     * @param array<int,string> $groups
     */
    private function injectAuth(int $userId, array $permissions, array $groups): void
    {
        $fakeUser = new class($userId, $permissions, $groups) extends User {
            /**
             * @param array<int,string> $permissions
             * @param array<int,string> $groups
             */
            public function __construct(private int $testUserId, private array $permissions, private array $groups)
            {
                parent::__construct([
                    'id'       => $testUserId,
                    'username' => 'gmail.oauth.' . $testUserId,
                    'email'    => 'gmail.oauth.' . $testUserId . '@example.org',
                ]);
            }

            public function can(string ...$permissions): bool
            {
                foreach ($permissions as $permission) {
                    if (!in_array($permission, $this->permissions, true)) {
                        return false;
                    }
                }

                return true;
            }

            public function inGroup(string ...$groups): bool
            {
                foreach ($groups as $group) {
                    if (in_array($group, $this->groups, true)) {
                        return true;
                    }
                }

                return false;
            }
        };

        $auth = new class(config('Auth'), $fakeUser, $userId) extends Auth {
            public function __construct($config, private User $fakeUser, private int $testUserId)
            {
                parent::__construct($config);
            }

            public function setAuthenticator(?string $alias = null): self
            {
                return $this;
            }

            public function loggedIn(): bool
            {
                return true;
            }

            public function id(): ?int
            {
                return $this->testUserId;
            }

            public function user(): ?User
            {
                return $this->fakeUser;
            }
        };

        Services::injectMock('auth', $auth);
    }
}
