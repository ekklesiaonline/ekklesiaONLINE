<?php

use App\Services\ClergyAuthorizationService;
use App\Services\ClergyReviewService;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * @internal
 */
final class SystemDiocesanOrdinariesFeatureTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    protected function setUp(): void
    {
        parent::setUp();

        helper(['auth', 'form', 'url']);
        Services::reset(true);
        $this->withRoutes([
            ['GET', 'system/diocesan-ordinaries', '\\App\\Controllers\\SystemDiocesanOrdinaries::index'],
            ['POST', 'system/diocesan-ordinaries/install/(:num)', '\\App\\Controllers\\SystemDiocesanOrdinaries::install/$1'],
            ['POST', 'system/diocesan-ordinaries/end/(:num)', '\\App\\Controllers\\SystemDiocesanOrdinaries::endOffice/$1'],
        ]);
    }

    protected function tearDown(): void
    {
        Services::reset(true);

        parent::tearDown();
    }

    public function testSystemAdminCanViewDiocesanOrdinaries(): void
    {
        [, $installedDioceseId] = $this->createDiocese('Installed Feature Ordinary Diocese');
        [, $vacantDioceseId] = $this->createDiocese('Vacant Feature Ordinary Diocese');
        $adminId = $this->createAccount('systemadmin', $installedDioceseId, 'System', 'Admin');
        $bishopId = $this->createAccount('dioceseadmin', $installedDioceseId, 'Installed', 'Bishop');
        $suspendedCandidateId = $this->createAccount('dioceseadmin', $vacantDioceseId, 'Suspended', 'Candidate');
        $this->injectOrdinaryAuth($adminId, true);
        $positionId = (new ClergyReviewService($this->db, new ClergyAuthorizationService($this->db)))
            ->installOrdinary($adminId, $installedDioceseId, $bishopId, null, date('Y-m-d'),
                'feature-test-rendered-ordinary');
        $now = date('Y-m-d H:i:s');
        $this->db->table('person')->insert([
            'first_name' => 'Suspended',
            'last_name' => 'Candidate',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $suspendedPersonId = (int) $this->db->insertID();
        $this->db->table('profile')->where('user_id', $suspendedCandidateId)
            ->update(['person_id' => $suspendedPersonId, 'updated_at' => $now]);
        $this->db->table('user_person')->insert([
            'user_id' => $suspendedCandidateId,
            'person_id' => $suspendedPersonId,
            'is_primary' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->db->table('clergy_canonical_status')->insert([
            'person_id' => $suspendedPersonId,
            'diocese_id' => $installedDioceseId,
            'status_code' => 'suspended',
            'effective_from' => date('Y-m-d', strtotime('-1 day')),
            'effective_to' => null,
            'evidence_reference' => 'feature-test-system-wide-suspension',
            'verified_by_user_id' => $adminId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $response = $this->get('system/diocesan-ordinaries');

        self::assertNotNull($positionId);
        $response->assertOK();
        $response->assertSee('Installed Feature Ordinary Diocese');
        $response->assertSee('Vacant Feature Ordinary Diocese');
        $response->assertDontSee('Suspended Candidate');

        $body = (string) $response->getBody();
        self::assertStringContainsString(
            'data-bs-target="#endOfficeModal' . $installedDioceseId . '"',
            $body
        );
        self::assertStringNotContainsString(
            'data-bs-target="#endOfficeModal' . $vacantDioceseId . '"',
            $body
        );
        self::assertStringContainsString('id="endOfficeModal' . $installedDioceseId . '"', $body);
        self::assertStringNotContainsString('id="endOfficeModal' . $vacantDioceseId . '"', $body);
    }

    public function testDioceseAdminCannotViewDiocesanOrdinaries(): void
    {
        [, $dioceseId] = $this->createDiocese('Restricted Ordinary Diocese');
        $adminId = $this->createAccount('dioceseadmin', $dioceseId, 'Diocese', 'Admin');
        $this->injectOrdinaryAuth($adminId, false);

        $response = $this->get('system/diocesan-ordinaries');

        self::assertNotSame(200, $response->getStatusCode());
    }

    public function testInstallWithEmptyEvidenceRedirectsWithErrorsAndCreatesNoPosition(): void
    {
        [, $dioceseId] = $this->createDiocese('Validation Ordinary Diocese');
        $adminId = $this->createAccount('systemadmin', $dioceseId, 'System', 'Admin');
        $bishopId = $this->createAccount('dioceseadmin', $dioceseId, 'Candidate', 'Bishop');
        $this->injectOrdinaryAuth($adminId, true);

        $response = $this->withHeaders($this->withCsrfHeaders([]))->withBodyFormat('form')
            ->post('system/diocesan-ordinaries/install/' . $dioceseId, [
                'user_id' => $bishopId,
                'office_started_on' => date('Y-m-d'),
                'ordained_on' => '',
                'evidence_reference' => '',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('errors');
        $response->assertSessionHas('open_modal', 'installOrdinaryModal' . $dioceseId);
        self::assertSame(0, $this->db->table('leadership_position')->where('user_id', $bishopId)->countAllResults());
    }

    public function testValidInstallRedirectsAndCreatesAnOrdinaryPosition(): void
    {
        [, $dioceseId] = $this->createDiocese('Install Ordinary Diocese');
        $adminId = $this->createAccount('systemadmin', $dioceseId, 'System', 'Admin');
        $bishopId = $this->createAccount('dioceseadmin', $dioceseId, 'Install', 'Bishop');
        $this->injectOrdinaryAuth($adminId, true);

        $response = $this->withHeaders($this->withCsrfHeaders([]))->withBodyFormat('form')
            ->post('system/diocesan-ordinaries/install/' . $dioceseId, [
                'user_id' => $bishopId,
                'office_started_on' => date('Y-m-d'),
                'ordained_on' => '',
                'evidence_reference' => 'feature-test-register',
            ]);

        $response->assertRedirectTo('/system/diocesan-ordinaries');
        self::assertSame(1, $this->db->table('leadership_position')->where('user_id', $bishopId)
            ->where('diocese_id', $dioceseId)->where('role_code', 'diocesan_bishop')->countAllResults());
    }

    public function testValidEndOfficeRedirectsAndRevokesThePosition(): void
    {
        [, $dioceseId] = $this->createDiocese('End Ordinary Diocese');
        $adminId = $this->createAccount('systemadmin', $dioceseId, 'System', 'Admin');
        $bishopId = $this->createAccount('dioceseadmin', $dioceseId, 'Former', 'Bishop');
        $this->injectOrdinaryAuth($adminId, true);
        $positionId = (new ClergyReviewService($this->db, new ClergyAuthorizationService($this->db)))
            ->installOrdinary($adminId, $dioceseId, $bishopId, null, date('Y-m-d', strtotime('-1 day')),
                'feature-test-end-register');

        self::assertNotNull($positionId);
        $response = $this->withHeaders($this->withCsrfHeaders([]))->withBodyFormat('form')
            ->post('system/diocesan-ordinaries/end/' . $positionId, [
                'ended_on' => date('Y-m-d'),
                'reason' => 'Feature test office ending',
            ]);

        $response->assertRedirectTo('/system/diocesan-ordinaries');
        $position = $this->db->table('leadership_position')->where('id', $positionId)->get()->getRowArray();
        self::assertSame(0, (int) $position['active']);
        self::assertNotEmpty($position['revoked_at']);
        self::assertSame(date('Y-m-d'), $position['ended_at']);

        $audit = $this->db->table('audit_log')->where('action', 'system.diocesan_ordinary.ended')
            ->where('entity_id', $dioceseId)->get()->getRowArray();
        self::assertNotNull($audit);
        $newValues = json_decode((string) $audit['new_values'], true);
        self::assertSame([
            'user_id' => $bishopId,
            'position_id' => $positionId,
            'ended_on' => date('Y-m-d'),
            'delegations_revoked' => 0,
        ], $newValues);
        self::assertStringNotContainsString('Feature test office ending', (string) $audit['new_values']);
    }

    /**
     * @return array{0:int,1:int}
     */
    private function createDiocese(string $name): array
    {
        $this->db->table('province')->insert([
            'name' => $name . ' Province',
            'province_number' => '1',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $provinceId = (int) $this->db->insertID();

        $this->db->table('diocese')->insert([
            'province_id' => $provinceId,
            'name' => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return [$provinceId, (int) $this->db->insertID()];
    }

    private function createAccount(string $group, int $dioceseId, string $firstName, string $lastName): int
    {
        $username = strtolower($group . '.' . $firstName . '.' . $lastName . '.' . uniqid());
        $now = date('Y-m-d H:i:s');
        $this->db->table('users')->insert([
            'username' => $username,
            'active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $userId = (int) $this->db->insertID();
        $this->db->table('auth_identities')->insert([
            'user_id' => $userId,
            'type' => 'email_password',
            'name' => $username . '@example.org',
            'secret' => 'feature-test-password-hash',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->db->table('auth_groups_users')->insert([
            'user_id' => $userId,
            'group' => $group,
            'created_at' => $now,
        ]);

        $profileColumns = array_map('strtolower', $this->db->getFieldNames($this->resolveTableName('profile')));
        $profile = [
            'user_id' => $userId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'diocese_id' => $dioceseId,
            'portal_access' => 'enabled',
            'locale' => 'en',
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $this->db->table('profile')->insert(array_intersect_key($profile, array_flip($profileColumns)));

        return $userId;
    }

    private function withCsrfHeaders(array $headers): array
    {
        $headers[csrf_header()] = csrf_hash();

        return $headers;
    }

    private function resolveTableName(string $table): string
    {
        $prefixed = $this->db->getPrefix() . $table;

        return $this->db->tableExists($prefixed) ? $prefixed : $table;
    }

    private function injectOrdinaryAuth(int $userId, bool $systemAdmin): void
    {
        $fakeUser = new class($userId, $systemAdmin) extends User {
            public function __construct(private int $testUserId, private bool $systemAdmin)
            {
                parent::__construct([
                    'id' => $testUserId,
                    'username' => 'ordinary.feature.user.' . $testUserId,
                    'email' => 'ordinary.feature.' . $testUserId . '@example.org',
                ]);
            }

            public function can(string ...$permissions): bool
            {
                if (! $this->systemAdmin) {
                    return false;
                }

                foreach ($permissions as $permission) {
                    if ($permission !== 'system.admin') {
                        return false;
                    }
                }

                return true;
            }

            public function inGroup(string ...$groups): bool
            {
                return $this->systemAdmin
                    ? in_array('systemadmin', $groups, true)
                    : in_array('dioceseadmin', $groups, true);
            }
        };

        $auth = new class(config('Auth'), $fakeUser, $userId) extends Auth {
            public function __construct($config, private User $fakeUser, private int $testUserId)
            {
                parent::__construct($config);
            }

            public function setAuthenticator(?string $alias = null): self
            {
                return $this;
            }

            public function loggedIn(): bool
            {
                return true;
            }

            public function id(): ?int
            {
                return $this->testUserId;
            }

            public function user(): ?User
            {
                return $this->fakeUser;
            }
        };

        Services::injectMock('auth', $auth);
    }
}
