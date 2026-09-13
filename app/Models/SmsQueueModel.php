<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * SmsQueueModel
 *
 * Manages the sms_queue table used by MessagingService::queueSms() and
 * processed by the `php spark sms:process` command.
 */
class SmsQueueModel extends Model
{
    protected $table            = 'sms_queue';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'to_number',
        'body',
        'from_number',
        'parish_id',
        'status',
        'attempts',
        'max_attempts',
        'message_id',
        'error_message',
        'scheduled_at',
        'sent_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'to_number'    => 'required|max_length[30]',
        'body'         => 'required',
        'scheduled_at' => 'required|valid_date[Y-m-d H:i:s]',
        'status'       => 'permit_empty|in_list[pending,sent,failed,cancelled]',
    ];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // ------------------------------------------------------------------
    // Queries
    // ------------------------------------------------------------------

    /**
     * Fetch rows eligible for processing right now.
     * Only returns rows where scheduled_at <= NOW and attempts < max_attempts.
     *
     * @param  int $limit Max rows to pull per run (rate-limiting guard)
     */
    public function getDueMessages(int $limit = 50): array
    {
        return $this->where('status', 'pending')
            ->where('scheduled_at <=', date('Y-m-d H:i:s'))
            ->where('attempts < max_attempts', null, false)
            ->orderBy('scheduled_at', 'ASC')
            ->limit($limit)
            ->findAll();
    }

    /**
     * Mark a queued message as sent.
     */
    public function markSent(int $id, string $messageId): bool
    {
        return $this->update($id, [
            'status'     => 'sent',
            'message_id' => $messageId,
            'sent_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Increment attempt counter; mark as failed when max_attempts reached.
     */
    public function recordAttempt(int $id, string $errorMessage): bool
    {
        $row = $this->find($id);
        if (!$row) {
            return false;
        }

        $newAttempts = (int) $row['attempts'] + 1;
        $newStatus   = $newAttempts >= (int) $row['max_attempts'] ? 'failed' : 'pending';

        return $this->update($id, [
            'attempts'      => $newAttempts,
            'status'        => $newStatus,
            'error_message' => mb_substr($errorMessage, 0, 65535),
        ]);
    }

    /**
     * Cancel all pending messages for a given recipient phone number.
     */
    public function cancelByPhone(string $toNumber): int
    {
        $this->where('to_number', $toNumber)->where('status', 'pending');
        $count = $this->countAllResults(false); // false = do not reset query
        $this->set('status', 'cancelled')->update();
        return $count;
    }

    /**
     * Count pending messages optionally scoped to a parish.
     */
    public function countPending(?int $parishId = null): int
    {
        $builder = $this->where('status', 'pending');
        if ($parishId !== null) {
            $builder->where('parish_id', $parishId);
        }
        return (int) $builder->countAllResults();
    }
}
