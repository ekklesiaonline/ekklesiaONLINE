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

        $this->assertStringContainsString('EKKLESIA_APP_ROOT', $content);
        $this->assertStringContainsString("\$minPhpVersion = '8.5';", $content);
        $this->assertStringContainsString("FCPATH . '../app/Config/Paths.php'", $content);
        $this->assertStringContainsString("FCPATH . '../ekklesia/app/Config/Paths.php'", $content);
        $this->assertStringContainsString('Application paths are not configured.', $content);

        $spark = (string) file_get_contents(ROOTPATH . 'spark');
        $this->assertStringContainsString("\$minPhpVersion = '8.5';", $spark);
    }

    public function testHostGatorSyncScriptUsesSeparatePrivateAndPublicTransfers(): void
    {
        $content = (string) file_get_contents(ROOTPATH . 'build/hostgator_sync.sh');

        foreach ([
            'HOSTGATOR_APP_ROOT',
            'HOSTGATOR_PUBLIC_HTML',
            'PUBLIC_STAGE',
            'EKKLESIA_APP_ROOT',
            '--delete-public',
            '.well-known/',
            'cgi-bin/',
        ] as $required) {
            $this->assertStringContainsString($required, $content);
        }

        $this->assertStringContainsString('"$SSH_TARGET:$APP_ROOT/"', $content);
        $this->assertStringContainsString('"$SSH_TARGET:$PUBLIC_HTML/"', $content);
    }
}
