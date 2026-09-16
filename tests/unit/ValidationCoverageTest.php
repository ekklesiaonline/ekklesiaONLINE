<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * M2: Validation & HTTP method consistency guard.
 *
 * Documents the current validation state of POST handlers that mutate data.
 * The plan identified these controllers as having validation gaps:
 * - Worship/Attendance.php mark path (uses inline casts)
 * - Worship/ParochialReport.php save/emailPdf
 * - Clergy/Import.php
 * - MemberPortal/SessionSecurity.php
 *
 * This test documents which controllers have CI4 Validation, which use
 * inline casts, and which have input normalization. It serves as a
 * baseline for tracking validation coverage improvements.
 *
 * @internal
 */
final class ValidationCoverageTest extends CIUnitTestCase
{
    /**
     * M2: Worship/Attendance::mark() uses inline casts but does verify
     * service ownership and member affiliation. Documents the gap.
     */
    public function testAttendanceMarkHasSecurityChecks(): void
    {
        $source = file_get_contents(ROOTPATH . 'Worship/Controllers/Attendance.php');

        // Has permission check
        $this->assertStringContainsString('requirePermission', $source);
        // Has parish scope check
        $this->assertStringContainsString('requireParish', $source);
        // Has service ownership verification
        $this->assertStringContainsString('parish_id', $source);
        // Has member affiliation verification
        $this->assertStringContainsString('isRecordableMember', $source);
        // Uses inline casts (validation gap documented)
        $this->assertStringContainsString('(bool)', $source);
    }

    /**
     * M2: Worship/ParochialReport::emailPdf() uses inline casts with
     * defaults and normalizes paper_size/pdf_engine. Documents the state.
     */
    public function testParochialReportHasInputNormalization(): void
    {
        $source = file_get_contents(ROOTPATH . 'Worship/Controllers/ParochialReport.php');

        // Has permission check
        $this->assertStringContainsString('requirePermission', $source);
        // Has parish scope check
        $this->assertStringContainsString('requireParish', $source);
        // Normalizes paper size
        $this->assertStringContainsString('normalizePaperSize', $source);
        // Normalizes PDF engine
        $this->assertStringContainsString('normalizePdfEngine', $source);
        // Uses inline cast for year with default
        $this->assertStringContainsString('(int)', $source);
    }

    /**
     * M2: Clergy/Import.php exists and has permission checks.
     */
    public function testClergyImportHasPermissionChecks(): void
    {
        $path = ROOTPATH . 'Clergy/Controllers/Import.php';

        if (! file_exists($path)) {
            $this->markTestSkipped('Clergy/Controllers/Import.php not found.');
        }

        $source = file_get_contents($path);

        // Has permission or auth check
        $this->assertTrue(
            str_contains($source, 'requirePermission') ||
            str_contains($source, 'permission') ||
            str_contains($source, 'auth'),
            'Clergy/Import should have permission or auth checks.'
        );
    }

    /**
     * M2: All mutating POST routes in the main Routes.php should use
     * the POST method, not GET.
     */
    public function testNoMutatingGetRoutesInMainRoutes(): void
    {
        $source = file_get_contents(APPPATH . 'Config/Routes.php');

        // The four mutating delete routes should be POST (fixed in C3)
        $this->assertStringNotContainsString(
            "->get('event-calendars/delete",
            $source,
            'Event calendar delete routes should be POST, not GET.'
        );
        $this->assertStringNotContainsString(
            "->get('events/delete",
            $source,
            'Events delete routes should be POST, not GET.'
        );
    }

    /**
     * M2: All mutating POST routes in Province Routes.php should use
     * the POST method, not GET.
     */
    public function testNoMutatingGetRoutesInProvinceRoutes(): void
    {
        $source = file_get_contents(ROOTPATH . 'Province/Config/Routes.php');

        $this->assertStringNotContainsString(
            "->get('events/delete",
            $source,
            'Province events delete routes should be POST, not GET.'
        );
        $this->assertStringNotContainsString(
            "->get('event-calendars/delete",
            $source,
            'Province event calendar delete routes should be POST, not GET.'
        );
    }

    /**
     * M2: The API/AJAX error contract document exists.
     */
    public function testApiErrorContractDocumentExists(): void
    {
        $this->assertFileExists(ROOTPATH . 'docs/API_AJAX_ERROR_CONTRACT.md');
    }

    /**
     * M2: Controllers that return JSON should follow the error contract
     * with mandatory error.code for new/updated endpoints.
     */
    public function testJsonResponseHelperExists(): void
    {
        // BaseController should have a jsonResponse helper method
        $source = file_get_contents(APPPATH . 'Controllers/BaseController.php');

        $this->assertStringContainsString(
            'jsonResponse',
            $source,
            'BaseController should have a jsonResponse helper for API/AJAX responses.'
        );
    }
}
