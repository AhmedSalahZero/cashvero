<?php

namespace App\Support\BankStatements;

/**
 * The one definition of "an untouched generated month-end interest row".
 *
 * CurrentAccountBankStatement::handleEndOfMonthInterestForCurrentAccountStatement()
 * pre-creates one empty row per month-end for a whole year. The MySQL
 * trigger (app/Triggers/Cashvero/current_account_bank_statements.sql)
 * later fills in `debit` for those rows once the account actually earns
 * interest, and the filled row can then be posted to Odoo as an Interest
 * Revenue journal entry.
 *
 * So `interest_type` alone does NOT tell you whether a row is inert:
 * at the time of writing 696 of the 709 month-end rows are empty
 * placeholders, but 13 carry a real amount and 11 of those are already
 * posted to Odoo. Only a row that is still empty AND unposted may be
 * treated as if it were not there.
 *
 * ⚠️ The `whereNotNull('interest_type')` below is load-bearing, not
 * redundant. A real transaction has `interest_type = NULL`, and
 * `NULL IN ('end_of_month', ...)` evaluates to NULL rather than FALSE.
 * Under excludeUntouchedFrom()'s `NOT (...)` that NULL stays NULL and
 * MySQL drops the row — silently hiding the 82 real zero-amount
 * transactions in the table. whereNotNull() yields FALSE instead, which
 * dominates the AND chain and keeps those rows visible.
 */
class GeneratedMonthEndInterestRows
{
    public const INTEREST_TYPES = ['end_of_month', 'end_of_month_final'];

    /**
     * Narrow a (sub)query down to untouched generated placeholders.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    public static function constrain($query): void
    {
        $query->whereNotNull('interest_type')
            ->whereIn('interest_type', self::INTEREST_TYPES)
            ->where('debit', 0)
            ->where('credit', 0)
            ->whereNull('interest_journal_entry_id')
            ->whereNull('interest_odoo_reference')
            ->whereNull('interest_account_bank_statement_odoo_id');
    }

    /**
     * Everything except untouched placeholders — i.e. rows that count as
     * real movement on the account.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    public static function excludeUntouchedFrom($query)
    {
        return $query->whereNot(fn ($sub) => self::constrain($sub));
    }

    /**
     * Only untouched placeholders — the rows that are safe to delete.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    public static function onlyUntouchedIn($query)
    {
        return $query->where(fn ($sub) => self::constrain($sub));
    }
}
