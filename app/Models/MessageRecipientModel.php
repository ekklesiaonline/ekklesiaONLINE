<?php

namespace App\Models;

use CodeIgniter\Model;

class MessageRecipientModel extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_QUEUED  = 'queued';
    public const STATUS_SENT    = 'sent';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_FAILED  = 'failed';

    public const SKIP_OPTED_OUT  = 'opted_out';
    public const SKIP_NO_ADDRESS = 'no_address';
    public const SKIP_NO_USER    = 'no_user';

    protected $table            = 'message_recipient';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'campaign_id',
        'person_id',
        'user_id',
        'name',
        'first_name',
        'last_name',
        'address',
        'status',
        'skip_reason',
        'queue_id',
        'error',
        'sent_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'campaign_id' => 'required|is_not_unique[message_campaign.id]',
        'status'      => 'permit_empty|in_list[pending,queued,sent,skipped,failed]',
    ];

    /**
     * Per-status tallies for a campaign.
     *
     * @return array<string, int>
     */
    public function countsForCampaign(int $campaignId): array
    {
        $rows = $this->db->table('message_recipient')
            ->select('status, COUNT(*) AS n', false)
            ->where('campaign_id', $campaignId)
            ->groupBy('status')
            ->get()
            ->getResultArray();

        $counts = [
            self::STATUS_PENDING => 0,
            self::STATUS_QUEUED  => 0,
            self::STATUS_SENT    => 0,
            self::STATUS_SKIPPED => 0,
            self::STATUS_FAILED  => 0,
        ];

        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['n'];
        }

        return $counts;
    }
}
