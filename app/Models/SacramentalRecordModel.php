<?php

namespace App\Models;

use CodeIgniter\Model;

class SacramentalRecordModel extends Model
{
    protected $table            = 'sacramental_record';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'parish_id',
        'person_id',
        'sacrament_type',
        'sacrament_date',
        'register_ref',
        'certificate_number',
        'officiant_person_id',
        'location_notes',
        'notes',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'parish_id'           => 'required|is_not_unique[parish.id]',
        'person_id'           => 'required|is_not_unique[person.id]',
        'sacrament_type'      => 'required|max_length[50]',
        'sacrament_date'      => 'permit_empty|valid_date',
        'register_ref'        => 'permit_empty|max_length[100]',
        'certificate_number'  => 'permit_empty|max_length[100]',
        'officiant_person_id' => 'permit_empty|is_not_unique[person.id]',
        'location_notes'      => 'permit_empty|max_length[255]',
        'notes'               => 'permit_empty|max_length[1000]',
    ];

    /**
     * Get sacramental records for a person scoped to a parish, with officiant name.
     */
    public function getRecordsForPerson(int $personId, int $parishId): array
    {
        return $this->db->table('sacramental_record sr')
            ->select('sr.*, p.first_name AS officiant_first_name, p.last_name AS officiant_last_name')
            ->join('person p', 'p.id = sr.officiant_person_id', 'left')
            ->where('sr.parish_id', $parishId)
            ->where('sr.person_id', $personId)
            ->orderBy('sr.sacrament_date', 'DESC')
            ->orderBy('sr.id', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * Get a single sacramental record scoped to a parish.
     */
    public function getRecordForParish(int $recordId, int $parishId): ?array
    {
        $row = $this->db->table('sacramental_record sr')
            ->select('sr.*, p.first_name AS officiant_first_name, p.last_name AS officiant_last_name')
            ->join('person p', 'p.id = sr.officiant_person_id', 'left')
            ->where('sr.id', $recordId)
            ->where('sr.parish_id', $parishId)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }
}
