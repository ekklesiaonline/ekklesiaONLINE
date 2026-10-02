<?php

declare(strict_types=1);

namespace Tests;

use App\Services\ClergyAuthorizationService;
use App\Services\ParishReportApprovalService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class ParishReportApprovalTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;
    /** @var array<string,list<int>> */
    private array $created = [];

    protected function tearDown(): void
    {
        $reportIds = $this->created['parish_report'] ?? [];
        if ($reportIds !== []) {
            foreach (['parish_report_signature', 'parish_report_revision'] as $table) {
                $this->db->table($table)->whereIn('report_id', $reportIds)->delete();
            }
            $this->db->table('parish_report')->whereIn('id', $reportIds)->delete();
        }
        if (! empty($this->created['users'])) {
            $this->db->table('clergy_access_grant')->whereIn('user_id', $this->created['users'])->delete();
            $this->db->table('leadership_position')->whereIn('user_id', $this->created['users'])->delete();
        }
        if (! empty($this->created['person'])) {
            foreach (['clergy_ordination', 'clergy_canonical_status'] as $table) {
                $this->db->table($table)->whereIn('person_id', $this->created['person'])->delete();
            }
        }
        foreach (['parish_report_storage', 'diocese_setting', 'user_person', 'profile', 'auth_identities',
            'users', 'person', 'parish', 'diocese', 'province'] as $table) {
            if (! empty($this->created[$table])) {
                $this->db->table($table)->whereIn('id', $this->created[$table])->delete();
            }
        }
        parent::tearDown();
    }

    public function testFourDistinctActorsSignOnlyOwnFrozenParishReportAndPreserveRevision(): void
    {
        [$diocese, $parish, $otherParish] = $this->places();
        $this->row('diocese_setting', [
            'diocese_id' => $diocese, 'key' => 'governance.report.signatures_enabled', 'value' => '1',
        ]);
        $reporter = $this->account($parish);
        $clerk = $this->account($parish);
        $warden = $this->account($parish);
        [$priest, $priestPerson] = $this->account($parish);
        [$bishop, $bishopPerson] = $this->account(null, $diocese);
        $this->row('parish_report_storage', [
            'parish_id' => $parish, 'report_year' => 2026, 'membership_previous_total' => 12,
        ]);
        $this->row('leadership_position', [
            'user_id' => $clerk[0], 'person_id' => $clerk[1], 'scope_type' => 'parish',
            'parish_id' => $parish, 'role_code' => 'vestry_secretary', 'role_category' => 'lay_staff',
            'title' => 'Vestry Secretary', 'active' => 1, 'approved_by_user_id' => 999,
            'approved_at' => date('Y-m-d H:i:s'),
            'approval_evidence_reference' => 'vestry-minutes-clerk',
        ]);
        $this->row('leadership_position', [
            'user_id' => $warden[0], 'person_id' => $warden[1], 'scope_type' => 'parish',
            'parish_id' => $parish, 'role_code' => 'warden', 'role_category' => 'lay_staff',
            'title' => 'Warden', 'active' => 1, 'approved_by_user_id' => 999,
            'approved_at' => date('Y-m-d H:i:s'),
            'approval_evidence_reference' => 'vestry-minutes-warden',
        ]);
        $priestOffice = $this->cleric($priest, $priestPerson, 'priest', 'rector', 'parish', $parish, $diocese);
        $bishopOffice = $this->cleric($bishop, $bishopPerson, 'bishop', 'diocesan_bishop', 'diocese', $diocese, $diocese);
        $this->grant($priest, $priestOffice, 'governance.report.finalize_clergy', 'parish', $parish);
        $this->grant($bishop, $bishopOffice, 'governance.diocese.approve', 'diocese', $diocese);
        $service = $this->service();
        $id = $service->submit($parish, 2026, $reporter[0]);
        self::assertNotNull($id);
        $this->created['parish_report'][] = $id;
        self::assertFalse($service->sign($id, $priest, 'finalize_clergy'));
        self::assertTrue($service->sign($id, $clerk[0], 'certify_clerk'));
        self::assertFalse($service->sign($id, $clerk[0], 'approve_warden'));
        self::assertTrue($service->sign($id, $warden[0], 'approve_warden'));
        self::assertTrue($service->sign($id, $priest, 'finalize_clergy'));
        self::assertFalse($service->sign($id, $priest, 'approve_diocese'));
        self::assertTrue($service->sign($id, $bishop, 'approve_diocese'));
        self::assertSame(4, $this->db->table('parish_report_signature')->where('report_id', $id)->countAllResults());
        $bishopSignature = $this->db->table('parish_report_signature')->where('report_id', $id)
            ->where('stage_code', 'approve_diocese')->get()->getRowArray();
        self::assertSame($diocese, (int) $bishopSignature['diocese_id']);
        self::assertSame($bishopOffice, (int) $bishopSignature['source_position_id']);
        self::assertSame(0, (int) $bishopSignature['was_delegated']);
        self::assertNull($service->submit($otherParish, 2026, $reporter[0]));
        self::assertTrue($service->startRevision($id, $reporter[0]));
        self::assertSame(1, $this->db->table('parish_report_revision')->where('report_id', $id)
            ->where('report_status', 'diocesan_approved')->countAllResults());
        self::assertSame('draft', $this->db->table('parish_report')->where('id', $id)->get()->getRow('report_status'));
    }

    public function testPolicyAndStorageChangesDenySignaturesRatherThanChangingFrozenReport(): void
    {
        [$diocese, $parish] = $this->places();
        $reporter = $this->account($parish);
        $storageId = $this->row('parish_report_storage', [
            'parish_id' => $parish, 'report_year' => 2026, 'membership_previous_total' => 4,
        ]);
        $service = $this->service();
        self::assertNull($service->submit($parish, 2026, $reporter[0]));
        $this->row('diocese_setting', [
            'diocese_id' => $diocese, 'key' => 'governance.report.signatures_enabled', 'value' => '1',
        ]);
        $id = $service->submit($parish, 2026, $reporter[0]);
        self::assertNotNull($id);
        $this->created['parish_report'][] = $id;
        $this->db->table('parish_report_storage')->where('id', $storageId)
            ->update(['membership_previous_total' => 5]);
        self::assertFalse($service->sign($id, $reporter[0], 'certify_clerk'));
        self::assertTrue($service->startRevision($id, $reporter[0]));
    }

    private function service(): ParishReportApprovalService
    {
        return new ParishReportApprovalService($this->db,
            new ClergyAuthorizationService($this->db, static fn (): bool => true),
            static fn (): bool => true);
    }

    private function places(): array
    {
        $province = $this->row('province', ['name' => 'Province', 'province_number' => 'I']);
        $diocese = $this->row('diocese', ['name' => 'Diocese A', 'province_id' => $province]);
        $parish = $this->row('parish', ['name' => 'Parish A', 'diocese_id' => $diocese]);
        $other = $this->row('parish', ['name' => 'Parish B', 'diocese_id' => $diocese]);
        return [$diocese, $parish, $other];
    }

    private function account(?int $parishId, ?int $dioceseId = null): array
    {
        $person = $this->row('person', ['first_name' => 'Reporter', 'last_name' => 'Test']);
        $user = $this->row('users', ['username' => 'report.' . uniqid(), 'active' => 1]);
        $this->row('auth_identities', ['user_id' => $user, 'type' => 'email_password', 'secret' => 'report@example.invalid']);
        $this->row('user_person', ['user_id' => $user, 'person_id' => $person, 'is_primary' => 1]);
        $this->row('profile', [
            'user_id' => $user, 'person_id' => $person, 'parish_id' => $parishId,
            'diocese_id' => $dioceseId, 'portal_access' => 'enabled',
        ]);
        return [$user, $person];
    }

    private function cleric(int $user, int $person, string $order, string $office,
        string $scope, int $scopeId, int $dioceseId): int
    {
        $this->row('clergy_ordination', [
            'person_id' => $person, 'order_code' => $order,
            'verification_status' => 'verified', 'verified_at' => date('Y-m-d H:i:s'),
            'verified_by_user_id' => 999,
        ]);
        $this->row('clergy_canonical_status', [
            'person_id' => $person, 'diocese_id' => $dioceseId,
            'status_code' => 'licensed', 'effective_from' => date('Y-m-d', strtotime('-1 year')),
        ]);
        return $this->row('leadership_position', [
            'user_id' => $user, 'person_id' => $person, 'scope_type' => $scope,
            $scope . '_id' => $scopeId, 'role_code' => $office, 'role_category' => 'clergy',
            'clergy_order' => $order, 'title' => $office, 'active' => 1,
            'started_at' => date('Y-m-d', strtotime('-1 month')),
            'approved_by_user_id' => 999, 'approved_at' => date('Y-m-d H:i:s'),
            'approval_evidence_reference' => 'diocesan-appointment-register',
        ]);
    }

    private function grant(int $user, int $officeId, string $permission, string $scope, int $scopeId): void
    {
        $this->row('clergy_access_grant', [
            'user_id' => $user, 'permission' => $permission, 'scope_type' => $scope,
            'scope_id' => $scopeId, 'source_position_id' => $officeId,
            'grant_type' => 'office', 'granted_by_user_id' => 999,
            'starts_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
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
