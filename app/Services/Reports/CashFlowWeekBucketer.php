<?php

namespace App\Services\Reports;

/**
 * Maps a calendar date to a cash-flow report week/month/day column key.
 */
final class CashFlowWeekBucketer
{
    /**
     * @param  array<string, array{start_date: string, end_date: string}>  $periodsByWeekKey
     */
    public static function resolveWeekKey(string $date, array $periodsByWeekKey): ?string
    {
        foreach ($periodsByWeekKey as $weekKey => $period) {
            if ($date >= $period['start_date'] && $date <= $period['end_date']) {
                return $weekKey;
            }
        }

        return null;
    }
}
