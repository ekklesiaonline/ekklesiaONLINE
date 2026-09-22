<?php

namespace Tests;

use App\Services\MembershipStatusService;
use CodeIgniter\Test\CIUnitTestCase;

class MembershipStatusServiceTest extends CIUnitTestCase
{
    public function testNormalizeAffiliationStatusCanonical(): void
    {
        $this->assertSame('visitor', MembershipStatusService::normalizeAffiliationStatus('visitor'));
        $this->assertSame('regular_attender', MembershipStatusService::normalizeAffiliationStatus('regular_attender'));
        $this->assertSame('member', MembershipStatusService::normalizeAffiliationStatus('member'));
        $this->assertSame('inactive', MembershipStatusService::normalizeAffiliationStatus('inactive'));
        $this->assertSame('transferred', MembershipStatusService::normalizeAffiliationStatus('transferred'));
        $this->assertSame('deceased', MembershipStatusService::normalizeAffiliationStatus('deceased'));
    }

    public function testNormalizeAffiliationStatusLegacyAliases(): void
    {
        $this->assertSame('visitor', MembershipStatusService::normalizeAffiliationStatus('guest'));
        $this->assertSame('regular_attender', MembershipStatusService::normalizeAffiliationStatus('attendee'));
        $this->assertSame('member', MembershipStatusService::normalizeAffiliationStatus('baptized_member'));
        $this->assertSame('member', MembershipStatusService::normalizeAffiliationStatus('communicant'));
        $this->assertSame('member', MembershipStatusService::normalizeAffiliationStatus('leader'));
        // Legacy profile-era aliases retained for import/back-compat paths.
        $this->assertSame('visitor', MembershipStatusService::normalizeAffiliationStatus('pending'));
        $this->assertSame('member', MembershipStatusService::normalizeAffiliationStatus('active'));
        $this->assertSame('inactive', MembershipStatusService::normalizeAffiliationStatus('non_active'));
    }

    public function testIsMember(): void
    {
        $this->assertFalse(MembershipStatusService::isMember('visitor'));
        $this->assertFalse(MembershipStatusService::isMember('regular_attender'));
        $this->assertTrue(MembershipStatusService::isMember('member'));
        $this->assertFalse(MembershipStatusService::isMember('inactive'));
        $this->assertFalse(MembershipStatusService::isMember('transferred'));
        $this->assertFalse(MembershipStatusService::isMember('deceased'));
    }

    public function testCanBeGoodStanding(): void
    {
        $this->assertFalse(MembershipStatusService::canBeGoodStanding('visitor'));
        $this->assertTrue(MembershipStatusService::canBeGoodStanding('member'));
        $this->assertFalse(MembershipStatusService::canBeGoodStanding('inactive'));
    }

    public function testBadgeColors(): void
    {
        $this->assertSame('success', MembershipStatusService::badgeColor('member'));
        $this->assertSame('primary', MembershipStatusService::badgeColor('regular_attender'));
        $this->assertSame('info', MembershipStatusService::badgeColor('visitor'));
        $this->assertSame('secondary', MembershipStatusService::badgeColor('inactive'));
        $this->assertSame('secondary', MembershipStatusService::badgeColor('transferred'));
        $this->assertSame('dark', MembershipStatusService::badgeColor('deceased'));
    }

    public function testOrderedPathway(): void
    {
        $pathway = MembershipStatusService::orderedPathway();
        $this->assertSame('visitor', $pathway[0]);
        $this->assertSame('regular_attender', $pathway[1]);
        $this->assertSame('member', $pathway[2]);
        $this->assertSame('inactive', $pathway[3]);
        $this->assertSame('transferred', $pathway[4]);
        $this->assertSame('deceased', $pathway[5]);
    }

    public function testLabelUsesLanguageFile(): void
    {
        $this->assertSame('Member', MembershipStatusService::label('member'));
        $this->assertSame('Visitor', MembershipStatusService::label('visitor'));
    }
}
