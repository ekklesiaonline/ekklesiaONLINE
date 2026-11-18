<?php

use App\Filters\ApiRateLimitFilter;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\Mock\MockCache;
use Config\Services;

/**
 * Regression coverage for the Diocese module security audit (rate limits).
 *
 * Asserts every diocese-owned mutation route is inside the apiRateLimit
 * `before` list, that the list entries actually match the concrete routes,
 * and that the strictest tiers (outbound credential mail) throttle at 429.
 *
 * @internal
 */
final class DioceseRateLimitCoverageTest extends CIUnitTestCase
{
    private static function filtersSection(string $filterName): string
    {
        $src     = file_get_contents(ROOTPATH . 'app/Config/Filters.php');
        $pattern = '/\'' . preg_quote($filterName, '/') . '\'\s*=>\s*\[\s*\'before\'\s*=>\s*\[(.*?)\]/s';
        self::assertSame(1, preg_match($pattern, $src, $m), 'missing ' . $filterName . ' before list');
        return $m[1];
    }

    /**
     * Concrete POST URIs declared in Diocese/Config/Routes.php and the
     * parish_list group of app/Config/Routes.php.
     *
     * @return list<string>
     */
    private static function concreteMutationUris(): array
    {
        return [
            // diocese/clergy-review/*
            'diocese/clergy-review/candidates/5/review/9',
            'diocese/clergy-review/ordination/5',
            'diocese/clergy-review/standing/5',
            'diocese/clergy-review/standing/5/lift',
            'diocese/clergy-review/office/5/approve',
            'diocese/clergy-review/office/5/revoke',
            'diocese/clergy-review/mission-office/5/approve',
            'diocese/clergy-review/delegation/5',
            'diocese/clergy-review/parish-vacancy/5',
            'diocese/clergy-review/report-policy/5',
            'diocese/clergy-review/delegation/revoke/5',
            'diocese/clergy-review/appointment-requests/5/review/9',
            // diocese/clergy/*
            'diocese/clergy/store',
            'diocese/clergy/5/edit',
            'diocese/clergy/5/deactivate',
            'diocese/clergy/5/resend-invitation',
            // chart-accounts/*
            'chart-accounts/store',
            'chart-accounts/update/5',
            'chart-accounts/toggle/5',
            // diocese/{deaneries,congregations}
            'diocese/deaneries/store',
            'diocese/deaneries/assign/5',
            'diocese/deaneries/end/5',
            'diocese/congregations/5/type',
            // diocese/admins/* + diocese/parish-admins/*
            'diocese/admins/store',
            'diocese/admins/update/5',
            'diocese/admins/reset-password/5',
            'diocese/admins/delete/5',
            'diocese/admins/deactivate/5',
            'diocese/admins/reactivate/5',
            'diocese/parish-admins/store',
            'diocese/parish-admins/update/5',
            'diocese/parish-admins/reset-password/5',
            'diocese/parish-admins/delete/5',
            'diocese/parish-admins/deactivate/5',
            'diocese/parish-admins/reactivate/5',
            // diocese/followup-workflow/*
            'diocese/followup-workflow/sequences/save',
            'diocese/followup-workflow/sequences/save/5',
            'diocese/followup-workflow/sequences/toggle/5',
            'diocese/followup-workflow/sequences/delete/5',
            'diocese/followup-workflow/sequences/set-default/5',
            'diocese/followup-workflow/sequences/clone/5',
            'diocese/followup-workflow/actions/save',
            'diocese/followup-workflow/actions/save/5',
            'diocese/followup-workflow/actions/delete/5',
            'diocese/followup-workflow/templates/save',
            'diocese/followup-workflow/templates/save/5',
            'diocese/followup-workflow/templates/delete/5',
            // diocese/email-settings (save + test)
            'diocese/email-settings',
            'diocese/email-settings/test',
            // parish_list/* (canonical + staff)
            'parish_list/store',
            'parish_list/update/5',
            'parish_list/delete/5',
            'parish_list/bulk-delete',
            'parish_list/5/staff/add',
            'parish_list/5/staff/promote',
            'parish_list/5/staff/remove-role',
            'parish_list/5/staff/demote',
            // Diocese module writes served under the system group.
            'system/liturgical-source/store',
            'system/liturgical-source/update/5',
            'system/liturgical-source/delete/5',
        ];
    }

    /**
     * Expand a `before`-list entry (`*` wildcards) into a regex.
     */
    private static function entryToRegex(string $entry): string
    {
        $pattern = preg_quote($entry, '#');
        // CI4 filter URI wildcards span path segments (`foo/*` matches any
        // remainder), unlike single-segment globs.
        $pattern = str_replace('\*', '.*', $pattern);
        return '#^' . $pattern . '$#';
    }

    /**
     * Every concrete mutation URI must match at least one filter entry —
     * catches wildcard typos that would silently leave a route unthrottled.
     */
    public function testEveryConcreteDioceseMutationMatchesAFilterEntry(): void
    {
        $section = self::filtersSection('apiRateLimit');
        preg_match_all("/'([^']+)'/", $section, $m);
        $entries = $m[1];
        $this->assertNotEmpty($entries);

        foreach (self::concreteMutationUris() as $uri) {
            $matched = false;
            foreach ($entries as $entry) {
                if (preg_match(self::entryToRegex($entry), $uri) === 1) {
                    $matched = true;
                    break;
                }
            }
            $this->assertTrue($matched, 'unthrottled diocese mutation route: ' . $uri);
        }
    }

    private ApiRateLimitFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetServices();
        Services::injectMock('cache', new MockCache());
        $this->filter = new ApiRateLimitFilter();
    }

    protected function tearDown(): void
    {
        $this->resetServices();
        parent::tearDown();
    }

    private function makeRequest(string $method, string $path, string $ip = '203.0.113.7'): IncomingRequest
    {
        $request = $this->createMock(IncomingRequest::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getIPAddress')->willReturn($ip);
        $request->method('getPath')->willReturn($path);

        return $request;
    }

    /**
     * The invitation-resend tier caps at 10/min: the first 10 requests pass
     * and the 11th must return HTTP 429 with the contract error payload.
     */
    public function testResendInvitationBurstIsThrottled(): void
    {
        $path = 'diocese/clergy/7/resend-invitation';

        for ($i = 0; $i < 10; $i++) {
            $this->assertNull(
                $this->filter->before($this->makeRequest('POST', $path)),
                "POST #" . ($i + 1) . " to {$path} should be allowed."
            );
        }

        $response = $this->filter->before($this->makeRequest('POST', $path));

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(429, $response->getStatusCode());

        $payload = json_decode($response->getBody(), true);
        $this->assertSame('RATE_LIMITED', $payload['error']['code'] ?? null);
    }

    /**
     * Admin account mutations (credential mail) throttle at 15/min.
     */
    public function testAdminMutationBurstIsThrottled(): void
    {
        $path = 'diocese/admins/reset-password/3';

        for ($i = 0; $i < 15; $i++) {
            $this->assertNull(
                $this->filter->before($this->makeRequest('POST', $path)),
                "POST #" . ($i + 1) . " to {$path} should be allowed."
            );
        }

        $response = $this->filter->before($this->makeRequest('POST', $path));
        $this->assertSame(429, $response->getStatusCode());
    }
}
