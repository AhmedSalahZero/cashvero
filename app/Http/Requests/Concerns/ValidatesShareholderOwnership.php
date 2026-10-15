<?php

namespace App\Http\Requests\Concerns;

use App\Models\Partner;
use App\Support\ShareholderAccounts\ShareholderAccountAccess;
use Illuminate\Validation\Rule;

/**
 * The validation rules for the two ownership columns, shared by every form
 * that can flag an account as belonging to a shareholder.
 *
 * Three things are enforced:
 *   1. An account flagged as a shareholder's MUST name the shareholder.
 *   2. That shareholder must be a Partner of THIS company with
 *      is_shareholder = 1 — not an employee, not another company's partner.
 *   3. A user without shareholder_account.view cannot flag anything at all
 *      (decision D6) — the field is prohibited for them rather than merely
 *      hidden in the UI, since a hidden field is not a guarantee.
 *
 * See docs/shareholder-accounts.md.
 */
trait ValidatesShareholderOwnership
{
    /**
     * @param  string  $prefix  '' for a flat form, or 'accounts.*.' for a repeater
     * @return array<string, mixed>
     */
    protected function shareholderOwnershipRules(string $prefix = ''): array
    {
        $companyId = $this->shareholderOwnershipCompanyId();
        $canFlag = ShareholderAccountAccess::canView();

        return [
            $prefix.'is_shareholder_account' => [
                'sometimes',
                'boolean',
                Rule::prohibitedIf(! $canFlag),
            ],
            $prefix.'shareholder_partner_id' => [
                'nullable',
                'integer',
                'required_if:'.$prefix.'is_shareholder_account,1,true',
                'prohibited_unless:'.$prefix.'is_shareholder_account,1,true',
                Rule::prohibitedIf(! $canFlag),
                Rule::exists(Partner::class, 'id')
                    ->where(function ($query) use ($companyId) {
                        $query->where('company_id', $companyId)
                            ->where('is_shareholder', 1);
                    }),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function shareholderOwnershipMessages(string $prefix = ''): array
    {
        return [
            $prefix.'shareholder_partner_id.required_if' => __('Please select which shareholder owns this account'),
            $prefix.'shareholder_partner_id.exists' => __('The selected shareholder does not exist for this company'),
            $prefix.'is_shareholder_account.prohibited' => __('You are not allowed to manage shareholder accounts'),
            $prefix.'shareholder_partner_id.prohibited' => __('You are not allowed to manage shareholder accounts'),
        ];
    }

    private function shareholderOwnershipCompanyId(): ?int
    {
        $company = $this->route('company');

        if ($company) {
            return (int) (is_object($company) ? $company->id : $company);
        }

        $company = currentCompany();

        return $company ? (int) $company->id : null;
    }
}
