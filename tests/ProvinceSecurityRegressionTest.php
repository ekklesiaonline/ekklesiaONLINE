<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regression coverage for the Province module security audit fixes.
 *
 * Route/filter wiring is asserted at source level (filters do not run in
 * the testing environment); service-level behavior is exercised directly
 * where feasible.
 */
final class ProvinceSecurityRegressionTest extends CIUnitTestCase
{
    public function testLegacyCalendarPostRoutesCarryEventPermissions(): void
    {
        $routes = file_get_contents(ROOTPATH . 'Province/Config/Routes.php');

        $this->assertIsString($routes);
        $this->assertStringContainsString(
            "\$routes->post('calendar/create', '\\\\Province\\\\Controllers\\\\ProvinceEventsManage::store', ['filter' => 'permission:province.event.create'])",
            $routes
        );
        $this->assertStringContainsString(
            "\$routes->post('calendar/edit/(:num)', '\\\\Province\\\\Controllers\\\\ProvinceEventsManage::update/\$1', ['filter' => 'permission:province.event.edit'])",
            $routes
        );
        $this->assertStringContainsString(
            "\$routes->post('calendar/delete/(:num)', '\\\\Province\\\\Controllers\\\\ProvinceEventsManage::delete/\$1', ['filter' => 'permission:province.event.delete'])",
            $routes
        );
    }

    public function testDioceseAdminReactivationIsPostOnly(): void
    {
        $routes = file_get_contents(ROOTPATH . 'Province/Config/Routes.php');

        $this->assertIsString($routes);
        $this->assertStringNotContainsString(
            "\$routes->get('diocese-admins/reactivate",
            $routes,
            'reactivate must not be registered as a GET (state-changing read)'
        );
        $this->assertStringContainsString(
            "\$routes->post('diocese-admins/reactivate/(:num)'",
            $routes
        );
    }

    public function testProvinceMutationEndpointsAreRateLimited(): void
    {
        $filters = file_get_contents(APPPATH . 'Config/Filters.php');
        $limits = file_get_contents(APPPATH . 'Filters/ApiRateLimitFilter.php');

        $this->assertIsString($filters);
        $this->assertIsString($limits);

        foreach ([
            'province/diocese-admins/*',
            'province/email-settings',
            'province/events/store',
            'province/calendar/create',
            'province/calendar/delete/*',
            'province/event-calendars/store',
            'province/quick-create-parish',
        ] as $uri) {
            $this->assertStringContainsString("'{$uri}'", $filters, "{$uri} missing from apiRateLimit before list");
        }

        $this->assertStringContainsString('#^province/diocese-admins/resend-invitation/#', $limits);
        $this->assertStringContainsString('#^province/diocese-admins/#', $limits);
        $this->assertStringContainsString('#^province/email-settings$#', $limits);
        $this->assertStringContainsString('#^province/(events|calendar|event-calendars)/#', $limits);
        // Resend invitations must share one per-identity bucket, not a
        // per-target quota (credential-mail bombing).
        $this->assertStringContainsString("'province/diocese-admins/resend-invitation'", $limits);
    }

    public function testTestMailerNeverMixesStoredPasswordWithSubmittedHost(): void
    {
        $service = file_get_contents(APPPATH . 'Services/EmailSettingsService.php');

        $this->assertIsString($service);

        // The stored-pass fallback inside the submitted-SMTP branch is the
        // exfiltration primitive: submitted host + persisted credential.
        $this->assertStringNotContainsString(
            "\$pass = (string) (\$this->tierSetting(\$scope, 'email.smtp_pass') ?? '');",
            $service,
            'buildTestMailer must not fall back to the stored smtp_pass'
        );
        // smtp_crypto must be constrained to the known values.
        $this->assertStringContainsString("['tls', 'ssl', '']", $service);
    }

