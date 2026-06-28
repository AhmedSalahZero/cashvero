<?php

namespace App\Rules;

use App\Http\Controllers\MoneyPaymentController;
use App\Http\Controllers\MoneyReceivedController;
use App\Models\Company;
use App\Models\MoneyPayment;
use Illuminate\Contracts\Validation\ImplicitRule;

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
