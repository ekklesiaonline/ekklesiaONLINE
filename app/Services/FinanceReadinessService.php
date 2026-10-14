<?php

namespace App\Services;

use App\Models\FundModel;
use CodeIgniter\Database\BaseConnection;

class FinanceReadinessService
{
    private BaseConnection $db;
    private FundModel $funds;

    public function __construct(?BaseConnection $db = null, ?FundModel $funds = null)
    {
        $this->db = $db ?? db_connect();
        $this->funds = $funds ?? model(FundModel::class);
    }

    public function readinessForParish(int $parishId): array
    {
        $rows = $this->funds->getParishFunds($parishId, false);
        $active = array_filter($rows, static fn (array $fund): bool => (int) ($fund['is_active'] ?? 0) === 1);
        $usable = array_filter($active, static fn (array $fund): bool => (int) ($fund['is_locked'] ?? 0) === 0);

        $status = 'ready';
        if ($rows === []) {
            $status = 'no_funds';
        } elseif ($active === []) {
            $status = 'no_active_funds';
        } elseif ($usable === []) {
            $status = 'all_active_funds_locked';
        }

        return [
            'parish_id'   => $parishId,
            'status'      => $status,
            'ready'       => $status === 'ready',
            'fund_count'  => count($rows),
            'active_count' => count($active),
            'usable_count' => count($usable),
        ];
    }

    public function audit(?int $parishId = null): array
    {
        $builder = $this->db->table('parish')
            ->select('id, name')
            ->orderBy('name', 'ASC');

        if ($this->db->fieldExists('deleted_at', 'parish')) {
            $builder->where('deleted_at', null);
        }
        if ($parishId !== null && $parishId > 0) {
            $builder->where('id', $parishId);
        }

        return array_map(function (array $parish): array {
            return [
                'parish_name' => (string) ($parish['name'] ?? ''),
                ...$this->readinessForParish((int) $parish['id']),
            ];
        }, $builder->get()->getResultArray());
    }

    public function provisionDefaultFund(int $parishId, ?int $createdBy = null): array
    {
        $parish = $this->db->table('parish')->select('id')->where('id', $parishId)->get()->getRowArray();
        if ($parish === null) {
            throw new \InvalidArgumentException('Parish not found.');
        }

        $this->db->transStart();
        $existing = $this->funds->where('parish_id', $parishId)->orderBy('id', 'ASC')->first();
        if (is_array($existing)) {
            $this->db->transComplete();

            return ['created' => false, 'fund' => $existing];
        }

        $fundId = (int) $this->funds->insert([
            'parish_id'   => $parishId,
            'name'        => 'Unrestricted Operating Fund',
            'description' => null,
            'fund_type'   => 'unrestricted',
            'restriction_purpose' => null,
            'chart_account_id' => null,
            'is_active'   => 1,
            'is_locked'   => 0,
            'created_by'  => $createdBy,
        ], true);
        $this->db->transComplete();

        if ($fundId <= 0 || ! $this->db->transStatus()) {
            throw new \RuntimeException('Unable to provision the default parish fund.');
        }

        $fund = $this->funds->find($fundId);
        service('audit')->log('fund.provision_default', [
            'user_id'    => $createdBy,
            'parish_id'  => $parishId,
            'entity_type' => 'fund',
            'entity_id'  => $fundId,
            'new_values' => [
                'name'       => 'Unrestricted Operating Fund',
                'fund_type'  => 'unrestricted',
                'is_active'  => 1,
                'is_locked'  => 0,
            ],
            'meta' => ['source' => 'finance_readiness'],
        ]);

        return ['created' => true, 'fund' => $fund];
    }
}
