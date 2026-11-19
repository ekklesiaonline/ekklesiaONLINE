<?php

use App\Controllers\BaseController;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Tests\Support\Database\SystemSettingsTestHelper;

/**
 * Covers BaseController::validateAdminPassword — the shared gate for every
 * admin/operator password write path (admin resets, set-password flow).
 *
 * @internal
 */
final class AdminPasswordValidationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $db = db_connect();
        SystemSettingsTestHelper::createCoreAuthTables($db);
        SystemSettingsTestHelper::createSystemSettingsTableAndSeed($db);
    }

    protected function setUp(): void
    {
        parent::setUp();

        helper(['auth', 'form', 'url']);
        Services::reset(true);
        SystemSettingsTestHelper::createSystemSettingsTableAndSeed($this->db);
    }

    protected function tearDown(): void
    {
        Services::reset(true);
        parent::tearDown();
    }

    public function testRejectsDictionaryPasswordThatPassesCompositionChecks(): void
    {
        // 'P@ssw0rd1234' is 12 chars with upper/lower/digit/special — it
        // satisfies every DB composition check but is an exact entry in
        // Shield's common-password dictionary. DictionaryValidator runs
        // before SafePwnedValidator in the chain, so this is offline-safe.
        $result = $this->controller()->checkAdminPassword('P@ssw0rd1234');

        $this->assertFalse((bool) ($result['ok'] ?? true));
        $this->assertNotEmpty($result['errors']);
    }

    public function testFloorsMinLengthAtShieldMinimumRegardlessOfDbPolicy(): void
    {
        // A DB policy below the Shield floor must never weaken the gate.
        service('systemSettings')->set('password_policy.min_length', 8, 'integer', 'policy');

        // 9 chars and satisfies every composition flag, but below the
        // config('Auth')->minimumPasswordLength (12) floor.
        $result = $this->controller()->checkAdminPassword('Abcde12!x');

        $this->assertFalse((bool) ($result['ok'] ?? true));
        $this->assertNotEmpty($result['errors']);
    }

    public function testAcceptsStrongUniquePassword(): void
    {
        // Passes composition, is not a dictionary word, contains no personal
        // data, and is not a known breach corpus entry. SafePwnedValidator
        // degrades to pass when the HIBP API is unreachable, so the result
        // is the same online or offline.
        $result = $this->controller()->checkAdminPassword('Xq7!mZ9vB2pL4nW8');

        $this->assertTrue((bool) ($result['ok'] ?? false), implode('; ', (array) ($result['errors'] ?? [])));
        $this->assertSame([], (array) ($result['errors'] ?? []));
    }

    private function controller(): BaseController
    {
        $controller = new class() extends BaseController {
            public function checkAdminPassword(string $password): array
            {
                return $this->validateAdminPassword($password);
            }
        };
        $controller->initController(service('request'), service('response'), service('logger'));

        return $controller;
    }
}
