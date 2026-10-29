<?php

use App\Models\LiturgicalCalendarEntryModel;
use CodeIgniter\Config\Factories;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Diocese\Controllers\LiturgicalCalendar;

/**
 * The canonical schema names the table `liturgical_calendar_entries`; the
 * model and the diocesan liturgical-source controller must both resolve it.
 *
 * @internal
 */
final class LiturgicalCalendarTableResolutionTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    protected function setUp(): void
    {
        parent::setUp();

        $forge = \Config\Database::forge($this->DBGroup);
        $forge->dropTable('liturgical_calendar_entries', true);
        $forge->addField([
            'id'                 => ['type' => 'INTEGER', 'auto_increment' => true],
            'parish_id'          => ['type' => 'INTEGER', 'null' => true],
            'owner_province_id'  => ['type' => 'INTEGER', 'null' => true],
            'owner_diocese_id'   => ['type' => 'INTEGER', 'null' => true],
            'created_by_user_id' => ['type' => 'INTEGER', 'null' => true],
            'source_tier'        => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'system'],
            'is_system_template' => ['type' => 'INTEGER', 'default' => 1],
            'name'               => ['type' => 'VARCHAR', 'constraint' => 255],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('liturgical_calendar_entries');

        Factories::reset('models');
    }

    protected function tearDown(): void
    {
        \Config\Database::forge($this->DBGroup)->dropTable('liturgical_calendar_entries', true);
        Factories::reset('models');

        parent::tearDown();
    }

    public function testModelResolvesCanonicalPluralTable(): void
    {
        $this->assertSame('liturgical_calendar_entries', (new LiturgicalCalendarEntryModel())->resolvedTable());
    }

    public function testControllerDetectsOwnershipMetadataOnCanonicalTable(): void
    {
        $controller = new LiturgicalCalendar();

        $this->assertTrue($this->getPrivateMethodInvoker($controller, 'supportsOwnershipMetadata')());
    }
}
