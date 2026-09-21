<?php

use App\Services\ConfigurationService;
use App\Services\EmailScopeService;
use App\Services\HierarchyService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Email as EmailConfig;

/**
 * EmailScopeService logic tests using test doubles for the DB-backed
 * collaborators. Keeps the test independent of canonical/test schema drift.
 *
 * @internal
 */
final class EmailScopeServiceTest extends CIUnitTestCase
{
    private FakeConfigurationService $config;
    private FakeHierarchyService $hierarchy;
    private EmailScopeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = new FakeConfigurationService();
        $this->hierarchy = new FakeHierarchyService();
        $this->service = new EmailScopeService(
            $this->config,
            $this->hierarchy,
            new EmailConfig()
        );
    }

    public function testResolveScopeWalksParishToDioceseToProvince(): void
    {
        $this->hierarchy->parishRows = [
            30 => ['id' => 30, 'diocese_id' => 20, 'province_id' => null],
        ];
        $this->hierarchy->dioceseProvinces = [20 => 10];

        $scope = $this->service->resolveScope(['parish_id' => 30]);

        $this->assertSame(30, $scope['parish_id']);
        $this->assertSame(20, $scope['diocese_id']);
        $this->assertSame(10, $scope['province_id']);
    }

    public function testResolveScopeFallsBackFromDioceseToProvince(): void
    {
        $this->hierarchy->dioceseProvinces = [20 => 10];

        $scope = $this->service->resolveScope(['diocese_id' => 20]);

        $this->assertNull($scope['parish_id']);
        $this->assertSame(20, $scope['diocese_id']);
        $this->assertSame(10, $scope['province_id']);
    }

    public function testSenderConfigFallsBackToConfigEmailWhenNoSettings(): void
    {
        $sender = $this->service->senderConfig([]);
        $config = new EmailConfig();

        $this->assertSame($config->fromEmail, $sender['from_email']);
        $this->assertSame($config->fromName, $sender['from_name']);
        $this->assertSame('', $sender['reply_to']);
    }

    public function testSenderConfigRespectsHierarchy(): void
    {
        $this->hierarchy->parishRows = [
            30 => ['id' => 30, 'diocese_id' => 20, 'province_id' => null],
        ];
        $this->hierarchy->dioceseProvinces = [20 => 10];

        // Set values at every tier; parish wins when present.
        $this->config->values = [
            'email.from_email' => 'system@example.test',
        ];
        $this->config->scopedValues = [
            ['email.from_email', ['parish_id' => 30], 'parish@example.test'],
            ['email.from_email', ['diocese_id' => 20], 'diocese@example.test'],
            ['email.from_email', ['province_id' => 10], 'province@example.test'],
        ];

        $this->assertSame(
            'parish@example.test',
            $this->service->senderConfig(['parish_id' => 30])['from_email']
        );

        $this->assertSame(
            'diocese@example.test',
            $this->service->senderConfig(['diocese_id' => 20])['from_email']
        );
    }

    public function testSmtpConfigRequiresCompleteBlock(): void
    {
        // Partial block → null.
        $this->config->scopedValues = [
            ['email.smtp_host', ['parish_id' => 30], 'smtp.parish.test'],
        ];
        $this->assertNull($this->service->smtpConfig(['parish_id' => 30]));

        // Complete block → used.
        $this->config->scopedValues = [
            ['email.smtp_host', ['parish_id' => 30], 'smtp.parish.test'],
            ['email.smtp_user', ['parish_id' => 30], 'user'],
            ['email.smtp_pass', ['parish_id' => 30], 'secret'],
        ];
        $smtp = $this->service->smtpConfig(['parish_id' => 30]);
        $this->assertNotNull($smtp);
        $this->assertSame('smtp.parish.test', $smtp['host']);
        $this->assertSame('user', $smtp['user']);
        $this->assertSame('secret', $smtp['pass']);
    }

    public function testSystemLockOverridesLowerTierFrom(): void
    {
        $this->config->locked = ['email.from_email' => true];
        $this->config->values = ['email.from_email' => 'locked@example.test'];
        $this->config->scopedValues = [
            ['email.from_email', ['parish_id' => 30], 'parish@example.test'],
        ];

        $this->assertSame(
            'locked@example.test',
            $this->service->senderConfig(['parish_id' => 30])['from_email']
        );
    }

    public function testMailerUsesSharedInstanceForSystemTransport(): void
    {
        $mailer1 = $this->service->mailer([]);
        $mailer2 = $this->service->mailer([]);

        $this->assertSame($mailer1, $mailer2);
    }

    public function testMailerUsesNonSharedInstanceForTenantSmtp(): void
    {
        $this->config->scopedValues = [
            ['email.smtp_host', ['parish_id' => 30], 'smtp.parish.test'],
            ['email.smtp_user', ['parish_id' => 30], 'user'],
            ['email.smtp_pass', ['parish_id' => 30], 'secret'],
        ];

        $mailer1 = $this->service->mailer(['parish_id' => 30]);
        $mailer2 = $this->service->mailer(['parish_id' => 30]);

        $this->assertNotSame($mailer1, $mailer2);
        $this->assertInstanceOf(\CodeIgniter\Email\Email::class, $mailer1);
    }

    public function testSystemMailerForcesSystemTransport(): void
    {
        $this->config->scopedValues = [
            ['email.smtp_host', ['parish_id' => 30], 'smtp.parish.test'],
            ['email.smtp_user', ['parish_id' => 30], 'user'],
            ['email.smtp_pass', ['parish_id' => 30], 'secret'],
        ];

        // systemMailer() ignores tenant SMTP.
        $mailer = $this->service->systemMailer();
        $this->assertInstanceOf(\CodeIgniter\Email\Email::class, $mailer);

        $mailer2 = $this->service->systemMailer();
        $this->assertSame($mailer, $mailer2);
    }
}

