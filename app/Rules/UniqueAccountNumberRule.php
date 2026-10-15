<?php

namespace App\Rules;

use App\Helpers\HArr;
use App\Models\FinancialInstitution;
use Illuminate\Contracts\Validation\Rule;

class UniqueAccountNumberRule implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
	public $excludeAccountNumbers = [];
    public function __construct(array $excludeAccountNumbers = [])
    {
        $this->excludeAccountNumbers = $excludeAccountNumbers;
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
		$financialInstitution = Request()->route('financialInstitution') ;
        $financialInstitution = $financialInstitution ?: FinancialInstitution::find(Request()->segment(4));
		/**
		 * @var FinancialInstitution $financialInstitution
		 */
		$allAccountNumbers = $financialInstitution->getAllAccountNumbers() ;
		if(count($this->excludeAccountNumbers)){
			$allAccountNumbers = HArr::removeKeyFromArrayByValue($allAccountNumbers,$this->excludeAccountNumbers);
		}
		return ! in_array($value , $allAccountNumbers );
		
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('This Account Number Already Exist');
    }
}
