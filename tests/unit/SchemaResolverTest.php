<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regression guards for M4 (schema resolver consolidation).
 *
 * Verifies that the shared SchemaResolver utility exists and that
 * the previously-duplicated helpers in MemberAccessService,
 * NonPortalMemberProvisioningService, and MemberMergeService now
 * delegate to it instead of containing copy-pasted logic.
 * (ParishPeopleRegistryService was retired in T4; PeopleDirectoryService
 * now queries canonical tables directly.)
 *
 * @internal
 */
final class SchemaResolverTest extends CIUnitTestCase
{
    /**
     * SchemaResolver class exists and is in the correct namespace.
     */
    public function testSchemaResolverClassExists(): void
    {
        $this->assertTrue(class_exists(\App\Libraries\SchemaResolver::class));
    }

    /**
     * SchemaResolver has the expected public methods.
     */
    public function testSchemaResolverHasExpectedMethods(): void
    {
        $methods = [
            'getColumnsForTable',
            'resolveExistingTable',
            'resolveExistingProfileTable',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(\App\Libraries\SchemaResolver::class, $method),
                "SchemaResolver must have method: {$method}"
            );
        }
    }

    /**
     * M4: MemberAccessService delegates to SchemaResolver, not inline code.
     */
    public function testMemberAccessServiceDelegatesToSchemaResolver(): void
    {
        $source = file_get_contents(APPPATH . 'Services/MemberAccessService.php');

        $this->assertStringContainsString('SchemaResolver', $source);
        $this->assertStringContainsString(
            'schemaResolver->getColumnsForTable',
            $source,
            'MemberAccessService::getColumnsForTable must delegate to SchemaResolver.'
        );
        $this->assertStringContainsString(
            'schemaResolver->resolveExistingProfileTable',
            $source,
            'MemberAccessService::resolveExistingProfileTable must delegate to SchemaResolver.'
        );
    }

    /**
     * M4: NonPortalMemberProvisioningService delegates to SchemaResolver.
     */
    public function testNonPortalMemberProvisioningServiceDelegates(): void
    {
        $source = file_get_contents(APPPATH . 'Services/NonPortalMemberProvisioningService.php');

        $this->assertStringContainsString('SchemaResolver', $source);
        $this->assertStringContainsString(
            'schemaResolver->getColumnsForTable',
            $source,
            'NonPortalMemberProvisioningService::getColumnsForTable must delegate to SchemaResolver.'
        );
    }

    /**
     * M4: MemberMergeService delegates to SchemaResolver.
     */
    public function testMemberMergeServiceDelegates(): void
    {
        $source = file_get_contents(APPPATH . 'Services/MemberMergeService.php');

        $this->assertStringContainsString('SchemaResolver', $source);
        $this->assertStringContainsString(
            'schemaResolver->resolveExistingProfileTable',
            $source,
            'MemberMergeService::resolveExistingProfileTable must delegate to SchemaResolver.'
        );
    }

    /**
     * T4: The parish_people snapshot service is retired — it must not
     * exist alongside canonical directory reads.
     */
    public function testParishPeopleRegistryServiceIsRetired(): void
    {
        $this->assertFalse(
            file_exists(APPPATH . 'Services/ParishPeopleRegistryService.php'),
            'ParishPeopleRegistryService must be removed (T4).'
        );
        $this->assertFalse(
            file_exists(APPPATH . 'Models/ParishPeopleModel.php'),
            'ParishPeopleModel must be removed (T4).'
        );
    }
}
