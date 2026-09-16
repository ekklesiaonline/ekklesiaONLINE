<?php

namespace Tests;

use App\Services\MembershipStatusService;
use CodeIgniter\Test\CIUnitTestCase;

class MembershipStatusServiceTest extends CIUnitTestCase
{
    public function testProfileToAffiliationMapping(): void
    {
        $this->assertSame('visitor', MembershipStatusService::profileToAffiliation('pending'));
        $this->assertSame('visitor', MembershipStatusService::profileToAffiliation('visitor'));
        $this->assertSame('regular_attender', MembershipStatusService::profileToAffiliation('regular_attender'));
        $this->assertSame('member', MembershipStatusService::profileToAffiliation('active'));
        $this->assertSame('member', MembershipStatusService::profileToAffiliation('member'));
        $this->assertSame('inactive', MembershipStatusService::profileToAffiliation('inactive'));
        $this->assertSame('inactive', MembershipStatusService::profileToAffiliation('non_active'));
        $this->assertSame('visitor', MembershipStatusService::profileToAffiliation('guest'));
        $this->assertSame('regular_attender', MembershipStatusService::profileToAffiliation('attendee'));
    }

    public function testAffiliationToProfileMapping(): void
    {
        $this->assertSame('visitor', MembershipStatusService::affiliationToProfile('visitor'));
        $this->assertSame('regular_attender', MembershipStatusService::affiliationToProfile('regular_attender'));
        $this->assertSame('member', MembershipStatusService::affiliationToProfile('member'));
        $this->assertSame('inactive', MembershipStatusService::affiliationToProfile('inactive'));
        $this->assertSame('inactive', MembershipStatusService::affiliationToProfile('transferred'));
        $this->assertSame('inactive', MembershipStatusService::affiliationToProfile('deceased'));
        $this->assertSame('visitor', MembershipStatusService::affiliationToProfile('guest'));
        $this->assertSame('regular_attender', MembershipStatusService::affiliationToProfile('attendee'));
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
