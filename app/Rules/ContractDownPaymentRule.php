<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\ImplicitRule;

class ContractDownPaymentRule implements ImplicitRule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
	
	protected $received_or_paid_amount;
	protected $isSo;
    public function __construct($totalReceivedAmount,$isSo)
    {
       $this->received_or_paid_amount = $totalReceivedAmount;
	   $this->isSo = $isSo;
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
		$isContractDownPayment = Request()->get('down_payment_type') === 'over_contract';
		if(!$isContractDownPayment){
			return true ;
		}
		$breakDownAmountsColumnName = $this->isSo ? 'sales_orders_amounts' : 'purchases_orders_amounts';
		$totalAmountColumnName = $this->isSo ? 'received_amount' : 'paid_amount';
		$totalBreakdown = array_sum(array_column(Request()->get($breakDownAmountsColumnName,[]),$totalAmountColumnName));
		$moneyType = Request()->get('type');
		$amountInInvoiceCurrency  = Request()->input('amount_in_invoice_currency.'.$moneyType);
		return $totalBreakdown == $amountInInvoiceCurrency;
		// return $totalBreakdown == $this->received_or_paid_amount;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
		if($this->isSo){
			return __('Total amounts assigned to SOs must be equal down payment amount');
		}
        return __('Total amounts assigned to POs must be equal down payment amount');
    }
}
