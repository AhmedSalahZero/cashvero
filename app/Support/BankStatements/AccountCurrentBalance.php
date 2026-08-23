<?php

namespace App\Support\BankStatements;

use App\Models\CurrentAccountBankStatement;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * A current account's balance AS OF a date — by default, today.
 *
 * ⚠️ REAL BUG FIXED HERE: the LG and LC issuance forms both showed the
 * end_balance of the account's LAST statement row by date, with no
 * upper bound. Every account carries generated month-end interest rows
 * running to the end of the year the system has synced — often years
 * ahead — so "the last row" is a FUTURE row, and the figure offered as
 * the account's available cash cover was the balance at that future
 * date.
 *
 * On live data one account read −1,946,026.24, taken from a row dated
 * 2028-12-31, while its actual balance today was +1,880,259.76. Wrong
 * by 3.8 million, and the wrong sign.
 *
 * Bounding the lookup at the as-of date is the whole fix: the running
 * balance is already correct on every row, it was simply being read
 * from the wrong one.
 */
class AccountCurrentBalance
{
    /**
     * account id => balance on that date.
     *
     * @param  iterable<int>  $accountIds
     * @return Collection<int, float>
     */
    public static function forAccounts(iterable $accountIds, ?string $asOf = null): Collection
    {
        $accountIds = collect($accountIds)->map(fn ($id) => (int) $id)->all();

        if ($accountIds === []) {
            return collect();
        }

        $asOf = $asOf ?: Carbon::today()->format('Y-m-d');

        return CurrentAccountBankStatement::query()
            ->whereIn('financial_institution_account_id', $accountIds)
            ->whereDate('date', '<=', $asOf)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get()
            ->groupBy('financial_institution_account_id')
            ->map(fn ($rows) => (float) $rows->first()->getEndBalance());
    }
}
