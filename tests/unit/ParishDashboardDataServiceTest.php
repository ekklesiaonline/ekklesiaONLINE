<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ParishDashboardDataService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\AuthGroups;
use Tests\Support\Database\CanonicalTestSchema;

/**
 * @internal
 */
final class ParishDashboardDataServiceTest extends CIUnitTestCase
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
            'auth_groups_users',
            'profile',
            'parish_affiliation',
        ] as $table) {
            CanonicalTestSchema::reset($this->db, $table);
        }
    }

    public function testCountBirthdaysExcludesDioceseAdmin(): void
    {
        $this->db->table('parish')->insert([
            'id'          => 1,
            'name'        => 'Test Parish',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        $this->db->table('users')->insert(['id' => 10, 'username' => 'member1', 'active' => 1, 'created_at' => date('Y-m-d H:i:s')]);
        $this->db->table('users')->insert(['id' => 20, 'username' => 'diocese_admin', 'active' => 1, 'created_at' => date('Y-m-d H:i:s')]);

        $today = new \DateTimeImmutable('now');
        $birthDate = '1990-' . $today->format('m-d');

        // Ordinary member
        $this->db->table('profile')->insert([
            'user_id'    => 10,
            'parish_id'  => 1,
            'first_name' => 'Ordinary',
            'last_name'  => 'Member',
            'birth_date' => $birthDate,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('parish_affiliation')->insert([
            'parish_id'         => 1,
            'linked_user_id'    => 10,
            'membership_status' => 'member',
            'created_at'        => date('Y-m-d H:i:s'),
        ]);

        // Diocese Admin
        $this->db->table('profile')->insert([
            'user_id'    => 20,
            'parish_id'  => 1,
            'first_name' => 'Diocese',
            'last_name'  => 'Admin',
            'birth_date' => $birthDate,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('parish_affiliation')->insert([
            'parish_id'         => 1,
            'linked_user_id'    => 20,
            'membership_status' => 'member',
            'created_at'        => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('auth_groups_users')->insert([
            'user_id'    => 20,
            'group'      => AuthGroups::DIOCESE_ADMIN,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $service = new ParishDashboardDataService();
        $ref = new \ReflectionClass($service);
        $method = $ref->getMethod('countBirthdaysThisWeek');
        $method->setAccessible(true);

        $weekStart = $today->modify('monday this week')->setTime(0, 0, 0);
        $weekEnd = $weekStart->modify('+6 days')->setTime(23, 59, 59);

        $count = $method->invoke($service, $this->db, 1, $weekStart, $weekEnd);

        // Only user 10 should be counted; dioceseadmin user 20 should be excluded!
        $this->assertSame(1, $count);
    }

    public function testCountBirthdaysHandlesYearBoundary(): void
    {
        $this->db->table('parish')->insert([
            'id' => 1,
            'name' => 'Test Parish',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        foreach ([10 => '1990-12-31', 20 => '1990-01-02', 30 => '1990-02-01'] as $userId => $birthDate) {
            $this->db->table('users')->insert([
                'id' => $userId,
                'username' => 'member' . $userId,
                'active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $this->db->table('profile')->insert([
                'user_id' => $userId,
                'parish_id' => 1,
                'first_name' => 'Member',
                'last_name' => (string) $userId,
                'birth_date' => $birthDate,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $this->db->table('parish_affiliation')->insert([
                'parish_id' => 1,
                'linked_user_id' => $userId,
                'membership_status' => 'member',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $service = new ParishDashboardDataService();
        $method = (new \ReflectionClass($service))->getMethod('countBirthdaysThisWeek');
        $method->setAccessible(true);

        $count = $method->invoke(
            $service,
            $this->db,
            1,
            new \DateTimeImmutable('2025-12-29 00:00:00'),
            new \DateTimeImmutable('2026-01-04 23:59:59')
        );

        $this->assertSame(2, $count);
    }
}
