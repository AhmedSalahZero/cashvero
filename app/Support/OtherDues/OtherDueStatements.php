<?php

namespace App\Support\OtherDues;

use App\Models\Company;
use App\Models\OtherDue;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Writes an Other Due into the partner ledger it belongs in, and takes it
 * back out again when the due changes or goes away.
 *
 * Only partner types that HAVE a ledger are written here. Customers and
 * suppliers keep no ledger table — their statement is derived from
 * invoices — so their dues are injected into that report at read time
 * instead (HasBalances::otherDueRowsFor). Nothing is written twice.
 */
class OtherDueStatements
{
    /**
     * The date every Other Due carries: the company's opening balance
     * date. A due is part of the opening position, not something that
     * happened on the day it was typed in.
     */
    public static function dateFor(Company $company): string
    {
        return $company->opening_balance_date
            ? Carbon::parse($company->opening_balance_date)->format('Y-m-d')
            : now()->format('Y-m-d');
    }

    /**
     * Rewrite the ledger row for one due. Removing first and re-creating
     * keeps edit and create on one path, so an edited amount can never
     * leave the old figure sitting beside the new one.
     */
    public static function sync(OtherDue $due, Company $company): void
    {
        self::remove($due);

        $statementModel = $due->statementModel();

        if (! $statementModel) {
            // Customer / supplier: nothing to write. Their statement is
            // built from invoices, and the due is added there at read time.
            return;
        }

        $amount = $due->getAmount();

        if ($amount <= 0) {
            return;
        }

        /**
         * "Due from" means the partner owes us, which increases what we
         * are owed — a debit. "Due to" is the mirror image.
         */
        $isDueFrom = $due->isDueFrom();

        $statementModel::create([
            'company_id' => $company->id,
            'partner_id' => $due->partner_id,
            'currency_name' => $due->currency,
            'other_due_id' => $due->id,
            'date' => self::dateFor($company),
            'is_debit' => $isDueFrom ? 1 : 0,
            'is_credit' => $isDueFrom ? 0 : 1,
            'debit' => $isDueFrom ? $amount : 0,
            'credit' => $isDueFrom ? 0 : $amount,
            'beginning_balance' => 0,
            'end_balance' => 0,
            'comment_en' => $due->getComment(),
            'comment_ar' => $due->getComment(),
        ]);
    }

    /**
     * Drop the ledger row a due created, wherever it went. Every ledger is
     * checked rather than only the due's current type, because the type
     * itself may have been edited since the row was written.
     */
    public static function remove(OtherDue $due): void
    {
        foreach (OtherDue::LEDGER_STATEMENTS as $statementModel) {
            foreach ($statementModel::where('other_due_id', $due->id)->get() as $row) {
                $row->delete();
            }
        }
    }

    /**
     * Every currency any due uses for this company.
     *
     * @return string[]
     */
    public static function currenciesFor(Company $company): array
    {
        return DB::table('other_dues')
            ->where('company_id', $company->id)
            ->distinct()
            ->orderBy('currency')
            ->pluck('currency')
            ->all();
    }
}
