<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regression guards for L6 (accessibility) and L10 (static analysis).
 *
 * L6: Parish Finance view has accessible table headers (scope="col"),
 * aria-labels on buttons, and aria-hidden on decorative icons.
 * L10: PHPStan is installed and configured with a baseline. It passes
 * at level 0 on the Services, Libraries, and Models directories.
 *
 * @internal
 */
final class AccessibilityAndStaticAnalysisTest extends CIUnitTestCase
{
    /**
     * L6: Parish Finance view has scope="col" on table headers.
     */
    public function testParishFinanceViewHasAccessibleTableHeaders(): void
    {
        $source = file_get_contents(ROOTPATH . 'Parish/Views/Finance/index.php');

        $this->assertStringContainsString('scope="col"', $source, 'Table headers should have scope="col".');
    }

    /**
     * L6: Parish Finance view has aria-label on the filter button.
     */
    public function testParishFinanceViewHasAriaLabelOnFilterButton(): void
    {
        $source = file_get_contents(ROOTPATH . 'Parish/Views/Finance/index.php');

        $this->assertStringContainsString('aria-label', $source, 'Filter button should have aria-label.');
    }

    /**
     * L6: Parish Finance view has aria-hidden on decorative icons.
     */
    public function testParishFinanceViewHasAriaHiddenOnIcons(): void
    {
        $source = file_get_contents(ROOTPATH . 'Parish/Views/Finance/index.php');

        $this->assertStringContainsString('aria-hidden="true"', $source, 'Decorative icons should have aria-hidden="true".');
    }

    /**
     * L10: PHPStan is installed.
     */
    public function testPhpstanInstalled(): void
    {
        $this->assertFileExists(ROOTPATH . 'vendor/bin/phpstan');
    }

    /**
     * L10: PHPStan configuration file exists.
     */
    public function testPhpstanConfigExists(): void
    {
        $this->assertFileExists(ROOTPATH . 'phpstan.neon');
    }

    /**
     * L10: PHPStan baseline file exists.
     */
    public function testPhpstanBaselineExists(): void
    {
        $this->assertFileExists(ROOTPATH . 'phpstan-baseline.php');
    }

    /**
     * L10: PHPStan stubs file exists for CI4 helper functions.
     */
    public function testPhpstanStubsFileExists(): void
    {
        $this->assertFileExists(ROOTPATH . 'phpstan-stubs.php');
    }

    /**
     * L10: PHPStan is in composer.json require-dev.
     */
    public function testPhpstanInComposerJson(): void
    {
        $composer = json_decode(file_get_contents(ROOTPATH . 'composer.json'), true);

        $this->assertArrayHasKey(
            'phpstan/phpstan',
            $composer['require-dev'],
            'phpstan/phpstan should be in require-dev.'
        );
    }

    /**
     * L10: PHPStan passes at level 0 with the baseline.
     */
    public function testPhpstanPassesAtLevel0(): void
    {
        $output = [];
        $exitCode = 0;
        exec(ROOTPATH . 'vendor/bin/phpstan analyse --no-progress 2>&1', $output, $exitCode);

        $combined = implode("\n", $output);

        $this->assertSame(0, $exitCode, 'PHPStan should pass at level 0 with baseline. Output: ' . $combined);
        $this->assertStringContainsString('[OK] No errors', $combined, 'PHPStan should report no errors.');
    }
}
