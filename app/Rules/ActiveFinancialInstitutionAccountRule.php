<?php

namespace App\Rules;

use App\Models\AccountType;
use Illuminate\Contracts\Validation\Rule;

class ActiveFinancialInstitutionAccountRule implements Rule
{
    public function __construct(
        protected int $companyId,
        protected ?int $accountTypeId,
        protected ?string $accountNumber,
        protected ?int $financialInstitutionId
    ) {
    }

    public function passes($attribute, $value): bool
    {
        if (! $this->accountTypeId || ! $this->accountNumber || ! $this->financialInstitutionId) {
            return true;
        }

        $accountType = AccountType::find($this->accountTypeId);
        if (! $accountType || ! $accountType->getModelName()) {
            return true;
        }

        $modelClass = '\App\Models\\'.$accountType->getModelName();
        $account = $modelClass::where('company_id', $this->companyId)
            ->where('financial_institution_id', $this->financialInstitutionId)
            ->where('account_number', $this->accountNumber)
            ->first();

        return $account && $account->isActive();
    }

    public function message(): string
    {
        return __('This bank account is locked and cannot be used.');
    }
}
