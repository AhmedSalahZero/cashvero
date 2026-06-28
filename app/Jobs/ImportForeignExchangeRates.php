<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\ForeignExchangeRate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ImportForeignExchangeRates implements ShouldQueue
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
	protected $company_id ;
    public function __construct(int $companyId)
    {
		$this->company_id = $companyId ; 
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
		$company = Company::find($this->company_id);
		try{
			ForeignExchangeRate::importOdooExchangeRates($company);
		}catch(\Exception $e){
			session()->put('fail',__('Can Not Connect To Odoo'));
		}
    }
	
}
