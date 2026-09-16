<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Static guard: no view file may call old() with the third argument set to
 * false or 'raw' for user-influenced input, because that disables CI4's
 * built-in HTML escaping. CI4's old() defaults to $escape = 'html', which
 * uses htmlspecialchars(ENT_QUOTES) and is safe in both body and attribute
 * contexts.
 *
 * This test scans all view files for unsafe old() patterns so that a future
 * change cannot silently introduce an XSS vector.
 *
 * @internal
 */
final class OldEscapingGuardTest extends CIUnitTestCase
{
    /**
     * Recursively find all .php view files under the project.
     *
     * @return list<string>
     */
    private function findViewFiles(string $dir): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $path = $file->getRealPath();
                if ($path !== false) {
                    $files[] = $path;
                }
            }
        }

        return $files;
    }

    public function testNoViewCallsOldWithRawOrFalseEscape(): void
    {
        $viewDirs = [
            ROOTPATH . 'app/Views',
            ROOTPATH . 'Clergy/Views',
            ROOTPATH . 'Diocesan/Views',
            ROOTPATH . 'MemberPortal/Views',
            ROOTPATH . 'Parish/Views',
            ROOTPATH . 'Province/Views',
            ROOTPATH . 'Treasurer/Views',
            ROOTPATH . 'Volunteer/Views',
            ROOTPATH . 'Worship/Views',
        ];

        $violations = [];

        foreach ($viewDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            foreach ($this->findViewFiles($dir) as $file) {
                $content = file_get_contents($file);
                if ($content === false) {
                    continue;
                }

                // Match old(..., ..., false) or old(..., ..., 'raw') or old(..., ..., "raw")
                // The third argument disables escaping.
                if (preg_match('/old\([^;]*,\s*[^,)]+,\s*(false|\'raw\'|"raw")\s*\)/', $content, $m)) {
                    $violations[] = $file . ' — old() with escape=' . $m[1];
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Found view(s) calling old() with escaping disabled:\n" . implode("\n", $violations)
        );
    }
}
