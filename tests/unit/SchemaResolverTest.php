<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regression guards for M4 (schema resolver consolidation).
 *
 * Verifies that the shared SchemaResolver utility exists and that
 * the previously-duplicated helpers in MemberAccessService,
 * NonPortalMemberProvisioningService, MemberMergeService,
 * ParishPeopleRegistryService, and PeopleDirectoryService now
 * delegate to it instead of containing copy-pasted logic.
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
            'resolveSnapshotTable',
            'resolveSnapshotScopeColumn',
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
     * M4: ParishPeopleRegistryService delegates to SchemaResolver.
     */
    public function testParishPeopleRegistryServiceDelegates(): void
    {
        $source = file_get_contents(APPPATH . 'Services/ParishPeopleRegistryService.php');

        $this->assertStringContainsString('SchemaResolver', $source);
        $this->assertStringContainsString('schemaResolver->getColumnsForTable', $source);
        $this->assertStringContainsString('schemaResolver->resolveExistingTable', $source);
        $this->assertStringContainsString('schemaResolver->resolveSnapshotTable', $source);
        $this->assertStringContainsString('schemaResolver->resolveSnapshotScopeColumn', $source);
    }

    /**
     * M4: PeopleDirectoryService delegates to SchemaResolver.
     */
    public function testPeopleDirectoryServiceDelegates(): void
    {
        $source = file_get_contents(APPPATH . 'Services/PeopleDirectoryService.php');

        $this->assertStringContainsString('SchemaResolver', $source);
        $this->assertStringContainsString('schemaResolver->resolveSnapshotTable', $source);
        $this->assertStringContainsString('schemaResolver->resolveSnapshotScopeColumn', $source);
    }

    /**
     * M4: The dead duplicate branch in ParishPeopleRegistryService
     * (['parish_people', 'parish_people'] and the triple parish_id check)
     * has been removed.
     */
    public function testNoDeadDuplicateSnapshotCandidates(): void
    {
        $source = file_get_contents(APPPATH . 'Services/ParishPeopleRegistryService.php');

        // The old code had ['parish_people', 'parish_people'] — same value twice.
        $this->assertStringNotContainsString(
            "'parish_people', 'parish_people'",
            $source,
            'ParishPeopleRegistryService must not have the dead duplicate parish_people candidates.'
        );
    }

    /**
     * M4: SchemaResolver::resolveSnapshotScopeColumn always returns 'parish_id'.
     */
    public function testSnapshotScopeColumnAlwaysReturnsParishId(): void
    {
        $resolver = new \App\Libraries\SchemaResolver();

        $this->assertSame('parish_id', $resolver->resolveSnapshotScopeColumn('parish_people'));
        $this->assertSame('parish_id', $resolver->resolveSnapshotScopeColumn('any_table'));
    }
}
