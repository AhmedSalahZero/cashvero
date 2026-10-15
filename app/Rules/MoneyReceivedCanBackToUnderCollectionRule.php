<?php

namespace App\Rules;

use App\Http\Controllers\MoneyReceivedController;
use App\Models\Company;
use App\Models\MoneyReceived;
use Illuminate\Contracts\Validation\ImplicitRule;

/**
 * ARCHITECTURE NOTE (Stage 1 audit finding, 2026-07-24, not fixed here): this Rule instantiates
 * a full HTTP Controller (`MoneyReceivedController`) directly to reuse one of its methods,
 * instead of calling a shared service/model method. This only works because the current global
 * `Request()` happens to carry the route bindings that controller method reads internally —
 * reusing this Rule from any other context (an Artisan command, a queued job, a future API
 * endpoint, a test) would fail in a confusing way rather than a clean no-op. Left as-is for now
 * (fixing it means touching the Controller too, out of scope for a Rules-only pass) — tracked
 * for whenever Controllers get their own dedicated audit stage.
 */
class MoneyReceivedCanBackToUnderCollectionRule implements ImplicitRule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
	protected Company $company ;
	protected MoneyReceived $moneyReceived;
    public function __construct(Company $company,MoneyReceived $moneyReceived)
    {
        $this->company = $company ; 
		$this->moneyReceived = $moneyReceived;
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
        $response = (new MoneyReceivedController)->updateNetBalanceBasedOnAccountNumber(Request(),$this->company,$this->moneyReceived->getAccountTypeId(),$this->moneyReceived->getAccountNumber(),$this->moneyReceived->getFinancialInstitutionId());
		$balance = $response->getData(true)['balance'] ;
		$moneyReceived = Request()->route('moneyReceived');
		$receivedAmount = $moneyReceived->getAmount();
		
		if($balance - $receivedAmount < 0){
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
		return __('No Enough Balance Available');
    }
}
