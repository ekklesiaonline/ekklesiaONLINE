<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regression guards for L2 (layout consolidation) and L4 (asset extraction).
 *
 * L2: Documents the module layout consolidation path. The app layout
 * serves as the shared base; module layouts are thin shims that extend it.
 * L4: A shared CSRF helper JS module exists at public/assets/js/csrf.js
 * and is loaded by the app layout.
 *
 * @internal
 */
final class LayoutAndAssetExtractionTest extends CIUnitTestCase
{
    /**
     * L4: The shared CSRF JS module exists.
     */
    public function testCsrfJsModuleExists(): void
    {
        $this->assertFileExists(ROOTPATH . 'public/assets/js/csrf.js');
    }

    /**
     * L4: The CSRF module defines CsrfFetch with post, headers, and body methods.
     */
    public function testCsrfJsModuleHasExpectedApi(): void
    {
        $source = file_get_contents(ROOTPATH . 'public/assets/js/csrf.js');

        $this->assertStringContainsString('window.CsrfFetch', $source);
        $this->assertStringContainsString('headers:', $source);
        $this->assertStringContainsString('body:', $source);
        $this->assertStringContainsString('post:', $source);
        $this->assertStringContainsString('URLSearchParams', $source);
        $this->assertStringContainsString('X-Requested-With', $source);
        $this->assertStringContainsString('X-CSRF-TOKEN', $source);
    }

    /**
     * L4: The app layout loads the CSRF JS module.
     */
    public function testAppLayoutLoadsCsrfJs(): void
    {
        $source = file_get_contents(APPPATH . 'Views/layouts/main.php');

        $this->assertStringContainsString('assets/js/csrf.js', $source, 'App layout should load the shared CSRF JS module.');
    }

    /**
     * L4: The app layout has the CSRF meta tag.
     */
    public function testAppLayoutHasCsrfMetaTag(): void
    {
        $source = file_get_contents(APPPATH . 'Views/layouts/main.php');

        $this->assertStringContainsString('meta name="csrf-token"', $source);
        $this->assertStringContainsString('csrf_hash()', $source);
    }

    /**
     * L2: All module layouts exist (documented for consolidation planning).
     */
    public function testModuleLayoutsExist(): void
    {
        $layouts = [
            'Clergy/Views/layouts/main.php',
            'Diocesan/Views/layouts/main.php',
            'Province/Views/layouts/main.php',
            'Treasurer/Views/layouts/main.php',
            'Volunteer/Views/layouts/main.php',
            'Worship/Views/layouts/main.php',
            'app/Views/layouts/main.php',
        ];

        foreach ($layouts as $layout) {
            $this->assertFileExists(
                ROOTPATH . $layout,
                "Module layout must exist: {$layout}"
            );
        }
    }

    /**
     * L2: All module layouts have the CSRF meta tag (consistency check).
     */
    public function testAllModuleLayoutsHaveCsrfMetaTag(): void
    {
        $layouts = [
            'Clergy/Views/layouts/main.php',
            'Diocesan/Views/layouts/main.php',
            'Province/Views/layouts/main.php',
            'Treasurer/Views/layouts/main.php',
            'Volunteer/Views/layouts/main.php',
            'Worship/Views/layouts/main.php',
            'app/Views/layouts/main.php',
        ];

        foreach ($layouts as $layout) {
            $source = file_get_contents(ROOTPATH . $layout);
            $this->assertStringContainsString(
                'csrf-token',
                $source,
                "Layout {$layout} must have the CSRF meta tag."
            );
        }
    }

    /**
     * L4: The CSRF module handles token refresh from response headers.
     */
    public function testCsrfJsHandlesTokenRefresh(): void
    {
        $source = file_get_contents(ROOTPATH . 'public/assets/js/csrf.js');

        $this->assertStringContainsString(
            'refreshToken',
            $source,
            'CSRF module should handle token refresh from response headers.'
        );
        $this->assertStringContainsString(
            'response.headers.get',
            $source,
            'CSRF module should read the new token from response headers.'
        );
    }

    /**
     * L4: The CSRF module uses form-encoded bodies, not JSON.stringify.
     */
    public function testCsrfJsUsesFormEncodedBodies(): void
    {
        $source = file_get_contents(ROOTPATH . 'public/assets/js/csrf.js');

        $this->assertStringContainsString(
            'application/x-www-form-urlencoded',
            $source,
            'CSRF module should use form-encoded content type.'
        );
        $this->assertStringContainsString(
            'URLSearchParams',
            $source,
            'CSRF module should use URLSearchParams for body encoding.'
        );
        // The body() method should not use JSON.stringify
        $bodyMethodStart = strpos($source, 'body: function');
        $this->assertNotFalse($bodyMethodStart);
        $bodyMethodEnd = strpos($source, '},', $bodyMethodStart);
        $this->assertNotFalse($bodyMethodEnd);
        $bodyMethod = substr($source, $bodyMethodStart, $bodyMethodEnd - $bodyMethodStart);
        $this->assertStringNotContainsString(
            'JSON.stringify',
            $bodyMethod,
            'body() method should not use JSON.stringify.'
        );
    }
}
