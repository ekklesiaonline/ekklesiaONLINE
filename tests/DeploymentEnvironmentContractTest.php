<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

final class DeploymentEnvironmentContractTest extends CIUnitTestCase
{
    public function testExampleContainsRequiredDeploymentKeys(): void
    {
        $content = file_get_contents(ROOTPATH . '.env.example');
        $this->assertNotFalse($content);

        $requiredKeys = [
            'CI_ENVIRONMENT',
            'app.baseURL',
            'app.forceGlobalSecureRequests',
            'database.default.hostname',
            'database.default.database',
            'database.default.username',
            'database.default.password',
            'database.default.DBDriver',
            'encryption.key',
            'encryption.blindIndexKey',
            'logger.threshold',
            'security.apiRateLimitPerWindow',
            'security.apiRateLimitWindowSeconds',
            'security.apiRateLimitAnonPerWindow',
            'security.loginRateLimitPerWindow',
            'security.loginRateLimitWindowSeconds',
            'monitoring.slowRequestMs',
            'monitoring.slowQueryMs',
            'monitoring.enabled',
            'monitoring.fileLogEnabled',
            'monitoring.auditLogEnabled',
            'monitoring.auditDbEnabled',
            'families.enforceSingleFamilyMembership',
            'backup.retentionDays',
            'backup.outputDir',
            'email.SMTPHost',
            'email.SMTPUser',
            'email.SMTPPass',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertMatchesRegularExpression('/^' . preg_quote($key, '/') . '\s*=/m', $content, 'Missing environment key: ' . $key);
        }
    }

    public function testExampleContainsNoReusableDefaultPassword(): void
    {
        $content = (string) file_get_contents(ROOTPATH . '.env.example');

        $this->assertStringNotContainsString('password@123', strtolower($content));
        $this->assertStringNotContainsString('Admin@123456', $content);
        $this->assertStringNotContainsString('database.default.password = root', $content);
    }

    public function testFamilyPolicyUsesCanonicalPluralKeyEverywhere(): void
    {
        $paths = [
            ROOTPATH . '.env.example',
            ROOTPATH . 'app/Services/FamilyService.php',
            ROOTPATH . 'MemberPortal/Controllers/Families.php',
            ROOTPATH . 'docs/DEPLOYMENT_RUNBOOK.md',
        ];

        foreach ($paths as $path) {
            $content = (string) file_get_contents($path);
            $this->assertStringContainsString('families.enforceSingleFamilyMembership', $content, $path);
            $this->assertStringNotContainsString("env('family.enforceSingleFamilyMembership')", $content, $path);
        }
    }

    public function testFrontControllerSupportsHostGatorSplitLayout(): void
    {
        $content = (string) file_get_contents(ROOTPATH . 'public/index.php');

        $this->assertStringContainsString("require FCPATH . 'paths_bootstrap.php';", $content);
        $this->assertStringContainsString('ekklesiaResolvePathsFile(FCPATH)', $content);
        $this->assertStringContainsString("\$minPhpVersion = '8.5';", $content);
        $this->assertStringContainsString('Application paths are not configured.', $content);

        $spark = (string) file_get_contents(ROOTPATH . 'spark');
        $this->assertStringContainsString("\$minPhpVersion = '8.5';", $spark);
    }

    public function testPathsBootstrapResolvesStandardSplitAndConfiguredLayouts(): void
    {
        require_once ROOTPATH . 'public/paths_bootstrap.php';

        $base = sys_get_temp_dir() . '/ekk-paths-' . bin2hex(random_bytes(4));
        $public = $base . '/public_html/';
        mkdir($public, 0777, true);
        mkdir($base . '/app/Config', 0777, true);
        mkdir($base . '/ekklesia/app/Config', 0777, true);
        touch($base . '/app/Config/Paths.php');
        touch($base . '/ekklesia/app/Config/Paths.php');

        $previousEnv = getenv('EKKLESIA_APP_ROOT');
        $hadServerValue = array_key_exists('EKKLESIA_APP_ROOT', $_SERVER);
        $previousServerValue = $_SERVER['EKKLESIA_APP_ROOT'] ?? null;

        try {
            putenv('EKKLESIA_APP_ROOT');
            unset($_SERVER['EKKLESIA_APP_ROOT']);

            $this->assertSame($public . '../app/Config/Paths.php', ekklesiaResolvePathsFile($public));

            putenv('EKKLESIA_APP_ROOT=' . $base . '/custom-app');
            $this->assertSame($base . '/custom-app/app/Config/Paths.php', ekklesiaResolvePathsFile($public));

            putenv('EKKLESIA_APP_ROOT');
            $_SERVER['EKKLESIA_APP_ROOT'] = $base . '/server-app';
            $this->assertSame($base . '/server-app/app/Config/Paths.php', ekklesiaResolvePathsFile($public));

            unset($_SERVER['EKKLESIA_APP_ROOT']);
            unlink($base . '/app/Config/Paths.php');
            $this->assertSame($public . '../ekklesia/app/Config/Paths.php', ekklesiaResolvePathsFile($public));
        } finally {
            if ($previousEnv === false) {
                putenv('EKKLESIA_APP_ROOT');
            } else {
                putenv('EKKLESIA_APP_ROOT=' . $previousEnv);
            }

            if ($hadServerValue) {
                $_SERVER['EKKLESIA_APP_ROOT'] = $previousServerValue;
            } else {
                unset($_SERVER['EKKLESIA_APP_ROOT']);
            }

            $this->removeDirectory($base);
        }
    }