    public function testProvinceDioceseAdminMutationsApplyHierarchyGuard(): void
    {
        $controller = file_get_contents(APPPATH . 'Controllers/ProvinceDioceseAdmins.php');

        $this->assertIsString($controller);
        $this->assertStringContainsString('use AdminHierarchyGuard;', $controller);
        $this->assertSame(
            5,
            substr_count($controller, 'guardTargetUserMutation($userId)'),
            'update, resetPassword, resendInvitation, deactivate and reactivate must each call the guard once'
        );
    }

    public function testAdminPasswordResetsForceRotation(): void
    {
        foreach ([
            APPPATH . 'Controllers/ProvinceDioceseAdmins.php',
            APPPATH . 'Controllers/DioceseAdminUsers.php',
            APPPATH . 'Services/SystemAdminUserService.php',
        ] as $file) {
            $source = file_get_contents($file);
            $this->assertIsString($source);
            $this->assertStringNotContainsString(
                "'force_reset' => 0",
                $source,
                basename($file) . ' still leaves an admin-known password usable'
            );
        }
    }

    public function testPublishAtEpochFallbackRemoved(): void
    {
        foreach ([
            ROOTPATH . 'Province/Controllers/ProvinceEventsManage.php',
            APPPATH . 'Controllers/SystemEvents.php',
            ROOTPATH . 'Clergy/Controllers/Events.php',
        ] as $file) {
            $source = file_get_contents($file);
            $this->assertIsString($source);
            $this->assertStringNotContainsString(
                '(int) strtotime($publishAt)',
                $source,
                basename($file) . ' still collapses unparseable publish_at to the epoch'
            );
            $this->assertStringContainsString('valid_date[Y-m-d', $source);
        }
    }

    public function testReportExportEscapesSpreadsheetFormulas(): void
    {
        $controller = new \Province\Controllers\Dashboard();

        $method = new \ReflectionMethod($controller, 'csvSafeCell');

        $this->assertSame("'=SUM(1)", $method->invoke($controller, '=SUM(1)'));
        $this->assertSame("'+evil", $method->invoke($controller, '+evil'));
        $this->assertSame("'-10", $method->invoke($controller, '-10'));
        $this->assertSame("'@name", $method->invoke($controller, '@name'));
        $this->assertSame('Normal Diocese', $method->invoke($controller, 'Normal Diocese'));
        $this->assertSame('', $method->invoke($controller, ''));
        $this->assertSame(42, $method->invoke($controller, 42));
    }

    public function testGetCalendarDoesNotPublishDueDrafts(): void
    {
        $controller = file_get_contents(ROOTPATH . 'Province/Controllers/Events.php');

        $this->assertIsString($controller);
        $this->assertStringNotContainsString(
            'publishDueDraftEvents()',
            $controller,
            'GET calendar handler must not trigger a global state mutation'
        );

        $command = file_get_contents(APPPATH . 'Commands/PublishDueEvents.php');
        $this->assertIsString($command);
        $this->assertStringContainsString('events:publish-due', $command);
    }

    public function testToastsRenderMessageAsText(): void
    {
        foreach ([
            ROOTPATH . 'Province/Views/layouts/main.php',
            APPPATH . 'Views/layouts/main.php',
            APPPATH . 'Views/layouts/public.php',
        ] as $file) {
            $source = file_get_contents($file);
            $this->assertIsString($source);
            $this->assertStringContainsString('textContent', $source, basename($file) . ' does not render the toast body as text');
            $this->assertStringNotContainsString(
                "'<div class=\"toast-body\">' + ",
                $source,
                basename($file) . ' still interpolates the message into innerHTML'
            );
        }
    }

    public function testQuickCreateParishRequiresParishEditGrant(): void
    {
        $controller = file_get_contents(ROOTPATH . 'Province/Controllers/Dashboard.php');

        $this->assertIsString($controller);
        $this->assertStringContainsString("checkPermission('parishes.edit')", $controller);
        $this->assertStringContainsString("'phone' => 'permit_empty|max_length[50]'", $controller);
        $this->assertStringContainsString("'address' => 'permit_empty|max_length[500]'", $controller);
    }
}
