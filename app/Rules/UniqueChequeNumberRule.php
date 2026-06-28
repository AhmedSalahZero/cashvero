<?php

namespace App\Rules;


use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

class UniqueChequeNumberRule implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
	protected $id ;
	protected $delivery_bank_id ;
	protected $failedMessage ;
    public function __construct(int $deliveryBankId  , $excludeId = null , $failedMessage = null)
    {
        $this->delivery_bank_id = $deliveryBankId ;
        $this->id = $excludeId ;
		$this->failedMessage = $failedMessage;
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

		if($value == 0){
			$this->failedMessage = __('Invalid Cheque Number');
			return false ;
		}
        return !DB::table('payable_cheques')->where('company_id',getCurrentCompanyId())
		->where('delivery_bank_id',$this->delivery_bank_id)
		->where($attribute,'=',$value)->where('id','!=',$this->id)->exists();
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
