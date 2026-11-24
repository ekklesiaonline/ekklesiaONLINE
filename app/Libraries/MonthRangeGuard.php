<?php

namespace App\Libraries;

/**
 * Guards commands that replace month-keyed summaries whole: the requested
 * range must cover every touched month completely, or the rebuild would
 * overwrite a full month with totals from only part of it.
 */
final class MonthRangeGuard
{
    /**
     * Returns an error message when the range would truncate a month, or
     * null when it is safe. Dates are Y-m-d strings already validated.
     *
     * - $from must be the 1st of its month.
     * - $to must be the last day of its month, or $today (an in-progress
     *   current month is rebuilt again by the next scheduled run).
     */
    public static function check(string $from, string $to, ?string $today = null): ?string
    {
        $today ??= date('Y-m-d');

        if (substr($from, 8, 2) !== '01') {
            return '--from must be the first day of a month (e.g. ' . substr($from, 0, 8) . '01) because monthly summaries are replaced whole.';
        }

        $monthEnd = date('Y-m-t', strtotime($to));
        if ($to !== $monthEnd && $to !== $today) {
            return '--to must be the last day of a month (e.g. ' . $monthEnd . ') or today because monthly summaries are replaced whole.';
        }

        return null;
    }
}
