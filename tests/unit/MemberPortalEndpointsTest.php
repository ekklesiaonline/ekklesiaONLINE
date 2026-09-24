<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class MemberPortalEndpointsTest extends CIUnitTestCase
{
    public function testPeopleMemberEndpointsAreRoutable(): void
    {
        $routesFile = file_get_contents(ROOTPATH . 'MemberPortal/Config/Routes.php');
        $this->assertNotFalse($routesFile);

        // Controller namespaces with escaped backslashes in the route file
        $expectedGetRoutes = [
            'AnnualPledges::index',
            'LeadershipPositions::index',
            'Standing::index',
        ];

        foreach ($expectedGetRoutes as $signature) {
            $this->assertStringContainsString($signature, $routesFile, "Expected to find $signature in routes");
        }
    }

    public function testPeopleMemberPostEndpointsAreRoutable(): void
    {
        $routesFile = file_get_contents(ROOTPATH . 'MemberPortal/Config/Routes.php');
        $this->assertNotFalse($routesFile);

        // POST endpoints for the new modules
        $expectedPostRoutes = [
            'AnnualPledges::store',
            'AnnualPledges::update',
            'AnnualPledges::delete',
            'LeadershipPositions::store',
            'LeadershipPositions::update',
            'LeadershipPositions::delete',
            'Standing::refresh',
        ];

        foreach ($expectedPostRoutes as $signature) {
            $this->assertStringContainsString($signature, $routesFile, "Expected to find $signature in routes");
        }
    }
}
