<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * M7: Legacy migration inventory & observability.
 *
 * Inventories all `profile`, `visitor`, `church_people`, and dynamic
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
     * T4.5: The legacy `guest`/`guest_visit` module store is retired.
     *
     * Classification: retired
     * Guest rows were migrated to person + parish_affiliation
     * (membership_status = 'visitor') + person_visit; GuestModel is removed and
     * reads go through VisitorDirectoryService over canonical tables.
     */
    public function testGuestTableUsageRetired(): void
    {
        $this->assertFalse(
            file_exists(APPPATH . 'Models/GuestModel.php'),
            'GuestModel must be removed (T4.5).'
        );
        $this->assertTrue(
            file_exists(APPPATH . 'Services/VisitorDirectoryService.php'),
            'VisitorDirectoryService must provide canonical visitor-shaped reads (T4.5).'
        );

        $service = file_get_contents(APPPATH . 'Services/VisitorDirectoryService.php');
        $this->assertStringNotContainsString(
            "table('guest'",
            $service,
            'VisitorDirectoryService must not query the dropped guest table.'
        );
        $this->assertStringNotContainsString(
            "table('visitor'",
            $service,
            'VisitorDirectoryService must not query a nonexistent visitor table.'
        );
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
     * T4: The parish_people snapshot table resolution is retired.
     *
     * Classification: retired
     * The snapshot model, registry service, and sync commands were removed;
     * directory reads now come from parish_affiliation + person.
     */
    public function testParishPeopleSnapshotRetired(): void
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
     * M7: SchemaResolver is the single source of truth for table resolution.
     *
     * Classification: consolidation (M4)
     */
    public function testSchemaResolverIsSingleSourceOfTruth(): void
    {
        $this->assertTrue(class_exists(\App\Libraries\SchemaResolver::class));

        $resolver = new \App\Libraries\SchemaResolver();
        $this->assertTrue(method_exists($resolver, 'resolveExistingProfileTable'));
        $this->assertTrue(method_exists($resolver, 'getColumnsForTable'));
    }
}
