<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

class ProvinceDashboardTest extends CIUnitTestCase
{
    public function testDashboardUsesCanonicalProvinceScopeAndValidatesReportFilters(): void
    {
        $controller = file_get_contents(ROOTPATH . 'Province/Controllers/Dashboard.php');

        $this->assertIsString($controller);
        $this->assertStringContainsString("db_connect()->table('province')->where('id', \$provinceId)", $controller);
        $this->assertStringContainsString("['today', 'week', 'month', 'year', 'custom']", $controller);
        $this->assertStringContainsString("return ['valid' => false, 'error' => lang('Dashboard.invalidDateRange')]", $controller);
        $this->assertStringContainsString("province_dashboard.reporting_overdue_days", $controller);
        $this->assertStringContainsString('getPreviousDateRange', $controller);
        $this->assertStringNotContainsString('FROM dioceses WHERE province_id', $controller);
        $this->assertStringContainsString("s.id, s.service_date, s.service_type, c.name as church_name", $controller);
        $this->assertStringNotContainsString("s.service, s.liturgical_day", $controller);
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
