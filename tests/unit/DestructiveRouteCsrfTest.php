<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regression guard: state-mutating delete routes must use POST (not GET)
 * so that CI4's CSRF filter protects them against cross-site request
 * forgery. Read-only preview/impact routes may remain GET.
 *
 * @internal
 */
final class DestructiveRouteCsrfTest extends CIUnitTestCase
{
    /**
     * These routes mutate state and must be registered as POST.
     */
    public static function mutatingDeleteRouteProvider(): array
    {
        return [
            'system event-calendars delete' => [
                ROOTPATH . 'app/Config/Routes.php',
                "event-calendars/delete/(:num)",
                'post',
            ],
            'system events delete' => [
                ROOTPATH . 'app/Config/Routes.php',
                "delete/(:num)",
                'post',
            ],
            'province events delete' => [
                ROOTPATH . 'Province/Config/Routes.php',
                "events/delete/(:num)",
                'post',
            ],
            'province event-calendars delete' => [
                ROOTPATH . 'Province/Config/Routes.php',
                "event-calendars/delete/(:num)",
                'post',
            ],
        ];
    }

    /**
     * These routes are read-only previews and must remain GET.
     */
    public static function safeGetRouteProvider(): array
    {
        return [
            'parish delete-impact preview' => [
                ROOTPATH . 'app/Config/Routes.php',
                "delete-impact/(:num)",
                'get',
            ],
            'member dedup hard-delete confirmation' => [
                ROOTPATH . 'Clergy/Config/Routes.php',
                "hard-delete/(:num)",
                'get',
            ],
        ];
    }

    /**
     * Asserts that a specific route definition uses the expected HTTP verb.
     * Scans the route file content for the verb + path pattern.
     */
    private function assertRoutePresent(string $routeFile, string $pathPattern, string $expectedVerb): void
    {
        $this->assertFileExists($routeFile, "Route file not found: {$routeFile}");
        $content = file_get_contents($routeFile);
        $this->assertNotFalse($content, "Failed to read: {$routeFile}");

        $escapedPath = preg_quote($pathPattern, '/');
        $pattern = '/\$routes->(' . $expectedVerb . ')\(\s*[\'' . '"]' . $escapedPath . '[\'' . '"]\s*,/i';

        $this->assertMatchesRegularExpression(
            $pattern,
            $content,
            "Expected route {$pathPattern} to be registered as {$expectedVerb} in {$routeFile}, but did not find it."
        );
    }

    /**
     * Asserts the opposite verb is NOT present for the same path.
     */
    private function assertOppositeVerbAbsent(string $routeFile, string $pathPattern, string $expectedVerb): void
    {
        $content = file_get_contents($routeFile);
        $this->assertNotFalse($content);

        $oppositeVerb = $expectedVerb === 'post' ? 'get' : 'post';
        $escapedPath = preg_quote($pathPattern, '/');
        $oppositePattern = '/\$routes->(' . $oppositeVerb . ')\(\s*[\'' . '"]' . $escapedPath . '[\'' . '"]\s*,/i';

        $this->assertDoesNotMatchRegularExpression(
            $oppositePattern,
            $content,
            "Route {$pathPattern} should NOT be registered as {$oppositeVerb} in {$routeFile}."
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('mutatingDeleteRouteProvider')]
    public function testMutatingDeleteRoutesUsePost(string $routeFile, string $pathPattern, string $expectedVerb): void
    {
        $this->assertRoutePresent($routeFile, $pathPattern, $expectedVerb);
        $this->assertOppositeVerbAbsent($routeFile, $pathPattern, $expectedVerb);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('safeGetRouteProvider')]
    public function testSafePreviewRoutesRemainGet(string $routeFile, string $pathPattern, string $expectedVerb): void
    {
        // Safe preview routes must have a GET, but may also have a POST
        // for the actual mutation (e.g. confirmHardDelete GET + hardDelete POST).
        $this->assertRoutePresent($routeFile, $pathPattern, $expectedVerb);
    }

    /**
     * Views that link to these delete routes must use POST forms with csrf_field(),
     * not GET <a> tags.
     */
    public static function viewDeleteFormProvider(): array
    {
        return [
            'system event-calendars index' => [
                ROOTPATH . 'app/Views/System/EventCalendars/index.php',
                'system/event-calendars/delete/',
            ],
            'province event-calendars index' => [
                ROOTPATH . 'Province/Views/EventCalendars/index.php',
                'province/event-calendars/delete/',
            ],
            'system events index' => [
                ROOTPATH . 'app/Views/System/Events/index.php',
                'system/events/delete/',
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('viewDeleteFormProvider')]
    public function testViewUsesPostFormForDelete(string $viewFile, string $urlFragment): void
    {
        $this->assertFileExists($viewFile);
        $content = file_get_contents($viewFile);
        $this->assertNotFalse($content);

        // Must contain a POST form pointing at the delete URL.
        $this->assertStringContainsString(
            'method="post"',
            $content,
            "View {$viewFile} must use a POST form for delete."
        );
        $this->assertStringContainsString(
            $urlFragment,
            $content,
            "View {$viewFile} must reference {$urlFragment}."
        );
        $this->assertStringContainsString(
            'csrf_field()',
            $content,
            "View {$viewFile} must include csrf_field() in the delete form."
        );

        // Must NOT contain a raw <a> tag linking to the delete GET URL.
        $this->assertStringNotContainsString(
            'href="' . $urlFragment,
            $content,
            "View {$viewFile} must not use a GET <a> link for delete."
        );
    }
}
