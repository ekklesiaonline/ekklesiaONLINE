<?php

namespace App\Models;

use CodeIgniter\Model;

class PersonVisitModel extends Model
{
    protected $afterInsert = ['scheduleFollowups'];
    protected $table            = 'person_visit';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'person_id',
        'parish_id',
        'visit_date',
        'visit_type',
        'service_id',
        'attended_event',
        'gift_given',
        'notes',
        'created_at',
        'updated_at',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [
        'person_id'  => 'int',
        'parish_id'  => '?int',
        'service_id' => '?int',
        'gift_given' => 'int',
    ];
    protected array $castHandlers = [];

    protected $useTimestamps = false;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'person_id'  => 'required|integer',
        'parish_id'  => 'permit_empty|integer',
        'visit_date' => 'required|valid_date',
        'visit_type' => 'required|in_list[first,second,return]',
        'service_id' => 'permit_empty|integer',
        'gift_given' => 'permit_empty|integer',
    ];

    protected function scheduleFollowups(array $data)
    {
        if (! isset($data['id']) || (string) ($data['visit_type'] ?? '') !== 'first') {
            return $data;
        }

        $personId = (int) ($data['person_id'] ?? 0);
        $parishId = (int) ($data['parish_id'] ?? 0);
        $visitDate = (string) ($data['visit_date'] ?? date('Y-m-d'));

        if ($personId <= 0 || $parishId <= 0) {
            return $data;
        }

        try {
            model(FollowupLogModel::class)->scheduleActiveSequencesForPerson($personId, $parishId, $visitDate);
        } catch (\Throwable $e) {
            log_message('error', 'PersonVisit follow-up scheduling failed: ' . $e->getMessage());
        }

        return $data;
    }

}
