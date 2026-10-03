<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

class ProvinceDashboardTest extends CIUnitTestCase
{
    public function testDashboardUsesCanonicalProvinceScopeAndValidatesReportFilters(): void
    {
        $controller = file_get_contents(ROOTPATH . 'Province/Controllers/Dashboard.php');
        $dataService = file_get_contents(ROOTPATH . 'app/Services/ProvinceDashboardDataService.php');

        $this->assertIsString($controller);
        $this->assertIsString($dataService);
        $this->assertStringContainsString("db_connect()->table('province')->where('id', \$provinceId)", $controller);
        $this->assertStringContainsString("['today', 'week', 'month', 'year', 'custom']", $controller);
        $this->assertStringContainsString("return ['valid' => false, 'error' => lang('Dashboard.invalidDateRange')]", $controller);
        $this->assertStringContainsString("province_dashboard.reporting_overdue_days", $dataService);
        $this->assertStringContainsString('getPreviousDateRange', $controller);
        $this->assertStringNotContainsString('FROM dioceses WHERE province_id', $controller);
        $this->assertStringNotContainsString('FROM dioceses WHERE province_id', $dataService);
        $this->assertStringContainsString("s.id, s.service_date, s.service_type, c.name as parish_name", $dataService);
        $this->assertStringNotContainsString("s.service, s.liturgical_day", $controller);
        $this->assertStringNotContainsString("s.service, s.liturgical_day", $dataService);
    }

    public function testProvinceOversightRoutesUseDedicatedReadOnlyControllers(): void
    {
        $routes = file_get_contents(ROOTPATH . 'Province/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'Province/Controllers/ProvinceOversight.php');

        $this->assertIsString($routes);
        $this->assertIsString($controller);
        $this->assertStringContainsString("'events', '\\\\Province\\\\Controllers\\\\Events::index'", $routes);
        $this->assertStringContainsString("'events/calendar', '\\\\Province\\\\Controllers\\\\Events::calendar'", $routes);
        $this->assertStringContainsString("'health', '\\\\Province\\\\Controllers\\\\ProvinceOversight::health'", $routes);
        $this->assertStringContainsString("'directory', '\\\\Province\\\\Controllers\\\\ProvinceOversight::directory'", $routes);
        $this->assertStringContainsString("->where('d.province_id', \$provinceId)", $controller);
        $this->assertStringNotContainsString('requireParish()', $controller);
    }

    public function testProvinceExportRouteRequiresExportPermission(): void
    {
        $routes = file_get_contents(ROOTPATH . 'Province/Config/Routes.php');

        $this->assertIsString($routes);
        $this->assertStringContainsString("'report-export'", $routes);
        $this->assertStringContainsString("'filter' => 'permission:report.export'", $routes);
    }
}
