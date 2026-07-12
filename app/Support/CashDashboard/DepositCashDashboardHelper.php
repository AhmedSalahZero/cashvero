<?php

namespace App\Support\CashDashboard;

use App\Helpers\HArr;
use App\Models\CertificatesOfDeposit;
use App\Models\FinancialInstitution;
use App\Models\TimeOfDeposit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DepositCashDashboardHelper
{
    public static function certificatesForCurrency(
        int $companyId,
        string $currencyName,
        array $bankIds,
        int $cdAccountTypeId,
        callable $bankNameResolver
    ): Collection {
        if ($bankIds === []) {
            return collect();
        }

        $lgLabel = __('LG');
        $overdraftLabel = __('Overdraft');
        $freeLabel = __('Free To Use');

        $rows = DB::table('certificates_of_deposits')
            ->where('certificates_of_deposits.company_id', $companyId)
            ->where('certificates_of_deposits.status', CertificatesOfDeposit::RUNNING)
            ->whereIn('certificates_of_deposits.financial_institution_id', $bankIds)
            ->where('certificates_of_deposits.currency', $currencyName)
            ->leftJoin('fully_secured_overdrafts', function ($query) use ($cdAccountTypeId) {
                $query->on('fully_secured_overdrafts.cd_or_td_account_id', '=', 'certificates_of_deposits.id')
                    ->where('fully_secured_overdrafts.cd_or_td_account_type_id', $cdAccountTypeId);
            })
            ->leftJoin('letter_of_guarantee_issuances', function ($query) use ($cdAccountTypeId) {
                $query->on('letter_of_guarantee_issuances.cd_or_td_id', '=', 'certificates_of_deposits.id')
                    ->where('letter_of_guarantee_issuances.cd_or_td_account_type_id', $cdAccountTypeId)
                    ->where('letter_of_guarantee_issuances.status', 'running');
            })
            ->orderBy('certificates_of_deposits.end_date', 'desc')
            ->select([
                'certificates_of_deposits.financial_institution_id',
                'certificates_of_deposits.account_number',
                'certificates_of_deposits.amount',
                DB::raw(
                    "CASE
                        WHEN letter_of_guarantee_issuances.cash_cover_deducted_from_account_type = {$cdAccountTypeId} THEN '{$lgLabel}'
                        WHEN letter_of_guarantee_issuances.cd_or_td_account_type_id = {$cdAccountTypeId} THEN '{$lgLabel}'
                        WHEN fully_secured_overdrafts.cd_or_td_account_type_id = {$cdAccountTypeId} THEN '{$overdraftLabel}'
                        ELSE '{$freeLabel}'
                    END as blocked"
                ),
            ])
            ->get()
            ->map(function ($row) use ($bankNameResolver) {
                $row->financial_institution_name = $bankNameResolver((int) $row->financial_institution_id);

                return $row;
            });

        return collect(HArr::filterByUnique($rows->toArray(), ['financial_institution_name', 'account_number', 'blocked']));
    }

    public static function timeDepositsForCurrency(
        int $companyId,
        string $currencyName,
        array $bankIds,
        int $tdAccountTypeId,
        callable $bankNameResolver
    ): Collection {
        if ($bankIds === []) {
            return collect();
        }

        $lgLabel = __('LG');
        $overdraftLabel = __('Overdraft');
        $freeLabel = __('Free To Use');

        $rows = DB::table('time_of_deposits')
            ->where('time_of_deposits.company_id', $companyId)
            ->where('time_of_deposits.status', TimeOfDeposit::RUNNING)
            ->whereIn('time_of_deposits.financial_institution_id', $bankIds)
            ->where('time_of_deposits.currency', $currencyName)
            ->leftJoin('fully_secured_overdrafts', function ($query) use ($tdAccountTypeId) {
                $query->on('fully_secured_overdrafts.cd_or_td_account_id', '=', 'time_of_deposits.id')
                    ->where('fully_secured_overdrafts.cd_or_td_account_type_id', $tdAccountTypeId);
            })
            ->leftJoin('letter_of_guarantee_issuances as lg_cd', function ($query) use ($tdAccountTypeId) {
                $query->on('lg_cd.cd_or_td_id', '=', 'time_of_deposits.id')
                    ->where('lg_cd.cd_or_td_account_type_id', $tdAccountTypeId)
                    ->where('lg_cd.status', 'running');
            })
            ->leftJoin('letter_of_guarantee_issuances as lg_cash', function ($query) use ($tdAccountTypeId) {
                $query->on('lg_cash.cash_cover_deducted_from_account_id', '=', 'time_of_deposits.id')
                    ->where('lg_cash.cash_cover_deducted_from_account_type', $tdAccountTypeId)
                    ->where('lg_cash.status', 'running');
            })
            ->orderBy('time_of_deposits.end_date', 'desc')
            ->select([
                'time_of_deposits.financial_institution_id',
                'time_of_deposits.account_number',
                'time_of_deposits.amount',
                DB::raw(
                    "CASE
                        WHEN lg_cash.cash_cover_deducted_from_account_type = {$tdAccountTypeId} THEN '{$lgLabel}'
                        WHEN lg_cd.cd_or_td_account_type_id = {$tdAccountTypeId} THEN '{$lgLabel}'
                        WHEN fully_secured_overdrafts.cd_or_td_account_type_id = {$tdAccountTypeId} THEN '{$overdraftLabel}'
                        ELSE '{$freeLabel}'
                    END as blocked"
                ),
            ])
            ->get()
            ->map(function ($row) use ($bankNameResolver) {
                $row->financial_institution_name = $bankNameResolver((int) $row->financial_institution_id);

                return $row;
            });

        return collect(HArr::filterByUnique($rows->toArray(), ['financial_institution_name', 'account_number', 'blocked']));
    }
}
