<?php

namespace App\Traits\Models;

use App\Helpers\HDate;
use App\Support\StatementCascade;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Same-day ordering for statements whose running balance is chained by (full_date, id):
 * letters of guarantee / credit (+ their cash cover), loans, medium term loans, leasing,
 * employee / tax / shareholder / subsidiary / other-partner statements.
 *
 * Inside ONE date (and ONE chain) the rows are ordered by:
 *   1) the transaction date (the "date" column - taken from the form)
 *   2) the real creation moment (created_at), NOT the clock time of day of the transaction
 *
 * A new row is therefore placed AFTER every row already saved for that date.
 * Rows listed in sameDayFirstRowTypes() (opening-type rows) always stay FIRST of their date.
 *
 * The model must define:  public static function sameDayGroupColumns(): array
 * (the columns that identify one balance chain, exactly as its cascade filters them)
 */
trait HasSameDayAppendOrder
{
    /** @return string[] columns that identify ONE chain (besides date) */
    abstract public static function sameDayGroupColumns(): array;

    /** @return string[] `type` values that must stay first of their date */
    public static function sameDayFirstRowTypes(): array
    {
        return [];
    }

    public static function sameDayRankOf(object $row): int
    {
        return in_array($row->type ?? null, static::sameDayFirstRowTypes(), true) ? 0 : 1;
    }

    /** @return array<string,mixed> chain column => value for this row */
    public static function sameDayGroupValuesOf(object $row): array
    {
        $values = [];
        foreach (static::sameDayGroupColumns() as $column) {
            $values[$column] = $row->{$column} ?? null;
        }

        return $values;
    }

    private static function sameDayChainQuery(array $groupValues, ?string $date = null)
    {
        $query = DB::table((new static)->getTable());
        foreach ($groupValues as $column => $value) {
            $query->where($column, $value); // null => "is null"
        }
        if ($date !== null) {
            $query->where('date', $date);
        }

        return $query;
    }

    /**
     * full_date for a NEW row.
     */
    public static function nextFullDateAppendOrder($model): string
    {
        $date = Carbon::make($model->date)->format('Y-m-d');
        $groupValues = static::sameDayGroupValuesOf($model);

        if (static::sameDayRankOf($model) === 0) {
            $alreadyFirst = static::sameDayChainQuery($groupValues, $date)
                ->whereIn('type', static::sameDayFirstRowTypes())->count();

            return Carbon::make($date)->startOfDay()->addSeconds($alreadyFirst)->format('Y-m-d H:i:s');
        }

        $proposed = date('Y-m-d H:i:s', strtotime($date.' '.now()->format('H:i:s')));
        $latest = static::sameDayChainQuery($groupValues, $date)->max('full_date');
        if ($latest && $proposed <= $latest) {
            $proposed = Carbon::make($latest)->addSecond()->format('Y-m-d H:i:s');
        }
        if (substr($proposed, 0, 10) !== $date) { // never spill into the next day
            $proposed = $date.' 23:59:59';
        }

        $conditions = [];
        foreach ($groupValues as $column => $value) {
            $conditions[] = [$column, '=', $value];
        }

        return HDate::generateUniqueDateTimeForModel(static::class, 'full_date', $proposed, $conditions);
    }

    /**
     * Desired order of ONE day: first-rows, then creation moment, then id.
     */
    public static function sortRowsOfOneDayAppendOrder($rows)
    {
        return $rows->sortBy(fn ($row) => [
            static::sameDayRankOf($row),
            (string) ($row->created_at ?: $row->full_date),
            (int) $row->id,
        ])->values();
    }

    /**
     * Re-stamps full_date of ONE chain + ONE date when its order is not the desired one.
     *
     * @return string|null earliest full_date touched (old or new), null when nothing changed
     */
    public static function resequenceOneDayAppendOrder(array $groupValues, string $date): ?string
    {
        $table = (new static)->getTable();
        $date = Carbon::make($date)->format('Y-m-d');
        $rows = static::sameDayChainQuery($groupValues, $date)->get();
        if ($rows->count() < 2) {
            return null;
        }
        $desired = static::sortRowsOfOneDayAppendOrder($rows);
        $current = $rows->sortBy(fn ($r) => [(string) $r->full_date, (int) $r->id])->values();
        if ($desired->pluck('id')->map(fn ($i) => (int) $i)->all() === $current->pluck('id')->map(fn ($i) => (int) $i)->all()) {
            return null;
        }
        /**
         * If this is the FIRST day of the chain, the stored beginning_balance of its first row is the
         * real starting balance. Keep it and give it to the new first row before balances are recalculated.
         */
        $isFirstDayOfChain = ! static::sameDayChainQuery($groupValues)->where('date', '<', $date)->exists();
        $anchorBalance = $isFirstDayOfChain ? $current->first()->beginning_balance : null;
        $start = Carbon::make($date)->startOfDay();
        $first = 0;
        $others = 0;
        $minTouched = null;
        foreach ($desired as $row) {
            $newFullDate = static::sameDayRankOf($row) === 0
                ? $start->copy()->addSeconds($first++)
                : $start->copy()->addSeconds(60 + $others++);
            $newFullDate = $newFullDate->format('Y-m-d H:i:s');
            if ((string) $row->full_date !== $newFullDate) {
                DB::table($table)->where('id', $row->id)->update(['full_date' => $newFullDate]);
                $candidate = min((string) $row->full_date, $newFullDate);
                $minTouched = $minTouched === null ? $candidate : min($minTouched, $candidate);
            }
        }
        if ($isFirstDayOfChain && (int) $desired->first()->id !== (int) $current->first()->id) {
            DB::table($table)->where('id', $desired->first()->id)->update(['beginning_balance' => $anchorBalance]);
        }

        return $minTouched;
    }

    /**
     * Recalculates the balances of one chain from a full_date onward.
     */
    public static function cascadeFromAppendOrder(array $groupValues, string $minFullDate): void
    {
        StatementCascade::touchRows(
            static::sameDayChainQuery($groupValues)->where('full_date', '>=', $minFullDate),
            'full_date asc , id asc'
        );
    }

    /**
     * Date edit: the row keeps its ORIGINAL creation moment and is placed among the rows of the
     * new date by it. Same signature / meaning as IsBankStatement::handleFullDateAfterDateEdit().
     */
    public function handleFullDateAfterDateEditAppendOrder(string $date, $debit, $credit, $additionUpdateData = []): void
    {
        $date = Carbon::make($date)->format('Y-m-d');
        $oldFullDate = (string) $this->full_date;
        $time = Carbon::make($oldFullDate)->format('H:i:s');
        $updatedData = array_merge([
            'date' => $date,
            'full_date' => date('Y-m-d H:i:s', strtotime("$date $time")),
            'credit' => $credit,
            'debit' => $debit,
        ], $additionUpdateData);

        DB::transaction(function () use ($updatedData, $date, $oldFullDate) {
            $this->update($updatedData);
            $groupValues = static::sameDayGroupValuesOf($this);
            $minTouched = static::resequenceOneDayAppendOrder($groupValues, $date);
            $from = min($oldFullDate, (string) $this->full_date);
            if ($minTouched) {
                $from = min($from, $minTouched);
            }
            static::cascadeFromAppendOrder($groupValues, $from);
        });
    }
}
