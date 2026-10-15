<?php

namespace App\Rules;

use App\Models\LeasingContract;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Rule;

/**
 * A "Through Leasing" payment can never exceed what is still available
 * on the contract at the payment date.
 *
 * The leasing sibling of MediumTermLoanRoomRule, with two deliberate
 * differences:
 *
 *   - It reads the room off the contract itself rather than through
 *     the account-balance endpoint. That endpoint resolves a bank +
 *     account type + account number, and this money type has none of
 *     the three.
 *
 *   - The room is read AT THE PAYMENT DATE, not as of today, so a
 *     backdated payment is checked against what was actually available
 *     back then — the same reason MediumTermLoanRoomRule passes its
 *     date through.
 *
 * Inert whenever the form is only half filled in (no contract picked
 * yet, no date yet): a half-chosen form is a normal state on a screen
 * that validates as you type, not an error.
 */
class LeasingContractRoomRule implements Rule
{
    public function __construct(
        protected int $companyId,
        protected mixed $paidAmount,
        protected ?int $leasingCompanyId,
        protected ?string $date,
    ) {
    }

    public function passes($attribute, $value): bool
    {
        if (! $value || ! $this->leasingCompanyId || ! $this->date) {
            return true;
        }

        $contract = LeasingContract::where('id', $value)
            ->where('company_id', $this->companyId)
            ->where('leasing_company_id', $this->leasingCompanyId)
            ->first();

        /**
         * Ownership is enforced by its own `exists` rule on the same
         * field, which produces a clearer message than "not enough
         * room" would. Nothing to check here if the contract is not
         * this company's.
         */
        if (! $contract) {
            return true;
        }

        $room = $contract->getAvailableRoomAt(Carbon::make($this->date)->format('Y-m-d'));

        return $room >= (float) number_unformat($this->paidAmount);
    }

    public function message(): string
    {
        return __('The Paid Amount Exceeds The Remaining Amount Of This Leasing Contract');
    }
}