    public function testHostGatorSyncDryRunExecutesSeparatePrivateAndPublicTransfers(): void
    {
        $base = sys_get_temp_dir() . '/ekk-sync-' . bin2hex(random_bytes(4));
        mkdir($base . '/build', 0777, true);
        mkdir($base . '/public', 0777, true);
        mkdir($base . '/vendor', 0777, true);
        mkdir($base . '/bin', 0777, true);
        copy(ROOTPATH . 'build/hostgator_sync.sh', $base . '/build/hostgator_sync.sh');
        file_put_contents($base . '/public/index.php', "<?php\n");
        file_put_contents($base . '/public/.htaccess', "base\n");
        file_put_contents($base . '/vendor/autoload.php', "<?php\n");

        $log = $base . '/commands.log';
        $htaccessCapture = $base . '/uploaded.htaccess';
        file_put_contents($base . '/bin/ssh', "#!/usr/bin/env bash\nprintf 'ssh' >> \"\$FAKE_LOG\"\nprintf ' <%s>' \"\$@\" >> \"\$FAKE_LOG\"\nprintf '\\n' >> \"\$FAKE_LOG\"\nprintf '/home/cptest'\n");
        file_put_contents($base . '/bin/rsync', "#!/usr/bin/env bash\nprintf 'rsync' >> \"\$FAKE_LOG\"\nprintf ' <%s>' \"\$@\" >> \"\$FAKE_LOG\"\nprintf '\\n' >> \"\$FAKE_LOG\"\nfor arg in \"\$@\"; do\n  case \"\$arg\" in\n    *ekk-hostgator-public.*/) cat \"\${arg}.htaccess\" > \"\$FAKE_HTACCESS_CAPTURE\" 2>/dev/null || true ;;\n  esac\ndone\n");
        chmod($base . '/bin/ssh', 0755);
        chmod($base . '/bin/rsync', 0755);

        $command = sprintf(
            'env FAKE_LOG=%s FAKE_HTACCESS_CAPTURE=%s HOSTGATOR_SSH=%s PATH=%s bash %s --dry-run --delete-public 2>&1',
            escapeshellarg($log),
            escapeshellarg($htaccessCapture),
            escapeshellarg('test@example'),
            escapeshellarg($base . '/bin' . PATH_SEPARATOR . getenv('PATH')),
            escapeshellarg($base . '/build/hostgator_sync.sh'),
        );
        exec($command, $output, $exitCode);

        try {
            $this->assertSame(0, $exitCode, implode("\n", $output));
            $this->assertContains('Dry run complete; no files were changed.', $output);

            $rsyncLines = array_values(array_filter(
                explode("\n", (string) file_get_contents($log)),
                static fn (string $line): bool => str_starts_with($line, 'rsync'),
            ));
            $this->assertCount(2, $rsyncLines);
            $this->assertStringContainsString('<test@example:ekklesia/>', $rsyncLines[0]);
            $this->assertStringContainsString('<test@example:public_html/>', $rsyncLines[1]);
            $this->assertStringContainsString('--delete', $rsyncLines[1]);
            $this->assertStringContainsString('.well-known/', $rsyncLines[1]);
            $this->assertStringContainsString('cgi-bin/', $rsyncLines[1]);
            $this->assertStringContainsString(
                'SetEnv EKKLESIA_APP_ROOT /home/cptest/ekklesia',
                (string) file_get_contents($htaccessCapture),
            );
        } finally {
            $this->removeDirectory($base);
        }
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($path);
    }
}
