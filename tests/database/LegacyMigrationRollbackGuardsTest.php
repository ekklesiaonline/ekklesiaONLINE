<?php

declare(strict_types=1);

require_once APPPATH . 'Database/Migrations/2026-08-04-010156_MakeCallTaskGuestIdNullable.php';
require_once APPPATH . 'Database/Migrations/2026-08-04-014847_AddConvertedUserIdsToGuest.php';

use App\Database\Migrations\AddConvertedUserIdsToGuest;
use App\Database\Migrations\MakeCallTaskGuestIdNullable;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class LegacyMigrationRollbackGuardsTest extends CIUnitTestCase
{
    private \CodeIgniter\Database\BaseConnection $rollbackDb;
    private \CodeIgniter\Database\Forge $rollbackForge;

    protected function setUp(): void
    {
        parent::setUp();

        $dbConfig = config(\Config\Database::class)->tests;
        $dbConfig['DBPrefix'] = 'legacy_rollback_';
        $this->rollbackDb = \Config\Database::connect($dbConfig);
        $this->rollbackForge = \Config\Database::forge($dbConfig);
        $this->dropLegacyTables();
    }

    protected function tearDown(): void
    {
        $this->dropLegacyTables();

        parent::tearDown();
    }

    public function testRetiredTablesCanBeAbsentDuringRollback(): void
    {
        (new MakeCallTaskGuestIdNullable($this->rollbackForge))->down();
        (new AddConvertedUserIdsToGuest($this->rollbackForge))->down();

        self::assertFalse($this->rollbackDb->tableExists('call_task'));
        self::assertFalse($this->rollbackDb->tableExists('guest'));
    }

    public function testRollbackDropsOnlyGuestColumnsThatStillExist(): void
    {
        $this->rollbackForge->addField([
            'id'                 => ['type' => 'INTEGER', 'auto_increment' => true],
            'converted_user_id'  => ['type' => 'INTEGER', 'null' => true],
            'unrelated_column'   => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
        ]);
        $this->rollbackForge->addKey('id', true);
        $this->rollbackForge->createTable('guest');

        (new AddConvertedUserIdsToGuest($this->rollbackForge))->down();

        self::assertFalse($this->rollbackDb->fieldExists('converted_user_id', 'guest'));
        self::assertTrue($this->rollbackDb->fieldExists('unrelated_column', 'guest'));
    }

    public function testCallTaskRollbackSkipsMissingGuestIdColumn(): void
    {
        $this->rollbackForge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
        ]);
        $this->rollbackForge->addKey('id', true);
        $this->rollbackForge->createTable('call_task');

        (new MakeCallTaskGuestIdNullable($this->rollbackForge))->down();

        self::assertTrue($this->rollbackDb->tableExists('call_task'));
        self::assertFalse($this->rollbackDb->fieldExists('guest_id', 'call_task'));
    }

    private function dropLegacyTables(): void
    {
        $this->rollbackForge->dropTable('guest', true);
        $this->rollbackForge->dropTable('call_task', true);
    }
}
