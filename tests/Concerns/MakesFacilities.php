<?php

namespace Tests\Concerns;

use App\Models\CleanOverdraft;
use App\Models\FinancialInstitution;
use App\Models\LoanSchedule;
use App\Models\MediumTermLoan;
use App\Models\OverdraftAgainstAssignmentOfContract;
use Database\Factories\CleanOverdraftFactory;
use Database\Factories\LoanScheduleFactory;
use Database\Factories\MediumTermLoanFactory;
use Database\Factories\OverdraftAgainstAssignmentOfContractFactory;

/**
 * Credit facilities at a company's bank (use together with MakesBanks).
 *
 *   $overdraft = $this->cleanOverdraft($bank, ['limit' => 400000]);
 *   $odac      = $this->overdraftAgainstAssignmentOfContract($bank);
 *   $loan      = $this->mediumTermLoan($bank, ['duration' => 6]);
 *   $schedule  = $this->loanSchedule($loan, '2020-01-31', ['schedule_payment' => 100000]);
 *
 * Overdrafts are saved without the model's events (they would store a rate read from the
 * current request); CleanOverdraftFactory::new()->at($bank)->withRate(36.5)->create() adds one.
 */
trait MakesFacilities
{
    protected function cleanOverdraft(FinancialInstitution $bank, array $overrides = []): CleanOverdraft
    {
        return CleanOverdraftFactory::new()->at($bank)->create($overrides);
    }

    protected function overdraftAgainstAssignmentOfContract(FinancialInstitution $bank, array $overrides = []): OverdraftAgainstAssignmentOfContract
    {
        return OverdraftAgainstAssignmentOfContractFactory::new()->at($bank)->create($overrides);
    }

    protected function mediumTermLoan(FinancialInstitution $bank, array $overrides = []): MediumTermLoan
    {
        return MediumTermLoanFactory::new()->at($bank)->create($overrides);
    }

    protected function loanSchedule(MediumTermLoan $loan, string $date, array $overrides = []): LoanSchedule
    {
        return LoanScheduleFactory::new()->of($loan)->create(['date' => $date] + $overrides);
    }
}
