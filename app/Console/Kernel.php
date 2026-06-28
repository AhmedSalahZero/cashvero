<?php

namespace App\Console;

use App\Jobs\CurrentAccountBankStatementActiveJob;
use App\Jobs\ImportOdooInvoicesJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;


class Kernel extends ConsoleKernel
{
  
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
		
		// $schedule->job(new CheckDueAndPastedInvoicesJob)->name('check_due_date')->dailyAt('00:01')->withoutOverlapping();
		$schedule->job(new ImportOdooInvoicesJob)->name('import_odd_invoices')->dailyAt('00:01')->withoutOverlapping();
		$schedule->job(new CurrentAccountBankStatementActiveJob)->name('current_account_bank_statement_active')->dailyAt('00:01')->withoutOverlapping();
		
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
