<?php

use App\Controllers\BaseController;
use CodeIgniter\Test\CIUnitTestCase;

class ProfileNavigationContextTestController extends BaseController
{
    /**
     * @param array<string,mixed>|null $profile
     * @param array<string,mixed>|null $registrationRequest
     * @return array{profile_pending: bool, profile_nav_url: string}
     */
    public function resolveContextForTest(?array $profile, ?array $registrationRequest): array
    {
        return $this->resolveProfileNavigationContext($profile, $registrationRequest);
    }
}

/**
 * @internal
 */
final class ProfileNavigationContextTest extends CIUnitTestCase
{
    public static function contextProvider(): array
    {
        return [
            'pending registration always routes to completion' => [
                ['id' => 10, 'user_id' => 5],
                ['status' => 'pending_profile'],
                true,
                'profile/complete',
            ],
            'missing profile routes to completion' => [
                null,
                ['status' => 'approved'],
                true,
                'profile/complete',
            ],
            'existing profile with approved status routes to profile view' => [
                ['id' => 22, 'user_id' => 5],
                ['status' => 'approved'],
                false,
                'profile',
            ],
            'existing profile without registration record stays on profile view' => [
                ['id' => 30, 'user_id' => 7],
                null,
                false,
                'profile',
            ],
        ];
    }

    /**
     * @dataProvider contextProvider
     * @param array<string,mixed>|null $profile
     * @param array<string,mixed>|null $registrationRequest
     */
    public function testProfileNavigationContext(?array $profile, ?array $registrationRequest, bool $expectedPending, string $expectedUrl): void
    {
        $controller = new ProfileNavigationContextTestController();
        $result = $controller->resolveContextForTest($profile, $registrationRequest);

        $this->assertSame($expectedPending, $result['profile_pending']);
        $this->assertSame($expectedUrl, $result['profile_nav_url']);
    }
}
