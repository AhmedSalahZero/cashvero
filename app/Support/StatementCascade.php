<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Serializes statement balance cascades under row locks.
 *
 * Statement end_balance is recalculated by MySQL triggers when updated_at is
 * touched in date order. Without a transaction + FOR UPDATE, concurrent
 * edits on the same account race and corrupt running balances.
 */
class StatementCascade
{
    /**
     * Lock matching rows, then touch updated_at in order so triggers recalculate.
     */
    public static function touchRows(Builder $baseQuery, string $orderBy): void
    {
        DB::transaction(function () use ($baseQuery, $orderBy) {
            $table = $baseQuery->from;

            $ids = (clone $baseQuery)
                ->orderByRaw($orderBy)
                ->lockForUpdate()
                ->pluck('id');

            $now = now();
            foreach ($ids as $id) {
                DB::table($table)->where('id', $id)->update([
                    'updated_at' => $now,
                ]);
            }
        });
    }
}
