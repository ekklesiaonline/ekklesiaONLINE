<?php

namespace App\Services;

class MembershipStatusService
{
    private static array $legacyAffiliationAliases = [
        'guest' => 'visitor',
        'attendee' => 'regular_attender',
        'baptized_member' => 'member',
        'communicant' => 'member',
        'leader' => 'member',
        // Retired profile.membership_status vocabulary — folded in here so
        // imports/dumps carrying the old values still normalize correctly.
        // ('pending' was never a parish lifecycle state; it mapped to visitor.)
        'active' => 'member',
        'non_active' => 'inactive',
        'pending' => 'visitor',
    ];

    private static array $affiliationStatuses = [
        'visitor' => [
            'order' => 1,
            'labelKey' => 'Membership.statusVisitor',
            'badge' => 'info',
            'isMember' => false,
        ],
        'regular_attender' => [
            'order' => 2,
            'labelKey' => 'Membership.statusRegularAttender',
            'badge' => 'primary',
            'isMember' => false,
        ],
        'member' => [
            'order' => 3,
            'labelKey' => 'Membership.statusMember',
            'badge' => 'success',
            'isMember' => true,
        ],
        'inactive' => [
            'order' => 4,
            'labelKey' => 'Membership.statusInactive',
            'badge' => 'secondary',
            'isMember' => false,
        ],
        'transferred' => [
            'order' => 5,
            'labelKey' => 'Membership.statusTransferred',
            'badge' => 'secondary',
            'isMember' => false,
        ],
        'deceased' => [
            'order' => 6,
            'labelKey' => 'Membership.statusDeceased',
            'badge' => 'dark',
            'isMember' => false,
        ],
    ];

    public static function affiliationStatuses(): array
    {
        return array_keys(self::$affiliationStatuses);
    }

    public static function orderedPathway(): array
    {
        $statuses = self::$affiliationStatuses;
        uksort($statuses, static fn(string $a, string $b): int => $statuses[$a]['order'] <=> $statuses[$b]['order']);
        return array_keys($statuses);
    }

    public static function isMember(string $status): bool
    {
        $normalized = self::normalizeAffiliationStatus($status);
        return (bool) (self::$affiliationStatuses[$normalized]['isMember'] ?? false);
    }

    /**
     * Whether the status keeps the person on the parish register.
     *
     * Canonically, inactive members remain on the register until they
     * transfer or die — the parish_affiliation.is_member flag tracks
     * register membership, distinct from the "active member" check in
     * isMember().
     */
    public static function isOnRegister(string $status): bool
    {
        return in_array(self::normalizeAffiliationStatus($status), ['member', 'inactive'], true);
    }

    /**
     * Normalized lifecycle status for parish_affiliation write paths — the
     * single place a raw status string becomes canonical. Write paths
     * should merge this array rather than setting membership_status by
     * hand.
     *
     * is_member is a generated column (isOnRegister(status));
     * canonical_status and affiliation_type were dropped — none may be
     * written.
     *
     * @return array{membership_status: string}
     */
    public static function fieldsForStatus(string $status): array
    {
        $normalized = self::normalizeAffiliationStatus($status);
        if (! in_array($normalized, self::affiliationStatuses(), true)) {
            $normalized = 'visitor';
        }

        return [
            'membership_status' => $normalized,
        ];
    }

    public static function canBeGoodStanding(string $status): bool
    {
        $normalized = self::normalizeAffiliationStatus($status);
        return $normalized === 'member';
    }

    public static function label(string $status): string
    {
        $normalized = self::normalizeAffiliationStatus($status);

        $key = self::$affiliationStatuses[$normalized]['labelKey']
            ?? 'Membership.status' . str_replace(' ', '', ucwords(str_replace('_', ' ', $normalized)));

        $translated = lang($key);
        if ($translated !== $key) {
            return $translated;
        }

        return ucwords(str_replace('_', ' ', $status));
    }

    public static function badgeColor(string $status): string
    {
        $normalized = self::normalizeAffiliationStatus($status);

        return self::$affiliationStatuses[$normalized]['badge']
            ?? 'secondary';
    }

    public static function definition(string $status): array
    {
        $normalized = self::normalizeAffiliationStatus($status);

        return self::$affiliationStatuses[$normalized]
            ?? [
                'labelKey' => 'Membership.status' . str_replace(' ', '', ucwords(str_replace('_', ' ', $normalized))),
                'badge' => 'secondary',
                'isMember' => false,
            ];
    }

    public static function normalizeAffiliationStatus(string $status): string
    {
        $normalized = strtolower(trim($status));

        if (isset(self::$legacyAffiliationAliases[$normalized])) {
            return self::$legacyAffiliationAliases[$normalized];
        }

        return $normalized;
    }
}
