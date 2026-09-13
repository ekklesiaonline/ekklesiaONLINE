<?php

namespace App\Models;

use CodeIgniter\Model;

class ServiceWorshipItemModel extends Model
{
    protected $table            = 'service_worship_item';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'service_id',
        'parish_id',
        'song_id',
        'item_type',
        'title',
        'leader_name',
        'notes',
        'sort_order',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'service_id' => 'required|is_not_unique[service.id]',
        'parish_id' => 'required|is_not_unique[parish.id]',
        'item_type' => 'required|in_list[song,reading,prayer,sermon,announcement,other]',
        'title' => 'required|min_length[2]|max_length[255]',
    ];

    public function getServiceItems(int $serviceId): array
    {
        return $this->db->table('service_worship_item swi')
            ->select('swi.*, ws.title as song_title, ws.artist as song_artist')
            ->join('worship_song ws', 'ws.id = swi.song_id', 'left')
            ->where('swi.service_id', $serviceId)
            ->orderBy('swi.sort_order', 'ASC')
            ->get()
            ->getResultArray();
    }
}
