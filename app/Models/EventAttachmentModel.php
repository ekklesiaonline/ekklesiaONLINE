<?php

namespace App\Models;

use CodeIgniter\Model;

class EventAttachmentModel extends Model
{
    protected $table            = 'event_attachments';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'event_id',
        'original_name',
        'stored_name',
        'mime_type',
        'file_size',
        'attachment_type',
        'created_at',
    ];
    protected $useTimestamps = false;

    public function getEventAttachments(int $eventId): array
    {
        return $this->where('event_id', $eventId)
            ->orderBy('id', 'ASC')
            ->findAll();
    }
}
