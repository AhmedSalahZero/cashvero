<?php

namespace App\Rules;

use App\Models\Company;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Arr;

class UniqueToCompanyAndAdditionalColumnsRule implements Rule
{
    private $modelName , $columnName  , $exceptId ,  $failMessage , $additionalConditions ; 
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct(string $modelName , string $columnName , ?int $exceptId , $additionalConditions = [],?string $failMessage = null )
    {
        $this->modelName =$modelName ;
        $this->additionalConditions =$additionalConditions ;
        $this->columnName =$columnName;
        $this->exceptId = $exceptId ;
        $this->failMessage = $failMessage ;
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
        $value = is_array($value) ? Arr::flatten($value) : $value ; 

		$company = currentCompany();
        $q =   (\getModelNamespace().$this->modelName)::where('id','!=',$this->exceptId)->where('company_id',$company->id)
        // (array) $value to include array values like service item and also includes single item like revenue business line
        ->whereIn($this->columnName, (array)$value)
		;
		foreach($this->additionalConditions as $additionalColumn){
			$q->where($additionalColumn[0],$additionalColumn[1],$additionalColumn[2]);
		}
        return ! $q->exists();

    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->failMessage ?: __('This Record Already Exist') ;
    }
}
