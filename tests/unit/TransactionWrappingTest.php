<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regression guard for H1: critical multi-table services must wrap their
 * write operations in database transactions so a failure mid-way rolls back
 * all changes and does not leave the database in an inconsistent state.
 *
 * @internal
 */
final class TransactionWrappingTest extends CIUnitTestCase
{
    /**
     * AdminCreationService::createAdmin must wrap its multi-table writes
     * (users, auth_identities, auth_groups_users, profile, registration_request)
     * in a transaction.
     */
    public function testAdminCreationServiceWrapsCreateAdminInTransaction(): void
    {
        $reflection = new ReflectionMethod(\App\Services\AdminCreationService::class, 'createAdmin');
        $source = $this->getMethodSource($reflection);

        $this->assertStringContainsString('transBegin', $source, 'AdminCreationService::createAdmin must call transBegin().');
        $this->assertStringContainsString('transCommit', $source, 'AdminCreationService::createAdmin must call transCommit().');
        $this->assertStringContainsString('transRollback', $source, 'AdminCreationService::createAdmin must call transRollback().');
    }

    /**
     * PersonProvisioningService::provision must wrap its multi-table writes
     * (users, person, parish_affiliation, user_person, profile) in a transaction.
     */
    public function testPersonProvisioningServiceWrapsProvisionInTransaction(): void
    {
        $reflection = new ReflectionMethod(\App\Services\PersonProvisioningService::class, 'provision');
        $source = $this->getMethodSource($reflection);

        $this->assertStringContainsString('transBegin', $source, 'PersonProvisioningService::provision must call transBegin().');
        $this->assertStringContainsString('transCommit', $source, 'PersonProvisioningService::provision must call transCommit().');
        $this->assertStringContainsString('transRollback', $source, 'PersonProvisioningService::provision must call transRollback().');
    }

    /**
     * GuestIntakeService::record must wrap its writes (provision + person_visit)
     * in a transaction.
     */
    public function testGuestIntakeServiceWrapsRecordInTransaction(): void
    {
        $reflection = new ReflectionMethod(\App\Services\GuestIntakeService::class, 'record');
        $source = $this->getMethodSource($reflection);

        $this->assertStringContainsString('transBegin', $source, 'GuestIntakeService::record must call transBegin().');
        $this->assertStringContainsString('transCommit', $source, 'GuestIntakeService::record must call transCommit().');
        $this->assertStringContainsString('transRollback', $source, 'GuestIntakeService::record must call transRollback().');
    }

    /**
     * SacramentFlagSyncService::applyForPerson must wrap its writes
     * (person_membership_details + profile) in a transaction.
     */
    public function testSacramentFlagSyncServiceWrapsApplyForPersonInTransaction(): void
    {
        $reflection = new ReflectionMethod(\App\Services\SacramentFlagSyncService::class, 'applyForPerson');
        $source = $this->getMethodSource($reflection);

        $this->assertStringContainsString('transBegin', $source, 'SacramentFlagSyncService::applyForPerson must call transBegin().');
        $this->assertStringContainsString('transCommit', $source, 'SacramentFlagSyncService::applyForPerson must call transCommit().');
        $this->assertStringContainsString('transRollback', $source, 'SacramentFlagSyncService::applyForPerson must call transRollback().');
    }

    /**
     * PersonCleanupService::cleanupForUser must wrap its multi-table deletes
     * (user_person + orphaned person rows) in a transaction.
     */
    public function testPersonCleanupServiceWrapsCleanupForUserInTransaction(): void
    {
        $reflection = new ReflectionMethod(\App\Services\PersonCleanupService::class, 'cleanupForUser');
        $source = $this->getMethodSource($reflection);

        $this->assertStringContainsString('transBegin', $source, 'PersonCleanupService::cleanupForUser must call transBegin().');
        $this->assertStringContainsString('transCommit', $source, 'PersonCleanupService::cleanupForUser must call transCommit().');
        $this->assertStringContainsString('transRollback', $source, 'PersonCleanupService::cleanupForUser must call transRollback().');
    }

    /**
     * DonationReceiptModel::issueForDonation must already have a transaction
     * (confirmed during investigation — this test guards against regression).
     */
    public function testDonationReceiptModelWrapsIssueForDonationInTransaction(): void
    {
        $reflection = new ReflectionMethod(\App\Models\DonationReceiptModel::class, 'issueForDonation');
        $source = $this->getMethodSource($reflection);

        $this->assertStringContainsString('transStart', $source, 'DonationReceiptModel::issueForDonation must call transStart().');
        $this->assertStringContainsString('transRollback', $source, 'DonationReceiptModel::issueForDonation must call transRollback().');
    }

    private function getMethodSource(ReflectionMethod $method): string
    {
        $file = $method->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();

        $lines = file($file);

        return implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));
    }
}
