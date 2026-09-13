<?php

namespace App\Models;

class DonationReceiptModel extends BaseTenantModel
{
    protected $table            = 'donation_receipts';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'parish_id',
        'donation_id',
        'receipt_number',
        'issued_at',
        'issued_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'parish_id'      => 'required|is_not_unique[parish.id]',
        'donation_id'    => 'required|is_not_unique[donation.id]',
        'receipt_number' => 'required|max_length[100]',
        'issued_at'      => 'required|valid_date',
        'issued_by'      => 'required|is_not_unique[users.id]',
    ];

    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Issue a receipt for a donation.
     *
     * @return array<string, mixed>|null
     */
    public function issueForDonation(int $donationId, int $parishId, int $issuedBy): ?array
    {
        $db = db_connect();

        $db->transStart();

        try {
            $receiptNumber = $this->nextReceiptNumber($parishId);

            $receiptId = (int) $this->insert([
                'parish_id'      => $parishId,
                'donation_id'    => $donationId,
                'receipt_number' => $receiptNumber,
                'issued_at'      => date('Y-m-d H:i:s'),
                'issued_by'      => $issuedBy,
            ], true);

            if ($receiptId <= 0) {
                $db->transRollback();

                return null;
            }

            $db->transComplete();

            return $this->find($receiptId);
        } catch (\Throwable $e) {
            $db->transRollback();

            return null;
        }
    }

    /**
     * Get the active receipt for a donation.
     *
     * @return array<string, mixed>|null
     */
    public function getForDonation(int $donationId): ?array
    {
        return $this->where('donation_id', $donationId)
            ->first() ?: null;
    }

    /**
     * Generate the next per-parish sequential receipt number.
     */
    private function nextReceiptNumber(int $parishId): string
    {
        $db = db_connect();

        $db->query(
            'INSERT INTO ' . $db->getPrefix() . 'parish_receipt_counter (parish_id, last_number) ' .
            'VALUES (?, 1) ' .
            'ON DUPLICATE KEY UPDATE last_number = last_number + 1',
            [$parishId]
        );

        $row = $db->table('parish_receipt_counter')
            ->where('parish_id', $parishId)
            ->get()
            ->getRowArray();

        $number = (int) ($row['last_number'] ?? 1);

        return 'RCP-' . $parishId . '-' . $number;
    }
}
