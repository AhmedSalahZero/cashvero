<?php

namespace App\Rules\Odoo;

use App\Models\Company;
use App\Services\Api\OdooService;
use Illuminate\Contracts\Validation\ImplicitRule;

class ChartOfAccountMustBeExistRule implements ImplicitRule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */

	protected OdooService $odoo_service;
	protected Company $company; 
	protected string $fail_message ; 
    public function __construct(OdooService $odooService , Company $company)
    {
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
		

			$code = $value;
			$chartOfAccount = $this->odoo_service->chartOfAccount($code) ;
			if(is_null($chartOfAccount)){
				$this->fail_message = __('Chart Of Account Number is not right [ ' . $code . ' ] Please write a valid number');
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
