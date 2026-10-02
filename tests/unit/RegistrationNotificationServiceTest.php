<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\EmailScopeService;
use App\Services\RegistrationNotificationService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\AuthGroups;
use Tests\Support\Database\CanonicalTestSchema;

/**
 * @internal
 */
final class RegistrationNotificationServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        CanonicalTestSchema::ensure($this->db);
        foreach ([
            'province',
            'diocese',
            'parish',
            'users',
            'auth_identities',
            'auth_groups_users',
            'profile',
            'parish_role_audit',
        ] as $table) {
            CanonicalTestSchema::reset($this->db, $table);
        }
    }

    public function testParishAdminRecipientsResolvesDioceseAndProvinceAdmins(): void
    {
        $this->db->table('province')->insert([
            'id'              => 1,
            'name'            => 'Province 1',
            'province_number' => 1,
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        $this->db->table('diocese')->insert([
            'id'          => 10,
            'name'        => 'Diocese 10',
            'province_id' => 1,
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        $this->db->table('parish')->insert([
            'id'          => 100,
            'name'        => 'Parish 100',
            'diocese_id'  => 10,
            'parish_ueid' => 'UEID-100',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        // 1. Parish Admin
        $this->db->table('users')->insert(['username' => 'parish_adm', 'active' => 1, 'created_at' => date('Y-m-d H:i:s')]);
        $parishAdmId = (int) $this->db->insertID();
        $this->db->table('auth_identities')->insert([
            'user_id'    => $parishAdmId,
            'type'       => 'email_password',
            'secret'     => 'parish.admin@example.org',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('auth_groups_users')->insert([
            'user_id'    => $parishAdmId,
            'group'      => AuthGroups::PARISH_ADMIN,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('profile')->insert([
            'user_id'    => $parishAdmId,
            'parish_id'  => 100,
            'first_name' => 'Parish',
            'last_name'  => 'Admin',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // 2. Diocese Admin (AuthGroups::DIOCESE_ADMIN = 'dioceseadmin')
        $this->db->table('users')->insert(['username' => 'diocese_adm', 'active' => 1, 'created_at' => date('Y-m-d H:i:s')]);
        $dioceseAdmId = (int) $this->db->insertID();
        $this->db->table('auth_identities')->insert([
            'user_id'    => $dioceseAdmId,
            'type'       => 'email_password',
            'secret'     => 'diocese.admin@example.org',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('auth_groups_users')->insert([
            'user_id'    => $dioceseAdmId,
            'group'      => AuthGroups::DIOCESE_ADMIN,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('profile')->insert([
            'user_id'    => $dioceseAdmId,
            'diocese_id' => 10,
            'first_name' => 'Diocese',
            'last_name'  => 'Admin',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // 3. Province Admin
        $this->db->table('users')->insert(['username' => 'province_adm', 'active' => 1, 'created_at' => date('Y-m-d H:i:s')]);
        $provinceAdmId = (int) $this->db->insertID();
        $this->db->table('auth_identities')->insert([
            'user_id'    => $provinceAdmId,
            'type'       => 'email_password',
            'secret'     => 'province.admin@example.org',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('auth_groups_users')->insert([
            'user_id'    => $provinceAdmId,
            'group'      => AuthGroups::PROVINCE_ADMIN,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('profile')->insert([
            'user_id'     => $provinceAdmId,
            'province_id' => 1,
            'first_name'  => 'Province',
            'last_name'   => 'Admin',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        $service = new RegistrationNotificationService();
        $profileColumns = ['diocese_id', 'province_id', 'parish_id'];
        $recipients = $service->parishAdminRecipients(100, 'profile', $profileColumns);

        $this->assertContains('parish.admin@example.org', $recipients);
        $this->assertContains('diocese.admin@example.org', $recipients);
        $this->assertContains('province.admin@example.org', $recipients);
    }

    public function testNotifyAdminsPendingReviewPreservesFromHeaderPerRecipient(): void
    {
        $capturedSenders = [];

        $emailScopeMock = $this->createMock(EmailScopeService::class);
        $emailScopeMock->method('mailer')->willReturnCallback(function (array $scope) use (&$capturedSenders) {
            $email = new class($capturedSenders) extends \CodeIgniter\Email\Email {
                private array $recorder;

                public function __construct(array &$recorder)
                {
                    parent::__construct(new \Config\Email());
                    $this->recorder = &$recorder;
                    $this->setFrom('scoped-parish@example.org', 'Scoped Parish');
                }

                public function send($autoClear = true): bool
                {
                    $ref = new \ReflectionClass(\CodeIgniter\Email\Email::class);
                    $headersProp = $ref->getProperty('headers');
                    $headersProp->setAccessible(true);
                    $headers = $headersProp->getValue($this);

                    $this->recorder[] = [
                        'to'   => $this->recipients ?? [],
                        'from' => $headers['From'] ?? null,
                    ];

                    return true;
                }
            };

            return $email;
        });

        $service = $this->getMockBuilder(RegistrationNotificationService::class)
            ->setConstructorArgs([$this->db, null, null, $emailScopeMock])
            ->onlyMethods(['parishAdminRecipients'])
            ->getMock();

        $service->method('parishAdminRecipients')->willReturn(['admin1@example.org', 'admin2@example.org']);

        $service->notifyParishAdminsOfPendingReview(
            1,
            123,
            99,
            'First',
            'Last',
            'requester@example.org',
            'profile',
            ['parish_id']
        );

        $this->assertCount(2, $capturedSenders);
        $this->assertSame(['admin1@example.org'], $capturedSenders[0]['to']);
        $this->assertSame('"Scoped Parish" <scoped-parish@example.org>', $capturedSenders[0]['from']);

        $this->assertSame(['admin2@example.org'], $capturedSenders[1]['to']);
        $this->assertSame('"Scoped Parish" <scoped-parish@example.org>', $capturedSenders[1]['from']);
    }
}
