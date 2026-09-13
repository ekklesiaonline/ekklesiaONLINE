<?php

namespace App\Models;

use CodeIgniter\Model;

class WorshipSongModel extends Model
{
    protected $table            = 'worship_song';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'parish_id',
        'title',
        'artist',
        'key_signature',
        'tempo',
        'ccli',
        'notes',
        'lyrics',
        'is_active',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'parish_id' => 'required|is_not_unique[parish.id]',
        'title' => 'required|min_length[2]|max_length[255]',
        'artist' => 'permit_empty|max_length[255]',
        'key_signature' => 'permit_empty|max_length[20]',
        'tempo' => 'permit_empty|max_length[40]',
        'ccli' => 'permit_empty|max_length[50]',
    ];

    public function getParishSongs(int $parishId, ?string $search = null, string $status = 'active'): array
    {
        $builder = $this->where('parish_id', $parishId);

        if ($status === 'active') {
            $builder->where('is_active', 1);
        } elseif ($status === 'inactive') {
            $builder->where('is_active', 0);
        }

        if ($search !== null && trim($search) !== '') {
            $builder->groupStart()
                ->like('title', $search)
                ->orLike('artist', $search)
                ->orLike('ccli', $search)
                ->orLike('lyrics', $search)
                ->groupEnd();
        }

        return $builder->orderBy('is_active', 'DESC')->orderBy('title', 'ASC')->findAll();
    }
}
