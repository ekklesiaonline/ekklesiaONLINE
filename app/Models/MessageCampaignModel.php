<?php

namespace App\Models;

class MessageCampaignModel extends BaseTenantModel
{
    public const CHANNEL_EMAIL        = 'email';
    public const CHANNEL_SMS          = 'sms';
    public const CHANNEL_NOTIFICATION = 'notification';

    public const STATUS_DRAFT      = 'draft';
    public const STATUS_SENDING    = 'sending';
    public const STATUS_SENT       = 'sent';
    public const STATUS_CANCELLED  = 'cancelled';

    protected $table            = 'message_campaign';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'parish_id',
        'channel',
        'subject',
        'body',
        'audience',
        'status',
        'total_recipients',
        'sent_count',
        'skipped_count',
        'failed_count',
        'created_by',
        'sent_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'parish_id' => 'required|is_not_unique[parish.id]',
        'channel'   => 'required|in_list[email,sms,notification]',
        'subject'   => 'permit_empty|max_length[255]',
        'body'      => 'required',
        'status'    => 'permit_empty|in_list[draft,sending,sent,cancelled]',
    ];

    /**
     * @return array<int, string>
     */
    public static function channels(): array
    {
        return [self::CHANNEL_EMAIL, self::CHANNEL_SMS, self::CHANNEL_NOTIFICATION];
    }
}
