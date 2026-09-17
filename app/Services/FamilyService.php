<?php

namespace App\Services;

use App\Models\FamilyMemberModel;
use App\Models\FamilyModel;

/**
 * FamilyService
 *
 * Domain logic for family management: validation rules, parish-scoped
 * lookups, membership checks, and the transactional create-family-
 * with-head operation. HTTP concerns stay in the controller.
 */
class FamilyService
{
    private FamilyModel $families;
    private FamilyMemberModel $members;

    public function __construct(?FamilyModel $families = null, ?FamilyMemberModel $members = null)
    {
        $this->families = $families ?? model(FamilyModel::class);
        $this->members = $members ?? model(FamilyMemberModel::class);
    }

    /**
     * @return array<string, string>
     */
    public function familyNameRules(): array
    {
        return [
            'family_name' => 'required|min_length[3]|max_length[255]',
        ];
    }

    /**
     * Family scoped to a parish (tenant isolation).
     *
     * @return array<string, mixed>|null
     */
    public function findFamilyInParish(int $familyId, int $parishId): ?array
    {
        $family = $this->families->find($familyId);

        if (! is_array($family) || (int) ($family['parish_id'] ?? 0) !== $parishId) {
            return null;
        }

        return $family;
    }

    /**
     * The family_membership row for a user, if any.
     *
     * @return array<string, mixed>|null
     */
    public function findRelationForUser(int $userId): ?array
    {
        $relation = $this->members->where('user_id', $userId)->first();

        return is_array($relation) ? $relation : null;
    }

    public function normalizeRelationship(string $relationship): ?string
    {
        return $this->members->normalizeRelationship($relationship);
    }

    public function relationExists(int $familyId, int $userId): bool
    {
        return $this->members->relationExists($familyId, $userId);
    }

    public function userHasAnotherFamily(int $userId, ?int $excludeFamilyId = null): bool
    {
        return $this->members->userHasAnotherFamily($userId, $excludeFamilyId);
    }

    /**
     * Whether a user may belong to only one family at a time.
     */
    public function enforceSingleFamilyMembership(): bool
    {
        $value = strtolower(trim((string) (env('family.enforceSingleFamilyMembership') ?? '0')));

        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Create a family and add the given user as Head in one transaction.
     * Returns the new family ID or null on failure.
     */
    public function createFamilyWithHead(int $parishId, string $familyName, int $userId, string $address = ''): ?int
    {
        $db = db_connect();
        $db->transBegin();

        try {
            $familyId = (int) $this->families->insert([
                'parish_id' => $parishId,
                'family_name' => $familyName,
                'head_of_household_id' => $userId,
                'address' => $address,
            ], true);

            if ($familyId <= 0) {
                throw new \RuntimeException('Failed to create family');
            }

            $relationId = (int) $this->members->insert([
                'family_id' => $familyId,
                'user_id' => $userId,
                'relationship' => 'Head',
            ], true);

            if ($relationId <= 0 || $db->transStatus() === false) {
                throw new \RuntimeException('Failed to add head to family');
            }

            $db->transCommit();

            return $familyId;
        } catch (\Throwable) {
            $db->transRollback();

            return null;
        }
    }

    /**
     * Add a member to a family. Returns the relation ID or null.
     */
    public function addMember(int $familyId, int $userId, ?string $relationship): ?int
    {
        $relationId = (int) $this->members->insert([
            'family_id' => $familyId,
            'user_id' => $userId,
            'relationship' => $relationship,
        ], true);

        return $relationId > 0 ? $relationId : null;
    }

    public function removeMember(int $relationId): bool
    {
        return (bool) $this->members->delete($relationId);
    }

    public function updateRelationship(int $relationId, string $relationship): bool
    {
        return (bool) $this->members->update($relationId, ['relationship' => $relationship]);
    }
}
