<?php

namespace App\Rules;

use App\Http\Controllers\MoneyReceivedController;
use App\Models\Company;
use App\Models\MoneyReceived;
use Illuminate\Contracts\Validation\ImplicitRule;

class MoneyReceivedCanBackToUnderCollectionRule implements ImplicitRule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
	protected Company $company ;
	protected MoneyReceived $moneyReceived;
    public function __construct(Company $company,MoneyReceived $moneyReceived)
    {
        $this->company = $company ; 
		$this->moneyReceived = $moneyReceived;
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
        $response = (new MoneyReceivedController)->updateNetBalanceBasedOnAccountNumber(Request(),$this->company,$this->moneyReceived->getAccountTypeId(),$this->moneyReceived->getAccountNumber(),$this->moneyReceived->getFinancialInstitutionId());
		$balance = $response->getData(true)['balance'] ;
		$moneyReceived = Request()->route('moneyReceived');
		$receivedAmount = $moneyReceived->getAmount();
		
		if($balance - $receivedAmount < 0){
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
		return __('No Enough Balance Available');
    }
}
