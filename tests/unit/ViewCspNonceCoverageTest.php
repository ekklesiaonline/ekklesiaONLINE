<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Static guard for the CSP rollout: every <script>/<style> tag in rendered
 * views must carry the nonce placeholder ({csp-script-nonce} /
 * {csp-style-nonce}) or the csp_*_nonce() helper. Without it the browser
 * blocks the block under the enforced policy.
 *
 * Email templates are excluded — they are not served under the app CSP.
 *
 * @internal
 */
final class ViewCspNonceCoverageTest extends CIUnitTestCase
{
    private const VIEW_DIRS = [
        'app/Views',
        'Clergy/Views',
        'Diocese/Views',
        'Finance/Views',
        'MemberPortal/Views',
        'Parish/Views',
        'Province/Views',
        'Volunteer/Views',
        'Worship/Views',
    ];

    public function testEveryInlineScriptAndStyleTagCarriesNonce(): void
    {
        $violations = [];

        foreach (self::VIEW_DIRS as $dir) {
            $root = ROOTPATH . $dir;
            if (! is_dir($root)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $path = str_replace('\\', '/', $file->getPathname());
                if (preg_match('#/emails?/#i', $path)) {
                    continue;
                }

                $lines = file($path) ?: [];
                foreach ($lines as $i => $line) {
                    if (! preg_match_all('/<(script|style)\b[^>]*>/i', $line, $matches)) {
                        continue;
                    }

                    foreach ($matches[0] as $tag) {
                        if (preg_match('/\{csp-(script|style)-nonce\}|csp_(script|style)_nonce\(/', $tag)) {
                            continue;
                        }
                        $violations[] = substr($path, strlen(str_replace('\\', '/', ROOTPATH))) . ':' . ($i + 1) . ' ' . trim($tag);
                    }
                }
            }
        }

        $this->assertSame([], $violations, "Tags missing a CSP nonce placeholder:\n" . implode("\n", $violations));
    }
}
