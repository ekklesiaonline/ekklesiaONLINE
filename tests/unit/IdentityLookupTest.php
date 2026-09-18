<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regression guards for H2 (canonical synchronization) and H3 (identity lookups).
 *
 * H3: PersonMembershipDetailsService::resolveAgeBand() and
 * MemberDashboardDataService::resolveIdentity() must query profile by
 * person_id (the canonical person.id), not by profile.id via find().
 *
 * H2: Writes to canonical identity tables (person, profile, user_person,
 * parish_affiliation) must trigger PersonProfileSyncService where applicable.
 * (ParishPeopleRegistryService retired in T4 — no snapshot to sync.)
 *
 * @internal
 */
final class IdentityLookupTest extends CIUnitTestCase
{
    /**
     * H3: resolveAgeBand must query by person_id, not by profile primary key.
     *
     * The method receives a canonical person.id. Using ProfileModel->find()
     * would look up by profile.id (the PK), which is a different key.
     */
    public function testResolveAgeBandQueriesByPersonIdNotProfileId(): void
    {
        $reflection = new ReflectionMethod(
            \App\Services\PersonMembershipDetailsService::class,
            'resolveAgeBand'
        );
        $source = $this->getMethodSource($reflection);

        $this->assertStringContainsString(
            "where('person_id', \$personId)",
            $source,
            'resolveAgeBand must query profile by person_id, not by find($personId) which uses profile.id.'
        );
        $this->assertStringNotContainsString(
            '->find($personId)',
            $source,
            'resolveAgeBand must not use find($personId) — that looks up by profile.id, not person.id.'
        );
    }

    /**
     * H3: MemberDashboardDataService::resolveIdentity fallback must query
     * by person_id, not by profile primary key.
     */
    public function testResolveIdentityFallbackQueriesByPersonId(): void
    {
        $reflection = new ReflectionMethod(
            \App\Services\MemberDashboardDataService::class,
            'resolveIdentity'
        );
        $source = $this->getMethodSource($reflection);

        $this->assertStringContainsString(
            "where('person_id', \$personId)",
            $source,
            'resolveIdentity fallback must query profile by person_id, not by find($personId).'
        );
        $this->assertStringNotContainsString(
            '->find($personId)',
            $source,
            'resolveIdentity must not use find($personId) — that looks up by profile.id, not person.id.'
        );
    }

    /**
     * H2: PersonProvisioningService::provision must call
     * PersonProfileSyncService::syncFromProfile after creating a profile.
     */
    public function testPersonProvisioningTriggersCanonicalSync(): void
    {
        $reflection = new ReflectionMethod(
            \App\Services\PersonProvisioningService::class,
            'provision'
        );
        $source = $this->getMethodSource($reflection);

        $this->assertStringContainsString(
            'PersonProfileSyncService',
            $source,
            'PersonProvisioningService::provision must call PersonProfileSyncService to sync the canonical person.'
        );
        $this->assertStringContainsString(
            'syncFromProfile',
            $source,
            'PersonProvisioningService::provision must call syncFromProfile after creating a profile.'
        );
    }

    /**
     * H2: PersonProfileSyncService must link profile.person_id after creating
     * or resolving a canonical person.
     */
    public function testPersonProfileSyncServiceLinksProfileToPerson(): void
    {
        $reflection = new ReflectionMethod(
            \App\Services\PersonProfileSyncService::class,
            'linkProfileToPerson'
        );
        $source = $this->getMethodSource($reflection);

        $this->assertStringContainsString(
            "person_id",
            $source,
            'linkProfileToPerson must set profile.person_id to link the profile to the canonical person.'
        );
        $this->assertStringContainsString(
            'user_person',
            $source,
            'linkProfileToPerson must also create a user_person link when a user_id is present.'
        );
    }

    /**
     * Parish metrics: active_members must filter by the canonical
     * affiliation status 'member'. 'active' is not a valid
     * parish_affiliation.membership_status value and always counts 0.
     */
    public function testActiveMembersUsesMemberStatus(): void
    {
        $source = file_get_contents(APPPATH . 'Libraries/PeopleMetricsService.php');

        $this->assertStringContainsString(
            "countDistinctMembers(\$parishId, ['member'])",
            $source,
            'active_members must count membership_status = member.'
        );
        $this->assertStringNotContainsString(
            "['active']",
            $source,
            "'active' is not a valid parish_affiliation.membership_status value."
        );
    }

    /**
     * T4: The Clergy Members controller must not depend on the retired
     * parish_people snapshot registry.
     */
    public function testClergyMembersControllerDoesNotUseRetiredSnapshot(): void
    {
        $source = file_get_contents(APPPATH . '../Clergy/Controllers/Members.php');

        $this->assertStringNotContainsString(
            'ParishPeopleRegistryService',
            $source,
            'Members controller must not reference the retired parish_people registry.'
        );
        $this->assertStringNotContainsString(
            'syncParish',
            $source,
            'Members controller must not call the retired syncParish.'
        );
    }

    private function getMethodSource(ReflectionMethod $method): string
    {
        $file = $method->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();

        $lines = file($file);

        return implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));
    }
}
