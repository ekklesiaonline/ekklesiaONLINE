<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regression guards for M5 (registry batching) and M6 (performance).
 *
 * M5: ParishPeopleRegistryService::syncParish() uses batch upsert
 * (updateBatch/insertBatch) instead of per-row select/update/insert.
 * M6: ParishDashboardDataService::countBirthdaysThisWeek() uses SQL
 * date logic instead of loading all rows and counting in PHP.
 * VestryVoteModel::tallyVotes() uses SQL SUM instead of load-all-and-count.
 *
 * @internal
 */
final class RegistryBatchingAndPerformanceTest extends CIUnitTestCase
{
    /**
     * M5: syncParish uses batch operations (updateBatch/insertBatch).
     */
    public function testSyncParishUsesBatchOperations(): void
    {
        $source = file_get_contents(APPPATH . 'Services/ParishPeopleRegistryService.php');

        $this->assertStringContainsString('updateBatch', $source, 'syncParish should use updateBatch for batch updates.');
        $this->assertStringContainsString('insertBatch', $source, 'syncParish should use insertBatch for batch inserts.');
    }

    /**
     * M5: syncParish fetches all existing snapshot rows in one query
     * instead of per-row selects.
     */
    public function testSyncParishFetchesExistingInOneQuery(): void
    {
        $source = file_get_contents(APPPATH . 'Services/ParishPeopleRegistryService.php');

        $this->assertStringContainsString(
            'existingMap',
            $source,
            'syncParish should build an existingMap from a single query.'
        );
        $this->assertStringContainsString(
            '$existingMap[$userId] ?? null',
            $source,
            'syncParish should look up existing rows from the map, not per-row queries.'
        );
    }

    /**
     * M5: syncParish no longer has per-row select queries inside the loop.
     */
    public function testSyncParishNoPerRowSelectInLoop(): void
    {
        $source = file_get_contents(APPPATH . 'Services/ParishPeopleRegistryService.php');

        // The old code had a per-row select inside the foreach loop.
        // The new code fetches all existing rows before the loop.
        $this->assertStringNotContainsString(
            "->where('user_id', \$payload['user_id'])\n                    ->where('deleted_at', null)\n                    ->get()",
            $source,
            'syncParish should not have per-row select queries inside the loop.'
        );
    }

    /**
     * M5: syncParish still uses transactions.
     */
    public function testSyncParishStillUsesTransactions(): void
    {
        $source = file_get_contents(APPPATH . 'Services/ParishPeopleRegistryService.php');

        $this->assertStringContainsString('transBegin', $source);
        $this->assertStringContainsString('transCommit', $source);
        $this->assertStringContainsString('transRollback', $source);
    }

    /**
     * M6: countBirthdaysThisWeek uses SQL date logic, not PHP-side counting.
     */
    public function testCountBirthdaysUsesSqlDateLogic(): void
    {
        $source = file_get_contents(APPPATH . 'Services/ParishDashboardDataService.php');

        // Should use driver-conditional date formatting
        $this->assertStringContainsString('DBDriver', $source, 'countBirthdaysThisWeek should detect DB driver.');
        $this->assertStringContainsString('DATE_FORMAT', $source, 'countBirthdaysThisWeek should use DATE_FORMAT for MySQL.');
        $this->assertStringContainsString('strftime', $source, 'countBirthdaysThisWeek should use strftime for SQLite.');
        // Should use countAllResults, not load-all-and-count
        $this->assertStringContainsString('countAllResults', $source, 'countBirthdaysThisWeek should use countAllResults.');
    }

    /**
     * M6: countBirthdaysThisWeek no longer loads all rows and counts in PHP.
     */
    public function testCountBirthdaysNoPhpSideLoop(): void
    {
        $source = file_get_contents(APPPATH . 'Services/ParishDashboardDataService.php');

        // The old code had a foreach loop with strtotime/date/m-d comparisons
        $this->assertStringNotContainsString(
            "strtotime(\$birthDateValue)",
            $source,
            'countBirthdaysThisWeek should not use PHP-side strtotime for counting.'
        );
    }

    /**
     * M6: VestryVoteModel::tallyVotes uses SQL SUM, not load-all-and-count.
     */
    public function testTallyVotesUsesSqlSum(): void
    {
        $source = file_get_contents(APPPATH . 'Models/VestryVoteModel.php');

        $this->assertStringContainsString('SUM(CASE', $source, 'tallyVotes should use SQL SUM(CASE...) for counting.');
        $this->assertStringContainsString('COUNT(*)', $source, 'tallyVotes should use COUNT(*) for total.');
    }

    /**
     * M6: VestryVoteModel::tallyVotes no longer loads all vote rows.
     */
    public function testTallyVotesNoLoadAllAndCount(): void
    {
        $source = file_get_contents(APPPATH . 'Models/VestryVoteModel.php');

        // The old code loaded all votes with findAll() and counted in PHP
        $this->assertStringNotContainsString(
            "where('motion_id', \$motionId)->findAll()",
            $source,
            'tallyVotes should not load all vote rows with findAll().'
        );
    }

    /**
     * M6: VestryVoteModel::tallyVotes returns the correct keys in its return type.
     * (Functional behavior is verified by VestryGovernanceDatabaseTest.)
     */
    public function testTallyVotesReturnTypeDocblock(): void
    {
        $source = file_get_contents(APPPATH . 'Models/VestryVoteModel.php');

        $this->assertStringContainsString(
            "@return array{yea: int, nay: int, abstain: int, total: int}",
            $source,
            'tallyVotes should document its return shape.'
        );
    }
}
