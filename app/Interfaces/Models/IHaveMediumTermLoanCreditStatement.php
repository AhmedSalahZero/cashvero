<?php

namespace App\Interfaces\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Marks a model that can draw money out of a Medium Term Loan.
 *
 * Deliberately a SEPARATE interface from IHaveCreditOverdraftStatement
 * rather than a new method on it: per the agreed scope (2026-08-16) the
 * MTL is a payment source on the Money Payment screen ONLY, so only
 * MoneyPayment implements this. Widening the existing interface would
 * have forced CashExpense (its other implementor) to grow a relation it
 * has no screen for.
 */
interface IHaveMediumTermLoanCreditStatement
{
    public function mediumTermLoanCreditBankStatement(): HasOne;
}
