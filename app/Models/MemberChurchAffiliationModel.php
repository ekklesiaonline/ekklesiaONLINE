<?php

namespace App\Models;

/**
 * Legacy class alias kept for compatibility with older references.
 * Provides backward-compatible method aliases for singular/plural naming transitions.
 */
class MemberParishAffiliationModel extends MemberParishAffiliationModel
{
    /**
     * Compatibility alias for searchParishMembers.
     * Maintained for legacy test and controller references during singular/plural transition.
     */
    public function searchParishMembers(int $parishId, string $keyword, bool $membersOnly = true): array
    {
        return $this->searchParishMembers($parishId, $keyword, $membersOnly);
    }
}
