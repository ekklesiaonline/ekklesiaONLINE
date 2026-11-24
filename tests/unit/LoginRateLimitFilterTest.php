<?php

use App\Filters\LoginRateLimitFilter;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\Mock\MockCache;
use Config\Services;

/**
 * Behavioral tests for LoginRateLimitFilter.
 *
 * @internal
 */
final class LoginRateLimitFilterTest extends CIUnitTestCase
{
    private const LIMIT = 10;

    private LoginRateLimitFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetServices();
        Services::injectMock('cache', new MockCache());
        $this->filter = new LoginRateLimitFilter();
    }

    protected function tearDown(): void
    {
        $this->resetServices();
        parent::tearDown();
    }

    private function makeRequest(string $method, string $path, string $ip = '203.0.113.7'): IncomingRequest
    {
        $request = $this->createStub(IncomingRequest::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getIPAddress')->willReturn($ip);
        $request->method('getPath')->willReturn($path);

        return $request;
    }

    private function exhaust(string $path): void
    {
        for ($i = 0; $i < self::LIMIT; $i++) {
            $this->assertNull(
                $this->filter->before($this->makeRequest('POST', $path)),
                "POST #" . ($i + 1) . " to {$path} should be allowed."
            );
        }
    }

    public function testRejectsPostAfterThresholdWithRetryAfter(): void
    {
        $this->exhaust('connect/abc123');

        $result = $this->filter->before($this->makeRequest('POST', 'connect/abc123'));

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertSame(429, $result->getStatusCode());
        $this->assertSame('60', $result->getHeaderLine('Retry-After'));
    }

    public function testGetRequestsAreNeverThrottled(): void
    {
        $this->exhaust('connect/abc123');

        $this->assertNull($this->filter->before($this->makeRequest('GET', 'connect/abc123')));
    }

    public function testGetVerifyMagicLinkIsThrottled(): void
    {
        // GET login/verify-magic-link performs a token lookup + delete per
        // request — it is the one non-POST path this filter must throttle.
        for ($i = 0; $i < self::LIMIT; $i++) {
            $this->assertNull(
                $this->filter->before($this->makeRequest('GET', 'login/verify-magic-link')),
                "GET #" . ($i + 1) . " to login/verify-magic-link should be allowed."
            );
        }

        $result = $this->filter->before($this->makeRequest('GET', 'login/verify-magic-link'));

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertSame(429, $result->getStatusCode());
    }

    public function testGetVerifyMagicLinkSharesLoginBucket(): void
    {
        $this->exhaust('login');

        $result = $this->filter->before($this->makeRequest('GET', 'login/verify-magic-link'));

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertSame(429, $result->getStatusCode());
    }

    public function testOtherGetRequestsStillBypassWhenLoginBucketExhausted(): void
    {
        $this->exhaust('login');

        // Only login/verify-magic-link is throttled on GET; form-rendering
        // GETs must still pass through.
        $this->assertNull($this->filter->before($this->makeRequest('GET', 'login')));
        $this->assertNull($this->filter->before($this->makeRequest('GET', 'login/magic-link')));
        $this->assertNull($this->filter->before($this->makeRequest('GET', 'register')));
    }

    public function testFilterConfigAppliesToVerifyMagicLinkEndpoint(): void
    {
        $config = new \Config\Filters();

        $this->assertContains(
            'login/verify-magic-link',
            $config->filters['loginRateLimit']['before'] ?? [],
            'loginRateLimit must cover login/verify-magic-link.'
        );
    }

    public function testConnectQuotaIsSharedAcrossParishTokens(): void
    {
        $this->exhaust('connect/parish-a');

        $result = $this->filter->before($this->makeRequest('POST', 'connect/parish-b'));

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertSame(429, $result->getStatusCode());
    }

    public function testExhaustedConnectQuotaDoesNotBlockLogin(): void
    {
        $this->exhaust('connect/abc123');

        $this->assertNull($this->filter->before($this->makeRequest('POST', 'login')));
        $this->assertNull($this->filter->before($this->makeRequest('POST', 'session/device-verify')));
    }

    public function testExhaustedLoginQuotaDoesNotBlockConnect(): void
    {
        $this->exhaust('login');

        $this->assertNull($this->filter->before($this->makeRequest('POST', 'connect/abc123')));
    }

    public function testAuthEndpointsShareOneBucket(): void
    {
        $this->exhaust('login');

        $result = $this->filter->before($this->makeRequest('POST', 'register'));

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertSame(429, $result->getStatusCode());
    }

    public function testFilterConfigAppliesToConnectEndpoint(): void
    {
        $config = new \Config\Filters();

        $this->assertArrayHasKey('loginRateLimit', $config->filters);
        $this->assertContains(
            'connect/*',
            $config->filters['loginRateLimit']['before'] ?? [],
            'loginRateLimit must cover connect/*.'
        );
    }

    public function testBucketsArePerIp(): void
    {
        $this->exhaust('connect/abc123');

        $this->assertNull(
            $this->filter->before($this->makeRequest('POST', 'connect/abc123', '198.51.100.9'))
        );
    }
}
