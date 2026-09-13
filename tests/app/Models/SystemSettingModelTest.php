<?php

use App\Models\SystemSettingModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use Tests\Support\Database\SystemSettingsTestHelper;

/**
 * @internal
 */
final class SystemSettingModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

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

    public function testDefaultSettingsAreSeeded(): void
    {
        $model = model(SystemSettingModel::class);

        $this->assertSame(12, $model->getValue('password_policy.min_length'));
        $this->assertTrue($model->getValue('password_policy.require_uppercase'));
        $this->assertTrue($model->getValue('password_policy.require_lowercase'));
        $this->assertTrue($model->getValue('password_policy.require_digit'));
        $this->assertTrue($model->getValue('password_policy.require_special'));
        $this->assertSame('@$!%*?&#^_-', $model->getValue('password_policy.special_chars'));
        $this->assertTrue($model->getValue('mfa.required_for_admin_ops'));
        $this->assertFalse($model->getValue('maintenance.enabled'));
        $this->assertSame(5, $model->getValue('lockout.max_attempts'));
    }

    public function testGetValueReturnsDefaultWhenMissing(): void
    {
        $model = model(SystemSettingModel::class);

        $this->assertNull($model->getValue('missing.key'));
        $this->assertSame('fallback', $model->getValue('missing.key', 'fallback'));
        $this->assertSame(42, $model->getValue('missing.key', 42));
    }

    public function testBooleanTypeCasting(): void
    {
        $model = model(SystemSettingModel::class);

        $this->assertTrue($model->getValue('password_policy.require_uppercase'));
        $this->assertFalse($model->getValue('maintenance.enabled'));
    }

    public function testIntegerTypeCasting(): void
    {
        $model = model(SystemSettingModel::class);

        $this->assertSame(12, $model->getValue('password_policy.min_length'));
        $this->assertSame(1800, $model->getValue('lockout.duration_seconds'));
    }

    public function testJsonTypeCasting(): void
    {
        $model = model(SystemSettingModel::class);

        $model->setValue('test.json', ['a' => 1, 'b' => 'two'], 'json', 'test', 'JSON test');

        $this->assertSame(['a' => 1, 'b' => 'two'], $model->getValue('test.json'));
    }

    public function testSetValueInsertsNewRow(): void
    {
        $model = model(SystemSettingModel::class);

        $this->assertTrue($model->setValue('test.new', 'hello', 'string', 'test', 'New test'));
        $this->assertSame('hello', $model->getValue('test.new'));
    }

    public function testSetValueUpdatesExistingRow(): void
    {
        $model = model(SystemSettingModel::class);

        $this->assertTrue($model->setValue('password_policy.min_length', 8, 'integer'));
        $this->assertSame(8, $model->getValue('password_policy.min_length'));
    }

    public function testDuplicateKeyInsertsOnlyOneRow(): void
    {
        $model = model(SystemSettingModel::class);

        $countBefore = $this->db->table($this->db->getPrefix() . 'system_settings')->countAllResults();

        // Re-insert an existing key with the same value
        $model->setValue('password_policy.min_length', 12, 'integer');

        $countAfter = $this->db->table($this->db->getPrefix() . 'system_settings')->countAllResults();

        $this->assertSame($countBefore, $countAfter);
    }

    public function testGetByCategory(): void
    {
        $model = model(SystemSettingModel::class);

        $policy = $model->getByCategory('policy');

        $this->assertArrayHasKey('password_policy.min_length', $policy);
        $this->assertSame(12, $policy['password_policy.min_length']);
    }

    public function testGetAllContainsMetadata(): void
    {
        $model = model(SystemSettingModel::class);

        $all = $model->getAll();

        $this->assertArrayHasKey('password_policy.min_length', $all);
        $this->assertArrayHasKey('value', $all['password_policy.min_length']);
        $this->assertArrayHasKey('data_type', $all['password_policy.min_length']);
        $this->assertArrayHasKey('category', $all['password_policy.min_length']);
    }
}
