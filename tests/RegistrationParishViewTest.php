<?php

declare(strict_types=1);

namespace Tests;

use App\Controllers\Auth\RegisterController;
use CodeIgniter\Test\CIUnitTestCase;
use ReflectionMethod;

final class RegistrationParishViewTest extends CIUnitTestCase
{
    public function testControllerBuildsParishLookupLabels(): void
    {
        $method = new ReflectionMethod(RegisterController::class, 'buildParishLookupItems');
        $items = $method->invoke(new RegisterController(), [[
            'id'            => '42',
            'name'          => 'Saint Mark',
            'parish_ueid'   => 'RD-042',
            'diocese_name'  => 'Santo Domingo',
            'province_name' => 'Caribbean',
        ]]);

        $this->assertSame([[
            'id'          => 42,
            'name'        => 'Saint Mark',
            'parish_ueid' => 'RD-042',
            'label'       => 'Saint Mark [RD-042] - Santo Domingo (Caribbean)',
        ]], $items);
    }

    public function testRegistrationViewContainsNoDatabaseQuery(): void
    {
        $view = file_get_contents(APPPATH . 'Views/auth/register.php');

        $this->assertIsString($view);
        $this->assertStringNotContainsString('db_connect(', $view);
        $this->assertStringContainsString('$parishLookupItems', $view);
    }

    public function testRegisterGetRouteUsesApplicationController(): void
    {
        $routes = file_get_contents(APPPATH . 'Config/Routes.php');

        $this->assertIsString($routes);
        $this->assertStringContainsString("App\\\\Controllers\\\\Auth\\\\RegisterController::registerView", $routes);
    }
}
