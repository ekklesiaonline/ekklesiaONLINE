<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class CriticalWorkflowContractCoverageTest extends CIUnitTestCase
{
    public function testMutationRoutesRemainProtectedForCriticalWorkflows(): void
    {
        $clergyRoutes = file_get_contents(ROOTPATH . 'Clergy/Config/Routes.php');
        $this->assertNotFalse($clergyRoutes);
        $memberRoutes = file_get_contents(ROOTPATH . 'MemberPortal/Config/Routes.php');
        $this->assertNotFalse($memberRoutes);

        $requiredSnippets = [
            "post('detach-parish'",
            "permission:member.edit",
            "post('set-home-parish'",
            "group('followup-workflow', ['filter' => 'permission:followup.view']",
            "post('call-tasks/complete/(:num)'",
            "permission:followup.manage",
            "group('families', ['filter' => 'permission:family.view']",
            "post('delete/(:num)', '\\\\MemberPortal\\\\Controllers\\\\Family::delete/$1', ['filter' => 'permission:family.manage']",
            "permission:family.manage",
            "post('addMember', '\\\\MemberPortal\\\\Controllers\\\\Family::addMember', ['filter' => 'permission:family.manage']",
            "post('families/addMemberNonPortal', '\\\\MemberPortal\\\\Controllers\\\\Family::addMemberNonPortal')",
            "post('families/enablePortal/(:num)', '\\\\MemberPortal\\\\Controllers\\\\Family::enablePortal/$1')",
        ];

        $combined = $clergyRoutes . "\n" . $memberRoutes;
        foreach ($requiredSnippets as $snippet) {
            $this->assertStringContainsString($snippet, $combined, 'Missing critical route guard snippet: ' . $snippet);
        }
    }

    public function testMembersControllerExposesExplicitErrorCodesForParishActions(): void
    {
        $content = file_get_contents(ROOTPATH . 'Clergy/Controllers/MembersAffiliation.php');
        $this->assertNotFalse($content);

        $expectedCodes = [
            'MEMBER_NOT_FOUND_IN_SCOPE',
            'PARISH_AFFILIATION_REQUIRED',
            'PARISH_OUTSIDE_DIOCESAN_SCOPE',
            'MEMBER_LAST_PARISH_CONSTRAINT',
        ];

        foreach ($expectedCodes as $code) {
            $this->assertStringContainsString("'" . $code . "'", $content, 'Missing Members error code: ' . $code);
        }
    }

    public function testFamiliesControllerExposesExplicitErrorCodesForAjaxActions(): void
    {
        $content = file_get_contents(ROOTPATH . 'MemberPortal/Controllers/Families.php');
        $this->assertNotFalse($content);

        $expectedCodes = [
            'FAMILY_NOT_FOUND_IN_SCOPE',
            'FAMILY_MEMBER_SELECTION_INVALID',
            'FAMILY_MEMBER_ADD_FAILED',
            'FAMILY_MEMBER_ALREADY_EXISTS',
            'FAMILY_MEMBER_ALREADY_IN_ANOTHER_FAMILY',
            'FAMILY_RELATIONSHIP_INVALID',
            'BAD_REQUEST_JSON',
            'OUT_OF_SCOPE',
        ];

        foreach ($expectedCodes as $code) {
            $this->assertStringContainsString("'" . $code . "'", $content, 'Missing Families error code: ' . $code);
        }

        $serviceContent = file_get_contents(ROOTPATH . 'app/Services/MemberAccessService.php');
        $this->assertNotFalse($serviceContent);
        $this->assertStringContainsString("'PORTAL_ACCESS_ENABLED'", $serviceContent);
        $this->assertStringContainsString("'PORTAL_ACCESS_ALREADY_ENABLED'", $serviceContent);
        $this->assertStringContainsString("'EMAIL_ALREADY_IN_USE'", $serviceContent);

        $nonPortalServiceContent = file_get_contents(ROOTPATH . 'app/Services/NonPortalMemberProvisioningService.php');
        $this->assertNotFalse($nonPortalServiceContent);
        $this->assertStringContainsString("'NON_PORTAL_MEMBER_CREATED'", $nonPortalServiceContent);
    }

    public function testFollowupWorkflowControllerExposesExplicitErrorCodesForAjaxActions(): void
    {
        $content = file_get_contents(ROOTPATH . 'Clergy/Controllers/FollowupSequences.php');
        $this->assertNotFalse($content);

        $this->assertStringContainsString("'Not found or forbidden.'", $content);
        $this->assertStringContainsString("'Not found.'", $content);
        $this->assertStringContainsString("'Forbidden.'", $content);
        $this->assertStringContainsString('jsonResponse([\'success\' => false', $content);
    }

    public function testCriticalControllersExposeParishScopeRequiredCode(): void
    {
        $membersContent = file_get_contents(ROOTPATH . 'Clergy/Controllers/Members.php');
        $this->assertNotFalse($membersContent);
        $familiesContent = file_get_contents(ROOTPATH . 'MemberPortal/Controllers/Families.php');
        $this->assertNotFalse($familiesContent);
        $followUpContent = file_get_contents(ROOTPATH . 'Clergy/Controllers/FollowupSequences.php');
        $this->assertNotFalse($followUpContent);

        $this->assertStringContainsString("'PARISH_SCOPE_REQUIRED'", $membersContent);
        $this->assertStringContainsString("'PARISH_SCOPE_REQUIRED'", $familiesContent);
        $this->assertStringContainsString('requireParish()', $followUpContent);
    }

    public function testAuthenticatedSmokeScriptValidatesExplicitErrorCodes(): void
    {
        $script = file_get_contents(ROOTPATH . 'build/e2e_members_families_followup_smoke.sh');
        $this->assertNotFalse($script);

        $requiredSnippets = [
            'MEMBERS_EMAIL',
            'FAMILIES_EMAIL',
            'FOLLOWUP_EMAIL',
            'STRICT',
            'MEMBER_NOT_FOUND_IN_SCOPE',
            'FAMILY_NOT_FOUND_IN_SCOPE',
            'FOLLOWUP_TASK_NOT_FOUND',
            'json_has_contract_error',
        ];

        foreach ($requiredSnippets as $snippet) {
            $this->assertStringContainsString($snippet, $script, 'Missing smoke-script contract snippet: ' . $snippet);
        }
    }

    /**
     * The SendFollowups Spark command must exist and be registered so the
     * daily cron can dispatch due followup_log entries.
     *
     * Without this command, even correctly-scheduled log rows will never be
     * sent — visitors will never receive automated follow-up emails/SMS/calls.
     */
    public function testFollowupSendCommandClassExists(): void
    {
        $content = file_get_contents(ROOTPATH . 'app/Commands/SendFollowups.php');
        $this->assertNotFalse(
            $content,
            "app/Commands/SendFollowups.php must exist. This is the command invoked by the " .
                "daily cron '0 9 * * * php spark followup:send'. Without it the automated " .
                "visitor follow-up dispatch is completely broken."
        );

        $this->assertStringContainsString(
            "'followup:send'",
            $content,
            "SendFollowups must declare the command name 'followup:send' — used in crontab."
        );

        $this->assertStringContainsString(
            'getPendingDue(',
            $content,
            "SendFollowups::run() must call getPendingDue() to retrieve due log rows."
        );
    }

    public function testDioceseDashboardControllerExposesRequiredPublicMethods(): void
    {
        $content = file_get_contents(ROOTPATH . 'Diocese/Controllers/Dashboard.php');
        $this->assertNotFalse($content, 'Unable to read Diocese\\Controllers\\Dashboard.');

        $this->assertStringContainsString('public function index()', $content, 'Diocese\\Controllers\\Dashboard must have an index() method.');
        $this->assertStringContainsString('public function recentActivity()', $content, 'Diocese\\Controllers\\Dashboard must have a recentActivity() method.');
        $this->assertStringContainsString('public function switchParish(', $content, 'Diocese\\Controllers\\Dashboard must have a switchParish() method.');
        $this->assertStringContainsString('isDioceseAdmin()', $content, 'Diocese\\Controllers\\Dashboard::index() must guard with isDioceseAdmin().');
        $this->assertStringContainsString('/diocese/dashboard', $content, 'Diocese\\Controllers\\Dashboard redirects must reference /diocese/dashboard.');
    }
}
