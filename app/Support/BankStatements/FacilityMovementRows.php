<?php

namespace App\Support\BankStatements;

/**
 * The one definition of "money has actually moved on this facility".
 *
 * A facility's statement holds three kinds of row, and only one of them
 * is a transaction:
 *
 *   active-limit         a zero-amount marker every facility gets.
 *   outstanding_balance  the amount already drawn when the facility was
 *                        first entered into CashVero — see
 *                        HasOutstandingBreakdown, whose own comment
 *                        calls it "الفلوس اللي انت سحبتها من الحساب لحد
 *                        لحظه فتح حسابك علي كاش فيرو". It is the
 *                        facility's opening position, typed in during
 *                        setup, not a movement recorded here.
 *   end_of_month         interest rows the system generates itself. The
 *                        database trigger then fills them in from the
 *                        balance, so they are DERIVED from the opening
 *                        position — the user never entered one.
 *
 * ⚠️ REAL BUG FIXED HERE: hasAnyTransactions() only excluded rows whose
 * debit and credit were both zero. That was enough while the generated
 * interest rows stayed empty, but the trigger fills them the moment the
 * facility carries a balance — so a facility with nothing but an
 * imported opening balance and the interest computed from it reported
 * "still has transactions" and could never be deleted. Three facilities
 * on record were stuck exactly that way, with zero real movement
 * between them.
 *
 * The bank-account side already draws the line here: a beginning
 * balance alone is not movement (ReferencedRecordGuard). This is the
 * same rule for the facility side.
 *
 * @see \App\Support\Deletion\ReferencedRecordGuard
 */
class FacilityMovementRows
{
    /**
     * The opening position, not a movement.
     */
    public const OUTSTANDING_BALANCE_TYPE = 'outstanding_balance';

    /**
     * Narrows a facility's statements query to rows where money
     * actually moved.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function onlyRealMovementIn($query)
    {
        return $query
            ->where(function ($amount) {
                $amount->where('debit', '>', 0)->orWhere('credit', '>', 0);
            })
            ->where(function ($notSetup) {
                $notSetup->whereNull('type')
                    ->orWhere('type', '!=', self::OUTSTANDING_BALANCE_TYPE);
            })
            /*
             * whereNull first: `interest_type NOT IN (...)` is NULL for
             * a NULL column, and MySQL drops the row — which would hide
             * every ordinary transaction, since those carry no
             * interest_type at all.
             */
            ->where(function ($notGenerated) {
                $notGenerated->whereNull('interest_type')
                    ->orWhereNotIn('interest_type', GeneratedMonthEndInterestRows::INTEREST_TYPES);
            });
    }
}
