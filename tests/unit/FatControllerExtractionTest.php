<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regression guards for M1 (fat-controller extraction) and M3 (DI).
 *
 * M1: Clergy\Controllers\People delegates DB logic to PeopleViewDataService
 * instead of using db_connect() directly.
 * M3: PeopleViewDataService is registered in app/Config/Services.php and
 * injected into the controller via service('peopleViewData').
 *
 * @internal
 */
final class FatControllerExtractionTest extends CIUnitTestCase
{
    /**
     * M1: PeopleViewDataService exists.
     */
    public function testPeopleViewDataServiceExists(): void
    {
        $this->assertTrue(class_exists(\App\Services\PeopleViewDataService::class));
    }

    /**
     * M1: PeopleViewDataService has the extracted methods.
     */
    public function testPeopleViewDataServiceHasExtractedMethods(): void
    {
        $this->assertTrue(method_exists(\App\Services\PeopleViewDataService::class, 'ensureProfilePersonLink'));
        $this->assertTrue(method_exists(\App\Services\PeopleViewDataService::class, 'enrichStatusHistory'));
    }

    /**
     * M3: PeopleViewDataService is registered in Services.php.
     */
    public function testPeopleViewDataServiceRegisteredInServices(): void
    {
        $this->assertTrue(method_exists(\Config\Services::class, 'peopleViewData'));

        $service = service('peopleViewData');
        $this->assertInstanceOf(\App\Services\PeopleViewDataService::class, $service);
    }

    /**
     * M3: PeopleViewDataService uses constructor injection with fallbacks.
     */
    public function testPeopleViewDataServiceUsesConstructorInjection(): void
    {
        $source = file_get_contents(APPPATH . 'Services/PeopleViewDataService.php');

        $this->assertStringContainsString(
            '?BaseConnection $db = null',
            $source,
            'PeopleViewDataService should accept BaseConnection via constructor.'
        );
        $this->assertStringContainsString(
            '?PersonProfileSyncService $profileSyncService = null',
            $source,
            'PeopleViewDataService should accept PersonProfileSyncService via constructor.'
        );
    }

    /**
     * M1: Clergy\Controllers\People no longer has db_connect() in
     * ensureProfilePersonLink or enrichStatusHistory.
     */
    public function testPeopleControllerDelegatesToService(): void
    {
        $source = file_get_contents(ROOTPATH . 'Clergy/Controllers/People.php');

        $this->assertStringContainsString('peopleViewDataService', $source);
        $this->assertStringContainsString(
            'peopleViewDataService->ensureProfilePersonLink',
            $source,
            'Controller should delegate ensureProfilePersonLink to the service.'
        );
        $this->assertStringContainsString(
            'peopleViewDataService->enrichStatusHistory',
            $source,
            'Controller should delegate enrichStatusHistory to the service.'
        );
    }

    /**
     * M1: The controller's ensureProfilePersonLink method is now a thin
     * delegation (no direct DB queries).
     */
    public function testControllerEnsureProfilePersonLinkIsThinDelegation(): void
    {
        $source = file_get_contents(ROOTPATH . 'Clergy/Controllers/People.php');

        // The delegating method should not contain tableExists or table('profile')
        // (those are now in the service)
        $ensureMethodStart = strpos($source, 'private function ensureProfilePersonLink');
        $this->assertNotFalse($ensureMethodStart);

        $ensureMethodEnd = strpos($source, "\n    }", $ensureMethodStart);
        $this->assertNotFalse($ensureMethodEnd);

        $ensureMethodBody = substr($source, $ensureMethodStart, $ensureMethodEnd - $ensureMethodStart);

        $this->assertStringNotContainsString('tableExists', $ensureMethodBody, 'Delegating method should not contain tableExists.');
        $this->assertStringNotContainsString("table('profile')", $ensureMethodBody, 'Delegating method should not contain direct profile table queries.');
        $this->assertStringNotContainsString("table('person')", $ensureMethodBody, 'Delegating method should not contain direct person table queries.');
    }

    /**
     * M3: PeopleViewDataService is shared by default (getSharedInstance).
     */
    public function testPeopleViewDataServiceIsShared(): void
    {
        $service1 = service('peopleViewData');
        $service2 = service('peopleViewData');

        $this->assertSame($service1, $service2, 'PeopleViewDataService should be a shared instance.');
    }
}
