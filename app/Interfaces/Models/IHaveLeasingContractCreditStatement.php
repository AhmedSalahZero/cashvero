<?php

namespace App\Interfaces\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Marks a model that can draw money out of a Leasing Contract.
 *
 * Deliberately a SEPARATE interface from IHaveCreditOverdraftStatement
 * and from IHaveMediumTermLoanCreditStatement: the "Through Leasing"
 * money type exists on the Money Payment screen ONLY, so only
 * MoneyPayment implements this. Widening an existing interface would
 * have forced CashExpense (their other implementor) to grow a relation
 * it has no screen for.
 */
interface IHaveLeasingContractCreditStatement
{
    public function leasingContractCreditBankStatement(): HasOne;
}
