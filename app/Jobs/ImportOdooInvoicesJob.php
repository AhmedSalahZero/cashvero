<?php

namespace App\Jobs;


use App\Models\Company;
use App\Services\Api\OdooService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ImportOdooInvoicesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        /**
         * Bulk import: per-row history here would be thousands of
         * entries describing one human action ("imported a file"), which
         * buries the events a person actually cares about. The import
         * itself is the auditable event, not each row it created.
         */
        $activityMute = \App\Support\Activity\ActivityLogger::mute();

		$companies = Company::all();
		foreach($companies as $company){
			if($company->hasOdooIntegrationCredentials()){
				$oddo = new OdooService($company);
				$startDate = now()->subDay()->format('Y-m-d') ; ;
				$endDate = now()->subDay()->format('Y-m-d') ; ;
				$oddo->startImportInvoices($startDate,$endDate,$company->id);
			}
		}
    }
}
