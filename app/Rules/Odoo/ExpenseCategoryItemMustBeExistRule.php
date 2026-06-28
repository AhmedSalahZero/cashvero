<?php

namespace App\Rules\Odoo;

use App\Models\Company;
use App\Services\Api\OdooService;
use Illuminate\Contracts\Validation\ImplicitRule;

class ExpenseCategoryItemMustBeExistRule implements ImplicitRule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
	protected array $cashExpenseCategoryNames ;
	protected OdooService $odoo_service;
	protected Company $company; 
	protected string $fail_message ; 
    public function __construct(OdooService $odooService ,array $cashExpenseCategoryNames , Company $company)
    {
        $this->cashExpenseCategoryNames = $cashExpenseCategoryNames;
		$this->odoo_service = $odooService ;
		$this->company = $company ;
		$this->fail_message = __('Error');
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
		$failedCodes = [];
		
        foreach($this->cashExpenseCategoryNames as $cashExpenseCategoryName){
			$code = $cashExpenseCategoryName['odoo_chart_of_account_number'];
			$odooExpense = $this->odoo_service->syncChartOfAccountNumbers($code,$this->company->id) ;
			if(!$odooExpense){
				$failedCodes[]=$code;
			}
		}
		if(count($failedCodes)){
			$this->fail_message = __('Expense Chart Of Account Number is not right [ ' . implode(' , ',$failedCodes) . ' ] Please write a valid number');
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
        return $this->fail_message;
    }
}
