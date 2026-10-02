<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DeaneryModel;
use CodeIgniter\Database\BaseConnection;

/** Deaneries are optional diocesan subdivisions, never admin-rank groups. */
final class DeaneryService
{
    public function __construct(
        private ?BaseConnection $db = null,
        private ?DeaneryModel $deaneries = null,
    ) {
        $this->db ??= db_connect();
        $this->deaneries ??= model(DeaneryModel::class);
    }

    public function create(int $dioceseId, string $code, string $name): ?int
    {
        $dioceseId = max(0, $dioceseId);
        $code = trim($code);
        $name = trim($name);
        if ($dioceseId === 0 || $code === '' || $name === '') {
            return null;
        }
        if ($this->db->table('diocese')->where('id', $dioceseId)->countAllResults() === 0
            || $this->db->table('deanery')->where('diocese_id', $dioceseId)->where('code', $code)->countAllResults() > 0) {
            return null;
        }
        $id = $this->deaneries->insert(['diocese_id' => $dioceseId, 'code' => $code, 'name' => $name, 'active' => 1]);
        return $id !== false ? (int) $id : null;
    }

    /**
     * Insert one time-bounded affiliation after locking the parish row.
     * All overlaps, including future intervals, are denied. End dates inclusive.
     */
    public function assignParish(int $deaneryId, int $parishId, string $start, ?string $end, int $actorId): bool
    {
        if ($deaneryId <= 0 || $parishId <= 0 || $actorId <= 0 || ! $this->validDates($start, $end)) {
            return false;
        }
        $this->db->transBegin();
        try {
            if ($this->db->DBDriver === 'MySQLi') {
                $this->db->query('SELECT id FROM ' . $this->db->protectIdentifiers('parish') . ' WHERE id = ? FOR UPDATE', [$parishId]);
            }
            $deanery = $this->db->table('deanery')->select('diocese_id, active')
                ->where('id', $deaneryId)->get()->getRowArray();
            $parish = $this->db->table('parish')->select('diocese_id')->where('id', $parishId)->get()->getRowArray();
            if ($deanery === null || $parish === null || (int) $deanery['active'] !== 1
                || (int) $parish['diocese_id'] <= 0
                || (int) $deanery['diocese_id'] !== (int) $parish['diocese_id']) {
                $this->db->transRollback();
                return false;
            }
            $existing = $this->db->table('deanery_parish_membership')
                ->select('started_at, ended_at')->where('parish_id', $parishId)->get()->getResultArray();
            foreach ($existing as $interval) {
                $oldStart = (string) $interval['started_at'];
                $oldEnd = $interval['ended_at'] !== null ? (string) $interval['ended_at'] : null;
                if (($end === null || $oldStart <= $end) && ($oldEnd === null || $start <= $oldEnd)) {
                    $this->db->transRollback();
                    return false;
                }
            }
            $ok = $this->db->table('deanery_parish_membership')->insert([
                'deanery_id' => $deaneryId, 'parish_id' => $parishId,
                'started_at' => $start, 'ended_at' => $end,
                'appointed_by_user_id' => $actorId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            if (! $ok || $this->db->transStatus() === false) {
                $this->db->transRollback();
                return false;
            }
            $this->db->transCommit();
            return true;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return false;
        }
    }

    public function endMembership(int $membershipId, int $dioceseId, string $end): bool
    {
        if ($membershipId <= 0 || $dioceseId <= 0 || ! $this->validDates($end, null)) {
            return false;
        }
        $row = $this->db->table('deanery_parish_membership m')
            ->select('m.started_at, m.ended_at')
            ->join('deanery d', 'd.id = m.deanery_id')
            ->where('m.id', $membershipId)->where('d.diocese_id', $dioceseId)->get()->getRowArray();
        if ($row === null || $row['started_at'] > $end
            || ($row['ended_at'] !== null && $row['ended_at'] < $end)) {
            return false;
        }
        return $this->db->table('deanery_parish_membership')
            ->where('id', $membershipId)->update(['ended_at' => $end, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    private function validDates(string $start, ?string $end): bool
    {
        $parse = static function (string $value): bool {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            return $date !== false && $date->format('Y-m-d') === $value;
        };
        return $parse($start) && ($end === null || ($parse($end) && $end >= $start));
    }
}
