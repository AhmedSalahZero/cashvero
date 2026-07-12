<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class LockableAccountSelector
{
    public static function getAccountNumbers(
        Builder $query,
        int $companyId,
        string $currencyName,
        int $financialInstitutionId,
        string $keyName = 'account_number',
        bool $onlyActiveAccounts = true,
        ?string $modelClass = null
    ): array {
        if ($onlyActiveAccounts) {
            $query->where('is_active', 1);
        }

        $accounts = $query->pluck('account_number', $keyName)->toArray();

        if ($onlyActiveAccounts && $modelClass) {
            $accounts = self::mergeSelectedLockedAccount(
                $accounts,
                $modelClass,
                $companyId,
                $currencyName,
                $financialInstitutionId,
                $keyName
            );
        }

        return $accounts;
    }

    public static function mergeSelectedLockedAccount(
        array $accounts,
        string $modelClass,
        int $companyId,
        string $currencyName,
        int $financialInstitutionId,
        string $keyName = 'account_number'
    ): array {
        $selectedAccountNumber = request()->get('selected_account_number');
        $selectedAccountId = request()->get('selected_account_id');

        if (! $selectedAccountNumber && ! $selectedAccountId) {
            return $accounts;
        }

        $lockedQuery = $modelClass::query()
            ->where('company_id', $companyId)
            ->where('financial_institution_id', $financialInstitutionId)
            ->where('currency', $currencyName)
            ->where('is_active', 0);

        if ($selectedAccountNumber) {
            $lockedQuery->where('account_number', $selectedAccountNumber);
        } else {
            $lockedQuery->where('id', $selectedAccountId);
        }

        $lockedAccount = $lockedQuery->first();

        if ($lockedAccount) {
            $key = $keyName === 'id' ? $lockedAccount->id : $lockedAccount->account_number;
            if (! array_key_exists($key, $accounts)) {
                $accounts[$key] = $lockedAccount->account_number;
            }
        }

        return $accounts;
    }
}
