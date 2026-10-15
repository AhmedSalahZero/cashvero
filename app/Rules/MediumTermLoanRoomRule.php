<?php

namespace App\Rules;

use App\Http\Controllers\MoneyReceivedController;
use App\Models\AccountType;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ImplicitRule;

/**
 * "You can never draw more out of the loan than the loan itself."
 *
 * AmountCanNotBeGreaterThanEndBalanceAtPaymentDate already enforces this
 * for Outgoing Transfer (and, since AccountType::isRoomBasedAccount()
 * now covers the MTL, it reads the loan's room rather than its
 * end_balance). It does NOT cover Payable Cheque — deliberately, for
 * ordinary bank accounts. But a cheque drawn on a loan still consumes
 * the loan, so this rule closes that gap for the MTL specifically and
 * leaves every other account type untouched.
 *
 * Reuses updateNetBalanceBasedOnAccountNumber() so edit mode gets the
 * same compensation every other balance check gets: the payment's own
 * already-posted amount is added back before comparing, otherwise
 * re-saving an unchanged payment would fail against the room it itself
 * consumed. (StoreMoneyPaymentRequest::prepareForValidation() is what
 * puts modelId/modelType on the request.)
 *
 * @see \App\Rules\AmountCanNotBeGreaterThanEndBalanceAtPaymentDate for the
 *      same controller-instantiation caveat noted there.
 */
class MediumTermLoanRoomRule implements ImplicitRule
{
    public function __construct(
        protected $company,
        protected $paidAmount,
        protected $accountTypeId,
        protected ?string $accountNumber,
        protected $financialInstitutionId,
        protected ?string $date
    ) {
    }

    public function passes($attribute, $value): bool
    {
        if (! $this->accountTypeId || ! $this->accountNumber || ! $this->financialInstitutionId || ! $this->date) {
            return true;
        }

        $accountType = AccountType::find($this->accountTypeId);
        if (! $accountType || ! $accountType->isMediumTermLoanAccount()) {
            return true;
        }

        $response = (new MoneyReceivedController)->updateNetBalanceBasedOnAccountNumber(
            Request(),
            $this->company,
            $this->accountTypeId,
            $this->accountNumber,
            $this->financialInstitutionId,
            Carbon::make($this->date)->format('Y-m-d')
        );

        $room = (float) ($response->getData(true)['balance'] ?? 0);

        return $room >= (float) number_unformat($this->paidAmount);
    }

    public function message(): string
    {
        return __('This exceeds what is left of the loan. Reduce the amount or pick another account.');
    }
}
