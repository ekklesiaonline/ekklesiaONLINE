<?php

declare(strict_types=1);

namespace Tests;

use App\Services\ClergyAuthorizationService;
use App\Services\ClergyReviewService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Parish hire requests land in the diocesan review queue as pending rows;
 * approval (diocese-side) is what creates the leadership_position.
 */
final class ClergyAppointmentRequestTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;
    /** @var array<string, list<int>> */
    private array $created = [];

    protected function tearDown(): void
    {
        $personIds = $this->created['person'] ?? [];
        if ($personIds !== []) {
            foreach (['clergy_appointment_request', 'clergy_ordination', 'clergy_canonical_status', 'leadership_position'] as $table) {
                if ($this->db->tableExists($table)) {
                    $this->db->table($table)->whereIn('person_id', $personIds)->delete();
                }
            }
        }
        foreach (['clergy_appointment_request', 'user_person', 'profile', 'auth_identities', 'users', 'person', 'parish', 'diocese', 'province'] as $table) {
            if (! empty($this->created[$table]) && $this->db->tableExists($table)) {
                $this->db->table($table)->whereIn('id', $this->created[$table])->delete();
            }
        }
        parent::tearDown();
    }

    public function testApprovalActivatesParishScopedClergyPosition(): void
    {
        [$diocese, $parish] = $this->places();
        [$reviewer] = $this->ordinary($diocese);
        [$user, $person] = $this->account($diocese);
        $this->verified($person, $diocese, 'priest');
        $requestId = $this->request($person, $parish, $diocese, 'rector');

        $review = new ClergyReviewService($this->db, $this->authorization());
        $positionId = $review->reviewAppointmentRequest($requestId, $diocese, 'approved', null, $reviewer);

        self::assertNotNull($positionId);
        self::assertSame('approved', $this->db->table('clergy_appointment_request')
            ->where('id', $requestId)->get()->getRow('review_status'));

        $position = $this->db->table('leadership_position')->where('id', $positionId)->get()->getRowArray();
        self::assertSame('parish', $position['scope_type']);
        self::assertSame($parish, (int) $position['parish_id']);
        self::assertSame($diocese, (int) $position['diocese_id']);
        self::assertSame('clergy', $position['role_category']);
        self::assertSame('rector', $position['role_code']);
    }

    public function testReviewCannotCrossDioceses(): void
    {
        [$dioceseA, $parishA] = $this->places();
        $provinceB = $this->row('province', ['name' => 'Province B', 'province_number' => 'II']);
        $dioceseB = $this->row('diocese', ['province_id' => $provinceB, 'name' => 'B Diocese']);
        [$reviewerA] = $this->ordinary($dioceseA);
        [$reviewerB] = $this->ordinary($dioceseB);
        [$user, $person] = $this->account($dioceseB);
        $requestId = $this->request($person, $parishA, $dioceseB, 'vicar');

        $review = new ClergyReviewService($this->db, $this->authorization());

        // Diocese A's reviewer cannot touch diocese B's queue.
        self::assertNull($review->reviewAppointmentRequest($requestId, $dioceseA, 'approved', null, $reviewerA));
        // And the request row itself pins its own diocese — reviewing under a
        // mismatched diocese id fails even for diocese B's ordinary.
        self::assertNull($review->reviewAppointmentRequest($requestId, $dioceseA, 'approved', null, $reviewerB));
        self::assertSame('pending', $this->db->table('clergy_appointment_request')
            ->where('id', $requestId)->get()->getRow('review_status'));
    }

    public function testRejectionLeavesNoPositionAndNoSecondReview(): void
    {
        [$diocese, $parish] = $this->places();
        [$reviewer] = $this->ordinary($diocese);
        [$user, $person] = $this->account($diocese);
        $requestId = $this->request($person, $parish, $diocese, 'vicar');

        $review = new ClergyReviewService($this->db, $this->authorization());
        self::assertSame(0, $review->reviewAppointmentRequest($requestId, $diocese, 'rejected', 'not_in_diocese', $reviewer));
        self::assertSame('rejected', $this->db->table('clergy_appointment_request')
            ->where('id', $requestId)->get()->getRow('review_status'));
        self::assertSame(0, $this->db->table('leadership_position')->where('person_id', $person)->countAllResults());
        self::assertNull($review->reviewAppointmentRequest($requestId, $diocese, 'approved', null, $reviewer));
    }

    public function testRequestForPersonWithoutLinkedAccountFails(): void
    {
        [$diocese, $parish] = $this->places();
        [$reviewer] = $this->ordinary($diocese);
        $person = $this->row('person', ['first_name' => 'No', 'last_name' => 'Account']);
        $requestId = $this->request($person, $parish, $diocese, 'vicar');

        $review = new ClergyReviewService($this->db, $this->authorization());
        self::assertNull($review->reviewAppointmentRequest($requestId, $diocese, 'approved', null, $reviewer));
        self::assertSame('pending', $this->db->table('clergy_appointment_request')
            ->where('id', $requestId)->get()->getRow('review_status'));
    }

    public function testParishCannotSkipQueue(): void
    {
        // The table only stores workflow state; a 'pending' row must never
        // carry an activated position.
        [$diocese, $parish] = $this->places();
        [$user, $person] = $this->account($diocese);
        $requestId = $this->request($person, $parish, $diocese, 'rector');

        $row = $this->db->table('clergy_appointment_request')->where('id', $requestId)->get()->getRowArray();
        self::assertSame('pending', $row['review_status']);
        self::assertNull($row['position_id']);
        self::assertSame(0, $this->db->table('leadership_position')->where('person_id', $person)->countAllResults());
    }

    private function authorization(): ClergyAuthorizationService
    {
        return new ClergyAuthorizationService($this->db, static fn (): bool => true);
    }

    private function places(): array
    {
        $province = $this->row('province', ['name' => 'Province', 'province_number' => 'I']);
        $diocese = $this->row('diocese', ['province_id' => $province, 'name' => 'A Diocese']);
        $parish = $this->row('parish', ['diocese_id' => $diocese, 'name' => 'A Parish']);

        return [$diocese, $parish];
    }

    private function account(int $diocese): array
    {
        $person = $this->row('person', ['first_name' => 'Test', 'last_name' => 'Cleric']);
        $user = $this->row('users', ['username' => 'hire.' . uniqid(), 'active' => 1]);
        $this->row('auth_identities', ['user_id' => $user, 'type' => 'email_password', 'secret' => 'hire@example.invalid']);
        $this->row('user_person', ['user_id' => $user, 'person_id' => $person, 'is_primary' => 1]);
        $this->row('profile', ['user_id' => $user, 'person_id' => $person, 'diocese_id' => $diocese, 'portal_access' => 'enabled']);

        return [$user, $person];
    }

    private function ordinary(int $diocese): array
    {
        [$user, $person] = $this->account($diocese);
        $this->verified($person, $diocese, 'bishop');
        $officeId = $this->row('leadership_position', [
            'user_id' => $user, 'person_id' => $person, 'scope_type' => 'diocese',
            'diocese_id' => $diocese, 'title' => 'diocesan_bishop', 'role_code' => 'diocesan_bishop',
            'role_category' => 'clergy', 'clergy_order' => 'bishop',
            'active' => 1, 'started_at' => date('Y-m-d', strtotime('-3 months')),
            'approved_by_user_id' => 999, 'approved_at' => date('Y-m-d H:i:s'),
            'approval_evidence_reference' => 'diocesan-register-1',
        ]);
        $this->row('clergy_access_grant', [
            'user_id' => $user, 'permission' => 'clergy.appointment.manage', 'scope_type' => 'diocese',
            'scope_id' => $diocese, 'source_position_id' => $officeId,
            'grant_type' => 'office', 'granted_by_user_id' => 999,
            'starts_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);

        return [$user, $person];
    }

    private function verified(int $person, int $diocese, string $order): void
    {
        $this->row('clergy_ordination', [
            'person_id' => $person, 'order_code' => $order, 'verification_status' => 'verified',
            'verified_by_user_id' => 999, 'verified_at' => date('Y-m-d H:i:s'),
        ]);
        $this->row('clergy_canonical_status', [
            'person_id' => $person, 'diocese_id' => $diocese, 'status_code' => 'licensed',
            'effective_from' => date('Y-m-d', strtotime('-1 year')),
        ]);
    }

    private function request(int $person, int $parish, int $diocese, string $office): int
    {
        return $this->row('clergy_appointment_request', [
            'person_id' => $person, 'parish_id' => $parish, 'diocese_id' => $diocese,
            'proposed_office' => $office, 'review_status' => 'pending',
            'requested_by_user_id' => 999,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function row(string $table, array $fields): int
    {
        $this->db->table($table)->insert($fields);
        $id = (int) $this->db->insertID();
        $this->created[$table][] = $id;

        return $id;
    }
}
