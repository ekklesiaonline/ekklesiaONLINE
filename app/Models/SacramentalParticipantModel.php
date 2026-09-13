<?php

namespace App\Models;

use CodeIgniter\Model;

class SacramentalParticipantModel extends Model
{
    protected $table            = 'sacramental_participant';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'sacramental_record_id',
        'person_id',
        'participant_role',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'sacramental_record_id' => 'required|is_not_unique[sacramental_record.id]',
        'person_id'             => 'required|is_not_unique[person.id]',
        'participant_role'      => 'required|max_length[50]',
    ];

    /**
     * Get participants for a sacramental record with names.
     */
    public function getParticipantsForRecord(int $recordId): array
    {
        return $this->db->table('sacramental_participant sp')
            ->select('sp.*, p.first_name, p.last_name')
            ->join('person p', 'p.id = sp.person_id', 'inner')
            ->where('sp.sacramental_record_id', $recordId)
            ->orderBy('sp.participant_role', 'ASC')
            ->orderBy('p.last_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Get a single participant with its record parish for scoping.
     */
    public function getParticipantWithRecord(int $participantId): ?array
    {
        $row = $this->db->table('sacramental_participant sp')
            ->select('sp.*, sr.parish_id')
            ->join('sacramental_record sr', 'sr.id = sp.sacramental_record_id', 'inner')
            ->where('sp.id', $participantId)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }
}
