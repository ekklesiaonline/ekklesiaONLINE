<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\DioceseDashboardDataService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Database\CanonicalTestSchema;

/**
 * @internal
 */
final class DioceseDashboardDataServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    private const PERIOD_START = '2026-10-01';
    private const PERIOD_END   = '2026-10-31';

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
            'parish_affiliation',
            'service',
            'service_metric',
        ] as $table) {
            CanonicalTestSchema::reset($this->db, $table);
        }
    }

    private function seedDioceseWithParishes(): array
    {
        $this->db->table('province')->insert([
            'id'   => 1,
            'name' => 'Test Province',
        ]);
        $this->db->table('diocese')->insert([
            'id'          => 1,
            'province_id' => 1,
            'name'        => 'Test Diocese',
        ]);

        $parishes = [];
        foreach (['Alpha', 'Bravo', 'Charlie', 'Delta'] as $i => $name) {
            $id = $i + 1;
            $this->db->table('parish')->insert([
                'id'         => $id,
                'diocese_id' => 1,
                'name'       => $name,
            ]);
            $parishes[$id] = $name;
        }

        return $parishes;
    }

    private function seedMembersAndServices(array $parishes, string $datePrefix = '2026-10'): void
    {
        $userId = 1;
        $personId = 1;
        foreach ($parishes as $parishId => $name) {
            // Each parish gets two members and one visitor.
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

            // Visitor
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

            // Two Sunday services and one weekday service in the period.
            $serviceId = ($parishId - 1) * 3 + 1;
            $this->db->table('service')->insert([
                'id'           => $serviceId,
                'parish_id'    => $parishId,
                'service_date' => "{$datePrefix}-04", // Sunday
            ]);
            $this->db->table('service_metric')->insert([
                'service_id'             => $serviceId,
                'reported_present_count' => 25,
            ]);

            $serviceId = ($parishId - 1) * 3 + 2;
            $this->db->table('service')->insert([
                'id'           => $serviceId,
                'parish_id'    => $parishId,
                'service_date' => "{$datePrefix}-11", // Sunday
            ]);
            $this->db->table('service_metric')->insert([
                'service_id'             => $serviceId,
                'reported_present_count' => 35,
            ]);

            $serviceId = ($parishId - 1) * 3 + 3;
            $this->db->table('service')->insert([
                'id'           => $serviceId,
                'parish_id'    => $parishId,
                'service_date' => "{$datePrefix}-08", // Thursday
            ]);
            $this->db->table('service_metric')->insert([
                'service_id'             => $serviceId,
                'reported_present_count' => 10,
            ]);
        }
    }

    public function testCountParishSummaryRowsReturnsAllParishes(): void
    {
        $this->seedDioceseWithParishes();

        $service = new DioceseDashboardDataService();

        $this->assertSame(4, $service->countParishSummaryRows(1));
    }

    public function testCountParishSummaryRowsWithParishFilter(): void
    {
        $this->seedDioceseWithParishes();

        $service = new DioceseDashboardDataService();

        $this->assertSame(1, $service->countParishSummaryRows(1, 2));
    }

    public function testGetParishSummaryRowsReturnsAllRowsWithoutPagination(): void
    {
        $parishes = $this->seedDioceseWithParishes();
        $this->seedMembersAndServices($parishes);

        $service = new DioceseDashboardDataService();
        $rows = $service->getParishSummaryRows('custom', self::PERIOD_START, self::PERIOD_END, 1);

        $this->assertCount(4, $rows);
        $this->assertSame('Alpha', $rows[0]['parish_name']);
        $this->assertSame('Delta', $rows[3]['parish_name']);

        // Each parish: 2 members, 60 Sunday attendance / 2 services = 30 ASA,
        // 10 weekday / 1 service = 10 AWA, 70 total / 3 services = 23.33 avg.
        $alpha = $rows[0];
        $this->assertSame(2, $alpha['total_members']);
        $this->assertSame(2, $alpha['active_members']);
        $this->assertSame(70, $alpha['total_attendance']);
        $this->assertSame(3, $alpha['total_services']);
        $this->assertSame(30.0, $alpha['asa']);
        $this->assertSame(10.0, $alpha['awa']);
        $this->assertSame(23.33, $alpha['average_per_service']);
    }

    public function testGetParishSummaryRowsPaginatesCorrectly(): void
    {
        $parishes = $this->seedDioceseWithParishes();
        $this->seedMembersAndServices($parishes);
        // 'Bravo' sorts before the page boundary; soft-deleting it must shift
        // page contents and the total count, not leave a gap.
        $this->db->table('parish')->where('id', 2)->update(['deleted_at' => '2026-10-05 00:00:00']);

        $service = new DioceseDashboardDataService();
        $page1 = $service->getParishSummaryRows('custom', self::PERIOD_START, self::PERIOD_END, 1, null, 2, 0);
        $page2 = $service->getParishSummaryRows('custom', self::PERIOD_START, self::PERIOD_END, 1, null, 2, 2);

        $this->assertCount(2, $page1);
        $this->assertCount(1, $page2);
        $this->assertSame('Alpha', $page1[0]['parish_name']);
        $this->assertSame('Charlie', $page1[1]['parish_name']);
        $this->assertSame('Delta', $page2[0]['parish_name']);
        $this->assertSame(3, $service->countParishSummaryRows(1));
    }

    public function testSundayTrendAndActivityFeedExcludeDeletedParishes(): void
    {
        $parishes = $this->seedDioceseWithParishes();
        $this->seedMembersAndServices($parishes);

        // Bravo's Sunday services (ids 4, 5) get a distinct attendance so its
        // presence would visibly shift the trend average if still counted.
        $this->db->table('service_metric')->whereIn('service_id', [4, 5])->update(['reported_present_count' => 100]);
        // The parish activity rows require created_at; a Bravo donation tests
        // the feed's parish join rather than its own parish row.
        $this->db->table('parish')->update(['created_at' => '2026-10-01 00:00:00']);
        $this->db->table('donation')->insert([
            'parish_id'  => 2,
            'amount'     => '50',
            'date'       => '2026-10-10',
            'created_at' => '2026-10-10 00:00:00',
        ]);
        $this->db->table('parish')->where('id', 2)->update(['deleted_at' => '2026-10-05 00:00:00']);

        $service = new DioceseDashboardDataService();

        $trend = $service->getDiocesanSundayAttendanceAverageTrend(1, 'custom_range', self::PERIOD_START, self::PERIOD_END, 'week');
        $this->assertTrue($trend['has_data']);
        $this->assertSame(30.0, $trend['sunday_overall_average']);

        $activity = $service->getRecentNetworkActivity(1, 50);
        $this->assertCount(3, $activity);
        $this->assertSame(3, $service->countRecentNetworkActivity(1));
    }

    public function testGetParishSummaryRowsWithParishFilter(): void
    {
        $parishes = $this->seedDioceseWithParishes();
        $this->seedMembersAndServices($parishes);

        $service = new DioceseDashboardDataService();
        $rows = $service->getParishSummaryRows('custom', self::PERIOD_START, self::PERIOD_END, 1, 3, 10, 0);

        $this->assertCount(1, $rows);
        $this->assertSame('Charlie', $rows[0]['parish_name']);
    }

    public function testGetDioceseSummaryTotalsMatchesFullPageSum(): void
    {
        $parishes = $this->seedDioceseWithParishes();
        $this->seedMembersAndServices($parishes);

        $service = new DioceseDashboardDataService();
        $allRows = $service->getParishSummaryRows('custom', self::PERIOD_START, self::PERIOD_END, 1);
        $totals = $service->getDioceseSummaryTotals('custom', self::PERIOD_START, self::PERIOD_END, 1);

        $expectedMembers = array_sum(array_column($allRows, 'total_members'));
        $expectedAttendance = array_sum(array_column($allRows, 'total_attendance'));
        $expectedServices = array_sum(array_column($allRows, 'total_services'));

        $this->assertSame(4, $totals['total_parishes']);
        $this->assertSame($expectedMembers, $totals['total_members']);
        $this->assertSame($expectedAttendance, $totals['total_attendance']);
        $this->assertSame($expectedServices, $totals['total_services']);
        $this->assertSame(30.0, $totals['asa']); // 4 parishes * 60 Sunday / 8 Sunday services = 30
        $this->assertSame(10.0, $totals['awa']); // 4 * 10 / 4 = 10
    }

    public function testGetDioceseSummaryTotalsWithParishFilter(): void
    {
        $parishes = $this->seedDioceseWithParishes();
        $this->seedMembersAndServices($parishes);

        $service = new DioceseDashboardDataService();
        $totals = $service->getDioceseSummaryTotals('custom', self::PERIOD_START, self::PERIOD_END, 1, 2);

        $this->assertSame(1, $totals['total_parishes']);
        $this->assertSame(2, $totals['total_members']);
        $this->assertSame(70, $totals['total_attendance']);
    }

    public function testEmptyDioceseReturnsZeroedRowsAndTotals(): void
    {
        $this->seedDioceseWithParishes();

        $service = new DioceseDashboardDataService();
        $rows = $service->getParishSummaryRows('custom', self::PERIOD_START, self::PERIOD_END, 1, null, 2, 0);
        $totals = $service->getDioceseSummaryTotals('custom', self::PERIOD_START, self::PERIOD_END, 1);

        $this->assertCount(2, $rows);
        $this->assertSame(0, $rows[0]['total_members']);
        $this->assertSame(0, $rows[0]['total_attendance']);
        $this->assertSame(4, $totals['total_parishes']);
        $this->assertSame(0, $totals['total_members']);
        $this->assertSame(0, $totals['total_attendance']);
    }
}
