<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ProvinceDashboardDataService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Database\CanonicalTestSchema;

/**
 * @internal
 */
final class ProvinceDashboardDataServiceTest extends CIUnitTestCase
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
            'person',
            'profile',
            'auth_groups_users',
            'parish_affiliation',
            'service',
            'attendance',
        ] as $table) {
            CanonicalTestSchema::reset($this->db, $table);
        }
    }

    private function seedProvinceWithDioceses(): array
    {
        $this->db->table('province')->insert([
            'id'   => 1,
            'name' => 'Test Province',
        ]);

        $dioceses = [];
        foreach (['Alpha Diocese', 'Bravo Diocese', 'Charlie Diocese', 'Delta Diocese'] as $i => $name) {
            $id = $i + 1;
            $this->db->table('diocese')->insert([
                'id'          => $id,
                'province_id' => 1,
                'name'        => $name,
            ]);
            $this->db->table('parish')->insert([
                'id'         => $id,
                'diocese_id' => $id,
                'name'       => "Parish {$name}",
            ]);
            $dioceses[$id] = $name;
        }

        return $dioceses;
    }

    private function seedMembersServicesAndAttendance(array $dioceses, string $datePrefix = '2026-10'): void
    {
        $userId = 1;
        $personId = 1;
        $serviceId = 1;

        foreach ($dioceses as $dioceseId => $name) {
            $parishId = $dioceseId;

            // Two members per parish.
            for ($i = 0; $i < 2; $i++) {
                $this->db->table('users')->insert([
                    'id'       => $userId,
                    'username' => "member{$userId}",
                    'active'   => 1,
                ]);
                $this->db->table('person')->insert([
                    'id'         => $personId,
                    'first_name' => "Member{$personId}",
                    'last_name'  => 'Test',
                    'is_active'  => 1,
                ]);
                $this->db->table('parish_affiliation')->insert([
                    'parish_id'         => $parishId,
                    'person_id'         => $personId,
                    'linked_user_id'    => $userId,
                    'membership_status' => 'member',
                ]);
                $userId++;
                $personId++;
            }

            // One visitor per parish.
            $this->db->table('users')->insert([
                'id'       => $userId,
                'username' => "visitor{$userId}",
                'active'   => 1,
            ]);
            $this->db->table('person')->insert([
                'id'         => $personId,
                'first_name' => "Visitor{$personId}",
                'last_name'  => 'Test',
                'is_active'  => 1,
            ]);
            $this->db->table('parish_affiliation')->insert([
                'parish_id'         => $parishId,
                'person_id'         => $personId,
                'linked_user_id'    => $userId,
                'membership_status' => 'visitor',
            ]);
            $userId++;
            $personId++;

            // Sunday service with 25 attendances.
            $this->db->table('service')->insert([
                'id'           => $serviceId,
                'parish_id'    => $parishId,
                'service_date' => "{$datePrefix}-05", // Sunday
            ]);
            for ($i = 0; $i < 25; $i++) {
                $this->db->table('attendance')->insert([
                    'service_id' => $serviceId,
                    'parish_id'  => $parishId,
                    'attended'   => 1,
                ]);
            }
            $serviceId++;

            // Weekday service with 10 attendances.
            $this->db->table('service')->insert([
                'id'           => $serviceId,
                'parish_id'    => $parishId,
                'service_date' => "{$datePrefix}-08", // Thursday
            ]);
            for ($i = 0; $i < 10; $i++) {
                $this->db->table('attendance')->insert([
                    'service_id' => $serviceId,
                    'parish_id'  => $parishId,
                    'attended'   => 1,
                ]);
            }
            $serviceId++;
        }
    }

    private function seedActiveDiocesanAdmin(int $dioceseId, int $userId): void
    {
        $this->db->table('users')->insert([
            'id'       => $userId,
            'username' => "diocesanadmin{$userId}",
            'active'   => 1,
        ]);
        $this->db->table('profile')->insert([
            'user_id'    => $userId,
            'diocese_id' => $dioceseId,
            'first_name' => 'Admin',
            'last_name'  => 'Test',
        ]);
        $this->db->table('auth_groups_users')->insert([
            'user_id' => $userId,
            '"group"' => 'dioceseadmin',
        ]);
    }

    public function testCountDioceseSummaryRows(): void
    {
        $this->seedProvinceWithDioceses();

        $service = new ProvinceDashboardDataService();

        $this->assertSame(4, $service->countDioceseSummaryRows(1));
    }

    public function testGetDioceseSummaryRowsPaginatesCorrectly(): void
    {
        $dioceses = $this->seedProvinceWithDioceses();
        $this->seedMembersServicesAndAttendance($dioceses);

        $service = new ProvinceDashboardDataService();
        $page1 = $service->getDioceseSummaryRows('month', null, null, 1, null, 2, 0);
        $page2 = $service->getDioceseSummaryRows('month', null, null, 1, null, 2, 2);

        $this->assertCount(2, $page1);
        $this->assertCount(2, $page2);
        $this->assertSame('Alpha Diocese', $page1[0]['diocese_name']);
        $this->assertSame('Bravo Diocese', $page1[1]['diocese_name']);
        $this->assertSame('Charlie Diocese', $page2[0]['diocese_name']);
        $this->assertSame('Delta Diocese', $page2[1]['diocese_name']);
    }

    public function testGetDioceseSummaryRowsReturnsCorrectAggregates(): void
    {
        $dioceses = $this->seedProvinceWithDioceses();
        $this->seedMembersServicesAndAttendance($dioceses);

        $service = new ProvinceDashboardDataService();
        $rows = $service->getDioceseSummaryRows('month', null, null, 1);

        $alpha = $rows[0];
        $this->assertSame(1, $alpha['parishes_count']);
        $this->assertSame(2, $alpha['total_members']);
        $this->assertSame(1, $alpha['visitor_count']);
        $this->assertSame(3, $alpha['total_people']); // 2 members + 1 visitor
        $this->assertSame(35, $alpha['total_attendance']); // 25 Sunday + 10 weekday
        $this->assertSame(2, $alpha['total_services']);
    }

    public function testGetProvinceSummaryTotalsMatchesFullPageSum(): void
    {
        $dioceses = $this->seedProvinceWithDioceses();
        $this->seedMembersServicesAndAttendance($dioceses);

        $service = new ProvinceDashboardDataService();
        $allRows = $service->getDioceseSummaryRows('month', null, null, 1);
        $totals = $service->getProvinceSummaryTotals('month', null, null, 1);

        $this->assertSame(4, $totals['total_dioceses']);
        $this->assertSame(4, $totals['total_parishes']);
        $this->assertSame(
            array_sum(array_column($allRows, 'total_people')),
            $totals['total_people']
        );
        $this->assertSame(
            array_sum(array_column($allRows, 'total_attendance')),
            $totals['total_attendance']
        );
    }

    public function testGetDioceseHealthRowsRestrictedToVisibleDioceses(): void
    {
        $dioceses = $this->seedProvinceWithDioceses();
        $this->seedMembersServicesAndAttendance($dioceses);

        // Only the first two dioceses get active admins.
        $this->seedActiveDiocesanAdmin(1, 100);
        $this->seedActiveDiocesanAdmin(2, 101);

        $service = new ProvinceDashboardDataService();
        $page1 = $service->getDioceseSummaryRows('month', null, null, 1, null, 2, 0);
        $visibleIds = array_column($page1, 'diocese_id');
        $health = $service->getDioceseHealthRows($page1, 1, $visibleIds);

        $this->assertCount(2, $health);
        $this->assertSame([1, 2], array_column($health, 'diocese_id'));
    }

    public function testGetDioceseHealthRowsReflectsAdminAndServiceStatus(): void
    {
        $dioceses = $this->seedProvinceWithDioceses();
        $this->seedMembersServicesAndAttendance($dioceses);
        $this->seedActiveDiocesanAdmin(1, 100);

        $service = new ProvinceDashboardDataService();
        $rows = $service->getDioceseSummaryRows('month', null, null, 1);
        $health = $service->getDioceseHealthRows($rows, 1);

        $byDiocese = array_column($health, null, 'diocese_id');
        $this->assertSame('healthy', $byDiocese[1]['health_status']);
        $this->assertSame('attention', $byDiocese[2]['health_status']);
    }

    public function testEmptyProvinceReturnsZeroedRowsAndTotals(): void
    {
        $this->seedProvinceWithDioceses();

        $service = new ProvinceDashboardDataService();
        $rows = $service->getDioceseSummaryRows('month', null, null, 1, null, 2, 0);
        $totals = $service->getProvinceSummaryTotals('month', null, null, 1);

        $this->assertCount(2, $rows);
        $this->assertSame(0, $rows[0]['total_members']);
        $this->assertSame(0, $rows[0]['total_attendance']);
        $this->assertSame(4, $totals['total_dioceses']);
        $this->assertSame(0, $totals['total_members']);
        $this->assertSame(0, $totals['total_attendance']);
    }
}
