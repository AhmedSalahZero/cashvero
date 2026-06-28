<?php

namespace App\Rules;

use App\Models\Contract;
use Illuminate\Contracts\Validation\ImplicitRule;

class CanDeleteContractRule implements ImplicitRule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
	protected Contract $contract ;
	protected string $failedMessage ;
    public function __construct(Contract $contract)
    {
        $this->contract = $contract ;
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
	
		if($this->contract->moneyReceived->count()){
			$this->failedMessage = __('This Contract Is Related To Money Received .. You Have To Delete Or Edit It First');
			return false ;
		}
		if($this->contract->MoneyPayment->count()){
			$this->failedMessage = __('This Contract Is Related To Money Payment .. You Have To Delete Or Edit It First');
			return false ;
		}
		
		if($this->contract->letterOfGuaranteeIssuances->count()){
			$this->failedMessage = __('This Contract Is Related To Letter Of Guarantee Issuance .. You Have To Delete Or Edit It First');
			return false ;
		}
		if(count($this->contract->cashExpenses)){
			$this->failedMessage = __('This Contract Is Related To Cash Expense .. You Have To Delete Or Edit It First');
			return false ;
		}
		if($this->contract->overdraftAgainstAssignmentOfContract){
			$this->failedMessage = __('This Contract Is Related To Overdraft Against Assignment Of Contract .. You Have To Delete Or Edit It First');
			return false ;
		}
		if($this->contract->customerInvoices->count()){
			$this->failedMessage = __('This Contract Is Related To Customer Invoices .. You Have To Delete Or Edit It First');
			return false ;
		}
		if($this->contract->lendingInformationForAgainstAssignmentContract){
			$this->failedMessage = __('This Contract Is Related To Lending Information Of Against Assignment Contract .. You Have To Delete Or Edit It First');
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
        return $this->failedMessage;
    }
}
