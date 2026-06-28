<?php

namespace App\Rules;

use App\Http\Controllers\MoneyPaymentController;
use App\Http\Controllers\MoneyReceivedController;
use App\Models\Company;
use App\Models\MoneyReceived;
use Illuminate\Contracts\Validation\ImplicitRule;

class MoneyReceivedCanBeDeletedRule implements ImplicitRule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
	protected MoneyReceived $moneyReceived ;
	protected Company $company ; 
    public function __construct(MoneyReceived $moneyReceived , Company $company)
    {
        $this->moneyReceived = $moneyReceived;
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
		return true ;
		// $balance = null ;
		// $receivedAmount = $this->moneyReceived->getReceivedAmount();
		// if($this->moneyReceived->isChequeInSafe()){
		// 	return true ;
		// }
		// if($this->moneyReceived->isIncomingTransfer() || $this->moneyReceived->isCheque() || $this->moneyReceived->isCashInBank() ){
		// 	$response = (new MoneyReceivedController)->updateNetBalanceBasedOnAccountNumber(Request(),$this->company,$this->moneyReceived->getAccountTypeId(),$this->moneyReceived->getAccountNumber(),$this->moneyReceived->getFinancialInstitutionId());
		// 	$balance = $response->getData(true)['balance'] ;
		// }
		// if($this->moneyReceived->isCashInSafe()){
		// 	$response = (new MoneyPaymentController)->getCashInSafeStatementEndBalance(Request(),$this->company,$this->moneyReceived->getCashInSafeReceivingBranchId(),$this->moneyReceived->getReceivingCurrency());
		// 	$balance = $response->getData(true)['end_balance'];
			
		// }
		// if($balance - $receivedAmount < 0 ){
		// 	return false ;
		// }
		// return true ;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('This Money Received Can Not Be Deleted .. There Is No Enough Balance');
    }
}
