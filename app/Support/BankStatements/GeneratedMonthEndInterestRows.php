<?php

namespace App\Support\BankStatements;

use Illuminate\Support\Facades\Schema;

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
     * Where this started. Every caller that works on a facility's own
     * statements passes its table instead.
     */
    public const DEFAULT_TABLE = 'current_account_bank_statements';

    /**
     * The Odoo links that turn a placeholder into a posted record.
     * Only the current-account statements table carries them; the
     * overdraft statement tables have no Odoo columns at all.
     */
    private const ODOO_LINK_COLUMNS = [
        'interest_journal_entry_id',
        'interest_odoo_reference',
        'interest_account_bank_statement_odoo_id',
    ];

    /**
     * Narrow a (sub)query down to untouched generated placeholders.
     *
     * $table names the statement table being queried. It defaults to
     * the current-account statements, which is where this started, but
     * every facility keeps its own statements in its own table and the
     * same rows appear there too — see
     * FullySecuredOverdraft::handleEndOfMonthInterestForContractStatements()
     * and its three siblings.
     *
     * Guards for columns a given table does not have are skipped rather
     * than crashing: the overdraft tables have no Odoo columns, so
     * "not posted to Odoo" is trivially true for them.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    public static function constrain($query, string $table = self::DEFAULT_TABLE): void
    {
        $query->whereNotNull('interest_type')
            ->whereIn('interest_type', self::INTEREST_TYPES)
            ->where('debit', 0)
            ->where('credit', 0);

        foreach (self::ODOO_LINK_COLUMNS as $column) {
            if (Schema::hasColumn($table, $column)) {
                $query->whereNull($column);
            }
        }
    }

    /**
     * Everything except untouched placeholders — i.e. rows that count as
     * real movement on the account.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    public static function excludeUntouchedFrom($query, string $table = self::DEFAULT_TABLE)
    {
        return $query->whereNot(fn ($sub) => self::constrain($sub, $table));
    }

    /**
     * Only untouched placeholders — the rows that are safe to delete.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    public static function onlyUntouchedIn($query, string $table = self::DEFAULT_TABLE)
    {
        return $query->where(fn ($sub) => self::constrain($sub, $table));
    }
}
