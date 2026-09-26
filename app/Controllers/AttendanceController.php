<?php

namespace App\Controllers;



class AttendanceController extends BaseController
{
    /** @var \App\Models\AttendanceModel */
    protected $attendanceModel;
    /** @var \App\Models\ServiceModel */
    protected $serviceModel;


    public function __construct()
    {
        $this->attendanceModel = model('AttendanceModel');
        $this->serviceModel = model('ServiceModel');
    }

    public function checkin()
    {
        if ($this->request->getMethod() === 'POST') {
            if ($redirect = $this->guardAttendanceAccess(true)) {
                return $redirect;
            }

            $parishId = (int) ($this->getOperationalparishId() ?? 0);
            if ($parishId <= 0) {
                return $this->jsonError(lang('App.errorNoParishScope'), 422);
            }

            $rules = [
                'service_id' => 'required|integer',
                'user_id' => 'required|integer',
                'attended' => 'permit_empty',
                'communicant' => 'permit_empty',
            ];

            if (!$this->validate($rules)) {
                return $this->jsonError(implode(' ', $this->validator->getErrors()), 422);
            }

            $serviceId = (int) $this->request->getPost('service_id');
            $userId = (int) $this->request->getPost('user_id');
            $attended = $this->toBool($this->request->getPost('attended'), true);
            $communicant = $this->toBool($this->request->getPost('communicant'), false);

            if (!$this->serviceBelongsToParish($serviceId, $parishId)) {
                return $this->jsonError('Invalid service selection.', 404);
            }

            if (!$this->isRecordableMember($userId, $parishId)) {
                return $this->jsonError('Selected member is not active for this Parish.', 422);
            }

            $result = $this->attendanceModel->recordAttendance($serviceId, $userId, $attended, $communicant);
            if (!$result['success']) {
                return $this->jsonError('Unable to record attendance.', 500);
            }

            return $this->jsonSuccess('Check-in recorded successfully.', [
                'action' => $result['action'],
                'id' => $result['id'],
            ]);
        }

        if ($redirect = $this->guardAttendanceAccess(false)) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalparishId() ?? 0);
        if ($parishId <= 0) {
            return redirect()->to('/dashboard')->with('error', lang('App.errorNoParishScope'));
        }

        $services = $this->getCheckInServiceOptions($parishId);
        $defaultServiceId = !empty($services) ? (int) $services[0]['id'] : 0;

        $data = array_merge($this->getCommonViewData('Mark Attendance'), [
            'services' => $services,
            'default_service_id' => $defaultServiceId,
            'csrf_name' => csrf_token(),
            'csrf_hash' => csrf_hash(),
        ]);

