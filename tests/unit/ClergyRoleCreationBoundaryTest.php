<?php

use Clergy\Controllers\Members;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Clergy is a diocese-domain entity: only diocesan admins may create clergy
 * accounts, so 'clergy' only appears in the diocesanadmin creatable list.
 * A tampered role=clergy post from a parish admin fails store()'s in_list.
 *
 * @internal
 */
final class ClergyRoleCreationBoundaryTest extends CIUnitTestCase
{
    private function rolesFor(array $groups): array
    {
        $user = new class($groups) {
            public $id = 1;

            public function __construct(private array $groups) {}

            public function inGroup(string ...$groups): bool
            {
                foreach ($groups as $group) {
                    if (in_array($group, $this->groups, true)) {
                        return true;
                    }
                }

                return false;
            }
        };

        $controller = new class($user) extends Members {
            public function __construct($user)
            {
                $this->user = $user;
            }

            public function rolesFor(): array
            {
                return $this->getCreatableRoles();
            }
        };

        return $controller->rolesFor();
    }

    public function testParishAdminCannotCreateClergyAccounts(): void
    {
        $roles = $this->rolesFor(['parishadmin']);

        $this->assertNotContains('clergy', $roles);
        $this->assertContains('member', $roles);
    }

    public function testProvinceAdminCreatesNoAccounts(): void
    {
        $this->assertNotContains('clergy', $this->rolesFor(['provinceadmin']));
    }

    public function testDiocesanAdminKeepsClergyInCreatableRoles(): void
    {
        $roles = $this->rolesFor(['dioceseadmin']);

        $this->assertContains('clergy', $roles);
    }
}
