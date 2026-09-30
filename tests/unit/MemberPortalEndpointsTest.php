<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class MemberPortalEndpointsTest extends CIUnitTestCase
{
    public function testPeopleMemberEndpointsAreRoutable(): void
    {
        $routesFile = file_get_contents(ROOTPATH . 'MemberPortal/Config/Routes.php');
        $this->assertNotFalse($routesFile);

        // Controller namespaces with escaped backslashes in the route file
        $expectedGetRoutes = [
            'AnnualPledges::index',
            'LeadershipPositions::index',
            'Standing::index',
        ];

        foreach ($expectedGetRoutes as $signature) {
            $this->assertStringContainsString($signature, $routesFile, "Expected to find $signature in routes");
        }
    }

    public function testPeopleMemberPostEndpointsAreRoutable(): void
    {
        $routesFile = file_get_contents(ROOTPATH . 'MemberPortal/Config/Routes.php');
        $this->assertNotFalse($routesFile);

        // POST endpoints for the new modules
        $expectedPostRoutes = [
            'AnnualPledges::store',
            'AnnualPledges::update',
            'AnnualPledges::delete',
            'LeadershipPositions::store',
            'LeadershipPositions::update',
            'LeadershipPositions::delete',
            'Standing::refresh',
        ];

        foreach ($expectedPostRoutes as $signature) {
            $this->assertStringContainsString($signature, $routesFile, "Expected to find $signature in routes");
        }
    }

    public function testAuditLogsEndpointsAreRoutable(): void
    {
        $routesFile = file_get_contents(ROOTPATH . 'MemberPortal/Config/Routes.php');
        $this->assertNotFalse($routesFile);

        $expectedAuditRoutes = [
            'AuditLogs::index',
            'AuditLogs::exportCsv',
            'AuditLogs::exportJson',
            'AuditLogs::exportPdf',
            'AuditLogs::actorAutocomplete',
            'AuditLogs::loadMore',
            'AuditLogs::diff',
            'AuditLogs::statistics',
            'AuditLogs::realtimeUpdates',
        ];

        foreach ($expectedAuditRoutes as $signature) {
            $this->assertStringContainsString($signature, $routesFile, "Expected to find $signature in routes");
        }
    }

    public function testAuditDiffModalRenderingAndThemeResponsiveness(): void
    {
        $modalFile = ROOTPATH . 'MemberPortal/Views/Admin/audit_diff_modal.php';
        $this->assertFileExists($modalFile);
        $content = file_get_contents($modalFile);

        // Bootstrap modal alignment
        $this->assertStringContainsString('id="auditDiffModal"', $content);
        $this->assertStringContainsString('class="modal fade"', $content);
        $this->assertStringContainsString('modal-dialog-centered', $content);
        $this->assertStringContainsString('modal-dialog-scrollable', $content);
        $this->assertStringContainsString('modal-content', $content);
        $this->assertStringContainsString('modal-header', $content);
        $this->assertStringContainsString('modal-body', $content);
        $this->assertStringContainsString('modal-footer', $content);
        $this->assertStringContainsString('data-bs-dismiss="modal"', $content);
        $this->assertStringContainsString('btn-outline-secondary', $content);

        // Outline badges per view consistency rules
        $this->assertStringContainsString('badge border border-success text-success', $content);
        $this->assertStringContainsString('badge border border-danger text-danger', $content);
        $this->assertStringContainsString('badge border border-warning text-warning', $content);
        $this->assertStringContainsString('badge border border-secondary text-secondary', $content);

        // Native Bootstrap table styling & field formatting (zero custom CSS)
        $this->assertStringNotContainsString('<style>', $content);
        $this->assertStringContainsString('table table-sm table-hover', $content);
        $this->assertStringContainsString('formatFieldLabel', $content);
        $this->assertStringContainsString('colFieldLabel', $content);
        $this->assertStringContainsString('colChangeLabel', $content);

        // Native Bootstrap modal event handling
        $this->assertStringContainsString('show.bs.modal', $content);
        $this->assertStringContainsString('hidden.bs.modal', $content);
        $this->assertStringContainsString('ekklesiard:themechange', $content);
    }
}
