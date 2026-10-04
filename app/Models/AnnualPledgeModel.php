<?php

namespace App\Models;

use CodeIgniter\Model;

class AnnualPledgeModel extends Model
{
    protected $table            = 'annual_pledge';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id',
        'person_id',
        'parish_id',
        'pledge_year',
        'pledged_amount',
        'fulfilled_amount',
        'pledged_at',
        'fulfillment_status',
        'status_id',
        'notes',
    ];

    /**
     * @var array<string, string>
     */
    protected array $casts = [
        'id'               => '?int',
        'user_id'          => '?int',
        'person_id'        => '?int',
        'parish_id'        => '?int',
        'pledge_year'      => '?int',
        'pledged_amount'   => '?float',
        'fulfilled_amount' => '?float',
        'status_id'        => '?int',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'user_id' => 'required|is_not_unique[users.id]',
        'parish_id' => 'required|is_not_unique[parish.id]',
        'pledge_year' => 'required|integer|greater_than_equal_to[2000]',
        'pledged_amount' => 'permit_empty|decimal',
        'fulfilled_amount' => 'permit_empty|decimal',
        'pledged_at' => 'permit_empty|valid_date',
        'fulfillment_status' => 'required|in_list[pledged,partial,fulfilled,cancelled]',
    ];

    public function hasPledgeForYear(int $userId, int $parishId, int $year): bool
    {
        return $this->where('user_id', $userId)
            ->where('parish_id', $parishId)
            ->where('pledge_year', $year)
            ->where('fulfillment_status !=', 'cancelled')
            ->countAllResults() > 0;
    }

    public function getPledgeForYear(int $userId, int $parishId, int $year): ?array
    {
        return $this->where('user_id', $userId)
            ->where('parish_id', $parishId)
            ->where('pledge_year', $year)
            ->first();
    }

    public function getMemberPledges(int $userId, ?int $parishId = null): array
    {
        $builder = $this->where('user_id', $userId);

        if ($parishId !== null) {
            $builder->where('parish_id', $parishId);
        }

        return $builder
            ->orderBy('pledge_year', 'DESC')
            ->findAll();
    }

    public function getParishPledgeSummary(int $parishId, int $year): array
    {
        $row = $this->builder()
            ->select('COUNT(*) AS total_pledges, COALESCE(SUM(pledged_amount), 0) AS pledged_total, COALESCE(SUM(fulfilled_amount), 0) AS fulfilled_total', false)
            ->where('parish_id', $parishId)
            ->where('pledge_year', $year)
            ->where('fulfillment_status !=', 'cancelled')
            ->get()
            ->getRowArray();

        return [
            'total_pledges' => (int) ($row['total_pledges'] ?? 0),
            'pledged_total' => (float) ($row['pledged_total'] ?? 0),
            'fulfilled_total' => (float) ($row['fulfilled_total'] ?? 0),
        ];
    }
}
