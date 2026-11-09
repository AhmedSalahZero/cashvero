<?php

namespace Tests\Concerns;

use App\Models\AccountType;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionAccount;
use App\Models\ForeignExchangeRate;
use Database\Factories\FinancialInstitutionAccountFactory;
use Database\Factories\FinancialInstitutionFactory;
use Database\Factories\ForeignExchangeRateFactory;

/**
 * Banks, current accounts and exchange rates of a company.
 *
 *   $bank    = $this->bank($company);                         // a bank of that company
 *   $account = $this->currentAccount($bank, 'USD');            // a current account at it
 *   $this->rate($company, 'USD', 'EGP', '2020-01-01', 50);     // 1 USD = 50 EGP from that date
 */
trait MakesBanks
{
    protected function bank(Company $company, array $overrides = []): FinancialInstitution
    {
        return FinancialInstitutionFactory::new()->forCompany($company)->create($overrides);
    }

    protected function currentAccount(FinancialInstitution $bank, string $currency = 'EGP', array $overrides = []): FinancialInstitutionAccount
    {
        return FinancialInstitutionAccountFactory::new()->at($bank)->currency($currency)->create($overrides);
    }

    protected function rate(Company $company, string $from, string $to, string $date, float $rate): ForeignExchangeRate
    {
        return ForeignExchangeRateFactory::new()->create([
            'company_id' => $company->id,
            'from_currency' => strtoupper($from),
            'to_currency' => strtoupper($to),
            'date' => $date,
            'exchange_rate' => $rate,
        ]);
    }

    protected function accountTypeId(string $slug): int
    {
        return (int) AccountType::where('slug', $slug)->value('id');
    }
}
