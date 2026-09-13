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

    private static array $profileStatuses = [
        'visitor' => [
            'labelKey' => 'Membership.statusVisitor',
            'badge' => 'info',
        ],
        'regular_attender' => [
            'labelKey' => 'Membership.statusRegularAttender',
            'badge' => 'primary',
        ],
        'member' => [
            'labelKey' => 'Membership.statusMember',
            'badge' => 'success',
        ],
        'non_active' => [
            'labelKey' => 'Membership.statusNonActive',
            'badge' => 'secondary',
        ],
        'inactive' => [
            'labelKey' => 'Membership.statusInactive',
            'badge' => 'secondary',
        ],
        'transferred' => [
            'labelKey' => 'Membership.statusTransferred',
            'badge' => 'secondary',
        ],
        'deceased' => [
            'labelKey' => 'Membership.statusDeceased',
            'badge' => 'dark',
        ],
    ];

    // Legacy profile statuses that are no longer in the DB enum are mapped here for
    // backward compatibility during data migrations and external imports.
    private static array $profileToAffiliation = [
        'visitor' => 'visitor',
        'regular_attender' => 'regular_attender',
        'active' => 'member',          // legacy: superseded by 'member'
        'member' => 'member',
        'inactive' => 'inactive',
        'guest' => 'visitor',          // legacy: superseded by 'visitor'
        'attendee' => 'regular_attender', // legacy: superseded by 'regular_attender'
        'non_active' => 'inactive',    // legacy: superseded by 'inactive'
    ];

    private static array $affiliationToProfile = [
        'visitor' => 'visitor',
        'regular_attender' => 'regular_attender',
        'member' => 'active',
        'guest' => 'visitor',
        'attendee' => 'regular_attender',
        'inactive' => 'inactive',
        'transferred' => 'inactive',
        'deceased' => 'inactive',
    ];

    public static function affiliationStatuses(): array
    {
        return array_keys(self::$affiliationStatuses);
    }

    public static function profileStatuses(): array
    {
        return array_keys(self::$profileStatuses);
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

    public static function canBeGoodStanding(string $status): bool
    {
        $normalized = self::normalizeAffiliationStatus($status);
        return $normalized === 'member';
    }

    public static function label(string $status): string
    {
        $normalized = self::normalizeAffiliationStatus($status);

        $key = self::$affiliationStatuses[$normalized]['labelKey']
            ?? self::$profileStatuses[$normalized]['labelKey']
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
            ?? self::$profileStatuses[$normalized]['badge']
            ?? 'secondary';
    }

    public static function profileToAffiliation(string $profileStatus): string
    {
        return self::normalizeAffiliationStatus(self::$profileToAffiliation[$profileStatus] ?? 'visitor');
    }

    public static function affiliationToProfile(string $affiliationStatus): string
    {
        $normalized = self::normalizeAffiliationStatus($affiliationStatus);
        return self::$affiliationToProfile[$normalized] ?? 'visitor';
    }

    public static function definition(string $status): array
    {
        $normalized = self::normalizeAffiliationStatus($status);

        return self::$affiliationStatuses[$normalized]
            ?? self::$profileStatuses[$normalized]
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

    /**
     * Map a normalized membership_status to the corresponding affiliation_type.
     *
     * The two columns have different vocabularies: affiliation_type is a
     * high-level bucket (guest/attendee/member/clergy/staff) while
     * membership_status tracks the lifecycle (visitor/regular_attender/member/
     * inactive/transferred/deceased).
     */
    public static function affiliationTypeForStatus(string $status): string
    {
        $normalized = self::normalizeAffiliationStatus($status);

        return match ($normalized) {
            'visitor'          => 'guest',
            'regular_attender' => 'attendee',
            'clergy'           => 'clergy',
            'staff'            => 'staff',
            default            => 'member',
        };
    }
}
