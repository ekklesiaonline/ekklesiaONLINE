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
     * View directories covered by the accessibility sweep.
     *
     * @var list<string>
     */
    private const VIEW_DIRS = [
        'Clergy/Views',
        'MemberPortal/Views',
        'Diocese/Views',
        'Finance/Views',
        'Worship/Views',
        'Parish/Views',
        'Province/Views',
        'Volunteer/Views',
        'app/Views',
    ];

    /**
     * L6: no <a>/<button> may be icon-only without an accessible name
     * (aria-label, title, or visually-hidden text).
     */
    public function testNoIconOnlyControlsWithoutAccessibleNames(): void
    {
        $tagPattern = '/<(a|button)\b((?:"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'|[^\'">])*)>(.*?)<\/\1>/s';
        $violations = [];

        foreach ($this->viewFiles() as $file) {
            $src = (string) file_get_contents($file);
            if (!preg_match_all($tagPattern, $src, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($matches as $m) {
                $attrs = $m[2][0];
                $inner = $m[3][0];
                if (!preg_match('/<i[^>]*class="[^"]*\bbi-[a-z0-9-]+/', $inner)) {
                    continue;
                }
                // PHP echoes render text; php code blocks do not.
                $probe = preg_replace('/<\?=.*?\x3f\x3e/s', 'X', $inner);
                $probe = preg_replace('/<\?(php)?[\s][\s\S]*?\x3f\x3e/s', '', (string) $probe);
                $text  = trim(html_entity_decode(strip_tags((string) $probe), ENT_QUOTES | ENT_HTML5));
                $named = str_contains($attrs, 'aria-label')
                    || str_contains($attrs, 'title=')
                    || str_contains($inner, 'aria-label')
                    || str_contains($inner, 'visually-hidden')
                    || str_contains($inner, 'sr-only');
                if ($text === '' && !$named) {
                    $line = substr_count(substr($src, 0, $m[0][1]), "\n") + 1;
                    $violations[] = $file . ':' . $line;
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            'Icon-only controls must expose an accessible name: ' . implode(', ', $violations)
        );
    }

    /**
     * L6: every <th> inside <thead> must declare scope="col".
     */
    public function testTableHeadersDeclareScope(): void
    {
        $violations = [];

        foreach ($this->viewFiles() as $file) {
            $src = (string) file_get_contents($file);
            if (!preg_match_all('/<thead\b[^>]*>([\s\S]*?)<\/thead>/', $src, $heads, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($heads[1] as $head) {
                if (!preg_match_all('/<th\b[^>]*>/', $head[0], $ths, PREG_OFFSET_CAPTURE)) {
                    continue;
                }
                foreach ($ths[0] as $th) {
                    if (!str_contains($th[0], 'scope=')) {
                        $line = substr_count(substr($src, 0, $head[1] + $th[1]), "\n") + 1;
                        $violations[] = $file . ':' . $line;
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            '<th> elements inside <thead> must declare scope="col": ' . implode(', ', $violations)
        );
    }

    /**
     * T2.7: completed language namespaces must have identical key sets in
     * every supported locale (es/fr must not silently fall back to English).
     * Namespaces with known pre-existing gaps are excluded until backfilled.
     */
    public function testCompletedLanguageNamespacesHaveLocaleParity(): void
    {
        $namespaces = [
            'Visitors', 'Attendance', 'Events', 'System', 'Errors', 'Dashboard', 'Volunteer',
            'App', 'People', 'Members', 'Clergy', 'Services', 'Finance', 'Diocesan', 'Province',
            'Leadership', 'Profile',
        ];
        $locales = ['es', 'fr'];
        $problems = [];

        foreach ($namespaces as $ns) {
            $enFile = ROOTPATH . 'app/Language/en/' . $ns . '.php';
            if (!is_file($enFile)) {
                continue;
            }
            $enKeys = array_keys((array) require $enFile);
            foreach ($locales as $loc) {
                $locFile = ROOTPATH . 'app/Language/' . $loc . '/' . $ns . '.php';
                if (!is_file($locFile)) {
                    $problems[] = $loc . '/' . $ns . ' (missing file)';
                    continue;
                }
                $locKeys = array_keys((array) require $locFile);
                foreach (array_diff($enKeys, $locKeys) as $k) {
                    $problems[] = $loc . '/' . $ns . '.' . $k;
                }
            }
        }

        $this->assertSame(
            [],
            $problems,
            'Language keys missing from supported locales: ' . implode(', ', $problems)
        );
    }

    /**
     * @return list<string>
     */
    private function viewFiles(): array
    {
        $files = [];
        foreach (self::VIEW_DIRS as $dir) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(ROOTPATH . $dir)
            );
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
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
        exec(ROOTPATH . 'vendor/bin/phpstan analyse --no-progress --debug --memory-limit=512M 2>&1', $output, $exitCode);

        $combined = implode("\n", $output);

        $this->assertSame(0, $exitCode, 'PHPStan should pass at level 0 with baseline. Output: ' . $combined);
        $this->assertStringContainsString('[OK] No errors', $combined, 'PHPStan should report no errors.');
    }
}
