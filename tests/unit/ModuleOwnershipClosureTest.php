<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ModuleOwnershipClosureTest extends CIUnitTestCase
{
    public static function moduleRouteFilesProvider(): array
    {
        return [
            'province routes' => [ROOTPATH . 'Province/Config/Routes.php'],
            'diocesan routes' => [ROOTPATH . 'Diocese/Config/Routes.php'],
            'clergy routes' => [ROOTPATH . 'Clergy/Config/Routes.php'],
            'worship routes' => [ROOTPATH . 'Worship/Config/Routes.php'],
            'treasurer routes' => [ROOTPATH . 'Treasurer/Config/Routes.php'],
            'volunteer routes' => [ROOTPATH . 'Volunteer/Config/Routes.php'],
            'member portal routes' => [ROOTPATH . 'MemberPortal/Config/Routes.php'],
        ];
    }

    /**
     * @dataProvider moduleRouteFilesProvider
     */
    public function testModuleRoutesAvoidAppControllerFallbackAndRedirectShims(string $routeFilePath): void
    {
        $content = file_get_contents($routeFilePath);
        $this->assertNotFalse($content, 'Unable to read route file: ' . $routeFilePath);

        $this->assertStringNotContainsString('addRedirect(', $content);

        // A small set of shared admin-user controllers intentionally live in
        // App\Controllers; everything else must be owned by the module namespace.
        $allowedAppControllers = ['ProvinceDioceseAdmins', 'DioceseAdminUsers'];
        if (preg_match_all('/App\\\\Controllers\\\\([A-Za-z0-9_]+)/', $content, $matches)) {
            foreach ($matches[1] as $class) {
                $this->assertContains($class, $allowedAppControllers, 'Module routes should not use App\Controllers fallback: ' . $class);
            }
        }
    }

    public static function moduleViewRootsProvider(): array
    {
        return [
            'province views' => ['Province/Views'],
            'diocesan views' => ['Diocese/Views'],
            'clergy views' => ['Clergy/Views'],
            'worship views' => ['Worship/Views'],
            'treasurer views' => ['Treasurer/Views'],
            'volunteer views' => ['Volunteer/Views'],
        ];
    }

    /**
     * @dataProvider moduleViewRootsProvider
     */
    public function testNonMemberPortalViewsDoNotDependOnSharedAppLayout(string $viewRoot): void
    {
        $directory = ROOTPATH . $viewRoot;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();
            $content = file_get_contents($path);
            $this->assertNotFalse($content, 'Unable to read view file: ' . $path);

            $this->assertStringNotContainsString('$this->extend(\'layouts/main\')', $content, 'Shared app layout dependency found in ' . $path);
        }
    }
}
