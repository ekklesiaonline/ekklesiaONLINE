<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class DormantSchemaRouteGuardTest extends CIUnitTestCase
{
    public function testDormantDomainsAreNotExposedByModuleRoutes(): void
    {
        $routeFiles = [
            ROOTPATH . 'app/Config/Routes.php',
            ROOTPATH . 'Clergy/Config/Routes.php',
            ROOTPATH . 'Diocese/Config/Routes.php',
            ROOTPATH . 'MemberPortal/Config/Routes.php',
            ROOTPATH . 'Treasurer/Config/Routes.php',
            ROOTPATH . 'Volunteer/Config/Routes.php',
            ROOTPATH . 'Worship/Config/Routes.php',
            ROOTPATH . 'Province/Config/Routes.php',
        ];

        $dormantRouteKeywords = [
            'facility',
            'vendor',
            'bank-',
            'journal',
            'communication-campaign',
            'notification',
            'portal-access-request',
            'pre-registration',
            'worship-template',
            'person-address',
            'person-contact',
            'electoral-roll',
            'sacramental-record',
            'ministry-role',
            'governance-term',
        ];

        foreach ($routeFiles as $filePath) {
            $content = file_get_contents($filePath);
            $this->assertNotFalse($content, 'Unable to read route file: ' . $filePath);

            foreach ($dormantRouteKeywords as $keyword) {
                $this->assertStringNotContainsString(
                    $keyword,
                    strtolower((string) $content),
                    'Dormant domain route keyword should not be exposed before implementation: ' . $keyword . ' in ' . $filePath
                );
            }
        }
    }
}