// ------------------------------------------------------------------
// Test doubles
// ------------------------------------------------------------------

class FakeConfigurationService extends ConfigurationService
{
    public array $values = [];
    public array $scopedValues = [];
    public array $locked = [];

    public function __construct()
    {
        // Skip parent constructor; avoid DB-backed model instantiation.
    }

    public function isSettingLocked(string $key): bool
    {
        return $this->locked[$key] ?? false;
    }

    public function getSettingForScope(string $key, array $scope = [], $default = null): mixed
    {
        // Prefer the most specific scoped match.
        $scope = [
            'parish_id'   => $scope['parish_id']   ?? null,
            'diocese_id'  => $scope['diocese_id']  ?? null,
            'province_id' => $scope['province_id'] ?? null,
        ];

        foreach ($this->scopedValues as $row) {
            [$rowKey, $rowScope, $rowValue] = $row;
            if ($rowKey !== $key) {
                continue;
            }
            $rowScope = [
                'parish_id'   => $rowScope['parish_id']   ?? null,
                'diocese_id'  => $rowScope['diocese_id']  ?? null,
                'province_id' => $rowScope['province_id'] ?? null,
            ];

            $matches = true;
            foreach (['parish_id', 'diocese_id', 'province_id'] as $s) {
                if ($rowScope[$s] !== null && $rowScope[$s] !== $scope[$s]) {
                    $matches = false;
                    break;
                }
            }
            if ($matches) {
                return $rowValue;
            }
        }

        return $this->values[$key] ?? $default;
    }
}

class FakeHierarchyService extends HierarchyService
{
    public array $parishRows = [];
    public array $dioceseProvinces = [];

    public function __construct()
    {
        // Skip parent constructor.
    }

    public function parishRow(int $parishId): ?array
    {
        return $this->parishRows[$parishId] ?? null;
    }

    public function provinceIdForDiocese(int $dioceseId): ?int
    {
        return $this->dioceseProvinces[$dioceseId] ?? null;
    }
}
