<?php

use App\Models\SystemSettingModel;
use App\Services\EmailSettingsService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Tests\Support\Database\SystemSettingsTestHelper;

/**
 * @internal
 */
final class EmailSettingsServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    private const SCOPE = ['type' => 'system', 'id' => null];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        SystemSettingsTestHelper::createSystemSettingsTableAndSeed(db_connect());
    }

    protected function setUp(): void
    {
        parent::setUp();

        Services::reset();
        SystemSettingsTestHelper::createSystemSettingsTableAndSeed($this->db);
    }

    public function testIdentityOnlySaveLeavesStoredSmtpBlockUntouched(): void
    {
        $settings = model(SystemSettingModel::class);
        $settings->setValue('email.smtp_host', 'smtp.example.org', 'string', 'email');
        $settings->setValue('email.smtp_user', 'mailer@example.org', 'string', 'email');
        $settings->setValue('email.smtp_port', '587', 'string', 'email');
        $settings->setValue('email.smtp_crypto', 'tls', 'string', 'email');

        $service = new EmailSettingsService();
        $errors  = $service->save(self::SCOPE, [
            'section'    => 'identity',
            'enabled'    => '1',
            'from_email' => 'parish@example.org',
            'from_name'  => 'Parish Office',
            'reply_to'   => 'replies@example.org',
        ], true);

        $this->assertSame([], $errors);
        $this->assertSame('parish@example.org', $settings->getValue('email.from_email'));
        $this->assertSame('Parish Office', $settings->getValue('email.from_name'));
        $this->assertSame('smtp.example.org', $settings->getValue('email.smtp_host'));
        $this->assertSame('mailer@example.org', $settings->getValue('email.smtp_user'));
        $this->assertSame('587', $settings->getValue('email.smtp_port'));
        $this->assertSame('tls', $settings->getValue('email.smtp_crypto'));
    }

    public function testSmtpOnlySaveLeavesStoredIdentityUntouched(): void
    {
        $settings = model(SystemSettingModel::class);
        $settings->setValue('email.enabled', '0', 'string', 'email');
        $settings->setValue('email.from_email', 'keep@example.org', 'string', 'email');
        $settings->setValue('email.from_name', 'Keep Me', 'string', 'email');

        $service = new EmailSettingsService();
        $errors  = $service->save(self::SCOPE, [
            'section'     => 'smtp',
            'smtp_host'   => 'smtp.example.org',
            'smtp_user'   => 'mailer@example.org',
            'smtp_pass'   => 'app-password',
            'smtp_port'   => '465',
            'smtp_crypto' => 'ssl',
        ], true);

        $this->assertSame([], $errors);
        $this->assertSame('keep@example.org', $settings->getValue('email.from_email'));
        $this->assertSame('Keep Me', $settings->getValue('email.from_name'));
        $this->assertSame('0', $settings->getValue('email.enabled'));
        $this->assertSame('smtp.example.org', $settings->getValue('email.smtp_host'));
        $this->assertSame('465', $settings->getValue('email.smtp_port'));
    }

    public function testSubmittedEmptyFieldStillClearsStoredValue(): void
    {
        $settings = model(SystemSettingModel::class);
        $settings->setValue('email.reply_to', 'old@example.org', 'string', 'email');

        $service = new EmailSettingsService();
        $errors  = $service->save(self::SCOPE, [
            'section'    => 'identity',
            'enabled'    => '1',
            'from_email' => '',
            'from_name'  => '',
            'reply_to'   => '',
        ], true);

        $this->assertSame([], $errors);
        $this->assertSame('', (string) $settings->getValue('email.reply_to'));
    }

    public function testInvalidFromEmailReturnsFieldError(): void
    {
        $service = new EmailSettingsService();
        $errors  = $service->save(self::SCOPE, [
            'section'    => 'identity',
            'enabled'    => '1',
            'from_email' => 'not-an-email',
        ], true);

        $this->assertArrayHasKey('from_email', $errors);
    }
}
