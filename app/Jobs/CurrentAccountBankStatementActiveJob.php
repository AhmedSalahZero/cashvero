<?php

namespace App\Jobs;


use App\Models\Company;
use App\Models\CurrentAccountBankStatement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class CurrentAccountBankStatementActiveJob implements ShouldQueue
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
		$companies = Company::all()  ;
		foreach($companies as $company){
			if(!$company->hasCashVero()){
				continue;
			}
			$firstRaw = CurrentAccountBankStatement::
			where('company_id',$company->id)
			->where('is_active',0)
			->orderByRaw('full_date asc , id asc')
			->where('date','<=',now()->format('Y-m-d'))->first() ;
			if($firstRaw){
				DB::table('current_account_bank_statements')
				->where('company_id',$company->id)
				->where('is_active',0)
				->orderByRaw('full_date asc , id asc')
				->where('date','<=',now()->format('Y-m-d'))
				->update([
					'is_active'=>1 
				]);
				/**
				 * * هنبدا نعمل ابديت من اول الرو اللي تاريخه اصغر حاجه في اللي كانوا محتاجين يتعدلوا
				 * * وبالتالي هيتعدل هو وكل اللي تحتة
				 */
				CurrentAccountBankStatement::updateNextRows($firstRaw);
				
			}
		}
    }
}
