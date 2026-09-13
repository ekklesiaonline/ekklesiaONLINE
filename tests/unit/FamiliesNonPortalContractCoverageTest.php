<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class FamiliesNonPortalContractCoverageTest extends CIUnitTestCase
{
    public function testFamiliesRoutesExposeNonPortalAndEnablePortalEndpoints(): void
    {
        $content = file_get_contents(ROOTPATH . 'MemberPortal/Config/Routes.php');
        $this->assertNotFalse($content);

        $this->assertStringContainsString("post('families/addMemberNonPortal'", $content);
        $this->assertStringContainsString("Family::addMemberNonPortal", $content);
        $this->assertStringContainsString("post('families/enablePortal/(:num)'", $content);
        $this->assertStringContainsString('Family::enablePortal/$1', $content);
    }

    public function testFamiliesControllerContainsDeterministicContractCodesAndEnvelopeKeys(): void
    {
        $content = file_get_contents(ROOTPATH . 'MemberPortal/Controllers/Families.php');
        $this->assertNotFalse($content);

        $expectedCodes = [
            'VALIDATION_FAILED',
            'BAD_REQUEST_JSON',
            'UNAUTHENTICATED',
            'FORBIDDEN',
            'OUT_OF_SCOPE',
            'INTERNAL_ERROR',
        ];

        foreach ($expectedCodes as $code) {
            $this->assertStringContainsString("'" . $code . "'", $content, 'Missing contract code: ' . $code);
        }

        $serviceContent = file_get_contents(ROOTPATH . 'app/Services/MemberAccessService.php');
        $this->assertNotFalse($serviceContent);
        $serviceCodes = [
            'PORTAL_ACCESS_ENABLED',
            'FAMILY_NOT_FOUND',
            'USER_NOT_FOUND',
            'PORTAL_ACCESS_ALREADY_ENABLED',
            'EMAIL_ALREADY_IN_USE',
            'TRANSACTION_FAILED',
        ];
        foreach ($serviceCodes as $code) {
            $this->assertStringContainsString("'" . $code . "'", $serviceContent, 'Missing service contract code: ' . $code);
        }

        $nonPortalService = file_get_contents(ROOTPATH . 'app/Services/NonPortalMemberProvisioningService.php');
        $this->assertNotFalse($nonPortalService);
        $this->assertStringContainsString("'NON_PORTAL_MEMBER_CREATED'", $nonPortalService);

        $envelopeSnippets = [
            "'success' =>",
            "'code' =>",
            "'message' =>",
            "'data' =>",
            "'errors' =>",
            "'meta' =>",
            "'request_id' =>",
            "'timestamp' =>",
            "'parish_id' =>",
        ];

        foreach ($envelopeSnippets as $snippet) {
            $this->assertStringContainsString($snippet, $content, 'Missing envelope snippet: ' . $snippet);
        }
    }

    public function testFamilyViewBranchesByCodeAndRendersNewPortalActions(): void
    {
        $content = file_get_contents(ROOTPATH . 'MemberPortal/Views/Families/view.php');
        $this->assertNotFalse($content);

        $this->assertStringContainsString("data.code === 'NON_PORTAL_MEMBER_CREATED'", $content);
        $this->assertStringContainsString("data.code === 'PORTAL_ACCESS_ENABLED'", $content);
        $this->assertStringContainsString('addNonPortalMemberForm', $content);
        $this->assertStringContainsString('enablePortalForm', $content);
        $this->assertStringContainsString('enable-portal-btn', $content);
    }
}
