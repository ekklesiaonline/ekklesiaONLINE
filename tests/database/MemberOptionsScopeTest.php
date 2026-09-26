<?php

use App\Models\MemberParishAffiliationModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Database\CanonicalTestSchema;

/**
 * Covers the slim AJAX member-option reads that replaced full-roster
 * getParishMembers() loads on picker pages.
 *
 * @internal
 */
final class MemberOptionsScopeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    private const TOUCHED_TABLES = [
        'parish_affiliation',
        'auth_groups_users',
        'auth_identities',
        'profile',
        'users',
        'parish',
        'diocese',
        'dioceses',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        CanonicalTestSchema::ensure($this->db);
        $this->cleanTouchedTables();
    }

    protected function tearDown(): void
    {
        // Rows persist on the shared :memory: connection — leave none behind
        // so later tests that recreate tables don't collide on primary keys.
        $this->cleanTouchedTables();
        parent::tearDown();
    }

    private function cleanTouchedTables(): void
    {
        foreach (self::TOUCHED_TABLES as $table) {
            try {
                $this->db->table($table)->emptyTable();
            } catch (\Throwable $e) {
                // Table may not exist in this execution order — nothing to clean.
            }
        }
    }

    public function testSearchMemberOptionsIsScopedToParish(): void
    {
        $dioceseId  = $this->insertDiocese('Option Diocese');
        $parishA    = $this->insertParish($dioceseId, 'St. Alpha');
        $parishB    = $this->insertParish($dioceseId, 'St. Beta');

        $memberA = $this->insertUser('option.member.a');
        $memberB = $this->insertUser('option.member.b');

        $this->insertProfile($memberA, 'Shared', 'SurnameA');
        $this->insertProfile($memberB, 'Shared', 'SurnameB');

        $this->insertAffiliation($memberA, $parishA, 'member');
        $this->insertAffiliation($memberB, $parishB, 'member');

        $rows = model(MemberParishAffiliationModel::class)->searchMemberOptions($parishA, 'Shared', true, 20);

        $this->assertCount(1, $rows);
        $this->assertSame($memberA, (int) $rows[0]['id']);
        $this->assertStringContainsString('SurnameA', (string) $rows[0]['full_name']);
    }

    public function testSearchMemberOptionsHonoursLimit(): void
    {
        $dioceseId = $this->insertDiocese('Limit Diocese');
        $parishId  = $this->insertParish($dioceseId, 'St. Limit');

        for ($i = 0; $i < 5; $i++) {
            $userId = $this->insertUser('option.limit.' . $i);
            $this->insertProfile($userId, 'Lim', 'Member' . $i);
            $this->insertAffiliation($userId, $parishId, 'member');
        }

        $rows = model(MemberParishAffiliationModel::class)->searchMemberOptions($parishId, '', true, 2);

        $this->assertCount(2, $rows);
    }

    public function testSearchMemberOptionsMembersOnlyFiltersVisitors(): void
    {
        $dioceseId = $this->insertDiocese('Status Diocese');
        $parishId  = $this->insertParish($dioceseId, 'St. Status');

        $memberId  = $this->insertUser('option.status.member');
        $visitorId = $this->insertUser('option.status.visitor');

        $this->insertProfile($memberId, 'Mem', 'StatusA');
        $this->insertProfile($visitorId, 'Vis', 'StatusB');

        $this->insertAffiliation($memberId, $parishId, 'member');
        $this->insertAffiliation($visitorId, $parishId, 'visitor');

        $membersOnly = model(MemberParishAffiliationModel::class)->searchMemberOptions($parishId, 'Status', true, 20);
        $this->assertCount(1, $membersOnly);
        $this->assertSame($memberId, (int) $membersOnly[0]['id']);

        $all = model(MemberParishAffiliationModel::class)->searchMemberOptions($parishId, 'Status', false, 20);
        $ids = array_map(static fn(array $row): int => (int) $row['id'], $all);
        sort($ids);
        $this->assertSame([$memberId, $visitorId], array_values($ids));
    }

    public function testMemberOptionReturnsNullOutsideParish(): void
    {
        $dioceseId = $this->insertDiocese('Single Diocese');
        $parishId  = $this->insertParish($dioceseId, 'St. Solo');
        $otherId   = $this->insertParish($dioceseId, 'St. Other');

        $userId = $this->insertUser('option.single.user');
        $this->insertProfile($userId, 'Solo', 'Person');
        $this->insertAffiliation($userId, $otherId, 'member');

        $model = model(MemberParishAffiliationModel::class);

        $this->assertNull($model->memberOption($parishId, $userId));

        $option = $model->memberOption($otherId, $userId);
        $this->assertNotNull($option);
        $this->assertSame($userId, (int) $option['id']);
        $this->assertStringContainsString('Solo Person', (string) $option['text']);
    }

    public function testMemberOptionsReturnsOnlyInParishIds(): void
    {
        $dioceseId = $this->insertDiocese('Multi Diocese');
        $parishId  = $this->insertParish($dioceseId, 'St. Multi');
        $otherId   = $this->insertParish($dioceseId, 'St. Remote');

        $inParish  = $this->insertUser('option.multi.in');
        $outParish = $this->insertUser('option.multi.out');

        $this->insertProfile($inParish, 'In', 'Parish');
        $this->insertProfile($outParish, 'Out', 'Parish');

        $this->insertAffiliation($inParish, $parishId, 'member');
        $this->insertAffiliation($outParish, $otherId, 'member');

        $options = model(MemberParishAffiliationModel::class)
            ->memberOptions($parishId, [$inParish, $outParish, 0, -5]);

        $this->assertCount(1, $options);
        $this->assertSame($inParish, (int) $options[0]['id']);
        $this->assertSame(['id', 'text'], array_keys($options[0]));
    }

    private function insertDiocese(string $name): int
    {
        $this->db->table('diocese')->insert([
            'name' => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertParish(int $dioceseId, string $name): int
    {
        $this->db->table('parish')->insert([
            'diocese_id' => $dioceseId,
            'name' => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertUser(string $username): int
    {
        $this->db->table('users')->insert([
            'username' => $username,
            'active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $userId = (int) $this->db->insertID();

        $this->db->table('auth_identities')->insert([
            'type' => 'email_password',
            'secret' => $username . '@example.com',
            'user_id' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $userId;
    }

    private function insertProfile(int $userId, string $firstName, string $lastName): void
    {
        $this->db->table('profile')->insert([
            'user_id' => $userId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function insertAffiliation(int $userId, int $parishId, string $status): void
    {
        // is_member is a generated column derived from membership_status —
        // never insert it directly.
        $this->db->table('parish_affiliation')->insert([
            'linked_user_id' => $userId,
            'parish_id' => $parishId,
            'membership_status' => $status,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
