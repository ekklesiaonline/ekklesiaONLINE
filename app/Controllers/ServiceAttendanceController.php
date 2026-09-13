<?php

namespace App\Controllers;

use App\Models\ServiceAttendanceModel;
use App\Models\MemberModel;
use App\Models\GuestModel;
use App\Models\ServiceModel;
use CodeIgniter\Controller;

class ServiceAttendanceController extends Controller
{
    protected $serviceAttendanceModel;
    protected $memberModel;
    protected $guestModel;
    protected $serviceModel;

    public function __construct()
    {
        $this->serviceAttendanceModel = new ServiceAttendanceModel();
        $this->memberModel = model('App\\Models\\MemberModel');
        $this->guestModel = model('App\\Models\\GuestModel');
        $this->serviceModel = model('App\\Models\\ServiceModel');
    }

    public function attendanceForm($serviceId)
    {
        return $this->response
            ->setStatusCode(410)
            ->setBody('Deprecated endpoint. Use /attendance/checkin and /attendance/manual.');
    }

    public function saveAttendance($serviceId)
    {
        return $this->response
            ->setStatusCode(410)
            ->setJSON([
                'success' => false,
                'error' => 'Deprecated endpoint. Use /attendance/checkin and /attendance/manual.',
            ]);
    }
}