        return view('attendance/checkin', $data);
    }

    public function manual()
    {
        if ($redirect = $this->guardAttendanceAccess(false)) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalparishId() ?? 0);
        if ($parishId <= 0) {
            return redirect()->to('/dashboard')->with('error', lang('App.errorNoParishScope'));
        }

        $services = $this->getManualServiceOptions($parishId, 50);
        $selectedServiceId = (int) ($this->request->getGet('service_id') ?? ($services[0]['id'] ?? 0));

        // A deep-linked service (e.g. from /attendance/service/{id}) may fall
        // outside the recent-50 options; prepend it so the select stays valid.
        if ($selectedServiceId > 0
            && !in_array($selectedServiceId, array_map(static fn($row) => (int) ($row['id'] ?? 0), $services), true)
            && $this->serviceBelongsToParish($selectedServiceId, $parishId)
        ) {
            $extra = db_connect()->query(
                'SELECT s.id, s.service_date, s.service_time, s.service
                 FROM service s
                 WHERE s.id = ? AND s.parish_id = ?
                 LIMIT 1',
                [$selectedServiceId, $parishId]
            )->getRowArray();

            if (!empty($extra)) {
                array_unshift($services, $extra);
            }
        }

        $data = array_merge($this->getCommonViewData('Mark Attendance'), [
            'services' => $services,
            'selected_service_id' => $selectedServiceId,
            'member_options' => [],
            'csrf_name' => csrf_token(),
            'csrf_hash' => csrf_hash(),
            'flash_success' => session()->getFlashdata('success'),
            'flash_error' => session()->getFlashdata('error'),
        ]);

        return view('attendance/manual', $data);
    }

    public function bulk()
    {
        if ($redirect = $this->guardAttendanceAccess(false)) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalparishId() ?? 0);
        if ($parishId <= 0) {
            return redirect()->to('/dashboard')->with('error', lang('App.errorNoParishScope'));
        }

        $rules = [
            'service_id' => 'required|integer',
            'user_ids' => 'required',
            'attended' => 'permit_empty',
            'communicant' => 'permit_empty',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $serviceId = (int) $this->request->getPost('service_id');
        $rawUserIds = (array) $this->request->getPost('user_ids');
        $attended = $this->toBool($this->request->getPost('attended'), true);
        $communicant = $this->toBool($this->request->getPost('communicant'), false);

        if (!$this->serviceBelongsToParish($serviceId, $parishId)) {
            return redirect()->back()->withInput()->with('error', 'Invalid service selection.');
        }

        $inserted = 0;
        $updated = 0;
        $failed = 0;

        $uniqueUserIds = array_values(array_unique(array_map('intval', $rawUserIds)));

        foreach ($uniqueUserIds as $userId) {
            if ($userId <= 0 || !$this->isRecordableMember($userId, $parishId)) {
                $failed++;
                continue;
            }

            $result = $this->attendanceModel->recordAttendance($serviceId, $userId, $attended, $communicant);
            if (!$result['success']) {
                $failed++;
                continue;
            }

            if ($result['action'] === 'inserted') {
                $inserted++;
            } else {
                $updated++;
            }
        }

        $summary = sprintf('Bulk attendance completed. Inserted: %d, Updated: %d, Failed: %d.', $inserted, $updated, $failed);

        return redirect()->to('/attendance/manual?service_id=' . $serviceId)->with('success', $summary);
    }

    public function recent($serviceId)
    {
        if ($redirect = $this->guardAttendanceAccess(true)) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalparishId() ?? 0);
        $serviceId = (int) $serviceId;

        if ($parishId <= 0 || !$this->serviceBelongsToParish($serviceId, $parishId)) {
            return $this->jsonError('Invalid service selection.', 404);
        }

        $rows = $this->attendanceModel->getRecentForService($serviceId, 20);
        $records = array_map(function (array $row): array {
            $fullName = trim(((string) ($row['first_name'] ?? '')) . ' ' . ((string) ($row['last_name'] ?? '')));
            if ($fullName === '') {
                $fullName = (string) ($row['username'] ?? 'Member');
            }

            return [
                'id' => (int) $row['id'],
                'service_id' => (int) $row['service_id'],
                'user_id' => (int) $row['user_id'],
                'full_name' => $fullName,
                'email' => (string) ($row['email'] ?? ''),
                'attended' => (int) ($row['attended'] ?? 0) === 1,
                'communicant' => (int) ($row['communicant'] ?? 0) === 1,
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }, $rows);

        return $this->jsonSuccess('Recent attendance loaded.', ['records' => $records]);
    }

    public function update($id)
    {
        if ($redirect = $this->guardAttendanceAccess(true)) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalparishId() ?? 0);
        $id = (int) $id;

        $record = $this->dbAttendanceRecordForParish($id, $parishId);
        if ($record === null) {
            return $this->jsonError('Attendance record not found.', 404);
        }

        $attended = $this->toBool($this->request->getPost('attended'), false);
        $communicant = $this->toBool($this->request->getPost('communicant'), false);

        $result = $this->attendanceModel->recordAttendance(
            (int) $record['service_id'],
            (int) $record['user_id'],
            $attended,
            $communicant
        );

        if (!$result['success']) {
            return $this->jsonError('Unable to update attendance record.', 500);
        }

        return $this->jsonSuccess('Attendance record updated.');
    }

    public function delete($id)
    {
        if ($redirect = $this->guardAttendanceAccess(true)) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalparishId() ?? 0);
        $id = (int) $id;

        $record = $this->dbAttendanceRecordForParish($id, $parishId);
        if ($record === null) {
            return $this->jsonError('Attendance record not found.', 404);
        }

        $deleted = (bool) $this->attendanceModel->delete($id);
        if (!$deleted) {
            return $this->jsonError('Unable to delete attendance record.', 500);
        }

        return $this->jsonSuccess('Attendance record deleted.');
    }

    public function searchMembers()
    {
        if ($redirect = $this->guardAttendanceAccess(true)) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalparishId() ?? 0);
        if ($parishId <= 0) {
            return $this->jsonError(lang('App.errorNoParishScope'), 422);
        }

        $term = trim((string) ($this->request->getGet('q') ?? ''));
        $limit = (int) ($this->request->getGet('limit') ?? 20);
        $limit = max(1, min($limit, 100));

        $rows = $this->searchActiveMembers($parishId, $term, $limit);

        return $this->jsonSuccess('Members loaded.', ['members' => $rows]);
    }

    private function guardAttendanceAccess(bool $json = false)
    {
        if (!auth()->loggedIn()) {
            if ($json) {
                return $this->jsonError('Unauthorized.', 401);
            }

            return redirect()->to('/login');
        }

        $user = auth()->user();
        $allowed = $user
            && (
                $user->inGroup('attendance_recorder')
                || $user->inGroup('admin')
                || $user->can('attendance.mark')
            );

        if ($allowed) {
            return null;
        }

        if ($json) {
            return $this->jsonError('Forbidden.', 403);
        }

        return redirect()->to('/dashboard')->with('error', lang('App.errorForbidden'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getCheckInServiceOptions(int $parishId): array
    {
        $rows = db_connect()->query(
            'SELECT s.id, s.service_date, s.service_time, s.service
             FROM service s
               WHERE s.parish_id = ?
               AND (
                    s.service_date = CURDATE()
                    OR TIMESTAMP(s.service_date, COALESCE(s.service_time, "00:00:00")) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 2 HOUR)
               )
             ORDER BY s.service_date ASC, s.service_time ASC
             LIMIT 50',
            [$parishId]
        )->getResultArray();

        if (empty($rows)) {
            $rows = db_connect()->query(
                'SELECT s.id, s.service_date, s.service_time, s.service
                 FROM service s
                 WHERE s.parish_id = ?
                 ORDER BY s.service_date DESC, s.service_time DESC
                 LIMIT 50',
                [$parishId]
            )->getResultArray();
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getManualServiceOptions(int $parishId, int $limit = 50): array
    {
        return db_connect()->query(
            'SELECT s.id, s.service_date, s.service_time, s.service
             FROM service s
             WHERE s.parish_id = ?
             ORDER BY s.service_date DESC, s.service_time DESC
             LIMIT ' . (int) max(1, $limit),
            [$parishId]
        )->getResultArray();
    }

    private function serviceBelongsToParish(int $serviceId, int $parishId): bool
    {
        $row = db_connect()->table('service')
            ->select('id')
            ->where('id', $serviceId)
            ->where('parish_id', $parishId)
            ->get()
            ->getRowArray();

        return !empty($row);
    }

    private function isRecordableMember(int $userId, int $parishId): bool
    {
        return model('MemberParishAffiliationModel')->isRecordableMember($userId, $parishId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchActiveMembers(int $parishId, string $term, int $limit): array
    {
        $hasIsMember = $this->affiliationHasMemberTag();
        $db = db_connect();
        $profileTable = $this->resolveExistingProfileTable($db);
        if ($profileTable === null) {
            return [];
        }

        $builder = $db->table('users u')
            ->select('u.id, u.username, p.first_name, p.last_name, MAX(ai.secret) as email')
            ->join('parish_affiliation mca', 'mca.linked_user_id = u.id', 'inner')
            ->join($profileTable . ' p', 'p.user_id = u.id', 'left')
            ->join('auth_identities ai', 'ai.user_id = u.id AND ai.type = "email_password"', 'left')
            ->where('u.active', 1)
            ->where('mca.parish_id', $parishId)
            ->where('mca.is_member', 1)
            ->where('mca.membership_status', 'member')
            ->groupBy('u.id, u.username, p.first_name, p.last_name')
            ->orderBy('p.last_name', 'ASC')
            ->orderBy('p.first_name', 'ASC')
            ->limit($limit);

        if ($hasIsMember) {
            $builder->where('mca.is_member', 1);
        }

        if ($term !== '') {
            $builder->groupStart()
                ->like('p.first_name', $term)
                ->orLike('p.last_name', $term)
                ->orLike('u.username', $term)
                ->orLike('ai.secret', $term)
                ->orWhere('u.id', ctype_digit($term) ? (int) $term : -1)
                ->groupEnd();
        }

        $rows = $builder->get()->getResultArray();

        return array_map(static function (array $row): array {
            $fullName = trim(((string) ($row['first_name'] ?? '')) . ' ' . ((string) ($row['last_name'] ?? '')));
            if ($fullName === '') {
                $fullName = (string) ($row['username'] ?? 'Member');
            }

            return [
                'id' => (int) $row['id'],
                'full_name' => $fullName,
                'email' => (string) ($row['email'] ?? ''),
                'username' => (string) ($row['username'] ?? ''),
            ];
        }, $rows);
    }

    private function toBool($value, bool $default = false): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'on', 'yes'], true);
    }

    private function affiliationHasMemberTag(): bool
    {
        try {
            $db = db_connect();
            $columns = $db->getFieldNames($db->getPrefix() . 'parish_affiliation');
            if ($columns === []) {
                $columns = $db->getFieldNames('parish_affiliation');
            }
        } catch (\Throwable $e) {
            $columns = [];
        }

        return in_array('is_member', array_map('strtolower', $columns), true);
    }

    private function resolveExistingProfileTable($db): ?string
    {
        $prefix = (string) $db->getPrefix();
        $candidates = [];
        if ($prefix !== '') {
            $candidates[] = $prefix . 'profile';
        }
        $candidates[] = 'profile';

        foreach ($candidates as $table) {
            try {
                $db->getFieldNames($table);
                return $table;
            } catch (\Throwable $e) {
                // Try next candidate.
            }
        }

        return null;
    }

    private function jsonSuccess(string $message, array $payload = [], int $status = 200)
    {
        return $this->response->setStatusCode($status)->setJSON(array_merge([
            'success' => true,
            'message' => $message,
            'csrf' => csrf_hash(),
            'csrf_name' => csrf_token(),
        ], $payload));
    }

    private function jsonError(string $message, int $status = 400)
    {
        return $this->response->setStatusCode($status)->setJSON([
            'success' => false,
            'message' => $message,
            'csrf' => csrf_hash(),
            'csrf_name' => csrf_token(),
        ]);
    }

    private function dbAttendanceRecordForParish(int $attendanceId, int $parishId): ?array
    {
        $row = db_connect()->table('attendance a')
            ->select('a.id, a.service_id, a.user_id')
            ->join('service s', 's.id = a.service_id', 'inner')
            ->where('a.id', $attendanceId)
            ->where('s.parish_id', $parishId)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }
}
