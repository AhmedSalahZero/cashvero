<?php

namespace App\Rules;

use App\Http\Controllers\MoneyPaymentController;
use App\Http\Controllers\MoneyReceivedController;
use App\Models\Company;
use App\Models\MoneyPayment;
use Illuminate\Contracts\Validation\ImplicitRule;

/**
 * CONFIRMED NOT WIRED IN — DO NOT RE-ENABLE AS WRITTEN (audit finding, 2026-07-24).
 *
 * Business rule (confirmed with project owner): deleting a Money Payment is always safe and
 * needs no balance-sufficiency check — reversing a payment only ever adds money back to a bank
 * account/safe, or frees up room on a facility (never the other direction), so it can never
 * cause a shortfall or breach a limit. This matches `DeleteMoneyPaymentRequest`, which
 * deliberately leaves the wiring line for this rule commented out.
 *
 * The logic below is also confirmed BROKEN, independent of the above: it checks
 * `$balance - $paidAmount < 0` using the account's CURRENT balance — but deleting a payment adds
 * the amount back, it doesn't subtract it, so this checks the wrong direction entirely. It
 * appears to have been copy-pasted from `MoneyReceivedCanBeDeletedRule`'s (also-disabled) logic
 * without flipping the sign for a payment. If this class is ever revisited, it should be
 * deleted outright rather than reactivated as-is.
 *
 * Separately (Stage 1 audit finding #2.2): the logic above also instantiates full HTTP
 * Controllers directly to reuse a balance-lookup method, instead of a shared service/model
 * method — same architecture concern as `AmountCanNotBeGreaterThanEndBalanceAtPaymentDate` and
 * `MoneyReceivedCanBackToUnderCollectionRule`. Moot for this specific class since it's confirmed
 * unused, but noted here in case the class is ever repurposed rather than deleted.
 */
class MoneyPaymentCanBeDeletedRule implements ImplicitRule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
	protected MoneyPayment $moneyPayment ;
	protected Company $company ; 
    public function __construct(MoneyPayment $moneyPayment , Company $company)
    {
        $this->moneyPayment = $moneyPayment;
		$this->company = $company;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
		$balance = null ;
		$paidAmount = $this->moneyPayment->getPaidAmount();
		if($this->moneyPayment->isOutgoingTransfer() || $this->moneyPayment->isPayableCheque()){
			$response = (new MoneyReceivedController)->updateNetBalanceBasedOnAccountNumber(Request(),$this->company,$this->moneyPayment->getAccountTypeId(),$this->moneyPayment->getAccountNumber(),$this->moneyPayment->getFinancialInstitutionId());
			$balance = $response->getData(true)['balance'] ;
		}
		if($this->moneyPayment->isCashPayment()){
			// code here
			$response = (new MoneyPaymentController)->getCashInSafeStatementEndBalance(Request(),$this->company,$this->moneyPayment->getCashPaymentBranchId(),$this->moneyPayment->getPaymentCurrency());
			$balance = $response->getData(true)['end_balance'];
			
		}
		if($balance - $paidAmount < 0 ){
			return false ;
		}
		return true ;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('This Money Payment Can Not Be Deleted .. There Is No Enough Balance');
    }
}
