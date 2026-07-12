<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\ImplicitRule;

class TotalBreakdownMustBeHundredRule implements ImplicitRule
{
	protected string $errorMessage;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
	protected $formKeyName;
    public function __construct(string $formKeyName)
    {
        $this->formKeyName = $formKeyName;
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
       $study = Request()->route('study') ;
		$items = Request($this->formKeyName);
		$totalPerYears = [];
		foreach($items as $item){
			foreach($item['percentage_payload']??[] as $yearAsIndex => $percentage){
				$totalPerYears[$yearAsIndex] = isset($totalPerYears[$yearAsIndex])? $totalPerYears[$yearAsIndex] + $percentage : $percentage;
			}
		}
		foreach($totalPerYears as $yearAsIndex => $total){
			$yearAsString = $study->getYearFromYearIndex($yearAsIndex);
			if($total != 100){
				$this->errorMessage= __('Total For Year ' . $yearAsString . ' Must Be Hundred The Current Value Is ' . $total  );
				return false ;
			}
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
        return $this->errorMessage;
    }
}
