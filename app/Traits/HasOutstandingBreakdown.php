<?php
namespace App\Traits;

use App\Models\CleanOverdraftBankStatement;
use App\Models\Company;
use App\OutstandingBreakdown;
use Carbon\Carbon;
use Illuminate\Http\Request;


trait HasOutstandingBreakdown
{
	/**
	 * * هو عباره عن التقسيمة الخاصة بال 
	 * *clean overdraft 
	 * * outstanding balance
	 * * او اي نوع تاني خاص بالتسهيلات
	 * * بمعني انك لما بتحط ال
	 * * الفلوس اللي انت سحبتها من الحساب لحد لحظه فتح حسابك علي كاش فيرو ..سحبت قديه يوم قديه وقديه يوم قديه وهكذا 
	 * * بمعني ان مجموع القيم لازم يساوي ال
	 * * outstanding balance in clean overdraft 
	 */
	public function outstandingBreakdowns()
	{
		return $this->hasMany(OutstandingBreakdown::class , 'model_id','id')->where('model_type',self::class);	
	}
	public function storeOutstandingBreakdown(Request $request , Company $company)
	{
		$balanceDate = $request->get('balance_date');
		$outstandingBalance = $request->get('outstanding_balance',0);
		$this->outstandingBreakdowns()->delete();
		$this->bankStatements()->where('type','outstanding_balance')->delete();
		if($outstandingBalance >= 0) {
		
			foreach($request->get('outstanding_breakdowns',[]) as $outstandingBreakdownArr){
				$settlementDate = $outstandingBreakdownArr['settlement_date'];
				if(!$settlementDate){
					continue;
				}
				unset($outstandingBreakdownArr['id']);
				$outstandingBreakdownArr['company_id'] = $company->id ;
				$outstandingBreakdownArr['model_type'] = get_class($this);
				$outstandingBreakdownArr['amount'] = number_unformat($outstandingBreakdownArr['amount']);
				$withdrawalDate = Carbon::make($settlementDate)->format('Y-m-d');
				$this->bankStatements()->create([
				'outstanding_withdrawal_date'=> $withdrawalDate , 
				'type'=>'outstanding_balance',
				'money_received_id'=>0 ,
				'company_id'=>$company->id ,
				'date'=>$balanceDate,
				'limit'=>$this->getLimit(),
				'beginning_balance'=>0 ,
				'debit'=>0,
				'credit'=> $outstandingBreakdownArr['amount'],
				'is_credit'=>1 ,
				'is_debit'=>0 
			]);
			
			$this->outstandingBreakdowns()->create($outstandingBreakdownArr);
			
			}
				
		}
		
	}
}
