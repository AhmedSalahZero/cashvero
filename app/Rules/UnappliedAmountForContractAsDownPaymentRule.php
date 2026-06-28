<?php

namespace App\Rules;

use App\Helpers\HArr;
use Illuminate\Contracts\Validation\ImplicitRule;

class UnappliedAmountForContractAsDownPaymentRule implements ImplicitRule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
	protected $total_unapplied_amount = 0;
	protected $is_down_payment ;
	protected $paid_amount ;
	protected $failed_message ;
    public function __construct($totalUnappliedAmount = null , $isDownPayment = false  , $paidAmount = 0 )
    {
        $this->total_unapplied_amount = $totalUnappliedAmount ;
		$this->is_down_payment = $isDownPayment ;		
		$this->paid_amount = $paidAmount ;		
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
		if(is_null($value)){
			return true;
		}
		$isMoneyReceivedForm = Request()->has('received_amount');
		$receivedAmountOrPaidAmountKeyName = $isMoneyReceivedForm ? 'received_amount' : 'paid_amount';
		$receivingOrPaymentCurrencyName = $isMoneyReceivedForm ? 'receiving_currency':'payment_currency';
		$totalPaidAmountForContract = HArr::sumFormattedArr(array_column($value,$receivedAmountOrPaidAmountKeyName));
		if($this->is_down_payment){
			$this->failed_message  = __('Total Paid Amount Must Equal To Total Down Payment');
			return $this->paid_amount == $totalPaidAmountForContract;
		}
		if($this->total_unapplied_amount <= 0 ){
			return true ;
		}
		$currency = Request()->get('currency') ;
		$receivingOrPaymentCurrency = Request()->get($receivingOrPaymentCurrencyName);
		$moneyType = Request()->get('type');
		$exchangeRate = $currency == $receivingOrPaymentCurrency ? 1 : number_unformat(Request()->input('exchange_rate.'.$moneyType,1)) ;
		$this->failed_message = __('Total Paid Amount For Contract Not Equal To Unapplied Amount');
		$diff = $totalPaidAmountForContract - ($this->total_unapplied_amount*$exchangeRate);
		return $diff >= -1 && $diff<=1;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->failed_message;
    }
}
