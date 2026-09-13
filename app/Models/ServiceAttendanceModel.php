<?php

namespace App\Models;

use CodeIgniter\Model;

class ServiceAttendanceModel extends Model
{
    protected $table = 'service_attendance';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'service_id',
        'member_id',
        'guest_id',
        'attended',
        'communion',
        'created_at',
        'updated_at'
    ];
    protected $useTimestamps = true;
    protected $returnType = 'array';

    public function getAttendanceForService($serviceId)
    {
        return $this->where('service_id', $serviceId)->findAll();
    }

    public function getMemberAttendance($serviceId)
    {
        return $this->where('service_id', $serviceId)->where('member_id IS NOT NULL')->findAll();
    }

    public function getGuestAttendance($serviceId)
    {
        return $this->where('service_id', $serviceId)->where('guest_id IS NOT NULL')->findAll();
    }
}
