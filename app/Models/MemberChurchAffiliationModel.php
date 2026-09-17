<?php

namespace App\Models;

/**
 * Legacy alias kept for compatibility with older references that still
 * resolve MemberChurchAffiliationModel (e.g. model('MemberChurchAffiliationModel')).
 *
 * The canonical implementation is MemberParishAffiliationModel.
 */
class MemberChurchAffiliationModel extends MemberParishAffiliationModel
{
}
