<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class FamiliesIntegrityRemediationTest extends CIUnitTestCase
{
    public function testFamilyMemberIntegrityMigrationAddsDedupeAndUniqueConstraint(): void
    {
        $content = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026_04_10_000003_HardenFamilyMembersIntegrity.php');
        $this->assertNotFalse($content);

        $this->assertStringContainsString('DELETE fm1 FROM family_members fm1', $content);
        $this->assertStringContainsString('uniq_family_members_family_user', $content);
        $this->assertStringContainsString('idx_family_members_relationship', $content);
    }

    public function testFamilyHeadBackfillMigrationExists(): void
    {
        $content = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026_04_10_000004_BackfillFamilyHeadsIntoFamilyMembers.php');
        $this->assertNotFalse($content);

        $this->assertStringContainsString('INSERT INTO family_members', $content);
        $this->assertStringContainsString("'Head'", $content);
        $this->assertStringContainsString('fm.id IS NULL', $content);
    }

    public function testFamilyMemberModelDefinesRelationshipValidationAndGuards(): void
    {
        $content = file_get_contents(ROOTPATH . 'app/Models/FamilyMemberModel.php');
        $this->assertNotFalse($content);

        $this->assertStringContainsString('RELATIONSHIP_VALUES', $content);
        $this->assertStringContainsString('in_list[Head,Spouse,Child,Parent,Sibling,Other]', $content);
        $this->assertStringContainsString('function relationExists', $content);
        $this->assertStringContainsString('function userHasAnotherFamily', $content);
        $this->assertStringContainsString('function normalizeRelationship', $content);
    }

    public function testFamiliesControllerHasTransactionAndConflictGuards(): void
    {
        $content = file_get_contents(ROOTPATH . 'MemberPortal/Controllers/Families.php');
        $this->assertNotFalse($content);

        $requiredSnippets = [
            'transBegin()',
            'transCommit()',
            'transRollback()',
            "'FAMILY_MEMBER_ALREADY_EXISTS'",
            "'FAMILY_MEMBER_ALREADY_IN_ANOTHER_FAMILY'",
            "'FAMILY_RELATIONSHIP_INVALID'",
            'ensureHeadOfHouseholdMembership',
            'availableMembers',
            'shouldEnforceSingleFamilyMembership',
        ];

        foreach ($requiredSnippets as $snippet) {
            $this->assertStringContainsString($snippet, $content, 'Missing Families hardening snippet: ' . $snippet);
        }
    }

    public function testFamiliesRoutesExposeAvailableMembersEndpoint(): void
    {
        $content = file_get_contents(ROOTPATH . 'MemberPortal/Config/Routes.php');
        $this->assertNotFalse($content);

        $this->assertStringContainsString("get('available-members/(:num)'", $content);
        $this->assertStringContainsString('Family::availableMembers/$1', $content);
    }

    public function testFamilyViewUsesAvailableMembersEndpointInsteadOfServerSideProfilePreload(): void
    {
        $content = file_get_contents(ROOTPATH . 'MemberPortal/Views/Families/view.php');
        $this->assertNotFalse($content);

        $this->assertStringContainsString('families/available-members/', $content);
        $this->assertStringNotContainsString("model('ProfileModel')", $content);
        $this->assertStringNotContainsString('getChurchProfiles(', $content);
    }
}
