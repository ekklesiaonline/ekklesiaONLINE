<?php

namespace App\Services;

use App\Models\PersonModel;
use App\Models\PersonVisitModel;

class VisitorIntakeService
{
    /**
     * Record a visitor/visitor intake as a person_visit.
     *
     * Deduplicates by email or phone hash within the parish. If no person is
     * found, provisions a full person/parish_affiliation chain first.
     *
     * @return array{person_id: int, person_visit_id: int, is_new: bool}
     */
    public static function record(array $data, int $parishId): array
    {
        if ($parishId <= 0) {
            throw new \InvalidArgumentException('A valid parish_id is required.');
        }

        $email      = isset($data['email']) ? trim((string) $data['email']) : '';
        $phone      = isset($data['phone']) ? trim((string) $data['phone']) : '';
        $emailHash  = PersonContactService::emailIndex($email);
        $phoneHash  = PersonContactService::phoneIndex($phone);

        $personId   = self::findExistingPerson($parishId, $emailHash, $phoneHash);
        $isNew      = $personId === null;

        $db = db_connect();
        $db->transBegin();

        try {
            if ($isNew) {
                $provisioned = PersonProvisioningService::provision([
                    'parish_id'                => $parishId,
                    'first_name'               => $data['first_name'] ?? '',
                    'last_name'                => $data['last_name'] ?? '',
                    'email'                    => $email,
                    'phone'                    => $phone,
                    'address'                  => $data['address'] ?? null,
                    'preferred_contact_method' => $data['preferred_contact_method'] ?? 'email',
                    'source'                   => $data['source'] ?? 'visitor_intake',
                    'membership_status'        => 'visitor',
                    'notes'                    => $data['notes'] ?? null,
                ]);

                $personId = $provisioned['person_id'];
            }

            $visitType = self::resolveVisitType($personId, $parishId);

            $personVisitId = model(PersonVisitModel::class)->insert([
                'person_id'      => $personId,
                'parish_id'      => $parishId,
                'visit_date'     => $data['visit_date'] ?? date('Y-m-d'),
                'visit_type'     => $visitType,
                'service_id'     => $data['service_id'] ?? null,
                'attended_event' => $data['service_attended'] ?? ($data['attended_event'] ?? null),
                'gift_given'     => (int) ($data['gift_given'] ?? 0),
                'notes'          => $data['notes'] ?? null,
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s'),
            ], true);

            if (! is_int($personVisitId) || $personVisitId <= 0) {
                throw new \RuntimeException('Failed to record person_visit.');
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }

        return [
            'person_id'       => $personId,
            'person_visit_id' => $personVisitId,
            'is_new'          => $isNew,
        ];
    }

    private static function findExistingPerson(int $parishId, ?string $emailHash, ?string $phoneHash): ?int
    {
        if ($emailHash === null && $phoneHash === null) {
            return null;
        }

        $builder = model(PersonModel::class)->builder('person')
            ->join('parish_affiliation', 'parish_affiliation.person_id = person.id')
            ->where('parish_affiliation.parish_id', $parishId);

        $builder->groupStart();
        if ($emailHash !== null) {
            $builder->where('person.email_hash', $emailHash);
        }
        if ($emailHash !== null && $phoneHash !== null) {
            $builder->orWhere('person.phone_hash', $phoneHash);
        } elseif ($phoneHash !== null) {
            $builder->where('person.phone_hash', $phoneHash);
        }
        $builder->groupEnd();

        $row = $builder->get()->getRowArray();

        return $row ? (int) $row['person_id'] : null;
    }

    private static function resolveVisitType(int $personId, int $parishId): string
    {
        $count = model(PersonVisitModel::class)
            ->where('person_id', $personId)
            ->where('parish_id', $parishId)
            ->countAllResults();

        return $count > 0 ? 'return' : 'first';
    }
}
