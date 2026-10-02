<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ProvinceStaffDirectoryService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\AuthGroups;
use Tests\Support\Database\CanonicalTestSchema;

/**
 * @internal
 */
final class ProvinceStaffDirectoryServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        CanonicalTestSchema::ensure($this->db);
        foreach ([
            'province',
            'diocese',
            'parish',
            'users',
            'auth_identities',
            'auth_groups_users',
            'profile',
        ] as $table) {
            CanonicalTestSchema::reset($this->db, $table);
        }
    }

    public function testDiocesanStaffIncludesDioceseAdminParishAdminAndClergy(): void
    {
        $this->db->table('province')->insert([
            'id'              => 1,
            'name'            => 'Province 1',
            'province_number' => 1,
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        $this->db->table('diocese')->insert([
            'id'          => 10,
            'name'        => 'Diocese 10',
            'province_id' => 1,
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        $this->db->table('parish')->insert([
            'id'          => 100,
            'name'        => 'Parish 100',
            'diocese_id'  => 10,
            'parish_ueid' => 'UEID-100',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        // 1. Diocese Admin
        $this->db->table('users')->insert(['username' => 'diocese_adm', 'active' => 1, 'created_at' => date('Y-m-d H:i:s')]);
        $dioceseAdmId = (int) $this->db->insertID();
        $this->db->table('auth_identities')->insert([
            'user_id'    => $dioceseAdmId,
            'type'       => 'email_password',
            'secret'     => 'diocese.admin@example.org',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('auth_groups_users')->insert([
            'user_id'    => $dioceseAdmId,
            'group'      => AuthGroups::DIOCESE_ADMIN,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('profile')->insert([
            'user_id'    => $dioceseAdmId,
            'diocese_id' => 10,
            'first_name' => 'Diocese',
            'last_name'  => 'Admin',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // 2. Parish Admin
        $this->db->table('users')->insert(['username' => 'parish_adm', 'active' => 1, 'created_at' => date('Y-m-d H:i:s')]);
        $parishAdmId = (int) $this->db->insertID();
        $this->db->table('auth_identities')->insert([
            'user_id'    => $parishAdmId,
            'type'       => 'email_password',
            'secret'     => 'parish.admin@example.org',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('auth_groups_users')->insert([
            'user_id'    => $parishAdmId,
            'group'      => AuthGroups::PARISH_ADMIN,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('profile')->insert([
            'user_id'    => $parishAdmId,
            'parish_id'  => 100,
            'first_name' => 'Parish',
            'last_name'  => 'Admin',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $service = new ProvinceStaffDirectoryService($this->db);
        $staff = $service->diocesanStaff('profile', 1, 10);

        $emails = array_column($staff, 'email');
        $this->assertContains('diocese.admin@example.org', $emails);
        $this->assertContains('parish.admin@example.org', $emails);
    }
}
