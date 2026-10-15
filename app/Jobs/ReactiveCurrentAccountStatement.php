<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\CurrentAccountBankStatement;
use App\Models\ForeignExchangeRate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReactiveCurrentAccountStatement implements ShouldQueue
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
		$inActiveCurrentAccountBankStatements = CurrentAccountBankStatement::withoutGlobalScope('only_active')->where('is_active',0)->where('date','<=',now()->format('Y-m-d'))->where('company_id',$this->company_id)->orderByRaw('date asc , id asc')->get();
		foreach($inActiveCurrentAccountBankStatements as $inActiveCurrentAccountBankStatement){
			$inActiveCurrentAccountBankStatement->update([
				'is_active'=>1 
			]);
		}
		
		
    }
	
}
