<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Phase 1/3 route contract: canonical /visitors/* endpoints exist,
 * legacy /guests/* GETs redirect, legacy POSTs alias to visitor actions,
 * and retired conversion URLs redirect to the directory's converted filter.
 *
 * @internal
 */
final class VisitorRouteShimTest extends CIUnitTestCase
{
    private string $clergyRoutes;
    private string $portalRoutes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clergyRoutes = (string) file_get_contents(ROOTPATH . 'Clergy/Config/Routes.php');
        $this->portalRoutes = (string) file_get_contents(ROOTPATH . 'MemberPortal/Config/Routes.php');
    }

    public function testCanonicalVisitorRoutesExist(): void
    {
        foreach ([
            "group('visitors'",
            "get('view/(:num)'",
            "post('store'",
            "post('update/(:num)'",
            "post('delete/(:num)'",
            "post('promote/(:num)'",
            "post('revert-conversion/(:num)'",
            "post('promote-attender/(:num)'",
            "post('demote/(:num)'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $this->clergyRoutes, "Missing canonical route: {$needle}");
        }
    }

    public function testVisitorRoutesArePermissionFiltered(): void
    {
        $this->assertMatchesRegularExpression(
            "/post\('promote\/\(:num\)'[^;]*visitor\.edit/",
            $this->clergyRoutes
        );
        $this->assertMatchesRegularExpression(
            "/post\('delete\/\(:num\)'[^;]*visitor\.delete/",
            $this->clergyRoutes
        );
        $this->assertMatchesRegularExpression(
            "/post\('store'[^;]*visitor\.create/",
            $this->clergyRoutes
        );
    }

    public function testLegacyGuestGetsRedirect(): void
    {
        // List + retired mutating GETs redirect; catch-all covers the rest.
        $this->assertStringContainsString("get('guests',", $this->clergyRoutes);
        $this->assertStringContainsString("get('guests/convertToMember/(:num)'", $this->clergyRoutes);
        $this->assertStringContainsString("get('guests/create-account/(:num)'", $this->clergyRoutes);
        $this->assertStringContainsString("get('guests/(:any)'", $this->clergyRoutes);

        // The retired GETs must not invoke controller mutations.
        $this->assertStringNotContainsString("get('guests/convertToMember/(:num)', '\\Clergy", $this->clergyRoutes);
    }

    public function testLegacyGuestPostsAliasVisitorActions(): void
    {
        foreach ([
            "post('guests/store'",
            "post('guests/update/(:num)'",
            "post('guests/delete/(:num)'",
            "post('guests/followup-trigger/(:num)'",
            "post('guests/assign-sequence/(:num)'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $this->clergyRoutes, "Missing legacy alias: {$needle}");
        }

        $this->assertStringContainsString("post('guests/store', '\\\\Clergy\\\\Controllers\\\\Visitors::store'", $this->clergyRoutes);
    }

    public function testRetiredConversionPagesRedirectToConvertedFilter(): void
    {
        $this->assertStringContainsString("get('guest-conversions'", $this->portalRoutes);
        $this->assertStringContainsString("get('visitor-conversions'", $this->portalRoutes);
        $this->assertStringContainsString('visitors?lifecycle=converted', $this->portalRoutes);
        $this->assertStringNotContainsString('VisitorConversions::', $this->portalRoutes);
    }

    public function testPermissionMigrationRenamesGuestToVisitor(): void
    {
        $migration = (string) file_get_contents(
            ROOTPATH . 'app/Database/Migrations/2026_09_22_000001_RenameGuestPermissionsToVisitor.php'
        );

        $this->assertStringContainsString('guest.view', $migration);
        $this->assertStringContainsString('visitor.view', $migration);
        $this->assertStringContainsString('auth_permissions', $migration);
        $this->assertStringContainsString('auth_groups_permissions', $migration);
        $this->assertStringContainsString('auth_permissions_users', $migration);
        $this->assertStringContainsString('function down', $migration);
    }
}
