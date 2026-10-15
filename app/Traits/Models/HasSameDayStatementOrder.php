<?php

namespace App\Traits\Models;

use App\Helpers\HDate;
use App\Support\BankStatements\GeneratedMonthEndInterestRows;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Same-day ordering for the OVERDRAFT statement tables
 * (clean / fully secured / against contracts / against commercial paper / LC).
 *
 * Their balances are chained by (date, id) inside the database triggers, and the
 * statement screens sort by full_date. This trait keeps full_date in the SAME order
 * as the balance chain, WITHOUT touching any balance logic:
 *
 *   - the "active-limit" row  : first of its date (00:00:00)   - unchanged convention
 *   - normal rows             : after all the normal rows already saved for that date
 *   - end-of-month interest   : last of its date               - unchanged convention
 *
 * "Interest before principal" is a SETTLEMENT rule (priority column) that is used by the
 * settlement logic - it is not part of this ordering and is not changed.
 */
trait HasSameDayStatementOrder
{
    /**
     * full_date for a NEW overdraft statement row.
     */
    public static function nextFullDateOnTheSameDay($model, string $foreignKey): string
    {
        $table = (new static)->getTable();
        $date = Carbon::make($model->date)->format('Y-m-d');
        $isMonthEnd = in_array($model->interest_type ?? null, GeneratedMonthEndInterestRows::INTEREST_TYPES, true);

        $proposed = date('Y-m-d H:i:s', strtotime($date.' '.now()->format('H:i:s')));

        if (! $isMonthEnd) {
            $latestNormal = DB::table($table)
                ->where($foreignKey, $model->{$foreignKey})
                ->where('date', $date)
                ->where(function ($q) {
                    $q->whereNull('interest_type')
                        ->orWhereNotIn('interest_type', GeneratedMonthEndInterestRows::INTEREST_TYPES);
                })
                ->max('full_date');

            if ($latestNormal && $proposed <= $latestNormal) {
                $proposed = Carbon::make($latestNormal)->addSecond()->format('Y-m-d H:i:s');
            }
            // keep normal rows before the end-of-month rows (23:59:00)
            $cap = $date.' 23:58:59';
            if ($proposed > $cap) {
                $proposed = $cap;
            }
        }

        return HDate::generateUniqueDateTimeForModel(static::class, 'full_date', $proposed, [
            [$foreignKey, '=', $model->{$foreignKey}],
        ]);
    }

    /**
     * 0 = limit row (first), 1 = normal rows, 2 = end-of-month interest rows (last).
     */
    public static function overdraftRankOf(object $row): int
    {
        if (($row->type ?? null) === 'active-limit') {
            return 0;
        }
        if (in_array($row->interest_type ?? null, GeneratedMonthEndInterestRows::INTEREST_TYPES, true)) {
            return 2;
        }

        return 1;
    }

    /**
     * Desired order of ONE day = the order the balance chain really uses (rank, then id).
     */
    public static function sortOverdraftRowsOfOneDay($rows)
    {
        return $rows->sortBy(fn ($row) => [self::overdraftRankOf($row), (int) $row->id])->values();
    }

    /**
     * Re-stamps full_date of one facility + one date so the screen order equals the balance
     * chain order. Balances are NOT recalculated (nothing about them changes).
     *
     * @return bool true when something was changed
     */
    public static function resequenceOverdraftDay(int $foreignKeyValue, string $foreignKey, string $date): bool
    {
        $table = (new static)->getTable();
        $date = Carbon::make($date)->format('Y-m-d');
        $rows = DB::table($table)->where($foreignKey, $foreignKeyValue)->where('date', $date)
            ->get(['id', 'type', 'interest_type', 'full_date']);
        if ($rows->count() < 2) {
            return false;
        }
        $desired = self::sortOverdraftRowsOfOneDay($rows);
        $current = $rows->sortBy(fn ($r) => [(string) $r->full_date, (int) $r->id])->values();
        if ($desired->pluck('id')->map(fn ($i) => (int) $i)->all() === $current->pluck('id')->map(fn ($i) => (int) $i)->all()) {
            return false;
        }
        $start = Carbon::make($date)->startOfDay();
        $counters = [0 => 0, 1 => 0, 2 => 0];
        foreach ($desired as $row) {
            $rank = self::overdraftRankOf($row);
            $newFullDate = match ($rank) {
                0 => $start->copy()->addSeconds($counters[0]++),
                1 => $start->copy()->addSeconds(60 + $counters[1]++),       // 00:01:00 onward
                default => $start->copy()->setTime(23, 59, 0)->addSeconds($counters[2]++), // 23:59:00 onward
            };
            DB::table($table)->where('id', $row->id)->update(['full_date' => $newFullDate->format('Y-m-d H:i:s')]);
        }

        return true;
    }
}
