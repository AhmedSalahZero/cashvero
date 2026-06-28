<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OverdraftInterestCalculation
{
    public function recalculateEndOfMonthCleanOverdraftInterests(int $year, int $month)
    {
        $interestTypeText = 'interest';
        $highestDebitBalanceText = 'highest_debit_balance';
        $highestDebtBalanceRate = 0;

        // Validate input
        if ($month < 1 || $month > 12 || $year < 2000 || $year > Carbon::now()->year) {
            throw new \InvalidArgumentException('Invalid month or year provided');
        }

        // Delete existing calculations for the specified month and year
        DB::table('clean_overdraft_bank_statements')
            ->whereIn('type', [$interestTypeText, $highestDebitBalanceText])
            ->whereRaw('EXTRACT(MONTH FROM date) = ?', [$month])
            ->whereRaw('EXTRACT(YEAR FROM date) = ?', [$year])
            ->delete();

        // Get count of distinct clean overdraft IDs for specified month
        $n = DB::table('clean_overdraft_bank_statements')
            ->where('type', '!=', $interestTypeText)
            ->where('type', '!=', $highestDebitBalanceText)
            ->whereRaw('EXTRACT(MONTH FROM date) = ?', [$month])
            ->whereRaw('EXTRACT(YEAR FROM date) = ?', [$year])
            ->groupBy('clean_overdraft_id')
            ->count();

        if ($n > 0) {
            for ($i = 0; $i < $n; $i++) {
                // Get overdraft details
                $overdraft = DB::table('clean_overdraft_bank_statements')
                    ->select(
                        'clean_overdraft_id',
                        DB::raw('SUM(interest_amount) as current_interest_amount'),
                        DB::raw('MIN(end_balance) as largest_end_balance')
                    )
                    ->where('type', '!=', $interestTypeText)
                    ->where('type', '!=', $highestDebitBalanceText)
                    ->whereRaw('EXTRACT(MONTH FROM date) = ?', [$month])
                    ->whereRaw('EXTRACT(YEAR FROM date) = ?', [$year])
                    ->groupBy('clean_overdraft_id')
                    ->skip($i)
                    ->take(1)
                    ->first();

                $cleanOverdraftId = $overdraft->clean_overdraft_id ?? 0;
                $currentInterestAmount = $overdraft->current_interest_amount ?? 0;
                $largestEndBalance = $overdraft->largest_end_balance ?? 0;

                // Get company details
                $company = DB::table('clean_overdrafts')
                    ->select('company_id', 'limit', 'highest_debt_balance_rate')
                    ->where('id', $cleanOverdraftId)
                    ->first();

                $companyId = $company->company_id ?? 0;
                $limit = $company->limit ?? 0;
                $highestDebtBalanceRate = $company->highest_debt_balance_rate ?? 0;

                // Calculate interest
                $currentInterestAmount = ($highestDebtBalanceRate / 100) * ($largestEndBalance * -1);

                // Create date for the last day of specified month
                $calculationDate = Carbon::create($year, $month, 1)->endOfMonth();

                // Insert highest debit balance record
                DB::table('clean_overdraft_bank_statements')->insert([
                    'type' => $highestDebitBalanceText,
                    'priority' => 1,
                    'clean_overdraft_id' => $cleanOverdraftId,
                    'money_received_id' => 0,
                    'company_id' => $companyId,
                    'date' => $calculationDate,
                    'limit' => $limit,
                    'credit' => $currentInterestAmount,
                    'interest_type' => 'end_of_month',
                    'full_date' => now(),
                ]);

                // Insert interest record
                DB::table('clean_overdraft_bank_statements')->insert([
                    'type' => $interestTypeText,
                    'priority' => 1,
                    'clean_overdraft_id' => $cleanOverdraftId,
                    'money_received_id' => 0,
                    'company_id' => $companyId,
                    'date' => $calculationDate,
                    'limit' => $limit,
                    'credit' => $currentInterestAmount,
                    'interest_type' => 'end_of_month',
                    'full_date' => now(),
                ]);
            }
        }
    }
}
