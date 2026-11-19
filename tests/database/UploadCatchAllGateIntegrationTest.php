<?php

use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * The /uploads/* catch-all (PublicAssets::serveUpload) is allowlist-gated and
 * the allowlist is intentionally empty: every upload type under
 * writable/uploads is tenant-scoped private data served by a dedicated,
 * scope-checked endpoint (media/* or expenses/receipt). These tests pin the
 * hard-404 behavior so a previously-servable unreviewed prefix can never
 * silently become readable by any authenticated session again.
 *
 * @internal
 */
final class UploadCatchAllGateIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    /** @var list<string> */
    private array $createdFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        helper('auth');
        Services::reset(true);
        $this->createdFiles = [];
    }

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }

            // Prune the now-empty fixture directories back to the uploads root.
            $dir = dirname($file);
            $uploadsRoot = rtrim((string) realpath(rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads'), DIRECTORY_SEPARATOR);
            while ($uploadsRoot !== '' && str_starts_with($dir, $uploadsRoot . DIRECTORY_SEPARATOR) && is_dir($dir)) {
                if (!@rmdir($dir)) {
                    break;
                }
                $dir = dirname($dir);
            }
        }

        Services::reset(true);
        parent::tearDown();
    }

    public function testSensitiveAttachmentPrefixReturnsNotFoundForLoggedInUser(): void
    {
        $relative = 'attachment/expense/7/document/2099/01/catchall-fixture.txt';
        $this->createUploadFixture($relative, 'expense-receipt-fixture');
        $this->assertFileExists($this->uploadsPath($relative));

        $this->injectAuthUser(101, ['member'], true);
        $controller = $this->newController();

        $this->expectException(\CodeIgniter\Exceptions\PageNotFoundException::class);
        $controller->serveUpload($relative);
    }

    public function testProfileAvatarPrefixReturnsNotFoundForLoggedInUser(): void
    {
        $relative = 'profile/55/avatar/2099/01/catchall-fixture.jpg';
        $this->createUploadFixture($relative, 'avatar-fixture');
        $this->assertFileExists($this->uploadsPath($relative));

        $this->injectAuthUser(101, ['member'], true);
        $controller = $this->newController();

        $this->expectException(\CodeIgniter\Exceptions\PageNotFoundException::class);
        $controller->serveUpload($relative);
    }

    public function testUnreviewedPrefixReturnsNotFoundEvenWhenFileExists(): void
    {
        // Regression: before the allowlist gate, any prefix outside the
        // blocklist — including brand-new upload types — was served to every
        // authenticated user. A real file on disk must still 404.
        $relative = 'unreviewed-prefix-2099/catchall-fixture.txt';
        $this->createUploadFixture($relative, 'previously-servable-content');
        $this->assertFileExists($this->uploadsPath($relative));

        $this->injectAuthUser(101, ['member'], true);
        $controller = $this->newController();

        $this->expectException(\CodeIgniter\Exceptions\PageNotFoundException::class);
        $controller->serveUpload($relative);
    }

    public function testArbitraryPrefixWithoutFileReturnsNotFound(): void
    {
        $this->injectAuthUser(101, ['member'], true);
        $controller = $this->newController();

        $this->expectException(\CodeIgniter\Exceptions\PageNotFoundException::class);
        $controller->serveUpload('some/future/upload-type/file.png');
    }

    public function testTraversalAndMalformedPathsReturnNotFound(): void
    {
        $this->injectAuthUser(101, ['member'], true);
        $controller = $this->newController();

        foreach (['', '../secret.txt', 'foo/../../bar', '..\\win\\path', 'bad path.txt'] as $badPath) {
            try {
                $controller->serveUpload($badPath);
                $this->fail('Expected PageNotFoundException for path: ' . $badPath);
            } catch (\CodeIgniter\Exceptions\PageNotFoundException $e) {
                $this->assertTrue(true);
            }
        }
    }

    public function testUnauthenticatedRequestReturnsNotFound(): void
    {
        $this->injectLoggedOutAuth();
        $controller = $this->newController();

        $this->expectException(\CodeIgniter\Exceptions\PageNotFoundException::class);
        $controller->serveUpload('unreviewed-prefix-2099/catchall-fixture.txt');
    }

    private function newController(): \MemberPortal\Controllers\PublicAssets
    {
        $controller = new \MemberPortal\Controllers\PublicAssets();
        $controller->initController(service('request'), service('response'), service('logger'));

        return $controller;
    }

    private function uploadsPath(string $relative): string
    {
        return rtrim(WRITEPATH, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'uploads'
            . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    private function createUploadFixture(string $relative, string $contents): void
    {
        $absolute = $this->uploadsPath($relative);
        $dir = dirname($absolute);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($absolute, $contents);
        $this->createdFiles[] = $absolute;
    }

    private function injectAuthUser(int $userId, array $groups, bool $canAll): void
    {
        $fakeUser = new class($userId, $groups, $canAll) extends User {
            public function __construct(private int $userIdForTest, private array $groupsForTest, private bool $canAllForTest)
            {
                parent::__construct([
                    'id' => $userIdForTest,
                    'username' => 'catchall.user.' . $userIdForTest,
                    'email' => 'catchall.user.' . $userIdForTest . '@example.org',
                ]);
            }

            public function can(string ...$permissions): bool
            {
                return $this->canAllForTest;
            }

            public function inGroup(string ...$groups): bool
            {
                return array_intersect($groups, $this->groupsForTest) !== [];
            }
        };

        $auth = new class(config('Auth'), $fakeUser, $userId) extends Auth {
            public function __construct($config, private User $fakeUser, private int $userIdForTest)
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
                return $this->userIdForTest;
            }

            public function user(): ?User
            {
                return $this->fakeUser;
            }
        };

        Services::injectMock('auth', $auth);
    }

    private function injectLoggedOutAuth(): void
    {
        $auth = new class(config('Auth')) extends Auth {
            public function setAuthenticator(?string $alias = null): self
            {
                return $this;
            }

            public function loggedIn(): bool
            {
                return false;
            }

            public function id(): ?int
            {
                return null;
            }

            public function user(): ?User
            {
                return null;
            }
        };

        Services::injectMock('auth', $auth);
    }
}
