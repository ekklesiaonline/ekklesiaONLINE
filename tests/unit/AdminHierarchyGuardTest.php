<?php

use App\Controllers\AdminHierarchyGuard;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class AdminHierarchyGuardTest extends CIUnitTestCase
{
    /**
     * Builds a guard harness with a fixed actor ID and canned role priorities.
     *
     * @param array<int, int> $priorities user_id => priority
     */
    private function makeGuard(int $actorId, array $priorities): object
    {
        return new class ($actorId, $priorities) {
            use AdminHierarchyGuard {
                guardTargetUserMutation as public;
                getUserRolePriority as public;
            }

            public function __construct(private int $actorId, private array $priorities)
            {
            }

            protected function getActorUserId(): int
            {
                return $this->actorId;
            }

            protected function getUserRolePriorities(array $userIds): array
            {
                $out = [];
                foreach ($userIds as $id) {
                    $out[(int) $id] = $this->priorities[(int) $id] ?? 0;
                }
                return $out;
            }

            protected function audit(): object
            {
                return new class {
                    public function log(...$args): void
                    {
                    }
                };
            }
        };
    }

    public function testParishAdminCannotMutateSystemAdmin(): void
    {
        // Actor 41 = parishadmin (1), target 4 = systemadmin (4)
        $guard = $this->makeGuard(41, [41 => 1, 4 => 4]);

        $this->assertSame('HIERARCHY', $guard->guardTargetUserMutation(4));
        $this->assertSame('HIERARCHY', $guard->guardTargetUserMutation(4, true));
    }

    public function testParishAdminCannotMutateDioceseOrProvinceAdmin(): void
    {
        $guard = $this->makeGuard(41, [41 => 1, 30 => 2, 32 => 3]);

        $this->assertSame('HIERARCHY', $guard->guardTargetUserMutation(30), 'dioceseadmin outranks parishadmin');
        $this->assertSame('HIERARCHY', $guard->guardTargetUserMutation(32), 'provinceadmin outranks parishadmin');
    }

    public function testSystemAdminCanMutateLowerTiers(): void
    {
        $guard = $this->makeGuard(4, [4 => 4, 41 => 1, 30 => 2, 100 => 0]);

        $this->assertNull($guard->guardTargetUserMutation(41));
        $this->assertNull($guard->guardTargetUserMutation(30));
        $this->assertNull($guard->guardTargetUserMutation(100));
    }

    public function testEqualPriorityIsAllowed(): void
    {
        $guard = $this->makeGuard(41, [41 => 1, 42 => 1]);

        $this->assertNull($guard->guardTargetUserMutation(42), 'peers at the same tier are not blocked by hierarchy');
    }

    public function testPlainMemberTargetIsAllowed(): void
    {
        $guard = $this->makeGuard(41, [41 => 1, 200 => 0]);

        $this->assertNull($guard->guardTargetUserMutation(200));
    }

    public function testSelfMutationBlockedOnlyWhenForbidSelf(): void
    {
        $guard = $this->makeGuard(41, [41 => 1]);

        $this->assertSame('SELF', $guard->guardTargetUserMutation(41, true), 'self-delete must be blocked');
        $this->assertNull($guard->guardTargetUserMutation(41, false), 'self-edit remains allowed');
    }

    public function testSelfBlockTakesPrecedenceEvenForSystemAdmin(): void
    {
        $guard = $this->makeGuard(4, [4 => 4]);

        $this->assertSame('SELF', $guard->guardTargetUserMutation(4, true));
    }

    public function testInvalidTargetIdIsIgnored(): void
    {
        $guard = $this->makeGuard(41, [41 => 1]);

        $this->assertNull($guard->guardTargetUserMutation(0));
        $this->assertNull($guard->guardTargetUserMutation(-5, true));
    }
}
