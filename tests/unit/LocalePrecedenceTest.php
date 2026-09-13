<?php

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

class LocalePrecedenceTestController extends BaseController
{
    public function setUserAndProfileForTest(?object $user, ?array $profile): void
    {
        $this->user = $user;
        $this->profile = $profile;
    }

    public function resolvePreferredLocaleForTest(RequestInterface $request): string
    {
        return $this->resolvePreferredLocale($request);
    }

    protected function getProfile(): ?array
    {
        return $this->profile;
    }
}

/**
 * @internal
 */
final class LocalePrecedenceTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Services::reset(true);
    }

    protected function tearDown(): void
    {
        Services::reset(true);
        parent::tearDown();
    }

    public function testDefaultsToEnglishWhenNoProfileAndNoBrowserMatch(): void
    {
        $controller = new LocalePrecedenceTestController();
        $controller->setUserAndProfileForTest(null, null);

        $request = $this->mockRequestWithHeader('zh-CN,zh;q=0.9');

        $this->assertSame('en', $controller->resolvePreferredLocaleForTest($request));
    }

    public function testProfileLocaleOverridesBrowserLocale(): void
    {
        $controller = new LocalePrecedenceTestController();
        $controller->setUserAndProfileForTest((object) ['id' => 1001], ['locale' => 'fr']);

        $request = $this->mockRequestWithHeader('es-ES,es;q=0.9');

        $this->assertSame('fr', $controller->resolvePreferredLocaleForTest($request));
    }

    public function testBrowserLocaleUsedWhenProfileLocaleMissing(): void
    {
        $controller = new LocalePrecedenceTestController();
        $controller->setUserAndProfileForTest((object) ['id' => 1002], ['locale' => null]);

        $request = $this->mockRequestWithHeader('es-ES,es;q=0.9');

        $this->assertSame('es', $controller->resolvePreferredLocaleForTest($request));
    }

    public function testBrowserLocaleUsedForGuests(): void
    {
        $controller = new LocalePrecedenceTestController();
        $controller->setUserAndProfileForTest(null, null);

        $request = $this->mockRequestWithHeader('fr-FR,fr;q=0.9');

        $this->assertSame('fr', $controller->resolvePreferredLocaleForTest($request));
    }

    private function mockRequestWithHeader(string $acceptLanguage): RequestInterface
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getHeaderLine')
            ->with('Accept-Language')
            ->willReturn($acceptLanguage);

        return $request;
    }
}
