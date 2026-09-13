<?php

namespace App\Models;

class EventModel extends BaseTenantModel
{
    protected $table            = 'event';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        // Organizer abstraction (new source of truth)
        'organizer_type',
        'organizer_church_id',
        'organizer_province_id',
        'organizer_diocese_id',
        'organizer_parish_id',
        'host_parish_id',
        // Legacy dual-write columns (to be dropped after full cut-over)
        'parish_id',
        'scope_level',
        'scope_diocese_id',
        'scope_province_id',
        'calendar_id',
        'title',
        'description',
        'cover_path',
        'event_type',
        'visibility',
        'audience_team_id',
        'audience_family_id',
        'schedule_mode',
        'is_all_day',
        'location',
        'start_at',
        'end_at',
        'capacity',
        'requires_registration',
        'status',
        'publish_at',
        'recurrence_pattern',
        'recurrence_days_json',
        'recurrence_until',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'organizer_type' => 'permit_empty|in_list[national,province,diocese,parish]',
        'parish_id' => 'permit_empty|is_not_unique[parish.id]',
        'calendar_id' => 'permit_empty|is_not_unique[event_calendar.id]',
        'title' => 'required|min_length[3]|max_length[255]',
        'event_type' => 'permit_empty|max_length[100]',
        'start_at' => 'required',
        'status' => 'required|in_list[draft,published,cancelled,completed]',
    ];

    protected $beforeInsert = ['syncOrganizerColumns'];
    protected $beforeUpdate = ['syncOrganizerColumns'];

    /**
     * Keep legacy columns in sync while the dual-write phase is active.
     * Also infers organizer values from legacy scope_level / parish_id for code
     * that has not been updated yet.
     */
    protected function syncOrganizerColumns(array $data): array
    {
        $row = &$data['data'];

        $hasOrganizerInfo = !empty($row['organizer_type'])
            || !empty($row['scope_level'])
            || !empty($row['organizer_church_id'])
            || !empty($row['organizer_province_id'])
            || !empty($row['organizer_diocese_id'])
            || !empty($row['organizer_parish_id'])
            || !empty($row['host_parish_id'])
            || array_key_exists('parish_id', $row);

        if (!$hasOrganizerInfo) {
            return $data;
        }

        if (empty($row['organizer_type'])) {
            $scopeLevel = $row['scope_level'] ?? 'parish';
            $row['organizer_type'] = in_array($scopeLevel, ['national','province','diocese','parish'], true)
                ? $scopeLevel
                : 'parish';
        }

        $type = $row['organizer_type'];

        if ($type === 'national') {
            if (empty($row['organizer_church_id'])) {
                $row['organizer_church_id'] = $row['organizer_church_id'] ?? 1;
            }
            $row['organizer_province_id'] = null;
            $row['organizer_diocese_id']  = null;
            $row['organizer_parish_id']   = null;
        } elseif ($type === 'province') {
            if (empty($row['organizer_province_id']) && !empty($row['scope_province_id'])) {
                $row['organizer_province_id'] = $row['scope_province_id'];
            }
            $row['organizer_church_id']  = null;
            $row['organizer_diocese_id'] = null;
            $row['organizer_parish_id']  = null;
        } elseif ($type === 'diocese') {
            if (empty($row['organizer_diocese_id']) && !empty($row['scope_diocese_id'])) {
                $row['organizer_diocese_id'] = $row['scope_diocese_id'];
            }
            $row['organizer_church_id']   = null;
            $row['organizer_province_id'] = null;
            $row['organizer_parish_id']   = null;
        } else { // parish
            if (empty($row['organizer_parish_id']) && !empty($row['parish_id'])) {
                $row['organizer_parish_id'] = $row['parish_id'];
            }
            $row['organizer_church_id']   = null;
            $row['organizer_province_id'] = null;
            $row['organizer_diocese_id']  = null;
        }

        if (!array_key_exists('host_parish_id', $row) || $row['host_parish_id'] === '') {
            $row['host_parish_id'] = !empty($row['parish_id']) ? $row['parish_id'] : null;
        }

        $row['scope_level']       = $type;
        $row['scope_province_id'] = $type === 'province' ? ($row['organizer_province_id'] ?? null) : null;
        $row['scope_diocese_id']  = $type === 'diocese'  ? ($row['organizer_diocese_id']  ?? null) : null;
        $row['parish_id']         = $type === 'parish'    ? ($row['organizer_parish_id']   ?? null)
                                                           : ($row['host_parish_id']       ?? null);

        return $data;
    }

    /**
     * Validate that the supplied IDs match the selected organizer_type.
     * Controllers should call this before saving.
     */
    public function validateOrganizerIds(array $data): bool
    {
        $type = $data['organizer_type'] ?? '';

        return match ($type) {
            'national' => !empty($data['organizer_church_id']),
            'province' => !empty($data['organizer_province_id']),
            'diocese'  => !empty($data['organizer_diocese_id']),
            'parish'   => !empty($data['organizer_parish_id']),
            default    => false,
        };
    }

    private function getCheckinCountSelect(): string
    {
        try {
            if ($this->db->fieldExists('status', 'event_registration')) {
                return 'SUM(CASE WHEN er.status = "checked_in" THEN 1 ELSE 0 END) as checkin_count';
            }
        } catch (\Throwable $e) {
            // Table/column may not exist yet.
        }

        return '0 as checkin_count';
    }

    public function getParishEventsWithCounts(int $parishId, int $dioceseId = 0, ?string $status = null, ?string $search = null): array
    {
        $builder = $this->db->table('event e')
            ->select('e.*, ec.name as calendar_name, ec.color_hex as calendar_color, COUNT(er.id) as registration_count, ' . $this->getCheckinCountSelect())
            ->join('event_calendar ec', 'ec.id = e.calendar_id', 'left')
            ->join('event_registration er', 'er.event_id = e.id', 'left')
            ->groupStart()
            ->where('e.organizer_type', 'parish')
            ->where('e.organizer_parish_id', $parishId);

        if ($dioceseId > 0) {
            $builder->orGroupStart()
                ->where('e.organizer_type', 'diocese')
                ->where('e.organizer_diocese_id', $dioceseId)
                ->groupEnd();
        }

        $builder->groupEnd()
            ->where('e.deleted_at', null)
            ->groupBy('e.id')
            ->orderBy('e.start_at', 'DESC');

        if ($status !== null && $status !== '') {
            $builder->where('e.status', $status);
        }

        if ($search !== null && trim($search) !== '') {
            $builder->groupStart()
                ->like('e.title', $search)
                ->orLike('e.description', $search)
                ->orLike('e.location', $search)
                ->orLike('e.event_type', $search)
                ->groupEnd();
        }

        return $builder->get()->getResultArray();
    }

    public function getEventWithCounts(int $eventId): ?array
    {
        $parishId = $this->currentParishId();

        $row = $this->db->table('event e')
            ->select('e.*, ec.name as calendar_name, ec.color_hex as calendar_color, COUNT(er.id) as registration_count, ' . $this->getCheckinCountSelect())
            ->join('event_calendar ec', 'ec.id = e.calendar_id', 'left')
            ->join('event_registration er', 'er.event_id = e.id', 'left')
            ->where('e.id', $eventId)
            ->where('e.parish_id', $parishId)
            ->where('e.deleted_at', null)
            ->groupBy('e.id')
            ->get()
            ->getRowArray();

        return is_array($row) ? $row : null;
    }

    public function getVisibleEventsForRange(int $parishId, int $dioceseId, string $rangeStart, string $rangeEnd): array
    {
        $builder = $this->db->table('event e')
            ->select('e.*, ec.name as calendar_name, ec.color_hex as calendar_color')
            ->join('event_calendar ec', 'ec.id = e.calendar_id', 'left')
            ->groupStart()
            ->where('e.organizer_type', 'parish')
            ->where('e.organizer_parish_id', $parishId);

        if ($dioceseId > 0) {
            $builder->orGroupStart()
                ->where('e.organizer_type', 'diocese')
                ->where('e.organizer_diocese_id', $dioceseId)
                ->groupEnd();
        }

        return $builder
            ->groupEnd()
            ->groupStart()
            ->groupStart()
            ->where('e.start_at <=', $rangeEnd)
            ->where('COALESCE(e.end_at, e.start_at) >=', $rangeStart)
            ->groupEnd()
            ->orGroupStart()
            ->where('e.recurrence_pattern !=', 'none')
            ->groupStart()
            ->where('e.recurrence_until IS NULL', null, false)
            ->orWhere('e.recurrence_until >=', substr($rangeStart, 0, 10))
            ->groupEnd()
            ->groupEnd()
            ->groupEnd()
            ->orderBy('e.start_at', 'ASC')
            ->orderBy('e.id', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function getNationalEventsWithCounts(int $churchId, ?string $status = null, ?string $search = null): array
    {
        $builder = $this->db->table('event e')
            ->select('e.*, ec.name as calendar_name, ec.color_hex as calendar_color, COUNT(er.id) as registration_count, ' . $this->getCheckinCountSelect())
            ->join('event_calendar ec', 'ec.id = e.calendar_id', 'left')
            ->join('event_registration er', 'er.event_id = e.id', 'left')
            ->where('e.organizer_type', 'national')
            ->where('e.organizer_church_id', $churchId)
            ->groupBy('e.id')
            ->orderBy('e.start_at', 'DESC');

        if ($status !== null && $status !== '') {
            $builder->where('e.status', $status);
        }

        if ($search !== null && trim($search) !== '') {
            $builder->groupStart()
                ->like('e.title', $search)
                ->orLike('e.description', $search)
                ->orLike('e.location', $search)
                ->orLike('e.event_type', $search)
                ->groupEnd();
        }

        return $builder->get()->getResultArray();
    }

    public function getNationalEventsForRange(int $churchId, string $rangeStart, string $rangeEnd): array
    {
        return $this->db->table('event e')
            ->select('e.*, ec.name as calendar_name, ec.color_hex as calendar_color')
            ->join('event_calendar ec', 'ec.id = e.calendar_id', 'left')
            ->where('e.organizer_type', 'national')
            ->where('e.organizer_church_id', $churchId)
            ->groupStart()
            ->groupStart()
            ->where('e.start_at <=', $rangeEnd)
            ->where('COALESCE(e.end_at, e.start_at) >=', $rangeStart)
            ->groupEnd()
            ->orGroupStart()
            ->where('e.recurrence_pattern !=', 'none')
            ->groupStart()
            ->where('e.recurrence_until IS NULL', null, false)
            ->orWhere('e.recurrence_until >=', substr($rangeStart, 0, 10))
            ->groupEnd()
            ->groupEnd()
            ->groupEnd()
            ->orderBy('e.start_at', 'ASC')
            ->orderBy('e.id', 'ASC')
            ->get()
            ->getResultArray();
    }
}
