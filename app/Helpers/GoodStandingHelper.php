<?php

namespace App\Helpers;

use App\Models\MemberStandingSummaryModel;

class GoodStandingHelper
{
    public static function getCurrentStandingCounts(?int $year = null): array
    {
        /** @var MemberStandingSummaryModel $model */
        $model = model(MemberStandingSummaryModel::class);

        return $model->getCurrentStandingCounts($year);
    }
}
