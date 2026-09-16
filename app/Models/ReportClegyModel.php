<?php

namespace App\Models;

/**
 * Compatibility alias for the corrected ReportClergyModel.
 *
 * The original file was named ReportClegyModel.php (typo: "Clegy"
 * instead of "Clergy"). This class extends the correctly-named model
 * so any code referencing the old name continues to work.
 *
 * @deprecated Use ReportClergyModel instead.
 */
class ReportClegyModel extends ReportClergyModel
{
}
