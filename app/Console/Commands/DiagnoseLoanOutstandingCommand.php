<?php

namespace App\Console\Commands;

use App\Models\LeasingContract;
use App\Models\MediumTermLoan;
use Illuminate\Console\Command;

/**
 * DiagnoseLoanOutstandingCommand
 * ------------------------------------------------------------------
 * Read-only. Shows exactly why a Medium Term Loan or Leasing Contract's
 * dashboard "Paid" figure isn't zero even though nothing has been paid.
 *
 * The dashboard's outstanding-principal formula
 * (min(principle_amount, remaining), summed) only nets Paid to 0 on an
 * untouched loan if the schedule's own principal amounts sum to exactly
 * the loan/lease's Limit. This prints every installment row so that
 * invariant can be checked against real numbers instead of guessed at.
 *
 * USAGE
 *   php artisan loans:diagnose-outstanding --mtl=123
 *   php artisan loans:diagnose-outstanding --leasing=456
 */
class DiagnoseLoanOutstandingCommand extends Command
{
    protected $signature = 'loans:diagnose-outstanding
        {--mtl= : Medium Term Loan id}
        {--leasing= : Leasing Contract id}';

    protected $description = 'Print every installment on a loan/lease next to its Limit, to find why Paid is nonzero on an unpaid loan';

    public function handle(): int
    {
        $mtlId = $this->option('mtl');
        $leasingId = $this->option('leasing');

        if (! $mtlId && ! $leasingId) {
            $this->error('Pass --mtl=<id> or --leasing=<id>.');

            return self::FAILURE;
        }

        if ($mtlId) {
            $this->diagnoseMtl((int) $mtlId);
        }

        if ($leasingId) {
            $this->diagnoseLeasing((int) $leasingId);
        }

        return self::SUCCESS;
    }

    private function diagnoseMtl(int $id): void
    {
        $loan = MediumTermLoan::with('loanSchedules')->find($id);
        if (! $loan) {
            $this->error("MediumTermLoan #{$id} not found.");

            return;
        }

        $this->info("Medium Term Loan #{$id} — {$loan->getName()}  (currency: {$loan->currency})");
        $this->line('Limit: '.number_format($loan->getLimit()));
        $this->line('');

        $rows = [];
        $sumPrinciple = 0;
        $sumOutstandingPrinciple = 0;
        foreach ($loan->loanSchedules as $schedule) {
            $principle = (float) $schedule->getPrincipleAmount();
            $remaining = (float) $schedule->getRemaining();
            $outstandingPrinciple = min($principle, $remaining);
            $sumPrinciple += $principle;
            $sumOutstandingPrinciple += $outstandingPrinciple;

            $rows[] = [
                $schedule->id,
                $schedule->date,
                number_format($schedule->getSchedulePayment()),
                number_format($principle),
                number_format($schedule->interest_amount ?: 0),
                number_format($remaining),
                number_format($outstandingPrinciple),
            ];
        }

        $this->table(
            ['id', 'date', 'cheque_amount', 'principle_amount', 'interest_amount', 'remaining', 'min(principle,remaining)'],
            $rows
        );

        $this->line('');
        $this->line('Sum of principle_amount across all rows: '.number_format($sumPrinciple));
        $this->line('Sum of min(principle,remaining) (= Outstanding shown on dashboard): '.number_format($sumOutstandingPrinciple));
        $this->line('Limit: '.number_format($loan->getLimit()));
        $this->line('Limit - Outstanding (= Paid shown on dashboard): '.number_format($loan->getLimit() - $sumOutstandingPrinciple));
        $this->line('');
        $gap = $loan->getLimit() - $sumPrinciple;
        if (abs($gap) > 0.01) {
            $this->warn('Sum of principle_amount does NOT equal Limit. Gap: '.number_format($gap));
            $this->warn('This gap is why "Paid" is nonzero even though nothing has been paid — it is a data mismatch between the schedule and the stated Limit, not a dashboard formula issue.');
        } else {
            $this->info('Sum of principle_amount matches Limit — no data mismatch found here.');
        }
    }

    private function diagnoseLeasing(int $id): void
    {
        $contract = LeasingContract::with('contractLoanSchedules')->find($id);
        if (! $contract) {
            $this->error("LeasingContract #{$id} not found.");

            return;
        }

        $this->info("Leasing Contract #{$id} — {$contract->getName()}  (currency: {$contract->currency})");
        $this->line('Limit: '.number_format($contract->getLimit()));
        $this->line('');

        $rows = [];
        $sumPrinciple = 0;
        $sumOutstandingPrinciple = 0;
        foreach ($contract->contractLoanSchedules as $schedule) {
            $principle = (float) $schedule->getPrincipleAmount();
            $remaining = (float) $schedule->getRemaining();
            $outstandingPrinciple = min($principle, $remaining);
            $sumPrinciple += $principle;
            $sumOutstandingPrinciple += $outstandingPrinciple;

            $rows[] = [
                $schedule->id,
                $schedule->date,
                number_format($schedule->getChequeAmount()),
                number_format($principle),
                number_format($schedule->interest_amount ?: 0),
                number_format($remaining),
                number_format($outstandingPrinciple),
            ];
        }

        $this->table(
            ['id', 'date', 'cheque_amount', 'principle_amount', 'interest_amount', 'remaining', 'min(principle,remaining)'],
            $rows
        );

        $this->line('');
        $this->line('Sum of principle_amount across all rows: '.number_format($sumPrinciple));
        $this->line('Sum of min(principle,remaining) (= Outstanding shown on dashboard): '.number_format($sumOutstandingPrinciple));
        $this->line('Limit: '.number_format($contract->getLimit()));
        $this->line('Limit - Outstanding (= Paid shown on dashboard): '.number_format($contract->getLimit() - $sumOutstandingPrinciple));
        $this->line('');
        $gap = $contract->getLimit() - $sumPrinciple;
        if (abs($gap) > 0.01) {
            $this->warn('Sum of principle_amount does NOT equal Limit. Gap: '.number_format($gap));
            $this->warn('This gap is why "Paid" is nonzero even though nothing has been paid — it is a data mismatch between the schedule and the stated Limit, not a dashboard formula issue.');
        } else {
            $this->info('Sum of principle_amount matches Limit — no data mismatch found here.');
        }
    }
}
