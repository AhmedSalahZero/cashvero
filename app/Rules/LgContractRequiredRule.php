<?php

namespace App\Rules;

use App\Support\LetterOfGuarantee\LgContractRequirement;
use Illuminate\Contracts\Validation\ImplicitRule;

/**
 * The LG issuance's contract, required or not depending on the LG type
 * and the beneficiary — see LgContractRequirement for the rule itself.
 *
 * ⚠️ ImplicitRule, and that is the whole point of it being a class.
 * Laravel skips a non-implicit rule when the value is an empty string,
 * so the closure this replaces never ran for `contract_id=''` — the
 * requirement simply did not apply. It only ever fired because the
 * form happens to post the literal string "null" for an empty select
 * (HasBasicStoreRequest::storeBasicForm), which is not empty as far as
 * the validator is concerned. Anything posting a genuinely blank value
 * — a different form, an API client, a changed select — walked
 * straight through.
 */
class LgContractRequiredRule implements ImplicitRule
{
    public function __construct(
        private ?string $lgType,
        private ?int $partnerId,
    ) {}

    /**
     * @param  string  $attribute
     * @param  mixed  $value
     */
    public function passes($attribute, $value): bool
    {
        if (! LgContractRequirement::isRequired($this->lgType, $this->partnerId)) {
            return true;
        }

        // The form's empty select posts the string "null".
        return ! (blank($value) || $value === 'null');
    }

    public function message(): string
    {
        return __('Contract is required for this LG type.');
    }
}
