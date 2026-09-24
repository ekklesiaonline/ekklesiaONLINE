<?php

use App\Services\VisitorDirectoryService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Database\CanonicalTestSchema;

/**
 * Phase 2: visitor directory lifecycle scope.
 *
 * The directory covers the pre-member pipeline — `visitor` and
 * `regular_attender` affiliations — plus rows converted from visitors.
 * The default list excludes converted rows; 'converted'/'all' are
 * explicit filter values. Suggestion thresholds are advisory only —
 * nothing auto-transitions status.
 *
 * @internal
 */
final class VisitorDirectoryLifecycleTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    private const PARISH = 301;

    private VisitorDirectoryService $directory;

    protected function setUp(): void
    {
        parent::setUp();

        CanonicalTestSchema::ensure($this->db);
        foreach (['parish_affiliation', 'person', 'person_visit', 'parish'] as $table) {
            CanonicalTestSchema::reset($this->db, $table);
        }

        $this->directory = new VisitorDirectoryService();
        $this->seedParish(self::PARISH);
        session()->set('selected_parish_id', self::PARISH);
    }

    public function testDefaultListIncludesVisitorAndRegularAttender(): void
    {
        $visitor  = $this->insertPipelinePerson('visitor');
        $attender = $this->insertPipelinePerson('regular_attender');
        $member   = $this->insertPipelinePerson('member');

        $result = $this->directory->paginateForParish(self::PARISH, [], 20, 1);
        $ids    = array_map('intval', array_column($result['rows'], 'person_id'));

        $this->assertContains($visitor, $ids);
        $this->assertContains($attender, $ids);
        $this->assertNotContains($member, $ids);
    }

    public function testDefaultListExcludesConvertedRows(): void
    {
        $plain = $this->insertPipelinePerson('visitor');
        $this->insertPipelinePerson('member', true);

        $result = $this->directory->paginateForParish(self::PARISH, [], 20, 1);
        $ids    = array_map('intval', array_column($result['rows'], 'person_id'));

        $this->assertSame([$plain], $ids);
    }

    public function testConvertedFilterListsOnlyConvertedRows(): void
    {
        $this->insertPipelinePerson('visitor');
        $convertedId = $this->insertPipelinePerson('member', true);

        $result = $this->directory->paginateForParish(self::PARISH, ['lifecycle' => 'converted'], 20, 1);
        $ids    = array_map('intval', array_column($result['rows'], 'person_id'));

        $this->assertSame([$convertedId], $ids);
    }

    public function testAllFilterIncludesConverted(): void
    {
        $visitorId = $this->insertPipelinePerson('visitor');
        $convertId = $this->insertPipelinePerson('member', true);

        $result = $this->directory->paginateForParish(self::PARISH, ['lifecycle' => 'all'], 20, 1);
        $ids    = array_map('intval', array_column($result['rows'], 'person_id'));

        sort($ids);
        $this->assertSame([$visitorId, $convertId], $ids);
    }

    public function testLifecycleStatusFilter(): void
    {
        $visitorId  = $this->insertPipelinePerson('visitor');
        $attenderId = $this->insertPipelinePerson('regular_attender');

        $result = $this->directory->paginateForParish(self::PARISH, ['lifecycle' => 'regular_attender'], 20, 1);
        $ids    = array_map('intval', array_column($result['rows'], 'person_id'));

        $this->assertSame([$attenderId], $ids);
        $this->assertNotContains($visitorId, $ids);
    }

    public function testConvertedCountRespectsWindow(): void
    {
        $this->insertPipelinePerson('member', true, self::PARISH, date('Y-m-d H:i:s'));          // now
        $this->insertPipelinePerson('member', true, self::PARISH, date('Y-m-d H:i:s', strtotime('-60 days')));

        $this->assertSame(2, $this->directory->convertedCountForParish(self::PARISH));
        $this->assertSame(1, $this->directory->convertedCountForParish(self::PARISH, 30));
        $this->assertSame(2, $this->directory->convertedCountForParish(self::PARISH, 90));
    }

    public function testConvertedWindowFilterOnConvertedList(): void
    {
        $recent = $this->insertPipelinePerson('member', true, self::PARISH, date('Y-m-d H:i:s'));
        $old    = $this->insertPipelinePerson('member', true, self::PARISH, date('Y-m-d H:i:s', strtotime('-60 days')));

        $all = $this->directory->paginateForParish(self::PARISH, ['lifecycle' => 'converted'], 20, 1);
        $this->assertSame(2, count($all['rows']));

        $windowed = $this->directory->paginateForParish(
            self::PARISH,
            ['lifecycle' => 'converted', 'converted_window' => '30'],
            20,
            1
        );
        $ids = array_map('intval', array_column($windowed['rows'], 'person_id'));
        $this->assertSame([$recent], $ids);
        $this->assertNotContains($old, $ids);
    }

    public function testConvertedWindowIgnoredOutsideConvertedLifecycle(): void
    {
        // The window applies only to the converted list — a stray
        // converted_window param must not leak into the pipeline scope.
        $visitor = $this->insertPipelinePerson('visitor');

        $result = $this->directory->paginateForParish(
            self::PARISH,
            ['converted_window' => '30'],
            20,
            1
        );
        $ids = array_map('intval', array_column($result['rows'], 'person_id'));
        $this->assertSame([$visitor], $ids);
    }

    public function testVisitCountIsPopulated(): void
    {
        $personId = $this->insertPipelinePerson('visitor');
        $this->insertVisit($personId, '2026-01-11');

        $row = $this->directory->findByPerson($personId, self::PARISH);

        $this->assertNotNull($row);
        $this->assertSame(2, (int) $row['visit_count']);
    }

    public function testAttenderSuggestionThresholdDefaultsToTwo(): void
    {
        $this->assertSame(2, $this->directory->attenderSuggestionThreshold(self::PARISH));
    }

    public function testSuggestionIsAdvisoryOnly(): void
    {
        // A visitor above the threshold stays a visitor — nothing in the
        // directory auto-transitions status.
        $personId = $this->insertPipelinePerson('visitor');
        $this->insertVisit($personId, '2026-01-11');

        $row = $this->directory->findByPerson($personId, self::PARISH);

        $this->assertSame('visitor', $row['membership_status']);
        $this->assertGreaterThanOrEqual(
            $this->directory->attenderSuggestionThreshold(self::PARISH),
            (int) $row['visit_count']
        );
    }

    // ------------------------------------------------------------------
    // Fixtures
    // ------------------------------------------------------------------

    private function seedParish(int $parishId): void
    {
        $exists = $this->db->table('parish')->where('id', $parishId)->countAllResults();
        if ($exists > 0) {
            return;
        }

        $this->db->table('parish')->insert([
            'id'         => $parishId,
            'name'       => 'Test Parish ' . $parishId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function insertPipelinePerson(string $status, bool $converted = false, int $parishId = self::PARISH, ?string $convertedAt = null): int
    {
        $this->db->table('person')->insert([
            'first_name' => 'Test',
            'last_name'  => 'Person',
            'is_active'  => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $personId = (int) $this->db->insertID();

        $this->db->table('parish_affiliation')->insert([
            'person_id'              => $personId,
            'parish_id'              => $parishId,
            'membership_status'      => $status,
            'converted_to_member_at' => $converted ? ($convertedAt ?? date('Y-m-d H:i:s')) : null,
            'start_date'             => date('Y-m-d'),
            'created_at'             => date('Y-m-d H:i:s'),
            'updated_at'             => date('Y-m-d H:i:s'),
        ]);

        $this->insertVisit($personId, '2026-01-04', $parishId);

        return $personId;
    }

    private function insertVisit(int $personId, string $date, int $parishId = self::PARISH): void
    {
        $this->db->table('person_visit')->insert([
            'person_id'  => $personId,
            'parish_id'  => $parishId,
            'visit_date' => $date,
            'visit_type' => 'first',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
