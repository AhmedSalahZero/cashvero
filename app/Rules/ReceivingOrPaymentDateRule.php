<?php

namespace App\Rules;

use App\Models\FinancialInstitution;
use App\Models\OpeningBalance;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Rule;

class ReceivingOrPaymentDateRule implements Rule
{
	protected int $company_id ;
	protected ?string $money_type ;
	protected ?int $financial_institution_id ;
	protected ?int $account_type_id ;
	protected ?string $account_number ;
	protected string $failed_message ;
	protected array $money_type_validation_for_bank_types;
	protected array $money_type_validation_for_safe_types;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct(int $companyId , ?string $moneyType , array $moneyTypeValidationForBankTypes , array $moneyTypeValidationForSafe, ?int  $financialInstitutionId , ?int $accountTypeId , ?string $accountNumber )
    {
		$this->company_id = $companyId;
        $this->money_type = $moneyType ;
		$this->money_type_validation_for_bank_types = $moneyTypeValidationForBankTypes ; 
		$this->money_type_validation_for_safe_types = $moneyTypeValidationForSafe ; 
		$this->financial_institution_id = $financialInstitutionId ;
		$this->account_type_id = $accountTypeId;
		$this->account_number = $accountNumber ;
		$this->failed_message = __('Transaction Date Must Be Greater Than Or Equal Account Opening Balance Date');
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
		
        if(in_array($this->money_type ,$this->money_type_validation_for_bank_types)){
			if(!$this->account_type_id || ! $this->account_number){
				return true ;
			}
			$financialInstitution = FinancialInstitution::find($this->financial_institution_id);
			$openingBalanceDate = $financialInstitution->getOpeningBalanceForAccount($this->account_type_id,$this->account_number);
			return Carbon::make($value)->greaterThanOrEqualTo(Carbon::make($openingBalanceDate));
		}
		if(in_array($this->money_type,$this->money_type_validation_for_safe_types)){
			$this->failed_message = __('Transaction Date Must Be Greater Than Or Equal Safe Opening Balance Date');
			$openingBalance = OpeningBalance::where('company_id',$this->company_id)->first();
			if(!$openingBalance){
				return true ;
			}
			$openingBalanceDate = $openingBalance->getDate();
			return Carbon::make($value)->greaterThanOrEqualTo(Carbon::make($openingBalanceDate));
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
        return $this->failed_message;
    }
}
