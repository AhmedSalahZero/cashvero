<?php

namespace App\Rules;

use App\Models\Company;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Arr;

class UniqueToCompanyRule implements Rule
{
    private $modelName , $columnName  , $exceptId ,  $failMessage ,$namespace; 
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct(string $modelName , string $columnName, ?int $exceptId, ?string $failMessage = null , ?string $namespace = null )
    {
        $this->modelName =$modelName ;
        $this->columnName =$columnName;
        $this->exceptId = $exceptId ;
        $this->failMessage = $failMessage ;
		$this->namespace = $namespace ;
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
		$namespace = is_null($this->namespace) ? \getModelNamespace() : $this->namespace ;
		$company = currentCompany();
        return ! ($namespace.$this->modelName)::where('id','!=',$this->exceptId)->where('company_id',$company->id)
        ->whereIn($this->columnName, (array)$value)
        ->exists();

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
