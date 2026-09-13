<?php

namespace App\Models;

use CodeIgniter\Model;

class FollowupTemplateModel extends Model
{
    protected $table            = 'followup_template';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'parish_id',
        'channel',
        'name',
        'subject',
        'body_text',
        'body_html',
        'variables',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules = [
        'channel'   => 'required|in_list[text,email,call,mail,gift]',
        'name'      => 'required|min_length[3]|max_length[150]',
        'body_text' => 'required',
        'subject'   => 'permit_empty|max_length[255]',
        'parish_id' => 'permit_empty|is_not_unique[parish.id]',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Return templates available to a parish for a given channel.
     * Includes global (parish_id IS NULL) and parish-specific rows.
     */
    public function getForParishAndChannel(int $parishId, string $channel): array
    {
        return $this->groupStart()
            ->where('parish_id', $parishId)
            ->orWhere('parish_id IS NULL', null, false)
            ->groupEnd()
            ->where('channel', $channel)
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    /**
     * Return all templates available to a parish (all channels).
     */
    public function getAllForParish(int $parishId): array
    {
        return $this->groupStart()
            ->where('parish_id', $parishId)
            ->orWhere('parish_id IS NULL', null, false)
            ->groupEnd()
            ->orderBy('channel', 'ASC')
            ->orderBy('name', 'ASC')
            ->findAll();
    }
}
