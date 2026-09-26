<?php

namespace App\Controllers;

use CodeIgniter\Controller;

class ServiceAttendanceController extends Controller
{
    public function attendanceForm($serviceId)
    {
        return redirect()->to('/attendance/manual?service_id=' . (int) $serviceId);
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
