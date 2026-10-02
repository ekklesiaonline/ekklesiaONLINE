<?php

declare(strict_types=1);

namespace Tests;

use App\Database\Migrations\CreateClergyDeaneryFoundation;
use App\Database\Migrations\StageClergyReviewCandidates;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

final class ClergyMigrationRehearsalTest extends CIUnitTestCase
{
    public function testAdditiveMigrationsStageLegacyEvidenceWithoutPromotion(): void
    {
        require_once APPPATH . 'Database/Migrations/2026-10-02-185843_CreateClergyDeaneryFoundation.php';
        require_once APPPATH . 'Database/Migrations/2026-10-02-185843_StageClergyReviewCandidates.php';
        $db = Database::connect([
            'DSN' => '', 'hostname' => '127.0.0.1', 'username' => '', 'password' => '',
            'database' => ':memory:', 'DBDriver' => 'SQLite3', 'DBPrefix' => '',
            'pConnect' => false, 'DBDebug' => true, 'charset' => 'utf8',
        ], false);
        $legacy = [
            'person' => 'id INTEGER PRIMARY KEY',
            'users' => 'id INTEGER PRIMARY KEY, active INTEGER',
            'diocese' => 'id INTEGER PRIMARY KEY',
            'parish' => 'id INTEGER PRIMARY KEY, diocese_id INTEGER',
            'leadership_position' => "id INTEGER PRIMARY KEY, user_id INTEGER, person_id INTEGER, scope_type TEXT DEFAULT 'parish', parish_id INTEGER, diocese_id INTEGER, province_id INTEGER, role_code TEXT, role_category TEXT, clergy_order TEXT, active INTEGER",
            'parish_report' => "id INTEGER PRIMARY KEY, parish_id INTEGER, report_year INTEGER, report_status TEXT DEFAULT 'draft', frozen_snapshot_json TEXT, finalized_by INTEGER, finalized_at TEXT",
            'parish_report_storage' => 'id INTEGER PRIMARY KEY, parish_id INTEGER, report_year INTEGER',
            'auth_permissions_users' => 'id INTEGER PRIMARY KEY, user_id INTEGER, permission TEXT',
            'auth_groups_users' => 'id INTEGER PRIMARY KEY, user_id INTEGER, `group` TEXT',
            'user_person' => 'id INTEGER PRIMARY KEY, user_id INTEGER, person_id INTEGER, is_primary INTEGER',
            'profile' => 'id INTEGER PRIMARY KEY, user_id INTEGER, person_id INTEGER',
        ];
        foreach ($legacy as $table => $fields) {
            $db->query('CREATE TABLE ' . $db->protectIdentifiers($table) . ' (' . $fields . ')');
        }
        $db->table('person')->insert(['id' => 1]);
        $db->table('users')->insert(['id' => 1, 'active' => 1]);
        $db->table('diocese')->insert(['id' => 1]);
        $db->table('parish')->insert(['id' => 1, 'diocese_id' => 1]);
        $db->table('user_person')->insert(['id' => 1, 'user_id' => 1, 'person_id' => 1, 'is_primary' => 1]);
        $db->table('profile')->insert(['id' => 1, 'user_id' => 1, 'person_id' => 1]);
        $db->table('auth_groups_users')->insert(['id' => 1, 'user_id' => 1, 'group' => 'clergy']);
        $db->table('leadership_position')->insert([
            'id' => 1, 'user_id' => 1, 'person_id' => 1, 'scope_type' => 'parish',
            'parish_id' => 1, 'role_code' => 'rector', 'role_category' => 'clergy',
            'clergy_order' => 'priest', 'active' => 1,
        ]);
        $foundation = new CreateClergyDeaneryFoundation(Database::forge($db));
        $foundation->up();
        self::assertTrue($db->tableExists('deanery'));
        self::assertTrue($db->tableExists('parish_report_signature'));
        self::assertContains('deanery_id', array_column(
            $db->query('PRAGMA table_info(leadership_position)')->getResultArray(), 'name'
        ));
        self::assertSame(0, (int) $db->table('leadership_position')->where('id', 1)
            ->get()->getRow('pastoral_in_charge'));
        self::assertNull($db->table('leadership_position')->where('id', 1)->get()->getRow('approved_at'));
        $candidates = new StageClergyReviewCandidates(Database::forge($db));
        $candidates->up();
        $candidates->up();
        self::assertSame(2, $db->table('clergy_transition_candidate')->countAllResults());
        self::assertSame(0, $db->table('clergy_ordination')->countAllResults());
        self::assertSame(0, $db->table('clergy_access_grant')->countAllResults());
        self::assertSame(0, $db->table('auth_permissions_users')->countAllResults());
        self::assertSame('clergy', $db->table('auth_groups_users')->where('user_id', 1)->get()->getRow('group'));
        $candidates->down();
        self::assertSame(0, $db->table('clergy_transition_candidate')->countAllResults());
        $foundation->down();
        self::assertFalse($db->tableExists('deanery'));
        self::assertSame('rector', $db->table('leadership_position')->where('id', 1)->get()->getRow('role_code'));
        $db->close();
    }
}
