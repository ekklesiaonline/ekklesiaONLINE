<?php

use App\Filters\DeviceVerificationFilter;
use App\Filters\SessionTrackingFilter;
use App\Services\DeviceVerificationPolicy;
use CodeIgniter\Config\Factories;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Tests\Support\Database\SystemSettingsTestHelper;

/**
 * Regression coverage for the device-verification development bypass defect:
 * SessionTrackingFilter used to create the challenge and send the email
 * BEFORE DeviceVerificationFilter evaluated the bypass flag, so developers
 * received a "verification required" email while being let through anyway.
 *
 * Now both filters share DeviceVerificationPolicy and the tracking filter
 * skips challenge/email side effects when the bypass is active.
 *
 * @internal
 */
final class DeviceVerificationBypassFilterTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    private const FLAG = 'security.disableDeviceVerificationForTesting';

    protected function setUp(): void
    {
        parent::setUp();

        helper('auth');
        Services::reset(true);
        $this->rebuildUserSessionTable();
        SystemSettingsTestHelper::createSystemSettingsTableAndSeed($this->db);
        $this->injectFakeMailer();
        unset($_SERVER[self::FLAG], $_ENV[self::FLAG]);
        putenv(self::FLAG);
    }

    protected function tearDown(): void
    {
        unset($_SERVER[self::FLAG], $_ENV[self::FLAG]);
        putenv(self::FLAG);
        Services::reset(true);
        Factories::reset('config');

        parent::tearDown();
    }

    public function testBypassRegistersSessionWithoutChallengeOrEmail(): void
    {
        $_SERVER[self::FLAG] = '1';
        $this->injectAuth(42);

        $filter = new SessionTrackingFilter();
        $result = $filter->before($this->makeRequest('dashboard'));

        $this->assertNull($result);
        $this->assertNull(
            session()->get('device_verification_required'),
            'Bypassed login must not create a verification challenge.'
        );
        $this->assertNull(
            session()->get('device_verification_code_hash'),
            'Bypassed login must not store a code hash (no email side effects).'
        );
        $this->assertNotNull(
            session()->get('device_verified_at'),
            'Bypassed login should be stamped as verified.'
        );

        $row = $this->db->table('user_session')->where('user_id', 42)->get()->getRowArray();
        $this->assertNotNull($row);
        $this->assertSame(0, (int) $row['is_new_device'], 'Bypassed sessions must not be flagged as new devices.');
        $this->assertFalse(
            $this->lastMailerSent(),
            'Bypassed login must not attempt to send a verification email.'
        );
    }

    public function testNoBypassCreatesChallenge(): void
    {
        $_SERVER[self::FLAG] = '0';
        $this->injectAuth(43);

        $filter = new SessionTrackingFilter();
        $result = $filter->before($this->makeRequest('dashboard'));

        $this->assertNull($result);
        $this->assertTrue(
            (bool) session()->get('device_verification_required'),
            'Non-bypassed new device must create a verification challenge.'
        );

        $row = $this->db->table('user_session')->where('user_id', 43)->get()->getRowArray();
        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row['is_new_device']);
        $this->assertTrue(
            (bool) session()->get('device_verification_mail_failed'),
            'A failed send must flag the session so the verify page can warn.'
        );
    }

    public function testEnforcementFilterDelegatesToSharedPolicy(): void
    {
        $src = file_get_contents(ROOTPATH . 'app/Filters/DeviceVerificationFilter.php');
        $this->assertNotFalse($src);

        $this->assertStringContainsString('DeviceVerificationPolicy', $src);
        $this->assertStringNotContainsString(
            self::FLAG,
            $src,
            'DeviceVerificationFilter must not re-parse the env flag — the policy is the single source of truth.'
        );
    }

    public function testTrackingFilterEvaluatesBypassBeforeChallenge(): void
    {
        $src = file_get_contents(ROOTPATH . 'app/Filters/SessionTrackingFilter.php');
        $this->assertNotFalse($src);

        $this->assertStringContainsString('DeviceVerificationPolicy', $src);

        $bypassPos    = strpos($src, 'shouldBypass()');
        $challengePos = strpos($src, 'startChallenge(');

        $this->assertNotFalse($bypassPos);
        $this->assertNotFalse($challengePos);
        $this->assertLessThan(
            $challengePos,
            $bypassPos,
            'The bypass decision must run before challenge/email creation.'
        );
    }

    public function testPolicyBehaviorInTestingEnvironment(): void
    {
        $policy = new DeviceVerificationPolicy();

        $_SERVER[self::FLAG] = '1';
        $this->assertTrue($policy->shouldBypass());

        $_SERVER[self::FLAG] = '0';
        $this->assertFalse($policy->shouldBypass());

        unset($_SERVER[self::FLAG]);
        $this->assertFalse($policy->shouldBypass());
    }

    public function testPolicyNeverBypassesOutsideDevAndTesting(): void
    {
        // Contract guard: the environment whitelist must remain in place so
        // production can never honor the flag.
        $src = file_get_contents(ROOTPATH . 'app/Services/DeviceVerificationPolicy.php');
        $this->assertNotFalse($src);

        $this->assertStringContainsString("'testing', 'development'", $src);
        $this->assertStringContainsString('ENVIRONMENT', $src);
    }

    public function testEnforcementFilterBypassClearsChallengeKeys(): void
    {
        $_SERVER[self::FLAG] = '1';
        $this->injectAuth(44);

        session()->set([
            'device_verification_required'   => true,
            'device_verification_code_hash'  => hash('sha256', '123456'),
            'device_verification_expires_at' => time() + 600,
        ]);

        $filter = new DeviceVerificationFilter();
        $result = $filter->before($this->makeRequest('dashboard'));

        $this->assertNull($result);
        $this->assertNull(session()->get('device_verification_required'));
        $this->assertNull(session()->get('device_verification_code_hash'));
        $this->assertNotNull(session()->get('device_verified_at'));
    }

    public function testEnforcementFilterRedirectsWhenChallengePending(): void
    {
        $_SERVER[self::FLAG] = '0';
        $this->injectAuth(45);

        session()->set([
            'device_verification_required'   => true,
            'device_verification_code_hash'  => hash('sha256', '123456'),
            'device_verification_expires_at' => time() + 600,
        ]);

        $filter = new DeviceVerificationFilter();
        $result = $filter->before($this->makeRequest('dashboard'));

        $this->assertInstanceOf(\CodeIgniter\HTTP\RedirectResponse::class, $result);
        $this->assertStringContainsString('session/device-verify', $result->getHeaderLine('Location'));
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function rebuildUserSessionTable(): void
    {
        $forge = \Config\Database::forge();

        $forge->dropTable('user_session', true);

        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'user_id' => ['type' => 'INTEGER'],
            'session_token' => ['type' => 'VARCHAR', 'constraint' => 64],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45],
            'user_agent' => ['type' => 'TEXT', 'null' => true],
            'device_fingerprint' => ['type' => 'VARCHAR', 'constraint' => 64],
            'is_new_device' => ['type' => 'INTEGER', 'constraint' => 1, 'default' => 0],
            'last_active' => ['type' => 'DATETIME'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->addUniqueKey('session_token');
        $forge->createTable('user_session', true);
    }

    private function makeRequest(string $path): IncomingRequest
    {
        $config  = new \Config\App();
        $uri     = new \CodeIgniter\HTTP\SiteURI($config, $path);
        $request = new IncomingRequest($config, $uri, 'php://input', new UserAgent());
        $request->setMethod('GET');

        Services::injectMock('request', $request);

        return $request;
    }

    /**
     * Replace the shared email service with a non-network fake that always
     * fails delivery, so tests never touch real SMTP.
     */
    private function injectFakeMailer(): void
    {
        $mock = new class(new \Config\Email()) extends \CodeIgniter\Email\Email {
            public bool $sendAttempted = false;

            public function send($autoClear = true): bool
            {
                $this->sendAttempted = true;

                return false;
            }
        };

        Services::injectMock('email', $mock);
    }

    private function lastMailerSent(): bool
    {
        $email = service('email');

        return property_exists($email, 'sendAttempted') ? (bool) $email->sendAttempted : false;
    }

    private function injectAuth(int $userId): void
    {
        $fakeUser = new class($userId) extends User {
            public function __construct(private int $userIdForTest)
            {
                $this->attributes['id']       = $userIdForTest;
                $this->attributes['username'] = 'u.' . $userIdForTest;
                $this->setEmail('u.' . $userIdForTest . '@example.org');
            }
        };

        $auth = new class($fakeUser, $userId) extends Auth {
            public function __construct(private User $fakeUser, private int $userIdForTest)
            {
                // Avoid parent constructor side-effects.
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
                return $this->userIdForTest;
            }

            public function user(): ?User
            {
                return $this->fakeUser;
            }
        };

        Services::injectMock('auth', $auth);
    }
}
