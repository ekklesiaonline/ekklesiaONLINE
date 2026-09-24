<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * PersonNoteModel
 *
 * Staff-authored notes on a canonical person row (person_note table).
 * Displayed in the visitor record's Notes feed alongside intake-form
 * notes (person_visit.notes) and follow-up outcome notes.
 */
class PersonNoteModel extends Model
{
    protected $table            = 'person_note';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'person_id',
        'parish_id',
        'user_id',
        'note',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules = [
        'person_id' => 'required|is_not_unique[person.id]',
        'parish_id' => 'required|is_not_unique[parish.id]',
        'user_id'   => 'required|is_not_unique[users.id]',
        'note'      => 'required|max_length[2000]',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;
}
