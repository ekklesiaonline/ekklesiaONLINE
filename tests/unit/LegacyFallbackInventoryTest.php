<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * M7: Legacy migration inventory & observability.
 *
 * Inventories all `profile`, `guest`, `church_people`, and dynamic
 * table fallbacks across the codebase. Each fallback is classified as:
 * - active compatibility: still needed for current behavior
 * - migration-only: only referenced in migration backfill code
 * - dead code: no longer reachable
 *
 * This test serves as a living document of legacy fallback usage.
 * No removal is done in this wave — the inventory is for planning only.
 *
 * @internal
 */
final class LegacyFallbackInventoryTest extends CIUnitTestCase
{
    /**
     * M7: Inventory all services that still reference the legacy `guest` table.
     *
     * Classification: active compatibility
     * The guest table is still used by GuestIntakeService, PersonConversionService,
     * MembershipStatusService, and MemberMergeService for legacy guest records
     * that haven't been converted to the canonical person model yet.
     */
    public function testGuestTableUsageInventory(): void
    {
        $servicesUsingGuestTable = [
            'app/Services/PersonConversionService.php',
            'app/Services/MembershipStatusService.php',
            'app/Services/MemberMergeService.php',
        ];

        foreach ($servicesUsingGuestTable as $file) {
            $path = APPPATH . str_replace('app/', '', $file);
            if (! file_exists($path)) {
                continue;
            }

            $source = file_get_contents($path);
            $this->assertTrue(
                str_contains($source, 'guest') || str_contains($source, 'Guest'),
                "{$file} should reference the guest table."
            );
        }

        $this->addToAssertionCount(1);
    }

    /**
     * M7: Inventory all services that use resolveExistingProfileTable.
     *
     * Classification: active compatibility
     * The profile table has both singular (`profile`) and plural (`profiles`)
     * name variants across environments. SchemaResolver centralizes this.
     */
    public function testProfileTableFallbackInventory(): void
    {
        $servicesUsingProfileFallback = [
            'app/Services/MemberAccessService.php',
            'app/Services/MemberMergeService.php',
            'app/Services/ParishPeopleRegistryService.php',
            'app/Services/PeopleDirectoryService.php',
        ];

        foreach ($servicesUsingProfileFallback as $file) {
            $path = APPPATH . str_replace('app/', '', $file);
            if (! file_exists($path)) {
                continue;
            }

            $source = file_get_contents($path);
            // All should now delegate to SchemaResolver
            $this->assertStringContainsString(
                'SchemaResolver',
                $source,
                "{$file} should use SchemaResolver for profile table resolution."
            );
        }
    }

    /**
     * M7: Inventory the parish_people snapshot table resolution.
     *
     * Classification: active compatibility
     * The parish_people table is the denormalized snapshot used by
     * ParishPeopleRegistryService and PeopleDirectoryService.
     */
    public function testParishPeopleSnapshotInventory(): void
    {
        $servicesUsingSnapshot = [
            'app/Services/ParishPeopleRegistryService.php',
            'app/Services/PeopleDirectoryService.php',
        ];

        foreach ($servicesUsingSnapshot as $file) {
            $path = APPPATH . str_replace('app/', '', $file);
            if (! file_exists($path)) {
                continue;
            }

            $source = file_get_contents($path);
            $this->assertStringContainsString(
                'Snapshot',
                $source,
                "{$file} should reference the snapshot table."
            );
        }
    }

    /**
     * M7: The UserRegistrationRequestModel has its own table resolution
     * logic for the user_registration_request table (singular vs plural).
     *
     * Classification: active compatibility
     * This is model-specific and hasn't been consolidated into SchemaResolver
     * because it's tightly coupled to the model's initialize() lifecycle.
     */
    public function testUserRegistrationRequestModelTableResolution(): void
    {
        $source = file_get_contents(APPPATH . 'Models/UserRegistrationRequestModel.php');

        $this->assertStringContainsString(
            'resolveExistingTable',
            $source,
            'UserRegistrationRequestModel has its own table resolution for legacy compatibility.'
        );
    }

    /**
     * M7: The dead duplicate branch in ParishPeopleRegistryService
     * (['parish_people', 'parish_people'] and triple parish_id check)
     * has been removed as part of M4.
     *
     * Classification: dead code (removed)
     */
    public function testDeadDuplicateBranchRemoved(): void
    {
        $source = file_get_contents(APPPATH . 'Services/ParishPeopleRegistryService.php');

        $this->assertStringNotContainsString(
            "'parish_people', 'parish_people'",
            $source,
            'Dead duplicate parish_people candidates should be removed.'
        );
    }

    /**
     * M7: SchemaResolver is the single source of truth for table resolution.
     *
     * Classification: consolidation (M4)
     */
    public function testSchemaResolverIsSingleSourceOfTruth(): void
    {
        $this->assertTrue(class_exists(\App\Libraries\SchemaResolver::class));

        $resolver = new \App\Libraries\SchemaResolver();
        $this->assertTrue(method_exists($resolver, 'resolveExistingProfileTable'));
        $this->assertTrue(method_exists($resolver, 'resolveSnapshotTable'));
        $this->assertTrue(method_exists($resolver, 'getColumnsForTable'));
    }
}
