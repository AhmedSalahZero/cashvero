<?php

namespace App\Traits\Models;

use App\Models\Partner;
use App\Support\ShareholderAccounts\AccountOwnerFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Shared "who owns this account" behaviour for the 4 instrument types that
 * can belong to a shareholder personally instead of to the company:
 * FinancialInstitutionAccount, TimeOfDeposit, CertificatesOfDeposit and
 * MediumTermLoan.
 *
 * See docs/shareholder-accounts.md for the decisions this implements.
 * The flag means one thing only — who owns the record — and every instrument
 * is filtered by it the same way (D1), a loan no differently from a current
 * account. Nothing in this trait applies a filter by itself; callers decide.
 *
 * The two columns live on each using model's table; declared here so static
 * analysis sees them without every model's generated docblock having to be
 * regenerated.
 *
 * @property bool $is_shareholder_account
 * @property int|null $shareholder_partner_id
 * @property-read \App\Models\Partner|null $shareholderPartner
 */
trait HasShareholderOwnership
{
    public function shareholderPartner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'shareholder_partner_id', 'id');
    }

    public function isShareholderAccount(): bool
    {
        return (bool) $this->is_shareholder_account && $this->shareholder_partner_id;
    }

    /**
     * Owner's name, or null for a company account. Safe on a record whose
     * partner row was deleted out from under it.
     */
    public function getShareholderName(): ?string
    {
        if (! $this->isShareholderAccount()) {
            return null;
        }

        return optional($this->shareholderPartner)->getName();
    }

    /** Company-owned records only. */
    public function scopeOnlyCompanyOwned(Builder $query): Builder
    {
        return $query->where(function (Builder $builder) {
            $builder->where($this->getTable().'.is_shareholder_account', 0)
                ->orWhereNull($this->getTable().'.shareholder_partner_id');
        });
    }

    /** Shareholder-owned records only, optionally one specific shareholder. */
    public function scopeOnlyShareholderOwned(Builder $query, ?int $shareholderPartnerId = null): Builder
    {
        $query->where($this->getTable().'.is_shareholder_account', 1)
            ->whereNotNull($this->getTable().'.shareholder_partner_id');

        if ($shareholderPartnerId) {
            $query->where($this->getTable().'.shareholder_partner_id', $shareholderPartnerId);
        }

        return $query;
    }

    /**
     * Apply an AccountOwnerFilter to an Eloquent query. `All accounts` adds
     * no constraint at all.
     */
    public function scopeOwnedAccordingTo(Builder $query, AccountOwnerFilter $filter): Builder
    {
        return $filter->applyToEloquent($query, $this->getTable());
    }

    /**
     * Suffix each label in an account-number dropdown with the owning
     * shareholder's name — decision D7.
     *
     * The KEYS are never touched. Most tables persist `account_number` as a
     * plain string (alongside financial_institution_id / account_type_id)
     * rather than a foreign key, so changing the stored value would break
     * every existing row; only what the user reads changes.
     *
     * @param  array<string|int, string>  $accounts  key => label, as built by getAllAccountNumberForCurrency()
     * @param  string  $keyName  the column the array is keyed by ('account_number' or 'id')
     * @return array<string|int, string>
     */
    public static function decorateAccountNumbersWithShareholderNames(
        array $accounts,
        string $keyName,
        int $companyId,
        ?int $financialInstitutionId = null
    ): array {
        if ($accounts === []) {
            return $accounts;
        }

        $table = static::query()->getModel()->getTable();

        $query = DB::table($table)
            ->join('partners', 'partners.id', '=', $table.'.shareholder_partner_id')
            ->where($table.'.company_id', $companyId)
            ->where($table.'.is_shareholder_account', 1)
            ->whereIn($table.'.'.$keyName, array_keys($accounts));

        if ($financialInstitutionId) {
            $query->where($table.'.financial_institution_id', $financialInstitutionId);
        }

        $namesByKey = $query
            ->pluck('partners.name', $table.'.'.$keyName)
            ->all();

        if ($namesByKey === []) {
            return $accounts;
        }

        foreach ($accounts as $key => $label) {
            $shareholderName = $namesByKey[$key] ?? null;
            if ($shareholderName) {
                $accounts[$key] = $label.' — '.$shareholderName;
            }
        }

        return $accounts;
    }
}
