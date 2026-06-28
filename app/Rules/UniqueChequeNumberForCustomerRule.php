<?php

namespace App\Rules;


use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

class UniqueChequeNumberForCustomerRule implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
	protected $id ;
	protected $drawee_bank_id ;
	protected $failedMessage ;
    public function __construct(?int $draweeBankId  , $excludeId = null , $failedMessage = null)
    {

        $this->drawee_bank_id = $draweeBankId ;
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
		if(!$this->drawee_bank_id){
			return false ;
		}
        return !DB::table('cheques')->where('company_id',getCurrentCompanyId())
		->where('drawee_bank_id',$this->drawee_bank_id)
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
