<?php

namespace App\Rules;

use App\Models\AccountType;
use App\Models\MediumTermLoan;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ImplicitRule;

/**
 * "Once the first installment is due, the loan is treated as already
 * consumed — it can no longer pay suppliers, only be repaid."
 *
 * A NEW (not-yet-consumed) MTL is usable as a Money Payment source only
 * up to the day BEFORE its first_installment_date. From that date
 * onward the transaction's Payment Date (Outgoing Transfer / Cash) or
 * Due Date (Payable Cheque) is no longer accepted for this account,
 * even if there is still room left on the loan — reaching the first
 * installment date means the company is assumed to have already drawn
 * down what it needed.
 *
 * Deliberately a SEPARATE rule from MediumTermLoanRoomRule rather than
 * folded into it: this is a date check independent of amount/room, and
 * keeping them separate keeps each rule's failure message specific to
 * what actually went wrong.
 */
class MediumTermLoanFirstInstallmentDateRule implements ImplicitRule
{
    public function __construct(
        protected $company,
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

        $mediumTermLoan = MediumTermLoan::findByAccountNumber($this->accountNumber, $this->company->id, $this->financialInstitutionId);
        if (! $mediumTermLoan || ! $mediumTermLoan->isNotConsumedYet()) {
            return true;
        }

        $firstInstallmentDate = $mediumTermLoan->first_installment_date;
        if (! $firstInstallmentDate) {
            return true;
        }

        return Carbon::make($this->date)->lt(Carbon::make($firstInstallmentDate));
    }

    public function message(): string
    {
        return __('This loan\'s first installment is already due, so it can no longer be used to pay suppliers.');
    }
}
