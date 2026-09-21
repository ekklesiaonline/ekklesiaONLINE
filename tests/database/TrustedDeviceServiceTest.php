<?php

use App\Services\TrustedDeviceService;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * TrustedDeviceService coverage: issue/validate/revoke lifecycle for the
 * durable "remember this device" cookie + user_trusted_device table.
 *
 * @internal
 */
final class TrustedDeviceServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        Services::reset(true);
        $this->rebuildTrustedDeviceTable();
        unset($_COOKIE[TrustedDeviceService::COOKIE_NAME]);
    }

    protected function tearDown(): void
    {
        unset($_COOKIE[TrustedDeviceService::COOKIE_NAME]);
        Services::reset(true);
        parent::tearDown();
    }

    public function testIssuePersistsHashedTokenAndSetsCookie(): void
    {
        $service = new TrustedDeviceService();
        $id = $service->issue(5, 'Mozilla/5.0 (Macintosh) Safari/605', '10.0.0.9');

        $this->assertNotNull($id);

        $row = $this->db->table('user_trusted_device')->where('id', $id)->get()->getRowArray();
        $this->assertNotNull($row);
        $this->assertSame(5, (int) $row['user_id']);
        $this->assertSame('Safari on macOS', $row['display_name']);
        $this->assertSame(64, strlen((string) $row['token_hash']), 'Only the SHA-256 validator hash may be stored.');
        $this->assertNull($row['revoked_at']);
        $this->assertGreaterThan(time(), strtotime((string) $row['expires_at']));

        $cookie = service('response')->getCookie(TrustedDeviceService::COOKIE_NAME);
        $this->assertNotNull($cookie, 'A trusted-device cookie must be queued on the response.');
        $this->assertStringContainsString('.', $cookie->getValue());
    }

    public function testValidateAcceptsIssuedCookie(): void
    {
        $service = new TrustedDeviceService();
        $id = $service->issue(7, 'Chrome on Windows', '10.0.0.1');
        $this->assertNotNull($id);

        $cookieValue = $this->issuedCookieValue();
        $this->assertNotNull($cookieValue);

        $request = $this->makeRequestWithCookie($cookieValue);
        $this->assertSame($id, $service->validate($request, 7));
    }

    public function testValidateRejectsWrongUser(): void
    {
        $service = new TrustedDeviceService();
        $service->issue(8, 'Firefox on Linux', '10.0.0.2');

        $request = $this->makeRequestWithCookie($this->issuedCookieValue());

        $this->assertNull($service->validate($request, 999));
    }

    public function testValidateRejectsTamperedValidator(): void
    {
        $service = new TrustedDeviceService();
        $service->issue(9, 'Edge on Windows', '10.0.0.3');

        $cookieValue = $this->issuedCookieValue();
        [$selector] = explode('.', $cookieValue, 2);

        $request = $this->makeRequestWithCookie($selector . '.' . str_repeat('ab', 32));

        $this->assertNull($service->validate($request, 9));
    }

    public function testValidateRejectsExpiredDevice(): void
    {
        $service = new TrustedDeviceService();
        $id = $service->issue(10, 'Safari on iOS', '10.0.0.4');
        $this->assertNotNull($id);

        $cookieValue = $this->issuedCookieValue();

        $this->db->table('user_trusted_device')
            ->where('id', $id)
            ->update(['expires_at' => date('Y-m-d H:i:s', time() - 3600)]);

        $this->assertNull($service->validate($this->makeRequestWithCookie($cookieValue), 10));
    }

    public function testRevokedDeviceNoLongerValidates(): void
    {
        $service = new TrustedDeviceService();
        $id = $service->issue(11, 'Chrome on macOS', '10.0.0.5');
        $this->assertNotNull($id);

        $cookieValue = $this->issuedCookieValue();

        $this->assertTrue($service->revoke(11, $id, 11));
        $this->assertNull($service->validate($this->makeRequestWithCookie($cookieValue), 11));
    }

    public function testRevokeIsScopedToOwner(): void
    {
        $service = new TrustedDeviceService();
        $id = $service->issue(12, 'Chrome on Android', '10.0.0.6');
        $this->assertNotNull($id);

        // A different user must not be able to revoke this device.
        $this->assertFalse($service->revoke(999, $id, 999));
    }

    public function testRevokeAllExceptCurrent(): void
    {
        $service = new TrustedDeviceService();
        $keepId   = $service->issue(13, 'Safari on macOS', '10.0.0.7');
        $otherId  = $service->issue(13, 'Chrome on Windows', '10.0.0.8');
        $other2Id = $service->issue(13, 'Firefox on Linux', '10.0.0.9');

        $count = $service->revokeAll(13, $keepId, 13);

        $this->assertSame(2, $count);

        $rows = $this->db->table('user_trusted_device')
            ->where('user_id', 13)
            ->get()->getResultArray();

        $byId = [];
        foreach ($rows as $r) {
            $byId[(int) $r['id']] = $r;
        }

        $this->assertNull($byId[$keepId]['revoked_at']);
        $this->assertNotNull($byId[$otherId]['revoked_at']);
        $this->assertNotNull($byId[$other2Id]['revoked_at']);
    }

    public function testForgetCurrentRevokesAndClearsCookie(): void
    {
        $service = new TrustedDeviceService();
        $id = $service->issue(14, 'Safari on macOS', '10.0.0.10');
        $this->assertNotNull($id);

        $cookieValue = $this->issuedCookieValue();
        $request = $this->makeRequestWithCookie($cookieValue);

        $service->forgetCurrent($request, 14);

        $row = $this->db->table('user_trusted_device')->where('id', $id)->get()->getRowArray();
        $this->assertNotNull($row['revoked_at'], 'forgetCurrent must revoke the DB row.');

        $this->assertNull(
            $service->validate($this->makeRequestWithCookie($cookieValue), 14),
            'The revoked device must no longer validate.'
        );
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function rebuildTrustedDeviceTable(): void
    {
        $forge = \Config\Database::forge();

        $forge->dropTable('user_trusted_device', true);

        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'user_id' => ['type' => 'INTEGER'],
            'selector' => ['type' => 'VARCHAR', 'constraint' => 24],
            'token_hash' => ['type' => 'VARCHAR', 'constraint' => 64],
            'display_name' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'last_ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'first_seen_at' => ['type' => 'DATETIME', 'null' => true],
            'last_seen_at' => ['type' => 'DATETIME', 'null' => true],
            'expires_at' => ['type' => 'DATETIME'],
            'revoked_at' => ['type' => 'DATETIME', 'null' => true],
            'revoked_by' => ['type' => 'INTEGER', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->addUniqueKey('selector');
        $forge->createTable('user_trusted_device', true);
    }

    /**
     * Read the cookie value that issue() queued on the shared response.
     */
    private function issuedCookieValue(): ?string
    {
        $cookie = service('response')->getCookie(TrustedDeviceService::COOKIE_NAME);

        return $cookie !== null ? $cookie->getValue() : null;
    }

    private function makeRequestWithCookie(?string $cookieValue): IncomingRequest
    {
        if ($cookieValue !== null) {
            $_COOKIE[TrustedDeviceService::COOKIE_NAME] = $cookieValue;
        } else {
            unset($_COOKIE[TrustedDeviceService::COOKIE_NAME]);
        }

        // The shared superglobals service snapshots $_COOKIE at construction —
        // refresh it so fetchGlobal('cookie') sees our value.
        service('superglobals')->setCookieArray($_COOKIE);

        $config  = new \Config\App();
        $uri     = new URI('http://example.com/dashboard');
        $request = new IncomingRequest($config, $uri, 'php://input', new UserAgent());
        $request->setMethod('GET');

        Services::injectMock('request', $request);

        return $request;
    }
}
