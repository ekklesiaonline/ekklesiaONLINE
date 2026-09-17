<?php

namespace App\Services;

use App\Models\SacramentalParticipantModel;
use App\Models\SacramentalRecordModel;

/**
 * SacramentalRecordService
 *
 * Domain logic for sacramental records and their participants:
 * validation rules, parish-scoped lookups, duplicate-participant
 * checks, persistence, and derived-flag synchronization.
 * sacramental_record is the canonical source of truth for
 * baptism/confirmation; HTTP concerns stay in the controller.
 */
class SacramentalRecordService
{
    private SacramentalRecordModel $records;
    private SacramentalParticipantModel $participants;
    private SacramentFlagSyncService $flagSync;

    public function __construct(
        ?SacramentalRecordModel $records = null,
        ?SacramentalParticipantModel $participants = null,
        ?SacramentFlagSyncService $flagSync = null
    ) {
        $this->records = $records ?? model(SacramentalRecordModel::class);
        $this->participants = $participants ?? model(SacramentalParticipantModel::class);
        $this->flagSync = $flagSync ?? new SacramentFlagSyncService();
    }

    /**
     * @return array<string, string>
     */
    public function recordValidationRules(): array
    {
        return [
            'sacrament_type'      => 'required|max_length[50]',
            'sacrament_date'      => 'permit_empty|valid_date',
            'register_ref'        => 'permit_empty|max_length[100]',
            'certificate_number'  => 'permit_empty|max_length[100]',
            'officiant_person_id' => 'permit_empty|is_not_unique[person.id]',
            'location_notes'      => 'permit_empty|max_length[255]',
            'notes'               => 'permit_empty|max_length[1000]',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function participantCreateRules(): array
    {
        return [
            'person_id'        => 'required|is_not_unique[person.id]',
            'participant_role' => 'required|max_length[50]',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function participantUpdateRules(): array
    {
        return [
            'participant_role' => 'required|max_length[50]',
        ];
    }

    /**
     * Sacramental record scoped to a parish (tenant isolation).
     *
     * @return array<string, mixed>|null
     */
    public function findRecordInParish(int $recordId, int $parishId): ?array
    {
        $record = $this->records->getRecordForParish($recordId, $parishId);

        return is_array($record) ? $record : null;
    }

    /**
     * Participant joined to its record, scoped to a parish.
     *
     * @return array<string, mixed>|null
     */
    public function findParticipantInParish(int $participantId, int $parishId): ?array
    {
        $participant = $this->participants->getParticipantWithRecord($participantId);

        if (! is_array($participant) || (int) ($participant['parish_id'] ?? 0) !== $parishId) {
            return null;
        }

        return $participant;
    }

    /**
     * Insert a record. Returns the new ID or null on failure
     * (call recordErrors() for details).
     */
    public function createRecord(array $payload): ?int
    {
        if ($this->records->insert($payload) === false) {
            return null;
        }

        return (int) $this->records->getInsertID();
    }

    public function updateRecord(int $recordId, array $payload): bool
    {
        return (bool) $this->records->update($recordId, $payload);
    }

    public function deleteRecord(int $recordId): bool
    {
        return (bool) $this->records->delete($recordId);
    }

    /**
     * Whether a (record, person, role) participant row already exists.
     */
    public function participantExists(int $recordId, int $personId, string $role, ?int $excludeId = null): bool
    {
        $builder = $this->participants
            ->where('sacramental_record_id', $recordId)
            ->where('person_id', $personId)
            ->where('participant_role', $role);

        if ($excludeId !== null) {
            $builder->where('id !=', $excludeId);
        }

        return $builder->first() !== null;
    }

    /**
     * Insert a participant. Returns the new ID or null on failure
     * (call participantErrors() for details).
     */
    public function createParticipant(array $payload): ?int
    {
        if ($this->participants->insert($payload) === false) {
            return null;
        }

        return (int) $this->participants->getInsertID();
    }

    public function updateParticipantRole(int $participantId, string $role): bool
    {
        return (bool) $this->participants->update($participantId, ['participant_role' => $role]);
    }

    public function deleteParticipant(int $participantId): bool
    {
        return (bool) $this->participants->delete($participantId);
    }

    /**
     * Recompute derived baptism/confirmation flags and profile dates
     * from the canonical sacramental_record rows for a person.
     */
    public function syncFlags(int $personId, int $parishId, ?int $actorId, string $reason): void
    {
        if ($personId <= 0) {
            return;
        }

        $this->flagSync->applyForPerson($personId, $parishId, $actorId, $reason);
    }

    /**
     * @return array<string, string>
     */
    public function recordErrors(): array
    {
        return $this->records->errors() ?: [];
    }

    /**
     * @return array<string, string>
     */
    public function participantErrors(): array
    {
        return $this->participants->errors() ?: [];
    }
}
